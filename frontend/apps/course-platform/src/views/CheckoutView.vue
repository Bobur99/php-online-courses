<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'
import { courseApi, type Course } from '../shared/api/courseApi'
import { purchaseApi } from '../shared/api/purchaseApi'
import { useAuthStore } from '../stores/auth'

const route = useRoute(); const router = useRouter(); const auth = useAuthStore(); const course = ref<Course | null>(null); const loading = ref(true); const paying = ref(false); const error = ref(''); const purchaseStatus = ref<'pending' | 'failed' | null>(null); let statusTimer: ReturnType<typeof setInterval> | undefined
const statusError = ref('')
let checking = false
let disposed = false

async function refreshPurchaseStatus() {
  if (checking || disposed) return
  checking = true
  try {
  const courseId = Number(route.params.courseId)
  const response = await purchaseApi.status(courseId)
  if (disposed) return
  statusError.value = ''
  if (!response.purchase) return
  purchaseStatus.value = response.purchase.status === 'pending' || response.purchase.status === 'failed' ? response.purchase.status : null
  if (response.is_purchased) {
    purchaseStatus.value = null
    if (course.value) course.value.is_purchased = true
    if (statusTimer) clearInterval(statusTimer)
  }
  if (purchaseStatus.value === 'failed' && statusTimer) clearInterval(statusTimer)
  } finally { checking = false }
}

function startStatusPolling() {
  if (disposed) return
  if (statusTimer) clearInterval(statusTimer)
  statusTimer = setInterval(() => {
    refreshPurchaseStatus().catch(() => { statusError.value = 'Не удалось обновить статус. Проверим ещё раз автоматически.' })
  }, 3000)
}
onMounted(async () => {
  if (!auth.isAuthenticated) {
    await router.replace({ path: '/login', query: { redirect: route.fullPath } })
    return
  }
  try {
    if (!auth.user) await auth.loadProfile()
    if (auth.user?.role !== 'student') {
      await router.replace(`/courses/${route.params.courseId}`)
      return
    }
    course.value = await courseApi.show(Number(route.params.courseId))
    await refreshPurchaseStatus()
    if (purchaseStatus.value === 'pending') startStatusPolling()
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить оформление заказа.'
  } finally {
    loading.value = false
  }
})
async function startPayment() { if (!course.value) return; paying.value = true; error.value = ''; try { await purchaseApi.create(course.value.id); purchaseStatus.value = 'pending'; startStatusPolling() } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Не удалось начать оплату.' } finally { paying.value = false } }

onBeforeUnmount(() => { disposed = true; if (statusTimer) clearInterval(statusTimer) })
</script>

<template>
  <section class="mx-auto max-w-2xl px-6 py-10 sm:py-14">
    <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500">Готовим оформление заказа…</div>
    <div v-else-if="error && !course" class="rounded-2xl bg-red-50 px-5 py-4 text-sm text-red-700">{{ error }}</div>
    <div v-else-if="course" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
      <div class="border-b border-slate-100 bg-slate-50 px-8 py-7 sm:px-10">
        <p class="text-sm font-semibold text-indigo-600">Оформление заказа</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight">Подтвердите покупку</h1>
        <p class="mt-2 text-sm text-slate-500">Проверьте данные курса перед оформлением покупки.</p>
      </div>
      <div class="px-8 py-8 sm:px-10">
        <p v-if="error || statusError" role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ error || statusError }}</p>
        <div class="flex items-start gap-4">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-lg font-bold text-indigo-600">{{ course.title.slice(0, 1).toUpperCase() }}</span>
          <div class="min-w-0"><h2 class="text-xl font-bold">{{ course.title }}</h2><p class="mt-2 text-sm leading-6 text-slate-500">{{ course.description }}</p></div>
        </div>
        <div class="my-8 flex items-center justify-between border-y border-slate-200 py-5"><span class="text-sm font-medium text-slate-500">Итого к оплате</span><span class="text-2xl font-bold text-slate-900">{{ course.price.toLocaleString() }} UZS</span></div>
        <template v-if="course.is_purchased">
          <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5 text-center"><p class="font-semibold text-emerald-800">Оплата прошла успешно</p><p class="mt-1 text-sm text-emerald-700">Материалы доступны на странице курса.</p></div>
          <RouterLink :to="`/courses/${course.id}`" class="mt-4 flex h-11 items-center justify-center rounded-xl bg-indigo-600 text-sm font-semibold text-white">Перейти к обучению</RouterLink>
        </template>
        <template v-else-if="purchaseStatus === 'pending'">
          <div class="rounded-2xl border border-amber-100 bg-amber-50 px-5 py-6 text-center"><p class="font-semibold text-amber-900">Платёж ожидает подтверждения</p></div>
        </template>
        <template v-else-if="purchaseStatus === 'failed'">
          <div class="rounded-2xl border border-red-100 bg-red-50 p-5 text-center"><p class="font-semibold text-red-800">Платёж не завершён</p><p class="mt-1 text-sm text-red-700">Операция была отклонена платёжным провайдером.</p></div>
          <AppButton class="mt-4 w-full" :disabled="paying" @click="startPayment">{{ paying ? 'Повторяем оплату…' : 'Повторить оплату' }}</AppButton>
        </template>
        <template v-else>
          <AppButton class="w-full" :disabled="paying" @click="startPayment">{{ paying ? 'Оформляем покупку…' : 'Подтвердить покупку' }}</AppButton>
        </template>
      </div>
    </div>
  </section>
</template>
