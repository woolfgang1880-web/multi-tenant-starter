import { useEffect, useState } from 'react'
import { getHealth, getUsers } from '../api/client.js'
import { useToast } from '../context/ToastContext.jsx'
import Card from '../components/ui/Card.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import { getDisplayName } from '../utils/userDisplay.js'

function IconUsersStat() {
  return (
    <svg className="dash-stat__svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
    </svg>
  )
}

function IconPulse() {
  return (
    <svg className="dash-stat__svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
    </svg>
  )
}

function IconUser() {
  return (
    <svg className="dash-stat__svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
      <circle cx="12" cy="7" r="4" />
    </svg>
  )
}

export default function DashboardHome({ user, loginSuccess }) {
  const { showToast } = useToast()
  const [statsLoading, setStatsLoading] = useState(true)
  const [totalUsers, setTotalUsers] = useState(null)
  const [apiStatus, setApiStatus] = useState(null)
  const [apiUptime, setApiUptime] = useState(null)
  const [statsError, setStatsError] = useState(null)

  useEffect(() => {
    if (!loginSuccess) return
    showToast(`Bienvenido, ${getDisplayName(user)}.`, 'success')
  }, [loginSuccess, showToast, user])

  useEffect(() => {
    let cancelled = false
    setStatsLoading(true)
    setStatsError(null)
    Promise.all([getUsers().catch(() => null), getHealth().catch(() => null)])
      .then(([usersRes, healthRes]) => {
        if (cancelled) return
        if (usersRes?.total != null) setTotalUsers(usersRes.total)
        else setTotalUsers('—')
        if (healthRes?.status) {
          setApiStatus(healthRes.status)
          setApiUptime(
            typeof healthRes.uptime === 'number' ? healthRes.uptime.toFixed(1) : null,
          )
        } else {
          setApiStatus('offline')
          setApiUptime(null)
        }
      })
      .catch(() => {
        if (!cancelled) setStatsError('No se pudieron cargar las métricas.')
      })
      .finally(() => {
        if (!cancelled) setStatsLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [])

  return (
    <div className="dash-page dash-page--wide">
      <div className="dash-hero">
        <p className="dash-hero__eyebrow">Panel principal</p>
        <h1 className="dash-hero__title">
          Hola, <span className="dash-hero__name">{getDisplayName(user)}</span>
        </h1>
        <p className="dash-hero__lead">Gestiona usuarios y revisa el estado del servicio desde un solo lugar.</p>
      </div>

      {statsError && (
        <div className="dash-alert dash-alert--error dash-alert--inline" role="alert">
          {statsError}
        </div>
      )}

      <div className="dash-stat-grid">
        {statsLoading ? (
          <>
            <div className="dash-stat dash-stat--skeleton" aria-busy="true" />
            <div className="dash-stat dash-stat--skeleton" aria-busy="true" />
            <div className="dash-stat dash-stat--skeleton" aria-busy="true" />
          </>
        ) : (
          <>
            <StatCard
              label="Total usuarios"
              value={totalUsers ?? '—'}
              hint="Usuarios registrados en tu organización"
              variant="indigo"
              icon={<IconUsersStat />}
            />
            <StatCard
              label="Estado del servicio"
              value={apiStatus === 'ok' ? 'Operativa' : apiStatus || '—'}
              hint={apiUptime != null ? `Tiempo activo aprox. ${apiUptime}s` : 'Disponibilidad del sistema'}
              variant={apiStatus === 'ok' ? 'success' : 'muted'}
              icon={<IconPulse />}
            />
            <StatCard
              label="Tu sesión"
              value={user?.email ? user.email.split('@')[0] : '—'}
              hint={user?.email || 'Sin email'}
              variant="violet"
              icon={<IconUser />}
            />
          </>
        )}
      </div>

      <div className="dash-home-grid">
        <Card title="Accesos rápidos" subtitle="Navegación">
          <ul className="dash-quick-list">
            <li>
              <strong>Users</strong> — alta, edición y baja de usuarios con validación en vivo.
            </li>
            <li>
              <strong>Estado del servicio</strong> — comprueba si el sistema está disponible.
            </li>
          </ul>
        </Card>
        <Card title="Cuenta activa" subtitle="Datos del perfil">
          <dl className="dash-dl dash-dl--comfortable">
            <dt>Nombre</dt>
            <dd>{user?.name ?? '—'}</dd>
            <dt>Email</dt>
            <dd>{user?.email ?? '—'}</dd>
            <dt>ID</dt>
            <dd>{user?.id ?? '—'}</dd>
          </dl>
        </Card>
      </div>
    </div>
  )
}
