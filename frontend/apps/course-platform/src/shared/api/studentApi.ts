import { download, request } from './http'
import type { Course } from './courseApi'

export type StudentCourse = Course & {
  lessons_count: number
  completed_lessons_count: number
  progress_percentage: number
  is_completed: boolean
}

export type Certificate = {
  id: number
  course: { id: number; title: string }
  created_at: string
  download_url: string
}

export const studentApi = {
  courses: () => request<{ data: StudentCourse[] }>('/student/courses'),
  certificates: () => request<{ data: Certificate[] }>('/certificates'),
  completeLesson: (lessonId: number) => request(`/lessons/${lessonId}/complete`, { method: 'POST' }),
  downloadCertificate: (downloadUrl: string) => download(downloadUrl),
}
