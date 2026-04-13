# Mapa de Dataset de Campo

## Proposito

Este mapa define los datos minimos y los comportamientos que QA debe proteger para que la app siga siendo util con conectividad inestable y pueda converger de forma segura despues.

## Tier A - nucleo operable en campo

| Dominio | Entidades | Por que importa en sitio | Riesgos principales | Control actual |
|---|---|---|---|---|
| acceso y scope | membresias, empresa seleccionada, permisos, usuarios | sin esto el operador no puede ver el workspace correcto ni asignar trabajo | scope de empresa incorrecto, permisos viejos, participantes faltantes | cobertura parcial en backend, checks manuales, deuda en guardia de cache |
| estructura del cliente | clients, folders | permite ubicar a quien pertenece el trabajo y donde; `clients` representa empresas del ecosistema referenciadas desde `empresas` y condiciona el scope operativo real | folders huerfanos, scope cliente/empresa incorrecto, drift en deletes, referencias inconsistentes a empresas del ecosistema | existen docs de sync, pocos tests focalizados |
| estado de ejecucion | statuses | es necesario para mover el flujo operativo con seguridad | catalogo de estados incorrecto, cache de referencias viejo, drift de deletes | existe logica en backend para statuses, poca QA directa |
| trabajo operativo | jobs, job_items, work_logs, appointments | son el nucleo del trabajo de campo, tiempos, participantes y agenda | relaciones padre invalidas, violaciones del arbol de folders, drift de conflicto/version | docs parciales, tests de appointments, poca cobertura directa en el resto |
| evidencia | files, file_attachments | prueba de trabajo y auditabilidad posterior | drift de upload state, errores de detach, reaparicion de attachments borrados | cobertura parcial en modelo/controlador de attachments |

## Tier B - referencias de soporte en campo

| Dominio | Entidades | Por que importa en sitio | Riesgos principales | Control actual |
|---|---|---|---|---|
| catalogos comerciales/de soporte | providers, categories, products_services, tariffs | sirven para clasificar y valorar trabajo de campo sin depender de ida y vuelta online; `providers` tambien representa empresas del ecosistema y debe converger como referencia de `empresas` | cache viejo, bleed entre empresas, drift de duplicados/defaults, referencias inconsistentes a empresas del ecosistema | existen tests offline-first en backend para varias referencias |
| operaciones reutilizables | payment_templates, cash_boxes | ayudan a ejecutar acciones operativas/financieras repetidas en campo | bootstrap incompleto, permisos viejos, refresh local incorrecto | existen tests smoke/contrato en backend, smoke de cliente limitado |

## Tier C - operaciones extendidas

| Dominio | Entidades | Por que importa en sitio | Riesgos principales | Control actual |
|---|---|---|---|---|
| ejecucion financiera | payments, receipts, invoices, invoice_items, invoice_receipt_payments | puede ser necesario para cerrar trabajo en sitio | drift de payload, acople con attachments, errores de scope | existe coverage smoke en backend, validacion de cliente todavia liviana |
| telemetria | tracking y ciclo de vida del dispositivo | ayuda a coordinacion y diagnostico en campo | drift de identidad de dispositivo, problemas de push/scope, validacion solo manual | existen docs, automatizacion limitada |

## Reglas de relacion que no deben degradarse

| Area de regla | Regla requerida |
|---|---|
| scope de empresa | los datos sincronizados de negocio deben resolver a un `company_id` valido o a un camino equivalente explicito |
| jerarquia de folders | si `jobs.folder_id` es `null`, los `job_items` pueden usar cualquier folder del mismo cliente; si `jobs.folder_id` tiene valor, cada `job_items.folder_id` debe ser esa misma carpeta o una subcarpeta valida |
| participantes | los participantes de appointments y worklogs deben pertenecer al scope correcto de empresa |
| attachments | cambiar el attachable debe tratarse como `detach + attach`, no como reasignacion implicita |
| entidades de enlace | los links puros deben usar `delete + create` en vez de reasignacion silenciosa |
| semantica de delete | un registro eliminado no debe aceptar updates operativos ni reaparecer como activo despues de pull/bootstrap |
| integridad de metadata | `version`, `source_device_id`, timestamps de auditoria y marcas de delete deben permanecer coherentes entre snapshots y streams de eventos |

## Controles genericos de sync

Toda entidad sincronizada deberia terminar teniendo estos controles:

1. cobertura de bootstrap
2. cobertura de eventos incrementales
3. cobertura de push/write cuando existan escrituras offline
4. cobertura de verify/reconcile
5. generacion de eventos del lado servidor fuera del sync push
6. persistencia local auditable del lado cliente
7. check manual o automatizado multi-dispositivo para create/update/delete
