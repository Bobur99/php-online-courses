import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/courses' },
    { path: '/courses', component: () => import('../views/CourseCatalogView.vue') },
    { path: '/courses/:id', component: () => import('../views/CourseDetailsView.vue') },
    { path: '/checkout/:courseId', component: () => import('../views/CheckoutView.vue') },
    { path: '/login', component: () => import('../views/LoginView.vue'), meta: { guestOnly: true } },
    { path: '/register', component: () => import('../views/RegisterView.vue'), meta: { guestOnly: true } },
    { path: '/dashboard', component: () => import('../views/DashboardView.vue'), meta: { requiresAuth: true } },
    { path: '/teacher', component: () => import('../views/AdminDashboardView.vue'), meta: { requiresAuth: true } },
    { path: '/admin', component: () => import('../views/AdminDashboardView.vue'), meta: { requiresAuth: true } },
    { path: '/admin/users', component: () => import('../views/AdminUsersView.vue'), meta: { requiresAuth: true } },
    { path: '/profile', component: () => import('../views/ProfileView.vue'), meta: { requiresAuth: true } },
  ],
})

router.beforeEach((to) => {
  if (to.meta.guestOnly && localStorage.getItem('auth_token')) {
    return { path: '/dashboard' }
  }
  if (to.meta.requiresAuth && !localStorage.getItem('auth_token')) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
})

export default router
