# Aplicación del plan de mejora integral

Fecha de trabajo: 21 de septiembre de 2026.

Este registro acompaña `Plan de mejora integral del sistema.docx` y distingue código entregado de alcance pendiente. Los avances de mesas, pedidos y caja continúan en `calidad-tpv.md`.

## Funciones incorporadas

- **Producción por lotes:** acceso desde Cocina. Crear borrador, conservar ingredientes e instrucciones de la receta con su versión, cerrar con cantidad real obtenida y anular borradores con motivo. El cierre consume los ingredientes previstos y registra el elaborado en una sola transacción. La misma confirmación no genera otro consumo. Una falta de disponibilidad o salida inactiva revierte todos los movimientos.
- **Bodega:** las altas por formulario o CSV registran saldo inicial con responsable. El catálogo deja de sobrescribir existencias; los ajustes requieren cantidad, motivo y saldo esperado para detectar pantallas desactualizadas. La vista comercial usa el saldo operativo, diferencia disponible, reservado y físico teórico e incorpora las mermas. El diagnóstico de integridad conserva los datos antiguos para revisión documental y no aplica parches automáticos.
- **Compras y mermas:** los formularios envían identificadores únicos. Repetir la petición devuelve el resultado existente; reutilizar la misma clave con datos distintos se rechaza. Las escrituras usan el mismo control transaccional del inventario y POS en ambos motores.
- **Importaciones:** revisión previa con cantidades de altas y actualizaciones, confirmación separada y aplicación atómica del archivo válido. Los artículos existentes conservan stock, unidad, mínimos y estado. Unidades desconocidas, números inválidos y códigos duplicados en el archivo provocan rechazo. Cada archivo confirmado queda marcado para impedir repetirlo y se conserva como evidencia.
- **Productos y recetas:** validación de ingredientes y cantidades, rechazo de duplicados y guardado conjunto. Las modificaciones incrementan la versión de la ficha; las producciones abiertas conservan su copia.
- **Reportes:** costos unitarios conservados en los movimientos nuevos, incluidos modificadores; cambios posteriores de precio o receta no alteran esos costos. Los períodos de ventas usan la confirmación del inventario al cobrar, con fecha de creación como alternativa para registros antiguos. Se advierte cuando faltan costos históricos. El cierre mensual rechaza meses en curso o futuros.
- **Administración:** límite de intentos de acceso, invalidación de sesiones de usuarios inactivos, PIN protegidos con hash y búsqueda mediante HMAC, preservación de ceros iniciales y bloqueo de duplicados. Los PIN no se muestran ni se incluyen en datos de sesión de errores. Desactivar personal conserva su historial.
- **Auditoría:** pantalla Historial de cambios para administración y programación, con actor y modificaciones de personal, productos, recetas y catálogo. Las credenciales se excluyen. Los movimientos mantienen además su propio responsable y motivo.
- **Operación local:** Chart.js 4.4.0 y su licencia se distribuyen con el proyecto; la interfaz usa fuentes del sistema. Se retiró la carga externa duplicada de Alpine, ya incluido en Livewire. La página permite ampliar con zoom.
- **Continuidad:** respaldo SQLite mediante su API de backup, con verificación de integridad y referencias antes de publicar el archivo. El instalador se detiene si falla una migración.
- **Calidad:** flujo CI para SQLite y PostgreSQL, pruebas de migración, credenciales, importación, inventario, producción, fallos y procesos simultáneos, además de compilación de vistas y frontend.

## Uso de producción

1. Crear en bodega el insumo elaborado que recibirá la producción.
2. Crear una ficha de receta cuyos ingredientes expresen el consumo por **una unidad del elaborado**. Usar las unidades base ya admitidas: gramos, mililitros o unidades; no hay conversión automática de kg o litros en esta entrega.
3. Abrir Cocina → Producción por lotes y elegir ficha, elaborado y cantidad prevista.
4. Revisar la copia de ingredientes e instrucciones guardada en el borrador.
5. Al terminar, indicar la cantidad útil obtenida y cerrar. La diferencia respecto de lo previsto queda reflejada en el rendimiento.
6. Para vender una preparación que utiliza ese elaborado, su receta debe consumir el insumo elaborado. No conservar simultáneamente los ingredientes originales en esa receta de venta, pues se consumirían dos veces.

El consumo de ingredientes al cerrar corresponde al previsto en la receta guardada; el registro de consumos reales variables y mermas detalladas por lote es una ampliación pendiente. El costo de adquisición actual conserva el criterio existente de última entrada; no se cambió a promedio ponderado sin definir la política del negocio.

## Seguimiento de los requisitos

| Requisito | Estado y alcance restante |
|---|---|
| PRO01 | Base implementada: versión y copia por lote. Faltan conversiones y vigencias administrables. |
| PRO02 | Borrador, cierre atómico, anulación e idempotencia implementados. Faltan liberación, estado en proceso y aprobaciones específicas. |
| PRO03 | Elaborados, rendimiento y costo de lote implementados. Faltan consumos reales y mermas detalladas de producción. |
| PRO04 | Referencias de movimientos a lote implementadas. Faltan lotes de proveedor, caducidades y planificación de demanda. |
| INV01 | Saldo operativo común y nuevas altas/ajustes trazables. La historia anterior requiere conciliación documental. |
| INV02 | Pendiente: existencias independientes por ubicación y transferencias con recepción. El atributo punto sigue siendo clasificación. |
| INV03 | Pendiente: lotes de entrada, caducidades, cuarentenas y política de negativos por operación. |
| INV04 | Conteo compara contra físico teórico incluyendo reservas. Pendiente: borrador, corte y aprobación del ajuste de conteo. |
| INV05 | Diagnóstico sin cambios automáticos implementado. Pendiente: conciliación aprobada de saldos iniciales antiguos. |
| COM01 | Registro de compras conservado y protegido contra reenvíos. Pendiente: solicitud, aprobación y recepciones parciales. |
| COM02 | Costo de movimientos nuevo conservado. Pendiente: unidad de compra, factores y método de valoración elegido. |
| COM03 | Repetición técnica de peticiones controlada. Pendiente: duplicados comerciales por factura, devoluciones y bonificaciones vinculadas. |
| CAT01 | Producto y receta atómicos; catálogo sin cambios silenciosos de stock o unidad con historial. Pendiente: clasificación completa de artículos y flujo de conversión. |
| ADM01 | Accesos por roles existentes comprobados. Pendiente: matriz granular de acciones, perfiles y ámbitos. |
| ADM02 | Hash de PIN, unicidad, límites y desactivación de sesiones implementados. Pendiente: caducidad por puesto y gestión de sesiones activas. |
| ADM03 | Auditoría consultable de catálogo, recetas y personal implementada. Pendiente: autorizaciones por umbral y ampliación a todos los parámetros. |
| ADM04 | Pendiente: administración central de políticas y catálogos sin editar archivos. |
| REP01 | Costos de movimientos nuevos y período de cobro incorporados; datos antiguos incompletos se señalan. Pendiente: conciliación de historia y nombres/categorías históricos. |
| REP02 | Dashboard actual conserva detalle; pendiente reorganizar vistas y filtros por responsabilidad. |
| REP03 | PDF conserva la instantánea del cierre y se impide cerrar meses no terminados. Pendiente: correcciones versionadas y aprobaciones de reapertura. |
| REP04 | Recursos gráficos locales y consultas agrupadas de costos implementados. Pendiente: caché con invalidación y objetivos de carga medidos. |
| TEC01 | Instalación y migraciones probadas en SQLite y PostgreSQL, incluidas búsquedas. Continúa como requisito de cada cambio. |
| TEC02 | Control transaccional común e idempotencia de producción, compras y mermas; pruebas con procesos simultáneos. Pendiente: extender claves a toda escritura e integraciones externas. |
| TEC03 | Recursos principales locales. Pendiente: ensayo completo sin Internet y tratamiento explícito de pérdida de LAN. |
| TEC04 | Pendiente: matriz de hardware, navegadores, impresoras y pruebas físicas. |
| TEC05 | Pendiente: exportación versionada entre motores y sedes. |
| OPS01 | Backup consistente y verificado de SQLite; pg_dump conservado. Pendiente: paquete integral de adjuntos, archivos y configuración recuperable. |
| OPS02 | Restauración de prueba SQLite y restauración PostgreSQL para ensayar actualización realizadas. Pendiente: programación, retención y objetivos de recuperación. |
| OPS03 | Instalador detiene errores de migración; actualización ensayada con copia. Pendiente: comprobaciones de espacio, respaldo automático previo y recuperación guiada. |
| OPS04 | Pendiente: estado de tareas, diagnósticos exportables y supervisión. |
| DAT01 | Revisión, lote confirmado, atomicidad e importación sin sobrescribir stock implementados. Pendiente: reversión compensada del lote y vista previa detallada por campo. |
| DAT02 | Validación de unidades y formatos básicos reforzada. Pendiente: codificaciones adicionales y protección general de todas las exportaciones. |
| UX01 | Nuevos formularios con errores y posibilidad de zoom. Pendiente: revisión integral de accesibilidad y pruebas en puestos reales. |
| UX02 | Pendiente: bandeja de alertas con responsable y seguimiento. |
| INT01 | Pendiente: contratos de integraciones según necesidades aprobadas. |
| CAL01 | Reglas nuevas en servicios y transacciones compartidas. Pendiente: consolidar todos los recorridos antiguos de compras y conteos. |
| CAL02 | CI y pruebas en dos motores incorporados; ejecución local verificada. La ejecución remota depende de subir los cambios. |
| CAL03 | Fallos y concurrencia real de producción probados. Pendiente: red, periféricos y tareas externas. |
| CAL04 | Pendiente: piloto con personal y datos de operación representativos. |

## Migraciones y recuperación

Las migraciones del 21 de septiembre agregan instantáneas de costo, producción, protección de PIN y auditoría/idempotencia. No reconstruyen costos antiguos usando precios actuales ni recalculan masivamente existencias.

La protección de PIN conserva el código de acceso, pero elimina su texto original. Se comprueba antes que no haya PIN compartidos. `APP_KEY` debe conservarse en la recuperación porque protege el índice de búsqueda del PIN. Las credenciales protegidas no tienen una migración inversa a texto: para volver a una versión anterior se restaura el respaldo previo y el código/configuración correspondientes. La migración rechaza un rollback que descartaría credenciales protegidas.

El respaldo previo a la primera actualización local es `storage/app/backups/pgsql_20260921_163158.dump`. Se restauró en una instancia de prueba separada antes de actualizar la instalación. El ensayo conservó 2 órdenes, 181 insumos y 25 movimientos existentes.

Antes de aplicar credenciales y auditoría se creó `storage/app/backups/pgsql_20260921_211306.dump`, se restauró de nuevo en una base aislada y se comprobaron los cinco PIN activos. Esa segunda actualización también quedó aplicada a la instalación local: los cinco códigos de acceso se conservaron y no quedaron PIN en texto en la tabla de usuarios.

## Comprobaciones

Resultado: suite completa con 51 pruebas aprobadas y una omitida específica de PostgreSQL en SQLite; 52 aprobadas en PostgreSQL 18. Se añadió y aprobó después, en ambos motores, un caso adicional que verifica que reaplicar los controles POS no elimina los tipos de movimientos de producción. La omisión en SQLite corresponde exclusivamente al valor UUID generado por defecto en PostgreSQL.

- `php artisan test`: conjunto de pruebas locales con SQLite en memoria, excepto las pruebas de concurrencia que usan un archivo temporal.
- El mismo conjunto con `DB_CONNECTION=pgsql` y una base **exclusiva de pruebas**. Estas pruebas usan `migrate:fresh` y nunca deben apuntar a la base de operación.
- Dos procesos PHP separados verifican consumo simultáneo y confirmación repetida del mismo lote.
- La restauración SQLite comprueba datos confirmados que siguen en WAL.
- `php artisan view:cache`, `pnpm run build` y análisis sintáctico del instalador PowerShell.
- Las pruebas no sustituyen la aceptación del restaurante ni certifican periféricos o recuperación completa de adjuntos.
- Comprobación final de la instalación actual: dashboard, inventario, producción, personal y auditoría renderizan con los datos existentes; permanecen 2 órdenes, 181 insumos y 25 movimientos de inventario. Las cuatro migraciones nuevas están aplicadas y la instancia PostgreSQL temporal de pruebas se detuvo al terminar.

## Refuerzo de calidad del 27 de septiembre de 2026

Se aplicaron controles de dinero, compras, conteo ciego, arqueos auditados, mantenimiento seguro, optimizaciones y actualizaciones de dependencias. Detalle, pruebas y límites en [calidad-datos-y-portabilidad.md](calidad-datos-y-portabilidad.md).
