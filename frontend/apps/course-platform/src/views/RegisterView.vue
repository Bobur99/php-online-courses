<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import { useAuthStore } from '../stores/auth'
const router = useRouter(); const auth = useAuthStore(); const name = ref(''); const email = ref(''); const password = ref(''); const confirmation = ref(''); const error = ref(''); const loading = ref(false)
async function submit() { error.value = ''; loading.value = true; try { await auth.register(name.value, email.value, password.value, confirmation.value); await router.push('/dashboard') } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Не удалось создать аккаунт.' } finally { loading.value = false } }
</script>
<template><section class="mx-auto max-w-md px-6 py-12"><div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"><h1 class="text-2xl font-bold">Создайте аккаунт</h1><p class="mt-2 text-sm text-slate-500">Начните обучение на Learnly.</p><div v-if="error" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div><form class="mt-6 space-y-5" @submit.prevent="submit"><AppInput v-model="name" label="Имя"/><AppInput v-model="email" label="Электронная почта" type="email"/><AppInput v-model="password" label="Пароль" type="password"/><AppInput v-model="confirmation" label="Подтверждение пароля" type="password"/><AppButton type="submit" :disabled="loading" class="w-full">{{ loading ? 'Создаём аккаунт…' : 'Зарегистрироваться' }}</AppButton></form><p class="mt-6 text-center text-sm text-slate-500">Уже зарегистрированы? <RouterLink to="/login" class="font-semibold text-indigo-600">Войти</RouterLink></p></div></section></template>
