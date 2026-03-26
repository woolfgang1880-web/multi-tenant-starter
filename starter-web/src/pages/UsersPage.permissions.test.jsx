import { render, screen, waitFor } from '@testing-library/react'
import { vi } from 'vitest'
import UsersPage from './UsersPage.jsx'

const getUsersMock = vi.fn()
const showToastMock = vi.fn()

vi.mock('../api/client.js', () => ({
  getUsers: (...args) => getUsersMock(...args),
  createUser: vi.fn(),
  updateUser: vi.fn(),
  deactivateUser: vi.fn(),
}))

vi.mock('../context/ToastContext.jsx', () => ({
  useToast: () => ({ showToast: showToastMock }),
}))

describe('UsersPage permisos UI', () => {
  beforeEach(() => {
    getUsersMock.mockReset()
    showToastMock.mockReset()
  })

  it('usuario sin permiso no ve create/edit/deactivate', async () => {
    getUsersMock.mockResolvedValueOnce({
      items: [{ id: 1, usuario: 'admin_demo', codigo_cliente: 'A', activo: true, fecha_alta: '2026-03-24' }],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })

    render(
      <UsersPage
        user={{ usuario: 'user_demo', roles: [{ slug: 'user' }] }}
      />,
    )

    expect(screen.getByText(/Acceso restringido en UI/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Crear usuario' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Editar' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Desactivar' })).not.toBeInTheDocument()
  })

  it('usuario con manage-users ve acciones CRUD', async () => {
    getUsersMock.mockResolvedValueOnce({
      items: [{ id: 1, usuario: 'admin_demo', codigo_cliente: 'A', activo: true, fecha_alta: '2026-03-24' }],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })

    render(
      <UsersPage
        user={{ usuario: 'ops', abilities: ['manage-users'] }}
      />,
    )

    await waitFor(() => expect(screen.getByText('admin_demo')).toBeInTheDocument())
    expect(screen.getByRole('button', { name: 'Crear usuario' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Editar' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Desactivar' })).toBeInTheDocument()
  })
})

