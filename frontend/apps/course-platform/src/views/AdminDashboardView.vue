<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'
import { useAuthStore } from '../stores/auth'
import { adminApi, type AdminLesson, type Category } from '../shared/api/adminApi'
import type { User } from '../shared/api/authApi'
import type { Course } from '../shared/api/courseApi'

const auth = useAuthStore()
const router = useRouter()
const isAdmin = computed(() => auth.user?.role === 'admin')
const users = ref<User[]>([])
const categories = ref<Category[]>([])
const courses = ref<Course[]>([])
const courseLessons = ref<Record<number, AdminLesson[]>>({})
const openCourseId = ref<number | null>(null)
const lessonTitle = ref('')
const lessonContent = ref('')
const lessonOrder = ref('1')
const lessonFile = ref<File | null>(null)
const lessonSaving = ref(false)
const lessonError = ref('')
const lessonModalOpen = ref(false)
const lessonTargetCourse = ref<Course | null>(null)
const lessonModalMode = ref<'create' | 'edit'>('create')
const editingLessonId = ref<number | null>(null)
const deleteLessonTarget = ref<AdminLesson | null>(null)
const newCategory = ref('')
const editingCategoryId = ref<number | null>(null)
const courseTitle = ref('')
const courseDescription = ref('')
const coursePrice = ref('')
const courseCategoryId = ref<number | ''>('')
const courseSaving = ref(false)
const courseError = ref('')
const courseSuccess = ref('')
const courseModalOpen = ref(false)
const courseModalMode = ref<'create' | 'edit'>('create')
const editingCourseId = ref<number | null>(null)
const deleteCourseTarget = ref<Course | null>(null)
const unpublishCourseTarget = ref<Course | null>(null)
const categoryMenuOpen = ref(false)
const categoryPanelOpen = ref(true)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  if (!auth.isAuthenticated) return router.replace('/login')
  await auth.loadProfile()
  if (!['admin', 'teacher'].includes(auth.user?.role ?? '')) return router.replace('/dashboard')
  try {
    const [categoryResponse, courseResponse] = await Promise.all([
      adminApi.categories(),
      isAdmin.value ? adminApi.courses() : adminApi.teacherCourses(),
    ])
    if (isAdmin.value) users.value = (await adminApi.users()).data
    categories.value = categoryResponse.data
    courses.value = courseResponse.data
  } catch (exception) {
    error.value =
      exception instanceof Error ? exception.message : 'Не удалось загрузить данные админ-панели.'
  } finally {
    loading.value = false
  }
})

async function saveCategory() {
  if (!newCategory.value.trim()) return
  if (editingCategoryId.value === null) {
    categories.value.push(await adminApi.createCategory(newCategory.value.trim()))
  } else {
    const updated = await adminApi.updateCategory(editingCategoryId.value, newCategory.value.trim())
    const index = categories.value.findIndex((category) => category.id === updated.id)
    if (index !== -1) categories.value[index] = updated
  }
  editingCategoryId.value = null
  newCategory.value = ''
}

function startEditCategory(category: Category) {
  editingCategoryId.value = category.id
  newCategory.value = category.name
}

async function deleteCategory(category: Category) {
  if (!window.confirm(`Удалить категорию «${category.name}»?`)) return
  await adminApi.deleteCategory(category.id)
  categories.value = categories.value.filter((item) => item.id !== category.id)
}

async function createCourse() {
  courseError.value = ''
  courseSuccess.value = ''
  if (
    !courseTitle.value.trim() ||
    !courseDescription.value.trim() ||
    !coursePrice.value ||
    courseCategoryId.value === ''
  ) {
    courseError.value = 'Заполните все поля курса.'
    return
  }
  courseSaving.value = true
  try {
    const payload = {
      title: courseTitle.value.trim(),
      description: courseDescription.value.trim(),
      price: Number(coursePrice.value),
      category_id: Number(courseCategoryId.value),
    }
    const course =
      courseModalMode.value === 'edit' && editingCourseId.value !== null
        ? await adminApi.updateCourse(editingCourseId.value, payload)
        : await adminApi.createCourse(payload)
    if (courseModalMode.value === 'edit') {
      const index = courses.value.findIndex((item) => item.id === course.id)
      if (index !== -1) courses.value[index] = course
    } else {
      courses.value.unshift(course)
    }
    courseTitle.value = ''
    courseDescription.value = ''
    coursePrice.value = ''
    courseCategoryId.value = ''
    courseSuccess.value =
      courseModalMode.value === 'edit' ? 'Изменения курса сохранены.' : 'Курс создан как черновик.'
    courseModalOpen.value = false
    categoryMenuOpen.value = false
  } catch (exception) {
    courseError.value = exception instanceof Error ? exception.message : 'Не удалось создать курс.'
  } finally {
    courseSaving.value = false
  }
}

function openCreateCourse() {
  courseModalMode.value = 'create'
  editingCourseId.value = null
  courseTitle.value = ''
  courseDescription.value = ''
  coursePrice.value = ''
  courseCategoryId.value = ''
  courseError.value = ''
  courseModalOpen.value = true
}

function openEditCourse(course: Course) {
  courseModalMode.value = 'edit'
  editingCourseId.value = course.id
  courseTitle.value = course.title
  courseDescription.value = course.description
  coursePrice.value = String(course.price)
  courseCategoryId.value = course.category_id
  courseError.value = ''
  courseModalOpen.value = true
}

function requestDeleteCourse(course: Course) {
  deleteCourseTarget.value = course
}

async function confirmDeleteCourse() {
  if (!deleteCourseTarget.value) return
  const course = deleteCourseTarget.value
  await adminApi.deleteCourse(course.id)
  courses.value = courses.value.filter((item) => item.id !== course.id)
  if (openCourseId.value === course.id) openCourseId.value = null
  deleteCourseTarget.value = null
}

async function publishCourse(course: Course) {
  try {
    const published = await adminApi.publishCourse(course.id)
    const index = courses.value.findIndex((item) => item.id === published.id)
    if (index !== -1) courses.value[index] = published
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось опубликовать курс.'
  }
}

function requestUnpublishCourse(course: Course) {
  unpublishCourseTarget.value = course
}

async function confirmUnpublishCourse() {
  if (!unpublishCourseTarget.value) return
  const course = unpublishCourseTarget.value
  try {
    const draft = await adminApi.unpublishCourse(course.id)
    const index = courses.value.findIndex((item) => item.id === draft.id)
    if (index !== -1) courses.value[index] = draft
    unpublishCourseTarget.value = null
  } catch (exception) {
    error.value =
      exception instanceof Error ? exception.message : 'Не удалось вернуть курс в черновики.'
  }
}

function selectCategory(categoryId: number) {
  courseCategoryId.value = categoryId
  categoryMenuOpen.value = false
}

function lessonCount(course: Course): number {
  return courseLessons.value[course.id]?.length ?? course.lessons_count ?? 0
}

async function openLessonModal(course: Course) {
  lessonError.value = ''
  lessonTargetCourse.value = course
  lessonModalMode.value = 'create'
  editingLessonId.value = null
  lessonTitle.value = ''
  lessonContent.value = ''
  lessonFile.value = null
  if (!courseLessons.value[course.id]) {
    courseLessons.value[course.id] = (await adminApi.lessons(course.id)).data
  }
  lessonOrder.value = String((courseLessons.value[course.id]?.length ?? 0) + 1)
  lessonModalOpen.value = true
}

function openEditLesson(course: Course, lesson: AdminLesson) {
  lessonTargetCourse.value = course
  lessonModalMode.value = 'edit'
  editingLessonId.value = lesson.id
  lessonTitle.value = lesson.title
  lessonContent.value = lesson.content
  lessonOrder.value = String(lesson.order_number)
  lessonFile.value = null
  lessonError.value = ''
  lessonModalOpen.value = true
}

function requestDeleteLesson(lesson: AdminLesson) {
  deleteLessonTarget.value = lesson
}

async function confirmDeleteLesson() {
  if (!deleteLessonTarget.value) return
  const lesson = deleteLessonTarget.value
  await adminApi.deleteLesson(lesson.id)
  for (const courseId of Object.keys(courseLessons.value)) {
    courseLessons.value[Number(courseId)] = (courseLessons.value[Number(courseId)] ?? []).filter(
      (item) => item.id !== lesson.id,
    )
  }
  deleteLessonTarget.value = null
}

async function toggleCourse(course: Course) {
  if (openCourseId.value === course.id) {
    openCourseId.value = null
    return
  }
  openCourseId.value = course.id
  lessonError.value = ''
  lessonTitle.value = ''
  lessonContent.value = ''
  if (!courseLessons.value[course.id]) {
    courseLessons.value[course.id] = (await adminApi.lessons(course.id)).data
  }
  lessonOrder.value = String((courseLessons.value[course.id]?.length ?? 0) + 1)
}

async function createLesson(course: Course) {
  lessonError.value = ''
  if (!lessonTitle.value.trim() || !lessonContent.value.trim() || !lessonOrder.value) {
    lessonError.value = 'Заполните название, содержание и порядок урока.'
    return
  }
  lessonSaving.value = true
  try {
    const formData = new FormData()
    formData.append('title', lessonTitle.value.trim())
    formData.append('content', lessonContent.value.trim())
    formData.append('order_number', lessonOrder.value)
    if (lessonFile.value) formData.append('file', lessonFile.value)
    const lesson =
      lessonModalMode.value === 'edit' && editingLessonId.value !== null
        ? await adminApi.updateLesson(editingLessonId.value, {
            title: lessonTitle.value.trim(),
            content: lessonContent.value.trim(),
            order_number: Number(lessonOrder.value),
          })
        : await adminApi.createLesson(course.id, formData)
    courseLessons.value[course.id] = [
      ...(courseLessons.value[course.id] ?? []).filter((item) => item.id !== lesson.id),
      lesson,
    ].sort((a, b) => a.order_number - b.order_number)
    lessonTitle.value = ''
    lessonContent.value = ''
    lessonFile.value = null
    lessonOrder.value = String((courseLessons.value[course.id]?.length ?? 0) + 1)
    lessonModalOpen.value = false
    lessonTargetCourse.value = null
    editingLessonId.value = null
  } catch (exception) {
    lessonError.value = exception instanceof Error ? exception.message : 'Не удалось добавить урок.'
  } finally {
    lessonSaving.value = false
  }
}
</script>

<template>
  <section class="mx-auto flex max-w-7xl flex-col px-6 py-12">
    <div v-if="loading" class="text-sm text-slate-500">Загружаем админ-панель…</div>
    <div v-else-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ error }}
    </div>
    <template v-else>
      <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
          <h1 class="text-4xl font-bold">{{ isAdmin ? 'Управление платформой' : 'Управление курсами' }}</h1>
          <p class="mt-3 text-slate-600">
            {{ isAdmin ? 'Курсы, категории и общая статистика платформы.' : 'Создавайте курсы, добавляйте уроки и управляйте учебными материалами.' }}
          </p>
        </div>
      </div>
      <div v-if="isAdmin" class="mt-8 grid gap-5 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm text-slate-500">Пользователи</p>
          <p class="mt-2 text-3xl font-bold">{{ users.length }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm text-slate-500">Категории</p>
          <p class="mt-2 text-3xl font-bold">{{ categories.length }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm text-slate-500">Заблокировано</p>
          <p class="mt-2 text-3xl font-bold">
            {{ users.filter((user) => user.is_blocked).length }}
          </p>
        </div>
      </div>
      <div
        class="order-2 mt-8 flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
      >
        <div>
            <h2 class="text-xl font-bold">{{ isAdmin ? 'Курсы' : 'Мои курсы' }}</h2>
          <p class="mt-1 text-sm text-slate-500">
            Создавайте курсы и добавляйте учебные материалы.
          </p>
        </div>
        <AppButton type="button" @click="openCreateCourse">Создать курс</AppButton>
      </div>
      <div
        v-if="courseModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/30 p-4"
      >
        <div
          class="max-h-[calc(100vh-2rem)] w-full max-w-xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6"
        >
          <div class="flex items-start justify-between gap-4">
            <h2 class="text-xl font-bold">
              {{ courseModalMode === 'edit' ? 'Редактировать курс' : 'Создать курс' }}
            </h2>
            <button
              type="button"
              class="rounded-lg px-3 py-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
              aria-label="Закрыть"
              @click="courseModalOpen = false"
            >
              ✕
            </button>
          </div>
          <div v-if="courseError" class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ courseError }}
          </div>
          <div
            v-if="courseSuccess"
            class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
          >
            {{ courseSuccess }}
          </div>
          <div
            v-if="categories.length === 0"
            class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-700"
          >
            Сначала добавьте категорию курса в разделе ниже.
          </div>
          <form class="mt-5 grid gap-4" @submit.prevent="createCourse">
            <label class="text-sm font-medium text-slate-700"
              >Название курса<input
                v-model="courseTitle"
                type="text"
                placeholder="Например, Основы веб-разработки"
                class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                required /></label
            ><label class="text-sm font-medium text-slate-700"
              >Категория
              <div class="relative mt-2">
                <button
                  type="button"
                  class="flex h-[46px] w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-3 text-left text-sm font-medium text-slate-700 outline-none transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                  :class="categoryMenuOpen ? 'border-indigo-500 ring-4 ring-indigo-100' : ''"
                  @click="categoryMenuOpen = !categoryMenuOpen"
                >
                  <span :class="courseCategoryId === '' ? 'text-slate-400' : 'text-slate-700'">{{
                    courseCategoryId === ''
                      ? 'Выберите категорию'
                      : categories.find((category) => category.id === courseCategoryId)?.name
                  }}</span
                  ><svg
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4 text-slate-400 transition"
                    :class="categoryMenuOpen ? 'rotate-180' : ''"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M5.2 7.5a.75.75 0 1 1 1.06.02L10 11.4l3.74-3.88a.75.75 0 1 1 1.08 1.04l-4.28 4.44a.75.75 0 0 1-1.08 0L5.18 8.56a.75.75 0 0 1 .02-1.06Z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </button>
                <div
                  v-if="categoryMenuOpen"
                  class="absolute left-0 right-0 top-full z-30 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
                >
                  <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm text-slate-700 transition hover:bg-indigo-50 hover:text-indigo-700"
                    :class="
                      courseCategoryId === category.id
                        ? 'bg-indigo-50 font-semibold text-indigo-700'
                        : ''
                    "
                    @click="selectCategory(category.id)"
                  >
                    {{ category.name
                    }}<span v-if="courseCategoryId === category.id" class="text-indigo-600">✓</span>
                  </button>
                </div>
              </div></label
            ><label class="text-sm font-medium text-slate-700"
              >Цена, UZS<input
                v-model="coursePrice"
                type="number"
                min="0"
                step="1"
                placeholder="Например, 250000"
                class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                required /></label
            ><label class="text-sm font-medium text-slate-700"
              >Описание<textarea
                v-model="courseDescription"
                rows="4"
                placeholder="Кратко опишите содержание и результат обучения"
                class="mt-2 w-full resize-y rounded-xl border border-slate-300 px-3 py-2.5 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                required
              ></textarea>
            </label>
            <div class="flex justify-end">
              <AppButton type="submit" :disabled="courseSaving || categories.length === 0">{{
                courseSaving
                  ? 'Сохраняем…'
                  : courseModalMode === 'edit'
                    ? 'Сохранить изменения'
                    : 'Создать курс'
              }}</AppButton>
            </div>
          </form>
        </div>
      </div>
      <div
        v-if="deleteCourseTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/30 p-4"
      >
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
          <h2 class="text-xl font-bold">Удалить курс?</h2>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            Курс «{{ deleteCourseTarget.title }}» и его материалы будут удалены без возможности
            восстановления.
          </p>
          <div class="mt-6 flex justify-end gap-3">
            <AppButton type="button" variant="secondary" @click="deleteCourseTarget = null"
              >Отмена</AppButton
            ><AppButton
              type="button"
              class="bg-red-600 hover:bg-red-700"
              @click="confirmDeleteCourse"
              >Удалить</AppButton
            >
          </div>
        </div>
      </div>
      <div
        v-if="unpublishCourseTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/30 p-4"
      >
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
          <div
            class="flex h-11 w-11 items-center justify-center rounded-full bg-amber-50 text-amber-600"
          >
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              class="h-5 w-5"
            >
              <path
                d="M12 8v4m0 4h.01M4.8 19h14.4a1.8 1.8 0 0 0 1.56-2.7L13.56 3.8a1.8 1.8 0 0 0-3.12 0L3.24 16.3A1.8 1.8 0 0 0 4.8 19Z"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>
          <h2 class="mt-4 text-xl font-bold">Вернуть курс в черновик?</h2>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            Курс «{{ unpublishCourseTarget.title }}» перестанет отображаться в каталоге. Вы сможете
            опубликовать его снова.
          </p>
          <div class="mt-6 flex justify-end gap-3">
            <AppButton type="button" variant="secondary" @click="unpublishCourseTarget = null"
              >Отмена</AppButton
            ><AppButton type="button" @click="confirmUnpublishCourse">Вернуть в черновик</AppButton>
          </div>
        </div>
      </div>
      <div class="order-3 mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold">{{ isAdmin ? 'Все курсы' : 'Мои курсы' }}</h2>
            <p class="mt-1 text-sm text-slate-500">
              {{ isAdmin ? 'Курсы всех преподавателей и учебные материалы.' : 'Созданные курсы и учебные материалы.' }}
            </p>
          </div>
          <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{
            courses.length
          }}</span>
        </div>
        <div
          v-if="courses.length === 0"
          class="mt-5 rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
        >
          Курсов пока нет. Создайте первый черновик выше.
        </div>
        <div v-else class="mt-5 space-y-4">
          <article
            v-for="course in courses"
            :key="course.id"
            class="rounded-2xl border border-slate-200 p-5"
          >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
              <div class="min-w-0 flex-1">
                <h3 class="font-bold text-slate-900">{{ course.title }}</h3>
                <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ course.description }}</p>
                <div v-if="isAdmin" class="mt-3 inline-flex items-center gap-2 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                  <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700">
                    {{ (course.author?.name ?? 'А').charAt(0).toUpperCase() }}
                  </span>
                  <span>Преподаватель: {{ course.author?.name ?? 'не указан' }}</span>
                </div>
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <span
                  :class="
                    course.status === 'published'
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-amber-50 text-amber-700'
                  "
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-semibold"
                  ><svg
                    v-if="course.status === 'published'"
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="h-3.5 w-3.5"
                    aria-hidden="true"
                  >
                    <path
                      d="m4.5 10.5 3.5 3.5 7.5-8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    /></svg
                  ><svg
                    v-else
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    class="h-3.5 w-3.5"
                    aria-hidden="true"
                  >
                    <path
                      d="M5 3.5h7l3 3V16.5H5V3.5Z M12 3.5v3h3"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    /></svg
                  >{{ course.status === 'published' ? 'Опубликован' : 'Черновик' }}</span
                >
                <div
                  class="inline-flex overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
                >
                  <button
                    type="button"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-indigo-50 hover:text-indigo-600"
                    aria-label="Редактировать курс"
                    title="Редактировать"
                    @click="openEditCourse(course)"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.7"
                      class="h-4 w-4"
                    >
                      <path
                        d="m13.7 3.3 3 3M4 16l.6-3.2L13.8 3.6a1.4 1.4 0 0 1 2 0l.6.6a1.4 1.4 0 0 1 0 2L6.8 15.4 4 16Z"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg></button
                  ><span class="w-px bg-slate-200" aria-hidden="true"></span
                  ><button
                    type="button"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                    aria-label="Удалить курс"
                    title="Удалить"
                    @click="requestDeleteCourse(course)"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.7"
                      class="h-4 w-4"
                    >
                      <path
                        d="M4.5 6h11M8 3.5h4M7 6v9.5m6-9.5v9.5M5.5 6l.6 10a1 1 0 0 0 1 .9h5.8a1 1 0 0 0 1-.9l.6-10"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                </div>
              </div>
              <div
                class="flex shrink-0 items-center justify-end gap-4 text-sm text-slate-500 lg:border-l lg:border-slate-200 lg:pl-6"
              >
                <div class="flex items-center gap-3">
                  <button
                    v-if="course.status !== 'published'"
                    type="button"
                    title="Опубликовать курс"
                    aria-label="Опубликовать курс"
                    class="w-40 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700"
                    @click="publishCourse(course)"
                  >
                    Опубликовать
                  </button>
                  <button
                    v-else
                    type="button"
                    title="Вернуть курс в черновик"
                    aria-label="Вернуть курс в черновик"
                    class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-amber-400 hover:bg-amber-50 hover:text-amber-700"
                    @click="requestUnpublishCourse(course)"
                  >
                    В черновик
                  </button>
                  <AppButton
                    type="button"
                    title="Добавить урок"
                    aria-label="Добавить урок"
                    variant="secondary"
                    class="w-44 shrink-0"
                    @click="openLessonModal(course)"
                  >
                    <span class="inline-flex items-center gap-1.5"
                      ><svg
                        viewBox="0 0 20 20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        class="h-4 w-4"
                      >
                        <path d="M10 4v12M4 10h12" stroke-linecap="round" /></svg
                      >Добавить урок</span
                    >
                  </AppButton>
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                    @click="toggleCourse(course)"
                  >
                    <span>({{ lessonCount(course) }})</span>
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      class="h-4 w-4 transition-transform duration-200"
                      :class="openCourseId === course.id ? 'rotate-180' : ''"
                      aria-hidden="true"
                    >
                      <path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
            <div v-if="openCourseId === course.id" class="mt-5 border-t border-slate-100 pt-5">
              <div
                v-if="lessonError"
                class="mb-4 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-700"
              >
                {{ lessonError }}
              </div>
              <div v-if="courseLessons[course.id]?.length" class="mb-5 space-y-2">
                <div
                  v-for="lesson in courseLessons[course.id]"
                  :key="lesson.id"
                  class="flex items-start justify-between gap-4 rounded-xl bg-slate-50 px-3 py-2"
                >
                  <div class="min-w-0">
                    <p class="text-sm font-semibold">{{ lesson.order_number }}. {{ lesson.title }}</p>
                    <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ lesson.content }}</p>
                  </div>
                  <div class="flex shrink-0 items-center gap-1">
                    <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-indigo-100 hover:text-indigo-600" title="Редактировать урок" aria-label="Редактировать урок" @click="openEditLesson(course, lesson)">
                      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" class="h-4 w-4"><path d="m13.7 3.3 3 3M4 16l.6-3.2L13.8 3.6a1.4 1.4 0 0 1 2 0l.6.6a1.4 1.4 0 0 1 0 2L6.8 15.4 4 16Z" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                    <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-red-100 hover:text-red-600" title="Удалить урок" aria-label="Удалить урок" @click="requestDeleteLesson(lesson)">
                      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" class="h-4 w-4"><path d="M4.5 6h11M8 3.5h4M7 6v9.5m6-9.5v9.5M5.5 6l.6 10a1 1 0 0 0 1 .9h5.8a1 1 0 0 0 1-.9l.6-10" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                  </div>
                </div>
              </div>
              <p v-else class="mb-4 text-sm text-slate-500">Уроков пока нет.</p>
            </div>
          </article>
        </div>
      </div>
      <div
        v-if="lessonModalOpen && lessonTargetCourse"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/30 p-4"
      >
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-indigo-600">Учебный материал</p>
              <h2 class="mt-1 text-2xl font-bold">{{ lessonModalMode === 'edit' ? 'Редактировать урок' : 'Добавить урок' }}</h2>
              <p class="mt-1 text-sm text-slate-500">Курс: {{ lessonTargetCourse.title }}</p>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700"
              aria-label="Закрыть"
              @click="lessonModalOpen = false"
            >
              ×
            </button>
          </div>
          <div v-if="lessonError" class="mt-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ lessonError }}
          </div>
          <form class="mt-5 space-y-4" @submit.prevent="createLesson(lessonTargetCourse!)">
            <label class="block text-sm font-semibold text-slate-700">
              Название урока
              <input
                v-model="lessonTitle"
                placeholder="Например, Введение в тему"
                class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                required
              />
            </label>
            <label class="block text-sm font-semibold text-slate-700">
              Содержание урока
              <textarea
                v-model="lessonContent"
                rows="4"
                placeholder="Опишите содержание и материалы урока"
                class="mt-2 w-full resize-y rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                required
              ></textarea>
            </label>
            <label class="block text-sm font-semibold text-slate-700">
              Порядковый номер
              <input
                v-model="lessonOrder"
                type="number"
                min="1"
                class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                required
              />
            </label>
            <label class="block text-sm font-semibold text-slate-700">
              Файл или видео
              <span class="font-normal text-slate-400">(до 10 МБ, необязательно)</span>
              <input
                type="file"
                accept="video/*,image/*,.pdf,.doc,.docx,.ppt,.pptx"
                class="mt-2 block w-full cursor-pointer rounded-xl border border-dashed border-slate-300 px-3 py-2.5 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700 hover:border-indigo-400"
                @change="lessonFile = ($event.target as HTMLInputElement).files?.[0] ?? null"
              />
            </label>
            <div class="flex justify-end gap-3 pt-2">
              <AppButton type="button" variant="secondary" @click="lessonModalOpen = false"
                >Отмена</AppButton
              >
              <AppButton type="submit" :disabled="lessonSaving">{{
                lessonSaving ? 'Сохраняем…' : lessonModalMode === 'edit' ? 'Сохранить изменения' : 'Добавить урок'
              }}</AppButton>
            </div>
          </form>
        </div>
      </div>
      <div v-if="deleteLessonTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/30 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
          <h2 class="text-xl font-bold">Удалить урок?</h2>
          <p class="mt-2 text-sm leading-6 text-slate-500">Урок «{{ deleteLessonTarget.title }}» будет удалён без возможности восстановления.</p>
          <div class="mt-6 flex justify-end gap-3">
            <AppButton type="button" variant="secondary" @click="deleteLessonTarget = null">Отмена</AppButton>
            <AppButton type="button" variant="danger" @click="confirmDeleteLesson">Удалить</AppButton>
          </div>
        </div>
      </div>
      <div v-if="isAdmin" class="order-1 mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold">Категории курсов</h2>
            <p class="mt-1 text-sm text-slate-500">
              Управляйте категориями, доступными при создании курса.
            </p>
          </div>
          <button
            type="button"
            :aria-label="categoryPanelOpen ? 'Скрыть категории' : 'Открыть категории'"
            :title="categoryPanelOpen ? 'Скрыть категории' : 'Открыть категории'"
            class="flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-indigo-50 hover:text-indigo-700"
            @click="categoryPanelOpen = !categoryPanelOpen"
          >
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              class="h-5 w-5 transition-transform duration-200"
              :class="categoryPanelOpen ? 'rotate-180' : ''"
              aria-hidden="true"
            >
              <path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
        </div>
        <form v-if="categoryPanelOpen" class="mt-4 flex gap-3" @submit.prevent="saveCategory">
          <input
            v-model="newCategory"
            placeholder="Название категории"
            class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
          /><AppButton type="submit">{{
            editingCategoryId === null ? 'Добавить' : 'Сохранить'
          }}</AppButton
          ><AppButton
            v-if="editingCategoryId !== null"
            type="button"
            variant="secondary"
            @click="
              editingCategoryId = null;
              newCategory = ''
            "
            >Отмена</AppButton
          >
        </form>
        <div v-if="categoryPanelOpen" class="mt-5 space-y-2">
          <div
            v-for="category in categories"
            :key="category.id"
            class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"
          >
            <span class="text-sm font-medium text-slate-700">{{ category.name }}</span
            ><span class="flex gap-2"
              ><AppButton variant="secondary" class="text-xs" @click="startEditCategory(category)"
                >Изменить</AppButton
              ><AppButton
                variant="secondary"
                class="text-xs text-red-600"
                @click="deleteCategory(category)"
                >Удалить</AppButton
              ></span
            >
          </div>
        </div>
      </div>
    </template>
  </section>
</template>
