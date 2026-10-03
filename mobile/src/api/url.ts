export function resolveApiUrl(raw: string | undefined, development: boolean): string {
  if (!raw?.trim()) throw new Error('Falta EXPO_PUBLIC_API_URL: indica la URL HTTPS de la API Laravel.');
  let url: URL;
  try { url = new URL(raw.trim()); }
  catch { throw new Error('EXPO_PUBLIC_API_URL no es una URL válida.'); }
  if (url.hostname === 'supabase.co' || url.hostname.endsWith('.supabase.co')) {
    throw new Error('La URL de Supabase no es la API Laravel. Configura el servidor del ERP con /api/v1.');
  }
  const local = development && url.protocol === 'http:' && ['localhost', '127.0.0.1', '10.0.2.2'].includes(url.hostname);
  if (url.protocol !== 'https:' && !local) throw new Error('La API Laravel requiere HTTPS.');
  if (url.username || url.password || url.search || url.hash) throw new Error('La URL de la API no debe contener credenciales, parámetros ni fragmentos.');
  if (url.pathname.replace(/\/$/, '') !== '/api/v1') throw new Error('La URL de la API Laravel debe terminar en /api/v1.');
  return url.toString().replace(/\/$/, '');
}
