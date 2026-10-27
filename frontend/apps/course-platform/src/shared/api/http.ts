const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api'

type ApiError = { message?: string; errors?: Record<string, string[]> }

export class HttpError extends Error {
  constructor(
    public readonly status: number,
    public readonly payload: ApiError,
  ) {
    super(payload.message ?? 'Request failed')
  }
}

export async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = localStorage.getItem('auth_token')
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  })

  const payload = (await response.json().catch(() => ({}))) as T & ApiError
  if (!response.ok) throw new HttpError(response.status, payload)
  return payload as T
}
