import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { ToastProvider } from '../context/ToastContext.jsx'
import SubscriptionExpiredPage from './SubscriptionExpiredPage.jsx'

const navigateMock = vi.fn()
const requestActivationMock = vi.fn().mockResolvedValue({ received: true })

vi.mock('../routes/router.js', () => ({
  ROUTES: { LOGIN: '/login', SUBSCRIPTION_EXPIRED: '/subscription-expired' },
  navigate: (...args) => navigateMock(...args),
}))

vi.mock('../api/client.js', () => ({
  requestSubscriptionActivation: (...args) => requestActivationMock(...args),
}))

function renderPage() {
  return render(
    <ToastProvider>
      <SubscriptionExpiredPage />
    </ToastProvider>,
  )
}

describe('SubscriptionExpiredPage', () => {
  beforeEach(() => {
    navigateMock.mockReset()
    requestActivationMock.mockClear()
    requestActivationMock.mockResolvedValue({ received: true })
  })

  it('renderiza mensaje principal y secundario', () => {
    renderPage()

    expect(screen.getByTestId('subscription-expired-page')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: /Tu periodo de prueba ha finalizado/i })).toBeInTheDocument()
    expect(screen.getByTestId('subscription-expired-alert')).toHaveTextContent(
      'Contacta para continuar usando el sistema.',
    )
  })

  it('muestra Solicitar activación y Volver al inicio de sesión', () => {
    renderPage()
    expect(screen.getByTestId('subscription-expired-request-activation')).toHaveTextContent('Solicitar activación')
    expect(screen.getByTestId('subscription-expired-back-login')).toBeInTheDocument()
  })

  it('navega al login al pulsar Volver al inicio de sesión', () => {
    renderPage()
    fireEvent.click(screen.getByTestId('subscription-expired-back-login'))
    expect(navigateMock).toHaveBeenCalledWith('/login')
  })

  it('envía solicitud de activación al pulsar el botón', async () => {
    renderPage()
    fireEvent.click(screen.getByTestId('subscription-expired-request-activation'))
    await waitFor(() => {
      expect(requestActivationMock).toHaveBeenCalledWith({})
    })
  })
})
