export type Payment = {
  operation_id: string
  amount: number | string
  currency: string
  description: string
  status: 'pending' | 'success' | 'failed'
  created_at?: string
  webhook_delivered?: boolean
  webhook_attempts?: number
  webhook_http_status?: number | null
}

const providerUrl = import.meta.env.VITE_PAYMENT_PROVIDER_URL ?? 'http://localhost:8080'

export async function providerRequest<T>(path: string, method = 'GET'): Promise<T> {
  let response: Response
  try {
    response = await fetch(`${providerUrl}${path}`, { method, signal: AbortSignal.timeout(10000) })
  } catch {
    throw new Error('Не удалось связаться с платёжным сервисом. Проверьте соединение и попробуйте ещё раз.')
  }
  if (!response.ok) {
    const messages: Record<number, string> = {
      404: 'Платёж не найден. Проверьте номер операции.',
      409: 'Результат этой операции уже зафиксирован. Обновите данные платежа.',
      422: 'Некорректные данные платежа.',
      502: 'Результат платежа сохранён, но уведомление не доставлено. Повторите отправку уведомления.',
    }
    throw new Error(messages[response.status] ?? 'Не удалось выполнить запрос. Попробуйте ещё раз.')
  }
  try { return await response.json() as T }
  catch { throw new Error('Платёжный сервис вернул некорректный ответ. Попробуйте ещё раз.') }
}
