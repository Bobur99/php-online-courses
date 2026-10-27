import { request } from './http'

export type Course = {
  id: number
  title: string
  description: string
  price: number
  status: 'draft' | 'published'
  category_id: number
  author_id: number
  lessons_count?: number
  is_purchased?: boolean
}
export type Lesson = {
  id: number
  title: string
  content?: string
  description?: string
  order?: number
  order_number?: number
  duration_minutes?: number
  file_path?: string | null
  has_material?: boolean
}

export type CourseFilters = { search?: string; category_id?: number; teacher_id?: number; sort?: 'price' | 'created_at'; direction?: 'asc' | 'desc' }

export const courseApi = {
  list: (filters: CourseFilters = {}) => {
    const query = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => { if (value !== undefined && value !== '') query.set(key, String(value)) })
    return request<{ data: Course[] } | Course[]>(`/courses${query.size ? `?${query}` : ''}`)
  },
  show: (id: number) => request<Course>(`/courses/${id}`),
  lessons: (id: number) => request<{ data: Lesson[] }>(`/courses/${id}/lessons`),
}
