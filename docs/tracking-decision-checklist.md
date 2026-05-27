# Checklist de decisiones bloqueantes para tracking

Estas decisiones deben cerrarse antes de implementar el primer PR de tracking. Si una queda abierta, el riesgo debe registrarse explicitamente en `QA_STATUS.md`.

## Producto y alcance

- [ ] Confirmar promesa comercial de captura: foreground fiable y background opt-in, sin garantia de app terminada.
- [ ] Definir si el piloto inicial es Android-only.
- [ ] Definir usuarios piloto, empresas piloto y duracion del piloto.
- [ ] Definir frecuencia inicial de captura por escenario: en movimiento, detenido, baja bateria y fuera de horario.
- [ ] Definir que se considera jornada activa y como se resuelve fuera de horario.

## Repos y ubicacion de UI web

- [x] Verificar estructura real de `sisa.web`: existe, es Vite/React 19 con `react-router-dom`, `src/App.tsx`, `app-navigation` y pantallas live de tracking.
- [x] Verificar si `sisa` cumple algun rol de coordinacion o documentacion: no existe directorio `sisa/` en el workspace revisado.
- [ ] Decidir formalmente si la consola avanzada de tracking vive en `sisa.web`; la base actual sugiere que si, porque ya tiene `TrackingCatalogsPages.tsx`.
- [x] Identificar sistema de rutas, permisos y servicios del target web elegido: `sisa.web/src/App.tsx`, `src/navigation/app-navigation.ts`, `src/services/trackingCatalogsService.ts`.

## Backend y datos

- [x] Confirmar motor SQL real: `sisa.api/src/Config/Database.php` usa MySQL por PDO con `charset=utf8mb4`.
- [ ] Confirmar capacidades geoespaciales disponibles; por ahora solo hay `lat/lng` numericos e indices basicos en `gps_points`.
- [ ] Confirmar convencion final para migraciones de tracking: la base actual usa `ensureTable()` en modelos y el repo tambien tiene `scripts/migrations/*`.
- [ ] Definir `device_id`: origen, estabilidad, rotacion, reset y relacion con usuario/miembro.
- [x] Definir baseline actual de identidad servidor: los puntos se asocian a `user_id` derivado del token y `device_id` del request/payload.
- [ ] Decidir si el modelo futuro usa `member_id` explicito o mantiene `user_id` con resolucion de membresia/company server-side.
- [x] Definir primera politica de idempotencia raw: `batch_uuid` por lote y `point_uuid` por punto, manteniendo fallback legacy por `device_id + sequence_no`.
- [ ] Definir indices minimos y estrategia ante volumen alto.

## Permisos y privacidad

- [ ] Definir permisos de captura, visualizacion, auditoria y edicion de labels.
- [ ] Definir quien puede ver ubicacion fuera de horario.
- [ ] Definir masking por defecto fuera de horario.
- [ ] Definir retencion de raw, derivados, labels y auditoria.
- [ ] Definir base legal/consentimiento o comunicacion laboral requerida.
- [ ] Definir si se auditan tambien las consultas de mapa/timeline, no solo cambios.

## Movil

- [x] Confirmar version Expo/EAS objetivo para background location: `sisa.ui` usa Expo 54, `expo-location`, `expo-task-manager`, `expo-dev-client` y `eas` configurado.
- [x] Confirmar que hay permisos declarados: `ACCESS_FINE_LOCATION`, `ACCESS_COARSE_LOCATION`, `ACCESS_BACKGROUND_LOCATION`, `FOREGROUND_SERVICE_LOCATION` y plugin `expo-location`.
- [ ] Definir si se usara development build/canal interno antes de rollout.
- [ ] Definir UX de permisos foreground/background y flujo a Android Settings; existe request tecnico en `src/tracking/location.ts`, falta decision UX/piloto.
- [ ] Definir comportamiento cuando permisos se revocan.
- [ ] Definir limites de cola local: maximo de puntos, maximo de dias y estrategia de descarte.
- [x] Confirmar baseline de captura local: existe `TaskManager.defineTask`, `startLocationUpdatesAsync`, captura manual y cola local en SQLite.
- [ ] Definir si se captura con app en foreground aunque background este deshabilitado.

## Procesamiento y timeline

- [ ] Definir umbrales iniciales completos de accuracy, stay, trip, gap y salto imposible; primer corte read-only usa gap > 5 min y baja confiabilidad por `accuracy_m > 100`.
- [ ] Definir como se versionan reglas y reprocesos.
- [ ] Definir frecuencia de rebuild: inmediato por batch, cron o manual.
- [ ] Definir formato de polyline o geometria simplificada para mapa.
- [ ] Definir dataset manual inicial de jornadas auditadas.

## IA futura

- [ ] Confirmar que IA no se implementa en el MVP de captura.
- [ ] Definir criterio minimo para activar IA: cantidad de labels, acuerdo humano y metricas.
- [ ] Definir metricas por tipo de prediccion: precision, recall, F1, matriz de confusion, MAE o ROC AUC.
- [ ] Definir registry minimo: dataset snapshot, feature version, model version, metricas y aprobador.
- [ ] Definir proceso de revision humana de sugerencias.

## Go/no-go antes de codigo

- [ ] Hay repo web objetivo confirmado formalmente; `sisa.web` es el candidato natural por implementacion existente.
- [ ] Hay motor SQL y migraciones confirmadas; MySQL esta confirmado, estrategia de migracion tracking sigue abierta.
- [ ] Hay policy de permisos y privacidad confirmada.
- [ ] Hay contrato de captura movil confirmado.
- [ ] Hay retencion definida.
- [ ] Hay alcance de piloto y rollback definido.
- [x] Hay primer corte P0 implementado para hardening raw: `company_id`, `batch_uuid`, `point_uuid`, scope opcional y payload movil.
