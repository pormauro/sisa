# Flujo minimo factura -> recibo -> pagos

## Que protege

- crear un recibo desde una factura sin perder la asociacion `invoice -> receipt`
- recalcular `applied_receipts_total`, `pending_balance` y estado operativo de la factura (`issued` / `paid`)
- asociar pagos a un recibo sin duplicar el mismo pago sobre la misma factura
- evitar que borrar un recibo deje saldo o links fantasmas en facturas y pagos

## Superficie tecnica

- API:
  - `POST /invoices/{id}/receipts` ahora acepta crear el recibo inline y opcionalmente `payment_links`
  - `GET /receipts/{id}/payments`
  - `POST /receipts/{id}/payments`
  - `DELETE /receipts/{id}/payments/{linkId}`
  - nueva tabla sync `receipt_payments`
- App movil:
  - `app/invoices/[id].tsx` muestra saldo/cobro y abre recibos asociados
  - `app/receipts/create.tsx` muestra resumen de factura, saldo y selector minimo de pagos
  - `app/receipts/[id].tsx` muestra pagos asociados

## Como se corre

- API focalizada: `vendor/bin/phpunit tests/Regression/InvoiceReceiptsAndPaymentsFlowRegressionTest.php`
- Sintaxis API tocada: `php -l src/Controllers/InvoicesController.php && php -l src/Controllers/ReceiptsController.php && php -l src/Controllers/PaymentsController.php && php -l src/Controllers/SyncOperationsController.php`
- UI focalizada: `npx eslint "app/receipts/create.tsx" "app/payments/create.tsx" "contexts/InvoicesContext.tsx" "contexts/PaymentsContext.tsx" "contexts/ReceiptsContext.tsx" "src/modules/jobs/presentation/hooks/usePullJobsSync.ts" "src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts" "src/modules/jobs/presentation/sync/referenceCache.ts" "app/invoices/[id].tsx" "app/receipts/[id].tsx"`

## QA manual

- Caso A:
  - emitir factura por `100000`
  - desde detalle crear recibo por `100000`
  - esperar `Cobro total`, `Saldo pendiente 0`
- Caso B:
  - emitir factura por `100000`
  - crear recibo por `40000`
  - esperar `Cobro parcial`, `Saldo pendiente 60000`
- Caso C:
  - sobre la misma factura crear recibo `40000` y luego otro `60000`
  - esperar `Cobro total`, `Saldo pendiente 0`
- Caso D:
  - intentar crear recibo por encima del saldo
  - esperar rechazo API/UI
- Caso E:
  - crear o elegir un pago del cliente y asociarlo al recibo
  - intentar volver a usar el mismo pago sobre otra recepcion de la misma factura
  - esperar rechazo por doble imputacion
- Caso F:
  - eliminar un recibo vinculado a factura
  - esperar recalculo de saldo y remocion de links `invoice_receipt_payments` + `receipt_payments`

## Puntos ciegos conocidos

- la creacion inline de recibo desde `POST /invoices/{id}/receipts` no corre todavia dentro de una transaccion explicita; si en el futuro se agregan mas pasos atomicos, conviene cerrar ese hueco
- la UI movil ofrece seleccion minima de pagos y alta rapida desde el recibo, pero no una pantalla dedicada para reimputar montos de un pago ya asociado
- `payments` sigue respondiendo al modelo financiero existente del proyecto; el vinculo nuevo a recibos se limita a trazabilidad y validacion operativa, sin redisenar la semantica historica del modulo
