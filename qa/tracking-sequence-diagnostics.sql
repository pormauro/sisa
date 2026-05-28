SELECT device_id, sequence_no, COUNT(*) c
FROM gps_points
GROUP BY device_id, sequence_no
HAVING c > 1;

SELECT device_id,
       MIN(sequence_no) min_seq,
       MAX(sequence_no) max_seq,
       COUNT(*) total,
       COUNT(DISTINCT sequence_no) distinct_seq
FROM gps_points
GROUP BY device_id;

SELECT id, device_id, sequence_no, point_uuid, captured_at, created_at
FROM gps_points
ORDER BY device_id, captured_at ASC, id ASC;
