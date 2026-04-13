# AGENTS

## Workspace topology

- Root workspace: `C:\Users\Mauri\Documents\GitHub\sisas`
- This root is not a Git repository.
- `sisa.api/` and `sisa.ui/` are separate projects with their own `.git` directories.
- Shared QA documentation for the whole workspace lives at the root unless a document is clearly project-specific.

## Mission for future agents

- Treat QA as a cross-project system, not as isolated tests.
- Prioritize the data and flows required to operate in unstable-signal, field-work scenarios.
- Do not optimize for cosmetic coverage. Prefer a small number of trustworthy controls.
- Keep sync generic: jobs are only one consumer of the sync model.
- Preserve existing architecture unless a minimal change is required to make QA executable.

## Source of truth

- Plan: `QA_ROADMAP.md`
- Session and milestone log: `QA_STATUS.md`
- Existing sync/domain references:
  - `sisa.api/docs/sync-propagation-matrix.md`
  - `sisa.api/docs/sync-references-qa-guide.md`
  - `sisa.ui/docs/architecture/sync_propagation_matrix.md`
  - `sisa.ui/docs/architecture/devices-sync-and-offline-first-standard.md`

## Operating rules

- Update `QA_STATUS.md` every time you finish, block, or partially complete a milestone.
- Do discovery before broad edits.
- Work in small milestones and validate after each one.
- Do not continue to the next milestone if you introduced a new failure without documenting it and attempting a reasonable fix.
- If a blocker is pre-existing, record it as baseline debt instead of hiding it.
- Do not move unrelated files or refactor outside the active QA milestone.

## QA priorities

1. Field-operable sync baseline: users, memberships, permissions, clients, folders, statuses, jobs, job items, worklogs, appointments, file attachments.
2. Data integrity: required relationships, delete propagation, orphan prevention, idempotency, version/source metadata.
3. Offline-first behavior: local persistence, queueing, bootstrap, pull, reconcile, reconnect.
4. Multi-device convergence: device identity, hint propagation, conflict handling, non-reappearance of deleted data.
5. Operational support data needed on-site: providers, categories, products/services, tariffs, cash boxes, payments, receipts, invoices.

## Validation commands

### Backend (`sisa.api`)

- Full suite: `vendor/bin/phpunit`
- Example focused runs:
  - `vendor/bin/phpunit tests/Controllers/AppointmentsControllerCrudOfflineFirstTest.php`
  - `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php`

### Frontend (`sisa.ui`)

- Lint: `npm run lint`
- Cache guard: `npm run check:cache`
- Sync smoke: `npm run check:sync-smoke`

### Workspace helper

- Root helper: `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1`

## Editing guidance

- If you add or change QA automation, document:
  - what it protects,
  - which risk it covers,
  - how to run it,
  - known blind spots.
- Prefer backend PHPUnit for server contracts and domain rules.
- Prefer lightweight client checks for storage, mapping, sync hooks, and smoke conditions before introducing heavyweight E2E tooling.
- For multi-device flows that cannot yet be automated reliably, leave a strict manual runbook instead of pretending they are covered.

## Current QA baseline notes

- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` is the shared entry point and currently passes.
- `sisa.api` PHPUnit currently emits a database connection error line even when the suite exits successfully; treat that as output noise or hidden setup debt until explicitly diagnosed.
- `sisa.ui` cache guard now accepts SQLite/repository-backed persistence and explicitly exempted non-field-critical contexts; any new exception should be justified in code review and mirrored in `QA_STATUS.md`.
