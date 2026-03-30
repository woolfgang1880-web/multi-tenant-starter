export function mapApiError(err, fallback = 'Error desconocido') {
  if (!err || typeof err !== 'object') return fallback

  if (err.status === 401) {
    return 'Sesion no valida o expirada. Vuelve a iniciar sesion.'
  }

  if (err.status === 403) {
    if (err.code === 'SUBSCRIPTION_EXPIRED') {
      return typeof err.message === 'string' && err.message
        ? err.message
        : 'El periodo de prueba de esta empresa ha finalizado o el acceso no esta disponible.'
    }
    return 'No tienes permisos para esta accion.'
  }

  if (err.code === 'VALIDATION_ERROR' && err.body?.data?.errors) {
    const firstField = Object.keys(err.body.data.errors)[0]
    const firstIssue = firstField ? err.body.data.errors[firstField]?.[0] : null
    if (firstField && firstIssue) return `${firstField}: ${firstIssue}`
  }

  return err.message || fallback
}

