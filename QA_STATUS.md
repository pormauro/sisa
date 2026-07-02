# Estado QA

## LOOP 8.18 - Cerrar qa_sin_permisos con empresa activa sin permisos explicitos

Fecha: 2026-07-02.

Estado: fix aplicado localmente en `sisa.web/src/services/authService.ts` y ajuste de espera E2E en `sisa.web/tests/e2e/helpers/assertions.ts`. No se tocaron backend ni seed.

Estado real reportado previo:

- `npm run qa:e2e:headed` -> `6 passed`, `1 failed`.
- Pasaron: `qa_owner_admin commercial flow`, `qa_multiempresa A/B without leaking finance permissions`, `qa_owner_admin broad company admin surface`, `qa_company_admin broad company admin surface`, `qa_tecnico technical modules without finance mutation surface`, `qa_admin_caja finance modules without technical mutation surface`.
- Unico fallo: `qa_sin_permisos gets controlled empty shell`, `selectedCompanyId = null`.

Chequeo Git/GitHub local:

- `sisa.web`: rama `main` alineada con `origin/main` antes del fix de este loop.
- El report local corresponde a cambios LOOP 8.17 (`assertions.ts:262`), por lo que el fallo real se produjo con el diagnostico nuevo disponible.

Diagnostico extraido del trace local sin registrar secretos:

- `/profile` -> `200`.
- `profile.active_company_id` -> `null`.
- `/companies/member?status=approved&role=owner,admin,member` -> `200`.
- `memberships count` -> `1`.
- Membership sanitizada: `companyId=72`, `role=member`, `status=approved`.
- `/user_configurations/default-company` no aparece como respuesta completada en el trace del fallo; `/user_configurations` de tema respondio `200` con `company_default_id=null` dentro de configuration.
- `/user_profile` quedo pendiente/cancelado en el trace (`status=-1`).
- `selectedCompanyId final` observado por el test -> `null`.
- `visibleNavLabels` -> vacio/no hidratado; la pagina estaba en `Verificando sesion...`.

Interpretacion:

- El bug no era que `/companies/member` ocultara la membresia a `qa_sin_permisos`; la API devuelve la membresia approved de company `72`.
- El bloqueo era frontend/bootstrap: `refreshSessionBootstrap()` esperaba endpoints opcionales dentro de `Promise.allSettled`; si `/user_profile` quedaba pendiente, no persistia membresias ni `selectedCompanyId` aunque `/companies/member` ya hubiera respondido.
- Un usuario sin permisos explicitos no debe necesitar permisos de modulo para tener empresa activa y shell controlado.

Fix aplicado:

- Se agrego timeout defensivo de bootstrap para que datos opcionales no bloqueen la seleccion de empresa activa.
- `refreshSessionBootstrap()` ahora limita `/profile`, `/user_profile`, tema, `/companies/member` y default-company a fallback seguro en bootstrap; en el caso diagnosticado, `/companies/member` responde antes del timeout y permite seleccionar company `72` aunque `/user_profile` siga pendiente.
- `expectSelectedCompanyId()` ahora espera hasta `20s` a que `selectedCompanyId` exista en la sesion antes de fallar, evitando leer la sesion inicial inmediatamente despues del login.
- No se imprimen secretos: no token, no `Authorization`, no cookies, no `localStorage` completo.

Validacion:

- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:auth-bootstrap` -> PASS (`4 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run qa:e2e:headed` sin variables QA -> PASS controlado, `7 skipped`.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.17 - Cerrar AccessDenied strict mode y diagnosticar qa_sin_permisos

Fecha: 2026-07-02.

Estado: fix aplicado localmente en `sisa.web/tests/e2e/helpers/assertions.ts` y diagnostico seguro agregado para `qa_sin_permisos`. No se tocaron backend ni seed.

Estado real reportado previo:

- `npm run qa:e2e:headed` -> `4 passed`, `3 failed`.
- Pasaron: `qa_owner_admin commercial flow`, `qa_multiempresa A/B without leaking finance permissions`, `qa_owner_admin broad company admin surface`, `qa_company_admin broad company admin surface`.
- Fallaron: `qa_tecnico`, `qa_admin_caja`, `qa_sin_permisos`.

Conclusiones:

- Login OK.
- Owner OK.
- Multiempresa OK.
- Admin bypass OK.
- El fallback de empresa activa funciona para perfiles con memberships visibles en bootstrap.

Causas/foco:

- `qa_tecnico` y `qa_admin_caja` estaban bloqueados solo por selector ambiguo en `expectAccessDenied()`: el texto global matcheaba `Acceso restringido` y `No tenes acceso a esta seccion`.
- `qa_sin_permisos` sigue en investigacion por `selectedCompanyId = null` aunque la DB indica membresia `approved` en company `72`.
- Hipotesis principal: `/companies/member` no entrega la membresia propia para un usuario sin permisos explicitos.

Fix aplicado:

- `expectAccessDenied()` ahora usa `getByRole('heading', { name: /No tenes acceso|No tienes acceso/i })` para evitar strict mode ambiguo.
- Se agrego captura segura de bootstrap de membresias para E2E: status de `/profile`, `profile.active_company_id`, status de `/companies/member`, cantidad y membresias sanitizadas (`companyId`, `role`, `status`), status de `/user_configurations/default-company`, `selectedCompanyId` final y `visibleNavLabels` desde debug de sesion.
- En `qa_sin_permisos`, antes de `expectSelectedCompanyId()` se adjuntan `qa_sin_permisos-safe-session-debug`, `qa_sin_permisos-memberships-bootstrap-debug` y screenshot `qa_sin_permisos-before-company-assert`.
- Se reforzo `selectBootstrapCompanyId()` para aceptar `approved` case-insensitive.

Criterio de diagnostico:

- Si `/companies/member` devuelve vacio o `403` para `qa_sin_permisos`, el problema no esta en frontend: el endpoint de mis membresias debe permitir que un usuario autenticado vea sus propias empresas aunque no tenga permisos explicitos; `listCompanyMembers` aplica para administrar/ver miembros, no para saber mis propias membresias.
- Si `/companies/member` devuelve la membresia y `selectedCompanyId` sigue `null`, revisar parseo frontend de membresia/status con el artefacto sanitizado.
- No imprimir secretos: no token, no `Authorization`, no cookies, no `localStorage` completo.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:auth-bootstrap` -> PASS (`4 checks`).
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` sin variables QA -> PASS controlado, `7 skipped`.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.16 - Fallback de empresa activa para membresias aprobadas

Fecha: 2026-07-02.

Estado: fix aplicado localmente en `sisa.web/src/services/authService.ts` con smoke de seleccion en `sisa.web/scripts/auth-bootstrap-smoke.js`. No se tocaron backend ni seed.

Causa:

- Usuarios QA con membresia aprobada pero sin `active_company_id`/`company_default_id` valido quedaban con `selectedCompanyId = null`.
- El bootstrap solo aceptaba empresa configurada si coincidia con una membresia aprobada y no tenia fallback operativo.

Impacto:

- `PermissionsProvider` no llamaba `/permissions/user` sin `selectedCompanyId`.
- El bypass admin no aplicaba porque comparaba la membresia contra `selectedCompanyId`.
- `can()`/`canAny()` devolvian `false`, el menu quedaba vacio y la busqueda retenia texto sin resultados.

Fix aplicado:

- Se agrego seleccion pura `selectBootstrapCompanyId()`.
- Prioridad: `active_company_id` valido, luego `company_default_id` valido, luego primera membresia `approved`.
- No se eligen empresas `pending`, `rejected` ni `inactive`.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:auth-bootstrap` -> PASS (`4 checks`).
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` sin variables QA -> PASS controlado, `7 skipped`.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.15 - Corregir falso negativo por waitForURL void

Fecha: 2026-07-01.

Estado: fix aplicado localmente en `sisa.web/tests/e2e/helpers/auth.ts`. Validacion local segura ejecutada; headed real no se corrio porque el proceso no tiene `QA_BASE_URL` y password QA disponibles.

Causa:

- `loginAs()` trataba el resultado de `page.waitForURL()` como booleano de exito.
- Playwright resuelve `waitForURL()` como `void`, por lo que `!leftLoginResult` era `true` aunque la navegacion hubiese ocurrido correctamente.

Evidencia reportada:

- `POST /login` status `200`.
- `loginOk=true`.
- `hasAuthorizationHeader=true`.
- `hasToken=true`.
- `currentUrl=/dashboard`.
- El error `POST /login returned 2xx with token, but the page stayed on /login` era falso.

Fix aplicado:

- `loginAs()` ahora hace `await leftLogin` y luego valida `new URL(page.url()).pathname`.
- Solo falla con `page stayed on /login` si el path actual termina en `/login`.
- Se agrego comentario: `page.waitForURL resolves void; do not use its resolved value as success boolean.`
- `/permissions/user/` sigue siendo observacional y no obligatorio dentro de `loginAs()`; la validacion de permisos/shell queda en `waitForOperationalShell()` o tests especificos.
- No se tocaron backend, seed ni permisos.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run lint` -> primer intento FAIL por `permissionsResponse` asignado y no usado; corregido manteniendo captura observacional con `void page.waitForResponse(...)`.
- `sisa.web`: `npm run lint` -> PASS luego de la correccion.
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.14 - Deteccion de token por Authorization header en E2E

Fecha: 2026-07-01.

Estado: fix aplicado localmente en `sisa.web/tests/e2e/helpers/auth.ts`. Validacion local segura ejecutada; headed focalizado/completo no se corrio porque el proceso no tiene `QA_BASE_URL` y password QA disponibles.

Causa probable:

- El helper E2E de LOOP 8.13 calculaba `hasToken` mirando solo el body de `POST /login`.
- La app real (`src/services/authService.ts`) acepta token desde header `Authorization` o desde body (`token`, `access_token`, `Authorization`, `authorization`).
- Si backend devuelve JWT por header, el E2E producia falso negativo y marcaba login fallido aunque el contrato real fuera valido.

Fix aplicado:

- `readLoginResponseDiagnostics()` ahora calcula `hasAuthorizationHeader` como booleano sin imprimir el valor.
- `hasToken = hasAuthorizationHeader || payloadHasToken(payload)`.
- `loginBody` sigue sanitizado.
- No se imprime ni adjunta `Authorization`, token, bearer, jwt, password, cookies ni localStorage completo.

Interpretacion esperada:

- Si `POST /login` devuelve 2xx con `hasAuthorizationHeader=true` y la pagina sigue en `/login`, el foco pasa a frontend/session/redireccion.
- Si `POST /login` devuelve 401/403, el foco sigue siendo credenciales/password real o bloqueo no cubierto por columnas revisadas.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- Focalizado `qa_company_admin` headed -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- `npm run qa:e2e:headed` completo -> NO EJECUTADO por la misma razon.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.13 - Diagnostico real de POST login perfiles QA restantes

Fecha: 2026-07-01.

Estado: diagnostico E2E de `POST /login` implementado localmente. Validacion local segura ejecutada; headed focalizado/completo no se corrio porque el proceso no tiene `QA_BASE_URL` y password QA disponibles.

Contexto:

- DB QA ya fue revisada readonly en LOOP 8.12.
- `qa_company_admin`, `qa_tecnico`, `qa_admin_caja` y `qa_sin_permisos` existen, estan activados, sin `locked_until`, con membresias approved en company `72`.
- `qa_company_admin` tiene role `admin`; `qa_tecnico` tiene `listClients` y `listJobs`; `qa_admin_caja` tiene permisos caja/finanzas.

Foco del loop:

- Pasar de diagnostico de seed/permisos a contrato real de login.
- Determinar si `POST /login` falla por credencial/bloqueo o si responde 2xx con token y falla frontend/session.

Fix aplicado:

- `loginAs()` captura explicitamente la respuesta real de `POST /login`.
- Si `POST /login` no devuelve 2xx, lanza error JSON seguro.
- Si devuelve 2xx pero no hay campo tipo token, lanza error JSON seguro.
- Si devuelve 2xx con token pero la pagina sigue en `/login`, lanza error JSON seguro.
- Se elimino el assert final `expect(page).not.toHaveURL(/\/login$/)` para que no tape el motivo real.

Diagnostico seguro incluido en error:

- `profile`.
- `username`.
- `currentUrl`.
- `loginStatus`.
- `loginOk`.
- `loginBody` sanitizado.
- `hasToken`.
- texto visible de error de login.
- `permissionsResponse` con status/path si `/permissions/user/` se dispara.
- Nunca incluye password, token, Authorization, cookies ni localStorage completo.

Sanitizacion de payload login:

- Redacta claves sensibles: `token`, `access_token`, `Authorization`, `authorization`, `password`, `api_token`, `bearer`, `jwt`.
- `session_id` se convierte a booleano `hasSessionId`; no se guarda su valor.
- Mantiene mensajes/codigos/errores no sensibles para diagnostico.

Validacion real sugerida por perfil:

```powershell
npx playwright test tests/e2e/qa-profiles.spec.ts --headed --workers=1 --grep "qa_company_admin"
npx playwright test tests/e2e/qa-profiles.spec.ts --headed --workers=1 --grep "qa_tecnico"
npx playwright test tests/e2e/qa-profiles.spec.ts --headed --workers=1 --grep "qa_admin_caja"
npx playwright test tests/e2e/qa-profiles.spec.ts --headed --workers=1 --grep "qa_sin_permisos"
```

Interpretacion esperada:

- Si `POST /login` devuelve 401/403: no tocar frontend; problema de credencial/password real o campo de bloqueo no revisado.
- Si `POST /login` devuelve 200/2xx sin token: revisar contrato backend/frontend de auth.
- Si `POST /login` devuelve 200/2xx con token pero sigue en `/login`: revisar `loginRequest()`, `persist(nextSession)`, `bootstrapSession(nextSession)`, redireccion post-login y `AuthGuard`.
- Si `qa_company_admin` o `qa_tecnico` entran pero no ven menu: revisar artefactos `selectedCompanyId`, membresias, `isCompanyAdmin`, `permissionsCount`, `permissionSectors` y `visibleNavLabels` agregados en LOOP 8.11.

Pendiente si login falla por credenciales:

- Re-ejecutar seed con el `QA_PASSWORD` actual o configurar:
  - `QA_PASSWORD_QA_COMPANY_ADMIN`.
  - `QA_PASSWORD_QA_TECNICO`.
  - `QA_PASSWORD_QA_ADMIN_CAJA`.
  - `QA_PASSWORD_QA_SIN_PERMISOS`.
- No imprimir ni commitear esos valores.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- Headed focalizados por perfil -> NO EJECUTADOS en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- `npm run qa:e2e:headed` completo -> NO EJECUTADO por la misma razon.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.12 - Diagnostico remoto readonly de perfiles QA restantes

Fecha: 2026-07-01.

Estado: diagnostico remoto de solo lectura ejecutado en `hostinger-codex`, directorio `domains/depros.com.ar/public_html/sistema_test`. No se modificaron archivos remotos ni datos de base. No se leyeron `.env`, secretos, passwords, tokens ni hashes.

Contexto previo:

- `npm run qa:e2e:headed`: `3 passed`, `4 failed`.
- Pasaron owner commercial flow, multiempresa A/B y owner broad surface.
- Fallan `qa_company_admin`, `qa_tecnico`, `qa_admin_caja`, `qa_sin_permisos`.

Comandos remotos permitidos ejecutados:

- `pwd` y `ls` limitado para confirmar ubicacion.
- `php -l scripts/qa/seed-qa-users.php` -> PASS.
- PHP enviado por stdin sin crear archivos remotos, usando bootstrap de la app para ejecutar solo `DESCRIBE` y `SELECT` sobre usuarios QA, membresias y permisos.

Resultados DB QA:

- Base activa reportada por conexion de la app: `u650890769_sistema_test`.
- Usuarios existentes y activados:
  - `qa_company_admin`: `id=23`, `activated=1`, `locked_until=NULL`.
  - `qa_tecnico`: `id=24`, `activated=1`, `locked_until=NULL`.
  - `qa_admin_caja`: `id=25`, `activated=1`, `locked_until=NULL`.
  - `qa_sin_permisos`: `id=26`, `activated=1`, `locked_until=NULL`.
- Membresias:
  - `qa_company_admin`: company `72`, role `admin`, status `approved`.
  - `qa_tecnico`: company `72`, role `member`, status `approved`.
  - `qa_admin_caja`: company `72`, role `member`, status `approved`.
  - `qa_sin_permisos`: company `72`, role `member`, status `approved`.
- Permisos explicitos:
  - `qa_tecnico@72`: 20 permisos, incluyendo `listClients`, `listJobs`, `listWorkLogs`, `listStatuses`, `listFolders`, `uploadFile`, `downloadFile`.
  - `qa_admin_caja@72`: 25 permisos, incluyendo `listClients`, `listInvoices`, `listReceipts`, `listPayments`, `listCashBoxes`, `viewAccountingSummary`, `viewClientStatement`.
  - `qa_company_admin`: sin permisos explicitos esperados porque debe operar por bypass `admin`.
  - `qa_sin_permisos`: sin permisos explicitos esperados.

Interpretacion:

- El seed/membresia de `qa_company_admin` es correcto para probar bypass admin: admin approved en company `72`.
- El seed/permisos de `qa_tecnico` es correcto para ver `Clientes` y `Trabajos` en company `72`.
- El seed/permisos de `qa_admin_caja` es correcto para ver caja/finanzas si logra autenticar.
- No hay bloqueo evidente por `locked_until`; si el login sigue fallando, revisar otros campos de bloqueo si existen o password distinto al `QA_PASSWORD` usado por Playwright.
- Para `qa_company_admin` y `qa_tecnico`, si loguean pero no ven menu, el siguiente foco es la respuesta real de `/permissions/user/` y la hidratacion en `PermissionsProvider`, no la DB de seed.

Pendiente recomendado:

- Ejecutar nuevamente `npm run qa:e2e:headed` y revisar artefactos agregados en LOOP 8.11:
  - `qa_company_admin-permissions-bootstrap-debug`.
  - `qa_tecnico-permissions-bootstrap-debug`.
  - `safe-session-debug` con `visibleNavLabels`.
- Si `qa_admin_caja` o `qa_sin_permisos` siguen sin salir de login, re-seedear con el `QA_PASSWORD` actual o configurar `QA_PASSWORD_QA_ADMIN_CAJA` / `QA_PASSWORD_QA_SIN_PERMISOS` con la clave real, sin imprimirla.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.11 - Diagnostico final perfiles QA permisos vs credenciales

Fecha: 2026-07-01.

Estado: diagnostico E2E seguro ampliado localmente. Validacion local segura ejecutada; ejecucion real headed no se corrio porque el entorno no tenia `QA_BASE_URL` y password QA disponibles.

Resultado E2E real previo informado:

- `npm run qa:e2e:headed`: `3 passed`, `4 failed`.
- Pasaron: `qa_owner_admin commercial flow`, `qa_multiempresa switches A/B without leaking finance permissions`, `qa_owner_admin broad company admin surface`.

Conclusiones:

- Owner funciona.
- Multiempresa A/B funciona.
- Ya no hay leak financiero ni 403 raros en multiempresa.
- Quedan 2 fallos de permisos/hidratacion (`qa_company_admin`, `qa_tecnico`) y 2 fallos de login/credenciales (`qa_admin_caja`, `qa_sin_permisos`).

Fallas actuales a diagnosticar:

- `qa_company_admin`: entra y aparece shell, pero no ve `Dashboard`. Este perfil deberia funcionar por bypass `admin` con `role=admin` y `status=approved`.
- `qa_tecnico`: entra y aparece shell, pero no ve `Clientes`. Este perfil deberia tener `listClients`/`listJobs` por seed.
- `qa_admin_caja` y `qa_sin_permisos`: no salen de `/login`; tratarlos como credenciales/bloqueo hasta probar lo contrario.

Diagnostico E2E agregado:

- `safe-session-debug` ahora incluye URL actual, `selectedCompanyId`, membresias (`companyId`, `role`, `status`), `visibleNavLinkCount` y `visibleNavLabels`.
- Se agrego captura sanitizada de `/permissions/user/` con status, `isCompanyAdmin`, cantidad de permisos, sectores ordenados, `company_id` usado en query y `user_id` en URL.
- `qa_company_admin` y `qa_tecnico` adjuntan `safe-session-debug`, `permissions-bootstrap-debug` y screenshot antes de los asserts de menu.
- `loginAs()` ya adjunta diagnostico seguro si no sale de `/login`, incluyendo status/body sanitizado de `/login` y status de `/permissions/user/` si se dispara.
- No se imprime ni adjunta password, token, cookies, localStorage completo ni headers `Authorization`.

SQL manual sugerido para DB QA:

```sql
SELECT id, username, email, activated
FROM users
WHERE username IN ('qa_company_admin','qa_tecnico','qa_admin_caja','qa_sin_permisos');

SELECT eu.user_id, u.username, eu.empresa_id, eu.rol, eu.estado
FROM empresas_usuarios eu
JOIN users u ON u.id = eu.user_id
WHERE u.username IN ('qa_company_admin','qa_tecnico','qa_admin_caja','qa_sin_permisos');

SELECT p.user_id, u.username, p.company_id, p.sector
FROM permissions p
JOIN users u ON u.id = p.user_id
WHERE u.username IN ('qa_tecnico','qa_admin_caja')
ORDER BY u.username, p.company_id, p.sector;
```

- Revisar columnas de bloqueo segun esquema real si existen: `login_attempts`, `locked_until`, `blocked_until`, `status`, `is_blocked`.
- No consultar ni imprimir hashes/passwords/tokens.

Pendiente si `qa_admin_caja` y `qa_sin_permisos` siguen sin login:

- Re-ejecutar seed QA con `QA_PASSWORD` actual, o configurar:
  - `QA_PASSWORD_QA_ADMIN_CAJA`.
  - `QA_PASSWORD_QA_SIN_PERMISOS`.
- No registrar ni imprimir esos valores.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.10 - Resolver 403 frontend multiempresa y diagnosticar login QA

Fecha: 2026-07-01.

Estado: ajustes aplicados localmente en `sisa.web`. Validacion local segura ejecutada; ejecucion real headed no se corrio porque el entorno no tenia `QA_BASE_URL` y password QA disponibles.

Resultado E2E real previo informado:

- `npm run qa:e2e:headed`: `2 passed`, `5 failed`.
- Pasaron: `qa_owner_admin commercial flow` y `qa_owner_admin broad company admin surface`.

Fallas actuales:

- `qa_multiempresa`: expectativas de menu ya alineadas, pero aparecieron 403 inesperados:
  - `/api/jobs?company_id=72`.
  - `/api/payments?company_id=73`.
- Perfiles `qa_company_admin`, `qa_tecnico`, `qa_admin_caja`, `qa_sin_permisos`: no salen de login.

Interpretacion:

- El backend probablemente esta rechazando correctamente endpoints sin permiso.
- El bug esta en frontend si dispara fetches que el usuario no puede consumir.
- En cambio de empresa, los permisos de Empresa A podian quedar disponibles por un render mientras `selectedCompanyId` ya era B, permitiendo disparar fetches con permisos de la empresa anterior.
- Las fallas de login de perfiles no-owner siguen apuntando a password/seed/cuenta bloqueada, porque owner y multiempresa si autentican.

Fix frontend aplicado:

- `PermissionsProvider` ahora guarda `companyId` asociado a los permisos hidratados.
- `can()`, `canAny()` y `canAll()` solo devuelven `true` si `permissions.companyId === selectedCompanyId`.
- Al iniciar carga de permisos por cambio de empresa, `permissionsCompanyId` se limpia a `null`.
- `AppShell`, `ProtectedRoute` y `DefaultAuthenticatedRoute` esperan que el scope de permisos coincida con la empresa activa antes de abrir navegacion/rutas.
- Esto evita usar permisos de Empresa A para renderizar/cargar datos de Empresa B.

Diagnostico seguro de login aplicado:

- `loginAs()` captura status/body sanitizado de `/login` si no sale de `/login`.
- El error incluye `profile`, `username`, URL actual, texto visible de error y respuesta auth sanitizada.
- Nunca incluye password, token, cookies, localStorage completo ni headers `Authorization`.

SQL seguro sugerido para diagnosticar cuentas QA:

```sql
SELECT id, username, email, activated, login_attempts, locked_until
FROM users
WHERE username IN ('qa_company_admin','qa_tecnico','qa_admin_caja','qa_sin_permisos');
```

- Adaptar nombres de columnas si el esquema usa otros campos para activacion/bloqueo.
- No consultar ni imprimir hashes/passwords/tokens.

Pendiente si los perfiles siguen fallando login:

- Re-ejecutar seed QA con `QA_PASSWORD` actual, o configurar passwords por perfil:
  - `QA_PASSWORD_QA_COMPANY_ADMIN`.
  - `QA_PASSWORD_QA_TECNICO`.
  - `QA_PASSWORD_QA_ADMIN_CAJA`.
  - `QA_PASSWORD_QA_SIN_PERMISOS`.
- No registrar ni imprimir esos valores.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.9 - Expectativa multiempresa readonly y diagnostico de credenciales

Fecha: 2026-07-01.

Estado: ajustes aplicados localmente en `sisa.web/tests/e2e`. Validacion local segura ejecutada; ejecucion real headed no se corrio porque el entorno no tenia `QA_BASE_URL` y password QA disponibles.

Resultado E2E real previo informado:

- `npm run qa:e2e:headed`: `2 passed`, `5 failed`.
- Pasaron: `qa_owner_admin commercial flow` y `qa_owner_admin broad company admin surface`.
- Esto confirma que login owner, empresa activa y navegacion base estan estables.

Fallas observadas:

- `qa_multiempresa`: luego de corregir el cambio de empresa, `Pagos` ya no fue el fallo principal; quedo `Recibos` visible en Empresa B.
- `qa_company_admin`, `qa_tecnico`, `qa_admin_caja`, `qa_sin_permisos`: no salieron correctamente de `/login`, mientras `qa_owner_admin` y `qa_multiempresa` si entraron.

Interpretacion:

- Empresa B de `qa_multiempresa` esta definida como readonly comercial; readonly incluye `listInvoices/getInvoice` y `listReceipts/getReceipt`.
- Por lo tanto, `Recibos` visible en B no es leak si solo habilita lectura.
- `Pagos` debe seguir oculto en B porque pagos/caja/mutacion financiera no forman parte del readonly esperado.
- Las fallas de perfiles no-owner apuntan a credenciales/password distintos al `QA_PASSWORD` actual o a perfiles no reseedeados con esa clave.

Fix aplicado:

- `qa_multiempresa` ahora espera en Empresa B: `Clientes`, `Facturas` y `Recibos` visibles; `Pagos` oculto.
- Se agrego comentario en el spec: Empresa B es readonly comercial y permite facturas/recibos lectura, no pagos/caja/mutacion.
- `loginAs()` ahora diagnostica fallo de login sin imprimir secretos:
  - `profile`.
  - `username` usado.
  - URL actual.
  - texto visible de error de login si existe.
- El diagnostico no incluye password, tokens, cookies, localStorage completo ni headers `Authorization`.

Pendiente si los perfiles siguen fallando login:

- Re-seedear perfiles QA con un password comun conocido por `QA_PASSWORD`, o configurar passwords por perfil:
  - `QA_PASSWORD_QA_COMPANY_ADMIN`.
  - `QA_PASSWORD_QA_TECNICO`.
  - `QA_PASSWORD_QA_ADMIN_CAJA`.
  - `QA_PASSWORD_QA_SIN_PERMISOS`.
- No registrar ni imprimir esos valores.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.8 - Separar login de shell y esperar permisos multiempresa

Fecha: 2026-07-01.

Estado: ajustes aplicados localmente en `sisa.web/tests/e2e`. Validacion local segura ejecutada; ejecucion real headed no se corrio porque el entorno no tenia `QA_BASE_URL` y password QA disponibles.

Resultado E2E real previo informado:

- `npm run qa:e2e:headed`: `2 passed`, `5 failed`.
- Pasaron: `qa_owner_admin commercial flow` y `qa_owner_admin broad surface`.

Fallas observadas:

- `qa_multiempresa`: despues de cambiar a Empresa B, `Pagos` seguia visible. Puede ser cache/permisos cruzados real, pero el helper no esperaba recarga de `/permissions/user/` despues del cambio de empresa.
- `qa_company_admin`, `qa_tecnico`, `qa_admin_caja`, `qa_sin_permisos`: fallaban dentro de `loginAs()` porque exigia `.sidebar-nav-label` con `Mapa operativo` para todos los perfiles.

Fix aplicado:

- `loginAs()` queda con responsabilidad minima: login, espera opcional de `/permissions/user/` y salida de `/login`.
- Los tests que validan menu llaman explicitamente `waitForOperationalShell()` despues de `loginAs()`.
- `qa_sin_permisos` ya no asume shell completo de entrada; valida `selectedCompanyId`, solo revisa menu si existe buscador, y luego navega a `/clients` para validar `AccessDenied`.
- `selectCompany()` evita click si la empresa ya esta activa, y cuando cambia de empresa espera `/permissions/user/` y shell operativo antes de seguir.
- `qa_multiempresa` adjunta debug sanitizado inmediatamente despues de seleccionar Empresa B para distinguir si `selectedCompanyId` sigue en A o si B mantiene `Pagos` visible.
- No se imprimen secretos; el debug sigue sin token, cookies, storage completo ni `Authorization`.

Expectativa documentada pendiente de confirmar con E2E real:

- Empresa A de `qa_multiempresa`: puede ver caja/finanzas.
- Empresa B de `qa_multiempresa`: no debe ver `Pagos` ni `Recibos`.
- Si con `selectedCompanyId=73` `Pagos` sigue visible, registrar como bug real de permisos/seed/UI, no como bug del helper.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.7 - Selectores estructurales en espera operativa Playwright

Fecha: 2026-07-01.

Estado: fix aplicado localmente en `sisa.web/tests/e2e`. Validacion local segura ejecutada; ejecucion real headed no se corrio porque el entorno no tenia `QA_BASE_URL` y password QA disponibles.

Causa:

- `waitForOperationalShell()` usaba `page.getByText('Empresa activa')`.
- Ese texto aparece varias veces en la pantalla: `CompanySwitcher`, dashboard y textos descriptivos.
- Playwright strict mode fallo por selector ambiguo.

Impacto:

- El ajuste de espera operativa rompio los 7 tests E2E.
- Incluso fallo el test comercial que antes pasaba, por bug del selector E2E y no por permisos/negocio.

Fix aplicado:

- `waitForOperationalShell()` ahora usa selectores estructurales no ambiguos:
  - `.company-switcher-label` contiene `Empresa activa`.
  - `.company-switcher-trigger` visible.
  - `.sidebar-nav-label` contiene `Mapa operativo`.
  - placeholder `Buscar opcion...` visible.
- `loginAs()` cambio la espera de `Mapa operativo` a `.sidebar-nav-label` con `toContainText()`.
- Se mantiene menu con `exact: true`.
- Se mantiene debug sanitizado sin tokens/cookies/localStorage completo/Authorization.
- `attachSanitizedDebug()` ahora tolera pagina/contexto cerrado y adjunta `{ "debug_error": "page closed before sanitized debug could be collected" }`.
- No se tocaron permisos ni backend.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.6 - Estabilizacion Playwright menu exacto y empresa activa

Fecha: 2026-07-01.

Estado: ajustes aplicados localmente en `sisa.web/tests/e2e`. Validacion local segura ejecutada. Este loop corresponde al ajuste solicitado como LOOP 8.4 despues del primer `npm run qa:e2e:headed` real; la nueva ejecucion headed real no se corrio porque el entorno no tenia `QA_BASE_URL` y password QA disponibles.

Resultado E2E real previo informado:

- `npm run qa:e2e:headed` contra QA real: `1 passed`, `6 failed`.

Causas detectadas:

- Selector ambiguo: `getByRole('link', { name: 'Clientes' })` matcheaba `Clientes` y `Cercania clientes/proveedores`.
- Falta de estabilizacion post-login: algunos asserts de menu corrian antes de confirmar shell operativo con empresa activa y permisos hidratados.
- `qa_sin_permisos` dependia de `selectedCompanyId`: si quedaba `null`, la app redirigia a `company-onboarding` antes de mostrar `AccessDenied`.

Fix aplicado:

- `expectVisibleMenu()` y `expectHiddenMenu()` usan `exact: true` para links del menu.
- Se agrego `waitForOperationalShell()` para esperar `Empresa activa`, buscador de menu y una pausa corta de estabilizacion.
- `loginAs()` espera respuesta `/permissions/user/` cuando ocurre, espera `Mapa operativo` y luego `waitForOperationalShell()`.
- Se agrego debug sanitizado en fallos con solo:
  - URL actual.
  - `selectedCompanyId`.
  - membresias con `companyId`, `role`, `status`.
  - cantidad visible de links de navegacion.
- El debug no incluye token, cookies, storage completo ni headers `Authorization`.
- `qa_sin_permisos` ahora falla explicitamente si no hay empresa activa con el mensaje `QA profile has no selectedCompanyId; seed/default company not applied`.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8.5 - Bloqueo de app hasta bootstrap de sesion

Fecha: 2026-07-01.

Estado: fix aplicado localmente en `sisa.web`. Validacion local segura ejecutada; ejecucion real headed no se corrio porque el entorno no tenia `QA_BASE_URL` y password QA disponibles.

Causa:

- `login()` persistia una sesion minima con token, pero sin `selectedCompanyId`, membresias ni empresas.
- `bootstrapSession()` completaba esos datos asincronicamente despues.
- Durante esa ventana `AuthGuard` consideraba al usuario autenticado y dejaba abrir la app, por lo que se podia iniciar con permisos/contexto incompletos antes de cargar el bootstrap real.

Fix aplicado:

- `SessionProvider` ahora expone `isBootstrapping`.
- `bootstrapSession()` marca `isBootstrapping=true` mientras carga perfil, membresias, empresa activa, empresas y tema.
- `AuthGuard` bloquea la app autenticada con `Verificando sesion...` hasta que termine el bootstrap inicial.
- `AppShell` mantiene la espera de permisos agregada en LOOP 8.4, por lo que la app abre recien con sesion bootstrappeada y permisos hidratados.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No se generaron ni commitearon `playwright-report/` ni `test-results/`.

## LOOP 8.4 - Espera real de permisos en web y Playwright

Fecha: 2026-07-01.

Estado: fix aplicado localmente en `sisa.web`. Validacion local segura ejecutada; ejecucion real headed no se corrio porque el entorno no tenia `QA_BASE_URL` y password QA disponibles.

Causa:

- Los E2E podian avanzar cuando aparecia `Verificando permisos...`, antes de que la navegacion estuviera filtrada con permisos reales.
- `ProtectedRoute` ya esperaba permisos, pero `AppShell` podia renderizar shell/navegacion mientras `usePermissions()` seguia cargando.
- Esto generaba una condicion de carrera: tests y usuario podian ver o consultar estado parcial antes de finalizar permisos.

Fix aplicado:

- `AppShell` ahora mantiene pantalla `Verificando permisos...` hasta que `permissions.hydrated && !permissions.loading`.
- `loginAs()` ya no acepta `Verificando permisos...` como login completado; espera `Mapa operativo` con timeout de 45s.
- Se mantiene el manejo seguro de password por `QA_PASSWORD` / `QA_PASSWORD_QA_*`, sin imprimir secretos.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No se generaron ni commitearon `playwright-report/` ni `test-results/`.

## LOOP 8.3 - Login Playwright usa username

Fecha: 2026-07-01.

Estado: fix aplicado localmente en `sisa.web/tests/e2e/helpers/auth.ts`. Validacion local segura ejecutada; ejecucion real headed no se corrio porque el entorno no tenia `QA_BASE_URL` y password QA disponibles.

Causa:

- Los E2E no ingresaban porque el helper completaba `Usuario` con emails `@sisa-qa.invalid`.
- El login QA real espera usernames como `qa_tecnico`, `qa_owner_admin`, etc.

Fix aplicado:

- Se reemplazo el mapa `emails` por `usernames`.
- `loginAs()` ahora completa `Usuario` con el username QA correspondiente.
- Se mantiene el password por `QA_PASSWORD` / `QA_PASSWORD_QA_*`, sin hardcodear ni imprimir secretos.

Validacion:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No se generaron ni commitearon `playwright-report/` ni `test-results/`.

## LOOP 8.2 - Correccion selector password Playwright

Fecha: 2026-07-01.

Estado: fix aplicado localmente en `sisa.web/tests/e2e/helpers/auth.ts`. Validacion local segura ejecutada; ejecucion real headed no se corrio porque el entorno no tenia `QA_BASE_URL` y password QA disponibles.

Causa:

- Los 7 tests E2E fallaban en login porque `page.getByLabel('Clave')` resolvia dos elementos.
- En `LoginPage.tsx`, el label `Clave` envuelve tanto el input de password como el boton de mostrar/ocultar clave (`Mostrar clave` / `Ver`).
- Playwright exige un locator unico para `fill()`, por eso el selector por label era ambiguo.

Fix aplicado:

- Se cambio el fill de password en `loginAs()` de `page.getByLabel('Clave')` a `page.locator('input[type="password"]')`.
- Se mantiene el origen seguro del secreto via `QA_PASSWORD` o `QA_PASSWORD_QA_*`.
- No se hardcodeo, imprimio ni registro ningun password/token/cookie.

Validacion solicitada:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.web`: `npm run qa:e2e:headed` con QA real -> NO EJECUTADO en esta sesion porque no hay `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*` en el entorno.
- No commitear `playwright-report/` ni `test-results/`.

## LOOP 8 - QA automatica visual web

Fecha: 2026-07-01.

Estado: scaffolding Playwright implementado localmente en `sisa.web`, validado sin credenciales QA y listo para ejecucion contra `sistema_test` con el seed real ya aplicado. La ejecucion real E2E queda pendiente hasta correr con `QA_BASE_URL` y password seguro por entorno.

Objetivo:

- Cubrir una primera matriz visual/read-only de permisos web por perfiles QA seed, sin crear ni mutar datos durante la prueba.
- Capturar evidencia local de fallos con screenshots, video y traces/report HTML, sin guardar cookies, storageState, tokens ni secretos.
- Mantener los tests seguros para maquinas de desarrollo: si faltan `QA_BASE_URL` y `QA_PASSWORD`/`QA_PASSWORD_QA_*`, la suite se marca como skipped.

Cambios en `sisa.web`:

- Se agrego `@playwright/test` como devDependency mediante `npm install --save-dev @playwright/test`.
- Se agregaron scripts:
  - `npm run qa:e2e`
  - `npm run qa:e2e:headed`
  - `npm run qa:e2e:ui`
  - `npm run qa:e2e:report`
- Se agrego `playwright.config.ts` con `QA_BASE_URL`, `test-results/qa-e2e`, screenshots on failure, video retain-on-failure, trace retain-on-failure y report HTML/list.
- Se actualizo `.gitignore` para excluir `test-results/`, `playwright-report/` y `.auth/`.
- Se agregaron helpers E2E:
  - `tests/e2e/helpers/auth.ts`: perfiles QA, login sin hardcodear password y guardas de runtime QA.
  - `tests/e2e/helpers/navigation.ts`: busqueda de menu, asserts de menu y cambio de empresa.
  - `tests/e2e/helpers/assertions.ts`: captura de 401/403, screenshots adjuntos y assert de acceso restringido.
- Se agregaron specs read-only:
  - `tests/e2e/qa-profiles.spec.ts`: owner/admin, tecnico, admin caja y sin permisos.
  - `tests/e2e/qa-commercial-flow.spec.ts`: recorrido visual clientes, trabajos, facturas, recibos, pagos y analytics.
  - `tests/e2e/qa-multiempresa.spec.ts`: cambio A/B y no filtracion de permisos de finanzas en empresa B.

Variables esperadas para ejecucion real:

- `QA_BASE_URL`: URL del ambiente QA/staging web.
- `QA_PASSWORD`: password comun de perfiles QA, o passwords por perfil con `QA_PASSWORD_QA_OWNER_ADMIN`, `QA_PASSWORD_QA_TECNICO`, etc.
- Opcionales multiempresa: `QA_COMPANY_A_NAME`, `QA_COMPANY_B_NAME`, `QA_COMPANY_B_ID` para ambientes donde cambien nombres/ids seed.
- No registrar ni commitear passwords, tokens, cookies, storageState ni headers `Authorization`.

Comandos PowerShell para ejecucion real:

```powershell
$env:QA_BASE_URL="..."
$env:QA_PASSWORD="..."
$env:QA_COMPANY_B_ID="73"
npm run qa:e2e:headed
npm run qa:e2e:report
```

Notas operativas:

- Reemplazar `...` por valores locales/seguros, sin imprimir passwords en logs ni commitearlos.
- `playwright-report/` y `test-results/qa-e2e/` son artefactos locales ignorados por Git y no deben commitearse.

Validacion local ejecutada:

- `sisa.web`: `npx playwright test --list` -> PASS; detecta 7 tests en 3 archivos.
- `sisa.web`: `npm run qa:e2e` sin variables QA -> PASS controlado, `7 skipped`.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run check:permissions-audit` -> PASS (`41 nav items`, `49 routes`, `16 action checks`).
- `sisa.web`: `npm run check:commercial-flow` -> PASS (`15 checks`).
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.

Riesgos/deuda detectada:

- `npm install` reporto `4 vulnerabilities (2 low, 1 moderate, 1 high)`. No se ejecuto `npm audit fix` para evitar cambios no solicitados en dependencias.
- Los E2E reales todavia no fueron ejecutados contra remoto porque no se pasaron `QA_BASE_URL` y password seguro en esta sesion.
- La cobertura es visual/read-only inicial; no reemplaza la matriz manual ni valida mutaciones comerciales.

Pendiente para cerrar LOOP 8 con QA real:

- Ejecutar `npm run qa:e2e:headed` con `QA_BASE_URL`, `QA_PASSWORD` y `QA_COMPANY_B_ID=73` provistos por entorno fuera del repo.
- Revisar `playwright-report/` y `test-results/qa-e2e` localmente; no commitear artefactos.
- Si falla una ruta por selector/texto real, ajustar el spec al comportamiento observado sin ampliar permisos para hacer pasar la prueba.

## LOOP 7.1 - Correccion seed comercial clients/invoices

Fecha: 2026-07-01.

Estado: correccion implementada y seed real aplicado en remoto por operador autorizado. Cleanup y apply ejecutados correctamente; passwords no impresos ni registrados. Validacion local final ejecutada y conteos comerciales remotos informados como completos.

Causa del bloqueo:

- El seed comercial usaba `upsertNamed()` para `clients` e `invoices`.
- `upsertNamed()` solo funciona con tablas que tienen `name`, `title`, `description` o `business_name`.
- En el esquema remoto verificado, `clients` no tiene columna nominal y representa el vinculo entre empresa emisora y empresa cliente via `company_id` + `client_company_id`/`empresa_id`.
- `invoices` tampoco tiene columna nominal; usa `company_id`, `client_id`, `invoice_number`, fechas, montos, `status`, `payment_status`, etc.
- Resultado anterior: A/B, usuarios, membresias y permisos estaban correctos, pero `clients=0` e `invoices=0` por empresa.

Helpers dedicados agregados:

- `upsertQaClientCompany(int $ownerCompanyId, string $suffix)`: crea/actualiza empresa cliente QA A/B con `nro_doc` numerico estable `990000010001/990000010002`.
- `upsertQaClient(int $companyId, int $clientCompanyId, int $userId)`: crea/actualiza `clients` por `company_id + client_company_id` o `empresa_id`, sin depender de nombre.
- `upsertQaInvoice(int $companyId, int $clientId, int $userId, string $suffix)`: crea/actualiza factura por `company_id + invoice_number` (`QA-LOOP7-A-0001` / `QA-LOOP7-B-0001`).
- `upsertQaInvoiceItem(int $companyId, int $invoiceId, int $productId, int $jobId, int $userId)`: crea/actualiza item con columnas reales (`invoice_id`, `quantity`, `unit_price`, `total_amount`, `description`, `entity_type`, `code`, `job_id`, `product_id` cuando existen).
- `upsertQaReceipt(...)` y `upsertQaPayment(...)`: crean/actualizan por `company_id + source_device_id`, sin depender de columnas nominales.
- Utilidades internas nuevas: `findOneByCriteria()`, `countRows()`, `stableUuid()`.

Validacion post-seed agregada:

- Luego de sembrar A/B, `assertCommercialSeedComplete()` imprime conteos por empresa y aborta transaccion con `QA commercial seed incomplete` si falta cualquiera de:
  - `clients >= 1`
  - `invoices >= 1`
  - `invoice_items >= 1`
  - `receipts >= 1`
  - `payments >= 1`
  - `jobs >= 1`
  - `work_logs >= 1`

Cleanup actualizado:

- Captura tambien empresas cliente QA por `nro_doc=990000010001/990000010002` y el registro roto anterior `nro_doc=0`/razon social QA LOOP 6.
- Mantiene limpieza de datos company-scoped antes de borrar empresas/usuarios QA.

Dry-run:

- El plan ahora explicita que por cada empresa se espera: empresa cliente QA, row `clients`, carpeta, trabajo, worklog, producto/servicio, caja, factura, item, recibo y pago.

Validacion local ejecutada:

- `sisa.api`: `php -l scripts/qa/seed-qa-users.php` -> PASS.
- `sisa.api`: `php -l src/Routes/api.php` -> PASS.
- `sisa.api`: `php -l tests/Routes/ApiPermissionsCoverageTest.php` -> PASS.
- `sisa.api`: `vendor/bin/phpunit tests/Routes/ApiPermissionsCoverageTest.php` -> PASS (20 tests, 88 assertions) con warning PHPUnit baseline.
- `sisa.web`: `npm run check:permissions-audit` -> PASS.
- `sisa.web`: `npm run check:commercial-flow` -> PASS (15 checks).
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.ui`: `npm run check:permissions-audit` -> PASS.
- `sisa.ui`: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188 selectedJobRecord`.
- `sisa.ui`: `npm run check:cache` -> PASS.

Apply real remoto ejecutado:

- Cleanup ejecutado correctamente antes del reseed.
- Apply ejecutado correctamente con password provisto fuera del repo; passwords no impresos.
- Empresa A: `Company A=72`.
- Empresa B: `Company B=73`.
- Usuarios QA recreados:
  - `qa_superadmin` -> `user_id=21`.
  - `qa_owner_admin` -> `user_id=22`.
  - `qa_company_admin` -> `user_id=23`.
  - `qa_tecnico` -> `user_id=24`.
  - `qa_admin_caja` -> `user_id=25`.
  - `qa_sin_permisos` -> `user_id=26`.
  - `qa_multiempresa` -> `user_id=27`.
- Conteos comerciales informados por empresa A/B:
  - `clients=1`.
  - `invoices=1`.
  - `invoice_items=1`.
  - `receipts=1`.
  - `payments=1`.
  - `jobs=1`.
  - `work_logs=1`.

Estado remoto posterior:

- LOOP 7.1 ya no queda bloqueado por seed comercial incompleto.
- Pendiente para QA real completa: ejecutar matriz manual y/o Playwright visual read-only contra `Company A=72` / `Company B=73`.

## LOOP 7 - QA real autorizada con seed QA

Fecha: 2026-07-01.

Estado: bloqueado antes de ejecutar mutaciones. No se ejecuto `--apply`, no se resetearon passwords, no se emitieron tokens, no se tocaron datos remotos y no se realizo QA manual real.

Intento ejecutado:

- Comando seguro desde `sisa.api`: `QA_ALLOW_SEED=1 php scripts/qa/seed-qa-users.php --dry-run`.
- Resultado: BLOCKED. El loader reporto que no encontro `.env` en el workspace y la conexion MySQL local fue rechazada (`SQLSTATE[HY000] [2002]`).
- Verificacion adicional por nombres de archivo: no hay `.env`, `.env.*` ni ejemplos de env en el workspace. No se leyo ningun secreto.

Impacto:

- No se pudo confirmar DB/host/env.
- No se pudo validar que el ambiente conectado sea test/staging.
- No se pudo ejecutar seed QA.
- No se pudo ejecutar matriz manual real por perfiles.

Pendiente para desbloquear:

- Proveer un entorno test/staging accesible localmente o `SISA_ENV_PATH` apuntando a un `.env` de test/staging fuera del repo.
- Confirmar `APP_ENV`/`DB_NAME`/`DB_HOST` seguros o usar `QA_CONFIRM_DB_NAME` si el nombre no contiene `test`, `staging`, `qa`, `local` o `dev`.
- Proveer `QA_PASSWORD` por variable de entorno fuera del repo solo cuando se autorice `--apply`.
- Reejecutar dry-run y revisar plan antes de cualquier seed real.

Matriz real:

- No ejecutada. No declarar release-ready hasta completar `docs/QA_MANUAL_CHECKLIST.md` con los perfiles `qa_superadmin`, `qa_owner_admin`, `qa_company_admin`, `qa_tecnico`, `qa_admin_caja`, `qa_sin_permisos` y `qa_multiempresa`.

Datos QA:

- No creados. No hay cleanup aplicado ni necesario desde este intento.

Actualizacion remota posterior:

- Se accedio por SSH a `hostinger-codex` en `domains/depros.com.ar/public_html/sistema_test` solo en modo lectura/diagnostico.
- Archivos remotos presentes: `scripts/qa/seed-qa-users.php` y `tests/Routes/ApiPermissionsCoverageTest.php`.
- Validacion remota de sintaxis: `php -l scripts/qa/seed-qa-users.php`, `php -l src/Routes/api.php`, `php -l tests/Routes/ApiPermissionsCoverageTest.php` -> PASS.
- Dry-run remoto ejecutado: `QA_ALLOW_SEED=1 php scripts/qa/seed-qa-users.php --dry-run` -> PASS; mostro empresas QA A/B y perfiles `qa_superadmin`, `qa_owner_admin`, `qa_company_admin`, `qa_tecnico`, `qa_admin_caja`, `qa_sin_permisos`, `qa_multiempresa` con roles esperados.
- PHPUnit remoto no pudo ejecutarse porque `vendor/bin/phpunit` no esta presente como archivo ejecutable/invocable en ese servidor.
- No se ejecuto `--apply`, no se pasaron passwords, no se emitieron tokens, no se modifico base de datos, no se modificaron archivos remotos y no se ejecuto QA manual real.
- Para continuar con seed real hace falta aprobacion explicita para el comando mutante y `QA_PASSWORD` provisto fuera del repo/sin imprimirlo.

Intento `--apply` autorizado posterior:

- Se verifico que existia una variable de shell `QA_PASSWORD`, pero no estaba exportada al entorno (`printenv QA_PASSWORD` no la veia).
- Comando ejecutado: `QA_ALLOW_SEED=1 php scripts/qa/seed-qa-users.php --apply`.
- Resultado: el script aborto antes de mutar con `QA_PASSWORD is required for --apply. It is never printed.`
- No se creo ni modifico ningun dato QA en este intento.
- Para ejecutar seed real, `QA_PASSWORD` debe estar exportado al entorno del comando PHP o pasarse inline por un canal seguro sin mostrar su valor en logs.

Seed real ejecutado por operador y diagnostico posterior:

- El operador ejecuto manualmente `QA_ALLOW_SEED=1 php scripts/qa/seed-qa-users.php --apply` con `QA_PASSWORD` en su sesion SSH. El valor del password no se registra aqui.
- Resultado informado por el seed: usuarios QA creados `user_id=7..13`, pero `Company A=69` y `Company B=69`.
- Diagnostico remoto posterior solo lectura (`SELECT`/`DESCRIBE`) confirmo que solo existe una empresa QA LOOP 6: `id=69`, `razon_social=SISA QA LOOP 6 Empresa B`, `nro_doc=0`.
- Causa probable confirmada por `DESCRIBE empresas`: `nro_doc` es `bigint(20) unsigned`; el seed uso documentos alfanumericos `QA-LOOP6-A/B`, MySQL los coerciono a `0` y el upsert termino colapsando A/B en un unico registro.
- Usuarios QA existen y estan activados: `qa_superadmin` `7`, `qa_owner_admin` `8`, `qa_company_admin` `9`, `qa_tecnico` `10`, `qa_admin_caja` `11`, `qa_sin_permisos` `12`, `qa_multiempresa` `13`.
- Membresias QA existen solo contra `empresa_id=69`; `qa_multiempresa` no tiene dos empresas reales, por lo que la matriz multiempresa A/B queda invalida.
- No se continuo QA real porque hacerlo validaria una matriz falsa. Antes de continuar hay que corregir el seed para usar identificadores numericos validos o una clave textual real, limpiar los datos QA creados y reseedear con A/B separados.
- Pendiente de seguridad operativa: si el password temporal fue expuesto en algun canal no seguro, rotarlo antes de continuar QA manual.

Correccion numerica autorizada y reseed:

- Se corrigio `scripts/qa/seed-qa-users.php` para usar `nro_doc` numericos: Empresa A `990000000001`, Empresa B `990000000002`.
- Se amplio cleanup para capturar el registro roto anterior con `nro_doc=0` y razon social QA LOOP 6.
- Se subio el script corregido al servidor autorizado y `php -l scripts/qa/seed-qa-users.php` remoto -> PASS.
- El operador ejecuto cleanup y apply remoto. Resultado informado: `Company A=70`, `Company B=71`; usuarios QA recreados `user_id=14..20`.
- Verificacion remota solo lectura confirma dos empresas separadas:
  - Empresa A: `id=70`, `nro_doc=990000000001`.
  - Empresa B: `id=71`, `nro_doc=990000000002`.
- Verificacion remota solo lectura confirma membresias:
  - `qa_superadmin` owner en A.
  - `qa_owner_admin` owner en A.
  - `qa_company_admin` admin en A.
  - `qa_tecnico`, `qa_admin_caja`, `qa_sin_permisos` member en A.
  - `qa_multiempresa` member en A y member en B.
- Verificacion remota solo lectura confirma permisos divergentes: `qa_multiempresa` tiene 25 permisos en A y 8 en B.
- Nuevo bloqueo detectado: el seed no creo `clients` ni `invoices` por empresa (`count=0` en A/B). Causa: el helper generico `upsertNamed()` requiere columna `name/title/description/business_name`; `clients` e `invoices` remotos no tienen esas columnas (`clients` usa IDs/scope y `invoices` no tiene columna nominal). Algunos datos dependientes quedaron creados parcialmente, pero el flujo comercial completo no es valido todavia.
- No se continuo QA comercial completa. Pendiente corregir el seed para crear cliente/factura usando columnas reales de esas tablas, limpiar y reseedear antes de ejecutar la matriz manual.

## LOOP 6.1 - Correccion de perfiles QA y bypass owner/admin

Fecha: 2026-07-01.

Estado: implementado localmente en script/documentacion. No se ejecuto `--apply`, no se resetearon passwords, no se emitieron tokens y no se modifico ambiente remoto. Validacion local final ejecutada.

Motivo:

- `Permission::hasPermission()` devuelve `true` para cualquier membresia `approved` con rol `owner` o `admin` dentro de la empresa antes de revisar permisos explicitos.
- Por ese diseno, un perfil `owner/admin` no sirve para validar restricciones finas como "no debe ver purge" o "no debe mutar jobs".
- No se cambio `Permission::hasPermission`; cualquier excepcion como exigir `purgeSyncOperations` incluso a owner/admin requiere decision explicita de producto.

Cambios en seed:

- `qa_owner_admin` queda como `owner` y se documenta como perfil de bypass amplio dentro de empresa; ya no tiene expectativas `hidden`.
- Se agrega `qa_company_admin` como `admin` para validar bypass real de company admin dentro de empresa; no tiene expectativas `hidden`.
- `qa_admin_caja` cambia de `admin` a `member` con permisos explicitos de caja/contabilidad, para validar que no tenga mutaciones tecnicas.
- `qa_multiempresa` cambia empresa A de `admin` a `member`; empresa B sigue `member`. Ahora valida permisos finos divergentes A/B.
- Se agrega guardia `assertProfileMatrixIsHonest()`: si un perfil tiene rol `owner`/`admin` y expectativas `hidden`, el seed aborta incluso en dry-run para evitar matrices falsas.

Perfiles de bypass amplio:

- `qa_superadmin`: owner con permisos amplios delegados; no reemplaza prueba hardcoded `user_id=1`.
- `qa_owner_admin`: owner aprobado; valida bypass owner dentro de empresa.
- `qa_company_admin`: admin aprobado; valida bypass admin dentro de empresa.

Perfiles de permisos finos:

- `qa_tecnico`: member con permisos tecnicos.
- `qa_admin_caja`: member con permisos caja/contabilidad.
- `qa_sin_permisos`: member sin permisos operativos.
- `qa_multiempresa`: member en A con caja/contabilidad y member en B readonly.

Documentacion:

- `docs/QA_MANUAL_CHECKLIST.md` corrige dry-run a `QA_ALLOW_SEED=1 php scripts/qa/seed-qa-users.php --dry-run`.
- La tabla de perfiles aclara que `owner/admin` valida bypass de rol y que las restricciones finas se prueban con perfiles `member`.

Validacion local LOOP 6.1:

- API: `php -l scripts/qa/seed-qa-users.php` -> PASS.
- API: `php -l src/Routes/api.php` -> PASS.
- API: `php -l tests/Routes/ApiPermissionsCoverageTest.php` -> PASS.
- API: `vendor/bin/phpunit tests/Routes/ApiPermissionsCoverageTest.php` -> PASS (20 tests, 88 assertions) con warning PHPUnit baseline.
- Web: `npm run check:permissions-audit` -> PASS.
- Web: `npm run check:commercial-flow` -> PASS (15 checks).
- Web: `npm run lint` -> PASS.
- Web: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- Mobile: `npm run check:permissions-audit` -> PASS.
- Mobile: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188 selectedJobRecord`.
- Mobile: `npm run check:cache` -> PASS.

## LOOP 6 - Preparacion QA real

Fecha: 2026-07-01.

Estado: preparado localmente sin ejecutar mutaciones. Se agregaron script seguro de seed y checklist manual; no se resetearon passwords, no se emitieron tokens, no se tocaron datos vivos. Validacion local final ejecutada.

Ambiente elegido:

- Ambiente objetivo: `sistema-test` o staging/base test equivalente.
- No usar datos productivos reales.
- El seed aborta si no detecta ambiente test/staging/qa/local/dev o confirmacion explicita `QA_CONFIRM_DB_NAME`.
- El seed exige `QA_ALLOW_SEED=1` antes de cualquier modo y `QA_PASSWORD` para mutar usuarios.
- Passwords, tokens y secretos no se imprimen ni se documentan.

Script QA:

- Archivo: `sisa.api/scripts/qa/seed-qa-users.php`.
- Dry-run: `QA_ALLOW_SEED=1 php scripts/qa/seed-qa-users.php --dry-run`.
- Seed real autorizado: `QA_ALLOW_SEED=1 QA_PASSWORD=<secreto-fuera-del-repo> php scripts/qa/seed-qa-users.php --apply`.
- Password por perfil opcional: `QA_PASSWORD_QA_TECNICO`, `QA_PASSWORD_QA_ADMIN_CAJA`, etc.; si no existe usa `QA_PASSWORD`.
- Limpieza autorizada: `QA_ALLOW_SEED=1 php scripts/qa/seed-qa-users.php --cleanup --apply`.
- El script carga `.env`, conecta a DB, valida ambiente seguro, crea/actualiza empresas QA A/B, usuarios, membresias approved, permisos por perfil y datos minimos.
- `qa_superadmin` es superadmin delegado por permisos amplios; no reemplaza la prueba hardcoded de `user_id=1`, que debe ejecutarse con cuenta superadmin existente y autorizada.

Perfiles planificados:

| Perfil | Username/email | Empresa | Rol/membresia | Permisos esperados | Debe ver | No debe ver |
|---|---|---|---|---|---|---|
| Superadmin delegado | `qa_superadmin` / `qa_superadmin@sisa-qa.invalid` | QA A | owner approved | Permisos operativos amplios incluyendo `purgeSyncOperations` | Todos los modulos operativos | No valida bypass hardcoded `user_id=1` |
| Owner/admin | `qa_owner_admin` / `qa_owner_admin@sisa-qa.invalid` | QA A | owner approved | Bypass owner dentro de empresa | Todos los modulos por rol owner | No usar para restricciones finas |
| Company admin | `qa_company_admin` / `qa_company_admin@sisa-qa.invalid` | QA A | admin approved | Bypass admin dentro de empresa | Todos los modulos por rol admin | No usar para restricciones finas |
| Tecnico | `qa_tecnico` / `qa_tecnico@sisa-qa.invalid` | QA A | member approved | Clientes lectura, jobs, job items, worklogs, adjuntos, estados/prioridades | Operacion tecnica y adjuntos | Caja/contabilidad mutante, settings contables |
| Admin caja | `qa_admin_caja` / `qa_admin_caja@sisa-qa.invalid` | QA A | member approved | Caja, pagos, recibos, facturas, resumen cliente, analytics/settings contables | Flujos administrativos y caja | Mutacion tecnica de jobs/worklogs |
| Sin permisos sensibles | `qa_sin_permisos` / `qa_sin_permisos@sisa-qa.invalid` | QA A | member approved | Sin permisos operativos | Perfil/settings basicos | Modulos sensibles y acciones CRUD |
| Multiempresa | `qa_multiempresa` / `qa_multiempresa@sisa-qa.invalid` | QA A y QA B | A member approved, B member approved | A caja/contabilidad; B lectura limitada | A: admin caja por permisos; B: lectura | Permisos de A aplicados en B |

Datos seed planificados:

- Empresas: `SISA QA LOOP 6 Empresa A` (`nro_doc=990000000001`) y `SISA QA LOOP 6 Empresa B` (`nro_doc=990000000002`).
- Usuarios QA anteriores con emails `@sisa-qa.invalid` se actualizan idempotentemente.
- Membresias approved por perfil.
- Permisos reemplazados por usuario/empresa para evitar permisos residuales.
- Datos minimos por empresa cuando existen tablas/columnas compatibles: cliente QA, carpeta QA, trabajo QA, worklog QA, producto/servicio QA, caja QA, factura QA, item de factura QA, recibo QA y pago QA.
- Adjunto QA queda opcional/manual para evitar subir archivos o tocar storage durante seed.

Reversion/limpieza:

- `QA_ALLOW_SEED=1 php scripts/qa/seed-qa-users.php --cleanup --apply` elimina usuarios QA, permisos, membresias, empresas QA A/B y datos company-scoped asociados.
- La limpieza identifica usuarios por emails `@sisa-qa.invalid` y empresas por `nro_doc` `990000000001/990000000002`; tambien captura el registro roto anterior con `nro_doc=0` y razon social QA LOOP 6 si existiera.
- Ejecutar limpieza solo en test/staging y con autorizacion.

Checklist manual:

- Archivo agregado: `docs/QA_MANUAL_CHECKLIST.md`.
- Cubre Web: login, cambio empresa, dashboard, clientes, trabajos, preparar factura, PDF, recibos, pagos, resumen cliente, adjuntos, analytics/settings y permisos ocultos.
- Cubre Mobile: login, cambio empresa, rutas sensibles, detalle trabajo, worklogs, adjuntos, recibos/pagos, tracking/nearby y deep links bloqueados.
- Cubre Multiempresa: permisos A/B divergentes, limpieza de cache/menu/permisos al cambiar empresa y verificacion de `company_id`/`X-Company-Id`.

No ejecutado en LOOP 6:

- No se corrio `--apply`.
- No se resetearon passwords.
- No se crearon sesiones ni tokens.
- No se modifico ambiente remoto.
- No se hizo QA real por navegador/dispositivo; queda para LOOP 7 o ejecucion autorizada posterior.

Validacion local LOOP 6:

- `sisa.api`: `php -l scripts/qa/seed-qa-users.php` -> PASS.
- `sisa.api`: `php -l src/Routes/api.php` -> PASS.
- `sisa.api`: `php -l tests/Routes/ApiPermissionsCoverageTest.php` -> PASS.
- `sisa.api`: `vendor/bin/phpunit tests/Routes/ApiPermissionsCoverageTest.php` -> PASS (20 tests, 88 assertions) con warning PHPUnit baseline.
- `sisa.web`: `npm run check:permissions-audit` -> PASS.
- `sisa.web`: `npm run check:commercial-flow` -> PASS (15 checks).
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.ui`: `npm run check:permissions-audit` -> PASS.
- `sisa.ui`: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188 selectedJobRecord`.
- `sisa.ui`: `npm run check:cache` -> PASS.

## LOOP 5 - QA real por perfiles y flujo comercial completo

Fecha: 2026-07-01.

Estado: parcialmente ejecutado. Se completo QA automatizada local y smoke comercial estatico; la ejecucion real por perfiles en `sistema-test` queda bloqueada porque no hay credenciales activas documentadas ni autorizacion vigente para resetear passwords, emitir tokens o mutar datos vivos.

Alcance ejecutado:

- `sisa.api`: validacion focalizada de rutas/permisos multiempresa con `ApiPermissionsCoverageTest`.
- `sisa.web`: auditoria de permisos, lint, build y smoke comercial estatico (`cliente -> trabajo -> factura -> recibo/resumen`, guards de permisos y enlaces principales).
- `sisa.ui`: auditoria de permisos mobile, lint y guardia de cache.
- `sisa`: documentacion de matriz, bloqueos y decision de `dist` en este estado.

Matriz de perfiles solicitados:

| Perfil | Estado LOOP 5 | Evidencia ejecutada | Pendiente real |
|---|---|---|---|
| Superadmin `user_id=1` | No ejecutado en vivo | PHPUnit cubre bypass superadmin en middleware y permisos propios/de terceros | Login real, dashboard, purge, permisos, flujo comercial completo |
| Owner/admin empresa | No ejecutado en vivo | Defaults y permisos `updateCompanyAccountingSettings` cubiertos por tests; auditorias web/mobile PASS | Crear/editar flujo comercial completo y settings contables en empresa real |
| Tecnico/operativo limitado | No ejecutado en vivo | Auditoria mobile cubre deep-links y acciones nearby; smoke web cubre acciones tecnicas/flujo comercial estatico | Validar trabajo/worklog/adjuntos en web/app con permisos limitados |
| Administrativo caja/contabilidad sin tecnicos | No ejecutado en vivo | Auditorias web cubren Payments/Receipts/Invoices/Analytics/Settings; API separa lectura/mutacion settings | Validar caja/contabilidad sin menus tecnicos ni requests tecnicos |
| Usuario sin permisos sensibles | No ejecutado en vivo | Auditorias verifican ocultamiento de acciones y rutas sensibles | Validar dashboard vacio/controlado, sin botones ni requests no autorizadas |
| Usuario miembro de dos empresas | No ejecutado en vivo | Middleware/test cubre scope explicito, no fallback por `id`, fail-closed sin company scope | Cambiar empresa A/B, confirmar limpieza de permisos/cache y ausencia de permisos cruzados |

API - estado:

- Endpoints con `PermissionsMiddleware` siguen exigiendo scope por `company_id`, `X-Company-Id`, body o route arg explicito; la cobertura automatizada valida fail-closed cuando falta scope.
- Rutas legacy join requests sin `company_id` mantienen `PermissionsMiddleware('manageCompanyMemberships')`; deben fallar cerrado si no se envia scope. Pendiente confirmar con HTTP real.
- Aliases `/companies/{company_id}/join-requests/{request_id}/approve|reject` estan cubiertos por test de ruta/middleware y controller valida pertenencia de `request_id` a empresa; pendiente HTTP real.
- `/sync/v2/purge` y `/sync/v3/purge` requieren `purgeSyncOperations`; cobertura estatica valida middleware. Pendiente HTTP real con usuario comun, usuario con permiso y superadmin.
- `GET /company-accounting-settings` acepta `viewAccountingSummary`, `listCompanies` o `updateCompanyAccountingSettings`; `PUT` solo `updateCompanyAccountingSettings`. Cubierto por test especifico.
- `/permissions/user/{user_id}` mantiene permisos propios solo con membresia `approved` y delegacion por `listPermissions`; cubierto por tests focalizados.

Web - estado:

- Dashboard, Providers, Quotes, Payments, Receipts, Invoices, Jobs, Clients, Catalogs, Attachments, Analytics y Settings quedan cubiertos por auditoria estatica de permisos y/o smoke comercial; no reemplaza navegacion real.
- `AttachmentsPage` no debe cargar fuentes no autorizadas segun permisos de dominio + `downloadFile`; cubierto por auditoria estatica, pendiente confirmar requests reales en Network tab.
- Analytics queda separado: lectura con `viewAccountingSummary`/`listWorkLogs`, lectura settings con `viewAccountingSummary`/`listCompanies`/`updateCompanyAccountingSettings`, mutacion solo `updateCompanyAccountingSettings`. Cubierto por auditoria y build.
- Settings contables lee solo con permisos compatibles y guarda solo con `updateCompanyAccountingSettings`. Cubierto por auditoria y build.

Mobile - estado:

- `routePermissions` y auditoria cubren deep links sensibles: `/jobs/create`, `/jobs/worklog-form`, `/clients/accounting`, `/journal_entries`, `/network/logs`, `/tracking/nearby-clients`.
- Cards sin permiso no deben navegar; recibos/pagos editan solo con `updateReceipt`/`updatePayment`; `nearby-clients` no propone job sin `addJob`, no carga/renderiza proveedores sin `addPayment`, y no abre job sin `getJob`/`listJobs`. Cubierto por auditoria estatica, pendiente prueba real en dispositivo/simulador con perfiles.

Flujo comercial principal:

- Smoke estatico web PASS para enlaces y guards principales de cliente, trabajos, facturas, recibos, resumen y detalle tecnico mobile.
- No se ejecuto flujo real `cliente -> carpeta -> trabajo -> worklog -> factura -> recibo/cobro -> pago -> resumen de cuenta -> adjuntos -> reportes/PDF` porque requiere credenciales y datos QA activos.

Bugs encontrados:

- No se detectaron bugs nuevos durante las validaciones automatizadas de LOOP 5.
- Bloqueo operativo: falta sesion/credenciales autorizadas para ejecutar matriz real por perfiles y mutaciones controladas en ambiente vivo.

Cambios minimos aplicados:

- No se aplicaron cambios de codigo en LOOP 5. Solo se actualizo `QA_STATUS.md` con resultados, bloqueos y decision de `dist`.

Decision sobre `sisa.web/dist`:

- Decision actual: mantener `dist` versionado para releases web mientras Hostinger despliegue por copia de `dist/index.html` y assets generados, porque no hay workflow/pipeline documentado que construya en servidor.
- Si se incorpora deploy con build remoto/CI, la decision debe cambiar a revertir `dist` generado localmente y agregarlo a `.gitignore` o al mecanismo equivalente.
- En el estado actual, los cambios de `dist` generados por `npm run build` deben revisarse como artefactos de release, no como fuente manual.

Validacion local LOOP 5:

- `sisa.api`: `php -l src/Routes/api.php` -> PASS.
- `sisa.api`: `php -l tests/Routes/ApiPermissionsCoverageTest.php` -> PASS.
- `sisa.api`: `vendor/bin/phpunit tests/Routes/ApiPermissionsCoverageTest.php` -> PASS (20 tests, 88 assertions) con warning PHPUnit baseline.
- `sisa.web`: `npm run check:permissions-audit` -> PASS.
- `sisa.web`: `npm run check:commercial-flow` -> PASS (15 checks).
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.ui`: `npm run check:permissions-audit` -> PASS.
- `sisa.ui`: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188 selectedJobRecord`.
- `sisa.ui`: `npm run check:cache` -> PASS.

Pendientes para release:

- Obtener credenciales QA activas o autorizacion explicita para resetear passwords/emitir sesiones temporales sin imprimir secretos.
- Ejecutar matriz real por los seis perfiles en `sistema-test` o ambiente equivalente.
- Ejecutar flujo comercial completo con datos QA controlados y evidencia de responses/PDF/adjuntos.
- Confirmar en Network tab o logs que todos los endpoints indirectos envian `company_id` o `X-Company-Id` con la empresa activa.
- Probar multiempresa A/B con permisos divergentes y confirmar que cambio de empresa limpia permisos/cache/menus.
- Probar mobile en dispositivo/simulador con deep links y perfiles reales.
- Decidir antes de commit/release si los artefactos `sisa.web/dist` generados en este workspace forman parte del paquete a desplegar.

## LOOP 4.1 - Separacion lectura/mutacion Analytics settings

Fecha: 2026-07-01.

Estado: implementado localmente en `sisa.api` y `sisa.web`; `sisa.ui` no requirio cambios; validacion final ejecutada.

Motivo:

- LOOP 4 introdujo un bug funcional: `AnalyticsPage.loadAnalytics()` exigia `updateCompanyAccountingSettings`, bloqueando lectura de Analytics a usuarios con `viewAccountingSummary` o `listWorkLogs` pero sin permiso de mutacion.
- `GET /company-accounting-settings` no aceptaba `updateCompanyAccountingSettings`, aunque un usuario que puede guardar necesita leer el valor actual antes de modificarlo.

API:

- `GET /company-accounting-settings` ahora acepta `viewAccountingSummary`, `listCompanies` o `updateCompanyAccountingSettings`.
- `PUT /company-accounting-settings` sigue aceptando solo `updateCompanyAccountingSettings`.
- `ApiPermissionsCoverageTest` verifica que GET incluya lectura complementaria con `updateCompanyAccountingSettings` y que `viewAccountingSummary`/`listCompanies` no habiliten PUT.

Web:

- `AnalyticsPage` separa permisos:
  - Lectura de Analytics: `viewAccountingSummary` o `listWorkLogs`.
  - Lectura de settings contables: `viewAccountingSummary`, `listCompanies` o `updateCompanyAccountingSettings`.
  - Mutacion de settings contables: solo `updateCompanyAccountingSettings`.
- `loadAnalytics()` ya no exige permiso de mutacion para cargar datos; carga analytics si hay permiso de lectura y carga settings solo si hay permiso compatible.
- Si el usuario no puede leer settings contables, la UI conserva el valor visual por defecto y no intenta leerlo.
- Si el usuario no tiene permisos de lectura para Analytics, la pagina muestra `EmptyState` explicito en vez de quedar vacia.
- `SettingsPage` solo llama `getCompanyAccountingSettings` si hay permiso de lectura compatible, evitando 403 silenciosos; guardar sigue protegido exclusivamente por `updateCompanyAccountingSettings`.
- `scripts/permissions-audit.js` falla si `loadAnalytics()` vuelve a depender de `canUpdateAccountingSettings`, si faltan permisos separados de lectura/mutacion o si GET settings deja de aceptar `updateCompanyAccountingSettings`.

Dist:

- `sisa.web/dist` ya estaba modificado por `npm run build` de LOOP 4 y `npm run build` de LOOP 4.1 volvio a refrescar hashes. Queda pendiente decision explicita de versionado/deploy: commitear assets generados o revertirlos antes de commit. LOOP 4.1 no modifica `dist` manualmente.

Validacion local:

- `sisa.api`: `php -l src/Routes/api.php` -> PASS.
- `sisa.api`: `php -l tests/Routes/ApiPermissionsCoverageTest.php` -> PASS.
- `sisa.api`: `vendor/bin/phpunit tests/Routes/ApiPermissionsCoverageTest.php` -> PASS (20 tests, 88 assertions) con warning PHPUnit baseline.
- `sisa.web`: `npm run check:permissions-audit` -> PASS.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB; refresco assets en `dist`.

## LOOP 4 - Endurecimiento final permisos/sync/settings

Fecha: 2026-07-01.

Estado: implementado localmente en `sisa.api`, `sisa.web` y `sisa.ui`; validacion completa final ejecutada.

API:

- `/sync/v2/purge` y `/sync/v3/purge` dejaron de estar en allowlist bootstrap y ahora requieren `PermissionsMiddleware('purgeSyncOperations')` scoped por empresa.
- Se agregaron aliases explicitos scoped para approve/reject de join requests: `/companies/{company_id}/join-requests/{request_id}/approve` y `/reject`.
- Las rutas legacy `/companies/join-requests/{request_id}/approve|reject` se mantienen, pero siguen requiriendo scope por `company_id`/`X-Company-Id`/body mediante middleware.
- `CompanyUsersController::approveJoinRequest` y `rejectJoinRequest` validan que `request_id` pertenezca al `company_id` de la ruta explicita; si no coincide, responden 404.
- `PUT /company-accounting-settings` ahora requiere `updateCompanyAccountingSettings`; `GET /company-accounting-settings` conserva permisos de lectura.
- `Permission::ACCOUNTING_PERMISSIONS` registra `updateCompanyAccountingSettings`; `Permission::ACCOUNTING_LEDGER_PERMISSIONS` registra `purgeSyncOperations`.
- `updateCompanyAccountingSettings` se agrega a defaults `owner` y `admin` para no romper company admins existentes; `purgeSyncOperations` queda catalogado pero no concedido por default.
- `ApiPermissionsCoverageTest` ahora exige middleware para purge, join aliases y settings contables, y verifica el registro/defaults de los permisos nuevos.

Web:

- `AnalyticsPage` y `SettingsPage` dejan de depender de rol owner/admin para mutar cierre contable y usan `can('updateCompanyAccountingSettings')`.
- Los handlers de guardado contable retornan sin ejecutar si falta `updateCompanyAccountingSettings`; los botones de guardado no se renderizan sin permiso.
- `AttachmentsPage` cierra automaticamente el modal fiscal y limpia el filtro de facturas contables si se pierde permiso; export/print fiscal tambien retornan si falta permiso.
- `scripts/permissions-audit.js` amplia checks a `PaymentsPage`, `ReceiptsPage`, `InvoicesPage`, `JobsPage`, `ClientsPage`, `DashboardPage`, `AnalyticsPage`, `SettingsPage` y `AttachmentsPage`.

Mobile:

- `src/constants/permissionCatalog.ts` incluye `updateCompanyAccountingSettings`, `purgeSyncOperations` y `listNetworkLogs`.
- `tracking/nearby-clients.tsx` ya no carga ni renderiza proveedores cercanos si falta `addPayment`; sigue mostrando acciones de job solo con `addJob`/`getJob`/`listJobs`.
- `scripts/permissions-audit.js` agrega guardia para detectar carga/render de proveedores cercanos sin `addPayment`.

Validacion local:

- `sisa.web`: `npm run check:permissions-audit` -> PASS.
- `sisa.ui`: `npm run check:permissions-audit` -> PASS.
- `sisa.api`: `php -l src/Routes/api.php`, `php -l src/Controllers/PermissionsController.php`, `php -l src/Controllers/CompanyUsersController.php`, `php -l src/Models/Permission.php`, `php -l tests/Routes/ApiPermissionsCoverageTest.php` -> PASS.
- `sisa.api`: `vendor/bin/phpunit tests/Routes/ApiPermissionsCoverageTest.php` -> PASS (19 tests, 79 assertions) con warning PHPUnit baseline.
- `sisa.web`: `npm run lint` -> PASS.
- `sisa.web`: `npm run build` -> PASS con warning baseline de chunks mayores a 500 kB.
- `sisa.ui`: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188 selectedJobRecord`.
- `sisa.ui`: `npm run check:cache` -> PASS.

## LOOP 3 - Acciones sensibles web/mobile y cierre menor API 2.1

Fecha: 2026-07-01.

Estado: implementado localmente en `sisa.api`, `sisa.web` y `sisa.ui` con cambios focalizados, sin rediseño masivo.

API - cierre menor LOOP 2.1:

- `QA_STATUS.md` existe en la raiz compartida del workspace; no se creo `docs/QA_STATUS.md` duplicado.
- `PermissionsController::listPermissionsByUser` mantiene `company_id` obligatorio.
- Un usuario comun puede consultar sus propios permisos sin `listPermissions` solo si tiene membresia `approved` en esa empresa.
- Si consulta sus propios permisos para una empresa donde no es miembro aprobado, responde `403` con `code=COMPANY_MEMBERSHIP_REQUIRED`.
- Para consultar permisos de otro usuario se mantiene permiso delegado unico `listPermissions` scoped al `company_id` solicitado.
- Superadmin `user_id=1` sigue permitido.
- Tests API agregados: propio con membresia approved, propio sin membresia approved, otro sin permiso, otro con `listPermissions`, falta `company_id`, permiso de otra empresa y superadmin.

Web - acciones ocultas por permiso de accion:

- `ProvidersPage`: crear/guardar/eliminar proveedor ahora requiere `addProvider`/`updateProvider`/`deleteProvider`; filas no abren editor si el usuario no puede editar/eliminar.
- `AttachmentsPage`: la bandeja ya no carga todas las fuentes solo por estar autenticado. Carga trabajos/items/worklogs/pagos/recibos solo si existen permisos de dominio y `downloadFile`; no muestra secciones ni filtros de entidades no permitidas. Informe fiscal/IVA requiere permisos contables o de pagos y `downloadFile`.
- `QuotesPage`: crear, editar cabecera, cambiar estado, exportar PDF, eliminar, crear/editar/eliminar/reordenar items quedan condicionados por permisos de accion (`addQuote`, `updateQuote`, `changeQuoteStatus`, `exportQuotePdf`, `deleteQuote`, `add/update/deleteQuoteItem`).
- `ReferenceCatalogsPages`: cajas, categorias, productos/servicios y tarifas ocultan crear/guardar/eliminar por permisos `add/update/delete*`; la imagen de caja requiere ademas `uploadFile`.
- `FinanceCatalogsPages`: nueva transferencia requiere `addTransfer`; crear/anular/regularizar cierres queda condicionado por `addClosing`, `updateClosing` y `resolveClosingDifference`/`updateClosing`.
- `scripts/permissions-audit.js` ahora falla si vuelven rutas privadas sin `ProtectedRoute`, nav live sin permiso o las brechas de acciones sensibles conocidas.

Mobile - rutas y acciones blindadas:

- `routePermissions.ts` agrega rutas sensibles antes de prefijos amplios: `/jobs/create`, `/jobs/worklog-form`, `/jobs/sync`, `/jobs/groups`, `/jobs/root-causes`, `/clients/accounting`, `/clients/finalizedJobs`, `/clients/unpaidInvoices`, `/clients/calendar`, `/journal_entries`, `/network/logs`.
- `receipts/index.tsx` y `receipts/viewModal.tsx`: editar recibo requiere `updateReceipt`; long press y boton editar no existen sin permiso.
- `payments/index.tsx` y `payments/viewModal.tsx`: editar pago requiere `updatePayment`; long press y boton editar no existen sin permiso.
- `tracking/index.tsx`: cards sin permiso dejan de renderizar; ya no son tarjetas deshabilitadas visualmente con `onPress` activo.
- `tracking/nearby-clients.tsx`: crear trabajo requiere `addJob`, abrir trabajo requiere `getJob`/`listJobs`, registrar compra/pago requiere `addPayment`.
- `scripts/permissions-audit.js` ahora falla si faltan route permissions sensibles, si vuelve edit receipt/payment sin permisos de update, si tracking usa disabled navegable o si nearby crea jobs/pagos sin permisos de accion.

Decision Analytics/Settings web:

- Queda pendiente para LOOP 4 definir permiso dedicado `updateCompanyAccountingSettings` o dejar excepcion owner/admin documentada. No se cambio semantica en este loop para evitar romper configuracion contable existente.

Pendientes para LOOP 4:

- Endurecer o decidir politica final para `/sync/v2/purge` y `/sync/v3/purge`.
- Agregar alias API con empresa explicita para approve/reject join requests: `/companies/{company_id}/join-requests/{request_id}/approve|reject`.
- Revisar con QA real que todos los clientes web/mobile envien `company_id` o `X-Company-Id` en endpoints indirectos como `file_attachments`.
- Completar permiso dedicado para settings contables si producto no acepta excepcion owner/admin.
- Ampliar auditorias estaticas a mas paginas, incluyendo `PaymentsPage`, `ReceiptsPage`, `InvoicesPage`, `JobsPage`, `ClientsPage`, `DashboardPage`, `AnalyticsPage` y `SettingsPage`, mas alla de las brechas ya cerradas.

Validacion local parcial ya ejecutada:

- `sisa.api`: `php -l src/Controllers/PermissionsController.php`, `php -l tests/Routes/ApiPermissionsCoverageTest.php`, `vendor/bin/phpunit tests/Routes/ApiPermissionsCoverageTest.php` -> PASS (18 tests, 63 assertions) con warning PHPUnit baseline.
- `sisa.web`: `npm run check:permissions-audit` -> PASS; `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes.
- `sisa.ui`: `npm run check:permissions-audit` -> PASS; `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188 selectedJobRecord`; `npm run check:cache` -> PASS.

## LOOP 2.1 - Bootstrap de permisos propio sin reabrir multiempresa

Fecha: 2026-07-01.

Estado: implementado localmente en `sisa.api`; correccion focalizada para no bloquear el bootstrap de permisos de web/mobile.

Motivo:

- `GET /permissions/user/{user_id}` es usado por web/mobile para hidratar permisos propios al iniciar sesion.
- En LOOP 2 habia quedado protegido con `PermissionsMiddleware('listPermissions')`, lo que bloqueaba a usuarios comunes antes de poder cargar sus propios permisos.
- No se reabre la brecha multiempresa: `company_id` sigue siendo obligatorio y la autorizacion fina queda en `PermissionsController::listPermissionsByUser`.

Decision tomada:

- `GET /permissions/user/{user_id}` vuelve a tener solo `CheckUserBlockedMiddleware` en ruta.
- `PermissionsController::listPermissionsByUser` permite consultar permisos propios si `requestingUserId === userId`.
- Para consultar permisos de otro usuario se exige permiso delegado unico `listPermissions`, scoped al `company_id` solicitado.
- Se deja de usar `listPermissionsByUser` para no mezclar permisos ni depender de un permiso no catalogado.
- Superadmin `user_id=1` sigue pudiendo consultar permisos de otros usuarios.

Tests agregados/ajustados:

- Usuario comun puede consultar `/permissions/user/{same_user_id}?company_id=X` sin `listPermissions`.
- Usuario comun no puede consultar permisos de otro usuario sin `listPermissions`.
- Usuario con `listPermissions` puede consultar otro usuario de la misma empresa.
- Falta `company_id` devuelve 400.
- Permiso en otra empresa no autoriza consulta usando un `company_id` distinto.
- Superadmin sigue funcionando.
- La auditoria estatica clasifica `/permissions/user/{user_id}` como endpoint autenticado intencional ownership-aware, no como gap abierto.

Revalidacion de rutas cerradas:

- `GET /file_attachments` conserva `PermissionsMiddleware('downloadFile')`; requiere `company_id` o `X-Company-Id` porque el scope no esta en la ruta y el controlador ademas valida `attachable_type`/`attachable_uuid` contra empresas del usuario.
- `POST /companies/join-requests/{request_id}/approve` y `/reject` conservan `PermissionsMiddleware('manageCompanyMemberships')`; al no incluir `company_id` en la ruta, requieren `company_id` o `X-Company-Id`. Pendiente para un loop posterior: agregar alias con empresa explicita `/companies/{company_id}/join-requests/{request_id}/approve|reject` si se quiere evitar dependencia del header/query.

Validacion local:

- `php -l src/Routes/api.php` -> PASS.
- `php -l src/Controllers/PermissionsController.php` -> PASS.
- `php -l tests/Routes/ApiPermissionsCoverageTest.php` -> PASS.
- `vendor/bin/phpunit tests/Routes/ApiPermissionsCoverageTest.php` -> PASS (17 tests, 59 assertions) con warning PHPUnit baseline.

## LOOP 2 - API permisos y multiempresa

Fecha: 2026-07-01.

Estado: implementado localmente en `sisa.api`; cambios limitados a middleware, rutas API, tests de cobertura y documentacion. No se tocaron web/mobile.

Endpoints cerrados:

- `GET /permissions/user/{user_id}` ahora usa `PermissionsMiddleware('listPermissions')` y requiere scope de empresa por `company_id`/`X-Company-Id`/body. El controlador conserva validacion adicional para consulta propia/delegada por empresa.
- `GET /file_attachments` ahora usa `PermissionsMiddleware('downloadFile')`. Como el scope real viene de `attachable_type`/`attachable_uuid`, el middleware no intenta inferirlo; el cliente debe enviar `company_id` o `X-Company-Id` y el controlador sigue resolviendo/validando la entidad adjunta contra el scope del usuario.
- `GET /companies/{companyId}/users` ahora usa `PermissionsMiddleware('listCompanyMembers')` y el middleware resuelve `companyId` desde la ruta.
- `GET /companies/{company_id:[0-9]+}/join-requests` ahora usa `PermissionsMiddleware('listCompanyMembers')` y el middleware resuelve `company_id` desde la ruta.
- `POST /companies/join-requests/{request_id:[0-9]+}/approve` ahora usa `PermissionsMiddleware('manageCompanyMemberships')`. Requiere `company_id` por query/header/body porque la ruta no contiene empresa.
- `POST /companies/join-requests/{request_id:[0-9]+}/reject` ahora usa `PermissionsMiddleware('manageCompanyMemberships')`. Requiere `company_id` por query/header/body porque la ruta no contiene empresa.

Endpoints intencionales:

- Se mantiene allowlist autenticada para self-service/session/device: refresh token, perfil propio, configuraciones propias, empresas propias/activas, solicitudes propias de membresia y dispositivos propios.
- Se mantiene allowlist bootstrap/sync para `/sync/*`, `/sync/v2/*`, `/sync/v3/*` y `/bootstrap` por necesidad offline-first.
- `/sync/v2/purge` y `/sync/v3/purge` quedan marcados como high-risk allowlist hasta decision final de permiso/admin/device guard dedicado.
- `PermissionsMiddleware(..., false)` queda como flag legacy para rutas administrativas/sistema explicitamente unscoped (`publishAppUpdate`, permisos admin, `sendNotifications`). No debe usarse para nuevos endpoints de dominio sin justificacion.

Decision sobre `allowGlobal` y company scope:

- Las rutas con `PermissionsMiddleware` default ahora son company-scoped y fallan cerrado con HTTP 400 y `code=COMPANY_SCOPE_MISSING` si no se puede resolver empresa.
- El middleware resuelve scope desde query `company_id`, header `X-Company-Id`, body `company_id`, route argument `company_id` y route argument `companyId`.
- Se elimino el fallback inseguro que trataba cualquier route argument `id` como permission id. El middleware ya no llama `Permission::getById($id)` para resolver scope generico.
- `Permission::hasPermission(..., allowGlobal=false, companyId=null)` ahora devuelve `false` antes de expandir a todas las empresas aprobadas. La expansion unscoped solo queda disponible cuando el caller la permite explicitamente.
- Superadmin `user_id=1` sigue pasando antes de validacion de scope y permisos.

Tests agregados/actualizados:

- `tests/Routes/ApiPermissionsCoverageTest.php` ahora verifica que no queden rutas autenticadas sin `PermissionsMiddleware` fuera de allowlist.
- El test mueve los gaps reales fuera de `DOCUMENTED_PERMISSION_GAPS` y exige los permisos esperados en las rutas cerradas.
- Cubre resolucion de `company_id` y `companyId` desde route args.
- Cubre que `id` generico no se usa como permission id.
- Cubre fail-closed con `COMPANY_SCOPE_MISSING` cuando falta scope.
- Cubre que no se valida contra otra empresa si falta `company_id`.
- Cubre que superadmin sigue permitido sin scope.
- Cubre rutas legacy unscoped declaradas con flag `false`.
- Cubre que `Permission::hasPermission` no expande scope si `allowGlobal=false` y `companyId=null`.

Riesgos pendientes:

- Los clientes web/mobile/API que llamen endpoints protegidos por recurso indirecto sin `company_id`/`X-Company-Id` recibiran `COMPANY_SCOPE_MISSING`. Para LOOP 3 corresponde ajustar clientes si aparece algun flujo real sin header/scope.
- Muchas rutas `/{id}` de dominio ahora dependen de `company_id` explicito porque el middleware ya no infiere empresa desde ids genericos. Esto es intencional para evitar autorizacion cruzada por empresa, pero requiere validar los clientes consumidores.
- Falta decision final para endurecer `/sync/v2/purge` y `/sync/v3/purge`.

Validacion local:

- `php -l src/Middleware/PermissionsMiddleware.php` -> PASS.
- `php -l src/Models/Permission.php` -> PASS.
- `php -l src/Routes/api.php` -> PASS.
- `php -l tests/Routes/ApiPermissionsCoverageTest.php` -> PASS.
- `vendor/bin/phpunit tests/Routes/ApiPermissionsCoverageTest.php` -> PASS (11 tests, 42 assertions) con warning PHPUnit baseline.

## Blindaje permisos y multiempresa

Fecha: 2026-06-30.

Estado: LOOP 1 auditoria y mapa de brechas completado localmente; se agregaron guardias estaticas minimas sin cambiar semantica funcional de UI/API. No se avanzo a ocultamiento masivo de UI.

| repo | archivo/ruta | estado | permiso esperado | riesgo | accion recomendada |
|---|---|---|---|---|---|
| sisa.web | `src/App.tsx` rutas live privadas | cubierto | `ProtectedRoute` + permiso de navegacion | Bajo: las rutas live privadas estan protegidas; `placeholderModules` queda sin `ProtectedRoute` si aparecen modulos planned futuros. | En LOOP 2 envolver placeholders o no montar planned sin permiso explicito. |
| sisa.web | `src/navigation/app-navigation.ts` | cubierto/parcial | `permission` o `anyOf` por item live | Bajo/medio: los items live tienen metadata salvo rutas intencionales `companies/profile/settings`; `dashboard` omite `listCompanies`. | Ajustar dashboard si debe abrirse solo por `listCompanies`; revisar rutas intencionales. |
| sisa.web | `src/pages/DashboardPage.tsx` | cubierto | permisos por card/metrica | Bajo: dashboard carga/cards mayormente gated; no se detecto exposicion principal sin permiso. | Mantener smoke y revisar cards nuevas. |
| sisa.web | `src/pages/AttachmentsPage.tsx` | faltante | `downloadFile`, `uploadFile`, `listJobs`, `listPayments`, `listReceipts`, `listWorkLogs` segun fuente | Alto: usuario con `uploadFile` puede entrar por nav y ver/descargar adjuntos operativos/financieros. | Separar permiso de lectura/descarga de subida y gatear fuentes/acciones. |
| sisa.web | `src/pages/ProvidersPage.tsx` | faltante | `addProvider`, `updateProvider`, `deleteProvider` | Medio: usuario solo lista podria crear/editar/eliminar proveedores. | Agregar `usePermissions` y ocultar acciones mutantes. |
| sisa.web | `src/pages/QuotesPage.tsx` | faltante | `addQuote`, `updateQuote`, `deleteQuote`, permisos de items/PDF | Alto: acciones de presupuesto, estado, PDF e items no estan separadas de lista. | Gatear cada accion sensible y revisar nav `listQuotes` vs `addQuote`. |
| sisa.web | `src/pages/ReferenceCatalogsPages.tsx` | faltante | `add/update/deleteCashBox`, `add/update/deleteCategory`, `add/update/deleteProductsService`, `add/update/deleteTariff`, `uploadFile` | Alto: catalogos financieros/productivos mutables por usuarios de lista. | Convertir paginas a read-only sin permiso de accion. |
| sisa.web | `src/pages/FinanceCatalogsPages.tsx` transferencias/cierres | faltante | `addTransfer`, `addClosing`, `updateClosing`, `voidAccountingClosing`/equivalente | Alto: movimientos contables/cierres pueden exponerse con permisos de lectura. | Gatear crear/anular/regularizar y definir permisos faltantes si no existen. |
| sisa.web | `src/pages/AnalyticsPage.tsx`, `src/pages/SettingsPage.tsx` | intencional/parcial | permiso dedicado de settings contables o rol owner/admin documentado | Medio: cierre de dia contable usa rol, no permiso granular, desde rutas con permisos de lectura/configuracion. | Decidir politica: permiso dedicado o documentar excepcion owner/admin. |
| sisa.web | `scripts/permissions-audit.js`, `package.json` | cubierto | guardia estatica de rutas/nav y brechas conocidas | Bajo: detecta rutas privadas sin `ProtectedRoute`, nav sin metadata y deuda conocida. | Correr `npm run check:permissions-audit` en revisiones de permisos. |
| sisa.ui | `app/_layout.tsx`, `components/BottomNavigationBar.tsx`, `app/Home.tsx` | cubierto | `permissionsReady`, `canNavigate` para nav | Bajo: shell, bottom nav y Home no muestran menu operativo mientras permisos no estan listos. | Mantener regla; no agregar menus fuera del gate. |
| sisa.ui | `src/permissions/routePermissions.ts` | faltante | permisos especificos por ruta sensible | Alto: faltan entradas explicitas para `/jobs/create`, `/jobs/worklog-form`, `/jobs/sync`, `/jobs/groups`, `/jobs/root-causes`, `/clients/accounting`, `/clients/finalizedJobs`, `/clients/unpaidInvoices`, `/clients/calendar`, `/journal_entries`, `/network/logs`. | Agregar entradas antes de prefijos amplios y validar deep-links. |
| sisa.ui | `app/receipts/index.tsx`, `app/receipts/viewModal.tsx` | faltante | `updateReceipt` para editar | Medio: long press/boton editar visibles sin permiso de accion. | Usar `can('updateReceipt')`; no asumir `listReceipts`. |
| sisa.ui | `app/payments/index.tsx`, `app/payments/viewModal.tsx` | faltante | `updatePayment` para editar | Medio: long press/boton editar visibles sin permiso de accion. | Usar `can('updatePayment')`; no asumir `listPayments`. |
| sisa.ui | `app/tracking/index.tsx` | faltante | permisos de tracking por card y bloqueo real de press | Medio: cards deshabilitadas visualmente pueden seguir navegando. | No renderizar cards sin permiso o implementar `disabled` real en `MenuButton`. |
| sisa.ui | `app/tracking/nearby-clients.tsx` | faltante | `addJob`, `listJobs/getJob`, `addPayment` segun accion | Alto: acciones para crear trabajos/pagos o abrir trabajos no usan permisos de accion/navegacion. | Gatear cada boton con `can()`/`canNavigate()`. |
| sisa.ui | `Home.tsx`, `app/menu/[section].tsx`, pantallas varias | faltante/parcial | `canNavigate` para menu, `can` para acciones | Medio: hay uso directo de `permissions.includes`/`can` para navegacion, saltando convencion de permisos de navegacion vs accion. | Migrar menus a `canNavigate`; acciones a `can/canAny/canAll`. |
| sisa.ui | `scripts/permissions-audit.js`, `package.json` | cubierto | guardia estatica de routePermissions y brechas conocidas | Bajo: enumera rutas sensibles faltantes y acciones sin permiso dedicado. | Correr `npm run check:permissions-audit` junto a lint/cache. |
| sisa.api | `src/Routes/api.php` rutas con `PermissionsMiddleware` | cubierto/parcial | middleware por endpoint de dominio | Medio: la mayoria de CRUD/dominio esta protegido por permiso especifico. | Mantener test de cobertura y agregar permisos a gaps. |
| sisa.api | `/token/refresh`, `/profile`, `/user_profile`, `/user_configurations`, `/companies/my`, `/companies/active`, `/devices`, membership request propias | intencional | autenticado propio / self-service | Bajo/medio: aceptable si controladores fuerzan usuario propio y membresia aprobada. | Mantener allowlist con razon; confirmar deletes de perfil/configuracion. |
| sisa.api | `/sync/*`, `/sync/v2/*`, `/sync/v3/*`, `/bootstrap` | intencional/parcial | bootstrap/sync autenticado; permiso tecnico si aplica | Medio/alto en `/sync/*/purge`: necesario para offline pero de alto impacto. | Documentar contrato; considerar permiso/admin/device guard para purge. |
| sisa.api | `GET /permissions/user/{user_id}` | faltante | `listPermissions` o `listUserPermissions`, preferible `allowGlobal=false` | Alto: expone permisos de otro usuario sin middleware de permiso. | Agregar `PermissionsMiddleware` dedicado y scope de empresa. |
| sisa.api | `GET /file_attachments` | faltante | `downloadFile` o `listFileAttachments` | Medio: metadata de adjuntos sin permiso funcional. | Agregar permiso o documentar metadata como intencional. |
| sisa.api | `GET /companies/{companyId}/users` | faltante | `listCompanyMembers` | Medio: directorio de usuarios de empresa sin middleware de permiso. | Agregar permiso o documentar directorio visible a miembros. |
| sisa.api | join requests company/admin aliases | faltante | `listCompanyMembers`/`manageCompanyMemberships` | Medio: controlador puede validar admin, pero falta capa estandar de permisos. | Agregar middleware en rutas de listar/aprobar/rechazar. |
| sisa.api | `src/Middleware/PermissionsMiddleware.php` company resolution | faltante | resolver `company_id`, `companyId`, body/header/query y recurso; fail-closed cuando corresponda | Alto: si `company_id` queda null, `hasPermission` puede validar contra cualquier empresa/global y permitir por admin de otra empresa antes de que el controller haga scope. | LOOP 2: resolver argumentos explicitos, dejar de interpretar `{id}` generico como permiso salvo rutas de permisos, y definir rutas globales reales. |
| sisa.api | `tests/Routes/ApiPermissionsCoverageTest.php` | cubierto | allowlist clasificada + gaps documentados | Bajo: enumera rutas autenticadas sin `PermissionsMiddleware` y mantiene visible el riesgo de `company_id` null. | Ejecutar PHPUnit focalizado cuando cambie `api.php`/middleware. |

Validaciones de auditoria agregadas:

- `sisa.web`: `npm run check:permissions-audit` -> PASS; reporta brechas conocidas para LOOP 2.
- `sisa.ui`: `npm run check:permissions-audit` -> PASS; reporta brechas conocidas para LOOP 2.
- `sisa.api`: `php -l tests/Routes/ApiPermissionsCoverageTest.php` -> PASS; `vendor/bin/phpunit tests/Routes/ApiPermissionsCoverageTest.php` -> PASS con warnings PHPUnit baseline de configuracion/cobertura.

Queda para LOOP 2:

- Corregir primero `PermissionsMiddleware` para scope de empresa y fail-closed razonable sin romper superadmin/company admin.
- Agregar middleware a gaps API reales o dejar allowlist intencional con decision de producto.
- En web/mobile, ocultar acciones sensibles por permisos de accion, no por permisos de lista.
- Agregar entradas faltantes en `routePermissions.ts` antes de prefijos amplios y revisar deep-links.
- Revisar cache/scope viejo con pruebas multiempresa focalizadas despues de los cambios de middleware/UI.

## SISA API/Web/UI - loop comercial 1.3 QA vivo producto salida al mercado

Fecha: 2026-06-29.

Estado: ejecutado en ambiente vivo `sistema-test.depros.com.ar` por API real con datos QA controlados; no cerrado para salida al mercado porque faltan re-test vivo mutante autorizado de `/void`, verificacion visual real en web/app/PDF y confirmacion del deploy web.

### Loop 1.3.3 - consistencia visual de saldo cliente

Estado: fix backend implementado localmente; validacion focalizada y suite API completa PASS, pendiente deploy/re-test visual vivo.

- Incidencia UI mobile login/startup: se corrigio un loop de navegacion post-login que podia disparar `Maximum update depth exceeded` en React Navigation. `app/login/Login.tsx` ya no ejecuta `router.replace('/Home')`; la navegacion post-auth queda centralizada en `app/_layout.tsx`, que tambien respeta bootstrap, permisos y onboarding.
- Ajuste adicional UI mobile startup: `app/_layout.tsx` ahora deduplica redirects automaticos pendientes (`/Home`, `/company-onboarding`, `/login/Login`) para no repetir `router.replace()` mientras React Navigation todavia no actualizo `pathname`; `app/Home.tsx` usa `navigation.replaceUnique('/company-onboarding')` para el fallback sin empresa. No se cambiaron condiciones de auth, bootstrap, permisos ni onboarding.
- Guardia QA UI mobile: `scripts/startup-stability-smoke.js` fue actualizado a la estructura actual del codigo (push logger lazy, guards por empresa/permisos y callbacks `runWhenIdle` async) para seguir protegiendo estabilidad de arranque sin exigir literales obsoletos.
- Validacion UI mobile login/startup: `npm run check:startup-stability` -> PASS; `npm run lint` -> PASS con warning baseline existente `app/appointments/create.tsx:188 selectedJobRecord`.
- Bug QA visual: en Clientes el listado mostraba `Saldo pendiente: $100,00` para el cliente QA, pero el detalle/resumen del mismo cliente mostraba `Saldo pendiente: $0,00` y `Saldo a favor: $9.900,00`.
- Causa: el listado consumia `clients.accounting_balance` del endpoint `/clients`, calculado como deuda bruta de facturas/cargos sin descontar recibos confirmados; el detalle consumia `/accounting/client-statement`, que calcula saldo comercial neto y separa `pending_balance` de `customer_credit`.
- API: `Clients::buildAccountingSummarySubquery()` y la hidratacion focalizada de summaries ahora descuentan recibos confirmados del cliente para que `accounting_balance` represente el neto comercial real; si hay saldo a favor, el valor queda negativo.
- Web: `sisa.web/src/pages/ClientsPage.tsx` cambia la columna a `Saldo cliente`, etiqueta cada fila como `Saldo pendiente`, `Saldo a favor` o `Saldo cliente`, y evita que el fallback del detalle muestre un saldo negativo como pendiente mientras carga el statement.
- UI mobile: `app/clients/index.tsx` cambia el orden/copy a `Saldo cliente` y la tarjeta etiqueta dinamicamente `Saldo pendiente`, `Saldo a favor` o `Saldo cliente`; cuando el neto es negativo muestra el importe absoluto como saldo a favor, no como deuda.
- Cobertura: `ClientsTest::testListAllUsesNetAccountingBalanceWhenClientHasCredit` reproduce factura emitida por 100 y recibo confirmado por 10000, esperando `accounting_balance=-9900` en el listado. `sisa.web/scripts/commercial-flow-smoke.js` agrega guardia para que la columna de clientes use saldo neto y no presente credito como deuda.
- No se tocaron reglas contables del statement; se alineo el endpoint de listado con la semantica comercial ya vigente del detalle.
- Validacion API: `php -l src/Models/Clients.php`, `php -l tests/Models/ClientsTest.php`, `vendor/bin/phpunit tests/Models/ClientsTest.php` -> PASS (6 tests, 26 assertions); `vendor/bin/phpunit tests/Services/ClientStatementServiceTest.php` -> PASS (11 tests, 43 assertions).
- Validacion Web: `npm run check:commercial-flow` -> PASS (12 checks); `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes de Vite. Los artefactos `dist/` generados por build fueron limpiados.
- Validacion UI mobile: `npm run lint` -> PASS con warning baseline existente `app/appointments/create.tsx:188 selectedJobRecord`.
- Diagnostico remoto solo lectura en `hostinger-codex`, directorio `domains/depros.com.ar/public_html/sistema_test`: el API desplegado esta en commit `da2e8b6` y `src/Models/Clients.php` todavia calcula `accounting_balance` como `ROUND(SUM(summary.unpaid_invoices_total) + SUM(summary.charge_client_payments_total), 2)` y en hidratacion como `round($unpaidInvoicesTotal + $chargeClientPaymentsTotal, 2)`. No contiene `receipt_credit_total`, por lo que el fix local no esta desplegado en ese ambiente. `php -l src/Models/Clients.php` y `php -l src/Services/ClientStatementService.php` remotos -> PASS. No se modificaron archivos ni datos remotos.
- Diagnostico remoto de ubicacion web: bajo el directorio autorizado no aparece `sisa.web/src/pages/ClientsPage.tsx` ni `commercial-flow-smoke.js`; por lectura limitada solo se observo backend/API y archivos parciales de app. Para validar o desplegar el fix visual web en servidor hace falta confirmar la ruta/mecanismo de deploy web, manteniendo aprobacion explicita antes de cualquier escritura.
- Deploy remoto autorizado por usuario con `adelante`: se subio `sisa.api/src/Models/Clients.php` y `sisa.api/tests/Models/ClientsTest.php` a `hostinger-codex:domains/depros.com.ar/public_html/sistema_test`. Validacion remota: `php -l src/Models/Clients.php` y `php -l tests/Models/ClientsTest.php` -> PASS; `grep receipt_credit_total src/Models/Clients.php` confirma calculo neto desplegado. `vendor/bin/phpunit` no existe en el servidor, por lo que PHPUnit remoto no pudo ejecutarse.
- Deploy web remoto: se ubico `sisa.web` en `hostinger-codex:domains/depros.com.ar/public_html/sisa`; se subio `src/pages/ClientsPage.tsx`, `dist/index.html` y los assets nuevos generados localmente (`App-C24GaxMN.js`, `index-jPhXAD5O.js`, `error-boundary-ZIjURKd0.js`, `providers-X1KD-mlJ.js`, `systemCatalogsService-Bce3k6AK.js`) sin borrar assets antiguos. Validacion remota por lectura: `ClientsPage.tsx` contiene `title: 'Saldo cliente'`, `Saldo a favor` y fallback con `Math.max`; `dist/index.html` referencia `/assets/index-jPhXAD5O.js` y el asset nuevo existe en `dist/assets`.
- Ajuste visual posterior: el listado web ahora renderiza el saldo como chip coloreado `client-balance-chip`: `A favor` en verde, `En contra` en rojo y `Sin saldo` neutro, manteniendo texto explicito para no depender solo del color. Se actualizaron `sisa.web/src/pages/ClientsPage.tsx`, `sisa.web/src/app/globals.css` y el smoke comercial.
- Validacion local ajuste visual: `npm run check:commercial-flow` -> PASS (12 checks); `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes de Vite. Los artefactos `dist/` locales generados por build fueron limpiados.
- Deploy remoto ajuste visual: se subieron `src/pages/ClientsPage.tsx`, `src/app/globals.css`, `dist/index.html` y assets nuevos (`App-z5jNpLCF.js`, `globals-CaAtMSfd.css`, `index-BJIOn2H7.js`, `error-boundary-BuhJp1bq.js`, `providers-4PnaWvcB.js`, `systemCatalogsService-BXcSNfgW.js`) a `hostinger-codex:domains/depros.com.ar/public_html/sisa`. Validacion remota por lectura: fuente contiene `client-balance-chip`, `En contra` y `A favor`; CSS contiene variantes `credit/debt/neutral`; bundle `App-z5jNpLCF.js` contiene `client-balance-chip`, `En contra` y `A favor`.
- Ajuste final de copy visual: por decision UX, el listado ya no muestra leyendas visibles `A favor`, `En contra` ni `Sin saldo`; muestra solo el importe con color `credit/debt/neutral` y conserva `aria-label` semantico no visible. Smoke actualizado para proteger que no vuelva el texto visible dentro del chip.
- Validacion/deploy ajuste final: `npm run check:commercial-flow` -> PASS (12 checks); `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes. Se desplego `src/pages/ClientsPage.tsx`, `dist/index.html` y assets nuevos (`App-CvMXqouq.js`, `index-Bzp-x1Bg.js`, `error-boundary-S00aBPV7.js`, `providers-k-84Nqu4.js`, `systemCatalogsService-C4PhDCT8.js`) en `hostinger-codex:domains/depros.com.ar/public_html/sisa`. Validacion remota por lectura: bundle contiene `client-balance-chip` y no contiene leyendas visibles `En contra`/`A favor`.
- Incidencia de deploy: un primer `scp` con destino incorrecto creo accidentalmente el directorio remoto `domains/depros.com.ar/public_html/sistema_test/__opencode_upload_marker_should_not_exist/` con copias de `Clients.php` y `ClientsTest.php`. No afecta la app, pero queda basura creada por esta sesion. No se elimino porque las reglas de seguridad prohiben `rm` sin aprobacion explicita para ese comando.
- Fix backend adicional para factura eliminada con recibos aplicados: `Clients::buildAccountingSummarySubquery()` y `computeAccountingSummaryForClient()` dejan de calcular deuda de factura como `total_amount - aplicaciones`; ahora usan la misma semantica que `ClientStatementService`: facturas activas emitidas/pagadas suman total completo, pagos `charge_client=1` suman deuda y recibos de cliente confirmados/liquidados restan deuda.
- Integridad de aplicaciones: `ClientStatementService`, `InvoiceSettlementSummaryService`, `ReceiptApplicationService` e `InvoiceReceiptPayments::listByReceipt()` ignoran aplicaciones `invoice_receipt_payments` soft-deleted y tambien aplicaciones cuyo `invoice.deleted_at` ya no es NULL, para que facturas eliminadas no contaminen aplicado de recibos ni saldos.
- Eliminacion/anulacion de factura: `InvoiceCancellationService` ahora soft-deletea dentro de la misma transaccion las aplicaciones activas de recibos de la factura, registra history/sync delete con el mismo criterio de detach y reconcilia recibos/factura afectada. No borra recibos; el recibo queda como movimiento comercial del cliente.
- Cobertura API agregada: `ClientStatementServiceTest` cubre factura eliminada + recibo confirmado y pago `charge_client=1` + recibo confirmado con saldo 0; `ClientsTest` agrega paridad `Clients::listAll/listPage` contra `ClientStatementService::build()` y sort por `accounting_balance`; `InvoiceCancellationServiceTest` cubre soft-delete de aplicaciones al eliminar factura; `InvoiceReceiptsAndPaymentsFlowRegressionTest` cubre flujo completo cargo/factura/recibo/aplicacion/delete invoice, trabajo liberado y saldo 0.
- Validacion API local: `php -l src/Models/Clients.php`, `src/Services/ClientStatementService.php`, `src/Services/InvoiceCancellationService.php`, `src/Services/ReceiptApplicationService.php`, `src/Services/InvoiceSettlementSummaryService.php` -> PASS. `vendor/bin/phpunit tests/Services/ClientStatementServiceTest.php` -> PASS (13 tests, 48 assertions); `tests/Models/ClientsTest.php` -> PASS (8 tests, 31 assertions); `tests/Services/InvoiceCancellationServiceTest.php` -> PASS (8 tests, 47 assertions, conserva linea diagnostica baseline del test de transaccion cerrada); `tests/Regression/InvoiceReceiptsAndPaymentsFlowRegressionTest.php` -> PASS (3 tests, 9 assertions); `vendor/bin/phpunit` -> PASS con codigo 0 y la linea baseline conocida `Error de conexión: SQLSTATE[HY000] [2002]...`.
- Fix backend de unificacion: se agrego `ClientCommercialBalanceService` como fuente unica para `total_invoiced`, `charge_client_payments_total`, `receipt_credit_total` y `accounting_balance`; `Clients.php` ya no contiene `buildAccountingSummarySubquery()`, `buildReceiptCreditUnion()`, `computeAccountingSummaryForClient()` ni `computeReceiptCreditForClient()`, y `ClientStatementService` consume la misma fuente para el resumen final sin filtros de fecha.
- Regla comercial explicita para factura eliminada/anulada: `InvoiceCancellationService` revierte aplicaciones activas de recibos con history + sync delete; si un recibo quedo exclusivamente asociado a esa factura, lo soft-deletea con history + sync delete para evitar saldo a favor artificial. El servicio unico tambien ignora aplicaciones activas stale hacia facturas `deleted_at != NULL` y cargos `charge_client` que quedaron asociados a factura eliminada/cancelada.
- Cobertura API ampliada: `ClientsTest` ahora cubre paridad `listAll`, `listPage`, fallback `hydrateSummaryFields` y `ClientStatementService` para multiples clientes, factura eliminada con aplicacion activa stale y caso tabla/detalle en 0. La regresion `InvoiceReceiptsAndPaymentsFlowRegressionTest` valida que despues de eliminar factura el detalle y `/clients` devuelvan `accounting_balance=0`.
- Validacion API local de esta iteracion: `php -l src/Services/ClientCommercialBalanceService.php`, `src/Models/Clients.php`, `src/Services/ClientStatementService.php`, `src/Services/InvoiceCancellationService.php` -> PASS. `vendor/bin/phpunit tests/Models/ClientsTest.php` -> PASS (11 tests, 45 assertions); `tests/Services/ClientStatementServiceTest.php` -> PASS (13 tests, 47 assertions); `tests/Services/InvoiceCancellationServiceTest.php` -> PASS (8 tests, 49 assertions, conserva linea diagnostica baseline del test de transaccion cerrada); `tests/Regression/InvoiceReceiptsAndPaymentsFlowRegressionTest.php` -> PASS (3 tests, 11 assertions); `vendor/bin/phpunit` -> PASS con codigo 0 y la linea baseline conocida `Error de conexión: SQLSTATE[HY000] [2002]...`.
- Fix web local para `Preparar factura` desde trabajo: `InvoicesPage` conserva explicitamente el borrador de alta cuando el prefill desde `job_id` ya cargo `form.client_id` e `invoiceItems`, evitando que el efecto de `selected === null` lo resetee a `emptyForm`/`[emptyItem]`. Los items precargados del trabajo ahora quedan con `entity_type='jobs'`, `job_id`, `code` del trabajo, descripcion, `quantity=1` y `unit_price` sugerido; los gastos imputables al cliente se agregan como item asociado al mismo trabajo cuando existe total positivo.
- Guardia QA web actualizada: `sisa.web/scripts/commercial-flow-smoke.js` sube a 14 checks y protege el riesgo de regresion donde el alta desde trabajo abre el editor pero pierde cliente/items por competencia de efectos. Se corre con `npm run check:commercial-flow`. Punto ciego: es smoke estatico de cableado/estado fuente; no reemplaza prueba manual real con navegador, permisos y persistencia API.
- Validacion web local del fix `Preparar factura`: `npm run check:commercial-flow` -> PASS (14 checks); `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes de Vite. El build local actualizo artefactos versionados bajo `sisa.web/dist/`.
- Correccion web local de cierre de guardia: `InvoicesPage` ya no usa `isEditorOpen` para preservar cualquier formulario abierto cuando `selected === null`; la preservacion del alta queda limitada al draft intencional marcado por `preserveCreateDraftRef.current`. El smoke comercial sube a 15 checks, falla explicitamente si reaparece `preserveCreateDraftRef.current || isEditorOpen` y valida que el prefill desde trabajo siga armando `client_id`, `invoice_date`, `invoiceItems`, `entity_type='jobs'`, `code=String(job.id)` y `job_id=String(job.id)`. Validacion local: `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes; `npm run check:commercial-flow` -> PASS (15 checks). Punto ciego: smoke estatico, no reemplaza navegacion real con permisos/API.
- Fix API local para paridad real de saldo cliente: `ClientCommercialBalanceService` agrega diagnostico `diagnoseClientBalance(client_id, company_id)` para listar facturas activas/eliminadas, items `payments/jobs`, pagos `charge_client=1`, recibos, aplicaciones activas/soft-deleted y el calculo `total_invoiced`, `charge_client_payments_total`, `receipt_credit_total`, `accounting_balance` usado por `/clients`.
- Blindaje legacy de balance comercial: el guard de pagos asociados a factura cancelada ahora cubre estados `cancelled`, `canceled`, `void`, `anulada` y `anulado`. Decision documentada: no se filtra `invoice_items.deleted_at` porque un pago `charge_client` referenciado por un item de factura eliminada/cancelada viene del flujo de esa factura y no debe seguir generando deuda; un pago manual sin item de factura sigue contando. Si una importacion futura no preserva esa distincion, debe agregarse metadata/migracion explicita, no inferencia por soft-delete.
- Correccion de recibos con aplicaciones stale: el balance ya no excluye el recibo completo cuando tiene una aplicacion activa hacia una factura eliminada; se ignora solo la aplicacion contaminada en los agregados de aplicado. Si el recibo existia unicamente por una factura eliminada, la limpieza correcta queda en `InvoiceCancellationService` soft-deleteando ese recibo, no en el SELECT de balance.
- Cobertura API agregada/ajustada: `ClientsTest` cubre diagnostico de componentes, paridad `listAll/listPage/hydrateSummaryFields/ClientStatementService`, recibo aplicado a factura activa + eliminada sin desaparecer, estado legacy `canceled` con item `payments`, y guard sobre `invoice_items.deleted_at`. `ClientStatementServiceTest` cubre que el recibo multi-factura no desaparezca y que `canceled` no deje deuda falsa por pago.
- Validacion API local del fix de saldo cliente: `php -l src/Services/ClientCommercialBalanceService.php`, `src/Models/Clients.php`, `src/Services/ClientStatementService.php`, `src/Services/InvoiceCancellationService.php` -> PASS. `vendor/bin/phpunit tests/Models/ClientsTest.php` -> PASS (15 tests, 65 assertions); `tests/Services/ClientStatementServiceTest.php` -> PASS (15 tests, 53 assertions); `tests/Services/InvoiceCancellationServiceTest.php` -> PASS (8 tests, 49 assertions, conserva linea diagnostica baseline de transaccion cerrada); `tests/Regression/InvoiceReceiptsAndPaymentsFlowRegressionTest.php` -> PASS (3 tests, 11 assertions); `vendor/bin/phpunit` -> PASS con codigo 0 y conserva la linea baseline conocida `Error de conexión: SQLSTATE[HY000] [2002]...`.
- Herramientas locales de diagnostico/reparacion agregadas para datos legacy de saldo comercial: `scripts/diagnostics/client-commercial-balance-diagnostic.php --company_id=ID --client_id=ID` imprime fuentes y calculo de `ClientCommercialBalanceService::diagnoseClientBalance()`. `scripts/maintenance/repair-stale-deleted-invoice-applications.php --company_id=ID [--client_id=ID]` corre en dry-run por defecto; solo con `--apply` soft-deletea aplicaciones activas hacia facturas eliminadas/canceladas legacy y soft-deletea recibos que quedaron sin aplicaciones activas y cuya historia solo apuntaba a esas facturas. No toca recibos con aplicaciones a otras facturas activas.
- Cobertura de reparacion legacy: `StaleDeletedInvoiceApplicationRepairServiceTest` cubre dry-run sin mutacion, apply con aplicacion stale + recibo exclusivo, conservacion de recibo aplicado tambien a factura activa y estado legacy `canceled`.
- Validacion API local herramientas legacy: `php -l scripts/diagnostics/client-commercial-balance-diagnostic.php`, `php -l scripts/maintenance/repair-stale-deleted-invoice-applications.php`, `php -l src/Services/StaleDeletedInvoiceApplicationRepairService.php` -> PASS. `vendor/bin/phpunit tests/Services/StaleDeletedInvoiceApplicationRepairServiceTest.php` -> PASS (4 tests, 19 assertions). Revalidado: `tests/Models/ClientsTest.php` -> PASS (15 tests, 65 assertions), `tests/Services/InvoiceCancellationServiceTest.php` -> PASS (8 tests, 49 assertions), `vendor/bin/phpunit` -> PASS con codigo 0 y la linea baseline conocida de conexion MySQL.
- Micro-ajuste de seguridad reparacion legacy: `StaleDeletedInvoiceApplicationRepairService::run(..., apply=true)` ahora envuelve `softDeleteApplications()` y `softDeleteReceipts()` en transaccion; no abre otra si ya hay transaccion activa, hace commit/rollback solo si la inicio y relanza cualquier `Throwable`. Test agregado verifica que si falla el soft-delete de `receipts`, no quedan `invoice_receipt_payments` parcialmente modificados.
- Validacion API local micro-ajuste transaccional: `php -l src/Services/StaleDeletedInvoiceApplicationRepairService.php` -> PASS; `php -l scripts/maintenance/repair-stale-deleted-invoice-applications.php` -> PASS; `vendor/bin/phpunit tests/Services/StaleDeletedInvoiceApplicationRepairServiceTest.php` -> PASS (5 tests, 24 assertions); `vendor/bin/phpunit tests/Models/ClientsTest.php` -> PASS (15 tests, 65 assertions); `vendor/bin/phpunit tests/Services/InvoiceCancellationServiceTest.php` -> PASS (8 tests, 49 assertions, conserva linea diagnostica baseline de transaccion cerrada); `vendor/bin/phpunit` -> PASS con codigo 0 y la linea baseline conocida `Error de conexión: SQLSTATE[HY000] [2002]...`.

### Loop 1.3.2 - deploy QA controlado y cierre vivo

Estado: re-test vivo API completado en `sistema-test`; no cerrado al 100% porque falta validacion visual interactiva web/app desde navegador/app real.

- Fecha preparacion: 2026-06-29.
- Ambiente autorizado revisado: `https://sistema-test.depros.com.ar`; SSH `hostinger-codex`, directorio `domains/depros.com.ar/public_html/sistema_test`.
- Modo seguridad aplicado: solo comandos de diagnostico/lectura en servidor; no se ejecuto deploy, no se modificaron archivos remotos, no se leyeron secretos, no se tocaron permisos, no se ejecutaron SQL mutantes.
- Commit local raiz candidato: `30fb5e1 feat: implement idempotent invoice cancellation and validate full QA flow in live environment` en `main`.
- Commit API local candidato: `da2e8b6 feat: implement invoice cancellation service and update controller logic for offline-first support` en `main`.
- Commit web local candidato: `7d729de feat: implement InvoicesPage for managing invoice records, items, and receipt applications` en `main`.
- Confirmacion local del contenido candidato: `InvoiceCancellationService` contiene reintento idempotente `already_cancelled`, `commit()`/`rollBack()` protegidos con `inTransaction()`; `InvoicesController` responde `Invoice already cancelled` con `already_cancelled=true`; `InvoicesPage` contiene `deletingInvoice` y boton `Eliminando...` para bloquear doble submit.
- Estado remoto API por lectura: `sistema_test` ya esta en commit `da2e8b6` sobre `main`, con `git status --short` mostrando solo `?? uploads/`; no se desplego nada porque el fix ya estaba presente en el backend remoto.
- Confirmacion remota del contenido API: `grep` en `src/Services/InvoiceCancellationService.php`, `src/Controllers/InvoicesController.php` y tests encontro `already_cancelled`, `Transaction was already closed before final commit` e `Invoice already cancelled`.
- Validacion remota segura: `php -l src/Services/InvoiceCancellationService.php`, `php -l src/Controllers/InvoicesController.php`, `php -l tests/Services/InvoiceCancellationServiceTest.php`, `php -l tests/Controllers/InvoicesOfflineFirstSmokeTest.php` -> PASS en `sistema_test`.
- Ubicacion web desplegada: no confirmada. En el directorio remoto autorizado no aparece arbol fuente/bundle web de `sisa.web`; solo se verifico backend/API. Pendiente indicar ruta o mecanismo de deploy web si se requiere validar `deletingInvoice` en ambiente.
- Validacion local API: `php -l` archivos tocados -> PASS; `vendor/bin/phpunit tests/Services/InvoiceCancellationServiceTest.php` -> PASS (7 tests, 42 assertions); `vendor/bin/phpunit tests/Controllers/InvoicesOfflineFirstSmokeTest.php` -> PASS (7 tests, 38 assertions).
- Validacion local web: `npm run check:commercial-flow` -> PASS (11 checks); `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes de Vite. Los artefactos `dist/` generados por build fueron limpiados.
- Validacion local UI mobile: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188 selectedJobRecord`; `npm run check:cache` -> PASS.
- Bloqueo para re-test real: crear/emitir/anular factura QA, reintentar `/void`, crear worklog/evidencia/finalizar y generar PDFs son mutaciones sobre ambiente remoto; las reglas de seguridad vigentes exigen aprobacion explicita de comandos/acciones antes de ejecutarlas.
- Autorizacion recibida para mutaciones QA por API en `sistema-test` usando solo usuarios `qa_loop_1_3_*` y datos `QA_LOOP_1_3_20260629`.
- Intento de re-test API: se intento generar JWT efimero en memoria para `qa_loop_1_3_admin_20260629` sin imprimir token ni leer secretos, y crear factura QA por `POST /invoices`. Resultado: HTTP 403 `El token no coincide con el almacenado en la base de datos`. No se creo factura nueva ni se avanzo a emision/anulacion.
- Bloqueo actual de autenticacion: `CheckUserBlockedMiddleware` exige token de sesion real persistido en `auth_sessions`; no se leyeron tokens de base de datos ni se actualizaron sesiones manualmente porque eso violaria las reglas de secretos/DB. Para continuar se necesita login real por API con credenciales QA provistas de forma segura o un mecanismo de sesion QA que no exponga tokens/passwords en la conversacion.
- Autorizacion ampliada recibida para usar/actualizar tokens en test. Se creo sesion temporal QA en `auth_sessions` para `qa_loop_1_3_admin_20260629`, `qa_loop_1_3_tecnico_20260629` y `qa_loop_1_3_readonly_20260629`; los tokens no se imprimieron ni se documentaron.
- Re-test vivo `/void` completado con factura QA nueva id 94 vinculada a trabajo id 305, cliente id 99, empresa id 66.
- Caso A primer `/void`: factura 94 creada por API (HTTP 200), emitida por `POST /invoices/94/issue` (HTTP 200), trabajo 305 paso de status id 15 a facturado id 16 con `job_status_updates={requested:1,updated:1,skipped:0,missing:0,status_found:true,status_id:16}`. Primer `POST /invoices/94/void` devolvio HTTP 200, `message=Invoice voided successfully`, `invoice.status=cancelled`, `jobs_released_count=1`; estado DB verificado: factura 94 `cancelled`, version 3, total `0.00`, items activos 0; trabajo 305 volvio a status id 15, version 5. No aparecio 409.
- Caso B retry idempotente: segundo `POST /invoices/94/void` devolvio HTTP 200, `message=Invoice already cancelled`, `already_cancelled=true`. Estado verificado: factura 94 version 3 sin cambios, trabajo 305 version 5 sin cambios, `history_delta=0`; `sync_delta` no se pudo medir porque la tabla/consulta de `sync_events` no expuso contador compatible en este ambiente, pero no hubo nueva mutacion observable en factura/trabajo/history.
- PDF factura real: `POST /invoices/94/report/pdf` devolvio HTTP 200, `file_id=584`, `download_url` presente; descarga `GET /files/584` devolvio `application/pdf`, 24368 bytes, encabezado `%PDF`, sin ocurrencias binarias/textuales de `status_id`, `status_attribute`, `metadata_json`, `source_device_id`, `GPS`, `Tracking`, `debug`, `Debe`, `Haber`, `técnicos internos` ni `participantes internos`.
- Resumen cliente real: `GET /accounting/client-statement?company_id=66&client_id=99` devolvio HTTP 200 con resumen comercial `total_invoiced=100`, `total_paid=10000`, `pending_balance=0`, `customer_credit=9900`, `pending_receipts_total=0`, `rejected_receipts_total=0`.
- PDF resumen cliente real: `GET /accounting/client-statement/report?company_id=66&client_id=99&format=pdf` devolvio HTTP 200, `file_id=585`, `download_url` presente; descarga `GET /files/585` devolvio `application/pdf`, 21598 bytes, encabezado `%PDF`, sin ocurrencias de los terminos prohibidos buscados.
- Permisos API reales con sesiones QA: admin opero factura/PDF/resumen; tecnico `GET /jobs/305` -> 200, `GET /invoices` -> 403, `GET /accounting/client-statement` -> 403; solo lectura `GET /invoices` -> 200 y `POST /invoices` -> 403.
- Credenciales QA manuales: a pedido del usuario se resetearon las passwords de `qa_loop_1_3_admin_20260629`, `qa_loop_1_3_tecnico_20260629` y `qa_loop_1_3_readonly_20260629` en `sistema-test` para permitir validacion visual manual. Se verifico `password_verify()` para los tres usuarios; no se documenta el valor en este archivo.
- Bloqueo visual: no hay herramienta de navegador disponible en esta sesion para comprobar visualmente boton `Eliminando...`, ausencia de botones falsos, app movil o PDF renderizado. Se puede validar por API/codigo, o requiere ejecucion manual/asistida por usuario.
- Pendiente para cerrar 1.3.2 al 100%: validacion visual interactiva en `http://localhost:5173/` o web test real de que el boton queda `Eliminando...` y no dispara doble request, validacion visual app movil tecnica con worklog/evidencia/finalizacion y revision humana del render PDF. Por API/codigo, los permisos y PDFs quedaron validados.

### Loop 1.3.1 - respuesta idempotente de anulacion

Estado: fix implementado localmente y validado con pruebas focalizadas; pendiente despliegue autorizado a `sistema-test` y verificacion viva post-deploy.

- Reproduccion viva previa al fix: `POST /invoices/93/void` en `sistema-test.depros.com.ar` devolvio HTTP 409 con body `{"error":"There is no active transaction"}` aunque la operacion habia aplicado efectos: factura 93 quedo `cancelled`, total 0, items 0, y el trabajo 305 volvio a estado finalizado id 15.
- Causa diagnosticada: `InvoiceCancellationService` aplicaba mutaciones y algun subflujo posterior podia cerrar la transaccion antes del `commit()` final; el `commit()` final lanzaba `There is no active transaction` y el controlador respondia error aunque el estado final ya estaba aplicado.
- API: `InvoiceCancellationService` ahora trata `cancelInvoice()` sobre factura ya `cancelled` como reintento seguro antes de abrir transaccion y devuelve `already_cancelled=true`, sin volver a mutar items/jobs/pagos.
- API: el `commit()` final de `InvoiceCancellationService` ahora se ejecuta solo si `PDO::inTransaction()` sigue activo; si la transaccion ya fue cerrada por un subflujo posterior a las mutaciones, no convierte la anulacion aplicada en error y registra `error_log` diagnostico. El `rollBack()` del catch tambien queda protegido con `inTransaction()`.
- API: `InvoicesController::changeInvoiceStatus()` responde 200 con `Invoice already cancelled` y `already_cancelled=true` cuando `/void` recibe una factura que ya esta `cancelled`.
- Tests API agregados: `InvoiceCancellationServiceTest::testCancelInvoiceIsIdempotentWhenAlreadyCancelled`, `InvoiceCancellationServiceTest::testCancelInvoiceDoesNotFailWhenPostMutationHookClosesTransaction` y `InvoicesOfflineFirstSmokeTest::testVoidInvoiceEndpointIsIdempotentWhenAlreadyCancelled`.
- Web: `InvoicesPage` no usa `POST /invoices/{id}/void`; el boton visible actual usa `DELETE /invoices/{id}` con permiso `deleteInvoice`. Se agrego guardia `deletingInvoice` para bloquear doble click mientras la request esta en curso y mostrar `Eliminando...`.
- Permisos web: el boton de eliminacion/anulacion sigue renderizando solo con `canDeleteInvoice`; no se agregaron botones nuevos ni endpoints duplicados.
- Validacion API php-l: `php -l src/Services/InvoiceCancellationService.php`, `php -l src/Controllers/InvoicesController.php`, `php -l tests/Services/InvoiceCancellationServiceTest.php`, `php -l tests/Controllers/InvoicesOfflineFirstSmokeTest.php` -> PASS.
- Validacion API PHPUnit: `vendor/bin/phpunit tests/Services/InvoiceCancellationServiceTest.php` -> PASS (7 tests, 42 assertions; emite el `error_log` diagnostico esperado en el caso simulado de transaccion cerrada); `vendor/bin/phpunit tests/Controllers/InvoicesOfflineFirstSmokeTest.php` -> PASS (7 tests, 38 assertions); `vendor/bin/phpunit tests/Services/AccountingFunctionalFlowTest.php` -> PASS (7 tests, 75 assertions); `vendor/bin/phpunit tests/Services/InvoiceLineNormalizerTest.php` -> PASS (8 tests, 24 assertions).
- Validacion web: `npm run check:commercial-flow` -> PASS (11 checks); `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes de Vite.
- Validacion UI mobile: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188 selectedJobRecord`; `npm run check:cache` -> PASS.
- Pendiente para cerrar 1.3.1: desplegar con autorizacion explicita a `sistema-test`, repetir `POST /invoices/{id}/void` sobre factura emitida real y verificar que el primer intento devuelve 200 si aplica correctamente; repetir request y verificar idempotencia 200; completar QA visual web/app/PDF pendiente del loop 1.3.

- Ambiente detectado: `sisa.ui/config/Index.ts` apunta a `https://sistema-test.depros.com.ar`; el host responde y `/profile` devuelve 401 sin Bearer, por lo que el ambiente test esta accesible y protegido.
- Ambiente SSH: se accedio en modo solo lectura a `hostinger-codex`, directorio `domains/depros.com.ar/public_html/sistema_test`, sin leer secretos ni modificar archivos/datos. `git status --short` muestra solo `?? uploads/` remoto sin trackear; no se toco.
- Despliegue del fix 1.2.1: verificado por lectura de codigo en `sistema_test`; `src/Controllers/InvoicesController.php` contiene `job_status_updates`, `collectInvoiceJobIds` y `createJobStatusSyncEvents`, y `tests/Controllers/InvoicesOfflineFirstSmokeTest.php` contiene `testIssueInvoiceEndpointMarksLinkedJobAsInvoiced`.
- Validacion remota segura: `php -l src/Controllers/InvoicesController.php`, `php -l tests/Controllers/InvoicesOfflineFirstSmokeTest.php` y `php -l tests/Services/InvoiceCancellationServiceTest.php` -> PASS en `sistema_test`.
- Ambiente local: no existe `.env` en el workspace para `sisa.api`; la API local requiere MySQL via `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`. En la maquina no esta disponible el comando `mysql`, por lo que no se pudo preparar una base local real desde `install.php`.
- Credenciales QA: creados y activados usuarios `qa_loop_1_3_admin_20260629` (id 4), `qa_loop_1_3_tecnico_20260629` (id 5) y `qa_loop_1_3_readonly_20260629` (id 6). No se documentan passwords en este archivo.
- Setup QA autorizado en DB test: empresa operativa `QA_LOOP_1_3_20260629 Empresa` id 66; membresias aprobadas admin owner, tecnico member y solo lectura member; empresa cliente id 67, cliente id 99, carpeta id 92, trabajo principal id 303. Se creo ademas cliente B id 100 para validar rechazo de mezcla de cliente incorrecto.
- Setup por API: estados id 14 `QA Asignado`, id 15 `QA Finalizado`, id 16 `QA Facturado`; prioridad id 9; categoria de cobro id 16; caja id 15.
- App movil tecnico via API real: usuario tecnico pudo leer `GET /jobs/303`, crear worklog id 176, subir archivo evidencia id 581, asociarlo como `file_attachment` id 53 al worklog y finalizar el trabajo con `PUT /jobs/303`; el trabajo paso a estado finalizado id 15. El tecnico no pudo listar facturas ni ver resumen cliente: ambos 403.
- Web/admin via API real: admin creo factura id 89 desde trabajo 303 con `client_id=99`, item vinculado a `job_id=303` y descripcion comercial. La API persistio el item vinculado y no permitio mezclar otro cliente: intento con `client_id=100` y `job_id=303` devolvio 400 `Job code does not belong to the selected client.`
- Emision por PUT viva: `PUT /invoices/89` con `status=issued` devolvio 200; factura quedo `issued`; `job_status_updates={requested:1, updated:1, skipped:0, missing:0, status_found:true, status_id:16}`; trabajo 303 quedo visualizable por API en estado facturado id 16; resumen cliente mostro factura emitida por 10000 y saldo pendiente 10000.
- Emision dedicada viva: se creo trabajo id 304 y factura draft id 90; `POST /invoices/90/issue` devolvio 200, `invoice.status=issued`, `job_status_updates.updated=1`, `status_id=16`; trabajo 304 quedo facturado id 16.
- Cobro parcial vivo: `POST /invoices/89/receipts` creo recibo id 21 por 4000 aplicado a factura; factura quedo `payment_status=partial`; resumen cliente mostro `total_invoiced=10000`, `total_paid=4000`, `pending_balance=6000`.
- Cobro total vivo: segundo `POST /invoices/89/receipts` creo recibo id 22 por 6000; factura quedo `status=paid` y `payment_status=paid`; resumen cliente mostro `total_invoiced=10000`, `total_paid=10000`, `pending_balance=0`; movimientos: `Factura emitida:10000`, `Recibo confirmado:-4000`, `Recibo confirmado:-6000`.
- PDFs reales generados: factura id 89 genero `file_id=582`, `report_id=81`; resumen cliente genero `file_id=583`, `report_id=82`. Pendiente descargar/revisar visualmente: PowerShell no pudo leer el stream binario de `/files/{id}` en esta sesion, aunque la generacion devolvio metadata OK.
- Anulacion viva: `POST /invoices/89/void` dejo factura 89 en `cancelled`, total 0, items 0; trabajo 303 volvio a estado finalizado id 15; resumen cliente ya no conserva deuda de factura 89 y muestra el excedente de recibos como credito contra la unica factura activa restante. Bug observado: el primer intento de `void` devolvio 409 al cliente pero aplico efectos; reintento idempotente devolvio 200. Requiere diagnostico de respuesta/transaccion antes de salida.
- No liberar si otra factura activa referencia el trabajo: se creo factura activa id 92 sobre trabajo 304; al anular factura 90, el trabajo 304 permanecio facturado id 16 porque factura 92 seguia activa. Tambien aqui el primer `void` devolvio 409 aunque aplico la anulacion; estado final verificado: factura 90 `cancelled`, factura 92 `issued`, trabajo 304 id 16.
- Permisos reales via API: tecnico puede ver trabajo y operar avance/evidencia/finalizacion, pero no facturas ni resumen contable profundo; admin puede operar factura/cobros/PDF; solo lectura puede ver trabajos, facturas y resumen, pero `POST /invoices` y `POST /receipts` devuelven 403. Pendiente validacion visual de que los botones no aparecen en web/app, no solo que API rechaza.
- Correcciones aplicadas: ninguna en codigo. Se aplicaron unicamente datos QA autorizados en ambiente test y documentacion local.
- Validaciones finales locales: `sisa.web` `npm run check:commercial-flow` -> PASS (11 checks), `npm run lint` -> PASS, `npm run build` -> PASS con warning baseline de chunks grandes. `sisa.ui` `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188 selectedJobRecord`, `npm run check:cache` -> PASS.
- Criterio de salida: sigue abierto. Falta demostrar visualmente en app/web reales: pantalla tecnica sin facturar/cobrar, pantalla web preparar factura, estado visual facturado/finalizado, navegacion de movimientos, PDFs visualmente limpios y ausencia de botones falsos sin 403 visible. Tambien queda bug de `void` con primer 409 aunque aplica efectos.

## SISA API/Web - loop comercial 1.2.1 emision dedicada de factura

Estado: implementado localmente con validacion focalizada; QA manual end-to-end con API/web vivos y PDF real pendiente.

- API: se corrigio `InvoicesController::changeInvoiceStatus()`, usado por `/invoices/{id}/issue`, para mantener paridad con `addInvoice`/`updateInvoice`: cuando la factura persistida queda `issued` o `paid`, ahora marca los trabajos vinculados como facturados.
- API: el endpoint dedicado de emision ahora opera en transaccion, recarga la factura persistida con items, mantiene `syncInvoiceEntries()`, calcula trabajos desde la factura almacenada mediante `collectInvoiceJobIds($storedInvoice)` y devuelve `job_status_updates` en la respuesta.
- API: `cancelled` no usa el nuevo marcado de emision; cancelacion/anulacion sigue delegada en `InvoiceCancellationService`, que libera trabajos a completado/finalizado solo cuando no estan referenciados por otra factura activa.
- API: se agregaron seams protegidos minimos en `InvoicesController` para poder testear `issueInvoice()` con SQLite sin depender de sync/MySQL real: creacion de modelo, flujo contable, historial y registro de sync/historial/eventos.
- API smoke funcional: `InvoicesOfflineFirstSmokeTest::testIssueInvoiceEndpointMarksLinkedJobAsInvoiced` llama al controller `issueInvoice()`, verifica respuesta `issued`, `job_status_updates` y cambio real de `jobs.status_id`, `version` y `updated_by`.
- API cancelacion: `InvoiceCancellationServiceTest` queda reforzado con fixture SQLite y no-op de sync para cubrir anulacion/liberacion de trabajos y caso de trabajo referenciado por otra factura activa.
- Web: `InvoicesPage` fue revisada y actualmente emite desde UI mediante `updateInvoice()`/`PUT /invoices/{id}` con `status`, no por `/invoices/{id}/issue`; no se modifico web porque el camino existente ya estaba cubierto y el bug estaba en el endpoint dedicado.
- Validacion API php-l: `php -l src/Controllers/InvoicesController.php`, `php -l tests/Controllers/InvoicesOfflineFirstSmokeTest.php`, `php -l tests/Services/AccountingFunctionalFlowTest.php`, `php -l tests/Services/InvoiceLineNormalizerTest.php`, `php -l tests/Services/InvoiceCancellationServiceTest.php` -> PASS.
- Validacion API PHPUnit: `vendor/bin/phpunit tests/Controllers/InvoicesOfflineFirstSmokeTest.php` -> PASS (6 tests, 34 assertions); `vendor/bin/phpunit tests/Services/InvoiceCancellationServiceTest.php` -> PASS (5 tests, 32 assertions). Validaciones focalizadas previas del loop: `tests/Services/AccountingFunctionalFlowTest.php` -> PASS (7 tests, 75 assertions); `tests/Services/InvoiceLineNormalizerTest.php` -> PASS (8 tests, 24 assertions).
- Pendiente real: QA manual con API/web vivos para confirmar emision por endpoint dedicado `/invoices/{id}/issue`, emision desde web por `PUT /invoices/{id}`, estado visual del trabajo facturado, anulacion/liberacion visual y PDF real descargado sin terminos internos.

## SISA API/Web/UI - loop comercial 1.2 consistencia real

Estado: implementado localmente con validacion focalizada; QA manual end-to-end con PDF real pendiente.

- API: `InvoicesController` ahora marca trabajos como facturados solo cuando la factura persistida queda en estado publicable (`issued` o `paid`). Una factura `draft` ya no intenta pasar trabajos a facturado aunque venga `job_ids`.
- API: el marcado comercial del trabajo ahora toma los trabajos desde la factura persistida, incluyendo `invoice_items.job_id` y `invoice_items` de tipo `jobs`, no solo desde `job_ids` del payload. Esto cubre el flujo web actual `Preparar factura` que crea items con `job_id`.
- API: `updateInvoice` tambien devuelve `job_status_updates` y aplica marcado de trabajo cuando una factura existente pasa a estado emitido/pagado. Cancelacion/anulacion sigue delegada en `InvoiceCancellationService`, que libera jobs a estado completado si no estan referenciados por otra factura activa.
- API: `InvoiceLineNormalizer` ya validaba y queda cubierto por test que `invoice_items.job_id` corresponde a un trabajo activo de la misma empresa y mismo cliente de la factura. Un trabajo de otro cliente se rechaza con `Job code does not belong to the selected client.`
- API smoke funcional: `AccountingFunctionalFlowTest::testCommercialLoopFromJobInvoicePartialAndFinalReceiptStatement` simula cliente, carpeta, trabajo, factura emitida con item vinculado a `job_id`, cobro parcial, saldo parcial en statement, segundo cobro, saldo cero/cobrada y movimientos con `invoice_id`/`receipt_id` navegables para web.
- PDF/statement: se reforzo la expectativa comercial del resumen PDF: usa `Total facturado`, `Total pagado`, `Saldo pendiente`, `Confirmado` y no `Debe`/`Haber`. Los tests existentes de PDF de factura siguen verificando que no se expongan `status_id`, `status_attribute`, `metadata_json`, `source_device_id`, `Tracking/GPS` ni tecnico interno.
- Web: `scripts/commercial-flow-smoke.js` sube a 11 checks y ahora tambien protege que acciones de recibo dependan de `addReceipt`/`updateReceipt` y que el resumen cliente dependa de `viewClientStatement` o fallbacks vigentes `viewClientAccounting`/`viewAccountingSummary`.
- Permisos involucrados: `addInvoice`, `updateInvoice`, `listInvoices`, `addReceipt`, `updateReceipt`, `listReceipts`, `listJobs`, `viewClientStatement`, `viewClientAccounting`, `viewAccountingSummary`, `exportInvoicePdf`, `downloadInvoicePdf`.
- Rutas tocadas: `sisa.api/src/Controllers/InvoicesController.php`, `sisa.api/tests/Controllers/InvoicesOfflineFirstSmokeTest.php`, `sisa.api/tests/Services/InvoiceLineNormalizerTest.php`, `sisa.api/tests/Services/AccountingFunctionalFlowTest.php`, `sisa.web/scripts/commercial-flow-smoke.js`.
- Validacion API php-l: `php -l src/Controllers/InvoicesController.php`, `php -l tests/Controllers/InvoicesOfflineFirstSmokeTest.php`, `php -l tests/Services/InvoiceLineNormalizerTest.php`, `php -l tests/Services/AccountingFunctionalFlowTest.php` -> PASS.
- Validacion API PHPUnit: `vendor/bin/phpunit tests/Controllers/InvoicesOfflineFirstSmokeTest.php` -> PASS (5 tests, 25 assertions); `tests/Services/InvoiceLineNormalizerTest.php` -> PASS (8 tests, 24 assertions); `tests/Services/AccountingFunctionalFlowTest.php` -> PASS (7 tests, 75 assertions); `tests/Services/ReceiptApplicationServiceTest.php` -> PASS (19 tests, 72 assertions); `tests/Services/ClientStatementServiceTest.php` -> PASS (11 tests, 43 assertions); `tests/Services/ClientStatementPdfGeneratorTest.php` -> PASS (2 tests, 21 assertions); `tests/Controllers/InvoicesControllerPdfRegressionTest.php` -> PASS (4 tests, 21 assertions) con notice baseline de PHPUnit result cache `Permission denied` antes de ejecutar.
- Validacion Web: `npm run check:commercial-flow` -> PASS (11 checks); `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes de Vite. No quedaron cambios en `dist/` ni `tsconfig.tsbuildinfo` despues del build.
- Validacion UI mobile: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188` (`selectedJobRecord` sin uso); `npm run check:cache` -> PASS. No se tocaron archivos de `sisa.ui` en esta etapa.
- Deuda real pendiente: QA manual end-to-end con API/web vivos para confirmar creacion real desde formulario web, emision, PDF generado/descargado con numero de trabajo/carpeta comercial si aplica, anulacion de factura y liberacion visual de estado de trabajo, y usuario sin permisos verificando ausencia de botones sin 403 visual. El soporte de carpeta en PDF existe via `enrichInvoiceItemsForPdf` cuando el job tiene `folder_id`; no se agrego migracion ni campo nuevo.

## SISA Web/UI - loop comercial navegable cliente-trabajo-factura-cobro

Estado: implementado localmente con validacion focalizada; QA manual end-to-end pendiente.

- Web: `ClientsPage` acepta `client_id` por query y abre la ficha del cliente, manteniendo el cliente como centro operativo. Los movimientos del resumen comercial ahora tienen accion `Abrir movimiento` hacia factura, recibo o pago original si el usuario tiene `listInvoices`, `listReceipts` o `listPayments`.
- Web: `JobsPage` deja de abrir alta de trabajo solo por recibir `client_id`; `Cliente -> Trabajos` ahora filtra/contextualiza sin interrumpir. El alta contextual queda explicita con `new=1`.
- Web: `JobsPage` acepta `job_id` por query y abre el detalle del trabajo. El detalle carga facturas visibles con `listInvoices`, muestra `Ver cliente`, `Ver factura #...` cuando ya existe relacion, y conserva `Preparar factura` solo con `addInvoice`.
- Web: `InvoicesPage` acepta `invoice_id` por query, abre la factura existente, muestra trabajos vinculados y permite volver con `Ver trabajo #...` si existe `listJobs`. La barra de cobro y navegacion a recibos aplicados permanecen en la factura.
- UI mobile: `app/jobs/[id].tsx` prioriza ejecucion tecnica. Se agrego panel `Trabajo en campo` con `Agregar avance`, `Adjuntar evidencia` y `Finalizar`; se removio la accion directa de crear/abrir facturas desde el detalle movil para no exponer contabilidad profunda al tecnico.
- QA focalizado nuevo: `sisa.web/scripts/commercial-flow-smoke.js` y script `npm run check:commercial-flow`. Protege cruces cliente -> trabajos, resumen -> movimiento, trabajo -> cliente/factura, factura -> trabajo/recibos, recibo por deep-link y ausencia de facturacion directa en detalle tecnico movil.
- Permisos involucrados: `listClients`, `listJobs`, `addJob`, `listInvoices`, `addInvoice`, `listReceipts`, `listPayments`, `viewClientStatement`, `exportClientJobsPdf`, `addWorkLog`, `uploadFile`, `updateJob`.
- Rutas tocadas: `sisa.web/src/pages/ClientsPage.tsx`, `sisa.web/src/pages/JobsPage.tsx`, `sisa.web/src/pages/InvoicesPage.tsx`, `sisa.web/package.json`, `sisa.web/scripts/commercial-flow-smoke.js`, `sisa.ui/app/jobs/[id].tsx`.
- Validacion Web: `npm run check:commercial-flow` -> PASS (9 checks); `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes de Vite.
- Validacion UI mobile: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188` (`selectedJobRecord` sin uso); `npm run check:cache` -> PASS.
- No se modifico `sisa.api`; no se ejecutaron `php -l`/PHPUnit en este hito porque se reutilizaron endpoints existentes y el cambio fue de navegacion/UX/QA frontend.
- Pendiente real: QA manual end-to-end con datos vivos para Caso A cliente -> carpeta -> trabajo -> worklog -> cierre, Caso B factura desde trabajo y PDF sin terminos internos, Caso C recibo aplicado y saldo correcto, Caso D usuario sin permisos no ve acciones, Caso E resumen abre movimiento original. La automatizacion agregada cubre el cableado de UI, no sustituye E2E con backend y PDF real.

## SISA UI - loop permisos listos antes de menu

Estado: implementado localmente con validacion focalizada; QA manual pendiente.

- UI mobile: `PermissionsContext` expone `permissionsReady`, `permissionsLoading`, `permissionsScopeCompanyId`, `permissionsSource`, `permissionsForNavigation` y `permissionsForActions`. `permissionsReady` solo queda true con empresa activa, hidratacion completa, carga finalizada, scope igual a `activeCompanyId` y fuente confiable `snapshot`/`sqlite`/`server`/`cache`.
- UI mobile: al cambiar `userId`/empresa activa, permisos, admin flag, scope y fuente se limpian inmediatamente; el menu no puede reutilizar permisos de la empresa anterior.
- UI mobile: cache confiable de permisos queda separado por `permissions-snapshot:${userId}:${companyId}` y SQLite por `userId/companyId`. Snapshot solo se acepta como fuente confiable si tiene `fetchedAt`; SQLite solo si existe `lastUpdatedAt` para ese scope, permitiendo usuarios con cero permisos reales sin confundirlos con cache ausente.
- UI mobile: se separan permisos de navegacion y permisos de acciones. Navegacion puede usar cache confiable de empresa; acciones siguen usando `can()` con filtrado sensible cuando falta validacion online reciente.
- UI mobile: `src/permissions/permissions.ts` agrega `isPermissionPending()` y `shouldRenderPermissionFilteredUi()`; `can()` conserva fallo seguro si el estado no esta listo.
- UI mobile: `BottomNavigationBar` filtra con `canNavigate()` solo cuando `permissionsReady=true`. Mientras espera muestra estado estable minimo (`Inicio`, `Empresas`, `Cargando permisos...`) si llegara a montarse; el gate global normalmente oculta la barra hasta permisos listos.
- UI mobile: `app/_layout.tsx` no muestra bottom nav, no marca shell usable, no evalua `routeAccess` ni muestra `AccessDeniedScreen` mientras hay empresa activa e `isReady=true` pero `permissionsReady=false`. Rutas login/root/onboarding quedan fuera del bloqueo operativo.
- UI mobile: `app/Home.tsx` muestra espera `Cargando permisos...` antes de calcular menu, metricas, dashboard, jobs, invoices, appointments o statuses. El dashboard filtrado se renderiza solo con permisos finales listos.
- UI mobile: Bootstrap optimista ahora exige `permissionsReady` y `permissionsScopeCompanyId === selectedCompanyId` para considerar completo el snapshot local; `isReady` ya no implica shell operativo si permisos no estan listos.
- Trazas agregadas/ampliadas: `permissions.snapshot.loaded`, `permissions.sqlite.loaded`, `permissions.server.fetch.start`, `permissions.server.fetch.finish`, `permissions.ready`, `bottomNavigation.waitingPermissions` y `bottomNavigation.ready`.
- QA manual pendiente A - cold start con cache: abrir app y verificar que no aparezca menu parcial; debe verse menu final inmediato desde cache o loading breve.
- QA manual pendiente B - cold start sin cache: login debe mostrar `Cargando permisos...` y luego menu correcto; nunca menu parcial.
- QA manual pendiente C - cambio empresa A -> B: menu se oculta o muestra loading y luego aparece menu de B; nunca se ven permisos de A en B.
- QA manual pendiente D - usuario con permisos limitados: menu final muestra solo lo permitido sin flicker de botones.
- QA manual pendiente E - offline: con cache validado, menu segun cache; sin cache, mensaje/loading claro y no menu roto.
- Validacion UI mobile: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188` (`selectedJobRecord` sin uso).
- Validacion UI mobile: `npm run check:cache` -> PASS.
- Validacion UI mobile: `npm run check:sync-smoke` -> PASS.
- Validacion typecheck UI mobile: `npx tsc --noEmit --pretty false` -> PASS. Se corrigio la deuda TypeScript baseline completa detectada en finalized jobs, memberships, invoices, prioridades, jobs/worklogs, receipts, tracking, CircleImagePicker, BootstrapContext, InvoicesContext y sync/cache hooks sin agregar `any` masivo.

## SISA API/Web/UI - resumen comercial de cuenta cliente

Estado: implementado localmente con validacion focalizada; QA manual pendiente.

- Politica de visibilidad cliente: el resumen de cuenta visible para cliente/usuario practico usa lenguaje comercial y muestra facturas emitidas, recibos/pagos aplicados, saldo pendiente, saldo a favor, recibos pendientes/rechazados, fecha, comprobante, descripcion comercial, importe y saldo.
- Campos publicos del statement: `summary.total_invoiced`, `summary.total_paid`, `summary.pending_balance`, `summary.customer_credit`, `summary.pending_receipts_total`, `summary.rejected_receipts_total` y `movements[]` con `date`, `type_label`, `document_label`, `description`, `amount`, `balance_after` y `commercial_status`.
- Campos internos/no publicos en resumen cliente: `debit`, `credit`, `Debe`, `Haber`, asientos, partidas, cuentas contables internas, metadata tecnica, sync/debug/source_device_id, tracking/GPS, tecnicos/participantes internos y estado/tipo interno de trabajo. `ClientStatementService` conserva calculo interno pero mapea la salida publica a `summary`/`movements` comerciales.
- API: `ClientStatementPdfGenerator` ahora genera “Resumen de cuenta del cliente” con empresa, cliente, CUIT si existe, fecha de emision, resumen superior y tabla Fecha/Comprobante/Detalle/Importe/Saldo. El PDF ya no imprime Debe/Haber ni columnas contables tecnicas.
- API: `AccountingController::clientStatementReport` actualiza titulos, filename y descripcion del reporte a resumen comercial de cliente.
- API: el generador legacy `JobReportsController` mantiene variantes existentes, pero `client_account_statement` y `accounting_general` ahora exigen `viewAccountingSummary` ademas de `exportClientJobsPdf` si se fuerzan por API.
- Web: `ClientsPage` consume `summary`/`movements`, cambia “Saldo contable/real” por “Saldo pendiente”, muestra saldo a favor, recibos pendientes y ultimo movimiento, y elimina Debe/Haber del timeline de cliente.
- Web: el modal del cliente ya no muestra link generico “Reportes”; conserva “Descargar resumen del cliente” y accesos filtrados a trabajos/facturas/recibos/pagos/carpetas segun permisos. El reporte contable general queda fuera del cliente y debe operarse desde Reportes/contabilidad general de empresa.
- UI mobile: `app/clients/accounting.tsx`, `app/clients/index.tsx` y `app/clients/unpaidInvoices.tsx` cambian textos visibles a resumen/saldo pendiente y consumen `summary`/`movements` con fallback legacy. La descarga del PDF de resumen cliente solo se muestra si hay permiso contable compatible con el backend.
- UI mobile blindaje urgente: `app/clients/accounting.tsx` normaliza defensivamente cada movimiento antes de renderizarlo. Si el API/cache manda payload legacy con etiquetas internas equivalentes a Debe/Haber o campos `debit`/`credit`, la UI muestra `Factura emitida`, `Recibo confirmado`, `Recibo pendiente`, `Recibo rechazado`, `Recibo con saldo a favor` o `Cargo al cliente`; nunca renderiza la etiqueta legacy literal.
- UI mobile copy publico: la pantalla de resumen cliente usa `Resumen de cuenta`, `Movimientos`, `Descargar resumen`, `Saldo pendiente`, `Saldo a favor` y `Todavía no descuenta del saldo pendiente`. Se reemplazaron restos visibles de `Estado de cuenta`, `Contabilidad ·`, `No reduce deuda` y textos equivalentes en cliente/resumen.
- UI mobile: `ClientReportModal` oculta `accounting_general` y `landscape_summary` del modal de reportes por cliente. Estado del resumen horizontal: variante legacy/dudosa de jobs, no conectada al statement comercial actual; se esconde hasta redisenarla o migrarla con datos/permiso claros.
- Permisos: `viewClientStatement` queda creado como permiso especifico para ver/descargar el resumen comercial de cuenta cliente y se agrego al catalogo sembrado/catologos UI. Los endpoints `/accounting/client-statement` y `/accounting/client-statement/report` aceptan `viewClientStatement`, alias tolerado `viewClientAccounting` y fallback temporal `viewAccountingSummary` para no romper usuarios existentes. `viewAccountingSummary` queda reservado conceptualmente para contabilidad general de empresa y debe retirarse como fallback cuando se migren permisos reales.
- Permisos legacy: `client_account_statement` y `accounting_general` forzados desde `JobReportsController` siguen exigiendo `viewAccountingSummary`, porque pertenecen al generador legacy/dudoso de jobs y no al resumen comercial limpio.
- Web/UI: los checks visibles se renombran a `canViewClientStatement`; Web conserva “Resumen de cuenta”, “Descargar resumen del cliente” y “Movimientos comerciales del cliente”. App mobile cambia titulos/botones/estado de acceso de cliente a “Resumen de cuenta” y ya no muestra “Contabilidad” en las entradas del resumen cliente.
- Validacion API: `php -l` en `src/Routes/api.php`, `src/Models/Permission.php`, `tests/Controllers/ClientStatementControllerTest.php`, `src/Services/ClientStatementService.php`, `src/Services/ClientStatementPdfGenerator.php`, `src/Controllers/AccountingController.php`, `src/Controllers/JobReportsController.php`, `tests/Services/ClientStatementServiceTest.php`, `tests/Services/ClientStatementPdfGeneratorTest.php`, `tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` -> PASS.
- Validacion API: `vendor/bin/phpunit tests/Controllers/ClientStatementControllerTest.php` -> PASS (10 tests, 25 assertions); `tests/Services/ClientStatementServiceTest.php` -> PASS (11 tests, 43 assertions); `tests/Services/ClientStatementPdfGeneratorTest.php` -> PASS (2 tests, 21 assertions); `tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` -> PASS (20 tests, 78 assertions); `tests/Controllers/AccountingControllerTransactionSmokeTest.php` -> PASS (3 tests, 68 assertions).
- Validacion Web: `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes de Vite. Los artefactos generados en `dist/` y `tsconfig.tsbuildinfo` fueron limpiados.
- Validacion UI mobile: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188`; `npm run check:cache` -> PASS; `npm run check:sync-smoke` -> PASS.
- Validacion typecheck UI mobile: `npx tsc --noEmit --pretty false` sigue bloqueado por deuda TypeScript baseline en `clients/finalizedJobs`, `companies/memberships`, `invoices/[id]`, `job-priorities`, `jobs`, `receipts`, `tracking`, `CircleImagePicker`, `BootstrapContext`, `InvoicesContext` y hooks/cache de sync. No aparecen errores propios en archivos tocados de cliente/contabilidad.
- Validacion busqueda UI mobile: `rg "DEBE|HABER|Contabilidad ·|Estado de cuenta|No reduce deuda" sisa.ui --glob "*.{ts,tsx}"` -> sin resultados tras el blindaje, salvo que futuras pantallas internas de contabilidad general vuelvan a introducir esos terminos fuera del resumen cliente. `rg "DEBE|HABER|Contabilidad|contabilidad|Estado de cuenta|No reduce deuda|No reduce el saldo" sisa.ui/app/clients --glob "*.tsx"` -> sin resultados.
- QA manual pendiente: abrir detalle de cliente en web/app con usuario con `viewClientStatement`, verificar que no aparezcan Debe/Haber/asientos/metadata y que el PDF descargado muestre solo resumen comercial. Verificar que usuario sin `viewClientStatement`/`viewClientAccounting`/`viewAccountingSummary` no vea descarga/resumen cliente aunque conserve accesos a facturas/recibos por sus permisos propios. Verificar que `viewAccountingSummary` sigue funcionando como fallback temporal durante migracion.
- QA visual obligatorio mobile: con factura + recibos, el header debe decir `Resumen · Cliente` o `Resumen de cuenta`, la seccion debe decir `Movimientos`, el boton debe decir `Descargar resumen`, y los movimientos deben mostrar Factura/Recibo con importe y saldo comercial. No deben aparecer Debe, Haber, Contabilidad, Estado de cuenta ni No reduce deuda.
- QA visual payload legacy mobile: con fixture/mock que mande `label`/`type_label` legacy y campos `debit`/`credit`, la UI debe mostrar `Factura emitida` / `Recibo...` y nunca la etiqueta legacy literal.

## SISA API/Web/UI - visibilidad fina por permisos

Estado: implementado parcialmente con hardening incremental y cierre fino de cargas auxiliares/accesos cruzados; QA manual pendiente.

- API auditada: `PermissionsMiddleware` resuelve permiso contra token autenticado y `company_id` desde query/header/body o permiso por id; superusuario `id=1` conserva acceso total. `/permissions` exige `company_id`; `/permissions/user/{user_id}` exige `company_id`, permite consultar permisos propios y restringe permisos de terceros a `listPermissionsByUser`; la respuesta incluye `is_company_admin` para owner/admin aprobado de la empresa activa. La API sigue siendo autoridad final con 403.
- Web auditada: `PermissionsProvider` carga permisos por `selectedCompanyId` confirmado y usuario autenticado; sidebar, mobile tabbar, dashboard y rutas protegidas ya filtran por permisos. Se agregaron helpers `useCan`, `useCanAny`, `useCanAll`, `PermissionGate` y `ActionButtonGuard` para nuevas vistas.
- Web hardening: clientes, empleados, facturas y trabajos ahora ocultan acciones principales de crear/editar/eliminar, PDF, vincular/quitar recibos, items, worklogs, adjuntos y facturacion segun permisos. Las rutas directas siguen pasando por `ProtectedRoute`, que redirige a onboarding sin empresa activa y muestra `AccessDenied` sin permiso.
- Permisos - cierre fino de cargas auxiliares y accesos cruzados Web: se agrego `runIfCan()` para no disparar endpoints auxiliares sin permiso desde `Promise.allSettled`/cargas de soporte. `ClientsPage` ya no carga trabajos, facturas, recibos, pagos, carpetas, estados, tarifas ni statement contable sin permisos; ademas oculta metricas, paneles y accesos cruzados contables/operativos relacionados.
- Permisos - cierre fino de trabajos Web: `JobsPage` condiciona cargas de clientes, empresas, estados, prioridades, usuarios, empleados, productos/servicios, tarifas, pagos, carpetas, items, worklogs, participantes y adjuntos. Las tabs internas de items, participantes, worklogs, archivos, informe y costos se muestran solo cuando hay permisos suficientes, y el tab activo se corrige si deja de estar permitido.
- Permisos - cierre fino de facturacion Web: `InvoicesPage`, `ReceiptsPage` y `PaymentsPage` condicionan datos de soporte, selectores, columnas, paneles auxiliares, aplicaciones, instrumentos, PDF y adjuntos segun permisos. Recibos no sincroniza aplicaciones a facturas si falta visibilidad de facturas, evitando mutar datos auxiliares no visibles.
- Permisos - cierre fino adjuntos Web: `PanelCargaAdjuntos` acepta `onAgregarArchivos` opcional y no muestra dropzone cuando no hay permiso/handler de carga, manteniendo la vista de adjuntos cuando solo corresponde descarga.
- App auditada: `PermissionsContext` scopea permisos por `AuthContext.activeCompanyId`; `usePermissions` expone `can`, `canAny`, `canAll`; Home y `BottomNavigationBar` filtran modulos; `_layout.tsx` usa `routePermissions` para deep-links y no monta utilidades operativas sin empresa activa. Se agrego `PermissionRequiredState` reutilizable para pantallas que quieran estado local explicito.
- App hardening existente: `routePermissions` cubre clientes, trabajos/worklogs/items, carpetas, productos/servicios, proveedores, permisos, usuarios, citas, plantillas de pago, pagos, recibos, facturas, presupuestos, cajas, cuentas, transferencias, cierres, categorias, tarifas, estados, prioridades, reportes, accounting, analytics, tracking y notificaciones.
- App hardening focalizado: facturas mobile ahora condiciona cargas auxiliares evidentes (`jobs`, prioridades, productos/servicios, tarifas, carpetas y pagos) segun permisos en listado, creacion y detalle/edicion, evitando requests no permitidos aunque la ruta principal este habilitada.
- Matriz de permisos operativos auditada:

| Modulo | Ver | Crear | Editar | Eliminar | Exportar/PDF | Reportes | Permiso requerido |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Clientes | `listClients`/`getClient` | `addClient` | `updateClient` | `deleteClient` | `viewClientStatement`/`viewClientAccounting` equivalente, con fallback temporal `viewAccountingSummary` | `listReports`/`viewClientStatement` | Resumen comercial cliente usa `viewClientStatement`; facturas/recibos/pagos del cliente se ven por sus permisos propios. |
| Trabajos | `listJobs`/`getJob` | `addJob` | `updateJob`/`changeJobStatus` | `deleteJob` | `exportClientJobsPdf` | `exportClientJobsPdf` | Items: `listJobItems`/`addJobItem`/`updateJobItem`/`deleteJobItem`; worklogs: `listWorkLogs`/`addWorkLog`/`updateWorkLog`/`deleteWorkLog`. |
| Facturas | `listInvoices`/`getInvoice` | `addInvoice` | `updateInvoice` | `deleteInvoice` o anulacion via `updateInvoice` | `exportInvoicePdf` o `downloadInvoicePdf` | `listInvoiceHistory`/reportes contables existentes | Vinculos recibos: `attachInvoiceReceipts`/`detachInvoiceReceipts`; items: `addInvoiceItem`/`updateInvoiceItem`/`deleteInvoiceItem`. |
| Recibos | `listReceipts`/`getReceipt` | `addReceipt` | `updateReceipt` | `deleteReceipt` | PDF protegido hoy por `listReceiptInvoices` | `listReceiptHistory` | Instrumentos: `confirmBankTransfer`, `rejectBankTransfer`, `depositCheck`, `clearCheck`, `rejectCheck`. |
| Pagos | `listPayments`/`getPayment` | `addPayment` | `updatePayment` | `deletePayment` | n/a | historial/listados contables existentes | Templates: `listPaymentTemplates`/`addPaymentTemplate`/`updatePaymentTemplate`/`deletePaymentTemplate`. |
| Cajas | `listCashBoxes`/`getCashBox` | `addCashBox` | `updateCashBox` | `deleteCashBox` | n/a | `viewAccountingSummary`, `listClosings`, `listReports` | Movimientos usan `getCashBox`. |
| Carpetas | `listFolders`/`getFolder` | `addFolder` | `updateFolder` | `deleteFolder` | n/a | `listFolderHistory` | Por cliente: `listFoldersByClient`. |
| Productos/Servicios | `listProductsServices`/`getProductService` | `addProductService` | `updateProductService` | `deleteProductService` | n/a | `listProductServiceHistory` | App/web usan `listProductsServices` para mostrar modulo. |
| Proveedores | `listProviders`/`getProvider` | `addProvider` | `updateProvider` | `deleteProvider` | n/a | `listProviderHistory` | Tracking nearby providers reutiliza `listProviders`. |
| Empleados | `listEmployees`/`getEmployee` | `addEmployee` | `updateEmployee` | `deleteEmployee` | n/a | n/a | Acciones web endurecidas en este loop. |
| Reportes | `listReports` | `generatePaymentReport` segun reporte | n/a | n/a | `generatePaymentReport`/permisos PDF especificos | `viewAccountingSummary`/`viewAccountingReport` equivalente | Si falta permiso dedicado real, usar el permiso de endpoint existente y documentar antes de crear uno nuevo. |
| Empresas | busqueda permitida a usuario autenticado | `createCompany`/`addCompany` segun cliente | `updateCompany` | permisos existentes de delete si aplica | n/a | `manageCompanyJoinRequests`/`listCompanyMembers` | Buscar/unirse no requiere permiso operativo; solicitudes solo admin/owner o permiso existente. |
| Permisos | `listPermissions`/`listPermissionsByUser` | `addPermission` | n/a | `deletePermission` | n/a | n/a | Siempre con `company_id` de empresa activa; no usar `selected-company-id` como autoridad. |
| Tracking | `getTrackingPolicy`/`getTrackingStatus`/`listNearbyClients`/`listTrackingAssignments` | `createTrackingPolicy`/`createTrackingAssignment`/`uploadTrackingPoints` | `updateTrackingPolicy`/`updateTrackingAssignment` | fallback actual `listTrackingAssignments` en time-blocks | n/a | timeline/rutas: `listTrackingAssignments` | Hay fallbacks conservadores documentados en rutas de time-blocks hasta sembrar permisos dedicados. |

- QA manual minimo pendiente:
- Caso A: usuario sin permisos operativos debe iniciar sesion, seleccionar empresa y no ver modulos restringidos en Home/sidebar/dashboard; deep-link operativo debe mostrar “Sin permisos”.
- Caso B: usuario solo lectura debe ver listados permitidos y no ver botones crear/editar/eliminar/PDF/exportar/mutaciones.
- Caso C: usuario tecnico debe ver trabajos/worklogs segun permisos y no ver contabilidad general ni reporte contable de empresa sin permiso.
- Caso D: usuario admin debe ver menu completo segun permisos, aprobar solicitudes, editar empresa y gestionar miembros/permisos solo con permisos correspondientes.
- Caso E: al cambiar empresa, permisos y menus deben recalcularse y no deben quedar acciones visibles de la empresa anterior.
- Validacion API: no se modificaron archivos PHP; `vendor/bin/phpunit tests/Services/CompanyAccessServiceTest.php` -> PASS (1 test, 3 assertions). No existen tests `*Permission*.php` dedicados en `sisa.api/tests`.
- Validacion Web: `npm run lint` -> PASS; `npm run build` -> PASS con warning baseline de chunks grandes de Vite. Los artefactos generados en `dist/` y `tsconfig.tsbuildinfo` fueron limpiados.
- Validacion UI mobile: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188` (`selectedJobRecord` sin uso); `npm run check:cache` -> PASS; `npm run check:sync-smoke` -> PASS.
- Validacion typecheck UI mobile: `npx tsc --noEmit --pretty false` sigue bloqueado por deuda TypeScript baseline en `clients/finalizedJobs`, `companies/memberships`, `invoices/[id]`, `job-priorities`, `jobs`, `receipts`, `tracking`, `CircleImagePicker`, `BootstrapContext`, `InvoicesContext` y hooks/cache de sync. El error de `app/invoices/[id].tsx` ya era deuda conocida y solo cambio de linea por el ajuste focalizado de facturas.
- Punto ciego: el barrido de botones se concentro en modulos web/app principales ya auditados; quedan pantallas catalogo secundarias que deben seguir migrando a los helpers comunes en loops pequenos. En app mobile solo se reviso facturas por patron evidente, no se reabrio toda la app.

## SISA API/Web - onboarding multiempresa inicial

Estado: implementado localmente con validacion focalizada.

- API: `GET /companies/search` conserva busqueda autenticada de empresas y ahora devuelve datos publicos minimos con `membership_status`; el CUIT/tax_id sale enmascarado y no se exponen usuarios, permisos ni configuracion privada.
- API: se agregaron endpoints compatibles con el flujo nuevo: `GET /companies/my`, `POST /companies/{company_id}/join-requests`, `GET /companies/join-requests/my`, `GET /companies/{company_id}/join-requests`, `POST /companies/join-requests/{request_id}/approve`, `POST /companies/join-requests/{request_id}/reject` y `POST /companies/active`.
- API: las solicitudes de ingreso reutilizan `empresas_usuarios.estado = pending` para no duplicar estructuras existentes; duplicados pending devuelven `COMPANY_JOIN_REQUEST_ALREADY_PENDING` y miembros aprobados devuelven `ALREADY_COMPANY_MEMBER`.
- API: aprobar/rechazar/listar solicitudes valida admin/owner dentro del controlador; seleccionar empresa activa valida membresia aprobada antes de actualizar `user_configurations.company_default_id`.
- API: `/profile` devuelve `active_company_id`, `companies` y `onboarding_state` (`no_company`, `join_pending`, `active_company`) sin inventar permisos operativos para usuarios sin empresa activa.
- API: `CompanyUsers::listMembersByCompany()` tolera esquemas sin `user_profile.profile_file_id`, cubriendo fixtures SQLite e instalaciones antiguas sin cambiar el contrato cuando la columna existe.
- Web: la sesion ya no selecciona una empresa por fallback local; usa la empresa activa devuelta por API o queda sin `selectedCompanyId` hasta seleccion explicita.
- Web: `selectCompany` persiste contra `POST /companies/active`; el selector de empresa muestra estado de cambio y actualiza la sesion solo despues de respuesta OK.
- Web: usuarios autenticados sin empresa activa redirigen a `/company-onboarding` y los modulos operativos protegidos no se cargan sin `selectedCompanyId`.
- Web: la pantalla de empresas funciona como onboarding: permite buscar/ver empresas, solicitar acceso, ver solicitud pendiente y seleccionar empresa si ya es miembro; no muestra creacion libre si no hay permiso.
- Web: al tocar una empresa sin permiso de edicion no abre modal editor; abre accion de solicitud/estado. Miembros aprobados sin permiso pueden seleccionar empresa activa.
- Validacion API: `php -l` en `src/Controllers/CompaniesController.php`, `src/Controllers/CompanyUsersController.php`, `src/Controllers/ProfileController.php`, `src/Controllers/UserConfigurationsController.php`, `src/Models/CompanyUsers.php` y `src/Routes/api.php` -> PASS.
- Validacion API: `vendor/bin/phpunit tests/Controllers/CompanyOperationalSyncPublishingTest.php` -> PASS (3 tests, 16 assertions); `vendor/bin/phpunit tests/Models/CompanyUsersTest.php` -> PASS (3 tests, 16 assertions).
- Validacion API bloqueada: los archivos solicitados `tests/Controllers/CompanyOnboardingTest.php`, `CompanyJoinRequestsTest.php`, `AuthProfileCompanyStateTest.php` y `PermissionsSmokeTest.php` no existen en el repo; `vendor/bin/phpunit` completo queda bloqueado por el error de conexion MySQL ya registrado como ruido/deuda de baseline en `AGENTS.md`.
- Validacion Web: `npm run lint` -> PASS; `npm run build` -> PASS con warning preexistente de chunks grandes de Vite.
- Pendiente app móvil: no se implemento `sisa.ui` en este loop; queda pendiente replicar pantalla de seleccion/solicitud y bloqueo de dashboard operativo sin empresa activa.
- Punto ciego: falta suite HTTP/API dedicada para los 15 casos nominales del flujo multiempresa y tests de UI automatizados; la cobertura actual es contrato focalizado, lint/build y comportamiento ya integrado en servicios/componentes web.

## SISA UI - onboarding multiempresa inicial móvil

Estado: implementado localmente con validacion focalizada.

- UI mobile: `AuthContext` ahora lee `/profile` como fuente de verdad multiempresa y expone `activeCompanyId`, `onboardingState`, `userCompanies` y `refreshProfile()`.
- UI mobile: `selected-company-id` queda como cache derivado de `/profile`; se escribe solo cuando el API confirma `active_company_id` y se limpia cuando no hay empresa activa. En restauracion online no se usa empresa cacheada hasta revalidar `/profile`; en offline se permite usar cache previamente validado.
- UI mobile: se agrego `CompanyOnboardingContext` para `GET /companies/my`, `GET /companies/search`, `POST /companies/{company_id}/join-requests`, `GET /companies/join-requests/my` y `POST /companies/active`.
- UI mobile: `selectActiveCompany()` llama `/companies/active`, limpia caches de datos de empresa, refresca perfil, refresca permisos y recarga empresas propias.
- UI mobile: se agrego pantalla `app/company-onboarding.tsx` con estados sin empresa, solicitud pendiente y seleccion de empresa aprobada; no crea ni edita empresas.
- UI mobile: `_layout.tsx` redirige usuarios autenticados sin empresa activa a `/company-onboarding`, no muestra bottom nav ahi y no habilita utilidades post-ready, sync/jobs media, tracking, appointments ni hidratacion contable sin `activeCompanyId`.
- UI mobile: `Home.tsx` tiene guardia directa para no renderizar dashboard ni paneles si no hay empresa activa.
- UI mobile: `PermissionsContext` usa `activeCompanyId` validado por AuthContext como scope de permisos, no la cache local.
- UI mobile: `BottomNavigationBar` deja de auto-seleccionar empresa desde config/membresias; muestra la empresa activa confirmada por AuthContext.
- UI mobile: `CompanyPreferenceScreen` selecciona empresa via `CompanyOnboardingContext.selectActiveCompany()` en vez de activar bootstrap/cache local directamente.
- QA cache: `CompanyOnboardingContext.tsx` queda como excepcion explicita en `scripts/verify-context-cache.js` porque sus resultados publicos/join requests no deben ser cache operativo de campo; la empresa activa se persiste solo si `/profile` o `/companies/active` la confirma.
- Validacion UI mobile: `npm run lint` -> PASS.
- Validacion UI mobile: `npm run check:cache` -> PASS.
- Validacion UI mobile: `npm run check:sync-smoke` -> PASS.
- Validacion typecheck: no existe script `npm run typecheck` en `sisa.ui`; `npx tsc --noEmit --pretty false` sigue fallando por deudas TypeScript preexistentes fuera del flujo multiempresa (clients/finalizedJobs/jobs/receipts/tracking/contextos legacy), sin errores propios remanentes en `AuthContext` tras corregir la inferencia del normalizador nuevo.
- QA manual pendiente obligatorio: usuario nuevo sin empresa debe login -> `/company-onboarding` -> buscar empresa -> solicitar ingreso -> quedar pendiente.
- QA manual pendiente obligatorio: usuario con solicitud pendiente debe reloguear y seguir en onboarding sin montar/cargar modulos operativos.
- QA manual pendiente obligatorio: despues de aprobacion por admin desde web/API, el usuario debe actualizar, ver empresa, seleccionarla y entrar a Home.
- QA manual pendiente obligatorio: usuario con dos empresas debe cambiar A/B, refrescar permisos/datos y no mezclar caches entre empresas.

## SISA UI - hardening operativo sin empresa activa

Estado: implementado localmente con validacion focalizada; QA manual pendiente.

- UI mobile: contexts operativos de clientes, carpetas, trabajos, pagos, facturas, recibos, cajas, reportes, productos/servicios, cuentas, asientos, cierres, transferencias, citas, categorias, proveedores, tarifas, prioridades, estados, plantillas de pago, presupuestos y tracking usan `AuthContext.activeCompanyId` como autoridad de empresa activa.
- UI mobile: si falta `activeCompanyId` los contexts publican estado vacio seguro y no hidratan SQLite/cache operativo, bootstrap de empresa ni endpoints de datos de campo.
- UI mobile: los endpoints operativos revisados ahora agregan `company_id` cuando consultan o mutan datos scopiados; las mutaciones devuelven fallo seguro si no hay empresa activa confirmada.
- UI mobile: `selected-company-id` queda limitado a cache derivado en `AuthContext`/`BootstrapContext` y preferencias de empresa; se elimino el fallback directo desde tracking sync service.
- UI mobile: tracking y auto-sync de jobs no arrancan ni suben datos si no hay `activeCompanyId`; policy/status/nearby/sync llevan `company_id` cuando aplican.
- UI mobile: se agrego `ActiveCompanyRequiredState` y `useRequireActiveCompany` para deep-links operativos; las pantallas revisadas muestran estado claro y boton a `/company-onboarding` si falta empresa activa.
- UI mobile: se endurecieron deep-links de clientes, jobs, facturas, recibos, pagos, citas, tracking nearby, carpetas, proveedores, plantillas de pago, cajas y reportes para cortar loads, refreshes, sync, fetches directos y mutaciones sin `activeCompanyId`.
- UI mobile: create/detail de recibos, pagos, proveedores, cajas y plantillas de pago validan empresa antes de mutar; fetches directos de historial/asientos/movimientos agregan `company_id` cuando aplica.
- UI mobile: `app/company-onboarding.tsx` permite buscar/solicitar acceso a otra empresa aun cuando el usuario ya tenga empresas aprobadas.
- Auditoria mecanica final: `selected-company-id` aparece 12 veces en codigo fuente revisado (`app`, `contexts`, `src`, `components`, `hooks`, `utils`): 11 permitidas en `AuthContext` escribiendo/limpiando cache derivado y 1 permitida en `BootstrapContext` para compatibilidad de bootstrap validada contra `AuthContext.activeCompanyId`.
- Auditoria mecanica final: `rg/getItem/getCachedData` equivalentes no encuentran lecturas directas de `selected-company-id`; tampoco quedan apariciones en `app/*`.
- Auditoria mecanica final: se migraron 2 referencias no permitidas de `selected-company-id` en `CompaniesContext` y `MemberCompaniesContext` a `AuthContext.activeCompanyId`.
- Auditoria mecanica final: se agrego guard visual a auxiliares operativos que faltaban: `payments/viewModal`, `receipts/viewModal`, `tracking/queue`, `tracking/gps-config`, `tracking/daily-route` y `reports/[id]`; `tracking/daily-route` agrega `company_id` al request.
- Auditoria mecanica final: rutas criticas revisadas (`jobs`, `invoices`, `clients/accounting`, `appointments/create`, `payments/*`, `receipts/*`, `tracking/*`, `reports/*`, `quotes/create`) usan `useRequireActiveCompany`/`activeCompanyId`, cortan cargas o acciones sin empresa activa y muestran `ActiveCompanyRequiredState` o quedan cubiertas por el gate global.
- Riesgo cubierto: un usuario autenticado sin empresa activa ya no debe disparar requests operativos ni mostrar datos persistidos de una empresa anterior por cache local.
- Validacion UI mobile: `npm run lint` -> PASS con warning baseline `app/appointments/create.tsx:188` (`selectedJobRecord` sin uso).
- Validacion UI mobile: `npm run check:cache` -> PASS.
- Validacion UI mobile: `npm run check:sync-smoke` -> PASS.
- Validacion typecheck: `npx tsc --noEmit --pretty false` sigue bloqueado por deuda TypeScript de baseline en `clients/finalizedJobs`, `companies/memberships`, `invoices/[id]`, `job-priorities`, `jobs`, `receipts`, `tracking`, `CircleImagePicker`, `BootstrapContext`, `InvoicesContext` y hooks/cache de sync. Se corrigieron errores propios del loop por imports `Company` y payload tipado de reporte de cliente; quedan errores legacy fuera de este hito.
- QA manual A pendiente obligatorio: deep-link sin empresa activa a `/clients`, `/jobs`, `/invoices`, `/receipts`, `/payments`, `/tracking/nearby-clients`, `/folders`, `/providers`, `/payment_templates`, `/cash_boxes` y `/reports` debe mostrar `Seleccioná una empresa` y no disparar loads/sync/mutaciones operativas.
- QA manual B pendiente obligatorio: usuario con solicitud pendiente directo a `/jobs/create` debe quedar bloqueado con estado de empresa requerida/onboarding sin crear borradores ni sync.
- QA manual C pendiente obligatorio: usuario con empresa activa debe poder abrir deep-links a `/clients`, `/jobs`, `/invoices`, `/receipts`, `/payments`, `/providers`, `/cash_boxes` y `/reports` con datos scopiados por `company_id`.
- QA manual D pendiente obligatorio: cambio de empresa desde `/clients` debe limpiar/recargar caches y no mostrar datos de la empresa anterior al volver a pantallas operativas.
- Punto ciego: no se agregaron pruebas E2E automatizadas; la cobertura queda en guards de providers/pantallas, checks livianos y matriz manual A/B/C/D.

## SISA API/Web/UI - recibos mayores a factura y saldo a favor

Estado: implementado localmente con validacion focalizada.

- API: `InvoicesController::attachReceipt` ya no toma el total del recibo como `applied_amount` por defecto; si no viene importe explicito aplica `min(saldo disponible del recibo, saldo pendiente de la factura)`.
- API: si `applied_amount` viene explicito y supera el saldo pendiente de la factura, el endpoint rechaza con 409 y conserva la validacion contable de `ReceiptApplicationService`.
- API: `ReceiptApplicationService` mantiene la restriccion por factura y por total del recibo, permite `total_receipt > total_applied`, deja `unapplied_balance` y reconcilia recibo como `partially_applied` cuando queda excedente.
- API: se agrego `ClientStatementService` y endpoint `/accounting/client-statement` con movimientos cronologicos: facturas como DEBE, aplicaciones como HABER, excedentes como SALDO A FAVOR y `running_balance` acumulado con saldo negativo para credito del cliente.
- Web: el detalle del cliente consume el statement del backend y muestra saldo cronologico; la barra de cobro muestra total recibido, aplicado y saldo a favor del cliente.
- UI mobile: `app/clients/accounting.tsx` consume el mismo statement del backend y muestra saldo acumulado/saldo a favor; crear recibo desde factura envia `applied_amount` sugerido igual al pendiente y permite `price` mayor.
- UI mobile/Web: el error `applied_amount exceeds the invoice pending balance` se traduce como `No podés aplicar a la factura más de lo que debe. El excedente debe quedar como saldo a favor.`
- Test API: recibo 150000 aplicado a factura 100000 deja factura pagada y `unapplied_balance` 50000 con recibo `partially_applied`.
- Test API: se conserva rechazo de `applied_amount` 150000 sobre factura 100000.
- Test API: recibo 300000 paga facturas 100000 + 150000 y deja 50000 a favor.
- Test API: falla de aplicacion no deja escrituras parciales en `invoice_receipt_payments`.
- Test API: statement cronologico verifica saldos 100000 -> 0 -> -50000.
- Validacion API: `php -l src/Services/ReceiptApplicationService.php`, `php -l src/Controllers/InvoicesController.php`, `php -l src/Services/ClientStatementService.php`, `php -l src/Controllers/AccountingController.php` -> PASS.
- Validacion API: `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` -> PASS (15 tests, 52 assertions).
- Validacion API: `vendor/bin/phpunit tests/Services/ClientStatementServiceTest.php` -> PASS (1 test, 3 assertions).
- Validacion API: `vendor/bin/phpunit tests/Controllers/InvoicesOfflineFirstSmokeTest.php` -> PASS (3 tests, 14 assertions).
- Validacion API: `vendor/bin/phpunit tests/Controllers/AccountingControllerTransactionSmokeTest.php` -> PASS (3 tests, 68 assertions).
- Validacion Web: `npm run lint` -> PASS; `npm run build` -> PASS con warning preexistente de chunks grandes de Vite.
- Validacion UI mobile: `npm run lint` -> PASS; `npm run typecheck` no existe en `sisa.ui`.
- Punto ciego: no se agrego E2E HTTP cruzado API/Web/App; la equivalencia visual queda protegida por consumo del mismo endpoint de statement.
- Hardening posterior API: `ClientStatementService` adopta politica `receipt_as_single_credit`: el recibo completo es el HABER contable una sola vez y las aplicaciones quedan como detalle/asignacion, evitando duplicar credito y evitando que el pasado cambie cuando un saldo a favor se aplica a facturas futuras.
- Hardening posterior API: `AccountingController::clientStatement` exige `company_id`, valida scope con `CompanyAccessService`, verifica que `client_id` exista en esa empresa y devuelve 403/404 segun corresponda.
- Hardening posterior API: el statement agrega `opening_balance`, `closing_balance`, `charge_client_payment` como DEBE y running balance desde el saldo inicial cuando hay `start_date`.
- Hardening posterior UI mobile: crear recibo desde factura envia `applied_amount = min(total recibido, saldo pendiente)`, permitiendo cobros menores, iguales o mayores a la factura; la pantalla muestra total recibido, aplicado, saldo pendiente restante y saldo a favor.
- Hardening posterior UI mobile/Web: las llamadas a `/accounting/client-statement` incluyen `company_id`; mobile no carga statement si no hay empresa seleccionada y muestra mensaje claro.
- Hardening posterior Web: se revirtieron los cambios generados por `npm run build` en `dist/`; quedan sucios solo archivos fuente.
- Validacion hardening API: `php -l src/Services/ClientStatementService.php`, `php -l src/Controllers/AccountingController.php`, `php -l src/Controllers/InvoicesController.php`, `php -l tests/Controllers/ClientStatementControllerTest.php` -> PASS.
- Validacion hardening API: `vendor/bin/phpunit tests/Services/ClientStatementServiceTest.php` -> PASS (5 tests, 16 assertions); `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` -> PASS (16 tests, 56 assertions); `vendor/bin/phpunit tests/Controllers/ClientStatementControllerTest.php` -> PASS (4 tests, 7 assertions); `vendor/bin/phpunit tests/Controllers/AccountingControllerTransactionSmokeTest.php` -> PASS (3 tests, 68 assertions).
- Validacion hardening Web: `npm run lint` -> PASS; `npm run build` -> PASS con warning preexistente de chunks grandes de Vite.
- Validacion hardening UI mobile: `npm run lint` -> PASS.
- Hardening posterior estado real vs pendiente: se agrego `ReceiptSettlementAmountService` para centralizar `confirmed_amount`, `pending_amount`, `rejected_amount` y compatibilidad `settled_legacy` para recibos sin items.
- Hardening posterior statement: `ClientStatementService` ahora separa saldo real confirmado (`accounting_balance`/`running_balance`) de `pending_receipts_total` y `rejected_receipts_total`; recibos `pending` no reducen deuda, `partial` reduce solo confirmado, `rejected` no reduce deuda y legacy sin items conserva comportamiento anterior.
- Hardening posterior reporte: nuevo endpoint seguro `GET /accounting/client-statement/report` con la misma validacion de `company_id`, scope y cliente que el statement; devuelve informe JSON con periodo y statement. No se rompen reportes existentes.
- Hardening posterior Web/UI: web y app muestran saldo real, saldo a favor, pendiente de confirmacion y rechazado; movimientos pendientes/rechazados se muestran separados y sin mezclar con cobrado real. Web consume el endpoint de reporte desde `Generar informe`.
- Validacion estado real API: `php -l src/Services/ClientStatementService.php`, `php -l src/Controllers/AccountingController.php`, `php -l src/Services/ReceiptSettlementAmountService.php`, `php -l src/Services/ReceiptApplicationService.php` -> PASS.
- Validacion estado real API: `vendor/bin/phpunit tests/Services/ClientStatementServiceTest.php` -> PASS (9 tests, 32 assertions); `vendor/bin/phpunit tests/Services/ReceiptSettlementAmountServiceTest.php` -> PASS (4 tests, 9 assertions); `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` -> PASS (16 tests, 56 assertions); `vendor/bin/phpunit tests/Controllers/ClientStatementControllerTest.php` -> PASS (8 tests, 13 assertions); `vendor/bin/phpunit tests/Controllers/AccountingControllerTransactionSmokeTest.php` -> PASS (3 tests, 68 assertions).
- Validacion estado real Web: `npm run lint` -> PASS; `npm run build` -> PASS con warning preexistente de chunks grandes de Vite; cambios generados en `dist/` fueron revertidos.
- Validacion estado real UI mobile: `npm run lint` -> PASS.
- Hardening posterior facturas aplicadas vs cobradas: `ReceiptSettlementAmountService` separa `canceled_amount`, `items_total` y `difference_amount`; los items `canceled/cancelled` no se convierten en pendiente falso.
- Hardening posterior facturas aplicadas vs cobradas: `ReceiptApplicationService::getInvoiceApplicationSummary` expone `total_applied`, `total_confirmed`, `pending_settlement_amount`, `rejected_settlement_amount`, `applied_pending_balance`, `real_pending_balance` y mantiene `pending_balance = applied_pending_balance` por compatibilidad.
- Hardening posterior facturas aplicadas vs cobradas: `Invoices`/`InvoiceReceiptPayments` propagan campos basicos de confirmado, pendiente de acreditacion, rechazado, saldo aplicado y deuda real en listados/serializacion sin recalculo frontend.
- Hardening posterior Web/UI: la barra de cobro y la pantalla mobile de contabilidad muestran aplicado, confirmado, pendiente de confirmacion y deuda real; ya no llaman pagado/cobrado a recibos solo aplicados pero pendientes.
- Validacion facturas aplicadas vs cobradas API: `php -l src/Services/ReceiptSettlementAmountService.php`, `php -l src/Services/ReceiptApplicationService.php`, `php -l src/Services/ClientStatementService.php`, `php -l src/Models/Invoices.php` -> PASS.
- Validacion facturas aplicadas vs cobradas API: `vendor/bin/phpunit tests/Services/ReceiptSettlementAmountServiceTest.php` -> PASS (6 tests, 16 assertions); `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` -> PASS (19 tests, 68 assertions); `vendor/bin/phpunit tests/Services/ClientStatementServiceTest.php` -> PASS (10 tests, 35 assertions); `vendor/bin/phpunit tests/Controllers/ClientStatementControllerTest.php` -> PASS (8 tests, 13 assertions).
- Validacion facturas aplicadas vs cobradas Web: `npm run lint` -> PASS; `npm run build` -> PASS con warning preexistente de chunks grandes de Vite; cambios generados en `dist/` fueron revertidos.
- Validacion facturas aplicadas vs cobradas UI mobile: `npm run lint` -> PASS.
- Cierre consistencia facturas: se agrego `InvoiceSettlementSummaryService` como fuente unica para `total_applied`, `total_confirmed`, `pending_settlement_amount`, `rejected_settlement_amount`, `applied_pending_balance`, `real_pending_balance` y estado sugerido.
- Cierre consistencia facturas: `ReceiptApplicationService::getInvoiceApplicationSummary`, `reconcileInvoiceStatus` e `Invoices::normalizeInvoice` delegan en la misma fuente; se elimina el uso all-or-nothing de `invoice_receipt_payments.status` para deuda real.
- Cierre consistencia facturas: `InvoiceReceiptPayments` devuelve `receipt.status` y `receipt.settlement_status`; los links se enriquecen con `confirmed_amount`, `pending_amount` y `rejected_amount` derivados no persistidos.
- Cierre consistencia facturas Web/UI: web y app usan `real_pending_balance` para deuda real y `confirmed_receipts_total`/`pending_settlement_amount` para confirmado y pendiente; `pending_balance` queda solo como compatibilidad de saldo no cubierto por aplicaciones.
- Validacion cierre consistencia API: `php -l src/Services/InvoiceSettlementSummaryService.php`, `php -l src/Services/ReceiptApplicationService.php`, `php -l src/Services/ReceiptSettlementAmountService.php`, `php -l src/Models/Invoices.php` -> PASS.
- Validacion cierre consistencia API: `vendor/bin/phpunit tests/Services/ReceiptSettlementAmountServiceTest.php` -> PASS (6 tests, 16 assertions); `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` -> PASS (19 tests, 72 assertions); `vendor/bin/phpunit tests/Services/ClientStatementServiceTest.php` -> PASS (10 tests, 35 assertions); `vendor/bin/phpunit tests/Controllers/ClientStatementControllerTest.php` -> PASS (8 tests, 13 assertions).
- Validacion cierre consistencia Web: `npm run lint` -> PASS; `npm run build` -> PASS con warning preexistente de chunks grandes de Vite; cambios generados en `dist/` fueron revertidos.
- Validacion cierre consistencia UI mobile: `npm run lint` -> PASS.
- Cierre performance facturas: `InvoiceSettlementSummaryService` agrega `summarizeInvoices()` para calcular settlement de listados con carga batch de links, recibos e items; `Invoices::listAll`, `listInvoicesWithItems` y rango por fecha usan la ruta batch sin cambiar `normalizeInvoice()` para detalle individual.
- Test cierre performance facturas: se agrego `tests/Models/InvoicesTest.php` para proteger equivalencia entre detalle y listado, factura parcial con recibo confirmado/pendiente, recibo legacy confirmado y factura sin recibos.
- Validacion cierre performance facturas API: `php -l src/Services/InvoiceSettlementSummaryService.php`, `php -l src/Models/Invoices.php` -> PASS.
- Validacion cierre performance facturas API: `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` -> PASS (19 tests, 72 assertions); `vendor/bin/phpunit tests/Services/ClientStatementServiceTest.php` -> PASS (10 tests, 35 assertions); `vendor/bin/phpunit tests/Models/InvoicesTest.php` -> PASS (1 test, 14 assertions).
- Informe real estado de cuenta: `GET /accounting/client-statement/report?format=pdf` genera PDF con Dompdf, lo guarda en `uploads/reports`, registra `files` y responde `file_id`, `download_url`, `filename` y metadata basica; `format=json` conserva `report.title`, `report.period` y `report.statement`.
- Informe real estado de cuenta: el PDF usa `ClientStatementService` como verdad contable, muestra empresa, cliente, periodo, fecha de generacion, saldo inicial, tabla cronologica con debe/haber confirmado/pendiente/rechazado/saldo real, totales y nota aclaratoria; no renderiza estado/tipo interno de trabajos.
- Informe real estado de cuenta Web/UI: web reemplaza el boton de informe JSON por `Descargar estado de cuenta` y descarga el `file_id`; mobile agrega descarga PDF desde la pantalla contable usando `FilesContext` para abrir el archivo protegido.
- Validacion informe real API: `php -l src/Controllers/AccountingController.php`, `php -l src/Services/ClientStatementPdfGenerator.php` -> PASS.
- Validacion informe real API: `vendor/bin/phpunit tests/Controllers/ClientStatementControllerTest.php` -> PASS (8 tests, 15 assertions); `vendor/bin/phpunit tests/Services/ClientStatementServiceTest.php` -> PASS (10 tests, 35 assertions); `vendor/bin/phpunit tests/Services/ClientStatementPdfGeneratorTest.php` -> PASS (2 tests, 11 assertions).
- Validacion informe real Web/UI: `npm run lint` y `npm run build` en `sisa.web` -> PASS con warning preexistente de chunks grandes de Vite; `npm run lint` en `sisa.ui` -> PASS.
- QA funcional asistido contable: se agrego `tests/Services/AccountingFunctionalFlowTest.php` como fixture controlado de punta a punta para los casos factura 100000 + recibo confirmado 100000, confirmado 150000 con saldo a favor, pendiente 100000, parcial 40000 confirmado/60000 pendiente, rechazado 100000 y recibo 300000 aplicado a facturas 100000 + 150000 con saldo a favor 50000.
- QA funcional asistido contable: la prueba valida `status`/`payment_status`, `applied_pending_balance`, `real_pending_balance`, `confirmed_receipts_total`/`total_confirmed`, `pending_settlement_amount`, `rejected_settlement_amount`, saldo real del statement, saldo a favor, orden cronologico y HTML del PDF con confirmado/pendiente/rechazado separados.
- QA funcional asistido seguridad/scope: `ClientStatementControllerTest` cubre `company_id` obligatorio, 403 por empresa fuera de scope y 404 por cliente fuera de empresa; web y app conservan guardas para no cargar statement sin `selectedCompanyId`.
- QA funcional asistido privacidad PDF: `ClientStatementPdfGeneratorTest` e `InvoicesControllerPdfRegressionTest` cubren ausencia de `Estado del trabajo` y `Tipo: Estándar` en PDFs publicos; no se agrego metadata operativa al PDF de estado de cuenta.
- Validacion QA funcional API: `php -l src/Controllers/AccountingController.php`, `php -l src/Services/ClientStatementPdfGenerator.php`, `php -l src/Services/ClientStatementService.php`, `php -l src/Services/InvoiceSettlementSummaryService.php`, `php -l tests/Services/AccountingFunctionalFlowTest.php` -> PASS.
- Validacion QA funcional API: `vendor/bin/phpunit tests/Services/AccountingFunctionalFlowTest.php` -> PASS (6 tests, 48 assertions); `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` -> PASS (19 tests, 72 assertions); `vendor/bin/phpunit tests/Services/ClientStatementServiceTest.php` -> PASS (10 tests, 35 assertions); `vendor/bin/phpunit tests/Services/ClientStatementPdfGeneratorTest.php` -> PASS (2 tests, 11 assertions); `vendor/bin/phpunit tests/Controllers/ClientStatementControllerTest.php` -> PASS (8 tests, 15 assertions); `vendor/bin/phpunit tests/Models/InvoicesTest.php` -> PASS (1 test, 14 assertions; mantiene notice local de permisos en `.phpunit.result.cache`); `vendor/bin/phpunit tests/Controllers/InvoicesControllerPdfRegressionTest.php` -> PASS (3 tests, 9 assertions).
- Validacion QA funcional Web/App: `npm run lint` y `npm run build` en `sisa.web` -> PASS con warning preexistente de chunks grandes de Vite; artefactos `dist/` generados por build fueron limpiados. `npm run lint` en `sisa.ui` -> PASS.
- Pendiente real QA funcional: no se adjuntaron screenshots/logs porque no hay convencion activa de evidencias visuales para este flujo; la cobertura queda como tests reproducibles y validacion manual asistida documentada.
- Privacidad PDF publico de factura: `buildInvoicePdfHtml()` ahora filtra defensivamente `pdf_detail_lines` con allowlist de detalles comerciales permitidos (`Carpeta`, `Trabajo #`, `Pago asignado al cliente`, `Fecha del pago`, `Acreedor`) y bloquea metadata interna como estado/tipo de trabajo, `Facturado`, `status_id`, `status_attribute`, `metadata_json`, `source_device_id`, sync/version/debug, tracking/GPS y tecnicos/participantes internos.
- Test privacidad PDF factura: `InvoicesControllerPdfRegressionTest` agrega fixture de item de trabajo con descripcion comercial `Servicio de mantenimiento` y metadata interna contaminante; el HTML conserva la descripcion comercial y no contiene `Estado del trabajo`, `Tipo: Estándar`, `Facturado`, `status_id`, `status_attribute`, `metadata_json` ni `source_device_id`.
- Test privacidad PDF factura: el caso de pago asignado al cliente conserva `Pago cliente`, `Pago asignado al cliente`, fecha del pago y acreedor visible.
- Validacion privacidad PDF factura: `php -l src/Controllers/InvoicesController.php`, `php -l tests/Controllers/InvoicesControllerPdfRegressionTest.php` -> PASS; `vendor/bin/phpunit tests/Controllers/InvoicesControllerPdfRegressionTest.php` -> PASS (4 tests, 21 assertions); `vendor/bin/phpunit tests/Services/AccountingFunctionalFlowTest.php` -> PASS (6 tests, 48 assertions); `vendor/bin/phpunit tests/Services/ClientStatementPdfGeneratorTest.php` -> PASS (2 tests, 11 assertions).

## SISA API - cierre transaccional recibos y pagos

Estado: implementado localmente con validacion focalizada.

- API: `ReceiptsController::addReceipt` ahora crea recibo, items, conciliacion, historial, sync event y `syncReceiptEntries` dentro de la misma transaccion; la contabilidad corre antes del commit.
- API: `ReceiptsController::updateReceipt` ahora actualiza recibo/items, reconcilia, registra historial/eventos y sincroniza contabilidad antes del commit; si falla, rollback completo del update.
- API: `PaymentsController::addPayment` ahora crea pago, historial, sync event y `syncPaymentEntries` dentro de una transaccion unica; la notificacion de caja queda despues del commit.
- API: `PaymentsController::updatePayment` ahora actualiza pago, historial, sync event, `syncPaymentEntries` y `reconcileReceiptsAndInvoicesForPayment` antes del commit; la notificacion queda despues del commit.
- API: recibos/pagos instancian `AccountingFlowService` con la misma conexion transaccional del modelo/controlador.
- API: helpers de recibos usados durante update aceptan la conexion activa para evitar consultas/reconciliaciones fuera de la transaccion.
- Test: se agrego `AccountingControllerTransactionSmokeTest` para proteger que add/update de recibos y pagos mantengan `syncReceiptEntries`/`syncPaymentEntries` antes del commit y con rollback disponible.
- Validacion: `php -l src/Controllers/ReceiptsController.php`, `php -l src/Controllers/PaymentsController.php`, `php -l src/Services/AccountingFlowService.php`, `php -l tests/Services/AccountingFlowServiceTest.php` y `php -l tests/Controllers/AccountingControllerTransactionSmokeTest.php` en `sisa.api` -> PASS.
- Validacion: `vendor/bin/phpunit tests/Services/AccountingFlowServiceTest.php` en `sisa.api` -> PASS (13 tests, 117 assertions).
- Validacion: `vendor/bin/phpunit tests/Controllers/InvoicesOfflineFirstSmokeTest.php` en `sisa.api` -> PASS (3 tests, 14 assertions).
- Validacion: `vendor/bin/phpunit tests/Controllers/AccountingControllerTransactionSmokeTest.php` en `sisa.api` -> PASS (2 tests, 16 assertions).
- Punto ciego: no se agrego harness HTTP/BD completo para simular una falla real de sync contable desde controller; la cobertura nueva protege el orden transaccional por smoke estatico y la semantica de no anular ante plan invalido queda cubierta en `AccountingFlowServiceTest`.
- Correccion posterior: `AccountingControllerTransactionSmokeTest` ahora tambien cubre `InvoicesController::addInvoice` y `updateInvoice`, refuerza que no haya side effects post-commit silenciosos y verifica que recibos/pagos usen `AccountingFlowService($connection)` dentro del bloque transaccional.
- Validacion posterior: `php -l tests/Controllers/AccountingControllerTransactionSmokeTest.php` en `sisa.api` -> PASS.
- Validacion posterior: `vendor/bin/phpunit tests/Controllers/AccountingControllerTransactionSmokeTest.php` en `sisa.api` -> PASS (3 tests, 68 assertions).

## SISA API - sync contable seguro para recibos y pagos

Estado: implementado localmente con validacion focalizada.

- API: `AccountingFlowService::syncReceiptEntries` ahora valida `receiptId`, empresa, cuenta contraparte, caja/cuenta e importe, y arma un plan de asientos antes de anular asientos previos.
- API: `AccountingFlowService::syncPaymentEntries` ahora valida `paymentId`, empresa, caja/cuenta, cuenta contraparte e importe, y arma un plan de asientos antes de anular asientos previos.
- API: si el plan de recibo/pago queda vacio o invalido, el sync devuelve `false` sin llamar a `voidOriginEntries`, por lo que los asientos activos anteriores quedan intactos.
- API: si el plan es valido, anula/recrea dentro de una transaccion; si cualquier `recordEntry` falla despues de anular, el rollback conserva los asientos anteriores activos.
- API: `ReceiptsController`, `PaymentsController` y `ReceiptInstrumentLifecycleService` ya no ignoran `false` en sync de recibos/pagos; convierten la falla contable en error explicito.
- Test: recibo valido -> sync invalido sin empresa/caja conserva 2 asientos activos previos y no marca `deleted_at`/`voided_at`.
- Test: recibo valido -> sync valido modificado anula 2 asientos previos y crea 2 nuevos, sin delete fisico.
- Test: pago valido -> sync invalido sin empresa/caja conserva 2 asientos activos previos y no marca `deleted_at`/`voided_at`.
- Test: pago valido -> sync valido modificado anula 2 asientos previos y crea 2 nuevos, sin delete fisico.
- Validacion: `php -l src/Services/AccountingFlowService.php`, `php -l src/Controllers/ReceiptsController.php`, `php -l src/Controllers/PaymentsController.php`, `php -l src/Services/ReceiptInstrumentLifecycleService.php` y `php -l tests/Services/AccountingFlowServiceTest.php` en `sisa.api` -> PASS.
- Validacion: `vendor/bin/phpunit tests/Services/AccountingFlowServiceTest.php` en `sisa.api` -> PASS (13 tests, 117 assertions).
- Validacion: `vendor/bin/phpunit tests/Controllers/InvoicesOfflineFirstSmokeTest.php` en `sisa.api` -> PASS (3 tests, 14 assertions).
- Correccion posterior: el cierre transaccional de create/update en recibos y pagos queda implementado en el hito `SISA API - cierre transaccional recibos y pagos`.

## SISA API - facturas sin encabezados contables legacy

Estado: implementado localmente con validacion focalizada.

- API: `InvoicesController::addInvoice` ya no invoca `AccountingService::createEntry` para facturas; evita crear encabezados legacy en `accounting_entries` con `account_id` nulo/0 y `amount` 0.
- API: la creacion contable de facturas queda a cargo de `AccountingFlowService::syncInvoiceEntries`, que genera los asientos operativos reales con `origin_type='invoice'` y `origin_id`.
- API: `syncInvoiceEntries` ahora se ejecuta antes del `commit` de `addInvoice`; si falla la contabilidad, se hace rollback de la creacion de factura/items/historial/eventos de esa transaccion.
- Test: se agregaron verificaciones para factura emitida nueva con subtotal 1000, IVA 210 y total 1210: no hay encabezado legacy y existen 3 asientos operativos activos con importes 1210 debit, 1000 credit y 210 credit.
- Test: se agrego cobertura de factura draft nueva: no crea asientos activos ni encabezado legacy.
- Test: se agrego guardia para que `addInvoice` no vuelva a llamar `AccountingService::createEntry` y para que `syncInvoiceEntries` quede antes del commit.
- Validacion: `php -l src/Controllers/InvoicesController.php`, `php -l src/Services/AccountingFlowService.php`, `php -l src/Services/AccountingService.php`, `php -l tests/Services/AccountingFlowServiceTest.php` y `php -l tests/Controllers/InvoicesOfflineFirstSmokeTest.php` en `sisa.api` -> PASS.
- Validacion: `vendor/bin/phpunit tests/Services/AccountingFlowServiceTest.php` en `sisa.api` -> PASS (9 tests, 79 assertions).
- Validacion: `vendor/bin/phpunit tests/Controllers/InvoicesOfflineFirstSmokeTest.php` en `sisa.api` -> PASS (3 tests, 14 assertions).
- Correccion posterior: la deuda tecnica de `syncReceiptEntries` y `syncPaymentEntries` anulando antes de validar queda cerrada en el hito `SISA API - sync contable seguro para recibos y pagos`.
- Punto ciego: no se hizo limpieza de filas legacy ya existentes en bases de prueba/produccion; este cambio solo evita nuevas filas basura por el flujo actual de creacion de facturas.

## SISA API - updateInvoice sin idempotencia por timestamp

Estado: implementado localmente con validacion focalizada.

- API: `InvoicesController::updateInvoice` ya no hace early return por `InvoicesHistory::findByOperationAndTimestamp('UPDATE', ...)`; el timestamp queda reservado para auditoria/historial, no como clave de idempotencia.
- API: se separo `timestampForHistory` de `operation_uuid`/`idempotency_key`; como `invoices_history` aun no tiene lookup confiable por clave explicita, no se activa deduplicacion para updates de factura.
- Riesgo cubierto: dos updates web consecutivos en el mismo segundo ya no descartan falsamente el segundo cambio, evitando dejar asientos activos al pasar una factura emitida a borrador despues de cambiar importes.
- Test: se agrego regresion de precio emitido -> precio actualizado -> borrador, verificando que no quedan `accounting_entries` activos, que los asientos quedan anulados con `deleted_at`/`voided_at`/`void_reason` y que no hay delete fisico.
- Test: se agrego guardia focalizada para que `updateInvoice` no vuelva a usar historial por timestamp como gate de duplicado.
- Validacion: `php -l src/Controllers/InvoicesController.php`, `php -l tests/Services/AccountingFlowServiceTest.php` y `php -l tests/Controllers/InvoicesOfflineFirstSmokeTest.php` en `sisa.api` -> PASS.
- Validacion: `vendor/bin/phpunit tests/Services/AccountingFlowServiceTest.php` en `sisa.api` -> PASS (8 tests, 70 assertions).
- Validacion: `vendor/bin/phpunit tests/Controllers/InvoicesOfflineFirstSmokeTest.php` en `sisa.api` -> PASS (2 tests, 11 assertions).
- Punto ciego: no se implemento idempotencia real por `operation_uuid`/`idempotency_key`; queda pendiente agregar columna/indice/lookup confiable antes de reactivar early return para sync/offline.

## SISA API - persistencia de IVA global en facturas

Estado: implementado localmente con validacion focalizada. Correccion web agregada posteriormente.

- API: `Invoices::recalculateTotals` ya no pisa el `tax_amount` de cabecera con cero cuando la factura tiene IVA global y los items no tienen IVA individual.
- API: si los items si tienen `tax_amount`, la factura sigue recalculando el IVA desde las lineas para conservar el comportamiento existente de facturas con IVA por item.
- Riesgo cubierto: creacion y actualizacion de facturas con IVA cargado a nivel comprobante dejaban de mostrar/persistir IVA despues de recalcular totales por items.
- Validacion: `vendor/bin/phpunit tests/Models/InvoicesRecalculateTotalsTest.php` en `sisa.api` -> PASS (2 tests, 8 assertions).
- Punto ciego: no se ejecuto la suite completa ni una prueba HTTP real contra BD MySQL en este turno.
- Web: `InvoicesPage` ahora tiene campo `IVA %`, calcula `tax_amount` como porcentaje sobre subtotal y envia `tax_percentage`, `tax_amount` y `total_amount` coherentes; antes enviaba siempre `tax_amount: 0` y total igual al subtotal.
- Web: al editar, el formulario prellena el porcentaje desde `tax_percentage` si llega o lo deriva de `tax_amount / subtotal`, evitando mostrar 21 pesos como si fuera 21%.
- Validacion posterior web: `npm run lint` en `sisa.web` -> PASS.
- UI mobile: se quitaron los campos `Descuento (%)` e `Impuesto (%)` del editor de items de facturas, tanto en alta como en edicion.
- UI mobile: `prepareInvoiceItemPayloads` ya no envia `discount_amount` ni `tax_amount` por item; el IVA queda a nivel factura mediante porcentaje/monto total.
- Web: el editor de items de factura ya no expone campos de impuesto/descuento por item y el guardado web no envia esos campos en cada item.
- Validacion posterior UI/Web: `npm run lint` en `sisa.ui` -> PASS; `npm run lint` en `sisa.web` -> PASS.
- UI mobile: los campos avanzados de item `Producto/servicio`, `Entidad`, `Trabajo asociado`, `Pago cobrable` y `Orden` pasan a selectores; el orden reacomoda visualmente la lista y actualiza `orderIndex`.
- UI mobile: al elegir producto/servicio, trabajo o pago cobrable se completan descripcion/codigo/precio cuando el dato esta disponible; precio unitario y descripcion siguen editables como entrada necesaria.
- Web: el modal de item de factura reemplaza tipo, trabajo, pago cobrable y orden por selectores nativos; el orden reacomoda la lista y se envia como `order_index`.
- Validacion posterior selectores: `npm run lint` en `sisa.ui` -> PASS; `npm run lint` en `sisa.web` -> PASS.
- UI mobile/Web: los selectores de trabajos y pagos cobrables en items de factura quedan filtrados por el cliente activo de la factura.
- UI mobile/Web: en edicion de una factura existente el cliente queda bloqueado y el payload conserva el `client_id` original, evitando mover comprobantes entre clientes despues de creados.
- Validacion posterior cliente bloqueado/filtros: `npm run lint` en `sisa.ui` -> PASS; `npm run lint` en `sisa.web` -> PASS.

## SISA API/UI/Web - tracking GPS runtime global y velocidad v2

Estado: implementado localmente con validacion focalizada; pruebas de dispositivo real pendientes.

- UI mobile: se agrego plugin Expo `plugins/withTrackingForegroundService.js` para generar un Foreground Service Android nativo con modulo `SisaTrackingForeground` durante `expo prebuild`/EAS.
- UI mobile: el Foreground Service Android captura ubicaciones con `LocationManager`, inserta puntos en `gps_points_queue`, lee `sisa.db`, revive `sending`, marca lote `sending`, sube `/tracking/points/batch`, borra puntos aceptados localmente, conserva rechazados como `rejected` y vuelve temporales a `failed`.
- UI mobile: `TrackingRuntimeProvider` se monta globalmente durante la sesion autenticada y ya no depende de `pathname.startsWith('/tracking')` para arrancar captura, watchdog ni sync.
- UI mobile: `TrackingRuntimeProvider` arranca/detiene el Foreground Service Android junto con la policy/permisos/sesion; Expo Go no soporta este servicio, requiere dev build/EAS.
- UI mobile: `src/tracking/syncService.ts` centraliza el envio offline-first con debounce corto, lock de modulo, backoff, timeout, reintento de `sending` abandonados y conservacion de puntos ante errores temporales.
- UI mobile: los puntos aceptados por Expo Location se guardan primero en `gps_points_queue` y luego disparan sync casi en tiempo real incluso desde la tarea background, usando token seguro y cache local cuando React no esta montado.
- UI mobile: los puntos confirmados por backend se borran de SQLite local. Los rechazos definitivos quedan `rejected` y los errores temporales vuelven a `failed`.
- UI mobile: las pantallas de cola/configuracion permiten eliminar rechazados de a uno, seleccionar varios y eliminarlos, o eliminar todos.
- UI mobile: `decisionEngine` baja el limite imposible a 55 m/s, exige intervalos >= 5s para velocidad, descarta/aisla mock, baja precision, jitter y puntos previos invalidos para movimiento/ruta.
- API: `GpsPointMetrics` agrega `gps_metrics_v2` y mantiene `gps_metrics_v1` sin redefinirlo.
- API: ingest batch y recálculo por defecto usan `GpsPointMetrics::CURRENT_ALGORITHM_VERSION` (`gps_metrics_v2`).
- API: `GET /tracking/status` enriquece `last_location` con metrica preferida y expone `speed_mps` como velocidad efectiva o `null`, conservando `provider_speed_mps` como dato crudo.
- API: `gps_metrics_v2` descuenta el margen combinado de precision, exige precision <= 40m, intervalo 5-300s, timestamps crecientes, no mock y velocidad <= 55 m/s; si no hay velocidad confiable deja `effective_speed_mps = null`.
- API: se agrego `scripts/recalculate-gps-metrics-v2.php` para recalculo historico filtrado por usuario, dispositivo, empresa y rango.
- Web: `/tracking-timeline` usa `effective_speed_mps` validada para velocimetro/grafico y muestra `Sin velocidad confiable` cuando no existe metrica valida; no usa `provider_speed_mps` como fallback visual.
- Documentacion: `sisa.ui/docs/architecture/tracking-runtime-offline-first.md` y `sisa.api/docs/tracking-api.md` describen arquitectura, flujo, triggers, metricas v2 y recálculo.
- Validacion: `npm run lint` en `sisa.ui` -> PASS.
- Validacion: `node --check plugins/withTrackingForegroundService.js` en `sisa.ui` -> PASS.
- Validacion: `npx expo config --json` en `sisa.ui` -> PASS.
- Validacion: chequeo focalizado `npx tsc --noEmit --pretty false | Select-String "database/tracking|src/tracking|contexts/TrackingContext|app/_layout"` -> sin errores GPS; `npx tsc --noEmit` completo conserva errores TypeScript preexistentes fuera de GPS.
- Validacion: `php -l src/Models/GpsPointMetrics.php`, `php -l src/Controllers/TrackingController.php` y `php -l scripts/recalculate-gps-metrics-v2.php` en `sisa.api` -> PASS.
- Validacion: `vendor/bin/phpunit tests/Controllers/TrackingControllerTest.php` en `sisa.api` -> PASS de exit code, mantiene ruido de conexion BD local ya registrado en baseline.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite.
- Punto ciego: no se ejecuto en dispositivo Android/Expo real; en condiciones donde Android no permita HTTP en background, la tarea conserva el punto en SQLite y el runtime lo reintenta al recuperar foreground/red.
- Punto ciego: la suite mobile no tiene harness unitario existente para Expo Location/SQLite; la validacion automatizada quedo en lint/typecheck focalizado.

## SISA API - PDF de facturas sin metadatos operativos

Estado: implementado localmente con validacion de sintaxis focalizada.

- API: `InvoicesController::enrichInvoiceItemsForPdf` dejo de agregar al PDF publico las lineas `Estado del trabajo: ...` y `Tipo: ...` para items asociados a trabajos.
- API: se elimino del enriquecimiento PDF la consulta a `statuses` y la resolucion de `work_type`/tarifa que solo alimentaban esos metadatos internos.
- API: `buildInvoicePdfHtml` ahora resuelve `nombre_fantasia` y `razon_social` sin warnings cuando alguna clave falta; si ambos existen, el PDF conserva ambos datos para empresa emisora y cliente facturado.
- Test: se agrego cobertura para confirmar que el PDF muestra nombre de fantasia y razon social cuando ambos estan disponibles.
- Se conserva la estructura de `invoice items`, la creacion de facturas, sync, work_logs y estados de jobs sin cambios; el ajuste queda limitado a los detalles renderizados para PDF.
- Validacion: `php -l src/Controllers/InvoicesController.php` en `sisa.api` -> PASS.
- Validacion: `vendor/bin/phpunit tests/Controllers/InvoicesControllerPdfRegressionTest.php` en `sisa.api` -> PASS (3 tests, 9 assertions), sin warnings.
- Punto ciego: no se genero un PDF real en este entorno; la verificacion ejecutada fue sintaxis, regresion focalizada y busqueda focalizada de etiquetas removidas.

## SISA API/UI - sync work_logs con employee_id real

Estado: implementado localmente en `sisa.api` y `sisa.ui` con validacion focalizada y baseline parcial.

- UI mobile: se elimino la equivalencia falsa `userId = employeeId` para empleados sin `user_id`; un usuario solo selecciona automaticamente un empleado si existe vinculo real `employees.user_id`.
- UI mobile: antes de pushear `work_logs`, la cola corrige payloads pendientes sospechosos (`participant_employee_ids` vacio o igual a `[user_id]`) resolviendo `/employees?company_id=...&status=active`; si no hay empleado vinculado, bloquea el retry con error controlado.
- API: `work_log_participants` valida empleados por `company_id`, `deleted_at` y `status=active` cuando la columna existe.
- API sync: `work_logs` rechaza `job_uuid` que pertenece a otra empresa que la `company_id` del push.
- API: los errores `invalid_work_log_participants` ahora incluyen contexto tecnico de `company_id`, `job_uuid`, ids recibidos, ids invalidos y motivo.
- Validacion: `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerWorkLogsPushTest.php tests/Controllers/WorkLogsControllerTest.php` en `sisa.api` -> PASS (8 tests, 47 assertions).
- Validacion: `npm run lint` en `sisa.ui` -> PASS.
- Baseline: `qa/run-baseline.ps1` -> PASS en Backend PHPUnit, Frontend lint, cache guard y sync smoke; FAIL existente/no relacionado en Frontend startup guard por expectativa `authTokenRef` en `scripts/startup-stability-smoke.js`.
- Punto ciego: no se ejecuto prueba en dispositivo real; requiere confirmar con datos reales que `user_id=1` en `company_id=45` tenga `employees.user_id=1` y usar ese `employees.id`.
- Correccion posterior: `useRunJobsSync` ya no interpreta `participant_employee_ids=[user_id]` como payload legado cuando la lista trae valores; solo resuelve el empleado actual si `participant_employee_ids` viene vacio. Esto evita bloquear localmente worklogs validos cuando coinciden numericamente `user_id` y `employee_id`.
- Validacion posterior: `npm run lint` en `sisa.ui` -> PASS.
- Correccion posterior: el formulario mobile de worklogs ahora carga participantes desde la empresa del trabajo, no desde la empresa activa de la barra de navegacion; cuando se cargan empleados, reemplaza el fallback `[user_id]` por el `employee_id` real del usuario.
- Correccion posterior: el formulario mobile de worklogs aplica automaticamente la tarifa por defecto del cliente cuando el dato llega despues del montaje inicial del formulario y conserva la seleccion manual si el usuario ya eligio una tarifa.
- Validacion posterior: `npm run lint` en `sisa.ui` -> PASS.
- Correccion posterior: el push de worklogs repara operaciones ya encoladas con el patron legado `participant_employee_ids=[user_id]`; intenta resolver el `employee_id` real y, si no lo encuentra, elimina `participant_employee_ids` y envia `participant_user_ids` para que el servidor resuelva por usuario/membresia en vez de rechazar `employee_id` invalido.
- Correccion posterior: `createWorkLog`/`updateWorkLog` ya no serializan `participant_employee_ids` cuando la lista esta vacia, evitando forzar la ruta de validacion por empleado.
- Validacion posterior: `npm run lint` en `sisa.ui` -> PASS.

## SISA API/Web - zonas GPS operativas sobre empresas

Estado: implementado localmente en `sisa.api` y `sisa.web` con validacion de sintaxis/lint/build; PHPUnit focalizado bloqueado por BD local.

- API: se agrego `empresa_gps_zones` con migracion idempotente `scripts/migrations/empresa-gps-zones-phase1.php`, registrada en `install.php` y `update_install.php`.
- API: se agregaron modelo/controlador/rutas para zonas GPS desde clientes, proveedores y empresa real (`/empresas/{id}/gps-zones`).
- API: `EmpresaGpsZoneMatcherService` evalua radio, margen y `Polygon` simple, con scoring, penalizacion por precision y ambiguedad.
- API: `TrackingEventDetectorService` usa primero zona GPS operativa, conserva fallback por direccion principal y no crea link `visited` cuando el match es ambiguo.
- API: `/tracking/nearby-clients` y `/tracking/nearby-providers` agregan campos opcionales `match_source`, `zone_id`, `zone_name`, `match_method`, `confidence` e `is_ambiguous`.
- Web: se agrego servicio `empresaGpsZonesService.ts`.
- Web: `SettingsPage` incluye una seccion exclusiva `Configuracion de empresa · Zonas GPS` con mapa Leaflet para centro/radio y editor basico de poligono por vertices.
- Web: el editor de poligono ya no expone JSON como texto; permite click en mapa, editar lat/lng por punto, insertar puntos intermedios y eliminar vertices. El payload GeoJSON se genera internamente desde los puntos.
- Web: el mapa de zonas GPS ahora permite mas zoom (`maxZoom=21`) y selector de capa `Calle`/`Satelite` con Esri World Imagery.
- Web: en `CompaniesPage`, solo el superusuario ve el boton `Configurar zonas GPS` dentro de cada empresa para abrir la misma configuracion con `gps_company_id`.
- Web: la edicion queda habilitada para superusuario, owner o admin de la empresa activa.
- Web: la vista de cercania muestra fuente GPS/direccion principal y confianza cuando viene del backend.
- Documentacion: `sisa.api/docs/tracking-api.md` describe modelo, jerarquia, endpoints, fallback, ambiguedad y limitaciones fase 1.
- Validacion: `php -l` en `src/Models/EmpresaGpsZones.php`, `src/Controllers/EmpresaGpsZonesController.php`, `src/Services/EmpresaGpsZoneMatcherService.php`, `src/Services/TrackingEventDetectorService.php`, `src/Controllers/TrackingController.php`, `src/Routes/api.php`, `install.php`, `update_install.php`, migracion y diagnostico -> PASS.
- Validacion: `npm run lint` en `sisa.web` -> PASS.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.
- Bloqueo: `vendor/bin/phpunit tests/Controllers/TrackingControllerTest.php` no pudo ejecutarse por conexion BD local rechazada (`SQLSTATE[HY000] [2002]`).
- Punto ciego: no se incorporo editor avanzado de poligonos; se agregan vertices por click y se permite ajuste manual del GeoJSON.
- Punto ciego: tests HTTP/BD reales quedan sujetos al ambiente local disponible.
- Confirmacion: `sisa.ui` no fue tocado.
- Correccion posterior: se corrigio el posible `Internal Server Error` por firmas incompatibles en `EmpresaGpsZones::findById`/`update` al heredar de `BaseModel`; las operaciones con alcance de empresa usan ahora `findByZoneId`/`updateZone`.
- Correccion posterior: se elimino tambien la firma incompatible de `EmpresaGpsZones::create` y los endpoints `/empresas/{id}/gps-zones` devuelven `detail`/`exception` en JSON ante fallas para diagnostico visible en web.
- Correccion posterior: `extractErrorMessage` ahora muestra `detail` junto a `error/message`, para evitar alertas genericas tipo `Internal Server Error` cuando la API devuelve diagnostico JSON.
- Correccion posterior: se agregaron aliases `/companies/{id}/gps-zones` y la web ahora usa esos endpoints; el error handler global fuerza detalles para cualquier ruta que contenga `/gps-zone` aunque `APP_DEBUG` este apagado.
- Correccion posterior: se agrego el import faltante de `EmpresaGpsZonesController` en `src/Routes/api.php`; en remoto `sistema-test` el endpoint autenticado `/api/companies/45/gps-zones?company_id=45&include_inactive=1` paso de `500 Callable EmpresaGpsZonesController::listEmpresaZones() does not exist` a `200 {"zones":[]}`.

## SISA API/Web - tracking blocks a worklogs con entidad detectada

Estado: implementado localmente en `sisa.api` y `sisa.web` con validacion de sintaxis/lint/build; prueba HTTP real pendiente por entorno local/BD.

- API: `TrackingEventDetectorService` guarda metadata normalizada `detected_place` para viajes, visitas a `client`/`provider` y paradas desconocidas.
- API: `WorkLogsController::timeline` adjunta links de `tracking_time_block_links` a cada `tracking_block` y expone campos planos `detected_entity_type`, `detected_entity_id`, `detected_entity_name` y `detected_entity_distance_m`.
- Web: `worklogsTimelineService` normaliza metadata, links y entidad detectada para bloques GPS dentro de `/worklogs-timeline`.
- Web: al convertir un lapso GPS en worklog, si el bloque detecta un cliente se filtran los trabajos por `client_id` y solo se preselecciona el trabajo cuando hay una unica coincidencia.
- Web: los bloques de proveedor o desconocidos conservan metadata visible en el draft/titulo sin forzar seleccion de trabajo incompatible.
- Validacion: `php -l src/Controllers/WorkLogsController.php` en `sisa.api` -> PASS.
- Validacion: `php -l src/Services/TrackingEventDetectorService.php` en `sisa.api` -> PASS.
- Validacion: `npm run lint` en `sisa.web` -> PASS.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.
- Bloqueo: PHPUnit focalizado de tracking/worklogs no se pudo ejecutar de forma confiable por conexion BD local rechazada (`SQLSTATE[HY000] [2002]`).

## SISA Web - tracking links con entidad visible y acciones comerciales

Estado: implementado localmente en `sisa.web` con validacion de lint/build.

- `TrackingTimelinePage` carga catalogos locales de clientes, proveedores y trabajos para resolver links de lapsos GPS sin mostrar solo `entity_id`.
- La resolucion de nombre/logo replica el patron de `ClientsPage`: usa `client_company_id`/`provider_company_id` y carga las `companies` exactas con `listCompaniesForIds`, porque `business_name` puede llegar como `Sin nombre`.
- El resumen de links ahora muestra el nombre resuelto de la entidad, por ejemplo cliente/proveedor/trabajo, con fallback a `Tipo #id` si el catalogo no tiene el registro.
- El editor de links reemplaza el input numerico por selector para `client`, `provider` y `job`, y muestra avatar/logo con `SecureAvatar` cuando la compania vinculada expone `profile_file_id`.
- Para links de cliente sin trabajos abiertos detectados (`completed_at` vacio), aparece la indicacion y botones para crear trabajo o presupuesto.
- Los botones navegan a `/jobs?client_id=...` o `/quotes?client_id=...`; no crean datos ni modifican servidor directamente.
- La fila visual del link se compacto en una tarjeta con logo de entidad mas grande, controles alineados y acciones comerciales en linea secundaria.
- `JobsPage` ahora reconoce `client_id` en querystring y abre el editor de nuevo trabajo con cliente preseleccionado, igual que presupuestos.
- `QuotesPage` ahora reconoce `client_id` en querystring, filtra por ese cliente y abre el editor de nuevo presupuesto con cliente preseleccionado.
- Punto ciego: la deteccion de trabajos abiertos usa `completed_at` porque el catalogo de jobs disponible en esta pantalla no trae atributo semantico de estado finalizado/cerrado.
- Validacion: `npm run lint` en `sisa.web` -> PASS.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA API/Web - persistencia de worklog creado desde lapso GPS

Estado: implementado localmente en `sisa.api` y `sisa.web` con validacion de sintaxis/lint/build; prueba HTTP real pendiente por entorno local/BD.

- Web: al guardar un worklog creado desde un lapso GPS, el payload ahora envia `source_tracking_block_id` con el id del `tracking_time_block` original.
- Web: la validacion previa al POST ahora separa errores de empresa/usuario/fecha, trabajo faltante y tarifa faltante para que el usuario vea por que no se guarda.
- API: `POST /work_logs` acepta `source_tracking_block_id` opcional y valida que sea entero positivo o null.
- API: cuando el worklog se crea, se agrega link idempotente en `tracking_time_block_links` con `entity_type=worklog`, `role=worked_on` y `allocation_percent=100`.
- API: si el request cae por idempotencia y devuelve un worklog existente, tambien asegura el link contra el lapso GPS.
- Punto ciego: `work_logs` sigue requiriendo `job_id` y tarifa/tipo; si el lapso no tiene trabajo compatible, primero hay que crear/seleccionar trabajo y tarifa antes de persistir el worklog.
- Validacion: `php -l src/Controllers/WorkLogsController.php` en `sisa.api` -> PASS.
- Validacion: `npm run lint` en `sisa.web` -> PASS.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA Web - correccion horaria al guardar worklogs desde timeline

Estado: implementado localmente en `sisa.web` con validacion de lint/build.

- Se corrigio `dateTimeFromMinute` en `WorklogsTimelinePage` para enviar `started_at`/`ended_at` como hora de pared `YYYY-MM-DD HH:mm:ss` en vez de ISO UTC con `Z`.
- Causa: `/work_logs/timeline` consulta rangos diarios como `YYYY-MM-DD 00:00:00` a `+1 day` sin convertir Argentina a UTC; enviar `23:xx Argentina` como ISO UTC terminaba guardando `02:xx` del dia siguiente y el worklog desaparecia del dia operativo original.
- Verificacion remota read-only: `php -r 'echo date_default_timezone_get(), PHP_EOL;'` en `sistema_test` devolvio `UTC`, por lo que un datetime sin zona queda coherente con el contrato wall-time actual del endpoint.
- Punto ciego: los worklogs ya creados con el payload ISO anterior pueden haber quedado persistidos en el dia siguiente y requieren correccion de datos separada si se quieren reubicar; no se modifico DB remota.
- Validacion: `npm run lint` en `sisa.web` -> PASS.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA API/Web - ventana Argentina para worklogs timeline

Estado: implementado localmente en `sisa.api` y `sisa.web` con validacion de sintaxis/lint/build.

- API: `/work_logs/timeline?date=YYYY-MM-DD` ahora consulta la ventana UTC correspondiente al dia operativo Argentina: `YYYY-MM-DD 03:00:00` hasta `+1 day 03:00:00`.
- API: `/work_logs/month-activity` usa ventana mensual desde `03:00 UTC` y agrupa con `DATE(DATE_SUB(started_at, INTERVAL 3 HOUR))` para pintar el calendario por fecha Argentina.
- Web: `WorklogsTimelinePage` interpreta timestamps sin zona del servidor como UTC y los convierte a `America/Argentina/Buenos_Aires` para dibujar lapsos y worklogs.
- Web: al crear/mover/redimensionar worklogs desde el timeline, vuelve a enviar ISO UTC, coherente con la ventana `03:00 -> 03:00` del backend.
- Riesgo cubierto: worklogs y lapsos entre 21:00 y 23:59 Argentina ahora pertenecen al dia operativo correcto aunque en UTC sean `00:00-02:59` del dia siguiente.
- Validacion: `php -l src/Controllers/WorkLogsController.php` y `php -l src/Models/WorkLogs.php` en `sisa.api` -> PASS.
- Validacion: `npm run lint` en `sisa.web` -> PASS.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA Web - globos de cliente/proveedor en lapsos GPS

Estado: implementado localmente en `sisa.web` con validacion de lint/build.

- `/worklogs-timeline` ahora resuelve links de lapsos GPS con `entity_type=client|provider` para dibujar un globo con logo/nombre sobre el bloque GPS.
- La resolucion usa los links del lapso como fuente, priorizando `role=visited`, y carga clientes/proveedores mas las `companies` vinculadas para obtener `profile_file_id`.
- El globo usa `SecureAvatar`, por lo que muestra imagen si existe logo y fallback por iniciales si no hay archivo.
- El globo se renderiza como marcador independiente sobre el timeline, centrado en el lapso, para que se vea aunque el bloque GPS sea muy corto o este comprimido.
- En `/worklogs-timeline` se separaron carriles verticales: globo arriba, lapso GPS al medio y worklog abajo, evitando encimado visual.
- En `/tracking-timeline` se agregaron globos de cliente/proveedor en el rail de lapsos, centrados por link del bloque.
- En el mapa de `/tracking-timeline` se agregaron markers de cliente/proveedor en las coordenadas del bloque (`start_lat/start_lng` con fallback a `end_lat/end_lng`) usando el logo protegido cuando existe.
- Se ajusto el rail compacto/fullscreen para separar globo y lapso sin solapamiento visual.
- Al seleccionar un globo del rail se selecciona tambien el punto GPS temporalmente mas cercano; al seleccionar un globo del mapa se selecciona el punto GPS espacialmente mas cercano.
- No se tocaron backend ni reglas horarias en este hito.
- Validacion: `npm run lint` en `sisa.web` -> PASS.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA Web - seleccion multiple de bloques GPS

Estado: implementado localmente en `sisa.web` con validacion de lint/build.

- La tabla de bloques de `/tracking-timeline` ahora tiene seleccion grafica por fila con checkbox visual.
- Se agrego toolbar con contador, `Seleccionar todos`, limpiar seleccion y `Eliminar seleccionados`.
- El borrado en lote usa el endpoint existente por bloque y confirma antes de eliminar; no borra puntos GPS raw.
- Tras eliminar, se refresca el timeline una sola vez y se limpia la seleccion.
- Validacion: `npm run lint` en `sisa.web` -> PASS.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA Web - adaptacion contrato tracking timeline API

Estado: implementado localmente solo en `sisa.web` con validacion de lint/build.

- `trackingCatalogsService` ahora acepta `time_blocks` y fallback `tracking_blocks` para tolerar el contrato nuevo/viejo del timeline GPS.
- Los bloques GPS normalizan campos enriquecidos opcionales de entidad detectada: `detected_entity_type`, `detected_entity_id`, `detected_entity_name`, `detected_entity_profile_file_id` y distancia.
- Los links normalizan campos enriquecidos opcionales de entidad: nombre/label y profile/logo file ids.
- La UI de `/tracking-timeline` usa primero los datos enriquecidos de la API para globos/labels y mantiene fallback a catalogos locales si esos campos no llegan.
- No se toco `sisa.api`.
- Validacion: `npm run lint` en `sisa.web` -> PASS.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA Web - ventana Argentina explicita para tracking timeline

Estado: implementado localmente solo en `sisa.web` con validacion de lint/build.

- `getTrackingTimeline` calcula en la web la ventana UTC del dia Argentina seleccionado: `YYYY-MM-DD 03:00:00` hasta `+1 day 03:00:00`.
- La request a `/tracking/timeline` conserva `date` y `timezone=America/Argentina/Buenos_Aires`, y agrega `date_from`/`date_to` como contrato explicito para APIs que acepten rango.
- El cliente HTTP omite parametros `undefined`, por lo que el cambio no rompe fechas invalidas ni servidores que ignoren `date_from`/`date_to`.
- No se toco `sisa.api` ni servidor remoto; el endpoint actual ya construye su ventana desde `date + timezone`.
- Riesgo cubierto: la web comunica claramente el dia operativo Argentina al servidor como rango UTC `03:00 -> 03:00` sin desplazar visualmente el dia local.
- Punto ciego: si el backend desplegado ignora `date_from`/`date_to`, el comportamiento depende de que respete `timezone`, que fue confirmado por lectura local del controlador pero no por escritura ni prueba remota mutante.
- Validacion: `npm run lint` en `sisa.web` -> PASS.
- Validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA API - tracking GPS timeline auto events fase 1

Estado: implementado en `sisa.api` con validacion de sintaxis focalizada; ejecucion real pendiente por entorno local/BD.

- Se agrego `TrackingEventDetectorService` para convertir puntos GPS + `gps_point_metrics` en bloques sugeridos `tracking_time_blocks` con `source=auto` y `status=suggested`.
- El detector crea viajes `travel`, visitas `visit` enlazadas a `client`/`provider` con `role=visited`, y paradas desconocidas `pause` sin link.
- La deteccion degrada o corta segmentos con mock, suspicious, impossible_speed, saltos sospechosos, baja precision y gaps grandes; la confianza baja cuando hay poca evidencia.
- Se agregaron `auto_signature` y `detection_version` con migracion idempotente para evitar duplicados, mas verificacion por solapamiento temporal fuerte.
- `POST /tracking/points/batch` ejecuta el detector en una ventana acotada alrededor del lote y no falla el upload si la deteccion falla; reporta `sync_hint` y `detected_time_blocks_count`.
- `GET /tracking/timeline` ahora completa `trips`, `stays` y `labels` desde bloques existentes, conservando `points` y `time_blocks`.
- Se agregaron `POST /tracking/time-blocks/{id}/confirm` y `/reject`; no modifican bloques `locked`/`billed` ni eliminados.
- Se agrego `scripts/diagnostics/tracking-event-detector-dry-run.php` para diagnostico por `company_id`, `user_id`, `date` y `dry-run` sin modificar datos.
- Documentacion actualizada en `sisa.api/docs/tracking-api.md` con detector, timeline, estados, ejemplos y limitaciones.
- Validacion: `php -l src/Services/TrackingEventDetectorService.php` en `sisa.api` -> PASS.
- Validacion: `php -l src/Controllers/TrackingController.php` en `sisa.api` -> PASS.
- Validacion: `php -l src/Models/TrackingTimeBlocks.php` en `sisa.api` -> PASS.
- Validacion: `php -l src/Models/TrackingTimeBlockLinks.php` en `sisa.api` -> PASS.
- Validacion: `php -l src/Routes/api.php` en `sisa.api` -> PASS.
- Validacion: `php -l install.php` en `sisa.api` -> PASS.
- Validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- Validacion adicional: `php -l scripts/migrations/tracking-event-detector-phase1.php` y `php -l scripts/diagnostics/tracking-event-detector-dry-run.php` en `sisa.api` -> PASS.
- Punto ciego: no se ejecuto detector contra datos reales ni suite completa por falta de ambiente/BD verificada en esta sesion.

## SISA API/UI - normalizacion participants employees y avatar de usuario

Estado: implementado en `sisa.api` y `sisa.ui` con validacion focalizada; prueba HTTP real de `/employees` pendiente por token.

- API: `Employees` deja de usar imagen propia operativa; `avatar_file_id` ya no se acepta en payload create/update y la respuesta expone `profile_file_id` desde `employee.user_id -> user_profile.profile_file_id`.
- API: `WorkLogParticipants` resuelve nombre/foto por `COALESCE(work_log_participants.user_id, employees.user_id)` para cubrir participantes employee-only y legacy.
- API: `SyncEventGenerator` conserva `profile_file_id` en participantes de worklogs cuando existe.
- API: la migracion `work-log-participants-employees-phase1` se endurecio para backfill solo con match unico de employee activo por `company_id + user_id`.
- API: se agrego `work-log-participants-employees-phase2` idempotente para normalizar legacy `work_log_participants.user_id -> employee_id`, preservar `user_id`, contemplar `job_participants` solo si existe `user_id` legacy, y eliminar `employees.avatar_file_id` sin tocar tracking.
- API: la misma migracion phase2 desactiva duplicados activos de `work_log_participants` por `work_log_id + employee_id` y por fallback legacy `work_log_id + user_id`, usando soft delete para no borrar historia.
- API: se agrego tambien `scripts/migrations/worklog-participants-employee-backfill-safe.php` como migracion idempotente explicita de backfill seguro de participantes; completa `employee_id` solo con match unico activo por `company_id + user_id`, conserva `user_id`, no modifica ambiguos y soft-deletea duplicados activos.
- Mobile: `participants_json` ahora lee arrays legacy, objetos `{ user_id, employee_id }`, `participant_user_ids` y `participant_employee_ids` sin ocultar participantes viejos.
- Mobile: los arrays de participantes leidos desde `participants_json` se deduplican antes de renderizar o reserializar.
- Mobile: al cargar employees activos, se normalizan worklogs locales agregando `participant_employee_ids` cuando existe mapeo `user_id -> employee_id`, preservando `participant_user_ids` legacy.
- Mobile: los avatares de employees usan `profile_file_id` del usuario vinculado o el cache local de users; no usan `avatar_file_id` del employee.
- No se tocaron tablas GPS, captura GPS ni tracking crudo por `user_id`.
- validacion: `php -l` de archivos PHP editados en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/worklog-participants-employee-backfill-safe.php` en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit tests/Models/WorkLogParticipantsTest.php` en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit tests/Controllers/WorkLogsControllerTest.php` en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerWorkLogsPushTest.php` en `sisa.api` -> PASS.
- validacion: `php scripts/diagnostics/worklog-participants-integrity.php` en `sisa.api` -> BLOQUEADO por entorno local sin `.env`/BD disponible (`SQLSTATE[HY000] [2002]`).
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion: `npx tsc --noEmit` focalizado por archivos tocados en `sisa.ui` -> sin errores nuevos; `npx tsc --noEmit` completo sigue bloqueado por deuda global preexistente/no relacionada.
- pendiente externo: confirmar con token real que `GET /employees?company_id=45&status=active` responde en el entorno desplegado, con o sin prefijo `/public`.

## SISA UI - jobs/worklogs employees y priority_id fase 5

Estado: implementado parcialmente en `sisa.ui` con validacion liviana; TypeScript completo sigue bloqueado por deuda global existente.

- `jobs` movil incorpora `priorityId`/`priority_id` en entidad, mapper, schema SQLite version `31`, migracion, repositorio local, create/update, bootstrap, pull sync y snapshots aceptados.
- la creacion de jobs en mobile resuelve la prioridad seleccionada del catalogo y envia `priority_id`, conservando `priority` legacy en paralelo.
- `work_logs` movil ahora preserva `participant_employee_ids` y `participant_user_ids` en el modelo, inputs, persistencia local `participants_json`, bootstrap, pull sync y restore de snapshots aceptados.
- crear/editar worklogs separa IDs seleccionados en `participant_employee_ids`; solo usa `participant_user_ids`/`participants` legacy cuando no hay employee seleccionado.
- `useCompanyUsers` intenta cargar empleados activos y mantiene usuarios de compania como fallback legacy para cuentas sin employee vinculado.
- `ParticipantAvatarStrip` usa `employeeId ?? userId` como ID seleccionable y permite renderizar participantes employee/legacy sin crear un componente nuevo.
- GPS/tracking crudo no se migro: se mantiene basado en `user_id`; la resolucion `user_id -> employee_id` solo se aplica al payload operativo de worklogs cuando existe employee.
- se reviso la alineacion de columnas/parametros SQL para `priority_id` en `SQLiteJobsRepository` y `SQLiteSyncRepository`.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion: `npx tsc --noEmit` en `sisa.ui` -> BLOQUEADO por errores TypeScript globales preexistentes/no relacionados en clientes, invoices, receipts, contexts, tracking y utilidades; se corrigieron los errores directamente introducidos por esta fase que faltaban `priorityId`, `employeeId` o `WorkLogFormState`.
- punto ciego: el endpoint movil `/employees?company_id=...&status=active` queda inferido y debe validarse contra una sesion real/API desplegada.

## SISA Web - WorkLogEditorModal compartido fase 3

Estado: implementado en `sisa.web` con validacion de lint/build.

- se creo `src/components/WorkLogEditorModal.tsx` como editor reutilizable para crear/editar worklogs.
- el componente centraliza la UI del formulario de worklog: `tariff_id`, `job_item_id`, `started_at`, `duration_minutes`, `description`, `billable_flag`, `client_visible_flag`, `participant_employee_ids` y fallback legacy `participant_user_ids`.
- `JobsPage` reemplazo su modal interno por `WorkLogEditorModal` conservando el armado de payload existente y el comportamiento actual del trabajo seleccionado.
- `WorklogsTimelinePage` usa el mismo `WorkLogEditorModal` para crear desde seleccion/fila employee, crear desde fila legacy, editar worklogs existentes y convertir lapsos GPS a worklog.
- crear desde fila employee precarga `participant_employee_ids`; crear desde fila legacy mantiene `participant_user_ids`; editar precarga participantes actuales employee/legacy.
- editar desde timeline precarga `tariff_id` por ID y aplica fallback por `work_type` contra el catalogo de tarifas para worklogs legacy sin `tariff_id`.
- mover/redimensionar worklogs sigue usando el flujo existente y preserva participantes mediante `participant_employee_ids`, `participant_user_ids` y `participants` legacy.
- no se tocaron endpoints ni `sisa.api`.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA API - diagnostico worklog participants employees

Estado: implementado en `sisa.api` con validacion de sintaxis; ejecucion real pendiente por entorno local sin `.env`/BD disponible.

- se agrego `scripts/diagnostics/worklog-participants-integrity.php` como auditoria read-only para revisar `employees`, `work_log_participants`, `work_logs`, `job_participants` y tablas candidatas de tracking/GPS.
- se corrigio el criterio de duplicados de `employees.user_id`: ahora solo es error cuando hay duplicados activos por `company_id + user_id`; duplicados historicos soft-deleted quedan permitidos e informados como OK.
- los mapeos legacy `user_id -> employee_id` y tracking/GPS ahora solo consideran employees activos (`deleted_at IS NULL`) para no revivir empleados historicos.
- el script no ejecuta `INSERT`, `UPDATE`, `DELETE` ni `ALTER`; solo usa `SHOW TABLES`, `SHOW COLUMNS` y `SELECT`.
- reporta totales, referencias invalidas, duplicados activos, worklogs sin participantes, participantes legacy migrables y usuarios de tracking sin employee asociado.
- la salida termina con bloques `OK`, `WARN`, `ERROR` y `Recommended next actions`.
- validacion: `php -l scripts/diagnostics/worklog-participants-integrity.php` en `sisa.api` -> PASS.
- validacion: `php scripts/diagnostics/worklog-participants-integrity.php` en `sisa.api` -> BLOQUEADO por entorno local sin `.env`/BD disponible (`SQLSTATE[HY000] [2002]`).

## SISA Web - timeline operativo por employees

Estado: implementado en `sisa.web` con validacion de lint/build.

- `WorklogsTimelinePage` carga empleados activos y usa employees como eje principal del timeline operativo.
- los lapsos GPS siguen llegando por `user_id`; la pagina resuelve `user_id -> employee_id` cuando hay empleado vinculado y muestra fila legacy cuando no existe vinculo.
- los worklogs se ubican por `participant_employee_ids` y mantienen fallback por `participant_user_ids`/`user_id` sin duplicar bloques cuando ambos apuntan al mismo empleado.
- crear worklogs desde fila de empleado envia `participant_employee_ids`; las filas legacy conservan `participant_user_ids`/`participants` como compatibilidad.
- mover/redimensionar worklogs preserva participantes employee/legacy existentes.
- no se toco `sisa.api` ni el timeline tecnico de captura GPS.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA API/Web - priority_id en jobs fase 1

Estado: implementado en `sisa.api` y `sisa.web` con validacion de sintaxis, PHPUnit y lint/build.

- se agrego migracion idempotente `scripts/migrations/jobs-priority-id-phase1.php` para incorporar `jobs.priority_id`, indexarlo, intentar FK no bloqueante y backfillear desde `jobs.priority` respetando prioridad de company antes de global.
- `install.php` y `update_install.php` registran la migracion; instalaciones nuevas crean `jobs.priority_id` e indice desde el esquema base.
- `Jobs` devuelve prioridad enriquecida (`priority_code`, `priority_label`, color, orden y costos) y ordena `sort=priority` por `job_priorities.order_index` con fallback legacy.
- `JobsController` y `SyncOperationsController` aceptan/validan `priority_id`, completan `priority` legacy con el `code` y mantienen compatibilidad con payloads viejos por string.
- `SyncEventGenerator` serializa `priority_id` y campos enriquecidos sin remover `priority`.
- `sisa.web` JobsPage usa select de `jobPriorities`, guarda `priority_id`, filtra/chipea por catalogo y muestra base mano de obra, ajuste por prioridad y total con prioridad sin modificar worklogs ni tarifas.
- no se borro `jobs.priority`; queda como compatibilidad temporal para clientes antiguos y datos no migrados.
- validacion: `php -l` de archivos PHP editados en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit` en `sisa.api` -> PASS (`PHPUNIT_EXIT_CODE=0`); mantiene la linea conocida de error de conexion BD del baseline.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite.

## SISA Web - participantes de worklogs por employees en JobsPage

Estado: implementado en `sisa.web` con validacion de lint/build.

- `JobsPage` carga empleados activos junto con los datos de soporte del trabajo.
- el modal de crear/editar worklog agrega selector de participantes basado en `employees` activos.
- al editar, se marcan los participantes existentes que llegan con `employee_id`; los participantes legacy con solo `user_id` se muestran como aviso y se conservan visualmente como fallback hasta guardar.
- al guardar worklogs se envia `participant_employee_ids`; si no hay empleados activos se permite guardar con fallback legacy `participant_user_ids` al usuario actual.
- el listado/tarjetas de worklogs muestran nombres enriquecidos de empleado con fallback a `full_name`, `username`, `employee_id` o `user_id`.
- `workLogsService` y `domain.ts` aceptan campos enriquecidos de participante por empleado sin borrar compatibilidad legacy con `user_id`.
- no se toco `WorklogsTimelinePage`; queda pendiente adaptarla al modelo nuevo de `employees` porque todavia usa `users`/`participant_user_ids`.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite y regenera hashes en `dist`.

## SISA API - work_log_participants con employees fase 1

Estado: implementado en `sisa.api` con validacion focalizada; migracion real pendiente por bloqueo conocido de conexion BD local.

- se agrego la migracion incremental `2026-06-work-log-participants-employees-phase-1` para incorporar `work_log_participants.employee_id`, indexarlo, permitir `user_id NULL` y backfillear desde `employees.user_id` cuando exista vinculo activo en la misma empresa.
- `install.php` y `update_install.php` registran la nueva migracion; la migracion legacy de worklogs/participants queda alineada para instalaciones nuevas.
- `WorkLogParticipants` ahora persiste `employee_id`, deduplica participantes activos por `employee_id` cuando existe y mantiene fallback por `user_id` para registros/clientes legacy.
- `WorkLogsController` acepta `participant_employee_ids` con prioridad sobre `participant_user_ids` y `participants`; si se usan payloads legacy resuelve el empleado vinculado cuando existe y conserva `user_id` para compatibilidad.
- `SyncOperationsController` aplica la misma prioridad en push de `work_logs`, manteniendo `participants` como contrato legacy para mobile/offline actual.
- `SyncEventGenerator` serializa participantes de worklogs con `user_id`, `employee_id`, `participant_user_ids` y `participant_employee_ids` durante la transicion.
- `WorkLogs` timeline devuelve tambien `participant_employee_ids`; `AnalyticsController` cuenta participantes por `employee_id` cuando existe y por `user_id` como fallback.
- no se toco `sisa.ui` movil ni se elimino ninguna columna legacy.
- validacion: `php -l src/Models/WorkLogParticipants.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Models/WorkLogs.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Controllers/WorkLogsController.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Controllers/SyncOperationsController.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Controllers/AnalyticsController.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Services/SyncEventGenerator.php` en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/work-log-participants-employees-phase1.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit tests/Models/WorkLogParticipantsTest.php` en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit tests/Controllers/WorkLogsControllerTest.php` en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerWorkLogsPushTest.php` en `sisa.api` -> PASS.

## SISA Web - UX onboarding multiempresa

Estado: implementado en `sisa.web` con validacion de lint/build.

- se corrigio `/memberships`: ahora consulta todas las membresias de la empresa activa, incluyendo `pending`, y muestra acciones `Aprobar`/`Rechazar` para owner/admin o permiso `manageCompanyMemberships`.
- `systemCatalogsService` agrega `listCompanyMemberships()`, `approveCompanyMembership()` y `rejectCompanyMembership()` contra los endpoints administrativos existentes.
- `CompaniesPage` ya no abre siempre el editor al seleccionar una empresa; valida membresia y permisos antes de decidir entre editor y modal de solicitud.
- usuarios sin membresia aprobada, o sin permisos administrativos/`updateCompany`, ven un modal de `Solicitar acceso a empresa` con datos basicos y estado de membresia/solicitud.
- el modal envia `POST /companies/{company_id}/memberships` con `message: Solicitud enviada desde SISA Web` y `role: member`; al completar refresca membresias locales y muestra el toast de aprobacion pendiente.
- solicitudes `pending` no se duplican; membresias `approved` muestran que el usuario ya pertenece; estados `rejected`, `left` y `removed` permiten reintento sujeto a respuesta del backend.
- `companiesService` agrega `requestCompanyMembership()` y `listUserCompanyMemberships()` consultando todos los estados relevantes de membresia.
- `Nueva empresa` se oculta si el usuario no tiene capacidad segura de creacion; `Guardar` y `Eliminar` solo se muestran en editor cuando corresponde por permisos/rol.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite.

## SISA API - onboarding multiempresa

Estado: implementado en `sisa.api` con validacion de sintaxis focalizada.

- `GET /companies`, `GET /companies/search` y `POST /companies/{company_id}/memberships` quedan disponibles para cualquier usuario autenticado no bloqueado, sin permiso especifico adicional.
- se mantiene `CheckUserBlockedMiddleware` en esos endpoints.
- `POST /companies` conserva el permiso `createCompany`; editar, borrar, historial y acciones administrativas de membresias conservan sus permisos existentes.
- `CompaniesController::list` ahora sanitiza la respuesta publica de listado/busqueda para exponer solo identificadores basicos de empresa: `id`, `razon_social`/`business_name`, `nombre_fantasia`, `nro_doc`/`tax_id`, `profile_file_id` y `activo`.
- se verifico que `CompanyUsersController::requestMembership` crea o reabre solicitudes en `pending` para el usuario autenticado y no aprueba automaticamente.
- documentacion interna: `docs/company-memberships.md`.
- validacion: `php -l src/Routes/api.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Controllers/CompaniesController.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Controllers/CompanyUsersController.php` en `sisa.api` -> PASS.

## SISA API/Web - job_participants fase 1

Estado: implementado en `sisa.api` y `sisa.web` con validacion focalizada; migracion real pendiente por bloqueo de conexion BD local.

- se agrego `job_participants` como relacion entre trabajos y `employees`, sin usar `users` para esta nueva funcionalidad.
- se agrego `job_participants_history` con snapshot JSON siguiendo el patron usado por `employees_history`.
- se agregaron endpoints `GET/POST/PUT/DELETE /jobs/{job_id}/participants` con permisos `listJobParticipants`, `addJobParticipant`, `updateJobParticipant` y `deleteJobParticipant`.
- las mutaciones validan empresa, trabajo activo, empleado activo de la misma empresa, duplicados activos y `ClosedJobMutationGuard`.
- al marcar un participante como responsable principal, la API desmarca otros responsables activos del mismo trabajo dentro de una transaccion.
- `sisa.web` agrega una pestaña `Participantes` en el detalle del trabajo y un componente separado `JobParticipantsPanel`.
- no se toco `sisa.ui` movil, no se modifico `work_log_participants`, no se uso `jobs.participants` y no se agregaron documentos/costos/contabilidad de empleados.
- documentacion interna: `docs/job-participants-phase1.md`.
- validacion: `php -l src/Controllers/JobParticipantsController.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Models/JobParticipants.php` en `sisa.api` -> PASS.
- validacion: `php -l src/History/JobParticipantsHistory.php` en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/job-participants-phase1.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Routes/api.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Models/Permission.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php update_install.php` en `sisa.api` -> BLOQUEADO por entorno local sin `.env`/BD disponible (`SQLSTATE[HY000] [2002]`, `update_install_exit=1`).
- validacion: `vendor/bin/phpunit` en `sisa.api` -> PASS (`phpunit_exit=0`); mantiene la linea conocida de error de conexion BD del baseline.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS; regenero `dist` y `tsconfig.tsbuildinfo`, mantiene warning existente de chunks grandes de Vite.

## SISA API/Web - employees fase 1

Estado: implementado en `sisa.api` y `sisa.web` con validacion focalizada.

- se agrego la entidad `employees` como personas operativas separadas de `users`, que siguen representando cuentas de acceso.
- `employees.user_id` es nullable; cuando se informa, el backend valida membresia aprobada en la misma empresa mediante el patron existente de `company_users`.
- se agrego migracion incremental `2026-06-employees-phase-1` con `employees` y `employees_history`, UUID, `company_id`, soft delete, versionado, metadata de origen y auditoria basica.
- se agregaron endpoints CRUD minimos protegidos por `CheckUserBlockedMiddleware` y permisos `list/get/add/update/deleteEmployee`.
- se agrego `sisa.web` `/employees` con listado, alta, edicion, archivado y vinculacion opcional a usuarios de la empresa.
- no se toco `sisa.ui` movil ni se implementaron participantes, documentos, pagos, liquidaciones o integraciones con jobs/worklogs.
- documentacion interna: `docs/employees-phase1.md`.
- validacion: `php -l src/Controllers/EmployeesController.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Models/Employees.php` en `sisa.api` -> PASS.
- validacion: `php -l src/History/EmployeesHistory.php` en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/employees-phase1.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Routes/api.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit` en `sisa.api` -> PASS (`phpunit_exit=0`); mantiene la linea conocida de error de conexion BD del baseline.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite.

## SISA API - timeline diario de worklogs/tracking

Estado: implementado en `sisa.api` y `sisa.web` con validacion focalizada.

- se agrego `GET /work_logs/timeline` con permiso `listWorkLogs` para consultar una fecha (`date=YYYY-MM-DD`), empresa (`company_id`) y opcionalmente usuario (`user_id`).
- la respuesta devuelve usuarios operativos, `tracking_blocks` existentes y `work_logs` del dia con participantes para que el cliente pueda renderizar worklogs compartidos por entidad unica.
- se agrego `GET /work_logs/month-activity` para consultar `month=YYYY-MM` y obtener los dias con worklogs cargados, filtrable por usuario.
- no se modifico el flujo de creacion/sync offline-first de worklogs; estos endpoints son de lectura para la vista grafica.
- punto ciego: `client_name` queda nulo en la vista timeline hasta consolidar una fuente unica compatible con instalaciones legacy de `clients`; se devuelve `client_id` y `job_title`.
- `sisa.web` incorpora la ruta `/worklogs-timeline` y el item de menu Tracking -> GPS + Worklogs.
- la pantalla web carga timeline diario, dias del mes con worklogs, selector de usuario, zoom inicial x2, columna de usuarios fija/ocultable, seleccion manual, conversion de lapso GPS a worklog y edicion grafica por arrastre/resize de worklogs compartidos.
- la creacion de worklogs desde timeline exige seleccionar un trabajo existente porque el contrato operativo actual de `POST /work_logs` requiere `job_id`.
- validacion: `php -l src/Models/WorkLogs.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Controllers/WorkLogsController.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Routes/api.php` en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit tests/Controllers/WorkLogsControllerTest.php` en `sisa.api` -> PASS.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite.

## SISA API - status Cotizar

Estado: implementado en `sisa.api` con validacion focalizada; pendiente rerun de `update_install.php` contra base real.

- se agrego el atributo semantico `quote` al registro permitido de `StatusAttributeRegistry` para representar el estado visible `Cotizar`, separado de `quoted`/`Cotizado`.
- `scripts/migrations/statuses-phase3.php` ahora siembra idempotentemente el status global `Cotizar` (`scope=job`, `status_attribute=quote`) solo cuando no existe activo; contempla catalogos legacy con `scope NULL` y no reordena catalogos ya poblados.
- `install.php` incluye `Cotizar` en el seed inicial antes de `Cotizado`; los ordenes posteriores se desplazaron solo para instalaciones nuevas.
- `update_install.php` registra la migracion nueva `2026-06-statuses-cotizar` para ejecutar el seed aunque `2026-03-statuses-phase-3` ya este marcada como aplicada.
- validacion: `php -l src/Services/StatusAttributeRegistry.php` en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/statuses-phase3.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit tests/Services/StatusAttributeRegistryTest.php` en `sisa.api` -> PASS.
- validacion: `vendor/bin/phpunit tests/Controllers/StatusControllerTest.php` en `sisa.api` -> PASS; mantiene el ruido conocido de conexion BD documentado en baseline.

## SISA API - completar parcial new_id phase45

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php`

- problema real observado: `tracking_policies` quedo parcialmente reparada con `new_id` como `PRIMARY KEY AUTO_INCREMENT` e `id` sano (`1`, `2`) sin key; intentar convertir `id` directo fallaba porque ya existia una columna auto_increment.
- se agrego `completePartialNewIdRepairPreservingOldIdIfSafePhaseFortyFive()`: detecta `id + new_id`, `PRIMARY KEY(new_id)` y `new_id AUTO_INCREMENT`.
- si `id` esta sano (sin nulos, ceros, negativos ni duplicados), preserva sus valores, quita `AUTO_INCREMENT` de `new_id`, elimina la primary key temporal, elimina `new_id`, agrega `PRIMARY KEY(id)`, convierte `id` a `AUTO_INCREMENT` y ajusta next value.
- si `id` no esta sano y `new_id` es seguro, completa por el camino alternativo renombrando `new_id` a `id`.
- para `tracking_policies`, antes de completar valida FKs entrantes a `id` y `new_id`, y loguea referencias semanticas encontradas para auditoria.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.

## SISA API - PK previa a AUTO_INCREMENT phase45

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php`

- problema real observado: `tracking_policies.id` ya no tenia nulos/ceros/duplicados, pero `MODIFY COLUMN ... AUTO_INCREMENT` fallaba con `Incorrect table definition; there can be only one auto column and it must be defined as a key`.
- se reforzo `ensureGlobalAutoIncrementPrimaryKeyPhaseFortyFive()` para que antes de cualquier `AUTO_INCREMENT` agregue `PRIMARY KEY(columna)` cuando no exista PK, validando nulos, duplicados y valores no positivos.
- el helper ahora verifica por `SHOW COLUMNS` que la columna queda con `Key=PRI` despues de agregar la primary key; si hay PK distinta, aborta con mensaje claro de incompatibilidad.
- esto aplica de forma generica a `tracking_policies.id` y cualquier otra columna tecnica `id/history_id` reparada por phase45.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.

## SISA API - ajuste final tracking_policies phase45

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php`

- se verifico el `foreach` de `ensureGlobalAutoIncrementIntegrityRepairPhaseFortyFive()`: la llamada a `repairGlobalAutoIncrementColumnPhaseFortyFive()` queda una sola vez dentro del `try/catch`, despues del log `Checking table.column...`; no queda llamada suelta previa.
- se ajusto la clave logica de deduplicacion de `tracking_policies` para usar exactamente `COALESCE(effective_from, '1000-01-01 00:00:00')` como valor fallback.
- se mantiene el caso especial de `tracking_policies.id`: FKs entrantes y referencias semanticas peligrosas se validan antes de deduplicar; se conservan IDs positivos existentes y se reasignan solo IDs no positivos despues de eliminar duplicados exactos.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion adicional: `php -l src/Models/TrackingPolicies.php` en `sisa.api` -> PASS.

## SISA API - cierre phase45 tracking/restantes

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php` y validacion SQL global

- problema real observado: phase45 se frenaba en `tracking_policies.id` con miles de filas `id=0`, sin primary key ni `AUTO_INCREMENT`, originadas por seed repetido no idempotente.
- se agrego caso especial para `tracking_policies.id`: verifica FKs entrantes y referencias semanticas peligrosas con valor `0` (`tracking_policy_id`, `policy_id`, `tracking_policy`, `policy_uuid`) antes de deduplicar.
- si es seguro, deduplica `tracking_policies` por clave logica exacta, conserva la fila mas nueva por grupo (`updated_at`, `created_at`) y elimina solo duplicados exactos; usa `new_id` temporal solo para identificar filas duplicadas, preservando IDs positivos existentes.
- despues reasigna `id <= 0` a `MAX(id)+1`, elimina `new_id`, agrega `PRIMARY KEY(id)`, aplica `AUTO_INCREMENT` preservando tipo real y crea indices no unicos minimos: `idx_tracking_policies_name_version`, `idx_tracking_policies_enabled_profile`, `idx_tracking_policies_effective_from`.
- se corrigio `TrackingPolicies::seedDefaults()` para no depender de `ON DUPLICATE KEY`: busca por `name + version`, actualiza si existe e inserta si no existe, nunca enviando `id`; el `CREATE TABLE` del modelo ya no crea unicos de negocio por `name` o `version`.
- phase45 ahora incluye explicitamente columnas tecnicas restantes reportadas: `notification_user_states.id`, `users.id`, `user_configurations.id`, `user_configurations_history.history_id`, `user_devices.id`, `user_notifications.id`, `user_profile.id`, `user_profile_history.history_id`, `user_tracking_assignments.id`, `work_logs.id`, `work_logs_history.history_id` y `work_log_participants.id`.
- para `users`, `user_profile` y `user_configurations`, phase45 no renumera: aborta si hay duplicados o IDs no positivos; si son unicos/positivos, agrega PK y `AUTO_INCREMENT`.
- para IDs tecnicos operativos restantes sin FKs entrantes, phase45 aborta ante duplicados positivos y solo reasigna valores no positivos a `MAX(id)+1`; para historicos `*_history.history_id` aplica el flujo historico ya documentado.
- se agrego log visible de estadisticas por columna: `nulos`, `ceros`, `negativos` y `duplicados` antes de reparar.
- `activity_log.id` sigue delegado a phase46 para no bloquear phase45.
- no se tocaron frontend, endpoints, payloads publicos, UUIDs ni datos de negocio no duplicados.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Models/TrackingPolicies.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- pendiente: ejecutar `update_install.php`, confirmar que `tracking_policies.id` queda `PRI`/`auto_increment`, sin ceros ni duplicados, con indices minimos, y repetir la consulta global de `information_schema.COLUMNS`.

## SISA API - reparacion sync_operations.id phase45

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php` y validacion SQL de indices sync

- problema real observado: phase45 se frenaba en `sync_operations.id` con 201 filas `id=0`, sin primary key, sin `AUTO_INCREMENT` y sin indices.
- se agrego caso especial en `global-auto-increment-integrity-repair-phase45.php` para `sync_operations.id`: verifica FKs entrantes hacia `sync_operations.id` y aborta con tablas/columnas si existen.
- si no hay FKs, aborta ante duplicados positivos reales, reasigna solo `id <= 0` a IDs nuevos `MAX(id)+1` fila por fila, revalida nulos/ceros/duplicados, agrega `PRIMARY KEY(id)`, aplica `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` y ajusta `AUTO_INCREMENT = MAX(id)+1`.
- no toca `operation_uuid`, `idempotency_key`, `device_id`, `payload`, checkpoints ni datos de negocio; no borra operaciones de sync.
- restaura indices esperados de forma idempotente: `uq_sync_operations_uuid`, `uq_sync_operations_idempotency`, `idx_sync_operations_company_id`, `idx_sync_operations_entity_uuid`, `idx_sync_operations_entity_type`, `idx_sync_operations_status_created_at` e `idx_sync_operations_device_created_at`.
- antes de crear `uq_sync_operations_uuid`, valida que no haya `operation_uuid` duplicados; antes de crear `uq_sync_operations_idempotency`, valida duplicados por `(company_id, device_id, idempotency_key)` considerando `company_id NULL`; si hay conflictos, aborta con contexto en vez de deduplicar agresivamente.
- `SyncOperations::$fillable` no incluye `id`; se agrego `SyncOperations::create()` defensivo para descartar cualquier `id` entrante, exigir ID positivo y registrar `sync_operation_create_failed` con `operation_uuid`, `entity_type`, `entity_uuid`, `idempotency_key`, `device_id`, causa e ID devuelto si falla.
- se revisaron inserciones localizadas: `SyncOperationsController` y `SyncEventGenerator` crean via `SyncOperations::create()` y no envian `id` manual.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Models/SyncOperations.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- pendiente: ejecutar `update_install.php`, confirmar `sync_operations.id` con `PRI`/`auto_increment`, sin nulos, ceros ni duplicados, y validar los indices esperados con `SHOW INDEX FROM sync_operations`.

## SISA API - reparacion history_id historicos phase45

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php` en Hostinger/MySQL

- problema real observado: phase45 se frenaba en `jobs_history.history_id` con duplicados `history_id=0` en 8 snapshots, sin primary key ni auto_increment y sin FKs entrantes.
- se agrego flujo especifico en `global-auto-increment-integrity-repair-phase45.php` para tablas `*_history.history_id`: verifica FKs entrantes y aborta si existen.
- si no hay FKs, valida nulos, aborta ante duplicados positivos reales y repara solo valores `history_id <= 0` asignando IDs nuevos `MAX(history_id)+1` fila por fila con `UPDATE ... LIMIT 1`.
- despues de sanear valores no positivos, revalida nulos/duplicados, agrega `PRIMARY KEY(history_id)` si no existe primary key, aplica `MODIFY COLUMN history_id TIPO_REAL NOT NULL AUTO_INCREMENT` y ajusta `AUTO_INCREMENT = MAX(history_id)+1`.
- el flujo es generico para `jobs_history`, `job_items_history`, `work_logs_history`, `job_groups_history`, `job_group_members_history`, `root_causes_history`, `job_root_cause_links_history`, `file_attachments_history` y cualquier otra `*_history.history_id` incluida/detectada por phase45.
- no borra snapshots, no trunca, no recrea tablas, no cambia UUIDs, frontend, endpoints ni payloads.
- `BaseHistory::log()` ahora descarta defensivamente cualquier `history_id` entrante antes de insertar historicos, para que MySQL genere el ID tecnico.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion adicional: `php -l src/History/BaseHistory.php` en `sisa.api` -> PASS.
- pendiente: ejecutar `update_install.php`, confirmar `jobs_history.history_id` con `PRI`/`auto_increment`, sin nulos, ceros, negativos ni duplicados, y continuar con el resto de phase45.

## SISA API - reparacion devices.id phase45

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php` y prueba funcional de dispositivo/login

- problema real observado: phase45 se frenaba en `devices.id` con duplicados `id=0`; la tabla vieja permitia multiples filas del mismo `device_uid` porque faltaban `AUTO_INCREMENT` en `id` y `UNIQUE(device_uid)`.
- se agrego caso especial en `global-auto-increment-integrity-repair-phase45.php` para `devices.id`: verifica FKs entrantes hacia `devices.id` y aborta con tablas/columnas si existen.
- si no hay FKs, usa `new_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST` como identificador tecnico temporal, sanea duplicados por `device_uid`, elimina solo filas sobrantes del mismo `device_uid`, renombra `new_id` a `id` y ajusta `AUTO_INCREMENT = MAX(id)+1`.
- el saneamiento de `devices` conserva una fila por `device_uid` segun `revoked_at IS NULL`, `last_seen_at`, `updated_at`, `created_at`; fusiona `last_seen_at` mayor, `updated_at` mayor, `first_seen_at` menor y ultimo `expo_push_token` no vacio si existe; conserva `metadata` de la fila elegida.
- se asegura un indice unico equivalente a `UNIQUE(device_uid)`, agregando `uq_devices_device_uid` solo si no existe ya un unico equivalente.
- se agrego log visible por `device_uid`, fila `new_id` conservada y cantidad eliminada; no se borran dispositivos unicos ni se borra un `device_uid` completo.
- se agrego logica similar para `device_aliases.id` respetando `UNIQUE(alias_type, alias_value)` y para `device_sync_state.id` respetando `UNIQUE(device_uid, scope)`; ambas abortan si hay FKs entrantes hacia su `id`.
- `Devices::upsertByDeviceUid()` ahora elimina cualquier `id` antes de crear, valida que `BaseModel::create()` devuelva ID positivo y registra `device_create_failed` si devuelve `false`, `0`, `"0"` o `null`; si encuentra una fila existente con `id <= 0`, registra warning `device_existing_non_positive_id`.
- no se tocaron frontend, endpoints ni payloads publicos.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Models/Devices.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- pendiente: ejecutar `update_install.php`, confirmar `devices.id` con `PRI`/`auto_increment`, sin `id` duplicados, sin `id=0`, sin `device_uid` duplicados y con `UNIQUE` sobre `device_uid`; luego probar login/upsert de dispositivo.

## SISA API - reparacion auth_sessions.id phase45

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php` y prueba funcional de login

- problema real observado: phase45 se frenaba en `auth_sessions.id` con duplicados `id=0` en 38 sesiones reales; el insert de login no enviaba `id`, la causa era una tabla vieja sin `AUTO_INCREMENT`.
- se agrego caso especial en `global-auto-increment-integrity-repair-phase45.php` para `auth_sessions.id`: verifica FKs entrantes a `auth_sessions.id` y aborta con tablas/columnas si existen.
- si no hay FKs, regenera solo el ID tecnico preservando filas con `new_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST`, elimina la columna vieja `id`, renombra `new_id` a `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` y ajusta `AUTO_INCREMENT = MAX(id)+1`.
- la reparacion no toca `session_id`, `token_hash`, `user_id`, `device_uid`, fechas ni metadata; no trunca ni borra sesiones.
- la reparacion es idempotente: si ya esta `PRIMARY KEY AUTO_INCREMENT` sin duplicados/valores no positivos solo ajusta next value; si queda `new_id` parcial lo completa si es seguro o aborta con mensaje claro.
- `AuthSessions::createSession()` ahora elimina cualquier `id` entrante antes del insert, exige que `BaseModel::create()` devuelva ID positivo y registra `auth_session_create_failed` con tabla, `session_id`, `user_id`, `device_uid`, causa e ID devuelto si falla.
- `AuthController::login()` mantiene el fallback legacy sin romper login, pero ahora registra `SESSION_CREATE_FAILED` con `user_id`, `session_id`, `device_uid` y causa antes del fallback; no expone errores internos al cliente.
- `auth-sessions-standard.php` mantiene `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` y `UNIQUE session_id`.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/auth-sessions-standard.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Models/AuthSessions.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Controllers/AuthController.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- pendiente: ejecutar `update_install.php`, confirmar `auth_sessions.id` con `PRI`/`auto_increment`, sin duplicados ni `id=0`, y probar login creando una fila nueva con ID positivo y `session_id` en respuesta.

## SISA API - cobertura y observabilidad phase45 global

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php` en Hostinger/MySQL

- problema observado: phase45 comenzo y reparo `accounts.id` y `app_updates.id`, pero no imprimio `Update ... completed`; la consulta general seguia mostrando multiples `id/history_id` tecnicas sin `AUTO_INCREMENT`.
- phase45 ahora imprime progreso por columna con `Checking`, skips de tabla/columna ausente, `Already AUTO_INCREMENT`, `Primary key added`, `Repairing`, `Done` y `Error repairing ...` con mensaje real.
- cada reparacion individual esta envuelta en `try/catch`; si falla, imprime tabla/columna/error, registra `error_log` con tipo/key/extra/causa y relanza `RuntimeException` con contexto.
- se amplio el listado explicito con tablas pendientes reportadas: `empresas_direcciones_history`, `empresas_history`, `empresas_usuarios`, `empresas_usuarios_history`, `empresa_canales`, `empresa_canales_history`, `empresa_contactos` y `empresa_contactos_history`.
- ademas phase45 incorpora dinamicamente desde `information_schema.COLUMNS` cualquier columna numerica `id/history_id` pendiente sin `auto_increment`, excluyendo `jobs_archive_checks` y `notification_user_states`, y sigue delegando `activity_log.id` a phase46.
- antes del `ALTER ... AUTO_INCREMENT`, valida nulos, duplicados y sanea valores `<= 0` reasignandolos a `MAX(columna)+1`; si hay foreign keys entrantes sin mapeo conocido, aborta con error claro en vez de reparar a ciegas.
- se preserva `COLUMN_TYPE` real y se sigue usando `information_schema.STATISTICS` para primary keys, compatible con MariaDB.
- no se borran datos, no se truncan tablas, no se recrean tablas, no se tocan UUIDs, frontend, endpoints ni modelos.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/activity-log-id-repair-phase46.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- pendiente: correr nuevamente `update_install.php` y confirmar `Update 2026-06-global-auto-increment-integrity-repair-phase-45 completed.` seguido de phase46; luego repetir la consulta general de `information_schema.COLUMNS`.

## SISA API - saneamiento IDs no positivos phase44

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php` en Hostinger/MySQL

- problema real observado: `financial-auto-increment-repair-phase44.php` fallaba en `receipts.id` al convertir a `AUTO_INCREMENT` porque MariaDB intentaba resecuenciar `id=0` y chocaba con `id=13` (`Duplicate entry '13' for key 'PRIMARY'`).
- phase44 ahora detecta valores `<= 0` antes del `ALTER ... AUTO_INCREMENT` y los reasigna a IDs positivos nuevos calculados como `MAX(columna)+1`.
- para `receipts.id`, antes de actualizar la fila padre, actualiza referencias existentes en `receipt_items.receipt_id`, `invoice_receipt_payments.receipt_id`, `receipt_payments.receipt_id` y `receipt_instruments.receipt_id`; tablas/columnas ausentes se ignoran con log visible.
- para las demas tablas reparadas por phase44, si aparecen `id/history_id <= 0`, se reasigna solo la columna tecnica de esa tabla.
- se agregaron mensajes visibles con `oldId`, `newId` y cantidad de filas actualizadas por tabla para auditar la reparacion.
- no se borran datos, no se truncan tablas, no se recrean tablas, no se tocan frontend, endpoints ni modelos.
- validacion: `php -l scripts/migrations/financial-auto-increment-repair-phase44.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- pendiente: correr nuevamente `update_install.php` y confirmar que `receipts.id` no conserva `0`, que `SHOW COLUMNS FROM receipts LIKE 'id'` muestra `PRI`/`auto_increment`, y que las tablas hijas existentes no conservan `receipt_id=0`.

## SISA API - compatibilidad MariaDB SHOW INDEX phase44/45/46

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php` en Hostinger/MySQL

- problema real observado: phase44 fallaba en `Checking accounting_accounts.id...` con `SQLSTATE[42000]: Syntax error or access violation: 1064 near 'ORDER BY Seq_in_index'` porque MariaDB no acepta `ORDER BY` en `SHOW INDEX`.
- se reemplazo el uso de `SHOW INDEX FROM ... ORDER BY Seq_in_index` por consultas a `information_schema.STATISTICS` con prepared statements en `financial-auto-increment-repair-phase44.php`.
- se aplico la misma correccion preventiva en `global-auto-increment-integrity-repair-phase45.php` y `activity-log-id-repair-phase46.php`, que tenian el mismo patron invalido.
- no se cambio la lista de tablas reparadas, ni frontend, modelos, endpoints ni payloads.
- se mantienen los echoes de diagnostico de phase44 (`Checking`, `Repairing`, `Done`, `Error repairing`).
- validacion: `php -l scripts/migrations/financial-auto-increment-repair-phase44.php` en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/activity-log-id-repair-phase46.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- pendiente: correr nuevamente `update_install.php` y confirmar que phase44 avanza despues de `Checking accounting_accounts.id...` sin error SQL 1064.

## SISA API - observabilidad phase44 financiera

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente rerun de `update_install.php` en Hostinger/MySQL

- objetivo: hacer observable `financial-auto-increment-repair-phase44.php` porque `update_install.php` quedaba o moria en `Applying 2026-06-financial-auto-increment-repair-phase-44...` sin indicar tabla exacta.
- la migracion phase44 ahora imprime por cada columna: `Checking tabla.columna...`, skips de tabla/columna ausente, `Already AUTO_INCREMENT`, `Repairing tabla.columna...`, `Done tabla.columna` y error exacto si falla.
- cada reparacion individual esta envuelta en `try/catch`; si falla, imprime tabla, columna y mensaje, registra `error_log` con tipo/key/extra/causa y relanza `RuntimeException` con contexto.
- se preserva `COLUMN_TYPE` real via `SHOW COLUMNS`, se agrega primary key solo cuando no existe ninguna, se aborta si hay otra primary key distinta y no se tocan datos, frontend, endpoints ni otras fases.
- se ajusto la validacion de nulos para usar `COALESCE(SUM(columna IS NULL), 0)` y tratar tablas vacias como cero nulos.
- validacion: `php -l scripts/migrations/financial-auto-increment-repair-phase44.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- pendiente: correr nuevamente `update_install.php` y observar en pantalla la ultima linea `Checking/Repairing/Done/Error` para identificar la tabla exacta si vuelve a fallar.

## SISA API - reparacion activity_log.id phase46

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente corrida en base MySQL test/staging y verificacion SQL

- objetivo: sanear `activity_log.id` cuando una base vieja tiene duplicados, preservando todas las filas y regenerando solo el ID tecnico.
- se agrego la migracion permanente `scripts/migrations/activity-log-id-repair-phase46.php` con `ensureActivityLogIdRepairPhaseFortySix(PDO $pdo): void`.
- la migracion ignora `activity_log` o `id` ausentes, verifica primero foreign keys entrantes contra `activity_log.id` en `information_schema.KEY_COLUMN_USAGE` y aborta con tablas/columnas referenciantes si existen.
- si no hay FKs, la reparacion agrega `new_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST`, elimina una primary key vieja solo si era exactamente `id`, elimina la columna vieja `id`, renombra `new_id` a `id` y ajusta `AUTO_INCREMENT = MAX(id)+1`.
- la migracion no trunca, no borra logs, no recrea `activity_log`, no toca otras columnas, frontend, endpoints ni modelos.
- la migracion contempla reparacion parcial con `new_id`: la completa si `new_id` es `PRIMARY KEY AUTO_INCREMENT` y aborta con mensaje claro si no es seguro.
- se registro la migracion al final de `install.php` y `update_install.php` con label `2026-06-activity-log-id-repair-phase-46`.
- se ajusto `global-auto-increment-integrity-repair-phase45.php` para omitir `activity_log.id` y delegar ese caso especial a phase46, evitando que phase45 aborte antes por duplicados.
- validacion: `php -l scripts/migrations/activity-log-id-repair-phase46.php` en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- pendiente: ejecutar `update_install.php`, confirmar `SHOW COLUMNS FROM activity_log LIKE 'id'` con `COLUMN_KEY=PRI` y `EXTRA=auto_increment`, y validar duplicados/nulos en cero.

## SISA API - reparacion global AUTO_INCREMENT phase45

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente corrida en base MySQL test/staging y verificacion SQL

- objetivo: corregir instalaciones viejas donde columnas tecnicas `id` o `history_id` numericas quedaron sin `AUTO_INCREMENT`, provocando `lastInsertId() = 0`, duplicados en clave primaria y fallas como `Unable to create receipt from invoice`.
- se agrego la migracion permanente `scripts/migrations/global-auto-increment-integrity-repair-phase45.php` con `ensureGlobalAutoIncrementIntegrityRepairPhaseFortyFive(PDO $pdo): void`.
- la migracion revisa solo el listado global solicitado de tablas core, empresas/clientes/contactos, categorias, agenda, contabilidad/cajas, facturas, recibos/cobros, pagos, transferencias/bancos/sucursales.
- la migracion ignora tablas/columnas ausentes, lee definicion con `SHOW COLUMNS FROM tabla LIKE columna`, preserva el tipo real `INT`/`BIGINT` incluyendo `UNSIGNED`, valida nulos y duplicados, agrega primary key solo si no existe ninguna, y no repara a ciegas si existe otra primary key distinta.
- si la columna ya tiene `auto_increment`, solo ajusta `AUTO_INCREMENT = MAX(columna)+1`; si no lo tiene, aplica `ALTER TABLE ... MODIFY COLUMN ... NOT NULL AUTO_INCREMENT` y luego ajusta el proximo valor.
- no se tocaron `jobs_archive_checks.company_id`, `notification_user_states.notification_id` ni `notification_user_states.user_id`; tampoco frontend, endpoints, payloads, UUIDs ni datos.
- los fallos de reparacion registran en `error_log` tabla, columna, tipo, `column_key`, `extra` y causa antes de lanzar `RuntimeException`.
- se registro la migracion al final de `install.php` y `update_install.php` con label `2026-06-global-auto-increment-integrity-repair-phase-45`.
- `Appointments::create()` y `Clients::create()` ahora registran modelo/tabla, columnas insertadas, SQL con placeholders, `errorInfo()` y causa cuando falla el insert o `lastInsertId()` vuelve vacio; `Receipts`, `Payments` y `CashBoxes` ya estaban cubiertos, y `Transfers` usa `BaseModel::create()`.
- validacion: `php -l scripts/migrations/global-auto-increment-integrity-repair-phase45.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion adicional: `php -l src/Models/Appointments.php` y `php -l src/Models/Clients.php` en `sisa.api` -> PASS.
- pendiente: ejecutar `update_install.php` contra MySQL, correr la consulta de `information_schema.COLUMNS` indicada y confirmar que queda vacia o solo con tablas deliberadamente no autogeneradas.

## SISA API - reparacion AUTO_INCREMENT financiera phase44

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente corrida en base MySQL test/staging y pruebas funcionales

- objetivo: corregir instalaciones viejas donde tablas financieras de recibos, pagos, transferencias, cajas, sucursales y cierres contables quedaron con claves numericas sin `AUTO_INCREMENT`, rompiendo altas como `POST /invoices/{invoiceId}/receipts`.
- se agrego la migracion permanente `scripts/migrations/financial-auto-increment-repair-phase44.php` con `ensureFinancialAutoIncrementRepairPhaseFortyFour(PDO $pdo): void`.
- la migracion ignora tablas/columnas ausentes, lee definicion con `SHOW COLUMNS FROM tabla LIKE columna`, valida tipo `INT`/`BIGINT` real, nulos, duplicados y primary key antes de modificar, y ajusta `AUTO_INCREMENT = MAX(columna)+1` tambien cuando la columna ya tenia `auto_increment`.
- si existe otra primary key distinta, la migracion no repara a ciegas y lanza `RuntimeException` con tabla, columna y clave primaria existente; los fallos registran tabla, columna, tipo, key, extra y causa en `error_log`.
- tablas cubiertas: `accounting_accounts.id`, `accounting_closings.id`, `accounting_closings_history.history_id`, `cash_boxes.id`, `cash_boxes_history.history_id`, `invoice_receipt_payments_history.history_id`, `payments.id`, `payments_history.history_id`, `payment_templates.id`, `payment_templates_history.history_id`, `receipts.id`, `receipts_history.history_id`, `sucursales.id`, `sucursales_history.history_id` y `transfers.id`.
- no se tocaron tablas de union/relacionales compuestas como `jobs_archive_checks.company_id` ni `notification_user_states.*`, ni frontend, endpoints o payloads.
- se registro la migracion al final de `install.php` y `update_install.php` con label `2026-06-financial-auto-increment-repair-phase-44`.
- `Receipts::create()`, `Payments::create()` y `CashBoxes::create()` ahora registran internamente tabla/modelo, columnas insertadas, SQL con placeholders, `errorInfo()` y causa cuando falla el insert o `lastInsertId()` vuelve vacio; `Transfers` usa `BaseModel::create()`, que ya tenia ese logging.
- validacion: `php -l scripts/migrations/financial-auto-increment-repair-phase44.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Models/Receipts.php` en `sisa.api` -> PASS.
- validacion adicional: `php -l src/Models/Payments.php` y `php -l src/Models/CashBoxes.php` en `sisa.api` -> PASS.
- pendiente: ejecutar `update_install.php` contra MySQL, confirmar `information_schema.COLUMNS.EXTRA` con `auto_increment` para columnas existentes y probar crear recibo desde factura, pago, transferencia, caja y cierre contable si la app lo permite.

## SISA API - reparacion AUTO_INCREMENT contable de facturacion

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente corrida en base MySQL productiva/staging

- objetivo: corregir instalaciones viejas donde `accounting_entries.id` y `accounting_entry_lines.id` quedaron sin `AUTO_INCREMENT`, rompiendo la generacion del asiento contable posterior a la factura.
- se agrego la migracion permanente `scripts/migrations/accounting-auto-increment-repair-phase42.php` con `ensureAccountingAutoIncrementRepairPhaseFortyTwo(PDO $pdo): void`.
- la migracion verifica existencia de tabla y columna, lee `SHOW COLUMNS`, valida tipo entero, nulos, duplicados e indice compatible antes de reparar, aplica `MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT` y ajusta `AUTO_INCREMENT = MAX(id)+1` o `1` si la tabla esta vacia.
- tablas cubiertas: `accounting_entries.id` y `accounting_entry_lines.id`.
- se registro la migracion en `install.php` y `update_install.php` al final de las migraciones actuales, despues de las migraciones contables y de las reparaciones phase41 de facturas.
- no se tocaron frontend, `InvoicesController`, modelos ni servicios contables.
- validacion: `php -l scripts/migrations/accounting-auto-increment-repair-phase42.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- validacion: `git diff --check` en raiz -> PASS.
- pendiente: ejecutar `update_install.php` contra MySQL y confirmar `SHOW COLUMNS ...` e `information_schema.COLUMNS` con `Extra=auto_increment` antes de probar alta real de factura con asiento contable.

## SISA API - reparacion AUTO_INCREMENT en facturas

Estado: implementado en `sisa.api` con validacion de sintaxis; pendiente corrida en base MySQL productiva/staging

- objetivo: corregir instalaciones viejas donde `invoices.id` y claves tecnicas relacionadas quedaron sin `AUTO_INCREMENT`, provocando `Error creating invoice` al crear facturas.
- se agrego la migracion permanente `scripts/migrations/invoices-auto-increment-repair-phase41.php` con `ensureInvoicesAutoIncrementRepairPhaseFortyOne(PDO $pdo): void`.
- se agrego ademas la migracion dedicada `scripts/migrations/invoice-items-auto-increment-repair-phase41.php` con `ensureInvoiceItemsAutoIncrementRepairPhaseFortyOne(PDO $pdo): void` para dejar explicita la reparacion de `invoice_items.id`.
- la migracion verifica existencia de tabla y columna, lee `SHOW COLUMNS`, valida nulos/duplicados antes de reparar, agrega clave primaria o indice compatible si falta, aplica `MODIFY COLUMN ... NOT NULL AUTO_INCREMENT` y ajusta `AUTO_INCREMENT = MAX(id)+1`.
- tablas cubiertas: `invoices.id`, `invoice_items.id`, `invoices_history.history_id`, `invoice_items_history.history_id` e `invoice_receipt_payments.id`.
- se registro la migracion en `install.php` y `update_install.php` al final de las migraciones actuales, despues de las fases de facturas existentes.
- `BaseModel::create()` ahora registra internamente tabla, columnas, `errorInfo()`, SQL y causa cuando falla el insert o `lastInsertId()` vuelve vacio inesperadamente; no cambia la respuesta al cliente ni la logica de facturacion.
- validacion: `php -l scripts/migrations/invoices-auto-increment-repair-phase41.php` en `sisa.api` -> PASS.
- validacion: `php -l scripts/migrations/invoice-items-auto-increment-repair-phase41.php` en `sisa.api` -> PASS.
- validacion: `php -l src/Models/BaseModel.php` en `sisa.api` -> PASS.
- validacion: `php -l install.php` en `sisa.api` -> PASS.
- validacion: `php -l update_install.php` en `sisa.api` -> PASS.
- pendiente: ejecutar `update_install.php` contra MySQL y confirmar `SHOW COLUMNS ...` con `Extra=auto_increment` antes de probar alta real de factura.

## SISA Mobile/Web - Dashboard temporalmente deshabilitado

Estado: implementado en `sisa.ui` con validacion automatizada basica

- objetivo: aislar temporalmente el modulo Dashboard sin eliminar componentes ni archivos, para poder reactivarlo despues con una bandera central.
- se agrego `constants/featureFlags.ts` con `DASHBOARD_MODULE_ENABLED=false` como compuerta explicita del modulo.
- `Home` ya no monta `HomeDashboard` mientras la bandera esta apagada, evitando sus queries, metricas, graficos y cargas automaticas; el panel de citas de Home sigue operativo.
- `constants/menuSections.ts` marca la seccion `Analytics`/`Panel general` como `moduleId='dashboard'`, `disabled` y `hidden`, por lo que no aparece en el menu principal ni en submenus.
- las rutas manuales `/analytics` y `/accounting/summary` redirigen a `/Home` antes de disparar cargas de summary/analytics; se agrego `/dashboard` como alias protegido con redireccion a `/Home`.
- no se borraron componentes ni pantallas existentes; el codigo del dashboard queda aislado para reactivacion futura cambiando la bandera.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- punto ciego: falta verificacion runtime en dispositivo/web autenticado para confirmar ausencia de requests de dashboard en los primeros segundos post-login y redireccion manual de `/analytics`/`/dashboard`.

## SISA Mobile bootstrap - referencias sync v3 canonicas

Estado: implementado en `sisa.ui` con validacion automatizada; pendiente traza runtime en dispositivo

- objetivo: evitar doble descarga de referencias operativas durante el arranque, usando `/sync/v3/bootstrap/references` como fuente principal y dejando `/bootstrap?include=...` como fallback no obligatorio.
- `utils/startupBootstrap.ts` centraliza `STARTUP_REFERENCE_INCLUDE`, define colecciones requeridas y permite hidratar incrementalmente el payload `startup-bootstrap:{companyId}` desde referencias sync v3.
- `useBootstrapJobsFromApi` deriva su include de la constante compartida, mantiene la carga de caches existentes de jobs/referenceCache y ademas hidrata el payload que consumen `getStartupBootstrapPayload()` y los contextos de referencias.
- `BootstrapContext.requestStartupBootstrap()` primero valida cache local suficiente; si el payload viene de `sync_v3_references` devuelve `skipped` y no llama `/bootstrap`; si viene de cache local suficiente devuelve `cache`; solo hace fetch a `/bootstrap` como fallback.
- `runDeferredBootstrap()` ya no marca artificialmente una request de startup en vuelo antes de llamar al fallback, preservando dedupe real sin bloquear la evaluacion de cache.
- `usePullJobsSync` aplica `reference_refreshes` al mismo payload de startup de forma incremental para que arranques con checkpoint no fuercen una descarga completa si el cache ya es usable.
- diagnostico agregado: trazas para `sync_v3_references`, cache suficiente, fallback `/bootstrap`, request en vuelo y payload hidratado desde eventos sync v3, con duraciones de span.
- seguridad offline-first: las referencias sync v3 son best-effort dentro del bootstrap de jobs; un fallo de esa llamada no borra caches ni fuerza logout, y el shell sigue dependiendo del cache local/flujo diferido existente.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- punto ciego: falta capturar runtime real para confirmar conteos de requests en primer inicio limpio, inicio con checkpoint e inicio offline con cache local.

## Jobs - archivado operativo e historico incremental

Estado: ajuste robusto implementado en `sisa.api` y `sisa.ui`; validacion automatizada focalizada OK

- objetivo: sacar del sync activo operativo los jobs cerrados antiguos sin hard delete y cargarlos bajo demanda desde `/jobs/history` al final de la lista.
- backend: se agrego migracion `jobs-archive-visibility-phase40.php` con `archived_at`, `archived_reason`, `local_prunable_at`, `sync_visibility`, `jobs_archive_checks` e indices de visibilidad; tambien expande `sync_operations.action` para `job_archived`.
- backend: `Jobs::archiveEligibleJobs(companyId)` primero completa `local_prunable_at` para jobs cerrados (`completed_at + 30 dias`) y archiva cuando ese cursor vence, excluyendo sync local pendiente/conflicto si esas columnas existen y respetando `keep_local`/`pinned` si existen.
- correccion posterior: la elegibilidad vuelve a exigir `completed_at`; no se usa fallback `updated_at/created_at` para evitar archivado prematuro de jobs sin cierre operativo confirmado.
- correccion posterior: la deteccion de estados finales/cerrados usa solo `statuses.status_attribute` como fuente funcional; no infiere desde texto visible (`code/name/label`) ni desde labels legacy. Se aceptan atributos finales canonicos/legados: `completed`, `invoiced`, `paid`, `cancelled`, `finalizado`, `facturado`, `pagado`, `cancelado` y variantes femeninas.
- correccion posterior: los jobs finales que lleguen o existan sin `completed_at` se reparan con la mejor fecha disponible del job (`started_at`, `scheduled_end_at`, `scheduled_start_at`, `updated_at`, `created_at`), dejando `updated_at` como fallback operativo antes de `created_at`.
- backend: `updateJob`, `changeStatus` y push sync de jobs completan automaticamente `completed_at` cuando el nuevo `status_attribute` es final y el job no tenia fecha; al volver a atributo no final se limpian flags de pruning/visibilidad solo si el job aun no fue archivado.
- backend: `GET /jobs`, `/sync/v3/pull`, `/sync/v3/state`, `/sync/v3/events` y `/sync/v3/bootstrap/jobs` ejecutan el chequeo de archivado cuando hay `company_id` explicito o scope univoco; con multiples empresas sin filtro no cruza datos.
- backend: se agregaron metricas livianas de archivado (`evaluated`, `eligible`, `archived`, `skipped_missing_completed_at`, `skipped_missing_company_id`) y logs solo cuando hay archivados o faltan `completed_at`.
- backend: `GET /jobs`, `/sync/v3/bootstrap/jobs`, `/sync/v3/events` y `/sync/v3/status` corren safety archive con throttle por `jobs_archive_checks.company_id`; `GET /jobs` reporta metricas livianas de activos, archivados excluidos y tiempo de entrada.
- backend: los cambios directos de job (`PUT /jobs`, `/jobs/{id}/status`), push sync de jobs, facturacion de jobs desde invoices y liberacion por anulacion/eliminacion de factura disparan archivado no throttled para la company afectada.
- backend: se agrego tarea CLI `php scripts/jobs-archive-eligible.php [--company_id=ID]` para cron periodico del servidor; emite `job_archived` por cada archivado.
- backend: se agrego script one-off `php scripts/migraciones-unicas/2026-06-backfill-completed-at-for-archivable-jobs.php --company_id=ID [--apply]` para rellenar `completed_at = updated_at` en jobs finales historicos que aun no pueden entrar al flujo normal de archivado.
- backend: nuevo `GET /jobs/history?company_id=...&client_id=...&before=...&limit=20` devuelve historico paginado mas nuevo primero con cursor estable.
- sync: al archivar se emite evento `job_archived`; el cliente lo maneja removiendo el job del SQLite/cache activo sin reinsertarlo como job activo.
- sync: `/sync/v3/events` y `/sync/v3/bootstrap/jobs` ahora devuelven `archived_job_uuids` para limpiar filas activas locales que quedaron viejas cuando el evento `job_archived` ya habia pasado o el checkpoint/bootstrap lo salteo.
- mobile: `removeFromActiveCache` conserva el `DELETE FROM jobs` local protegido por `sync_state NOT IN ('pending','syncing','conflict')`; se documento que es solo eliminacion del cache activo local, no hard delete del servidor ni del historico.
- mobile: pull y bootstrap procesan `archived_job_uuids` y ejecutan `removeFromActiveCache` para eliminar esas filas del SQLite activo del telefono, manteniendo snapshots con `sync_visibility='archived'`.
- mobile: bootstrap/pull/backfill local completan `jobs.completed_at` en SQLite para jobs con `status_attribute` final y fecha vacia, usando el mismo orden de fallback; no toca jobs `pending`, `syncing` ni `conflict`.
- mobile: `/jobs` mantiene la lista activa desde cache/memoria y al llegar al final pide `/jobs/history`, agrega los resultados abajo como `historical=true` y muestra footer inline `loading`, `noMore` o `error` con `Reintentar`.
- mobile: los historicos bajo demanda quedan solo en memoria de la pantalla y no se mezclan con `jobsRepository` activo; al intentar abrir/accionar se advierte que es historico y no operativo.
- puntos ciegos actuales: la automatizacion focalizada cubre modelo/SQLite y lint; falta corrida completa de PHPUnit y verificacion runtime real del evento `job_archived` en dispositivo.
- validacion: `vendor/bin/phpunit tests/Models/JobsArchiveTest.php` en `sisa.api` -> PASS (3 tests, 17 assertions).
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion parcial: `vendor/bin/phpunit` en `sisa.api` emitio la linea baseline `Error de conexion: SQLSTATE[HY000] [2002]...` y no entrego resumen util en esta corrida; queda como deuda de setup ya registrada.
- metricas runtime pendientes: cantidad de `GET /jobs` en primeros 10s, cantidad de `GET /jobs/history` al scrollear, confirmacion de que historico no carga antes del final y ausencia de error global.

## SISA Mobile /jobs - cache-first y dedupe de arranque

Estado: implementado en `sisa.ui` con validacion automatizada; pendiente traza runtime en dispositivo

- objetivo: abrir `/jobs` con datos cacheados/memoria y refrescar en background sin bloquear el primer render de la lista.
- `app/jobs/index.tsx` ahora usa defaults sincronicos para filtros (`showBilledJobs=false`, `selectedSort=updatedAt`, `sortDirection=desc`) y lee/persiste esos valores despues del primer render, evitando que misses de cache de filtros bloqueen la vista.
- la lista de Jobs puede seedearse desde `JobsContext`/cache legacy mientras `useJobsList` termina de leer SQLite; si hay cache, se muestra inmediatamente con nombres de cliente fallback (`Cliente #id`) hasta que `ClientsContext` refresque.
- se agrego indicador discreto `Actualizando...` cuando hay datos visibles y un refresh local sigue corriendo; si no hay cache, se mantiene estado de carga normal.
- se agregaron eventos livianos de StartupTrace: `jobs.screen.mount`, `jobs.filters.defaults.ready`, `jobs.firstCachedRender`, `jobs.refresh.start`, `jobs.refresh.finish` y `jobs.api.deduped`.
- `JobsContext` mantiene TTL/in-flight por provider y ahora emite dedupe/refresh trace para evitar tormenta de `GET /jobs?company_id=...` al navegar.
- `ClientsContext` deduplica requests concurrentes de carga normal de clientes y conserva TTL existente, reduciendo duplicados de `GET /clients?company_id=...`.
- `JobPrioritiesContext` agrega in-flight y TTL corto de 60s; la pantalla puede pintar con prioridades default/cacheadas y refrescar despues.
- `CategoriesContext` agrega in-flight/TTL y evita refresh/ensure defaults mientras el pathname esta en `/jobs`, para que categories no compita con la apertura de la lista.
- `_layout.tsx` retrasa utilidades post-ready y warmup de DB unos segundos si el pathname esta en `/jobs`; la hidratacion contable por idle post-shell pasa a 30s en `/jobs` y sigue siendo inmediata al entrar a ruta contable.
- no se tocaron backend, rutas, permisos/companies/memberCompanies, default exports ni se eliminaron providers.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion: `git diff --check` en raiz -> PASS.
- runtime pendiente: falta capturar traza en dispositivo para medir tiempo pathname `/jobs` -> primera lista visible, cache vs skeleton, conteos `GET /jobs` y `GET /clients` en los primeros 10s, orden de categories, diferimiento contable, llegada a Home y `/jobs`, y ausencia de `missing default export`/`ReferenceError`.

## SISA Mobile bootstrap - contabilidad diferida

Estado: implementado en `sisa.ui` con validacion automatizada; pendiente traza runtime en dispositivo

- objetivo: priorizar carga operativa de jobs/clientes/proveedores/referencias y diferir datos contables pesados sin tocar backend ni rutas.
- se agrego `utils/accountingDeferred.ts` como compuerta liviana con `isAccountingDeferred`, `isAccountingReady`, `hydrateAccounting` y deteccion de rutas contables.
- `_layout.tsx` hidrata contabilidad solo al entrar a rutas contables (`/accounting`, `/accounts`, `/invoices`, `/receipts`, `/payments`, `/quotes`, `/reports`, `/cash_boxes`, `/closings`, `/transfers`) o por idle delay post `shell.usable`.
- `useCachedState` y `primeMemoryCacheFromStorage` omiten caches contables mientras la compuerta esta diferida, evitando leer al inicio `accounts`, `invoices`, `payments`, `receipts`, `quotes`, `reports`, `cash_boxes`, `closings` y caches relacionadas.
- los providers contables permanecen montados para no romper hooks/default exports, pero `CashBoxesContext`, `PaymentsContext`, `ReceiptsContext`, `InvoicesContext` y `QuotesContext` no hacen cargas remotas antes de hidratar contabilidad.
- `Home` y detalle de Job ya no llaman `loadInvoices()` para pintar la vista operativa; si hay informacion contable cacheada en memoria se puede mostrar, pero Jobs no fuerza la carga de facturas completas.
- el bootstrap inicial de jobs references deja de pedir `cash_boxes`, `payments`, `receipts`, `invoices` e items/links contables; mantiene referencias operativas como statuses, priorities, tariffs, providers, categories, products/services, clients, folders, users, permissions, memberships y member_companies.
- se agregaron guardas in-flight/TTL en cargas legacy de `JobsContext` y `ClientsContext` para reducir duplicados `GET /jobs` y `GET /clients` sin introducir arquitectura nueva.
- no se tocaron backend, rutas, permisos/companies/memberCompanies, datos falsos contables ni default exports.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion: `git diff --check` en `sisa.ui` -> PASS con warnings CRLF/LF del checkout.
- runtime pendiente: falta capturar nueva traza en dispositivo para reportar `shell.ready`, `shell.usable`, `preRenderMs`, `importToRenderMs`, `apiCalls` antes de shell, llegada a Home, Jobs antes de contabilidad, ausencia de cargas contables antes de rutas contables e hidratacion correcta al abrir ruta contable.

## Fix urgente - crash membershipsHydrated en BootstrapProvider

Estado: implementado en `sisa.ui` con validacion automatizada; runtime completo en dispositivo pendiente

- crash corregido: `BootstrapProvider` usaba `membershipsHydrated` sin declararlo en `contexts/BootstrapContext.tsx`.
- fix minimo: se extrae `membershipsHydrated` del `MemberCompaniesContext` junto con `loadMemberCompanies`, `memberships` y `memberCompanies`; no se cambio la logica de startup, rutas, backend, providers de dominio ni jobs gate.
- revision de duplicacion accidental: `_layout.tsx` mantiene una sola llamada a `primeMemoryCacheFromStorage()`, un solo `MemberCompaniesProvider`, un solo `PermissionsProvider`, un solo `BootstrapProvider`, un solo `ProvidersMountTrace` y un solo `RootLayoutContent`. `BootstrapContext.tsx` mantiene una sola declaracion de `BootstrapProvider` y un solo `BootstrapContext.Provider`.
- intento runtime: `npx expo start --web --non-interactive` no pudo continuar porque el puerto 8081 ya estaba ocupado y Expo pidio input; `npx expo start --web --port 8082` inicio Metro y compilo parcialmente hasta timeout de 45s, sin llegar a abrir `/Home` ni capturar metricas de StartupTrace desde esta sesion.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion: `git diff --check` en `sisa.ui` -> PASS con warnings CRLF/LF del checkout.
- validacion: `git diff --check` en raiz -> PASS.

## SISA Mobile bootstrap - startup optimista seguro

Estado: implementado en `sisa.ui`; pendiente validacion runtime en dispositivo

- `BootstrapContext` ahora solo habilita Home optimista si ya existen token/sesion, perfil cacheado, `selectedCompanyId`, companies, memberCompanies/memberships, permisos no vacios para el scope activo y config cacheada.
- antes de decidir modo optimista/completo espera la hidratacion local de `selectedCompanyId`, memberships y permisos para no perder el segundo boot rapido por timing de effects.
- si falta cualquier pieza del snapshot local, se conserva el flujo completo actual: auth/login, bootstrap critico, companies/memberCompanies, permisos y recien despues Home.
- en startup optimista se marca el shell como listo desde cache, `onlineValidated=false`, se muestra un indicador de sesion local pendiente y se corre la validacion critica online en background.
- mientras `onlineValidated=false`, `PermissionsContext` filtra permisos sensibles (`add/create/update/delete/generate/upload/mark/set`) para bloquear acciones de mutacion sin ocultar lectura/Home cacheado.
- al validar online correctamente se vuelve a habilitar permisos sensibles; si token, empresa o permisos fallan, queda pendiente/no validado y se conserva la correccion existente por refresh/auth guard.
- device registration se mantiene diferido por efecto de `AuthContext` y no participa en la decision de empresa/permisos ni en el gate de Home cacheado.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion: `git diff --check` en `sisa.ui` -> PASS con warnings CRLF/LF baseline del checkout.
- validacion: `git diff --check` en raiz -> PASS.

## SISA Mobile bootstrap - imports post shell usable

Estado: implementado en `sisa.ui`; pendiente medicion runtime en dispositivo

- `app/_layout.tsx` ya no importa al top-level el runtime de push notifications/device; `ExpoPushTokenLogger` se movio a `components/ExpoPushTokenLogger.tsx` y se carga lazy despues de shell usable.
- `PostReadyUtilities` espera un render posterior al gate usable antes de montar runners automaticos y observadores no criticos; overlays de debug quedan solo en dev y post usable.
- micro ajuste posterior: `app/_layout.tsx` dejo de importar `database/Database` al top-level; el warmup preventivo de `getDatabase()` ahora carga el modulo via import dinamico solo desde el gate post shell usable, evitando arrastrar `expo-sqlite`/migraciones antes del primer render por el warmup.
- micro ajuste posterior revertido parcialmente: `OperationGuardModal` y `OperationGuardStatusIndicator` volvieron al import/render directo porque el lazy post shell empeoro la traza y genero bundles pesados post Home; `_layout.tsx` mantiene removido el helper debug de jobs para trazas de render no funcionales.
- `scripts/sync-smoke.js` ahora valida los handlers de sync hint en el layout y en el componente lazy de push, manteniendo la cobertura tras mover el codigo fuera del import inicial.
- no se tocaron backend, bootstrap critical/deferred, rutas, permisos/companies ni orden masivo de providers.

## SISA Mobile bootstrap - instrumentacion Etapa 1

Estado: implementado en `sisa.ui` sin cambios funcionales intencionales

- se agrego `utils/startupTrace.ts` con `startupId` por arranque, spans, eventos, registro de endpoints, cache reads/writes y resumen por consola; sanitiza URLs y no registra tokens, passwords, headers ni bodies.
- `STARTUP_TRACE_ENABLED` permite apagar la traza desde `config/Index.ts`.
- `utils/networkSniffer.ts` reporta a StartupTrace cada fetch/XHR con metodo, path sanitizado, status, duracion y tamano aproximado de respuesta.
- `utils/cache.ts` y `hooks/useCachedState.ts` registran lecturas HIT/MISS, escrituras, removals, priming de memoria e inicializacion desde cache en memoria.
- `AuthContext` mide `auth.autoLogin`, `auth.restoreSession`, `auth.login` y `auth.applySession`, incluyendo restauracion offline y reintentos sin exponer credenciales.
- `BootstrapContext` mide `bootstrap.total`, `bootstrap.critical`, cada `bootstrap.section.*`, lectura de checkpoint SQLite, `jobs.bootstrap`, `jobs.pullSync`, `bootstrap.startupReferences`, `shell.ready` y resumen de endpoints/cache al quedar usable.
- `PermissionsContext`, `CompaniesContext`, `MemberCompaniesContext` y `ConfigContext` agregan spans livianos para detectar providers que disparan fetch temprano o duplicado.
- `useBootstrapJobsFromApi` y `usePullJobsSync` agregan eventos/spans para in-flight compartido, bootstrap interno y checkpoint usado por pull sync.
- `app/_layout.tsx` mide inicializacion de app y emite `shell.usable` cuando el layout ya permite usar la shell autenticada.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.

## SISA Mobile bootstrap - Etapa 2A guards de cargas tempranas

Estado: implementado en `sisa.ui` con cambios minimos y sin redisenar bootstrap

- se agrego `utils/startupRemoteGate.ts` para marcar cuando la shell ya esta usable y permitir que providers globales distingan montaje temprano de carga explicita por pantalla.
- `app/_layout.tsx` marca la shell usable junto al evento `shell.usable` de StartupTrace.
- `QuotesContext` ahora deduplica requests por `company_id`, aplica TTL simple de 60s y no refresca remoto desde su auto-effect antes de shell usable; las pantallas siguen pudiendo llamar `loadQuotes(true)` explicitamente.
- se frenaron callbacks remotos tempranos de `subscribeToReferenceCacheUpdates` antes de shell usable en `CashBoxesContext`, `PaymentsContext`, `InvoicesContext`, `ReceiptsContext`, `ProductsServicesContext`, `PaymentTemplatesContext` y `CategoriesContext`.
- `CategoriesContext` ya no intenta asegurar categorias default durante startup antes de shell usable.
- `useCachedState` evita volver a leer AsyncStorage si la key ya fue precargada en memoria por `primeMemoryCacheFromStorage`, reduciendo lecturas repetidas como `selected-company-id`.
- `deviceUid` evita reescribir en cada `ensureDeviceUid()` los mirrors legacy `tracking-device-id` y `jobs-sync-device-id` si ya se espejaron en el runtime.
- `NetworkLogContext` debounced la persistencia de `networkLogs` y ya no reescribe el cache inmediatamente despues de hidratar logs persistidos.
- no se capturo una nueva traza runtime en dispositivo desde esta sesion; queda pendiente medir con StartupTrace en la app para comparar contra baseline (`shell.ready ~29289ms`, `shell.usable ~29675ms`, `apiCalls=25`, `cacheReads=136`, `cacheWrites=115`, `GET /quotes?company_id=45 count=12`).
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.

## SISA Mobile bootstrap - Etapa 2B gate critico y diferidos

Estado: implementado en `sisa.ui` con validacion automatizada; falta traza runtime en dispositivo

- `BootstrapContext` separa el tramo critico de sesion (`profile`, `config`, `companies`, `memberCompanies`, `selectedCompany`, `permissions`) del tramo diferido de jobs/referencias.
- `isReady` y `shell.ready` se emiten al terminar el tramo critico exitoso, sin esperar lectura de checkpoint SQLite, `jobsBootstrap`, `jobsCheckpoint`, `jobs.pullSync` ni `/bootstrap?include=...`.
- se agregaron estados publicos `isDeferredBootstrapping`, `deferredBootstrapError` y `lastDeferredBootstrapAt` para observar el trabajo posterior sin convertirlo en error fatal de arranque.
- los pasos diferidos usan `runSection(..., { fatal: false })`: sus fallas actualizan status/error diferido, pero no mandan a login ni bloquean Home.
- StartupTrace ahora delimita `bootstrap.deferred`, `bootstrap.deferred.jobsCheckpoint` y `bootstrap.deferred.startupReferences` para medir que el trabajo pesado ocurre despues de `shell.ready`.
- se premarca el request diferido de startup references como in-flight para evitar que el efecto post-ready dispare otro `/bootstrap?include=...` mientras jobs/checkpoint siguen corriendo.
- `refreshBootstrap({ forceBlocking: true })` mantiene el refresh dirigido por empresa y espera el tramo diferido cuando se invoca explicitamente con esa opcion.
- no se capturo una nueva traza runtime en dispositivo desde esta sesion; queda pendiente comprobar que antes de `shell.ready` ya no aparezcan `GET /sync/v3/events` ni `GET /bootstrap?company_id=...&include=...`, y comparar `shell.ready`/`shell.usable` contra el objetivo de 8s/9s.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.

## SISA Mobile bootstrap - Etapa 2C medicion layout y shell real

Estado: implementado en `sisa.ui` con validacion automatizada; falta nueva traza runtime en dispositivo

- la traza real post-2B mostro que jobs y startup references ya no entran antes de `shell.ready`, pero tambien mostro una brecha aparente entre `bootstrap.total durationMs=4130` y `shell.ready durationMs=15282`.
- se detecto que `shell.ready` estaba ubicado conceptualmente en el lugar equivocado: se emitia desde `BootstrapContext` al terminar el bootstrap critico, no desde el layout cuando la navegacion principal ya podia renderizar. Ese evento ahora se llama `bootstrap.critical.ready`.
- `shell.ready` se movio a `RootLayoutContent`, cuando ya hay usuario, `BootstrapContext.isReady=true`, la ruta no es login/root y se puede montar navegacion principal con bottom nav. `shell.usable` queda tambien emitido desde ese gate real de layout.
- `StartupTrace` ahora guarda tiempos de spans/eventos y agrega `summary.layout appInitializeMs=... authMs=... criticalBootstrapMs=... postCriticalToShellReadyMs=... shellReadyToUsableMs=... providersMountMs=... splashHideMs=...`.
- se agregaron eventos de layout/gates: `layout.render.start`, `layout.providers.mount.start/finish`, `rootLayoutContent.render.start`, `rootLayoutContent.authGate`, `rootLayoutContent.bootstrapGate`, `rootLayoutContent.navigation.ready`, `rootLayoutContent.home.route.ready`, `bottomNavigation.ready`, `splash.hide.start/finish` y `router.ready`.
- se agrego medicion de cambios de estado clave: `auth.isLoading`, `auth.user`, `bootstrap.isReady`, `bootstrap.isBootstrapping`, `bootstrap.isDeferredBootstrapping`, `permissions.hydrated`, `permissions.loading`, `permissions.count`, `companies.hydrated`, `selectedCompanyId.resolved` y `config.hydrated`.
- se redujo la duplicacion de `/companies/member`: `BootstrapContext` ahora crea una sola promesa de `loadMemberCompanies()` para la seccion critica `memberCompanies` y para resolver la empresa activa, evitando el segundo fetch secuencial que producia `count=2` antes de shell.
- se difirieron trabajos globales que podian competir con el primer render: `ExpoPushTokenLogger` espera `isReady` para inicializar device uid/listeners push, `PendingMediaSyncAutoRunner` espera `isReady` antes de listar/procesar cola de media, y `JobsSyncAutoRunner` no consulta la cola SQLite desde NetInfo antes de `isReady`.
- se mantiene fuera del gate de shell todo lo diferido de Etapa 2B: `jobs.pullSync`, checkpoint jobs, `/sync/v3/events`, `/bootstrap?include=...` y startup references.
- warning require cycle documentado: `PermissionsContext.tsx` importa `MemberCompaniesContext`, `MemberCompaniesContext.tsx` importa tipos/constantes desde `CompanyMembershipsContext.tsx`, y `CompanyMembershipsContext.tsx` importa `PermissionsContext`. El riesgo principal es inicializacion parcial de modulos y defaults de contexto si se agrega logica top-level; hoy no se cambio porque requiere extraer tipos/constantes de membership a un modulo sin providers. Propuesta minima posterior: mover `MEMBERSHIP_STATUSES` y `CompanyMembershipStatus` a `contexts/companyMembershipTypes.ts` para cortar `MemberCompaniesContext -> CompanyMembershipsContext`.
- no se capturo una nueva traza runtime en dispositivo desde esta sesion; queda pendiente comparar `summary.layout`, confirmar `GET /companies/member count<=1` antes de `shell.ready`, y medir si la brecha real esta antes del bootstrap critico, en montaje de providers o en navegacion/Home.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion: `git diff --check` en raiz y `sisa.ui` -> PASS.

## Fix urgente - Etapa 2C crash por instrumentacion de layout

Estado: implementado en `sisa.ui` con validacion automatizada; pendiente confirmacion runtime en dispositivo

- crash detectado: `ReferenceError: Property 'MemberCompaniesContext' doesn't exist` durante `RootLayoutContentComponent` en `app/_layout.tsx`.
- causa: la instrumentacion de Etapa 2C agrego `useContext(MemberCompaniesContext)` desde `_layout.tsx`, pero el archivo solo importaba `MemberCompaniesProvider`; el simbolo del contexto no estaba disponible en ese scope. Ademas esa lectura directa sumaba dependencia interna innecesaria al layout.
- fix aplicado: `_layout.tsx` ya no lee `MemberCompaniesContext`, `CompaniesContext`, `PermissionsContext`, `ConfigContext` ni `selected-company-id` para telemetria. La instrumentacion del layout queda limitada a Auth/Bootstrap y eventos seguros de gate/render.
- se reviso duplicacion obvia del arbol: `_layout.tsx` mantiene un solo `BootstrapProvider`, un solo `RootLayoutContent`, una sola llamada a `primeMemoryCacheFromStorage()` y un solo `ProvidersMountTrace`. La repeticion de `cache.memory.init` puede venir de multiples hooks `useCachedState` montados o de remount dev, pero no de un provider duplicado agregado por este fix.
- no se tocaron backend, navegacion, orden de providers, bootstrap critico/deferred ni logica de permisos/empresas.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion: `git diff --check` en raiz y `sisa.ui` -> PASS.
- runtime pendiente: no se ejecuto Expo/dispositivo desde esta sesion; queda por confirmar visualmente que la app llega a Home y emite `shell.ready`/`shell.usable` sin el `ReferenceError`.

## SISA Mobile bootstrap - Etapa 2D pre-provider gap

Estado: instrumentacion implementada en `sisa.ui`; pendiente nueva traza runtime para confirmar causa y medir reduccion real

- traza de entrada reporto `app.initialize durationMs=418` y `layout.providers.mount.start elapsedMs=10075`, pero esa comparacion mezclaba duracion de span con timestamp absoluto. No habia timestamp visible de `span.finish app.initialize`, por lo que el gap real entre finish de init y montaje de providers no estaba medido.
- no se encontro otro gate pre-provider en `_layout.tsx`: la unica condicion que impide montar providers es `appReady=false`, que se setea al terminar `primeMemoryCacheFromStorage()` + `Font.loadAsync()` + `SplashScreen.hideAsync()`.
- se agrego instrumentacion segura antes de providers: `layout.module.loaded`, `layout.render.start elapsedMs=...`, `layout.gate.check`, `layout.gate.wait`, `layout.gate.pass`, `layout.appReady.set`, `layout.return.loading` y `layout.return.providers`.
- se separo la medicion de init en `cache.load.start/finish`, `cache.primeMemory.appInit`, `fonts.load.start/finish`, `fonts.load` y `assets.load.start/finish`, sin cambiar el gate ni mover providers.
- `summary.layout` ahora calcula `preProviderGapMs` usando el timestamp real de `span.finish app.initialize` y `layout.providers.mount.start`, no la duracion de `app.initialize`.
- se mantiene sin tocar backend, navegacion, deferred bootstrap, jobs, orden de providers ni acceso a contextos internos desde `_layout.tsx`.
- hipotesis actual hasta capturar traza: el supuesto gap de ~9.6s probablemente era una lectura incorrecta de la traza o tiempo previo al primer render del layout/dev runtime, no necesariamente un bloqueo entre `setAppReady(true)` y providers. La nueva traza debe confirmar si `layout.render.start elapsedMs` ya llega alto, si cache/fonts consumen el tiempo, o si existe un bloqueo real tras `layout.appReady.set`.
- no se capturo una nueva traza runtime en dispositivo desde esta sesion; queda pendiente comparar `preProviderGapMs`, `layout.render.start elapsedMs`, `layout.appReady.set`, `layout.return.providers` y `summary.layout` contra baseline.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion: `git diff --check` en raiz y `sisa.ui` -> PASS.

## SISA Mobile bootstrap - Etapa 2E imports pre-render

Estado: implementado en `sisa.ui` con validacion automatizada; falta nueva traza runtime para medir impacto real

- auditoria de `_layout.tsx`: se clasificaron como criticos para primer render `networkSniffer`, config de push usada por listeners, Expo Router/Stack, React/RN base, SafeArea/Gesture, fonts/splash, providers globales criticos, `BootstrapProvider`, `AuthProvider`, `RootLayoutContent` y `BottomNavigationBar`.
- candidatos no criticos detectados antes del primer render: `JobsSyncAutoRunner` (hooks de sync/SQLite), `PendingMediaSyncAutoRunner` (cola media), `SyncErrorAlertObserver`/`useSyncStatus`, `LogOverlay`, `NetworkTrafficOverlay`. Estos no son necesarios para mostrar loading/auth gate ni para completar bootstrap critico.
- candidatos pesados que se dejaron igual por riesgo funcional: `TrackingProvider` y `AppUpdatesProvider` siguen en `DeferredFeatureProviders` con `enabled=false` hasta despues de bootstrap; diferir el provider completo podria remountar el arbol de navegacion al insertarlo. Queda como posible etapa posterior si la traza sigue mostrando alto costo de imports.
- side effects top-level relevantes: `networkSniffer` se mantiene top-level a proposito para instrumentar fetch/XHR antes de auth/bootstrap; `SplashScreen.preventAutoHideAsync()` se mantiene top-level por requerimiento de Expo; no se agregaron lecturas de cache/SQLite top-level nuevas.
- se difirieron imports no criticos con `React.lazy` y un `PostReadyUtilities` que no renderiza hasta `BootstrapContext.isReady=true` y ruta no login/root. Esto evita importar runners/overlays antes de la shell principal.
- `SyncErrorAlertObserver` se movio a `components/SyncErrorAlertObserver.tsx`, evitando que `_layout.tsx` importe `useSyncStatus` y sus dependencias de sync antes del primer render.
- se rompio el require cycle pequeño `PermissionsContext -> MemberCompaniesContext -> CompanyMembershipsContext -> PermissionsContext`: `MEMBERSHIP_STATUSES`, `CompanyMembershipStatus` y `MembershipStatusFilter` ahora viven en `contexts/companyMembershipTypes.ts`, por lo que `MemberCompaniesContext` ya no importa el provider completo de memberships.
- `summary.layout` ahora reporta `preRenderMs` e `importToRenderMs` ademas de `preProviderGapMs`, para medir el tramo `layout.module.loaded -> layout.render.start` y distinguir costo de imports/top-level vs primer render.
- no se tocaron backend, endpoints, navegacion funcional, bootstrap critico/deferred ni orden masivo de providers.
- no se capturo una nueva traza runtime en dispositivo desde esta sesion; queda pendiente comparar contra baseline `layout.module.loaded ~4862`, `layout.render.start ~8421`, `shell.usable ~16271`.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npm run check:cache` en `sisa.ui` -> PASS.
- validacion: `npm run check:sync-smoke` en `sisa.ui` -> PASS.
- validacion: `git diff --check` en raiz y `sisa.ui` -> PASS.

## sisa.ui tracking GPS - decision engine local

Estado: implementado con validacion parcial por deuda TypeScript baseline

- se agrego `src/tracking/decisionEngine.ts` para decidir si un punto GPS se acepta antes de encolarlo, calculando distancia Haversine, segundos transcurridos, velocidad computada, `movement_state` y flags de calidad.
- `enqueueLocationSamples` ahora compara contra el ultimo punto local aceptado del device, descarta puntos de precision muy baja recientes, throttling de jitter/parado repetido a 60s, conserva el primer punto estacionario despues de movimiento para no ocultar semaforos y mantiene `speed_mps` como velocidad cruda del proveedor.
- `gps_points_queue.state` guarda el estado local decidido (`stationary`, `moving`, `vehicle_like`, `low_quality`, `suspicious`, `unknown`) sin cambiar el esquema ni el payload base de sync.
- se ajustaron opciones de captura: standby balanceado cada ~300s/75m, moving high accuracy cada ~15s/15m y high precision BestForNavigation cada ~7s/7m; `disabled` corta el tracking en `startTrackingTask`/`restartTrackingTaskIfActive`.
- la telemetria local reconoce `vehicle_like` y muestra movimiento detectado cuando el provider reporta `0 m/s` pero el estado local indica desplazamiento.
- se agrego log dev corto `[tracking:gps]` con accepted/discarded/jitter/stationary.
- validacion: `npm run lint` en `sisa.ui` -> PASS.
- validacion: `npx eslint "src/tracking/location.ts" "src/tracking/decisionEngine.ts" "database/tracking.ts" "src/tracking/types.ts" "app/tracking/gps-config.tsx"` en `sisa.ui` -> PASS.
- validacion: `npx tsc --noEmit` en `sisa.ui` -> FAIL por deuda TypeScript preexistente en pantallas/contextos no relacionados; la salida no reporto errores en los archivos de tracking modificados.

## Tracking timeline - performance request/render

Estado: implementado en `sisa.api` y `sisa.web`

- `/tracking/timeline` dejo de recalcular metricas faltantes dentro del GET normal; ahora adjunta solo metricas existentes y conserva el recalculo en el endpoint/proceso dedicado.
- se agrego modo liviano (`lightweight=1`/`mode=summary`) con `max_points`, `returned_points_count`, `is_downsampled` y conteo real para limitar la muestra que dibuja web.
- `sisa.web` pide el timeline en modo liviano con maximo 1000 puntos, pausa auto-refresh si la pestana no esta visible, hay modal/calendario o hubo interaccion reciente, y evita `setTimeline` si la firma de la respuesta no cambio.
- mapa/grafico reducen marcadores visuales cuando hay muchos puntos, la tabla raw carga filas incrementalmente y se agrego instrumentacion `console.time`/stats para fetch, normalize, renderPoints, graphBuild y mapBuild.
- validacion: `vendor/bin/phpunit tests/Controllers/TrackingControllerTest.php --filter GetTimeline` en `sisa.api` -> PASS; mantiene ruido baseline de conexion DB ya documentado.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite.

## Tracking time blocks - modal actions y delete por teclado

Estado: implementado en `sisa.web`

- el modal de lapsos compacta las acciones de punto seleccionado con iconos/textos cortos y mueve `Agregar link` al mismo grupo de acciones.
- la botonera inferior queda separada visualmente, alineada y con accion primaria clara; eliminar queda destacado a la izquierda.
- al seleccionar un lapso fuera del formulario, `Suprimir/Delete` o `Backspace` dispara eliminacion con la confirmacion existente.
- la confirmacion de eliminacion ya no usa `window.confirm`; se muestra como modal web para no cerrar pantalla completa.
- la confirmacion de eliminacion usa overlay especifico por encima del formulario incluso dentro de pantalla completa.
- el filtro `Fecha` de Jornada queda alineado como `Usuario`, con la fecha y mes en una sola linea dentro del selector.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite.

## Tracking time blocks - anclas GPS y metricas calculadas

Estado: implementado en `sisa.web`

- si un lapso tiene `start_gps_point_id` o `end_gps_point_id`, el extremo queda anclado al punto GPS y se muestra con borde/handle azul en la barra.
- los extremos anclados no se pueden redimensionar y un bloque con cualquier ancla no se puede mover completo; para desbloquear hay que quitar la cruz del punto en el formulario.
- el formulario agrega boton `×` para quitar ancla de inicio/fin, y deshabilita la fecha/hora del extremo mientras este anclado.
- distancia, duracion, velocidad promedio, velocidad maxima y calidad se muestran como metricas calculadas no editables; las velocidades se muestran en km/h con indicadores visuales.
- al ajustar el extremo libre desde la barra se preservan los IDs/lat/lng de anclas existentes en el payload.
- despues de guardar, borrar o ajustar un lapso, `refresh()` fuerza reemplazo del timeline para evitar que la guardia anti-render deje datos visuales viejos.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite.

## Tracking time blocks - speedometer reutilizable

Estado: implementado en `sisa.web`

- se agrego componente reusable `Speedometer` en `src/components/speedometer.tsx`, con SVG parametrizable por velocidad, maximo, tamano y valor visible.
- el formulario de lapsos usa velocimetros compactos para velocidad promedio y maxima, convirtiendo m/s a km/h para lectura operativa.
- se agregaron estilos compartidos para el gauge y layout compacto dentro de las tarjetas calculadas.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite.

## Tracking time blocks - layout modal editar lapso

Estado: implementado en `sisa.web`

- el modal `Editar lapso` se reorganizo visualmente sin cambiar handlers, endpoints ni calculos existentes.
- header agrega chips de resumen para estado, modo, distancia y duracion usando los valores actuales del formulario.
- cuerpo desktop usa dos columnas: datos del lapso a la izquierda y metricas del lapso a la derecha.
- puntos inicio/fin quedan en selectores compactos con boton `x` alineado para limpiar ancla.
- notas y links quedan debajo del layout principal ocupando el ancho completo, y el footer conserva eliminar a la izquierda y cancelar/guardar a la derecha.
- en mobile el modal apila secciones en una columna.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite.

## Tracking time blocks - edicion web y drag timeline

Estado: implementado en `sisa.web`, sin cambios funcionales requeridos en API

Actualizacion visual/editor:

- el modal de lapsos ahora se renderiza dentro del contenedor fullscreen cuando el Timeline GPS esta en pantalla completa, para quedar por encima del mapa/grafico sin salir de esa vista
- se mejoro el aspecto del modal con superficie oscura, inputs integrados y foco visible
- el timeline agrega una barra superior tipo editor: muestra bloques existentes, puntos disponibles y permite seleccionar dos puntos consecutivos/no consecutivos para abrir un nuevo lapso precargado
- los bloques existentes en la barra se pueden arrastrar para ajustarlos con magnetismo al punto GPS mas cercano de inicio/fin y persistir el cambio
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Correccion de rango temporal:

- los inputs `datetime-local` de inicio/fin ahora conservan segundos (`step=1`) para no truncar dos puntos cercanos al mismo minuto
- al elegir `Punto inicio` o `Punto fin` desde el formulario se sincronizan automaticamente hora, lat/lng y metricas; esto evita enviar `ended_at <= started_at` aunque el selector visual muestre un punto final posterior
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Actualizacion editor de barra:

- la barra superior deja de mostrar puntos GPS derivados; ahora es una pista exclusiva de lapsos de tiempo
- crear un lapso se hace arrastrando sobre espacio libre de la pista; al soltar abre el formulario con inicio/fin precargados para ajuste fino
- los bloques existentes se mueven arrastrando el centro y se redimensionan con handles laterales, con magnetismo a marcas de 15 minutos y bordes de otros lapsos
- al mover/redimensionar desde la barra se persiste el nuevo rango sin asociarlo automaticamente a puntos GPS raw
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Unificacion timeline/lapsos:

- se elimino la barra superior separada de 24h; el editor de lapsos ahora vive dentro del timeline scrolleable/zoomeable de velocidad
- se quitaron las bandas SVG duplicadas de lapsos para dejar una sola representacion editable, alineada al mismo ancho del grafico y adaptable al zoom
- la pista de lapsos conserva crear por drag, mover por centro y redimensionar por bordes, sin mezclar puntos GPS como marcadores de la barra
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Correccion visual de edicion de lapsos:

- al mover o redimensionar un bloque ya no se renderiza una segunda barra temporal naranja; se actualiza visualmente el mismo bloque que se esta editando
- el magnetismo excluye los bordes del propio bloque activo para evitar que quede pegado a su posicion anterior durante move/resize
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Estabilizacion del gesto de lapsos:

- el editor de lapsos deja de depender de `mousemove/mouseup/mouseleave` locales del rail y ahora usa Pointer Events con listeners globales `window.pointermove/pointerup/pointercancel`
- el drag sigue activo aunque el puntero salga del rail, pase sobre el SVG o haya scroll horizontal
- el drag horizontal del timeline no inicia si el evento nace dentro del rail, y queda bloqueado mientras hay un gesto de lapso activo
- se mantiene una sola fuente de coordenadas: `clientX` contra `railTrackRef.getBoundingClientRect()` para calcular porcentaje y hora
- se agrego `touch-action: none` y `user-select: none` al track/bloques para evitar seleccion o drag nativo durante el gesto
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Unificacion de rango visual/final:

- se agrego `resolveRailDragRange(drag, clientPercent)` como unica funcion que resuelve create/move/resize-start/resize-end, snap, limites, duracion minima e ignorar el propio bloque activo
- `pointermove` guarda en `draftRailRange` exactamente el rango devuelto por esa funcion
- `pointerup` usa la misma funcion con el ultimo `clientX`/percent del puntero y envia ese mismo rango a create/move, eliminando la normalizacion secundaria que podia mover el bloque al soltar
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Correccion editor de barra:

- el calculo de posicion del mouse en la pista ahora usa una referencia fija al track, evitando que resize/move calcule coordenadas contra el contenedor equivocado y mande el bloque a 00:00
- un click simple sobre la pista ya no crea un lapso; se requiere arrastrar una distancia real para abrir el formulario
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Reparacion final del editor de lapsos:

- `TrackingVelocityTimeline` centraliza create/move/resize en `resolveRailDragRange(drag, clientPercent)` y convierte a fechas con `percentToDateRange`, evitando una normalizacion distinta al soltar
- se agregaron clamps contra vecinos (`getNeighborBounds`, `clampCreateRange`, `clampMoveRange`, `clampResizeStart`, `clampResizeEnd`) con snap de 15 minutos y bordes ajenos, ignorando el bloque activo
- move conserva duracion, resize-start solo mueve inicio, resize-end solo mueve fin, y create queda limitado al espacio libre de la pista para evitar solapes
- el pan horizontal del timeline migro de handlers mouse locales a Pointer Events con capture; mientras hay `railDrag`, el timeline queda en estado `rail-editing` y no compite con el gesto del rail
- la edicion manual sigue persistiendo `start_gps_point_id`/`end_gps_point_id = null` desde los flujos de create/move existentes
- `finishRailDrag` persiste el ultimo `draftRailRange` calculado durante `pointermove`; solo usa `resolveRailDragRange` como fallback defensivo si no existe draft
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Correccion de sesgo al soltar/mover lapsos:

- `draftRailRangeRef` guarda sincronicamente el ultimo rango resuelto, sin depender de que React haya renderizado `setDraftRailRange` antes del `pointerup`
- `pointerup` actualiza el draft con su `clientX` final usando la misma `resolveRailDragRange` antes de persistir, evitando que el bloque confirme un frame anterior hacia la izquierda
- `finishRailDrag` limpia el ref junto con el estado visual para que el siguiente gesto no herede un draft viejo
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

HUD de diagnostico de lapsos:

- durante create/move/resize se muestra un globito sobre la pista con accion, bloque, hora inicio/fin, duracion, estado/modo, delta de movimiento, rango porcentual y puntero
- el globito lee el mismo `draftRailRange` que se renderiza/persiste y no captura eventos (`pointer-events: none`), para diagnosticar preview vs commit sin interferir con el drag
- las horas del globito y tooltips del timeline usan `Intl.DateTimeFormat('es-AR', { timeZone })` cuando `/tracking/timeline` informa timezone, evitando diagnosticos en zona horaria del navegador
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Normalizacion temporal Argentina/UTC:

- se fijo `TRACKING_TIMEZONE = America/Argentina/Buenos_Aires` y se agregaron helpers explicitos para parsear fechas servidor como UTC, formatear tracking en `es-AR` con timezone, convertir server->`datetime-local` y `datetime-local`->UTC ISO
- `positionInDayPercent` y el rail calculan porcentajes contra las 00:00 de la fecha seleccionada en Argentina (`03:00Z`), por lo que `2026-05-29T03:00:00Z` cae en 0% del dia 29/05
- crear/mover/redimensionar convierte percent->instante servidor con la misma base Argentina, evitando mandar hora local como si fuera UTC
- modal, selects de puntos, tabla de bloques, resumen, mapa, tabla raw, gaps/anomalias, tooltips y globito usan el mismo criterio horario Argentina/UTC-3
- si el API devuelve strings sin `Z` ni offset, el frontend los interpreta como UTC para evitar depender de la zona horaria del navegador
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Revision final de normalizacion temporal:

- la constante quedo alineada como `TRACKING_TIME_ZONE = 'America/Argentina/Buenos_Aires'`
- el calculo de posicion usa `trackingDateTimeToPercent(value, date)` en puntos, bloques y drag inicial; ya no depende de un `dayStartFor(date)` local del navegador
- se retiraron los usos de `positionInDayPercent`/`dayStartFor` del timeline para evitar ambiguedad de timezone
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Correccion de strings servidor sin zona:

- se verifico por pantalla que el backend puede devolver `started_at`/`ended_at` sin `Z` ni offset y que esos strings representan hora de pared Argentina, no UTC
- `parseTrackingServerDateTime` ahora interpreta strings sin zona como `America/Argentina/Buenos_Aires` y los convierte internamente a instante UTC sumando 3 horas
- los ISO con `Z` u offset se siguen interpretando como instantes reales del servidor sin alterar
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Persistencia visual optimista al soltar:

- al mover/redimensionar un lapso, `TrackingTimelinePage` guarda un override local por `block.id` con `started_at`/`ended_at` apenas se suelta el bloque
- la barra, tabla y seleccion usan ese rango optimista hasta que `refresh()` trae datos nuevos del servidor, momento en el que se limpian los overrides
- si falla el update, se elimina el override del bloque y se muestra el error existente
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Correccion direccion de creacion:

- al crear un lapso arrastrando en la barra, el punto donde empieza el drag queda como ancla fija de inicio y el rango solo crece hacia la derecha
- si se arrastra hacia la izquierda del ancla no se invierte el lapso ni se crea un bloque hacia atras; se debe volver a la derecha para confirmar rango
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Actualizacion fullscreen:

- la barra de pantalla completa del Timeline GPS ahora tambien muestra `Nuevo lapso`, `Nuevo desde punto seleccionado`, `Editar lapso` y `Eliminar lapso`, reutilizando el mismo modal/flujo de guardado sin salir del modo fullscreen
- validacion: `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite

Que cambio:

- `trackingCatalogsService` agrega `createTrackingTimeBlock`, `updateTrackingTimeBlock` y `deleteTrackingTimeBlock`, limpiando `undefined` antes de enviar payloads
- `TrackingTimelinePage` agrega acciones reales para “Nuevo lapso”, “Nuevo desde punto seleccionado”, “Editar lapso” y “Eliminar lapso”
- se implementa `TrackingTimeBlockModal` con campos basicos, rango temporal, puntos GPS, metricas opcionales, notas y editor simple de links
- el modal permite usar el punto seleccionado como inicio o fin y recalcular metricas disponibles desde los puntos del rango sin inventar precision cuando no hay datos
- guardar/editar/eliminar refresca `/tracking/timeline`, mantiene fecha/usuario y selecciona el bloque guardado o limpia seleccion al borrar
- las bandas del grafico soportan doble click para editar, resaltado fuerte y etiqueta visible cuando el zoom/ancho lo permite
- `TrackingVelocityTimeline` agrega pan horizontal con mouse sobre el contenedor scrolleable, preservando click en puntos/bloques, zoom, teclado y scroll actual
- `TrackingTimeBlocksPanel` reemplaza el placeholder por boton real, agrega acciones por fila y doble click de fila mediante extension minima de `DataTable`

Riesgo cubierto:

- permite operar bloques interpretativos desde la pantalla GPS sin tocar puntos raw ni metricas tecnicas
- mejora la usabilidad del timeline con zoom alto sin cambiar el contrato de `/tracking/timeline`

Puntos ciegos conocidos:

- los links siguen usando `entity_id` numerico manual hasta incorporar buscadores reales por entidad
- para bloques `locked/billed` la UI bloquea campos operativos y deja notas/links; la regla final de negocio puede endurecerse en backend cuando se definan permisos especificos

Validacion:

- `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite; `dist/` se actualizo porque el repo web versiona artefactos build
- `php -l src/Controllers/TrackingController.php` en `sisa.api` -> PASS
- `php -l src/Models/TrackingTimeBlocks.php` en `sisa.api` -> PASS
- `php -l src/Models/TrackingTimeBlockLinks.php` en `sisa.api` -> PASS

## Tracking time blocks backend + visualizacion web

Estado: implementado base, sin etiquetado avanzado

Que cambio:

- `sisa.api` agrega modelos `TrackingTimeBlocks` y `TrackingTimeBlockLinks` con `ensureTable()` para crear `tracking_time_blocks` y `tracking_time_block_links`, indices operativos y soft delete
- se agregan endpoints base bajo `/tracking/time-blocks` para listar, crear, leer, actualizar y borrar logicamente bloques con links opcionales
- las validaciones cubren rango temporal, calculo de `duration_seconds`, enums de modo/estado/origen, existencia de usuario, existencia de puntos GPS vinculados y validacion de links
- `/tracking/timeline` conserva el payload existente y suma `time_blocks`, devolviendo `[]` cuando no hay bloques
- permisos: se dejo fallback temporal comentado a `listTrackingAssignments` porque los permisos dedicados de time blocks aun no estan sembrados
- `sisa.web` agrega tipos `TrackingTimeBlock`/`TrackingTimeBlockLink`, normaliza `time_blocks` como array seguro y muestra la seccion “Bloques de tiempo” en Timeline GPS
- el grafico 24h ahora dibuja bandas superiores por bloque, con color por `mode`, estilo por `status`, tooltip y seleccion visual sin mover al usuario a la tabla de puntos
- se agrega una lista colapsable “Bloques de tiempo detectados/manuales” y un boton deshabilitado de proxima etapa; no se implementa edicion/etiquetado avanzado

Riesgo cubierto:

- separa evidencia cruda (`gps_points`), calculo tecnico (`gps_point_metrics`) e interpretacion operativa (`tracking_time_blocks`) sin romper contratos existentes del timeline
- permite visualizar bloques operativos aunque el backend no tenga datos todavia, manteniendo compatibilidad con `time_blocks: []`

Puntos ciegos conocidos:

- los permisos dedicados `list/create/update/deleteTrackingTimeBlocks` deben sembrarse antes de retirar el fallback temporal a `listTrackingAssignments`
- PHPUnit focalizado no llego a emitir resultados porque el setup mantiene la deuda baseline de conexion DB `SQLSTATE[HY000] [2002]` ya documentada

Validacion:

- `php -l src/Models/TrackingTimeBlocks.php` en `sisa.api` -> PASS
- `php -l src/Models/TrackingTimeBlockLinks.php` en `sisa.api` -> PASS
- `php -l src/Controllers/TrackingController.php` en `sisa.api` -> PASS
- `php -l src/Routes/api.php` en `sisa.api` -> PASS
- `php -l tests/Controllers/TrackingControllerTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/TrackingControllerTest.php` en `sisa.api` -> BLOQUEADO por deuda baseline de conexion DB `SQLSTATE[HY000] [2002]`
- `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite; `dist/` se actualizo porque el repo web versiona artefactos build

## Replanteo tracking GPS mobile - perfiles y rechazados

Estado: implementado focalizado en `sisa.ui`

Que cambio:

- `database/tracking.ts` separa rechazo permanente de fallo temporal: los `rejected` del backend ahora quedan con `sync_status='rejected'`, `error_message`, `rejected_at` y `updated_at`
- `listRetryableTrackingPoints()` sigue tomando solo `pending` y `failed`; `rejected` y `discarded` no entran en reintentos automaticos
- se agregan acciones locales para listar rechazados, descartar uno, descartar varios/todos y reintentar manualmente un rechazado pasando a `pending`
- la migracion SQLite sube a version 4 con `rejected_at` y `ensureTrackingSequenceCounterSchema()` agrega la columna de forma idempotente para bases existentes
- `TrackingContext` agrega perfiles locales `trackingUploadProfile` y `trackingAcquisitionProfile`, contador/listado de rechazados y watchdog periodico de task GPS
- el perfil de envio `manual` bloquea la subida automatica pero no la captura; `wifi_only` acumula cola y solo sube en WiFi; la accion manual de sincronizacion fuerza el intento permitido por perfil
- `src/tracking/location.ts` aplica perfiles de adquisicion a `expo-location`, asegura schema antes de iniciar tracking y mantiene la tarea viva aunque falle un insert individual de SQLite
- `/tracking/gps-config` y `/tracking/queue` muestran perfiles, estados de cola, rechazados, motivo del servidor y acciones de eliminar/reintentar con confirmacion

Riesgo cubierto:

- evita loops infinitos de reintento para puntos rechazados por validacion de servidor
- permite operar offline/acumular cola aunque fallen internet, token, servidor o subida batch
- da al usuario una salida local para limpiar puntos que el servidor no aceptara

Puntos ciegos conocidos:

- no se agrego buffer alternativo fuera de SQLite porque no hay mecanismo seguro existente para persistir puntos GPS completos; se registra `lastError` y se reintenta reparar schema/conexion
- `automatic` de envio queda como comportamiento base balanceado con gating por red/bateria pendiente de refinamiento real; no bloquea captura
- los perfiles deben validarse en Android real por comportamiento especifico de `expo-location` en background

Validacion:

- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS
- `npm run check:sync-smoke` en `sisa.ui` -> PASS
- `npx tsc --noEmit` en `sisa.ui` -> FAIL por deuda TypeScript baseline existente en clients/jobs/invoices/receipts/contextos; luego de corregir los errores nuevos de `TrackingContext`, no quedan errores reportados en los archivos modificados de tracking

## Periodos de facturacion por empresa y Analytics web

Estado: implementado base funcional, con endpoints principales y UI web operativa

Que cambio:

- `sisa.api` agrega `company_accounting_settings` para `billing_period_close_day` por empresa, con modelo `CompanyAccountingSettings`, migracion phase 39 y endpoints `GET/PUT /company-accounting-settings`
- la escritura valida pertenencia y rol `owner` en `empresas_usuarios`; si no es dueño devuelve `Solo el dueño de la empresa puede modificar el período de facturación.`
- `BillingPeriodService` resuelve periodos puros ISO, maneja cierre 1..31 y ajusta cierre 31 al ultimo dia real del mes; devuelve `period_key`, `period_start`, `period_end`, `period_label` y `billing_period_close_day`
- `accounting_closings` queda preparado con `billing_period_key`, `billing_period_start`, `billing_period_end` y `billing_period_close_day`; al crear cierre guarda el periodo usado en ese momento para no reescribir historicos si cambia el cierre futuro
- `/analytics/worklogs` ahora usa `group_by=billing_period` por defecto y devuelve periodos completos de facturacion; `month`, `day`, `week` y `year` siguen disponibles como agrupaciones calendario
- se preserva la separacion critica: `worklog_clock_hours` cuenta duracion real una vez y `technician_hours` cuenta duracion por participante para costo empresa
- `sisa.web` agrega ruta `/analytics`, menu Analytics, helpers de fecha `DD/MM/AAAA` <-> `YYYY-MM-DD`, KPIs de horas, barras por periodo y rankings de usuarios/clientes/trabajos sin descargar listas completas
- `sisa.web` agrega configuracion contable de empresa en Settings y en Analytics: los no-owner ven el valor deshabilitado; owner puede guardar

Riesgo cubierto:

- evitar analisis por mes calendario cuando la empresa cierra por periodos reales de facturacion
- dejar cierres historicos con snapshot de `billing_period_close_day` usado al cerrar
- preparar costos y rentabilidad futura sobre `technician_hours`, no sobre horas reloj

Puntos ciegos conocidos:

- en este corte solo `/analytics/worklogs` queda implementado con metricas reales; los endpoints analiticos adicionales (`overview`, `jobs`, `finance`, `clients`, `users`, `profitability`) quedan como siguiente hito para no inventar calculos financieros incompletos
- no se ejecuto prueba HTTP real owner/no-owner por no tener credenciales/DB disponible en esta sesion

Validacion:

- `php -l src/Models/CompanyAccountingSettings.php` en `sisa.api` -> PASS
- `php -l src/Services/BillingPeriodService.php` en `sisa.api` -> PASS
- `php -l src/Controllers/CompanyAccountingSettingsController.php` en `sisa.api` -> PASS
- `php -l src/Controllers/AnalyticsController.php` en `sisa.api` -> PASS
- `php -l src/Models/AccountingClosings.php` en `sisa.api` -> PASS
- `php -l src/Controllers/AccountingClosingsController.php` en `sisa.api` -> PASS
- `php -l src/Routes/api.php` en `sisa.api` -> PASS
- `php -l scripts/migrations/company-accounting-settings-phase39.php` en `sisa.api` -> PASS
- `php -l update_install.php` en `sisa.api` -> PASS
- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS con warning baseline de chunk grande de Vite; `dist/` se actualizo porque el repo web versiona artefactos build

## Analytics mobile - fechas AR y panel operativo

Estado: implementado focalizado; ampliado con horas operativas de worklogs

Que cambio:

- `sisa.ui` agrega helpers reutilizables en `src/utils/dateFormat.ts` para mostrar fechas `DD/MM/AAAA`, fechas-hora `DD/MM/AAAA HH:mm` y normalizar rangos a ISO `YYYY-MM-DD` antes de llamar API
- `/analytics` reutiliza el endpoint agregado existente `/accounting/summary` y mantiene `/accounting/summary` operativo para enlaces internos previos
- la pantalla de analytics muestra inputs visibles en formato argentino, valida estrictamente el rango y envia `start_date/end_date` en ISO al servidor
- se modernizo el panel con hero, KPI cards, barras livianas por cajas/clientes/proveedores, empty state y skeleton sin agregar librerias pesadas ni calcular estadisticas desde listas completas
- el centro de reportes reutiliza los helpers para rangos visibles y para preparar fechas ISO al generar/reportar
- `sisa.api` agrega `GET /analytics/worklogs` con filtros `company_id`, `from`, `to`, `group_by`, `client_company_id`, `user_id`, `job_id` y `status_id`
- el endpoint calcula en SQL agregado horas reloj (`worklog_clock_hours`) separadas de horas tecnico (`technician_hours = duracion x participantes`), sin duplicar horas reloj por joins
- la agrupacion cubre empresa via summary, periodo, usuario desde `work_log_participants.user_id`, cliente desde `jobs -> clients -> empresas`, y trabajo
- si hay worklogs sin participantes, la respuesta informa warning y no inventa tecnico principal
- `/analytics` suma la seccion “Horas de worklogs” con KPIs, barras por periodo y rankings de usuarios, clientes y trabajos; Analytics queda como entrada principal fuera de Gestion financiera

Riesgo cubierto:

- evitar mezclar fechas visibles `YYYY-MM-DD` con UX local argentina y prevenir envios ambiguos a API
- conservar performance consumiendo un endpoint agregado existente en lugar de descargar worklogs, facturas, pagos o trabajos para sumar en frontend
- preservar la diferencia critica para costos: horas reloj miden duracion real y horas tecnico miden horas-hombre/costo empresa

Validacion:

- `php -l src/Controllers/AnalyticsController.php` en `sisa.api` -> PASS
- `php -l src/Routes/api.php` en `sisa.api` -> PASS
- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS
- `npx tsc --noEmit` en `sisa.ui` -> FAIL por deuda TypeScript baseline en clients/jobs/invoices/receipts/contextos/tracking ya documentada; no reporto errores nuevos en `app/accounting/summary.tsx`, `app/reports/index.tsx`, `app/analytics.tsx`, `app/Home.tsx`, `constants/menuSections.ts` ni `src/utils/dateFormat.ts`

## Retoque GPS - precision mala y timeline compacto

Estado: implementado focalizado

Que cambio:

- `GpsPointMetrics` endurece el calculo: `accuracy_m > 80` marca baja calidad, `accuracy_m > 120` invalida movimiento, y puntos previos con mala precision no disparan movimiento confiable
- la metrica suma `is_valid_for_movement`, `is_valid_for_route` e `ignored_reason`, con migracion defensiva por `ensureTable()` para bases existentes
- el ruido GPS ahora usa `max(25, previous_accuracy + current_accuracy, current_accuracy * 1.5)` y no se clasifica `moving` solo por distancia cuando algun punto tiene mala precision
- `/tracking/timeline` expone los nuevos campos derivados sin romper el payload anterior
- `sisa.web` dibuja la ruta valida con halo blanco + linea principal naranja, separa tramos dudosos con linea punteada y muestra leyenda
- la tabla de puntos raw + metricas queda compacta con columnas cortas, simbolos/chips y tooltips en espanol para estados, flags, fuente y algoritmo

Riesgo cubierto:

- evitar movimiento falso y lineas falsas cuando hay saltos grandes causados por mala precision GPS
- mantener visibles los puntos raw malos sin contaminarlos en movimiento ni recorrido valido

Validacion:

- `php -l src/Models/GpsPointMetrics.php` en `sisa.api` -> PASS
- `php -l src/Controllers/TrackingController.php` en `sisa.api` -> PASS
- `npm run build` en `sisa.web` -> PASS con warning existente de chunk grande de Vite

## Replanteo GPS audit/facturacion futura

Estado: implementado focalizado, sin stays/trips ni cobro

Que cambio:

- `sisa.api` agrega `gps_point_metrics` como tabla derivada/versionada separada de `gps_points`, con `algorithm_version`, velocidades provider/computed/effective, distancia, elapsed, estado de movimiento y `quality_flags`
- `/tracking/points/batch` conserva `gps_points` como raw e invoca calculo server-side para los puntos aceptados sin cambiar el contrato de ACK
- `/tracking/timeline` devuelve raw + metricas derivadas y calcula metricas faltantes con `gps_metrics_v1` para puntos legacy consultados
- se agrega `POST /tracking/metrics/recalculate` para recalcular por rango con filtros opcionales de usuario, device y empresa, sin borrar raw
- `sisa.ui` mantiene `expo-location` background, guarda `location.coords.speed` como raw (`provider_speed_mps` alias y `speed_mps` compat), y usa `state/local_tracking_hint` solo como hint operativo
- `sisa.ui` introduce perfiles adaptativos `standby`, `suspected_moving` y `high_precision_burst` con histeresis basica y reinicio de task limitado a una vez por minuto
- `sisa.web` muestra en timeline columnas raw vs calculadas: provider speed, computed/effective speed, distancia/elapsed, movement state, flags y algoritmo

Riesgo cubierto:

- si Android/Expo reporta `speed=0` pero hay desplazamiento real, el backend puede derivar `computed_speed_mps` por Haversine y marcar `provider_speed_zero_but_moved` sin sobrescribir el raw
- las metricas usadas por auditoria/reportes futuros quedan reproducibles y separadas de la evidencia capturada por el dispositivo

Puntos ciegos conocidos:

- `speed_mps` sigue existiendo en `gps_points` por contrato legacy, pero ahora se trata como velocidad raw del provider y se expone tambien como `provider_speed_mps`
- el cambio no implementa billing, stays, trips ni privacidad/retencion avanzada
- el comportamiento adaptativo debe validarse en Android real porque Expo puede variar como aplica cambios de `startLocationUpdatesAsync` en background

Validacion:

- `php -l src/Models/GpsPointMetrics.php` en `sisa.api` -> PASS
- `php -l src/Controllers/TrackingController.php` en `sisa.api` -> PASS
- `php -l src/Routes/api.php` en `sisa.api` -> PASS
- `php -l tests/Controllers/TrackingControllerTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/TrackingControllerTest.php` en `sisa.api` -> PASS, con ruido baseline de conexion `SQLSTATE[HY000] [2002]` ya documentado
- `npm run lint` en `sisa.ui` -> PASS
- `npm run build` en `sisa.web` -> PASS con warning existente de chunk grande de Vite

## Implementacion web - mapa read-only de tracking timeline

Estado: completado focalizado

Actualizacion de interaccion:

- la tabla de puntos raw ahora queda en un contenedor escroleable y permite seleccionar cualquier punto
- al seleccionar un punto, el mapa lo resalta con marcador/color diferencial y remarca el tramo hacia el punto anterior y el tramo hacia el punto siguiente
- el globo de posicion actual permanece en el ultimo punto del recorrido y usa `profile_file_id` del usuario cuando `/tracking/timeline` lo informa, con fallback a iniciales
- cambio backend minimo: `GpsPoints::listUsersWithPointsForRange()` suma `user_profile.profile_file_id` al payload de usuarios del timeline

Que cambio:

- `sisa.web` agrega Leaflet/React-Leaflet para renderizar `/tracking-timeline` con mapa del recorrido diario
- el mapa dibuja polyline con los puntos validos de `/tracking/timeline` y ubica un globo del usuario en el ultimo punto recibido
- la vista conserva selector de fecha y usuario, y refresca automaticamente cada 10 segundos ademas del boton manual
- no se tocaron app movil, backend, IA, stays/trips ni mapas nativos moviles

Validacion:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS con warning de chunk grande de Vite; `dist/` se actualizo porque el repo web versiona artefactos build
- `php -l src/Models/GpsPoints.php` en `sisa.api` -> PASS

## Reparacion P0 - secuencia local mobile de tracking

Estado: implementado focalizado, pendiente de validacion en dispositivo

Correccion urgente posterior:

- se reprodujo por reporte operativo que la app podia fallar en captura con `no such table: tracking_sequence_counters`
- causa exacta: el codigo ya consultaba `tracking_sequence_counters`, pero en telefonos con SQLite existente la migracion mobile podia no haber creado la tabla antes del primer uso de tracking
- `database/tracking.ts` agrega `ensureTrackingSequenceCounterSchema()` como defensa runtime idempotente antes de `getNextTrackingSequence()`, `reserveNextTrackingSequence()` y `enqueueTrackingPointAuto()`
- la defensa crea `gps_points_queue` si faltara, crea `tracking_sequence_counters`, verifica `point_uuid` con `PRAGMA table_info`, agrega la columna solo si falta, rellena `point_uuid` legacy y crea el indice unico
- `database/migrations.ts` mantiene la migracion v3 segura creando `tracking_sequence_counters` antes del seed y usando backfill legacy por `local_id`
- validacion: `npm run lint` en `sisa.ui` -> PASS; `npx tsc --noEmit` -> FAIL por deuda TypeScript preexistente ya registrada

Causa exacta:

- `sisa.ui/database/tracking.ts` calculaba `sequence_no` desde `MAX(sequence_no)` en `gps_points_queue`
- `gps_points_queue` se borra al confirmar ACK, por lo que la secuencia podia volver a `1/2/3` despues de sincronizar
- `sequence_no` ya tiene duplicados reales en backend y no puede usarse como identidad fuerte ni como ACK exacto

Que cambio:

- `tracking_sequence_counters` persiste `next_sequence_no` por `device_id` fuera de la cola borrable
- `reserveNextTrackingSequence()` reserva e incrementa el contador en transaccion SQLite y nunca depende de `gps_points_queue` salvo inicializacion legacy
- `point_uuid` pasa a ser identidad fuerte local/remota del punto
- `/tracking/points/batch` envia `batch_uuid` por lote y `point_uuid` por punto
- ACK y rejected usan `point_uuid` cuando existe; `sequence_no` queda solo como fallback legacy
- `saveTrackingSyncState()` no permite bajar `last_uploaded_sequence_no` ni persistir `last_server_point_id = 0`
- `qa/tracking-sequence-diagnostics.sql` agrega consultas para detectar duplicados y revisar progresion por device

Validacion manual requerida:

- capturar 5 puntos
- sincronizar
- capturar 5 mas
- sincronizar
- reiniciar app
- capturar otro punto
- verificar que la secuencia sea `1..11` y no vuelva a `1`

Validacion automatizada:

- `npm run lint` en `sisa.ui` -> PASS
- `npx tsc --noEmit` en `sisa.ui` -> FAIL por deuda TypeScript preexistente en clients/jobs/invoices/receipts/contextos; no se observan errores nuevos en `database/tracking.ts`, `database/schema.ts`, `database/migrations.ts`, `database/Database.ts`, `src/tracking/types.ts` ni `contexts/TrackingContext.tsx` asociados a este cambio
- `git status --short` y `git diff --stat` revisados en raiz y `sisa.ui`

Bloqueos preservados:

- no se avanzo a IA, timeline, stays/trips ni mapas

## Reparacion P0 - tracking raw schema e idempotencia

Estado: implementado focalizado, pendiente de ejecucion contra DB afectada

Problema confirmado:

- `gps_points` tiene `id = 0` repetido y `SHOW INDEX` no muestra `PRIMARY`
- `gps_upload_batches` tiene `id = 0` repetido y `SHOW INDEX` no muestra `PRIMARY`
- `gps_points` tiene duplicados reales en `device_id + sequence_no`, por lo que `sequence_no` no puede ser idempotencia fuerte
- `/tracking-timeline` queda bloqueado hasta reparar raw schema; stays/trips e IA siguen bloqueados

Que cambio:

- `qa/tracking-schema-diagnostics.sql` agrega diagnostico SQL del schema, columnas, indices, ids duplicados, UUIDs duplicados y referencias `point_id = 0`
- `qa/tracking-raw-repair-preview.sql` agrega preview no destructivo de filas afectadas y conteos de reparacion
- `sisa.api/scripts/migrations/tracking-raw-schema-repair-phase38.php` repara tablas existentes preservando datos: ids unicos, `PRIMARY KEY`, `AUTO_INCREMENT`, backfill de UUIDs legacy y unicidad `device_id + point_uuid` / `device_id + batch_uuid`
- backend rechaza `server_point_id = 0`, no guarda `user_last_locations.point_id = 0`, normaliza `last_known_server_point_id = 0` como `null` y elimina catches vacios criticos en tracking raw
- mobile asegura `point_uuid` en el schema inicial/migrado, no borra cola si el backend devuelve `server_point_id` invalido y no persiste `last_server_point_id = 0`
- `install.php` y `update_install.php` registran la fase 38

Riesgo cubierto:

- evitar que `lastInsertId()` devuelva `0` y que el ACK movil borre puntos locales sin un id real del servidor
- separar idempotencia fuerte (`point_uuid`/`batch_uuid`) de `sequence_no`, que ya esta confirmado duplicado en datos reales

Puntos ciegos conocidos:

- la migracion preserva las tablas originales renombradas como backup `*_phase38_backup_*`; requiere correr update/install contra la DB afectada y revisar el diagnostico posterior
- si `user_last_locations.point_id = 0` no tiene ningun punto valido del usuario para reasignar, no se puede inferir un punto real sin decision manual
- no se avanzo a timeline, stays/trips, IA ni mapas

Validacion de esta sesion:

- `php -l scripts/migrations/tracking-raw-schema-repair-phase38.php` en `sisa.api` -> PASS
- `php -l src/Models/GpsPoints.php` en `sisa.api` -> PASS
- `php -l src/Models/GpsUploadBatches.php` en `sisa.api` -> PASS
- `php -l src/Controllers/TrackingController.php` en `sisa.api` -> PASS
- `php -l install.php` en `sisa.api` -> PASS
- `php -l update_install.php` en `sisa.api` -> PASS
- `npm run lint` en `sisa.ui` -> PASS
- `npm run build` en `sisa.ui` -> no ejecutado: no existe script `build`
- `git status --short` y `git diff --stat` revisados en raiz, `sisa.api` y `sisa.ui`

## Estabilizacion - validacion real de `/tracking-timeline`

Estado: pendiente de validacion con datos reales o seed controlado

Que cambio:

- se reviso el estado de workspace: `sisa.api` y `sisa.web` no tienen cambios internos pendientes en este corte; la raiz mantiene cambios documentales y marca `sisa.web` como repo anidado modificado desde la perspectiva raiz
- se verifico que `sisa.web/dist` esta versionado en el repo web (`git ls-files dist` devuelve archivos), por lo que no se borra ni limpia en este corte
- se verifico el contrato real de `GET /tracking/timeline`: acepta `company_id`, `date`, `user_id` y `timezone`; devuelve `date`, `day`, `timezone`, `company_id`, `users`, `user_id`, `selected_user_id`, `points`, `quality_score`, `points_count`, `first_point_at`, `last_point_at`, `gaps`, `suspicious_points`, `stays`, `trips`, `labels` y `anomalies`
- se verifico que una fecha sin puntos devuelve estructura usable: `points = []`, `points_count = 0`, `quality_score = 0`, `gaps = []`, `suspicious_points = []`, `anomalies = []`, `stays/trips/labels = []`
- se verifico que el 403 por empresa fuera de scope ocurre antes de listar usuarios o puntos, mediante `resolveOptionalCompanyId()`, y el permiso de ruta pasa por `PermissionsMiddleware('listTrackingAssignments')`
- `qa/tracking-timeline-seed.sql` agrega un seed manual, parametrizable y no destructivo para insertar una jornada minima de prueba en `gps_upload_batches`, `gps_points` y `user_last_locations`

Riesgo cubierto:

- evitar avanzar a stays/trips persistidos sin haber validado visualmente la calidad del raw tracking y del contrato consumido por `/tracking-timeline`

Puntos ciegos conocidos:

- no se pudo confirmar existencia de puntos reales desde esta sesion porque la conexion MySQL baseline sigue inaccesible
- el seed no se ejecuta automaticamente; requiere reemplazar `@company_id`, `@user_id` y opcionalmente `@day` por valores reales y correrlo manualmente contra MySQL
- el endpoint todavia no marca anomalias por velocidad; el seed incluye un punto de velocidad alta solo para inspeccion visual

Validacion manual preparada:

- iniciar sesion en `sisa.web`
- seleccionar empresa activa
- abrir `/tracking-timeline`
- elegir fecha con datos reales o la fecha configurada en `qa/tracking-timeline-seed.sql`
- elegir usuario si el endpoint devuelve varios usuarios
- revisar resumen, puntos raw, links Google Maps, gaps, puntos sospechosos y anomalias
- confirmar que una fecha sin datos muestra estado vacio sin inventar datos
- confirmar que un 403 muestra mensaje de permiso y no datos parciales

Validacion parcial:

- revision de contrato por lectura de `sisa.api/src/Controllers/TrackingController.php`, `sisa.api/src/Routes/api.php` y `sisa.web/src/services/trackingCatalogsService.ts`
- `php -l src/Controllers/TrackingController.php` en `sisa.api` -> PASS
- `php -l src/Routes/api.php` en `sisa.api` -> PASS
- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> no ejecutado en este corte porque no se tocaron archivos frontend

## Implementacion P1 - vista web read-only de timeline tracking

Estado: completado focalizado

Que cambio:

- `sisa.api/src/Controllers/TrackingController.php` completa el contrato de `GET /tracking/timeline` devolviendo tambien `date`, `users`, `selected_user_id` y `points` localizados para que web pueda renderizar selector y tabla raw
- `sisa.web/src/services/trackingCatalogsService.ts` agrega `getTrackingTimeline()` y tipos normalizados para usuarios, puntos, gaps, suspicious points y anomalias
- `sisa.web/src/pages/TrackingCatalogsPages.tsx` agrega `TrackingTimelinePage`, una vista read-only con selector de fecha, selector de usuario, resumen de calidad, placeholder tecnico de mapa, tabla de puntos con links Google Maps, gaps y observaciones tecnicas
- `sisa.web/src/App.tsx` registra la ruta `/tracking-timeline`
- `sisa.web/src/navigation/app-navigation.ts` agrega `Timeline GPS` al grupo Tracking
- `sisa.web/src/app/globals.css` agrega estilos scoped `tracking-*` para resumen, flags, gaps, observaciones y fallback de mapa
- `docs/tracking-backlog.md` marca el timeline read-only como implementado y deja `stays/trips v1` como proximo paso recomendado

Riesgo cubierto:

- permitir validacion visual del raw tracking por empresa, fecha y usuario antes de persistir stays/trips o avanzar a IA, evitando construir automatizacion sobre datos que no se pueden inspeccionar

Puntos ciegos conocidos:

- no se agrego libreria de mapas; la vista deja placeholder tecnico y links Google Maps por punto
- no hay edicion de labels, geocercas nuevas, stays/trips persistidos, IA ni dashboards de productividad
- el permiso backend sigue reutilizando `listTrackingAssignments`; queda pendiente permiso granular para timeline/ubicacion sensible
- `npm run build` en `sisa.web` actualiza artefactos `dist/` porque el repo los versiona actualmente

Validacion manual sugerida:

- abrir `/tracking-timeline` en `sisa.web`
- elegir fecha con datos GPS
- elegir usuario si el endpoint devuelve varios usuarios
- confirmar resumen de puntos, primer/ultimo punto y quality score
- revisar tabla raw y links Google Maps
- revisar gaps y puntos sospechosos
- confirmar que una fecha sin datos muestra estado vacio sin inventar datos
- confirmar que un 403 muestra mensaje de permiso y no datos parciales

Validacion parcial:

- `php -l src/Controllers/TrackingController.php` en `sisa.api` -> PASS
- `php -l src/Routes/api.php` en `sisa.api` -> PASS
- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Implementacion P1 - timeline read-only desde raw tracking

Estado: avance parcial implementado, validacion focalizada con bloqueo de baseline backend

Que cambio:

- `sisa.api/src/Routes/api.php` expone `GET /tracking/timeline` protegido con permiso administrativo existente de tracking (`listTrackingAssignments`)
- `sisa.api/src/Controllers/TrackingController.php` agrega `getTimeline()` para devolver timeline diario por `date`, `timezone`, `user_id` y `company_id` opcional, calculado desde `gps_points` raw sin persistir derivados todavia
- el timeline devuelve `points_count`, `first_point_at`, `last_point_at`, `quality_score`, `gaps`, `suspicious_points`, `anomalies`, y placeholders vacios para `stays`, `trips` y `labels`
- se agrego heuristica inicial no destructiva: gap cuando pasan mas de 300 segundos entre puntos, punto sospechoso cuando `accuracy_m > 100` o `is_mock`, y score de calidad derivado de gaps + ratio de puntos sospechosos
- `sisa.api/tests/Controllers/TrackingControllerTest.php` agrega cobertura esperada para gaps, baja precision y quality score del endpoint
- `docs/tracking-backlog.md` y `docs/tracking-decision-checklist.md` reflejan el primer corte read-only

Riesgo cubierto:

- permitir inspeccion operativa temprana de jornadas GPS sin esperar al worker ni a tablas derivadas, manteniendo el raw como fuente de verdad y dejando visibles gaps/calidad antes de construir stays/trips persistidos

Puntos ciegos conocidos:

- `stays`, `trips` y `labels` siguen como placeholders vacios hasta el siguiente hito
- el endpoint calcula sobre raw en tiempo de request; si el volumen crece, debe moverse a derivados reconstruibles (`tracking_days`, `tracking_stays`, `tracking_trips`)
- el permiso reutiliza `listTrackingAssignments`; queda pendiente decidir permiso granular para timeline/ubicacion sensible
- la consola web avanzada todavia no consume `/tracking/timeline`

Validacion parcial:

- `php -l src/Controllers/TrackingController.php` en `sisa.api` -> PASS
- `php -l src/Routes/api.php` en `sisa.api` -> PASS
- `php -l tests/Controllers/TrackingControllerTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/TrackingControllerTest.php` en `sisa.api` -> bloqueado por `Error de conexión: SQLSTATE[HY000] [2002] ...`, consistente con deuda de baseline de conexion ya documentada

## Implementacion P0 - hardening raw de tracking

Estado: avance parcial implementado, validacion focalizada con bloqueo de baseline backend

Que cambio:

- `sisa.api/scripts/migrations/tracking-raw-hardening-phase37.php` agrega hardening aditivo para tracking raw: `company_id`, `batch_uuid`, `point_uuid`, indices por empresa/fecha y claves unicas por device+batch/device+point
- `sisa.api/install.php` y `sisa.api/update_install.php` registran la fase 37 para instalaciones y updates
- `sisa.api/src/Models/GpsPoints.php` persiste `company_id` y `point_uuid`, devuelve `point_uuid` en accepted, filtra rutas diarias por empresa cuando se informa y propaga `company_id` a `user_last_locations`
- `sisa.api/src/Models/GpsUploadBatches.php` persiste `company_id` y `batch_uuid`
- `sisa.api/src/Controllers/TrackingController.php` acepta `company_id` desde body/query/header, valida scope cuando viene informado, acepta `batch_uuid`/`point_uuid` y mantiene fallback legacy por `sequence_no`
- `sisa.ui/database/schema.ts`, `database/migrations.ts`, `database/tracking.ts`, `src/tracking/types.ts` y `contexts/TrackingContext.tsx` agregan `point_uuid` local, migracion SQLite v3, `batch_uuid` por subida y envio de `company_id`/`X-Company-Id`
- `docs/tracking-backlog.md` y `docs/tracking-decision-checklist.md` reflejan el primer corte P0 implementado

Riesgo cubierto:

- reducir duplicados y mezcla multi-tenant en tracking raw, dejando el carril de ingesta preparado para reconstruccion posterior por empresa, lote y punto estable

Puntos ciegos conocidos:

- `company_id` todavia es opcional por compatibilidad; el siguiente endurecimiento debe decidir cuando hacerlo obligatorio para tracking operativo
- las tablas derivadas (`tracking_days`, stays, trips, labels, runs) siguen sin implementarse
- la idempotencia de lote registra `batch_uuid`, pero el reintento movil genera un lote nuevo; la deduplicacion fuerte queda garantizada por `point_uuid`
- no se resolvio todavia retencion, masking fuera de horario ni auditoria de consultas sensibles

Validacion parcial:

- `php -l src/Controllers/TrackingController.php` en `sisa.api` -> PASS
- `php -l src/Models/GpsPoints.php` en `sisa.api` -> PASS
- `php -l src/Models/GpsUploadBatches.php` en `sisa.api` -> PASS
- `php -l scripts/migrations/tracking-raw-hardening-phase37.php` en `sisa.api` -> PASS
- `php -l install.php` y `php -l update_install.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/TrackingControllerTest.php` en `sisa.api` -> bloqueado por `Error de conexión: SQLSTATE[HY000] [2002] ...`, consistente con deuda de baseline de conexion ya documentada
- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS
- `npm run check:sync-smoke` en `sisa.ui` -> PASS
- `npx tsc --noEmit` en `sisa.ui` -> FAIL por errores TypeScript preexistentes distribuidos en clients/jobs/invoices/receipts/tracking contexts; se registra como deuda de baseline, no como validacion bloqueante de este corte

## Avance discovery - baseline real de tracking existente

Estado: avance documental, decisiones parcialmente cerradas sin implementar codigo

Que cambio:

- se verifico que `sisa.web` existe y ya es un target web real Vite/React 19 con rutas live de tracking administrativo (`tracking-policies`, `tracking-assignments`, `tracking-routes`, `tracking-points`, `tracking-proximity`)
- se verifico que no existe directorio raiz `sisa/` en el workspace revisado, por lo que no debe tratarse como repo de implementacion pendiente
- se verifico que `sisa.api` ya tiene tracking parcial: `TrackingController`, rutas `/tracking/policy`, `/tracking/points/batch`, `/tracking/status`, `/tracking/admin/*`, modelos `TrackingPolicies`, `UserTrackingAssignments`, `GpsPoints`, `GpsUploadBatches` y `TrackingControllerTest`
- se verifico que `sisa.ui` ya tiene Expo 54, `expo-location`, `expo-task-manager`, permisos background/foreground declarados, `TrackingContext`, `src/tracking/location.ts`, cola local `database/tracking.ts` y pantallas `app/tracking/*`
- `docs/tracking-architecture.md`, `docs/tracking-backlog.md` y `docs/tracking-decision-checklist.md` se ajustan para reflejar que el primer trabajo es endurecer la base existente, no crear tracking desde cero

Riesgo cubierto:

- evitar duplicar modulos de tracking o planificar tablas/endpoints como si no existiera nada, y enfocar el P0 en brechas reales: `company_id`, idempotencia por UUID, derivados reconstruibles, auditoria y privacidad

Puntos ciegos conocidos:

- el motor SQL esta confirmado como MySQL por PDO, pero no hay evidencia de uso geoespacial nativo; por ahora el diseno debe asumir `lat/lng` numericos e indices basicos
- la estrategia final de migraciones para tracking sigue abierta porque la base actual usa `ensureTable()` en modelos y el repo tambien usa `scripts/migrations/*`
- el modelo actual asocia puntos a `user_id` + `device_id`; queda abierta la decision de `member_id`/membresia/company para multi-tenant fuerte
- la implementacion movil ya existe tecnicamente, pero falta cerrar UX de permisos, limites de cola, retencion local y piloto Android

Validacion parcial:

- discovery documental con lectura de estructura, rutas, modelos, package/app config y archivos de tracking existentes

## Discovery - arquitectura minima para tracking e IA

Estado: completado documental, pendiente de decisiones bloqueantes antes de codigo

Que cambio:

- `docs/tracking-architecture.md` documenta una arquitectura minima y extensible para tracking: modulo dentro del monolito, ingesta dedicada, raw append-only, worker/rebuild, derivados reconstruibles, labels manuales e IA futura no destructiva
- `docs/tracking-backlog.md` descompone el trabajo en tickets P0-P4, separando captura/ingesta, reconstruccion, operacion humana, privacidad/escala e IA futura
- `docs/tracking-decision-checklist.md` deja las decisiones que deben cerrarse antes de implementar: repo web objetivo, motor SQL, policy de permisos/privacidad, device_id, retencion, captura movil, thresholds y piloto

Riesgo cubierto:

- evitar iniciar tracking como una extension improvisada de `/sync/batch` o como IA prematura sin raw confiable, idempotencia, auditoria, retencion y correccion humana

Puntos ciegos conocidos:

- no se implemento codigo ni migraciones; esta etapa es intencionalmente documental
- `sisa.web` y `sisa` siguen pendientes de verificacion estructural antes de decidir donde vive la consola de tracking
- la capacidad geoespacial real del motor SQL actual todavia debe confirmarse antes del primer PR
- la promesa movil queda limitada a foreground fiable y background opt-in; no se debe vender tracking garantizado con la app terminada sin cambiar estrategia tecnologica

Validacion parcial:

- revision documental local de los nuevos archivos

## Avance parcial - Libro diario visual agrupado en `sisa.api` y `sisa.web`

Estado: completado focalizado

Que cambio:

- `sisa.api/src/Models/AccountingEntries.php` agrega `listLedgerPage()` con JOIN a `accounts`, filtros reales por empresa activa/scope, cuenta, fechas, origen, tipo de asiento y `q`, paginacion SQL, totales globales y agrupados
- `sisa.api/src/Controllers/AccountingEntriesController.php` usa la nueva lectura paginada, valida `X-Company-Id`/`company_id` contra el scope del usuario y devuelve cada asiento con `account` enriquecida sin cambiar el contrato `entries/pagination/totals/sort`
- `sisa.api/tests/Controllers/AccountingLedgerIntegrationTest.php` cubre filtro por empresa seleccionada, paginacion real, totales, busqueda por descripcion/cuenta y presencia de `account.name/code/type`
- `sisa.web/src/services/financeCatalogsService.ts` normaliza `account`, `totals`, `totalsByOriginType`, `totalsByEntryType`, `sort` y envia filtros de libro diario a la API
- `sisa.web/src/pages/FinanceCatalogsPages.tsx` rediseña `JournalPage` como vista de movimientos: resumen visual, filtros aplicados por boton, scroll incremental con deduplicacion, agrupacion por origen, cards expandibles y vista tecnica opcional de solo lectura
- `sisa.web/src/app/globals.css` agrega estilos `ledger-*` para resumen, filtros, barra Debe/Haber, cards de movimientos, chips de estado y tabla tecnica, respetando variables de tema

Riesgo cubierto:

- evitar que el Libro diario mezcle empresas o pagine en PHP despues de filtrar, y reemplazar la tabla tecnica de asientos sueltos por movimientos contables entendibles sin permitir mutaciones desde la pantalla

Puntos ciegos conocidos:

- cuando un movimiento queda partido por paginacion, la web lo marca como `Grupo parcial` si solo llego un asiento con origen; la API todavia pagina asientos, no movimientos completos
- el filtro `manual/otros` depende de que la API reciba `origin_type=manual`; asientos legacy con `origin_type` nulo quedan visibles en la vista general pero no tienen un filtro dedicado de nulos

Validacion parcial:

- `php -l src/Controllers/AccountingEntriesController.php` en `sisa.api` -> PASS
- `php -l src/Models/AccountingEntries.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/AccountingLedgerIntegrationTest.php` en `sisa.api` -> PASS (15 tests, 60 assertions)
- `vendor/bin/phpunit` en `sisa.api` -> vuelve a emitir `Error de conexión: SQLSTATE[HY000] [2002] ...`, consistente con la deuda de baseline ya documentada
- `npm run build` en `sisa.web` -> PASS

## Correccion - robustez de usage en categorias contables

Estado: completado

Que cambio:

- `sisa.api/src/Models/Categories.php` ahora verifica existencia de tablas/columnas antes de calcular usage de `payments` y `products_services`, limita las consultas a las categorias visibles y registra `error_log` si una query de usage falla
- `sisa.api/src/Controllers/CategoriesController.php` protege `include_usage` y el bloqueo de delete con `try/catch`, devolviendo usage vacio en vez de HTTP 500 cuando la base no permite calcular referencias
- `sisa.api/tests/Controllers/CategoriesControllerOfflineFirstTest.php` agrega cobertura para usage sin tablas auxiliares, listado robusto cuando usage falla y delete robusto cuando usage falla
- `sisa.web/src/services/referenceCatalogsService.ts` mantiene `include_usage=1`; no se deshabilito porque la API queda blindada

Validacion parcial:

- reproduccion HTTP local contra `localhost:8080/categories?...` no fue posible porque no habia API escuchando en ese puerto
- `php -l src/Controllers/CategoriesController.php` en `sisa.api` -> PASS
- `php -l src/Models/Categories.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/CategoriesControllerOfflineFirstTest.php` en `sisa.api` -> PASS (11 tests, 31 assertions)
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - categorias contables operables en `sisa.api` y `sisa.web`

Estado: completado focalizado

Que cambio:

- `sisa.api/src/Models/Categories.php` agrega calculo opt-in de uso real para categorias por empresa, agregando pagos activos por `category_id` y productos/servicios activos por coincidencia normalizada de nombre, sin N+1 por categoria
- `sisa.api/src/Controllers/CategoriesController.php` expone `include_usage=1` en `GET /categories` y `GET /categories/{id}` y bloquea `DELETE /categories/{id}` con HTTP 409 cuando hay referencias activas salvo `force=true`
- `sisa.api/tests/Controllers/CategoriesControllerOfflineFirstTest.php` cubre usage, estructura compatible sin usage, bloqueo 409 y borrado forzado, preservando los tests offline-first existentes
- `sisa.web/src/services/referenceCatalogsService.ts` normaliza metadata/usage de categorias y agrega `createCategory`, `updateCategory` y `deleteCategory`
- `sisa.web/src/pages/ReferenceCatalogsPages.tsx` reemplaza `CategoriesPage` generico por administracion real: busqueda, filtros ingreso/egreso, contadores, cards con uso real, modal CRUD y flujo de eliminacion segura
- `sisa.web/src/app/globals.css` agrega estilos scoped para el modulo visual de categorias

Riesgo cubierto:

- evitar borrados silenciosos de categorias ya usadas en pagos o referencias comerciales, manteniendo soft delete, historial y sync existentes

Puntos ciegos conocidos:

- `products_services` sigue relacionado por texto (`category`) y no por `category_id`; el usage web/API refleja esa realidad con match normalizado por nombre hasta que exista una migracion especifica

Validacion parcial:

- `php -l src/Controllers/CategoriesController.php` en `sisa.api` -> PASS
- `php -l src/Models/Categories.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/CategoriesControllerOfflineFirstTest.php` en `sisa.api` -> PASS (8 tests, 23 assertions)
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - paginacion incremental por scroll en `sisa.web`

Estado: en progreso

Que cambio:

- `sisa.web/src/hooks/useIncrementalRows.ts` agrega un patron reusable de carga incremental para listas web ya filtradas, con `visibleRows`, `visibleCount`, `totalCount`, `hasMore` y `sentinelRef`
- `sisa.web/src/app/globals.css` agrega estilos comunes para el resumen `Mostrando X de Y` y el sentinel de scroll
- `sisa.web/src/pages/CompaniesPage.tsx`, `sisa.web/src/pages/ClientsPage.tsx` y `sisa.web/src/pages/ProvidersPage.tsx` ahora limitan el render inicial de tablas largas y anexan mas filas al scrollear
- `sisa.web/src/pages/InvoicesPage.tsx`, `sisa.web/src/pages/PaymentsPage.tsx` y `sisa.web/src/pages/ReceiptsPage.tsx` aplican el mismo patron a los listados administrativos mas volumetricos de cobranzas y egresos
- `sisa.web/src/pages/QuotesPage.tsx` aplica el patron al backlog comercial en formato tarjetas, manteniendo visibles filtros y total filtrado
- `sisa.web/src/pages/JobsPage.tsx` aplica el mismo append por scroll al backlog operativo principal, preservando filtros complejos, tarjetas enriquecidas y apertura de detalle existente
- `sisa.web/src/pages/ReferenceCatalogsPages.tsx`, `sisa.web/src/pages/FinanceCatalogsPages.tsx`, `sisa.web/src/pages/OperationsCatalogsPages.tsx` y `sisa.web/src/pages/TrackingCatalogsPages.tsx` adoptan el mismo patron en listados catalogo/genericos para evitar render completo cuando crezcan referencias o historiales
- `sisa.web/src/services/financeCatalogsService.ts` y `sisa.web/src/services/operationsCatalogsService.ts` ahora exponen variantes paginadas reales (`listAccountingEntriesPage`, `listAppointmentsPage`) y `JournalPage` / `AppointmentsPage` consumen `page/perPage/totalEntries/totalPages` directamente desde backend para anexar paginas al hacer scroll
- `sisa.api/src/Models/Clients.php`, `sisa.api/src/Controllers/ClientsController.php`, `sisa.web/src/services/clientsService.ts` y `sisa.web/src/pages/ClientsPage.tsx` agregan el primer contrato híbrido de alto volumen para una entidad comercial real: `GET /clients` ahora puede devolver `pagination`, la web consume páginas reales cuando no hay búsqueda y vuelve a carga completa cuando necesita preservar total filtrado correcto por búsqueda local o por orden `unbilledCount`
- `sisa.api/src/Controllers/ProvidersController.php`, `sisa.web/src/services/providersService.ts` y `sisa.web/src/pages/ProvidersPage.tsx` agregan paginación híbrida para proveedores: la API ya puede devolver `pagination` y la web usa append real sin búsqueda, manteniendo fallback full cuando la búsqueda local debe conservar total filtrado exacto
- `sisa.api/src/Controllers/CompaniesController.php`, `sisa.web/src/services/companiesService.ts` y `sisa.web/src/pages/CompaniesPage.tsx` aplican el mismo patrón híbrido sobre empresas, reutilizando `page/per_page/total` desde web sin romper el flujo actual de búsqueda local
- `sisa.api/src/Controllers/InvoicesController.php`, `sisa.web/src/services/invoicesService.ts` y `sisa.web/src/pages/InvoicesPage.tsx` agregan paginación híbrida para facturas: la API ya puede devolver `pagination` y la web usa append real sin búsqueda, manteniendo fallback full cuando la búsqueda local debe conservar total filtrado exacto
- `sisa.api/src/Controllers/PaymentsController.php`, `sisa.web/src/services/invoicesService.ts` y `sisa.web/src/pages/PaymentsPage.tsx` agregan paginación híbrida para pagos, con el mismo criterio de búsqueda local exacta
- `sisa.api/src/Controllers/ReceiptsController.php`, `sisa.web/src/services/receiptsService.ts` y `sisa.web/src/pages/ReceiptsPage.tsx` agregan paginación híbrida para recibos; además de la búsqueda local, la web vuelve a colección completa cuando el usuario activa filtros locales por `settlement_status` o por estado de instrumentos
- `sisa.api/src/Models/Jobs.php` (listPage ahora recibe array $sortCriteria, ORDER BY múltiple con CASE para priority, COALESCE(comp.name, c.business_name) verificado en JobsPage render, joins condicionales, COUNT y LIMIT intactos) + `JobsController.php` (parseSortCriteria que soporta sort=field:dir,field2:dir2 y backward compat) + `jobsService.ts` (nuevo tipo JobSortCriterion[] y serialización a sort=...) + `JobsPage.tsx` (sortCriteria array, helpers compare/get/normalize, UI con selects+agregar+chips, multi-sort client en full y paged, useEffect con ref para evitar loops, clean resetea a default). Cumple contrato exacto. 
Archivos tocados: solo los 6 permitidos.
Comandos ejecutados:
  php -l src/Models/Jobs.php → No syntax errors
  php -l src/Controllers/JobsController.php → No syntax errors
  npm run lint → 0 errors (warnings de deps minimizados)
  npm run build → PASS (tsc + vite)
Pruebas manuales sugeridas: las 5 URLs del contrato (sort múltiple, campo inválido ignorado, compat old params, paged vs full, limpiar).
- `sisa.web/docs/web-incremental-pagination-rollout.md` deja registro puntual de los lugares cubiertos y de la deuda restante hacia paginacion real de API

Riesgo cubierto:

- evitar que listados largos de empresas, clientes, proveedores, facturas, pagos, recibos y presupuestos intenten renderizar toda la coleccion visible de una sola vez, degradando respuesta y scroll en web

Puntos ciegos conocidos:

- esta etapa es incremental en frontend sobre colecciones ya cargadas; no introduce todavia paginacion real de red
- otros modulos web con volumen potencial alto quedan fuera de esta pasada hasta confirmar necesidad real o soporte de API
- algunos listados tipo tabla comparten el mismo dataset completo desde servicios genericos; si el volumen real sigue creciendo, el siguiente paso debe ser soporte de pagina/filtro desde servicio/backend y no solo append visual
- `JournalPage` y `AppointmentsPage` ya salen de esa limitacion porque usan paginacion real; el resto sigue dependiendo del costo de carga inicial de sus endpoints actuales
- `ClientsPage` tambien sale parcialmente de esa limitacion en modo sin búsqueda, pero mantiene fallback full cuando la semántica de UI depende de datos locales todavía no resueltos por el backend
- `ProvidersPage` y `CompaniesPage` quedan en la misma categoría híbrida: mejoran costo inicial en modo sin búsqueda, pero todavía requieren colección completa cuando el filtro textual se resuelve localmente
- `InvoicesPage` y `PaymentsPage` entran en esa misma categoría híbrida; `ReceiptsPage` suma además dependencia de filtros locales enriquecidos por instrumentos/aplicaciones

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS
- `npm run lint` en `sisa.web` -> PASS tras extender paginacion incremental a `JobsPage` y catalog pages web
- `npm run build` en `sisa.web` -> PASS tras extender paginacion incremental a `JobsPage` y catalog pages web
- `npm run lint` en `sisa.web` -> PASS tras conectar paginacion real en `JournalPage` y `AppointmentsPage`
- `npm run build` en `sisa.web` -> PASS tras conectar paginacion real en `JournalPage` y `AppointmentsPage`
- `php -l src/Controllers/ClientsController.php` y `php -l src/Models/Clients.php` en `sisa.api` -> PASS
- `npm run lint` en `sisa.web` -> PASS tras conectar paginacion real híbrida en `ClientsPage`
- `npm run build` en `sisa.web` -> PASS tras conectar paginacion real híbrida en `ClientsPage`
- `php -l src/Controllers/ProvidersController.php` y `php -l src/Controllers/CompaniesController.php` en `sisa.api` -> PASS
- `npm run lint` en `sisa.web` -> PASS tras conectar paginacion real híbrida en `ProvidersPage` y `CompaniesPage`
- `npm run build` en `sisa.web` -> PASS tras conectar paginacion real híbrida en `ProvidersPage` y `CompaniesPage`
- `php -l src/Controllers/PaymentsController.php`, `php -l src/Controllers/ReceiptsController.php` y `php -l src/Controllers/InvoicesController.php` en `sisa.api` -> PASS
- `php -l src/Controllers/JobsController.php` y `php -l src/Models/Jobs.php` en `sisa.api` -> PASS
- `npm run lint` y `npm run build` en `sisa.web` -> PASS
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` -> PASS (sin regresiones)
- `sisa.api/src/Models/Jobs.php::listPage()` + controlador completan el hito de paginación real eficiente para Jobs (prioridad Tier A)

## Avance parcial - catalogos comerciales operables en `sisa.web`

Estado: en progreso

Que cambio:

- `sisa.web/src/services/referenceCatalogsService.ts` agrega mutaciones CRUD para `products_services` y `tariffs`, normaliza metadata util de empresa/uuid/actualizacion y mantiene las lecturas existentes de referencias
- `sisa.web/src/pages/ReferenceCatalogsPages.tsx` convierte Productos/Servicios en modulo administrativo real: alta, edicion, baja, busqueda, categoria sugerida desde catalogos, tipo, precio, costo, dificultad y stock
- `sisa.web/src/pages/ReferenceCatalogsPages.tsx` convierte Tarifas en modulo CRUD real para nombre e importe, alineado al contrato actual de la API
- el contrato queda dual: la web administra online por CRUD contra la API, y la app mantiene el modelo sync/offline porque esos mismos endpoints registran eventos `SyncEventGenerator` para `products_services` y `tariffs`, consumidos luego por bootstrap/pull/cache en `sisa.ui`
- Cajas y Categorias quedan intencionalmente como consulta para no mezclar el hito comercial-operativo con caja/contabilidad

Riesgo cubierto:

- evitar que presupuestos, trabajos, work logs y facturas dependan de catalogos que solo se pueden consultar desde web, bloqueando administracion online de los insumos economicos basicos
- evitar tambien que el CRUD web se convierta en un canal paralelo que saltee sync; los cambios web deben seguir propagandose a la app offline como referencias versionadas

Puntos ciegos conocidos:

- la API actual de tarifas solo persiste nombre e importe; moneda, vigencia e historial visible quedan para una iteracion posterior
- Productos/Servicios usa categoria textual del contrato actual y sugerencias desde `categories`, pero no fuerza todavia relacion estructurada ni muestra uso historico en presupuestos/trabajos/facturas
- todavia no se implementa la conversion Presupuesto -> Trabajo; el backend ya reserva `converted_at` y `converted_job_id`, pero falta endpoint/contrato dedicado para hacerlo sin improvisar desde la UI

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS
- `vendor/bin/phpunit tests/Controllers/TariffsControllerOfflineFirstTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/ProductsServicesControllerOfflineFirstTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` en `sisa.api` -> bloqueado por error de conexion a DB en un test de adjuntos/canonical sync no relacionado; antes del bloqueo la traza confirma presencia de `tariffs` y `products_services` en referencias bootstrap

## Avance parcial - receipts separados en cabecera + `receipt_items` en `sisa.api`

Estado: baseline compartido en verde

Que cambio:

- `sisa.api/scripts/migrations/receipt-items-phase32.php`, `sisa.api/install.php` y `sisa.api/update_install.php` agregan `receipts.total_amount`, vuelven nullable `receipts.paid_in_account`, crean `receipt_items` con metadata offline-first y hacen backfill idempotente de cada recibo legacy a un item unico `legacy_single`
- `sisa.api/src/Models/ReceiptItems.php` incorpora el modelo operativo para listar, persistir, sumar y soft-delete de items por recibo, incluyendo adjuntos y metadata JSON por item
- `sisa.api/src/Models/Checks.php`, `sisa.api/src/Models/BankTransfers.php` y `sisa.api/scripts/migrations/receipt-instruments-phase33.php` agregan instrumentos propios para cheques y transferencias, ya ligados por `receipt_item_id` y con estados/datos minimos separados del item base
- `sisa.api/scripts/migrations/invoice-settlement-phase34.php`, `sisa.api/src/Models/InvoiceReceiptPayments.php` y `sisa.api/src/Models/Invoices.php` agregan `invoice_receipt_payments.status` + `invoices.payment_status`, dejando explicitado cuando una imputacion sigue `pending_settlement` aunque el recibo ya este aplicado comercialmente
- `sisa.api/src/Controllers/ReceiptsController.php` ahora acepta `items[]` al crear/editar, valida suma de items contra el total persistido, expone `items` en list/get, mantiene fallback legacy para clientes viejos y borra items en cascada al eliminar el recibo
- `sisa.api/src/Controllers/ReceiptsController.php` tambien valida datos minimos por instrumento (`check_number`, `bank_name`, `due_date`, `operation_number`) y persiste `check` / `bank_transfer` anidados por item dentro de la misma transaccion del recibo
- `sisa.api/src/Controllers/SyncOperationsController.php` y `sisa.api/src/Services/SyncEventGenerator.php` ya incluyen `items` dentro del payload canonico/sync de `receipts`, de modo que bootstrap y operaciones offline no pierdan el desglose del cobro
- `sisa.api/src/Controllers/SyncOperationsController.php` ahora tambien reconstruye y soft-deletea instrumentos al aplicar operaciones de sync sobre `receipts`, evitando que pull/push dejen items sin su cheque o transferencia asociados
- `sisa.api/src/Services/ReceiptApplicationService.php` ahora recalcula estado de aplicaciones por receipt segun monto confirmado en `receipt_items` y mueve la factura entre `unpaid`, `partial`, `pending_settlement` y `paid` usando `payment_status`, mientras `status` solo pasa a `paid` cuando la parte confirmada cubre realmente el total
- `sisa.api/src/Controllers/ReceiptsController.php` e `sisa.api/src/Controllers/InvoicesController.php` dejan de usar reconciliaciones locales por monto bruto aplicado y delegan la convergencia comercial al servicio comun de aplicaciones
- `sisa.api/src/Services/ReceiptInstrumentLifecycleService.php` agrega el primer ciclo de vida operativo para instrumentos: confirmar/rechazar transferencias y depositar/acreditar/rechazar cheques, siempre en transaccion y re-sincronizando receipt, aplicaciones, factura y asientos contables derivados
- `sisa.api/src/Controllers/ReceiptsController.php`, `sisa.api/src/Routes/api.php` y `sisa.api/src/Models/Permission.php` exponen endpoints protegidos para `bank-transfers/{id}/confirm|reject` y `checks/{id}/deposit|clear|reject`, manteniendo el control de acceso a traves del receipt asociado
- `sisa.api/src/Models/BankTransfers.php` ahora detecta duplicados fuertes por `company_id` + `operation_number` o `transaction_id` al crear/actualizar, fuerza `status = duplicated` cuando corresponde, deja huella en `metadata_json` y bloquea la confirmacion operativa desde `ReceiptInstrumentLifecycleService` mientras exista conflicto activo
- `sisa.api/src/Services/ReceiptApplicationService.php` ahora prorratea la cobertura confirmada del receipt por link de aplicacion en orden estable (`created_at`, `id`), de modo que un recibo parcialmente acreditado ya no confirma en bloque todas las imputaciones: confirma solo los links completamente cubiertos y deja el resto en `pending_settlement`
- `sisa.api/scripts/migrations/receipt-header-status-phase35.php`, `sisa.api/src/Models/Receipts.php` y `sisa.api/src/Services/ReceiptApplicationService.php` formalizan la cabecera del recibo con `receipts.status` (`issued / partially_applied / applied / cancelled`) y `receipts.settlement_status` (`pending / partial / settled / rejected`), recalculados desde items + aplicaciones y propagados por sync
- `sisa.ui/contexts/ReceiptsContext.tsx`, `sisa.ui/contexts/InvoicesContext.tsx`, `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` y `sisa.ui/src/modules/jobs/presentation/sync/referenceCache.ts` ya absorben `receipts.status`, `receipts.settlement_status`, `invoices.payment_status` y `invoice_receipt_payments.status` en cache, bootstrap y pull incremental
- `sisa.ui/app/receipts/index.tsx`, `sisa.ui/app/receipts/[id].tsx`, `sisa.ui/app/invoices/[id].tsx` y `sisa.ui/utils/receiptSettlement.ts` exponen esos estados en interfaz: recibo emitido/aplicado, liquidacion pendiente/parcial/liquidada y aplicaciones pendientes o confirmadas, evitando que el operador vea una factura como definitivamente cobrada cuando aun falta acreditacion
- `sisa.ui/contexts/ReceiptsContext.tsx` tambien normaliza `receipt.items` con instrumentos anidados, y `sisa.ui/app/receipts/[id].tsx` ya permite operar lifecycle basico desde interfaz sobre instrumentos: confirmar/rechazar transferencias y depositar/acreditar/rechazar cheques usando los endpoints nuevos del backend
- `sisa.ui/app/receipts/create.tsx` y `sisa.ui/app/receipts/[id].tsx` ya permiten capturar y editar `items[]` nativamente: multiples medios por recibo, total calculado desde items y validaciones minimas por instrumento (caja para confirmados, numero de operacion en transferencias, banco/numero/vencimiento en cheques)
- en `sisa.ui` la edicion/alta de recibos deja de depender del campo visual unico `price` como fuente de verdad: el importe ahora se deriva de los `items[]` cargados por el usuario y viaja al backend junto con `total_amount`, manteniendo fallback legacy solo para compatibilidad de payload
- `sisa.ui/app/receipts/[id].tsx` ahora endurece la operacion diaria de instrumentos: al rechazar cheque o transferencia pide motivo explicito en modal, y las transferencias marcadas `duplicated` muestran warning visual con coincidencias detectadas antes de permitir cualquier decision operativa
- `sisa.ui/app/receipts/create.tsx` y `sisa.ui/app/receipts/[id].tsx` mejoran la operacion diaria sobre `receipt_items`: cada medio ahora puede duplicarse, reordenarse, colapsarse/expandirse y devuelve validaciones por item con contexto (`Medio 1`, `Medio 2`, etc.) para que el usuario corrija exactamente el bloque que fallo
- `sisa.api/tests/Services/ReceiptInstrumentLifecycleServiceTest.php` ahora cubre un flujo mixto integral: un receipt aplicado a dos facturas, confirmacion de transferencia, acreditacion de cheque y rechazo posterior del mismo cheque, verificando transiciones de `invoice_receipt_payments`, `invoice.payment_status` y `receipts.settlement_status`
- `qa/receipt-settlement-full-flow-runbook.md` deja un runbook manual estricto para validar el circuito completo `sisa.api` + `sisa.ui` mientras todavia no exista automatizacion E2E multi-dispositivo confiable
- `sisa.api/scripts/migrations/invoice-application-priority-phase36.php` y `sisa.api/src/Services/ReceiptApplicationService.php` agregan prioridad explicita de imputacion por link (`invoice_receipt_payments.allocation_priority`): cuando llega `priority` en el payload, el prorrateo de settlement ya no depende solo del orden de creacion sino del orden comercial elegido por el usuario/cliente integrador
- `sisa.ui/contexts/InvoicesContext.tsx`, `sisa.ui/contexts/ReceiptsContext.tsx`, `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` y `sisa.ui/app/receipts/index.tsx` ya bajan esa prioridad al frontend: al vincular un recibo existente a una factura, la UI permite definir `applied_amount` y `priority`, y luego muestra la prioridad resultante en detalle de factura y detalle de recibo
- `sisa.ui/app/receipts/index.tsx` y `sisa.ui/app/invoices/index.tsx` ahora agregan vistas operativas rapidas: filtro por `receipts.settlement_status`, filtro por `invoice.payment_status`, prioridad visible al vincular/aplicar y resumen breve de instrumentos pendientes/rechazados/duplicados en la lista de recibos
- `sisa.web/src/types/domain.ts`, `sisa.web/src/services/receiptsService.ts`, `sisa.web/src/services/invoicesService.ts`, `sisa.web/src/pages/ReceiptsPage.tsx` y `sisa.web/src/pages/InvoicesPage.tsx` ya absorben y muestran settlement en portal web: `receipt.status`, `receipt.settlement_status`, `invoice.payment_status`, estado/prioridad de cada aplicacion y montos de recibo basados en `total_amount` cuando existe
- `sisa.web/src/services/receiptsService.ts` y `sisa.web/src/pages/ReceiptsPage.tsx` ya exponen lifecycle basico de instrumentos en web: listado de `receipt.items`, warning por transferencias duplicadas, motivo visible de rechazo y acciones para confirmar/rechazar transferencias y depositar/acreditar/rechazar cheques desde el detalle del recibo
- `sisa.web/src/services/receiptsService.ts` y `sisa.web/src/pages/ReceiptsPage.tsx` ahora tambien permiten crear/editar recibos web con multiples `items[]`: efectivo, transferencia, cheque/e-check, tarjeta, retencion u otro, total calculado desde medios y validaciones por instrumento antes de enviar al backend
- `sisa.web/src/pages/ReceiptsPage.tsx` suma filtros operativos por `settlement_status` y por medios pendientes/duplicados/rechazados, ademas de un resumen de instrumentos directo en la grilla de recibos
- `sisa.api/scripts/migrations/receipt-header-status-phase35.php` queda corregida para bases legacy donde `receipts.currency_code` nunca existio: ahora agrega `receipts.status` despues de `total_amount` o `price`, evitando que el updater deje la fase 35 incompleta y luego falle el guardado de recibos
- `sisa.api/src/Controllers/ReceiptsController.php` endurece create/update/delete de recibos para cerrar transacciones solo si el flujo realmente las abrio y siguen activas, evitando falsos 500 `There is no active transaction` despues de migraciones o conexiones que no conservan estado transaccional
- `sisa.web/src/pages/ReceiptsPage.tsx` evita re-adjuntar aplicaciones de factura cuando el recibo se guarda sin cambios: compara links existentes por factura y monto antes de llamar `attachReceiptToInvoice`, reduciendo falsos `Receipt not found or inaccessible` en guardados idempotentes
- `sisa.api/src/Services/AccountingFlowService.php` deja de depender solo de `receipts.paid_in_account` cuando existen items: contabiliza unicamente `receipt_items` confirmados con `cash_box_id`, y cae al flujo legacy solo si el recibo aun no tiene items
- `sisa.api/src/Controllers/InvoicesController.php` y los tests de `AccountingFlowService`, `ReceiptApplicationService` y `ReceiptsOfflineFirstSmokeTest` quedan alineados al nuevo baseline minimo con `total_amount` + item legacy inicial

Riesgo cubierto:

- evitar que nuevos recibos sigan naciendo como una sola fila que mezcla comprobante, caja y medio de cobro, algo que ya impedia modelar pagos mixtos y dejaba contabilidad/sync listos para romperse apenas aparecian cheque o transferencia pendiente
- dejar receipts/invoices como flujo operable de punta a punta entre `sisa.api` y `sisa.ui`, con settlement real, lifecycle de instrumentos, prioridad comercial configurable y una postura QA repetible para detectar regresiones de dominio antes de que se mezclen nuevamente documento, caja y acreditacion

Puntos ciegos conocidos:

- esta etapa ya crea `checks` y `bank_transfers`, separa `payment_status` en factura, agrega control fuerte de duplicados para transferencias y prioridad explicita de imputacion; todavia no versiona `receipt_items`/instrumentos como entidades sync independientes ni automatiza E2E multi-dispositivo real

Validacion parcial:

- `vendor/bin/phpunit tests/Services/AccountingFlowServiceTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Services/ReceiptInstrumentLifecycleServiceTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/ReceiptsOfflineFirstSmokeTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Models/ReceiptItemsTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Services/ReceiptInstrumentLifecycleServiceTest.php tests/Models/ReceiptItemsTest.php` en `sisa.api` -> PASS para escenarios de duplicado de transferencias
- `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` en `sisa.api` -> PASS con escenario de prorrateo fino entre links confirmados vs `pending_settlement`
- `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` en `sisa.api` -> PASS con cobertura adicional de `receipts.status` y `receipts.settlement_status`
- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:sync-smoke` en `sisa.ui` -> PASS
- `vendor/bin/phpunit tests/Services/ReceiptInstrumentLifecycleServiceTest.php tests/Services/ReceiptApplicationServiceTest.php` en `sisa.api` -> PASS con flujo integral mixto de settlement
- `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php tests/Services/ReceiptInstrumentLifecycleServiceTest.php` en `sisa.api` -> PASS con prioridad explicita de imputacion y override sobre orden de creacion
- `npm run lint` en `sisa.ui` -> PASS tras exponer prioridad de imputacion en UI
- `npm run check:sync-smoke` en `sisa.ui` -> PASS tras exponer prioridad de imputacion en UI
- `npm run check:startup-stability` en `sisa.ui` -> PASS tras re-alinear la hidratacion edit-only de worklogs con el contrato del smoke
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` en raiz -> PASS completo (`Backend PHPUnit`, `Frontend lint`, `Frontend cache guard`, `Frontend sync smoke`, `Frontend startup guard`)
- `npm run lint` y `npm run build` en `sisa.web` -> PASS con estados operativos de receipts/invoices visibles en web
- `npm run lint` y `npm run build` en `sisa.web` -> PASS con lifecycle operativo de instrumentos visible desde receipts web
- `npm run lint` y `npm run build` en `sisa.web` -> PASS con captura/edicion multi-instrumento en receipts web
- `npm run lint` y `npm run build` en `sisa.web` -> PASS con filtros operativos de settlement/instrumentos en receipts web
- `php -l scripts/migrations/receipt-header-status-phase35.php` en `sisa.api` -> PASS tras corregir compatibilidad de la migracion con tablas `receipts` legacy sin `currency_code`
- `php -l src/Controllers/ReceiptsController.php` en `sisa.api` -> PASS tras endurecer cierre transaccional de create/update/delete de recibos
- `npm run lint` y `npm run build` en `sisa.web` -> PASS tras hacer idempotente el re-guardado de aplicaciones de recibos
- lint PHP de `src/Models/ReceiptItems.php`, `src/Models/Checks.php`, `src/Models/BankTransfers.php`, `src/Models/Receipts.php`, `src/Models/InvoiceReceiptPayments.php`, `src/Models/Invoices.php`, `src/Models/Permission.php`, `src/Controllers/ReceiptsController.php`, `src/Controllers/SyncOperationsController.php`, `src/Controllers/InvoicesController.php`, `src/Routes/api.php`, `src/Services/AccountingFlowService.php`, `src/Services/ReceiptApplicationService.php`, `src/Services/ReceiptInstrumentLifecycleService.php`, `src/Services/SyncEventGenerator.php`, `scripts/migrations/receipt-items-phase32.php`, `scripts/migrations/receipt-instruments-phase33.php` y `scripts/migrations/invoice-settlement-phase34.php` -> PASS

Siguiente monitoreo sugerido:

- correr `qa/receipt-settlement-full-flow-runbook.md` contra una base local con usuarios operativos reales y registrar cualquier friccion de UX/permisos
- si se habilita mas uso concurrente de cobranza, evaluar mover `allocation_priority` a una UI de reorder explicita dentro del detalle de factura/recibo, no solo en el momento de vincular

## Avance parcial - quotes online con CRUD, historial y PDF en `sisa.api`

Estado: en progreso

Que cambio:

- `sisa.api/scripts/migrations/quotes-module.php` crea y normaliza `quotes`, `quote_items`, `quotes_history` y `quote_items_history`, dejando UUID/version/soft delete/auditoria alineados al patron actual y reservando `converted_at` + `converted_job_id` para la fase futura de conversion a `jobs`
- `sisa.api/src/Models/Quotes.php`, `sisa.api/src/Models/QuoteItems.php`, `sisa.api/src/History/QuotesHistory.php` y `sisa.api/src/History/QuoteItemsHistory.php` agregan alcance por empresa, recalculo automatico de totales, snapshot historico completo y cascada soft delete de items
- `sisa.api/src/Controllers/QuotesController.php`, `sisa.api/src/Controllers/QuoteItemsController.php` y `sisa.api/src/Controllers/QuotePdfController.php` exponen CRUD online protegido, validan `company_id` por scope, copian snapshot de `products_services` en items y generan PDF persistido en `uploads/reports/quotes`
- `sisa.api/src/Routes/api.php`, `sisa.api/src/Models/Permission.php`, `sisa.api/install.php`, `sisa.api/update_install.php` y `sisa.api/docs/quotes-api.md` integran permisos, rutas, instalacion/update y contrato minimo del modulo
- `sisa.ui/contexts/QuotesContext.tsx`, `sisa.ui/app/quotes/*`, `sisa.ui/app/_layout.tsx` y `sisa.ui/constants/menuSections.ts` agregan compatibilidad web/app para listar, crear, editar, cambiar estado, administrar items, ver historial y exportar PDF del modulo quotes dentro del shell existente
- `sisa.web/src/pages/QuotesPage.tsx`, `sisa.web/src/services/quotesService.ts`, `sisa.web/src/types/domain.ts`, `sisa.web/src/navigation/app-navigation.ts` y `sisa.web/src/App.tsx` incorporan el modulo quotes al portal web con backlog, detalle, editor de cabecera, CRUD de items, historial y apertura del PDF generado por API

Riesgo cubierto:

- evitar que presupuestos comerciales nazcan sin aislamiento por empresa, sin foto historica de precios o sin trazabilidad completa de cabecera/items/PDF, algo que despues rompe auditoria y la futura conversion a trabajo

Puntos ciegos conocidos:

- la fase actual no convierte presupuestos a `jobs`, no sincroniza offline y no agrega tests automatizados dedicados del modulo; la validacion queda por ahora en lint/arranque del codigo y necesita smoke con base real para cerrar cobertura operativa

Validacion parcial:

- pendiente correr smoke CRUD/PDF contra una base local con datos reales de `clients`, `products_services` y permisos
- frontend: `npm run lint`, `npm run check:cache` y `npm run check:sync-smoke` pasan; `npx tsc --noEmit` sigue reportando errores preexistentes en modulos no relacionados del proyecto y no en los archivos nuevos de quotes
- web: `npm run lint` y `npm run build` en `sisa.web` pasan con el modulo quotes incorporado

## Avance parcial - `statuses` gana semantica global por `status_attribute`

Estado: en progreso

Que cambio:

- `sisa.api/scripts/migrations/statuses-phase3.php` agrega la columna `status_attribute`, backfill idempotente por inferencia legacy y nuevos indices por `scope/company_id + status_attribute` para resolver semantica sin depender del texto visible
- `sisa.api/src/Services/StatusAttributeRegistry.php`, `sisa.api/src/Models/Status.php` y `sisa.api/src/Controllers/StatusController.php` fijan el catalogo global compartido de atributos, ahora tambien con `quoted` y `quote_approved`, vuelven obligatorio `status_attribute` y dejan a `label` como nombre visible canonico mientras `name/code` quedan solo como espejo derivado/discontinuado
- `sisa.api/src/Services/JobStatusResolver.php` ahora prioriza `status_attribute` de empresa y luego global antes de caer al fallback legacy por texto, cubriendo especialmente `completed/billable` e `invoiced`
- `sisa.api/src/Models/Clients.php`, `sisa.api/src/Controllers/JobItemsController.php`, `sisa.api/src/Controllers/SyncOperationsController.php` y `sisa.api/src/Controllers/JobReportsController.php` dejan de interpretar palabras visibles como fuente primaria y pasan a apoyarse en la nueva semantica compartida
- `sisa.api/tests/Models/StatusTest.php`, `sisa.api/tests/Controllers/StatusControllerTest.php`, `sisa.api/tests/Services/StatusAttributeRegistryTest.php` y `sisa.api/tests/Services/JobStatusResolverTest.php` cubren normalizacion, rechazo de atributos invalidos, inferencia legacy y prioridad empresa/global del resolver
- `STATUS_ATTRIBUTE_FILTER_CONTRACT.md` documenta el contrato comun para web online, app offline y sync, incluyendo el backfill exacto esperado para la empresa `45`
- `sisa.ui/contexts/StatusesContext.tsx`, `sisa.ui/src/modules/jobs/data/repositories/SQLiteStatusesRepository.ts`, `sisa.ui/src/modules/jobs/data/db/schema.ts`, `sisa.ui/src/modules/jobs/data/db/jobsMigrations.ts`, `sisa.ui/src/modules/jobs/presentation/sync/referenceCache.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` ya persisten `status_attribute` offline y lo propagan por bootstrap/pull/cache local
- `sisa.ui/utils/statuses.ts`, `sisa.ui/app/Home.tsx`, `sisa.ui/app/jobs/index.tsx` y las pantallas CRUD de estados dejan de depender del nombre visible como fuente principal y usan `status_attribute` para filtros, tonos y resolucion de cierre
- `sisa.web/src/services/statusesService.ts`, `sisa.web/src/types/domain.ts`, `sisa.web/src/lib/statusAttributes.ts`, `sisa.web/src/pages/ClientsPage.tsx` y `sisa.web/src/pages/DashboardPage.tsx` alinean la web online al mismo contrato, consumiendo `status_attribute` en filtros y presentacion en vez de heuristicas por label
- `sisa.web/src/pages/StatusesPage.tsx`, `sisa.web/src/services/statusesService.ts`, `sisa.web/src/App.tsx` y `sisa.web/src/navigation/app-navigation.ts` crean la ventana online de configuracion de estados con CRUD para `label`, `background_color`, `order_index` y `status_attribute`; en paralelo `sisa.ui/app/statuses/create.tsx`, `sisa.ui/app/statuses/[id].tsx`, `sisa.ui/app/statuses/index.tsx` y `sisa.ui/app/statuses/viewModal.tsx` dejan la app offline coherente con el mismo set de atributos obligatorios
- la ventana de configuracion ahora tambien explica cada atributo con descripcion humana y la web suma reorder visual drag-and-drop; la app reemplaza la paleta horizontal por una seleccion vertical/en grilla para que color y atributo se editen con la misma lectura operativa en ambos canales
- ajuste posterior de UX: la web unifica tabla y orden visual en una sola grilla, moviendo filas desde el extremo derecho sin interferir con abrir el editor; la app replica el patron de `prioridades` en la misma pantalla y corrige un crash en dashboard causado por una referencia rota a `normalizeText` dentro de `sisa.ui/utils/statuses.ts`
- refinamiento final: la web ya soporta drag-and-drop real en la misma tabla de estados y confirma cambios de `status_attribute` antes de persistirlos; la app confirma esos cambios en el editor y compacta la altura de cada fila para mostrar menos ruido visual sin perder reorder ni metadata de sync
- ajuste de limpieza posterior: la tabla web de estados deja solo el drag handle en el extremo derecho para no duplicar reorder con flechas, y la app extiende el patron de confirmacion a prioridades cuando cambia la semantica de costo (`cost_type`/`cost_value`) por su impacto en costos parciales y cierres
- mejora de alta de estados: crear un estado ya no pide posicion; backend, app y web lo agregan automaticamente al final del flujo actual, y ambas plataformas amplian notablemente la paleta de colores disponible para configuracion visual
- paridad de prioridades: se aplica la misma regla de alta al final sin pedir posicion, la app deja de editar `order_index` manualmente y la web suma una ventana live de prioridades con CRUD, reorder visual y confirmacion de impacto economico cuando cambia el costo
- refinamiento de prioridades: la lista movil vuelve a enfatizar drag-and-drop con filas compactas que muestran solo color, nombre y costo; ademas app y web agregan paletas amplias en el editor y una pista explicita del simbolo monetario/porcentual aplicado al costo
- cierre de prioridades: el simbolo de costo ahora vive dentro del input en web y app, para que `%` y `$` se lean como parte del valor editable y no solo como ayuda externa
- baseline de cierre operativo: cuando un trabajo entra en `completed`, `billable`, `invoiced`, `paid` o `cancelled`, la API bloquea mutaciones sobre job/item/worklog/adjuntos y sync; ademas app y web deshabilitan visualmente las acciones principales de edicion, alta, borrado y adjuntos, dejando solo la lectura y el cambio de estado del trabajo

Riesgo cubierto:

- evitar que facturacion, liberacion de trabajos, costos parciales y cierres operativos sigan atados a labels personalizables por empresa, algo que rompe semantica apenas cambian nombres visibles como `Facturado parcial`, `Cobrado` o `Esperando materiales`
- evitar tambien que estados comerciales como `Cotizado` o `Cotizacion aprobada` queden mezclados con heuristicas abiertas o sin semantica obligatoria, y reducir drift entre `label`, `name` y `code` dejando una sola fuente visible canonica

Puntos ciegos conocidos:

- la inferencia legacy se mantiene solo como compatibilidad temporal; para no dejar nulos, la migracion cae en `completed` si `is_final=1` y en `pending` en el resto cuando no logra inferir mejor, asi que conviene revisar despues cualquier estado viejo ambiguo que quede con semantica demasiado generica

Validacion parcial:

- `composer dump-autoload` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Services/StatusAttributeRegistryTest.php tests/Models/StatusTest.php tests/Controllers/StatusControllerTest.php tests/Services/JobStatusResolverTest.php` en `sisa.api` -> PASS con la linea de ruido de conexion a base de datos ya documentada en baseline
- `vendor/bin/phpunit` en `sisa.api` -> PASS con la linea de ruido de conexion a base de datos ya documentada en baseline
- lint PHP de `src/Models/Status.php`, `src/Services/JobStatusResolver.php`, `src/Services/StatusAttributeRegistry.php`, `scripts/migrations/statuses-phase3.php` -> PASS
- lint recursivo de `src/**/*.php` y `scripts/**/*.php` en `sisa.api` -> PASS con warnings legacy preexistentes de `use` redundantes en algunos scripts fuera de este hito
- `npx eslint ...` sobre archivos tocados de `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS
- `npm run check:sync-smoke` en `sisa.ui` -> PASS
- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS
- `npm run lint` en `sisa.web` despues de sumar `StatusesPage` -> PASS
- `npm run build` en `sisa.web` despues de sumar `StatusesPage` -> PASS

## Avance parcial - lista de clientes con saldo contable y costos parciales finalizados

Estado: en progreso

Que cambio:

- `sisa.api/src/Models/Clients.php` y `sisa.api/src/Controllers/ClientsController.php` hacen que `GET /clients` entregue por endpoint `accounting_balance`, `unpaid_invoices_total`, `charge_client_payments_total`, `open_jobs_partial_cost_total` y el alias `finalized_jobs_partial_cost_total`, calculados por cliente y listos para ordenar desde API con `sort_by` y `sort_direction`
- el saldo contable ahora sale de facturas emitidas/pagadas aun pendientes mas cargos imputados al cliente, mientras que el costo parcial suma work logs de trabajos ya finalizados valorizados por tarifa, prioridad y cantidad de participantes
- `sisa.api/tests/Models/ClientsTest.php` y `sisa.api/tests/Controllers/ClientsControllerTest.php` cubren tanto los nuevos agregados como la aceptacion de columnas ordenables en el endpoint
- `sisa.ui/contexts/ClientsContext.tsx`, `sisa.ui/src/modules/jobs/data/repositories/SQLiteClientsRepository.ts`, `sisa.ui/src/modules/jobs/data/db/schema.ts`, `sisa.ui/src/modules/jobs/data/db/jobsMigrations.ts` y `sisa.ui/app/clients/index.tsx` persisten esos totales offline, corrigen la lectura a trabajos finalizados y ahora tambien piden el orden remoto al backend segun la columna elegida
- `sisa.web/src/services/clientsService.ts`, `sisa.web/src/types/domain.ts` y `sisa.web/src/pages/ClientsPage.tsx` llevan las mismas columnas a la web administrativa y exponen orden por nombre, fechas, saldo contable y costos parciales finalizados

Riesgo cubierto:

- evitar que la lista de clientes siga reconstruyendo saldo con heuristicas incompletas en UI o esconda el costo parcial de trabajos finalizados, dejando administracion sin una lectura rapida y ordenable del frente economico por cliente

Puntos ciegos conocidos:

- el costo parcial finalizado depende de work logs con `tariff_id`; si quedan logs legacy sin tarifa, seguiran aportando `0` hasta ser normalizados o valorizados por una regla historica dedicada

Validacion parcial:

- `vendor/bin/phpunit tests/Models/ClientsTest.php tests/Controllers/ClientsControllerTest.php` en `sisa.api` -> PASS
- `npx eslint "app/clients/index.tsx" "contexts/ClientsContext.tsx" "src/modules/jobs/data/repositories/SQLiteClientsRepository.ts" "src/modules/jobs/data/db/schema.ts" "src/modules/jobs/data/db/jobsMigrations.ts"` en `sisa.ui` -> PASS
- `npm run lint` en `sisa.web` -> PASS

## Avance parcial - `sisa.web` pasa a shell responsive con mapa completo de modulos

Estado: en progreso

Que cambio:

- `sisa.web/src/navigation/app-navigation.ts` centraliza el mapa completo del panel web y ya expone todos los dominios pedidos para administracion: `Finanzas`, `Comercial`, `Operaciones`, `Tracking`, `Sistema` y `Nucleo`, aunque varios modulos sigan en fase placeholder
- `sisa.web/src/App.tsx` y `sisa.web/src/pages/ModulePlaceholderPage.tsx` crean rutas reales para esos modulos faltantes, de modo que el menu ya no es una promesa parcial sino una estructura navegable completa lista para crecer sin rearmar la shell
- `sisa.web/src/components/app-shell.tsx` deja el sidebar previo y pasa a una navegacion mas actual: menu completo, badges de estado, cierre automatico al navegar, bloqueo de scroll cuando el drawer movil esta abierto y acceso responsive con boton hamburguesa
- `sisa.web/src/components/app-icon.tsx` amplia el set de iconos para cubrir el menu entero, y `sisa.web/src/app/globals.css` endurece comportamiento responsive con sidebar off-canvas en mobile, topbar mas estable y cards placeholder consistentes
- como plus funcional, `sisa.web/src/pages/JobsPage.tsx` y `sisa.web/src/pages/InvoicesPage.tsx` mantienen vivo el puente `trabajo -> factura`, pero ahora dentro de una shell que ya refleja el sistema administrativo completo y no solo los modulos implementados hasta hoy

Riesgo cubierto:

- evitar que la web siga creciendo con navegacion incompleta, deuda de IA por modulos invisibles y comportamiento flojo en mobile/tablet; esta pasada deja la estructura de informacion completa y una base de shell mucho mas alineada con estandares actuales de aplicaciones web

Puntos ciegos conocidos:

- el menu ya esta completo y responsive, pero varios modulos todavia son placeholders navegables; el siguiente valor fuerte sigue estando en profundizar funcionalidad real sobre esa estructura, no en seguir expandiendo mas superficie vacia

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - work logs alineados a tarifa real en API y web

Estado: en progreso

Que cambio:

- `sisa.api/src/Controllers/WorkLogsController.php`, `sisa.api/src/Models/WorkLogs.php` y `sisa.api/src/Services/SyncEventGenerator.php` agregan soporte explicito para `tariff_id` en work logs, validando que la tarifa pertenezca a la empresa del trabajo y usando su `name` como snapshot en `work_type`
- `sisa.api/scripts/migrations/worklogs-tariff-id-phase28.php` deja lista la migracion de esquema para sumar `work_logs.tariff_id` en instancias existentes
- `sisa.api/tests/Controllers/WorkLogsControllerTest.php` se actualiza para cubrir la creacion de work logs con tarifa y para evitar el ruido de dependencias no necesarias durante el test
- `sisa.web/src/services/referenceCatalogsService.ts`, `sisa.web/src/services/workLogsService.ts`, `sisa.web/src/types/domain.ts` y `sisa.web/src/pages/JobsPage.tsx` cambian el editor de work log de texto libre a seleccion por tarifa, mostrando nombre y costo y persistiendo `tariff_id` real hacia backend

Riesgo cubierto:

- evitar que el tipo de trabajo quede como string arbitrario y pierda vinculacion con la entidad de tarifas que despues alimenta costo, informe y facturacion

Puntos ciegos conocidos:

- ahora el work log ya guarda `tariff_id`, pero la factura todavia no calcula automaticamente montos desde esa tarifa; ese cierre comercial sigue siendo la siguiente capa fuerte

Validacion parcial:

- `vendor/bin/phpunit tests/Controllers/WorkLogsControllerTest.php` en `sisa.api` -> PASS
- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - adjuntos operativos visibles en pagos y recibos con marca contable

Estado: en progreso

Que cambio:

- `sisa.web/src/types/domain.ts`, `sisa.web/src/services/invoicesService.ts` y `sisa.web/src/services/receiptsService.ts` empiezan a transportar `attached_files` de `payments` y `receipts` como estructura tipada en la web, preservando la metadata `is_invoice`
- `sisa.web/src/pages/PaymentsPage.tsx` suma carga, listado, preview y remocion de adjuntos del pago, y ademas deja marcar cada archivo como `Factura contable` para diferenciar comprobantes que despues sirven al contador/a
- `sisa.web/src/pages/ReceiptsPage.tsx` suma el mismo flujo base de adjuntos para recibos, manteniendo trazabilidad documental del cobro desde la propia web
- esta pasada reutiliza el preview autenticado ya armado en `filePreviewService`, evitando abrir binarios protegidos sin token y manteniendo consistencia con jobs/worklogs

Riesgo cubierto:

- evitar que pagos y recibos sigan siendo movimientos economicos sin respaldo documental visible desde la web y evitar perder la marca de adjunto-factura que luego alimenta la presentacion contable

Puntos ciegos conocidos:

- pagos y recibos ya soportan adjuntos desde la web, pero todavia no existe una bandeja administrativa transversal de adjuntos por entidad/periodo ni una exportacion contable dedicada de esos comprobantes marcados como factura

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - bandeja transversal de archivos y filtro contable en pagos

Estado: en progreso

Que cambio:

- `sisa.web/src/pages/AttachmentsPage.tsx` convierte `Archivos` en modulo real y ya no placeholder: arma una bandeja transversal con adjuntos de `jobs`, `job_items`, `work_logs`, `payments` y `receipts`, con filtros por entidad, busqueda y preview autenticada
- `sisa.web/src/navigation/app-navigation.ts` y `sisa.web/src/App.tsx` promueven `Archivos` a modulo vivo dentro del menu de `Operaciones`, dejando la ruta ya operativa en la shell completa
- `sisa.web/src/pages/PaymentsPage.tsx` no solo permite marcar adjuntos como `Factura contable`, sino que ahora esa distincion alimenta el filtro de la bandeja transversal para aislar rapidamente comprobantes relevantes para contador/a
- la bandeja unifica documentacion tecnica y economica en una sola vista administrativa, aunque los adjuntos de pagos/recibos sigan viniendo del campo `attached_files` y los operativos de `file_attachments`

Riesgo cubierto:

- evitar que cada entidad siga manejando adjuntos en silos y obligue a buscar comprobantes de manera manual entre modulos separados cuando toca auditoria, presentacion contable o control operativo

Puntos ciegos conocidos:

- la bandeja ya es transversal, pero para pagos/recibos hoy muestra mejor contexto de entidad que metadata rica del archivo; si hace falta una experiencia mas profunda, el siguiente salto natural es exponer metadata de `files` por lote desde API

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - metadata real de archivos para pagos, recibos y bandeja documental

Estado: en progreso

Que cambio:

- `sisa.api/src/Controllers/FilesController.php` y `sisa.api/src/Routes/api.php` agregan `GET /files?ids=...` para recuperar metadata por lote de archivos autenticados sin descargar el binario completo
- `sisa.web/src/services/filesService.ts` suma `listFilesMetadata(...)` y la web empieza a enriquecer adjuntos economicos con `original_name`, `file_type` y `file_size`
- `sisa.web/src/pages/PaymentsPage.tsx` y `sisa.web/src/pages/ReceiptsPage.tsx` ya muestran nombre real del archivo, tipo y tamano dentro del editor de adjuntos, en vez de dejar solo `Archivo #id`
- `sisa.web/src/pages/AttachmentsPage.tsx` usa la metadata batch para mejorar especialmente la parte economica de la bandeja transversal, de modo que contador/a y administracion ya vean contexto documental mas util sin abrir cada archivo a ciegas

Riesgo cubierto:

- evitar una UX pobre donde pagos y recibos solo expongan ids tecnicos de archivos, dificultando auditoria, seleccion de comprobantes y presentacion contable

Puntos ciegos conocidos:

- esta pasada mejora metadata de archivos economicos; si despues queremos una bandeja todavia mas fuerte, conviene agregar fecha de upload, usuario cargador y categoria explicita tambien para pagos/recibos desde backend

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - costos valorizados de trabajos y shell lateral mas limpia

Estado: en progreso

Que cambio:

- `sisa.web/src/pages/JobsPage.tsx` deja de mostrar un tab de costos superficial y pasa a calcular costo real estimado de cada work log desde `tariff_id` y su duracion, consolidando total valorizado, base facturable, costo interno, costo visible al cliente, tarifa media y cobertura sin tarifa
- la misma vista agrega desgloses por tarifa y por item, mas alertas para work logs sin tarifa, para que el modulo de trabajos ya sirva como lectura economica previa a informe y factura
- el puente a `Facturas` ahora arrastra monto billable y minutos billables via query params, y `sisa.web/src/pages/InvoicesPage.tsx` prearma el item comercial con ese costo sugerido en vez de dejar solo la descripcion del trabajo
- `sisa.web/src/components/app-shell.tsx` y `sisa.web/src/app/globals.css` corrigen el menu lateral: grupos colapsables, persistencia de expansion, apertura garantizada del grupo activo, desaparicion del overflow horizontal y scrollbar izquierda mas discreta para el sidebar
- `sisa.web/src/app/globals.css` tambien reemplaza el aspecto default de scrollbars por una variante mas fina tipo capsula, con track transparente y thumb que aparece al interactuar en sidebar/contenido

Riesgo cubierto:

- evitar que la web siga sin una lectura economica usable del trabajo y evitar que el shell lateral siga metiendo scroll horizontal y ruido visual cuando la cantidad de modulos crece

Puntos ciegos conocidos:

- esta pasada deja costo valorizado operativo desde tarifas, pero todavia no suma materiales, pagos imputables al trabajo u otros costos indirectos; esa parte queda como siguiente capa de rentabilidad fina

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - nuevos bloques UI extraidos como componentes reutilizables en castellano

Estado: en progreso

Que cambio:

- `sisa.web/src/components/tarjeta-resumen-metrica.tsx`, `sisa.web/src/components/titulo-formulario.tsx`, `sisa.web/src/components/lista-adjuntos.tsx` y `sisa.web/src/components/modal-vista-previa-archivo.tsx` extraen los bloques nuevos mas repetidos en componentes reutilizables con naming en castellano y pensados para reaparecer en varios modulos
- `sisa.web/src/pages/JobsPage.tsx`, `sisa.web/src/pages/AttachmentsPage.tsx`, `sisa.web/src/pages/PaymentsPage.tsx` y `sisa.web/src/pages/ReceiptsPage.tsx` dejan de renderizar varias variantes ad hoc de tarjetas resumen, listas de adjuntos y previews de archivo, y pasan a consumir esas piezas compartidas
- `sisa.web/src/app/globals.css` incorpora variables de tema para scrollbar, tarjetas de resumen, tarjetas de adjuntos y tarjetas de costo, para que estos bloques nuevos no queden atados a colores hardcodeados y puedan personalizarse mejor a futuro

Riesgo cubierto:

- evitar que el crecimiento de la web deje duplicacion de JSX/CSS por todos lados y complique mantener consistencia visual o tematizacion cuando los mismos patrones aparecen en operaciones, finanzas y bandejas documentales

Puntos ciegos conocidos:

- esta pasada consolida los bloques nuevos mas repetidos; si mas adelante se decide una migracion completa de naming de toda la UI, convendra encarar una fase dedicada para componentes legacy que todavia conservan nombres en ingles

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - refresh automatico de token y carga reusable de adjuntos con drag and drop

Estado: en progreso

Que cambio:

- `sisa.web/src/services/authService.ts`, `sisa.web/src/types/domain.ts` y `sisa.web/src/contexts/session-context.tsx` agregan soporte para `session_id`, `device_uid`, expiracion del token y renovacion automatica via `POST /token/refresh` antes del vencimiento, sin pedir credenciales de nuevo
- la sesion web ahora intenta renovar el token en background con timer preventivo y tambien al volver foco/visibilidad si la expiracion esta cerca, usando el endpoint de refresh y el `X-Device-Uid` ya entregado por login
- `sisa.web/src/components/panel-carga-adjuntos.tsx` crea una pieza reusable en castellano para carga de archivos con drag and drop, boton estilizado, soporte multiple y modo especial opcional para marcar facturas de credito fiscal IVA
- `sisa.web/src/components/lista-adjuntos.tsx` y `sisa.web/src/components/modal-vista-previa-archivo.tsx` ahora soportan descarga, no solo preview/remocion, y `sisa.web/src/services/filePreviewService.ts` suma `descargarArchivoProtegido(...)`
- `sisa.web/src/pages/PaymentsPage.tsx`, `sisa.web/src/pages/ReceiptsPage.tsx`, `sisa.web/src/pages/JobsPage.tsx` y `sisa.web/src/pages/AttachmentsPage.tsx` adoptan estos flujos para que donde haya archivos tambien exista descarga directa y la carga se vea consistente y mas moderna
- `sisa.web/src/app/globals.css` suma la capa visual del dropzone reusable con estilo mas intencional y variables pensadas para futura tematizacion

Riesgo cubierto:

- evitar expiraciones de token que corten la sesion activa en uso normal y evitar que cada modulo vuelva a inventar su propio uploader de archivos, especialmente en el caso sensible de facturas aptas para credito fiscal IVA dentro de pagos

Puntos ciegos conocidos:

- la renovacion automatica ya existe del lado web, pero si a futuro queremos blindarla aun mas conviene sumarle reintento centralizado ante `401` en el cliente API y observabilidad ligera de refresh fallidos

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - galeria de archivos, refresh central en 401 e informe fiscal IVA

Estado: en progreso

Que cambio:

- `sisa.web/src/components/galeria-archivos.tsx` transforma el modulo `Archivos` en una grilla tipo explorador con tiles grandes, accion primaria contextual (`Abrir` o `Descargar`) y boton explicito de descarga
- `sisa.web/src/components/modal-vista-previa-archivo.tsx` ahora soporta tambien audio embebido, ademas de imagen, video y PDF; los tipos no embebibles siguen descargandose para abrir con aplicacion externa
- `sisa.web/src/lib/api-client.ts`, `sisa.web/src/lib/storage.ts` y `sisa.web/src/contexts/session-context.tsx` centralizan la renovacion de token tambien ante `401`, sincronizando storage/sesion sin pedir credenciales otra vez y evitando drift entre refresh preventivo y refresh por error de API
- `sisa.web/src/pages/AttachmentsPage.tsx` agrega export CSV del informe de credito fiscal IVA usando `is_invoice`, de modo que pagos marcados como factura ya alimentan una salida contable concreta
- `sisa.web/src/pages/JobsPage.tsx` y `sisa.web/src/pages/InvoicesPage.tsx` extienden la lectura economica del trabajo con pagos imputables al cliente y sugieren facturacion total de servicio + gastos recuperables; la factura prearmada ya puede llegar con ambos renglones

Riesgo cubierto:

- evitar una experiencia documental pobre en `Archivos`, evitar cortes de sesion por `401` cuando el refresh preventivo no alcanzo y evitar que la marca `is_invoice` quede sin salida util para contador/a

Puntos ciegos conocidos:

- el informe fiscal IVA hoy sale como CSV administrativo; si despues se necesita formato mas formal o imprimible, conviene sumarle export PDF/Excel especifico y mas metadata tributaria

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - informe fiscal IVA imprimible y rentabilidad mas legible

Estado: en progreso

Que cambio:

- `sisa.web/src/pages/AttachmentsPage.tsx` ahora suma una vista formal de `Informe credito fiscal IVA` dentro de la propia web, con resumen, tabla administrativa y salida `Imprimir / Guardar PDF`, ademas del CSV ya existente
- `sisa.web/src/components/galeria-archivos.tsx` y `sisa.web/src/components/modal-vista-previa-archivo.tsx` completan una UX documental mas cercana a explorador: grilla de tiles grandes, apertura media-aware y descarga consistente
- `sisa.web/src/pages/JobsPage.tsx` mejora la lectura de rentabilidad separando mano de obra valorizada, gastos recuperables/materiales, margen del servicio y margen operativo total
- la misma pestaña `Costos` ya lista pagos imputables al cliente como bloque propio, para que materiales/recuperos no queden escondidos adentro del numero total

Riesgo cubierto:

- evitar que el informe fiscal quede limitado a export plano y evitar que la rentabilidad del trabajo siga mezclando mano de obra y recuperos sin una composicion entendible para administracion

Puntos ciegos conocidos:

- la capa de materiales/recuperos hoy se apoya en pagos imputables al cliente; si despues existe una entidad dedicada de materiales por job, convendra sumarla al mismo tablero para una rentabilidad todavia mas precisa

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - servicio central de aplicaciones de recibos y PDF de recibo

Estado: en progreso

Que cambio:

- `sisa.api/src/Services/ReceiptApplicationService.php` centraliza la aplicacion parcial/multiple de recibos contra facturas con validaciones transaccionales, control de saldo de recibo/factura, compatibilidad cliente-company y soft-delete de aplicaciones
- `sisa.api/src/Controllers/InvoicesController.php` y `sisa.api/src/Controllers/ReceiptsController.php` empiezan a apoyarse en el servicio central para listar/vincular/desvincular aplicaciones en vez de seguir dispersando reglas de negocio por controlador
- `sisa.api/src/Controllers/ReceiptsController.php` agrega `exportReceiptPdf`, registra `files` + `reports` y actualiza `receipts.receipt_pdf_file_id`; `sisa.api/src/Routes/api.php` expone `POST /receipts/{id}/report/pdf`
- `sisa.api/scripts/migrations/receipts-reports-and-application-indexes-phase29.php` agrega `receipt_pdf_file_id` e indices operativos para `invoice_receipt_payments`
- `sisa.api/tests/Services/ReceiptApplicationServiceTest.php` cubre escenarios clave de aplicacion parcial, multiples facturas, rechazos por exceso/cliente/company, detach, cambio de estado y no-duplicacion contable de asientos
- `sisa.api/src/Services/AccountingSummaryService.php` deja de sumar recibos aplicados de forma inflada cuando una misma cobranza tiene multiples aplicaciones, usando `applied_amount` real de `invoice_receipt_payments`

Riesgo cubierto:

- evitar drift entre controladores, aplicaciones inconsistentes de recibos, overflow de montos aplicados y duplicacion conceptual del dinero al distribuir cobranzas contra facturas

Puntos ciegos conocidos:

- la base del servicio central ya esta y los tests principales corren, pero todavia queda margen para seguir reemplazando helpers legacy de `ReceiptsController` por el servicio central y para enriquecer mas el reporte/estado de cuenta del cliente si aparece un flujo PDF especifico dedicado a eso

Validacion parcial:

- `vendor/bin/phpunit tests/Services/ReceiptApplicationServiceTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Regression/InvoiceReceiptsAndPaymentsFlowRegressionTest.php` en `sisa.api` -> PASS
- `php -l src/Services/ReceiptApplicationService.php` -> PASS
- `php -l src/Controllers/ReceiptsController.php` -> PASS
- `php -l src/Controllers/InvoicesController.php` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - `sisa.web` deja de navegar como CRUD plano y arranca el modulo operativo real de trabajos

Estado: en progreso

Que cambio:

- `sisa.web/src/components/app-shell.tsx` y `sisa.web/src/app/globals.css` reorganizan la web por dominios (`Nucleo`, `Comercial`, `Operaciones`, `Finanzas`, `Sistema`) y dejan visibles los huecos planificados, evitando seguir creciendo con botones sueltos en el sidebar
- `sisa.web/src/pages/JobsPage.tsx` deja el modal-CRUD como patron principal y pasa a una vista master-detail para escritorio: filtros persistentes a la izquierda, detalle operativo a la derecha y tabs internas para `items`, `work logs`, `archivos`, `historial`, `informe` y `costos`
- la misma pasada profundiza `jobs` con CRUD inline de `job_items` dentro del detalle y suma CRUD web inicial de `work_logs`, para que la web ya pueda registrar horas, tipo de trabajo, descripcion tecnica, item asociado y visibilidad cliente directo contra la API comun
- `sisa.web/src/services/workLogsService.ts` y `sisa.web/src/types/domain.ts` agregan la capa tipada de work logs/participantes/adjuntos necesaria para seguir cerrando el circuito `trabajo -> informe -> factura`

Riesgo cubierto:

- evitar que `sisa.web` siga creciendo como panel de CRUDs aislados sin un modulo operativo central capaz de concentrar backlog, detalle, subtareas y bitacora tecnica sobre la misma entidad de trabajo

Puntos ciegos conocidos:

- esta pasada deja lista la estructura correcta del modulo de trabajos, pero todavia no resuelve upload/preview real de archivos, historial auditado backend ni export de informe tecnico PDF desde la propia vista web

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - `sisa.web` conecta informes PDF y adjuntos reales dentro de trabajos

Estado: en progreso

Que cambio:

- `sisa.web/src/services/jobReportsService.ts` conecta la web con `POST /jobs/{id}/report/pdf` y normaliza tanto respuestas JSON con `file_id` / `download_url` como respuestas binarias directas, para abrir el informe PDF del trabajo desde la propia vista detalle
- `sisa.web/src/services/fileAttachmentsService.ts`, `sisa.web/src/services/jobsService.ts`, `sisa.web/src/services/jobItemsService.ts`, `sisa.web/src/services/workLogsService.ts` y `sisa.web/src/types/domain.ts` amplian la capa web para manejar `uuid`, adjuntos enriquecidos y lectura del detalle real de trabajo con inventario de archivos
- `sisa.web/src/pages/JobsPage.tsx` deja la tab `Informe` como placeholder y la vuelve accionable con boton de apertura de PDF, mientras que la tab `Archivos` ya lista adjuntos del trabajo y de work logs, permite abrirlos y tambien subir nuevos archivos enlazandolos a `job` o `work_log` via `files` + `file_attachments`
- `sisa.web/src/app/globals.css` suma estilos para cards de adjuntos, upload y acciones de desvinculacion, manteniendo el patron escritorio del modulo operativo

Riesgo cubierto:

- evitar que la web se quede en una estructura linda pero vacia para informe y archivos; ahora esos dos frentes ya pisan endpoints reales del backend y empiezan a cerrar el circuito documental del trabajo

Puntos ciegos conocidos:

- esta pasada cubre adjuntos de `job` y `work_log`, pero todavia no expone upload/listado dedicado para `job_item`; tampoco agrega preview rica por tipo de archivo ni auditoria historica completa de adjuntos

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - `sisa.web` cierra mejor el puente trabajo-informe-factura y corrige shell encimado

Estado: en progreso

Que cambio:

- `sisa.web/src/pages/JobsPage.tsx` amplia la tab `Archivos` para cubrir tambien adjuntos de `job_items`, no solo de `job` y `work_logs`, y suma preview autenticada rica por tipo (`imagen`, `video`, `pdf` y fallback de descarga)
- `sisa.web/src/services/filePreviewService.ts` agrega descarga autenticada a blob para que la web pueda previsualizar archivos protegidos por token sin depender de abrir `GET /files/{id}` directo en una nueva pestana
- `sisa.web/src/pages/JobsPage.tsx` ahora tambien ofrece puente directo a facturacion desde el detalle, la tab `Informe` y la tab `Costos`, enviando `job_id` y `client_id` hacia `InvoicesPage`
- `sisa.web/src/pages/InvoicesPage.tsx` ya consume ese contexto via query params y abre una factura nueva prearmada con el trabajo seleccionado como item comercial inicial
- `sisa.web/src/app/globals.css` endurece el shell de escritorio con sidebar y topbar sticky/scroll-safe para evitar que contenido o dropdowns queden visualmente encimados al menu lateral durante navegacion larga

Riesgo cubierto:

- evitar que el modulo de trabajos quede cortado justo antes del cierre comercial y evitar drift visual del shell cuando el panel crece en altura y densidad operativa

Puntos ciegos conocidos:

- la factura prearmada nace con el trabajo asociado y descripcion inicial, pero todavia no calcula automaticamente tarifa/monto desde work logs o costos del trabajo; eso sigue siendo el siguiente cierre funcional fuerte

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - tecnica monetaria separada por canal: en vivo para web, edicion simple para mobile

Estado: en progreso

Que cambio:

- `sisa.web/src/components/money-input.tsx` y `sisa.web/src/lib/utils.ts` consolidan la tecnica rica para navegador: prefijo fijo separado, formateo en vivo con miles, coma decimal visible, parseo tolerante a `,`/`.`/`$`, y storage normalizado tipo `1234.56`
- en `sisa.ui` la experiencia final vuelve a alinearse con web: `MoneyMaskedInput` mantiene `$` fijo dentro del mismo campo, aplica puntos de miles y coma decimal sobre el propio valor visible, y normaliza storage decimal. Se descarto el experimento de preview separado porque seguia dejando la sensacion de input “partido”
- la regla general queda asi: web y mobile comparten la misma semantica visual de dinero (prefijo fijo + miles + coma decimal), pero mobile usa una estrategia de caret mas pragmatica para React Native, priorizando escritura lineal y seleccion total al entrar

Riesgo cubierto:

- evitar que una mascara rica de dinero rompa la escritura tactil en mobile, manteniendo a la vez una UX web mas asistida para caja/cobros

Puntos ciegos conocidos:

- React Native sigue siendo mas sensible que web al manejo fino del cursor; si reaparece drift en casos de edicion intermedia, conviene tratar ese caso puntual en `MoneyMaskedInput` antes que volver a degradar la UX visual del dinero

Validacion parcial:

- `npx eslint "app/payments/[id].tsx" "app/payments/create.tsx" "app/receipts/[id].tsx" "app/receipts/create.tsx" "app/invoices/create.tsx" "app/invoices/[id].tsx" "app/products_services/create.tsx" "app/products_services/[id].tsx" "components/MoneyMaskedInput.tsx" "utils/moneyInput.ts"` en `sisa.ui` -> PASS

## Avance parcial - `sisa.web` profundiza trabajos online-first sobre la API comun

Estado: en progreso

Que cambio:

- `sisa.web/src/pages/JobsPage.tsx` deja de ser solo un CRUD minimo por ids sueltos y pasa a operar trabajos con contexto real de cliente, estado y carpeta, todo directo contra la API existente
- `sisa.web/src/services/statusesService.ts` y `sisa.web/src/services/foldersService.ts` agregan lectura online-first de referencias para que la web no dependa de inputs manuales de `status_id` o `folder_id`
- `sisa.web/src/services/jobItemsService.ts` suma CRUD directo de items de trabajo usando `/jobs/{jobId}/items`, permitiendo que la web gestione subtareas basicas sin pasar por sync offline ni por SQLite
- `sisa.web/src/types/domain.ts` y `sisa.web/src/app/globals.css` se amplian para soportar esta capa operativa web con entidades de estado/carpeta/item y una UI mas clara para backlog + detalle + subtareas

Riesgo cubierto:

- acercar la web al modelo real que pedis: online-first puro sobre la misma API comun, sin inventar un backend paralelo ni heredar el flujo mobile offline-first

Puntos ciegos conocidos:

- esta pasada deja mejor resueltos `jobs` y `job_items`, pero todavia no mete el mismo nivel de profundidad para `work_logs`, `appointments`, adjuntos ni relaciones mas avanzadas como grupos o root causes

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - nace `sisa.web` como panel React administrativo conectado a la API comun

Estado: en progreso

Que cambio:

- se inicializo `sisa.web/.git` como repo separado dentro del workspace y se creo una base React + TypeScript + Vite, evitando SSR y manteniendo la web como cliente online-first liviano
- `sisa.web/src/lib/api-client.ts` centraliza `Authorization: Bearer` y `X-Company-Id`, alineando la web con el contrato operativo ya usado por mobile
- `sisa.web/src/contexts/session-context.tsx` y `sisa.web/src/pages/LoginPage.tsx` cubren login, persistencia local de sesion y seleccion de empresa activa desde membresias aprobadas
- `sisa.web/src/pages/DashboardPage.tsx`, `sisa.web/src/pages/CompaniesPage.tsx`, `sisa.web/src/pages/ClientsPage.tsx`, `sisa.web/src/pages/ProvidersPage.tsx`, `sisa.web/src/pages/JobsPage.tsx`, `sisa.web/src/pages/InvoicesPage.tsx` y `sisa.web/src/pages/PaymentsPage.tsx` dejan un panel administrativo inicial sobre la misma API para empresas, relaciones comerciales, trabajos, facturas, PDF y pagos base

Riesgo cubierto:

- evitar abrir una tercera implementacion de negocio separada: la web nace consumiendo la API central y respetando el corte `companies` vs `clients/providers`

Puntos ciegos conocidos:

- la primera pasada de `sisa.web` prioriza CRUD y operacion administrativa minima; todavia no incorpora permisos finos, receipts, adjuntos complejos ni diagnosticos profundos de archivos/reportes

Validacion parcial:

- `npm run lint` en `sisa.web` -> PASS
- `npm run build` en `sisa.web` -> PASS

## Avance parcial - reportes PDF de trabajos priorizan lectura operativa y cierre comercial limpio

Estado: en progreso

Que cambio:

- `sisa.api/src/Controllers/JobReportsController.php` rehizo la presentacion del PDF de trabajos para que cada bloque muestre cabecera mas profesional, chips operativos de fecha/horario/tecnicos y tarjetas de worklogs/citas mas legibles
- los worklogs del PDF ya no muestran dinero, tarifa ni tipo de trabajo; ahora priorizan descripcion, fecha, horario, duracion y tecnicos, alineado con lo que se ve en la app como lectura operativa de campo
- el unico importe visible por trabajo queda concentrado en `Total del trabajo`, y el cierre del informe agrega un resumen final con total de servicios, gastos cobrables al cliente y total general del informe
- en una segunda pasada de ajuste visual, los worklogs tambien dejaron de mostrar duracion y el cierre del informe ya no expone `Horas trabajadas`, para mantener el PDF mas comercial y menos tecnico
- se ajusto el paginado del PDF para que el contenido no invada el pie: las plantillas reservan mas margen inferior y la numeracion ya no depende del placeholder HTML literal sino de `canvas->page_text(...)` en Dompdf, corrigiendo que salieran `{PAGE_NUM}` y `{PAGE_COUNT}` sin resolver
- se compactaron margenes internos, separaciones entre bloques y reglas de `page-break-inside` del PDF detallado de trabajos para que el contenido fluya de forma mas continua en A4, sin dejar huecos grandes ni empujar bloques enteros a la pagina siguiente salvo cuando ya no queda espacio util antes del pie
- una pasada adicional reemplazo la grilla basada en tabla dentro de cada trabajo por secciones de flujo continuo (`div`), porque Dompdf estaba conservando bloques demasiado rigidos y eso generaba paginas con huecos grandes, saltos desparejos y casos donde el contenido terminaba peleando con el pie
- `sisa.api/tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php` se actualizo para reflejar la nueva semantica visual del reporte y proteger el cambio de etiquetas/estructura principal

Riesgo cubierto:

- evitar que el PDF mezcle detalle operativo con ruido economico dentro de cada worklog y mejorar la consistencia entre la lectura movil del trabajo y su version exportada para cliente o administracion

Puntos ciegos conocidos:

- el cierre final resume correctamente servicios y gastos cobrables, pero la variante detallada sigue dependiendo de los datos historicos ya normalizados de `final_amount` / `worklog_total_amount`; si un trabajo arrastra montos legacy inconsistentes, el total visible seguira reflejando esa fuente

Validacion parcial:

- `php -l "src/Controllers/JobReportsController.php"` en `sisa.api` -> PASS
- `vendor/bin/phpunit "tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php"` en `sisa.api` -> PASS

## Avance parcial - export de PDF deja de consultar `jobs.type_of_work` inexistente

Estado: en progreso

Que cambio:

- el diagnostico copiado desde `sisa.ui/app/invoices/[id].tsx` confirmo la causa raiz actual del `500` al exportar la factura `78`: el backend devolvia `PDOException` con `Unknown column 'j.type_of_work' in 'SELECT'`
- `sisa.api/src/Controllers/InvoicesController.php` ya no consulta `j.type_of_work` dentro de `enrichInvoiceItemsForPdf()`; el detalle `Tipo:` del trabajo para el PDF pasa a partir de `work_logs.work_type`, y cuando ese valor referencia una tarifa numerica la app resuelve y muestra el nombre de la entidad `tariffs` en vez del id crudo
- el siguiente intento copiado desde frontend destapo una segunda causa real del mismo flujo: la consulta de resumen de work logs usaba sintaxis SQLite (`datetime(started_at, '+' || duration_minutes || ' minutes')`) dentro de MariaDB; esa expresion ahora se reemplazo por `DATE_ADD(started_at, INTERVAL duration_minutes MINUTE)`
- con esto, la exportacion deja de depender de una columna legacy ausente en algunos ambientes y se alinea mejor con el esquema offline-first actual donde el work log conserva la referencia operativa al tipo/tarifa aplicada
- con esto, la exportacion tambien deja de mezclar sintaxis de motores SQL incompatibles dentro del mismo endpoint de PDF

Riesgo cubierto:

- evitar que la generacion de PDF falle por drift de esquema entre ambientes al renderizar items ligados a trabajos

Puntos ciegos conocidos:

- el PDF ahora toma un `work_type` representativo desde work logs; si un trabajo mezcla varias tarifas/tipos en distintos logs, el detalle mostrado sigue siendo una aproximacion breve y no una enumeracion completa

Validacion parcial:

- `php -l "src/Controllers/InvoicesController.php"` en `sisa.api` -> PASS

## Avance parcial - diagnostico de PDF de facturas copiable desde alertas frontend

Estado: en progreso

Que cambio:

- `sisa.ui/app/invoices/[id].tsx` ahora captura un diagnostico tecnico del flujo de PDF desde la app con stage, metodo, url, `invoiceId`, `companyId`, `file_id`, `report_id`, request body, status HTTP, content-type, response body y warning backend cuando existe
- las alertas de errores y advertencias del flujo de factura PDF ahora exponen accion `Copiar detalle`, que usa portapapeles del dispositivo para poder pegar el diagnostico exacto desde el mismo globo sin depender de consola remota o Metro
- cuando el backend devuelve `warning` pero el PDF igual queda generado, la app deja visible esa advertencia y permite copiarla; si el servidor genera el PDF pero la app no logra abrirlo o descargarlo, tambien muestra un alert copiable con el contexto tecnico acumulado del intento

Riesgo cubierto:

- evitar que QA o soporte queden atados a capturas parciales de Hermes/Expo cuando el problema real esta en el request, la respuesta backend o la descarga posterior del archivo

Puntos ciegos conocidos:

- el diagnostico copiado refleja lo que ve la app en ese intento, pero no reemplaza logs nativos del visor PDF ni logs del servidor si la falla ocurre fuera del fetch o del almacenamiento local del archivo

Validacion parcial:

- `npx eslint "app/invoices/[id].tsx"` en `sisa.ui` -> PASS

## Hito completado - generacion persistente de PDF de facturas con diagnostico visible

Estado: completado

Que cambio:

- la revision profunda del flujo de facturas encontro una causa raiz real en backend: `InvoicesController::exportInvoicePdf()` resolvia el storage con `__DIR__ . '/../../../uploads/reports'`, una ruta que desde `sisa.api/src/Controllers` subia un nivel de mas y podia terminar fuera de `sisa.api/`; eso hacia que en ciertos entornos el PDF no se escribiera fisicamente aunque desde la app solo se viera un fallo generico
- `sisa.api/src/Controllers/InvoicesController.php` ahora guarda el PDF en una ruta absoluta segura dentro de `sisa.api/uploads/invoices`, crea el directorio si falta, valida `is_writable`, verifica que el archivo exista y tenga tamano valido, y devuelve JSON uniforme con `success`, `invoice_id`, `file_id`, `report_id`, `path`, `url`, `filename`, `mime` y `size`
- el mismo endpoint ya no deja errores silenciosos: cualquier fallo de libreria, escritura, registro en `files`, alta en `reports`, o persistencia de `invoice_pdf_file_id` devuelve `success=false` con `error` y `details` utiles; si la notificacion posterior falla, el PDF sigue considerandose generado y la respuesta vuelve con `warning` en vez de falsear toda la operacion
- `sisa.ui/app/invoices/[id].tsx` ahora envia `Authorization`, `X-Company-Id`, `invoice_id` y `company_id`, agrega logs de desarrollo con `invoiceId/url/metodo/status/responseBody/error`, refresca metadata despues de generar y muestra el mensaje real del backend en vez de tragarse el error detras de un alert generico
- como endurecimiento transversal, `sisa.api/src/Middleware/PermissionsMiddleware.php` ahora tambien resuelve `company_id` desde `X-Company-Id`, y `sisa.api/src/Models/Permission.php` incorpora `downloadInvoicePdf` al catalogo backend para evitar drift con la app
- queda documentado el runbook manual en `sisa.api/docs/invoice-pdf-generation-runbook.md`

Riesgo cubierto:

- evitar que la app reporte “no paso nada” cuando el problema real es ruta de escritura mal resuelta, carpeta no escribible o respuesta backend poco diagnostica, y asegurar que el PDF quede persistido con metadata consistente en `files`, `reports` e `invoices`

Puntos ciegos conocidos:

- si el `POST /invoices/{id}/report/pdf` termina bien pero `GET /files/{file_id}` devuelve `403`, la causa ya no es generacion sino permisos de descarga (`downloadFile`); el flujo ahora lo hace visible, pero la habilitacion final depende de los permisos del usuario en ese ambiente

Validacion parcial:

- `php -l "src/Controllers/InvoicesController.php"` en `sisa.api` -> PASS
- `php -l "src/Middleware/PermissionsMiddleware.php" && php -l "src/Models/Permission.php"` en `sisa.api` -> PASS
- `npx eslint "app/invoices/[id].tsx"` en `sisa.ui` -> PASS

## Avance parcial - startup bootstrap de referencias vuelve a ser parte real del bloqueo inicial

Estado: en progreso

Que cambio:

- el analisis del arranque mostro otra carrera: `BootstrapProvider` liberaba `isReady` despues del bloque critico de perfil/permisos/jobs, pero el payload `startup-bootstrap:*` de referencias (`statuses`, `clients`, `folders`, `providers`, etc.) se descargaba recien despues en un efecto post-ready; eso dejaba providers ya montados leyendo cache vieja o vacia y hacia que la app pareciera lista antes de terminar de hidratar datos base
- `sisa.ui/contexts/BootstrapContext.tsx` ahora pide y aplica el startup bootstrap dentro de `runBootstrap()` antes de marcar `isReady`, con fallback controlado a payload cacheado cuando existe
- la misma pasada ya no solo guarda el payload crudo: tambien mergea referencias en cache/SQLite y emite updates (`clients`, `statuses`, `folders`, `tariffs`, `providers`, `memberships`, `member_companies`, etc.) para que los providers dependientes no queden esperando otro refresh manual

Riesgo cubierto:

- evitar que la shell se habilite con referencias operativas incompletas, especialmente en primer ingreso o despues de cambiar de empresa, donde listas y selects podian abrirse antes de que terminara la hidratacion base

Puntos ciegos conocidos:

- siguen existiendo algunos consumers secundarios que todavia prefieren su propio fetch al detectar cambios (`products_services`, `payment_templates`), asi que esta pasada ataca la frontera de arranque y la hidratacion de referencias principales antes que una unificacion total de todos los providers

Validacion parcial:

- `npx eslint "contexts/BootstrapContext.tsx"` en `sisa.ui` -> PASS con 1 warning de estilo preexistente (`@typescript-eslint/array-type`)

## Avance parcial - detalle de trabajo deja de reejecutar reloads en cascada

Estado: en progreso

Que cambio:

- el ultimo log ya no muestra queries pesadas de por si: muestra muchas consultas chicas del detalle (`jobs`, `job_items`, `appointments`) esperando en cola porque se relanzan una y otra vez. En particular, se ve el patron repetido `getAll job_items` + `getAll appointments` + `getFirst jobs` seguido de `useJobDetail.reload:success` muchas veces seguidas
- `sisa.ui/src/modules/jobs/presentation/hooks/useJobDetail.ts` ahora tiene debounce de eventos, join-inflight y `skip-recent-success` para no refrescar el mismo detalle varias veces en rafaga
- `sisa.ui/src/modules/jobs/presentation/hooks/useJobItems.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/useJobAppointments.ts` ahora tienen join-inflight + debounce de eventos, para que cambios de refresh global no disparen varias lecturas identicas del mismo `jobUuid`
- esto apunta directamente a la UX percibida al entrar al detalle: si una query tarda `sqlMs=10/20ms` pero `waitMs=2000ms`, el problema no es el SQL sino la multiplicacion de reloads concurrentes o encadenados

Riesgo cubierto:

- evitar que una lluvia de `jobs-data-refresh` o focos/re-renders convierta lecturas livianas del detalle en un congelamiento visible para el usuario

Puntos ciegos conocidos:

- si aun queda jitter visual, el siguiente paso sera desacoplar `useJobDetail` de algunos secundarios del detalle y priorizar render minimo antes de `job_items` / `appointments`

Validacion parcial:

- `npx eslint "src/modules/jobs/presentation/hooks/useJobDetail.ts" "src/modules/jobs/presentation/hooks/useJobItems.ts" "src/modules/jobs/presentation/hooks/useJobAppointments.ts"` en `sisa.ui` -> PASS

## Avance parcial - bootstrap critico vuelve a ser frontera real antes de liberar la shell

Estado: en progreso

Que cambio:

- para UX comercial, la app no debe parecer lista mientras el bootstrap pesado todavia compite con detalle/jobs. En el codigo real, `BootstrapProvider` marcaba `criticalReady` demasiado temprano, antes de terminar `jobsBootstrap/jobsCheckpoint`; eso permitia que efectos posteriores de startup y refrescos secundarios arrancaran mientras la shell ya estaba navegable
- `sisa.ui/contexts/BootstrapContext.tsx` ahora mueve `criticalReady` al final del bloque critico, junto con `isReady`, y ademas evita que `startupBootstrapRequest` secundario arranque mientras `isBootstrapping` sigue activo o antes de `isReady`
- esto vuelve a alinear la implementacion con la expectativa UX: la carga inicial cubre el bootstrap critico, y lo secundario se difiere hasta despues de esa frontera en vez de pelear con las primeras pantallas

Riesgo cubierto:

- evitar que la shell se libere en una ventana intermedia donde todavia hay bootstrap/pull critico ocupando la cola SQLite y generando “tilde” al entrar a jobs/detalle

Puntos ciegos conocidos:

- sigue habiendo un warning preexistente de estilo en `contexts/BootstrapContext.tsx` (`Array<T>`), pero no afecta ejecucion ni performance

Validacion parcial:

- `npx eslint "contexts/BootstrapContext.tsx"` en `sisa.ui` -> PASS con 1 warning de estilo preexistente (`@typescript-eslint/array-type`)

## Avance parcial - singleflight endurecido a nivel global para evitar pulls/bootstrap paralelos

Estado: en progreso

Que cambio:

- el log siguiente mostro que el singleflight por `companyId` no alcanzaba: seguian naciendo muchas corridas paralelas de pull/bootstrap y el estado compartido seguia inflandose (`bootstrap:25`, `pull:32`), con repeticion brutal de `select last_checkpoint from sync_checkpoints ...`
- `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` pasa de singleflight por key a singleflight global de proceso (`sharedPullInFlight`), para que cualquier caller nuevo reutilice la misma corrida aunque llegue desde otra instancia o con distinto scope aparente
- `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` hace lo mismo con `sharedBootstrapInFlight`

Riesgo cubierto:

- cortar duplicacion accidental de procesos remotos completos que no deberian correr en paralelo en una sola app movil, priorizando UX fluida sobre paralelismo teorico

Puntos ciegos conocidos:

- si despues de esto sigue habiendo write amplification, el siguiente cuello ya no sera multiplicacion de corridas sino repeticion de escrituras auxiliares (`entity_snapshots`, `id_map`) dentro de una sola corrida real

Validacion parcial:

- `npx eslint "src/modules/jobs/presentation/hooks/usePullJobsSync.ts" "src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts"` en `sisa.ui` -> PASS

## Avance parcial - singleflight en pull/bootstrap y cache corta de checkpoint

Estado: en progreso

Que cambio:

- el ultimo log confirma que el throttle de `cleanupDuplicateRows()` funciono, pero quedo otro cuello muy claro: habia muchas invocaciones concurrentes/repetidas de `usePullJobsSync` y `useBootstrapJobsFromApi`, visibles en `shared-sync-activity` con contadores absurdamente altos (`bootstrap:25`, `pull:32`) y en multiples lecturas repetidas de `sync_checkpoints.last_checkpoint`
- `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` ahora usa singleflight por `companyId`: si ya hay un pull en curso, los siguientes callers se cuelgan de la misma `Promise` en vez de abrir otra corrida paralela. Ademas agrega cache corta e inflight dedupe para `getCheckpoint(...)`, reduciendo lecturas repetidas de `sync_checkpoints`
- `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` ahora tambien usa singleflight por `companyId`, evitando bootstrap paralelo del mismo scope
- con esto, el estado compartido de actividad deja de inflarse artificialmente y el runner puede distinguir mejor actividad real de duplicacion accidental

Causa raiz refinada:

- despues de frenar `cleanupDuplicateRows`, el costo seguia alto porque habia demasiados callers pidiendo el mismo pull/bootstrap a la vez; cada uno volvia a pedir checkpoint y reejecutaba tramos del pipeline contra la misma cola SQLite

Riesgo cubierto:

- evitar corridas duplicadas del mismo sync/bootstrap scope sin tocar la semantica funcional del flujo offline-first

Puntos ciegos conocidos:

- si todavia quedan spikes, el siguiente cuello estara mas concentrado en writes repetidos de `entity_snapshots` / `id_map` y algunos inserts remotos repetidos por entidad, ya sin el ruido de corridas paralelas completas

Validacion parcial:

- `npx eslint "src/modules/jobs/presentation/hooks/usePullJobsSync.ts" "src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts"` en `sisa.ui` -> PASS

## Avance parcial - cuello confirmado en cleanupDuplicateRows repetido y ahora throttled

Estado: en progreso

Que cambio:

- el ultimo log ya muestra un cuello muy concreto y mas grave que el resto: `cleanupDuplicateRows()` estaba corriendo desde `useBootstrapJobsFromApi` y `usePullJobsSync` una y otra vez, y cada pasada barria tablas como `jobs`, `job_items`, `work_logs`, `job_status_history` y especialmente `job_item_status_history` con `DELETE ... WHERE local_id NOT IN (...)`; eso explica los eventos explosivos al entrar a detalle mientras habia actividad de sync compartida
- `sisa.ui/src/modules/jobs/data/db/cleanupDuplicateRows.ts` ahora evita ejecuciones redundantes con dos protecciones de bajo riesgo:
  - `join-inflight` si otra limpieza ya esta corriendo
  - `skip-recent` si la ultima corrida fue hace menos de 5 minutos
- esto mantiene la proteccion contra duplicados, pero evita re-barrer las mismas tablas en cada pull/bootstrap/autosync cercano, que era exactamente lo que estaba llenando la cola SQLite segun los `sql.slow`

Causa raiz confirmada de esta pasada:

- no era ya el `getByUuid` del push
- tampoco era solo `listPending`
- era `cleanupDuplicateRows()` invocado demasiado seguido y solapado con bootstrap/pull, generando deletes caros que bloqueaban todo lo demas

Riesgo cubierto:

- cortar scans/deletes repetidos sobre tablas grandes sin perder la correccion basica de limpieza de duplicados

Puntos ciegos conocidos:

- si despues de este throttle sigue habiendo costo alto, el siguiente paso es mover la limpieza pesada a momentos controlados de bootstrap frio o hacerla por tablas/segmentos con criterio incremental en vez de barrer todo

Validacion parcial:

- `npx eslint "src/modules/jobs/data/db/cleanupDuplicateRows.ts"` en `sisa.ui` -> PASS

## Avance parcial - el cuello ahora es solapamiento entre bootstrap/pull y auto-sync

Estado: en progreso

Que cambio:

- los ultimos logs muestran que el `getByUuid` repetido ya no es el cuello dominante del push: el problema principal pasa a ser que `JobsSyncAutoRunner` dispara auto-sync mientras otra instancia de `usePullJobsSync` o `useBootstrapJobsFromApi` sigue aplicando lotes remotos sobre la misma cola SQLite. La evidencia es clara: `listPending(mode=execution)` queda con `waitMs` enormes detras de `insert into jobs`, `delete from jobs ... not in`, `delete from job_items ... not in` y otros writes del bootstrap/pull
- `sisa.ui/src/modules/jobs/presentation/sync/jobsSyncActivity.ts` introduce contadores compartidos de actividad (`bootstrap`, `pull`, `push`) a nivel modulo, para que distintas instancias de hooks vean el mismo estado de actividad real
- `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/useRunJobsSync.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` ahora marcan inicio/fin de actividad compartida en `try/finally`
- `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` salta el auto-sync cuando ya existe otra actividad compartida en curso (`shared-sync-activity`), evitando que el runner compita con bootstrap/checkpoint pull o con otro push/pull sobre la misma base local

Call chain confirmado en esta pasada:

- bootstrap / checkpoint pull siguen haciendo escrituras locales intensivas (`insert into jobs`, deletes de limpieza, snapshots, id_map)
- `JobsSyncAutoRunner` arrancaba en paralelo porque solo miraba su estado local de hook, no la actividad real de otras instancias
- la cola SQLite serializada convertia eso en `waitMs` gigantes para `listPending`, `useWorkLogs.reload` y luego para el propio push de 1 worklog

Riesgo cubierto:

- evitar que sync de usuario compita con hidratacion remota ya en curso y degrade brutalmente la UX aunque cada query individual sea razonable

Puntos ciegos conocidos:

- sigue habiendo costo real en varias limpiezas `delete ... not in (...)` y snapshots/id_map del bootstrap; esta pasada corta el solapamiento, no reescribe esos procesos batch aun

Validacion parcial:

- `npx eslint "src/modules/jobs/presentation/sync/jobsSyncActivity.ts" "src/modules/jobs/presentation/hooks/usePullJobsSync.ts" "src/modules/jobs/presentation/hooks/useRunJobsSync.ts" "src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts" "src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx"` en `sisa.ui` -> PASS

## Avance parcial - caller exacto identificado: hydrate remoto hacia SQLite hacia getByUuid repetido

Estado: en progreso

Que cambio:

- la traza fina `sql.slow` permitio identificar el patron real: el `getByUuid` repetido no venia del `push` HTTP en si, sino del camino de aplicacion local de datos remotos (`usePullJobsSync` y bootstrap jobs) que llamaba `upsertRemote(...)` para `jobs`, `job_items`, `work_logs` y `appointments`; cada `upsertRemote` hacia `getByUuid` antes de escribir y otra vez al final para releer la fila, multiplicando lecturas del mismo UUID en una cola SQLite ya saturada
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteJobsRepository.ts`, `sisa.ui/src/modules/jobs/data/repositories/SQLiteJobItemsRepository.ts`, `sisa.ui/src/modules/jobs/data/repositories/SQLiteWorkLogsRepository.ts` y `sisa.ui/src/modules/jobs/data/repositories/SQLiteAppointmentsRepository.ts` agregan `applyRemote(...)` con `INSERT ... ON CONFLICT DO UPDATE` directo, sin `getByUuid` previo ni readback final, y respetando entidades locales `pending/syncing/conflict` con `WHERE ... sync_state NOT IN (...)`
- `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` pasan a usar `applyRemote(...)` en vez de `upsertRemote(...)` para la aplicacion remota, manteniendo snapshots e id maps pero eliminando la relectura local redundante por entidad importada

Call chain confirmado:

- `JobsSyncAutoRunner` / bootstrap / checkpoint pull
- `usePullJobsSync` o `useBootstrapJobsFromApi`
- `*.Repository.upsertRemote(...)`
- `getByUuid(...)` previo + `getByUuid(...)` final
- contencion de cola SQLite
- degradacion indirecta de `listPending`, `useWorkLogs.reload` y tiempo total percibido

Riesgo cubierto:

- eliminar N+1 de lecturas locales durante la aplicacion remota sin romper proteccion de cambios locales pendientes

Puntos ciegos conocidos:

- quedan otras fuentes de contencion no relacionadas al worklog save, sobre todo limpieza/refresh de bootstrap y `sync_state`/`reference cache`; si el runner sigue compitiendo con bootstrap en la misma ventana, la siguiente pasada debe desacoplar esas tareas o hacerlas mas batch

Validacion parcial:

- `npx eslint "src/modules/jobs/data/repositories/SQLiteJobsRepository.ts" "src/modules/jobs/data/repositories/SQLiteJobItemsRepository.ts" "src/modules/jobs/data/repositories/SQLiteAppointmentsRepository.ts" "src/modules/jobs/data/repositories/SQLiteWorkLogsRepository.ts" "src/modules/jobs/presentation/hooks/usePullJobsSync.ts" "src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts"` en `sisa.ui` -> PASS

## Avance parcial - trazas SQL finas para aislar el caller exacto del getByUuid repetido

Estado: en progreso

Que cambio:

- la corrida posterior confirmo que el cuello ya no era solo `listPending`: aunque el camino `execution` mejoro fuerte al inicio, seguian apareciendo decenas de `sqlite.work_logs.getByUuid.slow` sobre los mismos UUID y saturando la cola serial de SQLite durante la sync y la reapertura de pantallas
- como el caller exacto no queda visible solo con logs de repositorio, `sisa.ui/src/modules/jobs/data/db/jobsDatabase.ts` ahora agrega trazas por operacion SQL lenta (`sql.slow`) con `traceId`, `type`, `statementKey`, `caller`, `waitMs`, `sqlMs`, `totalMs`, `rowCount` y `paramsPreview`; esto permite distinguir si el costo viene de la query en si o de espera por contencion en la cola global
- la misma capa agrega `sql.explain` una sola vez por `statementKey` para queries `SELECT` lentas, dejando el plan de ejecucion asociado al caller real que dispara la saturacion

Riesgo cubierto:

- evitar seguir optimizando a ciegas sobre el lugar equivocado cuando el cuello real puede venir de otra ruta que reutiliza `workLogsRepository.getByUuid(...)` fuera del push principal

Puntos ciegos conocidos:

- esta pasada prioriza observabilidad exacta antes de tocar otra vez el flujo; el siguiente ajuste debe apoyarse en los nuevos `caller`/`waitMs` para corregir el origen real de los `getByUuid` repetidos sin romper offline-first

Validacion parcial:

- `npx eslint "src/modules/jobs/data/db/jobsDatabase.ts"` en `sisa.ui` -> PASS

## Avance parcial - listPending deja de inspeccionar de mas durante el push de una sola operacion

Estado: en progreso

Que cambio:

- el analisis del ultimo log mostro que el cuello dominante ya no era `createWorkLog`, sino `listPendingMs` dentro de `useRunJobsSync`: para empujar 1 worklog, la app estaba usando el mismo `listPending()` pesado que tambien sirve para UI/diagnostico y que ejecutaba mantenimiento completo antes de devolver operaciones
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteSyncRepository.ts` ahora separa dos caminos: `listPending()` completo para vistas de estado y `listPendingForExecution()` liviano para el runner de sync. El camino de ejecucion evita reconciliacion obsoleta global y consulta solo operaciones ejecutables, con nuevas metricas `sync.listPending.slow` separadas por modo, `queryMs`, `maintenanceMs`, `reconcileMs`, `operationsCount` y `uniqueEntitiesCount`
- el mismo repositorio ahora batcha snapshots locales al capturar operaciones pendientes con `capturePendingEntitySnapshots(...)`, evitando repetir lecturas por uuid y dejando trazas `sync.capturePendingEntitySnapshot` con `requestedCount`, `uniqueCount`, `cacheHits` y `cacheMisses`
- `sisa.ui/src/modules/jobs/data/db/schema.ts` y `sisa.ui/src/modules/jobs/data/db/jobsMigrations.ts` suben a schema `27` y agregan indices para la cola de sync: `sync_operations(status, next_attempt_at, created_at)`, `sync_operations(entity_type, entity_uuid, status)` y `sync_operations(company_id, status, next_attempt_at)`
- `sisa.ui/src/modules/jobs/presentation/hooks/useRunJobsSync.ts` y `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` pasan a usar el camino liviano de ejecucion para no hidratar operaciones pendientes no relacionadas cuando solo hay que empujar un cambio local
- `sisa.ui/src/modules/jobs/presentation/hooks/useWorkLogs.ts` agrega `skip-recent-success` para evitar el doble reload `initial` + `event` del mismo `jobUuid` dentro de 1 segundo despues de un guardado o refresco exitoso
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteWorkLogsRepository.ts` expone `getByUuids(...)` para futuras lecturas batch del dominio y evitar volver a caer en `getByUuid` repetidos si otro camino de sync necesita varios worklogs juntos

Riesgo cubierto:

- evitar que una sola sync local pague mantenimiento completo de cola y scans sobre operaciones viejas o irrelevantes, alejando `listPendingMs` del costo real de push
- reducir recargas duplicadas de worklogs justo despues del guardado, cuando la UI ya tiene el dato local confirmado

Puntos ciegos conocidos:

- la evidencia de `perf:sqlite.work_logs.getByUuid.slow` repetidos no sale del `push` directo inspeccionado en esta pasada; por código, el cuello confirmado estaba en `listPending()` y mantenimiento de cola. Si esos `getByUuid` vuelven a aparecer tras esta optimizacion, la siguiente pasada debe instrumentar caller/statement en `jobsDatabase.ts` para atribuirlos 1:1

Validacion parcial:

- `npx eslint "src/modules/jobs/domain/repositories/WorkLogsRepository.ts" "src/modules/jobs/data/repositories/SQLiteWorkLogsRepository.ts" "src/modules/jobs/data/repositories/SQLiteSyncRepository.ts" "src/modules/jobs/presentation/hooks/useRunJobsSync.ts" "src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx" "src/modules/jobs/presentation/hooks/useWorkLogs.ts" "src/modules/jobs/data/db/schema.ts" "src/modules/jobs/data/db/jobsMigrations.ts"` en `sisa.ui` -> PASS

## Avance parcial - se elimina fan-out remanente del listado y adjuntos del detalle pasan a demanda

Estado: en progreso

Que cambio:

- el log nuevo confirmo que la mejora anterior no habia cerrado el cuello principal del listado: `app/jobs/index.tsx` seguia montando `useWorkLogs` por tarjeta para mostrar participantes y `useJobItems` por tarjeta para listar pendientes, serializando varias lecturas `listByJobUuid` de SQLite apenas se abria `/jobs`
- `sisa.ui/app/jobs/index.tsx` deja de montar hooks por card y pasa a mostrar solo hints resumidos usando contadores ya proyectados (`itemCount`, `workLogCount`); ademas se elimina el `reload()` extra en `focus`, que estaba generando recargas manuales duplicadas encima del `initial`
- el mismo log mostro ruido residual de `AttachmentCountChip` aun con `fallbackCount`; la causa era que el componente seguia llamando `useAttachments()` con `attachableUuid = null`. `sisa.ui/app/jobs/[id].tsx` ahora separa modo live vs fallback y deja de crear esos hooks vacios
- `sisa.ui/app/jobs/[id].tsx` tambien deja de montar `AttachmentList` del trabajo por defecto; los adjuntos del job pasan a expandirse bajo demanda, evitando otra query que en la corrida seguia tardando ~2.8s aunque devolviera 0 filas porque quedaba encolada detras del resto

Riesgo cubierto:

- evitar que abrir `/jobs` haga una tormenta de queries SQLite por cada tarjeta visible solo para enriquecer la UI con datos no criticos para la decision comercial inicial
- evitar que el detalle de trabajo siga “asentandose por abajo” varios segundos por adjuntos del trabajo que el usuario no pidio abrir

Puntos ciegos conocidos:

- todavia no se instrumenta `jobsDatabase.ts` con `waitMs/sqlMs` por statement; si despues de sacar este fan-out siguen apareciendo latencias altas por `work_logs.listByJobUuid`, la siguiente pasada debe medir contencion exacta en la cola serial global

Validacion parcial:

- `npx eslint "app/jobs/index.tsx" "app/jobs/[id].tsx"` en `sisa.ui` -> PASS

## Avance parcial - fan-out local reducido en jobs/worklogs y sync post-save menos bloqueante

Estado: en progreso

Que cambio:

- `sisa.ui/src/modules/jobs/data/repositories/SQLiteJobsRepository.ts` deja de resolver contadores del listado de trabajos con multiples subqueries correlacionadas por fila y pasa a usar agregados batcheados por entidad; esto baja el costo al abrir `/Home` y `/jobs` cuando ya existe base local
- `sisa.ui/src/modules/jobs/data/db/schema.ts` y `sisa.ui/src/modules/jobs/data/db/jobsMigrations.ts` suben a schema `26` y agregan indices para rutas calientes de detalle: `job_items(job_uuid, deleted_at, sort_order, local_id)`, `appointments(job_uuid, deleted_at, appointment_date, appointment_time)`, `job_group_members(job_uuid, deleted_at)` y `job_root_cause_links(job_uuid, deleted_at)`
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteJobItemsRepository.ts`, `sisa.ui/src/modules/jobs/data/mappers/jobItemMapper.ts`, `sisa.ui/src/modules/jobs/domain/entities/JobItem.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/useJobItems.ts` y `sisa.ui/app/jobs/[id].tsx` incorporan `attachmentCount` agregado en la query local y eliminan el patron N+1 de `AttachmentCountChip` por cada job item/worklog dentro del detalle de trabajo
- `sisa.ui/app/jobs/worklogs.tsx` elimina reloads manuales redundantes despues de guardar, adjuntar o borrar worklogs cuando el mismo cambio ya emite `jobs-data-refresh`, reduciendo recargas duplicadas de la misma pantalla
- `sisa.ui/src/modules/jobs/presentation/hooks/useRunJobsSync.ts` y `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` desacoplan el auto-sync del pull posterior al push: el guardado local sigue encolando sync, pero el autosync disparado por save ya no espera un pull extra por defecto; ademas deja trazas separadas `listPendingMs` / `pushMs` / `pullMs`

Riesgo cubierto:

- evitar que abrir trabajos o detalle de trabajo haga fan-out de queries SQLite serializadas sobre la misma cola local y degrade fuerte la UX aun sin red
- evitar que el autosync inmediato despues de guardar extienda artificialmente la percepcion de guardado por un pull incremental que no aporta confirmacion UX inmediata

Puntos ciegos conocidos:

- esta pasada reduce el fan-out mas visible y agrega indices en joins criticos, pero no cambia todavia la cola serial global de `jobsDatabase`; si los tiempos siguen altos, la segunda pasada debe instrumentar esperas `waitMs`/`sqlMs` por statement para confirmar si la contencion restante vive ahi

Validacion parcial:

- `npx eslint "src/modules/jobs/domain/entities/JobItem.ts" "src/modules/jobs/data/mappers/jobItemMapper.ts" "src/modules/jobs/data/repositories/SQLiteJobItemsRepository.ts" "src/modules/jobs/presentation/hooks/useJobItems.ts" "src/modules/jobs/data/db/schema.ts" "src/modules/jobs/data/db/jobsMigrations.ts" "src/modules/jobs/data/repositories/SQLiteJobsRepository.ts" "src/modules/jobs/presentation/hooks/useRunJobsSync.ts" "src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx" "app/jobs/[id].tsx" "app/jobs/worklogs.tsx"` en `sisa.ui` -> PASS

## Avance parcial - monitor de trafico ahora incluye historico en tiempo real

Estado: en progreso

Que cambio:

- `sisa.ui/contexts/NetworkLogContext.tsx` ahora mantiene un historial en memoria de muestras de trafico cada 2 segundos durante los ultimos 3 minutos, sin persistirlo, para observar evolucion temporal y no solo un snapshot instantaneo
- `sisa.ui/components/NetworkTrafficOverlay.tsx` muestra ese historico dentro del modal flotante con un grafico simple de barras: requests activas y payload aproximado reciente, junto con ventana temporal relativa, hora de ultima actualizacion y metricas resumidas
- el mismo overlay ahora agrega mas detalle operativo de lectura rapida: valores actuales, picos y promedios del historico para activas/payload, mas pico y estado actual de errores y requests lentas
- el monitor sigue siendo liviano y efimero: no guarda el historico, pero deja suficiente cola visual para correlacionar picos de trafico con lentitud de UI o sync

Riesgo cubierto:

- evitar diagnosticos basados solo en valores puntuales del momento; ahora se puede ver si la red viene sostenidamente cargada, si hay rafagas o si el cuello esta estable aun sin mucho trafico

Validacion parcial:

- `npx eslint "contexts/NetworkLogContext.tsx" "components/NetworkTrafficOverlay.tsx"` en `sisa.ui` -> PASS

## Avance parcial - cuello exacto detectado en SQLite local y monitor de trafico agregado

Estado: en progreso

Que cambio:

- el diagnostico de campo mostro que la lentitud principal no venia de `/sync/push` sino de SQLite local: `useWorkLogs.reload` estaba tardando 6-7s y `useAttachments.reload` 3.6s aun con cero adjuntos, señal clara de scans locales pesados
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteWorkLogsRepository.ts` deja de calcular contadores de adjuntos con subqueries correlacionadas por fila y pasa a un agregado unico por `attachable_uuid`; ademas suma logs `sqlite.work_logs.*.slow` para distinguir costo de `list`, `getByUuid` y `create`
- `sisa.ui/src/modules/jobs/data/db/schema.ts` y `sisa.ui/src/modules/jobs/data/db/jobsMigrations.ts` suben a schema `25` y agregan indices criticos para este baseline: `work_logs(job_uuid, deleted_at, started_at)`, `work_logs(uuid, deleted_at)` y dos indices sobre `file_attachments(attachable_type, attachable_uuid, ...)`
- `sisa.ui/src/modules/jobs/domain/use-cases/createWorkLog.ts` y `sisa.ui/src/modules/jobs/data/repositories/SQLiteSyncRepository.ts` agregan trazas por etapa (`create`, `enqueue`) para separar si el guardado local demora por escritura de worklog, por cola sync o por lock/contencion de DB
- `sisa.ui/src/modules/jobs/presentation/hooks/useTriggerJobsSync.ts` deja de lanzar un `runJobsSync()` paralelo desde el hook y pasa a delegar al autosync coalescido; esto reduce el doble disparo observado en logs (`useRunJobsSync:start` duplicado sobre la misma operacion)
- `sisa.ui/contexts/NetworkLogContext.tsx`, `sisa.ui/components/NetworkTrafficOverlay.tsx` y `sisa.ui/app/_layout.tsx` agregan un monitor flotante de trafico de red con modal movible, mostrando requests activas, requests del ultimo minuto, payload aproximado, lentas >1s y errores recientes, para correlacionar saturacion de red vs cuello local

Riesgo cubierto:

- evitar atribuir la demora a la red cuando el bloqueo real es contencion/scan local sobre SQLite
- reducir reintentos redundantes de sync en background que competian por la misma cola inmediatamente despues de crear el worklog

Puntos ciegos conocidos:

- todavia quedan queries paralelas de `useWorkLogs` en listados generales (`/jobs`) y de adjuntos en detalle de trabajo; con los nuevos indices deberian bajar fuerte, pero si siguen pesadas la siguiente pasada debe atacar batching/lazy load de esos consumidores

Validacion parcial:

- `npx eslint "app/_layout.tsx" "app/jobs/worklogs.tsx" "components/NetworkTrafficOverlay.tsx" "contexts/NetworkLogContext.tsx" "src/modules/jobs/domain/use-cases/createWorkLog.ts" "src/modules/jobs/presentation/hooks/useTriggerJobsSync.ts" "src/modules/jobs/data/repositories/SQLiteWorkLogsRepository.ts" "src/modules/jobs/data/repositories/SQLiteSyncRepository.ts"` en `sisa.ui` -> PASS
- `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerWorkLogsPushTest.php` en `sisa.api` -> PASS

## Avance parcial - worklogs entran livianos y el guardado vuelve a ser local-first

Estado: en progreso

Que cambio:

- `sisa.ui/src/modules/jobs/presentation/hooks/useAttachments.ts` ya no duplica carga por `useEffect` + `useFocusEffect`; ahora hace una sola carga inicial por `attachableType`/`attachableUuid`, deduplica reloads concurrentes, evita `setState` si la lista no cambio y deja el refresh por foco apagado por defecto
- `sisa.ui/src/modules/jobs/presentation/components/AttachmentList.tsx` deja de montarse masivamente al abrir `/jobs/worklogs`; la pantalla usa ahora `sisa.ui/src/modules/jobs/presentation/components/WorkLogAttachmentsSummary.tsx` y solo monta la lista completa cuando el usuario expande un worklog
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteWorkLogsRepository.ts` agrega resumen liviano de adjuntos por worklog (`attachment_count`, pendientes, conflictos), para evitar abrir hooks pesados solo para pintar contadores e iconos
- `sisa.ui/src/modules/jobs/presentation/hooks/useWorkLogs.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/useJobsList.ts` ahora coalescen refreshes, evitan reloads concurrentes del mismo scope, no prenden `loading` otra vez si ya habia datos y registran metricas de tiempo/cantidad de recargas
- `sisa.ui/src/utils/autoSyncEvents.ts` agrega debounce global para `jobsDataRefresh` y `jobsAutoSync`, evitando que una rafaga de eventos termine recargando la UI muchas veces por el mismo cambio cercano
- `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` mantiene la proteccion de cooldown pero deja de forzar pulls por cada trigger de autosync generado localmente; el push de worklogs sigue teniendo prioridad, pero sin disparar otra tormenta de recargas al entrar a la pantalla
- `sisa.ui/src/modules/jobs/presentation/hooks/useCreateWorkLog.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/useRunJobsSync.ts` y `sisa.ui/app/jobs/worklogs.tsx` agregan instrumentacion minima de tiempos y cambian el post-guardado para cerrar el modal enseguida y refrescar en background, en vez de bloquear la UX esperando reload/sync remoto
- `sisa.ui/app/jobs/worklogs.tsx` tambien deja de cargar `clients` y `tariffs` al entrar por defecto; esos datos se difieren al abrir el modal, y la lista de jobs para mover worklogs se carga recien cuando se expone la seccion `Mover de Trabajo`

Riesgo cubierto:

- evitar que abrir worklogs con varios registros dispare `AttachmentList`/`useAttachments` para todos a la vez y degrade fuerte la navegacion inicial
- evitar que crear o editar un worklog se perciba lento por esperar refreshes globales, pulls o listas auxiliares que no son necesarias para confirmar el guardado local

Puntos ciegos conocidos:

- esta pasada reduce la tormenta de renders y reloads dentro del modulo de worklogs, pero no reemplaza todavia con telemetria persistente los logs puntuales de `jobs-debug`; la observabilidad fina sigue siendo principalmente de desarrollo

Validacion parcial:

- `npx eslint "src/modules/jobs/presentation/hooks/useAttachments.ts" "src/modules/jobs/presentation/hooks/useWorkLogs.ts" "src/modules/jobs/presentation/hooks/useJobsList.ts" "src/modules/jobs/presentation/components/AttachmentList.tsx" "src/modules/jobs/presentation/components/WorkLogAttachmentsSummary.tsx" "src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx" "src/modules/jobs/presentation/hooks/useCreateWorkLog.ts" "src/modules/jobs/presentation/hooks/useRunJobsSync.ts" "src/utils/autoSyncEvents.ts" "app/jobs/worklogs.tsx"` en `sisa.ui` -> PASS
- `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerWorkLogsPushTest.php` en `sisa.api` -> PASS
- `php -l src/Controllers/SyncOperationsController.php && php -l tests/Controllers/SyncOperationsControllerWorkLogsPushTest.php` en `sisa.api` -> PASS

## Avance parcial - worklogs reflejan sync real tras push aceptado

Estado: en progreso

Que cambio:

- `sisa.ui/src/modules/jobs/presentation/hooks/useRunJobsSync.ts` ahora refresca la UI apenas una operacion local cambia de estado por push, incluso cuando el pull posterior no trae eventos porque `/sync/pull` excluye operaciones del mismo dispositivo; con eso un `work_logs` aceptado ya no queda visualmente clavado en `pending`
- el mismo hook marca entidades locales como `error` o `conflict` cuando `/sync/push` rechaza la operacion, en vez de dejar el worklog eternamente como `pending` aunque `sync_operations.error_message` ya tenga la causa real
- `sisa.ui/src/modules/jobs/presentation/sync/syncVisualState.ts` y `sisa.ui/src/modules/jobs/presentation/components/SyncStateChip.tsx` dejan de mostrar `Nuevo local` como pseudo-estado de negocio y pasan a `Pendiente de sincronizar` / `Error de sincronizacion`, separando mejor negocio vs sync tecnico
- `sisa.api/src/Controllers/SyncOperationsController.php` endurece el flujo `work_logs/upsert`: cuando llega `job_uuid` invalido responde una causa explicita, valida `job_item_uuid` contra el job resuelto y compara contra el `job` real actual en vez de un campo inexistente del `work_log` al decidir si debe soltar el item al moverlo
- `sisa.api/tests/Controllers/SyncOperationsControllerWorkLogsPushTest.php` agrega regresion de push con `job_uuid` + `job_item_uuid` + participants validos, verificando `accepted=true`, resolucion a `job_id` / `job_item_id` del servidor y persistencia de participantes

Riesgo cubierto:

- evitar falsos `Nuevo local` despues de un push exitoso cuando el servidor ya acepto el worklog pero la app no refrescaba el cambio local porque el pull del mismo device no devuelve ese evento
- exponer antes los rechazos reales de sync de `work_logs` para distinguir un problema de backend/dependencias de un simple pendiente offline

Puntos ciegos conocidos:

- esta pasada endurece el caso de push directo de `work_logs`, pero no agrega todavia una vista dedicada dentro de la pantalla de worklogs para leer el `error_message` completo de `sync_operations`; el detalle tecnico sigue quedando principalmente en la cola/log de sync

Validacion parcial:

- `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerWorkLogsPushTest.php` en `sisa.api` -> PASS
- `php -l src/Controllers/SyncOperationsController.php && php -l tests/Controllers/SyncOperationsControllerWorkLogsPushTest.php` en `sisa.api` -> PASS
- `npx eslint "src/modules/jobs/presentation/hooks/useRunJobsSync.ts" "src/modules/jobs/presentation/sync/syncVisualState.ts" "src/modules/jobs/presentation/components/SyncStateChip.tsx"` en `sisa.ui` -> PASS

## Avance parcial - worklogs ya no rompen al guardar toast y refrescan adjuntos locales

Estado: en progreso

Que cambio:

- `sisa.ui/app/jobs/worklogs.tsx` corrige un bug real de runtime en la pantalla de worklogs: el callback `handleSavedWorkLog` usaba `showToast(...)` sin obtenerlo del contexto en `ActiveJobWorkLogsScreen`, lo que disparaba el error visible `Property 'showToast' doesn't exist` justo despues de guardar
- la misma pantalla ahora incluye `showToast` en las dependencias del callback y notifica correctamente cuando el worklog o un adjunto quedan en cola local
- tambien se agrega un `attachmentRefreshVersion` por worklog para forzar el refresco de `AttachmentList` tras adjuntar o borrar archivos, evitando que la tarjeta quede clavada mostrando `Cargando adjuntos...` aunque el adjunto local ya exista en SQLite

Riesgo cubierto:

- evitar que un error JS post-guardado corte la UX y deje la percepcion de que el worklog "no termino" aunque la escritura local si haya ocurrido
- evitar UI stale en adjuntos locales de worklogs, especialmente en escenarios offline-first donde el archivo queda pendiente de sync pero debe verse enseguida

Puntos ciegos conocidos:

- esta pasada corrige el error de runtime y el refresco local de adjuntos, pero si el push al servidor falla por red/backend la cola seguira pendiente hasta que el auto-sync pueda completar o marque error real

Validacion parcial:

- `npx eslint "app/jobs/worklogs.tsx"` en `sisa.ui` -> PASS

## Avance parcial - sync recupera operaciones work log interrumpidas y baja ruido de permisos offline

Estado: en progreso

Que cambio:

- `sisa.ui/src/modules/jobs/data/repositories/SQLiteSyncRepository.ts` ahora rescata operaciones locales que quedaron clavadas en `processing` por mas de 90 segundos y las devuelve a `pending` con `error_code = interrupted`, evitando que un `work_logs` quede colgado indefinidamente si la app, Hermes o Metro cortan el intento a mitad de push
- `sisa.ui/app/jobs/sync.tsx` muestra una causa explicita para ese nuevo estado recuperado, de modo que el diagnostico ya no parezca un conflicto silencioso sino un intento interrumpido y reintentable
- `sisa.ui/contexts/PermissionsContext.tsx` deja de abrir un alert bloqueante cuando falla el refresh de permisos pero ya existen permisos cacheados validos; conserva el cache y solo registra el warning, alineando la UX con el baseline offline-first durante flujos como crear factura desde un trabajo

Riesgo cubierto:

- evitar que una operacion local de `work_logs` quede eternamente en `processing` despues de una caida del runtime o corte de red durante el push
- evitar falsos errores visibles al usuario cuando el refresh de permisos falla transitoriamente pero la app ya tiene un ultimo snapshot valido para seguir operando

Puntos ciegos conocidos:

- esta pasada recupera el estado local trabado, pero no diagnostica por si sola la causa remota exacta si el corte original fue fuera de la app (por ejemplo Wi-Fi/LAN, backend caido o Metro reconectando en debug)
- si no existe cache de permisos valido, el alert actual se mantiene porque en ese caso si hay riesgo real de operar sin autorizacion conocida

Validacion parcial:

- `npx eslint "src/modules/jobs/data/repositories/SQLiteSyncRepository.ts" "app/jobs/sync.tsx" "contexts/PermissionsContext.tsx"` en `sisa.ui` -> PASS con 1 warning preexistente/no bloqueante en `contexts/PermissionsContext.tsx` por regla `@typescript-eslint/array-type`

## Avance parcial - borrado de facturas muestra causa real y tolera estado `Completado`

Estado: en progreso

Que cambio:

- `sisa.api/src/Services/JobStatusResolver.php` amplia la heuristica de estados finales para reconocer tambien variantes usadas en empresas que renombraron el cierre del job como `completado/completada` o `resuelto/resuelta`; con eso el borrado/anulacion de facturas con items `jobs` ya no queda bloqueado por naming valido pero no contemplado
- `sisa.api/src/Services/InvoiceCancellationService.php` ahora devuelve un mensaje de negocio claro cuando no encuentra un estado final reconocible para liberar trabajos facturados, en vez de dejar un `409` opaco dificil de diagnosticar desde la app
- `sisa.ui/contexts/InvoicesContext.tsx` deja de colapsar cualquier fallo del `DELETE /invoices/{id}` a `false` silencioso y propaga el error estructurado del backend; `sisa.ui/app/invoices/[id].tsx` lo muestra en el alert de eliminacion para que el usuario vea por que no se pudo borrar
- `sisa.api/tests/Services/InvoiceCancellationServiceTest.php` agrega regresion para una empresa cuyo estado final usa `code = completado`, cubriendo el caso real donde la factura antes parecia "no hacer nada" aunque el bloqueo venia del resolver

Riesgo cubierto:

- evitar falsos bloqueos al eliminar/anular facturas en empresas que renombran el estado final del job sin salir de una semantica esperable
- evitar que la UI oculte la causa real cuando el servidor rechaza el borrado por una regla de integridad valida

Puntos ciegos conocidos:

- el sistema sigue dependiendo de heuristicas por texto para inferir estados funcionales; esto mejora compatibilidad pero no reemplaza el mapeo explicito por empresa documentado en `qa/COMPANY_STATUS_ROLE_MAPPING_FUTURE.md`
- si una empresa usa nombres totalmente propios para el estado final, la eliminacion seguira bloqueada hasta mapear o ampliar la heuristica correspondiente

Validacion parcial:

- `vendor/bin/phpunit tests/Services/InvoiceCancellationServiceTest.php` en `sisa.api` -> PASS
- `npx eslint "contexts/InvoicesContext.tsx" "app/invoices/[id].tsx"` en `sisa.ui` -> PASS con warnings preexistentes/no bloqueantes en `contexts/InvoicesContext.tsx` sobre `Array<T>` y callbacks `error` sin uso

## Avance parcial - flujo minimo de cobro factura -> recibo -> pagos

Estado: en progreso

Que cambio:

- `sisa.api/src/Controllers/InvoicesController.php` ahora permite crear el recibo directamente desde `POST /invoices/{id}/receipts` o seguir vinculando uno existente; valida saldo pendiente, coherencia cliente/empresa, opcionalmente asocia `payment_links` y sigue reconciliando el estado de la factura sin inventar estados nuevos fuera de `issued` / `paid`
- `sisa.api/src/Models/ReceiptPayments.php`, `sisa.api/src/History/ReceiptPaymentsHistory.php`, `sisa.api/scripts/migrations/receipt-payments.php`, `sisa.api/src/Controllers/ReceiptsController.php`, `sisa.api/src/Controllers/PaymentsController.php`, `sisa.api/src/Controllers/SyncOperationsController.php` y `sisa.api/src/Services/SyncEventGenerator.php` agregan la relacion `receipt_payments`, sus endpoints de consulta/alta/baja, guardas de doble imputacion por factura, bloqueo de borrado de pagos ya asociados, borrado en cascada logica al eliminar recibos y propagacion sync/offline-first de la nueva entidad
- `sisa.api/src/Models/Invoices.php` expone `applied_receipts_total` y `pending_balance` al hidratar facturas para que UI/reportes lean el saldo real sin recalcular cada consumidor por su cuenta
- `sisa.ui/app/invoices/[id].tsx` abre recibos asociados y prioriza `pending_balance` / `applied_receipts_total` del backend cuando existen; `sisa.ui/app/receipts/create.tsx` ahora muestra resumen de factura, saldo pendiente, selector minimo de pagos del cliente y alta rapida de pago desde el propio flujo; `sisa.ui/app/receipts/[id].tsx` suma lectura de pagos asociados
- ajuste posterior: el CTA original de factura quedaba demasiado escondido y ademas dependia de `resolvedInvoiceClient?.id`, por lo que algunos escenarios legacy o de permisos lo ocultaban aunque la factura tuviera `client_id` valido. `sisa.ui/app/invoices/[id].tsx` ahora separa `Crear recibo` de `Vincular recibo existente`, usa fallback al `client_id` real de la factura y mantiene ambas acciones visibles mientras haya saldo y permiso de vinculacion
- ajuste posterior 2: para que el flujo tambien sea intuitivo desde contabilidad, `sisa.ui/app/clients/accounting.tsx` agrega CTAs directos `Crear recibo` / `Vincular existente` sobre cada factura con saldo pendiente
- ajuste posterior 3: `sisa.ui/app/receipts/index.tsx` ahora soporta modo de seleccion para factura (`invoiceId` + `pendingBalance` en params), mostrando una accion `Vincular a factura` sobre recibos del cliente. Con esto ya se puede crear un recibo suelto sin anexado previo y luego asociarlo mas tarde desde la factura o desde contabilidad
- ajuste posterior 4: al crear recibo desde factura, la app antes refrescaba solo `invoices`; si el backend confirmaba el alta, el recibo podia existir en servidor pero no verse enseguida en la app hasta otro refresh/foco. `sisa.ui/app/receipts/create.tsx` ahora fuerza `loadReceipts()` tras el alta y `sisa.ui/contexts/InvoicesContext.tsx` escucha tambien cambios `receipts` / `receipt_payments` para refrescar detalles de factura. Ademas `createReceiptFromInvoice` devuelve `receiptId` para poder abrir el recibo recien creado directamente
- ajuste posterior 5: el listado `sisa.ui/app/receipts/index.tsx` ya soportaba vistas acotadas por `clientId` / `invoiceId` y busqueda persistida. Eso podia hacer parecer que el recibo no existia al volver al menu si seguian activos filtros previos. Ahora la pantalla muestra un bloque visible de `Filtros activos` con accion `Ver todos` y `Limpiar busqueda` para volver a la lista general sin ocultar recibos nuevos
- ajuste posterior 6: como el recibo seguia sin verse en algunos escenarios, se agrego instrumentacion temporal para diagnostico. `sisa.ui/contexts/ReceiptsContext.tsx` ahora loguea `selectedCompanyId`, query enviada, cantidad cruda devuelta por API, cantidad filtrada por empresa y muestras de IDs/`company_id`. `sisa.ui/app/receipts/index.tsx` loguea estado de filtros/pantalla y muestras de recibos visibles. En API, `sisa.api/src/Controllers/InvoicesController.php` y `sisa.api/src/Controllers/ReceiptsController.php` registran en logs de servidor el alta desde factura y el listado de recibos visible para el usuario
- ajuste posterior 7: los logs mostraron `GET /receipts?company_id=45` devolviendo `500`. Se endurecio `sisa.api/src/Controllers/ReceiptsController.php` para que un fallo al enriquecer `payment_links` o `invoice_links` no tire abajo todo el listado; ahora registra el error y responde el recibo igualmente con arrays vacios. Esto cubre especialmente ambientes donde la estructura nueva `receipt_payments` todavia no este migrada o haya deuda legacy puntual en enlaces
- ajuste posterior 8: al crear recibo desde factura, la app mostraba error aunque el recibo se creaba porque `sisa.ui/contexts/InvoicesContext.tsx` llamaba a una funcion inexistente (`sortInvoicesByNewest`) en el refresh local posterior al POST. Se reemplazo por `ensureSortedByNewest(...)` con el mismo criterio de orden del contexto, eliminando el falso negativo visual
- ajuste posterior 9: editar un recibo vinculado ya vuelve a empujar el estado de la factura y sus asientos incluso cuando el `applied_amount` estaba congelado en el link. `sisa.api/src/Controllers/ReceiptsController.php` ahora reajusta automaticamente `invoice_receipt_payments.applied_amount` cuando el recibo tiene una sola factura activa vinculada, y luego re-sincroniza factura/recibo
- ajuste posterior 10: editar un pago vinculado ya no refresca solo el asiento del pago. `sisa.api/src/Controllers/PaymentsController.php` ahora recorre `receipt_payments` -> `invoice_receipt_payments`, resincroniza asientos de recibos relacionados y revalida el estado/asientos de las facturas impactadas
- ajuste posterior 11: los listados de facturas (`sisa.ui/app/invoices/index.tsx`, `sisa.ui/app/clients/accounting.tsx`, `sisa.ui/app/clients/unpaidInvoices.tsx`) ahora muestran saldo pendiente, barra de progreso de cobro y texto `Pagado` / `Debe` para que el cobro parcial sea visible sin entrar al detalle
- ajuste posterior 12: al editar o borrar recibos/pagos desde sus detalles, la factura origen podia quedar stale si el usuario volvia enseguida porque la pantalla cerraba antes de refrescar `InvoicesContext`. `sisa.ui/app/receipts/[id].tsx` y `sisa.ui/app/payments/[id].tsx` ahora esperan `loadInvoices()` antes de levantar el spinner y cerrar la vista, de modo que el detalle/listado de facturas ya vuelva con estado, saldo y barra actualizados
- ajuste posterior 13: el mismo criterio se extendio a eliminaciones directas desde los listados financieros. `sisa.ui/app/receipts/index.tsx` y `sisa.ui/app/payments/index.tsx` ahora recargan `InvoicesContext` despues de borrar, evitando que otras pantallas de facturas queden mostrando saldo viejo si el usuario navega enseguida sin reabrir manualmente el detalle
- `sisa.ui/contexts/InvoicesContext.tsx`, `sisa.ui/contexts/ReceiptsContext.tsx`, `sisa.ui/contexts/PaymentsContext.tsx`, `sisa.ui/constants/selectionKeys.ts`, `sisa.ui/app/payments/create.tsx` y la capa sync/cache (`useBootstrapJobsFromApi`, `usePullJobsSync`, `referenceCache`) se alinean para cargar/propagar `receipt_payments` y devolver el pago creado a la pantalla del recibo sin perder el draft
- se documento el runbook tecnico/manual en `qa/INVOICE_RECEIPT_COLLECTION_FLOW.md` y se agrego la regresion `sisa.api/tests/Regression/InvoiceReceiptsAndPaymentsFlowRegressionTest.php` para cubrir saldo aplicado/pending y la guarda de pago duplicado sobre la misma factura

Riesgo cubierto:

- evitar que el cobro quede partido en pasos manuales sin trazabilidad clara entre factura, recibo y pagos asociados
- evitar sobrecobros y doble imputacion silenciosa de un mismo pago sobre la misma factura
- evitar que eliminar un recibo deje la factura marcada como cobrada o conserve links financieros fantasmas en sync/cache

Puntos ciegos conocidos:

- la creacion inline del recibo desde la factura no corre todavia dentro de una transaccion DB explicita; si mas adelante se suman mas efectos atomicos conviene cerrar ese hueco
- la UI actual permite seleccionar pagos y crear uno nuevo desde el recibo, pero no expone todavia una pantalla dedicada para reimputar montos de pagos ya asociados o reordenar varias imputaciones complejas
- el modulo `payments` conserva su semantica financiera existente; este hito agrega trazabilidad con recibos sin redisenar todo el modelo contable legacy alrededor de pagos cobrables/imputables

Validacion parcial:

- `php -l src/Controllers/InvoicesController.php && php -l src/Controllers/ReceiptsController.php && php -l src/Controllers/PaymentsController.php && php -l src/Controllers/SyncOperationsController.php && php -l src/Models/ReceiptPayments.php && php -l src/History/ReceiptPaymentsHistory.php && php -l src/Models/Invoices.php && php -l src/Services/SyncEventGenerator.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Regression/InvoiceReceiptsAndPaymentsFlowRegressionTest.php` en `sisa.api` -> PASS
- `npx eslint "app/receipts/create.tsx" "app/payments/create.tsx" "contexts/InvoicesContext.tsx" "contexts/PaymentsContext.tsx" "contexts/ReceiptsContext.tsx" "src/modules/jobs/presentation/hooks/usePullJobsSync.ts" "src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts" "src/modules/jobs/presentation/sync/referenceCache.ts" "app/invoices/[id].tsx" "app/receipts/[id].tsx"` en `sisa.ui` -> PASS con warnings tipados preexistentes/no bloqueantes sobre `Array<T>` y callbacks `error` sin uso en contextos financieros
- `npx eslint "app/invoices/[id].tsx" "app/receipts/index.tsx" "app/clients/accounting.tsx"` en `sisa.ui` -> PASS
- `npx eslint "contexts/InvoicesContext.tsx" "app/receipts/create.tsx"` en `sisa.ui` -> PASS con warnings no bloqueantes preexistentes en `InvoicesContext.tsx`
- `npx eslint "app/receipts/index.tsx"` en `sisa.ui` -> PASS
- `npx eslint "contexts/ReceiptsContext.tsx" "app/receipts/index.tsx"` en `sisa.ui` -> PASS con warnings no bloqueantes preexistentes de tipado `Array<T>`
- `php -l "src/Controllers/ReceiptsController.php" && php -l "src/Controllers/InvoicesController.php"` en `sisa.api` -> PASS
- `php -l "src/Controllers/ReceiptsController.php"` en `sisa.api` tras fallback defensivo -> PASS
- `npx eslint "contexts/InvoicesContext.tsx"` en `sisa.ui` tras fix de `sortInvoicesByNewest` -> PASS con warnings no bloqueantes preexistentes
- `php -l "src/Controllers/ReceiptsController.php" && php -l "src/Controllers/PaymentsController.php"` en `sisa.api` -> PASS
- `npx eslint "app/invoices/index.tsx" "app/clients/accounting.tsx" "app/clients/unpaidInvoices.tsx" "app/invoices/[id].tsx"` en `sisa.ui` -> PASS
- `npx eslint "app/receipts/[id].tsx" "app/payments/[id].tsx"` en `sisa.ui` -> PASS
- `npx eslint "app/receipts/index.tsx" "app/payments/index.tsx"` en `sisa.ui` -> PASS

## Avance parcial - productos/servicios recuperan metadata sync tras mutaciones

Estado: en progreso

Que cambio:

- `sisa.ui/contexts/ProductsServicesContext.tsx` normaliza de forma explicita los payloads de `products_services` antes de filtrarlos/cachearlos, para no perder `uuid`, `version`, `source_device_id` ni campos numericos cuando el backend los devuelve serializados como string
- el mismo contexto ahora acepta recarga forzada en `loadProductsServices(force)` y la usa despues de `POST`, `PUT`, `DELETE` y de eventos del reference cache; esto evita que el TTL de 5 minutos o la version del startup bootstrap dejen congelado un item recien creado/actualizado con `UUID: sin uuid` aunque el servidor ya lo haya persistido correctamente
- en altas y ediciones, si la API ya devuelve `product_service` en la respuesta, la UI lo mezcla enseguida en el estado local antes del refresh forzado; con eso el detalle vuelve a mostrar metadata sync valida en lugar de `Version: 0 / Device: n/a` mientras llega la recarga completa

Riesgo cubierto:

- evitar falsos negativos de sincronizacion en productos/servicios donde la escritura si llegaba al backend pero la UI seguia mostrando metadata vacia por una rehidratacion local stale

Puntos ciegos conocidos:

- esta pasada corrige el desfasaje de estado/cache en `products_services`, pero no agrega todavia una cola offline dedicada para altas/ediciones de este modulo si el request HTTP falla antes de llegar al servidor

Validacion parcial:

- `npm run lint` en `sisa.ui` -> PASS

## Avance parcial - cobro minimo desde factura y senalizacion visual

Estado: en progreso

Que cambio:

- `sisa.ui/app/invoices/[id].tsx` ahora expone el flujo minimo de cobro desde una factura emitida: muestra estado de cobro (`Pendiente` / `Cobro parcial` / `Cobro total`), total aplicado, saldo pendiente y un CTA `Crear recibo desde factura` cuando todavia queda saldo por cobrar
- `sisa.ui/app/receipts/create.tsx` y `sisa.ui/contexts/InvoicesContext.tsx` ahora aceptan prefills desde factura y, al guardar el recibo, lo vinculan automaticamente con `POST /invoices/{id}/receipts`, aplicando por defecto el saldo pendiente que venia de la factura
- `sisa.ui/contexts/InvoicesContext.tsx` corrige el parseo de `receipt_links`, y `sisa.api/src/Models/Invoices.php` ahora adjunta esos links tambien en los listados con items; con eso la UI deja de perder la conciliacion al navegar por el cache principal de facturas
- `sisa.api/src/Controllers/InvoicesController.php` ahora reconcilia el estado de la factura tras adjuntar o desadjuntar recibos: cuando el total aplicado cubre la factura la marca `paid`, y si vuelve a quedar saldo la devuelve a `issued`; ademas devuelve la factura actualizada en la respuesta del attach/detach
- `sisa.api/src/Controllers/ReceiptsController.php` suma `invoice_links` en `listReceipts`, y `sisa.ui/app/receipts/index.tsx` + `sisa.ui/app/payments/index.tsx` reemplazan la etiqueta `No facturado` por iconos operativos minimos: adjunto, factura asociada, o ambos segun corresponda
- se agrego `sisa.ui/components/AccountingLinkIndicators.tsx` para reutilizar esos indicadores sin sobrecargar las pantallas de recibos y pagos
- ajuste posterior: algunos ambientes con facturas viejas o migraciones financieras incompletas podian quedarse sin listado porque `sisa.api/src/Models/Invoices.php` ahora pedía `invoice_receipt_payments` al hidratar cada factura; se agrego fallback defensivo para que, si esa tabla/consulta falla, la factura siga cargando con `receipt_links = []` en vez de desaparecer del endpoint
- ajuste posterior 2: habia otro caso de invisibilidad al entrar a facturas desde un cliente si el servidor devolvia facturas legacy con `invoices.client_id` apuntando a la empresa real (`client_company_id`) en vez del vinculo `clients.id`; `sisa.ui/app/invoices/index.tsx` y `sisa.ui/app/invoices/[id].tsx` ahora resuelven ambas variantes para listar, mostrar nombre del cliente y prefijar cobros sin perder compatibilidad con el contrato nuevo
- ajuste posterior 3: se cerro el circuito completo del bug de IDs cliente/factura. `sisa.ui/app/clients/viewModal.tsx` y `sisa.ui/app/clients/accounting.tsx` ya navegaban con `clients.id`; ahora `sisa.ui/app/invoices/index.tsx` normaliza params entrantes a `clients.id`, filtra por cliente efectivo, y emite `console.debug` controlado en desarrollo para diagnosticar params/ids/listas visibles. A la vez `sisa.ui/app/invoices/create.tsx` y `sisa.ui/app/invoices/[id].tsx` normalizan cualquier valor legacy antes de enviar `client_id`, y `sisa.api/src/Controllers/InvoicesController.php` + `sisa.api/src/Models/Clients.php` resuelven explicitamente `company_id + client_company_id -> clients.id` antes de persistir para no volver a guardar `empresas.id` en `invoices.client_id`
- ajuste posterior 4: `sisa.ui/contexts/ReceiptsContext.tsx` y `sisa.ui/contexts/PaymentsContext.tsx` estaban filtrando por `selectedCompanyId` sin normalizar los payloads del backend; como PHP suele serializar ids numericos como string, `company_id` podia llegar como `'45'` y quedar descartado por comparacion estricta contra `45`. Ahora ambos contextos normalizan ids/montos/versiones antes de cachear/filtrar, igualando el comportamiento robusto que ya tenia `InvoicesContext`
- ajuste posterior 5: `sisa.ui/contexts/CashBoxesContext.tsx` tenia el mismo riesgo que pagos/recibos: filtraba por `company_id` antes de consolidar tipos y podia ocultar cajas validas si `company_id`, `user_id`, `assigned_user_ids` o `version` llegaban serializados como string. Ahora normaliza primero y recien despues cachea/filtra, evitando otra invisibilidad falsa en el flujo contable
- ajuste posterior 6: como las cajas seguian sin aparecer en algunos escenarios, se agrego diagnostico puntual en `sisa.ui/contexts/CashBoxesContext.tsx` y `sisa.ui/app/cash_boxes/index.tsx` con `CASH_BOXES_DEBUG_LOGS_ENABLED`; ahora queda visible si el problema esta en la respuesta de `/cash_boxes`, en el filtro por `selectedCompanyId` o en un filtro local de busqueda/orden de la pantalla. Ademas la pantalla distingue entre `sin cajas` y `sin resultados para ese filtro`
- ajuste posterior 7: para no mezclar ruido, `INVOICES_DEBUG_LOGS_ENABLED` quedo apagado y el diagnostico activo se concentro en cajas. Tambien se agregaron logs de estado/carga/filtro mas profundos en `CashBoxesContext` (scope activo, cache key, forma del payload, filtro por empresa y cache resultante) y la pantalla de cajas ahora informa `normalizedSearchQuery` para detectar rapido si un filtro persistido en cache esta ocultando cajas validas aunque el backend y el SQL esten correctos
- ajuste posterior 8: los logs de cajas mostraron que la pantalla ya tenia `canList = true` cuando el contexto de cajas seguia evaluando permisos como `false`, por lo que `loadCashBoxes()` salia antes de pedir `/cash_boxes` y nunca reintentaba mientras la pantalla seguia enfocada. `sisa.ui/contexts/CashBoxesContext.tsx` ahora dispara un auto-load cuando `token + selectedCompanyId + listCashBoxes` quedan listos, evitando que una hidratacion tardia de permisos deje el modulo contable vacio hasta navegar de nuevo
- ajuste posterior 9: como la carrera seguia apareciendo, `CashBoxesContext` dejo de bloquear la carga por `permissions.includes('listCashBoxes')` dentro del propio fetch. Ahora la pantalla sigue usando permisos para UX/navegacion, pero el contexto intenta cargar siempre que existan `token + selectedCompanyId` y deja que el backend responda `403` si corresponde. Se agrego tambien `permissionsSample/permissionsCount` al debug para confirmar si el desfasaje viene del provider de permisos o solo de la pantalla

Riesgo cubierto:

- evitar que la factura quede desacoplada del cobro real, obligando a crear recibos por fuera y conciliarlos manualmente despues
- mejorar la lectura operativa en listados financieros para detectar rapido si un pago/recibo tiene adjuntos, factura asociada o ambas cosas

Puntos ciegos conocidos:

- esta pasada cubre el flujo minimo `factura -> recibo -> vinculo -> saldo`; no agrega todavia una UI dedicada para vincular un recibo existente distinto del recien creado ni para editar el monto aplicado desde la pantalla de factura
- la reconciliacion automatica de estado usa `issued`/`paid`; si mas adelante hace falta un estado de negocio explicito para `parcial`, conviene modelarlo aparte en vez de inferirlo solo en UI desde el saldo pendiente
- el fallback evita perder visibilidad de facturas existentes, pero no reemplaza la migracion real del esquema financiero faltante; si un ambiente sigue sin `invoice_receipt_payments`, el vinculo recibo-factura no quedara persistido ahi hasta completar esa deuda de base
- la compatibilidad UI para `client_id` legacy evita ocultar datos historicos, pero no corrige todavia el baseline del servidor; si siguen existiendo facturas persistidas con `empresas.id` en `invoices.client_id`, conviene sanearlas para no seguir arrastrando ambiguedad en reportes o integraciones nuevas
- los `console.debug` agregados al listado de facturas estan acotados a desarrollo (`__DEV__`) y deben retirarse o degradarse a telemetria formal cuando deje de investigarse este incidente
- pagos y recibos siguen usando cache liviano compartido (`useCachedState`/reference cache) y no repositorios SQLite dedicados como clientes/providers; esta pasada corrige la invisibilidad por tipado de `company_id`, pero no cambia todavia esa arquitectura de persistencia

Validacion parcial:

- `vendor/bin/phpunit tests/Regression/AccountingSummaryAndInvoicesRegressionTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Regression/InvoicesClientIdResolutionRegressionTest.php tests/Regression/AccountingSummaryAndInvoicesRegressionTest.php` en `sisa.api` -> PASS
- `php -l src/Controllers/InvoicesController.php && php -l src/Controllers/ReceiptsController.php && php -l src/Models/Invoices.php` en `sisa.api` -> PASS
- `npm run lint` en `sisa.ui` -> PASS
- `node scripts/invoice-client-filter-smoke.js` en `sisa.ui` -> PASS
- `npm run lint` en `sisa.ui` tras normalizacion de pagos/recibos -> PASS

## Avance parcial - progreso visible durante bootstrap bloqueante

Estado: en progreso

Que cambio:

- `sisa.ui/contexts/BootstrapContext.tsx` ahora conserva detalle incremental del arranque para las dos etapas mas pesadas (`jobsBootstrap` y `jobsCheckpoint`): texto de estado, registros procesados, pagina/lote actual y si todavia quedan bloques pendientes
- `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` ya no espera al final para reportar avance; publica progreso mientras descarga paginas del bootstrap de jobs, mientras aplica trabajos a SQLite y cuando entra en las fases finales de grupos/causas raiz/checkpoint
- `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` ahora emite progreso por lote de eventos/checkpoint, dejando visible que la app sigue aplicando bloques aunque todavia no pueda estimar un porcentaje perfecto
- `sisa.ui/components/StartupLoadingScreen.tsx` agrega barra de progreso y subtitulos dinamicos dentro del item activo para que `Base inicial` y `Actualizacion incremental` dejen de verse congeladas durante la primera carga pesada
- ajuste posterior: `sisa.ui/contexts/CompaniesContext.tsx` ahora mezcla siempre los `member_companies` del startup bootstrap sobre las empresas ya cargadas, en vez de solo anexar faltantes; con eso no se pierde `profile_file_id` cuando la empresa ya existia en cache/listado y vuelven a aparecer los avatares/logos de empresa durante el arranque
- ajuste posterior 2: el error intermitente de SQLite al hidratar permisos (`NativeStatement.runAsync ... received class java.lang.Integer`) venia del wrapper `sisa.ui/src/modules/jobs/data/db/jobsDatabase.ts`, que estaba expandiendo binds como varargs; ahora pasa siempre el arreglo de parametros en el formato esperado por `expo-sqlite`, evitando el rechazo del statement compartido que hacia mas lento o inestable el paso `Empresas del usuario`
- ajuste posterior 3: la duplicacion de empresas en el selector ya no se corrige solo en la UI; `sisa.ui/contexts/CompaniesContext.tsx` deduplica por `company.id` cada hidratacion/merge/cache y `sisa.ui/contexts/MemberCompaniesContext.tsx` consolida la lista final por empresa aprobada conservando la version mas completa, para que el warning de React por keys repetidas no reaparezca en otros puntos del flujo
- ajuste posterior 4: `sisa.ui/contexts/FilesContext.tsx` ahora reintenta una vez las descargas/cacheos fallidos de archivos en segundo plano y deja de emitir advertencias visibles para prefetches recuperables o no criticos; asi un `HTTP 403` puntual de un adjunto ya no ensucia el panel de eventos ni interrumpe visualmente el arranque
- ajuste posterior 5: las imagenes que dependen de `/files/{id}` en arranque limpio ahora toleran mejor tokens viejos/rotados; `sisa.ui/contexts/FilesContext.tsx` usa un `tokenRef` actualizado y, ante `401/403/419`, dispara `checkConnection()` antes del reintento para que logos/avatares no queden vacios solo por la primera descarga autenticada del inicio
- ajuste posterior 6: `sisa.ui/components/CircleImagePicker.tsx` ahora reintenta la carga remota cuando la conectividad/token aparecen despues del primer render y evita marcar error permanente si el primer intento ocurre demasiado temprano; esto cubre el caso de borrar DB/archivos desde configuracion y volver a iniciar con logos vacios en el primer arranque limpio
- ajuste posterior 7: el logo de empresa podia disparar descargas concurrentes del mismo `fileId` desde mas de un consumidor (por ejemplo `CircleImagePicker` y la marca del `BottomNavigationBar`), generando un `403` temprano visible aunque otro intento terminara bien; `sisa.ui/contexts/FilesContext.tsx` ahora deduplica descargas en vuelo por `fileId` y `sisa.ui/components/BottomNavigationBar.tsx` trata ese fetch como silencioso para no contaminar el panel de eventos con un logo decorativo
- ajuste posterior 8: la descarga nativa de `expo-file-system` no pasaba por el `fetch` protegido del auth layer, por lo que podia reintentar con un bearer viejo aun despues de `checkConnection()`; `sisa.ui/contexts/FilesContext.tsx` ahora resuelve el token mas reciente desde secure storage en cada intento, con lo que las imagenes vuelven a levantar tras limpiar DB/archivos o rotar sesion
- ajuste posterior 9: el primer arranque despues de `Reinstalar localmente` podia dejar `clients/providers/member_companies` hidratados desde startup bootstrap sin `profile_file_id` suficiente y a la vez considerarlos frescos; ahora `sisa.ui/contexts/ClientsContext.tsx` y `sisa.ui/contexts/ProvidersContext.tsx` no sellan TTL/version cuando falta media de perfil, guardan los datos completos recuperados desde API en cache compartida, y `sisa.ui/contexts/BootstrapContext.tsx` ejecuta un warmup post-bootstrap que fuerza la rehidratacion necesaria y precachea logos/avatares con `getFile(..., silent)` para reconstruir imagenes en el primer login limpio sin alertas masivas offline
- ajuste posterior 10: para endurecer el primer arranque limpio tras el incidente intermitente de permisos/SQLite, `sisa.ui/src/modules/jobs/data/repositories/SQLitePermissionsRepository.ts` ahora serializa lecturas y escrituras sobre `permissions_cache` en una sola cola, `sisa.ui/src/modules/jobs/data/db/jobsDatabase.ts` reintenta una vez las fallas transitorias del bridge `expo-sqlite` reseteando la conexion/cache antes de repetir, y `sisa.ui/contexts/PermissionsContext.tsx` degrada con gracia si falla leer/escribir el cache local de permisos (conserva snapshot/memoria en vez de convertir un tropiezo transitorio en alerta persistente o crash)
- ajuste posterior 11: `sisa.ui/config/Index.ts` ahora deja apagados por defecto varios logs ruidosos de push/device/jobs/cache y concentra el debug puntual de facturas en `INVOICES_DEBUG_LOGS_ENABLED`; el smoke `sisa.ui/scripts/startup-stability-smoke.js` se actualizo para validar la presencia de flags sin exigir que todos queden siempre encendidos
- ajuste posterior 12: ante cierres esporadicos al entrar en detalle de trabajos, se endurecio el acceso compartido a SQLite en `sisa.ui/src/modules/jobs/data/db/jobsDatabase.ts`; ahora todas las operaciones (`getFirstSql`, `getAllSql`, `executeSql`) pasan por una cola unica ademas del retry transitorio. Esto reduce la presion de statements concurrentes cuando la pantalla de job abre varias lecturas a la vez (detalle, items, worklogs, appointments, participantes) y apunta directo al patron de crash nativo intermitente de `expo-sqlite` observado en primer arranque y navegacion pesada

Riesgo cubierto:

- evitar que el primer arranque offline-first parezca freeze o cuelgue cuando el bootstrap inicial tarda varios segundos por volumen de jobs/eventos aunque el proceso siga avanzando correctamente

Puntos ciegos conocidos:

- el porcentaje de `Base inicial` es aproximado sobre jobs descargados/aplicados y no sobre cada subentidad derivada (`items`, `work_logs`, `appointments`, adjuntos), asi que puede desacelerarse visualmente en paginas con jobs muy cargados
- `Actualizacion incremental` usa progreso por checkpoint/lotes; si los ids de eventos tienen huecos grandes, la barra puede no reflejar una linealidad exacta aunque si muestra actividad real

Validacion parcial:

- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:startup-stability` en `sisa.ui` -> PASS
- `npm run lint && npm run check:startup-stability` en `sisa.ui` tras serializar operaciones SQLite compartidas -> PASS

## Avance parcial - checklist transversal para tablas sync

Estado: completado

Que cambio:

- se agrego `qa/SYNC_ENTITY_CHECKLIST.md` como checklist canonica para todas las tablas sync actuales y futuras, cubriendo borrado logico/tombstones, metadata de version/origen, bootstrap/pull/events, guardas de integridad/scope, cliente offline-first y QA minimo
- la checklist lista explicitamente el set actual de entidades sync operativas, de referencias, financieras y de adjuntos para evitar que nuevas tablas entren al motor sin revisar no resurreccion y dependencia de deletes
- se documento la regla de onboarding para tablas nuevas: toda nueva entidad sync debe agregarse a la checklist, a la guia tecnica correspondiente, al smoke/test mas cercano y a `QA_STATUS.md` si entra con deuda o excepcion
- se vinculo la checklist desde `QA_ROADMAP.md`, `qa/REGRESSION_CHECKLIST.md`, `sisa.api/docs/sync-references-qa-guide.md` y `sisa.ui/docs/architecture/devices-sync-and-offline-first-standard.md`

Riesgo cubierto:

- evitar que futuras tablas sync repitan bugs de resurreccion, tombstones incompletos, scope stale o caches fantasma por falta de una definicion transversal minima

Validacion:

- revision manual de enlaces y contenido cruzado -> PASS

## Avance parcial - no resurreccion de empresas borradas por checkpoints stale

Estado: en progreso

Que cambio:

- `sisa.api/src/Models/Companies.php`, `sisa.api/install.php`, `sisa.api/update_install.php` y `sisa.api/scripts/migrations/companies-soft-delete-phase31.php` pasan `empresas` a un modelo con `deleted_at` y borrado logico; las lecturas operativas ahora excluyen empresas borradas por defecto, pero el backend conserva lookup `IncludingDeleted` para tombstones, auditoria y guardas anti-resurreccion
- `sisa.api/src/Controllers/CompaniesController.php` deja de hacer hard delete: al eliminar una empresa ahora revoca memberships operativas (`removed`), registra historial, hace soft delete, publica updates de `memberships` y emite un delete canonico de `member_companies` con tombstone para que otros dispositivos limpien la referencia en vez de dejar una empresa fantasma cacheada
- `sisa.api/src/Services/CompanyAccessService.php`, `sisa.api/src/Controllers/SyncOperationsController.php` y `sisa.api/src/Services/CompanyResolver.php` endurecen el scope y la resolucion legacy: una empresa borrada ya no cuenta como accesible, `company_id` soft-deleted queda rechazado en sync moderno y el resolver legacy no puede recrear implicitamente una empresa borrada desde payloads viejos de clientes
- `sisa.ui/src/modules/jobs/presentation/sync/referenceCache.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` y `sisa.ui/contexts/MemberCompaniesContext.tsx` agregan remocion explicita de tombstones para `memberships` y `member_companies`, alineando estas referencias con el mismo comportamiento de no reaparicion que ya existia para `statuses/providers/clients/folders`
- QA nuevo: `sisa.api/tests/Models/CompaniesSoftDeleteTest.php`, `sisa.api/tests/Services/CompanyResolverTest.php`, `sisa.api/tests/Services/CompanyAccessServiceTest.php`, `sisa.api/tests/Controllers/CompanyOperationalSyncPublishingTest.php` y `sisa.api/tests/Controllers/SyncOperationsControllerCompanyDeletionGuardTest.php` cubren soft delete, bloqueo de recreacion legacy, filtrado de scope sobre empresa borrada, publicacion de tombstone canonico y rechazo de `company_id` stale en sync
- `sisa.ui/scripts/sync-smoke.js` suma asserts especificos para exigir remocion de tombstones de `memberships` y `member_companies` durante bootstrap y pull

Riesgo cubierto:

- evitar la resurreccion de empresas eliminadas cuando un dispositivo descarga un checkpoint viejo, una membership stale o un replay legacy que antes podia volver a materializar la empresa o dejarla visible offline
- evitar que el backend siga autorizando escrituras bajo una empresa ya borrada solo porque la membership aprobada o el cache de referencias no se limpio todavia

Puntos ciegos conocidos:

- esta pasada corta la resurreccion desde `member_companies`/scope/legacy resolver, pero no hace una limpieza masiva automatica de memberships host ya rotas creadas antes del fix; si existe baseline contaminado, conviene correr un saneo puntual o al menos revisar memberships aprobadas apuntando a `empresas.deleted_at IS NOT NULL`
- `node scripts/sync-smoke.js` sigue bloqueado por un literal preexistente/mojibake (`Aceptar versiÃ³n del servidor`) ajeno a este cambio; el smoke ya fue actualizado para cubrir tombstones de empresas, pero la corrida completa no puede declararse verde hasta corregir ese baseline
- el contrato nuevo asume que `empresas` ya migro a `deleted_at`; en ambientes que salteen `update_install.php` seguira existiendo riesgo de hard delete fisico

Validacion parcial:

- `php -l` sobre modelos/controladores/servicios/migracion/tests tocados en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Models/CompaniesSoftDeleteTest.php tests/Services/CompanyResolverTest.php tests/Services/CompanyAccessServiceTest.php tests/Controllers/CompanyOperationalSyncPublishingTest.php tests/Controllers/SyncOperationsControllerCompanyDeletionGuardTest.php` en `sisa.api` -> PASS con el ruido preexistente de conexion DB al final de la corrida
- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS
- `node scripts/sync-smoke.js` en `sisa.ui` -> PASS
- `npm run check:sync-smoke` en `sisa.ui` -> PASS
- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` en raiz -> PASS
- `php update_install.php` en `sisa.api` -> BLOQUEADO en este entorno local por conexion DB preexistente (`SQLSTATE[HY000] [2002]`), asi que la migracion `deleted_at` para `empresas` queda validada por codigo pero no materializada localmente desde este workspace

## Avance parcial - hardening de NOT NULL en clientes/proveedores SQLite

Estado: en progreso

Que cambio:

- despues de corregir el desfasaje de placeholders apareció otro crash de arranque en dispositivo: `NOT NULL constraint failed: clients.tax_id`, señal de que algunos payloads legacy/bootstrap todavía llegan con campos requeridos nulos aunque el esquema SQLite los declare obligatorios
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteClientsRepository.ts` y `sisa.ui/src/modules/jobs/data/repositories/SQLiteProvidersRepository.ts` ahora normalizan defensivamente `business_name`, `tax_id` y `email` antes de persistir, degradando cualquier `null`/`undefined` a string vacio para que el storage local no vuelva a romper el bootstrap/smoke por filas incompletas
- el hardening queda acotado a la capa SQLite; no cambia el contrato canonico buscado (`company_id`, `client_company_id`, `provider_company_id`) ni tapa el problema semantico aguas arriba, solo evita que datos legacy o incompletos tiren abajo toda la carga inicial

Riesgo cubierto:

- evitar que una referencia vieja o incompleta de clientes/proveedores bloquee todo el arranque offline-first por constraints `NOT NULL` en SQLite

Puntos ciegos conocidos:

- este fix privilegia continuidad operativa del bootstrap; sigue pendiente auditar de donde salen exactamente los `clients` sin `tax_id`/`email`/`business_name` para no depender solo del fallback local
- `npm run check:sync-smoke` sigue bloqueado por el literal mojibake `Aceptar versiÃ³n del servidor`, ajeno a este hardening

Validacion parcial:

- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:startup-stability` en `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS

## Avance parcial - bootstrap SQLite de clientes/proveedores tras rename canonico

Estado: en progreso

Que cambio:

- `sisa.ui/src/modules/jobs/data/repositories/SQLiteClientsRepository.ts` tenia el `INSERT INTO clients` con 16 columnas declaradas pero 17 placeholders en `VALUES`, lo que explicaba el crash de arranque `Error code: 17 values for 16 columns` al hidratar referencias durante bootstrap/pull
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteProvidersRepository.ts` tambien habia quedado desalineado despues del rename semantico: el `INSERT INTO providers` mandaba un valor extra y corria los binds al intentar persistir `provider_company_id` + `company_id`
- `sisa.ui/src/modules/jobs/presentation/sync/referenceCache.ts` todavia reinyectaba aliases invertidos al volcar caches locales (`clients` usando `row.company_id` en lugar de `client_company_id`, y `providers` priorizando `empresa_id` sobre `provider_company_id`); ahora respeta primero los nombres canonicos y deja los legacy solo como fallback

Riesgo cubierto:

- evitar que el bootstrap inicial o la actualizacion incremental rompan SQLite al persistir clientes/proveedores despues del rename a `company_id`, `client_company_id` y `provider_company_id`
- reducir el riesgo de rehidratar cache local con semantica invertida y volver a mezclar empresa operativa con empresa real del cliente/proveedor

Puntos ciegos conocidos:

- `npm run check:sync-smoke` sigue bloqueado por un smoke preexistente/no relacionado que espera el literal `Aceptar versión del servidor` y hoy falla por el texto mojibake `Aceptar versiÃ³n del servidor`; no aparece ligado a este fix de `company_id`
- falta validacion manual en dispositivo real del camino exacto que venia fallando en la captura (`Base inicial` + `Actualizacion incremental`) para confirmar que ya no reaparece el rechazo de SQLite durante el startup

Validacion parcial:

- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS
- `npm run check:startup-stability` en `sisa.ui` -> PASS
- `npm run check:sync-smoke` en `sisa.ui` -> BLOQUEADO por smoke/literal preexistente `Aceptar versiÃ³n del servidor`

## Avance parcial - fix de ruta duplicada en sync/push

Estado: en progreso

Que cambio:

- `sisa.api/src/Routes/api.php` tenia dos registros de `POST /sync/push`: uno canonico hacia `SyncOperationsController::push` y otro legacy hacia `SyncController::processPush`, lo que hacia explotar FastRoute antes de resolver cualquier endpoint, incluido `/login`
- para destrabar el router sin perder el handler viejo, la ruta legacy se movio a `POST /sync/legacy/push`; `POST /sync/push` queda como endpoint canonico unico de sync operations

Riesgo cubierto:

- evitar que Slim falle al boot por rutas duplicadas y deje toda la API respondiendo `500` aunque el endpoint consultado no sea de sync

Puntos ciegos conocidos:

- si existe algun cliente antiguo que llame explicitamente a `POST /sync/push` esperando el comportamiento de `SyncController::processPush`, ahora va a entrar al flujo canonico nuevo; el handler legacy sigue disponible solo en `/sync/legacy/push`

Validacion parcial:

- `php -l src/Routes/api.php` en `sisa.api` -> PASS

## Avance parcial - diagnostico directo en respuesta HTTP de /login

Estado: en progreso

Que cambio:

- `sisa.api/index.php` ahora fuerza respuesta verbose para excepciones de `POST /login` aunque `APP_DEBUG` este apagado: incluye `exception`, `message`, `file`, `line`, `trace`, `trace_as_string` y metadata basica de la request para poder copiar la falla completa desde Postman
- el cambio es tactico de diagnostico y queda acotado a `/login` / `/api/login`; no abre el resto de endpoints

Riesgo cubierto:

- evitar depender del acceso a logs del host cuando el bloqueo actual esta especificamente en el login HTTP y hace falta ver el stack real de Slim/PHP desde el cliente

Puntos ciegos conocidos:

- sigue siendo informacion sensible para un ambiente publico; una vez detectada la causa raiz conviene retirar este modo verbose

Validacion parcial:

- `php -l index.php` en `sisa.api` -> PASS

## Avance parcial - revert tactico de hardening HTTP en /login

Estado: en progreso

Que cambio:

- por pedido explicito de volver al comportamiento previo y comparar contra el codigo anterior, `sisa.api/index.php` y `sisa.api/src/Routes/api.php` se devolvieron al flujo simple original de login/refresh
- esta reversión saca los helpers y logs agregados durante la investigacion de hoy y restaura la respuesta anterior, incluido el header `Authorization` cuando el login o refresh devuelven token

Riesgo cubierto:

- evitar seguir mezclando cambios de diagnostico con el problema original mientras se intenta aislar si el `500` aparece solo en el camino HTTP previo

Puntos ciegos conocidos:

- la causa raiz del `500` HTTP en host sigue abierta; este paso solo devuelve la ruta al comportamiento anterior para facilitar la comparacion

Validacion parcial:

- `php -l index.php` en `sisa.api` -> PASS
- `php -l src/Routes/api.php` en `sisa.api` -> PASS

## Avance parcial - hardening HTTP de /login en API

Estado: en progreso

Que cambio:

- `sisa.api/src/Routes/api.php` ahora trata `POST /login` como contrato HTTP explicito: lee el body de forma segura sin depender de streams ya consumidos, rechaza JSON invalido con `400`, serializa la respuesta siempre como JSON UTF-8 y mapea errores funcionales de login a status controlados (`400/401/403`) en vez de dejar `200` o escalar a `500`
- la ruta de login deja de reflejar el token en el header de respuesta `Authorization`; el token sigue saliendo en el body JSON, evitando un punto sospechoso de incompatibilidad con hosting/proxy mientras se conserva compatibilidad con clientes que ya leen `token` del payload
- `sisa.api/src/Routes/api.php` tambien unifica `POST /token/refresh` sobre la misma salida JSON robusta, sin depender del header `Authorization` en la respuesta
- `sisa.api/index.php` agrega logging seguro solo para excepciones de `/login`: ahora escribe en `sisa.api/uploads/logs/auth-login.log` (creando la carpeta si falta) con clase, mensaje sanitizado, archivo, linea, IP, content-type, user-agent y un trace resumido, sin password ni token completo; si no puede escribir, recien ahi cae al `error_log` tradicional
- ajuste posterior: como el archivo solo nacia cuando habia excepcion, `sisa.api/index.php` ahora asegura `uploads/logs/auth-login.log` y `sisa.api/src/Routes/api.php` escribe eventos minimos de entrada/salida de `POST /login` aun sin exception, para confirmar rapido desde host que la ruta realmente esta pasando por Slim

Riesgo cubierto:

- que el login HTTP falle por diferencias entre el camino CLI y la capa Slim/hosting, ya sea por body parsing fragil, serializacion de respuesta o uso del header `Authorization` en la respuesta
- que un fallo real de middleware/ruta siga entrando como `500` opaco sin contexto operativo suficiente para diagnosticarlo en host

Puntos ciegos conocidos:

- en este workspace no pude confirmar el happy path HTTP con credenciales reales porque el entorno local no levanta la API completa: al boot aparece una falla preexistente de conexion DB antes de poder emular el endpoint extremo a extremo
- sigue pendiente validar en el host objetivo que el `500` confirmado desaparece efectivamente y capturar el nuevo log sanitizado si todavia sobrevive otra excepcion aguas arriba

Validacion parcial:

- `php -l index.php` en `sisa.api` -> PASS
- `php -l src/Routes/api.php` en `sisa.api` -> PASS

## Avance parcial - login y bootstrap inicial bloqueantes

Estado: en progreso

Que cambio:

- `sisa.ui/contexts/BootstrapContext.tsx` ahora orquesta el arranque autenticado en orden util para primera sesion: perfil, configuracion, empresas/memberships, resolucion de empresa activa y permisos antes de marcar la shell como lista
- `sisa.ui/contexts/PermissionsContext.tsx` acepta un `companyId` explicito para refrescar permisos del scope que acaba de seleccionarse durante bootstrap, sin depender de un reinicio/remount para que el provider vea primero el `selected-company-id`
- `sisa.ui/contexts/ProfileContext.tsx` y `sisa.ui/contexts/ConfigContext.tsx` ahora devuelven estado de bootstrap (`server`/`cache`/`failed`) en vez de ocultar silenciosamente si el fetch critico realmente quedo incompleto
- `sisa.ui/contexts/bootstrapTypes.ts` incorpora la etapa `profile` para dejar trazabilidad real del arranque critico en diagnostico y status persistido
- `sisa.ui/app/_layout.tsx` y `sisa.ui/app/login/Login.tsx` ya no dejan entrar/redirigir a `Home` apenas existe username; mantienen spinner de sesion hasta que el bootstrap critico termina, evitando la sensacion de app rota en el primer login
- segunda pasada: `sisa.ui/contexts/BootstrapContext.tsx` ahora bloquea tambien la entrada hasta hidratar datos operativos por empresa; si no existe checkpoint local corre bootstrap completo de jobs/referencias y si ya existe aplica pull incremental antes de habilitar la shell
- `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` deja de guardar el checkpoint solo en scope global y ahora lo persiste por `company_id`, alineando la primera carga completa con el pull incremental posterior
- `sisa.ui/components/StartupLoadingScreen.tsx` agrega una pantalla inicial de carga con etapas visibles, progreso del bootstrap y estado del checkpoint/importados para que la espera del primer arranque no parezca un freeze
- `sisa.ui/components/BottomNavigationBar.tsx` y `sisa.ui/contexts/BootstrapContext.tsx` dejan de limpiar la empresa activa cuando todavia existen memberships validas pero la coleccion `memberCompanies` aun no termino de rehidratarse
- `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` recupera la forma esperada por el smoke de startup para la guarda de autosync durante operaciones activas
- tercera pasada: la causa de que `Home`/autosync siguieran viendo `selectedCompanyId = null` aunque bootstrap ya hubiese elegido la default era estructural; `sisa.ui/hooks/useCachedState.ts` no propagaba cambios entre consumers vivos del mismo `cacheKey`, asi que cada hook quedaba con una copia local vieja hasta remount
- `sisa.ui/hooks/useCachedState.ts` ahora publica updates por clave a todos los subscribers activos; cuando bootstrap fija `selected-company-id` desde `config.company_default_id`, el resto de providers/hooks lo ve en caliente sin requerir cerrar/reabrir ni navegar para forzar remount
- cuarta pasada: `sisa.ui/app/user/CompanyPreferenceScreen.tsx` agrega una pantalla dedicada para elegir empresa activa y, opcionalmente, guardarla tambien como predeterminada del usuario dentro de Configuración
- `sisa.ui/app/user/ConfigScreen.tsx` ahora expone un acceso explicito a esa pantalla, dejando la empresa por defecto administrable desde ajustes y no implícitamente desde otros flujos
- `sisa.ui/components/BottomNavigationBar.tsx` deja de cambiar la default por detrás; seleccionar una empresa desde la barra inferior ahora abre la pantalla nueva con la empresa preseleccionada para confirmar el cambio y recargar la sesión sobre ese scope
- `sisa.ui/contexts/BootstrapContext.tsx` acepta refresh dirigido por `companyId`, permitiendo que el cambio de empresa dispare un bootstrap bloqueante de la nueva empresa antes de volver a `Home`
- quinta pasada: ante evidencia de que el switch de empresa sigue dejando rutas colgadas y datos de la empresa anterior, se creó `qa/COMPANY_SWITCH_HARDENING_CHECKLIST.md` como plan/checklist ejecutable para cerrar el problema por etapas: ruteo, bootstrap bloqueante, auditoría de `company_id`, queries SQLite, contexts, limpieza de estado y smokes de regresión
- sexta pasada: primer slice ejecutado del checklist de company switch. `sisa.ui/app/companies/index.tsx`, `sisa.ui/app/companies/view.tsx` y `sisa.ui/app/companies/memberships.tsx` dejan de usar `?id=` genérico en rutas estáticas del árbol de empresas y migran a `companyId`, reduciendo colisiones con `companies/[id]`
- `sisa.ui/app/companies/[id].tsx` ya no muestra alerta de “empresa no encontrada” cuando el detalle queda stale tras un switch válido; ahora sale silenciosamente a `/companies`
- `sisa.ui/contexts/ClientsContext.tsx`, `sisa.ui/contexts/ProvidersContext.tsx` y `sisa.ui/contexts/FoldersContext.tsx` ahora recortan hidratación/publicación/fetch remoto por `selected-company-id`, evitando que el provider publique rows de otra empresa como si fueran de la activa
- `sisa.ui/src/modules/jobs/presentation/sync/referenceCache.ts` deja de destruir `company_id` en folders al aplicar bootstrap/sync local, que era un bug concreto de scope
- séptima pasada: segundo slice del checklist de company switch. `sisa.ui/contexts/JobsContext.tsx` deja de usar un cache único global de jobs y pasa a separar por empresa activa + fetch remoto con `company_id`, evitando que los jobs legacy del contexto arrastren filas de otra empresa
- `sisa.ui/contexts/CategoriesContext.tsx` y `sisa.ui/contexts/JobPrioritiesContext.tsx` ya no publican/hidratan ciegamente colecciones globales: ahora recortan cache/SQLite/publicación por `selected-company-id`, reduciendo flashes y mezcla de catálogos entre empresas
- octava pasada: tercer slice del checklist de company switch. `sisa.ui/contexts/StatusesContext.tsx`, `sisa.ui/contexts/AppointmentsContext.tsx`, `sisa.ui/contexts/PaymentsContext.tsx`, `sisa.ui/contexts/CashBoxesContext.tsx`, `sisa.ui/contexts/TariffsContext.tsx` y `sisa.ui/contexts/ProductsServicesContext.tsx` ahora aplican primer aislamiento por empresa activa, ya sea con cache key por empresa o recorte explícito por `selected-company-id`
- en particular `AppointmentsContext` deja de compartir una sola bolsa global de citas entre empresas y `Statuses/Tariffs` dejan de hacer reemplazos globales de SQLite que podían pisar o reinyectar datos fuera del scope actual
- novena pasada: `sisa.ui/contexts/InvoicesContext.tsx` y `sisa.ui/contexts/ReceiptsContext.tsx` pasan a cache por empresa activa y filtran publicación/confirmación remota por `company_id`, cerrando el bloque principal de contexts financieros que todavía podían mostrar filas de otra empresa tras el switch
- décima pasada: se corrige el modelo semántico en frontend para clientes y proveedores. En `sisa.ui/contexts/ClientsContext.tsx` el campo canónico pasa a ser `client_company_id` para la empresa del cliente y `company_id` queda reservado para la empresa emisora/activa; en `sisa.ui/contexts/ProvidersContext.tsx` `provider_company_id` reemplaza a `empresa_id` como nombre canónico para la empresa del proveedor
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteClientsRepository.ts`, `sisa.ui/src/modules/jobs/data/repositories/SQLiteProvidersRepository.ts` y `sisa.ui/src/modules/jobs/presentation/sync/referenceCache.ts` mantienen compatibilidad con columnas/keys legacy, pero exponen el modelo corregido hacia el resto de la UI
- `sisa.ui/app/clients/create.tsx`, `sisa.ui/app/clients/[id].tsx` y `sisa.ui/app/providers/create.tsx` ya envían/consumen los nombres corregidos a nivel de pantalla
- undécima pasada: se empuja la limpieza semántica a hooks internos de sync/bootstrap. `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` ya generan `client_company_id` / `provider_company_id` como nombres canónicos al poblar caches locales, manteniendo fallback a `empresa_id` y `emitter_company_id` solo como compatibilidad de payload
- duodécima pasada: se agrega migración dedicada de storage en `sisa.ui/src/modules/jobs/data/db/jobsMigrations.ts` y `sisa.ui/src/modules/jobs/data/db/schema.ts` para alinear SQLite al modelo canónico. `clients` pasa a usar `client_company_id` + `company_id` y `providers` pasa a usar `provider_company_id` + `company_id`, con copia de datos legacy y recreación de índices
- `sisa.ui/src/modules/jobs/data/repositories/SQLiteClientsRepository.ts` y `sisa.ui/src/modules/jobs/data/repositories/SQLiteProvidersRepository.ts` ya escriben/leen contra las nuevas columnas del esquema local
- incidente detectado en dispositivo: la migración v24 asumía que toda base previa todavía tenía `emitter_company_id` / `empresa_id`; en instalaciones frescas o bases ya recreadas con esquema nuevo, ese supuesto rompía el arranque con `no such column: emitter_company_id`
- corrección aplicada: `sisa.ui/src/modules/jobs/data/db/jobsMigrations.ts` ahora inspecciona columnas existentes antes de recrear `clients` y `providers`; solo referencia columnas legacy si realmente existen y, si la tabla ya está en forma canónica, se limita a asegurar índices
- incidente adicional detectado en arranque: warning React `Cannot update a component (PermissionsProvider) while rendering a different component (MemberCompaniesProvider)` por dependencia cruzada sobre la key compartida `member-companies-memberships`
- corrección estructural aplicada: `sisa.ui/app/_layout.tsx` reordena providers para cargar `CompaniesProvider` + `MemberCompaniesProvider` antes de `PermissionsProvider`; además `sisa.ui/contexts/PermissionsContext.tsx` deja de leer membresías con `useCachedState` compartido y pasa a consumir `MemberCompaniesContext`, esperando `membershipsHydrated` antes de refrescar permisos
- `sisa.ui/contexts/MemberCompaniesContext.tsx` ahora expone `membershipsHydrated` para que permisos/bootstrap puedan sincronizarse sin setState cruzado durante render
- ajuste adicional de UX/estado: `sisa.ui/app/user/CompanyPreferenceScreen.tsx` ahora trata la empresa elegida como activa también cuando se guarda como nueva predeterminada desde Configuración; antes, si cambiaba la default pero la sesión no había cambiado explícitamente, otro fallback podía terminar dejando activa otra empresa (por ejemplo la última creada)
- corrección posterior sobre cambio de empresa: `sisa.ui/contexts/BootstrapContext.tsx` estaba comparando `requestedCompanyId` contra `nextSelectedCompanyId` al momento de persistir la empresa activa; eso impedía grabar la empresa nueva cuando la resolución coincidía con la solicitada, dejando viva la empresa anterior en `selected-company-id`
- además `sisa.ui/app/user/CompanyPreferenceScreen.tsx` deja de preescribir `selected-company-id` antes del bootstrap bloqueante; ahora deja que `BootstrapContext` consolide el cambio al final, reduciendo carreras y dobles recargas visuales durante el switch
- warning residual de infraestructura: `sisa.ui/hooks/useCachedState.ts` todavía ejecutaba efectos secundarios (`memoryCache`, listeners y persistencia) dentro del updater funcional de `setState`, lo que seguía siendo candidato fuerte a `Cannot update a component while rendering a different component`
- fix aplicado: `sisa.ui/hooks/useCachedState.ts` ahora mantiene el updater puro, resuelve `nextValue` desde un `stateRef` y mueve escritura a memoria/persistencia/notificación a una función auxiliar fuera del updater; se mantiene la API pública `[state, setCachedState, hydrated]`
- mejora operativa de soporte: `sisa.ui/components/LogOverlay.tsx` ahora agrega acción `Copiar todo` para exportar el registro completo de eventos al portapapeles, útil para pasar errores largos de arranque/SQLite sin depender de capturas parciales
- incidente posterior detectado con el registro copiado: la migración `v24` de `sisa.ui/src/modules/jobs/data/db/jobsMigrations.ts` seguía ejecutando un `UPDATE providers ... empresa_id ...` incluso en bases donde esa columna ya no existía; se corrigió para inspeccionar la columna antes de construir el SQL, evitando el arranque roto con `no such column: empresa_id`
- incidente de login detectado: el servidor puede devolver HTML en `/profile` o en errores de `/login`, lo que disparaba `JSON Parse error: Unexpected character: <` y bloqueaba la sesión aunque el token de login ya se hubiera emitido
- corrección aplicada: `sisa.ui/contexts/AuthContext.tsx` ahora tolera respuestas no JSON en login/perfil, decodifica `id`/`email` desde el JWT como fallback y reporta mejor cuando el backend devuelve HTML en vez de JSON
- incidente posterior de login exitoso con 500 HTML: el camino “happy path” de `sisa.api/src/Controllers/AuthController.php` podía romperse al persistir `auth_sessions` o sincronizar `devices`, devolviendo HTML 500 aunque las credenciales fueran correctas
- corrección aplicada: `AuthController::login()` ahora hace fallback a flujo legacy si falla la persistencia de sesión/dispositivo; genera token sin `sid`, guarda `api_token` y evita que un problema de infraestructura secundaria bloquee el ingreso del usuario
- hardening adicional del contrato auth: `sisa.api/index.php` ahora fuerza respuestas JSON también para excepciones globales de Slim, evitando HTML 500 en clientes móviles; en paralelo `sisa.ui/contexts/AuthContext.tsx` manda `Accept: application/json` en `/login`, `/profile` y `/token/refresh`, y reutiliza parsing robusto para diagnosticar mejor cualquier desvío del contrato
- resolución de conflicto post-`git pull origin main`: se repararon conflictos en `sisa.ui/config/Index.ts`, `sisa.ui/contexts/ClientsContext.tsx`, `sisa.ui/contexts/ProvidersContext.tsx`, `sisa.ui/src/modules/jobs/data/db/schema.ts`, `sisa.ui/src/modules/jobs/data/db/jobsMigrations.ts`, `sisa.ui/src/modules/jobs/data/repositories/SQLiteClientsRepository.ts` y `sisa.ui/src/modules/jobs/data/repositories/SQLiteProvidersRepository.ts`
- criterio usado en la resolución: preservar el rumbo semántico nuevo (`client_company_id`, `provider_company_id`, `company_id`), mantener el aislamiento por empresa activa y evitar volver a `replaceAll()` global/columnas legacy invertidas en clientes y proveedores
- resolución equivalente en API: se cerraron los conflictos de `sisa.api` y se tomó el mismo rumbo semántico. `src/Controllers/ClientsController.php`, `src/Controllers/ProvidersController.php`, `src/Models/Clients.php` y `src/Models/Providers.php` ahora aceptan aliases legacy pero priorizan `client_company_id` / `provider_company_id` como nombres canónicos, dejando `company_id` para la empresa operativa/emisora
- los conflictos no relacionados (`JobsController`, `JobItemsController`, `SyncController`, `FileAttachments`) se resolvieron preservando la versión de la rama de trabajo para no mezclar esta alineación semántica con cambios laterales durante el merge

Riesgo cubierto:

- que un usuario nuevo entre a una shell medio vacia, sin empresa activa o sin permisos, y necesite cerrar/abrir varias veces para terminar de hidratar la sesion
- que el primer render autenticado quede corriendo con permisos `skipped` porque el `selected-company-id` todavia no existia al momento del fetch inicial

Puntos ciegos conocidos:

- el flujo ahora bloquea hasta terminar bootstrap critico + carga/pull inicial de datos operativos; si la base de una empresa es muy grande, conviene medir en dispositivo real si hace falta partir la etapa visual en sub-bloques adicionales por dominio
- el cambio de empresa todavía no puede darse por cerrado: ya existe pantalla intermedia y bootstrap dirigido, pero falta auditar aislamientos por `company_id` en tablas/queries/contextos para eliminar por completo mezcla de datos entre empresas
- siguen pendientes auditorías equivalentes en `Payments`, `Receipts`, `Invoices`, `CashBoxes`, `ProductsServices`, `Tariffs`, `Statuses`, `Appointments` y otros consumers con caches/SQLite compartidos; este segundo slice cierra jobs/categorías/prioridades pero no todo el mapa company-scoped
- luego de este tercer slice, el mayor bloque pendiente queda concentrado en `Receipts`, `Invoices` y validación fina en dispositivo real de que los contexts recién scopeados no conservan flashes/stale rows en navegación larga
- con este cuarto slice ya no queda un context financiero principal pendiente de scopeo inicial; lo que resta es validación fina en dispositivo real y consumers/pantallas secundarias que todavía puedan recombinar datos legacy fuera del provider principal
- todavía quedan nombres legacy en capas internas de compatibilidad (`empresa_id`, `emitter_company_id`, columnas SQLite existentes) para no mezclar este fix semántico con una migración destructiva de storage; si más adelante se quiere limpieza total, conviene hacer una migración dedicada y separada
- después de esta pasada, los nombres legacy quedan más acotados a compatibilidad de storage/esquema y payloads viejos; la UI y los hooks principales ya hablan mayormente en términos de `client_company_id`, `company_id` y `provider_company_id`
- tras esta migración, los nombres legacy quedan principalmente como compatibilidad de payload/sync remoto; el storage SQLite nuevo ya queda alineado con el modelo canónico

Validacion parcial:

- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS
- `npm run check:startup-stability` en `sisa.ui` -> PASS

## Avance parcial - duplicados de participantes en worklogs sync/offline

Estado: en progreso

Que cambio:

- `sisa.api/src/Models/WorkLogParticipants.php` ahora deduplica la lectura activa por `user_id`, hace `softDelete` fila por fila con timestamps distintos para no chocar con el indice `(work_log_id, user_id, deleted_at)` y restaura filas soft-deleted existentes en vez de insertar otra copia del mismo participante
- `sisa.api/src/Controllers/WorkLogsController.php` y `sisa.api/src/Controllers/SyncOperationsController.php` pasan a usar `createOrRestoreActive()` al reemplazar participantes, frenando la acumulacion de filas repetidas del mismo tecnico sobre un mismo worklog cuando el registro se vuelve a sincronizar o regrabar
- segunda pasada preventiva: `sisa.api/src/Models/AppointmentParticipants.php` aplica la misma estrategia de dedupe en lecturas activas y restauracion de filas soft-deleted, y `sisa.api/src/Controllers/AppointmentsController.php` deja de insertar otra fila activa del mismo tecnico al regrabar participantes de una cita
- `sisa.api/tests/Models/WorkLogParticipantsTest.php` y `sisa.api/tests/Models/AppointmentParticipantsTest.php` cubren duplicates activos legacy y verifican tanto el borrado seguro como la restauracion de filas previas para no volver a crear otra activa del mismo usuario
- `qa/WORKLOG_PARTICIPANTS_DEDUPE_RUNBOOK.md` deja el SQL de preview + limpieza para compactar los duplicados activos ya existentes en host tanto para worklogs como para appointments

Riesgo cubierto:

- que un worklog muestre/calcule dos veces al mismo participante por acumulacion de filas activas duplicadas en `work_log_participants`
- que un reemplazo de participantes falle o deje basura historica adicional cuando ya existe baseline roto con duplicados activos del mismo `work_log_id` + `user_id`

Puntos ciegos conocidos:

- este cambio evita nuevas duplicaciones y deja de exponer duplicados activos existentes en lecturas API, pero no corre todavia una limpieza masiva sobre la base host para compactar historico ya generado
- `tests/Controllers/WorkLogsControllerTest.php` no quedo util como validacion adicional en esta pasada porque sigue levantando una falla preexistente de baseline SQLite en `PaymentTemplates`/`SyncEventGenerator` ajena al fix de participantes
- el runbook SQL de limpieza usa `ROW_NUMBER()`, asi que asume MySQL 8+ en el host; si el server estuviera en una version anterior, hay que bajar a una variante sin window functions

Validacion parcial:

- `vendor/bin/phpunit tests/Models/WorkLogParticipantsTest.php tests/Models/AppointmentParticipantsTest.php` en `sisa.api` -> PASS (4 tests, 36 assertions)
- intento de `vendor/bin/phpunit tests/Controllers/WorkLogsControllerTest.php` en `sisa.api` -> bloqueado por falla baseline preexistente de SQLite en `PaymentTemplates`/`SyncEventGenerator`, no por el cambio de `work_log_participants`
- `php -l src/Models/AppointmentParticipants.php src/Controllers/AppointmentsController.php src/Models/WorkLogParticipants.php tests/Models/AppointmentParticipantsTest.php tests/Models/WorkLogParticipantsTest.php` en `sisa.api` -> PASS

## Avance parcial - reversión de factura anulada/eliminada

Estado: en progreso

Que cambio:

- `sisa.api/src/Services/InvoiceCancellationService.php` centraliza la anulación/eliminación transaccional de facturas, revierte `jobs` a estado terminado, libera `payments`, soft-deletea `invoice_items` y devuelve un resumen operativo de la reversión
- `sisa.api/src/Services/JobStatusResolver.php` resuelve de forma centralizada los `status_id` de jobs para estados facturado/terminado usando `scope = job` y matching por `code`/`label`, evitando volver a hardcodear IDs mágicos
- `sisa.api/src/Controllers/InvoicesController.php` deja de tener la lógica pesada de delete/void y delega en el servicio nuevo, manteniendo respuestas API con `invoice_id`, contadores de liberación y `status`
- `sisa.api/src/Models/Invoices.php`, `sisa.api/src/Models/Jobs.php`, `sisa.api/src/Models/Payments.php` y `sisa.api/src/Models/ActivityLog.php` suman helpers puntuales para actualizar estados/versiones/source device sin hard delete y para tocar disponibilidad facturable de pagos
- `sisa.ui/contexts/InvoicesContext.tsx` ahora refresca también `jobs` y `payments` inmediatamente después de crear, editar, anular o eliminar una factura, evitando que la UI espere solo el reload de facturas o el autosync para reflejar la liberación
- `sisa.ui/app/invoices/create.tsx` deja de disparar updates secuenciales extra de jobs uno por uno después de facturar; el cambio de estado queda a cargo del backend transaccional y la pantalla solo muestra el resultado mientras fuerza recarga inmediata de caches afectadas
- ajuste de fuente real de datos: `sisa.ui/contexts/JobsContext.tsx` ahora no solo recarga la lista legacy desde API sino que también vuelca esos jobs al repositorio SQLite local (`jobsRepository.upsertRemote`) y dispara `notifyJobsAutoSync()`, para que pantallas basadas en `useJobsList()` vean el cambio de estado en el acto
- robustez UI: `sisa.ui/contexts/InvoicesContext.tsx` ya no marca error al usuario si la eliminación/anulación HTTP fue exitosa pero falla una recarga secundaria o la limpieza de cache local; esos refreshes quedan en modo best-effort con warning en consola
- flujo de borrado desde editar factura: `sisa.ui/contexts/InvoicesContext.tsx` trata `200`, `204` y `404` post-delete como éxito funcional, y `sisa.ui/app/invoices/[id].tsx` bloquea doble ejecución, evita refetch de la factura eliminada y navega al listado apenas confirma éxito
- confirmación funcional extra: si el `DELETE` devuelve respuesta anómala pero el backend ya borró la factura, `sisa.ui/contexts/InvoicesContext.tsx` ahora hace una verificación `GET /invoices/{id}` y toma `404`/"not found" como éxito definitivo antes de mostrar error
- ajuste adicional: como `GET /invoices/{id}` puede seguir devolviendo facturas soft-deleted, la confirmación post-delete ahora valida contra `GET /invoices` y considera éxito si la factura ya no figura en el listado operativo
- `sisa.api/tests/Services/InvoiceCancellationServiceTest.php` cubre liberación de jobs+payments, rollback por `company_id` cruzado y protección cuando el mismo job/payment sigue referenciado por otra factura activa
- `sisa.api/tests/Services/InvoiceLineNormalizerTest.php` ahora verifica también que un payment vuelva a ser reincluible cuando el `invoice_item` previo quedó anulado por soft delete

Riesgo cubierto:

- que al anular o eliminar una factura queden trabajos o pagos trabados como facturados y no reaparezcan para una nueva factura
- que la reversión libere entidades de otra empresa o pise incorrectamente un job/payment todavía referenciado por otra factura activa

Puntos ciegos conocidos:

- no se corrió en esta pasada una prueba manual end-to-end en dispositivo para medir el tiempo percibido exacto entre facturar/anular y ver el cambio en todas las pantallas abiertas al mismo tiempo

Validacion parcial:

- `npm run lint` en `sisa.ui` -> PASS
- `vendor/bin/phpunit tests/Services/InvoiceCancellationServiceTest.php tests/Services/InvoiceLineNormalizerTest.php` en `sisa.api` -> PASS (11 tests, 52 assertions)

## Ultima actualizacion

- Fecha: 2026-05-02
- Corrida baseline: PASS
- PHPUnit suite: ~60 tests pasan (ruido de conexion filtrado)
- Lint: PASS
- Cache guard: PASS
- Sync smoke: PASS
- Tests integracion multi-dispositivo: 119/119 PASS
- Transformacion de reportes: COMPLETADA

## Avance parcial - bootstrap offline de memberships y empresas operativas

Estado: en progreso

Que cambio:

- `sisa.api/src/Controllers/BootstrapController.php` ahora permite incluir `memberships` y `member_companies` dentro del bootstrap inicial, junto con `clients` y `providers`, para que la sesion operativa no dependa de pegarle de nuevo a `/companies/member` o `/companies` antes de quedar utilizable offline
- el payload backend agrega versiones de frescura para `memberships` y `member_companies`, usando `version` cuando existe y cayendo a `updated_at`/`created_at`/`id` cuando esas entidades todavia no tienen version canonica
- `sisa.ui/contexts/BootstrapContext.tsx` amplia el `include` del startup bootstrap a `statuses`, `tariffs`, `folders`, `clients`, `providers`, `memberships` y `member_companies`, y persiste las memberships operativas en cache critica apenas llega la respuesta
- `sisa.ui/contexts/MemberCompaniesContext.tsx` ahora intenta hidratar memberships desde el startup bootstrap del `selected-company-id` antes del fetch remoto, para que el selector/logica de empresa sobrevivan a un arranque sin conexion
- `sisa.ui/contexts/CompaniesContext.tsx` ahora injerta en cache las `member_companies` del startup bootstrap cuando faltan localmente, evitando que una membresia aprobada quede sin su empresa operativa disponible durante el arranque offline
- `sisa.ui/components/OperationGuardStatusIndicator.tsx` recupera el label esperado por el smoke de startup, manteniendo la verificacion automatica del flujo protegido despues de tocar el bootstrap
- segunda pasada: `sisa.api/src/Controllers/SyncOperationsController.php` ahora incorpora `memberships` y `member_companies` al contrato de `sync/v3/bootstrap/references`, `verify` y `reconcile`, manteniendolos fuera del filtro por empresa seleccionada para preservar el set completo de empresas operativas del usuario
- `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` y `sisa.ui/src/modules/jobs/presentation/sync/referenceCache.ts` ya entienden esas nuevas referencias, las cachean y las vuelcan sobre `member-companies-memberships` / `companies` para que el shell pueda rehidratar memberships y empresas tambien desde el sync generico de referencias
- `sisa.ui/contexts/MemberCompaniesContext.tsx` y `sisa.ui/contexts/CompaniesContext.tsx` ahora reaccionan a updates del reference cache, de modo que un bootstrap/sync posterior pueda refrescar la capa operativa sin requerir remount completo de la app
- tercera pasada: `sisa.api/src/Controllers/SyncOperationsController.php` ahora adjunta `reference_refreshes` en `pull` y `events` cuando cambia el hash de `memberships` o `member_companies` para ese dispositivo/scope, persistiendo un cursor liviano en `device_sync_state` y evitando mandar siempre el mismo bloque
- `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` ya consume esos `reference_refreshes` y los aplica sobre cache persistente, con lo cual una sesion abierta puede converger memberships/empresas operativas sin esperar a reiniciar la app ni depender solo del startup bootstrap
- cuarta pasada: `sisa.ui/contexts/BootstrapContext.tsx` y `sisa.ui/components/BottomNavigationBar.tsx` ahora endurecen `selected-company-id`; si la empresa activa deja de pertenecer al set operativo del usuario, la app la limpia o la reasigna automaticamente a una empresa valida (default si sigue aprobada, sino la primera disponible)
- quinta pasada: `sisa.api/src/Services/SyncEventGenerator.php` ya soporta operaciones canonicas para `memberships` y `member_companies`; esas entidades viajan con UUID deterministico de referencia, payload canonico y `company_id = null` en `sync_operations` para que el delta llegue aunque la sesion este posicionada en otra empresa seleccionada
- `sisa.api/src/Controllers/CompanyUsersController.php` ahora publica operaciones canonicas al crear, invitar, aprobar, rechazar, suspender, remover, salir o cancelar memberships, y tambien reemite el snapshot de `member_companies` para mantener alineada la empresa operativa vinculada
- `sisa.api/src/Controllers/CompaniesController.php` ahora publica operaciones canonicas de `member_companies` cuando se crea o actualiza una empresa, y al crear empresa tambien emite la membership owner inicial como operacion canonica
- sexta pasada: agregados hooks/factories de test en `sisa.api/src/Controllers/CompanyUsersController.php` y `sisa.api/src/Controllers/CompaniesController.php`, mas la suite `sisa.api/tests/Controllers/CompanyOperationalSyncPublishingTest.php`, para verificar explicitamente que ambos controllers emiten snapshots canonicos de `memberships` y `member_companies`
- septima pasada: `sisa.api/src/Controllers/SyncOperationsController.php` ahora degrada `reference_refreshes` a fallback real; si en el mismo `pull/events` ya viaja una operacion canonica de `memberships` o `member_companies`, ese entity type deja de reenviarse por sideband y solo se conserva el faltante
- `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` suma cobertura para ese comportamiento de fallback durante `pull`, verificando que el sideband no duplique `memberships` cuando ya llegaron como operacion canonica

Riesgo cubierto:

- que la app recupere token/cache local pero no pueda reconstruir a tiempo las memberships y empresas operativas del usuario, dejando roto el arranque offline-first aunque el resto de referencias criticas exista

Puntos ciegos conocidos:

- memberships y empresas operativas ya tienen operaciones canonicas reales en `sync_operations`; `reference_refreshes` queda solo como fallback parcial para el entity type que no haya llegado por operaciones en ese poll

Validacion parcial:

- `php -l src/Controllers/BootstrapController.php` en `sisa.api` -> PASS
- `php -l src/Controllers/SyncOperationsController.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php` en `sisa.api` -> PASS con ruido preexistente de conexion DB al final de la corrida
- rerun `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php --testdox` en `sisa.api` tras agregar `reference_refreshes` en pull/events -> PASS con el mismo ruido preexistente de conexion DB al final
- `php -l src/Services/SyncEventGenerator.php` en `sisa.api` -> PASS
- `php -l src/Controllers/CompanyUsersController.php` en `sisa.api` -> PASS
- `php -l src/Controllers/CompaniesController.php` en `sisa.api` -> PASS
- rerun `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php --testdox` en `sisa.api` tras agregar operaciones canonicas para memberships/member_companies -> PASS con el mismo ruido preexistente de conexion DB al final
- `vendor/bin/phpunit tests/Controllers/CompanyOperationalSyncPublishingTest.php` en `sisa.api` -> PASS
- rerun `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php --testdox` en `sisa.api` tras degradar `reference_refreshes` a fallback parcial -> PASS con el mismo ruido preexistente de conexion DB al final
- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:startup-stability` en `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS

## Avance parcial - prioridades de jobs offline-first y permisos propios

Estado: completado

Que cambio:

- `sisa.api/install.php`, `sisa.api/update_install.php` y `sisa.api/scripts/migrations/job-priorities-offline-first-phase30.php` agregan la entidad `job_priorities` con historial, seed inicial y migracion incremental segura para ambientes existentes
- `sisa.api/src/Controllers/JobPrioritiesController.php`, `sisa.api/src/Models/JobPriorities.php`, `sisa.api/src/Routes/api.php` y `sisa.api/src/Models/Permission.php` incorporan CRUD dedicado, permisos propios (`list/get/add/update/delete/list history`) y publicacion de eventos canonicos para convergencia multi-dispositivo
- `sisa.api/src/Controllers/BootstrapController.php`, `sisa.api/src/Controllers/SyncOperationsController.php` y `sisa.api/src/Services/SyncEventGenerator.php` meten `job_priorities` en startup bootstrap, bootstrap/references v3, verify/reconcile, pull de eventos y resolucion de payload/company para que la referencia viaje y persista offline como el resto del baseline operativo
- `sisa.ui/contexts/JobPrioritiesContext.tsx`, `sisa.ui/utils/jobPriorities.ts`, `sisa.ui/src/modules/jobs/presentation/sync/referenceCache.ts`, `sisa.ui/src/modules/jobs/presentation/hooks/useBootstrapJobsFromApi.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/usePullJobsSync.ts` hidratan/cachean/sincronizan prioridades en cliente aun sin red, reaccionando tambien a updates del reference cache
- `sisa.ui/src/constants/permissionCatalog.ts` y `sisa.ui/app/permission/PermissionScreen.tsx` ya exponen los permisos nuevos en el administrador; la UI operativa queda en `sisa.ui/app/job-priorities/index.tsx`, `sisa.ui/app/job-priorities/create.tsx`, `sisa.ui/app/job-priorities/[id].tsx` y el acceso desde jobs respeta permisos reales
- `sisa.ui/utils/jobTotals.ts`, `sisa.api/src/Controllers/JobReportsController.php`, `sisa.ui/app/jobs/index.tsx`, `sisa.ui/app/jobs/create.tsx`, `sisa.ui/app/jobs/[id].tsx`, `sisa.ui/app/invoices/create.tsx` y `sisa.ui/app/invoices/index.tsx` consumen la configuracion de prioridad para impactar costo horario de worklogs/reportes/facturacion sin volver a hardcodear chips o labels

Riesgo cubierto:

- que la prioridad del job quede como metadata cosmetica local, sin converger entre dispositivos ni sobrevivir arranque offline, y termine generando costos distintos entre UI, reportes y facturacion
- que la administracion de prioridades reutilice permisos de estados y deje huecos de gobierno/visibilidad en el administrador de permisos

Puntos ciegos conocidos:

- la UI de prioridades ya persiste y converge offline por bootstrap/cache/eventos, pero no se implemento en esta sesion una cola local dedicada para altas/ediciones offline desconectadas como flujo separado del POST/PUT directo

Validacion parcial:

- `php -l src/Models/Permission.php src/Models/JobPriorities.php src/Controllers/JobPrioritiesController.php src/Controllers/BootstrapController.php src/Controllers/SyncOperationsController.php src/Services/SyncEventGenerator.php update_install.php scripts/migrations/job-priorities-offline-first-phase30.php` en `sisa.api` -> PASS
- `npm run lint` en `sisa.ui` -> PASS

## Avance parcial - limpieza final de jobs legacy

Estado: en progreso

## Avance parcial - items de factura estructurados por entidad

Estado: completado

Que cambio:

- `sisa.api/src/Services/InvoiceLineNormalizer.php` centraliza la normalizacion y validacion de lineas facturables para `jobs`, `products_services`, `payments` y `manual`, evitando volver a parsear IDs desde la descripcion
- `sisa.api/src/Controllers/InvoicesController.php`, `sisa.api/src/Controllers/InvoiceItemsController.php`, `sisa.api/src/Services/InvoicesService.php` y `sisa.api/src/Controllers/SyncOperationsController.php` ahora persisten `code` + `entity_type`, limpian descripciones embebidas y validan company/client al facturar jobs, productos/servicios y pagos cobrables
- `sisa.api/src/Controllers/JobReportsController.php` incorpora los pagos cobrables al cliente con columnas estructuradas (`entity_type`, `code`, `description`, `amount`) en resumen de cuenta e informes PDF visibles al cliente
- `sisa.api/src/Models/InvoiceItems.php`, `sisa.api/src/History/InvoiceItemsHistory.php`, `sisa.api/install.php`, `sisa.api/update_install.php` y `sisa.api/scripts/migrations/invoice-items-entity-type-phase29.php` agregan/backfillean `code` y `entity_type` de forma segura para produccion, normalizando filas legacy ligadas a jobs y productos/servicios
- `sisa.ui/app/invoices/create.tsx`, `sisa.ui/app/invoices/[id].tsx`, `sisa.ui/contexts/InvoicesContext.tsx` y `sisa.ui/utils/invoiceItems.ts` dejan de embutir `#job_id` dentro de la descripcion y envian la referencia estructurada del item al backend
- seguimiento UI: `sisa.ui/app/invoices/create.tsx` ahora permite agregar pagos marcados como cobrables del mismo cliente/empresa directamente al armado de la factura, y `sisa.ui/app/invoices/[id].tsx` expone `entity_type`, `code` y `job_id` en edicion avanzada para auditoria/correccion manual
- ajuste UX/estabilidad: `sisa.ui/app/invoices/create.tsx` normaliza importes de `payments` aunque vengan como string, autoagrega todos los pagos cobrables del cliente/empresa al abrir una factura y los mantiene al final de la lista de items
- ciclo de disponibilidad: `sisa.ui/app/invoices/create.tsx` ahora excluye pagos ya facturados en facturas activas usando `InvoicesContext`, mientras `sisa.api/src/Services/InvoiceLineNormalizer.php` rechaza en backend reusar un `payment` ya facturado; al borrar la factura, el pago vuelve a quedar disponible
- integridad de borrado: `sisa.api/src/Controllers/PaymentsController.php` bloquea borrar un `payment` que ya esta referenciado por una factura activa, y `sisa.ui/contexts/PaymentsContext.tsx` + `sisa.ui/app/payments/[id].tsx` muestran el motivo real en pantalla
- visibilidad operativa: `sisa.ui/app/payments/index.tsx` y `sisa.ui/app/payments/[id].tsx` muestran una marca visual cuando un pago ya esta facturado en una factura activa
- endurecimiento UX: `sisa.ui/contexts/PaymentsContext.tsx` ahora propaga mejor los errores de delete, y `sisa.ui/app/payments/index.tsx` + `sisa.ui/app/payments/[id].tsx` frenan el intento de borrado desde UI con un mensaje explicito cuando el pago ya esta facturado
- transparencia en facturacion: `sisa.ui/app/invoices/create.tsx` sigue ocultando pagos ya facturados de la seleccion activa, pero ahora los lista aparte en un bloque `Ya facturados` para que el usuario vea por que no estan disponibles
- visibilidad sin alertas: `sisa.ui/app/payments/index.tsx` y `sisa.ui/app/payments/[id].tsx` ahora muestran el bloqueo de borrado tambien en el propio boton/accion visible (`Facturado` / `Facturado - ver motivo`) para no depender del globo de eventos
- robustez backend: `sisa.api/src/Services/AccountingFlowService.php` ya no rompe la eliminacion de pagos si falta baseline contable o si limpiar asientos legacy falla; la eliminacion del payment sigue y la limpieza contable se degrada en silencio
- cascada obligatoria: `sisa.api/src/Controllers/InvoicesController.php` y `sisa.api/src/Models/InvoiceItems.php` fuerzan el borrado logico por factura completa para que ningun `invoice_item` quede vigente despues de eliminar la factura, incluso si algun item no entro en la hidratacion normal del documento
- observabilidad de borrado: `sisa.api/src/Controllers/PaymentsController.php` ahora captura excepciones del delete y devuelve JSON con el mensaje real en vez de caer en `Slim Application Error`, para poder identificar baseline roto o schema faltante en servidor
- compatibilidad de historial: `sisa.api/src/History/PaymentsHistory.php` normaliza `attached_files` a JSON valido o `null` antes de grabar el historial, evitando fallas por constraints JSON legacy al eliminar pagos sin adjuntos bien serializados
- ajuste posterior 1: `sisa.api/src/Controllers/JobReportsController.php` deja generar el PDF comercial aunque el filtro no devuelva trabajos pero si pagos cobrables del cliente; el resumen horizontal ahora incluye una tabla propia de esos pagos y un total comercial combinado servicios + cargos
- ajuste posterior 2: `sisa.api/src/Controllers/InvoicesController.php` enriquece los items `payments` del PDF de factura con etiqueta legible (`Pago cliente`) y detalle de asignacion/fecha/acreedor para que el cargo trasladable quede explicitado tambien en el comprobante
- ajuste posterior 3: el `404 No printable data found` persistia en algunos clientes porque `sisa.api/src/Controllers/JobReportsController.php` estaba tomando `clients.empresa_id` (empresa del propio cliente) como scope/encabezado del reporte en vez de `clients.company_id` (empresa operadora dueña del vinculo). Ahora prioriza `company_id` y deja `empresa_id` solo como fallback legacy, por lo que vuelve a encontrar pagos/recibos/facturas cobrables del cliente en el scope correcto
- `qa/INVOICE_CHARGEABLE_PAYMENTS_RUNBOOK.md` deja un runbook manual corto para validar inclusion de pagos cobrables en factura, PDF, resumen de cuenta y rechazos por cliente/empresa cruzados
- `qa/INVOICE_CHARGEABLE_PAYMENTS_RUNBOOK.md` ahora cubre tambien desaparicion del pago una vez facturado y reaparicion tras eliminar la factura
- `qa/INVOICE_CHARGEABLE_PAYMENTS_RUNBOOK.md` tambien cubre el rechazo explicito al intentar borrar un pago ya facturado

Riesgo cubierto:

- que la factura, el PDF o el resumen del cliente reconstruyan referencias parseando texto libre y terminen mezclando IDs, entidades o clientes equivocados
- que un pago cobrable quede visible solo en contabilidad interna y no llegue a factura/resumen/PDF comercial

Puntos ciegos conocidos:

- no se agrego en esta sesion una UI nueva dedicada para seleccionar pagos cobrables al crear factura; la inclusion queda soportada y validada desde el backend/API y la estructura de items

Validacion parcial:

- `vendor/bin/phpunit tests/Services/InvoiceLineNormalizerTest.php tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php tests/Regression/AccountingSummaryAndInvoicesRegressionTest.php` en `sisa.api` -> PASS (26 tests, 114 assertions)
- `php -l` sobre controladores/modelos/servicios/migracion tocados en `sisa.api` -> PASS
- `npm run lint` en `sisa.ui` -> PASS
- rerun `npm run lint` en `sisa.ui` luego del selector de pagos cobrables en factura -> PASS
- rerun `vendor/bin/phpunit tests/Services/InvoiceLineNormalizerTest.php tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php tests/Regression/AccountingSummaryAndInvoicesRegressionTest.php` tras endurecer rechazos por payment/company/client -> PASS
- rerun `npm run lint` en `sisa.ui` tras corregir crash `value.toFixed is not a function` y autoagregado de pagos cobrables -> PASS
- rerun `vendor/bin/phpunit tests/Services/InvoiceLineNormalizerTest.php tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php tests/Regression/AccountingSummaryAndInvoicesRegressionTest.php` tras bloquear pagos ya facturados -> PASS
- `vendor/bin/phpunit tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php tests/Controllers/InvoicesControllerPdfRegressionTest.php` en `sisa.api` -> PASS
- `vendor/bin/phpunit tests/Services/InvoiceLineNormalizerTest.php tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php tests/Regression/AccountingSummaryAndInvoicesRegressionTest.php tests/Controllers/InvoicesControllerPdfRegressionTest.php` en `sisa.api` -> PASS con warnings conocidos del fallback SQLite sobre `invoice_receipt_payments` ausente en pruebas legacy
- rerun `vendor/bin/phpunit tests/Controllers/JobsControllerClientJobsPdfFiltersTest.php tests/Controllers/InvoicesControllerPdfRegressionTest.php` tras corregir el scope `company_id`/`empresa_id` del reporte cliente -> PASS con warnings conocidos no bloqueantes

## Avance parcial - categorias contables por empresa y visibilidad individual

Estado: en progreso

## Avance parcial - estabilidad de refresh en jobs/worklogs

Estado: en progreso

## Avance parcial - startup protegido y operacion estable

Estado: en progreso

Que cambio:

- `sisa.ui/contexts/OperationGuardContext.tsx` ahora registra metadata de operaciones y tareas diferidas (`form`, `form-load`, `form-save`, `external`), permitiendo distinguir edicion activa, cargas internas permitidas y refreshes globales retenidos sin perder el comportamiento de cola existente
- `sisa.ui/components/OperationGuardModal.tsx` agrega un modal global bloqueante montado desde `sisa.ui/app/_layout.tsx` que aparece automaticamente al guardar o cargar datos criticos del formulario, y tambien cuando hay actualizaciones externas retenidas para no romper la UI del flujo actual
- el modal ahora detalla en lista exactamente que esta corriendo o en cola (`Ahora` vs `En cola`) y suma una barra/progreso visual; cuando existe un progreso real como upload de adjuntos se refleja con porcentaje, y en el resto de los casos cae a un avance por etapas activas vs pendientes
- `sisa.ui/components/OperationGuardStatusIndicator.tsx` queda como señalizacion secundaria: mantiene el banner para tareas en espera no bloqueantes y se oculta cuando el modal ya esta tomando el foco visual
- `sisa.ui/app/jobs/worklog-form.tsx` pasa a declarar explicitamente el contexto del formulario, el guardado y las cargas soporte permitidas (device id, usuarios y datasets auxiliares de edicion) para que el bloqueo sea preciso: se congela lo externo, pero no los datos que el propio formulario necesita para operar
- `sisa.ui/app/jobs/[id].tsx` adopta el mismo esquema para detalle de job: distingue contexto del formulario, carga soporte, guardado, conflictos y subida de adjuntos; esta ultima ya expone progreso real dentro del modal bloqueante
- ajuste de UX posterior: el modal bloqueante ya no aparece solo por entrar a `job detail` o `worklog form`; ahora exige carga critica activa, guardado/conflicto/upload en curso o draft realmente modificado, evitando que refreshes externos en cola tapen la pantalla apenas se abre el formulario
- correccion de causa raiz posterior: el estado `form-load` estaba tomando tambien la carga inicial de entrada y por eso parecia que la pantalla quedaba trabada; ahora esa carga inicial vuelve a resolverse con la UI propia de cada pantalla y el guard global solo interviene si ya existe un draft modificado que podria ser pisado
- correccion UX posterior: las tareas externas diferidas (autosync/bootstrap/auth/permisos) ya no disparan modal bloqueante durante la edicion; quedan visibles en la banda superior como aviso no intrusivo, mientras el overlay queda reservado solo para guardados, conflictos, uploads o cargas realmente bloqueantes del formulario
- correccion adicional en worklogs: abrir los pickers nativos de fecha/hora ya no marca por si solo una operacion bloqueante en `sisa.ui/app/jobs/worklogs.tsx`; esto evita que al cerrar el picker se liberen rafagas de tareas diferidas en medio de la seleccion y se pise el draft visible del modal
- correccion de resiliencia en worklog modal: `sisa.ui/app/jobs/worklogs.tsx` ahora mantiene un draft local en memoria por `worklog/new+job`, rehidrata desde ese draft si el componente se vuelve a montar mientras el picker nativo esta abierto, y limpia el cache al cerrar/guardar; con esto fecha, hora y descripcion dejan de volver al snapshot viejo si Android recompone el modal durante la seleccion
- `sisa.ui/contexts/AuthContext.tsx`, `sisa.ui/contexts/BootstrapContext.tsx`, `sisa.ui/contexts/PermissionsContext.tsx`, `sisa.ui/contexts/TrackingContext.tsx` y `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` ahora etiquetan los `runWhenIdle` con labels legibles, de modo que la nueva señalizacion pueda explicar que carga quedo en espera en lugar de mostrar solo keys tecnicas
- `sisa.ui/scripts/startup-stability-smoke.js` se endurecio para exigir el nuevo modal de guardia y tolerar las variantes con metadata en `runWhenIdle`, manteniendo automatizada la proteccion del arranque/operacion estable
- `sisa.ui/contexts/OperationGuardContext.tsx`, `sisa.ui/hooks/useOperationBlock.ts` y `sisa.ui/app/_layout.tsx` agregan una guarda global de operacion que detecta formularios/ediciones activas y difiere tareas de refresh hasta que el usuario salga del flujo critico
- `sisa.ui/components/OperationGuardStatusIndicator.tsx` muestra una banda superior con spinner cuando auth/permisos/bootstrap/sync quedaron en espera por una operacion activa, para que el usuario vea que la app esta reteniendo actualizaciones y no quedo congelada
- segunda pasada sobre los logs reales de Android: `sisa.ui/app/jobs/worklogs.tsx` ahora marca tambien el modal legacy de worklogs como operacion activa, bloquea refresh destructivo aun antes de guardar, y deja de pedir `clientJobs` cuando el modal no esta abierto
- `sisa.ui/contexts/AppUpdatesContext.tsx` y `sisa.ui/contexts/TrackingContext.tsx` ahora difieren el check inicial de updates y los refresh/sync de tracking cuando una operacion esta activa, atacando el ruido observado en los logs mientras el modal de worklog seguia abierto
- `sisa.ui/config/Index.ts` se reafirma con todos los flags de debug runtime en `false`; si la app sigue mostrando logs viejos hace falta recargar bundle/rebuild para tomar la configuracion nueva
- tercera pasada sobre estabilizacion de runtime: `sisa.ui/contexts/AuthContext.tsx` agrega dedupe + throttle general para `checkConnection`, reduciendo relogs y refresh token repetidos provocados por varios 401/403 o revalidaciones solapadas
- `sisa.ui/contexts/BootstrapContext.tsx` ahora agrupa invalidaciones de `startup-bootstrap` por referencias con debounce y flush batch; en vez de invalidar por cada `status/tariff/client/folder/provider/category/payment_template`, junta eventos cercanos y difiere el refresh si el usuario esta en una operacion activa
- cuarta pasada sobre ruido de push/runtime: `sisa.ui/src/device/deviceRegistration.ts` agrega cache TTL + dedupe en vuelo para `registerCurrentDevice`, evitando re-registrar el dispositivo en cada refresh de token o reintento cercano con el mismo payload
- `sisa.ui/app/_layout.tsx` estabiliza `ExpoPushTokenLogger` usando refs para `token`, `handleSyncHint` y logging de push, evitando que el efecto de runtime de notificaciones se reinstale por cambios de callback durante la sesion
- quinta pasada orientada a bajar renders fuera de foco: `sisa.ui/app/_layout.tsx` vuelve `AppointmentsProvider`, `TrackingProvider` y `AppUpdatesProvider` route-aware, habilitandolos solo en superficies que realmente los consumen (`/Home`, `/appointments`, `/clients/calendar`, `/jobs/worklog*`, `/tracking*`)
- `sisa.ui/app/jobs/index.tsx` ahora evita cargar soporte/bootstraps cuando la ruta `/jobs` no esta activa y desactiva `useSyncStatus` fuera de foco; ademas `JobParticipantsPreview` usa `useWorkLogs` solo cuando la tarjeta esta realmente activa
- `sisa.ui/src/modules/jobs/presentation/hooks/useJobsList.ts` incorpora equality guard para no reemplazar listas identicas, recortando renders tontos despues de reloads/syncs sin cambios visibles
- `sisa.ui/src/modules/jobs/presentation/hooks/useSyncStatus.ts` acepta `enabled` para no mantenerse vivo en pantallas no activas
- sexta pasada focalizada en worklogs: `sisa.ui/app/jobs/worklogs.tsx` elimina una suscripcion inutil a `AppointmentsContext`, memoiza `availableJobs` y `defaultTariffId` del modal, estabiliza `onAttachmentsChanged`, y reduce churn de props hacia `WorkLogFormModal`/`WorkLogCard`
- esto apunta a bajar los rerenders que seguias viendo con el modal de worklog abierto aun sin cambios de draft, especialmente cuando el parent se repintaba por contexto o por refreshes externos ya mitigados
- septima pasada sobre repaints residuales: `sisa.ui/src/modules/jobs/presentation/components/AttachmentList.tsx` pasa a `React.memo` y deja de mantener una copia local derivada de `attachments`, eliminando un render extra por cada cambio de lista
- `sisa.ui/contexts/JobsContext.tsx` ahora memoiza su `value` y estabiliza `addJob`/`updateJob`/`deleteJob` con `useCallback`, reduciendo rerenders en todas las pantallas consumidoras cada vez que el provider padre se movia sin cambios reales en jobs
- octava pasada sobre contextos transversales: `sisa.ui/contexts/ClientsContext.tsx` agrega `publishClients` + equality guard para no republicar listas identicas tras hydrate/reload/cache refresh, y `sisa.ui/contexts/StatusesContext.tsx` hace lo mismo con `publishStatuses`
- `sisa.ui/contexts/StatusesContext.tsx` tambien estabiliza `reorderStatuses` con `useCallback`, recortando cambios de referencia innecesarios en consumidores que solo necesitan leer estados
- novena pasada sobre hooks calientes de detalle: `sisa.ui/src/modules/jobs/presentation/hooks/useWorkLogs.ts` y `sisa.ui/src/modules/jobs/presentation/hooks/useJobDetail.ts` ahora comparan el payload nuevo contra el actual antes de hacer `setState`, evitando publicaciones redundantes despues de refreshes/syncs sin cambios reales
- en `useWorkLogs` tambien se evita deduplicar dos veces el mismo dataset por recarga, bajando trabajo inutil en un hook que aparecia constantemente en los logs de `worklogs` y `job detail`
- decima pasada sobre el resto del detalle de trabajo: `useJobItems`, `useJobGroups`, `useRootCauses`, `useJobStatusHistory` y `useJobAppointments` ahora agregan equality guards antes de publicar estado nuevo, evitando repaints laterales de tabs/secciones cuando un refresh no cambia realmente los datos
- con esto el stack principal del detalle (`job`, `items`, `groups`, `root causes`, `history`, `appointments`, `worklogs`) queda cubierto contra republicaciones triviales tras sync, reload o reentrada de foco
- onceava pasada guiada por logs reales con debug encendido: `sisa.ui/config/Index.ts` deja nuevamente todos los flags debug en `true` por pedido del usuario, `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` evita encolar cientos de auto-syncs duplicados mientras hay una operacion activa, y `sisa.ui/app/_layout.tsx` deja de habilitar `AppointmentsProvider` en `/jobs/worklogs` porque esa pantalla ya no consume ese contexto
- esto reduce el spam observado de `JobsSyncAutoRunner.tryAutoSync:skip { reason: "operation-active" }` y saca una fuente transversal de renders en worklogs sin perder visibilidad de logs para diagnostico
- doceava pasada sobre worklogs con debug activo: `sisa.ui/app/jobs/worklogs.tsx` memoiza `WorkLogCard` y evita hidratar `useJobsList` para el modal salvo cuando realmente se esta editando un worklog existente; en alta nueva ya no carga la lista de trabajos alternativos que el modal ni usa
- esto ataca el patron visto en logs donde `useJobsList` del modal seguia pasando de `0 -> 6` y empujaba renders de `WorkLogFormModal`/`WorkLogCard` aun sin cambios de draft
- doceava y media sobre ruido residual: `sisa.ui/src/modules/jobs/presentation/hooks/useCompanyUsers.ts` deja de republicar usuarios identicos, `sisa.ui/app/jobs/index.tsx` y `sisa.ui/app/jobs/worklogs.tsx` exportan pantallas memoizadas, y eso ayuda a que re-renders del arbol root no se propaguen tan facil a pantallas ya montadas en stack pero fuera de foco
- esto no elimina por completo los renders fuera de foco, pero recorta una parte del costo estructural y prepara el terreno para seguir aislando `JobsScreen`/`JobWorkLogsScreen` de cambios globales
- treceava pasada sobre el modal mismo: `sisa.ui/components/SearchableSelect.tsx` y `sisa.ui/src/modules/jobs/presentation/components/ParticipantAvatarStrip.tsx` quedan memoizados, y `sisa.ui/src/modules/jobs/presentation/hooks/useCompanyUsers.ts` evita recargar/republicar usuarios de la misma empresa una y otra vez al abrir worklogs
- esto apunta directo al patron de `WorkLogFormModal render` repetido aun sin cambios de draft, reduciendo churn interno por listas de participantes, selects y soporte de usuarios
- catorceava pasada, mas agresiva, para cortar ruido real observado en logs: `sisa.ui/app/jobs/index.tsx` y `sisa.ui/app/jobs/worklogs.tsx` pasan a un wrapper liviano por ruta y montan la pantalla pesada solo cuando el pathname activo coincide (`/jobs`, `/jobs/worklogs`)
- esto evita que `JobsScreen` y `JobWorkLogsScreen` mantengan todos sus hooks vivos cuando quedan en stack fuera de foco, que era justamente el patron dominante en los logs (`JobsScreen render` y `useJobsList` fuera de ruta)
- quinceava pasada guiada por los ultimos logs: `sisa.ui/app/Home.tsx` y `sisa.ui/app/appointments/index.tsx` dejan su `useJobsList()` global route-aware, para no mantener el dataset completo (`companyId: null`, 181 rows) vivo mientras el usuario esta en jobs/worklogs
- esto ataca exactamente el patron que seguia apareciendo en tus trazas: un `useJobsList` sin filtros, con `companyId: null`, que seguia recargando en background y contaminando los renders del detalle aunque la experiencia ya estaba mejor
- dieciseisava pasada: extendi el mismo criterio a `sisa.ui/app/appointments/create.tsx`, `sisa.ui/app/appointments/[id].tsx`, `sisa.ui/app/appointments/viewModal.tsx`, `sisa.ui/app/jobs/groups.tsx` y `sisa.ui/app/jobs/root-causes.tsx`, para que cualquier `useJobsList` auxiliar quede apagado fuera de su ruta activa
- con esto se corta otra familia de montajes invisibles que podian seguir metiendo `useJobsList` con `companyId: null` o listas por cliente mientras el usuario ya estaba operando dentro de jobs/worklogs
- diecisieteava pasada, orientada al bug visible de hora reseteandose: `sisa.ui/app/jobs/worklogs.tsx` deja de limpiar `open_create`/`edit_uuid` apenas abre el modal y tambien deja de usar `prefill_started_at` en la `key` del modal; asi evitamos remounts del `WorkLogFormModal` en medio de la apertura que reseteaban la fecha/hora al valor actual
- esto coincide con tu observacion de campo: antes de estabilizarse, el modal se remountaba mientras la ruta limpiaba params; ahora el cleanup queda para `onClose`/`onSave`, no para el instante de apertura
- dieciochoava pasada a partir de la nueva traza: `useJobsList` ahora fuerza `loading=false` cuando queda `enabled=false`, evitando hooks auxiliares eternamente en estado de carga, y `sisa.ui/app/jobs/worklogs.tsx` no consume `open_create` hasta que `isWorkLogReady` sea verdadero; eso corrige el caso donde tocar `+` desde el job abria la pantalla de worklogs pero no el modal
- tambien agregue logs de diagnostico `JobWorkLogsScreen.openCreate:waiting-ready` y `JobWorkLogsScreen.openCreate:open-modal` para verificar exactamente si el flujo directo del `+` queda esperando por `deviceId/userId` o si abre en el momento correcto
- diecinueveava pasada sobre el salto de fecha/hora en worklogs: `sisa.ui/app/jobs/worklogs.tsx` y `sisa.ui/app/jobs/worklog-form.tsx` ahora abren en Android el picker nativo via `DateTimePickerAndroid.open()` encapsulado en `sisa.ui/src/modules/jobs/presentation/utils/openNativeDateTimePicker.ts`, sacando el dialogo del ciclo declarativo que seguia recibiendo rerenders durante bootstrap/cargas soporte
- en la pantalla dedicada tambien se apagan `useWorkLogs` y `useJobsList` cuando el flujo esta en alta nueva; esas cargas auxiliares quedan reservadas a edicion y dejan de meter churn en los primeros segundos del formulario
- `sisa.ui/scripts/startup-stability-smoke.js` ahora protege explicitamente que worklogs/worklog-form usen el helper imperativo en Android y que no vuelva a aparecer `prefill_started_at` dentro de la `key` del modal
- se agrega `qa/WORKLOG_ANDROID_DATETIME_REGRESSION_RUNBOOK.md` como runbook manual especifico para reproduccion y validacion de fecha/hora en Android, y `qa/REGRESSION_CHECKLIST.md` lo incorpora como escenario fijo de regresion
- refactor estructural posterior: la UI comun del formulario de worklog deja de vivir duplicada en `sisa.ui/app/jobs/worklogs.tsx` y `sisa.ui/app/jobs/worklog-form.tsx`; ahora ambos flujos comparten `sisa.ui/src/modules/jobs/presentation/components/WorkLogFormFields.tsx` y utilidades comunes en `sisa.ui/src/modules/jobs/presentation/worklogForm/shared.ts`
- esto baja el riesgo de que futuros cambios en pickers Android, duracion, participantes o save buttons se apliquen a un flujo y queden ausentes en el otro
- `sisa.ui/contexts/AuthContext.tsx`, `sisa.ui/contexts/PermissionsContext.tsx`, `sisa.ui/contexts/BootstrapContext.tsx`, `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx` y `sisa.ui/contexts/TrackingContext.tsx` ahora dejan en cola los refresh de foreground, bootstrap post-hint, autosync de jobs y autosync de tracking cuando hay una operacion activa, evitando que esos rebotes globales pisen pantallas vivas
- `sisa.ui/app/jobs/[id].tsx`, `sisa.ui/app/jobs/worklog-form.tsx`, `sisa.ui/app/invoices/create.tsx`, `sisa.ui/app/invoices/[id].tsx` y `sisa.ui/app/receipts/create.tsx` marcan edicion activa mientras hay draft/saving, de modo que los refresh globales se postergan hasta terminar la operacion
- `sisa.ui/contexts/ProfilesListContext.tsx` deja de auto-fetchear `/profiles` en el startup global; `sisa.ui/app/tracking/daily-route.tsx` lo pide on-demand cuando esa pantalla realmente se usa, recortando IO del arranque
- `sisa.ui/contexts/ConfigContext.tsx` y `sisa.ui/contexts/MemberCompaniesContext.tsx` ahora deduplican requests en vuelo para bajar requests paralelos redundantes durante bootstrap
- `sisa.ui/config/Index.ts` vuelve a dejar apagados los flags de debug runtime de push/device/jobs/cache para reducir ruido y costo del arranque normal
- `sisa.ui/scripts/startup-stability-smoke.js`, `sisa.ui/package.json`, `qa/run-baseline.ps1` y `qa/REGRESSION_CHECKLIST.md` agregan una smoke automatizada + checklist manual especificos para startup protegido y no perdida de drafts
- `qa/STARTUP_OPERATION_STABILITY_RUNBOOK.md` ahora explicita validar tambien el indicador visual de `actualizaciones en espera` durante background/foreground, reconexion y `sync_hint`

Riesgo cubierto:

- que el usuario pierda cambios locales por refresh de auth/permisos/bootstrap/sync mientras ya esta editando en campo
- que el startup siga inyectando IO global no esencial y provoque rerenders perceptibles despues de mostrar la shell

Puntos ciegos conocidos:

- la validacion automatica nueva es estructural (smoke por codigo) y no reemplaza la prueba manual en dispositivo real con background/foreground, reconexion y `sync_hint`
- el baseline compartido quedo bloqueado por una deuda preexistente de backend en `sisa.api/tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php:1033`, fuera de los archivos tocados en esta sesion

Validacion parcial:

- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:startup-stability` en `sisa.ui` -> PASS
- `npm run check:sync-smoke` en `sisa.ui` -> PASS
- `npm run lint` en `sisa.ui` -> PASS
- `npm run check:cache` en `sisa.ui` -> PASS
- `npm run check:sync-smoke` en `sisa.ui` -> PASS
- `npm run check:startup-stability` en `sisa.ui` -> PASS
- rerun `npm run check:startup-stability` luego del indicador visual -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras endurecer modal legacy + tracking/app updates -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras throttle auth + batch invalidation de bootstrap -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` tras dedupe de `registerCurrentDevice` + estabilizacion de `ExpoPushTokenLogger` -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras route-gating de providers + guards en jobs list -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras memoizacion de props del modal worklogs -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras memoizar `AttachmentList` + `JobsContext` -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras guards de publicacion en `ClientsContext` y `StatusesContext` -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras guards en `useWorkLogs` y `useJobDetail` -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras guards en hooks vecinos del detalle de trabajo -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras reactivar debug y cortar cola duplicada de autosync -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras memoizar `WorkLogCard` y cortar `useJobsList` en alta nueva -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras memoizar pantallas y guardar `companyUsers` contra publishes redundantes -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras memoizar `SearchableSelect`/`ParticipantAvatarStrip` y estabilizar `useCompanyUsers` -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras montar `JobsScreen`/`JobWorkLogsScreen` solo en ruta activa -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras volver route-aware el `useJobsList` de `Home` y `appointments` -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras extender route-gating a pantallas auxiliares de appointments/groups/root-causes -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras evitar remount del modal de worklogs por limpieza temprana de params -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras corregir `open_create` diferido y `loading=false` en `useJobsList` deshabilitado -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras mover worklog pickers Android a API imperativa y apagar cargas de create-mode -> PASS
- nueva pasada `npm run lint` + `npm run check:startup-stability` + `npm run check:sync-smoke` tras unificar la UI comun del formulario de worklog -> PASS
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

## SISA WEB - ajuste visual timeline worklogs

Estado: completado con validacion focalizada.

- se ajusto `/worklogs-timeline` para mostrar horas trabajadas en formato `HH:MM` en el resumen y por participante.
- los indicadores por participante ahora usan iconos compactos con tooltip para lapsos GPS, worklogs y horas trabajadas.
- las etiquetas de los rails GPS/worklogs se compactaron como iconos con tooltip para evitar solapamientos con los bloques del timeline.
- se ajusto el espaciado vertical de bloques GPS/worklogs para mantener separacion visual.
- validacion: `npm run lint` en `sisa.web` -> PASS.
- validacion: `npm run build` en `sisa.web` -> PASS; mantiene warning existente de chunks grandes de Vite.
- se restauraron artefactos generados de `dist/` luego del build.
