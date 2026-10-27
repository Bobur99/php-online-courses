<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from './components/ui/AppButton.vue'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()
const profileMenuOpen = ref(false)

const initials = () => auth.user?.name?.trim().split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase() || 'П'

async function logout() {
  profileMenuOpen.value = false
  await auth.logout()
  await router.push('/courses')
}

onMounted(() => {
  if (auth.isAuthenticated && !auth.user) {
    auth.loadProfile().catch(() => auth.logout())
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
      <div class="mx-auto grid max-w-7xl grid-cols-3 items-center px-6 py-4">
        <div aria-hidden="true"></div>
        <nav class="flex items-center justify-self-center gap-6 text-sm font-medium text-slate-600">
          <RouterLink to="/courses" class="hover:text-indigo-600">Курсы</RouterLink>
        </nav>
        <div class="flex items-center justify-self-end gap-4">
          <template v-if="auth.isAuthenticated">
            <div class="relative">
              <button type="button" class="group flex items-center gap-3 rounded-full py-1 pl-1 pr-2 transition hover:bg-slate-50" :aria-expanded="profileMenuOpen" @click="profileMenuOpen = !profileMenuOpen">
              <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">{{ initials() }}</span>
              <span class="hidden text-sm font-semibold text-slate-700 group-hover:text-indigo-600 sm:block">{{ auth.user?.name ?? 'Мой профиль' }}</span>
              <svg viewBox="0 0 20 20" fill="currentColor" class="hidden h-4 w-4 text-slate-400 sm:block" aria-hidden="true"><path fill-rule="evenodd" d="M5.2 7.5a.75.75 0 0 1 1.06.02L10 11.4l3.74-3.88a.75.75 0 1 1 1.08 1.04l-4.28 4.44a.75.75 0 0 1-1.08 0L5.18 8.56a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
              </button>
              <div v-if="profileMenuOpen" class="absolute right-0 z-30 mt-3 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                <RouterLink to="/profile" exact-active-class="bg-indigo-50 text-indigo-700" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-indigo-50 hover:text-indigo-700" @click="profileMenuOpen = false">Профиль</RouterLink>
                <div v-if="auth.user?.role === 'admin'" class="mt-2">
                  <RouterLink to="/admin" exact-active-class="bg-indigo-50 text-indigo-700" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-indigo-50 hover:text-indigo-700" @click="profileMenuOpen = false">Управление платформой</RouterLink>
                  <RouterLink to="/admin/users" exact-active-class="bg-indigo-50 text-indigo-700" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-indigo-50 hover:text-indigo-700" @click="profileMenuOpen = false">Пользователи и доступ</RouterLink>
                </div>
                <RouterLink v-if="auth.user?.role !== 'admin'" to="/dashboard" exact-active-class="bg-indigo-50 text-indigo-700" class="mt-1 block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-indigo-50 hover:text-indigo-700" @click="profileMenuOpen = false">Личный кабинет</RouterLink>
                <RouterLink v-if="auth.user?.role === 'teacher'" to="/teacher" exact-active-class="bg-indigo-50 text-indigo-700" class="mt-1 block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-indigo-50 hover:text-indigo-700" @click="profileMenuOpen = false">Управление курсами</RouterLink>
                <div class="mt-2 border-t border-slate-100 pt-2">
                  <button type="button" class="block w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-red-600 transition hover:bg-red-50" @click="logout">Выйти</button>
                </div>
              </div>
            </div>
          </template>
          <template v-else>
            <RouterLink to="/login"><AppButton variant="ghost">Войти</AppButton></RouterLink>
            <RouterLink to="/register"><AppButton>Начать обучение</AppButton></RouterLink>
          </template>
        </div>
      </div>
    </header>
    <RouterView />
  </div>
</template>

<style scoped></style>
