import { useEffect, useState } from 'react'
import AppSidebar from './AppSidebar.jsx'
import DashboardHeader from './DashboardHeader.jsx'
import TenantTrialStatusBanner from '../TenantTrialStatusBanner.jsx'

const DASH_THEME_KEY = 'starter-web_theme'
const THEMES = new Set(['soft', 'light', 'dark'])

function getInitialTheme() {
  try {
    const saved = localStorage.getItem(DASH_THEME_KEY)
    if (saved && THEMES.has(saved)) return saved
  } catch {}
  return 'dark'
}

export default function DashboardLayout({ user, activeSection, onNavigate, onLogout, children }) {
  const [theme, setTheme] = useState(getInitialTheme)
  const [sidebarOpen, setSidebarOpen] = useState(false)

  useEffect(() => {
    document.documentElement.setAttribute('data-dash-theme', theme)
    try {
      localStorage.setItem(DASH_THEME_KEY, theme)
    } catch {}
  }, [theme])

  useEffect(() => {
    function onResize() {
      if (window.innerWidth >= 960) setSidebarOpen(false)
    }
    window.addEventListener('resize', onResize)
    return () => window.removeEventListener('resize', onResize)
  }, [])

  function handleNavigate(section) {
    onNavigate(section)
    if (window.innerWidth < 960) setSidebarOpen(false)
  }

  return (
    <div className="dash-shell">
      <DashboardHeader
        user={user}
        onLogout={onLogout}
        theme={theme}
        onThemeChange={setTheme}
        sidebarOpen={sidebarOpen}
        onToggleSidebar={() => setSidebarOpen((v) => !v)}
      />

      <div className="dash-body">
        <AppSidebar
          user={user}
          activeSection={activeSection}
          onNavigate={handleNavigate}
          sidebarOpen={sidebarOpen}
          onCloseSidebar={() => setSidebarOpen(false)}
        />
        {sidebarOpen && <button type="button" className="dash-sidebar-backdrop" aria-label="Cerrar menu" onClick={() => setSidebarOpen(false)} />}
        <main className="dash-main">
          <TenantTrialStatusBanner tenant={user?.tenant} />
          {children}
        </main>
      </div>
      <footer className="dash-footer" role="contentinfo">
        <span className="dash-footer__brand">FDS FABRICA DEL SOFTWARE</span>
        <span className="dash-footer__meta">Starter Web · User API</span>
      </footer>
    </div>
  )
}
