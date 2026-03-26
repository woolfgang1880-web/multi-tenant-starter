import { render, screen } from '@testing-library/react'
import DataTable, { RowActions } from './DataTable.jsx'

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'usuario', label: 'Usuario' },
  { key: 'acciones', label: 'Acciones' },
]

describe('DataTable', () => {
  it('renderiza loading state', () => {
    render(
      <DataTable
        columns={columns}
        rows={[]}
        loading
        loadingText="Cargando directorio..."
        renderRow={() => null}
      />,
    )
    expect(screen.getByText(/Cargando directorio/i)).toBeInTheDocument()
  })

  it('renderiza empty state', () => {
    render(
      <DataTable
        columns={columns}
        rows={[]}
        emptyTitle="Sin usuarios"
        emptyText="Crea uno."
        renderRow={() => null}
      />,
    )
    expect(screen.getByText(/Sin usuarios/i)).toBeInTheDocument()
    expect(screen.getByText(/Crea uno/i)).toBeInTheDocument()
  })

  it('renderiza error state', () => {
    render(
      <DataTable
        columns={columns}
        rows={[]}
        error="forbidden"
        errorTitle="No se pudo cargar"
        renderRow={() => null}
      />,
    )
    expect(screen.getByText(/No se pudo cargar/i)).toBeInTheDocument()
    expect(screen.getByText(/forbidden/i)).toBeInTheDocument()
  })

  it('renderiza filas con acciones visibles', () => {
    const rows = [{ id: 1, usuario: 'admin_demo' }]
    render(
      <DataTable
        columns={columns}
        rows={rows}
        renderRow={(row, key) => (
          <tr key={key}>
            <td>{row.id}</td>
            <td>{row.usuario}</td>
            <RowActions>
              <button type="button">Editar</button>
              <button type="button">Desactivar</button>
            </RowActions>
          </tr>
        )}
      />,
    )
    expect(screen.getByText('admin_demo')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Editar' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Desactivar' })).toBeInTheDocument()
  })
})

