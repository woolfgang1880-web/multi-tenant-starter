import { EmptyState, ErrorState } from './feedback.jsx'

function TableSkeletonRows({ colSpan, count = 5 }) {
  return (
    <>
      {Array.from({ length: count }, (_, i) => (
        <tr key={i} className="dash-table__skeleton-row">
          <td colSpan={colSpan}>
            <span className="dash-skel dash-skel--line" />
          </td>
        </tr>
      ))}
    </>
  )
}

export function RowActions({ children }) {
  return <td className="dash-table__actions">{children}</td>
}

/**
 * Tabla ligera reutilizable para listados con estados.
 */
export default function DataTable({
  columns,
  rows,
  loading = false,
  loadingText = 'Cargando...',
  error = null,
  errorTitle = 'No se pudo cargar la tabla',
  onRetry,
  emptyTitle = 'Sin datos',
  emptyText,
  rowKey = (row) => row?.id,
  renderRow,
  toolbarRight = null,
  toolbarMeta = null,
}) {
  const colSpan = columns.length

  if (loading) {
    return (
      <>
        <div className="dash-table-toolbar">
          <span className="dash-muted dash-skeleton-line">{loadingText}</span>
        </div>
        <div className="dash-table-wrap dash-table-wrap--polish">
          <table className="dash-table dash-table--polish">
            <thead>
              <tr>
                {columns.map((c) => (
                  <th key={c.key} className={c.className}>{c.label}</th>
                ))}
              </tr>
            </thead>
            <tbody><TableSkeletonRows colSpan={colSpan} /></tbody>
          </table>
        </div>
      </>
    )
  }

  if (error) {
    return (
      <ErrorState
        title={errorTitle}
        message={error}
        action={
          onRetry ? (
            <button type="button" className="dash-btn dash-btn--secondary" onClick={onRetry}>
              Reintentar
            </button>
          ) : null
        }
      />
    )
  }

  return (
    <>
      {(toolbarMeta || toolbarRight) && (
        <div className="dash-table-toolbar">
          <p className="dash-table-toolbar__meta">{toolbarMeta}</p>
          {toolbarRight}
        </div>
      )}
      <div className="dash-table-wrap dash-table-wrap--polish">
        <table className="dash-table dash-table--polish">
          <thead>
            <tr>
              {columns.map((c) => (
                <th key={c.key} className={c.className}>{c.label}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={colSpan}>
                  <EmptyState title={emptyTitle} text={emptyText} />
                </td>
              </tr>
            ) : (
              rows.map((row) => renderRow(row, rowKey(row)))
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}

