/**
 * UI y datos solo para desarrollo (`npm run dev`).
 * En `npm run build` / producción es siempre false: sin modales técnicos, JSON de depuración
 * ni credenciales demo visibles en el HTML para el cliente final.
 *
 * @see https://vite.dev/guide/env-and-mode
 */
export const DEBUG_UI_ENABLED = import.meta.env.DEV === true
