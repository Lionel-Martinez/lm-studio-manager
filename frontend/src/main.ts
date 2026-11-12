import '@fontsource-variable/public-sans'
import '@fontsource-variable/newsreader/wght.css'
import '@fontsource-variable/newsreader/wght-italic.css'
import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import primeLocale from 'primelocale/es.json'

import App from './App.vue'
import router from './router'
import { onSessionExpired } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { CuadernoPreset } from './theme/preset'

const app = createApp(App)

const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(PrimeVue, {
  locale: primeLocale.es,
  theme: {
    preset: CuadernoPreset,
    options: {
      // El modo oscuro se activa con la clase `.dark` en <html> (ver useTheme).
      darkModeSelector: '.dark',
      cssLayer: {
        name: 'primevue',
        order: 'theme, base, primevue',
      },
    },
  },
})

// Si la sesión vence mientras se usa la app, se vuelve al login.
onSessionExpired(() => {
  useAuthStore(pinia).clear()
  if (router.currentRoute.value.name !== 'login') {
    router.replace({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
  }
})

app.mount('#app')
