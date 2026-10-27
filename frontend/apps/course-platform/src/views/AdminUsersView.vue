<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'
import { useAuthStore } from '../stores/auth'
import { adminApi } from '../shared/api/adminApi'
import type { User } from '../shared/api/authApi'

const auth = useAuthStore()
const router = useRouter()
const users = ref<User[]>([])
const loading = ref(true)
const error = ref('')
const openRoleId = ref<number | null>(null)

onMounted(async () => {
  if (!auth.isAuthenticated) return router.replace('/login')
  await auth.loadProfile()
  if (auth.user?.role !== 'admin') return router.replace('/dashboard')

  try {
    users.value = (await adminApi.users()).data
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить пользователей.'
  } finally {
    loading.value = false
  }
})

function roleLabel(role: User['role']) {
  return role === 'admin' ? 'Администратор' : role === 'teacher' ? 'Преподаватель' : 'Студент'
}

async function changeRole(user: User, role: User['role']) {
  if (auth.user?.id === user.id) {
    openRoleId.value = null
    return
  }
  user.role = (await adminApi.updateRole(user.id, role)).role
  openRoleId.value = null
}

async function toggleBlock(user: User) {
  if (auth.user?.id === user.id) return
  user.is_blocked = (await adminApi.setBlocked(user.id, !user.is_blocked)).is_blocked
}
</script>

<template>
  <section class="mx-auto max-w-7xl px-6 py-12">
    <div v-if="loading" class="text-sm text-slate-500">Загружаем пользователей…</div>
    <div v-else-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>
    <template v-else>
      <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
          <p class="text-sm font-semibold text-indigo-600">Управление платформой</p>
          <h1 class="mt-2 text-4xl font-bold">Пользователи и доступ</h1>
          <p class="mt-3 text-slate-600">Управляйте ролями, статусами и доступом к платформе.</p>
        </div>
        <RouterLink to="/admin"><AppButton variant="secondary">Назад к управлению</AppButton></RouterLink>
      </div>

      <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-slate-200 text-slate-500">
              <tr>
                <th class="px-3 py-3">Пользователь</th>
                <th class="px-3 py-3">Роль</th>
                <th class="px-3 py-3">Статус</th>
                <th class="px-3 py-3 text-right">Действие</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id" class="border-b border-slate-100 last:border-0">
                <td class="px-3 py-4"><p class="font-semibold">{{ user.name }}</p><p class="text-xs text-slate-500">{{ user.email }}</p></td>
                <td class="px-3 py-4">
                  <div class="relative w-48">
                    <button type="button" class="flex h-10 w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 transition hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-100" @click="openRoleId = openRoleId === user.id ? null : user.id">
                      {{ roleLabel(user.role) }}
                      <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-slate-400"><path fill-rule="evenodd" d="M5.2 7.5a.75.75 0 1 1 1.06.02L10 11.4l3.74-3.88a.75.75 0 1 1 1.08 1.04l-4.28 4.44a.75.75 0 0 1-1.08 0L5.18 8.56a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                    </button>
                    <div v-if="openRoleId === user.id" class="absolute top-full z-20 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                      <button v-for="role in (['student', 'teacher', 'admin'] as User['role'][] )" :key="role" type="button" class="block w-full rounded-lg px-3 py-2 text-left text-sm transition hover:bg-indigo-50 hover:text-indigo-700" :class="user.role === role ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-slate-700'" @click="changeRole(user, role)">{{ roleLabel(role) }}</button>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-4"><span :class="user.is_blocked ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'" class="rounded-full px-2 py-1 text-xs font-semibold">{{ user.is_blocked ? 'Заблокирован' : 'Активен' }}</span></td>
                <td class="px-3 py-4 text-right"><AppButton variant="secondary" class="text-xs" @click="toggleBlock(user)">{{ user.is_blocked ? 'Разблокировать' : 'Заблокировать' }}</AppButton></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </section>
</template>
