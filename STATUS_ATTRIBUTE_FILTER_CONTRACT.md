# Status Attribute Filter Contract

## Objetivo

La semantica operativa de `statuses` deja de depender del nombre visible (`label`, `name`, `code`).

Desde ahora:

- la API publica y sincroniza `status_attribute`
- la web online filtra y colorea por `status_attribute`
- la app offline persiste `status_attribute` localmente y lo consume por bootstrap/pull/sync
- `label` queda solo como texto visible editable por empresa

## Catalogo global compartido

Todos los clientes usan el mismo set global:

- `requested`
- `quoted`
- `quote_approved`
- `scheduled`
- `assigned`
- `in_progress`
- `pending`
- `blocked`
- `completed`
- `billable`
- `invoiced`
- `paid`
- `cancelled`

## Reglas de consumo

### Web online

- cualquier filtro de jobs/clientes/dashboard que antes buscaba palabras como `finalizado`, `facturado`, `pagado`, `cancelado`, `cotizado` o `aprobado` debe resolver por `status_attribute`
- la web debe tratar `label` solo como display
- `name` y `code` quedan discontinuados como fuente funcional

### App offline

- el bootstrap de referencias, el pull incremental y la cache SQLite deben persistir `status_attribute`
- cualquier resolucion local de estado final/facturable/facturado/cancelado debe usar `status_attribute`
- solo si un registro legacy todavia no trae atributo se permite fallback temporal por texto

### API / sync

- `status_attribute` es obligatorio para altas y updates canonicos
- la sincronizacion de referencias debe propagar `status_attribute` en `statuses`
- la resolucion de significado funcional debe priorizar siempre el atributo y nunca el nombre visible

## Mapeo esperado para empresa 45

- `Solicitado` -> `requested`
- `Calificado` -> `pending`
- `Cotizado` -> `quoted`
- `Aprobado` -> `quote_approved`
- `Planificado` -> `scheduled`
- `En curso` -> `in_progress`
- `En revisión` -> `pending`
- `Finalizado` -> `completed`
- `Facturado` -> `invoiced`
- `Pagado` -> `paid`
- `Cancelado` -> `cancelled`

## SQL de backfill para test / empresa 45

```sql
UPDATE statuses
SET status_attribute = 'requested'
WHERE company_id = 45 AND label = 'Solicitado' AND (status_attribute IS NULL OR status_attribute = '');

UPDATE statuses
SET status_attribute = 'pending'
WHERE company_id = 45 AND label = 'Calificado' AND (status_attribute IS NULL OR status_attribute = '');

UPDATE statuses
SET status_attribute = 'quoted'
WHERE company_id = 45 AND label = 'Cotizado' AND (status_attribute IS NULL OR status_attribute = '');

UPDATE statuses
SET status_attribute = 'quote_approved'
WHERE company_id = 45 AND label = 'Aprobado' AND (status_attribute IS NULL OR status_attribute = '');

UPDATE statuses
SET status_attribute = 'scheduled'
WHERE company_id = 45 AND label = 'Planificado' AND (status_attribute IS NULL OR status_attribute = '');

UPDATE statuses
SET status_attribute = 'in_progress'
WHERE company_id = 45 AND label = 'En curso' AND (status_attribute IS NULL OR status_attribute = '');

UPDATE statuses
SET status_attribute = 'pending'
WHERE company_id = 45 AND label IN ('En revisión', 'En revision') AND (status_attribute IS NULL OR status_attribute = '');

UPDATE statuses
SET status_attribute = 'completed'
WHERE company_id = 45 AND label = 'Finalizado' AND (status_attribute IS NULL OR status_attribute = '');

UPDATE statuses
SET status_attribute = 'invoiced'
WHERE company_id = 45 AND label = 'Facturado' AND (status_attribute IS NULL OR status_attribute = '');

UPDATE statuses
SET status_attribute = 'paid'
WHERE company_id = 45 AND label = 'Pagado' AND (status_attribute IS NULL OR status_attribute = '');

UPDATE statuses
SET status_attribute = 'cancelled'
WHERE company_id = 45 AND label = 'Cancelado' AND (status_attribute IS NULL OR status_attribute = '');
```

## Validacion minima posterior

- `GET /statuses?company_id=45` no debe devolver `status_attribute: null`
- la web debe seguir calculando filtros de clientes/facturacion sin depender de texto
- la app debe poder bootstrapear `statuses` y mantener el mismo significado offline despues del pull incremental
