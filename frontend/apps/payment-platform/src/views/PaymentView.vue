<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'

const route = useRoute()
const status = ref<'pending' | 'success' | 'error'>('pending')
const loading = ref(false)
const error = ref('')
const returnUrl = ref(import.meta.env.VITE_COURSE_PLATFORM_URL ?? 'http://localhost:5174/courses')
const providerUrl = import.meta.env.VITE_PAYMENT_PROVIDER_URL ?? 'http://localhost:8080'

async function finalizePayment(result: 'success' | 'failed') {
  loading.value = true
  error.value = ''
  try {
    const endpoint = result === 'success' ? 'complete' : 'fail'
    const response = await fetch(`${providerUrl}/payments/${route.params.operationId}/${endpoint}`, { method: 'POST' })
    if (!response.ok) throw new Error(result === 'success' ? 'Не удалось подтвердить оплату.' : 'Не удалось отклонить оплату.')
    const responseData = await response.json() as { return_url?: string }
    if (responseData.return_url) returnUrl.value = responseData.return_url
    status.value = result === 'success' ? 'success' : 'error'
    if (result === 'failed') error.value = 'Платёж отклонён. Курс не был приобретён.'
  } catch (exception) {
    status.value = 'error'
    error.value = exception instanceof Error ? exception.message : 'Не удалось выполнить оплату.'
  } finally { loading.value = false }
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-6 py-12"><div class="mx-auto max-w-lg"><div class="mb-8 text-center"><p class="text-sm font-semibold text-indigo-600">Безопасная оплата</p></div><section class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-200/60"><template v-if="status === 'pending'"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-xl font-bold text-indigo-600">UZS</div><h1 class="mt-6 text-2xl font-bold">Подтвердите оплату</h1><p class="mt-3 text-sm text-slate-500">Выберите результат операции для демонстрации платежа.</p><p class="mt-5 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-400">Операция: {{ route.params.operationId }}</p><div class="mt-6 grid gap-3 sm:grid-cols-2"><AppButton :disabled="loading" @click="finalizePayment('success')">{{ loading ? 'Обрабатываем…' : 'Подтвердить оплату' }}</AppButton><AppButton variant="secondary" :disabled="loading" @click="finalizePayment('failed')">Отклонить оплату</AppButton></div></template><template v-else-if="status === 'success'"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-2xl text-emerald-600">✓</div><h1 class="mt-6 text-2xl font-bold">Оплата прошла успешно</h1><p class="mt-3 text-sm text-slate-500">Курс добавлен в ваш личный кабинет.</p><a :href="returnUrl" class="mt-8 inline-flex h-11 w-full items-center justify-center rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white transition hover:bg-indigo-700">Перейти к курсу</a></template><template v-else><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-2xl text-red-600">!</div><h1 class="mt-6 text-2xl font-bold">Оплата отклонена</h1><p class="mt-3 text-sm text-red-600">{{ error }}</p><a :href="returnUrl" class="mt-8 inline-flex h-11 w-full items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Вернуться к курсу</a><AppButton variant="secondary" class="mt-3" @click="status = 'pending'">Повторить оплату</AppButton></template></section></div></main>
</template>
