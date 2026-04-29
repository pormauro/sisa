# Estado QA

## Ultima actualizacion

- Fecha: 2026-04-29
- Corrida baseline: PASS
- PHPUnit suite: ~60 tests pasan (ruido de conexion filtrado)
- Lint: PASS
- Cache guard: PASS
- Sync smoke: PASS
- Tests integracion multi-dispositivo: 119/119 PASS
- Transformacion de reportes: COMPLETADA

## Avance parcial - limpieza final de jobs legacy

Estado: en progreso

## Avance parcial - categorias contables por empresa y visibilidad individual

Estado: en progreso

## Avance parcial - estabilidad de refresh en jobs/worklogs

Estado: en progreso

## Avance parcial - startup protegido y operacion estable

Estado: en progreso

Que cambio:

- `sisa.ui/contexts/OperationGuardContext.tsx`, `sisa.ui/hooks/useOperationBlock.ts` y `sisa.ui/app/_layout.tsx` agregan una guarda global de operacion que detecta formularios/ediciones activas y difiere tareas de refresh hasta que el usuario salga del flujo critico
- `sisa.ui/contexts/AuthContext.tsx`, `sisa.ui/contexts/PermissionsContext.tsx`, `sisa.ui/contexts/BootstrapContext.tsx`, `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` y `sisa.ui/contexts/TrackingContext.tsx` ahora dejan en cola los refresh de foreground, bootstrap post-hint, autosync de jobs y autosync de tracking cuando hay una operacion activa, evitando que esos rebotes globales pisen pantallas vivas
- `sisa.ui/app/jobs/[id].tsx`, `sisa.ui/app/jobs/worklog-form.tsx`, `sisa.ui/app/invoices/create.tsx`, `sisa.ui/app/invoices/[id].tsx` y `sisa.ui/app/receipts/create.tsx` marcan edicion activa mientras hay draft/saving, de modo que los refresh globales se postergan hasta terminar la operacion
- `sisa.ui/contexts/ProfilesListContext.tsx` deja de auto-fetchear `/profiles` en el startup global; `sisa.ui/app/tracking/daily-route.tsx` lo pide on-demand cuando esa pantalla realmente se usa, recortando IO del arranque
- `sisa.ui/contexts/ConfigContext.tsx` y `sisa.ui/contexts/MemberCompaniesContext.tsx` ahora deduplican requests en vuelo para bajar requests paralelos redundantes durante bootstrap
- `sisa.ui/config/Index.ts` vuelve a dejar apagados los flags de debug runtime de push/device/jobs/cache para reducir ruido y costo del arranque normal
- `sisa.ui/scripts/startup-stability-smoke.js`, `sisa.ui/package.json`, `qa/run-baseline.ps1` y `qa/REGRESSION_CHECKLIST.md` agregan una smoke automatizada + checklist manual especificos para startup protegido y no perdida de drafts

Riesgo cubierto:

- que el usuario pierda cambios locales por refresh de auth/permisos/bootstrap/sync mientras ya esta editando en campo
- que el startup siga inyectando IO global no esencial y provoque rerenders perceptibles despues de mostrar la shell

Puntos ciegos conocidos:

- la validacion automatica nueva es estructural (smoke por codigo) y no reemplaza la prueba manual en dispositivo real con background/foreground, reconexion y `sync_hint`
- el baseline compartido quedo bloqueado por una deuda preexistente de backend en `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php:1033`, fuera de los archivos tocados en esta sesion

Validacion parcial:

- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS
- `npm run check:sync-smoke` en `sisa.ui` -> PASS
- `npm run check:startup-stability` en `sisa.ui` -> PASS
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> FAIL por mismatch de firma en PHPUnit backend (`TestableSyncOperationsControllerForReferences::listCategoriesForSync`), tratado como bloqueo/deuda de baseline no introducida por este cambio frontend

Que cambio:

- `sisa.ui/app/jobs/[id].tsx` ya no vuelve a hidratar el formulario local mientras hay cambios sin guardar; los pulls/reloads al recuperar foco se pausan durante la edicion para evitar que descripcion, cliente, estado, carpeta o prioridad salten al valor anterior
- `sisa.ui/app/jobs/worklogs.tsx` pausa los auto-refresh de `worklogs` y del selector de trabajos relacionados mientras el modal de alta/edicion esta abierto, reduciendo refrescos que interrumpian la edicion en campo
- `sisa.ui/src/modules/jobs/presentation/hooks/useJobDetail.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/useJobsList.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/useWorkLogs.ts` ahora permiten desactivar la suscripcion de auto-refresh por pantalla cuando el flujo necesita preservar un draft local
- seguimiento: el refresh repetido no venia solo del autosync sino tambien de hooks derivados (`worklogs`, `job_items`, `appointments`, `groups`, `root causes`, `history`) que recreaban `reload()` cuando cambiaba el largo de sus colecciones; se estabilizaron con banderas `hasLoaded*` para cortar la cascada de recargas durante la hidratacion inicial
- seguimiento 2: el detalle de job todavia seguia suscripto a refresh aun con draft activo porque `useJobDetail()` habia quedado invocado sin la bandera `autoRefreshEnabled`; ahora se vuelve a cortar esa suscripcion cuando el snapshot local del formulario diverge del ultimo hydrate conocido
- seguimiento 3: el modal de `worklogs` seguia reseteando fecha/hora si se abria y se editaba demasiado rapido, porque se montaba oculto y la inicializacion real corria en un `useEffect` posterior a `visible=true`; ahora el formulario se crea solo al abrirse y nace ya hidratado con su estado inicial, evitando que un efecto tardio pise cambios de fecha/hora hechos apenas entra el usuario
- seguimiento 4: el listado `jobs` quedaba montado debajo de `/jobs/[id]` y `/jobs/worklogs`, manteniendo hooks por tarjeta (`useWorkLogs` y `useJobItems`) activos aun fuera de foco; se agrego una bandera `enabled` en hooks y el listado ahora apaga esas cargas/auto-refresh cuando la ruta activa ya no es `/jobs`, reduciendo el bucle de renders en background mientras se edita un worklog
- seguimiento 5: el `BootstrapProvider` estaba relanzando el bootstrap critico cada vez que cambiaba el JWT por refresh/login silencioso, incluso con la app ya lista; ahora salta esos reruns cuando la sesion ya esta operativa y deja de usar el token dentro de la clave de `startup-bootstrap`, reduciendo la cascada de renders globales que seguia viva sobre `/jobs/worklogs`
- seguimiento 6: el detalle de job seguia montado por debajo de `/jobs/worklogs` y conservaba hooks vivos (`useJobDetail`, `useWorkLogs`, `useJobItems`, `useJobAppointments`, `useJobGroups`, `useRootCauses`, `useJobStatusHistory`); ahora esos hooks aceptan `enabled` y `sisa.ui/app/jobs/[id].tsx` se autoapaga fuera de la ruta exacta del detalle, recortando otra fuente de renders mientras el modal del worklog esta abierto
- seguimiento 7: el `WorkLogFormModal` seguia re-renderizando en cascada cada vez que `JobWorkLogsScreen` cambiaba por ruido de providers globales; ahora el modal esta memoizado y sus handlers principales (`onSave`, `onClose`, `onEdit`, `onOpen`) quedaron estabilizados con `useCallback`, para que los cambios de contexto ajenos no repinten el formulario si sus props efectivas no cambiaron
- seguimiento 8: se retiro el flujo principal de alta/edicion desde modal y se movio a una pantalla dedicada `sisa.ui/app/jobs/worklog-form.tsx`; los accesos desde `Home`, `jobs/[id]` y `jobs/worklogs` ahora navegan a esa ruta, desacoplando el formulario del screen listado y evitando que el picker de fecha/hora dependa de un modal superpuesto mientras el resto del arbol sigue refrescando
- seguimiento 9: se volvio al flujo pedido por QA usando `open_create` en `jobs/worklogs`, pero ahora con guardas anti-loop: `consumedOpenCreateRef` consume y limpia la URL con `router.replace`, `savingRef` bloquea doble submit en el formulario, el modal no se reabre tras guardar/reload/focus, y `JobsScreen` usa `openingJobUuid` para evitar doble navegacion por taps rapidos

Validacion parcial:

- `npm run lint` en `sisa.ui` -> PASS con warning preexistente en `sisa.ui/app/reports/index.tsx:191`

Que cambio:

- `sisa.api/src/Controllers/CategoriesController.php` ahora deja la administracion de categorias contables solo a owners/admins de la empresa y expone a miembros un arbol filtrado por asignacion, conservando ancestros para no romper la navegacion del arbol principal
- `sisa.api/src/Models/Categories.php`, `sisa.api/src/Services/SyncEventGenerator.php`, `sisa.api/src/Controllers/SyncOperationsController.php` y la migracion `sisa.api/scripts/migrations/categories-assigned-users-phase28.php` agregan `assigned_users` a categorias para sync/bootstrap/offline-first
- `sisa.api/src/Controllers/PaymentsController.php`, `sisa.api/src/Controllers/ReceiptsController.php` y `sisa.api/src/Services/AccountingSummaryService.php` ahora respetan visibilidad contable individual: miembros solo ven/usan sus propios movimientos salvo categorias compartidas/asignadas; owners/admins mantienen visibilidad company-scoped
- `sisa.ui/contexts/CategoriesContext.tsx`, `sisa.ui/app/categories/index.tsx`, `sisa.ui/app/categories/create.tsx` y `sisa.ui/app/categories/[id].tsx` ahora manejan asignacion de usuarios y restringen la edicion de categorias a admins/owners, manteniendo visible el arbol para usuarios alcanzados
- la cache/sync local de categorias en `sisa.ui/src/modules/jobs/data/db/schema.ts`, `sisa.ui/src/modules/jobs/data/db/jobsMigrations.ts`, `sisa.ui/src/modules/jobs/data/repositories/SQLiteCategoriesRepository.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` ya persiste `assigned_users`

Validacion parcial:

- `vendor/bin/phpunit tests/Controllers/CategoriesControllerOfflineFirstTest.php --testdox` -> PASS (4 tests, 12 assertions)
- `vendor/bin/phpunit tests/Regression/AccountingSummaryAndInvoicesRegressionTest.php --testdox` -> PASS (6 tests, 33 assertions)
- `php -l` sobre controladores/modelos/servicios tocados en `sisa.api` -> PASS
- `npm run lint` en `sisa.ui` -> PASS con warning preexistente en `sisa.ui/app/reports/index.tsx:191`

Que cambio:

- se elimino el uso operativo de `job_date`, `start_time` y `end_time` en UI, reportes e instalacion legacy
- se agrego `qa/JOBS_LEGACY_CLEANUP_CHECKLIST.md` para ordenar la limpieza restante por etapas
- se preparo la migracion `sisa.api/scripts/migrations/jobs-remove-legacy-schedule-columns-phase26.php`
- se alineo `sisa.api/install.php` y `sisa.api/update_install.php` para remover las columnas horarias legacy de `jobs`
- se migro parte del calculo y renderizado de reportes/invoices para usar `worklogs`

Pendiente principal:

- sacar de `jobs` los restos `participants`, `tariff_id`, `manual_amount` y `attached_files`
- cerrar la fuente de verdad final del precio antes de borrar `tariff_id/manual_amount`
- limpiar payloads sync/backend/frontend para que no reintroduzcan columnas legacy por compatibilidad

Avance adicional en esta sesion:

- `sisa.ui/contexts/JobsContext.tsx` ya no expone ni serializa `participants`, `tariff_id`, `manual_amount` ni `attached_files`
- `sisa.ui/app/jobs/viewModal.tsx` ahora toma participantes desde `worklogs` y adjuntos desde `file_attachments`
- `sisa.ui/hooks/useClientFinalizedJobTotals.ts`, `sisa.ui/app/clients/finalizedJobs.tsx`, `sisa.ui/app/invoices/create.tsx` y `sisa.ui/app/invoices/index.tsx` dejaron de depender de `job.manual_amount` y `job.tariff_id`, usando tarifa del cliente como politica activa
- `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` ahora limpian `participants`, `tariff_id`, `manual_amount` y `attached_files` antes de hidratar snapshots y jobs locales
- `sisa.api/src/Controllers/JobReportsController.php` ya no lee `jobs.participants`, `jobs.manual_amount`, `jobs.tariff_id` ni `jobs.attached_files`; ahora usa `worklogs`, tarifa del cliente y `file_attachments`
- se agrego `sisa.api/scripts/migrations/jobs-remove-legacy-metadata-columns-phase27.php` y se registro en `sisa.api/install.php` + `sisa.api/update_install.php` para eliminar `participants`, `tariff_id`, `manual_amount` y `attached_files` de `jobs`
- `sisa.api/src/Controllers/JobsController.php` y `sisa.api/src/Controllers/SyncOperationsController.php` ahora descartan esos campos legacy cuando reciben payloads de `jobs`
- verificado que `sisa.api/src/Services/SyncEventGenerator.php` no los emite en `serializeJob()`, por lo que la capa canonical de eventos de `jobs` ya queda alineada con el modelo limpio
- se agrego el script manual `sisa.api/scripts/migrations/jobs-backfill-legacy-finalized-worklogs-oneoff.php` para crear worklogs a partir de jobs finalizados legacy antes de correr la eliminacion de columnas de la fase 27
- el script solo migra jobs `status_id = 8` con `job_date`, `start_time` y `end_time` validos, copia participantes legacy y preserva `tariff_id/manual_amount` dentro de la descripcion del worklog como bloque trazable
- se robustecio el bootstrap del script manual para que reporte errores fatales y pueda resolver `load_env.php`/`.env` con mas tolerancia en ejecuciones manuales de servidor
- se agrego fallback de carga de `.env` sin dependencia de `phpdotenv`, para permitir corridas CLI en hosts donde `vendor/autoload.php` o `Dotenv\Dotenv` no esten disponibles en ese contexto
- completada la corrida manual del backfill de worklogs legacy; el script one-off ya fue retirado del repo para evitar reutilizacion accidental despues de la migracion efectiva
- `sisa.ui/app/jobs/worklogs.tsx` ahora permite mover un worklog desde el editor a otro trabajo del mismo cliente y redirige al nuevo trabajo cuando el movimiento se guarda
- `sisa.api/src/Controllers/WorkLogsController.php` y `sisa.api/src/Controllers/SyncOperationsController.php` ahora rechazan movimientos de worklogs hacia trabajos de otro cliente y limpian `job_item_id` si se cambia de trabajo sin item destino explicito
- `sisa.ui/app/jobs/index.tsx` ahora muestra el numero de trabajo (`#id`) en las tarjetas del listado para identificarlo rapidamente
- `sisa.ui/utils/cache.ts` y `sisa.ui/contexts/NetworkLogContext.tsx` ahora recuperan y purgan silenciosamente caches sobredimensionados de `networkLogs`, ademas de limitar lo persistido para evitar el error de `CursorWindow` al iniciar la app
- `sisa.ui/app/clients/finalizedJobs.tsx`, `sisa.ui/hooks/useClientFinalizedJobTotals.ts` y `sisa.ui/app/invoices/create.tsx` dejaron de recalcular costos de trabajos finalizados usando la tarifa actual del cliente cuando el worklog ya trae `workType` con tarifa; ahora priorizan la tarifa del worklog y solo caen al cliente como fallback
- se ajusto nuevamente la resolucion de importes para worklogs legacy migrados: si el worklog conserva el bloque `[legacy_job_fields]` en la descripcion, ahora se prioriza `manual_amount` y luego `tariff_id` antes de caer a `workType` o a la tarifa actual del cliente
- `sisa.ui/app/clients/finalizedJobs.tsx` ahora muestra debajo de cada trabajo un desglose por worklog con importe calculado, horas, participantes y origen de tarifa para diagnosticar diferencias de costos en campo antes de facturar
- se corrigio la fuente de tarifa en calculos UI: los importes de worklogs ahora se resuelven solo contra la entidad `tariffs` usando `tariff_id` legacy o `workType`; se elimino el fallback a `manual_amount` y a la tarifa actual del cliente para evitar montos derivados que no respetan la tarifa elegida
- `sisa.ui/app/invoices/create.tsx` ahora espera a que la entidad `tariffs` este cargada antes de prefijar los items de factura desde trabajos seleccionados, evitando que se congelen importes en cero por una corrida temprana del calculo
- `sisa.ui/app/clients/finalizedJobs.tsx` ahora envia a facturacion un prefill explicito con los importes ya calculados por trabajo, y `sisa.ui/app/invoices/create.tsx` lo consume via `PendingSelection`; esto evita desalineaciones entre la pantalla de trabajos finalizados y la factura nueva
- adicionalmente el prefill de trabajos a factura ahora viaja tambien serializado en params de ruta (`jobPrefill`) para sobrevivir mejor a montajes/reaperturas de pantalla y evitar perder importes al navegar desde `Trabajos finalizados`
- `sisa.api/src/Controllers/InvoicesController.php` vuelve a marcar en servidor los `jobs` enviados en `job_ids` como `Facturado` al crear una factura, registrando historia y evento de sync para mantener convergencia entre dispositivos
- `sisa.ui/app/invoices/create.tsx` ahora recarga `jobs` luego de crear la factura y del marcado local para reflejar en la app el estado `Facturado` sin depender de refresh manual
- `sisa.ui/app/jobs/index.tsx` ahora entra en modo seleccion por `long press`, permite seleccionar multiples trabajos visibles y expone acciones en lote desde el listado para cambiar estado, mover carpeta, cambiar prioridad, eliminar y facturar la seleccion (si pertenece a un unico cliente)
- `sisa.ui/app/jobs/index.tsx` ahora renderiza la carpeta del trabajo arriba de la descripcion dentro de cada tarjeta del listado
- `sisa.ui/app/appointments/create.tsx` y `sisa.ui/app/appointments/[id].tsx` ahora excluyen del selector de trabajo los jobs `cancelados` y `finalizados` (manteniendo visible el job ya asociado al editar una cita existente)
- se agrego `qa/COMPANY_STATUS_ROLE_MAPPING_FUTURE.md` para documentar la deuda futura: mover la semantica especial de estados de trabajo (`facturado`, `finalizado`, `cancelado`, etc.) a una configuracion explicita por empresa en lugar de inferirla por nombre
- `sisa.ui/app/jobs/index.tsx` ahora permite colapsar/descolapsar la barra de acciones en lote cuando hay seleccion multiple, reduciendo el espacio ocupado en pantalla sin perder el contexto de seleccion
- `sisa.ui/app/clients/finalizedJobs.tsx` ahora agrega referencia de fecha y horario trabajado en cada fila del bloque `Worklogs calculados`, para facilitar auditoria manual de importes antes de facturar
- `sisa.ui/app/jobs/index.tsx` ahora muestra en cada tarjeta los `job_items` pendientes (no tildados) para exponer el trabajo abierto sin tener que entrar al detalle
- `sisa.ui/app/jobs/index.tsx` ahora hace que el switch de listado oculte/muestre tanto trabajos `facturados` como `cancelados`, y actualiza su rotulo para reflejar ambas categorias
- `sisa.ui/app/_layout.tsx` ahora monta un observador global de sync que muestra automaticamente un `Alert` cuando aparecen operaciones en estado `error/failed`, para hacer visibles fallas de sincronizacion sin entrar manualmente a la cola
- el observador global de sync ahora evita falsos positivos por errores transitorios: solo alerta conflictos, rechazos 4xx persistentes o fallas que sobreviven al menos dos intentos, reduciendo ruido cuando la cola se recupera sola en reintentos posteriores
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteSyncRepository.ts` ahora reconcilia operaciones fallidas/bloqueadas que quedaron obsoletas despues de que el estado aceptado ya se aplico localmente; esto evita errores fantasma en la cola cuando los cambios terminaron sincronizados por otra via o reintento posterior
- `sisa.ui/app/network/logs.tsx` ahora permite copiar todo el registro de red como un JSON completo con timestamp de exportacion y todas las entradas disponibles, para diagnostico externo sin perder detalle
- se agrego `qa/STARTUP_BOOTSTRAP_SYNC_REFACTOR_PLAN.md` con el diagnostico y plan futuro para pasar de un arranque fragmentado a un bootstrap unico, company-scoped y compatible con sync v3 + lazy loading
- `sisa.ui/contexts/TrackingContext.tsx` deja de disparar `/tracking/policy` en el startup y usa `/tracking/status` como fuente de verdad para hidratar policy + status, reduciendo una request redundante
- `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` ahora bloquea el auto sync de jobs si todavia no hay empresa seleccionada, evitando pulls con `company_id = null`
- `sisa.ui/contexts/AppUpdatesContext.tsx` ya no muestra un alert de usuario si falla la comprobacion de actualizaciones durante startup; conserva cache y baja ruido en el arranque
- `sisa.ui/contexts/CompaniesContext.tsx` ya no sobrecarga el startup con `company-addresses/contacts/channels`; ahora el listado trae empresas livianas y los detalles se hidratan bajo demanda desde `sisa.ui/app/companies/[id].tsx` y `sisa.ui/app/companies/view.tsx`
- `sisa.api/src/Controllers/BootstrapController.php` y `sisa.api/src/Routes/api.php` agregan `GET /bootstrap`, un endpoint liviano company-scoped para startup con contexto de usuario, empresa, cursores de sync, tracking y datos iniciales minimos (`statuses`, `tariffs`, `clients`, `folders`)
- `sisa.ui/contexts/BootstrapContext.tsx` ahora consume ese `/bootstrap` cuando ya existe empresa seleccionada, cachea el payload por empresa y precalienta caches de referencias; ademas deja de tratar `categories` e `invoices` como parte del bootstrap critico
- `sisa.ui/contexts/StatusesContext.tsx`, `sisa.ui/contexts/TariffsContext.tsx`, `sisa.ui/contexts/ClientsContext.tsx` y `sisa.ui/contexts/FoldersContext.tsx` ahora aprovechan el cache de `startup-bootstrap:<companyId>` para hidratar referencias y aplicar una ventana de frescura inicial, reduciendo fetches redundantes justo despues del bootstrap
- `sisa.ui/contexts/CategoriesContext.tsx` e `sisa.ui/contexts/InvoicesContext.tsx` dejan de auto-fetchear al boot del provider; ahora salen del camino critico de startup y se cargan cuando una pantalla/flujo realmente los necesita
- `sisa.ui/contexts/ClientsContext.tsx` mejora la hidratacion inicial para priorizar rows locales, luego bootstrap cache por empresa y recien despues el cache legacy, evitando fetches tempranos innecesarios sin perder datos ya persistidos
- `sisa.ui/utils/startupBootstrap.ts` centraliza la lectura del cache `startup-bootstrap:<companyId>` y las versions del payload; `StatusesContext`, `TariffsContext`, `ClientsContext` y `FoldersContext` ahora usan esas versions para evitar refrescos redundantes cuando el bootstrap ya trajo la misma revision de referencias
- `sisa.ui/contexts/BootstrapContext.tsx` ahora invalida el cache `startup-bootstrap:<companyId>` cuando cambian referencias base (`statuses`, `tariffs`, `clients`, `folders`), evitando que el snapshot de arranque quede viejo despues de mutaciones o sync posteriores
- `sisa.api/src/Controllers/BootstrapController.php` ahora expone tambien `membership`, `config.company` y `features` dentro de `/bootstrap`, dejando un contrato mas listo para feature flags y configuracion company-scoped futura
- la invalidacion de `startup-bootstrap:<companyId>` ahora cubre tambien referencias de soporte como `providers`, `categories`, `products_services` y `payment_templates`, preparando el camino para ampliar el bootstrap sin arrastrar snapshots obsoletos
- `/bootstrap` ahora tambien incluye `providers`, `products_services` y `payment_templates` dentro de `versions` + `initial_data`; `ProvidersContext`, `ProductsServicesContext` y `PaymentTemplatesContext` ya aprovechan ese snapshot company-scoped para hidratar cache y evitar refreshes redundantes cuando la version no cambio
- `sisa.ui/contexts/BootstrapContext.tsx` ahora registra diagnostico de arranque por etapas y lo persiste en `startup-bootstrap-diagnostics`, incluyendo duraciones de secciones criticas y del request `/bootstrap`; esto prepara visibilidad de performance tipo startup orchestrator sin agregar ruido a usuario final
- `sisa.ui/app/network/logs.tsx` ahora expone al superusuario un bloque de diagnostico de arranque y un boton para copiar `startup-bootstrap-diagnostics` como JSON, dejando la telemetria operativa accesible sin abrir tooling externo
- el diagnostico de startup se movio a `sisa.ui/app/network/startup.tsx` para no tapar el listado de red; el superusuario ahora puede entrar a una pantalla separada, ver los pasos mas lentos y copiar tanto el JSON de startup como un export combinado de startup + logs de red
- `BootstrapContext` ahora conserva tambien el arranque anterior en `startup-bootstrap-diagnostics-previous`, y `sisa.ui/app/network/startup.tsx` muestra semaforo de performance, comparacion entre ultimo arranque y el previo, mas contexto adicional del payload bootstrap (`features/config/versions`)
- `BootstrapContext` ahora pide `/bootstrap` con `include=statuses,tariffs,folders`, dejando `clients/providers/products_services/payment_templates` fuera del payload critico por defecto; el bootstrap queda mas chico y las referencias mas pesadas siguen resolviendose por cache/version/lazy loading
- `PermissionsContext` ahora reutiliza snapshot con `fetchedAt` y aplica una TTL de 10 minutos antes de volver a pegarle al backend, reduciendo el costo del paso `permissions` en reingresos/foreground sin perder capacidad de forzar refresh cuando se limpia cache
- `/bootstrap` ahora expone `included_initial_data` y normaliza `device.sync.jobs.company_id` cuando falta; en cliente, `startupBootstrap.ts` solo considera colecciones realmente incluidas, evitando que caches viejos o snapshots previos reinyecten entidades pesadas fuera del bootstrap critico
- `CompaniesContext` ya no auto-fetchea empresas al montar el provider; ahora reutiliza cache local, aplica una TTL de 15 minutos y solo refresca desde servidor cuando una pantalla/flujo lo necesita realmente, sacando `companies` del startup critico efectivo
- se corrigio un loop de arranque en `sisa.ui/contexts/AuthContext.tsx`: el fetch global ya no intenta auto-recuperar autenticacion sobre `/token/refresh`, evitando recursion cuando la renovacion del token tambien devuelve un estado auth error
- se corrigio una regresion en `PermissionsContext`: si el usuario es superusuario o su membresia company-scoped ya indica `owner/admin`, la app vuelve a expandir permisos automaticamente aunque el snapshot local haya quedado vacio; ademas la TTL ya no evita el refresh cuando todavia no hay permisos efectivos hidratados
- `sisa.ui/config/Index.ts` vuelve a dejar en `false` los flags de debug runtime (`PUSH_FULL_DEBUG`, `PUSH_DEBUG_REPORTS`, `PUSH_*_LOGS`, `DEVICE_UID_LOGS`, `JOBS_DEBUG_LOGS`, `CACHE_DEBUG_LOGS`) para cortar el spam de consola y de registro durante uso normal
- `sisa.ui/app/jobs/index.tsx` deja de mostrar el chip/icono de conteo total de items en cada tarjeta de trabajo, porque el bloque textual de items pendientes ya cubre esa referencia y evita redundancia visual
- `sisa.ui/app/jobs/index.tsx` ahora ubica el texto `Ordenado por ...` en la misma fila de resumen que la cantidad de trabajos y los accesos de sync/refresh, compactando la cabecera del listado
- `sisa.ui/app/jobs/index.tsx` ahora desactiva el `load more` automatico cuando el listado tiene filtros restrictivos (busqueda, cliente, estados o facturados/cancelados) y agrega una ventana minima entre pulls al llegar al final, evitando loops de recarga continua en listados filtrados
- `sisa.ui/app/jobs/index.tsx` ahora permite expandir/colapsar la lista de `items pendientes` dentro de cada tarjeta, para ver todos los items sin entrar al trabajo cuando el preview inicial de 3 no alcanza
- `sisa.ui/app/clients/finalizedJobs.tsx` ahora toma como finalizados solo los trabajos con `status_id = 8` y excluye cancelados del listado dentro de clientes, evitando que la pantalla de pre-facturacion mezcle estados no operativos
- `sisa.api/src/Controllers/JobReportsController.php` ahora simplifica el PDF detallado de trabajos: elimina el timeline operativo, quita la fila de tarifa aplicada y remueve importes/subtotales dentro del bloque de worklogs; tambien deja de mostrar la fila `Creado/Iniciado/Finalizado` que venia vacia en muchos casos
- `sisa.api/src/Controllers/JobReportsController.php` ahora elimina del encabezado PDF las dos meta-cards vacias bajo el membrete y compacta el salto hacia `Detalle operativo`, recuperando espacio util en la primera pagina
- `sisa.api/src/Controllers/JobReportsController.php` ahora elimina por completo la seccion `RESUMEN ECONÓMICO` del PDF detallado de trabajos, dejando solo el detalle operativo y evitando contenido redundante al final del documento
- `sisa.api/src/Controllers/JobReportsController.php` ahora cambia la leyenda de footer a `Documento generado automáticamente por SISA` y agrega al final del PDF detallado de trabajos una aclaracion de que los costos informados no incluyen IVA

Validacion parcial:

- `npm run lint` en `sisa.ui` -> PASS con warning preexistente en `sisa.ui/app/reports/index.tsx:191`
- `php -l` en archivos tocados de `sisa.api` -> PASS
- `vendor/bin/phpunit --filter "testBuildClientJobsPdfHtmlHidesStartTimeWhenDisabled|testBuildAccountingGeneralPdfHtml" tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` -> un test nuevo pasa y queda un fallo previo/no relacionado por expectativa de titulo `Reporte economico general`

Actualizacion posterior de tests:

- `sisa.api/tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` fue corregido para validar el titulo real vigente del reporte contable
- `sisa.api/src/Controllers/JobsController.php` ahora permite inyectar `SyncEventGenerator` y `JobCascadeDeleteService`, de modo que `sisa.api/tests/Controllers/JobsControllerCrudOfflineFirstTest.php` ya no dependa del constructor real de `PaymentTemplates`
- se agrego tambien inyeccion de `FolderScopeService` en `JobsController` para terminar de aislar la suite CRUD del acceso real a base
- validacion focal actual: `vendor/bin/phpunit tests/Controllers/JobsControllerCrudOfflineFirstTest.php tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` -> PASS (12 tests, 56 assertions)

Avance adicional en PDFs:

- `sisa.api/src/Controllers/JobReportsController.php` ahora renderiza mejor worklogs crudos en HTML de PDF, derivando horario y duracion desde `started_at`/`ended_at`/`duration_minutes`
- cuando no existe snapshot tarifario, el PDF deja de mostrar `Tarifa manual` como falso fallback y pasa a mostrar `Sin tarifa definida`
- se agrego cobertura en `sisa.api/tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` para estos casos de salida HTML
- `normalizeWorkLogForPdfRender()` ahora cae al `user_id` del worklog cuando no hay participantes explicitos y deriva `ended_at_label` desde `duration_minutes` si falta `ended_at`
- `buildClientJobsPdfHtml()` y el resumen landscape ahora priorizan `worklog_total_amount` por encima de `final_amount` legacy al mostrar costos
- se agrego cobertura para asegurar que el PDF ignore `attached_files` legacy si no existen `attached_images` reales
- validacion focal actual de PDFs: `vendor/bin/phpunit tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` -> PASS (14 tests, 53 assertions)
- el resumen operativo del PDF ahora cae a los `jobs/worklogs` reales cuando `reportContext.summary` no trae conteos ni horas precalculadas
- validacion focal actualizada de PDFs: `vendor/bin/phpunit tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` -> PASS (14 tests, 56 assertions)

Avance adicional en facturacion desde trabajos:

- `sisa.ui/app/invoices/create.tsx` ahora crea un item por trabajo seleccionado usando como importe la suma de todos sus `worklogs`
- la descripcion del item paso a ser `#id_del_trabajo - descripcion_del_trabajo`
- el detalle del item ya no repite fecha ni monto dentro de la descripcion; el monto queda solo en el valor economico del item
- validacion focal UI: `npm run lint` -> PASS con warning preexistente en `sisa.ui/app/reports/index.tsx:191`

Avance adicional en reportes contables:

- `client_account_statement` ahora filtra enlaces de facturas/recibos para no arrastrar aplicaciones de otros clientes dentro del mismo PDF
- `accounting_general` ahora cae a los movimientos reales cuando `accounting_summary` llega vacio o en cero, evitando resúmenes completamente vacíos con movimientos existentes
- validacion focal backend: `vendor/bin/phpunit tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` -> PASS (16 tests, 64 assertions)
- se mejoro la legibilidad visual del PDF detallado y del landscape: header mas consistente, tarjetas de metadatos en grilla, bloque explicito de detalle operativo y resumen resaltado
## Transformacion de Reportes

Estado: completado

Que cambio:

- sistema dePDFs/reportes transformado en plataforma completa de informes
- variantes multiples: full_detailed, technical_timeline, client_account_statement, accounting_general, landscape_summary
- hub unificado de reportes en frontend
- filtros extendidos: company_id, client_id, cash_box_id, invoice_id, receipt_id, payment_id, fechas
- regeneracion con metadata persistida
- visual PDF mejorado con paginacion y saltos de pagina
- Postman actualizado con ejemplos

Archivos creados/modificados:

Backend:
- sisa.api/src/Controllers/JobReportsController.php - variantes multiples
- sisa.api/src/Controllers/ReportsController.php - CRUD + regenerate
- sisa.api/src/Models/Reports.php - filtros extendidos
- sisa.api/src/History/ReportsHistory.php - trazabilidad
- sisa.api/docs/reports-pdf-variants.md - documentacion
- sisa.api/docs/reports-table.md - schema

Frontend:
- sisa.ui/contexts/ReportsContext.tsx - estado global
- sisa.ui/app/reports/index.tsx - hub unificado
- sisa.ui/app/reports/[id].tsx - detalle
- sisa.ui/src/features/reports/components/ClientReportModal.tsx - modal compartido

Documentacion:
- qa/REPORTS_TRANSFORMATION_CHECKLIST.md - checklist completo
- qa/REPORTS_RUNBOOK.md - validacion manual
- sisa.api/Sistema.postman_collection.json - ejemplos actualizados

Variantes implementadas:

1. full_detailed - reporte operativo de jobs
2. technical_timeline - timeline tipo messegeria
3. client_account_statement - estado de cuenta con aging
4. accounting_general - resumen contable por caja
5. landscape_summary - resumen horizontal

Validacion:
- vendor/bin/phpunit --filter Reports -> 7 tests pass
- powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1 -> pasa

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

- Fase: base inicial de QA establecida + transformacion de reportes completada
- Principio activo: el QA de sync es generico y prioriza la operacion en campo, no solamente `jobs`
- Topologia confirmada: raiz compartida mas dos proyectos independientes (`sisa.api`, `sisa.ui`)
- Existe un helper compartido de baseline y actualmente pasa en este entorno
- Se corrigio un problema de runtime ligado a handles SQLite liberados que podia romper corridas manuales en dispositivo
- Se documentaron 5 escenarios manuales en runbook multi-dispositivo
- Se cubrio delete propagation con tests automatizados completos (tombstones en server, pull, bootstrap, reconcile, verify)
- Transformacion de reportes completada: variantes multiples, hub unificado, filtros extendidos, regeneracion habilitada

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
