import { request } from './http'
import type { User } from './authApi'
import type { Course } from './courseApi'

export type Category = { id: number; name: string }
export type AdminLesson = { id: number; title: string; content: string; order_number: number }

export const adminApi = {
  users: () => request<{ data: User[] }>('/admin/users'),
  updateRole: (id: number, role: User['role']) => request<User>(`/admin/users/${id}/role`, { method: 'PATCH', body: JSON.stringify({ role }) }),
  setBlocked: (id: number, isBlocked: boolean) => request<User>(`/admin/users/${id}/block`, { method: 'PATCH', body: JSON.stringify({ is_blocked: isBlocked }) }),
  categories: () => request<{ data: Category[] }>('/categories'),
  createCategory: (name: string) => request<Category>('/categories', { method: 'POST', body: JSON.stringify({ name }) }),
  updateCategory: (id: number, name: string) => request<Category>(`/categories/${id}`, { method: 'PATCH', body: JSON.stringify({ name }) }),
  deleteCategory: (id: number) => request<void>(`/categories/${id}`, { method: 'DELETE' }),
  createCourse: (course: Pick<Course, 'title' | 'description' | 'price' | 'category_id'>) => request<Course>('/courses', { method: 'POST', body: JSON.stringify(course) }),
  updateCourse: (id: number, course: Partial<Pick<Course, 'title' | 'description' | 'price' | 'category_id'>>) => request<Course>(`/courses/${id}`, { method: 'PATCH', body: JSON.stringify(course) }),
  deleteCourse: (id: number) => request<void>(`/courses/${id}`, { method: 'DELETE' }),
  publishCourse: (id: number) => request<Course>(`/courses/${id}/publish`, { method: 'POST' }),
  unpublishCourse: (id: number) => request<Course>(`/courses/${id}/unpublish`, { method: 'POST' }),
  courses: () => request<{ data: Course[] }>('/admin/courses'),
  teacherCourses: () => request<{ data: Course[] }>('/teacher/courses'),
  lessons: (courseId: number) => request<{ data: AdminLesson[] }>(`/courses/${courseId}/lessons`),
  createLesson: (courseId: number, lesson: FormData | Pick<AdminLesson, 'title' | 'content' | 'order_number'>) => request<AdminLesson>(`/courses/${courseId}/lessons`, { method: 'POST', body: lesson instanceof FormData ? lesson : JSON.stringify(lesson) }),
  updateLesson: (id: number, lesson: FormData) => {
    lesson.set('_method', 'PATCH')
    return request<AdminLesson>(`/lessons/${id}`, { method: 'POST', body: lesson })
  },
  deleteLesson: (id: number) => request<void>(`/lessons/${id}`, { method: 'DELETE' }),
}
