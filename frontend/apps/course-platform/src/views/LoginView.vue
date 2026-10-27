<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import { useAuthStore } from '../stores/auth'
const router = useRouter(); const auth = useAuthStore(); const email = ref(''); const password = ref(''); const error = ref(''); const loading = ref(false)
async function submit() { error.value = ''; loading.value = true; try { await auth.login(email.value, password.value); await router.push(String(router.currentRoute.value.query.redirect ?? '/dashboard')) } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Не удалось выполнить вход.' } finally { loading.value = false } }
</script>
<template><section class="mx-auto max-w-md px-6 py-16"><div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"><h1 class="text-2xl font-bold">С возвращением</h1><p class="mt-2 text-sm text-slate-500">Войдите, чтобы продолжить обучение.</p><div v-if="error" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div><form class="mt-6 space-y-5" @submit.prevent="submit"><AppInput v-model="email" label="Электронная почта" type="email" placeholder="you@example.com"/><AppInput v-model="password" label="Пароль" type="password" placeholder="••••••••"/><AppButton type="submit" :disabled="loading" class="w-full">{{ loading ? 'Выполняем вход…' : 'Войти' }}</AppButton></form><p class="mt-6 text-center text-sm text-slate-500">Нет аккаунта? <RouterLink to="/register" class="font-semibold text-indigo-600">Зарегистрироваться</RouterLink></p></div></section></template>
