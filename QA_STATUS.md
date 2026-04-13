# QA Status

## Current state

- Phase: initial QA foundation established
- Active principle: sync QA is generic and field-operation-first, not jobs-only
- Workspace topology confirmed: shared root plus two independent projects (`sisa.api`, `sisa.ui`)
- Shared baseline helper exists and currently passes in this environment

## Decisions taken

- Shared QA documentation lives at workspace root.
- The first QA priority is the minimum dataset required to keep operating on-site with unstable connectivity.
- Existing failures found during baseline are treated as pre-existing QA debt, not as regressions introduced by this session.
- Milestones will prefer server contract coverage first, then client storage/smoke coverage, then multi-device runbooks.

## Milestones

### Milestone 0 - QA operating base

Status: completed

What changed:

- created `AGENTS.md`
- created `QA_ROADMAP.md`
- created `QA_STATUS.md`
- created `qa/run-baseline.ps1`
- repaired stale baseline tooling so the shared helper is trustworthy again

Validation runs:

Initial run:

- backend: `vendor/bin/phpunit` -> failed from `PaymentsControllerTest` fake signature drift
- frontend: `npm run lint` -> passed
- frontend: `npm run check:cache` -> failed from stale cache-guard assumptions
- frontend: `npm run check:sync-smoke` -> failed from stale UI label expectation

Follow-up fixes applied:

- updated `sisa.api/tests/Controllers/PaymentsControllerTest.php` fake signatures to match production APIs
- updated `sisa.ui/scripts/verify-context-cache.js` to recognize SQLite/repository persistence and explicit non-field-critical exceptions
- updated `sisa.ui/scripts/sync-smoke.js` to check current conflict labels

Current validation status:

- backend: `vendor/bin/phpunit` -> pass
- frontend: `npm run lint` -> pass
- frontend: `npm run check:cache` -> pass
- frontend: `npm run check:sync-smoke` -> pass

Notes:

- PHPUnit still prints a database connection error line during the run even though the suite exits successfully; keep that under observation as output noise or hidden test setup debt.

### Milestone 1 - Domain map and regression contract

Status: completed

What changed:

- created `qa/FIELD_DATASET_MAP.md`
- created `qa/REGRESSION_CHECKLIST.md`
- aligned the roadmap to a generic sync model prioritized by field-operable data

Outcome:

- Tier A, B, and C data groups are explicitly defined
- the minimum release gate is documented
- relationship, delete, and metadata rules now have a shared QA reference

Validation:

- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pass

### Milestone 2 - Backend domain integrity tests

Status: in progress

Target:

- add focused PHPUnit coverage for highest-risk Tier A server contracts beyond the current baseline

Progress in this session:

- added `sisa.api/tests/Controllers/FileAttachmentsControllerTest.php`
- covered two critical attachment rules:
  - attachable reassignment must fail with `detach + attach` semantics instead of silent update
  - delete must emit the sync delete path and keep company-scoped attachment semantics

Validation:

- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pass after adding the new controller test

Notes:

- this is the first incremental slice of Milestone 2, not the full milestone
- next server-side targets remain `jobs`, `job_items`, `work_logs`, `clients`, `folders`, and `statuses`

## Risks currently prioritized

### High

- sync drift in `version`, `payload.version`, `source_device_id`, `deleted_at`
- delete propagation failures and resurrection of deleted attachments
- orphaned relations across clients/folders/jobs/job_items/work_logs/appointments
- mixed legacy CRUD vs offline-first behavior causing inconsistent convergence
- shell/bootstrap regressions in `sisa.ui/app/_layout.tsx`

### Medium

- incomplete cache/local-store guarantees for contexts still outside the stronger sync path
- stale smoke scripts creating false confidence or false failures
- multi-company scope regressions in permissions/references

## Problems found that can block QA expansion

- backend full-suite baseline is currently broken before adding new tests
- frontend has no formal test runner for unit/integration coverage
- existing client smoke scripts are narrow and partially stale
- sync documentation exists but is split across frontend and backend docs

## Next steps

1. start Milestone 2 with focused PHPUnit for `jobs`, `job_items`, `work_logs`, `file_attachments`, `clients`, `folders`, and `statuses`
2. isolate any remaining backend output-noise debt if PHPUnit keeps printing connection errors while passing
3. after server-contract coverage improves, expand client smoke coverage for local persistence and generic sync convergence
