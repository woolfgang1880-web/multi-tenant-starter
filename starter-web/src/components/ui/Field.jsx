export function Field({ label, error = null, children }) {
  return (
    <label className="dash-field">
      {label && <span className="dash-field__label">{label}</span>}
      {children}
      {error && (
        <p className="dash-field-error" role="alert">
          {error}
        </p>
      )}
    </label>
  )
}

export function TextInput({ className = 'dash-input', ...props }) {
  return <input className={className} {...props} />
}

export function SelectInput({ className = 'dash-input', children, ...props }) {
  return (
    <select className={className} {...props}>
      {children}
    </select>
  )
}

