import { useState } from 'react'
import { buildAuthErrorDebugReport, getApiBaseUrl, login, selectLoginTenant } from '../api/client.js'
import Button from './ui/Button.jsx'
import { Field, SelectInput, TextInput } from './ui/Field.jsx'

/** Alineado con `starter-core/database/seeders/DemoUserSeeder.php` y docs/DEMO_USERS.md */
const DEMO_CREDENTIAL_ROWS = [
  { tenant: 'DEFAULT', usuario: 'admin_demo', password: 'Admin123!' },
  { tenant: 'DEFAULT', usuario: 'user_demo', password: 'User123!' },
  { tenant: 'DEFAULT', usuario: 'manager_demo', password: 'Manager123!' },
  { tenant: 'PRUEBA1', usuario: 'admin_prueba1', password: 'AdminPrueba1!' },
  { tenant: 'PRUEBA1', usuario: 'user_prueba1', password: 'UserPrueba1!' },
  { tenant: 'PRUEBAS', usuario: 'admin_pruebas', password: 'AdminPruebas123!' },
  { tenant: 'PRUEBAS', usuario: 'user_pruebas1', password: 'UserPruebas123!' },
  { tenant: 'DEFAULT o PRUEBA1', usuario: 'multi_demo', password: 'MultiDemo123!', note: 'Misma contraseña; login global = vaciar empresa' },
]

const isViteDevelopment = import.meta.env.MODE === 'development'

function mapLoginError(err) {
  if (!err || typeof err !== 'object') {
    return 'No se pudo iniciar sesión.'
  }
  if (err.code === 'SELECTION_TOKEN_INVALID') {
    return 'La selección de empresa expiró. Inicia sesión de nuevo.'
  }

  const afterProfile = err.afterLoginProfile === true

  if (afterProfile) {
    if (err.status === 401) {
      return 'La sesión no pudo validarse al cargar tu perfil (no es un fallo de contraseña). Intenta de nuevo o vuelve a iniciar sesión.'
    }
    if (err.status === 429) {
      return 'Demasiadas peticiones al cargar el perfil. Espera unos segundos e inténtalo de nuevo.'
    }
    if (err.status >= 500) {
      return 'El acceso fue aceptado, pero el servidor falló al cargar el perfil. Revisa los logs del API; no suele indicar usuario o contraseña incorrectos.'
    }
    if (err.message) {
      return err.message
    }
    return 'No se pudo cargar el perfil tras iniciar sesión.'
  }

  if (err.status === 403 || err.code === 'ACCOUNT_INACTIVE') {
    return 'La cuenta está desactivada.'
  }
  if (err.status === 401 || err.code === 'INVALID_CREDENTIALS') {
    return 'Empresa, usuario o contraseña incorrectos.'
  }
  if (err.status === 429) {
    return 'Demasiados intentos de acceso. Espera unos segundos e inténtalo de nuevo.'
  }
  if (err.status >= 500) {
    return 'El servidor devolvió un error. Comprueba que la API esté en marcha y revisa los logs del servidor.'
  }
  if (err instanceof TypeError && typeof err.message === 'string' && /fetch|network/i.test(err.message)) {
    return 'No hay conexión con la API. Comprueba la URL (VITE_API_BASE_URL) y que el servidor esté activo.'
  }
  if (err.message) {
    return err.message
  }
  return 'No se pudo iniciar sesión.'
}

export default function LoginForm() {
  const [tenantCodigo, setTenantCodigo] = useState('DEFAULT')
  const [usuario, setUsuario] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [selection, setSelection] = useState(null)
  const [selectedTenantCodigo, setSelectedTenantCodigo] = useState('')
  /** Solo en Vite `development`: último informe JSON para depuración. */
  const [devErrorReport, setDevErrorReport] = useState(null)

  function useDemoAdmin() {
    setTenantCodigo('DEFAULT')
    setUsuario('admin_demo')
    setPassword('Admin123!')
  }

  function useDemoUser() {
    setTenantCodigo('DEFAULT')
    setUsuario('user_demo')
    setPassword('User123!')
  }

  function useDemoPruebasAdmin() {
    setTenantCodigo('PRUEBAS')
    setUsuario('admin_pruebas')
    setPassword('AdminPruebas123!')
  }

  function useDemoPruebasUser() {
    setTenantCodigo('PRUEBAS')
    setUsuario('user_pruebas1')
    setPassword('UserPruebas123!')
  }

  function useDemoPrueba1Admin() {
    setTenantCodigo('PRUEBA1')
    setUsuario('admin_prueba1')
    setPassword('AdminPrueba1!')
  }

  function useDemoPrueba1User() {
    setTenantCodigo('PRUEBA1')
    setUsuario('user_prueba1')
    setPassword('UserPrueba1!')
  }

  function useDemoManager() {
    setTenantCodigo('DEFAULT')
    setUsuario('manager_demo')
    setPassword('Manager123!')
  }

  function useDemoMultiDefault() {
    setTenantCodigo('DEFAULT')
    setUsuario('multi_demo')
    setPassword('MultiDemo123!')
  }

  function useDemoMultiPrueba1() {
    setTenantCodigo('PRUEBA1')
    setUsuario('multi_demo')
    setPassword('MultiDemo123!')
  }

  /** Login global (sin tenant): usuario con varias empresas → paso de selección. */
  function useDemoGlobalMulti() {
    setTenantCodigo('')
    setUsuario('multi_demo')
    setPassword('MultiDemo123!')
  }

  async function handleSubmit(e) {
    e.preventDefault()
    const tenant = tenantCodigo.trim()
    const user = usuario.trim()
    if (!user || !password) {
      setError('Completa usuario y contrasena.')
      return
    }

    setError(null)
    setDevErrorReport(null)
    setSelection(null)
    setSelectedTenantCodigo('')
    setSubmitting(true)
    try {
      const result = await login(
        tenant === ''
          ? { usuario: user, password }
          : { tenant_codigo: tenant, usuario: user, password },
      )
      if (result?.needsTenantSelection) {
        setSelection({
          token: result.selection_token,
          tenants: result.tenants,
        })
        const first = result.tenants?.[0]?.codigo
        if (first) setSelectedTenantCodigo(first)
      }
    } catch (err) {
      setError(mapLoginError(err))
      if (isViteDevelopment) {
        setDevErrorReport(
          buildAuthErrorDebugReport(err, {
            flow: 'POST /auth/login (+ GET /auth/me si llegó tokens)',
            tenantEnviado: tenant === '' ? '(vacío → login global)' : tenant,
            usuario: user,
          }),
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  async function handleSelectTenant(e) {
    e.preventDefault()
    if (!selection?.token || !selectedTenantCodigo) {
      setError('Elige una empresa.')
      return
    }
    setError(null)
    setDevErrorReport(null)
    setSubmitting(true)
    try {
      await selectLoginTenant({
        selection_token: selection.token,
        tenant_codigo: selectedTenantCodigo,
      })
      setSelection(null)
    } catch (err) {
      setError(mapLoginError(err))
      if (isViteDevelopment) {
        setDevErrorReport(
          buildAuthErrorDebugReport(err, {
            flow: 'POST /auth/login/select-tenant (+ GET /auth/me)',
            tenantElegido: selectedTenantCodigo,
            usuario: usuario.trim() || '(no cambiado en formulario)',
          }),
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="dash-login-card">
      <h2 className="dash-login-card__title" id="login-heading">
        Acceso al panel
      </h2>
      <p className="dash-login-card__hint">
        Acceso seguro al sistema. Los botones rellenan empresa, usuario y contraseña según los datos demo del API (
        <code className="dash-login-dev-hint__mono">php artisan app:setup-demo</code>
        ).
      </p>
      {isViteDevelopment && (
        <details className="dash-login-dev-hint">
          <summary>Referencia rápida — empresa / usuario / contraseña (solo entorno dev)</summary>
          <p style={{ margin: '0.5rem 0 0', lineHeight: 1.45 }}>
            API base del front:{' '}
            <span className="dash-login-dev-hint__mono">{getApiBaseUrl()}</span>
            . Si el login falla, confirma que la API esté en marcha y que CORS permita el origen de Vite.
          </p>
          <div className="dash-login-dev-hint__table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Empresa</th>
                  <th>Usuario</th>
                  <th>Contraseña</th>
                </tr>
              </thead>
              <tbody>
                {DEMO_CREDENTIAL_ROWS.map((row) => (
                  <tr key={`${row.tenant}-${row.usuario}`}>
                    <td>{row.tenant}</td>
                    <td>
                      <code>{row.usuario}</code>
                    </td>
                    <td>
                      <code>{row.password}</code>
                      {row.note ? <div style={{ marginTop: '0.25rem', opacity: 0.9 }}>{row.note}</div> : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </details>
      )}
      <div className="dash-form__actions dash-form__actions--row dash-login-shortcuts">
        <Button type="button" variant="ghost" onClick={useDemoAdmin}>
          DEFAULT · admin
        </Button>
        <Button type="button" variant="ghost" onClick={useDemoUser}>
          DEFAULT · usuario
        </Button>
        <Button type="button" variant="ghost" onClick={useDemoManager}>
          DEFAULT · manager
        </Button>
        <Button type="button" variant="ghost" onClick={useDemoPrueba1Admin}>
          PRUEBA1 · admin
        </Button>
        <Button type="button" variant="ghost" onClick={useDemoPrueba1User}>
          PRUEBA1 · usuario
        </Button>
        <Button type="button" variant="ghost" onClick={useDemoPruebasAdmin}>
          PRUEBAS · admin
        </Button>
        <Button type="button" variant="ghost" onClick={useDemoPruebasUser}>
          PRUEBAS · usuario
        </Button>
        <Button type="button" variant="ghost" onClick={useDemoMultiDefault}>
          multi · DEFAULT
        </Button>
        <Button type="button" variant="ghost" onClick={useDemoMultiPrueba1}>
          multi · PRUEBA1
        </Button>
        <Button type="button" variant="ghost" onClick={useDemoGlobalMulti}>
          global · multi_demo
        </Button>
      </div>

      {error && (
        <div className="dash-alert dash-alert--error" role="alert">
          {error}
        </div>
      )}

      {isViteDevelopment && devErrorReport && (
        <details className="dash-login-error-debug" open>
          <summary>Diagnóstico extendido (solo desarrollo)</summary>
          <p className="dash-login-error-debug__lead">
            Copia este bloque en un issue o chat de depuración. No incluye tu contraseña; los tokens en el cuerpo de
            respuesta aparecen redactados.
          </p>
          <pre className="dash-login-error-debug__pre">{JSON.stringify(devErrorReport, null, 2)}</pre>
        </details>
      )}

      <form className="dash-form" onSubmit={handleSubmit}>
        <Field label="Código de empresa (opcional)">
          <TextInput
            type="text"
            autoComplete="organization"
            value={tenantCodigo}
            onChange={(e) => setTenantCodigo(e.target.value)}
            placeholder="Vacío = login global; o DEFAULT, PRUEBA1, PRUEBAS…"
          />
        </Field>
        <Field label="Usuario">
          <TextInput
            type="text"
            autoComplete="username"
            value={usuario}
            onChange={(e) => setUsuario(e.target.value)}
            required
            placeholder="admin"
          />
        </Field>
        <Field label="Contrasena">
          <TextInput
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            placeholder="••••••••"
          />
        </Field>

        <Button type="submit" variant="primary" block loading={submitting} disabled={!!selection}>
          {submitting ? 'Entrando…' : 'Entrar'}
        </Button>
      </form>

      {selection && (
        <form className="dash-form" style={{ marginTop: '1.25rem' }} onSubmit={handleSelectTenant}>
          <h3 className="dash-login-card__title" style={{ fontSize: '1.1rem' }}>
            Elegir empresa
          </h3>
          <p className="dash-login-card__hint">Tu usuario tiene acceso a más de una empresa.</p>
          <Field label="Empresa">
            <SelectInput
              value={selectedTenantCodigo}
              onChange={(e) => setSelectedTenantCodigo(e.target.value)}
              required
              aria-label="Empresa"
            >
              {selection.tenants.map((t) => (
                <option key={t.id} value={t.codigo}>
                  {t.codigo}
                  {t.nombre ? ` — ${t.nombre}` : ''}
                </option>
              ))}
            </SelectInput>
          </Field>
          <Button type="submit" variant="primary" block loading={submitting}>
            {submitting ? 'Entrando…' : 'Continuar en esta empresa'}
          </Button>
        </form>
      )}
    </div>
  )
}
