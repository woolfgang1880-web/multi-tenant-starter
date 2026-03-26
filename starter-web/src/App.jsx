import { useEffect, useRef, useState } from 'react'
import './App.css'
import { ToastProvider } from './context/ToastContext.jsx'
import {
  clearSession,
  getMe,
  getStoredUser,
  getToken,
  logout,
  subscribeAuthChange,
  syncStoredUserFromMe,
} from './api/client.js'
import DashboardLayout from './components/layout/DashboardLayout.jsx'
import HealthCheck from './components/HealthCheck.jsx'
import LoginPage from './pages/LoginPage.jsx'
import DashboardHome from './pages/DashboardHome.jsx'
import UsersPage from './pages/UsersPage.jsx'
import PlatformAdminPage from './pages/PlatformAdminPage.jsx'
import { currentPath, isProtectedRoute, navigate, routeToSection, sectionToRoute, subscribeRouteChange } from './routes/router.js'
import { canViewUsers } from './utils/authz.js'

export default function App() {
  const [authed, setAuthed] = useState(() => !!getToken())
  const [user, setUser] = useState(() => getStoredUser())
  const [loginSuccess, setLoginSuccess] = useState(false)
  const [activeSection, setActiveSection] = useState(() => routeToSection(currentPath()))
  const [bootingAuth, setBootingAuth] = useState(() => !!getToken())

  const isFirstAuthEffect = useRef(true)
  const prevAuthed = useRef(!!getToken())

  useEffect(() => {
    if (!getToken()) {
      setBootingAuth(false)
      return
    }

    let active = true
    ;(async () => {
      try {
        const me = await getMe()
        if (!active) return
        if (syncStoredUserFromMe(me)) {
          setUser(getStoredUser())
          setAuthed(true)
        }
      } catch {
        if (!active) return
        clearSession({ reason: 'session_invalid', showMessage: true })
        setAuthed(false)
        setUser(null)
      } finally {
        if (active) setBootingAuth(false)
      }
    })()

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    return subscribeAuthChange(() => {
      setAuthed(!!getToken())
      setUser(getStoredUser())
    })
  }, [])

  useEffect(() => {
    return subscribeRouteChange((pathname) => {
      setActiveSection(routeToSection(pathname))
    })
  }, [])

  useEffect(() => {
    const path = currentPath()
    if (!authed && isProtectedRoute(path)) {
      navigate('/login')
      return
    }
    if (authed && path === '/login') {
      navigate('/dashboard')
      return
    }
    if (authed && path === '/users' && !canViewUsers(user)) {
      navigate('/dashboard')
    }

    if (authed && path === '/platform' && !user?.is_platform_admin) {
      navigate('/dashboard')
    }
  }, [authed, user])

  useEffect(() => {
    if (isFirstAuthEffect.current) {
      isFirstAuthEffect.current = false
      prevAuthed.current = authed
      return
    }
    if (!prevAuthed.current && authed) {
      setLoginSuccess(true)
      setActiveSection('dashboard')
      const t = setTimeout(() => setLoginSuccess(false), 5000)
      prevAuthed.current = authed
      return () => clearTimeout(t)
    }
    prevAuthed.current = authed
  }, [authed])

  return (
    <ToastProvider>
      {bootingAuth ? null : !authed ? (
        <div className="dash-login-view">
          <div className="dash-login-view__glow" aria-hidden="true" />
          <div className="dash-login-view__inner">
            <div className="dash-login-brand">
              <span className="dash-login-brand__mark" aria-hidden="true">
                O
              </span>
              <div>
                <h1 className="dash-login-brand__title">Ohtli</h1>
                <p className="dash-login-brand__sub">Inicia sesión para continuar</p>
              </div>
            </div>
            <LoginPage />
          </div>
        </div>
      ) : (
        <DashboardLayout
          user={user}
          activeSection={activeSection}
          onNavigate={(section) => navigate(sectionToRoute(section))}
          onLogout={() => logout()}
        >
          {activeSection === 'dashboard' && (
            <DashboardHome user={user} loginSuccess={loginSuccess} />
          )}
          {activeSection === 'users' && canViewUsers(user) && <UsersPage user={user} />}
          {activeSection === 'health' && <HealthCheck />}
          {activeSection === 'platform' && <PlatformAdminPage user={user} />}
        </DashboardLayout>
      )}
    </ToastProvider>
  )
}
