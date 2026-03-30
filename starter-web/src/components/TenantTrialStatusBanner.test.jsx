import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import TenantTrialStatusBanner from './TenantTrialStatusBanner.jsx'

describe('TenantTrialStatusBanner', () => {
  it('muestra banner success cuando trial sigue activo', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-01T00:00:00.000Z'))

    render(
      <TenantTrialStatusBanner
        tenant={{
          subscription_status: 'trial',
          trial_ends_at: '2026-03-10T00:00:00.000Z',
        }}
      />,
    )

    const banner = screen.getByTestId('tenant-trial-banner')
    expect(banner).toHaveClass('dash-alert--success')
    expect(screen.getByText(/Tu prueba gratuita está activa hasta el 2026-03-10/i)).toBeInTheDocument()

    vi.useRealTimers()
  })

  it('muestra banner warning cuando trial vence pronto', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-01T00:00:00.000Z'))

    render(
      <TenantTrialStatusBanner
        tenant={{
          subscription_status: 'trial',
          trial_ends_at: '2026-03-04T00:00:00.000Z',
        }}
      />,
    )

    const banner = screen.getByTestId('tenant-trial-banner')
    expect(banner).toHaveClass('dash-alert--warning')
    expect(screen.getByText(/vence en 3 día\(s\) \(el 2026-03-04\)/i)).toBeInTheDocument()
    expect(screen.getByTestId('tenant-trial-banner-hint')).toHaveTextContent(
      'Tu acceso puede bloquearse al vencer el periodo de prueba.',
    )

    vi.useRealTimers()
  })

  it('muestra banner error cuando trial ya venció', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-01T00:00:00.000Z'))

    render(
      <TenantTrialStatusBanner
        tenant={{
          subscription_status: 'trial',
          trial_ends_at: '2026-02-28T00:00:00.000Z',
        }}
      />,
    )

    const banner = screen.getByTestId('tenant-trial-banner')
    expect(banner).toHaveClass('dash-alert--error')
    expect(screen.getByText(/Tu prueba gratuita venció el 2026-02-28/i)).toBeInTheDocument()

    vi.useRealTimers()
  })
})

