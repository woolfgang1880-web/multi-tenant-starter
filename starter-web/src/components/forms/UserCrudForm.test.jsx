import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import UserCrudForm from './UserCrudForm.jsx'

describe('UserCrudForm', () => {
  it('renderiza formulario base', () => {
    render(<UserCrudForm mode="create" onSubmit={vi.fn()} />)
    expect(screen.getByLabelText(/^Usuario$/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/Codigo cliente/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/Contrasena inicial/i)).toBeInTheDocument()
  })

  it('valida campos basicos en cliente', async () => {
    const onSubmit = vi.fn()
    render(<UserCrudForm mode="create" onSubmit={onSubmit} />)

    fireEvent.change(screen.getByLabelText(/^Usuario$/i), { target: { value: 'a' } })
    fireEvent.change(screen.getByLabelText(/Contrasena inicial/i), { target: { value: '123' } })
    fireEvent.submit(screen.getByRole('button', { name: /Crear usuario/i }).closest('form'))

    expect(await screen.findByText(/Usuario debe tener al menos 2 caracteres/i)).toBeInTheDocument()
    expect(await screen.findByText(/Contrasena debe tener al menos 8 caracteres/i)).toBeInTheDocument()
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('hace submit exitoso', async () => {
    const onSubmit = vi.fn().mockResolvedValueOnce({ ok: true })
    render(<UserCrudForm mode="create" onSubmit={onSubmit} />)

    fireEvent.change(screen.getByLabelText(/^Usuario$/i), { target: { value: 'nuevo_usuario' } })
    fireEvent.change(screen.getByLabelText(/Contrasena inicial/i), { target: { value: 'Password123' } })
    fireEvent.submit(screen.getByRole('button', { name: /Crear usuario/i }).closest('form'))

    await waitFor(() => expect(onSubmit).toHaveBeenCalled())
    expect(await screen.findByText(/Usuario creado correctamente/i)).toBeInTheDocument()
  })

  it('muestra error de validacion API por campo', async () => {
    const onSubmit = vi.fn().mockRejectedValueOnce({
      code: 'VALIDATION_ERROR',
      body: { data: { errors: { usuario: ['Usuario invalido'] } } },
      message: 'Validation',
    })
    render(<UserCrudForm mode="create" onSubmit={onSubmit} />)

    fireEvent.change(screen.getByLabelText(/^Usuario$/i), { target: { value: 'usuario_test' } })
    fireEvent.change(screen.getByLabelText(/Contrasena inicial/i), { target: { value: 'Password123' } })
    fireEvent.submit(screen.getByRole('button', { name: /Crear usuario/i }).closest('form'))

    const matches = await screen.findAllByText(/Usuario invalido/i)
    expect(matches.length).toBeGreaterThan(0)
  })

  it('muestra submitting y evita doble submit', async () => {
    let resolvePromise
    const onSubmit = vi.fn().mockImplementation(
      () =>
        new Promise((resolve) => {
          resolvePromise = resolve
        }),
    )
    render(<UserCrudForm mode="create" onSubmit={onSubmit} />)

    fireEvent.change(screen.getByLabelText(/^Usuario$/i), { target: { value: 'usuario_test' } })
    fireEvent.change(screen.getByLabelText(/Contrasena inicial/i), { target: { value: 'Password123' } })
    const form = screen.getByRole('button', { name: /Crear usuario/i }).closest('form')

    fireEvent.submit(form)
    fireEvent.submit(form)

    expect(await screen.findByRole('button', { name: /Creando/i })).toBeDisabled()
    expect(onSubmit).toHaveBeenCalledTimes(1)

    resolvePromise({ ok: true })
    await waitFor(() => expect(screen.getByRole('button', { name: /Crear usuario/i })).toBeInTheDocument())
  })
})

