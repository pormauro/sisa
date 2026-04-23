# Jobs Legacy Cleanup Checklist

## Objetivo

Eliminar del agregado `jobs` los restos de modelo legacy que ya no son fuente de verdad:

- `participants`
- `tariff_id`
- `manual_amount`
- `attached_files`

La meta es que `jobs` quede como entidad operativa liviana y que los datos derivados vivan en sus relaciones reales:

- participantes -> `work_log_participants`
- costo/tarifa efectiva -> `work_logs` + politica tarifaria vigente
- adjuntos -> `file_attachments`

## Regla de ejecucion

- no borrar columnas fisicas hasta sacar antes todos los consumers activos
- priorizar primero frontend y reportes, despues sync/backend, y al final schema
- cada etapa debe dejar validacion ejecutable o un bloqueo explicitado

## Etapa 0 - discovery y freeze de la fuente de verdad

- [x] confirmar que `job_date`, `start_time` y `end_time` ya salieron de `jobs`
- [ ] cerrar decision final de precio:
  - [x] costo deriva siempre del cliente/tarifa actual
  - [ ] costo deriva de snapshot por `work_log`
  - [ ] costo deriva solo del `invoice_item`
- [ ] cerrar decision final de participantes visibles en reportes:
  - [ ] solo tecnicos de `work_logs`
  - [ ] tecnicos de `appointments` como complemento
- [ ] cerrar decision final de adjuntos:
  - [ ] solo `file_attachments` por `job_uuid`

## Etapa 1 - frontend jobs sin campos legacy

### Modelo y contextos

- [x] sacar `job_date`, `start_time` y `end_time` del flujo principal de `JobsContext`
- [x] sacar `participants`, `tariff_id`, `manual_amount` y `attached_files` del tipo `Job` en `sisa.ui/contexts/JobsContext.tsx`
- [x] sacar serializacion de esos campos en `addJob` y `updateJob`

### Pantallas y hooks

- [x] reemplazar participantes del job por participantes calculados desde `worklogs` en `sisa.ui/app/jobs/viewModal.tsx`
- [x] reemplazar adjuntos del job por carga desde `file_attachments` en `sisa.ui/app/jobs/viewModal.tsx`
- [ ] quitar fallback de `manual_amount` y `tariff_id` en:
  - [x] `sisa.ui/hooks/useClientFinalizedJobTotals.ts`
  - [x] `sisa.ui/app/clients/finalizedJobs.tsx`
  - [x] `sisa.ui/app/invoices/create.tsx`
  - [x] `sisa.ui/app/invoices/index.tsx`
  - [ ] `sisa.ui/utils/jobTotals.ts`
- [ ] revisar bootstrap/sync UI para dejar de hidratar esos campos en jobs locales

### Validacion UI

- [x] `npm run lint`
- [ ] smoke manual:
  - [ ] listado de jobs
  - [ ] detalle de job
  - [ ] trabajos finalizados por cliente
  - [ ] seleccion/facturacion desde jobs finalizados

## Etapa 2 - backend sin lectura funcional de campos legacy

### Controladores

- [ ] `JobsController`: ignorar o rechazar `participants`, `tariff_id`, `manual_amount`, `attached_files` en create/update
- [ ] `JobReportsController`: sacar lectura de `jobs.participants`
- [ ] `JobReportsController`: sacar fallback de costo desde `jobs.manual_amount` y `jobs.tariff_id`
- [ ] `JobReportsController`: sacar adjuntos desde `jobs.attached_files`
- [ ] `InvoicesController`: no depender de metadata legacy del job para adjuntos/costos

### Sync

- [ ] `SyncOperationsController`: sacar esos campos del payload canonical de `jobs`
- [ ] `SyncEventGenerator`: dejar de emitir esos campos en eventos de jobs
- [ ] revisar bootstrap/reconcile para no reintroducirlos por compatibilidad

### Validacion backend

- [ ] `vendor/bin/phpunit tests/Controllers/JobsControllerCrudOfflineFirstTest.php`
- [ ] `vendor/bin/phpunit tests/Controllers/WorkLogsControllerTest.php`
- [ ] `vendor/bin/phpunit tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php`
- [ ] `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php`

## Etapa 3 - limpieza de schema y migraciones

- [ ] agregar migracion para `DROP COLUMN participants` en `jobs`
- [ ] agregar migracion para `DROP COLUMN tariff_id` en `jobs`
- [ ] agregar migracion para `DROP COLUMN manual_amount` en `jobs`
- [ ] agregar migracion para `DROP COLUMN attached_files` en `jobs`
- [ ] revisar si `jobs_history` necesita drop fisico o alcanza con snapshot JSON
- [ ] actualizar `install.php` para que no recree esas columnas
- [ ] actualizar `update_install.php` para registrar la fase de limpieza

## Etapa 4 - tests, compatibilidad y cierre

- [ ] actualizar tests que construyen jobs con esos campos legacy
- [ ] agregar tests que aseguren:
  - [ ] participantes salen de `worklogs`
  - [ ] costo no sale de `jobs`
  - [ ] adjuntos no salen de `jobs`
- [ ] correr baseline compartido
- [ ] documentar riesgos remanentes o deuda aceptada

## Riesgos conocidos

- `tariff_id` y `manual_amount` no son solo limpieza de schema; cambian la semantica del precio
- `participants` en jobs todavia aparece en reportes PDF y algun detalle legacy del frontend
- `attached_files` en jobs todavia sobrevive como atajo de compatibilidad en varios flujos historicos
- mientras convivan payloads legacy y canonicales, hay riesgo de reintroduccion por sync/bootstrap

## Estado actual

- [x] eliminado `job_date/start_time/end_time` del flujo principal y schema legacy
- [x] iniciada migracion de reportes/invoices a `worklogs`
- [x] iniciada migracion de UI de jobs/finalizados/calendario
- [x] limpieza frontend principal iniciada y `JobsContext` ya no expone esos campos
- [ ] pendiente limpieza final de `participants/tariff_id/manual_amount/attached_files` en sync/backend/schema
