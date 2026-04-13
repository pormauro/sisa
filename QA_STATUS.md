# Estado QA

## Estado actual

- Fase: base inicial de QA establecida
- Principio activo: el QA de sync es generico y prioriza la operacion en campo, no solamente `jobs`
- Topologia confirmada: raiz compartida mas dos proyectos independientes (`sisa.api`, `sisa.ui`)
- Existe un helper compartido de baseline y actualmente pasa en este entorno
- Se corrigio un problema de runtime en cliente ligado a handles SQLite liberados que podia romper corridas manuales en dispositivo

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

Validacion del ajuste:

- `npm run lint` -> pasa
- `npm run check:sync-smoke` -> pasa
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

Validacion:

- `npm run check:sync-smoke` -> pasa
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> pasa

Notas:

- este primer slice de Milestone 4 todavia es smoke estructural, no test unitario del cliente
- el foco fue mantener alineado el lado UI con los contratos de no reaparicion ya reforzados en backend
- este segundo slice baja la misma garantia a `bootstrap/references`, evitando que el cliente reanime referencias eliminadas al reconstruir cache local
- este tercer slice refuerza el lado cliente de `reconcile`, dejando controlado que el drift detectado por servidor quede persistido localmente y visible para resolucion posterior
- este cuarto slice deja cubiertos los minimos operativos de Milestone 4: persistencia local, checkpoints, bootstrap, consumo de hints y smokes de no reaparicion en el cliente

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
