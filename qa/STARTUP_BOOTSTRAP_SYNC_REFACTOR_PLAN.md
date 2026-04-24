# Startup, Bootstrap and Sync Refactor Plan

## Objetivo

Reducir el costo del arranque inicial de la app, eliminar requests redundantes y asegurar que `sync v3` solo corra cuando exista contexto valido de empresa y dispositivo.

La meta funcional es pasar de un inicio con multiples requests paralelos y parcialmente desacoplados a un flujo orquestado:

```text
auth
  -> device_uid
  -> selected company
  -> bootstrap cache
  -> /bootstrap
  -> apply startup state
  -> sync v3
  -> lazy loads / push hints
```

## Problemas detectados hoy

### 1. Arranque fragmentado

Hoy el inicio mezcla varios loaders independientes desde distintos providers/contextos. Eso dispara requests en paralelo sin una orquestacion unica.

Superficies observadas:

- `sisa.ui/contexts/BootstrapContext.tsx`
- `sisa.ui/contexts/CompaniesContext.tsx`
- `sisa.ui/contexts/TrackingContext.tsx`
- `sisa.ui/contexts/AppUpdatesContext.tsx`
- `sisa.ui/src/modules/jobs/presentation/components/JobsSyncAutoRunner.tsx`

### 2. Sync sin contexto de empresa garantizado

Se observo `sync/v3/events` ejecutandose con `company_id = null`. Aun si parte del modelo de sync tolera scope global, para el baseline actual de jobs esto agrega ruido, complica debugging y mezcla estados de arranque con estados de operacion reales.

### 3. Tracking redundante

Actualmente se consultan ambos:

- `/tracking/policy`
- `/tracking/status`

`/tracking/status` ya trae la policy en la respuesta, por lo que mantener ambos en arranque introduce duplicacion de IO.

### 4. Overfetch en empresas

`CompaniesContext.loadCompanies()` no solo pide `/companies`; tambien levanta en paralelo:

- `/company-addresses`
- `/contacts`
- `/company-contacts`
- `/company-channels`
- `/contact-channels`

Esto aumenta costo de red y de parseo aunque muchas veces esas colecciones no sean necesarias para pintar el shell inicial.

### 5. App updates compite con el arranque

`AppUpdatesContext` consulta `/app_updates/latest` en startup. Conceptualmente es correcto, pero no deberia competir con el camino critico del arranque ni gatillar UX de error durante bootstrap.

## Principios para la refactorizacion

### A. Contexto obligatorio antes de sync

No debe existir request critico de sync antes de resolver:

- `device_uid`
- `selected_company_id`

Regla deseada:

```text
si no hay company seleccionada -> no correr pull sync automatico de jobs
```

### B. Bootstrap unico y chico

El endpoint futuro `/bootstrap` debe traer solo estado minimo para operar, no datasets pesados completos.

### C. Lazy loading real

Colecciones no criticas para shell inicial deben moverse a carga diferida al entrar a la seccion correspondiente.

### D. Push como hint, no como payload

Las notificaciones push deben avisar que hay cambios y disparar sync; no deben transportar entidades completas.

## Diseño futuro del endpoint `/bootstrap`

### Request

Opcion recomendada:

```http
GET /bootstrap
Authorization: Bearer ...
X-Device-UID: device-xxx
X-Company-Id: 45
```

### Response objetivo

```json
{
  "server_time": "2026-04-24T18:40:00Z",
  "user": {
    "id": 1,
    "username": "pormauro"
  },
  "company": {
    "id": 45,
    "name": "Empresa X"
  },
  "device": {
    "device_uid": "device-xxx",
    "sync": {
      "jobs": {
        "after": 2105,
        "has_more": false
      },
      "tracking": {
        "last_server_point_id": 13108
      }
    }
  },
  "config": {
    "tracking": {
      "enabled": true,
      "profile": "standby",
      "sample_min_seconds": 60,
      "sample_max_seconds": 600,
      "distance_filter_m": 50,
      "next_poll_after_seconds": 300
    }
  },
  "versions": {
    "bootstrap": 12,
    "permissions": 4,
    "catalogs": 9
  },
  "initial_data": {
    "clients": [],
    "folders": [],
    "statuses": [],
    "tariffs": []
  }
}
```

## Qué deberia salir del arranque actual

### Eliminar del camino critico de startup

- `/tracking/policy` (si `status` ya incluye policy)
- `/contacts`
- `/company-contacts`
- `/company-channels`
- `/contact-channels`
- `/invoices` completos

### Mantener solo si el shell inicial realmente lo necesita

- empresas
- permisos
- company seleccionada
- statuses/tariffs/clients/folders minimos
- tracking config minima

## Quick wins aplicables sin esperar el bootstrap completo

### 1. Bloquear auto sync sin company seleccionada

Impacto: evita `company_id = null` en el flujo automatico de jobs sync.

### 2. Dejar de pedir `/tracking/policy` en startup

Impacto: reduce una request redundante y deja `tracking/status` como fuente unica en arranque.

### 3. Mover datasets no criticos de empresa a lazy loading

Impacto: reduce IO del arranque, pero requiere que las pantallas de detalle sepan hidratar addresses/contacts/channels cuando realmente se abren.

### 4. Desacoplar app updates del camino critico

Impacto: evita que la comprobacion de updates compita con el render inicial o genere alertas de ruido al abrir la app.

## Plan incremental sugerido

### Fase 1 - Higiene de startup

- bloquear sync automatico sin company valida
- eliminar request redundante de tracking
- bajar ruido de errores en startup

### Fase 2 - Bootstrap liviano

- crear `/bootstrap`
- consolidar company/user/device/config/versiones
- hidratar cache local de bootstrap

### Fase 3 - Lazy loading de datos no criticos

- company addresses/contactos/canales
- invoices/listados pesados
- datos secundarios de tracking/reportes

### Fase 4 - Integracion completa con push + sync v3

- push manda solo `sync_hint`
- app reacciona con sync v3 post-bootstrap
- polling reducido al minimo razonable

## Dependencias relacionadas

Esta refactorizacion se conecta con otra deuda ya documentada:

- `qa/COMPANY_STATUS_ROLE_MAPPING_FUTURE.md`

Motivo: el bootstrap futuro es un buen lugar para entregar configuracion semantica por empresa, incluyendo mapeos de estados especiales (`facturado`, `finalizado`, `cancelado`, etc.).

## QA futura sugerida

- verificar que no exista `sync/v3/events` con `company_id = null`
- medir cantidad de requests en startup antes/despues
- comprobar que tracking siga operativo sin `/tracking/policy` inicial
- abrir detalle de empresa y verificar hidratacion lazy de addresses/contactos/canales
- validar que push + sync sigan convergiendo con startup liviano
