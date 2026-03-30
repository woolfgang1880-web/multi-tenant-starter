import { useId, useRef, useState } from 'react'
import { switchSessionTenant } from '../../api/client.js'
import { useToast } from '../../context/ToastContext.jsx'
import NotificationBell from '../notifications/NotificationBell.jsx'
import { getDisplayName, getUserInitials } from '../../utils/userDisplay.js'
import Button from '../ui/Button.jsx'

export default function DashboardHeader({ user, onLogout, theme, onThemeChange, sidebarOpen, onToggleSidebar }) {
  const gradId = useId().replace(/:/g, '')
  const initials = getUserInitials(user)
  const name = getDisplayName(user)
  const tenantLabel = user?.tenant?.codigo || user?.tenant_codigo || null
  const [profileOpen, setProfileOpen] = useState(false)
  const profileRef = useRef(null)
  const { showToast } = useToast()
  const tenants = Array.isArray(user?.accessible_tenants) ? user.accessible_tenants : []
  const canSwitchTenant = tenants.length > 1
  const [tenantSwitching, setTenantSwitching] = useState(false)
  const [tenantSelectOverride, setTenantSelectOverride] = useState(null)
  const tenantSelectValue = tenantSelectOverride ?? user?.tenant?.codigo ?? ''

  function handleToggleProfile() {
    setProfileOpen((prev) => !prev)
  }

  function handleBlurProfile(event) {
    const nextTarget = event.relatedTarget
    if (!profileRef.current || (nextTarget && profileRef.current.contains(nextTarget))) {
      return
    }

    setProfileOpen(false)
  }

  async function handleTenantChange(event) {
    const codigo = event.target.value
    const current = user?.tenant?.codigo ?? ''
    if (!codigo || codigo === current) return
    setTenantSelectOverride(codigo)
    setTenantSwitching(true)
    try {
      await switchSessionTenant({ tenant_codigo: codigo })
      showToast('Empresa activa actualizada.', 'success')
      setTenantSelectOverride(null)
    } catch (err) {
      setTenantSelectOverride(null)
      const msg =
        err && typeof err === 'object' && typeof err.message === 'string'
          ? err.message
          : 'No se pudo cambiar de empresa.'
      showToast(msg, 'error')
    } finally {
      setTenantSwitching(false)
    }
  }

  return (
    <header className="dash-header">
      <div className="dash-header__menu">
        <button
          type="button"
          className="dash-header__menu-btn"
          aria-label="Abrir menu"
          aria-expanded={sidebarOpen}
          onClick={onToggleSidebar}
        >
          ☰
        </button>
      </div>
      <div className="dash-header__brand">
        <div className="dash-header__logo-wrap" aria-hidden="true">
          <svg className="dash-header__logo-svg" viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="8" fill={`url(#dashLogoGrad-${gradId})`} />
            {/* Logotipo 'O' (anillo) en el fondo degradado */}
            <circle cx="16" cy="16" r="9.75" stroke="white" strokeWidth="2.4" fill="none" />
            <circle cx="16" cy="16" r="6.8" fill="none" />
            <defs>
              <linearGradient id={`dashLogoGrad-${gradId}`} x1="0" y1="0" x2="32" y2="32">
                <stop stopColor="#6366f1" />
                <stop offset="1" stopColor="#8b5cf6" />
              </linearGradient>
            </defs>
          </svg>
        </div>
        <div>
          <span className="dash-header__name">Ohtli</span>
          <span className="dash-header__tag">Panel operativo</span>
        </div>
      </div>

      <div className="dash-header__tools">
        {canSwitchTenant && (
          <label className="dash-header__tenant-switch dash-theme-picker">
            <span className="dash-theme-picker__label">Empresa</span>
            <select
              className="dash-input dash-theme-picker__select"
              value={tenantSelectValue}
              disabled={tenantSwitching}
              onChange={handleTenantChange}
              aria-label="Cambiar empresa activa"
            >
              {tenants.map((t) => (
                <option key={t.id} value={t.codigo}>
                  {t.codigo}
                  {t.nombre ? ` — ${t.nombre}` : ''}
                </option>
              ))}
            </select>
          </label>
        )}
        <label className="dash-theme-picker">
          <span className="dash-theme-picker__label">Tema</span>
          <select className="dash-input dash-theme-picker__select" value={theme} onChange={(e) => onThemeChange(e.target.value)}>
            <option value="soft">Suave</option>
            <option value="light">Claro</option>
            <option value="dark">Oscuro</option>
          </select>
        </label>
        <NotificationBell />

        <div className="dash-header__avatar dash-header__avatar--mobile" aria-hidden="true" title={name}>
          {initials}
        </div>

        <div className="dash-header__profile-menu" ref={profileRef} onBlur={handleBlurProfile}>
          <button
            type="button"
            className="dash-header__profile"
            data-testid="header-profile-toggle"
            aria-haspopup="menu"
            aria-expanded={profileOpen}
            onClick={handleToggleProfile}
          >
            <div className="dash-header__avatar" aria-hidden="true" title={name}>
              {initials}
            </div>
            <div className="dash-header__profile-text">
              <span className="dash-header__profile-name">{name}</span>
              {tenantLabel && <span className="dash-header__profile-email">Empresa: {tenantLabel}</span>}
            </div>
          </button>

          {profileOpen && (
            <div className="dash-header__profile-popover" role="menu" aria-label="Menu de perfil">
              <Button
                type="button"
                variant="outline"
                className="dash-btn--header dash-header__logout-btn"
                onClick={onLogout}
                data-testid="header-logout"
              >
                Cerrar sesión
              </Button>
            </div>
          )}
        </div>
      </div>
    </header>
  )
}
