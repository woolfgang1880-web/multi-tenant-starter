function variantClass(variant) {
  if (variant === 'primary') return 'dash-btn--primary'
  if (variant === 'secondary') return 'dash-btn--secondary'
  if (variant === 'ghost') return 'dash-btn--ghost'
  if (variant === 'danger') return 'dash-btn--danger'
  if (variant === 'danger-solid') return 'dash-btn--danger-solid'
  if (variant === 'outline') return 'dash-btn--outline'
  return ''
}

function sizeClass(size) {
  if (size === 'sm') return 'dash-btn--sm'
  return ''
}

export default function Button({
  type = 'button',
  variant = 'secondary',
  size = 'md',
  block = false,
  loading = false,
  disabled = false,
  className = '',
  children,
  ...props
}) {
  const classes = [
    'dash-btn',
    variantClass(variant),
    sizeClass(size),
    block ? 'dash-btn--block' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <button type={type} className={classes} disabled={disabled || loading} {...props}>
      {children}
    </button>
  )
}

