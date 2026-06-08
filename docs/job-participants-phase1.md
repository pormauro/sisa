# Job participants phase 1

`employees` representa personas operativas. `job_participants` representa empleados asignados o participantes de un trabajo.

En esta etapa:

- un trabajo puede tener cero, uno o varios participantes.
- un trabajo puede tener un unico responsable principal activo.
- si se marca un participante como responsable, la API desmarca otros responsables activos del mismo trabajo dentro de una transaccion.
- no se usa ni se revive `jobs.participants`; se considera legacy.
- `work_log_participants` sigue existiendo basado en `user_id` y no se migra en esta etapa.
- la app movil y el modelo offline/sync movil todavia no consumen `job_participants`.
- la integracion queda limitada a API y `sisa.web`.

API inicial:

- `GET /jobs/{job_id}/participants`
- `POST /jobs/{job_id}/participants`
- `PUT /jobs/{job_id}/participants/{participant_id}`
- `DELETE /jobs/{job_id}/participants/{participant_id}`

Reglas principales:

- `job_id` y `employee_id` deben pertenecer a la misma empresa.
- no se permite asociar empleados eliminados o archivados/inactivos.
- no se permite asociar trabajos eliminados.
- no se permite duplicar el mismo empleado activo en el mismo trabajo.
- las mutaciones respetan `ClosedJobMutationGuard`.
- el borrado es soft delete.

Proxima etapa sugerida:

1. integrar `job_participants` visualmente en timeline.
2. disenar migracion controlada de `work_log_participants` hacia `employee_id` o relacion compatible.
3. permitir que un worklog herede participantes del job.
4. evaluar documentos, costos o contabilidad de empleados despues de estabilizar participantes.
