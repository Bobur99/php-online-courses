<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'
import { providerRequest, type Payment } from '../api'

const route = useRoute()
const payment = ref<Payment | null>(null)
const loading = ref(true)
const processing = ref(false)
const error = ref('')
const loadError = ref('')
let timer: ReturnType<typeof setInterval> | undefined
let disposed = false
let fetching = false
const amount = computed(() => payment.value
  ? `${new Intl.NumberFormat('ru-RU').format(Number(payment.value.amount))} ${payment.value.currency}` : '')

async function loadPayment(clearError = true) {
  if (fetching || disposed) return
  fetching = true
  if (clearError) error.value = ''
  try {
    const response = await providerRequest<{ payment: Payment }>(`/payments/${route.params.operationId}`)
    if (!response.payment || !['pending', 'success', 'failed'].includes(response.payment.status)) throw new Error('Платёжный сервис вернул некорректные данные.')
    if (!disposed) { payment.value = response.payment; loadError.value = '' }
  } catch (exception) {
    if (!disposed) loadError.value = exception instanceof Error ? exception.message : 'Не удалось загрузить платёж.'
  } finally { fetching = false; loading.value = false }
}

async function finalizePayment(result: 'success' | 'failed') {
  processing.value = true
  error.value = ''
  try {
    await providerRequest(`/payments/${route.params.operationId}/${result === 'success' ? 'complete' : 'fail'}`, 'POST')
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось обработать платёж.'
  } finally {
    await loadPayment(false)
    processing.value = false
  }
}

onMounted(async () => {
  await loadPayment()
  if (!disposed) timer = setInterval(() => {
    if (!processing.value) void loadPayment(false)
  }, 3000)
})
onBeforeUnmount(() => { disposed = true; if (timer) clearInterval(timer) })
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-6 py-8">
    <div class="mx-auto max-w-xl">
      <RouterLink to="/" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">← Платёжный кабинет</RouterLink>
      <section class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-xl shadow-slate-200/60 sm:p-8">
        <p class="text-sm font-semibold text-indigo-600">Тестовая платёжная операция</p>
        <p v-if="loading" class="mt-6 text-slate-500">Загружаем платёж…</p>
        <template v-else-if="payment">
          <h1 class="mt-6 text-2xl font-bold text-slate-900">{{ payment.status === 'pending' ? 'Подтвердите оплату' : payment.status === 'success' ? 'Оплата прошла успешно' : 'Платёж отклонён' }}</h1>
          <p class="mt-3 text-slate-600">{{ payment.description }}</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ amount }}</p>
          <p class="mt-4 break-all rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500">Операция: {{ route.params.operationId }}</p>
          <template v-if="payment.status === 'pending'">
            <p class="mt-5 text-sm text-slate-500">Выберите результат операции для демонстрации платежа.</p>
            <div class="mt-6 grid gap-3 sm:grid-cols-2">
              <AppButton :disabled="processing" @click="finalizePayment('success')">{{ processing ? 'Обрабатываем…' : 'Подтвердить оплату' }}</AppButton>
              <AppButton variant="secondary" :disabled="processing" @click="finalizePayment('failed')">Отклонить оплату</AppButton>
            </div>
          </template>
          <template v-else>
            <p v-if="payment.webhook_delivered === true" class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">Результат передан платформе. Можно закрыть эту страницу.</p>
            <div v-else class="mt-5 rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900">
              <p>Результат операции сохранён. Доставка уведомления платформе пока не подтверждена.</p>
              <AppButton variant="secondary" class="mt-4 w-full" :disabled="processing" @click="finalizePayment(payment.status)">{{ processing ? 'Отправляем…' : 'Повторить уведомление' }}</AppButton>
            </div>
          </template>
        </template>
        <h1 v-else class="mt-6 text-2xl font-bold text-slate-900">Платёж недоступен</h1>
        <p v-if="error || loadError" role="alert" class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ error || loadError }}</p>
        <button v-if="error || loadError" type="button" class="mt-4 text-sm font-semibold text-indigo-600" @click="loadPayment()">Обновить данные</button>
      </section>
    </div>
  </main>
</template>
