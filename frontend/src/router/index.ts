import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: AppLayout,
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/HomeView.vue'),
          meta: {
            title: 'Resumen',
            description: 'Resumen general de LM Studio.',
          },
        },
        {
          path: 'clientes',
          name: 'clientes',
          component: () => import('@/views/ClientsView.vue'),
          meta: {
            title: 'Clientes',
            description: 'Administrá tus contactos y relaciones comerciales.',
          },
        },
        {
          path: 'servicios',
          name: 'servicios',
          component: () => import('@/views/ServicesView.vue'),
          meta: {
            title: 'Servicios',
            description: 'Gestioná los servicios y proyectos contratados.',
          },
        },
        {
          path: 'pagos',
          name: 'pagos',
          component: () => import('@/views/PaymentsView.vue'),
          meta: {
            title: 'Pagos',
            description: 'Controlá ingresos, cuotas y vencimientos.',
          },
        },
        {
          path: 'reportes',
          name: 'reportes',
          component: () => import('@/views/ReportsView.vue'),
          meta: {
            title: 'Reportes',
            description: 'Analizá el rendimiento de tu negocio.',
          },
        },
        {
          path: 'geografia',
          name: 'geografia',
          component: () => import('@/views/GeographyView.vue'),
          meta: {
            title: 'Geografía',
            description: 'Distribución geográfica de tus clientes.',
          },
        },
      ],
    },
  ],
})

export default router
