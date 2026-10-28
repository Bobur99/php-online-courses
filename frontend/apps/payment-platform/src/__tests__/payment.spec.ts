import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import PaymentView from '../views/PaymentView.vue'

afterEach(() => vi.unstubAllGlobals())

async function page() {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: { template: '<div>Кабинет</div>' } }, { path: '/pay/:operationId', component: PaymentView }] })
  await router.push('/pay/operation-test')
  const wrapper = mount(PaymentView, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}

describe('Provider payment', () => {
  it('loads an already completed payment without redirecting or offering another decision', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ payment: { operation_id: 'operation-test', status: 'success', webhook_delivered: true, description: 'Курс', amount: 100, currency: 'UZS' } }) }))
    const { wrapper, router } = await page()
    expect(wrapper.text()).toContain('Оплата прошла успешно')
    expect(wrapper.text()).not.toContain('Подтвердить оплату')
    expect(router.currentRoute.value.fullPath).toBe('/pay/operation-test')
    wrapper.unmount()
  })

  it('does not claim rejection or webhook delivery when the network fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))
    const { wrapper } = await page()
    expect(wrapper.text()).not.toContain('Failed to fetch')
    expect(wrapper.text()).not.toContain('Оплата отклонена')
    expect(wrapper.text()).not.toContain('Результат передан платформе.')
    expect(wrapper.text()).toContain('Не удалось связаться')
    wrapper.unmount()
  })

  it('offers webhook retry without changing the payment result', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ payment: { status: 'success', webhook_delivered: false, description: 'Курс', amount: 100, currency: 'UZS' } }) }))
    const { wrapper } = await page()
    expect(wrapper.text()).toContain('Повторить уведомление')
    expect(wrapper.text()).not.toContain('Подтвердить оплату')
    wrapper.unmount()
  })
})
