# Limpieza de duplicados activos en participants

## Que protege

- elimina duplicados activos legacy en `work_log_participants` y `appointment_participants` sin hard delete
- conserva una sola fila activa por `(work_log_id, user_id)` o `(appointment_id, user_id)`, dejando las demas como soft-deleted

## Riesgo que cubre

- que un worklog o una cita muestre dos veces el mismo tecnico por baseline roto previo al fix de aplicacion
- que futuras reasignaciones choquen con el indice unico por seguir acumulando filas activas duplicadas

## Antes de correr

- correr primero los `SELECT` de preview y confirmar cuantas filas duplicadas activas existen
- idealmente ejecutar fuera de horario o con poca concurrencia sobre esas tablas
- hacer backup previo si el hosting lo permite

## Preview worklogs

```sql
SELECT work_log_id, user_id, COUNT(*) AS active_rows, GROUP_CONCAT(id ORDER BY id DESC) AS row_ids
FROM work_log_participants
WHERE deleted_at IS NULL
GROUP BY work_log_id, user_id
HAVING COUNT(*) > 1
ORDER BY work_log_id DESC, user_id ASC;
```

## Fix worklogs

```sql
START TRANSACTION;

CREATE TEMPORARY TABLE tmp_work_log_participant_dupes AS
SELECT id,
       ROW_NUMBER() OVER (ORDER BY work_log_id, user_id, id) AS seq
FROM (
    SELECT id,
           work_log_id,
           user_id,
           ROW_NUMBER() OVER (PARTITION BY work_log_id, user_id ORDER BY id DESC) AS rn
    FROM work_log_participants
    WHERE deleted_at IS NULL
) ranked
WHERE rn > 1;

UPDATE work_log_participants target
JOIN tmp_work_log_participant_dupes dupes ON dupes.id = target.id
SET target.deleted_at = DATE_ADD(NOW(), INTERVAL dupes.seq SECOND),
    target.updated_at = DATE_ADD(NOW(), INTERVAL dupes.seq SECOND),
    target.version = COALESCE(target.version, 1) + 1;

SELECT ROW_COUNT() AS soft_deleted_rows;

DROP TEMPORARY TABLE tmp_work_log_participant_dupes;

COMMIT;
```

## Preview appointments

```sql
SELECT appointment_id, user_id, COUNT(*) AS active_rows, GROUP_CONCAT(id ORDER BY id DESC) AS row_ids
FROM appointment_participants
WHERE deleted_at IS NULL
GROUP BY appointment_id, user_id
HAVING COUNT(*) > 1
ORDER BY appointment_id DESC, user_id ASC;
```

## Fix appointments

```sql
START TRANSACTION;

CREATE TEMPORARY TABLE tmp_appointment_participant_dupes AS
SELECT id,
       ROW_NUMBER() OVER (ORDER BY appointment_id, user_id, id) AS seq
FROM (
    SELECT id,
           appointment_id,
           user_id,
           ROW_NUMBER() OVER (PARTITION BY appointment_id, user_id ORDER BY id DESC) AS rn
    FROM appointment_participants
    WHERE deleted_at IS NULL
) ranked
WHERE rn > 1;

UPDATE appointment_participants target
JOIN tmp_appointment_participant_dupes dupes ON dupes.id = target.id
SET target.deleted_at = DATE_ADD(NOW(), INTERVAL dupes.seq SECOND),
    target.updated_at = DATE_ADD(NOW(), INTERVAL dupes.seq SECOND),
    target.version = COALESCE(target.version, 1) + 1;

SELECT ROW_COUNT() AS soft_deleted_rows;

DROP TEMPORARY TABLE tmp_appointment_participant_dupes;

COMMIT;
```

## Verificacion posterior

```sql
SELECT work_log_id, user_id, COUNT(*) AS active_rows
FROM work_log_participants
WHERE deleted_at IS NULL
GROUP BY work_log_id, user_id
HAVING COUNT(*) > 1;

SELECT appointment_id, user_id, COUNT(*) AS active_rows
FROM appointment_participants
WHERE deleted_at IS NULL
GROUP BY appointment_id, user_id
HAVING COUNT(*) > 1;
```

Ambos `SELECT` deben volver vacios.

## Puntos ciegos conocidos

- el script no fusiona metadata entre filas duplicadas; conserva activa la de `id` mas alto y soft-deletea el resto
- no reescribe historico ni corrige reportes ya exportados previamente con datos duplicados
- requiere MySQL 8+ por el uso de `ROW_NUMBER()`; si el host estuviera en una version mas vieja, habria que correr una variante con variables temporales
