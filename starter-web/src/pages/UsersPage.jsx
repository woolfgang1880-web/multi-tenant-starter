import { useCallback, useEffect, useRef, useState } from 'react'
import { createUser, deactivateUser, getUsers, updateUser } from '../api/client.js'
import { useToast } from '../context/ToastContext.jsx'
import Card from '../components/ui/Card.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import { mapApiError } from '../utils/apiError.js'
import { useAsyncData } from '../hooks/useAsyncData.js'
import UserCrudForm from '../components/forms/UserCrudForm.jsx'
import { ErrorState, InlineAlert } from '../components/ui/feedback.jsx'
import DataTable, { RowActions } from '../components/ui/DataTable.jsx'
import { canCreateUser, canDeactivateUser, canEditUser, canViewUsers } from '../utils/authz.js'
import Button from '../components/ui/Button.jsx'
import { Field, SelectInput, TextInput } from '../components/ui/Field.jsx'
import PageHeader from '../components/ui/PageHeader.jsx'

function formatError(err) {
  if (err?.code === 'USER_ALREADY_EXISTS') return 'Ese usuario ya esta registrado.'
  return mapApiError(err, 'Error inesperado')
}

function normalizeUsersResponse(data) {
  const items = data?.items ?? data?.data ?? []
  const meta = data?.meta ?? null
  return {
    items: Array.isArray(items) ? items : [],
    current_page: meta?.current_page ?? 1,
    last_page: meta?.last_page ?? 1,
    total: meta?.total ?? (Array.isArray(items) ? items.length : 0),
    per_page: meta?.per_page ?? (Array.isArray(items) ? items.length : 0),
  }
}

export default function UsersPage({ user = null }) {
  const { showToast } = useToast()
  const { state: listState, run: runUsersRequest } = useAsyncData({
    fallbackMessage: 'No se pudo cargar la lista de usuarios.',
    initialData: null,
  })
  const [listRefreshing, setListRefreshing] = useState(false)
  const [operationError, setOperationError] = useState(null)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(15)
  const [searchTerm, setSearchTerm] = useState('')

  const [editing, setEditing] = useState(null)

  const [deactivateTarget, setDeactivateTarget] = useState(null)
  const [deactivateLoading, setDeactivateLoading] = useState(false)
  const deactivateInFlight = useRef(false)

  const loadUsers = useCallback(async ({ silent = false } = {}) => {
    if (silent) {
      setListRefreshing(true)
      const result = await runUsersRequest(() => getUsers({ page, perPage }).then(normalizeUsersResponse), { silent: true })
      if (!result.ok) {
        setOperationError(result.error)
        showToast(result.error, 'error')
      }
      setListRefreshing(false)
      return
    }

    await runUsersRequest(() => getUsers({ page, perPage }).then(normalizeUsersResponse))
  }, [page, perPage, runUsersRequest, showToast])

  const uiAuth = user
    ? {
        view: canViewUsers(user),
        create: canCreateUser(user),
        edit: canEditUser(user),
        deactivate: canDeactivateUser(user),
      }
    : { view: true, create: true, edit: true, deactivate: true }

  useEffect(() => {
    if (!uiAuth.view) return
    loadUsers({ silent: false })
  }, [loadUsers, uiAuth.view])

  function clearOperationError() {
    setOperationError(null)
  }

  function startEdit(u) {
    clearOperationError()
    setEditing(u)
  }

  function cancelEdit() {
    setEditing(null)
  }

  function requestDeactivate(u) {
    setDeactivateTarget(u)
  }

  async function confirmDeactivate() {
    if (!deactivateTarget || deactivateInFlight.current) return
    deactivateInFlight.current = true
    clearOperationError()
    setDeactivateLoading(true)
    try {
      await deactivateUser(deactivateTarget.id)
      if (editing?.id === deactivateTarget.id) cancelEdit()
      showToast('Usuario desactivado.', 'success')
      setDeactivateTarget(null)
      await loadUsers({ silent: true })
    } catch (err) {
      const msg = formatError(err)
      setOperationError(msg)
      showToast(msg, 'error')
    } finally {
      setDeactivateLoading(false)
      deactivateInFlight.current = false
    }
  }

  const rows = listState.data?.items ?? []
  const searchedRows = rows.filter((u) => {
    const q = searchTerm.trim().toLowerCase()
    if (!q) return true
    const hay = `${u.usuario ?? ''} ${u.codigo_cliente ?? ''}`.toLowerCase()
    return hay.includes(q)
  })

  const columns = [
    { key: 'id', label: 'ID' },
    { key: 'usuario', label: 'Usuario' },
    { key: 'codigo_cliente', label: 'Codigo cliente' },
    { key: 'activo', label: 'Activo' },
    { key: 'fecha_alta', label: 'Fecha alta' },
    ...(uiAuth.edit || uiAuth.deactivate ? [{ key: 'acciones', label: 'Acciones', className: 'dash-table__th-actions' }] : []),
  ]

  if (!uiAuth.view) {
    return (
      <div className="dash-page dash-page--wide users-page">
        <PageHeader title="Users" lead="No tienes permisos UI para ver este modulo." />
        <ErrorState title="Acceso restringido en UI" message="Tu perfil no puede visualizar gestion de usuarios." />
      </div>
    )
  }

  return (
    <div className="dash-page dash-page--wide users-page">
      <PageHeader
        title="Users"
        lead="Alta, edición y desactivación de usuarios de la empresa actual."
      />

      {operationError && (
        <InlineAlert kind="error" dismissLabel="Cerrar" onDismiss={clearOperationError}>
          {operationError}
        </InlineAlert>
      )}

      {uiAuth.create && (
        <Card title="Nuevo usuario" subtitle="Completa los datos para dar de alta un usuario" className="dash-card--lift">
          <UserCrudForm
            mode="create"
            onSubmit={(payload) => createUser(payload)}
            onSuccess={async () => {
              showToast('Usuario creado correctamente.', 'success')
              await loadUsers({ silent: true })
            }}
            onError={(msg) => {
              showToast(msg, 'error')
            }}
          />
        </Card>
      )}

      <Card title="Directorio" subtitle="Listado paginado de la empresa" className="dash-card--lift">
        <div className="dash-form dash-form--grid" style={{ marginBottom: '0.75rem' }}>
          <Field label="Buscar (usuario/codigo)">
            <TextInput
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Ej. admin_demo"
            />
          </Field>
          <Field label="Tamano pagina">
            <SelectInput
              value={perPage}
              onChange={(e) => {
                setPerPage(Number(e.target.value))
                setPage(1)
              }}
            >
              <option value={10}>10</option>
              <option value={15}>15</option>
              <option value={25}>25</option>
              <option value={50}>50</option>
            </SelectInput>
          </Field>
        </div>
        <DataTable
          columns={columns}
          rows={searchedRows}
          loading={listState.status === 'loading' && !listState.data}
          loadingText="Cargando directorio..."
          error={listState.status === 'error' ? listState.error : null}
          errorTitle="No se pudo cargar la lista"
          onRetry={() => loadUsers({ silent: false })}
          emptyTitle="Sin usuarios"
          emptyText={searchTerm.trim() ? 'No hay resultados para el filtro actual.' : 'Crea el primero con el formulario superior.'}
          toolbarMeta={
            listState.status === 'success' && listState.data
              ? `Pagina ${listState.data.current_page} · ${searchedRows.length} de ${listState.data.total} · limite ${listState.data.per_page}`
              : null
          }
          toolbarRight={
            listState.status === 'success'
              ? (
                <div className="dash-form__actions dash-form__actions--row">
                  {listRefreshing && <span className="dash-badge dash-badge--pulse" aria-live="polite">Sincronizando...</span>}
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={!listState.data || listState.data.current_page <= 1 || listRefreshing}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                  >
                    Anterior
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={!listState.data || listState.data.current_page >= (listState.data.last_page ?? 1) || listRefreshing}
                    onClick={() => setPage((p) => p + 1)}
                  >
                    Siguiente
                  </Button>
                </div>
              ) : null
          }
          renderRow={(u, key) => (
            <tr key={key}>
              <td className="dash-table__mono dash-table__cell-id">{u.id}</td>
              <td><span className="dash-table__strong">{u.usuario}</span></td>
              <td className="dash-table__muted">{u.codigo_cliente ?? '—'}</td>
              <td>
                <span className={u.activo ? 'dash-pill dash-pill--on' : 'dash-pill dash-pill--off'}>
                  {u.activo ? 'Si' : 'No'}
                </span>
              </td>
              <td className="dash-table__mono dash-table__muted">
                <time dateTime={u.fecha_alta ?? ''}>{u.fecha_alta ?? '—'}</time>
              </td>
              {(uiAuth.edit || uiAuth.deactivate) && (
                <RowActions>
                  {uiAuth.edit && (
                    <Button type="button" variant="ghost" size="sm" onClick={() => startEdit(u)}>
                      Editar
                    </Button>
                  )}
                  {uiAuth.deactivate && (
                    <Button
                      type="button"
                      variant="danger"
                      size="sm"
                      onClick={() => requestDeactivate(u)}
                      disabled={listRefreshing || deactivateLoading}
                    >
                      Desactivar
                    </Button>
                  )}
                </RowActions>
              )}
            </tr>
          )}
        />
      </Card>

      {uiAuth.edit && editing && (
        <Card title={`Editar · ${editing.usuario}`} subtitle="Actualiza los datos del usuario seleccionado" className="dash-card--accent dash-card--lift">
          <UserCrudForm
            mode="edit"
            initialValues={editing}
            onSubmit={(payload) => updateUser(editing.id, payload)}
            onSuccess={async () => {
              showToast('Usuario actualizado.', 'success')
              setEditing(null)
              await loadUsers({ silent: true })
            }}
            onError={(msg) => {
              showToast(msg, 'error')
            }}
          />
          <div className="dash-form__actions dash-form__actions--row">
            <Button type="button" variant="ghost" onClick={cancelEdit}>
              Cancelar
            </Button>
          </div>
        </Card>
      )}

      {uiAuth.deactivate && (
        <ConfirmDialog
          open={!!deactivateTarget}
          title="Desactivar usuario"
          message={deactivateTarget ? `¿Seguro que deseas desactivar a «${deactivateTarget.usuario}»?` : ''}
          confirmLabel="Desactivar"
          cancelLabel="Cancelar"
          danger
          loading={deactivateLoading}
          onConfirm={confirmDeactivate}
          onClose={() => !deactivateLoading && setDeactivateTarget(null)}
        />
      )}
    </div>
  )
}
