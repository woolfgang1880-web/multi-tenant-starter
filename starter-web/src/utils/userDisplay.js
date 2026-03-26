/** Iniciales para avatar (usuario preferente). */
export function getUserInitials(user) {
  if (!user) return '?'
  const n = typeof user.usuario === 'string' ? user.usuario.trim() : ''
  if (n) {
    const parts = n.split(/\s+/).filter(Boolean)
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
    }
    return n.slice(0, 2).toUpperCase()
  }
  const e = typeof user.email === 'string' ? user.email.trim() : ''
  if (e) {
    const local = e.split('@')[0] || e
    return local.slice(0, 2).toUpperCase()
  }
  return 'U'
}

export function getDisplayName(user) {
  if (!user) return 'Usuario'
  const n = typeof user.usuario === 'string' && user.usuario.trim()
  if (n) return n
  return 'Usuario'
}
