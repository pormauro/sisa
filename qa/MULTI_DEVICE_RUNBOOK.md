# Runbook Multi-Dispositivo

## Objetivo

Estandarizar pruebas manuales para convergencia multi-dispositivo, no reaparicion de datos eliminados y operacion offline-first en condiciones de campo.

Este runbook es obligatorio mientras estos escenarios no tengan automatizacion confiable de punta a punta.

## Preparacion

### Dispositivos

- Dispositivo A: origen del cambio
- Dispositivo B: receptor secundario
- Opcional: Web/Admin para inspeccion de estado canonico del servidor

### Datos minimos de prueba

- una empresa activa con permisos suficientes
- un client de prueba
- una estructura de folders con al menos una carpeta padre y una subcarpeta
- al menos un status operativo valido
- un job de prueba con uno o mas job items
- al menos un worklog y un attachment reutilizable

### Evidencia obligatoria por corrida

- fecha y operador
- empresa usada
- `device_id` de A y B si es visible
- UUIDs afectados
- comandos corridos (`qa/run-baseline.ps1`, smokes o capturas de sync state)
- resultado esperado vs resultado real
- evidencia adjunta: screenshots, logs o export de SQLite cuando aplique
- usar `qa/MULTI_DEVICE_EVIDENCE_TEMPLATE.md` como formato base de registro

## Regla de aprobacion

- el escenario pasa solo si el estado final converge en A, B y servidor
- si aparece un dato eliminado, se considera falla critica
- si un conflicto queda silencioso sin drift visible ni marca local, se considera falla critica

## Escenario 1 - create offline y convergencia

### Protege

- persistencia local
- cola offline
- convergencia posterior
- propagacion a segundo dispositivo

### Pasos

1. En A, verificar que el sync este estable y anotar checkpoint actual.
2. Poner A en offline real.
3. Crear un registro Tier A, preferentemente un `job`, `job_item` o `worklog`.
4. Confirmar que el dato queda visible en A sin conectividad.
5. Volver A a online y correr sync manual o esperar sincronizacion.
6. En servidor o web, confirmar que el registro existe con `version` y `source_device_id` esperados.
7. En B, ejecutar pull/bootstrap si hace falta.
8. Confirmar que el registro aparece una sola vez y con los mismos datos clave.

### Resultado esperado

- A conserva el dato durante offline.
- el servidor recibe una unica version canonica.
- B converge sin duplicados.

## Escenario 2 - delete propagation sin reaparicion

### Protege

- soft delete
- tombstones
- bootstrap/pull/events/reconcile
- no resurreccion de referencias y adjuntos

### Pasos

1. Elegir una entidad existente visible en A y B.
2. Eliminarla en A.
3. Confirmar en A que ya no aparece como activa.
4. Ejecutar sync desde A.
5. En B, correr pull o bootstrap.
6. Confirmar que la entidad no aparece como activa.
7. Forzar refresh adicional en B.
8. Repetir con bootstrap limpio si el tipo de dato lo permite.

### Variantes obligatorias

- referencia: `status`, `provider`, `client` o `folder`
- attachment: `job_file`, `job_item_file` o `worklog_file`

### Resultado esperado

- el dato eliminado no reaparece en A, B ni bootstrap limpio.
- si se inspecciona estado interno, existe marca `deleted_at` o equivalente canonicamente sincronizado.

## Escenario 3 - conflicto visible y resoluble

### Protege

- drift detectable
- reconcile
- marca local de conflicto
- resolucion consciente

### Pasos

1. Elegir una entidad editable visible en A y B.
2. Desconectar A y editar el registro.
3. Desde B o servidor, editar el mismo registro con otro valor.
4. Reconectar A y correr sync.
5. Confirmar que el sistema detecta conflicto o drift.
6. Verificar en cliente local que exista marca de conflicto visible o estado equivalente.
7. Resolver usando el flujo previsto (`aceptar servidor`, `descartar local`, etc.).
8. Confirmar convergencia final en A, B y servidor.

### Resultado esperado

- el conflicto no pasa silenciosamente.
- queda evidencia local del drift.
- despues de resolver, el estado final converge y la marca de conflicto se limpia.

## Escenario 4 - orden de dependencias

### Protege

- relaciones obligatorias
- reglas de folder/job/job_item
- rechazo de operaciones invalidas

### Pasos

1. Intentar crear o editar un dato dependiente con una relacion invalida.
2. Verificar rechazo canonico del servidor o del flujo de sync.
3. Corregir la dependencia y repetir la operacion.
4. Confirmar que la version valida converge.

### Casos minimos

- `job_item.folder_id` fuera del arbol permitido
- `job_item_id` fuera del `job` del `worklog`
- reasignacion implicita de `file_attachments`

## Escenario 5 - bootstrap limpio de dispositivo nuevo

### Protege

- reconstruccion desde snapshot
- referencias eliminadas con tombstone
- dataset minimo operable en campo

### Pasos

1. Limpiar el store local del dispositivo B o usar un dispositivo nuevo.
2. Ejecutar bootstrap de referencias y datos operativos.
3. Confirmar que el dataset Tier A queda disponible.
4. Verificar especificamente que referencias o attachments eliminados no reaparecen.
5. Correr un pull incremental posterior y confirmar que no revive informacion borrada.

## Escenario 6 - reapertura offline con sesion previa

### Protege

- continuidad de auth/sesion
- shell-first sin bloqueo por red
- operacion offline despues de login previo
- persistencia local de datos Tier A hasta reconexion

### Pasos

1. En A, iniciar sesion online con usuario y password validos.
2. Confirmar que el bootstrap critico termina y que el dataset Tier A minimo queda visible.
3. Cerrar completamente la app sin hacer logout manual.
4. Cortar conectividad real del dispositivo A.
5. Reabrir la app en A.
6. Confirmar que entra a la shell autenticada y no redirige a login.
7. Abrir al menos una vista operativa (`statuses`, `clients`, `jobs` o equivalente) y confirmar que sigue leyendo cache local.
8. Crear o editar un dato offline si el flujo lo permite y confirmar que queda visible/localmente persistido.
9. Volver a online, correr sync y confirmar que el cambio converge o, si no hubo escritura, que la sesion sigue estable sin logout espurio.

### Resultado esperado

- A reabre autenticado aunque no tenga red.
- la app queda marcada como offline, pero operable.
- la shell no vuelve a login salvo logout manual o limpieza explicita de credenciales.
- al reconectar, la sesion puede retomar sync sin inconsistencias nuevas.

## Checklist de cierre por corrida

- A converge
- B converge
- servidor converge
- no hubo reaparicion
- no hubo duplicados
- `verify/reconcile` no dejan drift inexplicable
- evidencia archivada

## Criterio de archivo

- cada corrida debe quedar registrada copiando `qa/MULTI_DEVICE_EVIDENCE_TEMPLATE.md`
- nombre sugerido: `qa/evidence/YYYY-MM-DD-escenario-dispositivo.md`
- si la corrida falla, el registro debe incluir hipotesis y siguiente accion concreta
- la carpeta operativa es `qa/evidence/` y ya existe un ejemplo base en `qa/evidence/2026-04-13-escenario-base.md`

## Puntos ciegos actuales

- no hay E2E automatizado real entre dos dispositivos fisicos
- parte de la verificacion sigue dependiendo de inspeccion manual de UI/cache/store
- el baseline automatizado valida contratos y smokes, pero no reemplaza estas corridas manuales
