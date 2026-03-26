export const API_ACCESS_TOKEN_KEY = 'starter-web_access_token'
export const API_REFRESH_TOKEN_KEY = 'starter-web_refresh_token'
export const API_USER_KEY = 'starter-web_user'
export const API_AUTH_NOTICE_KEY = 'starter-web_auth_notice'

const DEFAULT_BASE_URL = 'http://localhost:8000/api/v1'
const AUTH_CHANGE_EVENT = 'starter-web:auth'

let refreshPromise = null

export function getApiBaseUrl() {
  const fromEnv = import.meta.env.VITE_API_BASE_URL
  if (fromEnv && typeof fromEnv === 'string') {
    return fromEnv.replace(/\/$/, '')
  }
  return DEFAULT_BASE_URL
}

export function getToken() {
  return localStorage.getItem(API_ACCESS_TOKEN_KEY) ?? ''
}

export function getRefreshToken() {
  return localStorage.getItem(API_REFRESH_TOKEN_KEY) ?? ''
}

function setAccessToken(token) {
  const t = typeof token === 'string' ? token.trim() : ''
  if (t) localStorage.setItem(API_ACCESS_TOKEN_KEY, t)
  else localStorage.removeItem(API_ACCESS_TOKEN_KEY)
}

function setRefreshToken(token) {
  const t = typeof token === 'string' ? token.trim() : ''
  if (t) localStorage.setItem(API_REFRESH_TOKEN_KEY, t)
  else localStorage.removeItem(API_REFRESH_TOKEN_KEY)
}

export function getStoredUser() {
  try {
    const raw = localStorage.getItem(API_USER_KEY)
    if (!raw) return null
    return JSON.parse(raw)
  } catch {
    return null
  }
}

export function setStoredUser(user) {
  if (user) localStorage.setItem(API_USER_KEY, JSON.stringify(user))
  else localStorage.removeItem(API_USER_KEY)
}

function notifyAuthChange() {
  window.dispatchEvent(new Event(AUTH_CHANGE_EVENT))
}

function authNoticeForReason(reason) {
  if (reason === 'session_expired' || reason === 'session_invalid') {
    return 'Tu sesion expiro o ya no es valida. Inicia sesion nuevamente.'
  }
  if (reason === 'auth_error') {
    return 'No se pudo validar tu sesion. Intenta iniciar sesion de nuevo.'
  }
  return null
}

export function setAuthNotice(message) {
  const m = typeof message === 'string' ? message.trim() : ''
  if (m) sessionStorage.setItem(API_AUTH_NOTICE_KEY, m)
  else sessionStorage.removeItem(API_AUTH_NOTICE_KEY)
}

export function consumeAuthNotice() {
  const msg = sessionStorage.getItem(API_AUTH_NOTICE_KEY)
  if (msg) sessionStorage.removeItem(API_AUTH_NOTICE_KEY)
  return msg
}

function setSessionFromTokenPayload(payload) {
  if (!payload || typeof payload !== 'object') return
  setAccessToken(payload.access_token || '')
  setRefreshToken(payload.refresh_token || '')
}

export function clearSession({ reason = null, showMessage = false } = {}) {
  setAccessToken('')
  setRefreshToken('')
  setStoredUser(null)
  if (showMessage) setAuthNotice(authNoticeForReason(reason))
  notifyAuthChange()
}

function buildUrl(path) {
  const base = getApiBaseUrl()
  return `${base}${path.startsWith('/') ? path : `/${path}`}`
}

async function parseResponse(res) {
  const text = await res.text()
  if (!text) return null
  try {
    return JSON.parse(text)
  } catch {
    return { message: text }
  }
}

/**
 * @param {Response} res
 * @param {unknown} body
 * @param {{ path?: string, method?: string, requestUrl?: string }} [meta]
 */
function normalizeError(res, body, meta = {}) {
  const msg = (body && typeof body.message === 'string' && body.message) || `Error ${res.status}`
  const err = new Error(msg)
  err.status = res.status
  err.body = body
  err.code = body?.code
  if (meta.path != null) err.requestPath = meta.path
  if (meta.method != null) err.requestMethod = meta.method
  if (meta.requestUrl != null) err.requestUrl = meta.requestUrl
  return err
}

function redactSensitiveForDebug(value) {
  if (value == null || typeof value !== 'object') return value
  try {
    const clone = JSON.parse(JSON.stringify(value))
    const walk = (x) => {
      if (!x || typeof x !== 'object') return
      for (const k of Object.keys(x)) {
        if (/token|password|secret|refresh/i.test(k) && typeof x[k] === 'string' && x[k].length > 8) {
          x[k] = '[redactado]'
        } else walk(x[k])
      }
    }
    walk(clone)
    return clone
  } catch {
    return value
  }
}

/**
 * Información extensa para depuración en pantalla (solo usar en desarrollo).
 * No incluye contraseñas del formulario; token en cuerpos de API se redacta.
 *
 * @param {unknown} err
 * @param {Record<string, unknown>} [context] — p. ej. { flow, tenantEnviado, usuario }
 */
export function buildAuthErrorDebugReport(err, context = {}) {
  const base = {
    at: new Date().toISOString(),
    viteMode: import.meta.env.MODE,
    apiBaseUrl: getApiBaseUrl(),
    ...context,
  }

  if (err == null) {
    return { ...base, note: 'err es null o undefined' }
  }
  if (typeof err !== 'object') {
    return { ...base, errorKind: typeof err, value: String(err) }
  }

  const e = err
  const out = {
    ...base,
    errorConstructor: e.constructor?.name,
    message: typeof e.message === 'string' ? e.message : undefined,
    stack: typeof e.stack === 'string' ? e.stack : undefined,
    httpStatus: Number.isFinite(e.status) ? e.status : undefined,
    apiCode: e.code ?? undefined,
    afterLoginProfile: e.afterLoginProfile === true,
    requestPath: e.requestPath,
    requestMethod: e.requestMethod,
    requestUrl: e.requestUrl ?? (e.requestPath ? `${getApiBaseUrl()}${e.requestPath.startsWith('/') ? '' : '/'}${e.requestPath}` : undefined),
    isNetworkError: e.isNetworkError === true,
    responseBody: redactSensitiveForDebug(e.body),
  }

  if (e.cause != null) {
    out.fetchCause =
      e.cause instanceof Error
        ? { name: e.cause.name, message: e.cause.message }
        : String(e.cause)
  }

  out.hints = []
  const h = out.hints
  if (out.isNetworkError) {
    h.push(
      'Fallo de red o bloqueo CORS: en DevTools → Red, busca la petición fallida. Comprueba origen del front vs CORS_ALLOWED_ORIGINS en la API.',
    )
  }
  if (out.afterLoginProfile && out.httpStatus === 401) {
    h.push(
      'El POST de login devolvió tokens pero GET /auth/me respondió 401: revisa user_sessions, middleware active.api.session y que no quede un access_token viejo en otra pestaña.',
    )
  }
  if (out.afterLoginProfile && out.httpStatus != null && out.httpStatus >= 500) {
    h.push('Error de servidor en /auth/me: revisa storage/logs/laravel.log en starter-core.')
  }
  if (!out.afterLoginProfile && out.httpStatus === 401) {
      h.push('401 en login: empresa/usuario/contraseña incorrectos, usuario sin membresía en esa empresa, o cuenta inactiva.')
  }
  if (!out.afterLoginProfile && out.httpStatus === 403) {
    h.push('403: cuenta desactivada (ACCOUNT_INACTIVE) u otro forbidden en login.')
  }
  if (out.httpStatus === 422) {
    h.push('422: cuerpo de validación en responseBody (campos requeridos o formato).')
  }
  if (out.httpStatus === 429) {
    h.push('429: rate limit; espera o ajusta throttle en la API para desarrollo.')
  }
  const sqlBlob = [out.message, typeof out.responseBody?.message === 'string' ? out.responseBody.message : ''].join(' ')
  if (/Unknown column ['`]?tenant_id['`]?/i.test(sqlBlob) && /user_sessions/i.test(sqlBlob)) {
    h.push(
      'BD desactualizada: falta user_sessions.tenant_id. En starter-core: php artisan migrate (y opcional php artisan app:setup-demo). Comprueba GET /api/v1/ready → checks.auth_schema.',
    )
  }
  if (h.length === 0) {
    h.push('Copia este JSON o la pestaña Red del navegador para revisar el flujo completo.')
  }

  return out
}

async function rawFetch(path, init = {}, options = {}) {
  const { skipAuth = false } = options
  const headers = new Headers(init.headers)
  if (!headers.has('Accept')) headers.set('Accept', 'application/json')
  if (!skipAuth) {
    const token = getToken()
    if (token) headers.set('Authorization', `Bearer ${token}`)
  }

  const requestUrl = buildUrl(path)
  const method = (init.method || 'GET').toString()

  try {
    const res = await fetch(requestUrl, { ...init, headers })
    const body = await parseResponse(res)
    return { res, body }
  } catch (cause) {
    const err = new Error(cause?.message || 'Error de red')
    err.name = cause?.name || 'NetworkError'
    err.cause = cause
    err.requestPath = path
    err.requestUrl = requestUrl
    err.requestMethod = method
    err.isNetworkError = true
    throw err
  }
}

async function refreshAccessToken() {
  const refresh = getRefreshToken()
  if (!refresh) return false

  const { res, body } = await rawFetch(
    '/auth/refresh',
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: refresh }),
    },
    { skipAuth: true },
  )

  if (!res.ok) return false
  setSessionFromTokenPayload(body?.data ?? null)
  return true
}

async function ensureFreshToken() {
  if (!refreshPromise) {
    refreshPromise = refreshAccessToken().finally(() => {
      refreshPromise = null
    })
  }
  return refreshPromise
}

/**
 * @param {string} path
 * @param {RequestInit} [init]
 * @param {{ skipAuth?: boolean, retryOn401?: boolean }} [options]
 */
export async function apiFetch(path, init = {}, options = {}) {
  const { skipAuth = false, retryOn401 = true } = options

  let { res, body } = await rawFetch(path, init, { skipAuth })
  if (res.status === 401 && !skipAuth && retryOn401 && path !== '/auth/refresh') {
    const refreshed = await ensureFreshToken()
    if (refreshed) {
      ;({ res, body } = await rawFetch(path, init, { skipAuth }))
    }
  }

  if (!res.ok) {
    if (res.status === 401 && !skipAuth) clearSession({ reason: 'session_expired', showMessage: true })
    throw normalizeError(res, body, {
      path,
      method: (init.method || 'GET').toString(),
      requestUrl: buildUrl(path),
    })
  }

  if (res.status === 204) return null
  return body?.data ?? body
}

/** Persiste perfil desde payload de GET /auth/me (incl. accessible_tenants). */
export function syncStoredUserFromMe(me) {
  if (!me?.user) return false
  setStoredUser({
    id: me.user.id,
    usuario: me.user.usuario,
    activo: me.user.activo,
    tenant_id: me.user.tenant_id,
    codigo_cliente: me.user.codigo_cliente ?? null,
    tenant: me.tenant ?? null,
    is_platform_admin: !!me.user.is_platform_admin,
    roles: Array.isArray(me.user.roles) ? me.user.roles : [],
    abilities: Array.isArray(me.user.abilities) ? me.user.abilities : [],
    accessible_tenants: Array.isArray(me.accessible_tenants) ? me.accessible_tenants : [],
  })
  return true
}

export async function refreshUserProfile() {
  const me = await getMe()
  syncStoredUserFromMe(me)
  notifyAuthChange()
  return me
}

/** Cambia empresa activa en la sesión actual (Fase 3); mismo Bearer; luego refresca /me. */
export async function switchSessionTenant({ tenant_codigo }) {
  await apiFetch('/auth/switch-tenant', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ tenant_codigo }),
  })
  return refreshUserProfile()
}

async function persistMeAfterTokens() {
  try {
    const me = await getMe()
    syncStoredUserFromMe(me)
  } catch (err) {
    clearSession({ reason: 'auth_error', showMessage: false })
    if (err && typeof err === 'object') {
      err.afterLoginProfile = true
    }
    throw err
  }
  notifyAuthChange()
}

/**
 * Login: con `tenant_codigo` (camino legacy) o solo usuario/contraseña (login global Fase 2).
 * Si el usuario tiene varias empresas, devuelve `{ needsTenantSelection: true, selection_token, tenants, expires_in }` sin fijar sesión.
 */
export async function login({ tenant_codigo, usuario, password } = {}) {
  const body = { usuario, password }
  const tenantTrim = typeof tenant_codigo === 'string' ? tenant_codigo.trim() : ''
  if (tenantTrim !== '') body.tenant_codigo = tenantTrim

  const { res, body: envelope } = await rawFetch(
    '/auth/login',
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    },
    { skipAuth: true },
  )

  if (!res.ok) {
    throw normalizeError(res, envelope, {
      path: '/auth/login',
      method: 'POST',
      requestUrl: buildUrl('/auth/login'),
    })
  }

  if (envelope?.code === 'TENANT_SELECTION_REQUIRED') {
    const d = envelope.data && typeof envelope.data === 'object' ? envelope.data : {}
    return {
      needsTenantSelection: true,
      selection_token: typeof d.selection_token === 'string' ? d.selection_token : '',
      expires_in: d.expires_in,
      tenants: Array.isArray(d.tenants) ? d.tenants : [],
    }
  }

  const tokenData = envelope?.data
  setSessionFromTokenPayload(tokenData)
  await persistMeAfterTokens()
  return tokenData
}

/** Completa login global tras elegir empresa (`POST /auth/login/select-tenant`). */
export async function selectLoginTenant({ selection_token, tenant_codigo }) {
  const { res, body: envelope } = await rawFetch(
    '/auth/login/select-tenant',
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ selection_token, tenant_codigo }),
    },
    { skipAuth: true },
  )

  if (!res.ok) {
    throw normalizeError(res, envelope, {
      path: '/auth/login/select-tenant',
      method: 'POST',
      requestUrl: buildUrl('/auth/login/select-tenant'),
    })
  }

  const tokenData = envelope?.data
  setSessionFromTokenPayload(tokenData)
  await persistMeAfterTokens()
  return tokenData
}

export async function logout() {
  try {
    await apiFetch('/auth/logout', { method: 'POST' }, { retryOn401: false })
  } catch {
    // Logout idempotente en frontend: limpiar estado local aunque backend falle.
  } finally {
    clearSession({ reason: 'manual_logout', showMessage: false })
  }
}

export function getMe() {
  return apiFetch('/auth/me', { method: 'GET' })
}

export function getHealth() {
  return apiFetch('/health', { method: 'GET' }, { skipAuth: true })
}

export function getUsers({ page, perPage } = {}) {
  const qs = new URLSearchParams()
  if (Number.isInteger(page) && page > 0) qs.set('page', String(page))
  if (Number.isInteger(perPage) && perPage > 0) qs.set('per_page', String(perPage))
  const suffix = qs.toString() ? `?${qs.toString()}` : ''
  return apiFetch(`/users${suffix}`, { method: 'GET' })
}

export function createUser(payload) {
  return apiFetch('/users', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
}

/** Crea tenant en la plataforma (Fase: super admin global). */
export function createPlatformTenant({ nombre, codigo, activo }) {
  return apiFetch('/platform/tenants', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ nombre, codigo, activo }),
  })
}

/** Crea admin inicial para tenant existente (Fase: super admin global). */
export function createPlatformTenantInitialAdmin({ tenant_codigo, admin_usuario, admin_password, admin_password_confirmation, admin_codigo_cliente }) {
  return apiFetch(`/platform/tenants/${encodeURIComponent(tenant_codigo)}/admins`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      admin_usuario,
      admin_password,
      admin_password_confirmation,
      admin_codigo_cliente: admin_codigo_cliente ?? null,
    }),
  })
}

export function updateUser(id, payload) {
  return apiFetch(`/users/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
}

export function deactivateUser(id) {
  return apiFetch(`/users/${id}/deactivate`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
  })
}

export function subscribeAuthChange(callback) {
  window.addEventListener(AUTH_CHANGE_EVENT, callback)
  return () => window.removeEventListener(AUTH_CHANGE_EVENT, callback)
}
