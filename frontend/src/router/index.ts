import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true, title: 'Acceso' },
    },
    {
      // Fuera del layout: es una página limpia pensada para imprimir o guardar como PDF.
      path: '/comprobantes/:id',
      name: 'comprobante',
      component: () => import('@/views/ReceiptView.vue'),
      meta: { requiresAuth: true, title: 'Comprobante' },
    },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
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
          path: 'agenda',
          name: 'agenda',
          component: () => import('@/views/AgendaView.vue'),
          meta: {
            title: 'Agenda',
            description: 'Entregas, cobros y recordatorios por fecha.',
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
          path: 'notas',
          name: 'notas',
          component: () => import('@/views/NotesView.vue'),
          meta: {
            title: 'Notas',
            description: 'Documentación, acuerdos y plantillas en Markdown.',
          },
        },
        {
          path: 'cuenta',
          name: 'cuenta',
          component: () => import('@/views/AccountView.vue'),
          meta: {
            title: 'Cuenta',
            description: 'Contraseña y actividad de tu sesión.',
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

// Todo lo que no sea el login exige sesión iniciada.
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.checked) {
    try {
      await auth.fetchUser()
    } catch {
      // Sin conexión con el backend: se trata como sin sesión.
      auth.clear()
    }
  }

  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : undefined }
  }

  if (to.meta.guest && auth.isLoggedIn) return { path: '/' }
})

export default router
