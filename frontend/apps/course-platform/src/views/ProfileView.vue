<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppButton from '../components/ui/AppButton.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const editing = ref(false)
const saving = ref(false)
const error = ref('')
const name = ref('')
const email = ref('')

const initials = () => auth.user?.name?.trim().split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase() || 'П'
const roleLabel = () => auth.user?.role === 'admin' ? 'Администратор' : auth.user?.role === 'teacher' ? 'Преподаватель' : 'Студент'

onMounted(async () => {
  try {
    await auth.loadProfile()
    name.value = auth.user?.name ?? ''
    email.value = auth.user?.email ?? ''
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить профиль.'
  }
})

async function save() {
  saving.value = true
  error.value = ''
  try {
    await auth.updateProfile(name.value, email.value)
    editing.value = false
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось сохранить изменения.'
  } finally { saving.value = false }
}
</script>

<template>
  <section class="mx-auto max-w-4xl px-6 py-12">
    <p class="text-sm font-semibold text-indigo-600">Настройки аккаунта</p>
    <h1 class="mt-2 text-4xl font-bold tracking-tight">Профиль</h1>
    <p class="mt-3 text-slate-600">Управляйте личными данными и настройками аккаунта.</p>
    <div v-if="error" class="mt-6 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>
    <div v-if="auth.user" class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-col gap-5 border-b border-slate-100 p-6 sm:flex-row sm:items-center"><span class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-600 text-xl font-bold text-white">{{ initials() }}</span><div><h2 class="text-2xl font-bold">{{ auth.user.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ roleLabel() }}</p></div></div>
      <div class="p-6"><div class="flex items-center justify-between gap-4"><div><h2 class="text-xl font-bold">Личные данные</h2><p class="mt-1 text-sm text-slate-500">Эти данные используются в аккаунте и сертификатах.</p></div><AppButton variant="secondary" @click="editing = !editing">{{ editing ? 'Отмена' : 'Редактировать' }}</AppButton></div><form v-if="editing" class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="save"><label class="text-sm font-medium text-slate-700">Имя<input v-model="name" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100" required /></label><label class="text-sm font-medium text-slate-700">Электронная почта<input v-model="email" type="email" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100" required /></label><AppButton type="submit" :disabled="saving" class="sm:col-span-2">{{ saving ? 'Сохраняем…' : 'Сохранить изменения' }}</AppButton></form><dl v-else class="mt-6 divide-y divide-slate-100"><div class="flex justify-between gap-4 py-4"><dt class="text-sm text-slate-500">Имя</dt><dd class="text-sm font-semibold text-slate-900">{{ auth.user.name }}</dd></div><div class="flex justify-between gap-4 py-4"><dt class="text-sm text-slate-500">Электронная почта</dt><dd class="text-sm font-semibold text-slate-900">{{ auth.user.email }}</dd></div><div class="flex justify-between gap-4 py-4"><dt class="text-sm text-slate-500">Роль</dt><dd class="text-sm font-semibold text-indigo-600">{{ roleLabel() }}</dd></div></dl><RouterLink v-if="auth.user.role === 'admin'" to="/admin" class="mt-6 inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-700">Перейти к управлению платформой →</RouterLink></div>
    </div>
  </section>
</template>
