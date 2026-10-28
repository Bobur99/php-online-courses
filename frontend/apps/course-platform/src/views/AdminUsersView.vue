<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
const roleMenuStyle = ref<Record<string, string>>({})
const openRoleUser = computed(() => users.value.find((user) => user.id === openRoleId.value) ?? null)

onMounted(async () => {
  if (!auth.isAuthenticated) return router.replace('/login')
  try {
    await auth.loadProfile()
    if (auth.user?.role !== 'admin') return router.replace('/dashboard')
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

function toggleRoleMenu(userId: number, event: MouseEvent) {
  if (openRoleId.value === userId) {
    openRoleId.value = null
    return
  }

  const target = event.currentTarget as HTMLElement
  const rect = target.getBoundingClientRect()
  const menuHeight = 154
  const top = rect.bottom + 8 + menuHeight <= window.innerHeight
    ? rect.bottom + 8
    : Math.max(8, rect.top - menuHeight - 8)

  roleMenuStyle.value = {
    top: `${top}px`,
    left: `${rect.left}px`,
    width: `${rect.width}px`,
  }
  openRoleId.value = userId
}

async function changeRole(user: User, role: User['role']) {
  if (auth.user?.id === user.id) {
    openRoleId.value = null
    return
  }
  try {
    user.role = (await adminApi.updateRole(user.id, role)).role
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось изменить роль.'
  } finally { openRoleId.value = null }
}

async function toggleBlock(user: User) {
  if (auth.user?.id === user.id) return
  try {
    user.is_blocked = (await adminApi.setBlocked(user.id, !user.is_blocked)).is_blocked
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось изменить блокировку.'
  }
}
</script>

<template>
  <section class="mx-auto max-w-7xl px-6 py-12">
    <div v-if="loading" class="text-sm text-slate-500">Загружаем пользователей…</div>
    <template v-else>
      <div v-if="error" role="alert" class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}<button type="button" class="ml-4 font-semibold" @click="error = ''">Закрыть</button></div>
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
                    <button type="button" class="flex h-10 w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 transition hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-100" @click="toggleRoleMenu(user.id, $event)">
                      {{ roleLabel(user.role) }}
                      <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-slate-400"><path fill-rule="evenodd" d="M5.2 7.5a.75.75 0 1 1 1.06.02L10 11.4l3.74-3.88a.75.75 0 1 1 1.08 1.04l-4.28 4.44a.75.75 0 0 1-1.08 0L5.18 8.56a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                    </button>
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
  <Teleport to="body">
    <div v-if="openRoleUser" class="fixed z-[100] overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl" :style="roleMenuStyle">
      <button v-for="role in (['student', 'teacher', 'admin'] as User['role'][] )" :key="role" type="button" class="block w-full rounded-lg px-3 py-2 text-left text-sm transition hover:bg-indigo-50 hover:text-indigo-700" :class="openRoleUser.role === role ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-slate-700'" @click="changeRole(openRoleUser, role)">{{ roleLabel(role) }}</button>
    </div>
  </Teleport>
</template>
