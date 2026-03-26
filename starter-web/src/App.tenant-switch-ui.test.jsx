import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { vi } from 'vitest'
import App from './App.jsx'

const authListeners = new Set()

const ACCESSIBLE_TENANTS = [
  { id: 1, codigo: 'DEFAULT', nombre: 'Empresa principal' },
  { id: 2, codigo: 'PRUEBA1', nombre: 'PRUEBA1' },
]

function makeUserForTenant(tenantCodigo) {
  const isAdminTenant = tenantCodigo === 'DEFAULT'
  return {
    id: 1,
    usuario: 'multi_demo',
    activo: true,
    tenant_id: isAdminTenant ? 1 : 2,
    tenant: { codigo: tenantCodigo },
    roles: isAdminTenant ? [{ slug: 'admin' }] : [{ slug: 'user' }],
    abilities: [],
    permissions: [],
    accessible_tenants: ACCESSIBLE_TENANTS,
  }
}

const state = {
  token: 'token-multi',
  user: makeUserForTenant('DEFAULT'),
}

function emitAuthChange() {
  for (const cb of authListeners) cb()
}

vi.mock('./api/client.js', () => ({
  getToken: () => state.token,
  getStoredUser: () => state.user,
  setStoredUser: (user) => {
    state.user = user
  },
  syncStoredUserFromMe: (me) => {
    if (!me?.user) return false
    state.user = {
      id: me.user.id,
      usuario: me.user.usuario,
      activo: me.user.activo,
      tenant_id: me.user.tenant_id,
      tenant: me.tenant ?? null,
      roles: Array.isArray(me.user.roles) ? me.user.roles : [],
      abilities: Array.isArray(me.user.abilities) ? me.user.abilities : [],
      permissions: Array.isArray(me.user.permissions) ? me.user.permissions : [],
      accessible_tenants: Array.isArray(me.accessible_tenants) ? me.accessible_tenants : [],
    }
    return true
  },
  subscribeAuthChange: (cb) => {
    authListeners.add(cb)
    return () => authListeners.delete(cb)
  },
  clearSession: () => {
    state.token = ''
    state.user = null
    emitAuthChange()
  },
  logout: async () => {
    state.token = ''
    state.user = null
    emitAuthChange()
  },
  getMe: async () => {
    const tenantCodigo = state.user?.tenant?.codigo ?? 'DEFAULT'
    const user = makeUserForTenant(tenantCodigo)
    return {
      user: {
        id: user.id,
        usuario: user.usuario,
        activo: true,
        tenant_id: user.tenant_id,
        roles: user.roles,
        abilities: user.abilities,
        permissions: user.permissions,
      },
      tenant: user.tenant,
      accessible_tenants: user.accessible_tenants,
    }
  },
  switchSessionTenant: async ({ tenant_codigo }) => {
    state.user = makeUserForTenant(tenant_codigo)
    emitAuthChange()
    return { ok: true }
  },
  // Se usan porque DashboardHome y UsersPage intentan cargar métricas/listados al montarse.
  getHealth: async () => ({ status: 'ok', uptime: 123.4 }),
  getUsers: async ({ page = 1, perPage = 15 } = {}) => {
    const items = []
    if (page || perPage) {
      return {
        items,
        meta: {
          current_page: page,
          last_page: 1,
          per_page: perPage,
          total: items.length,
        },
        total: items.length,
      }
    }
    return { total: items.length }
  },
  createUser: vi.fn(),
  updateUser: vi.fn(),
  deactivateUser: vi.fn(),
  login: vi.fn(),
  selectLoginTenant: vi.fn(),
}))

vi.mock('./components/notifications/NotificationBell.jsx', () => ({
  default: () => <div data-testid="notification-bell" />,
}))

vi.mock('./context/ToastContext.jsx', () => ({
  ToastProvider: ({ children }) => <>{children}</>,
  useToast: () => ({ showToast: () => {}, dismissToast: () => {} }),
}))

describe('Fase 3 — switch tenant (UI): visibilidad Users', () => {
  beforeEach(() => {
    authListeners.clear()
    state.token = 'token-multi'
    state.user = makeUserForTenant('DEFAULT')
    window.location.hash = '#/users'
  })

  it('muestra Users con rol admin (DEFAULT) y lo oculta al cambiar a rol user (PRUEBA1) sin relogin', async () => {
    render(<App />)

    await waitFor(() => expect(screen.getByRole('heading', { name: 'Users' })).toBeInTheDocument())
    const usersMenuBtn = screen.getByRole('button', { name: 'Users' })
    expect(usersMenuBtn).toBeInTheDocument()

    const tenantSelect = screen.getByLabelText(/Cambiar empresa activa/i)
    expect(tenantSelect).toHaveValue('DEFAULT')

    fireEvent.change(tenantSelect, { target: { value: 'PRUEBA1' } })

    await waitFor(() => expect(window.location.hash).toBe('#/dashboard'))
    await waitFor(() => expect(screen.queryByRole('button', { name: 'Users' })).not.toBeInTheDocument())

    // Sanity: el usuario sigue autenticado y la UI no “vuelve” a login.
    expect(screen.queryByText('LOGIN_PAGE')).not.toBeInTheDocument()

    // El layout de Users ya no debería renderizarse.
    expect(screen.queryByRole('heading', { name: 'Users' })).not.toBeInTheDocument()
  })
})

