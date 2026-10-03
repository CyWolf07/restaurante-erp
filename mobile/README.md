# Restaurant ERP móvil

Base nativa Android/iOS de desarrollo. No contiene APK firmada ni credenciales.

Consulta [arquitectura y siguientes etapas](../docs/app-movil-escalable.md).

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
