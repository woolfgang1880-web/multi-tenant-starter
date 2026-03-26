import { fireEvent, render, screen } from '@testing-library/react'
import { EmptyState, ErrorState, InlineAlert, LoadingState } from './feedback.jsx'

describe('Feedback UI components', () => {
  it('renderiza loading state', () => {
    render(<LoadingState text="Cargando data..." />)
    expect(screen.getByText(/Cargando data/i)).toBeInTheDocument()
  })

  it('renderiza empty state', () => {
    render(<EmptyState title="Sin elementos" text="Agrega uno nuevo." />)
    expect(screen.getByText(/Sin elementos/i)).toBeInTheDocument()
    expect(screen.getByText(/Agrega uno nuevo/i)).toBeInTheDocument()
  })

  it('renderiza error state', () => {
    render(<ErrorState title="Error al cargar" message="Fallo API" />)
    expect(screen.getByText(/Error al cargar/i)).toBeInTheDocument()
    expect(screen.getByText(/Fallo API/i)).toBeInTheDocument()
  })

  it('inline alert permite dismiss opcional', () => {
    const onDismiss = vi.fn()
    render(
      <InlineAlert kind="error" onDismiss={onDismiss}>
        Mensaje importante
      </InlineAlert>,
    )
    fireEvent.click(screen.getByRole('button', { name: /Cerrar/i }))
    expect(onDismiss).toHaveBeenCalled()
  })
})

