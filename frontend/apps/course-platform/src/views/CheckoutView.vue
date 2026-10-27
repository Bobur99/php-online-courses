<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'
import { courseApi, type Course } from '../shared/api/courseApi'
import { purchaseApi } from '../shared/api/purchaseApi'
import { useAuthStore } from '../stores/auth'

const route = useRoute(); const router = useRouter(); const auth = useAuthStore(); const course = ref<Course | null>(null); const loading = ref(true); const paying = ref(false); const error = ref('')
onMounted(async () => { if (!auth.isAuthenticated) { await router.replace('/login'); return } try { course.value = await courseApi.show(Number(route.params.courseId)) } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить оформление заказа.' } finally { loading.value = false } })
async function startPayment() { if (!course.value) return; paying.value = true; error.value = ''; try { const response = await purchaseApi.create(course.value.id); window.location.href = response.payment_url } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Не удалось начать оплату.'; paying.value = false } }
</script>

<template>
  <section class="mx-auto max-w-2xl px-6 py-10 sm:py-14">
    <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500">Готовим оформление заказа…</div>
    <div v-else-if="error" class="rounded-2xl bg-red-50 px-5 py-4 text-sm text-red-700">{{ error }}</div>
    <div v-else-if="course" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
      <div class="border-b border-slate-100 bg-slate-50 px-8 py-7 sm:px-10">
        <p class="text-sm font-semibold text-indigo-600">Оформление заказа</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight">Подтвердите покупку</h1>
        <p class="mt-2 text-sm text-slate-500">Проверьте данные курса перед переходом к оплате.</p>
      </div>
      <div class="px-8 py-8 sm:px-10">
        <div class="flex items-start gap-4">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-lg font-bold text-indigo-600">{{ course.title.slice(0, 1).toUpperCase() }}</span>
          <div class="min-w-0"><h2 class="text-xl font-bold">{{ course.title }}</h2><p class="mt-2 text-sm leading-6 text-slate-500">{{ course.description }}</p></div>
        </div>
        <div class="my-8 flex items-center justify-between border-y border-slate-200 py-5"><span class="text-sm font-medium text-slate-500">Итого к оплате</span><span class="text-2xl font-bold text-slate-900">{{ course.price.toLocaleString() }} UZS</span></div>
        <AppButton class="w-full" :disabled="paying" @click="startPayment">{{ paying ? 'Переходим к оплате…' : 'Перейти к безопасной оплате' }}</AppButton>
        <p class="mt-4 text-center text-xs text-slate-500">Вы будете перенаправлены на защищённую страницу платёжного провайдера.</p>
      </div>
    </div>
  </section>
</template>
