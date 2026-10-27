<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

export type SelectOption = { value: string; label: string }

const props = defineProps<{
  label: string
  modelValue: string
  options: SelectOption[]
  placeholder?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const root = ref<HTMLElement | null>(null)
const open = ref(false)

const selectedLabel = computed(() => props.options.find((option) => option.value === props.modelValue)?.label ?? props.placeholder ?? 'Выберите значение')

function choose(value: string) {
  emit('update:modelValue', value)
  open.value = false
}

function closeOnOutsideClick(event: MouseEvent) {
  if (root.value && !root.value.contains(event.target as Node)) open.value = false
}

onMounted(() => document.addEventListener('click', closeOnOutsideClick))
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutsideClick))
</script>

<template>
  <div ref="root" class="relative text-sm font-medium text-slate-700">
    <span class="block">{{ label }}</span>
    <button type="button" class="mt-2 flex h-[42px] w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 text-left outline-none transition hover:border-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" :aria-expanded="open" @click.stop="open = !open" @keydown.escape="open = false">
      <span :class="props.modelValue ? 'text-slate-700' : 'text-slate-400'">{{ selectedLabel }}</span>
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0 text-slate-400 transition" :class="open ? 'rotate-180 text-indigo-600' : ''"><path d="m5.5 7.5 4.5 4.5 4.5-4.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
    </button>
    <div v-if="open" class="absolute left-0 right-0 top-full z-40 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl shadow-slate-200/70">
      <button v-for="option in options" :key="option.value" type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm transition hover:bg-indigo-50 hover:text-indigo-700" :class="option.value === modelValue ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-slate-700'" @click="choose(option.value)">
        {{ option.label }}
        <span v-if="option.value === modelValue" class="text-indigo-600">✓</span>
      </button>
    </div>
  </div>
</template>
