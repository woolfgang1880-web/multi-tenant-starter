import { fireEvent, render, screen } from '@testing-library/react'
import DashboardLayout from './DashboardLayout.jsx'

vi.mock('../notifications/NotificationBell.jsx', () => ({
  default: () => <div data-testid="notification-bell" />,
}))

describe('DashboardLayout auth UI', () => {
  beforeEach(() => {
    localStorage.clear()
    document.documentElement.removeAttribute('data-dash-theme')
  })

  it('muestra app shell y logout con sesion', () => {
    const onLogout = vi.fn()
    render(
      <DashboardLayout
        user={{ usuario: 'admin_demo', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'admin' }] }}
        activeSection="dashboard"
        onNavigate={() => {}}
        onLogout={onLogout}
      >
        <div>CONTENT</div>
      </DashboardLayout>,
    )

    expect(screen.getByText('Ohtli')).toBeInTheDocument()
    expect(screen.getByRole('contentinfo')).toHaveTextContent(/FDS FABRICA DEL SOFTWARE/i)
    expect(screen.getByRole('contentinfo')).toHaveTextContent(/Starter Web · User API/i)
    expect(screen.getByText('Empresa: DEFAULT')).toBeInTheDocument()
    const profileBtn = screen.getByRole('button', { name: /admin_demo/i })
    fireEvent.click(profileBtn)
    const logoutBtn = screen.getByRole('button', { name: /log out/i })
    expect(logoutBtn).toBeInTheDocument()
    fireEvent.click(logoutBtn)
    expect(onLogout).toHaveBeenCalled()
  })

  it('oculta Users si no tiene rol/permisos de gestion', () => {
    render(
      <DashboardLayout
        user={{ usuario: 'user_demo', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'user' }] }}
        activeSection="dashboard"
        onNavigate={() => {}}
        onLogout={() => {}}
      >
        <div>CONTENT</div>
      </DashboardLayout>,
    )

    expect(screen.queryByRole('button', { name: 'Users' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Dashboard' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Estado del servicio' })).toBeInTheDocument()
  })

  it('cambia tema y persiste preferencia', () => {
    render(
      <DashboardLayout
        user={{ usuario: 'admin_demo', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'admin' }] }}
        activeSection="dashboard"
        onNavigate={() => {}}
        onLogout={() => {}}
      >
        <div>CONTENT</div>
      </DashboardLayout>,
    )

    const themeSelect = screen.getByRole('combobox')
    fireEvent.change(themeSelect, { target: { value: 'light' } })
    expect(document.documentElement.getAttribute('data-dash-theme')).toBe('light')
    expect(localStorage.getItem('starter-web_theme')).toBe('light')
  })

  it('toggle de sidebar movil abre y cierra menu', () => {
    render(
      <DashboardLayout
        user={{ usuario: 'admin_demo', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'admin' }] }}
        activeSection="dashboard"
        onNavigate={() => {}}
        onLogout={() => {}}
      >
        <div>CONTENT</div>
      </DashboardLayout>,
    )

    const menuBtn = screen.getByRole('button', { name: /abrir menu/i })
    expect(menuBtn).toHaveAttribute('aria-expanded', 'false')
    fireEvent.click(menuBtn)
    expect(menuBtn).toHaveAttribute('aria-expanded', 'true')
    fireEvent.click(screen.getByRole('button', { name: /cerrar menu/i }))
    expect(menuBtn).toHaveAttribute('aria-expanded', 'false')
  })
})

