# Receipt Settlement Full Flow Runbook

## What it covers

- mixed receipt capture with `receipt_items`
- invoice application against more than one invoice
- transfer confirmation
- check clear and rejection
- UI visibility of receipt, application, and invoice settlement states

## Risks covered

- receipts looking fully paid before funds are actually settled
- transfer/check lifecycle changes not propagating to invoice state
- duplicate or rejected instruments leaving stale payment status in UI

## Preconditions

- backend migrated with receipt phases 32-35
- frontend updated with receipt item capture and instrument actions
- user has permissions for receipts, invoice links, and instrument lifecycle actions

## Backend verification

1. Run `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php tests/Services/ReceiptInstrumentLifecycleServiceTest.php` inside `sisa.api`
2. Confirm all assertions pass, especially:
   - partial receipt settlement after transfer confirmation
   - full settlement after check clear
   - invoice/application rollback to `pending_settlement` after check rejection

## Manual UI verification

1. Create two invoices for the same client in `sisa.ui`
2. Create one receipt with two items:
   - transfer for the first invoice amount
   - check for the second invoice amount
3. Apply the receipt so both invoices are fully covered
4. Confirm the transfer from the receipt detail screen
5. Verify:
   - receipt shows `Aplicado` and `Liquidacion parcial`
   - first invoice shows `Cobrado`
   - second invoice shows `Pagada pendiente de acreditacion`
6. Clear the check from the same receipt detail screen
7. Verify:
   - receipt shows `Liquidado`
   - second invoice moves to `Cobrado`
8. Reject the same check with an explicit reason
9. Verify:
   - rejection reason is visible on the instrument card
   - receipt goes back to `Liquidacion parcial`
   - second invoice returns to `Pagada pendiente de acreditacion`

## Current blind spots

- no full device-level E2E automation yet across `sisa.api` and `sisa.ui`
- proration priority still follows stable creation order, not configurable business priority
- duplicate transfer warning is surfaced in UI, but bulk operator workflows are still manual
