import { nextTick, ref, watch } from 'vue'

import { useTheme } from '@/composables/useTheme'

/**
 * Chart.js dibuja en canvas y no entiende variables CSS, así que leemos
 * los valores actuales del tema y los volvemos a leer cuando cambia.
 */
const categorical = {
  light: ['#b4451f', '#3f5f73', '#8a6f3d', '#6b6358', '#7a4e6d'],
  dark: ['#e0794f', '#7fa6bf', '#d2b06a', '#a89f8e', '#c392b5'],
}

function readColors(isDark: boolean) {
  const styles = getComputedStyle(document.documentElement)
  const read = (name: string) => styles.getPropertyValue(name).trim()

  return {
    text: read('--app-text'),
    muted: read('--app-muted'),
    border: read('--app-border'),
    surface: read('--app-surface'),
    bar: read('--app-bar'),
    accent: read('--app-accent'),
    series: isDark ? categorical.dark : categorical.light,
  }
}

export function useChartColors() {
  const { isDark } = useTheme()
  const colors = ref(readColors(isDark.value))

  watch(isDark, async (value) => {
    await nextTick()
    colors.value = readColors(value)
  })

  return colors
}
