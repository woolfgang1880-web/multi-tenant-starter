import { IconActivity, IconDashboard, IconUsers } from './icons.jsx'
import { canViewUsers } from '../../utils/authz.js'

const NAV = [
  { id: 'dashboard', label: 'Dashboard', Icon: IconDashboard },
  { id: 'users', label: 'Users', Icon: IconUsers, visible: (user) => canViewUsers(user) },
  { id: 'health', label: 'Estado del servicio', Icon: IconActivity },
  {
    id: 'platform',
    label: 'Plataforma',
    Icon: IconDashboard,
    visible: (user) => !!user?.is_platform_admin,
  },
]

export default function AppSidebar({ user, activeSection, onNavigate, sidebarOpen = false, onCloseSidebar }) {
  const items = NAV.filter((item) => (typeof item.visible === 'function' ? item.visible(user) : true))

  return (
    <aside className={`dash-sidebar ${sidebarOpen ? 'dash-sidebar--open' : ''}`} aria-label="Navegación principal">
      <div className="dash-sidebar__section-label">Menú</div>
      <nav className="dash-nav">
        {items.map(({ id, label, Icon }) => (
          <button
            key={id}
            type="button"
            className={`dash-nav__item ${activeSection === id ? 'dash-nav__item--active' : ''}`}
            onClick={() => {
              onNavigate(id)
              onCloseSidebar?.()
            }}
            aria-current={activeSection === id ? 'page' : undefined}
          >
            <Icon className="dash-nav__svg" aria-hidden="true" />
            <span>{label}</span>
          </button>
        ))}
      </nav>
    </aside>
  )
}
