# Desplegar el ERP Laravel en Render

El error `bash: docker: command not found` ocurre cuando un servicio Node intenta ejecutar `docker` como Start Command. Docker es el runtime que hay que seleccionar al crear el servicio, no un comando de inicio.

## Configuración del servicio

Subir `Dockerfile`, `.dockerignore`, `deploy/` y el cambio de proxies en `bootstrap/app.php` a la rama elegida. Crear un **Web Service** desde el repositorio del ERP, con **Language: Docker**, rama `main`, Root Directory vacío y Dockerfile Path `./Dockerfile`. Dejar Docker Command vacío. Health Check Path: `/up`. El servicio Node fallido puede conservarse hasta comprobar el nuevo; no es necesario borrarlo para desplegar.

Docker compila Vite con pnpm, instala Composer y ejecuta Apache/PHP con raíz pública `public/`, escuchando en el `PORT` de Render. No se usa el servidor de desarrollo de Artisan en producción. El contenedor no incorpora archivos `.env`, credenciales locales, bases SQLite ni logs.

## Variables privadas en Render

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:REEMPLAZAR_POR_UNA_CLAVE_REAL
APP_URL=https://NOMBRE-DEL-SERVICIO.onrender.com
APP_TIMEZONE=America/Bogota
LOG_CHANNEL=stderr
DB_CONNECTION=pgsql
DB_HOST=HOST_DEL_POSTGRES_O_POOLER
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=USUARIO_POSTGRES
DB_PASSWORD=CONTRASENA_POSTGRES
DB_SSLMODE=require
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
LOYALTY_ENABLED=false
```

Obtener los parámetros de PostgreSQL en la configuración de conexión de Supabase. La URL y la clave publicable de Supabase no sustituyen host/usuario/contraseña de PostgreSQL. Usar el pooler y puerto que indique el panel cuando el servidor directo no sea accesible. Guardar la contraseña exclusivamente en Render.

Para una instalación nueva generar una clave con `php artisan key:generate --show` y copiarla directamente al panel. Para migrar un ERP existente conservar su APP_KEY para poder leer sus datos cifrados. Nunca versionar la clave.

## Inicialización y comprobación

Antes de usar el ERP, aplicar las migraciones a la base seleccionada con `php artisan migrate --force` desde un proceso de despliegue o shell con las variables del servicio. Revisar y respaldar previamente una base existente. El arranque no ejecuta migraciones ni siembra usuarios automáticamente. Las cuentas deben existir en la base remota; cuentas de la base local no aparecen en Supabase por desplegar el código.

Comprobar `/up` y que `/api/v1/me`, sin sesión, devuelva JSON HTTP 401. Configurar en `mobile/.env`:

```dotenv
EXPO_PUBLIC_API_URL=https://NOMBRE-DEL-SERVICIO.onrender.com/api/v1
```

Reiniciar Expo con caché limpia o recompilar la APK para incorporar la URL. Las colas y cumpleaños necesitan un worker/scheduler separado; el servicio web solo atiende HTTP. Configurar almacenamiento persistente o Storage para archivos subidos y respaldos antes de depender de ellos, porque el filesystem del contenedor es efímero. La impresión Windows del ERP local necesita un agente local; no imprimirá directamente desde Render.

Referencias: [Docker en Render](https://render.com/docs/docker), [puertos del servicio web](https://render.com/docs/web-services#port-binding).
