# Backlog tecnico de tracking

Este backlog traduce la arquitectura de `docs/tracking-architecture.md` en tickets implementables. El orden recomendado es incremental y evita IA o dashboards antes de tener captura, raw, timeline y correccion humana.

## P0 - endurecer base operable existente

| Ticket | Repos probables | Resultado esperado |
|---|---|---|
| `tracking-raw-schema-idempotency-repair` | `sisa.api`, `sisa.ui` | P0 urgente: reparar ids `0`/duplicados, restaurar `PRIMARY KEY AUTO_INCREMENT`, backfill unico de `point_uuid`/`batch_uuid`, rechazar ACKs con `server_point_id=0` y bloquear timeline/stays/trips/IA hasta validar raw |
| `tracking-current-baseline-contract` | `sisa.api`, `sisa.ui`, `sisa.web` | Documentar contrato real de endpoints, payloads, tablas `gps_*`, permisos y pantallas existentes |
| `tracking-raw-company-scope` | `sisa.api` | Primer corte implementado: `company_id` en raw, batches, last location e ingesta; falta exigirlo como contrato estricto |
| `tracking-batch-point-uuid-idempotency` | `sisa.api`, `sisa.ui` | Primer corte implementado: `batch_uuid` y `point_uuid` con fallback legacy por `device_id + sequence_no` |
| `tracking-policy-hardening` | `sisa.api` | `GET /tracking/policy` con scope por empresa, miembro, device, permiso, horario y versionado explicito |
| `tracking-mobile-queue-limits` | `sisa.ui` | Limites de cola local, descarte controlado, metadata de app/permisos/app state y ack parcial robusto |
| `tracking-docs-postman` | `sisa.api`, `sisa.ui` | Contratos documentados, ejemplos y coleccion Postman actualizada |

## P1 - lectura y reconstruccion

Nota de estabilizacion: no avanzar a `tracking-stays-trips-gaps-v1` hasta validar `/tracking-timeline` con datos reales o con el seed controlado `qa/tracking-timeline-seed.sql`.

| Ticket | Repos probables | Resultado esperado |
|---|---|---|
| `tracking-day-route-readonly` | `sisa.api`, web target | Consulta de dias y ruta simplificada para mapa |
| `tracking-timeline-readonly` | `sisa.api`, `sisa.web` | Implementado: `/tracking/timeline` calcula gaps, puntos dudosos y quality score desde raw; `sisa.web` expone vista read-only para fecha, usuario, resumen, puntos, gaps y anomalias |
| `tracking-rebuild-worker` | `sisa.api` | Worker CLI/cron para reconstruir dias por miembro/dispositivo |
| `tracking-stays-trips-gaps-v1` | `sisa.api` | Proximo paso recomendado: deteccion inicial de stays/trips v1 desde raw validado, sin IA ni scoring laboral |
| `tracking-processing-runs-audit` | `sisa.api` | Registro de corridas, versiones de reglas y estadisticas de reproceso |

## P2 - operacion humana

| Ticket | Repos probables | Resultado esperado |
|---|---|---|
| `tracking-label-types` | `sisa.api`, web target | Catalogo configurable de tipos de etiqueta por empresa |
| `tracking-time-labels-crud` | `sisa.api`, web target | Crear, corregir, reemplazar y auditar labels manuales |
| `tracking-known-places-geofences` | `sisa.api`, web target | CRUD de lugares conocidos y geocercas circulares |
| `tracking-known-place-match-v1` | `sisa.api` | Match server-side de stays contra geofences |
| `tracking-unknown-place-review` | `sisa.api`, web target | Candidatos recurrentes revisables y convertibles a known place |

## P3 - privacidad, auditoria y escala

| Ticket | Repos probables | Resultado esperado |
|---|---|---|
| `tracking-sensitive-access-audit` | `sisa.api`, web target | Auditoria de consultas, cambios y accesos sensibles |
| `tracking-retention-policy` | `sisa.api` | Retencion, downsampling, archivado o borrado por empresa/politica |
| `tracking-out-of-hours-masking` | `sisa.api`, web target | Masking fuera de horario para roles sin permiso elevado |
| `tracking-volume-index-review` | `sisa.api` | Revision de indices, particionado logico y rendimiento de timeline |
| `tracking-mobile-battery-pilot` | `sisa.ui` | Medicion real de bateria y ajuste de frecuencia por policy |

## P4 - IA y automatizacion no destructiva

| Ticket | Repos probables | Resultado esperado |
|---|---|---|
| `tracking-dataset-export` | `sisa.api` | Export supervisado desde raw, derivados, labels y contexto operativo |
| `tracking-feature-versioning` | `sisa.api` | Versionado de features, reglas y snapshots de dataset |
| `tracking-model-registry-minimal` | `sisa.api` o repo IA futuro | Registro de modelo, version, metricas, alias y aprobador |
| `tracking-ai-label-suggestions` | `sisa.api`, web target | Sugerencias de label no destructivas con confianza y explicacion |
| `tracking-ai-review-loop` | web target | Aceptar/rechazar sugerencias y alimentar dataset de correcciones |

## Criterios transversales de aceptacion

- Todo endpoint debe validar `company_id`, miembro, permisos y scope.
- Toda escritura raw debe ser idempotente por batch y por punto.
- Ningun proceso debe editar destructivamente puntos crudos.
- Todo derivado debe ser reconstruible desde raw con version de regla.
- Toda accion manual sobre labels, places o policy debe dejar auditoria.
- Cualquier IA debe producir sugerencias separadas de labels manuales.
- Cada PR debe incluir pruebas o runbook manual si no se puede automatizar todavia.
