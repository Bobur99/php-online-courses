import { request } from './http'

export type PurchaseResponse = { purchase: { id: number; status: string; provider_operation_id: string }; payment_url: string }
export type PurchaseStatusResponse = { purchase: PurchaseResponse['purchase'] | null; is_purchased: boolean }

export const purchaseApi = {
  create: (courseId: number) => request<PurchaseResponse>(`/courses/${courseId}/purchase`, { method: 'POST' }),
  status: (courseId: number) => request<PurchaseStatusResponse>(`/courses/${courseId}/purchase`),
}
