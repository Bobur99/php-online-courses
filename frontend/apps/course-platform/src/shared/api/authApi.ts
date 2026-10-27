import { request } from './http'

export type User = { id: number; name: string; email: string; role: string; is_blocked: boolean }
export type AuthResponse = { user: User; token: string }

type AuthPayload = AuthResponse | { data: AuthResponse } | { user: User; access_token: string }

function normalizeAuthResponse(response: AuthPayload): AuthResponse {
  if ('data' in response) return response.data
  if ('access_token' in response) return { user: response.user, token: response.access_token }
  return response
}

export const authApi = {
  register: async (payload: { name: string; email: string; password: string; password_confirmation: string }) =>
    normalizeAuthResponse(await request<AuthPayload>('/auth/register', { method: 'POST', body: JSON.stringify(payload) })),
  login: (payload: { email: string; password: string }) =>
    request<AuthPayload>('/auth/login', { method: 'POST', body: JSON.stringify(payload) }).then(normalizeAuthResponse),
  profile: () => request<User>('/profile'),
  updateProfile: (payload: { name: string; email: string }) =>
    request<User>('/profile', { method: 'PATCH', body: JSON.stringify(payload) }),
  changePassword: (payload: { current_password: string; password: string; password_confirmation: string }) =>
    request<{ message: string }>('/profile/password', { method: 'PATCH', body: JSON.stringify(payload) }),
  logout: () => request<void>('/auth/logout', { method: 'POST' }),
}
