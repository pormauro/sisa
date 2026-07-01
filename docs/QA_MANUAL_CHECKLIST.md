# QA Manual Checklist

## Alcance

Checklist para ejecutar QA real por perfiles en ambiente test/staging. No usar datos productivos reales. No registrar passwords, tokens, cookies ni secretos en evidencias.

## Preparacion

- Confirmar ambiente: `sistema-test`, staging o base test equivalente.
- Confirmar que `QA_ALLOW_SEED=1` solo se usa contra DB test/staging.
- Ejecutar seed en dry-run primero desde `sisa.api`: `QA_ALLOW_SEED=1 php scripts/qa/seed-qa-users.php --dry-run`.
- Ejecutar seed real solo con autorizacion: `QA_ALLOW_SEED=1 QA_PASSWORD=<valor-no-commiteado> php scripts/qa/seed-qa-users.php --apply`.
- Guardar passwords temporales en canal seguro externo al repo.
- Si se necesita limpiar: `QA_ALLOW_SEED=1 php scripts/qa/seed-qa-users.php --cleanup --apply`.

## Perfiles

| Perfil | Usuario | Empresa | Debe ver | No debe ver |
|---|---|---|---|---|
| Superadmin delegado QA | `qa_superadmin` | QA A | Todos los modulos operativos y purge por permiso | No reemplaza prueba hardcoded `user_id=1` |
| Owner/admin | `qa_owner_admin` | QA A | Bypass de rol owner dentro de la empresa | No usar para validar restricciones finas |
| Company admin | `qa_company_admin` | QA A | Bypass de rol admin dentro de la empresa | No usar para validar restricciones finas |
| Tecnico | `qa_tecnico` | QA A | Clientes lectura, jobs, worklogs, adjuntos tecnicos | Pagos, recibos, mutacion contable, settings contables |
| Admin caja | `qa_admin_caja` | QA A | Caja, pagos, recibos, facturas, resumen cliente, analytics | Mutacion tecnica de jobs/worklogs |
| Sin permisos sensibles | `qa_sin_permisos` | QA A | Perfil/settings basicos | Modulos sensibles y acciones CRUD |
| Multiempresa | `qa_multiempresa` | QA A y QA B | A: caja/contabilidad; B: lectura limitada | B: mutaciones de caja/contabilidad y permisos heredados de A |

`owner` y `admin` tienen bypass efectivo de permisos por backend dentro de su empresa. Usar perfiles `member` con permisos explicitos (`qa_tecnico`, `qa_admin_caja`, `qa_sin_permisos`, `qa_multiempresa`) para validar restricciones finas.

## Web

- Login con cada perfil QA.
- Confirmar empresa activa visible.
- Cambiar empresa si el perfil tiene mas de una membresia.
- Dashboard: confirmar que cards y requests corresponden a permisos del perfil.
- Clientes: abrir cliente QA, validar acciones visibles/ocultas por permiso.
- Carpetas: validar acceso desde cliente/trabajo cuando aplique.
- Trabajos: crear/editar solo con permiso; tecnico debe poder operar worklogs si corresponde.
- Preparar factura desde trabajo: visible solo con `addInvoice`.
- Factura PDF: visible/descargable solo con permisos de factura/PDF.
- Recibo/cobro: crear/editar solo con `addReceipt`/`updateReceipt`.
- Pagos: crear/editar/eliminar solo con `addPayment`/`updatePayment`/`deletePayment`.
- Resumen cliente: visible con `viewClientStatement`, `viewClientAccounting` o `viewAccountingSummary`.
- Adjuntos: confirmar que no carga fuentes no autorizadas en Network tab.
- Analytics: usuario lectura carga datos; usuario update puede guardar cierre; usuario sin lectura ve mensaje/EmptyState.
- Settings contables: lectura solo con permisos compatibles; guardar solo con `updateCompanyAccountingSettings`.
- Providers, Quotes, Catalogs: confirmar botones crear/editar/eliminar ocultos sin permisos de accion.

## Mobile

- Login con cada perfil QA.
- Confirmar empresa activa y cambio de empresa si aplica.
- Probar deep links bloqueados/autorizados:
  - `/jobs/create`
  - `/jobs/worklog-form`
  - `/clients/accounting`
  - `/journal_entries`
  - `/network/logs`
  - `/tracking/nearby-clients`
- Confirmar que cards sin permiso no renderizan ni navegan.
- Trabajo detalle: tecnico ve acciones de campo permitidas.
- Worklogs: crear/editar solo con permisos correspondientes.
- Adjuntos: subir/descargar solo con `uploadFile`/`downloadFile`.
- Recibos: editar solo con `updateReceipt`.
- Pagos: editar solo con `updatePayment`.
- Tracking/nearby:
  - sin `addJob` no propone crear job.
  - sin `addPayment` no carga ni renderiza proveedores.
  - sin `getJob`/`listJobs` no propone abrir job.

## Multiempresa

- Iniciar sesion como `qa_multiempresa`.
- Empresa A: confirmar permisos de caja/contabilidad.
- Empresa B: confirmar lectura limitada.
- Intentar operar en B con permisos que solo existen en A: debe fallar/ocultarse.
- Cambiar A/B varias veces y confirmar que menus, permisos y cache se actualizan.
- En Network tab/logs, confirmar que `company_id` o `X-Company-Id` siempre corresponde a empresa activa.
- Confirmar que no quedan datos de A visibles al cambiar a B.

## API Manual

- Legacy join requests sin `company_id`: deben fallar cerrado con scope faltante o 403.
- Aliases `/companies/{company_id}/join-requests/{request_id}/approve|reject`: deben funcionar para admin de esa empresa.
- `/sync/v2/purge` y `/sync/v3/purge`: usuario comun debe fallar; perfil con `purgeSyncOperations` o superadmin autorizado debe pasar.
- `GET /company-accounting-settings`: debe pasar con lectura o update.
- `PUT /company-accounting-settings`: solo debe pasar con `updateCompanyAccountingSettings`.
- `/permissions/user/{user_id}`: permisos propios solo con membresia `approved`; otro usuario requiere `listPermissions`.

## Evidencia Permitida

- Capturas sin tokens/cookies visibles.
- HTTP status, ruta y usuario QA usado.
- IDs de empresas/datos QA.
- Errores funcionales reproducibles.

## Evidencia Prohibida

- Passwords.
- Tokens JWT, cookies, refresh tokens o `Authorization` headers.
- Secrets de `.env`.
- Datos productivos reales.
