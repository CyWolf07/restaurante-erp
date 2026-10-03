# App nativa: base incremental

## Implementado

Cliente Expo / React Native en `mobile/`, sin WebView. API Laravel versionada `/api/v1`, tokens Sanctum revocables con vencimiento de 24 horas, acceso con correo y contraseña, no con PIN remoto. Todos los roles pueden consultar sus pedidos permitidos: meseros únicamente propios, cocina únicamente enviados; caja, administración y programador ven activos. Cocina/caja/administradores pueden marcar preparados, reutilizando el bloqueo transaccional y la comprobación de caja abierta. El servidor registra al actor. No se incorporan cobros ni transmisión fiscal a la app en esta etapa.

El cliente utiliza almacenamiento seguro del sistema, listas paginadas, actualización manual, áreas seguras, texto adaptable y vistas desplazables. No registra credenciales ni duplica automáticamente solicitudes mutantes tras fallos de conexión. No permite operar desconectado. La interfaz PC permanece disponible.

## Desarrollo

1. Respaldar la base y ejecutar `php artisan migrate` para agregar tokens UUID compatibles con usuarios existentes.
2. En `mobile/`, ejecutar `pnpm install --frozen-lockfile` con pnpm 10.17.1 (la carpeta móvil fija esta versión; el proyecto web conserva la suya). El workspace móvil exige 30 días de antigüedad incluso para dependencias transitivas y deshabilita scripts de instalación.
3. Copiar `.env.example` a `.env` y establecer `EXPO_PUBLIC_API_URL` con URL HTTPS real que termine en `/api/v1`. Es configuración pública, nunca colocar secretos ahí. En desarrollo solamente se admite HTTP localhost / emulador Android 10.0.2.2.
4. Ejecutar `pnpm typecheck`, `pnpm export` y `pnpm start`. Para teléfono físico se necesita servidor HTTPS accesible, no localhost del PC.
5. Crear usuarios con correo/contraseña segura desde administración. La API no genera contraseñas ni convierte PIN en contraseña.
6. En producción establecer `APP_DEBUG=false`, TLS y proxies confiables explícitos. Activar el scheduler Laravel (`schedule:run`) para la limpieza diaria de tokens vencidos; revocar tokens inmediatamente al retirar dispositivos.

## APK, iOS y actualizaciones

No se ha generado una APK firmada ni iniciado una compilación remota. Faltan dominio HTTPS del backend, cuenta/proyecto Expo y revisión de identificadores `com.cywolf.restaurant.erp` (definirlos antes de primera publicación). Configurar `EAS_PROJECT_ID` y `EXPO_OWNER` desde el proyecto propio y una versión fijada de EAS CLI, autenticar, verificar `eas config`, después `eas build --platform android --profile preview`. El perfil preview produce APK interna; production produce AAB para Google Play. iOS necesita firma y distribución Apple propia, no utiliza APK.

EAS Update queda condicionado a un proyecto real: sin ID no hay URL ficticia de actualizaciones. Publicar primero en canal preview y luego production. `runtimeVersion: appVersion` exige incrementar la versión y recompilar cuando cambien módulos nativos; JavaScript/recursos compatibles pueden publicarse por OTA. Nunca usar OTA para cambiar automáticamente reglas fiscales sin revisión y pruebas. El backend debe mantener compatibilidad con clientes anteriores.

## Ruta para seguir avanzando

### Control de seguridad previo a distribución

La auditoría inicial encontró 6 avisos transitivos (4 altos, 2 moderados) exclusivamente en las rutas de herramientas `expo > @expo/cli` y `expo > @expo/config-plugins > xcode`: `brace-expansion`, `braces`, `node-forge`, `uuid`. No equivale a demostrar explotación ni a afirmar que el código instalado en el teléfono esté afectado. No se ocultan ni se fuerza un reemplazo mayor incompatible. Dos avisos no ofrecen versión corregida en el registro y el parche disponible de `brace-expansion` tiene menos de los 30 días exigidos. Revisar actualizaciones oficiales y compatibilidad antes de liberar.

`pnpm releasecheck` y el hook EAS `eas-build-post-install` ejecutan tipos y auditoría y bloquean distribución mientras fallen. Esta base es de desarrollo, no una entrega habilitada para producción. Las 477 versiones resueltas fueron comprobadas contra fechas del registro, sin versiones menores de 30 días. La recomendación online de Expo propone parches más nuevos; la comprobación local del SDK admite las versiones fijadas.

Resultados backend: 93 pruebas SQLite, 632 comprobaciones y una omitida específica de PostgreSQL; 46 pruebas PostgreSQL aisladas, 406 comprobaciones. TypeScript sin errores. Exportación local Android/iOS completada con Hermes (593/594 módulos), no equivale a una compilación nativa firmada. Metro emitió un aviso opcional de `@expo/metro-runtime` ausente; la plantilla nativa no lo incluye y ambos bundles finalizaron, pero debe revisarse al incorporar Router o soporte web. Migración de tokens aplicada tras backup. Validación visual en dispositivos físicos y APK firmada pendientes.

1. Validar dispositivos Android y tablet reales, tamaños de fuente accesibles y suspensión/reanudación; posteriormente firmar piloto interno.
2. Separar servicios de creación/edición/envío de pedidos de los controladores web y reutilizarlos en endpoints móviles. Añadir claves de idempotencia persistentes antes de habilitar mutaciones reintentables u operaciones offline.
3. Migrar mesas/catálogo/modificadores y operación de meseros con pruebas de precios, impuestos, inventario y permisos.
4. Migrar caja usando exclusivamente cálculo decimal, servicios transaccionales e instantáneas fiscales del servidor. Cancelar borrador no cancela venta cobrada; pendientes siguen incluidos en cierre. No habilitar envío DIAN hasta integrar y validar proveedor.
5. Escalar backend detrás de HTTPS con PHP workers, PostgreSQL, Redis compartido para límites/cache/colas y workers supervisados. El bloqueo POS actual prioriza consistencia; medir contención antes de particionarlo. Actualmente un restaurante: multitenencia requiere claves de organización, aislamiento y pruebas, no basta añadir pantallas.
6. Probar rotación/revocación de tokens, cache no-store, monitoreo sin datos sensibles, backups/restauración y compatibilidad API. Configurar proxies confiables explícitos en infraestructura; no exponer directamente el servidor local.

## Referencias oficiales

- https://laravel.com/docs/12.x/sanctum
- https://docs.expo.dev/build/internal-distribution/
- https://docs.expo.dev/eas-update/how-it-works/

Engram disponible en este checkout contiene únicamente el nombre del proyecto; no contiene un índice de código consultable. Se mantuvo como referencia y se inspeccionaron los archivos reales sin inventar resultados de memoria.
