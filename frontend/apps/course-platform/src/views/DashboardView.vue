<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '../shared/api/adminApi'
import type { Course } from '../shared/api/courseApi'
import { studentApi, type Certificate, type StudentCourse } from '../shared/api/studentApi'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const loading = ref(true)
const error = ref('')
const studentCourses = ref<StudentCourse[]>([])
const certificates = ref<Certificate[]>([])
const teacherCourses = ref<Course[]>([])

const completedCourses = computed(() => studentCourses.value.filter((course) => course.is_completed).length)
const averageProgress = computed(() => studentCourses.value.length
  ? Math.round(studentCourses.value.reduce((sum, course) => sum + course.progress_percentage, 0) / studentCourses.value.length)
  : 0)
const publishedTeacherCourses = computed(() => teacherCourses.value.filter((course) => course.status === 'published').length)

async function downloadCertificate(certificate: Certificate) {
  try {
    const blob = await studentApi.downloadCertificate(certificate.download_url)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `Сертификат — ${certificate.course.title}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось скачать сертификат.'
  }
}

onMounted(async () => {
  if (!auth.isAuthenticated) {
    await router.replace({ path: '/login', query: { redirect: '/dashboard' } })
    return
  }

  try {
    await auth.loadProfile()
    if (auth.user?.role === 'admin') {
      await router.replace('/admin')
      return
    }
    if (auth.user?.role === 'teacher') {
      teacherCourses.value = (await adminApi.teacherCourses()).data
    } else {
      const [courseResponse, certificateResponse] = await Promise.all([
        studentApi.courses(),
        studentApi.certificates(),
      ])
      studentCourses.value = courseResponse.data
      certificates.value = certificateResponse.data
    }
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить личный кабинет.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="mx-auto max-w-6xl px-6 py-12">
    <div v-if="loading" class="space-y-6"><div class="h-32 animate-pulse rounded-3xl bg-white"></div><div class="h-64 animate-pulse rounded-3xl bg-white"></div></div>
    <div v-else-if="error" class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>
    <template v-else-if="auth.user">
      <div>
        <p class="text-sm font-semibold text-indigo-600">Личный кабинет</p>
        <h1 class="mt-2 text-4xl font-bold tracking-tight">Здравствуйте, {{ auth.user.name }}</h1>
        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-500">
          <span>{{ auth.user.email }}</span><span class="h-1 w-1 rounded-full bg-slate-300"></span>
          <span class="font-semibold text-indigo-600">{{ auth.user.role === 'teacher' ? 'Преподаватель' : 'Студент' }}</span>
        </div>
      </div>

      <template v-if="auth.user.role === 'teacher'">
        <div class="mt-10 grid gap-4 sm:grid-cols-3">
          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Всего курсов</p><p class="mt-2 text-3xl font-bold">{{ teacherCourses.length }}</p></div>
          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Опубликовано</p><p class="mt-2 text-3xl font-bold text-emerald-600">{{ publishedTeacherCourses }}</p></div>
          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Черновики</p><p class="mt-2 text-3xl font-bold text-amber-600">{{ teacherCourses.length - publishedTeacherCourses }}</p></div>
        </div>
        <RouterLink to="/teacher" class="group mt-6 flex items-center justify-between gap-6 rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-100/60">
          <div><p class="text-sm font-semibold text-indigo-600">Преподавание</p><h2 class="mt-2 text-2xl font-bold">Управление курсами</h2><p class="mt-2 text-sm leading-6 text-slate-500">Создавайте курсы, добавляйте уроки и публикуйте учебные материалы.</p></div>
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xl text-indigo-600 transition group-hover:translate-x-1">→</span>
        </RouterLink>
      </template>

      <template v-else>
        <div class="mt-10 grid gap-4 sm:grid-cols-3">
          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Мои курсы</p><p class="mt-2 text-3xl font-bold">{{ studentCourses.length }}</p></div>
          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Средний прогресс</p><p class="mt-2 text-3xl font-bold text-indigo-600">{{ averageProgress }}%</p></div>
          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Завершено</p><p class="mt-2 text-3xl font-bold text-emerald-600">{{ completedCourses }}</p></div>
        </div>

        <div class="mt-10 flex items-end justify-between gap-4"><div><p class="text-sm font-semibold text-indigo-600">Обучение</p><h2 class="mt-1 text-2xl font-bold">Мои курсы</h2></div><RouterLink to="/courses" class="text-sm font-semibold text-indigo-600">Каталог курсов →</RouterLink></div>
        <div v-if="studentCourses.length" class="mt-5 grid gap-5 md:grid-cols-2">
          <RouterLink v-for="course in studentCourses" :key="course.id" :to="`/courses/${course.id}`" class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-indigo-200 hover:shadow-md">
            <div class="flex items-start justify-between gap-4"><div><span class="text-xs font-semibold text-indigo-600">{{ course.category?.name ?? 'Курс' }}</span><h3 class="mt-2 text-lg font-bold group-hover:text-indigo-700">{{ course.title }}</h3></div><span class="rounded-full px-3 py-1 text-xs font-semibold" :class="course.is_completed ? 'bg-emerald-50 text-emerald-700' : 'bg-indigo-50 text-indigo-700'">{{ course.is_completed ? 'Завершён' : `${course.progress_percentage}%` }}</span></div>
            <div class="mt-6 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-600 transition-all" :style="{ width: `${course.progress_percentage}%` }"></div></div>
            <p class="mt-3 text-xs text-slate-500">Пройдено {{ course.completed_lessons_count }} из {{ course.lessons_count }} уроков</p>
          </RouterLink>
        </div>
        <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"><h3 class="font-semibold">У вас пока нет приобретённых курсов</h3><p class="mt-2 text-sm text-slate-500">Выберите программу в каталоге и начните обучение.</p><RouterLink to="/courses" class="mt-5 inline-flex text-sm font-semibold text-indigo-600">Перейти в каталог →</RouterLink></div>

        <div class="mt-12"><p class="text-sm font-semibold text-indigo-600">Результаты</p><h2 class="mt-1 text-2xl font-bold">Сертификаты</h2></div>
        <div v-if="certificates.length" class="mt-5 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div v-for="certificate in certificates" :key="certificate.id" class="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center"><div class="flex items-center gap-4"><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xs font-bold text-emerald-700">PDF</span><div><p class="font-semibold">{{ certificate.course.title }}</p><p class="mt-1 text-xs text-slate-500">Выдан {{ new Date(certificate.created_at).toLocaleDateString('ru-RU') }}</p></div></div><button type="button" class="text-left text-sm font-semibold text-indigo-600" @click="downloadCertificate(certificate)">Скачать сертификат ↓</button></div>
        </div>
        <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Сертификаты появятся после прохождения всех уроков курса.</div>
      </template>
    </template>
  </section>
</template>
