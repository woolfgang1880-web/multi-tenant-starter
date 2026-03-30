import { useState } from 'react'
import { requestSubscriptionActivation } from '../api/client.js'
import { useToast } from '../context/ToastContext.jsx'
import { mapApiError } from '../utils/apiError.js'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import { InlineAlert } from '../components/ui/feedback.jsx'
import { navigate, ROUTES } from '../routes/router.js'

export default function SubscriptionExpiredPage() {
  const { showToast } = useToast()
  const [requesting, setRequesting] = useState(false)

  const supportEmail = typeof import.meta.env.VITE_SUPPORT_EMAIL === 'string' ? import.meta.env.VITE_SUPPORT_EMAIL.trim() : ''
  const supportMailtoHref =
    supportEmail !== ''
      ? `mailto:${encodeURIComponent(supportEmail)}?subject=${encodeURIComponent('Solicitud de acceso — Ohtli')}`
      : null

  async function handleRequestActivation() {
    setRequesting(true)
    try {
      await requestSubscriptionActivation({})
      showToast('Solicitud registrada. Te contactaremos cuando sea posible.', 'success')
    } catch (err) {
      showToast(mapApiError(err, 'No se pudo enviar la solicitud.'), 'error')
    } finally {
      setRequesting(false)
    }
  }

  return (
    <div className="login-page subscription-expired-page" data-testid="subscription-expired-page">
      <Card title="Tu periodo de prueba ha finalizado" className="subscription-expired-page__card">
        <InlineAlert kind="warning" data-testid="subscription-expired-alert">
          Contacta para continuar usando el sistema.
        </InlineAlert>
        <div className="subscription-expired-page__actions subscription-expired-page__actions--row">
          <Button
            type="button"
            variant="primary"
            onClick={() => navigate(ROUTES.LOGIN)}
            data-testid="subscription-expired-back-login"
          >
            Volver al inicio de sesión
          </Button>
          <Button
            type="button"
            variant="secondary"
            loading={requesting}
            disabled={requesting}
            onClick={handleRequestActivation}
            data-testid="subscription-expired-request-activation"
          >
            Solicitar activación
          </Button>
          {supportMailtoHref ? (
            <a
              className="dash-btn dash-btn--outline subscription-expired-page__mailto"
              href={supportMailtoHref}
              data-testid="subscription-expired-contact-support"
            >
              Contactar soporte
            </a>
          ) : null}
        </div>
      </Card>
    </div>
  )
}
