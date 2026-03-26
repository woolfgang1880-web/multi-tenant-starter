/**
 * Tarjeta de métrica para el dashboard.
 */
export default function StatCard({ label, value, hint, variant = 'default', icon }) {
  return (
    <div className={`dash-stat dash-stat--${variant}`}>
      {icon && <div className="dash-stat__icon" aria-hidden="true">{icon}</div>}
      <div className="dash-stat__body">
        <span className="dash-stat__label">{label}</span>
        <span className="dash-stat__value">{value}</span>
        {hint && <span className="dash-stat__hint">{hint}</span>}
      </div>
    </div>
  )
}
