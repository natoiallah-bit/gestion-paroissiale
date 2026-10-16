import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';

// base './' : les fichiers sont ouverts depuis le disque (Electron) ou l'APK (Capacitor)
export default defineConfig({
  base: './',
  plugins: [preact()],
  test: { environment: 'node', setupFiles: ['fake-indexeddb/auto'] },
});
