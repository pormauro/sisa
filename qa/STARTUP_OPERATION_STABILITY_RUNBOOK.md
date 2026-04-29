# Runbook manual - startup y operacion estable

## Objetivo

Validar en dispositivo real que el arranque no vuelva a disparar refreshes destructivos mientras existe una operacion activa y que los refreshes diferidos corran solo cuando el usuario ya salio del flujo critico.

## Que protege

- perdida de drafts por refresh de auth/permisos/bootstrap al volver de background
- autosync de jobs/tracking o `sync_hint` que repinten pantallas operativas mientras el usuario esta editando
- overfetch de startup que sigue pegando despues de que la shell ya esta visible

## Como correrlo

### Precondiciones

- backend y app accesibles
- usuario con permisos para jobs, worklogs, invoices y receipts
- al menos una empresa operativa con datos basicos cargados
- acceso al panel de diagnostico startup en `sisa.ui/app/network/startup.tsx`

### Escenario 1 - edicion de job protegida

1. Abrir un trabajo existente.
2. Modificar descripcion, estado o carpeta sin guardar.
3. Mandar la app a background 10-15 segundos y volver.
4. Confirmar que los cambios siguen visibles.
5. Guardar y confirmar que el refresh diferido corre recien despues de terminar.

### Escenario 2 - worklog en curso con reconexion

1. Abrir `Nuevo worklog` o editar uno existente.
2. Cambiar fecha/hora, tipo de trabajo, descripcion y participantes.
3. Cortar y restaurar red, o disparar un `sync_hint` desde otro dispositivo.
4. Confirmar que el formulario no se resetea ni cambia de trabajo destino.
5. Guardar y confirmar que el resultado converge normalmente.

### Escenario 3 - factura en creacion con draft vivo

1. Entrar a `Nueva factura`.
2. Completar cliente, items, notas e impuestos sin guardar.
3. Volver a foreground o navegar temporalmente a Home y regresar.
4. Confirmar que el draft sigue intacto.
5. Crear la factura y verificar que el flujo posterior se mantiene estable.

### Escenario 4 - recibo en creacion con startup diferido

1. Entrar a `Nuevo recibo`.
2. Completar caja, categoria, pagador, importe y adjuntos.
3. Mientras el draft esta abierto, esperar tracking/jobs autosync o volver de background.
4. Confirmar que el formulario conserva todos los campos.

### Escenario 5 - arranque frio sin operacion activa

1. Cerrar totalmente la app.
2. Abrirla con sesion valida y medir tiempo a shell.
3. Revisar `Diagnóstico startup` y copiar JSON si hace falta evidencia.
4. Confirmar que `/profiles` no se pide durante el arranque salvo que luego se abra una pantalla que lo necesite.

## Resultado esperado

- ningun formulario pierde cambios locales por refresh global
- `isReady` no vuelve a `false` una vez visible la shell
- los refreshes diferidos se ejecutan al terminar la operacion, no en el medio
- el arranque no dispara fetches innecesarios de perfiles ni debug runtime ruidoso

## Evidencia minima

- screen o video antes/despues de background durante una edicion
- export JSON de `Diagnóstico startup`
- si hubo hint/sync, nota de hora aproximada y dispositivo origen

## Puntos ciegos conocidos

- este runbook no reemplaza una corrida multi-dispositivo completa
- no detecta micro-rerenders invisibles si no terminan afectando el draft del usuario
