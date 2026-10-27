<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import { courseApi, type Category, type Course, type CourseFilters } from '../shared/api/courseApi'

const courses = ref<Course[]>([])
const categories = ref<Category[]>([])
const teachers = ref<Array<{ id: number; name: string }>>([])
const search = ref('')
const categoryId = ref('')
const teacherId = ref('')
const sortValue = ref('newest')
const loading = ref(true)
const error = ref('')

const hasFilters = computed(() => Boolean(search.value.trim() || categoryId.value || teacherId.value || sortValue.value !== 'newest'))

function filters(): CourseFilters {
  const [sort, direction] = sortValue.value === 'price_asc'
    ? ['price', 'asc'] as const
    : sortValue.value === 'price_desc'
      ? ['price', 'desc'] as const
      : sortValue.value === 'oldest'
        ? ['created_at', 'asc'] as const
        : ['created_at', 'desc'] as const

  return {
    search: search.value.trim(),
    category_id: categoryId.value ? Number(categoryId.value) : undefined,
    teacher_id: teacherId.value ? Number(teacherId.value) : undefined,
    sort,
    direction,
  }
}

function responseCourses(response: { data: Course[] } | Course[]): Course[] {
  return Array.isArray(response) ? response : response.data ?? []
}

async function loadInitialData() {
  loading.value = true
  error.value = ''
  try {
    const [categoryResponse, courseResponse] = await Promise.all([
      courseApi.categories(),
      courseApi.list({ sort: 'created_at', direction: 'desc' }),
    ])
    categories.value = categoryResponse.data
    courses.value = responseCourses(courseResponse)
    const uniqueTeachers = new Map<number, string>()
    courses.value.forEach((course) => {
      if (course.author) uniqueTeachers.set(course.author.id, course.author.name)
    })
    teachers.value = [...uniqueTeachers].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name, 'ru'))
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить каталог.'
  } finally {
    loading.value = false
  }
}

async function loadCourses() {
  loading.value = true
  error.value = ''
  try {
    courses.value = responseCourses(await courseApi.list(filters()))
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить курсы.'
  } finally {
    loading.value = false
  }
}

async function resetFilters() {
  search.value = ''
  categoryId.value = ''
  teacherId.value = ''
  sortValue.value = 'newest'
  await loadCourses()
}

onMounted(loadInitialData)
</script>

<template>
  <section class="mx-auto max-w-7xl px-6 py-12">
    <div>
      <p class="text-sm font-semibold text-indigo-600">Каталог</p>
      <h1 class="mt-2 text-4xl font-bold tracking-tight">Все курсы</h1>
      <p class="mt-3 max-w-2xl text-slate-600">Найдите подходящую программу по направлению, преподавателю и стоимости.</p>
    </div>

    <form class="mt-8 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="loadCourses">
      <div class="grid gap-3 lg:grid-cols-[minmax(240px,1.5fr)_1fr_1fr_1fr_auto] lg:items-end">
        <AppInput v-model="search" label="Поиск" placeholder="Название курса" />
        <label class="text-sm font-medium text-slate-700">Категория
          <select v-model="categoryId" class="mt-2 h-[42px] w-full rounded-lg border border-slate-300 bg-white px-3 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
            <option value="">Все категории</option>
            <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
          </select>
        </label>
        <label class="text-sm font-medium text-slate-700">Преподаватель
          <select v-model="teacherId" class="mt-2 h-[42px] w-full rounded-lg border border-slate-300 bg-white px-3 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
            <option value="">Все преподаватели</option>
            <option v-for="teacher in teachers" :key="teacher.id" :value="String(teacher.id)">{{ teacher.name }}</option>
          </select>
        </label>
        <label class="text-sm font-medium text-slate-700">Сортировка
          <select v-model="sortValue" class="mt-2 h-[42px] w-full rounded-lg border border-slate-300 bg-white px-3 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
            <option value="newest">Сначала новые</option>
            <option value="oldest">Сначала старые</option>
            <option value="price_asc">Сначала дешевле</option>
            <option value="price_desc">Сначала дороже</option>
          </select>
        </label>
        <AppButton type="submit" class="h-[42px] px-7">Найти</AppButton>
      </div>
      <button v-if="hasFilters" type="button" class="mt-3 text-sm font-semibold text-slate-500 transition hover:text-indigo-600" @click="resetFilters">Сбросить фильтры</button>
    </form>

    <div v-if="error" class="mt-8 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>
    <div v-else-if="loading" class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <div v-for="index in 3" :key="index" class="h-72 animate-pulse rounded-2xl border border-slate-200 bg-white p-6"><div class="h-5 w-28 rounded bg-slate-100"></div><div class="mt-8 h-7 w-2/3 rounded bg-slate-100"></div><div class="mt-4 h-20 rounded bg-slate-100"></div></div>
    </div>
    <div v-else-if="courses.length === 0" class="mt-10 rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
      <h2 class="font-semibold text-slate-900">Подходящие курсы не найдены</h2>
      <p class="mt-2 text-sm text-slate-500">Измените параметры поиска или сбросьте фильтры.</p>
      <button type="button" class="mt-5 text-sm font-semibold text-indigo-600" @click="resetFilters">Показать все курсы</button>
    </div>
    <div v-else class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <article v-for="course in courses" :key="course.id" class="group flex min-h-72 flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-100/60">
        <div class="flex items-start justify-between gap-4">
          <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ course.category?.name ?? 'Курс' }}</span>
          <span class="whitespace-nowrap font-bold text-slate-900">{{ course.price.toLocaleString() }} UZS</span>
        </div>
        <h2 class="mt-6 text-xl font-bold text-slate-900">{{ course.title }}</h2>
        <p class="mt-3 line-clamp-3 flex-1 text-sm leading-6 text-slate-600">{{ course.description }}</p>
        <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
          <span class="text-xs font-medium text-slate-500">{{ course.author?.name ?? 'Преподаватель' }}</span>
          <RouterLink :to="`/courses/${course.id}`" class="text-sm font-semibold text-indigo-600 transition group-hover:text-indigo-700">Подробнее →</RouterLink>
        </div>
      </article>
    </div>
  </section>
</template>
