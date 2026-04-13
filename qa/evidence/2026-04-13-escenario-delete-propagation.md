# Evidencia Multi-Dispositivo - Delete Propagation Sin Reaparicion

## Encabezado

- Fecha: 2026-04-13
- Operador: pendiente
- Milestone: 5
- Escenario: delete propagation sin reaparicion (`status`)
- Empresa: pendiente
- Dispositivo A: pendiente
- Dispositivo B: pendiente
- Web/Admin usado: pendiente

## Identificadores

- `device_id` A: pendiente
- `device_id` B: pendiente
- UUID principal: pendiente
- UUIDs relacionados: pendiente

## Preparacion

- Baseline corrido: si
- Resultado baseline: pass (`powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1`)
- Estado inicial confirmado en A: pendiente
- Estado inicial confirmado en B: pendiente
- Estado inicial confirmado en servidor: pendiente

## Pasos ejecutados

1. Elegir una entidad visible en A y B. Recomendado: `status`, `provider`, `client`, `folder` o un attachment relacional (`job_file`, `job_item_file`, `worklog_file`).
1. Entidad elegida para esta corrida: `status`.
2. Registrar UUID y, si aplica, `device_id` de A y B.
3. Eliminar el `status` en A.
4. Confirmar en A que deja de aparecer como activo.
5. Ejecutar sync desde A.
6. Verificar en servidor/web el estado canonico.
7. En B, ejecutar pull o bootstrap.
8. Confirmar que el `status` no reaparece en B.
9. Forzar refresh adicional o bootstrap limpio si aplica.
10. Confirmar de nuevo no reaparicion.

## Resultado esperado

- la entidad eliminada no reaparece en A
- la entidad eliminada no reaparece en B
- la entidad eliminada no reaparece despues de refresh/pull/bootstrap
- el servidor conserva estado canonico de delete/tombstone

## Resultado real

- A:
- B:
- servidor/web:

## Evidencia adjunta

- Screenshot A:
- Screenshot B:
- Screenshot servidor/web:
- Log/DB export:

## Verificaciones finales

- A converge: pendiente
- B converge: pendiente
- Servidor converge: pendiente
- Sin reaparicion: pendiente
- Sin duplicados: pendiente
- Drift visible/resuelto: pendiente

## Observaciones

- Tipo elegido en esta primera corrida: `status`.
- Si esta corrida pasa, la siguiente recomendada es repetir con `provider` y luego con un attachment relacional.

## Decision

- Estado: en progreso
- Accion siguiente: completar observaciones de A/B/servidor para cerrar la corrida.
