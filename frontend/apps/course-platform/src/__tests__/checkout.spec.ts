import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import CheckoutView from '../views/CheckoutView.vue'

const api = vi.hoisted(() => ({ create: vi.fn<() => Promise<unknown>>(), status: vi.fn<() => Promise<unknown>>(), show: vi.fn<() => Promise<unknown>>() }))
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ isAuthenticated: true, user: { role: 'student' } }) }))
vi.mock('../shared/api/courseApi', () => ({ courseApi: { show: api.show } }))
vi.mock('../shared/api/purchaseApi', () => ({ purchaseApi: api }))
afterEach(() => { vi.useRealTimers(); vi.clearAllMocks() })

describe('Checkout', () => {
  it('stays on the course platform and updates a successful payment in place', async () => {
    vi.useFakeTimers()
    api.show.mockResolvedValue({ id: 1, title: 'Курс', description: 'Описание', price: 100, is_purchased: false })
    api.status.mockResolvedValueOnce({ purchase: null, is_purchased: false })
      .mockResolvedValue({ purchase: { status: 'paid' }, is_purchased: true })
    api.create.mockResolvedValue({ payment_url: 'https://provider.test/pay/operation' })
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/checkout/:courseId', component: CheckoutView }] })
    await router.push('/checkout/1')
    const wrapper = mount(CheckoutView, { global: { plugins: [router] } })
    await flushPromises()
    expect(wrapper.text()).not.toContain('будете перенаправлены')
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Платёж ожидает подтверждения')
    await vi.advanceTimersByTimeAsync(3000)
    await flushPromises()
    expect(wrapper.text()).toContain('Оплата прошла успешно')
    expect(router.currentRoute.value.fullPath).toBe('/checkout/1')
    expect(wrapper.find('a[href*="provider.test"]').exists()).toBe(false)
    wrapper.unmount()
  })
})
