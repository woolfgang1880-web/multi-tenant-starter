import { render, screen } from '@testing-library/react'
import Button from './Button.jsx'
import { Field, TextInput } from './Field.jsx'

describe('Componentes base UI', () => {
  it('Button respeta disabled/loading', () => {
    const { rerender } = render(
      <Button type="button" variant="primary">
        Guardar
      </Button>,
    )
    expect(screen.getByRole('button', { name: 'Guardar' })).not.toBeDisabled()

    rerender(
      <Button type="button" variant="primary" loading>
        Guardar
      </Button>,
    )
    expect(screen.getByRole('button', { name: 'Guardar' })).toBeDisabled()
  })

  it('Field renderiza label y error de forma consistente', () => {
    render(
      <Field label="Usuario" error="Campo requerido">
        <TextInput value="" readOnly />
      </Field>,
    )

    expect(screen.getByText('Usuario')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('Campo requerido')
  })
})

