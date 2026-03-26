import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { vi } from 'vitest'
import App from './App.jsx'

const authListeners = new Set()

const state = {
  token: '',
  user: null,
  notice: null,
  meError: null,
  users: [],
}

function resetState() {
  state.token = ''
  state.user = null
  state.notice = null
  state.meError = null
  state.users = [
    { id: 1, usuario: 'admin_demo', codigo_cliente: 'DEMO-ADMIN', activo: true, fecha_alta: '2026-03-24' },
    { id: 2, usuario: 'user_demo', codigo_cliente: 'DEMO-USER', activo: true, fecha_alta: '2026-03-24' },
  ]
}

function emitAuthChange() {
  for (const cb of authListeners) cb()
}

function sessionNoticeFor(reason) {
  if (reason === 'session_expired' || reason === 'session_invalid') {
    return 'Tu sesion expiro o ya no es valida. Inicia sesion nuevamente.'
  }
  return null
}

vi.mock('./api/client.js', () => ({
  getToken: () => state.token,
  getStoredUser: () => state.user,
  setStoredUser: (user) => {
    state.user = user
      ? {
          ...user,
          roles: Array.isArray(user.roles) ? user.roles : (state.user?.roles ?? []),
          abilities: Array.isArray(user.abilities) ? user.abilities : (state.user?.abilities ?? []),
          permissions: Array.isArray(user.permissions) ? user.permissions : (state.user?.permissions ?? []),
          accessible_tenants: Array.isArray(user.accessible_tenants)
            ? user.accessible_tenants
            : (state.user?.accessible_tenants ?? []),
        }
      : null
  },
  syncStoredUserFromMe: (me) => {
    if (!me?.user) return false
    const base = {
      id: me.user.id,
      usuario: me.user.usuario,
      activo: me.user.activo,
      tenant_id: me.user.tenant_id,
      codigo_cliente: me.user.codigo_cliente ?? null,
      tenant: me.tenant ?? null,
      roles: Array.isArray(me.user.roles) ? me.user.roles : [],
      abilities: Array.isArray(me.user.abilities) ? me.user.abilities : [],
      accessible_tenants: Array.isArray(me.accessible_tenants) ? me.accessible_tenants : [],
    }
    state.user = {
      ...base,
      permissions: state.user?.permissions ?? [],
    }
    return true
  },
  subscribeAuthChange: (cb) => {
    authListeners.add(cb)
    return () => authListeners.delete(cb)
  },
  clearSession: ({ reason = null, showMessage = false } = {}) => {
    state.token = ''
    state.user = null
    if (showMessage) state.notice = sessionNoticeFor(reason)
    emitAuthChange()
  },
  consumeAuthNotice: () => {
    const msg = state.notice
    state.notice = null
    return msg
  },
  login: async ({ tenant_codigo, usuario, password }) => {
    if (tenant_codigo !== 'DEFAULT') throw { status: 401, code: 'INVALID_CREDENTIALS' }
    if (usuario === 'admin_demo' && password === 'Admin123!') {
      state.token = 'token-admin'
      state.user = { id: 1, usuario: 'admin_demo', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'admin' }] }
      emitAuthChange()
      return { access_token: 'token-admin', refresh_token: 'refresh-admin' }
    }
    if (usuario === 'user_demo' && password === 'User123!') {
      state.token = 'token-user'
      state.user = { id: 2, usuario: 'user_demo', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'user' }] }
      emitAuthChange()
      return { access_token: 'token-user', refresh_token: 'refresh-user' }
    }
    throw { status: 401, code: 'INVALID_CREDENTIALS' }
  },
  logout: async () => {
    state.token = ''
    state.user = null
    emitAuthChange()
  },
  getMe: async () => {
    if (state.meError) throw state.meError
    if (!state.token || !state.user) throw { status: 401 }
    return {
      user: {
        id: state.user.id,
        usuario: state.user.usuario,
        activo: true,
        tenant_id: 1,
        roles: state.user.roles ?? [],
      },
      tenant: state.user.tenant ?? { codigo: 'DEFAULT' },
      accessible_tenants: state.user.accessible_tenants ?? [],
    }
  },
  getHealth: async () => ({ status: 'ok', uptime: 321.4 }),
  getUsers: async ({ page = 1, perPage = 15 } = {}) => {
    const start = (page - 1) * perPage
    const items = state.users.slice(start, start + perPage)
    return {
      items,
      meta: {
        current_page: page,
        last_page: Math.max(1, Math.ceil(state.users.length / perPage)),
        per_page: perPage,
        total: state.users.length,
      },
      total: state.users.length,
    }
  },
  createUser: async (payload) => {
    const id = Math.max(0, ...state.users.map((u) => u.id)) + 1
    state.users.push({
      id,
      usuario: payload.usuario,
      codigo_cliente: payload.codigo_cliente ?? null,
      activo: true,
      fecha_alta: '2026-03-24',
    })
    return { id }
  },
  updateUser: async (id, payload) => {
    state.users = state.users.map((u) => (u.id === id ? { ...u, ...payload } : u))
    return { ok: true }
  },
  deactivateUser: async (id) => {
    state.users = state.users.map((u) => (u.id === id ? { ...u, activo: false } : u))
    return { ok: true }
  },
}))

vi.mock('./components/notifications/NotificationBell.jsx', () => ({
  default: () => <div data-testid="notification-bell" />,
}))

describe('Integracion de vistas clave', () => {
  beforeEach(() => {
    authListeners.clear()
    resetState()
    window.location.hash = '#/dashboard'
    sessionStorage.clear()
    localStorage.clear()
  })

  it('login -> sesion -> redirecciona a dashboard', async () => {
    window.location.hash = '#/login'
    render(<App />)

    fireEvent.change(screen.getByLabelText(/Código de empresa/i), { target: { value: 'DEFAULT' } })
    fireEvent.change(screen.getByLabelText(/^Usuario$/i), { target: { value: 'admin_demo' } })
    fireEvent.change(screen.getByLabelText(/Contrasena/i), { target: { value: 'Admin123!' } })
    fireEvent.click(screen.getByRole('button', { name: 'Entrar' }))

    await waitFor(() => expect(window.location.hash).toBe('#/dashboard'))
    expect(screen.getByText(/Hola,/i)).toBeInTheDocument()
    expect(screen.getByText(/Bienvenido, admin_demo\./i)).toBeInTheDocument()
  })

  it('usuario con permiso puede acceder a Users y ver acciones', async () => {
    state.token = 'token-admin'
    state.user = { id: 1, usuario: 'admin_demo', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'admin' }] }
    window.location.hash = '#/users'

    render(<App />)

    await waitFor(() => expect(screen.getByRole('heading', { name: 'Users' })).toBeInTheDocument())
    expect(screen.getByRole('button', { name: 'Crear usuario' })).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Editar' }).length).toBeGreaterThan(0)
    expect(screen.getAllByRole('button', { name: 'Desactivar' }).length).toBeGreaterThan(0)
  })

  it('usuario sin permiso no puede quedarse en Users', async () => {
    state.token = 'token-user'
    state.user = { id: 2, usuario: 'user_demo', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'user' }] }
    window.location.hash = '#/users'

    render(<App />)

    await waitFor(() => expect(window.location.hash).toBe('#/dashboard'))
    expect(screen.queryByRole('button', { name: 'Users' })).not.toBeInTheDocument()
  })

  it('flujo integrado create/edit/deactivate en UsersPage', async () => {
    state.token = 'token-admin'
    state.user = { id: 1, usuario: 'admin_demo', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'admin' }] }
    window.location.hash = '#/users'

    render(<App />)
    await waitFor(() => expect(screen.getByText('admin_demo')).toBeInTheDocument())

    fireEvent.change(screen.getByPlaceholderText('usuario'), { target: { value: 'ops_user' } })
    fireEvent.change(screen.getByPlaceholderText('Minimo 8 caracteres'), { target: { value: 'Password123' } })
    fireEvent.click(screen.getByRole('button', { name: 'Crear usuario' }))
    await waitFor(() => expect(screen.getByText('ops_user')).toBeInTheDocument())
    expect(screen.getAllByText('Usuario creado correctamente.').length).toBeGreaterThan(0)

    const rowOps = screen.getByText('ops_user').closest('tr')
    fireEvent.click(within(rowOps).getByRole('button', { name: 'Editar' }))
    const editCard = screen.getByText(/Editar · ops_user/i).closest('section')
    const editUsuarioInput = within(editCard).getByDisplayValue('ops_user')
    fireEvent.change(editUsuarioInput, { target: { value: 'ops_user_edit' } })
    fireEvent.click(within(editCard).getByRole('button', { name: 'Guardar cambios' }))
    await waitFor(() => expect(screen.getByText('ops_user_edit')).toBeInTheDocument())
    expect(screen.getAllByText('Usuario actualizado.').length).toBeGreaterThan(0)

    const editedRow = screen.getByText('ops_user_edit').closest('tr')
    fireEvent.click(within(editedRow).getByRole('button', { name: 'Desactivar' }))
    const dialog = screen.getByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Desactivar' }))
    await waitFor(() => expect(screen.getByText('Usuario desactivado.')).toBeInTheDocument())
    expect(within(screen.getByText('ops_user_edit').closest('tr')).getByText('No')).toBeInTheDocument()
  })

  it('sesion invalida redirige a login con notice', async () => {
    state.token = 'token-bad'
    state.user = { id: 1, usuario: 'admin_demo', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'admin' }] }
    state.meError = new Error('unauthorized')
    window.location.hash = '#/users'

    render(<App />)

    await waitFor(() => expect(window.location.hash).toBe('#/login'))
    expect(await screen.findByText(/Tu sesion expiro o ya no es valida/i)).toBeInTheDocument()
  })
})

