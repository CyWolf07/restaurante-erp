# Mejora del recorrido de caja y mesas

Referencia funcional: Sysme TPV. Revisión del proyecto: 18 de septiembre de 2026.

El orden acordado es **mesas → pedidos → cambios → cobro → caja**. Esta entrega refuerza la integridad del recorrido existente y añade traslado de mesa. No completa todavía todas las funciones de un TPV de hostelería.

## Referencia consultada

El [terminal de ventas de Sysme](https://www.sysme.net/sysme-tpv/manual-del-usuario/gestion-de-ventas/terminal-de-ventas-tpv/) integra ventas pendientes, asignación a mesas, notas, envío a cocina, pre-ticket, división de ventas y cobro con forma de pago. Su manual de [mesas](https://www.sysme.net/sysme-tpv/manual-del-usuario/modulo-de-hosteleria/mesas/) organiza las mesas por salones. Los [movimientos de caja](https://www.sysme.net/sysme-tpv/manual-del-usuario/gestion-de-ventas/movimientos-de-caja/) se asocian a un punto de venta y a su apertura. Se toma ese recorrido como referencia; las decisiones de implementación siguientes corresponden a este proyecto.

## Cambios de esta entrega

| Paso | Problema encontrado | Cambio |
| --- | --- | --- |
| Mesas | Dos solicitudes podían superar la comprobación de mesa libre. Una mesa aocupada podía renumerarse o desactivarse. | Comprobación dentro de una transacción serializada y protección de mesas ocupadas. Traslado a una mesa activa y libre, conservando la orden y sus importes. |
| Pedidos | No se validaban cantidades de modificadores ni el estado activo del catálogo en el alta del mesero. Otro mesero podía enviar una orden ajena a cocina. | Validación de cantidades, notas, productos, mesas y modificadores; control de autoría para el envío. |
| Cambios | Las reservas antiguas se devolvían repetidamente al editar y después cancelar. Eliminar el último plato dejaba un total antiguo. | Las reservas revertidas se identifican y no se vuelven a devolver ni confirmar. Cancelación con total cero cuando ya no quedan platos. Motivo obligatorio al eliminar y registro de cambios en `order_events`. |
| Cobro | El estado pagado se guardaba antes de confirmar el inventario. Una pantalla antigua podía confirmar otro total. | Cobro e inventario en una transacción; comparación del total mostrado; protección frente a cobro repetido y órdenes bloqueadas. Impresión posterior a la transacción. |
| Caja | Los cierres ignoraban órdenes activas de ayer. Era posible operar tras el cierre o cerrarlo sin arqueo. | Los dos cierres revisan todas las órdenes activas. El cierre de caja exige arqueo. Se bloquean nuevas ventas tras cierre de caja o informe Z; el arqueo puede completarse tras Z hasta el cierre de caja. |

Los errores de validación ahora se muestran en la plantilla común. El traslado se encuentra en el detalle de mesa de caja. Si la orden ya se envió, la pantalla indica que hay que avisar a cocina del traslado.

## Base técnica y actualización

- Migración nueva: `2026_09_18_000001_add_pos_operation_controls.php`. Crea el control de operaciones, el historial y `inventory_logs.reversed_at`.
- Corrige el CHECK antiguo de SQLite que rechazaba reservas, confirmaciones y reversiones de inventario. PostgreSQL conserva los tipos admitidos por su migración anterior.
- Consulta los arqueos y cierres por fecha, evitando que SQLite confunda una fecha almacenada con hora con una fecha sin hora. Actualizar el arqueo del mismo día conserva un único registro.
- `PosOperationService` usa una escritura en una fila compartida para serializar operaciones del POS y cierres. Esto funciona también en SQLite, donde `FOR UPDATE` no proporciona ese bloqueo. Las rutas del POS deben seguir usando este servicio; las escrituras directas a la base de datos no quedan cubiertas.
- Para actualizar una instalación, aplicar las migraciones con `php artisan migrate --force` junto con el código. No ejecutar `migrate:fresh` sobre datos reales. Las validaciones de esta entrega utilizan bases de prueba.
- Los cambios evitan nuevas reversiones duplicadas. No reconstruyen el saldo histórico ni identifican automáticamente reservas que ya habían sido revertidas antes de existir `reversed_at`. Una instalación con ese historial necesita conciliación de inventario antes de continuar órdenes antiguas.
- Revertir esta migración elimina el historial nuevo y los marcadores de reversión; es una herramienta de desarrollo, no una estrategia de recuperación de movimientos reales.

## Siguiente recorrido funcional

1. **Mesas:** mapa consistente para caja y mesero, apertura desde el mapa, comensales y tiempo de ocupación. Criterio: ambos roles identifican y recuperan la misma cuenta sin duplicarla.
2. **Pedidos:** envíos por lote con únicamente productos nuevos; distinguir preparado, servido y pendiente de cobro; reintentos de impresión visibles. Criterio: añadir un plato no vuelve a ordenar la preparación de los anteriores.
3. **Cambios:** avisos de corrección/anulación a cocina, historial visible para supervisión y permisos para descuentos; distinguir devolución de reserva de merma de un plato ya preparado. Criterio: cada corrección conserva responsable, motivo y comunicación a cocina.
4. **Cobro:** división por productos/personas, pagos parciales y combinados, importe recibido y cambio. Guardar fecha de cobro y usarla coherentemente en informes. Criterio: pagos más saldo pendiente coinciden con la cuenta, y una venta de ayer cobrada hoy figura en la caja de hoy.
5. **Caja:** apertura explícita por turno, entradas y salidas durante el turno, desglose por medio de pago y cierre conciliado. Criterio: base + cobros en efectivo + entradas − salidas coincide con efectivo esperado, separado de tarjeta y transferencia.

## Pruebas reproducibles

Validación de esta entrega: SQLite, 24 pruebas aprobadas y una exclusiva de PostgreSQL omitida (220 aserciones); PostgreSQL 16, 25 pruebas aprobadas (228 aserciones). También se verificó el renderizado del detalle de mesa y el formato PHP de los archivos modificados. PostgreSQL se ejecutó en una instancia temporal aislada.

`php vendor/phpunit/phpunit/phpunit` usa SQLite en memoria por defecto. Para PostgreSQL, definir `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` y `DB_URL` vacío en el proceso de pruebas, apuntando exclusivamente a una base de pruebas: la suite recrea sus tablas.

La suite cubre migraciones y reversión selectiva, UUID, apertura repetida, catálogo inactivo, cantidades inválidas, autoría, edición y cancelación con inventario, traslado, cobro repetido o con total obsoleto, rollback ante fallo de inventario, bloqueo de órdenes y cierres/arqueos. La impresión está simulada: no sustituye una comprobación con la impresora física. Las pruebas HTTP repetidas no constituyen una prueba de carga concurrente.

Limitaciones actuales: el envío a cocina aún imprime la orden completa; el cierre sigue basado en la fecha de creación de la orden y trata el total vendido como efectivo; no hay pagos parciales ni turnos de caja. Estas funciones requieren el siguiente desarrollo del recorrido y no se presentan como completadas.
