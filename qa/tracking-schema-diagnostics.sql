SHOW CREATE TABLE gps_points;
SHOW CREATE TABLE gps_upload_batches;
SHOW CREATE TABLE user_last_locations;

SHOW COLUMNS FROM gps_points;
SHOW COLUMNS FROM gps_upload_batches;
SHOW COLUMNS FROM user_last_locations;

SHOW INDEX FROM gps_points;
SHOW INDEX FROM gps_upload_batches;
SHOW INDEX FROM user_last_locations;

SELECT id, COUNT(*) c
FROM gps_points
GROUP BY id
HAVING c > 1;

SELECT id, COUNT(*) c
FROM gps_upload_batches
GROUP BY id
HAVING c > 1;

SELECT device_id, sequence_no, COUNT(*) c
FROM gps_points
GROUP BY device_id, sequence_no
HAVING c > 1;

SELECT device_id, point_uuid, COUNT(*) c
FROM gps_points
WHERE point_uuid IS NOT NULL AND point_uuid <> ''
GROUP BY device_id, point_uuid
HAVING c > 1;

SELECT device_id, batch_uuid, COUNT(*) c
FROM gps_upload_batches
WHERE batch_uuid IS NOT NULL AND batch_uuid <> ''
GROUP BY device_id, batch_uuid
HAVING c > 1;

SELECT COUNT(*) total, SUM(id = 0) id_zero
FROM gps_points;

SELECT COUNT(*) total, SUM(id = 0) id_zero
FROM gps_upload_batches;

SELECT *
FROM user_last_locations
WHERE point_id = 0;
