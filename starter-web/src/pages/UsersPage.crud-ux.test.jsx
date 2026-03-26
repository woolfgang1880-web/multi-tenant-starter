import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { vi } from 'vitest'
import UsersPage from './UsersPage.jsx'

const getUsersMock = vi.fn()
const createUserMock = vi.fn()
const updateUserMock = vi.fn()
const deactivateUserMock = vi.fn()
const showToastMock = vi.fn()

vi.mock('../api/client.js', () => ({
  getUsers: (...args) => getUsersMock(...args),
  createUser: (...args) => createUserMock(...args),
  updateUser: (...args) => updateUserMock(...args),
  deactivateUser: (...args) => deactivateUserMock(...args),
}))

vi.mock('../context/ToastContext.jsx', () => ({
  useToast: () => ({ showToast: showToastMock }),
}))

function seedUsersOnce() {
  getUsersMock.mockResolvedValueOnce({
    items: [{ id: 1, usuario: 'admin_demo', codigo_cliente: 'A', activo: true, fecha_alta: '2026-03-24' }],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
  })
}

describe('UsersPage CRUD UX', () => {
  beforeEach(() => {
    getUsersMock.mockReset()
    createUserMock.mockReset()
    updateUserMock.mockReset()
    deactivateUserMock.mockReset()
    showToastMock.mockReset()
  })

  it('muestra confirmacion para desactivar usuario', async () => {
    seedUsersOnce()
    render(<UsersPage />)
    await waitFor(() => expect(screen.getByText('admin_demo')).toBeInTheDocument())

    fireEvent.click(screen.getByRole('button', { name: 'Desactivar' }))
    expect(screen.getByRole('alertdialog')).toBeInTheDocument()
    expect(screen.getByText(/¿Seguro que deseas desactivar/i)).toBeInTheDocument()
  })

  it('submit create exitoso muestra feedback', async () => {
    seedUsersOnce()
    createUserMock.mockResolvedValueOnce({ id: 2 })
    getUsersMock.mockResolvedValueOnce({
      items: [{ id: 2, usuario: 'nuevo', codigo_cliente: null, activo: true, fecha_alta: '2026-03-24' }],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })

    render(<UsersPage />)
    await waitFor(() => expect(screen.getByText('admin_demo')).toBeInTheDocument())

    fireEvent.change(screen.getByPlaceholderText('usuario'), { target: { value: 'nuevo' } })
    fireEvent.change(screen.getByPlaceholderText('Minimo 8 caracteres'), { target: { value: 'Password123' } })
    fireEvent.click(screen.getByRole('button', { name: 'Crear usuario' }))

    await waitFor(() => expect(createUserMock).toHaveBeenCalled())
    expect(showToastMock).toHaveBeenCalledWith('Usuario creado correctamente.', 'success')
  })

  it('error de operacion muestra feedback', async () => {
    seedUsersOnce()
    createUserMock.mockRejectedValueOnce({ status: 403, message: 'forbidden' })
    render(<UsersPage />)
    await waitFor(() => expect(screen.getByText('admin_demo')).toBeInTheDocument())

    fireEvent.change(screen.getByPlaceholderText('usuario'), { target: { value: 'nuevo' } })
    fireEvent.change(screen.getByPlaceholderText('Minimo 8 caracteres'), { target: { value: 'Password123' } })
    fireEvent.click(screen.getByRole('button', { name: 'Crear usuario' }))

    await waitFor(() => expect(showToastMock).toHaveBeenCalledWith('No tienes permisos para esta accion.', 'error'))
  })

  it('previene doble accion al confirmar desactivacion', async () => {
    seedUsersOnce()
    let resolveDeactivate
    deactivateUserMock.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveDeactivate = resolve
        }),
    )

    render(<UsersPage />)
    await waitFor(() => expect(screen.getByText('admin_demo')).toBeInTheDocument())

    fireEvent.click(screen.getByRole('button', { name: 'Desactivar' }))
    const confirmBtn = screen.getAllByRole('button', { name: 'Desactivar' })[1]
    fireEvent.click(confirmBtn)
    fireEvent.click(confirmBtn)

    expect(deactivateUserMock).toHaveBeenCalledTimes(1)
    await act(async () => {
      resolveDeactivate({ ok: true })
    })
  })
})

