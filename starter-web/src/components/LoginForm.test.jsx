import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { vi } from 'vitest'
import LoginForm from './LoginForm.jsx'

const loginMock = vi.fn()
const selectLoginTenantMock = vi.fn()

vi.mock('../api/client.js', () => ({
  login: (...args) => loginMock(...args),
  selectLoginTenant: (...args) => selectLoginTenantMock(...args),
}))

describe('LoginForm', () => {
  beforeEach(() => {
    loginMock.mockReset()
    selectLoginTenantMock.mockReset()
  })

  it('renderiza el formulario con tenant, usuario y password', () => {
    render(<LoginForm />)

    expect(screen.getByLabelText(/Código de empresa \(opcional\)/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Usuario$/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/Contrasena/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Entrar/i })).toBeInTheDocument()
    expect(screen.getByText(/Acceso seguro al sistema/i)).toBeInTheDocument()
    expect(screen.getByText(/app:setup-demo/i)).toBeInTheDocument()
  })

  it('valida usuario y contrasena antes de enviar', async () => {
    render(<LoginForm />)

    fireEvent.change(screen.getByLabelText(/Código de empresa \(opcional\)/i), { target: { value: '   ' } })
    fireEvent.change(screen.getByLabelText(/^Usuario$/i), { target: { value: '  ' } })
    fireEvent.change(screen.getByLabelText(/Contrasena/i), { target: { value: '' } })
    fireEvent.submit(screen.getByRole('button', { name: /Entrar/i }).closest('form'))

    expect(await screen.findByText(/Completa usuario y contrasena/i)).toBeInTheDocument()
    expect(loginMock).not.toHaveBeenCalled()
  })

  it('hace submit exitoso con credenciales validas', async () => {
    loginMock.mockResolvedValueOnce({ access_token: 'a', refresh_token: 'r' })
    render(<LoginForm />)

    fireEvent.change(screen.getByLabelText(/Código de empresa \(opcional\)/i), { target: { value: 'DEFAULT' } })
    fireEvent.change(screen.getByLabelText(/^Usuario$/i), { target: { value: 'admin_demo' } })
    fireEvent.change(screen.getByLabelText(/Contrasena/i), { target: { value: 'Admin123!' } })
    fireEvent.submit(screen.getByRole('button', { name: /Entrar/i }).closest('form'))

    await waitFor(() => {
      expect(loginMock).toHaveBeenCalledWith({
        tenant_codigo: 'DEFAULT',
        usuario: 'admin_demo',
        password: 'Admin123!',
      })
    })
  })

  it('muestra error cuando login es invalido', async () => {
    loginMock.mockRejectedValueOnce({ status: 401 })
    render(<LoginForm />)

    fireEvent.change(screen.getByLabelText(/Código de empresa \(opcional\)/i), { target: { value: 'DEFAULT' } })
    fireEvent.change(screen.getByLabelText(/^Usuario$/i), { target: { value: 'bad' } })
    fireEvent.change(screen.getByLabelText(/Contrasena/i), { target: { value: 'bad' } })
    fireEvent.submit(screen.getByRole('button', { name: /Entrar/i }).closest('form'))

    expect(await screen.findByText(/Empresa, usuario o contraseña incorrectos/i)).toBeInTheDocument()
  })

  it('login global sin tenant llama login solo con usuario y password', async () => {
    loginMock.mockResolvedValueOnce({
      needsTenantSelection: true,
      selection_token: 'sel',
      tenants: [
        { id: 1, codigo: 'DEFAULT', nombre: 'A', slug: 'a' },
        { id: 2, codigo: 'PRUEBA1', nombre: 'B', slug: 'b' },
      ],
    })
    render(<LoginForm />)

    fireEvent.change(screen.getByLabelText(/Código de empresa \(opcional\)/i), { target: { value: '' } })
    fireEvent.change(screen.getByLabelText(/^Usuario$/i), { target: { value: 'multi_demo' } })
    fireEvent.change(screen.getByLabelText(/Contrasena/i), { target: { value: 'MultiDemo123!' } })
    fireEvent.submit(screen.getByRole('button', { name: /Entrar/i }).closest('form'))

    await waitFor(() => {
      expect(loginMock).toHaveBeenCalledWith({
        usuario: 'multi_demo',
        password: 'MultiDemo123!',
      })
    })
    expect(screen.getByText(/Elegir empresa/i)).toBeInTheDocument()
  })

  it('completa seleccion de empresa con selectLoginTenant', async () => {
    loginMock.mockResolvedValueOnce({
      needsTenantSelection: true,
      selection_token: 'tok-1',
      tenants: [
        { id: 1, codigo: 'DEFAULT', nombre: 'Uno', slug: 'uno' },
        { id: 2, codigo: 'PRUEBA1', nombre: 'Dos', slug: 'dos' },
      ],
    })
    selectLoginTenantMock.mockResolvedValueOnce({ access_token: 'a', refresh_token: 'r' })
    render(<LoginForm />)

    fireEvent.change(screen.getByLabelText(/Código de empresa \(opcional\)/i), { target: { value: '' } })
    fireEvent.change(screen.getByLabelText(/^Usuario$/i), { target: { value: 'u' } })
    fireEvent.change(screen.getByLabelText(/Contrasena/i), { target: { value: 'p' } })
    fireEvent.submit(screen.getByRole('button', { name: /Entrar/i }).closest('form'))

    await waitFor(() => screen.getByLabelText(/^Empresa$/i))

    fireEvent.change(screen.getByLabelText(/^Empresa$/i), { target: { value: 'PRUEBA1' } })
    fireEvent.submit(screen.getByRole('button', { name: /Continuar en esta empresa/i }))

    await waitFor(() => {
      expect(selectLoginTenantMock).toHaveBeenCalledWith({
        selection_token: 'tok-1',
        tenant_codigo: 'PRUEBA1',
      })
    })
  })
})

