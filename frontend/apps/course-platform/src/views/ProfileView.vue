<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import { authApi } from '../shared/api/authApi'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const editing = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const name = ref('')
const email = ref('')
const changingPassword = ref(false)
const passwordSaving = ref(false)
const currentPassword = ref('')
const newPassword = ref('')
const passwordConfirmation = ref('')

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

async function saveProfile() {
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await auth.updateProfile(name.value, email.value)
    editing.value = false
    success.value = 'Личные данные обновлены.'
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось сохранить изменения.'
  } finally {
    saving.value = false
  }
}

async function savePassword() {
  error.value = ''
  success.value = ''
  if (newPassword.value !== passwordConfirmation.value) {
    error.value = 'Новый пароль и подтверждение не совпадают.'
    return
  }

  passwordSaving.value = true
  try {
    await authApi.changePassword({
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: passwordConfirmation.value,
    })
    currentPassword.value = ''
    newPassword.value = ''
    passwordConfirmation.value = ''
    changingPassword.value = false
    success.value = 'Пароль успешно изменён.'
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось изменить пароль.'
  } finally {
    passwordSaving.value = false
  }
}
</script>

<template>
  <section class="mx-auto max-w-4xl px-6 py-12">
    <p class="text-sm font-semibold text-indigo-600">Настройки аккаунта</p>
    <h1 class="mt-2 text-4xl font-bold tracking-tight">Профиль</h1>
    <p class="mt-3 text-slate-600">Управляйте личными данными и безопасностью аккаунта.</p>

    <div v-if="error" class="mt-6 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>
    <div v-if="success" class="mt-6 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ success }}</div>

    <div v-if="auth.user" class="mt-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-col gap-5 border-b border-slate-100 p-6 sm:flex-row sm:items-center">
        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-600 text-xl font-bold text-white">{{ initials() }}</span>
        <div><h2 class="text-2xl font-bold">{{ auth.user.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ roleLabel() }}</p></div>
      </div>

      <div class="p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div><h2 class="text-xl font-bold">Личные данные</h2><p class="mt-1 text-sm text-slate-500">Имя используется в аккаунте и сертификатах.</p></div>
          <AppButton variant="secondary" @click="editing = !editing">{{ editing ? 'Отмена' : 'Редактировать' }}</AppButton>
        </div>
        <form v-if="editing" class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="saveProfile">
          <AppInput v-model="name" label="Имя" />
          <AppInput v-model="email" label="Электронная почта" type="email" />
          <AppButton type="submit" :disabled="saving" class="sm:col-span-2">{{ saving ? 'Сохраняем…' : 'Сохранить изменения' }}</AppButton>
        </form>
        <dl v-else class="mt-6 divide-y divide-slate-100">
          <div class="flex justify-between gap-4 py-4"><dt class="text-sm text-slate-500">Имя</dt><dd class="text-sm font-semibold">{{ auth.user.name }}</dd></div>
          <div class="flex justify-between gap-4 py-4"><dt class="text-sm text-slate-500">Электронная почта</dt><dd class="text-sm font-semibold">{{ auth.user.email }}</dd></div>
          <div class="flex justify-between gap-4 py-4"><dt class="text-sm text-slate-500">Роль</dt><dd class="text-sm font-semibold text-indigo-600">{{ roleLabel() }}</dd></div>
        </dl>
      </div>

      <div class="border-t border-slate-100 p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div><h2 class="text-xl font-bold">Пароль</h2><p class="mt-1 text-sm text-slate-500">Используйте не менее 8 символов.</p></div>
          <AppButton variant="secondary" @click="changingPassword = !changingPassword">{{ changingPassword ? 'Отмена' : 'Изменить пароль' }}</AppButton>
        </div>
        <form v-if="changingPassword" class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="savePassword">
          <div class="sm:col-span-2"><AppInput v-model="currentPassword" label="Текущий пароль" type="password" /></div>
          <AppInput v-model="newPassword" label="Новый пароль" type="password" />
          <AppInput v-model="passwordConfirmation" label="Повторите новый пароль" type="password" />
          <AppButton type="submit" :disabled="passwordSaving" class="sm:col-span-2">{{ passwordSaving ? 'Обновляем…' : 'Обновить пароль' }}</AppButton>
        </form>
      </div>
    </div>
  </section>
</template>
