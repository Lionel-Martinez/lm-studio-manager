import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'ar.lmstudio.manager',
  appName: 'LM Studio',
  webDir: 'dist',
  // La app habla solo con tu servidor por https (Tailscale); no se permite tráfico sin cifrar.
  server: { androidScheme: 'https', cleartext: false },
}

export default config
