export function InlineAlert({ kind = 'error', children, dismissLabel, onDismiss, ...props }) {
  const klass =
    kind === 'success'
      ? 'dash-alert dash-alert--success'
      : kind === 'warning'
        ? 'dash-alert dash-alert--warning'
        : 'dash-alert dash-alert--error'

  return (
    <div className={klass} role="alert" {...props}>
      <span>{children}</span>
      {onDismiss && (
        <button type="button" className="dash-alert__dismiss" onClick={onDismiss}>
          {dismissLabel || 'Cerrar'}
        </button>
      )}
    </div>
  )
}

export function LoadingState({ text = 'Cargando...' }) {
  return (
    <div className="dash-health-loading" aria-busy="true">
      <div className="dash-health-loading__pulse" />
      <p className="dash-muted">{text}</p>
    </div>
  )
}

export function EmptyState({ title = 'Sin datos', text, action }) {
  return (
    <div className="dash-empty-state">
      <p className="dash-empty-state__title">{title}</p>
      {text && <p className="dash-empty-state__text">{text}</p>}
      {action}
    </div>
  )
}

export function ErrorState({ title = 'Ocurrio un error', message, action }) {
  return (
    <div className="dash-empty-state dash-empty-state--error">
      <p className="dash-empty-state__title">{title}</p>
      {message && <p className="dash-empty-state__text">{message}</p>}
      {action}
    </div>
  )
}

