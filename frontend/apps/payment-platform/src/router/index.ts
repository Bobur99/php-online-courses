import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', component: () => import('../views/PaymentDashboardView.vue') },
    { path: '/pay/:operationId', component: () => import('../views/PaymentView.vue') },
  ],
})

export default router
