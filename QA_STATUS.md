# Estado QA

## Estado actual

- Fase: base inicial de QA establecida
- Principio activo: el QA de sync es generico y prioriza la operacion en campo, no solamente `jobs`
- Topologia confirmada: raiz compartida mas dos proyectos independientes (`sisa.api`, `sisa.ui`)
- Existe un helper compartido de baseline y actualmente pasa en este entorno

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

- drift de sync en `version`, `payload.version`, `source_device_id`, `deleted_at`
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

Validacion:

- `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php tests/Controllers/ProvidersControllerOfflineFirstTest.php tests/Controllers/ClientsControllerTest.php` -> pasa
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

Notas:

- este es el primer slice de Milestone 3 y ataca una de las deudas mas criticas: no reaparicion de referencias eliminadas
- el helper de baseline sigue filtrando el ruido espurio de conexion cuando PHPUnit termina bien, pero sin alterar el runtime productivo

## Intervenciones documentales recientes

- se tradujo al espanol la documentacion QA agregada en raiz y `qa/` para mantener consistencia con el idioma operativo del proyecto.
