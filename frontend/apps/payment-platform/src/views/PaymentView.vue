<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'

const route = useRoute()
const status = ref<'pending' | 'success' | 'error'>('pending')
const loading = ref(false)
const error = ref('')
const providerUrl = import.meta.env.VITE_PAYMENT_PROVIDER_URL ?? 'http://localhost:8080'

async function finalizePayment(result: 'success' | 'failed') {
  loading.value = true
  error.value = ''
  try {
    const endpoint = result === 'success' ? 'complete' : 'fail'
    const response = await fetch(`${providerUrl}/payments/${route.params.operationId}/${endpoint}`, { method: 'POST' })
    if (!response.ok) throw new Error(result === 'success' ? 'Не удалось подтвердить оплату.' : 'Не удалось отклонить оплату.')
    await response.json()
    status.value = result === 'success' ? 'success' : 'error'
    if (result === 'failed') error.value = 'Платёж отклонён. Курс не был приобретён.'
  } catch (exception) {
    status.value = 'error'
    error.value = exception instanceof Error ? exception.message : 'Не удалось выполнить оплату.'
  } finally { loading.value = false }
}
</script>

<template>
  <RouterLink to="/" class="fixed left-6 top-6 z-10 text-sm font-semibold text-indigo-600 transition hover:text-indigo-700">Платёжный кабинет <span aria-hidden="true">→</span></RouterLink>
  <main class="min-h-screen bg-slate-50 px-6 py-12"><div class="mx-auto max-w-lg"><div class="mb-8 text-center"><p class="text-sm font-semibold text-indigo-600">Безопасная оплата</p></div><section class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-200/60"><template v-if="status === 'pending'"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-xl font-bold text-indigo-600">UZS</div><h1 class="mt-6 text-2xl font-bold">Подтвердите оплату</h1><p class="mt-3 text-sm text-slate-500">Выберите результат операции для демонстрации платежа.</p><p class="mt-5 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-400">Операция: {{ route.params.operationId }}</p><div class="mt-6 grid gap-3 sm:grid-cols-2"><AppButton :disabled="loading" @click="finalizePayment('success')">{{ loading ? 'Обрабатываем…' : 'Подтвердить оплату' }}</AppButton><AppButton variant="secondary" :disabled="loading" @click="finalizePayment('failed')">Отклонить оплату</AppButton></div></template><template v-else-if="status === 'success'"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-2xl text-emerald-600">✓</div><h1 class="mt-6 text-2xl font-bold">Оплата прошла успешно</h1><p class="mt-3 text-sm text-slate-500">Результат передан платформе. Можно закрыть эту страницу.</p></template><template v-else><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-2xl text-red-600">!</div><h1 class="mt-6 text-2xl font-bold">Оплата отклонена</h1><p class="mt-3 text-sm text-red-600">{{ error }}</p><p class="mt-4 text-sm text-slate-500">Результат передан платформе.</p><AppButton variant="secondary" class="mt-6 w-full" @click="status = 'pending'">Повторить оплату</AppButton></template></section></div></main>
</template>
