import type { ExpoConfig } from 'expo/config';

const projectId = process.env.EAS_PROJECT_ID || 'cee99ce5-a923-4f60-b655-55912dd7c901';
if (process.env.EAS_BUILD === 'true' && (!projectId || !process.env.EXPO_PUBLIC_API_URL?.startsWith('https://'))) {
  throw new Error('Configura EAS_PROJECT_ID y EXPO_PUBLIC_API_URL HTTPS antes de compilar para distribución.');
}
if (process.env.EAS_BUILD === 'true') {
  const api = new URL(process.env.EXPO_PUBLIC_API_URL!);
  if (api.hostname.endsWith('.supabase.co') || api.pathname.replace(/\/$/, '') !== '/api/v1' || api.username || api.password || api.search || api.hash) {
    throw new Error('La compilación requiere la API Laravel HTTPS terminada en /api/v1, no la URL de Supabase ni credenciales en la URL.');
  }
}
const config: ExpoConfig = {
  name: 'Restaurant ERP', slug: 'cywolf', owner: process.env.EXPO_OWNER || 'cywolfs-team', version: '0.1.0',
  orientation: 'default', userInterfaceStyle: 'light',
  android: { package: 'com.cywolf.restaurant.erp' },
  ios: { bundleIdentifier: 'com.cywolf.restaurant.erp', supportsTablet: true },
  plugins: ['expo-secure-store'],
  runtimeVersion: { policy: 'appVersion' },
  ...(projectId ? { extra: { eas: { projectId } }, updates: { url: `https://u.expo.dev/${projectId}` } } : {}),
};
export default config;
