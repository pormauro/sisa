# QA Roadmap

## Objetivo

Construir un sistema de QA persistente para `sisa.api` + `sisa.ui`, enfocado en estabilidad real, prevencion de regresiones, validacion funcional de punta a punta y, especialmente, en el comportamiento generico de sync para los datos que deben seguir siendo utilizables en trabajo de campo con senal inestable.

Este roadmap evita de forma intencional que el QA de sync quede atado solo a `jobs`. La pregunta central es:

> Puede un tecnico u operador seguir trabajando con el conjunto minimo de datos confiables y luego converger de forma segura cuando vuelve la conectividad?

## Baseline de alcance

### Tier A - nucleo operable en campo

- continuidad de auth/sesion
- empresa seleccionada y membresias
- permisos
- usuarios necesarios para asignacion/participantes
- clientes
- folders
- estados
- jobs
- job items
- worklogs
- appointments
- archivos y adjuntos

### Tier B - datos de referencia y soporte en campo

- providers
- tariffs
- categories
- products/services
- payment templates
- cash boxes

### Tier C - datos operativos/financieros extendidos

- payments
- receipts
- invoices
- invoice items
- invoice receipt payments
- tracking y diagnosticos relacionados

## Principios de QA

- Empezar por las superficies de mayor riesgo y menor confianza.
- Preferir pocos controles confiables antes que muchos checks superficiales.
- Cubrir create, update, delete, sync y re-sync como un unico sistema.
- Cada milestone debe dejar el workspace en un estado mediblemente mejor.
- La documentacion es parte del entregable, no un agregado posterior.
- Regla conservadora: si algo todavia no puede automatizarse, escribir el procedimiento manual y el bloqueo actual.

## Baseline conocido desde discovery

- El backend ya tiene cobertura util con PHPUnit para appointments, formato de sync, referencias y parte del comportamiento de soft delete a nivel modelo.
- El backend todavia no tiene cobertura focalizada suficiente para `jobs`, `job_items`, `work_logs`, `clients`, `folders`, `statuses` y flujos crudos de archivos.
- El frontend no tiene framework formal de unit/integration/e2e configurado; hoy depende de scripts smoke propios y lint.
- El conocimiento de sync existe, pero esta fragmentado entre `sisa.api/docs/*` y `sisa.ui/docs/*`.
- La arquitectura es mixta: conviven CRUD legacy y flujos offline-first mas nuevos.

## Milestones

### Milestone 0 - base operativa de QA

Objetivo: crear instrucciones compartidas persistentes, plan, estado de sesion y un punto unico de entrada para el baseline de validacion.

Entregables:

- `AGENTS.md`
- `QA_ROADMAP.md`
- `QA_STATUS.md`
- `qa/FIELD_DATASET_MAP.md`
- `qa/REGRESSION_CHECKLIST.md`
- `qa/run-baseline.ps1`

Validacion:

- Ejecutar los comandos actuales de validacion de backend y frontend.
- Registrar pass/fail y clasificar fallas como deuda de baseline o regresion nueva.

Se considera terminado cuando:

- futuras sesiones pueden continuar sin redescubrir el repo,
- los comandos de baseline quedan documentados y son corribles,
- las fallas actuales quedan explicitas o el tooling viejo del baseline fue reparado.

### Milestone 1 - mapa de dominio y contrato de regresion

Objetivo: definir que no debe degradarse silenciosamente entre ambos proyectos.

Cobertura:

- mapa de dominio y relaciones entre entidades,
- relaciones obligatorias impuestas por la aplicacion,
- reglas de propagacion de delete,
- semantica `attach/detach` frente a `update`,
- dependencias de orden de operaciones,
- dataset minimo operable en campo,
- checklist manual de regresion previo a deploy.

Implementacion:

- documentar el contrato de regresion y el checklist manual,
- documentar el dataset minimo operable en campo,
- vincular cada riesgo con su automatizacion actual o su fallback manual.

Validacion:

- asegurar que cada entidad critica tenga postura documentada para create/update/delete/sync,
- volver a correr el baseline luego de cambios documentales.

### Milestone 2 - tests de integridad de dominio en backend

Objetivo: aumentar la confianza en las reglas de negocio del servidor de forma independiente de la UI.

Primeros objetivos:

- `JobsController`
- `JobItemsController`
- `WorkLogsController`
- `FileAttachmentsController`
- `ClientsController`
- `FoldersController`
- `StatusController`

Casos minimos:

- happy path de create/update/delete,
- validacion de relaciones obligatorias,
- rechazo de parents/scopes invalidos,
- guardas despues de delete,
- reasignaciones prohibidas (`delete + create`, `detach + attach`).

Validacion:

- corridas focalizadas de PHPUnit para cada archivo nuevo de tests,
- luego `vendor/bin/phpunit` mas amplio cuando los bloqueos de baseline queden resueltos o aislados.

### Milestone 3 - tests de contrato de sync en backend

Objetivo: verificar consistencia generica de sync, no solo CRUD por modulo.

Cobertura:

- `bootstrap`, `events`, `push`, `verify`, `reconcile`,
- consistencia de `version`,
- `payload.version`,
- `source_device_id`,
- propagacion de deletes,
- no resurreccion despues de delete,
- comportamiento de claves de idempotencia,
- scope por empresa/dispositivo.

Orden de prioridad:

1. Entidades Tier A
2. Referencias Tier B
3. Flujos financieros Tier C

Validacion:

- corridas focalizadas de PHPUnit de sync,
- checks manuales solo en los huecos que todavia no se puedan automatizar.

### Milestone 4 - cobertura smoke de cliente/offline/store

Objetivo: agregar confianza del lado cliente sin saltar demasiado pronto a E2E pesados.

Cobertura:

- persistencia local e hidratacion,
- comportamiento de cola/checkpoints,
- propagacion del cache de referencias,
- transiciones local/remoto de attachments,
- smokes de reconexion,
- checks de no regresion del shell/bootstrap.

Opciones de implementacion, en este orden:

1. endurecer los smoke scripts existentes,
2. agregar tests de funciones puras o repositorios,
3. agregar tests livianos de hooks con mocks,
4. postergar E2E de dispositivo completo hasta que las capas inferiores sean confiables.

### Milestone 5 - runbook multi-dispositivo y offline-to-online

Objetivo: volver ejecutables y auditables los escenarios de sync mas dificiles.

Escenarios obligatorios:

- dispositivo A crea offline y luego converge online,
- dispositivo A elimina y dispositivo B no debe ver reaparicion,
- propagacion de delete de attachments,
- conflicto de edicion sobre la misma entidad,
- orden correcto de operaciones dependientes,
- bootstrap de un dispositivo nuevo,
- propagacion por empresa excluyendo el dispositivo de origen.

Validacion:

- runbook manual con evidencia esperada,
- export/logs cuando sea posible.

Entregable minimo:

- `qa/MULTI_DEVICE_RUNBOOK.md`

### Milestone 6 - gate de release y estrategia de expansion

Objetivo: definir la puerta minima de QA antes de deploy y el camino para ampliar cobertura.

Cobertura:

- set minimo de comandos smoke para release,
- checklist de regresion,
- politica de tratamiento de bloqueos,
- matriz de cobertura automatizada vs manual,
- siguientes entidades a incorporar a QA mas fuerte.

## Baseline del checklist de regresion

Antes de deploy, como minimo verificar:

- login/restauracion de sesion no rompen shell/bootstrap,
- empresa seleccionada, membresias y permisos convergen correctamente,
- clientes/folders/statuses son visibles y editables dentro del scope correcto,
- jobs/job items/worklogs/appointments soportan create/edit/delete sin dejar datos huerfanos,
- adjuntos eliminados no reaparecen despues de pull/bootstrap,
- escrituras offline convergen al reconectar,
- un segundo dispositivo recibe los cambios esperados sin ecos incorrectos del origen,
- `verify/reconcile` no reportan drift inexplicable para entidades tocadas,
- ningun comando nuevo del baseline falla sin aprobacion explicita.

## Criterios de salida para el sistema QA inicial

- la documentacion compartida existe y esta actualizada,
- los comandos de baseline estan centralizados,
- los riesgos de Tier A estan al menos mapeados con automatizacion o control manual,
- nuevas sesiones pueden continuar desde `QA_STATUS.md` sin redescubrir el sistema,
- el siguiente hueco de mayor valor queda explicitado.
