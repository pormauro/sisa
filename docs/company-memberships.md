# Membresias de Empresas

## Onboarding

- `GET /companies` y `GET /companies/search`: cualquier usuario autenticado no bloqueado puede listar o buscar empresas existentes. La respuesta publica solo expone datos suficientes para identificar la empresa: `id`, `razon_social`/`business_name`, `nombre_fantasia`, `nro_doc`/`tax_id`, `profile_file_id` y `activo`.
- `POST /companies/{company_id}/memberships`: cualquier usuario autenticado no bloqueado puede solicitar pertenecer a una empresa existente. La solicitud crea o reabre una membresia del usuario autenticado en estado `pending`; no aprueba automaticamente.

## Acciones Protegidas

- `POST /companies`: requiere permiso `createCompany`.
- `PUT /companies/{id}`, `DELETE /companies/{id}` y `GET /companies/{id}/history`: mantienen permisos administrativos de empresa.
- `GET /companies/{company_id}/memberships`: requiere permiso para listar miembros.
- `POST /companies/{company_id}/memberships/invite`: requiere permisos/rol administrativo para invitar miembros.
- Aprobar, rechazar, remover, suspender o cancelar invitaciones de membresias sigue reservado a owners/admins o permisos especificos segun la ruta.
