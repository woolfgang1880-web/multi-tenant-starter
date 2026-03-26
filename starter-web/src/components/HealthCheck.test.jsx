import { render, screen, waitFor } from '@testing-library/react'
import { vi } from 'vitest'
import HealthCheck from './HealthCheck.jsx'

const getHealthMock = vi.fn()

vi.mock('../api/client.js', () => ({
  getHealth: (...args) => getHealthMock(...args),
}))

describe('HealthCheck states', () => {
  beforeEach(() => {
    getHealthMock.mockReset()
  })

  it('muestra loading y luego exito', async () => {
    getHealthMock.mockResolvedValueOnce({ status: 'ok', uptime: 10 })
    render(<HealthCheck />)

    expect(screen.getByText(/Comprobando disponibilidad/i)).toBeInTheDocument()
    await waitFor(() => expect(screen.getByText('ok')).toBeInTheDocument())
  })

  it('muestra error si falla la API', async () => {
    getHealthMock.mockRejectedValueOnce(new Error('fallo health'))
    render(<HealthCheck />)

    await waitFor(() =>
      expect(screen.getByText(/No se pudo consultar el estado del servicio/i)).toBeInTheDocument(),
    )
    expect(screen.getByText(/fallo health/i)).toBeInTheDocument()
  })
})

