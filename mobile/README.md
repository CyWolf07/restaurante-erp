# Restaurant ERP móvil

Base nativa Android/iOS de desarrollo. No contiene APK firmada ni credenciales.

Consulta [arquitectura y siguientes etapas](../docs/app-movil-escalable.md).

## Comandos desde la raíz del repositorio

La app Expo está en `mobile/`, junto con `app.config.ts` y `eas.json`. Ejecutar `eas build` directamente desde la raíz Laravel o desde `/` no selecciona esa app. No duplicar `eas.json` en la raíz: los siguientes accesos directos cambian automáticamente a `mobile/`.

```powershell
Set-Location E:\Programas\restaurant-erp
pnpm expo:info       # Comprueba el proyecto EAS vinculado
pnpm expo:check      # Comprueba tipos y pruebas de URL
pnpm expo:start      # Inicia Expo
pnpm expo:android    # Inicia Expo para Android/emulador
```

Cuando estén resueltos el servidor HTTPS y las alertas de seguridad:

```powershell
pnpm expo:apk       # Perfil preview: APK interna
pnpm expo:aab       # Perfil production: Android App Bundle
```

Estos comandos ejecutan primero `releasecheck`; si falla la auditoría, no solicitan una compilación remota. La CLI `eas` debe estar instalada y autenticada (en este equipo se verificó EAS CLI 24.8.0). No incluyen publicación automática ni cambios de base de datos.

`mobile/eas.json` ya existe y está versionado. Solo si hay que regenerar o configurar EAS, usar `pnpm expo:configure`, que ejecuta `eas build:configure` **dentro de mobile**; revisar cualquier cambio que proponga. Para comandos manuales:

```powershell
Set-Location E:\Programas\restaurant-erp\mobile
eas project:info
# eas build:configure   # Solo cuando haga falta configurar
```

Referencia: [EAS en repositorios con varias aplicaciones](https://docs.expo.dev/build-reference/build-with-monorepos/).

## Instalación y desarrollo dentro de mobile

Los comandos siguientes se ejecutan desde `E:\Programas\restaurant-erp\mobile`, no desde la raíz Laravel:

```powershell
pnpm install --frozen-lockfile
pnpm typecheck
pnpm export
pnpm start
```

Configurar `.env` siguiendo `.env.example` antes de conectar con el ERP. El backend debe estar actualizado y servido por HTTPS. No habilitar operaciones financieras offline ni transmisión fiscal desde este cliente inicial.

Proyecto configurado bajo el equipo `cywolfs-team`, con sesión `cywolf`. Consultar [estado de Expo y Supabase](../docs/conexion-expo-supabase.md). La URL de Supabase no debe utilizarse como `EXPO_PUBLIC_API_URL`: hace falta la URL del servidor Laravel. `pnpm test` verifica esta separación; los tests usan un Node con soporte de eliminación de tipos TypeScript (validado con Node 24).

`src/api/` concentra transporte y contratos; `src/auth/` conserva tokens en el almacén seguro del dispositivo. La API `/api/v1` es la autoridad de roles, estados e inventario. Los nuevos módulos deben reutilizar servicios del servidor y agregar pruebas antes de habilitar capacidades.

La política de 30 días puede mantener parches anteriores a las recomendaciones online de Expo. No actualizar automáticamente: verificar SDK, changelog, antigüedad y dispositivos antes de publicar.
