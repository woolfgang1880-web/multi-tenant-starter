import { useEffect, useState } from 'react'
import { getHealth } from '../api/client.js'
import Card from './ui/Card.jsx'
import { useAsyncData } from '../hooks/useAsyncData.js'
import { ErrorState, LoadingState } from './ui/feedback.jsx'

export default function HealthCheck() {
  const { state, run } = useAsyncData({ fallbackMessage: 'No se pudo consultar el estado del servicio.' })
  const [tick, setTick] = useState(0)

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      if (cancelled) return
      await run(() => getHealth())
    })()

    return () => {
      cancelled = true
    }
  }, [tick])

  const refreshBtn = (
    <button
      type="button"
      className="dash-btn dash-btn--secondary"
      onClick={() => setTick((t) => t + 1)}
      disabled={state.status === 'loading'}
    >
      Actualizar
    </button>
  )

  return (
    <div className="dash-page dash-page--wide">
      <div className="dash-page__intro">
        <h1 className="dash-page__title">Estado del servicio</h1>
        <p className="dash-page__lead">Comprueba si el sistema está operativo y responde con normalidad.</p>
      </div>

      <Card
        title="Resumen"
        actions={refreshBtn}
        className="dash-card--lift"
      >
        {state.status === 'loading' && (
          <LoadingState text="Comprobando disponibilidad..." />
        )}

        {state.status === 'error' && (
          <ErrorState title="No se pudo consultar el estado del servicio" message={state.error} />
        )}

        {state.status === 'success' && state.data && (
          <div className="dash-health-metrics">
            <div className="dash-metric dash-metric--hero">
              <span className="dash-metric__label">Estado</span>
              <span className="dash-metric__value dash-metric__value--ok">{state.data.status}</span>
            </div>
            <div className="dash-metric dash-metric--hero">
              <span className="dash-metric__label">Uptime (s)</span>
              <span className="dash-metric__value">
                {typeof state.data.uptime === 'number' ? state.data.uptime.toFixed(2) : '—'}
              </span>
            </div>
          </div>
        )}
      </Card>
    </div>
  )
}
