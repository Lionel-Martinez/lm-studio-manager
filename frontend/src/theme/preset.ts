import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

/**
 * Preset de PrimeVue basado en Aura, adaptado al tema "Cuaderno".
 * Los colores base replican las variables de `assets/base.css`
 * para que los componentes de PrimeVue y el resto de la app se vean iguales.
 */
const terracota = {
  50: '#fdf4ef',
  100: '#fbe5d9',
  200: '#f5c8b1',
  300: '#ec9f7e',
  400: '#e0794f',
  500: '#c95a2d',
  600: '#b4451f',
  700: '#93381a',
  800: '#772f1a',
  900: '#622a19',
  950: '#35130a',
}

export const CuadernoPreset = definePreset(Aura, {
  semantic: {
    primary: terracota,
    colorScheme: {
      light: {
        primary: {
          color: '{primary.600}',
          contrastColor: '#fffaf5',
          hoverColor: '{primary.700}',
          activeColor: '{primary.800}',
        },
        surface: {
          0: '#fbf8f2',
          50: '#f4efe6',
          100: '#ece5d8',
          200: '#e0d7c6',
          300: '#d0c6b1',
          400: '#a89f8e',
          500: '#8a8071',
          600: '#6b6358',
          700: '#4f483e',
          800: '#3a352c',
          900: '#27231d',
          950: '#1e1b16',
        },
      },
      dark: {
        primary: {
          color: '{primary.400}',
          contrastColor: '#1a1714',
          hoverColor: '{primary.300}',
          activeColor: '{primary.200}',
        },
        surface: {
          0: '#ede7da',
          50: '#e0d9ca',
          100: '#c9c1b0',
          200: '#a89f8e',
          300: '#8a8071',
          400: '#6b6358',
          500: '#4a443a',
          600: '#3a352c',
          700: '#2b2720',
          800: '#211e19',
          900: '#1a1714',
          950: '#14120f',
        },
      },
    },
  },
  components: {
    button: {
      root: {
        borderRadius: '2px',
      },
    },
  },
})
