import { computed, ref, watch } from 'vue'

type Theme = 'light' | 'dark'

const STORAGE_KEY = 'lm-studio-theme'

function readInitialTheme(): Theme {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved === 'light' || saved === 'dark') return saved
  } catch {
    // El almacenamiento puede no estar disponible (modo privado, etc.).
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

const theme = ref<Theme>(readInitialTheme())

watch(
  theme,
  (value) => {
    document.documentElement.classList.toggle('dark', value === 'dark')
    try {
      localStorage.setItem(STORAGE_KEY, value)
    } catch {
      // Se ignora: el tema simplemente no se recuerda.
    }
  },
  { immediate: true },
)

export function useTheme() {
  const isDark = computed(() => theme.value === 'dark')

  function setTheme(value: Theme) {
    theme.value = value
  }

  function toggleTheme() {
    theme.value = isDark.value ? 'light' : 'dark'
  }

  return { theme, isDark, setTheme, toggleTheme }
}
