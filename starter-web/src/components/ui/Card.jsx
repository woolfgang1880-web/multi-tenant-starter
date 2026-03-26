/**
 * Tarjeta reutilizable para el panel (título, subtítulo, acciones opcionales).
 */
export default function Card({ title, subtitle, actions, children, className = '', as: Tag = 'section' }) {
  return (
    <Tag className={`dash-card ${className}`.trim()}>
      {(title || actions) && (
        <header className="dash-card__header">
          <div className="dash-card__head-text">
            {title && <h2 className="dash-card__title">{title}</h2>}
            {subtitle && <p className="dash-card__subtitle">{subtitle}</p>}
          </div>
          {actions && <div className="dash-card__actions">{actions}</div>}
        </header>
      )}
      <div className="dash-card__body">{children}</div>
    </Tag>
  )
}
