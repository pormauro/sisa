# Regression Checklist

## Release gate - minimum pass

### 1. Session and scope

- login works and session restore does not loop or blank the shell
- selected company is stable after bootstrap
- memberships and permissions match the active company

### 2. Core field dataset

- clients load inside the correct company scope
- folders preserve hierarchy and valid parentage
- statuses are visible and usable by operational forms
- jobs can be created, edited, and deleted without orphaning child data
- job items respect required parent job and folder-tree rules
- worklogs keep valid participants and do not lose metadata after sync
- appointments preserve participants and `visited_at`
- attachments can be added, synced, opened, and removed without reappearing

### 3. Offline-first and sync

- an offline write converges after reconnect
- a deleted record does not revive after pull or bootstrap
- `verify/reconcile` do not show unexplained drift for touched entities
- second device receives the change and origin device is not echoed by mistake
- operations that must be `delete + create` or `detach + attach` are still enforced

### 4. Support references

- providers, categories, products/services, tariffs, payment templates, and cash boxes load for the active company
- reference updates become visible without requiring a full app reset

### 5. Validation commands

- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1`
- if any step fails, classify it as:
  - pre-existing baseline debt,
  - known accepted limitation,
  - new regression that blocks progress

## Multi-device manual scenarios

### Scenario A - offline create then converge

1. Device A goes offline.
2. Create or edit a Tier A entity.
3. Reconnect device A and run sync.
4. Confirm device B receives the converged state.

### Scenario B - delete propagation

1. Delete a Tier A entity or attachment on device A.
2. Pull or wait for hint on device B.
3. Confirm the record does not reappear after refresh, pull, or bootstrap.

### Scenario C - ordering dependency

1. Create dependent records in a realistic order.
2. Confirm the server rejects invalid parent/scope relationships.
3. Confirm the client eventually reflects the canonical state.

### Scenario D - fresh bootstrap

1. Start with a fresh device or clean local store.
2. Bootstrap references and operational data.
3. Confirm Tier A dataset is enough to operate on site.

## Evidence to capture

- commands run
- affected entity UUIDs if available
- device/company used
- whether the result came from bootstrap, pull, or direct CRUD
- screenshots/logs/db exports when the issue is sync-related
