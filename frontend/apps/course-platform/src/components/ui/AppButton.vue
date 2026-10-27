<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset'
    variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
    disabled?: boolean
  }>(),
  { type: 'button', variant: 'primary', disabled: false },
)
</script>

<template>
  <button :type="type" :disabled="disabled" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" :class="{
    'bg-indigo-600 text-white hover:bg-indigo-700': variant === 'primary',
    'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50': variant === 'secondary',
    'bg-red-600 text-white hover:bg-red-700': variant === 'danger',
    'text-slate-600 hover:bg-slate-100': variant === 'ghost',
  }">
    <slot />
  </button>
</template>
