const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api'

type ApiError = { message?: string; errors?: Record<string, string[]> }

const translatedMessages: Record<string, string> = {
  'Request failed': 'Не удалось выполнить запрос.',
  'Invalid credentials.': 'Неверная электронная почта или пароль.',
  'User is blocked.': 'Ваш аккаунт заблокирован. Обратитесь к администратору.',
  'Current password is incorrect.': 'Текущий пароль указан неверно.',
  'Course has already been purchased.': 'Этот курс уже приобретён.',
  'Only published courses can be purchased.': 'Купить можно только опубликованный курс.',
  'Payment provider request failed.': 'Платёжный сервис временно недоступен. Попробуйте ещё раз.',
}

function errorMessage(payload: ApiError): string {
  const validationMessage = payload.errors ? Object.values(payload.errors).flat()[0] : undefined
  const message = validationMessage ?? payload.message ?? 'Request failed'
  return translatedMessages[message] ?? message
}

export class HttpError extends Error {
  constructor(
    public readonly status: number,
    public readonly payload: ApiError,
  ) {
    super(errorMessage(payload))
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

export async function download(path: string): Promise<Blob> {
  const token = localStorage.getItem('auth_token')
  const response = await fetch(`${API_BASE_URL}${path.replace(/^\/api/, '')}`, {
    headers: {
      Accept: 'application/pdf',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  })

  if (!response.ok) {
    const payload = await response.json().catch(() => ({})) as ApiError
    throw new HttpError(response.status, payload)
  }

  return response.blob()
}
