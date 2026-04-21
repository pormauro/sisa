# Runbook de Reportes - Validación Manual

Este documento define los escenarios manuales para validar que los reportes funcionan correctamente en producción.

## Entorno

- Backend: `sisa.api` (PHP 8.2)
- Frontend: `sisa.ui` (Expo/React Native)
- Base de datos: MySQL (production-like)
- PDF: Dompdf + dompdf/lib-html5

## Comandos de validación

### Backend - Generar reporte operativo de jobs

```bash
# Como usuario con acceso a clients
curl -X POST "http://localhost:8080/jobs/client/123/report/pdf" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "report_variant": "full_detailed",
    "include_sections": ["jobs", "items", "worklogs", "participants", "timeline"],
    "display_options": {"include_images": true},
    "start_date": "2024-01-01",
    "end_date": "2024-12-31"
  }'
```

**Respuesta esperada:**
```json
{
  "file_id": 1234,
  "report_id": 567,
  "download_url": "/files/1234"
}
```

### Backend - Generar estado de cuenta

```bash
curl -X POST "http://localhost:8080/clients/123/jobs/report/pdf" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "report_variant": "client_account_statement",
    "include_sections": ["invoices", "receipts", "payments", "aging"],
    "start_date": "2024-01-01",
    "end_date": "2024-12-31"
  }'
```

### Backend - Generar reporte contable

```bash
curl -X POST "http://localhost:8080/clients/123/jobs/report/pdf" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "report_variant": "accounting_general",
    "include_sections": ["cash_boxes", "movements", "actors"],
    "group_by": "cash_box",
    "start_date": "2024-01-01",
    "end_date": "2024-12-31"
  }'
```

### Backend - Listar reportes

```bash
curl -X GET "http://localhost:8080/reports?company_id=1&client_id=123&report_variant=full_detailed" \
  -H "Authorization: Bearer $TOKEN"
```

### Backend - Regenerar reporte

```bash
curl -X POST "http://localhost:8080/reports/567/regenerate" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"filters": {"start_date": "2024-06-01"}}'
```

---

## Casos de prueba manual

### CP1: Reporte operativojobs completos

**Objetivo:** Verificar que el PDF operativo muestra jobs con items, worklogs y participantes.

**Pasos:**
1. Seleccionar un cliente con al menos 3 jobs completados en el período
2. Generar reporte `full_detailed`
3. Descargar PDF
4. Verificar:
   - [ ] Encabezado con empresa y logo
   - [ ] Datos del cliente
   - [ ] Período del reporte
   - [ ] Lista de jobs (mínimo 3)
   - [ ] Items por job
   - [ ] Worklogs por item
   - [ ] Participantes con tarifas
   - [ ] Timelinecronológico
   - [ ] Resumeneconómico
   - [ ] Pie con fecha y página

### CP2: Estado de cuenta

**Objetivo:** Verificar saldos y aging.

**Pasos:**
1. Seleccionar cliente con facturas pendientes y pagadas
2. Generar reporte `client_account_statement`
3. Verificar:
   - [ ] Total facturado coincide con suma de facturas
   - [ ] Total cobrado coincide con suma de recibos
   - [ ] Saldo pendiente = facturado - cobrado
   - [ ] Aging: al día, vencido 1-30, 31-60, 61-90, +90
   - [ ] Aplicaciones factura-recibo correctas

### CP3: Reporte contable por caja

**Objetivo:** Verificar resumen por caja.

**Pasos:**
1. Generar reporte `accounting_general` con `group_by: cash_box`
2. Verificar:
   - [ ] Ingresos totales = suma de cajas
   - [ ] Egresos totales = suma de cajas
   - [ ] Balance neto = ingresos - egresos
   - [ ] Detalle por caja con saldo final

### CP4: Regeneración

**Objetivo:** Verificar que regenerar crea nuevo PDF.

**Pasos:**
1. Tomar un reporte existente
2. Regenerar con fecha modificada
3. Verificar:
   - [ ] Nuevo `file_id`
   - [ ] Nuevo `report_id`
   - [ ] Fechas actualizadas en metadata

### CP5: Filtros inválidos

**Objetivo:** Verificar validación de payload.

**Pasos:**
1. Enviar `group_by: invalid_value` con variant operativos
2. Verificar error 400 con mensaje claro

---

## Dataset mínimo de prueba

| Entity | Cantidad minima | Condicion |
|--------|-------------|----------|
| Clients | 3 | Con jobs, facturas, pagos |
| Jobs | 10 | Mixto estados |
| Job items | 20 | Con/sin worklogs |
| Worklogs | 30 | Con participantes |
| Invoices | 5 | Mixto pagadas/pendientes |
| Receipts | 5 | Aplicados a facturas |
| Payments | 10 | Con comprobantes |
| Cash boxes | 3 | Con movimientos |

---

## Errores comunes

### PDF no se genera

- Causa: Dompdf no instalado
- Solución: `composer install`

### Datos vacíos en PDF

- Causa: Filtro de fechas incorrecto
- Solución: Verificar `start_date` <= `end_date`

### Error de permisos

- Causa: Usuario sin `generateJobReport`
- Solución: Asignar permiso en `company_users`

### Archivo no descarga

- Causa: File no existe en storage
- Solución: Verificar `/files/{id}` existe

---

## Última actualización

- Fecha: 2026-04-20
- Autor: QA Agent
- Estado: Draft