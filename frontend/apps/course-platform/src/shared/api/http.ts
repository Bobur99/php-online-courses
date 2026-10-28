const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api'

type ApiError = { message?: string; errors?: Record<string, string[]> }

const translatedMessages: Record<string, string> = {
  'Request failed': 'Не удалось выполнить запрос.',
  'The given data was invalid.': 'Проверьте правильность заполнения формы.',
  'Unauthenticated.': 'Срок действия сессии истёк. Войдите в аккаунт снова.',
  'This action is unauthorized.': 'У вас нет прав для выполнения этого действия.',
  'Not Found': 'Запрошенная страница или ресурс не найдены.',
  'Invalid credentials.': 'Неверная электронная почта или пароль.',
  'User is blocked.': 'Ваш аккаунт заблокирован. Обратитесь к администратору.',
  'Current password is incorrect.': 'Текущий пароль указан неверно.',
  'Course has already been purchased.': 'Этот курс уже приобретён.',
  'Only published courses can be purchased.': 'Купить можно только опубликованный курс.',
  'Payment provider request failed.': 'Платёжный сервис временно недоступен. Попробуйте ещё раз.',
  'There is already a payment awaiting confirmation.': 'Платёж уже ожидает подтверждения.',
}

const fieldLabels: Record<string, string> = {
  name: 'Имя',
  email: 'Электронная почта',
  password: 'Пароль',
  current_password: 'Текущий пароль',
  password_confirmation: 'Подтверждение пароля',
  title: 'Название курса',
  description: 'Описание',
  price: 'Стоимость',
  category_id: 'Категория',
  order_number: 'Порядковый номер',
  content: 'Содержание урока',
  file: 'Учебный материал',
  is_blocked: 'Статус блокировки',
  role: 'Роль',
}

function fieldLabel(attribute?: string): string {
  return attribute ? (fieldLabels[attribute] ?? attribute) : 'это поле'
}

export function translateValidationMessage(message: string): string {
  let match = message.match(/^The (.+) field confirmation does not match\.$/i)
  if (match) return match[1] === 'password'
    ? 'Пароль и подтверждение пароля не совпадают.'
    : `Поле «${fieldLabel(match[1])}» не совпадает с подтверждением.`

  match = message.match(/^The (.+) field is required\.$/i)
  if (match) return `Заполните поле «${fieldLabel(match[1])}».`

  match = message.match(/^The (.+) has already been taken\.$/i)
  if (match) return `Значение поля «${fieldLabel(match[1])}» уже используется.`

  match = message.match(/^The (.+?)(?: field)? must be at least (\d+) characters\.$/i)
  if (match) return `Поле «${fieldLabel(match[1])}» должно содержать не менее ${match[2]} символов.`

  match = message.match(/^The (.+?)(?: field)? may not be greater than (\d+) characters\.$/i)
  if (match) return `Поле «${fieldLabel(match[1])}» должно содержать не более ${match[2]} символов.`

  match = message.match(/^The (.+) field must be a valid email address\.$/i)
  if (match) return `Введите корректный адрес электронной почты.`

  match = message.match(/^The selected (.+) is invalid\.$/i)
  if (match) return `Выберите корректное значение поля «${fieldLabel(match[1])}».`

  match = message.match(/^The (.+) must be at least (\d+)\.$/i)
  if (match) return `Значение поля «${fieldLabel(match[1])}» должно быть не менее ${match[2]}.`

  match = message.match(/^The (.+) must be a string\.$/i)
  if (match) return `Поле «${fieldLabel(match[1])}» должно быть текстовым.`

  match = message.match(/^The (.+) must be an integer\.$/i)
  if (match) return `Поле «${fieldLabel(match[1])}» должно содержать целое число.`

  match = message.match(/^The (.+) field must be true or false\.$/i)
  if (match) return `Укажите корректное значение для поля «${fieldLabel(match[1])}».`

  match = message.match(/^The (.+) field must be a file of type: (.+)\.$/i)
  if (match) return `Файл в поле «${fieldLabel(match[1])}» должен быть одного из форматов: ${match[2]}.`

  match = message.match(/^The (.+) must be a file\.$/i)
  if (match) return `Загрузите корректный файл в поле «${fieldLabel(match[1])}».`

  match = message.match(/^The (.+) may not be greater than (\d+) kilobytes\.$/i)
  if (match) return `Размер файла в поле «${fieldLabel(match[1])}» не должен превышать ${match[2]} КБ.`

  return translatedMessages[message] ?? 'Не удалось выполнить запрос. Попробуйте ещё раз.'
}

function errorMessage(payload: ApiError): string {
  const validationMessage = payload.errors ? Object.values(payload.errors).flat()[0] : undefined
  const message = validationMessage ?? payload.message ?? 'Request failed'
  return translateValidationMessage(message)
}

export class HttpError extends Error {
  constructor(
    public readonly status: number,
    public readonly payload: ApiError,
  ) {
    super(status === 409 ? 'Изменение невозможно: запись используется или конфликтует с существующими данными.' : errorMessage(payload))
  }
}

export async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = localStorage.getItem('auth_token')
  const response = await safeFetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  })

  const payload = (await response.json().catch(() => ({}))) as T & ApiError
  if (!response.ok) {
    if (response.status === 401) localStorage.removeItem('auth_token')
    throw new HttpError(response.status, payload)
  }
  return payload as T
}

export async function download(path: string): Promise<Blob> {
  const token = localStorage.getItem('auth_token')
  const response = await safeFetch(`${API_BASE_URL}${path.replace(/^\/api/, '')}`, {
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

async function safeFetch(url: string, options: RequestInit): Promise<Response> {
  try {
    return await fetch(url, { ...options, signal: options.signal ?? AbortSignal.timeout(15000) })
  } catch {
    throw new Error('Не удалось связаться с сервером. Проверьте соединение и попробуйте ещё раз.')
  }
}
