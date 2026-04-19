# Checklist de Regresion

## Gate de release - minimo exigible

### 1. Sesion y scope

- login funciona y la restauracion de sesion no deja loops ni shell en blanco
- si el usuario ya inicio sesion una vez y no hizo logout, reabrir sin red sigue dejando la app operable en modo offline
- la empresa seleccionada permanece estable despues de bootstrap
- membresias y permisos coinciden con la empresa activa

### 2. Dataset central de campo

- clients cargan dentro del scope correcto de empresa
- folders preservan jerarquia y parentage valido
- statuses son visibles y utilizables por los formularios operativos
- jobs pueden crearse, editarse y eliminarse sin dejar hijos huerfanos
- job items respetan el parent `job` requerido y las reglas del arbol de folders
- worklogs mantienen participantes validos y no pierden metadata despues de sync
- appointments preservan participantes y `visited_at`
- attachments pueden agregarse, sincronizarse, abrirse y eliminarse sin reaparecer

### 3. Offline-first y sync

- una escritura offline converge despues de reconectar
- un registro eliminado no revive despues de pull o bootstrap
- `verify/reconcile` no muestran drift inexplicable para entidades tocadas
- un segundo dispositivo recibe el cambio y el dispositivo origen no recibe ecos incorrectos
- se sigue forzando la semantica `delete + create` o `detach + attach` donde corresponda

### 4. Referencias de soporte

- providers, categories, products/services, tariffs, payment templates y cash boxes cargan para la empresa activa
- los updates de referencias se vuelven visibles sin requerir reinicio total de la app

### 5. Comandos de validacion

- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1`
- si algun paso falla, clasificarlo como:
  - deuda previa de baseline,
  - limitacion conocida aceptada,
  - regresion nueva que bloquea el avance

## Escenarios manuales multi-dispositivo

- Runbook fuente: `qa/MULTI_DEVICE_RUNBOOK.md`
- Plantilla de evidencia: `qa/MULTI_DEVICE_EVIDENCE_TEMPLATE.md`

### Escenario A - create offline y convergencia posterior

1. El dispositivo A pasa a offline.
2. Crear o editar una entidad Tier A.
3. Reconectar el dispositivo A y correr sync.
4. Confirmar que el dispositivo B recibe el estado convergente.

### Escenario B - propagacion de delete

1. Eliminar una entidad Tier A o un attachment en el dispositivo A.
2. Hacer pull o esperar el hint en el dispositivo B.
3. Confirmar que el registro no reaparece despues de refresh, pull o bootstrap.

### Escenario C - dependencia de orden

1. Crear registros dependientes en un orden realista.
2. Confirmar que el servidor rechaza relaciones invalidas de parent/scope.
3. Confirmar que el cliente termina reflejando el estado canonico.

### Escenario D - bootstrap limpio

1. Partir de un dispositivo nuevo o de un store local limpio.
2. Ejecutar bootstrap de referencias y datos operativos.
3. Confirmar que el dataset Tier A alcanza para operar en sitio.

## Evidencia a capturar

- comandos ejecutados
- UUIDs de entidades afectadas, si existen
- dispositivo/empresa usados
- si el resultado provino de bootstrap, pull o CRUD directo
- screenshots/logs/exports de base cuando el problema esta relacionado con sync
