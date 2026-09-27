# Calidad de datos, dinero y portabilidad

Actualizado: 27 de septiembre de 2026.

## Cambios aplicados

| Área | Comportamiento nuevo |
| --- | --- |
| Instalación | `app:ensure-key` conserva APP_KEY. Composer limpia la configuración anterior antes de comprobarla. |
| Windows | La clave se prepara después de disponer de las dependencias PHP. Se comprueba el manifiesto de assets y se detiene la instalación si falla Composer, pnpm o el build. El arranque usa `call` para volver de los ejecutables batch de npm/pnpm. |
| Compras | La compra rápida delega en el servicio de compras y crea un documento vinculado al movimiento. La interfaz exige el precio de esta compra por unidad. Los reintentos con la misma clave no duplican existencias ni documentos. |
| Histórico | Las compras rápidas antiguas sin documento se incluyen en entradas sin duplicar documentos vinculados. Un costo histórico ausente se identifica como incompleto; no se crean facturas ficticias. |
| Dinero | Brick Math calcula importes de líneas, descuentos, subtotales e impuestos de pedidos, y cantidad × precio de compras, con decimales. Los impuestos y totales de compra se redondean a dos decimales, mitad hacia arriba. |
| Límites | Las compras aceptan hasta cuatro decimales de cantidad y dos de precio. Se rechazan descuentos mayores que la línea y totales que exceden el almacenamiento admitido por PostgreSQL; SQLite aplica las mismas validaciones. |
| Corte de ventas | Caja e Informe Z usan la fecha de confirmación del cobro; para registros históricos sin esa fecha conservan la fecha de creación como alternativa. |
| Arqueo | Se rechazan denominaciones desconocidas y sumas fuera de rango. Cada guardado registra responsable, valores anteriores y nuevos. |
| Conteo físico | El formulario es realmente ciego: no muestra ni rellena existencias. Conserva valores tras errores. Rechaza insumos inexistentes o inactivos y registra el conteo en una transacción coordinada con los movimientos. No ajusta stock automáticamente. |
| Catálogo | Búsqueda y paginación de 25 platos. Cambiar solo precio no borra recetas. El formulario completo indica cuándo reemplaza la receta. La casilla de actividad permite desactivar correctamente. |
| Seguridad de interfaz | Los nombres de insumos en las opciones dinámicas se insertan como texto, sin interpolarlos como HTML. |
| Consultas | La comparación de consumos usa dos consultas independientemente del número de insumos y excluye movimientos revertidos. |
| Importación | La previsualización CSV no actualiza la fila de bloqueo; la importación definitiva conserva su transacción y validación. |
| Informes | El cierre mensual persiste el snapshot antes de renderizar el PDF. Un fallo de PDF conserva el cierre y permite reintentar desde el historial. |
| Mantenimiento | Limpiar cachés conserva sesiones y logs. Reiniciar trabajadores conserva trabajos pendientes y fallidos. |
| Reproducibilidad | Lockfiles actualizados, instalación congelada, eliminación de la descarga dinámica de `only-allow` y exclusión de vistas compiladas del repositorio. |

## Dependencias y comprobaciones

Actualizaciones principales dentro de las ramas actuales:

- Laravel 12.69.2, Livewire 4.4.6 y Dompdf 3.1.6.
- Axios 1.20.0, Vite 7.3.6, Laravel Vite Plugin 2.1.0 y Tailwind 4.3.3.
- Dependencias transitivas PHP y JavaScript actualizadas en sus lockfiles.
- Brick Math declarado como dependencia directa para los cálculos decimales.
- YAML 2.8.3 para inspeccionar versiones reales del lockfile.

El verificador de antigüedad recorre paquetes directos y transitivos resueltos; no sustituye versiones por `latest` ni declara éxito ante errores de consulta. `pnpm run safe-install` instala con el lockfile, verifica antigüedad y ejecuta auditoría. CI incluye auditorías Composer/pnpm, verificación de antigüedad y la matriz SQLite/PostgreSQL.

Validación ejecutada:

- SQLite: 68 pruebas aprobadas, una omitida por ser exclusiva de UUID de PostgreSQL; 450 aserciones.
- PostgreSQL 18, base aislada: 69 pruebas aprobadas; 458 aserciones.
- Incluye migraciones reversibles/reaplicables de modificadores y UUID, concurrencia de producción, caja, compras, conteos, recetas, importaciones y restauración SQLite con WAL.
- `composer audit --locked`: cero avisos conocidos.
- `pnpm audit`: cero avisos conocidos.
- Verificación de antigüedad: cero versiones menores de 30 días y cero errores al consultar.
- Build Vite, compilación Blade, validación Composer, sintaxis PHP/JavaScript y análisis sintáctico del instalador PowerShell correctos.

No se ejecutaron migraciones ni operaciones de negocio en la base del restaurante para estas pruebas. No se probó un ciclo completo de instalación Windows en un equipo limpio ni impresión en hardware real.

## Uso y límites

Antes de trasladar una instalación, conservar su `.env` y APP_KEY junto con un respaldo consistente de la base y los archivos necesarios de `storage/app`. Restaurar solamente la base no conserva toda la instalación ni sus credenciales.

La compra rápida utiliza el proveedor y sede del insumo; si faltan, muestra la sede predeterminada Bodega y registra proveedor no informado. Para registrar proveedor, factura o sede explícitos, utilizar Compras. El precio solicitado corresponde a la unidad del insumo, no al paquete completo.

Los conteos y sus diferencias son evidencia para revisión. Las correcciones de stock siguen pasando por el ajuste con motivo y responsable. Los importes históricos sin costo no deben interpretarse como compras gratuitas.

La versión ESC/POS y las versiones principales de Vite/Laravel se conservan; no se hicieron saltos mayores sin validación de compatibilidad. Chart.js local mantiene su distribución existente, fuera de las auditorías de lockfiles.

Quedan como refactorizaciones posteriores la separación completa de los controladores por operaciones, extracción general de JavaScript/CSS de Blade, bloqueos más específicos de PostgreSQL con pruebas de carga y conversión de los cálculos restantes de costos y cierres a decimales. Se conserva la coordinación global de escrituras necesaria para los controles actuales de caja y SQLite. Este cambio no incorpora pagos divididos, conciliación bancaria ni contabilidad de partida doble.
