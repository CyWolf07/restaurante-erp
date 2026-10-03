export class ApiError extends Error {
  constructor(message: string, public status: number) { super(message); }
}

export async function request<T>(path: string, token?: string, body?: unknown): Promise<T> {
  const base = process.env.EXPO_PUBLIC_API_URL?.replace(/\/$/, '');
  if (!base || (!base.startsWith('https://') && !(__DEV__ && /^http:\/\/(localhost|127\.0\.0\.1|10\.0\.2\.2)(:\d+)?\//.test(base)))) {
    throw new Error('Configura EXPO_PUBLIC_API_URL con HTTPS.');
  }
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15000);
  try {
    const response = await fetch(`${base}/${path}`, {
      method: body === undefined ? 'GET' : 'POST', signal: controller.signal,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    const data = response.status === 204 ? undefined : await response.json();
    if (!response.ok) throw new ApiError(data?.message ?? 'No se pudo completar la solicitud.', response.status);
    return data as T;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    if (error instanceof Error && error.name === 'AbortError') throw new Error('Tiempo de espera agotado. Actualiza antes de repetir una operación.');
    throw new Error('No se pudo conectar con el servidor.');
  } finally { clearTimeout(timeout); }
}
