import { afterEach, describe, expect, it, vi } from 'vitest'
import { request, translateValidationMessage } from '../shared/api/http'

afterEach(() => vi.unstubAllGlobals())

describe('API error translation', () => {
  it.each([
    ['The password field confirmation does not match.', 'Пароль и подтверждение пароля не совпадают.'],
    ['The password field must be at least 8 characters.', 'Поле «Пароль» должно содержать не менее 8 символов.'],
    ['The email field must be a valid email address.', 'Введите корректный адрес электронной почты.'],
    ['The email has already been taken.', 'Значение поля «Электронная почта» уже используется.'],
    ['The price must be at least 0.', 'Значение поля «Стоимость» должно быть не менее 0.'],
    ['The file field must be a file of type: pdf, jpg.', 'Файл в поле «Учебный материал» должен быть одного из форматов: pdf, jpg.'],
  ])('translates: %s', (message, expected) => {
    expect(translateValidationMessage(message)).toBe(expected)
  })

  it('does not expose an unknown English server message', () => {
    expect(translateValidationMessage('Unexpected server failure.')).toBe(
      'Не удалось выполнить запрос. Попробуйте ещё раз.',
    )
  })

  it('shows a Russian network error', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))
    await expect(request('/courses')).rejects.toThrow('Не удалось связаться с сервером. Проверьте соединение и попробуйте ещё раз.')
  })
})
