import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { vi } from 'vitest'
import PlatformAdminPage from './PlatformAdminPage.jsx'

const createTenantMock = vi.fn()
const createAdminMock = vi.fn()
const showToastMock = vi.fn()

vi.mock('../api/client.js', () => ({
  createPlatformTenant: (...args) => createTenantMock(...args),
  createPlatformTenantInitialAdmin: (...args) => createAdminMock(...args),
}))

vi.mock('../context/ToastContext.jsx', () => ({
  useToast: () => ({ showToast: showToastMock, dismissToast: () => {} }),
}))

describe('PlatformAdminPage (UI mínima)', () => {
  beforeEach(() => {
    createTenantMock.mockReset()
    createAdminMock.mockReset()
    showToastMock.mockReset()
  })

  it('crea tenant y luego admin inicial con feedback', async () => {
    createTenantMock.mockResolvedValueOnce({
      data: { codigo: 'TENANTX' },
    })
    createAdminMock.mockResolvedValueOnce({
      data: { usuario: 'admin_tenantx' },
    })

    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    fireEvent.change(screen.getByLabelText('Nombre'), { target: { value: 'Tenant X' } })
    fireEvent.change(screen.getByLabelText('Código'), { target: { value: 'TENANTX' } })
    fireEvent.change(screen.getByLabelText('Activo'), { target: { value: '1' } })
    fireEvent.click(screen.getByRole('button', { name: /Crear empresa/i }))

    await waitFor(() => expect(createTenantMock).toHaveBeenCalled())
    expect(await screen.findByText(/Empresa creada correctamente: TENANTX/i)).toBeInTheDocument()

    const codigoEmpresaInput = screen.getByLabelText('Código de empresa')
    expect(codigoEmpresaInput).toHaveValue('TENANTX')

    fireEvent.change(screen.getByLabelText('Admin usuario'), { target: { value: 'admin_tenantx' } })
    fireEvent.change(screen.getByLabelText('Admin contraseña'), { target: { value: 'Admin1234!' } })
    fireEvent.change(screen.getByLabelText('Confirmar contraseña'), { target: { value: 'Admin1234!' } })
    fireEvent.change(screen.getByLabelText('Código cliente (opcional)'), { target: { value: 'CLI-X' } })

    fireEvent.click(screen.getByRole('button', { name: /Crear admin inicial/i }))

    await waitFor(() => expect(createAdminMock).toHaveBeenCalled())
    expect(await screen.findByText(/Admin inicial creado correctamente: admin_tenantx/i)).toBeInTheDocument()
  })
})

