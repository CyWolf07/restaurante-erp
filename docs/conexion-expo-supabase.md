# Vinculación Expo y conexión Supabase — 2026-10-03

## Datos públicos recibidos

- Cuenta Expo: `cywolf`.
- Proyecto indicado: `@cywolf/restaurante_erp`. EAS verificó que el Project ID pertenece al equipo `cywolfs-team` y tiene slug `cywolf`; su identidad real es `@cywolfs-team/cywolf`. Se ajustaron owner y slug a ese ID, sin renombrar ni transferir el proyecto. Confirmar que este es el proyecto deseado antes de publicar actualizaciones o compilar.
- EAS Project ID: `cee99ce5-a923-4f60-b655-55912dd7c901`.
- Supabase Project URL: `https://pizpdvfdnlvasqvtjbiv.supabase.co` (sin barra invertida).
- Repositorio de este checkout: `https://github.com/CyWolf07/restaurante-erp.git`. El enlace adicional a `jdvillacorte/MyApp` no cambia el remoto ni autoriza mezclar ambos proyectos.

## Arquitectura conservada

Android/iOS → API Laravel HTTPS `/api/v1` → PostgreSQL Supabase.

La URL Supabase es de sus servicios/Data API. No aloja los endpoints PHP `/api/v1/auth/login`, pedidos o cierres implementados en este ERP. `NEXT_PUBLIC_SUPABASE_URL` es una variable de Next.js y no configura este cliente Expo ni Laravel. El cliente móvil únicamente necesita `EXPO_PUBLIC_API_URL` apuntando a Laravel; no necesita contraseña PostgreSQL ni `service_role`.

## Estado verificado

La sesión EAS disponible pertenece a `cywolf`, con acceso Owner al equipo `cywolfs-team`. Se configuraron slug, owner, project ID y URL de actualizaciones. El `.env` del ERP sigue en `APP_ENV=local`, API `http://127.0.0.1:8000` y PostgreSQL `127.0.0.1:5432/restaurant_erp`: proporcionar una URL cloud no migra esta base automáticamente.

El conector Supabase accesible en esta sesión no lista el proyecto solicitado. No se ejecutaron consultas, migraciones ni cambios en otro proyecto, y no se sustituyeron las credenciales locales. Falta acceder al proyecto correcto para comprobar tablas, RLS, permisos, versión y existencia de datos.

## Lo que falta

1. URL HTTPS o decisión de alojamiento para el backend Laravel. Configurar PHP, TLS, colas, scheduler y secretos en el proveedor elegido, sin exponer el PC ni publicar credenciales.
2. Autorizar/conectar la cuenta u organización Supabase que contiene `pizpdvfdnlvasqvtjbiv`, o confirmar si la referencia es otra.
3. Copiar desde **Connect** en Supabase los datos PostgreSQL correctos para el servidor. Usar conexión directa si el alojamiento soporta la red indicada; session pooler para IPv4 cuando corresponda. No inventar el host del pooler. Colocar contraseña únicamente en secretos del servidor, no en chat, Git ni Expo. Activar TLS (mínimo `require`, preferible `verify-full` con certificado configurado).
4. Antes de cambiar la base: confirmar si contiene ventas reales, backup y plan de importación/restauración. No usar `migrate:fresh`, borrar tablas ni importar encima de datos existentes. Revisar versiones/extensiones afectadas por el changelog y verificar seguridad de esquemas expuestos, RLS y permisos; no dar acceso Data API público a tablas del ERP.
5. Resolver o revisar formalmente las alertas de herramientas documentadas en `app-movil-escalable.md`. El control de publicación permanece activo. Probar Android/tablet y completar firma antes de una APK de distribución.

Referencias oficiales: [Conexiones PostgreSQL](https://supabase.com/docs/guides/database/connecting-to-postgres), [Configuración Expo](https://docs.expo.dev/versions/latest/config/app/).
