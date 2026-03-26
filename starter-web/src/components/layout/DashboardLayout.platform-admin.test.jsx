import { render, screen } from '@testing-library/react'
import { vi } from 'vitest'
import DashboardLayout from './DashboardLayout.jsx'

vi.mock('../notifications/NotificationBell.jsx', () => ({
  default: () => <div data-testid="notification-bell" />,
}))

describe('DashboardLayout — sección Plataforma', () => {
  beforeEach(() => {
    localStorage.clear()
    document.documentElement.removeAttribute('data-dash-theme')
  })

  it('muestra Plataforma solo para usuarios super admin global', () => {
    render(
      <DashboardLayout
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'user' }],
          is_platform_admin: true,
        }}
        activeSection="dashboard"
        onNavigate={() => {}}
        onLogout={() => {}}
      >
        <div>CONTENT</div>
      </DashboardLayout>,
    )

    expect(screen.getByRole('button', { name: 'Plataforma' })).toBeInTheDocument()
  })

  it('oculta Plataforma para usuarios sin capacidad global', () => {
    render(
      <DashboardLayout
        user={{
          usuario: 'tenant_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'admin' }],
          is_platform_admin: false,
        }}
        activeSection="dashboard"
        onNavigate={() => {}}
        onLogout={() => {}}
      >
        <div>CONTENT</div>
      </DashboardLayout>,
    )

    expect(screen.queryByRole('button', { name: 'Plataforma' })).not.toBeInTheDocument()
  })
})

