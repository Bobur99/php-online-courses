<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  if (!auth.isAuthenticated) {
    await router.replace({ path: '/login', query: { redirect: '/dashboard' } })
    return
  }

  try {
    await auth.loadProfile()
    if (auth.user?.role === 'admin') {
      await router.replace('/admin')
      return
    }
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить профиль.'
  } finally {
    loading.value = false
  }
})

</script>

<template>
  <section class="mx-auto max-w-5xl px-6 py-12">
    <div v-if="loading" class="text-sm text-slate-500">Загружаем профиль…</div>
    <div v-else-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ error }}
    </div>
    <template v-else-if="auth.user">
      <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
          <p class="text-sm font-semibold text-indigo-600">Личный кабинет</p>
          <h1 class="mt-2 text-4xl font-bold">Здравствуйте, {{ auth.user.name }}</h1>
          <p class="mt-3 text-slate-600">{{ auth.user.email }}</p>
          <span class="mt-3 inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
            {{ auth.user.role === 'admin' ? 'Администратор' : auth.user.role === 'teacher' ? 'Преподаватель' : 'Студент' }}
          </span>
        </div>
      </div>

      <div v-if="auth.user.role === 'teacher'" class="mt-10 grid gap-6">
        <RouterLink to="/teacher" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
          <p class="text-sm font-semibold text-indigo-600">Преподавание</p>
          <h2 class="mt-3 text-xl font-bold">Управление курсами</h2>
          <p class="mt-2 text-sm text-slate-500">Создавайте курсы, добавляйте уроки и публикуйте учебные материалы.</p>
        </RouterLink>
      </div>
      <div v-else class="mt-10 grid gap-6 md:grid-cols-3">
        <RouterLink to="/courses" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
          <p class="text-sm font-semibold text-indigo-600">Обучение</p>
          <h2 class="mt-3 text-xl font-bold">Мои курсы</h2>
          <p class="mt-2 text-sm text-slate-500">Открыть каталог и продолжить обучение.</p>
        </RouterLink>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <p class="text-sm font-semibold text-indigo-600">Прогресс</p>
          <h2 class="mt-3 text-xl font-bold">Мой прогресс</h2>
          <p class="mt-2 text-sm text-slate-500">Данные появятся после покупки курса.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <p class="text-sm font-semibold text-indigo-600">Сертификаты</p>
          <h2 class="mt-3 text-xl font-bold">Мои сертификаты</h2>
          <p class="mt-2 text-sm text-slate-500">Сертификаты появятся после завершения курса.</p>
        </div>
      </div>
    </template>
    <div v-else class="rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-600">
      Профиль не найден. Войдите в аккаунт ещё раз.
    </div>
  </section>
</template>
