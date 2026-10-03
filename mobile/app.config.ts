import type { ExpoConfig } from 'expo/config';

const projectId = process.env.EAS_PROJECT_ID;
if (process.env.EAS_BUILD === 'true' && (!projectId || !process.env.EXPO_PUBLIC_API_URL?.startsWith('https://'))) {
  throw new Error('Configura EAS_PROJECT_ID y EXPO_PUBLIC_API_URL HTTPS antes de compilar para distribución.');
}
const config: ExpoConfig = {
  name: 'Restaurant ERP', slug: 'restaurant-erp', version: '0.1.0',
  orientation: 'default', userInterfaceStyle: 'light',
  android: { package: 'com.cywolf.restaurant.erp' },
  ios: { bundleIdentifier: 'com.cywolf.restaurant.erp', supportsTablet: true },
  plugins: ['expo-secure-store'],
  runtimeVersion: { policy: 'appVersion' },
  ...(process.env.EXPO_OWNER ? { owner: process.env.EXPO_OWNER } : {}),
  ...(projectId ? { extra: { eas: { projectId } }, updates: { url: `https://u.expo.dev/${projectId}` } } : {}),
};
export default config;
