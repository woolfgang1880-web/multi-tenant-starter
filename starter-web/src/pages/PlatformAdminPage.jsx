import { useMemo, useState } from 'react'
import { createPlatformTenant, createPlatformTenantInitialAdmin } from '../api/client.js'
import { useToast } from '../context/ToastContext.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import { Field, TextInput, SelectInput } from '../components/ui/Field.jsx'
import { InlineAlert, LoadingState } from '../components/ui/feedback.jsx'
import { mapApiError } from '../utils/apiError.js'

const tenantFormDefaults = () => ({
  nombre: '',
  codigo: '',
  activo: true,
})

const adminFormDefaults = () => ({
  tenant_codigo: '',
  admin_usuario: '',
  admin_password: '',
  admin_password_confirmation: '',
  admin_codigo_cliente: '',
})

export default function PlatformAdminPage({ user }) {
  const { showToast } = useToast()

  const [tenantForm, setTenantForm] = useState(() => tenantFormDefaults())
  const [tenantLoading, setTenantLoading] = useState(false)
  const [tenantError, setTenantError] = useState(null)
  const [tenantSuccess, setTenantSuccess] = useState(null)

  const [adminForm, setAdminForm] = useState(() => adminFormDefaults())
  const [adminLoading, setAdminLoading] = useState(false)
  const [adminError, setAdminError] = useState(null)
  const [adminSuccess, setAdminSuccess] = useState(null)

  const canCreateTenant = useMemo(
    () => tenantForm.nombre.trim() !== '' && tenantForm.codigo.trim() !== '' && !tenantLoading,
    [tenantForm, tenantLoading],
  )
  const canCreateAdmin = useMemo(
    () =>
      adminForm.tenant_codigo.trim() !== '' &&
      adminForm.admin_usuario.trim() !== '' &&
      adminForm.admin_password !== '' &&
      !adminLoading,
    [adminForm, adminLoading],
  )

  async function handleCreateTenant(e) {
    e.preventDefault()
    setTenantError(null)
    setTenantSuccess(null)
    setAdminError(null)
    setAdminSuccess(null)

    setTenantLoading(true)
    try {
      const res = await createPlatformTenant({
        nombre: tenantForm.nombre.trim(),
        codigo: tenantForm.codigo.trim(),
        activo: !!tenantForm.activo,
      })

      const createdCodigo = res?.data?.codigo || tenantForm.codigo.trim()
      setTenantSuccess(`Empresa creada correctamente: ${createdCodigo}`)
      showToast('Empresa creada correctamente.', 'success')

      // Semillado del segundo formulario para hacer la demo lo más fluida posible.
      setAdminForm((f) => ({
        ...f,
        tenant_codigo: createdCodigo,
      }))
    } catch (err) {
      const msg = mapApiError(err, 'No se pudo crear la empresa.')
      setTenantError(msg)
      showToast(msg, 'error')
    } finally {
      setTenantLoading(false)
    }
  }

  async function handleCreateInitialAdmin(e) {
    e.preventDefault()
    setAdminError(null)
    setAdminSuccess(null)

    setAdminLoading(true)
    try {
      const res = await createPlatformTenantInitialAdmin({
        tenant_codigo: adminForm.tenant_codigo.trim(),
        admin_usuario: adminForm.admin_usuario.trim(),
        admin_password: adminForm.admin_password,
        admin_password_confirmation: adminForm.admin_password_confirmation,
        admin_codigo_cliente: adminForm.admin_codigo_cliente.trim() || undefined,
      })

      const createdUser = res?.data?.usuario || adminForm.admin_usuario.trim()
      setAdminSuccess(`Admin inicial creado correctamente: ${createdUser}`)
      showToast('Admin inicial creado correctamente.', 'success')
    } catch (err) {
      const msg = mapApiError(err, 'No se pudo crear el admin inicial.')
      setAdminError(msg)
      showToast(msg, 'error')
    } finally {
      setAdminLoading(false)
    }
  }

  // UX-only: se oculta en sidebar, pero también protegemos aquí para rutas directas.
  if (!user?.is_platform_admin) {
    return (
      <div className="dash-page dash-page--wide">
        <Card title="Plataforma" subtitle="Acceso restringido">
          <InlineAlert kind="error">Acceso restringido en UI.</InlineAlert>
        </Card>
      </div>
    )
  }

  return (
    <div className="dash-page dash-page--wide platform-admin-page">
      <div className="dash-hero" style={{ marginBottom: '1.25rem' }}>
        <p className="dash-hero__eyebrow">Administración de plataforma</p>
        <h1 className="dash-hero__title">Plataforma</h1>
        <p className="dash-hero__lead">Crea empresas y el admin inicial de cada una.</p>
      </div>

      <div className="dash-grid" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(20rem, 1fr))' }}>
        <Card title="Crear empresa" subtitle="Nombre, código y estado">
          <form className="dash-form" onSubmit={handleCreateTenant}>
            <Field label="Nombre">
              <TextInput value={tenantForm.nombre} onChange={(e) => setTenantForm((f) => ({ ...f, nombre: e.target.value }))} />
            </Field>
            <Field label="Código">
              <TextInput value={tenantForm.codigo} onChange={(e) => setTenantForm((f) => ({ ...f, codigo: e.target.value }))} />
            </Field>
            <Field label="Activo">
              <SelectInput
                value={tenantForm.activo ? '1' : '0'}
                onChange={(e) => setTenantForm((f) => ({ ...f, activo: e.target.value === '1' }))}
              >
                <option value="1">Sí</option>
                <option value="0">No</option>
              </SelectInput>
            </Field>

            {tenantError && <InlineAlert kind="error">{tenantError}</InlineAlert>}
            {tenantSuccess && <InlineAlert kind="success">{tenantSuccess}</InlineAlert>}

            <Button type="submit" variant="primary" block disabled={!canCreateTenant} loading={tenantLoading}>
              {tenantLoading ? 'Creando...' : 'Crear empresa'}
            </Button>
          </form>
        </Card>

        <Card title="Crear admin inicial" subtitle="Empresa existente">
          <form className="dash-form" onSubmit={handleCreateInitialAdmin}>
            <Field label="Código de empresa">
              <TextInput
                value={adminForm.tenant_codigo}
                onChange={(e) => setAdminForm((f) => ({ ...f, tenant_codigo: e.target.value }))}
                placeholder="Ej. EMPRESA1"
              />
            </Field>
            <Field label="Admin usuario">
              <TextInput
                value={adminForm.admin_usuario}
                onChange={(e) => setAdminForm((f) => ({ ...f, admin_usuario: e.target.value }))}
                placeholder="ej. admin_empresa1"
              />
            </Field>
            <Field label="Admin contraseña">
              <TextInput
                type="password"
                value={adminForm.admin_password}
                onChange={(e) => setAdminForm((f) => ({ ...f, admin_password: e.target.value }))}
                placeholder="Minimo 8 caracteres"
              />
            </Field>
            <Field label="Confirmar contraseña">
              <TextInput
                type="password"
                value={adminForm.admin_password_confirmation}
                onChange={(e) => setAdminForm((f) => ({ ...f, admin_password_confirmation: e.target.value }))}
                placeholder="Repite la contraseña"
              />
            </Field>
            <Field label="Código cliente (opcional)">
              <TextInput
                value={adminForm.admin_codigo_cliente}
                onChange={(e) => setAdminForm((f) => ({ ...f, admin_codigo_cliente: e.target.value }))}
                placeholder="CLI-X"
              />
            </Field>

            {adminLoading && <LoadingState text="Creando admin..." />}
            {adminError && <InlineAlert kind="error">{adminError}</InlineAlert>}
            {adminSuccess && <InlineAlert kind="success">{adminSuccess}</InlineAlert>}

            <Button type="submit" variant="primary" block disabled={!canCreateAdmin} loading={adminLoading}>
              {adminLoading ? 'Creando...' : 'Crear admin inicial'}
            </Button>
          </form>
        </Card>
      </div>
    </div>
  )
}

