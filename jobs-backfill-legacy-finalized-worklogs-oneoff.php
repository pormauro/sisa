<?php

declare(strict_types=1);

bootstrapAutoload();
bootstrapLoadEnv();

const LEGACY_BACKFILL_SOURCE_DEVICE_ID = 'system-oneoff-migration';
const LEGACY_BACKFILL_WORK_TYPE = 'legacy_job_schedule_backfill';
const LEGACY_BACKFILL_IDEMPOTENCY_PREFIX = 'legacy-job-schedule-backfill-';

try {
    main($argv);
} catch (Throwable $exception) {
    fwrite(STDERR, '[fatal] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function bootstrapLoadEnv(): void
{
    $candidates = [
        __DIR__ . '/../load_env.php',
        __DIR__ . '/load_env.php',
        dirname(__DIR__) . '/load_env.php',
        dirname(__DIR__, 2) . '/scripts/load_env.php',
    ];

    foreach ($candidates as $candidate) {
        if (!is_file($candidate)) {
            continue;
        }

        require_once $candidate;
        return;
    }

    throw new RuntimeException('Unable to locate load_env.php. Run the script from the project structure or place it under sisa.api/scripts/migrations/.');
}

function main(array $argv): void
{
    $dryRun = in_array('--dry-run', $argv, true);

    loadProjectEnvironment(resolveProjectBaseDir());

    $pdo = connectPdo();

    $jobs = fetchCandidateJobs($pdo);
    $summary = [
        'eligible_jobs' => count($jobs),
        'created_worklogs' => 0,
        'existing_worklogs' => 0,
        'created_participants' => 0,
        'jobs_without_owner' => [],
        'jobs_invalid_schedule' => [],
        'jobs_failed' => [],
    ];

    println('Legacy finalized jobs backfill');
    println('Mode: ' . ($dryRun ? 'dry-run' : 'execute'));
    println('Eligible jobs: ' . (string) $summary['eligible_jobs']);

    foreach ($jobs as $job) {
        $jobId = (int) $job['id'];
        $schedule = buildLegacySchedule($job);
        if ($schedule === null) {
            $summary['jobs_invalid_schedule'][] = $jobId;
            println("Skipping job #{$jobId}: invalid schedule (end_time must be greater than start_time).");
            continue;
        }

        $participantIds = parseLegacyParticipantIds($job['participants'] ?? null);
        $ownerUserId = resolveOwnerUserId($job, $participantIds);
        if ($ownerUserId === null) {
            $summary['jobs_without_owner'][] = $jobId;
            println("Skipping job #{$jobId}: no valid owner user found.");
            continue;
        }

        $idempotencyKey = LEGACY_BACKFILL_IDEMPOTENCY_PREFIX . $jobId;
        $existingWorkLogId = findExistingWorkLogId($pdo, (int) $job['company_id'], $idempotencyKey);
        if ($existingWorkLogId !== null) {
            $summary['existing_worklogs']++;
            println("Skipping job #{$jobId}: worklog already exists (#{$existingWorkLogId}).");
            continue;
        }

        if ($dryRun) {
            $summary['created_worklogs']++;
            $summary['created_participants'] += count($participantIds);
            println("[dry-run] Job #{$jobId}: would create worklog with " . count($participantIds) . ' participants.');
            continue;
        }

        try {
            $pdo->beginTransaction();

            $workLogId = insertWorkLog($pdo, $job, $schedule, $ownerUserId, $idempotencyKey);
            $createdParticipants = insertWorkLogParticipants($pdo, $job, $workLogId, $participantIds);

            $pdo->commit();

            $summary['created_worklogs']++;
            $summary['created_participants'] += $createdParticipants;
            println("Job #{$jobId}: created worklog #{$workLogId} with {$createdParticipants} participants.");
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $summary['jobs_failed'][$jobId] = $exception->getMessage();
            println("Failed job #{$jobId}: {$exception->getMessage()}");
        }
    }

    printSummary($summary);
}

function bootstrapAutoload(): void
{
    $candidates = [
        dirname(__DIR__, 2) . '/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        getcwd() !== false ? rtrim((string) getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vendor/autoload.php' : null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || !is_file($candidate)) {
            continue;
        }

        require_once $candidate;
        return;
    }
}

function resolveProjectBaseDir(): string
{
    $candidates = [
        dirname(__DIR__, 2),
        dirname(__DIR__),
        getcwd() !== false ? getcwd() : __DIR__,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '') {
            continue;
        }

        if (is_file(rtrim($candidate, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env')) {
            return rtrim($candidate, DIRECTORY_SEPARATOR);
        }
    }

    return dirname(__DIR__, 2);
}

function loadProjectEnvironment(string $baseDir): void
{
    if (function_exists('loadProjectEnv')) {
        try {
            loadProjectEnv($baseDir);
            return;
        } catch (Throwable $exception) {
            if (!classExistsForEnvLoaderFailure($exception)) {
                throw $exception;
            }
        }
    }

    loadEnvFileFallback($baseDir . DIRECTORY_SEPARATOR . '.env');
}

function classExistsForEnvLoaderFailure(Throwable $exception): bool
{
    $message = $exception->getMessage();

    return str_contains($message, 'Dotenv\\Dotenv') || str_contains($message, 'phpdotenv');
}

function loadEnvFileFallback(string $envFile): void
{
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (str_starts_with($trimmed, 'export ')) {
            $trimmed = trim(substr($trimmed, 7));
        }

        $separatorPos = strpos($trimmed, '=');
        if ($separatorPos === false) {
            continue;
        }

        $name = trim(substr($trimmed, 0, $separatorPos));
        if ($name === '') {
            continue;
        }

        $value = trim(substr($trimmed, $separatorPos + 1));
        $value = trimEnvValue($value);

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function trimEnvValue(string $value): string
{
    $length = strlen($value);
    if ($length >= 2) {
        $first = $value[0];
        $last = $value[$length - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
        }
    }

    return str_replace(['\\n', '\\r'], ["\n", "\r"], $value);
}

function connectPdo(): PDO
{
    $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbName = $_ENV['DB_NAME'] ?? '';
    $dbUser = $_ENV['DB_USER'] ?? 'root';
    $dbPass = $_ENV['DB_PASS'] ?? '';

    if (!is_string($dbName) || trim($dbName) === '') {
        throw new RuntimeException('DB_NAME is required to run the one-off backfill.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);

    try {
        $pdo = new PDO($dsn, (string) $dbUser, (string) $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $exception) {
        throw new RuntimeException('Unable to connect to the database: ' . $exception->getMessage(), 0, $exception);
    }

    return $pdo;
}

function fetchCandidateJobs(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
    id,
    uuid,
    company_id,
    description,
    participants,
    tariff_id,
    manual_amount,
    source_device_id,
    created_by,
    updated_by,
    created_at,
    updated_at,
    job_date,
    start_time,
    end_time,
    deleted_at
FROM jobs
WHERE status_id = 8
  AND deleted_at IS NULL
  AND job_date IS NOT NULL
  AND start_time IS NOT NULL
  AND end_time IS NOT NULL
  AND start_time <> '00:00:00'
  AND end_time <> '00:00:00'
ORDER BY id ASC
SQL;

    return $pdo->query($sql)->fetchAll();
}

function buildLegacySchedule(array $job): ?array
{
    $jobDate = trim((string) ($job['job_date'] ?? ''));
    $startTime = trim((string) ($job['start_time'] ?? ''));
    $endTime = trim((string) ($job['end_time'] ?? ''));

    if ($jobDate === '' || $startTime === '' || $endTime === '') {
        return null;
    }

    $startedAt = $jobDate . ' ' . $startTime;
    $endedAt = $jobDate . ' ' . $endTime;
    $startedAtTs = strtotime($startedAt);
    $endedAtTs = strtotime($endedAt);

    if ($startedAtTs === false || $endedAtTs === false || $endedAtTs <= $startedAtTs) {
        return null;
    }

    $durationMinutes = (int) floor(($endedAtTs - $startedAtTs) / 60);
    if ($durationMinutes <= 0) {
        return null;
    }

    return [
        'started_at' => date('Y-m-d H:i:s', $startedAtTs),
        'ended_at' => date('Y-m-d H:i:s', $endedAtTs),
        'duration_minutes' => $durationMinutes,
    ];
}

function parseLegacyParticipantIds($rawParticipants): array
{
    if ($rawParticipants === null || $rawParticipants === '') {
        return [];
    }

    $value = $rawParticipants;
    for ($depth = 0; $depth < 2; $depth++) {
        if (!is_string($value)) {
            break;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            break;
        }

        $value = $decoded;
    }

    if (is_object($value)) {
        $value = (array) $value;
    }

    if (!is_array($value)) {
        return [];
    }

    $participantIds = [];
    foreach ($value as $participant) {
        $candidate = null;

        if ((is_int($participant) || is_string($participant)) && ctype_digit((string) $participant)) {
            $candidate = (int) $participant;
        } elseif (is_array($participant)) {
            foreach (['user_id', 'id', 'value'] as $field) {
                if (isset($participant[$field]) && (is_int($participant[$field]) || ctype_digit((string) $participant[$field]))) {
                    $candidate = (int) $participant[$field];
                    break;
                }
            }
        }

        if ($candidate !== null && $candidate > 0) {
            $participantIds[$candidate] = $candidate;
        }
    }

    return array_values($participantIds);
}

function resolveOwnerUserId(array $job, array $participantIds): ?int
{
    foreach (['updated_by', 'created_by'] as $field) {
        $candidate = isset($job[$field]) ? (int) $job[$field] : 0;
        if ($candidate > 0) {
            return $candidate;
        }
    }

    return $participantIds[0] ?? null;
}

function findExistingWorkLogId(PDO $pdo, int $companyId, string $idempotencyKey): ?int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM work_logs WHERE company_id = :company_id AND idempotency_key = :idempotency_key LIMIT 1'
    );
    $stmt->execute([
        ':company_id' => $companyId,
        ':idempotency_key' => $idempotencyKey,
    ]);

    $value = $stmt->fetchColumn();

    return $value !== false ? (int) $value : null;
}

function insertWorkLog(PDO $pdo, array $job, array $schedule, int $ownerUserId, string $idempotencyKey): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO work_logs (
            uuid,
            company_id,
            job_id,
            job_item_id,
            user_id,
            work_type,
            started_at,
            ended_at,
            duration_minutes,
            title,
            description,
            billable_flag,
            client_visible_flag,
            idempotency_key,
            version,
            source_device_id,
            created_by,
            updated_by,
            created_at,
            updated_at
        ) VALUES (
            :uuid,
            :company_id,
            :job_id,
            NULL,
            :user_id,
            :work_type,
            :started_at,
            :ended_at,
            :duration_minutes,
            :title,
            :description,
            NULL,
            0,
            :idempotency_key,
            1,
            :source_device_id,
            :created_by,
            :updated_by,
            :created_at,
            :updated_at
        )'
    );

    $description = buildLegacyWorkLogDescription($job);
    $title = buildWorkLogTitle($job);
    $createdBy = normalizeNullablePositiveInt($job['created_by'] ?? null) ?? $ownerUserId;
    $updatedBy = normalizeNullablePositiveInt($job['updated_by'] ?? null) ?? $createdBy;
    $sourceDeviceId = trim((string) ($job['source_device_id'] ?? ''));

    $stmt->execute([
        ':uuid' => generateUuidV4(),
        ':company_id' => (int) $job['company_id'],
        ':job_id' => (int) $job['id'],
        ':user_id' => $ownerUserId,
        ':work_type' => LEGACY_BACKFILL_WORK_TYPE,
        ':started_at' => $schedule['started_at'],
        ':ended_at' => $schedule['ended_at'],
        ':duration_minutes' => $schedule['duration_minutes'],
        ':title' => $title,
        ':description' => $description,
        ':idempotency_key' => $idempotencyKey,
        ':source_device_id' => $sourceDeviceId !== '' ? substr($sourceDeviceId, 0, 191) : LEGACY_BACKFILL_SOURCE_DEVICE_ID,
        ':created_by' => $createdBy,
        ':updated_by' => $updatedBy,
        ':created_at' => normalizeDateTimeOrFallback($job['created_at'] ?? null),
        ':updated_at' => normalizeDateTimeOrFallback($job['updated_at'] ?? null),
    ]);

    return (int) $pdo->lastInsertId();
}

function buildWorkLogTitle(array $job): ?string
{
    $description = trim((string) ($job['description'] ?? ''));
    if ($description === '') {
        return 'Backfill trabajo legacy #' . (int) $job['id'];
    }

    return mb_substr($description, 0, 255);
}

function buildLegacyWorkLogDescription(array $job): string
{
    $description = trim((string) ($job['description'] ?? ''));
    $legacyLines = [
        '[legacy_job_fields]',
        'tariff_id: ' . formatLegacyScalar($job['tariff_id'] ?? null),
        'manual_amount: ' . formatLegacyScalar($job['manual_amount'] ?? null),
        'job_date: ' . formatLegacyScalar($job['job_date'] ?? null),
        'start_time: ' . formatLegacyScalar($job['start_time'] ?? null),
        'end_time: ' . formatLegacyScalar($job['end_time'] ?? null),
        '[/legacy_job_fields]',
    ];

    return $description !== ''
        ? $description . "\n\n" . implode("\n", $legacyLines)
        : implode("\n", $legacyLines);
}

function insertWorkLogParticipants(PDO $pdo, array $job, int $workLogId, array $participantIds): int
{
    if ($participantIds === []) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO work_log_participants (
            uuid,
            company_id,
            work_log_id,
            user_id,
            version,
            source_device_id,
            created_by,
            updated_by,
            created_at,
            updated_at
        ) VALUES (
            :uuid,
            :company_id,
            :work_log_id,
            :user_id,
            1,
            :source_device_id,
            :created_by,
            :updated_by,
            :created_at,
            :updated_at
        )'
    );

    $createdAt = normalizeDateTimeOrFallback($job['created_at'] ?? null);
    $updatedAt = normalizeDateTimeOrFallback($job['updated_at'] ?? null);
    $createdBy = normalizeNullablePositiveInt($job['created_by'] ?? null);
    $updatedBy = normalizeNullablePositiveInt($job['updated_by'] ?? null) ?? $createdBy;
    $sourceDeviceId = trim((string) ($job['source_device_id'] ?? ''));
    $inserted = 0;

    foreach ($participantIds as $participantUserId) {
        $stmt->execute([
            ':uuid' => generateUuidV4(),
            ':company_id' => (int) $job['company_id'],
            ':work_log_id' => $workLogId,
            ':user_id' => $participantUserId,
            ':source_device_id' => $sourceDeviceId !== '' ? substr($sourceDeviceId, 0, 191) : LEGACY_BACKFILL_SOURCE_DEVICE_ID,
            ':created_by' => $createdBy,
            ':updated_by' => $updatedBy,
            ':created_at' => $createdAt,
            ':updated_at' => $updatedAt,
        ]);
        $inserted++;
    }

    return $inserted;
}

function normalizeNullablePositiveInt($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }

    if (is_string($value) && ctype_digit($value)) {
        $normalized = (int) $value;
        return $normalized > 0 ? $normalized : null;
    }

    return null;
}

function normalizeDateTimeOrFallback($value): string
{
    if (is_string($value) && trim($value) !== '') {
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
    }

    return date('Y-m-d H:i:s');
}

function formatLegacyScalar($value): string
{
    if ($value === null) {
        return 'null';
    }

    $stringValue = trim((string) $value);

    return $stringValue !== '' ? $stringValue : 'null';
}

function generateUuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function printSummary(array $summary): void
{
    println('');
    println('Summary');
    println('Eligible jobs: ' . (string) $summary['eligible_jobs']);
    println('Created worklogs: ' . (string) $summary['created_worklogs']);
    println('Existing worklogs skipped: ' . (string) $summary['existing_worklogs']);
    println('Created participants: ' . (string) $summary['created_participants']);
    println('Invalid schedules: ' . count($summary['jobs_invalid_schedule']));
    println('Jobs without owner: ' . count($summary['jobs_without_owner']));
    println('Failed jobs: ' . count($summary['jobs_failed']));

    if ($summary['jobs_invalid_schedule'] !== []) {
        println('Invalid schedule job ids: ' . implode(', ', array_map('strval', $summary['jobs_invalid_schedule'])));
    }

    if ($summary['jobs_without_owner'] !== []) {
        println('Jobs without owner ids: ' . implode(', ', array_map('strval', $summary['jobs_without_owner'])));
    }

    if ($summary['jobs_failed'] !== []) {
        println('Failed job ids: ' . implode(', ', array_map('strval', array_keys($summary['jobs_failed']))));
    }
}

function println(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}
