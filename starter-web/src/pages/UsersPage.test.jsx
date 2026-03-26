import { fireEvent, render, screen, waitFor } from '@testing-library/react'
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

describe('UsersPage data states', () => {
  beforeEach(() => {
    getUsersMock.mockReset()
    showToastMock.mockReset()
  })

  it('muestra loading y luego vista con datos', async () => {
    getUsersMock.mockResolvedValueOnce({
      items: [{ id: 1, usuario: 'admin_demo', codigo_cliente: 'X', activo: true, fecha_alta: '2026-03-24' }],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    })
    render(<UsersPage />)

    expect(screen.getByText(/Cargando directorio/i)).toBeInTheDocument()
    await waitFor(() => expect(screen.getByText('admin_demo')).toBeInTheDocument())
    expect(screen.getByRole('button', { name: 'Editar' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Desactivar' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Anterior' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Siguiente' })).toBeInTheDocument()
  })

  it('muestra empty state cuando no hay usuarios', async () => {
    getUsersMock.mockResolvedValueOnce({
      items: [],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
    })
    render(<UsersPage />)

    await waitFor(() => expect(screen.getByText(/Sin usuarios/i)).toBeInTheDocument())
  })

  it('muestra error state si falla el listado', async () => {
    getUsersMock.mockRejectedValueOnce({ status: 403, message: 'forbidden' })
    render(<UsersPage />)

    await waitFor(() => expect(screen.getByText(/No se pudo cargar la lista/i)).toBeInTheDocument())
    expect(screen.getByText(/No tienes permisos para esta accion/i)).toBeInTheDocument()
  })

  it('permite busqueda local en la pagina actual', async () => {
    getUsersMock.mockResolvedValueOnce({
      items: [
        { id: 1, usuario: 'admin_demo', codigo_cliente: 'A', activo: true, fecha_alta: '2026-03-24' },
        { id: 2, usuario: 'user_demo', codigo_cliente: 'B', activo: true, fecha_alta: '2026-03-24' },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 },
    })
    render(<UsersPage />)
    await waitFor(() => expect(screen.getByText('admin_demo')).toBeInTheDocument())

    fireEvent.change(screen.getByPlaceholderText(/Ej. admin_demo/i), { target: { value: 'user_demo' } })
    expect(screen.queryByText('admin_demo')).not.toBeInTheDocument()
    expect(screen.getByText('user_demo')).toBeInTheDocument()
  })

  it('cambia pagina con botones anterior/siguiente', async () => {
    getUsersMock
      .mockResolvedValueOnce({
        items: [{ id: 1, usuario: 'admin_demo', codigo_cliente: 'A', activo: true, fecha_alta: '2026-03-24' }],
        meta: { current_page: 1, last_page: 2, per_page: 15, total: 2 },
      })
      .mockResolvedValueOnce({
        items: [{ id: 2, usuario: 'user_demo', codigo_cliente: 'B', activo: true, fecha_alta: '2026-03-24' }],
        meta: { current_page: 2, last_page: 2, per_page: 15, total: 2 },
      })
    render(<UsersPage />)
    await waitFor(() => expect(screen.getByText('admin_demo')).toBeInTheDocument())

    fireEvent.click(screen.getByRole('button', { name: 'Siguiente' }))
    await waitFor(() => expect(screen.getByText('user_demo')).toBeInTheDocument())
    expect(getUsersMock).toHaveBeenNthCalledWith(1, { page: 1, perPage: 15 })
    expect(getUsersMock).toHaveBeenNthCalledWith(2, { page: 2, perPage: 15 })
  })
})

