SELECT 'gps_points id = 0' AS check_name, gp.*
FROM gps_points gp
WHERE gp.id = 0;

SELECT 'gps_upload_batches id = 0' AS check_name, gub.*
FROM gps_upload_batches gub
WHERE gub.id = 0;

SELECT id, COUNT(*) affected_rows
FROM gps_points
GROUP BY id
HAVING affected_rows > 1;

SELECT id, COUNT(*) affected_rows
FROM gps_upload_batches
GROUP BY id
HAVING affected_rows > 1;

SELECT 'gps_points missing point_uuid' AS check_name, gp.*
FROM gps_points gp
WHERE gp.point_uuid IS NULL OR gp.point_uuid = '';

SELECT 'gps_upload_batches missing batch_uuid' AS check_name, gub.*
FROM gps_upload_batches gub
WHERE gub.batch_uuid IS NULL OR gub.batch_uuid = '';

SELECT 'gps_points duplicate device_id + point_uuid' AS check_name, device_id, point_uuid, COUNT(*) affected_rows
FROM gps_points
WHERE point_uuid IS NOT NULL AND point_uuid <> ''
GROUP BY device_id, point_uuid
HAVING affected_rows > 1;

SELECT 'gps_upload_batches duplicate device_id + batch_uuid' AS check_name, device_id, batch_uuid, COUNT(*) affected_rows
FROM gps_upload_batches
WHERE batch_uuid IS NOT NULL AND batch_uuid <> ''
GROUP BY device_id, batch_uuid
HAVING affected_rows > 1;

SELECT 'gps_upload_batches last_known_server_point_id = 0' AS check_name, gub.*
FROM gps_upload_batches gub
WHERE gub.last_known_server_point_id = 0;

SELECT 'user_last_locations point_id = 0' AS check_name, ull.*
FROM user_last_locations ull
WHERE ull.point_id = 0;

SELECT 'gps_points ids to repair' AS repair_name, COUNT(*) affected_rows
FROM gps_points gp
JOIN (
    SELECT id
    FROM gps_points
    GROUP BY id
    HAVING id = 0 OR COUNT(*) > 1
) d ON d.id = gp.id;

SELECT 'gps_upload_batches ids to repair' AS repair_name, COUNT(*) affected_rows
FROM gps_upload_batches gub
JOIN (
    SELECT id
    FROM gps_upload_batches
    GROUP BY id
    HAVING id = 0 OR COUNT(*) > 1
) d ON d.id = gub.id;

SELECT 'gps_points point_uuid to backfill' AS repair_name, COUNT(*) affected_rows
FROM gps_points
WHERE point_uuid IS NULL OR point_uuid = '';

SELECT 'gps_upload_batches batch_uuid to backfill' AS repair_name, COUNT(*) affected_rows
FROM gps_upload_batches
WHERE batch_uuid IS NULL OR batch_uuid = '';

SELECT 'gps_upload_batches last_known_server_point_id = 0 to null' AS repair_name, COUNT(*) affected_rows
FROM gps_upload_batches
WHERE last_known_server_point_id = 0;

SELECT 'user_last_locations point_id = 0 to clear from trusted last location' AS repair_name, COUNT(*) affected_rows
FROM user_last_locations
WHERE point_id = 0;
