import { useCallback, useState } from 'react'
import { mapApiError } from '../utils/apiError.js'

export function useAsyncData({ fallbackMessage = 'Error desconocido', initialData = null } = {}) {
  const [state, setState] = useState({
    status: 'idle',
    data: initialData,
    error: null,
  })

  const run = useCallback(async (request, { silent = false } = {}) => {
    if (!silent) {
      setState((s) => ({ ...s, status: 'loading', error: null }))
    }

    try {
      const data = await request()
      setState({ status: 'success', data, error: null })
      return { ok: true, data }
    } catch (e) {
      const mapped = mapApiError(e, fallbackMessage)
      setState((s) => ({
        status: 'error',
        data: silent ? s.data : initialData,
        error: mapped,
      }))
      return { ok: false, error: mapped }
    }
  }, [fallbackMessage, initialData])

  return { state, setState, run }
}

