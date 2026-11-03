import { ref, watch } from 'vue'

type Theme = 'light' | 'dark'

const savedTheme = localStorage.getItem('lm-studio-theme') as Theme | null

const theme = ref<Theme>(
  savedTheme === 'light' || savedTheme === 'dark'
    ? savedTheme
    : 'dark',
)

watch(
  theme,
  (value) => {
    document.documentElement.classList.toggle('dark', value === 'dark')
    localStorage.setItem('lm-studio-theme', value)
  },
  { immediate: true },
)

export function useTheme() {
  function toggleTheme() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
  }

  return {
    theme,
    toggleTheme,
  }
}
