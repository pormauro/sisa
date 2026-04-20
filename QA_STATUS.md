# Estado QA

## Ultima actualizacion

- Fecha: 2026-04-20
- Corrida baseline: PASS
- PHPUnit suite: ~60 tests pasan (ruido de conexion filtrado)
- Lint: PASS
- Cache guard: PASS
- Sync smoke: PASS
- Tests integracion multi-dispositivo: 119/119 PASS
## Tests de Integracion Multi-Dispositivo

Estado: completado

Objetivo:

- simular dispositivos multiples con SQLite real y validar flujos de sync distribuido

Archivos creados:

- `sisa.api/tests/Integration/MultiDevice/TestDevice.php`
- `sisa.api/tests/Integration/MultiDevice/DeletePropagationTest.php`
- `sisa.api/tests/Integration/MultiDevice/DriftConflictTest.php`
- `sisa.api/tests/Integration/MultiDevice/MultiCompanyIsolationTest.php`
- `sisa.api/tests/Integration/MultiDevice/JobsSyncTest.php`
- `sisa.api/tests/Integration/MultiDevice/WorklogsAppointmentsAttachmentsTest.php`
- `sisa.api/tests/Integration/MultiDevice/ReconcileVerifyBootstrapTest.php`
- `sisa.api/tests/Integration/MultiDevice/OfflineQueueRetryTest.php`
- `sisa.api/tests/Integration/MultiDevice/ReferencesSyncTest.php`

Suite de tests (119 tests, 298 assertions):

- Delete propagation (status, client, provider, file_attachments)
- Drift detection y conflict resolution
- Multi-company isolation (empresas aisladas, global statuses)
- Jobs y job_items sync
- Worklogs, appointments y archivos adjuntos
- Reconcile, verify, bootstrap y checkpoints
- Offline queue y retry logic
- Error recovery y consistencia
- References sync (tariffs, categories, products_services, payment_templates, cash_boxes, permissions, payments, receipts, invoices, job_groups, root_causes, etc.)

Validacion:

- `vendor/bin/phpunit tests/Integration/MultiDevice/` -> 119/119 pass
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

## Estado actual

- Fase: base inicial de QA establecida
- Principio activo: el QA de sync es generico y prioriza la operacion en campo, no solamente `jobs`
- Topologia confirmada: raiz compartida mas dos proyectos independientes (`sisa.api`, `sisa.ui`)
- Existe un helper compartido de baseline y actualmente pasa en este entorno
- Se corrigio un problema de runtime ligado a handles SQLite liberados que podia romper corridas manuales en dispositivo
- Se documentaron 5 escenarios manuales en runbook multi-dispositivo
- Se cubrio delete propagation con tests automatizados completos (tombstones en server, pull, bootstrap, reconcile, verify)

## Decisiones tomadas

- La documentacion QA compartida vive en la raiz del workspace.
- La primera prioridad de QA es el dataset minimo necesario para seguir operando en sitio con conectividad inestable.
- Las fallas existentes detectadas en el baseline se tratan como deuda previa, no como regresiones introducidas por esta sesion.
- Los milestones priorizan primero contratos del servidor, luego storage/smokes del cliente y despues runbooks multi-dispositivo.

## Milestones

### Milestone 0 - base operativa de QA

Estado: completado

Que cambio:

- se creo `AGENTS.md`
- se creo `QA_ROADMAP.md`
- se creo `QA_STATUS.md`
- se creo `qa/run-baseline.ps1`
- se reparo tooling viejo del baseline para que el helper compartido vuelva a ser confiable

Corridas de validacion:

Corrida inicial:

- backend: `vendor/bin/phpunit` -> fallo por drift de firmas fake en `PaymentsControllerTest`
- frontend: `npm run lint` -> paso
- frontend: `npm run check:cache` -> fallo por supuestos viejos de la guardia de cache
- frontend: `npm run check:sync-smoke` -> fallo por expectativa vieja de labels en UI

Correcciones aplicadas:

- se actualizaron las firmas fake en `sisa.api/tests/Controllers/PaymentsControllerTest.php` para alinearlas con las APIs reales
- se actualizo `sisa.ui/scripts/verify-context-cache.js` para reconocer persistencia via SQLite/repositorios y excepciones explicitas fuera del flujo critico de campo
- se actualizo `sisa.ui/scripts/sync-smoke.js` para validar los labels actuales de conflicto

Estado actual de validacion:

- backend: `vendor/bin/phpunit` -> pasa
- frontend: `npm run lint` -> pasa
- frontend: `npm run check:cache` -> pasa
- frontend: `npm run check:sync-smoke` -> pasa

Notas:

- PHPUnit todavia imprime una linea de error de conexion durante la corrida aunque la suite termina bien; mantenerlo observado como ruido de salida o deuda oculta de setup.

### Milestone 1 - mapa de dominio y contrato de regresion

Estado: completado

Que cambio:

- se creo `qa/FIELD_DATASET_MAP.md`
- se creo `qa/REGRESSION_CHECKLIST.md`
- se alineo el roadmap a un modelo de sync generico priorizado por datos operables en campo

Resultado:

- quedaron definidos explicitamente los grupos Tier A, B y C
- quedo documentada la puerta minima de release
- las reglas de relaciones, delete y metadata ahora tienen una referencia QA compartida

Validacion:

- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

### Milestone 2 - tests de integridad de dominio en backend

Estado: en progreso

Objetivo:

- agregar cobertura focalizada con PHPUnit sobre contratos Tier A del servidor mas alla del baseline existente

Avance en esta etapa:

- se agrego `sisa.api/tests/Controllers/FileAttachmentsControllerTest.php`
- se cubrieron dos reglas criticas de adjuntos:
  - la reasignacion de attachable debe fallar con semantica `detach + attach` en vez de update silencioso
  - delete debe emitir el camino de sync delete y preservar la semantica de scope por empresa
- se agrego `sisa.api/tests/Controllers/WorkLogsControllerTest.php`
- se cubrieron dos contratos de worklogs ligados a la integridad de sync generico:
  - create exitoso agrega por defecto al creador como participante, persiste historial y emite evento de sync create
  - create rechaza `job_item_id` cuando no pertenece al `job` seleccionado
- se agrego `sisa.api/tests/Controllers/JobItemsControllerTest.php`
- se cubrieron dos contratos de `job_items` de alta sensibilidad operativa:
  - create resuelve automaticamente un estado final cuando el item nace terminado y deja rastro en history + status history + sync event
  - create rechaza `folder_id` cuando el item queda fuera del arbol permitido del job
- se agrego `sisa.api/tests/Controllers/JobsControllerCrudOfflineFirstTest.php`
- se cubrieron dos contratos de `jobs` alineados con la regla de folders y el delete propagado:
  - update permite limpiar `jobs.folder_id` a `null` sin invalidar items existentes, dejando que usen cualquier carpeta valida del mismo cliente
  - delete expone el resultado del borrado propagado basado en soft delete sobre hijos y adjuntos
- se agrego `sisa.api/tests/Models/FoldersTest.php`
- se corrigio `sisa.api/src/Models/Folders.php` para respetar soft delete cuando existe `deleted_at`, ocultando folders borrados de `find/list`
- se alineo `sisa.api/install.php` con el modelo actual de folders agregando `uuid`, `version`, `source_device_id` y `deleted_at` al schema de instalacion
- se agrego `sisa.api/tests/Models/ClientsTest.php`
- se corrigio `sisa.api/src/Models/Clients.php` para respetar soft delete y no devolver clientes borrados en `find/list`
- se alineo `sisa.api/install.php` con el modelo actual de clients agregando `uuid`, `version`, `source_device_id` y `deleted_at` al schema de instalacion
- se agrego `sisa.api/tests/Models/StatusTest.php` para asegurar que statuses globales + company-scoped filtren bien y que un soft delete saque al status de los lookups asignables
- se agrego `sisa.api/tests/Controllers/StatusControllerTest.php` para cubrir filtros por scope y rechazo de `company_id` fuera de alcance en el controlador
- se agrego `sisa.api/tests/Controllers/ClientsControllerTest.php` para cubrir filtros de `clients` por referencia company-backed y rechazo de empresas inactivas al crear referencias del ecosistema
- se expandio `sisa.api/tests/Controllers/ProvidersControllerOfflineFirstTest.php` para cubrir filtros de `providers` por `company_id` y rechazo de empresas inactivas al crear referencias del ecosistema
- se actualizo `sisa.api/update_install.php` y se agrego `sisa.api/scripts/migrations/clients-folders-sync-alignment-phase25.php` para que instalaciones existentes reciban la alineacion de columnas/indexes de `clients` y `folders`
- se ajusto `sisa.api/src/Controllers/ClientsController.php` para permitir inyeccion de dependencias y volver testeable el controlador sin alterar su contrato HTTP
- se documento explicitamente que `clients` y `providers` son referencias operativas basadas en `empresas` dentro del ecosistema de companias

### Avance parcial - reportes PDF operativos y trazabilidad

Estado: en progreso

Que cambio:

- se fortalecio `sisa.api/src/Models/Reports.php` para persistir `company_id` en filas de reportes
- se ajusto `sisa.api/src/History/ReportsHistory.php` para arrastrar `company_id` al historial de reportes
- se amplio `sisa.api/src/Controllers/JobReportsController.php` para aceptar variantes y secciones de reporte via payload (`report_variant`, `include_sections`, banderas auxiliares y display options extendidas)
- el reporte PDF operativo de jobs ahora puede consolidar `worklogs`, participantes, tarifas aplicadas, appointments, timeline operativo y gastos a cargo del cliente desde `payments`
- se agrego resumen operativo/economico enriquecido en el PDF de jobs sin romper la ruta existente
- se alinearon `sisa.api/src/Controllers/InvoicesController.php` y `sisa.api/src/Controllers/PaymentsController.php` para registrar `company_id` en `reports` cuando generan PDFs
- se agregaron tests focalizados en `sisa.api/tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php`
- se agrego `sisa.api/tests/Models/ReportsTest.php` para cubrir persistencia de `company_id` y metadata en `reports` + `reports_history`
- se creo `qa/REPORTS_TRANSFORMATION_CHECKLIST.md` como checklist integral para transformar los informes de jobs y llevarlos hasta estado de cuenta cliente + reportes economicos/contables por `company_id`
- el checklist nuevo cubre arquitectura, payloads, persistencia, dataset assembly, informe operativo, timeline, cuenta corriente, reportes economicos, QA automatizado/manual, migraciones, riesgos y criterio de terminado
- se amplio `sisa.api/src/Controllers/JobReportsController.php` con dos variantes nuevas dentro del mismo endpoint/patron operativo:
  - `client_account_statement`: estado de cuenta por cliente con facturas, recibos, pagos/cargos, aplicaciones y aging
  - `accounting_general`: reporte economico general filtrado por `company_id` y periodo, reutilizando `AccountingSummaryService`
- se ajusto `sisa.api/src/Services/AccountingSummaryService.php` para aceptar recorte opcional por `company_id` sin romper llamadas existentes
- se expandio `sisa.api/tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` para cubrir aging/saldos de cuenta y render PDF economico general
- se actualizo `qa/REPORTS_TRANSFORMATION_CHECKLIST.md` tachando los bloques ya implementados de payload, persistencia, cuenta corriente, accounting general, QA y soporte Postman/documental
- se creo `sisa.api/docs/reports-pdf-variants.md` para documentar el contrato extendido del endpoint `POST /jobs/client/{clientId}/report/pdf`
- se actualizo `sisa.api/docs/reports-table.md` con `company_id`, tipos de reporte nuevos y metadata persistida por variante
- se agrego una carpeta `Reports` a `sisa.api/Sistema.postman_collection.json` con requests listas para:
  - reporte operativo detallado
  - estado de cuenta cliente
  - reporte economico general
- se endurecio la validacion del payload en `sisa.api/src/Controllers/JobReportsController.php` para rechazar:
  - `timeline_order` invalido
  - `group_by` no permitido para la variante elegida
  - `include_sections` incompatibles con la variante
  - combinaciones invalidas como `status_ids` o `include_timeline` sobre `accounting_general`/`client_account_statement`
- se expandio `sisa.api/tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` para cubrir estas reglas de validacion endurecidas
- se endurecio `sisa.api/src/Controllers/ReportsController.php` para recortar `/reports` al scope de companias aprobadas del usuario y validar `company_id` en create/update/list/get/delete/history/regenerate
- se amplio `sisa.api/src/Models/Reports.php` para soportar consulta accesible por `company_scope` y filtros operativos por metadata (`client_id`, `report_variant`, `generated_by_user_id`, `start_date`, `end_date`)
- se agrego `regenerateReport` al catalogo de permisos de reportes en `sisa.api/src/Models/Permission.php` y una migracion incremental en `sisa.api/update_install.php` para sembrarlo en instalaciones existentes
- se actualizo `sisa.api/docs/reports-table.md` y `sisa.api/docs/reports-pdf-variants.md` con el endpoint `POST /reports/{id}/regenerate` y los nuevos filtros operativos de `GET /reports`
- se expandieron `sisa.api/tests/Models/ReportsTest.php` y `sisa.api/tests/Controllers/ReportsControllerRegenerateTest.php` para cubrir scope/filtros de reportes y presencia del permiso nuevo
- se refactorizo `sisa.ui/contexts/ReportsContext.tsx` para soportar operaciones genericas de reportes (`getReport`, `getReportHistory`, `deleteReport`, `regenerateReport`) y normalizacion de metadata operativa
- se transformo `sisa.ui/app/reports/index.tsx` de bandeja payment-only a centro generico de reportes con filtros por tipo, variante, estado, empresa, cliente, usuario y rango de fechas
- se creo `sisa.ui/app/reports/[id].tsx` para detalle, historial basico, apertura de PDF y regeneracion desde UI
- se alineo `sisa.ui/src/constants/permissionCatalog.ts` y `sisa.ui/docs/features/reports-api.md` con `regenerateReport` y el contrato nuevo de `/reports`
- se actualizaron `sisa.ui/app/clients/[id].tsx` y `sisa.ui/app/clients/viewModal.tsx` para generar variantes nuevas (`full_detailed`, `technical_timeline`, `client_account_statement`, `accounting_general`, `landscape_summary`) sobre el endpoint comun y refrescar la bandeja de reportes
- se alineo `sisa.ui/app/invoices/[id].tsx` para que la generacion/apertura de PDF de factura refresque la bandeja comun de `reports` y derive al detalle del reporte cuando el backend devuelve `report_id`
- se agrego un acceso rapido al centro de reportes desde `sisa.ui/app/accounting/summary.tsx` con filtro inicial para reportes contables
- se extrajo `sisa.ui/src/features/reports/components/ClientReportModal.tsx` para dejar un unico modal compartido de generacion de reportes de cliente y reducir drift entre `clients/[id]` y `clients/viewModal`
- `sisa.ui/app/reports/index.tsx` ahora acepta `start_date` y `end_date` por params para aterrizar desde otros modulos con filtros precargados
- se agregaron accesos contextuales al centro de reportes desde `sisa.ui/app/payments/index.tsx`, `sisa.ui/app/receipts/index.tsx` y `sisa.ui/app/cash_boxes/index.tsx` con filtros iniciales alineados al modulo origen

Validacion:

- `vendor/bin/phpunit tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php tests/Models/ReportsTest.php` -> pasa (7 tests, 29 assertions)
- `vendor/bin/phpunit tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php tests/Models/ReportsTest.php tests/Regression/AccountingSummaryAndInvoicesRegressionTest.php` -> pasa (15 tests, 74 assertions)
- `vendor/bin/phpunit tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php tests/Models/ReportsTest.php tests/Regression/AccountingSummaryAndInvoicesRegressionTest.php` -> pasa despues del hardening (17 tests, 80 assertions)
- `vendor/bin/phpunit tests/Models/ReportsTest.php tests/Controllers/ReportsControllerRegenerateTest.php` -> pasa (7 tests, 24 assertions)
- `npm run lint` -> pasa despues del refactor del centro de reportes
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa con el nuevo hub generico de reportes
- `npm run lint` -> pasa despues de conectar los generadores contextuales de cliente al hub de reportes
- `npm run lint` -> pasa despues de conectar invoices + accesso contable al hub de reportes
- `npm run lint` -> pasa despues de unificar el modal compartido de cliente y aceptar filtros precargados en `/reports`
- `npm run lint` -> pasa despues de sumar accesos contextuales al centro de reportes desde pagos/recibos/cajas

Notas:

- esta etapa cubre el primer tramo recomendado: ampliacion del informe operativo de jobs con variantes, timeline y trazabilidad basica de reports
- ya existe una guia/checklist ejecutable para completar el resto del roadmap de reportes sin redescubrir alcance ni criterios
- ya existe un primer corte ejecutable de estado de cuenta cliente y reporte economico general dentro del mismo endpoint extendido
- el contrato del payload ahora esta mas cerrado y falla rapido ante combinaciones inconsistentes, reduciendo riesgo de PDFs mal armados o ambiguos
- `/reports` ya tiene un primer corte mas util para la app porque puede listar y resolver reportes dentro del scope real del usuario, pero todavia faltan filtros por entidad principal, detalle/historial de UI y regeneracion generalizada para invoices/payments
- la UI ya puede listar, abrir, inspeccionar y regenerar reportes de jobs desde un centro generico, aunque todavia falta conectar los generadores contextuales de clientes/contabilidad al nuevo flujo comun
- los generadores de cliente ya alimentan el flujo comun y pueden derivar al detalle del reporte creado, aunque contabilidad global e invoices siguen sin integracion equivalente
- invoices ya alimenta la bandeja comun cuando el backend devuelve `report_id`, y contabilidad global ya tiene punto de entrada al hub; sigue faltando profundizar generacion contable contextual desde UI
- la duplicacion mas visible de UI de reportes en cliente ya quedo reducida a un componente compartido; sigue pendiente una generacion contable verdaderamente global desde UI si el backend expone un contrato mas directo para ese caso
- la navegacion hacia `/reports` ya esta mejor distribuida en modulos operativos/contables, aunque todavia faltan filtros backend por entidad principal/caja para que esos aterrizajes sean mas precisos
- siguen pendientes el detalle contable mas profundo por caja/libro, el refinamiento visual, la regeneracion generalizada mas alla de jobs y la cobertura QA de performance/escenarios de alto volumen

### Limpieza de baseline

Estado: completado

Que cambio:

- se limpio el ruido del baseline compartido en `qa/run-baseline.ps1` ocultando la linea espuria de conexion cuando PHPUnit termina correctamente
- se mantuvo intacto el comportamiento actual del runtime en `sisa.api/src/Config/Database.php` para no abrir una regresion amplia en tests heredados

Resultado:

- el baseline vuelve a ser legible y usable como puerta operativa
- la deuda estructural de setup sigue existiendo, pero ya no contamina la salida del helper compartido

Validacion:

- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa despues de agregar los nuevos tests de controladores

Notas:

- este es solo el primer tramo incremental del Milestone 2, no el milestone completo
- los siguientes objetivos de servidor pasan a ser el arranque del Milestone 3 sobre contratos genericos de sync para referencias y propagacion de deletes, con `clients/providers/folders/statuses` como base ya mas firme

## Riesgos priorizados actualmente

### Alta prioridad

- ~~drift de sync en `version`, `payload.version`, `source_device_id`, `deleted_at`~~ (CORREGIDO)
- fallas de propagacion de delete y resurreccion de attachments eliminados
- relaciones huerfanas entre clients/folders/jobs/job_items/work_logs/appointments
- convivencia de CRUD legacy y offline-first provocando convergencia inconsistente
- regresiones de shell/bootstrap en `sisa.ui/app/_layout.tsx`

### Prioridad media

- garantias incompletas de cache/storage local en contextos todavia fuera del camino fuerte de sync
- smoke scripts viejos que generan falsa confianza o falsos fallos
- regresiones de scope multi-company en permisos/referencias

## Problemas encontrados que pueden bloquear la expansion de QA

- el baseline completo de backend estuvo roto antes de agregar nuevos tests
- frontend sigue sin runner formal para cobertura unit/integration
- los smoke scripts actuales del cliente son utiles pero angostos y parcialmente fragiles
- la documentacion de sync existe, pero sigue repartida entre frontend y backend

## Incidente de runtime corregido

- Sintoma: error Expo SQLite `NativeDatabase.prepareAsync` con mensaje `Cannot use shared object that was already released`
- Causa mas probable confirmada por inspeccion: `resetDatabaseConnection()` podia cerrar el handle SQLite compartido mientras `jobsDatabase.ts` seguia cacheando la instancia previa en `initializedDb`
- Correccion aplicada:
  - `sisa.ui/database/Database.ts` ahora expone `subscribeToDatabaseReset()`
  - `sisa.ui/database/sqlite.ts` reexporta esa suscripcion
  - `sisa.ui/src/modules/jobs/data/db/jobsDatabase.ts` invalida su cache cuando la conexion compartida se resetea
- Alcance: solucion conservadora, sin reestructurar el acceso global a SQLite ni tocar la semantica de tracking mas alla de invalidar caches dependientes

Validacion del fix:

- `npm run lint` -> pasa
- `npm run check:sync-smoke` -> pasa
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

## Alineacion de statuses con sync

- Diagnostico: `StatusesContext` seguia siendo mas legacy que sync-first; mezclaba SQLite local con fetch directo a `/statuses` y `replaceAll()` sin filtrar por `selectedCompanyId`
- Riesgo observado: un dispositivo podia ver `statuses` residuales o de un scope distinto mientras otro no, y el resultado parecia no venir del flujo de sync sino de una capa de contexto mas vieja
- Correccion aplicada:
  - `sisa.ui/src/modules/jobs/data/repositories/SQLiteStatusesRepository.ts` ahora soporta `listAll(companyId)` e `hydrateIfEmpty(..., companyId)`
  - `sisa.ui/contexts/StatusesContext.tsx` ahora:
    - usa `selected-company-id`
    - respeta permiso `listStatuses`
    - hidrata desde SQLite filtrando por company/global
    - al hacer fetch directo usa `?company_id=` cuando corresponde
    - refresca desde cache local sincronizada en vez de confiar solo en `replaceAll()` ciego
- Resultado esperado: baja el riesgo de residuos locales y reduce la divergencia entre dispositivos para `statuses`
- Ajuste adicional aplicado:
  - `sisa.ui/contexts/StatusesContext.tsx` ahora envia `company_id` desde la empresa seleccionada de la barra inferior al crear y actualizar estados
  - tambien envia `source_device_id` para mantener trazabilidad de origen
  - cuando el backend devuelve el objeto `status`, el cliente usa esa respuesta canonica; si no, fuerza `loadStatuses(true)` para refrescar desde servidor
- Ajuste de propagacion aplicado:
  - `sisa.ui/src/modules/jobs/data/repositories/SQLiteStatusesRepository.ts` ahora persiste `uuid`, `company_id` y `source_device_id` en SQLite local
  - `sisa.ui/src/modules/jobs/presentation/sync/referenceCache.ts` ahora baja esos mismos campos al cachear `statuses`
  - `sisa.api/src/Services/SyncEventGenerator.php` ahora emite hints con scopes especificos de referencias (`statuses`, `clients`, `folders`, `providers`, etc.) ademas de `jobs`
  - `sisa.ui/app/_layout.tsx` ahora refresca bootstrap al recibir `sync_hint` de `statuses`, `clients` y `folders`
- Hipotesis principal del incidente funcional: las altas/ediciones de `statuses` actualizaban version y backend, pero no disparaban un camino de refresco claro en el otro dispositivo porque el hint venia demasiado centrado en `jobs` y el cache local de `statuses` no retenia suficiente metadata de scope
- Ajuste adicional de visibilidad aplicado:
  - `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` ahora considera rutas de referencias (`/statuses`, `/clients`, `/providers`, `/folders`) como rutas validas para autosync/pull incremental
  - `sisa.ui/app/statuses/index.tsx` ahora fuerza `loadStatuses(true)` al entrar en foco
  - `sisa.ui/app/statuses/[id].tsx` ahora fuerza `loadStatuses(true)` al intentar recargar el item
- Hipotesis complementaria: aun con eventos correctos, en pantallas de referencias el autosync podia no correr por restriccion de ruta y la UI podia quedar mostrando cache vieja hasta reiniciar o volver a una ruta de jobs/home
- Causa raiz adicional encontrada con evidencia real:
  - el payload incremental de `statuses` salia sin `id` desde `sisa.api/src/Services/SyncEventGenerator.php`
  - en `usePullJobsSync` el cliente exige `payload.id > 0` para hacer `mergeStatusesCache`, asi que los updates de status llegaban pero eran descartados silenciosamente del upsert local
- Correccion aplicada:
  - `sisa.api/src/Services/SyncEventGenerator.php` ahora incluye `id` canonico en `serializeStatus()`
  - se agrego prueba en `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` para bloquear regresiones del payload de `statuses`
- Esta causa explica el sintoma observado: bootstrap inicial traia el ultimo estado, pero las ediciones posteriores no se reflejaban por feed incremental aunque `version` subiera en servidor
- Ajuste final de metadata aplicado:
  - `sisa.ui/contexts/StatusesContext.tsx` ahora envia `source_device_id` tambien en delete de `statuses`
  - `sisa.api/src/Controllers/StatusController.php` ahora lee payload opcional en delete y propaga `source_device_id` a `softDelete()` y `recordDelete()`
  - se agrego cobertura en `sisa.api/tests/Controllers/StatusControllerTest.php` para asegurar que delete conserve `source_device_id`
- Visibilidad de sync agregada en UI:
  - `sisa.ui/src/modules/jobs/data/db/syncState.ts` ahora expone `getEntitySyncInfo()` para consultar estado de sync local por `entity_type + uuid`
  - `sisa.ui/app/statuses/[id].tsx` ahora muestra bloque `Sync` con icono, estado, UUID, version, device, empresa, ultimo sync y error/conflicto si aplica
  - `sisa.ui/app/statuses/index.tsx` ahora muestra icono de sync y metadata minima (`version`, `source_device_id`) en cada fila
  - `sisa.ui/scripts/sync-smoke.js` se expandio para exigir esta visibilidad minima en `statuses`

Validacion del ajuste:

- `npm run lint` -> pasa
- `npm run check:sync-smoke` -> pasa
- `vendor/bin/phpunit tests/Controllers/StatusControllerTest.php tests/Models/StatusTest.php` -> pasa
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

## Siguientes pasos

1. continuar Milestone 2 con PHPUnit focalizado para `jobs`, `job_items`, `work_logs`, `file_attachments`, `clients`, `folders` y `statuses`
2. aislar la deuda restante de ruido en backend si PHPUnit sigue imprimiendo errores de conexion mientras pasa
3. despues de reforzar los contratos del servidor, expandir smoke coverage del cliente para persistencia local y convergencia generica de sync

### Milestone 3 - contratos genericos de sync en backend

Estado: en progreso

Objetivo:

- asegurar consistencia generica de sync para referencias y deletes, no solo CRUD puntual

Avance en esta etapa:

- se corrigio `sisa.api/src/Controllers/SyncOperationsController.php` para que delete de `clients` y `folders` via sync use soft delete en vez de hard delete
- `listClientsForSync` y `listFoldersForSync` ahora incluyen `deleted_at`, permitiendo propagar tombstones y prevenir reapariciones
- se expandio `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` con dos contratos nuevos:
  - delete de `clients` via sync deja tombstone visible, conserva `source_device_id` y aumenta `version`
  - delete de `folders` via sync deja tombstone visible, conserva `source_device_id` y aumenta `version`
- se ajusto `sisa.api/src/Models/Status.php` para que `softDelete` acepte timestamp y `source_device_id`, alineandose con el resto de referencias offline-first
- se corrigio `sisa.api/src/Controllers/SyncOperationsController.php` para que delete de `statuses` devuelva tombstone con `deleted_at`, `source_device_id` y `version` incrementada
- se expandio `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` con contratos adicionales:
  - delete de `statuses` via sync conserva tombstone, `source_device_id` y `version`
  - delete de `providers` via sync conserva tombstone, `source_device_id` y `version`
- se separo en `sisa.api/src/Controllers/SyncOperationsController.php` la lectura de referencias para verify/reconcile en `statuses` y `providers`, de modo que esos flujos puedan incluir tombstones y no solo registros activos
- se agregaron contratos nuevos en `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` para asegurar que:
  - `verify` contabiliza `statuses` y `providers` eliminados cuando existen tombstones
  - `reconcile` detecta drift cuando el cliente conserva una referencia eliminada como activa
- se ajusto `sisa.api/src/Controllers/SyncOperationsController.php` para que `bootstrap/references` tambien use lecturas aptas para tombstones en `statuses` y `providers`
- se agrego un contrato nuevo en `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` para asegurar que `bootstrap/references` puede incluir referencias eliminadas con `deleted_at` cuando existen tombstones
- se agregaron contratos nuevos en `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` para asegurar que `pull` y `events` entregan operaciones delete de referencias con tombstones completos (`deleted_at`, `source_device_id`, `version`)
- se reforzaron los doubles de checkpoints/operations del test de sync para validar `listForPull`, `upsertCheckpoint` y snapshot de operaciones sin tocar el runtime productivo
- se corrigio `sisa.api/src/Controllers/SyncOperationsController.php` para que delete de `file_attachments` via sync devuelva tombstone canonico con `deleted_at`, `source_device_id` y `version`
- se agrego `findByUuidIncludingDeleted` en `sisa.api/src/Models/FileAttachments.php` y `sisa.api/src/Services/SyncEventGenerator.php` ahora lo usa para construir operaciones canonicas de adjuntos eliminados
- se ajusto `listFileAttachmentsForVerify` en `sisa.api/src/Controllers/SyncOperationsController.php` para incluir tombstones y permitir detectar drift de adjuntos eliminados
- se agregaron contratos nuevos en `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` para asegurar que:
  - delete de `file_attachments` via sync conserva tombstone canonico
  - `reconcile` detecta drift cuando un attachment eliminado sigue activo del lado cliente
- se agregaron contratos nuevos en `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` para asegurar que `pull` y `events` propagan deletes de `file_attachments` con tombstones completos y que en sync v3 se mapean como `job_file`/`job_item_file` con accion `detach`
- se agrego cobertura explicita para `worklog_file` en `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php`, cerrando los tres tipos relacionales de adjuntos (`job_file`, `job_item_file`, `worklog_file`) en el feed incremental v3
- se ajusto el double `FakeFileAttachmentsForSyncDeletes` para evitar lookups reales de archivos durante los tests de feed incremental y mantener el baseline estable

Validacion:

- `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php tests/Controllers/ProvidersControllerOfflineFirstTest.php tests/Controllers/ClientsControllerTest.php` -> pasa
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

Notas:

- este es el primer slice de Milestone 3 y ataca una de las deudas mas criticas: no reaparicion de referencias eliminadas
- este segundo slice extiende esa misma garantia a `verify/reconcile`, para que los tombstones de referencias tambien participen de la deteccion de drift
- este tercer slice extiende la misma garantia a `bootstrap/references`, reduciendo el riesgo de reaparicion de referencias eliminadas en dispositivos que reconstruyen estado desde snapshot
- este cuarto slice extiende la garantia a `pull/events`, cerrando el circuito basico de bootstrap + verify + reconcile + feed incremental para referencias eliminadas
- este quinto slice extiende el mismo criterio a `file_attachments`, cubriendo una de las areas de mayor riesgo de reaparicion operativa
- este sexto slice cierra el feed incremental de adjuntos: snapshot, reconcile, pull y events ahora tienen cobertura minima para deletes relacionales de archivos
- con la cobertura explicita de `worklog_file`, los tres attachables operativos mas sensibles ya tienen un contrato minimo de no reaparicion en sync incremental
- el helper de baseline sigue filtrando el ruido espurio de conexion cuando PHPUnit termina bien, pero sin alterar el runtime productivo

### Milestone 4 - cobertura smoke de cliente/offline/store

Estado: en progreso

Objetivo:

- agregar confianza del lado cliente para persistencia local y consumo correcto del feed incremental sin saltar todavia a E2E pesados

Avance en esta etapa:

- se expandio `sisa.ui/scripts/sync-smoke.js` para cubrir el tratamiento cliente de adjuntos relacionales eliminados
- el smoke ahora exige que `usePullJobsSync`:
  - reconozca `job_file`, `job_item_file` y `worklog_file`
  - trate `detach/delete` como borrado local del attachment
  - persista tombstones de `file_attachments` en snapshots locales
- el smoke tambien valida en `sisa.ui/src/modules/jobs/data/db/syncState.ts` que `deleted_at` sobreviva al materializar entidades cliente para `reconcile`
- se ajusto `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` para que `bootstrap/references` quite de cache local `statuses`, `providers`, `clients` y `folders` cuando llegan con `deleted_at`, en vez de reinsertarlos como activos
- se expandio `sisa.ui/scripts/sync-smoke.js` para exigir esa logica de remocion por tombstone durante bootstrap de referencias
- se expandio nuevamente `sisa.ui/scripts/sync-smoke.js` para cubrir persistencia local del drift de `reconcile`, validando que `syncState.ts`:
  - inserte conflictos en `entity_sync_state`
  - marque `sync_state = 'conflict'`
  - eleve `conflict_flag = 1` en tablas locales
  - limpie conflictos resueltos desde `useSyncStatus.ts`
- se expandio otra vez `sisa.ui/scripts/sync-smoke.js` para cubrir garantias operativas base del cliente:
  - `usePullJobsSync` espera hidratacion de permisos
  - lee y persiste checkpoints de sync
  - emite refresh local de jobs al finalizar importacion relevante
  - `useBootstrapJobsFromApi` persiste el checkpoint maximo de `sync/v3/state`
  - `app/_layout.tsx` refresca bootstrap y autosync ante `sync_hint` con scopes relevantes
- se endurecio `sisa.ui/contexts/AuthContext.tsx` para que una sesion ya autenticada pueda reabrirse offline aunque el token local haya vencido, manteniendo la app operable hasta logout manual
- se expandio `sisa.ui/scripts/sync-smoke.js` para bloquear regresiones de restauracion offline de sesion persistida y del hydrator comun de auth
- se actualizaron `sisa.ui/docs/architecture/authentication.md`, `sisa.ui/docs/architecture/startup-and-shell.md` y `qa/REGRESSION_CHECKLIST.md` para declarar este contrato como parte del baseline Tier A
- se expandio `qa/MULTI_DEVICE_RUNBOOK.md` con un escenario explicito de reapertura offline con sesion previa y se ajusto `qa/MULTI_DEVICE_EVIDENCE_TEMPLATE.md` para auditar esa corrida manual

Validacion:

- `npm run check:sync-smoke` -> pasa
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

Notas:

- este primer slice de Milestone 4 todavia es smoke estructural, no test unitario del cliente
- el foco fue mantener alineado el lado UI con los contratos de no reaparicion ya reforzados en backend
- este segundo slice baja la misma garantia a `bootstrap/references`, evitando que el cliente reanime referencias eliminadas al reconstruir cache local
- este tercer slice refuerza el lado cliente de `reconcile`, dejando controlado que el drift detectado por servidor quede persistido localmente y visible para resolucion posterior
- este cuarto slice deja cubiertos los minimos operativos de Milestone 4: persistencia local, checkpoints, bootstrap, consumo de hints y smokes de no reaparicion en el cliente
- este quinto slice agrega una guarda explicita para continuidad de sesion offline: una autenticacion previa ya no depende de conectividad al reabrir la app para seguir operando en campo
- el milestone manual ahora tambien exige evidencia repetible de que la shell autenticada reabre sin red despues de un login previo

### Milestone 5 - runbook multi-dispositivo y offline-to-online

Estado: en progreso

Objetivo:

- volver ejecutables y auditables los escenarios manuales mas criticos de convergencia multi-dispositivo

Avance en esta etapa:

- se creo `qa/MULTI_DEVICE_RUNBOOK.md`
- el runbook define preparacion, evidencia obligatoria, regla de aprobacion y cinco escenarios manuales:
  - create offline y convergencia
  - delete propagation sin reaparicion
  - conflicto visible y resoluble
  - orden de dependencias
  - bootstrap limpio de dispositivo nuevo
- se creo `qa/MULTI_DEVICE_EVIDENCE_TEMPLATE.md` como formato reutilizable para registrar corridas manuales de Milestone 5
- se creo `qa/evidence/README.md` y `qa/evidence/2026-04-13-escenario-base.md` para dejar la estructura operativa lista para registrar corridas reales
- se preparo `qa/evidence/2026-04-13-escenario-delete-propagation.md` como primera corrida manual real recomendada para validar no reaparicion
- se selecciono `status` como primer tipo de entidad para la corrida manual real de delete propagation
- se actualizo `qa/REGRESSION_CHECKLIST.md` para apuntar explicitamente al runbook
- se actualizo `qa/REGRESSION_CHECKLIST.md` para apuntar tambien a la plantilla de evidencia
- se actualizo `QA_ROADMAP.md` para declarar `qa/MULTI_DEVICE_RUNBOOK.md` como entregable minimo del milestone

Validacion:

- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

Notas:

- este primer slice de Milestone 5 no automatiza dispositivos reales; deja un procedimiento manual estricto y auditable
- la prioridad del runbook esta alineada con no reaparicion, convergencia y control de drift visible en campo
- este segundo slice endurece el milestone con un formato de evidencia repetible para que futuras corridas no dependan de memoria de sesion

## Intervenciones documentales recientes

- se tradujo al espanol la documentacion QA agregada en raiz y `qa/` para mantener consistencia con el idioma operativo del proyecto.

## Tests de Integracion Multi-Dispositivo

Estado: en progreso

Objetivo:

- simular dispositivos multiples con SQLite real y validar flujos de sync distribuido

Avance en esta etapa:

- se creo `sisa.api/tests/Integration/MultiDevice/TestDevice.php` con:
  - base de datos SQLite por dispositivo
  - operaciones de sync (addSyncOperation, getPendingOperations, markOperationsSynced)
  - manejo de tombstone (softDelete, findByUuidIncludingDeleted)
  - checkpoint y sync state
- se creo `sisa.api/tests/Integration/MultiDevice/DeletePropagationTest.php` con 6 tests:
  - delete de status/client/provider/attachment no resurrect en otro dispositivo
  - delete con multiples dispositivos (3 dispositivos)
  - bootstrap con entidades eliminadas no resurrect
- se creo `sisa.api/tests/Integration/MultiDevice/MultiCompanyIsolationTest.php` con 5 tests:
  - Company 1 no puede ver statuses/clients/providers de Company 2
  - Status global (company_id null) visible para todas las empresas
  - Delete en Company 1 no afecta datos de Company 2
  - drift detectado cuando cliente tiene version mas nueva
  - server wins en resolucion de conflictos
  - delete aplicado a pesar de drift
  - ediciones concurrentes generan drift

Validacion:

- `vendor/bin/phpunit tests/Integration/MultiDevice/` -> 119/119 pass
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

## Correccion de version drift en sync_operations

Estado: completado

Problema: `sync_operations.version` no coincidia con la version real de la tabla principal. Por ejemplo, un appointment con `version = 9` en la tabla podia tener `version = 1` en `sync_operations`.

Causa raiz:
- En `sync/push`, se guardaba primero la version del payload del cliente (a veces 1)
- Despues se llamaba `buildCanonicalOperation` para obtener la version canonica
- Si este paso fallaba o no se actualizaba, quedaba con version incorrecta

Correcciones aplicadas:

1. `sisa.api/src/Services/SyncEventGenerator.php`:
   - Se modifico `buildCanonicalOperation` para usar `canonicalRecord['version']` como fuente principal
   - El payload version ahora es fallback, no fuente primaria
   - Formula cambiada de: `max(1, (int) ($payload['version'] ?? ($canonicalRecord['version'] ?? 1)))`
   - A: `max(1, (int) ($canonicalRecord['version'] ?? ($payload['version'] ?? 1)))`

2. `sisa.api/src/Controllers/SyncOperationsController.php`:
   - Se agrego logging cuando `canonicalOperation` es null para detectar fallos
   - Linea agregada: `error_log('SyncOperationsController version drift warning: ...')`

3. Tests creados:
   - `sisa.api/tests/Controllers/SyncVersionDriftRegressionTest.php` - tests de regresion
   - `sisa.api/tests/Services/SyncEventGeneratorVersionTest.php` - tests unitarios de logica de version

Validacion:

- `vendor/bin/phpunit tests/Services/SyncEventGeneratorVersionTest.php` -> 5/5 pass
- `vendor/bin/phpunit tests/Integration/MultiDevice/` -> 119/119 pass
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

Notas:

- La correccion prioriza la version de la tabla canonica sobre el payload
- El logging permitira detectar en produccion si `canonicalOperation` es null
- Los tests de regresion verifican que las versiones coincidan
- Este fix ataca una de las deudas mas criticas del pipeline de sync

Notas de lectura:

- la infraestructura de TestDevice permite расширение facile para mas escenarios
- se puede agregar mas coverage de multi-empresa y multi-usuario expandiendo TestDevice
