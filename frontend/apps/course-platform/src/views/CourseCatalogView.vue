<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import { courseApi, type Course } from '../shared/api/courseApi'

const courses = ref<Course[]>([])
const search = ref('')
const loading = ref(true)
const error = ref('')

async function loadCourses() {
  loading.value = true; error.value = ''
  try {
    const response = await courseApi.list({ search: search.value, sort: 'created_at', direction: 'desc' })
    courses.value = Array.isArray(response) ? response : response.data ?? []
  }
  catch (exception) { error.value = exception instanceof Error ? exception.message : 'Unable to load courses.' }
  finally { loading.value = false }
}

onMounted(loadCourses)
</script>

<template>
  <section class="mx-auto max-w-7xl px-6 py-12">
    <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end"><div><p class="text-sm font-semibold text-indigo-600">Каталог</p><h1 class="mt-2 text-4xl font-bold tracking-tight">Все курсы</h1></div><form class="flex w-full items-end gap-2 md:w-auto" @submit.prevent="loadCourses"><AppInput v-model="search" label="" placeholder="Поиск курсов"/><AppButton type="submit" class="mt-0 h-[42px] min-w-28 px-6">Найти</AppButton></form></div>
    <div v-if="error" class="mt-8 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">Не удалось загрузить курсы: {{ error }}</div>
    <div v-else-if="loading" class="mt-10 text-sm text-slate-500">Загружаем курсы…</div>
    <div v-else-if="courses.length === 0" class="mt-10 rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-500">
      <template v-if="search.trim()">По запросу «{{ search.trim() }}» курсы не найдены.</template>
      <template v-else>Опубликованных курсов пока нет.</template>
    </div>
    <div v-else class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3"><article v-for="course in courses" :key="course.id" class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md"><div class="flex items-center justify-between"><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">Опубликован</span><span class="font-semibold text-slate-900">{{ course.price.toLocaleString() }} UZS</span></div><h2 class="mt-6 text-xl font-semibold">{{ course.title }}</h2><p class="mt-3 flex-1 text-sm leading-6 text-slate-600">{{ course.description }}</p><RouterLink :to="`/courses/${course.id}`" class="mt-6 text-sm font-semibold text-indigo-600 hover:text-indigo-700">Подробнее →</RouterLink></article></div>
  </section>
</template>
