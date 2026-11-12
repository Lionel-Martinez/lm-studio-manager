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
import { CuadernoPreset } from './theme/preset'

const app = createApp(App)

app.use(createPinia())
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

app.mount('#app')
