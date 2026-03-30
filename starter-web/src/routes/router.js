const ROUTE_CHANGE_EVENT = 'starter-web:route-change'

export const ROUTES = {
  LOGIN: '/login',
  SUBSCRIPTION_EXPIRED: '/subscription-expired',
  DASHBOARD: '/dashboard',
  USERS: '/users',
  HEALTH: '/health',
  PLATFORM: '/platform',
}

const PROTECTED = new Set([ROUTES.DASHBOARD, ROUTES.USERS, ROUTES.HEALTH, ROUTES.PLATFORM])

function normalize(path) {
  const p = typeof path === 'string' && path.trim() ? path.trim() : ROUTES.DASHBOARD
  return p.startsWith('/') ? p : `/${p}`
}

export function currentPath() {
  const raw = window.location.hash.replace(/^#/, '')
  return normalize(raw || ROUTES.DASHBOARD)
}

export function isProtectedRoute(pathname) {
  return PROTECTED.has(normalize(pathname))
}

export function routeToSection(pathname) {
  const p = normalize(pathname)
  if (p === ROUTES.USERS) return 'users'
  if (p === ROUTES.HEALTH) return 'health'
  if (p === ROUTES.PLATFORM) return 'platform'
  return 'dashboard'
}

export function sectionToRoute(section) {
  if (section === 'users') return ROUTES.USERS
  if (section === 'health') return ROUTES.HEALTH
  if (section === 'platform') return ROUTES.PLATFORM
  return ROUTES.DASHBOARD
}

export function navigate(pathname, { replace = false } = {}) {
  const target = `#${normalize(pathname)}`
  if (replace) window.location.replace(target)
  else window.location.hash = target
  window.dispatchEvent(new Event(ROUTE_CHANGE_EVENT))
}

export function subscribeRouteChange(callback) {
  const handler = () => callback(currentPath())
  window.addEventListener('hashchange', handler)
  window.addEventListener(ROUTE_CHANGE_EVENT, handler)
  return () => {
    window.removeEventListener('hashchange', handler)
    window.removeEventListener(ROUTE_CHANGE_EVENT, handler)
  }
}

