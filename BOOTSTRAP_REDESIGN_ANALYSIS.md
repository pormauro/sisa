# Rediseño del Bootstrap Inicial de SISA

## Resumen ejecutivo brutal

El bootstrap actual de SISA móvil está demasiado cargado y mezcla responsabilidades que deberían estar separadas.

Problemas reales detectados:

- El shell depende de datos de módulo, especialmente `jobs`, `worklogs`, `appointments`, referencias operativas y checkpoints.
- `BootstrapProvider` no es solo bootstrap de identidad. Coordina perfil, configuración, empresas, permisos, jobs, sync incremental, referencias, media warmup y revalidaciones.
- `app/_layout.tsx` monta casi todos los providers de negocio desde el inicio, incluyendo contabilidad, jobs, archivos, pagos, recibos, reportes, productos, tarifas, tracking y notificaciones.
- Hay cargas duplicadas entre `ConfigContext`, `PermissionsContext`, `MemberCompaniesContext`, `CompaniesContext` y `BootstrapProvider`.
- El login llama `/profile` y luego el bootstrap llama `/user_profile`. No son exactamente el mismo contrato, pero reparten identidad/perfil en dos pasos.
- El primer arranque puede bloquear por jobs: si no hay checkpoint local, llama `/sync/v3/bootstrap/jobs`; después ejecuta sync incremental y después `/bootstrap`.
- El endpoint `/bootstrap` actual no es un identity bootstrap. Devuelve identidad parcial, empresa, membresía, device cursors, config, tracking y referencias como clientes, folders, providers, productos/servicios, tarifas y templates.
- La arquitectura actual asume que `ready` significa “muchas cosas sincronizadas”, cuando debería significar “sesión segura + empresa activa + permisos mínimos”.
- La app soporta offline, pero el camino crítico todavía intenta demasiadas redes antes de permitir shell.
- `sisa.web` no parece ser dependencia directa del bootstrap mobile. Tiene su propio `session-context` y módulos web, pero no se detectó acoplamiento crítico con el arranque móvil.

## Diagrama textual del bootstrap actual

```text
App abre
  app/_layout.tsx
    importa networkSniffer
    preventAutoHideAsync
    getDatabase()
    primeMemoryCacheFromStorage()
    load fonts
    monta árbol global completo

Providers globales montados antes de shell
  AuthProvider
  FilesProvider
  ProfileProvider
  ProfilesProvider
  ProfilesListProvider
  ConfigProvider
  ThemeProvider
  CashBoxesProvider
  CompaniesProvider
  MemberCompaniesProvider
  PermissionsProvider
  CompanyMembershipsProvider
  ClientsProvider
  ProvidersProvider
  CategoriesProvider
  ProductsServicesProvider
  StatusesProvider
  JobPrioritiesProvider
  TariffsProvider
  JobsProvider
  JobItemsProvider
  PaymentTemplatesProvider
  AccountsProvider
  AccountingEntriesProvider
  TransfersProvider
  ClosingsProvider
  PaymentsProvider
  InvoicesProvider
  QuotesProvider
  ReceiptsProvider
  ReportsProvider
  FoldersProvider
  NotificationsProvider
  PendingSelectionProvider
  BootstrapProvider
  DeferredFeatureProviders
  ExpoPushTokenLogger
  JobsSyncAutoRunner
  PendingMediaSyncAutoRunner
  SyncErrorAlertObserver
  RootLayoutContent
```

Al abrir la app:

```text
AuthProvider.autoLogin()
  lee SecureStore:
    token
    user_id
    username
    password
    token_expiration
    email

  si token válido:
    hydrateStoredSession()
    set token/userId/username/email

  si token inválido o ausente pero hay username/password:
    login(username, password)
      POST /login
      GET /profile
      guarda SecureStore
      set token/userId/username/email

  si no hay sesión:
    isLoading=false
    login screen
```

Después de login exitoso:

```text
AuthProvider.performLogin()
  ensureDeviceUid()
  POST /login
  GET /profile
  save token/user/password/email
  token cambia

BootstrapProvider detecta token
  runBootstrap()

runBootstrap()
  bloqueante:
    Promise.all:
      loadProfile()             -> GET /user_profile, SQLite users
      loadConfig()              -> GET /user_configurations
      loadCompanies()           -> cache companies + GET /companies
      loadMemberCompanies...    -> GET /companies/member?status=approved&role=owner,admin,member

  después:
    lee cache config
    loadMemberCompanies() otra vez
    resuelve selected-company-id
    set selected-company-id cache

  bloqueante:
    refreshPermissions(force)   -> GET /permissions/user/{userId}?company_id=X
      lee snapshot AsyncStorage
      lee SQLite permissions
      escribe SQLite permissions
      escribe AsyncStorage permissions snapshot

  si no hubo fallo crítico:
    readStoredCheckpoint()      -> SQLite sync_checkpoints

    si no hay checkpoint:
      bootstrapJobsFromApi()
        waitForPermissionsHydration()
        cleanupDuplicateRows()
        GET /sync/v3/bootstrap/references con include enorme
        escribe snapshots/id maps SQLite
        mergea caches de referencias
        GET /sync/v3/bootstrap/jobs paginado
        escribe jobs/job_items/worklogs/appointments/groups/root_causes/attachments
        set checkpoint

    siempre:
      pullJobsSync()
        GET /sync/v3/events
        aplica operaciones incrementales
        actualiza checkpoint

    requestStartupBootstrap()
      GET /bootstrap?company_id=X&include=statuses,job_priorities,tariffs,folders,clients,providers,products_services,payment_templates,memberships,member_companies
      escribe @sisa:data:startup-bootstrap:X
      mergea caches de referencias

  set criticalReady=true
  set isReady=true
```

Después de `isReady`:

```text
RootLayoutContent permite /Home
BottomNavigation aparece

DeferredFeatureProviders espera 2.5s
  habilita AppointmentsProvider en /Home o rutas appointment
  habilita TrackingProvider en /tracking
  habilita AppUpdatesProvider en /Home

JobsSyncAutoRunner
  espera rutas app y 12s
  puede ejecutar sync de jobs o pull incremental

PendingMediaSyncAutoRunner
  revisa cola de medios si hay token, no offline y no operation active

ExpoPushTokenLogger
  con isReady y username registra push token/device
  ante sync_hint puede llamar refreshBootstrap()
```

## Operaciones bloqueantes

| Operación | Archivo | Motivo |
|---|---|---|
| `primeMemoryCacheFromStorage()` | `sisa.ui/app/_layout.tsx` | Bloquea hide splash/appReady |
| `Font.loadAsync()` | `sisa.ui/app/_layout.tsx` | Bloquea appReady |
| `autoLogin()` | `sisa.ui/contexts/AuthContext.tsx` | Bloquea navegación auth |
| `POST /login` | `sisa.ui/contexts/AuthContext.tsx` | Login interactivo |
| `GET /profile` durante login | `sisa.ui/contexts/AuthContext.tsx` | Resolver userId/email |
| `GET /user_profile` | `sisa.ui/contexts/ProfileContext.tsx` | Considerado crítico por bootstrap |
| `GET /user_configurations` | `sisa.ui/contexts/ConfigContext.tsx` | Considerado crítico por bootstrap |
| `GET /companies` | `sisa.ui/contexts/CompaniesContext.tsx` | Considerado crítico por bootstrap |
| `GET /companies/member` | `sisa.ui/contexts/MemberCompaniesContext.tsx` | Considerado crítico por bootstrap |
| `GET /permissions/user/{id}` | `sisa.ui/contexts/PermissionsContext.tsx` | Crítico correcto, pero contrato separado |
| SQLite `sync_checkpoints` | `sisa.ui/contexts/BootstrapContext.tsx` | Decide bootstrap jobs |
| `/sync/v3/bootstrap/jobs` | `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` | Incorrectamente crítico |
| `/sync/v3/events` | `usePullJobsSync` | Incorrectamente crítico |
| `/bootstrap` | `sisa.ui/contexts/BootstrapContext.tsx` | Incorrectamente crítico por incluir referencias pesadas |

## Operaciones no bloqueantes actuales

| Operación | Archivo |
|---|---|
| Registro de device | `AuthContext.tsx`, `ExpoPushTokenLogger` |
| Push token | `app/_layout.tsx` |
| Appointments diferido | `DeferredFeatureProviders` |
| Tracking diferido por ruta | `DeferredFeatureProviders` |
| Jobs autosync con delay | `JobsSyncAutoRunner.tsx` |
| Pending media sync | `PendingMediaSyncAutoRunner.tsx` |
| Warmup de imágenes/perfiles | `BootstrapContext.tsx` después de `isReady` |

## Operaciones duplicadas

| Duplicación | Impacto |
|---|---|
| Login llama `/profile` y bootstrap llama `/user_profile` | Perfil repartido en contratos distintos |
| `ConfigContext` auto carga config y `BootstrapProvider` llama `loadConfig` | Red/cache repetidos |
| `PermissionsContext` auto ejecuta `fetchPermissions()` y `BootstrapProvider` fuerza `refreshPermissions()` | Fetch duplicable |
| `MemberCompaniesContext.loadMemberCompaniesWithStatus()` y luego `loadMemberCompanies()` | Mismo endpoint potencialmente reutilizado por in-flight, pero conceptualmente duplicado |
| `/sync/v3/bootstrap/references` y luego `/bootstrap` con referencias similares | Dos contratos de referencias para arranque |
| `CompaniesContext` hidrata startup bootstrap y también `/companies` | Puede traer empresas completas antes de necesitar detalles |
| `Jobs bootstrap` carga referencias de contabilidad y luego providers globales también pueden cargar contabilidad | Mezcla fuerte |

## Operaciones paralelizables o movibles a background

| Operación | Recomendación |
|---|---|
| `loadProfile`, `loadConfig`, `loadCompanies`, `loadMemberCompanies` | Reemplazar por `/bootstrap/identity` compacto |
| `permissions + membership + active company` | Un solo contrato identity |
| `startupBootstrap` de referencias livianas | Background Warmup |
| Jobs checkpoint/events | Lazy o warmup, nunca shell gate |
| Notifications | Background o lazy |
| Media queue | Background con condiciones |
| Tracking policy | Identity mínimo solo si afecta permisos; resto lazy |

## Operaciones que no deberían estar en bootstrap

| Dato/Módulo | Motivo |
|---|---|
| Jobs | Módulo pesado |
| Worklogs | Módulo jobs |
| Job items | Módulo jobs |
| Attachments/files | Pesado y offline-first propio |
| Appointments | Módulo calendario/jobs |
| Tracking GPS | Permisos/device/runtime específicos |
| Payments | Contabilidad |
| Receipts | Contabilidad |
| Invoices | Contabilidad |
| Cash boxes | Contabilidad |
| Products/services | Catálogo operativo |
| Providers completos | Catálogo |
| Clients completos | Catálogo |
| Folders completos | Módulo/files/jobs |
| Reports | Módulo analítico |
| Quotes | Módulo comercial |

## Tabla de endpoints actuales durante login/bootstrap

| Endpoint | Consumidor | Bloquea shell | Observación |
|---|---|---:|---|
| `POST /login` | `AuthContext.performLogin` | Sí | Correcto |
| `GET /profile` | `AuthContext.performLogin` | Sí | Solo para userId/email; debería venir en login o identity |
| `POST /token/refresh` | `AuthContext.refreshTokenSession` | A veces | Correcto para session gate |
| `POST /devices/register` | `AuthContext`, push logger | No | Correcto diferido |
| `GET /user_profile` | `ProfileContext.loadProfile` | Sí | Debería integrarse o cache-first no bloqueante |
| `GET /user_configurations` | `ConfigContext.loadConfig` | Sí | Solo default company/min theme debería ser crítico |
| `GET /companies` | `CompaniesContext.loadCompanies` | Sí | Demasiado amplio para identity |
| `GET /companies/member?status=approved&role=owner,admin,member` | `MemberCompaniesContext` | Sí | Necesario pero debería ser mínimo |
| `GET /permissions/user/{userId}?company_id=X` | `PermissionsContext` | Sí | Necesario, pero mejor incluido en identity |
| `GET /sync/v3/bootstrap/references` | `useBootstrapJobsFromApi` | Sí si no checkpoint | Incluye contabilidad, clientes, folders, usuarios, permisos, memberships, job groups |
| `GET /sync/v3/bootstrap/jobs` | `useBootstrapJobsFromApi` | Sí si no checkpoint | No debe estar en bootstrap crítico |
| `GET /sync/v3/events` | `usePullJobsSync` | Sí | No debe bloquear shell |
| `GET /bootstrap` | `BootstrapProvider.requestStartupBootstrap` | Sí | Actual contrato es demasiado amplio |
| `GET /notifications` | `NotificationsContext` manual | No automático directo | No debe bloquear |
| `GET /receipts`, `/payments`, `/invoices`, `/quotes`, etc. | Providers globales | Varía | Providers montados globalmente, riesgo de carga temprana |

## Clasificación por niveles

| Nivel | Datos/Módulos |
|---|---|
| Nivel 0 obligatorio | JWT válido o sesión offline restaurable, user id/username/email básico, empresa activa válida, membresía actual mínima, rol actual, permisos mínimos, `selected-company-id`, device uid local, estado online/offline |
| Nivel 1 background | Config extendida, tema/preferencias visuales, member companies completas, lista mínima de empresas, notificaciones, push/device registration, server time, feature flags mínimas, sync cursors/checkpoints, catálogos muy livianos si son necesarios para Home |
| Nivel 2 lazy | Jobs, worklogs, job items, appointments, file attachments, files metadata remota, tracking GPS, reports, accounting, cash boxes, payments, receipts, invoices, quotes, clients completos, providers completos, products/services, tariffs completos, categories, folders, job groups, root causes |

## Diagrama textual del bootstrap propuesto

```text
Capa A: Session Gate
  restaura sesión local
  valida expiración JWT
  refresca token si hay red
  permite sesión offline si hay sesión previa válida/aceptable
  no carga permisos, empresas, jobs, config extendida ni datos de negocio

Capa B: Identity Bootstrap
  user básico
  active_company
  membership/role actual
  permissions mínimos
  selected company
  server_time
  feature flags mínimas
  cursors mínimos
  cache local primero
  GET /bootstrap/identity si hay red
  fallback cache si offline

Capa C: App Shell
  muestra Home, menú, navegación y estado offline/degradado
  permite módulos visibles según permisos
  no espera jobs, contabilidad, tracking, adjuntos ni reportes

Capa D: Background Warmup
  corre después de shell_ready
  precarga config extendida, member companies completas, notificaciones, referencias livianas, checkpoints, device registration y push token
  tiene límite de concurrencia, cancelación y tolerancia offline

Capa E: Module Lazy Loader
  por ruta/módulo
  jobs bootstrap/sync
  tracking bootstrap
  accounting bootstrap
  files bootstrap
  clients/providers/products
```

## Contrato de estados propuesto

| Estado | Usuario ve | Acciones permitidas | Datos obligatorios | Errores |
|---|---|---|---|---|
| `unauthenticated` | Login | Login, recuperar password | Ninguno | Credenciales inválidas visibles |
| `restoring_session` | Splash/loading corto | Ninguna o cancelar si demora | SecureStore token/user | Fallos de storage recuperables; token inválido lleva a login |
| `authenticated_missing_identity` | Loading identidad o shell degradado si hay cache parcial | Logout, reintentar | Token/session | Falta empresa/permisos |
| `bootstrapping_identity` | Loading corto con timeout | Logout, reintentar | User básico, empresa, permisos mínimos | Red falla -> cache; auth falla -> login |
| `shell_ready` | Home/menu | Navegar módulos permitidos | JWT o sesión offline, user, active company, permisos | Warnings no bloqueantes |
| `background_syncing` | Shell con indicador discreto | Uso normal | Shell ready | Fallos silenciosos medidos, no bloquean |
| `module_loading` | Skeleton del módulo | Salir del módulo, reintentar | Shell ready + permiso módulo | Error por módulo |
| `degraded_offline` | Shell con banner offline | Usar datos locales, cola offline | Sesión local + identity cache | Recupera al volver red |
| `fatal_bootstrap_error` | Pantalla de error controlada | Reintentar, logout, soporte | Ninguno confiable | Solo auth inválida, sin identity cache, sin empresa o permisos |

## Endpoint propuesto `/bootstrap/identity`

Sí conviene crear un endpoint compacto.

```http
GET /bootstrap/identity
Authorization: Bearer <token>
X-Device-Uid: <device>
X-Company-Id: <optional selected company>
```

Respuesta sugerida:

```json
{
  "ok": true,
  "server_time": "2026-05-31T00:00:00Z",
  "user": {
    "id": 123,
    "username": "mauro",
    "email": "..."
  },
  "active_company": {
    "id": 10,
    "name": "Empresa",
    "legal_name": "Empresa SA"
  },
  "membership": {
    "id": 55,
    "company_id": 10,
    "role": "admin",
    "status": "approved"
  },
  "memberships": [
    { "id": 55, "company_id": 10, "role": "admin", "status": "approved" }
  ],
  "permissions": ["listJobs", "createJob"],
  "is_company_admin": true,
  "feature_flags": {
    "jobs_sync_v3": true,
    "offline_identity_cache": true
  },
  "sync_cursors": {
    "jobs": { "last_checkpoint": 1234 },
    "references": { "last_checkpoint": 5678 }
  }
}
```

No debe devolver:

| No incluir | Motivo |
|---|---|
| Jobs | Pesado |
| Worklogs | Pesado |
| Adjuntos | Pesado |
| Contabilidad | Dominio separado |
| Reportes | Lazy |
| Tracking points | Runtime/módulo |
| Clientes/proveedores completos | Catálogos |
| Productos/servicios | Catálogos |
| Tarifas/categorías completas | Catálogos |

## Endpoints por módulo propuestos

| Endpoint | Cuándo |
|---|---|
| `GET /modules/jobs/bootstrap` | Al entrar a Jobs o warmup explícito |
| `GET /modules/tracking/bootstrap` | Al entrar a Tracking |
| `GET /modules/accounting/bootstrap` | Al entrar a contabilidad |
| `GET /modules/files/bootstrap` | Al entrar a archivos/galería |
| `GET /modules/references/bootstrap` | Si un módulo necesita catálogos |

Regla: ningún endpoint `/modules/*/bootstrap` puede ser requisito para `shell_ready`.

## Archivos que hay que tocar

| Archivo | Motivo |
|---|---|
| `sisa.ui/contexts/AuthContext.tsx` | Separar Session Gate y medición |
| `sisa.ui/contexts/BootstrapContext.tsx` | Reducir a identity o reemplazar |
| `sisa.ui/contexts/PermissionsContext.tsx` | Evitar auto-fetch duplicado, aceptar identity payload |
| `sisa.ui/contexts/CompaniesContext.tsx` | Separar empresa activa/lista completa/detalles |
| `sisa.ui/contexts/MemberCompaniesContext.tsx` | Membership mínima vs lista completa |
| `sisa.ui/contexts/ConfigContext.tsx` | Config mínima vs extendida |
| `sisa.ui/app/_layout.tsx` | Reducir providers globales o diferir efectos |
| `sisa.ui/utils/cache.ts` | Instrumentación/timers de cache |
| `sisa.ui/hooks/useCachedState.ts` | Medición de hidratación |
| `sisa.ui/utils/startupBootstrap.ts` | Reubicar como warmup, no identity |
| `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` | Sacarlo del bootstrap crítico |
| `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` | Asegurar lazy/warmup |
| `sisa.api/src/Routes/api.php` | Nueva ruta `/bootstrap/identity` |
| `sisa.api/src/Controllers/BootstrapController.php` | Nuevo método identity |
| `sisa.api/src/Controllers/PermissionsController.php` | Reusar lógica para identity |
| `sisa.api/src/Controllers/CompanyUsersController.php` | Membership mínima |
| `QA_STATUS.md` | Por regla del workspace cuando avance un hito |

## Archivos que no conviene tocar todavía

| Archivo/módulo | Motivo |
|---|---|
| Repositorios SQLite de jobs | Etapa 1 es medición |
| Migraciones DB | No necesarias para medir |
| `SyncOperationsController` profundo | Evitar romper sync producción |
| Modelos de contabilidad | No mezclar contabilidad con bootstrap |
| Tracking runtime/location | No pertenece al shell |
| Files binary storage | No pertenece al shell |
| UI visual de módulos | No resuelve causa raíz |
| `sisa.web` | No parece dependencia del bootstrap móvil |

## Plan de implementación incremental

### Etapa 1

| Acción | Objetivo |
|---|---|
| Agregar logs/timers de bootstrap | Saber duración real |
| Instrumentar `fetch` de arranque | Listar endpoint, duración, status, payload aproximado |
| Medir cache reads/writes críticas | Detectar lecturas repetidas |
| Medir SQLite writes del bootstrap jobs/references | Cuantificar peso |
| Guardar resumen local `startup-bootstrap-diagnostics` | Ya existe base, ampliar sin cambiar comportamiento |

### Etapa 2

| Acción | Objetivo |
|---|---|
| Separar `session_state` de `identity_state` | Auth no debe saber de datos |
| Marcar shell listo sin jobs | Sacar jobs del gate |
| Convertir `jobsBootstrap` y `jobsCheckpoint` en warmup/lazy | Entrar más rápido |

### Etapa 3

| Acción | Objetivo |
|---|---|
| Crear `/bootstrap/identity` | Contrato compacto |
| Devolver user/company/membership/permissions/cursors mínimos | Una sola red crítica |
| Persistir identity snapshot | Offline perfecto |

### Etapa 4

| Acción | Objetivo |
|---|---|
| Jobs lazy loader | Cargar al entrar a Jobs |
| Worklogs/items/appointments dentro de jobs module | No shell |
| Files lazy | No arranque |
| Tracking lazy | No arranque |
| Accounting lazy | No arranque |

### Etapa 5

| Acción | Objetivo |
|---|---|
| Background warmup con cola | Controlar concurrencia |
| Cancelable por logout/company change | Evitar datos cruzados |
| Offline tolerant | No alertas innecesarias |

### Etapa 6

| Acción | Objetivo |
|---|---|
| Eliminar providers globales innecesarios o sus efectos automáticos | Menos trabajo inicial |
| Quitar fetch duplicados | Menos red/cache |
| Unificar config/profile/permissions identity | Menos estados a medias |

### Etapa 7

| Acción | Objetivo |
|---|---|
| Smoke startup online | Shell listo sin jobs |
| Smoke startup offline | Shell con identity cache |
| Smoke token expirado | Refresh o login |
| Smoke sin empresa | Error controlado |
| Smoke sin permisos | Error controlado |
| Smoke no llamadas a jobs antes de Home | Guardia anti-regresión |

## Riesgos

| Riesgo | Mitigación |
|---|---|
| Romper offline login | Identity snapshot cache-first antes de endpoint |
| Mostrar shell con permisos viejos | Versionar identity snapshot y marcar `degraded_offline` |
| Empresa activa inválida | Validar membership contra cache y servidor cuando haya red |
| Datos de empresa cruzados | Scope por `userId:companyId:deviceId` |
| Jobs no precargados al entrar módulo | Lazy loader con skeleton y fallback SQLite |
| Usuarios esperan ver Home con datos completos | Cambiar UX a shell rápido + estados por módulo |
| Push sync_hint fuerza bootstrap gordo | Reemplazar por invalidación selectiva/warmup |
| Providers globales siguen auto-fetch | Etapa 6 con guardias por ruta/enable |
| Medición agregue ruido | Logs con flag y resumen compacto |
| Producción activa | Mantener endpoints viejos hasta migrar completamente |

## Primer prompt de implementación para Etapa 1

```text
Implementá solo la Etapa 1 de rediseño del bootstrap de SISA. No cambies comportamiento funcional ni saques cargas todavía.

Objetivo:
Agregar instrumentación medible del arranque/login/bootstrap para saber qué endpoints se llaman, cuánto tardan, cuánto payload devuelven aproximadamente, qué cache keys se leen/escriben y cuánto dura cada sección crítica.

Reglas:
- No refactor masivo.
- No mover jobs fuera del bootstrap todavía.
- No cambiar estados `isReady`, `isBootstrapping` ni navegación.
- No romper offline.
- No modificar contratos API.
- Mantener logs detrás de flags/config si existen o usar logging discreto compatible con los diagnostics actuales.
- Actualizar `QA_STATUS.md` al finalizar.

Archivos a revisar/tocar:
- `sisa.ui/contexts/AuthContext.tsx`
- `sisa.ui/contexts/BootstrapContext.tsx`
- `sisa.ui/utils/cache.ts`
- `sisa.ui/hooks/useCachedState.ts`
- `sisa.ui/utils/networkSniffer.ts` si sirve para medir fetch
- `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts`
- `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts`
- `sisa.ui/app/_layout.tsx`
- `QA_STATUS.md`

Entregable técnico:
- Un resumen local de diagnóstico por arranque con:
  - startup id
  - timestamp
  - auth restore duration
  - login duration si aplica
  - bootstrap total duration
  - duración por sección
  - endpoint, method, status, durationMs, approximate response bytes
  - cache read/write key, durationMs, hit/miss si aplica
  - SQLite checkpoint read duration
  - si shell quedó ready, offline o failed
- Mantener o extender `startup-bootstrap-diagnostics` sin romper lectores existentes.
- Agregar logs suficientes para detectar fetch duplicados durante el arranque.

Validación:
- Ejecutar `npm run lint`
- Ejecutar `npm run check:cache`
- Ejecutar `npm run check:sync-smoke`
- Si algo falla por baseline existente, documentarlo en `QA_STATUS.md` sin ocultarlo.
```
