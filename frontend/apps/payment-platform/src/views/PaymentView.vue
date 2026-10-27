<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'

const route = useRoute()
const status = ref<'pending' | 'success' | 'error'>('pending')
const loading = ref(false)
const error = ref('')
const providerUrl = import.meta.env.VITE_PAYMENT_PROVIDER_URL ?? 'http://localhost:8080'

async function completePayment() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch(`${providerUrl}/payments/${route.params.operationId}/complete`, { method: 'POST' })
    if (!response.ok) throw new Error('Провайдер отклонил платёж.')
    status.value = 'success'
  } catch (exception) {
    status.value = 'error'
    error.value = exception instanceof Error ? exception.message : 'Не удалось выполнить оплату.'
  } finally { loading.value = false }
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-6 py-12"><div class="mx-auto max-w-lg"><div class="mb-8 text-center"><p class="text-sm text-slate-500">Безопасная оплата</p></div><section class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm"><template v-if="status === 'pending'"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-2xl">₿</div><h1 class="mt-6 text-2xl font-bold">Подтвердите оплату</h1><p class="mt-3 text-sm text-slate-500">Операция: {{ route.params.operationId }}</p><AppButton class="mt-8 w-full" :disabled="loading" @click="completePayment">{{ loading ? 'Обрабатываем…' : 'Подтвердить оплату' }}</AppButton></template><template v-else-if="status === 'success'"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-50 text-2xl text-green-600">✓</div><h1 class="mt-6 text-2xl font-bold">Оплата прошла успешно</h1><p class="mt-3 text-sm text-slate-500">Покупка подтверждена.</p></template><template v-else><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-2xl text-red-600">!</div><h1 class="mt-6 text-2xl font-bold">Оплата не выполнена</h1><p class="mt-3 text-sm text-red-600">{{ error }}</p><AppButton class="mt-8" @click="status = 'pending'">Повторить</AppButton></template></section></div></main>
</template>
