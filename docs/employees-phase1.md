# Employees phase 1

`users` representa cuentas de acceso al sistema. `employees` representa personas operativas o de la empresa.

En esta primera etapa:

- `employees.user_id` es nullable para permitir empleados sin cuenta de acceso.
- si `user_id` se informa, la API valida que el usuario sea miembro aprobado de la misma empresa.
- la app movil (`sisa.ui`) todavia no consume empleados.
- no se implementan documentos, pagos, participantes, liquidaciones, RRHH completo ni dashboards.

API inicial:

- `GET /employees`
- `GET /employees/{id}`
- `POST /employees`
- `PUT /employees/{id}`
- `DELETE /employees/{id}`

Web inicial:

- `sisa.web` expone `/employees` con listado, alta, edicion y archivado.

Proxima etapa sugerida:

1. `job_participants`
2. `worklog_participants`
3. `employee_documents`
4. integracion con timeline/worklogs
