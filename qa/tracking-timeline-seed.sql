-- Seed manual no destructivo para validar /tracking-timeline.
--
-- Uso:
-- 1. Confirmar que la migracion tracking raw hardening ya fue aplicada.
-- 2. Reemplazar @company_id y @user_id por IDs reales visibles para el usuario que va a probar la pantalla.
-- 3. Ajustar @day si se quiere otra fecha de prueba.
-- 4. Ejecutar manualmente contra la base MySQL de SISA.
--
-- No borra datos. Usa INSERT ... ON DUPLICATE KEY UPDATE con asignacion no-op
-- para que sea re-ejecutable sin modificar contenido existente.

SET @company_id = 1;
SET @user_id = 1;
SET @device_id = 'qa-tracking-timeline-device';
SET @day = '2026-05-27';
SET @batch_uuid = CONCAT('qa-tracking-timeline-', @company_id, '-', @user_id, '-', @day);

INSERT INTO gps_upload_batches (
    user_id,
    company_id,
    device_id,
    batch_uuid,
    first_sequence_no,
    last_sequence_no,
    points_count,
    last_known_server_point_id,
    status,
    metadata,
    received_at,
    processed_at,
    created_at,
    updated_at
) VALUES (
    @user_id,
    @company_id,
    @device_id,
    @batch_uuid,
    1,
    5,
    5,
    NULL,
    'processed',
    JSON_OBJECT('source', 'manual_qa_seed', 'purpose', 'tracking_timeline_visual_validation'),
    CONCAT(@day, ' 12:00:00'),
    CONCAT(@day, ' 12:00:01'),
    CONCAT(@day, ' 12:00:00'),
    CONCAT(@day, ' 12:00:01')
) ON DUPLICATE KEY UPDATE id = id;

INSERT INTO gps_points (
    user_id,
    company_id,
    device_id,
    sequence_no,
    point_uuid,
    captured_at,
    lat,
    lng,
    accuracy_m,
    speed_mps,
    heading_deg,
    altitude_m,
    battery_level,
    is_mock,
    source,
    state,
    job_id,
    client_candidate_id,
    created_at,
    updated_at
) VALUES
    (@user_id, @company_id, @device_id, 1, CONCAT(@batch_uuid, '-point-001'), CONCAT(@day, ' 12:00:00'), -31.4201000, -64.1888000, 18.00, 0.00, 0.00, NULL, 0.92, 0, 'manual_qa_seed', 'standby', NULL, NULL, CONCAT(@day, ' 12:00:00'), CONCAT(@day, ' 12:00:00')),
    (@user_id, @company_id, @device_id, 2, CONCAT(@batch_uuid, '-point-002'), CONCAT(@day, ' 12:01:00'), -31.4202500, -64.1889500, 22.00, 2.40, 135.00, NULL, 0.91, 0, 'manual_qa_seed', 'moving', NULL, NULL, CONCAT(@day, ' 12:01:00'), CONCAT(@day, ' 12:01:00')),
    -- Gap intencional: 9 minutos entre sequence 2 y 3. Debe aparecer como gap > 5 min.
    (@user_id, @company_id, @device_id, 3, CONCAT(@batch_uuid, '-point-003'), CONCAT(@day, ' 12:10:00'), -31.4210000, -64.1897000, 24.00, 3.10, 140.00, NULL, 0.89, 0, 'manual_qa_seed', 'moving', NULL, NULL, CONCAT(@day, ' 12:10:00'), CONCAT(@day, ' 12:10:00')),
    -- Accuracy mala intencional: debe aparecer como suspicious_point low_accuracy.
    (@user_id, @company_id, @device_id, 4, CONCAT(@batch_uuid, '-point-004'), CONCAT(@day, ' 12:11:00'), -31.4213000, -64.1900500, 125.00, 4.20, 145.00, NULL, 0.88, 0, 'manual_qa_seed', 'moving', NULL, NULL, CONCAT(@day, ' 12:11:00'), CONCAT(@day, ' 12:11:00')),
    -- Velocidad alta para inspeccion visual. El backend actual todavia no genera anomalia por velocidad.
    (@user_id, @company_id, @device_id, 5, CONCAT(@batch_uuid, '-point-005'), CONCAT(@day, ' 12:12:00'), -31.4221000, -64.1910000, 20.00, 38.00, 150.00, NULL, 0.87, 0, 'manual_qa_seed', 'moving', NULL, NULL, CONCAT(@day, ' 12:12:00'), CONCAT(@day, ' 12:12:00'))
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO user_last_locations (
    user_id,
    company_id,
    point_id,
    device_id,
    captured_at,
    lat,
    lng,
    accuracy_m,
    speed_mps,
    heading_deg,
    state,
    updated_at
)
SELECT
    gp.user_id,
    gp.company_id,
    gp.id,
    gp.device_id,
    gp.captured_at,
    gp.lat,
    gp.lng,
    gp.accuracy_m,
    gp.speed_mps,
    gp.heading_deg,
    gp.state,
    NOW()
FROM gps_points gp
WHERE gp.device_id = @device_id
  AND gp.point_uuid = CONCAT(@batch_uuid, '-point-005')
ON DUPLICATE KEY UPDATE user_id = user_id;

-- Validacion esperada en /tracking-timeline:
-- date = @day
-- points_count = 5
-- gaps >= 1, con duration_s cercano a 540
-- suspicious_points >= 1, con type low_accuracy para sequence 4
-- anomalies >= 1, por gap
