# Checklist de Transformacion de Informes y Reportes

## Objetivo

Convertir el sistema actual de PDFs/reportes en una plataforma completa de informes operativos, timeline tecnico, estado de cuenta por cliente y reportes economicos/contables por `company_id`, manteniendo el patron operativo actual:

- payload de filtros
- consolidacion de dataset
- generacion PDF
- guardado en `files`
- registro en `reports`
- trazabilidad en `reports_history`

Este checklist esta pensado para ejecutarse por etapas pequenas, dejando evidencia y QA en cada bloque.

## Estado de uso

- `[ ]` pendiente
- `[~]` en progreso
- `[x]` completado
- `[!]` bloqueado o con deuda conocida

## Referencias base

- Plan general: `QA_ROADMAP.md`
- Estado de sesion: `QA_STATUS.md`
- Contrato de regresion general: `qa/REGRESSION_CHECKLIST.md`
- Dataset operativo compartido: `qa/FIELD_DATASET_MAP.md`
- Reportes backend existentes: `sisa.api/docs/reports-table.md`
- PDF de jobs existente: `sisa.api/src/Controllers/JobReportsController.php`
- PDF de invoices existente: `sisa.api/src/Controllers/InvoicesController.php`
- PDF de payments existente: `sisa.api/src/Controllers/PaymentsController.php`
- Resumen contable existente: `sisa.api/src/Services/AccountingSummaryService.php`

---

## 0. Criterios rectores

- [ ] No romper rutas actuales ni respuesta base (`file_id`, `report_id`, `download_url`).
- [ ] Mantener la compatibilidad con consumidores existentes del backend y UI.
- [ ] Filtrar siempre por `company_id` y respetar scope del usuario.
- [ ] Separar claramente capas de:
  - recoleccion de datos
  - normalizacion
  - calculo economico
  - timeline
  - render HTML/PDF
  - persistencia del reporte
  - QA
- [ ] Evitar duplicar logica entre reportes operativos, financieros y de cuenta corriente.
- [ ] Asegurar que la metadata persistida permita regenerar el reporte en el futuro.
- [ ] Documentar toda deuda de schema legacy vs schema actual antes de profundizar el alcance.

---

## 1. Baseline y discovery tecnico

### 1.1 Inventario funcional actual

- [x] Identificar todos los endpoints PDF/report actuales.
- [x] Verificar patron comun de guardado en `files` + `reports`.
- [x] Verificar estado actual de `reports` y `reports_history`.
- [x] Identificar que reportes usan `company_id` y cuales no.
- [x] Identificar servicios ya reutilizables para resumen contable.

### 1.2 Debt log de modelo

- [ ] Documentar de forma explicita diferencias entre schema legacy y nuevo de `jobs`.
- [ ] Documentar diferencias entre schema legacy y nuevo de `job_items`.
- [ ] Documentar fuente canonica para calcular duracion/costo: job legacy vs `work_logs`.
- [ ] Documentar faltantes para vincular `payments` con `job`, `job_item`, `work_log`, `invoice_item`.
- [ ] Documentar faltantes para aging, vencimientos y estados de cuenta robustos.

### 1.3 Baseline de validacion

- [ ] Dejar listado de comandos minimos para validar cada tramo de reportes.
- [ ] Definir smoke minimo para PDF operativo.
- [ ] Definir smoke minimo para account statement.
- [ ] Definir smoke minimo para reportes economicos.

---

## 2. Contrato de payload comun de reportes

### 2.1 Payload estandar

- [~] Soportar `report_variant`.
- [~] Soportar `include_sections`.
- [~] Soportar `display_options`.
- [~] Soportar banderas auxiliares (`include_timeline`, `include_financials`, `include_attachments`, `include_account_summary`).
- [ ] Soportar `group_by` consistente entre variantes.
- [ ] Soportar `orientation`/layout cuando aplique.
- [ ] Soportar `entity_ids` explicitos cuando se necesite corte quirurgico.
- [ ] Soportar `status_ids`, `cash_box_id`, `invoice_status`, `aging_bucket` y otros filtros por variante.

### 2.2 Reglas de validacion del payload

- [ ] Validar formatos de fechas.
- [ ] Validar consistencia `start_date <= end_date`.
- [ ] Validar combinaciones incompatibles entre variante y secciones.
- [ ] Validar strings permitidos en `group_by`.
- [ ] Validar strings permitidos en `timeline_order`.
- [ ] Validar que `include_sections` solo contenga secciones soportadas.
- [ ] Validar que filtros por ids sean arrays de enteros positivos.

### 2.3 Versionado semantico del contrato

- [ ] Documentar contrato final en docs backend.
- [ ] Agregar ejemplos reales para cada variante.
- [ ] Registrar defaults por variante.
- [ ] Definir comportamiento cuando faltan secciones opcionales.

---

## 3. Persistencia y trazabilidad de reportes

### 3.1 Tabla `reports`

- [x] Persistir `company_id` en nuevas filas.
- [ ] Persistir `client_id` en metadata estandarizada.
- [ ] Persistir `report_variant` de forma estable.
- [ ] Persistir `start_date` y `end_date` a nivel metadata estandar.
- [ ] Persistir `generated_by_user_id`.
- [ ] Persistir `include_sections`.
- [ ] Persistir `display_options`.
- [ ] Persistir `group_by`.
- [ ] Persistir entidad principal (`client`, `company`, `cash_box`, `invoice`, etc.).
- [ ] Persistir ids relacionados (`job_ids`, `invoice_ids`, `payment_ids`, `receipt_ids`).
- [ ] Persistir resumen numerico clave (totales, conteos, importes visibles).
- [ ] Evaluar columna dedicada para `report_variant` si la metadata queda insuficiente para consultas.

### 3.2 Tabla `reports_history`

- [x] Persistir `company_id` en historial.
- [ ] Registrar regeneracion de reporte como evento distinto o `UPDATE` consistente.
- [ ] Registrar reemplazo de archivo generado.
- [ ] Registrar archivado/borrado logico del reporte.
- [ ] Evaluar auditoria de descarga si negocio la necesita.

### 3.3 Regeneracion y reutilizacion

- [ ] Poder reejecutar un reporte usando metadata persistida.
- [ ] Definir si regenerar crea nuevo `file_id` y nuevo `report_id`, o nueva version del mismo reporte.
- [ ] Definir criterio de deduplicacion futura por hash/firma del contenido.
- [ ] Documentar estrategia de naming de archivo.

### 3.4 Consulta operativa de reportes

- [ ] Extender filtros de `/reports` para `company_id`, `client_id`, `report_variant`, rango de fechas y entidad principal.
- [ ] Permitir ubicar rapidamente el ultimo reporte de una entidad/rango.
- [ ] Permitir listar reportes previos de un cliente para reclamos o reenvios.

---

## 4. Dataset assembler comun

### 4.1 Capa de consolidacion

- [ ] Crear/extraer un assembler comun para datasets de reportes.
- [ ] Evitar que cada controlador vuelva a consultar lo mismo por separado.
- [ ] Separar loaders por modulo: operativo, cuenta corriente, contable.
- [ ] Incluir caches locales por request para status, tariffs, folders, users, files, cash boxes.

### 4.2 Reglas de composicion

- [ ] Definir fuente canonica para participant profiles.
- [ ] Definir fuente canonica para tarifas.
- [ ] Definir fuente canonica para estados y labels.
- [ ] Definir reglas para importes manuales vs tarifas automaticas.
- [ ] Definir como tratar valores faltantes, soft deleted e historicos.

### 4.3 Performance y volumen

- [ ] Evitar N+1 en worklogs, appointments, histories y attachments.
- [ ] Definir estrategia para clientes con alto volumen.
- [ ] Definir paginacion interna o cortes logicos antes del render PDF si hiciera falta.
- [ ] Definir estrategia para adjuntos pesados y PDFs de gran tamano.

---

## 5. Informe operativo/técnico de jobs para cliente

### 5.1 Encabezado general del reporte

- [x] Fecha/hora de generacion.
- [x] Cliente.
- [x] Variante.
- [x] Company ID.
- [ ] Tipo de informe legible.
- [ ] Usuario que genero el reporte con nombre legible.
- [ ] Codigo interno del reporte reutilizable.
- [ ] Filtros aplicados visibles en el PDF.

### 5.2 Datos de empresa emisora

- [ ] Razon social.
- [ ] Nombre comercial.
- [ ] CUIT/documento.
- [ ] Direccion.
- [ ] Telefono.
- [ ] Email.
- [ ] Logo.
- [ ] Canales secundarios si aplican.

### 5.3 Datos del cliente

- [ ] `client_id`.
- [ ] Codigo interno visible.
- [ ] Razon social/nombre.
- [ ] Nombre de fantasia.
- [ ] Documento/CUIT.
- [ ] Domicilio.
- [ ] Localidad/provincia.
- [ ] Telefonos.
- [ ] Email.
- [ ] Persona de contacto.
- [ ] Observaciones.
- [ ] Condicion comercial si existe.

### 5.4 Datos de job

- [ ] `job_id`.
- [ ] UUID.
- [ ] Codigo visible si existe.
- [x] Carpeta.
- [ ] Producto/servicio si aplica.
- [x] Descripcion.
- [ ] Detalle tecnico.
- [ ] Observaciones.
- [ ] Prioridad.
- [x] Estado actual.
- [x] Fecha de creacion.
- [ ] Fecha programada.
- [x] Fecha/hora de inicio.
- [x] Fecha/hora de finalizacion.
- [x] Duracion total.
- [ ] Tecnico responsable.
- [x] Personal asignado.
- [ ] Ubicacion del trabajo.
- [ ] Visitado/no visitado.
- [ ] Resultado final.
- [ ] Conclusion tecnica.
- [ ] Firma/conformidad si en el futuro existe.
- [ ] Root causes/grupos si existen.
- [ ] Referencias cruzadas a appointments.
- [ ] Referencias cruzadas a adjuntos.

### 5.5 Datos de job items

- [x] Descripcion.
- [ ] `job_item_id`.
- [ ] Estado.
- [ ] Detalle.
- [ ] Diagnostico.
- [ ] Solucion aplicada.
- [ ] Prioridad.
- [ ] Fecha/hora creacion.
- [ ] Fecha/hora inicio.
- [ ] Fecha/hora fin.
- [ ] Responsable.
- [ ] Carpeta asociada.
- [ ] Observaciones.
- [ ] Evidencia asociada.
- [ ] Subtotal economico por item si se define.

### 5.6 Datos de worklogs

- [x] `worklog_id`.
- [x] Trabajo padre.
- [x] Item relacionado si existe.
- [x] Tipo de actividad.
- [x] Descripcion.
- [ ] Detalle tecnico ampliado.
- [x] Fecha/hora inicio.
- [x] Fecha/hora fin.
- [x] Duracion en minutos.
- [x] Duracion formateada.
- [ ] Facturable/no facturable.
- [ ] Visible/no visible al cliente.
- [ ] Usuario creador.
- [ ] Observaciones.
- [ ] Ubicacion si existe.
- [ ] Evidencia relacionada.

### 5.7 Participantes del worklog

- [x] `user_id`.
- [x] Nombre.
- [ ] Rol.
- [x] Tarifa aplicada.
- [x] Origen de tarifa.
- [x] Importe hora.
- [x] Tiempo trabajado.
- [x] Subtotal individual.

### 5.8 Calculo economico de mano de obra

- [x] `participante x tarifa x tiempo = subtotal`.
- [x] Subtotal por participante.
- [x] Subtotal por worklog.
- [x] Subtotal por job.
- [ ] Subtotal por item.
- [x] Total mano de obra.
- [x] Total gastos trasladables.
- [x] Total general.
- [ ] Regla explicita para override manual.
- [ ] Regla explicita cuando falta tarifa.
- [ ] Regla explicita cuando `duration_minutes = 0`.

### 5.9 Tarifas

- [x] `tariff_id`.
- [x] Nombre.
- [ ] Tipo.
- [x] Importe.
- [ ] Moneda configurable.
- [ ] Vigencia si existe.
- [x] Aplicacion automatica/manual.

### 5.10 Gastos a cargo del cliente desde `payments`

- [x] Integrar `payments` con `charge_client = true` dentro del rango del reporte.
- [x] Mostrar `payment_id`.
- [x] Fecha.
- [ ] Concepto.
- [x] Descripcion.
- [x] Categoria.
- [x] Caja.
- [x] Importe.
- [ ] Moneda.
- [ ] Metodo de pago.
- [ ] Referencia/comprobante.
- [ ] Observaciones.
- [x] Marca de gasto trasladable.
- [x] Cliente relacionado.
- [ ] Vinculo con `job`/`worklog` si luego existe.
- [x] Subtotal de gastos trasladables.

### 5.11 Adjuntos y evidencias

- [x] Adjuntos de job en el PDF operativo.
- [ ] Adjuntos de item.
- [ ] Adjuntos de worklog.
- [ ] Fotos antes/despues.
- [ ] Modo full embed vs modo listado liviano.
- [ ] Nombre, tipo, fecha y origen de cada adjunto cuando no se incrusta.

### 5.12 Appointments

- [x] `appointment_id`.
- [x] Fecha/hora.
- [ ] Estado semantico legible.
- [ ] Programado/visitado/reprogramado/cancelado.
- [x] Participantes.
- [x] Observaciones.
- [ ] Evidencia.
- [x] Relacion con job.
- [ ] Relacion con cliente si el reporte es multi-job.

### 5.13 Resumen operativo final

- [x] Cantidad de trabajos.
- [x] Cantidad de items.
- [x] Cantidad de worklogs.
- [x] Total de horas.
- [x] Total mano de obra.
- [x] Total gastos cliente.
- [x] Total general.
- [ ] Estado general consolidado.
- [ ] Observaciones finales del reporte.

---

## 6. Timeline tecnico tipo mensajeria/logistica

### 6.1 Modelo de timeline

- [x] Definir timeline unificado por eventos de varias entidades.
- [ ] Definir DTO estable de evento de timeline.
- [ ] Definir prioridad/orden cuando varios eventos comparten mismo timestamp.
- [ ] Definir timezone y formato unico de timestamp.

### 6.2 Eventos de jobs

- [x] Trabajo creado.
- [x] Trabajo actualizado desde history.
- [x] Cambio de estado.
- [ ] Asignacion de tecnico.
- [x] Inicio.
- [x] Finalizacion.
- [ ] Cierre formal.

### 6.3 Eventos de items

- [x] Item creado.
- [x] Item actualizado desde history.
- [x] Cambio de estado.
- [x] Inicio.
- [x] Finalizacion.

### 6.4 Eventos de worklogs

- [x] Worklog iniciado.
- [x] Worklog finalizado.
- [x] Historial de worklog.
- [ ] Modificacion de duracion como evento legible.
- [ ] Modificacion de tarifa como evento legible.
- [ ] Participantes agregados/removidos.

### 6.5 Eventos de appointments

- [x] Cita programada.
- [x] Cita visitada.
- [x] Historial de cita.
- [ ] Reprogramacion legible.
- [ ] Cancelacion legible.

### 6.6 Eventos de adjuntos

- [ ] Archivo adjuntado.
- [ ] Archivo eliminado.
- [ ] Evidencia agregada.

### 6.7 Eventos financieros

- [x] Gasto cargado al cliente.
- [x] Historial de pago relevante.
- [ ] Factura emitida.
- [ ] Recibo generado.
- [ ] Pago aplicado a factura.
- [ ] Saldo actualizado.

### 6.8 Render del timeline

- [x] Tabla cronologica basica.
- [ ] Variante vertical tipo tracking.
- [ ] Variante agrupada por `job`.
- [ ] Variante agrupada por fecha.
- [ ] Variante ascendente/descendente configurable.
- [ ] Ocultar ruido tecnico cuando se quiera vista para cliente final.

---

## 7. Estado de cuenta por cliente

### 7.1 Variante `client_account_statement`

- [ ] Crear/terminar variante dedicada en el backend.
- [ ] Reusar patron actual de PDF + `files` + `reports`.
- [ ] Definir payload estandar para cuenta corriente.

### 7.2 Dataset de facturas

- [ ] `invoice_id`.
- [ ] Numero.
- [ ] Fecha emision.
- [ ] Fecha vencimiento.
- [ ] Estado.
- [ ] Subtotal.
- [ ] Impuestos.
- [ ] Total.
- [ ] Saldo pendiente.
- [ ] Saldo cancelado.
- [ ] Moneda.

### 7.3 Dataset de recibos

- [ ] `receipt_id`.
- [ ] Numero/codigo visible si existe.
- [ ] Fecha.
- [ ] Total.
- [ ] Estado.
- [ ] Comprobantes vinculados.

### 7.4 Dataset de pagos y cargos

- [ ] `payment_id`.
- [ ] Fecha.
- [ ] Concepto.
- [ ] Importe.
- [ ] Caja.
- [ ] Medio de pago.
- [ ] Observaciones.
- [ ] Distinguir pago normal vs gasto trasladable.

### 7.5 Aplicaciones factura-recibo

- [ ] Factura.
- [ ] Recibo.
- [ ] Importe aplicado.
- [ ] Fecha de aplicacion.
- [ ] Saldo remanente.

### 7.6 Otros movimientos

- [ ] Notas de credito si existen.
- [ ] Notas de debito si existen.
- [ ] Ajustes manuales si existen.
- [ ] Saldos de arrastre si existen.

### 7.7 Resumen de cuenta

- [ ] Total facturado.
- [ ] Total cobrado.
- [ ] Total pendiente.
- [ ] Total vencido.
- [ ] Total por vencer.
- [ ] Total gastos trasladables.
- [ ] Saldo neto del cliente.

### 7.8 Aging

- [ ] Al dia.
- [ ] Vencido 1-30.
- [ ] Vencido 31-60.
- [ ] Vencido 61-90.
- [ ] Vencido +90.
- [ ] Regla unica para computar buckets.

### 7.9 Formato para reclamo

- [ ] Resumen ejecutivo.
- [ ] Detalle por comprobante.
- [ ] Fecha de corte.
- [ ] Saldo final claro.
- [ ] Observaciones/comentarios para reclamo.
- [ ] Version presentable para enviar al cliente.

---

## 8. Reportes economicos y contables por company

### 8.1 Variante `accounting_general`

- [ ] Crear/terminar variante dedicada.
- [ ] Reusar `AccountingSummaryService` donde sirva.
- [ ] Definir cuando usar resumen vs `accounting_entries` detallado.

### 8.2 Resumen general por periodo

- [ ] Ingresos totales.
- [ ] Egresos totales.
- [ ] Balance neto.
- [ ] Movimientos por periodo.
- [ ] Comparativo entre periodos.

### 8.3 Resumen por caja

- [ ] `cash_box_id`.
- [ ] Nombre de caja.
- [ ] Saldo inicial.
- [ ] Ingresos.
- [ ] Egresos.
- [ ] Saldo final.
- [ ] Cantidad de movimientos.
- [ ] Diferencias/conciliacion.

### 8.4 Resumen por cliente

- [ ] Facturado.
- [ ] Cobrado.
- [ ] Pendiente.
- [ ] Gastos trasladables.
- [ ] Rentabilidad si luego se define.

### 8.5 Resumen por proveedor

- [ ] Pagos realizados.
- [ ] Deuda pendiente.
- [ ] Movimientos por periodo.

### 8.6 Agrupaciones

- [ ] Por categoria.
- [ ] Por origen.
- [ ] Por metodo de pago.
- [ ] Por caja.
- [ ] Por cliente.
- [ ] Por proveedor.

### 8.7 Libro/mayor/detalle contable

- [ ] Fecha.
- [ ] Tipo.
- [ ] Origen.
- [ ] Referencia.
- [ ] Debe/haber o equivalente.
- [ ] Saldo acumulado si aplica.
- [ ] Filtro por cuenta contable.

### 8.8 Rankings y vistas ejecutivas

- [ ] Ranking de clientes deudores.
- [ ] Ranking de clientes con mayor facturacion.
- [ ] Ranking de cajas con mayor movimiento.
- [ ] Resumen diario.
- [ ] Resumen semanal.
- [ ] Resumen mensual.
- [ ] Flujo de ingresos/egresos.

---

## 9. UI/API de soporte operativo

- [ ] Definir requests en Postman/coleccion para cada variante.
- [ ] Definir como la UI selecciona variante y secciones.
- [ ] Definir presets reutilizables por modulo.
- [ ] Definir defaults de filtros por ultima ejecucion.
- [ ] Permitir volver a generar usando metadata del ultimo reporte.
- [ ] Permitir listar reportes anteriores por cliente/company.
- [ ] Permitir descargar facilmente ultimo reporte relevante.

---

## 10. Documentacion tecnica necesaria

- [ ] Actualizar `sisa.api/docs/reports-table.md` con nuevas variantes y metadata obligatoria.
- [ ] Crear doc especifica para payload comun de reportes.
- [ ] Crear doc especifica para `client_account_statement`.
- [ ] Crear doc especifica para reportes economicos/contables.
- [ ] Documentar reglas de calculo economico de jobs/worklogs.
- [ ] Documentar limites de performance y puntos ciegos actuales.
- [ ] Documentar deudas conocidas de schema legacy.

---

## 11. QA automatizado

### 11.1 Filtros y payload

- [x] Tests basicos de `display_options` existentes.
- [x] Tests basicos de `report_variant` e `include_sections`.
- [ ] Tests de `group_by`.
- [ ] Tests de flags auxiliares.
- [ ] Tests de combinaciones invalidas por variante.

### 11.2 Dataset operativo

- [ ] Jobs incluidos correctamente.
- [ ] Job items incluidos correctamente.
- [ ] Worklogs incluidos correctamente.
- [ ] Participantes incluidos correctamente.
- [ ] Tarifas incluidas correctamente.
- [ ] Calculo de mano de obra correcto.
- [ ] Gastos cliente incluidos correctamente.
- [ ] Adjuntos incluidos/listados correctamente.
- [ ] Appointments incluidos correctamente.

### 11.3 Timeline

- [ ] Orden cronologico correcto.
- [ ] No duplicados relevantes.
- [ ] Eventos mixtos de varias entidades.
- [ ] Eventos faltantes se manejan bien.
- [ ] Timestamps visibles y legibles.

### 11.4 Economico de jobs

- [ ] `participante x tarifa x tiempo`.
- [ ] Subtotales por participant.
- [ ] Subtotales por worklog.
- [ ] Subtotales por job.
- [ ] Override manual.
- [ ] Worklog sin tarifa.
- [ ] Participant sin tarifa.
- [ ] Duracion cero.
- [ ] Duracion negativa invalida.

### 11.5 Cuenta corriente cliente

- [ ] Facturas emitidas.
- [ ] Pagos parciales.
- [ ] Pagos totales.
- [ ] Recibos aplicados.
- [ ] Saldo pendiente.
- [ ] Aging correcto.
- [ ] Gastos trasladables incluidos.
- [ ] Cliente sin movimientos.
- [ ] Cliente con muchos movimientos.

### 11.6 Economicos/contables

- [ ] Resumen general.
- [ ] Resumen por caja.
- [ ] Ingresos.
- [ ] Egresos.
- [ ] Balance neto.
- [ ] Movimientos por periodo.
- [ ] Aislamiento por `company_id`.
- [ ] Datos vacios.
- [ ] Cajas sin movimientos.

### 11.7 Persistencia de reportes

- [x] `company_id` en `reports`.
- [x] `company_id` en `reports_history`.
- [ ] `client_id` en metadata consistente.
- [ ] `report_variant` persistido.
- [ ] `start_date`/`end_date` persistidos.
- [ ] `include_sections` persistidas.
- [ ] Regeneracion de reporte.
- [ ] Historial de regeneracion.
- [ ] Descarga posterior por `/reports`/`/files`.

### 11.8 Visual PDF

- [ ] Encabezado correcto.
- [ ] Pie correcto.
- [ ] Saltos de pagina razonables.
- [ ] Tablas largas.
- [ ] Textos largos.
- [ ] Imagenes.
- [ ] Resumen final.
- [ ] Vertical/horizontal.
- [ ] Legibilidad en A4 real.

### 11.9 Performance

- [ ] Cliente con muchos jobs.
- [ ] Jobs con muchos items.
- [ ] Jobs con muchos worklogs.
- [ ] Timeline largo.
- [ ] Muchos adjuntos.
- [ ] Muchas facturas/pagos/recibos.
- [ ] PDFs grandes.

---

## 12. QA manual y runbooks

- [ ] Crear runbook manual para PDF operativo completo.
- [ ] Crear runbook manual para cuenta corriente cliente.
- [ ] Crear runbook manual para informe economico por caja.
- [ ] Crear runbook manual para informe economico general por company.
- [ ] Definir evidencia esperada en cada corrida manual.
- [ ] Definir dataset minimo de prueba para reportes.

---

## 13. Despliegue y migraciones

- [ ] Confirmar si hace falta migracion para `reports` (columnas extras o indices).
- [ ] Confirmar si hace falta migracion para relaciones de `payments` con `job`/`worklog`/`invoice_item`.
- [ ] Confirmar si hace falta migracion para vencimientos/aging en invoices.
- [ ] Confirmar si hace falta migracion para soportar mejor historial de tarifas aplicadas.
- [ ] Preparar pasos de despliegue y rollback.

---

## 14. Riesgos a vigilar durante la implementacion

- [ ] Divergencia entre schema legacy y modelo actual de jobs.
- [ ] Doble fuente de verdad para duracion e importes.
- [ ] Reportes con filtros fuera de scope company.
- [ ] PDFs demasiado pesados por imagenes.
- [ ] N+1 en histories/worklogs/appointments.
- [ ] Falta de vinculo directo de cargos cliente con job/worklog.
- [ ] Inconsistencia entre resumen contable y detalle operativo.
- [ ] Metadata incompleta que impida regeneracion futura.

---

## 15. Orden recomendado de ejecucion

### Tramo A - consolidacion del operativo de jobs

- [~] Variantes y secciones en endpoint actual.
- [~] Worklogs + participants + tarifas.
- [~] Timeline inicial.
- [~] Gastos cliente desde `payments`.
- [ ] Adjuntos ampliados.
- [ ] Appointments enriquecidos.

### Tramo B - cuenta corriente por cliente

- [ ] Variante `client_account_statement`.
- [ ] Facturas + recibos + aplicaciones.
- [ ] Saldo + aging.
- [ ] Formato enviable a cliente.

### Tramo C - economicos/contables

- [ ] Variante `accounting_general`.
- [ ] Resumen por caja.
- [ ] Resumen general company.
- [ ] Ranking y vistas ejecutivas.
- [ ] Libro/detalle contable.

### Tramo D - hardening final

- [ ] Persistencia avanzada de metadata.
- [ ] Regeneracion/reutilizacion.
- [ ] QA de performance.
- [ ] Documentacion final de contratos.
- [ ] Runbooks manuales.

---

## 16. Definicion de terminado

Se considera cumplido el objetivo de transformacion cuando:

- [ ] el informe operativo de jobs puede mostrar de forma confiable jobs + items + worklogs + participantes + tarifas + timeline + gastos trasladables
- [ ] existe estado de cuenta PDF por cliente con saldo, recibos, pagos y aging
- [ ] existen reportes economicos/contables por company y por caja
- [ ] toda generacion queda trazada en `reports` y `reports_history`
- [ ] la metadata permite reubicar y regenerar reportes
- [ ] hay QA automatizado focalizado para filtros, dataset, timeline, persistencia y contabilidad
- [ ] existe checklist/manual suficiente para operar y mantener el modulo sin redescubrirlo
