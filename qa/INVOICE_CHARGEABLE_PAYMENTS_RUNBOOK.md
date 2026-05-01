# Runbook manual - pagos cobrables en factura

Objetivo: validar que un `payment` marcado como cobrable al cliente aparezca con referencia estructurada en factura, resumen e informe PDF.

## Precondiciones

- usuario con permisos sobre `payments`, `invoices` y `reports`
- una `company` activa con al menos un cliente accesible
- al menos una caja y categoria operativas

## Dataset minimo

1. Crear o identificar un cliente `C1` de la empresa activa.
2. Crear un pago con:
   - `charge_client = true`
   - `client_id = C1`
   - descripcion clara, por ejemplo `Viaticos visita tecnica`
   - importe facil de reconocer, por ejemplo `1234.56`
3. Confirmar el `id` del pago creado.

## Escenario A - inclusion en factura

1. Abrir `Nueva factura`.
2. Seleccionar la misma empresa y el cliente `C1`.
3. En el bloque `Pagos cobrables al cliente`, elegir el pago recien creado.
4. Agregarlo a la factura.
5. Verificar antes de guardar:
   - la descripcion no contiene `#<id>` embebido
   - el importe coincide con el pago
   - en campos avanzados del item se ve:
     - `entity_type = payments`
     - `code = <id del payment>`
6. Crear la factura.

Resultado esperado:

- la factura se crea sin error
- el item queda persistido con `entity_type = payments`
- el `code` visible corresponde al `id` del pago
- al volver a `Nueva factura` para el mismo cliente/empresa, ese pago ya no aparece disponible ni se autoagrega porque quedo facturado en una factura activa

## Escenario B - PDF / informe visible al cliente

1. Generar el PDF de la factura creada o un reporte del cliente en el mismo periodo.
2. Verificar que la linea del pago cobrable aparezca visible al cliente.

Resultado esperado:

- el documento muestra `entity_type`/entidad `payments`
- el codigo coincide con el `id` del pago
- la descripcion es limpia y humana
- el importe coincide con el monto cobrable

## Escenario C - resumen de cuenta del cliente

1. Generar el estado de cuenta del cliente `C1` para el periodo del pago.
2. Revisar la seccion de pagos/cargos.

Resultado esperado:

- el pago aparece como movimiento del cliente
- se ve diferenciado como `payments`
- el codigo coincide con el `id` del pago
- el total impacta en el saldo neto del cliente

## Escenario D - seguridad multicliente

1. Intentar facturar para `C1` un pago cobrable ligado a otro cliente `C2`.

Resultado esperado:

- el backend rechaza la operacion
- no se crea ni actualiza la factura con ese item

## Escenario E - seguridad multiempresa

1. Intentar facturar para la empresa activa un pago cobrable perteneciente a otra `company`.

Resultado esperado:

- el backend rechaza la operacion
- no se mezclan datos entre empresas

## Escenario F - reapertura al eliminar factura

1. Eliminar la factura que contenia el pago cobrable facturado.
2. Volver a abrir `Nueva factura` para la misma empresa y el mismo cliente.

Resultado esperado:

- los `invoice_items` de esa factura quedan sin vigencia (`deleted_at`/borrado logico en cascada)
- el pago vuelve a aparecer como disponible para facturar
- si existen pagos cobrables vigentes, se autoagregan otra vez al final de la lista de items

## Escenario G - intento de borrar payment ya facturado

1. Con la factura activa todavia existente, ir al detalle del `payment` original.
2. Intentar eliminar el pago.

Resultado esperado:

- el sistema rechaza la eliminacion
- el mensaje indica que primero hay que eliminar la factura activa
- no queda una factura con item apuntando a un pago borrado
