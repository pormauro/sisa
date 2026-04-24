# Company Status Role Mapping (Future)

## Objetivo

Desacoplar los comportamientos especiales de `jobs.statuses` de los nombres literales de cada estado.

Hoy varias reglas del sistema infieren semantica a partir del `label` del estado (`Facturado`, `Finalizado`, `Cancelado`, etc.). Eso funciona mientras el naming real siga una convencion reconocible, pero deja una deuda importante: cada empresa puede personalizar sus estados y renombrarlos, por lo que los comportamientos del sistema no deberian depender del texto visible.

La propuesta futura es mover estas semanticas a una configuracion explicita por empresa dentro de `company`/`company settings`.

## Problema actual

- una empresa puede renombrar estados y romper filtros o automatismos sin tocar codigo
- distintos modulos usan heuristicas por texto para decidir si un job esta facturado, finalizado o cancelado
- al existir tabla de estados personalizada por empresa, la semantica real deberia vivir en configuracion de la empresa, no en keywords hardcodeadas

## Semanticas especiales candidatas

Cada empresa deberia poder mapear desde su tabla de estados personalizada hacia roles funcionales del sistema.

Roles minimos propuestos para `jobs`:

- `billed_status_ids`
- `completed_status_ids`
- `cancelled_status_ids`

Roles posibles a futuro si aparecen reglas nuevas:

- `pending_status_ids`
- `in_progress_status_ids`
- `schedulable_status_ids`
- `archived_status_ids`
- `blocked_status_ids`

Notas:

- conviene permitir multiples ids por rol, no uno solo
- conviene permitir `null`/vacio para empresas que aun no configuren el mapeo
- la UI de configuracion deberia mostrar los estados reales de la empresa y permitir asignarles estos roles funcionales

## Ubicacion futura sugerida

Agregar estos mapeos a la configuracion de la empresa, no a la tabla `statuses` directamente.

Ejemplo conceptual:

```json
{
  "job_status_roles": {
    "billed_status_ids": [11],
    "completed_status_ids": [8, 9],
    "cancelled_status_ids": [12]
  }
}
```

Opciones de persistencia posibles:

- columna JSON en `companies`
- tabla `company_settings`
- tabla dedicada `company_job_status_roles`

La decision de schema puede postergarse; lo importante es fijar la direccion funcional.

## Superficies actuales que dependen de semantica por estado

### Facturado

Hoy se usa heuristica por label en:

- `sisa.ui/utils/statuses.ts`
- `sisa.ui/app/clients/finalizedJobs.tsx`
- `sisa.ui/hooks/useClientFinalizedJobTotals.ts`
- `sisa.ui/app/invoices/create.tsx`
- `sisa.ui/app/tracking/nearby-clients.tsx`
- `sisa.ui/app/jobs/index.tsx`
- `sisa.api/src/Controllers/InvoicesController.php`

Comportamientos asociados:

- excluir o incluir trabajos facturados en listados
- mover jobs a `Facturado` al crear factura
- detectar jobs ya facturados para no ofrecer ciertas acciones

### Finalizado

Hoy se usa o se necesita usar para:

- `sisa.ui/app/clients/finalizedJobs.tsx`
- `sisa.ui/hooks/useClientFinalizedJobTotals.ts`
- selector de trabajo en citas (`sisa.ui/app/appointments/create.tsx`, `sisa.ui/app/appointments/[id].tsx`)

Comportamientos asociados:

- considerar jobs cerrados en la pantalla previa a facturacion
- excluir jobs finalizados del selector de citas nuevas

### Cancelado

Hoy se usa o se necesita usar para:

- selector de trabajo en citas (`sisa.ui/app/appointments/create.tsx`, `sisa.ui/app/appointments/[id].tsx`)
- cualquier flujo futuro donde un job cancelado no deba seguir operativo

Comportamientos asociados:

- excluir jobs cancelados de acciones operativas nuevas

## Cambio funcional introducido en esta sesion

Mientras el sistema siga usando semantica por label:

- el selector de trabajo al crear/editar citas ahora excluye jobs `cancelados` y `finalizados`
- el filtro sigue siendo heuristico por texto, por lo que debe migrar en el futuro al mapping por empresa descrito en este documento

## Plan de migracion futura recomendado

1. Definir storage oficial para `job_status_roles` en company settings.
2. Exponer endpoint/backend para leer y actualizar esos mappings por empresa.
3. Hidratar esa configuracion en cliente junto con la empresa seleccionada.
4. Reemplazar helpers por texto (`isStatusFacturado`, `isStatusFinalizado`, `isStatusCancelado`) por helpers basados en ids configurados.
5. Mantener fallback por texto solo como compatibilidad temporal y removerlo cuando todas las empresas esten configuradas.
6. Agregar QA especifico de regression para asegurar que los comportamientos especiales siguen funcionando aunque la empresa renombre sus estados.

## QA futuro sugerido

Casos minimos a cubrir cuando exista esta configuracion:

- una empresa renombra `Facturado` y el sistema sigue identificando el estado correcto
- una empresa define dos estados como `completed` y ambos gatillan los filtros esperados
- una empresa cambia el estado `cancelled` y el selector de citas deja de ofrecer esos trabajos sin tocar codigo
- la creacion de factura sigue marcando jobs al estado configurado como `billed`
- listados, facturacion, citas y filtros usan la misma fuente de verdad
