# Correcciones de la auditoría del restaurante

## Resultado y alcance

Se corrigieron los 14 defectos identificados en el informe, con controles adicionales para cocina, impuestos guardados por producto y conciliación de medios de pago históricos. Esta entrega incluye el control fiscal local que estaba pendiente de publicación. No incluye un conector a un proveedor electrónico desconocido ni certifica cumplimiento tributario por sí sola.

Se consultó `.engram/config.json` como base del proyecto: solo contiene su nombre, sin historial técnico adicional disponible. No se modificó ese archivo.

## Correcciones por hallazgo

| # | Hallazgo | Corrección |
| --- | --- | --- |
| 1 | Tablas fiscales pendientes; errores al cobrar | Migraciones aplicadas en PostgreSQL después de un respaldo local. Para otras instalaciones, actualizar código y esquema juntos. |
| 2 | Todo cobro se trataba como efectivo | Desglose inmutable de cobros nuevos. Los pagos mixtos deben sumar exactamente la cuenta. El cierre compara efectivo con efectivo, no con tarjetas o transferencias. |
| 3 | Cambios fiscales concurrentes o basados en estados antiguos | Las escrituras fiscales usan el mismo bloqueo transaccional que los cierres. Se recarga el estado antes de modificar, retener o conciliar. |
| 4 | Cualquier texto retiraba una factura de pendientes | CUFE/CUDE con formato hexadecimal de 96 caracteres, soporte privado obligatorio y conciliación restringida a admin/programador. Se declara explícitamente que no hay verificación automática DIAN. |
| 5 | HTML inseguro y comentarios dañados por comillas | Escape contextual de nombres, opciones, recetas y comentarios. Los campos enviados por el mesero se construyen como elementos con valores, no concatenando HTML. |
| 6 | Cancelar comida enviada a cocina reponía ingredientes consumidos | El envío se registra por plato: los enviados se clasifican como merma; las adiciones aún no enviadas sí restituyen su reserva. Cambiar producto/cantidad después del envío requiere retiro con motivo y alta del reemplazo. Comentarios/descuentos no alteran inventario. |
| 7 | Ventas con existencias negativas | Comprobación de disponibilidad bajo bloqueo de insumo. Un faltante revierte toda la operación, sin venta parcial. |
| 8 | Editar comentarios actualizaba el precio al catálogo actual | Se conserva el precio vendido cuando el producto no cambia, y se calcula el descuento sobre ese precio. |
| 9 | Fallos de impresora informados como éxito | Ejecución directa del trabajo devuelve el resultado real. Fallo visible sin revertir cobros. `preticket_printed` solo se marca si se envía correctamente. Reimpresión sin nuevo cobro ni nuevo borrador. |
| 10 | Menú vacío en tablet | Se ocultan textos, no los iconos de navegación. |
| 11 | Comandero/modal cortados en pantalla estrecha | Columnas flexibles, comandero apilado desde 1100 px y receta en grilla responsive; se elimina el recorte del contenido principal. |
| 12 | Consultas POS superpuestas y datos antiguos sin aviso | Nueva consulta después de terminar la anterior, tiempo límite y aviso de desconexión/sesión vencida. Se actualizan totales del día y pendientes fiscales. |
| 13 | Comparación vacía o error silencioso | Estado de carga, validación HTTP, aviso de datos insuficientes y manejo de error; se limpian gráficos obsoletos. |
| 14 | PDF perdido producía una página 404 | Se regenera desde el cierre guardado, conservando importes y pendientes históricos. Si el almacenamiento falla, retorna a una pantalla válida con aviso. |

## Flujo fiscal y operativo

1. El mesero registra el pedido y se reserva inventario. Mesero o caja lo envían a cocina.
2. Cocina dispone de una cola de pedidos y puede marcarlos listos. Ese cambio aparece en POS y no consume inventario otra vez. Cocina no puede cobrar.
3. Caja cobra y guarda el desglose. En la misma transacción se confirma el inventario y se genera un borrador fiscal con precios, descuentos, modificadores, comprador y totales.
4. El borrador se revisa sin transmisión automática. ESC lo retiene; no elimina ni descobra la venta. Admin/programador pueden exigir o hacer opcional el motivo.
5. El cierre de caja y el Informe Z guardan la cantidad y valor acumulado de borradores pendientes al cierre. Una conciliación posterior no cambia ese cierre histórico.
6. El responsable verifica la aceptación en el sistema externo y adjunta soporte para conciliar. Solo entonces deja de contar como pendiente en el control actual.

La exportación JSON es **información interna**, no XML fiscal firmado. La impresión de un recibo tampoco equivale a emitir una factura electrónica. Reimprimir no transmite ni duplica ventas. El éxito de impresión significa envío a la impresora, no prueba física de que salió el papel.

## Controles adicionales y límites explícitos

- Los impuestos se pueden definir por producto; las líneas nuevas guardan tipo/tasa antes de cambios de catálogo. Los modificadores heredan la tasa de su plato. Excluidos/exentos requieren tasa cero. La clasificación correcta debe validarse con el contador. Las líneas antiguas sin tasa guardada usan la configuración general; no se inventa un historial fiscal perdido.
- Las ventas anteriores sin documento fiscal local se muestran como no conciliadas, separadas de las facturas conocidas sin enviar. No se crean facturas retrospectivas automáticamente: podrían estar emitidas en otro sistema.
- Los cobros históricos sin desglose no se presumen efectivo. Admin/programador pueden clasificarlos con motivo y auditoría, sin modificar importes ni cierres pasados. Un cierre nuevo exige resolver los cobros sin clasificar de su período.
- Corregir comprador/notas es posible mientras el borrador esté pendiente. No se cambia el medio de pago fiscal independientemente del cobro ni se reescriben importes de ventas pagadas. Las correcciones monetarias posteriores necesitan un proceso contable de ajustes, no un cambio silencioso al borrador.
- Falta conocer proveedor, API, credenciales y entorno de pruebas para integrar el autorellenado de su formulario y la transmisión real. ESC protege este flujo local; no puede deshacer una transmisión realizada fuera del ERP. Antes de activar un conector se necesitan pruebas de idempotencia, aceptación/rechazo y conciliación de respuestas.
- Retener un borrador no autoriza aplazar indefinidamente la expedición ni sustituye los procedimientos de contingencia aplicables.

Referencias oficiales consultadas: [validación previa DIAN](https://www.dian.gov.co/impuestos/factura-electronica/factura-electronica/Paginas/validacion-previa.aspx), [consulta de aceptación por CUFE](https://micrositios.dian.gov.co/sistema-de-facturacion-electronica/como-verifico-si-una-factura-fue-validada-por-la-dian/), [preguntas frecuentes](https://micrositios.dian.gov.co/sistema-de-facturacion-electronica/facturacion-preguntas-frecuentes/). Revisar requisitos y plazos concretos con el contador/proveedor antes de operar fiscalmente.

## Verificación y despliegue

- Pruebas de regresión de cobros, motivos, permisos, soportes, precios, merma, faltantes, cocina, impuestos, reimpresión y recuperación de PDF.
- Compilación de vistas y comprobación de sintaxis JavaScript en HTML renderizado.
- Revisión de encuadre en HTML de prueba: POS, control fiscal, productos, dashboard, cocina y recetas a 320, 820 y 1440 px; comandero a 375, 820 y 1440 px. Sin desbordamiento horizontal de página en esas muestras. No sustituye una prueba de todos los contenidos reales y dispositivos.
- Suite general SQLite: **86 pruebas correctas, 598 aserciones**; una prueba específica de PostgreSQL omitida en SQLite. Pruebas adicionales PostgreSQL: **40 correctas, 372 aserciones**, incluida aquella prueba omitida. `tests/Fixtures/postgres_fiscal_smoke.php` crea y elimina únicamente su base aleatoria de prueba, nunca migra la base real.

En otras instalaciones: crear respaldo verificable, aplicar `php artisan migrate --force`, renovar cachés de vistas/configuración según su despliegue y reiniciar los trabajadores que ejecuten código antiguo. **No ejecutar `migrate:fresh` sobre datos reales.**

El respaldo local, los soportes privados, `.env`, credenciales y los HTML de prueba no se publican en GitHub.
