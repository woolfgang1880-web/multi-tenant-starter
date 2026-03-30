import { useMemo } from 'react'
import { InlineAlert } from './ui/feedback.jsx'

function formatDateShort(iso) {
  if (!iso || typeof iso !== 'string') return null
  const s = iso.trim()
  if (s.length < 10) return null
  return s.slice(0, 10)
}

function getDaysLeft(trialEndsAtIso) {
  const ends = trialEndsAtIso ? new Date(trialEndsAtIso) : null
  if (!ends || Number.isNaN(ends.getTime())) return null
  const diffMs = ends.getTime() - Date.now()
  if (diffMs <= 0) return 0
  const dayMs = 1000 * 60 * 60 * 24
  return Math.ceil(diffMs / dayMs)
}

export default function TenantTrialStatusBanner({ tenant }) {
  const banner = useMemo(() => {
    const subscriptionStatus = tenant?.subscription_status ?? null
    const trialEndsAt = tenant?.trial_ends_at ?? null
    const trialEndsShort = formatDateShort(trialEndsAt)

    if (!subscriptionStatus) return null

    if (subscriptionStatus === 'trial') {
      const daysLeft = getDaysLeft(trialEndsAt)
      if (daysLeft === null) return null

      if (daysLeft === 0) {
        return {
          kind: 'error',
          message: trialEndsShort ? `Tu prueba gratuita venció el ${trialEndsShort}.` : 'Tu prueba gratuita venció.',
        }
      }

      if (daysLeft <= 7) {
        return {
          kind: 'warning',
          message: trialEndsShort
            ? `Tu prueba gratuita vence en ${daysLeft} día(s) (el ${trialEndsShort}).`
            : `Tu prueba gratuita vence en ${daysLeft} día(s).`,
          secondaryHint: 'Tu acceso puede bloquearse al vencer el periodo de prueba.',
        }
      }

      return {
        kind: 'success',
        message: trialEndsShort ? `Tu prueba gratuita está activa hasta el ${trialEndsShort}.` : 'Tu prueba gratuita está activa.',
      }
    }

    if (subscriptionStatus === 'active') {
      return { kind: 'success', message: 'Tu suscripción está activa.' }
    }

    if (subscriptionStatus === 'expired' || subscriptionStatus === 'suspended') {
      return {
        kind: 'error',
        message: subscriptionStatus === 'suspended' ? 'Tu acceso fue suspendido.' : 'Tu suscripción está vencida.',
      }
    }

    return null
  }, [tenant])

  if (!banner) return null

  return (
    <InlineAlert kind={banner.kind} data-testid="tenant-trial-banner">
      <span>{banner.message}</span>
      {banner.secondaryHint ? (
        <span className="dash-trial-banner__hint" data-testid="tenant-trial-banner-hint">
          {banner.secondaryHint}
        </span>
      ) : null}
    </InlineAlert>
  )
}

