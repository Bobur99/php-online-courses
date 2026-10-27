<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

type Payment = {
  operation_id: string
  amount: number | string
  currency: string
  description: string
  status: 'pending' | 'success' | 'failed'
  created_at?: string
}

const providerUrl = import.meta.env.VITE_PAYMENT_PROVIDER_URL ?? 'http://localhost:8080'
const payments = ref<Payment[]>([])
const loading = ref(true)
const error = ref('')
let refreshTimer: ReturnType<typeof setInterval> | undefined

const stats = computed(() => ({
  total: payments.value.length,
  pending: payments.value.filter((payment) => payment.status === 'pending').length,
  success: payments.value.filter((payment) => payment.status === 'success').length,
  failed: payments.value.filter((payment) => payment.status === 'failed').length,
}))

const totalAmount = computed(() => payments.value
  .filter((payment) => payment.status === 'success')
  .reduce((sum, payment) => sum + Number(payment.amount), 0))

function formatAmount(payment: Payment) {
  return `${new Intl.NumberFormat('ru-RU').format(Number(payment.amount))} ${payment.currency}`
}

function formatDate(value?: string) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function statusLabel(status: Payment['status']) {
  return { pending: 'Ожидает подтверждения', success: 'Успешно', failed: 'Отклонено' }[status]
}

async function loadPayments() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch(`${providerUrl}/payments`)
    if (!response.ok) throw new Error('Не удалось загрузить операции.')
    const data = await response.json() as { payments: Payment[] }
    payments.value = data.payments
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить операции.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadPayments()
  refreshTimer = setInterval(() => {
    loadPayments().catch(() => undefined)
  }, 3000)
})

onBeforeUnmount(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-8 lg:px-12">
    <div class="mx-auto max-w-6xl">
      <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-sm font-semibold text-indigo-600">Mock Payment Provider</p>
          <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Платёжный кабинет</h1>
          <p class="mt-2 text-slate-500">Контролируйте операции и подтверждайте тестовые платежи.</p>
        </div>
        <button class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" @click="loadPayments">Обновить</button>
      </header>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="item in [{ label: 'Всего операций', value: stats.total }, { label: 'Ожидают решения', value: stats.pending }, { label: 'Успешные', value: stats.success }, { label: 'Оборот', value: `${new Intl.NumberFormat('ru-RU').format(totalAmount)} UZS` }]" :key="item.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm text-slate-500">{{ item.label }}</p><p class="mt-2 text-2xl font-bold text-slate-900">{{ item.value }}</p>
        </div>
      </div>

      <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-900">История операций</h2><p class="mt-1 text-sm text-slate-500">Все тестовые платежи из текущей сессии провайдера.</p></div>
        <div v-if="loading" class="px-5 py-12 text-center text-slate-500">Загрузка операций…</div>
        <div v-else-if="error" class="px-5 py-12 text-center text-red-600">{{ error }}</div>
        <div v-else-if="payments.length === 0" class="px-5 py-12 text-center text-slate-500">Операций пока нет.</div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="payment in payments" :key="payment.operation_id" class="flex flex-col gap-4 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0"><p class="font-semibold text-slate-900">{{ payment.description }}</p><p class="mt-1 truncate text-xs text-slate-400">{{ payment.operation_id }} · {{ formatDate(payment.created_at) }}</p></div>
            <div class="flex items-center gap-4"><div class="text-right"><p class="font-semibold text-slate-900">{{ formatAmount(payment) }}</p><span class="text-xs font-medium" :class="{ 'text-amber-600': payment.status === 'pending', 'text-emerald-600': payment.status === 'success', 'text-red-600': payment.status === 'failed' }">{{ statusLabel(payment.status) }}</span></div><RouterLink v-if="payment.status === 'pending'" :to="`/pay/${payment.operation_id}`" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">Открыть</RouterLink><span v-else class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-500">Завершено</span></div>
          </div>
        </div>
      </section>
    </div>
  </main>
</template>
