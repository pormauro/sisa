# Runbook manual - worklog Android fecha y hora

## Objetivo

Validar en Android que la fecha y la hora del worklog no vuelvan al valor anterior cuando el usuario entra rapido al flujo, abre el picker y la pantalla sigue recibiendo cargas de soporte o refreshes diferidos.

## Que protege

- remounts o rerenders que pisen el valor visible del picker antes de guardar
- regresiones entre el flujo modal de `sisa.ui/app/jobs/worklogs.tsx` y la pantalla dedicada `sisa.ui/app/jobs/worklog-form.tsx`
- cambios futuros que reintroduzcan el picker declarativo de Android o vuelvan a cargar soporte innecesario en alta nueva

## Como correrlo

### Precondiciones

- dispositivo Android fisico preferido; emulador solo como fallback
- sesion valida y empresa operativa con al menos un job donde crear o editar worklogs
- build con logs accesibles si hace falta revisar `JobWorkLogsScreen.openCreate:*`
- si se quiere ampliar la ventana del bug, usar red lenta o inestable

### Escenario 1 - alta nueva desde worklogs con arranque frio

1. Cerrar totalmente la app.
2. Abrir la app con sesion valida.
3. Entrar a `jobs/worklogs` lo mas rapido posible.
4. Tocar `Nuevo worklog` apenas el flujo lo permita.
5. Abrir el picker de fecha.
6. Cambiar la fecha y dejar el picker abierto 2-5 segundos.
7. Confirmar la seleccion.
8. Repetir lo mismo con el picker de hora de inicio y con el de hora de fin.

### Escenario 2 - edicion rapida de worklog existente

1. Abrir un job con worklogs existentes.
2. Entrar al editor de un worklog apenas carga la pantalla.
3. Cambiar fecha y hora manteniendo cada picker abierto unos segundos.
4. Confirmar que el valor visible no vuelve al anterior al cerrar el picker.
5. Guardar y confirmar que el valor persistido coincide con el elegido.

### Escenario 3 - cargas de soporte durante el picker

1. Abrir `Nuevo worklog` en Android.
2. Con red limitada o reconexion en curso, abrir fecha u hora.
3. Mantener el picker abierto mientras terminan `loadTariffs()`, `loadCompanyUsers()` o `ensureDeviceId()`.
4. Confirmar que la seleccion final sigue siendo la elegida por el usuario.

### Escenario 4 - ruta dedicada `worklog-form`

1. Entrar al flujo que navega a `sisa.ui/app/jobs/worklog-form.tsx`.
2. Repetir los mismos pasos de fecha/hora del escenario 1.
3. Confirmar que en alta nueva no aparecen cargas auxiliares de edicion que interfieran con la seleccion.

## Resultado esperado

- el picker mantiene la seleccion hecha por el usuario y no vuelve visualmente al valor anterior
- el campo visible en el formulario coincide con lo elegido al cerrar el picker
- al guardar, el worklog conserva exactamente la fecha/hora seleccionadas
- la experiencia se mantiene estable tanto en modal como en pantalla dedicada

## Evidencia minima

- video corto o capturas antes y despues de confirmar el picker
- nota de si fue flujo modal o pantalla dedicada
- hora aproximada del intento y condicion de red usada
- si fallo, valor previo, valor elegido y valor final observado

## Criterio de falla

- el picker vuelve al valor anterior mientras esta abierto o justo al cerrarse
- el formulario muestra un valor distinto al elegido
- el valor guardado difiere del valor confirmado por el usuario

## Puntos ciegos conocidos

- este runbook no reemplaza una automatizacion E2E real con delay artificial de red
- si el problema reaparece solo con un vendor/dispositivo especifico, puede requerir evidencia adicional de logs o video
