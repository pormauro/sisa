# Field Dataset Map

## Purpose

This map defines the minimum data and behaviors that QA must protect so the app remains usable during unstable connectivity and converges safely afterward.

## Tier A - field-operable core

| Domain | Entities | Why it matters on site | Main risks | Current control |
|---|---|---|---|---|
| access and scope | memberships, selected company, permissions, users | without them the operator cannot see the right workspace or assign work | wrong company scope, stale permissions, missing participants | partial backend coverage, manual checks, cache guard debt |
| customer structure | clients, folders | lets the operator locate who and where work belongs | orphaned folders, wrong company/client scope, hard-delete drift | sync docs exist, limited focused tests |
| execution state | statuses | required to move operational flow safely | wrong status catalog, stale reference cache, delete drift | backend status controller logic exists, little direct QA |
| operational work | jobs, job_items, work_logs, appointments | core field work, timing, participants, scheduling | invalid parent relations, folder-tree violations, conflict/version drift | partial docs, appointments tests, low direct coverage elsewhere |
| evidence | files, file_attachments | proof of work and later auditability | upload state drift, detach errors, deleted attachments reappearing | file attachment model/controller coverage is partial |

## Tier B - field support references

| Domain | Entities | Why it matters on site | Main risks | Current control |
|---|---|---|---|---|
| commercial/support catalogs | providers, categories, products_services, tariffs | needed to classify and price field work without round-tripping online | stale cache, cross-company bleed, duplicate/default drift | backend offline-first tests exist for several references |
| reusable operations | payment_templates, cash_boxes | helps perform repeated financial/operational actions in the field | missing bootstrap, stale permissions, wrong local refresh | backend smoke/contract tests exist, client smoke limited |

## Tier C - extended operations

| Domain | Entities | Why it matters on site | Main risks | Current control |
|---|---|---|---|---|
| financial execution | payments, receipts, invoices, invoice_items, invoice_receipt_payments | may be needed to close work on-site | payload drift, attachment coupling, scope mistakes | backend smoke coverage exists, client validation still light |
| telemetry | tracking and device lifecycle | supports field coordination and diagnosis | device identity drift, push/scope issues, manual-only validation | docs exist, automation limited |

## Relationship rules that must not regress

| Rule area | Required rule |
|---|---|
| company scope | synchronized business data must resolve to a valid `company_id` or an explicit equivalent path |
| folder hierarchy | `job_items.folder_id` must stay within the allowed client/job subtree |
| participants | appointment and worklog participants must belong to the correct company scope |
| attachments | changing an attachable must be treated as `detach + attach`, not implicit reassignment |
| link entities | pure links must use `delete + create` instead of silent reassignment |
| delete semantics | deleted records must not accept operational updates or reappear as active after pull/bootstrap |
| metadata integrity | `version`, `source_device_id`, audit timestamps, and delete markers must remain coherent across snapshots and event streams |

## Generic sync controls

Every synchronized entity should eventually have all of these controls:

1. bootstrap coverage
2. incremental events coverage
3. push/write coverage when offline writes are supported
4. verify/reconcile coverage
5. server-side event generation outside sync push
6. local persistence auditable on the client side
7. manual or automated multi-device check for create/update/delete
