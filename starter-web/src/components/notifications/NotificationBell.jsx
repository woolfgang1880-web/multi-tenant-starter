import { useEffect, useRef, useState } from 'react'

const MOCK_NOTIFICATIONS = [
  {
    id: '1',
    title: 'Bienvenida',
    body: 'Usa el menú lateral para gestionar usuarios y revisar el estado del API.',
    time: 'Hoy',
  },
  {
    id: '2',
    title: 'Recordatorio',
    body: 'Los tokens de acceso caducan; si falla una petición, vuelve a iniciar sesión.',
    time: 'Demo',
  },
]

export default function NotificationBell() {
  const [open, setOpen] = useState(false)
  const wrapRef = useRef(null)

  useEffect(() => {
    if (!open) return undefined
    function handle(e) {
      if (wrapRef.current && !wrapRef.current.contains(e.target)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handle)
    return () => document.removeEventListener('mousedown', handle)
  }, [open])

  return (
    <div className="dash-notify" ref={wrapRef}>
      <button
        type="button"
        className="dash-notify__trigger"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-haspopup="true"
        aria-label="Notificaciones"
      >
        <svg className="dash-notify__bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>
        <span className="dash-notify__badge" aria-hidden="true">
          {MOCK_NOTIFICATIONS.length}
        </span>
      </button>

      {open && (
        <div className="dash-notify__panel" role="menu">
          <div className="dash-notify__head">
            <span className="dash-notify__head-title">Notificaciones</span>
            <span className="dash-notify__head-badge">Demo</span>
          </div>
          <ul className="dash-notify__list">
            {MOCK_NOTIFICATIONS.map((n) => (
              <li key={n.id} className="dash-notify__item">
                <p className="dash-notify__item-title">{n.title}</p>
                <p className="dash-notify__item-body">{n.body}</p>
                <span className="dash-notify__item-time">{n.time}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}
