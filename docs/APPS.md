# Apps de escritorio (Linux) y celular (Android) — LM Studio

Un único servidor (esta misma API Laravel + MySQL) corre en tu PC con Linux. Las apps son el mismo frontend
empaquetado y se conectan a ese servidor por **Tailscale**, así que todo queda sincronizado y no hace falta dominio.

```
Celular (Android) ──┐
                    ├── Tailscale (red privada, HTTPS) ──► tu PC: Laravel + MySQL
App Linux ──────────┘
```

Limitaciones que conviene saber:
- La PC tiene que estar **encendida** y con Tailscale activo para que las apps funcionen (no hay edición sin conexión).
- iOS queda preparado (`@capacitor/ios`), pero compilarlo exige una Mac y una cuenta de Apple Developer.
- En el celular se ocultan «Exportar CSV» e «Imprimir comprobante» (no están verificados en Android).

## 1. Preparar el servidor en tu PC

1. Seguí la instalación normal del backend (`backend/README` o `DEPLOY.md`): PHP 8.4, Composer, MySQL, `.env`, `php artisan key:generate`, `php artisan migrate`.
2. En `backend/.env`:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://TU-PC.TU-TAILNET.ts.net
   TRUSTED_PROXIES=127.0.0.1
   ```
3. `composer install --no-dev --optimize-autoloader` y `php artisan config:cache route:cache`.
4. Servicios (reemplazá `TU_USUARIO` y la ruta en los dos archivos):
   ```bash
   sudo cp deploy/pc/lm-studio-*.service /etc/systemd/system/
   sudo systemctl daemon-reload
   sudo systemctl enable --now lm-studio-api lm-studio-schedule
   ```

## 2. Tailscale

1. Instalá Tailscale en la PC, en el celular y (si querés) en otras computadoras; iniciá sesión con **la misma cuenta**.
2. En la consola de Tailscale activá **MagicDNS** y **HTTPS Certificates**.
3. En la PC:
   ```bash
   sudo tailscale up
   sudo tailscale serve --bg 8000
   tailscale serve status    # muestra la dirección https://TU-PC.TU-TAILNET.ts.net
   ```
   Esa dirección es la que escribís en las apps. Solo responde a dispositivos de tu red Tailscale (**no** uses `tailscale funnel`: publicaría el servidor en internet).

## 3. Compilar las apps (GitHub Actions)

1. En GitHub: **Actions → Build apps (Linux + Android) → Run workflow**.
2. Al terminar, descargá los artefactos: `lm-studio-linux` (`.AppImage` y `.deb`) y `lm-studio-android` (`.apk`).

### Firma estable del APK (una sola vez)
Sin firma propia, cada APK tiene otra firma y Android no permite actualizar encima. Generá la clave **una vez** y guardala en un lugar seguro (si la perdés no podés actualizar la app):
```bash
keytool -genkeypair -v -keystore lmstudio.jks -alias lmstudio -keyalg RSA -keysize 2048 -validity 10000
base64 -w0 lmstudio.jks    # copiar el resultado
```
En GitHub → Settings → Secrets and variables → Actions, creá: `ANDROID_KEYSTORE_B64` (lo copiado), `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS` (`lmstudio`), `ANDROID_KEY_PASSWORD`. No subas el `.jks` al repositorio.

## 4. Instalar y conectar

- **Linux**: `sudo apt install ./LM\ Studio_*.deb` (o dar permisos de ejecución al `.AppImage`).
- **Android**: pasá el `.apk` al celular y habilitá «instalar apps desconocidas» para el navegador/archivos. Tené Tailscale instalado y activo.
- Al abrir la app por primera vez pide la **dirección del servidor** (`https://TU-PC.TU-TAILNET.ts.net`), después tu correo, contraseña y el código 2FA si lo tenés. Cada dispositivo queda en **Cuenta → Dispositivos** y desde ahí podés cerrar su sesión; los tokens vencen a los 90 días sin uso.

## 5. Desarrollo local

```bash
cd frontend
npm run dev -- --mode native      # frontend en modo app contra tu servidor
npm run tauri dev                 # ventana de escritorio (requiere Rust y libwebkit2gtk-4.1-dev)
npm run build:android             # APK de prueba (requiere Android SDK)
```

## Copias de seguridad
Toda la información vive en la MySQL de tu PC: mantené los backups (`php artisan app:backup` / ver `DEPLOY.md`) en otro disco o nube.
