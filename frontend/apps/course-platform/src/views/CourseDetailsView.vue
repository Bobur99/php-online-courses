<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'
import { courseApi, type Course, type Lesson } from '../shared/api/courseApi'
import { useAuthStore } from '../stores/auth'
import { studentApi } from '../shared/api/studentApi'

const route = useRoute(); const auth = useAuthStore(); const course = ref<Course | null>(null); const lessons = ref<Lesson[]>([]); const loading = ref(true); const error = ref('')
const actionError = ref('')
const completingLessonId = ref<number | null>(null)
const completedLessons = computed(() => lessons.value.filter((lesson) => lesson.is_completed).length)
const progressPercentage = computed(() => lessons.value.length ? Math.round((completedLessons.value / lessons.value.length) * 100) : 0)
const canTrackProgress = computed(() => auth.user?.role === 'student' && Boolean(course.value?.is_purchased))
const materialUrl = (path: string) => `/storage/${path}`
const fileName = (path: string) => path.split('/').pop() ?? 'Материал курса'
const isImageFile = (path: string) => /\.(avif|gif|jpe?g|png|webp)$/i.test(path)
const isVideoFile = (path: string) => /\.(mp4|mov|webm)$/i.test(path)
onMounted(async () => { try { if (auth.isAuthenticated && !auth.user) await auth.loadProfile(); const id = Number(route.params.id); const [courseResponse, lessonResponse] = await Promise.all([courseApi.show(id), courseApi.lessons(id)]); course.value = courseResponse; lessons.value = lessonResponse.data } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить курс.' } finally { loading.value = false } })

async function completeLesson(lesson: Lesson) {
  if (lesson.is_completed) return
  completingLessonId.value = lesson.id
  actionError.value = ''
  try {
    await studentApi.completeLesson(lesson.id)
    lesson.is_completed = true
  } catch (exception) {
    actionError.value = exception instanceof Error ? exception.message : 'Не удалось отметить урок пройденным.'
  } finally {
    completingLessonId.value = null
  }
}
</script>

<template>
  <section class="mx-auto max-w-6xl px-6 py-10 sm:py-14">
    <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500">
      Загружаем информацию о курсе…
    </div>
    <div v-else-if="error" class="rounded-2xl bg-red-50 px-5 py-4 text-sm text-red-700">
      {{ error }}
    </div>
    <template v-else-if="course">
      <div v-if="route.query.payment === 'success'" class="mb-6 flex items-center gap-3 rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-white">✓</span>
        Оплата подтверждена. Курс доступен для обучения.
      </div>
      <div class="overflow-hidden rounded-3xl border border-indigo-100 bg-indigo-50 text-slate-900 shadow-xl shadow-indigo-100/70">
        <div class="max-w-3xl p-8 sm:p-12">
          <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            Опубликован
          </span>
          <h1 class="mt-6 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">{{ course.title }}</h1>
          <p class="mt-5 text-lg leading-8 text-slate-600">{{ course.description }}</p>
          <div class="mt-9 flex flex-wrap items-center gap-5">
            <span class="text-2xl font-bold text-slate-900">{{ course.price.toLocaleString() }} UZS</span>
            <span v-if="course.is_purchased" class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700">
              <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="m4.5 10.5 3.5 3.5 7.5-8" stroke-linecap="round" stroke-linejoin="round" /></svg>
              Курс уже приобретён
            </span>
            <RouterLink v-else-if="!auth.user || auth.user.role === 'student'" :to="`/checkout/${course.id}`">
              <AppButton variant="secondary">Купить курс</AppButton>
            </RouterLink>
          </div>
        </div>
      </div>

      <div class="mt-12">
        <div class="flex items-end justify-between gap-4">
          <div>
            <p class="text-sm font-semibold text-indigo-600">Учебная программа</p>
            <h2 class="mt-1 text-3xl font-bold">Содержание курса</h2>
          </div>
          <span class="rounded-full bg-indigo-50 px-3 py-1 text-sm font-semibold text-indigo-700">{{ lessons.length }} уроков</span>
        </div>

        <div v-if="canTrackProgress && lessons.length" class="mt-6 rounded-2xl border border-indigo-100 bg-indigo-50/70 p-5">
          <div class="flex items-center justify-between gap-4"><div><p class="font-semibold text-slate-900">Ваш прогресс</p><p class="mt-1 text-sm text-slate-500">Пройдено {{ completedLessons }} из {{ lessons.length }} уроков</p></div><span class="text-xl font-bold text-indigo-700">{{ progressPercentage }}%</span></div>
          <div class="mt-4 h-2 overflow-hidden rounded-full bg-white"><div class="h-full rounded-full bg-indigo-600 transition-all" :style="{ width: `${progressPercentage}%` }"></div></div>
          <p v-if="progressPercentage === 100" class="mt-3 text-sm font-semibold text-emerald-700">Курс завершён. Сертификат появится в личном кабинете после формирования.</p>
        </div>
        <div v-if="actionError" class="mt-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">{{ actionError }}</div>

        <div v-if="lessons.length" class="mt-6 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div v-for="lesson in lessons" :key="lesson.id" class="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center lg:gap-8">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-sm font-bold text-indigo-600">{{ lesson.order_number ?? lesson.order }}</span>
            <div class="min-w-0 flex-1">
              <h3 class="font-semibold text-slate-900">{{ lesson.title }}</h3>
              <p v-if="lesson.content || lesson.description" class="mt-1 text-sm leading-6 text-slate-500">{{ lesson.content ?? lesson.description }}</p>
            </div>
            <button v-if="canTrackProgress" type="button" :disabled="lesson.is_completed || completingLessonId === lesson.id" class="flex h-10 shrink-0 items-center gap-2 rounded-xl px-3 text-sm font-semibold transition" :class="lesson.is_completed ? 'bg-emerald-50 text-emerald-700' : 'border border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-700'" @click="completeLesson(lesson)">
              <span class="flex h-5 w-5 items-center justify-center rounded-full border" :class="lesson.is_completed ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300'">{{ lesson.is_completed ? '✓' : '' }}</span>
              {{ lesson.is_completed ? 'Пройдено' : completingLessonId === lesson.id ? 'Сохраняем…' : 'Завершить урок' }}
            </button>
            <div v-if="lesson.file_path" class="flex w-full shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-1.5 lg:w-[280px]">
              <div v-if="isImageFile(lesson.file_path)" class="h-9 w-9 shrink-0 overflow-hidden rounded-lg bg-white ring-1 ring-slate-200">
                <img :src="materialUrl(lesson.file_path)" :alt="fileName(lesson.file_path)" class="h-full w-full object-cover" />
              </div>
              <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-[10px] font-bold uppercase tracking-wide text-indigo-600">
                {{ isVideoFile(lesson.file_path) ? 'Видео' : fileName(lesson.file_path).split('.').pop() }}
              </div>
              <div class="min-w-0 flex-1" :title="fileName(lesson.file_path)">
                <p class="text-sm font-semibold text-slate-700">Учебный материал</p>
              </div>
              <div class="flex shrink-0 items-center gap-1">
                <a :href="materialUrl(lesson.file_path)" target="_blank" rel="noreferrer" title="Открыть материал" aria-label="Открыть материал" class="flex h-8 w-8 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-100">
                  <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5"><path d="M10 3.5h6.5V10M16.5 3.5 9 11m-4.5 5.5h9A2 2 0 0 0 15.5 14V12" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </a>
                <a :href="materialUrl(lesson.file_path)" :download="fileName(lesson.file_path)" title="Скачать материал" aria-label="Скачать материал" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-white hover:text-indigo-600">
                  <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5"><path d="M10 3v9m0 0 3.5-3.5M10 12 6.5 8.5M4 15.5v.8A1.7 1.7 0 0 0 5.7 18h8.6a1.7 1.7 0 0 0 1.7-1.7v-.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </a>
              </div>
            </div>
            <div v-else-if="lesson.has_material" class="flex w-full shrink-0 items-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 lg:w-[280px]">
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-slate-500">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" class="h-4 w-4"><rect x="4.5" y="8.5" width="11" height="8" rx="1.5" /><path d="M7 8.5V6.8a3 3 0 0 1 6 0v1.7" stroke-linecap="round" /></svg>
              </span>
              <span class="font-medium">Материал доступен после покупки</span>
            </div>
          </div>
        </div>
        <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
          Уроки скоро появятся.
        </div>
      </div>
    </template>
  </section>
</template>
