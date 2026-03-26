import { render, screen, waitFor } from '@testing-library/react'
import { vi } from 'vitest'
import App from './App.jsx'

const clientState = {
  token: '',
  user: null,
  meResult: null,
  meError: null,
}

const clearSessionMock = vi.fn()
const setStoredUserMock = vi.fn()
const logoutMock = vi.fn()
let pendingNotice = null

vi.mock('./api/client.js', () => ({
  getToken: () => clientState.token,
  getStoredUser: () => clientState.user,
  setStoredUser: (...args) => setStoredUserMock(...args),
  syncStoredUserFromMe: (me) => {
    if (!me?.user) return false
    setStoredUserMock({
      id: me.user.id,
      usuario: me.user.usuario,
      activo: me.user.activo,
      tenant_id: me.user.tenant_id,
      codigo_cliente: me.user.codigo_cliente ?? null,
      tenant: me.tenant ?? null,
      roles: Array.isArray(me.user.roles) ? me.user.roles : [],
      abilities: Array.isArray(me.user.abilities) ? me.user.abilities : [],
      accessible_tenants: Array.isArray(me.accessible_tenants) ? me.accessible_tenants : [],
    })
    return true
  },
  clearSession: (...args) => {
    const opts = args[0] || {}
    if (opts.showMessage) {
      pendingNotice = 'Tu sesion expiro o ya no es valida. Inicia sesion nuevamente.'
    }
    return clearSessionMock(...args)
  },
  subscribeAuthChange: () => () => {},
  logout: (...args) => logoutMock(...args),
  getMe: async () => {
    if (clientState.meError) throw clientState.meError
    return clientState.meResult
  },
}))

vi.mock('./components/layout/DashboardLayout.jsx', () => ({
  default: ({ children }) => <div data-testid="dashboard-layout">{children}</div>,
}))
vi.mock('./pages/LoginPage.jsx', () => ({
  default: () => (
    <div>
      LOGIN_PAGE
      {pendingNotice && <div role="alert">{pendingNotice}</div>}
    </div>
  ),
}))
vi.mock('./pages/DashboardHome.jsx', () => ({
  default: () => <div>DASHBOARD_PAGE</div>,
}))
vi.mock('./pages/UsersPage.jsx', () => ({
  default: () => <div>USERS_PAGE</div>,
}))
vi.mock('./components/HealthCheck.jsx', () => ({
  default: () => <div>HEALTH_PAGE</div>,
}))

describe('App auth/sesion/guards', () => {
  beforeEach(() => {
    clientState.token = ''
    clientState.user = null
    clientState.meResult = null
    clientState.meError = null
    clearSessionMock.mockReset()
    setStoredUserMock.mockReset()
    logoutMock.mockReset()
    pendingNotice = null
    window.location.hash = '#/dashboard'
  })

  it('bootstrap con sesion valida restaura usuario', async () => {
    clientState.token = 'token-ok'
    clientState.meResult = {
      user: { id: 1, usuario: 'admin_demo', activo: true, tenant_id: 1 },
      tenant: { id: 1, codigo: 'DEFAULT' },
    }
    window.location.hash = '#/login'
    render(<App />)

    await waitFor(() => expect(setStoredUserMock).toHaveBeenCalled())
    await waitFor(() => expect(window.location.hash).toBe('#/dashboard'))
    expect(screen.getByTestId('dashboard-layout')).toBeInTheDocument()
  })

  it('si auth/me falla con sesion, limpia sesion y manda a login', async () => {
    clientState.token = 'token-bad'
    clientState.meError = new Error('unauthorized')
    window.location.hash = '#/users'

    render(<App />)

    await waitFor(() => expect(clearSessionMock).toHaveBeenCalled())
    await waitFor(() => expect(window.location.hash).toBe('#/login'))
    expect(screen.getByText('LOGIN_PAGE')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent(/Tu sesion expiro o ya no es valida/i)
  })

  it('ruta protegida redirige a /login sin sesion', async () => {
    clientState.token = ''
    window.location.hash = '#/users'
    render(<App />)

    await waitFor(() => expect(window.location.hash).toBe('#/login'))
    expect(screen.getByText('LOGIN_PAGE')).toBeInTheDocument()
  })

  it('usuario autenticado en /login redirige a /dashboard', async () => {
    clientState.token = 't'
    clientState.meResult = {
      user: { id: 1, usuario: 'admin_demo', activo: true, tenant_id: 1 },
      tenant: { id: 1, codigo: 'DEFAULT' },
    }
    window.location.hash = '#/login'
    render(<App />)

    await waitFor(() => expect(window.location.hash).toBe('#/dashboard'))
    expect(screen.getByTestId('dashboard-layout')).toBeInTheDocument()
  })

  it('usuario autenticado sin permiso no puede quedarse en /users', async () => {
    clientState.token = 't'
    clientState.user = { id: 2, usuario: 'user_demo', roles: [{ slug: 'user' }] }
    clientState.meResult = {
      user: { id: 2, usuario: 'user_demo', activo: true, tenant_id: 1 },
      tenant: { id: 1, codigo: 'DEFAULT' },
    }
    window.location.hash = '#/users'
    render(<App />)

    await waitFor(() => expect(window.location.hash).toBe('#/dashboard'))
  })
})

