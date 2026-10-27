<script setup lang="ts">
withDefaults(defineProps<{ type?: 'button' | 'submit' | 'reset'; disabled?: boolean }>(), { type: 'button', disabled: false })
</script>

<template><button :type="type" :disabled="disabled" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"><slot /></button></template>
