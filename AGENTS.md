# AGENTS

## Topologia del workspace

- Workspace raiz: `C:\Users\Mauri\Documents\GitHub\sisas`
- Esta raiz no es un repositorio Git unico para toda la solucion.
- `sisa.api/` y `sisa.ui/` son proyectos separados con sus propios `.git`.
- La documentacion QA compartida de todo el workspace vive en la raiz, salvo que un documento sea claramente especifico de un proyecto.

## Mision para futuros agentes

- Tratar QA como un sistema transversal entre proyectos, no como tests aislados.
- Priorizar los datos y flujos necesarios para operar en escenarios de trabajo de campo con senal inestable.
- No optimizar por cobertura cosmetica. Preferir pocos controles confiables antes que muchos superficiales.
- Mantener sync como capacidad generica: `jobs` es solo uno de los consumidores del modelo de sincronizacion.
- Preservar la arquitectura existente salvo que un cambio minimo sea necesario para volver ejecutable el QA.

## Fuente de verdad

- Plan: `QA_ROADMAP.md`
- Registro de sesion e hitos: `QA_STATUS.md`
- Referencias existentes de sync/dominio:
  - `sisa.api/docs/sync-propagation-matrix.md`
  - `sisa.api/docs/sync-references-qa-guide.md`
  - `sisa.ui/docs/architecture/sync_propagation_matrix.md`
  - `sisa.ui/docs/architecture/devices-sync-and-offline-first-standard.md`

## Reglas operativas

- Actualizar `QA_STATUS.md` cada vez que se completa, bloquea o avanza parcialmente un hito.
- Hacer discovery antes de editar en amplitud.
- Trabajar en hitos pequenos y validar despues de cada uno.
- No avanzar al siguiente hito si se introdujo una falla nueva sin documentarla y sin intentar una correccion razonable.
- Si un bloqueo ya existia, registrarlo como deuda de baseline en vez de ocultarlo.
- No mover archivos no relacionados ni refactorizar fuera del hito QA activo.

## Prioridades de QA

1. Baseline de sync operable en campo: usuarios, membresias, permisos, clientes, folders, estados, jobs, job items, worklogs, appointments y adjuntos.
2. Integridad de datos: relaciones obligatorias, propagacion de deletes, prevencion de huerfanos, idempotencia y metadata de version/origen.
3. Comportamiento offline-first: persistencia local, cola, bootstrap, pull, reconcile y reconexion.
4. Convergencia multi-dispositivo: identidad de dispositivo, propagacion de hints, manejo de conflictos y no reaparicion de datos eliminados.
5. Datos de soporte operativo necesarios en sitio: providers, categories, products/services, tariffs, cash boxes, payments, receipts e invoices.

## Comandos de validacion

### Backend (`sisa.api`)

- Suite completa: `vendor/bin/phpunit`
- Ejemplos de corridas focalizadas:
  - `vendor/bin/phpunit tests/Controllers/AppointmentsControllerCrudOfflineFirstTest.php`
  - `vendor/bin/phpunit tests/Controllers/SyncOperationsControllerBootstrapReferencesTest.php`

### Frontend (`sisa.ui`)

- Lint: `npm run lint`
- Guardia de cache: `npm run check:cache`
- Smoke de sync: `npm run check:sync-smoke`

### Helper del workspace

- Entrada compartida desde raiz: `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1`

## Guia de edicion

- Si agregas o cambias automatizacion de QA, documentar:
  - que protege,
  - que riesgo cubre,
  - como se corre,
  - que puntos ciegos conocidos mantiene.
- Preferir PHPUnit en backend para contratos del servidor y reglas de dominio.
- Preferir checks livianos en cliente para storage, mapeos, hooks de sync y smokes antes de incorporar E2E pesados.
- Para flujos multi-dispositivo que todavia no se puedan automatizar de forma confiable, dejar un runbook manual estricto en vez de simular cobertura.

## Notas actuales del baseline QA

- `powershell -ExecutionPolicy Bypass -File .\qa\run-baseline.ps1` es la entrada compartida y actualmente pasa.
- `sisa.api` PHPUnit sigue emitiendo una linea de error de conexion a base de datos aunque la suite termine bien; tratarlo como ruido de salida o deuda oculta de setup hasta diagnosticarlo.
- La guardia de cache en `sisa.ui` ahora acepta persistencia basada en SQLite/repositorios y excepciones explicitas fuera del flujo critico de campo; cualquier nueva excepcion debe justificarse en review y reflejarse en `QA_STATUS.md`.
