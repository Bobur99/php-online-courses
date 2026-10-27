<script setup lang="ts">
import { computed, ref } from 'vue'

defineEmits<{ 'update:modelValue': [value: string] }>()
const props = defineProps<{ label: string; modelValue: string; type?: string; placeholder?: string; error?: string }>()
const showPassword = ref(false)
const inputType = computed(() => props.type === 'password' && showPassword.value ? 'text' : props.type ?? 'text')
</script>

<template>
  <label class="block text-sm font-medium text-slate-700">
    {{ label }}
    <span class="relative mt-2 block">
      <input :type="inputType" :value="modelValue" :placeholder="placeholder" class="w-full rounded-lg border px-3 py-2.5 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" :class="[error ? 'border-red-400' : 'border-slate-300', type === 'password' ? 'pr-11' : '']" @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)" />
      <button v-if="type === 'password'" type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 transition hover:text-indigo-600" :aria-label="showPassword ? 'Скрыть пароль' : 'Показать пароль'" @click="showPassword = !showPassword">
        <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="m3 3 18 18"/><path d="M10.6 6.2A10.5 10.5 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-3.1 3.8M6.3 6.3C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1.2 0 2.3-.2 3.3-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
      </button>
    </span>
    <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
  </label>
</template>
