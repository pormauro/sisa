# Checklist de Entidades Sync

## Objetivo

Evitar que una entidad sincronizable quede a mitad de camino entre CRUD, offline-first y convergencia multi-dispositivo.

Este checklist formaliza una regla simple para todas las tablas sync actuales y futuras:

> `deleted_at` o tombstone mas nuevo siempre gana contra updates, snapshots o checkpoints viejos.

Si una tabla nueva entra al sistema de sync, no alcanza con agregarla al `push/pull/bootstrap`: tambien tiene que pasar por este checklist.

## Tablas sync actuales que deben cumplirlo

### Scope, identidad y referencias operativas

- `memberships`
- `member_companies`
- `users`
- `permissions`
- `clients`
- `folders`
- `statuses`
- `job_priorities`
- `tariffs`
- `providers`
- `categories`
- `products_services`
- `payment_templates`
- `cash_boxes`

### Operacion de campo

- `jobs`
- `job_items`
- `work_logs`
- `work_log_participants`
- `appointments`
- `job_groups`
- `job_group_members`
- `root_causes`
- `job_root_cause_links`
- `file_attachments`

### Operacion financiera extendida

- `payments`
- `receipts`
- `invoices`
- `invoice_items`
- `invoice_receipt_payments`

## Gate obligatorio por entidad

### 1. Modelo de borrado

- la tabla usa `deleted_at` o una tombstone equivalente sincronizable
- no depende de hard delete para datos de negocio o referencias sync
- si por alguna razon existe hard delete, hay una defensa explicita para no dejar checkpoints huerfanos ni referencias rotas

### 2. Versionado y metadata de origen

- la tabla propaga `version`
- la tabla propaga `source_device_id`
- create, update y delete dejan snapshot canonico consistente
- delete incrementa `version` y conserva metadata suficiente para convergecia/reconcile

### 3. Bootstrap, pull y eventos

- `bootstrap` puede devolver tombstones cuando corresponda
- `pull/events` propagan deletes con payload completo
- `verify/reconcile` no ignoran registros borrados si la entidad usa tombstones
- un dispositivo nuevo no revive datos eliminados al hacer bootstrap limpio

### 4. Reglas de integridad y scope

- create/update rechazan parents, `company_id` o foreign keys que ya no existen o estan soft-deleted
- una entidad borrada no sigue contando como scope valido solo por tener una membership/cache stale
- una referencia faltante no se recrea implicitamente para "arreglar" el payload
- si la entidad depende de otra (`client -> company`, `folder -> client`, `job_item -> job`, etc.), el contrato de delete esta documentado

### 5. Cliente offline-first

- el cache local sabe persistir `deleted_at`
- bootstrap/pull remueven la entidad visible cuando llega tombstone
- la UI no mantiene listas visibles a partir de memberships/referencias stale
- una pantalla no inventa entidades fantasma para tapar referencias rotas

### 6. QA automatizado o fallback manual

- existe al menos un test o smoke que cubre create/update/delete/sync/re-sync
- existe al menos un caso de no resurreccion despues de delete
- si todavia no puede automatizarse, el escenario esta cubierto en `qa/MULTI_DEVICE_RUNBOOK.md`
- `QA_STATUS.md` deja registrado el riesgo, el alcance y los puntos ciegos reales

## Casos minimos que toda entidad debe pasar

### Caso A - create + update + delete + pull

- A crea
- A actualiza
- A elimina
- B hace pull
- B no ve reaparicion

### Caso B - delete + bootstrap limpio

- A elimina
- B limpia cache o parte de un bootstrap nuevo
- bootstrap no vuelve a materializar la entidad

### Caso C - stale replay

- A crea
- A actualiza
- A elimina
- B recibe despues un checkpoint/update viejo
- el update viejo no revive la entidad ni limpia `deleted_at`

### Caso D - dependencia rota

- una entidad hija llega con parent borrado o inexistente
- servidor y cliente rechazan o reconcilian el caso
- nunca se crea un parent fantasma para completar la referencia

## Regla para nuevas tablas sync

Cada tabla nueva debe agregarse en cuatro lugares antes de considerarse "lista":

- `qa/SYNC_ENTITY_CHECKLIST.md`
- la guia tecnica correspondiente del backend o frontend
- el smoke/test automatizado mas cercano
- `QA_STATUS.md` si entra con deuda, hueco o excepcion temporal

Checklist de onboarding para una tabla nueva:

1. definir owner, scope y foreign keys obligatorias
2. definir si usa soft delete/tombstone y como gana sobre updates viejos
3. cubrir `bootstrap`, `pull`, `events`, `verify` y `reconcile`
4. cubrir persistencia local y remocion por tombstone en cliente
5. agregar al menos un test de no resurreccion o un runbook manual estricto
6. actualizar esta lista y la documentacion de arquitectura/QA

## Excepciones

- datos temporales o puramente tecnicos pueden quedar fuera de soft delete solo si no participan del contrato offline-first ni del dataset operable en campo
- cualquier excepcion debe justificar por que no puede generar `ghost record`, `zombie record`, `dangling checkpoint` o `stale replay`
- si una excepcion nueva se aprueba, debe quedar anotada explicitamente en `QA_STATUS.md`

## Referencias relacionadas

- `QA_ROADMAP.md`
- `QA_STATUS.md`
- `qa/REGRESSION_CHECKLIST.md`
- `qa/MULTI_DEVICE_RUNBOOK.md`
- `sisa.api/docs/sync-references-qa-guide.md`
- `sisa.ui/docs/architecture/devices-sync-and-offline-first-standard.md`
