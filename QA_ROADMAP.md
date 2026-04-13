# QA Roadmap

## Objective

Build a persistent QA system for `sisa.api` + `sisa.ui` focused on real stability, regression prevention, end-to-end functional validation, and especially generic sync behavior for the data that must remain usable in unstable-signal field work.

This roadmap intentionally avoids making sync QA jobs-specific. The first-class question is:

> Can a technician or operator continue working with the minimum reliable dataset, then converge safely once connectivity returns?

## Scope baseline

### Tier A - field-operable core

- auth/session continuity
- selected company and memberships
- permissions
- users needed for assignment/participants
- clients
- folders
- statuses
- jobs
- job items
- worklogs
- appointments
- files and file attachments

### Tier B - field support and reference data

- providers
- tariffs
- categories
- products/services
- payment templates
- cash boxes

### Tier C - extended operational/financial data

- payments
- receipts
- invoices
- invoice items
- invoice receipt payments
- tracking and related diagnostics

## QA principles

- Start with the highest-risk, least-trustworthy surfaces.
- Prefer a few reliable controls over many shallow checks.
- Cover create, update, delete, sync, and re-sync as one system.
- Every milestone must leave the workspace in a measurably better state.
- Documentation is part of the deliverable, not an afterthought.
- Conservative default: if something cannot be automated yet, write the manual procedure and the current blocker.

## Known baseline from discovery

- Backend already has useful PHPUnit coverage for appointments, sync formatting, references, and some model-level soft-delete behavior.
- Backend lacks focused coverage for `jobs`, `job_items`, `work_logs`, `clients`, `folders`, `statuses`, and raw file flows.
- Frontend has no formal unit/integration/e2e framework configured; only custom smoke scripts and lint.
- Sync knowledge exists but is fragmented across `sisa.api/docs/*` and `sisa.ui/docs/*`.
- Architecture is mixed: legacy CRUD + newer offline-first flows coexist.

## Milestones

### Milestone 0 - QA operating base

Goal: create persistent shared instructions, plan, session status, and a baseline validation entry point.

Deliverables:

- `AGENTS.md`
- `QA_ROADMAP.md`
- `QA_STATUS.md`
- `qa/FIELD_DATASET_MAP.md`
- `qa/REGRESSION_CHECKLIST.md`
- `qa/run-baseline.ps1`

Validation:

- Run current backend and frontend validation commands.
- Record pass/fail and classify failures as baseline debt vs new regressions.

Done when:

- future sessions can continue without re-discovery,
- baseline commands are documented and runnable,
- current failures are explicit or the stale baseline tooling has been repaired.

### Milestone 1 - Domain map and regression contract

Goal: define what must never silently regress across both projects.

Coverage:

- domain map and entity relationships,
- required foreign-by-application relationships,
- delete propagation rules,
- attach/detach vs update semantics,
- operation ordering dependencies,
- field-operable minimum dataset,
- manual regression checklist before deploy.

Implementation:

- document the regression contract and manual checklist,
- document the field-operable minimum dataset,
- link each risk to current automation or manual fallback.

Validation:

- ensure each critical entity has a documented create/update/delete/sync stance,
- run baseline validations again after documentation changes.

### Milestone 2 - Backend domain integrity tests

Goal: raise trust in server-side business rules independent of UI.

First targets:

- `JobsController`
- `JobItemsController`
- `WorkLogsController`
- `FileAttachmentsController`
- `ClientsController`
- `FoldersController`
- `StatusController`

Minimum cases:

- create/update/delete happy path,
- required relationship validation,
- invalid parent/scope rejection,
- post-delete guardrails,
- forbidden reassignments (`delete + create`, `detach + attach`).

Validation:

- focused PHPUnit runs for each new test file,
- then broader `vendor/bin/phpunit` once baseline blockers are resolved or isolated.

### Milestone 3 - Backend sync contract tests

Goal: verify generic sync consistency, not only module CRUD.

Coverage:

- `bootstrap`, `events`, `push`, `verify`, `reconcile`,
- `version` consistency,
- `payload.version`,
- `source_device_id`,
- delete propagation,
- no resurrection after delete,
- idempotency-key behavior,
- company/device scope.

Priority order:

1. Tier A entities
2. Tier B references
3. Tier C financial flows

Validation:

- focused sync PHPUnit runs,
- manual cross-checks only for gaps that are not yet automatable.

### Milestone 4 - Client offline/store smoke coverage

Goal: add client-side confidence without overcommitting to heavyweight E2E.

Coverage:

- local persistence and hydration,
- queue/checkpoint behavior,
- reference cache propagation,
- attachment local/remote transitions,
- reconnect smoke flows,
- shell/bootstrap no-regression checks.

Implementation options, in this order:

1. harden existing custom smoke scripts,
2. add pure-function or repository tests,
3. add lightweight mocked hook tests,
4. defer full device E2E until lower layers are trustworthy.

### Milestone 5 - Multi-device and offline-to-online runbook

Goal: make the hardest sync scenarios executable and auditable.

Mandatory scenarios:

- device A offline create -> online convergence,
- device A delete -> device B no reappearance,
- attachment delete propagation,
- same-entity edit conflict,
- dependent operation ordering,
- bootstrap of a fresh device,
- company-scoped propagation excluding origin device.

Validation:

- manual runbook with expected evidence,
- export/log artifacts where possible.

### Milestone 6 - Release gate and expansion strategy

Goal: define the minimum QA gate before deploy and the path to expand coverage.

Coverage:

- release smoke command set,
- regression checklist,
- blocker handling policy,
- matrix of automated vs manual coverage,
- next entities to bring into stronger QA.

## Regression checklist baseline

Before deploy, at minimum verify:

- login/session restore do not break shell/bootstrap,
- selected company, memberships, and permissions converge correctly,
- clients/folders/statuses are visible and editable within proper scope,
- jobs/job items/worklogs/appointments support create/edit/delete without orphaning data,
- deleted attachments do not reappear after pull/bootstrap,
- offline writes converge after reconnect,
- a second device receives expected changes but not origin-only echoes,
- `verify/reconcile` do not report unexplained drift for changed entities,
- no new baseline commands fail without explicit sign-off.

## Exit criteria for the initial QA system

- shared documentation exists and is current,
- baseline commands are centralized,
- Tier A risks are at least mapped with either automation or manual control,
- new sessions can continue the program from `QA_STATUS.md` without rediscovery,
- the next highest-value gap is explicit.
