import { Fragment, useCallback, useEffect, useMemo, useState } from 'react'
import {
  buildPlatformTenantCreateDebugReport,
  createPlatformTenant,
  createPlatformTenantInitialAdmin,
  getPlatformTenants,
  inactivatePlatformTenant,
  reactivatePlatformTenant,
  updatePlatformTenantCompany,
  updatePlatformTenantSubscription,
} from '../api/client.js'
import { DEBUG_UI_ENABLED } from '../utils/debugUi.js'
import { useToast } from '../context/ToastContext.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import { Field, TextInput, SelectInput } from '../components/ui/Field.jsx'
import { InlineAlert, LoadingState } from '../components/ui/feedback.jsx'
import { mapApiError } from '../utils/apiError.js'
import { inferTipoContribuyenteFromRfc, validateRfcMx } from '../utils/rfcMx.js'
import {
  getRegimenOptionsForTipo,
  mapSatRegimenTextsToCodes,
} from '../utils/regimenesFiscalCatalog.js'
import { decodeSatQrFromFile } from '../utils/qrSat.js'
import { fetchSatValidadorHtml, parseSatUrl, parseSatValidadorHtml } from '../utils/satValidador.js'
import { detectSatQrUrlFromPdf, extractSatDataFromPdf } from '../utils/satConstanciaPdf.js'

const tenantFormDefaults = () => ({
  nombre: '',
  codigo: '',
  activo: true,
  tipo_contribuyente: '',
  origen_datos: '',

  // Referencias de origen (solo UX)
  sat_qr_url: '',

  // Fiscal
  regimen_fiscal_principal: '',

  // Comunes
  rfc: '',

  // PF
  curp: '',
  pf_nombre: '',
  pf_primer_apellido: '',
  pf_segundo_apellido: '',

  // PM
  nombre_fiscal: '',

  // Domicilio registrado (PM)
  codigo_postal: '',
  tipo_vialidad: '',
  calle: '',
  numero_exterior: '',
  numero_interior: '',
  colonia: '',
  localidad: '',
  municipio: '',
  estado: '',
  correo_electronico: '',

  sat_locked: false,
  regimen_sat_codes: [],
  regimen_ui_locked: false,
  regimenes_options: [],
})

const adminFormDefaults = () => ({
  tenant_codigo: '',
  admin_usuario: '',
  admin_password: '',
  admin_password_confirmation: '',
  admin_codigo_cliente: '',
})

function normalizeCodigo(value) {
  return String(value || '').trim()
}

/** Código de empresa = RFC (misma cadena normalizada que envía el core al preparar RFC). */
function normalizeRfcAsCodigo(rfcRaw) {
  return String(rfcRaw || '').trim().toUpperCase()
}

/** Nombre de empresa en UI/payload: derivado solo de datos fiscales (PF: nombre + apellidos; PM: razón social). */
function deriveEmpresaNombreFromFiscal(form) {
  const tipo = form.tipo_contribuyente || inferTipoContribuyenteFromRfc(form.rfc)
  if (tipo === 'persona_moral') {
    return String(form.nombre_fiscal || '')
      .trim()
      .replace(/\s+/g, ' ')
  }
  if (tipo === 'persona_fisica') {
    const parts = [form.pf_nombre, form.pf_primer_apellido, form.pf_segundo_apellido]
      .map((x) => String(x || '').trim())
      .filter(Boolean)
    return parts.join(' ').replace(/\s+/g, ' ').trim()
  }
  return ''
}

function platformFriendlyError(err, fallback) {
  if (err?.code === 'VALIDATION_ERROR' && err?.body?.data?.errors) {
    const errors = err.body.data.errors
    if (errors.codigo?.length) return 'Ese código de empresa ya existe. Usa otro.'
    if (errors.admin_usuario?.length) return 'Ese usuario ya existe. Usa otro.'
    const firstKey = Object.keys(errors)[0]
    if (firstKey && Array.isArray(errors[firstKey]) && errors[firstKey][0]) return String(errors[firstKey][0])
  }
  return mapApiError(err, fallback)
}

/** Payload que se envía a POST /platform/tenants (misma forma que handleCreateTenant). */
function buildTenantCreatePayload(form) {
  const rfcNorm = normalizeRfcAsCodigo(form.rfc)
  const nombreDer = deriveEmpresaNombreFromFiscal({ ...form, rfc: rfcNorm })
  return {
    nombre: nombreDer,
    codigo: rfcNorm,
    activo: !!form.activo,
    origen_datos: form.origen_datos,
    tipo_contribuyente: form.tipo_contribuyente,
    rfc: rfcNorm || null,
    curp: form.curp.trim() || null,
    pf_nombre: form.pf_nombre.trim() || null,
    pf_primer_apellido: form.pf_primer_apellido.trim() || null,
    pf_segundo_apellido: form.pf_segundo_apellido.trim() || null,
    nombre_fiscal: form.nombre_fiscal.trim() || null,
    regimen_fiscal_principal: form.regimen_fiscal_principal.trim() || null,
    codigo_postal: form.codigo_postal.trim() || null,
    tipo_vialidad: form.tipo_vialidad?.trim() || null,
    calle: form.calle.trim() || null,
    numero_exterior: form.numero_exterior.trim() || null,
    numero_interior: form.numero_interior.trim() || null,
    colonia: form.colonia.trim() || null,
    localidad: form.localidad.trim() || null,
    municipio: form.municipio.trim() || null,
    estado: form.estado.trim() || null,
    correo_electronico: form.correo_electronico.trim() || null,
  }
}

function collectTenantCreateBlockers({
  tenantForm,
  regimenPasoCompleto,
  rfcPasoCompleto,
  inferredTipo,
  tenantLoading,
  canCreateTenant,
}) {
  const list = []
  if (tenantLoading) list.push('Petición en curso (no reenviar hasta terminar).')
  if (!tenantForm.origen_datos?.trim()) list.push('origen_datos: vacío (elige origen al inicio).')
  if (!tenantForm.rfc?.trim()) list.push('RFC: vacío.')
  if (!rfcPasoCompleto) {
    const v = validateRfcMx(tenantForm.rfc, tenantForm.tipo_contribuyente || inferredTipo)
    list.push(
      `RFC no pasa validación en navegador: ${v.error || 'formato o tipo no resuelto'} (tipo_estado=${tenantForm.tipo_contribuyente || '—'}, inferido=${inferredTipo || '—'}).`,
    )
  }
  if (!regimenPasoCompleto) list.push('regimen_fiscal_principal: no elegido.')
  if (!tenantForm.nombre?.trim()) list.push('nombre (empresa): vacío.')
  if (!tenantForm.codigo?.trim()) list.push('codigo (empresa): vacío.')
  if (!tenantForm.tipo_contribuyente) list.push('tipo_contribuyente: vacío (debe alinearse con el RFC).')
  if (tenantForm.tipo_contribuyente === 'persona_moral' && !tenantForm.nombre_fiscal?.trim()) {
    list.push('Persona moral: nombre_fiscal vacío — starter-core devolverá 422 (CreateTenantRequest).')
  }
  if (tenantForm.tipo_contribuyente === 'persona_fisica') {
    if (!tenantForm.curp?.trim()) list.push('Persona física: CURP vacío — 422 en API si envías así.')
    if (!tenantForm.pf_nombre?.trim()) list.push('Persona física: nombre(s) vacío — 422 en API.')
    if (!tenantForm.municipio?.trim()) list.push('Persona física: municipio vacío — 422 en API.')
  }
  if (!tenantForm.codigo_postal?.trim()) list.push('codigo_postal vacío — 422 en API (requerido en core).')
  if (!tenantForm.estado?.trim()) list.push('estado (entidad federativa) vacío — 422 en API.')
  if (canCreateTenant && list.length === 0) {
    list.push('Condiciones mínimas del botón cumplidas; si falla al enviar, el error vendrá del API (ver capa_probable).')
  }
  return list
}

function formatDateShort(iso) {
  if (!iso || typeof iso !== 'string') return null
  const s = iso.trim()
  if (s.length < 10) return null
  return s.slice(0, 10)
}

function formatOperationalStatus(st) {
  if (st === 'active') return 'Activa'
  if (st === 'inactive') return 'Inactiva'
  if (st === 'expired') return 'Expirada'
  return st ? String(st) : '—'
}

function editTenantFormDefaults() {
  return {
    correo_electronico: '',
    codigo_postal: '',
    tipo_vialidad: '',
    calle: '',
    numero_exterior: '',
    numero_interior: '',
    colonia: '',
    localidad: '',
    municipio: '',
    estado: '',
  }
}

/** Hidrata el formulario de edición desde un ítem del listado de plataforma (PATCH mismos campos). */
function tenantListItemToEditForm(t) {
  const str = (v) => (v == null || v === '' ? '' : String(v))
  return {
    correo_electronico: str(t.correo_electronico),
    codigo_postal: str(t.codigo_postal),
    tipo_vialidad: str(t.tipo_vialidad),
    calle: str(t.calle),
    numero_exterior: str(t.numero_exterior),
    numero_interior: str(t.numero_interior),
    colonia: str(t.colonia),
    localidad: str(t.localidad),
    municipio: str(t.municipio),
    estado: str(t.estado),
  }
}

/** Solo campos con valor; PATCH parcial al backend. */
function buildTenantEditPayload(form) {
  const keys = [
    'correo_electronico',
    'codigo_postal',
    'tipo_vialidad',
    'calle',
    'numero_exterior',
    'numero_interior',
    'colonia',
    'localidad',
    'municipio',
    'estado',
  ]
  const out = {}
  for (const k of keys) {
    const v = form[k]
    if (typeof v === 'string' && v.trim() !== '') out[k] = v.trim()
  }
  return out
}

function formatInitialAdminCell(t) {
  const a = t?.initial_admin
  if (!a || typeof a !== 'object' || !a.usuario) {
    return null
  }
  return {
    usuario: String(a.usuario),
    codigo_cliente: a.codigo_cliente != null && String(a.codigo_cliente).trim() !== '' ? String(a.codigo_cliente) : null,
    activo: a.activo !== false,
  }
}

const INLINE_ALERT_DISMISS_MS = 4000

function formatSubscriptionStatus(status, trialEndsAtIso) {
  if (!status) return '—'
  if (status === 'trial') {
    const ends = trialEndsAtIso ? new Date(trialEndsAtIso) : null
    if (ends && !Number.isNaN(ends.getTime()) && ends.getTime() <= Date.now()) return 'Vencida (trial)'
    return 'Prueba (trial)'
  }
  if (status === 'active') return 'Activa'
  if (status === 'expired') return 'Vencida'
  if (status === 'suspended') return 'Suspendida'
  return String(status)
}

export default function PlatformAdminPage({ user }) {
  const { showToast } = useToast()

  const [tenants, setTenants] = useState([])
  const [tenantsLoading, setTenantsLoading] = useState(false)
  const [tenantsError, setTenantsError] = useState(null)
  /** 'active' = API sin inactivas; 'inactive' = API con inactivas + filtro cliente solo operational inactive. */
  const [operationalFilter, setOperationalFilter] = useState('active')

  const [tenantForm, setTenantForm] = useState(() => tenantFormDefaults())
  const [tenantLoading, setTenantLoading] = useState(false)
  const [tenantError, setTenantError] = useState(null)
  const [tenantSuccess, setTenantSuccess] = useState(null)
  const [tenantWarning, setTenantWarning] = useState(null)
  const [tenantAutofillSource, setTenantAutofillSource] = useState(null)

  const [adminForm, setAdminForm] = useState(() => adminFormDefaults())
  const [adminLoading, setAdminLoading] = useState(false)
  const [adminError, setAdminError] = useState(null)
  const [adminSuccess, setAdminSuccess] = useState(null)

  const [subscriptionSaving, setSubscriptionSaving] = useState(null)

  const [tenantOperationalSaving, setTenantOperationalSaving] = useState(null)
  const [editingTenantCodigo, setEditingTenantCodigo] = useState(null)
  const [editTenantForm, setEditTenantForm] = useState(() => editTenantFormDefaults())

  const [tenantCreateDiagOpen, setTenantCreateDiagOpen] = useState(false)
  const [tenantCreateDiagText, setTenantCreateDiagText] = useState('')

  function openTenantCreateDiagnostic(err, opts = {}) {
    if (!DEBUG_UI_ENABLED) return
    const inferred = inferTipoContribuyenteFromRfc(tenantForm.rfc)
    const rfcCheck = validateRfcMx(tenantForm.rfc, tenantForm.tipo_contribuyente || inferred)
    const blockers = collectTenantCreateBlockers({
      tenantForm,
      regimenPasoCompleto,
      rfcPasoCompleto,
      inferredTipo: inferred,
      tenantLoading,
      canCreateTenant,
    })
    const report = buildPlatformTenantCreateDebugReport(err, {
      formSnapshot: { ...tenantForm },
      formBlockers: blockers,
      payloadPreview: buildTenantCreatePayload(tenantForm),
      rfcDiagnostic: {
        tipo_inferido_desde_rfc: inferred,
        tipo_en_estado: tenantForm.tipo_contribuyente,
        validateRfcMx: {
          ok: rfcCheck.ok,
          error: rfcCheck.error ?? null,
          warning: rfcCheck.warning ?? null,
          normalized: rfcCheck.normalized ?? null,
        },
      },
      clientOnlyMessage: opts.clientOnlyMessage,
      usuarioPlataforma: user
        ? { usuario: user.usuario, is_platform_admin: !!user.is_platform_admin }
        : null,
    })
    setTenantCreateDiagText(JSON.stringify(report, null, 2))
    setTenantCreateDiagOpen(true)
  }

  async function copyTenantCreateDiagnostic() {
    if (!DEBUG_UI_ENABLED) return
    try {
      await navigator.clipboard.writeText(tenantCreateDiagText)
      showToast('Diagnóstico copiado al portapapeles.', 'success')
    } catch {
      showToast('No se pudo copiar. Selecciona el texto manualmente.', 'error')
    }
  }

  const refreshTenants = useCallback(async () => {
    setTenantsError(null)
    setTenantsLoading(true)
    try {
      const includeInactive = operationalFilter === 'inactive'
      const res = await getPlatformTenants({ include_inactive: includeInactive })
      let items = Array.isArray(res?.items) ? res.items : []
      if (operationalFilter === 'inactive') {
        items = items.filter((t) => (t.operational_status ?? 'active') === 'inactive')
      }
      setTenants(items)
    } catch (err) {
      setTenantsError(mapApiError(err, 'No se pudo cargar el listado de empresas.'))
      setTenants([])
    } finally {
      setTenantsLoading(false)
    }
  }, [operationalFilter])

  useEffect(() => {
    refreshTenants()
  }, [refreshTenants])

  useEffect(() => {
    if (tenantError === null && tenantSuccess === null && tenantWarning === null) return
    const id = window.setTimeout(() => {
      setTenantError(null)
      setTenantSuccess(null)
      setTenantWarning(null)
      setTenantAutofillSource(null)
    }, INLINE_ALERT_DISMISS_MS)
    return () => window.clearTimeout(id)
  }, [tenantError, tenantSuccess, tenantWarning])

  useEffect(() => {
    if (adminError === null && adminSuccess === null) return
    const id = window.setTimeout(() => {
      setAdminError(null)
      setAdminSuccess(null)
    }, INLINE_ALERT_DISMISS_MS)
    return () => window.clearTimeout(id)
  }, [adminError, adminSuccess])

  useEffect(() => {
    if (tenantsError === null) return
    const id = window.setTimeout(() => {
      setTenantsError(null)
    }, INLINE_ALERT_DISMISS_MS)
    return () => window.clearTimeout(id)
  }, [tenantsError])

  useEffect(() => {
    const t = inferTipoContribuyenteFromRfc(tenantForm.rfc)
    if (!t) return
    setTenantForm((f) => {
      if (f.tipo_contribuyente === t) return f
      const opts = getRegimenOptionsForTipo(t).map((o) => o.value)
      const keepReg = f.regimen_fiscal_principal && opts.includes(f.regimen_fiscal_principal)
      return {
        ...f,
        tipo_contribuyente: t,
        regimen_fiscal_principal: keepReg ? f.regimen_fiscal_principal : '',
        regimen_sat_codes: [],
        regimen_ui_locked: false,
      }
    })
  }, [tenantForm.rfc])

  useEffect(() => {
    setTenantForm((f) => {
      const nextCodigo = normalizeRfcAsCodigo(f.rfc)
      const nextNombre = deriveEmpresaNombreFromFiscal(f)
      if (f.codigo === nextCodigo && f.nombre === nextNombre) return f
      return { ...f, codigo: nextCodigo, nombre: nextNombre }
    })
  }, [
    tenantForm.rfc,
    tenantForm.tipo_contribuyente,
    tenantForm.pf_nombre,
    tenantForm.pf_primer_apellido,
    tenantForm.pf_segundo_apellido,
    tenantForm.nombre_fiscal,
  ])

  const regimenOpciones = useMemo(() => {
    const full = getRegimenOptionsForTipo(tenantForm.tipo_contribuyente)
    const filt = tenantForm.regimen_sat_codes
    if (Array.isArray(filt) && filt.length > 1) {
      return full.filter((o) => filt.includes(o.value))
    }
    return full
  }, [tenantForm.tipo_contribuyente, tenantForm.regimen_sat_codes])

  const regimenSelectDisabled =
    tenantForm.regimen_ui_locked && Array.isArray(tenantForm.regimen_sat_codes) && tenantForm.regimen_sat_codes.length === 1

  const tenantCodigoOptions = useMemo(() => {
    return tenants
      .slice()
      .sort((a, b) => String(a.codigo).localeCompare(String(b.codigo)))
      .map((t) => ({
        codigo: String(t.codigo),
        label: `${t.codigo} — ${t.nombre}${t.activo ? '' : ' (inactiva)'}`,
      }))
  }, [tenants])

  const selectedTenantCodigo = normalizeCodigo(adminForm.tenant_codigo)
  const selectedTenant = useMemo(
    () => tenants.find((t) => normalizeCodigo(t.codigo) === selectedTenantCodigo) || null,
    [tenants, selectedTenantCodigo],
  )

  const inferredTipo = useMemo(
    () => inferTipoContribuyenteFromRfc(tenantForm.rfc),
    [tenantForm.rfc],
  )

  const rfcPasoCompleto = useMemo(() => {
    if (!tenantForm.rfc?.trim() || !inferredTipo) return false
    return validateRfcMx(tenantForm.rfc, inferredTipo).ok
  }, [tenantForm.rfc, inferredTipo])

  const regimenPasoCompleto = useMemo(
    () => Boolean(tenantForm.regimen_fiscal_principal?.trim()),
    [tenantForm.regimen_fiscal_principal],
  )

  const canCreateTenant = useMemo(
    () =>
      regimenPasoCompleto &&
      tenantForm.nombre.trim() !== '' &&
      tenantForm.codigo.trim() !== '' &&
      tenantForm.tipo_contribuyente !== '' &&
      tenantForm.origen_datos !== '' &&
      tenantForm.rfc.trim() !== '' &&
      !tenantLoading,
    [tenantForm, tenantLoading, regimenPasoCompleto],
  )
  const canCreateAdmin = useMemo(
    () =>
      !tenantsLoading &&
      selectedTenantCodigo !== '' &&
      !!selectedTenant &&
      adminForm.admin_usuario.trim() !== '' &&
      adminForm.admin_password !== '' &&
      !adminLoading,
    [adminForm, adminLoading, selectedTenant, selectedTenantCodigo, tenantsLoading],
  )

  async function handleCreateTenant(e) {
    e.preventDefault()
    setTenantError(null)
    setTenantSuccess(null)
    setTenantWarning(null)
    setAdminError(null)
    setAdminSuccess(null)

    const rfcCheck = validateRfcMx(tenantForm.rfc, tenantForm.tipo_contribuyente)
    if (!rfcCheck.ok) {
      const msg = rfcCheck.error || 'RFC inválido.'
      setTenantError(msg)
      openTenantCreateDiagnostic(null, { clientOnlyMessage: msg })
      return
    }
    if (rfcCheck.warning) {
      setTenantWarning(rfcCheck.warning)
    }

    setTenantLoading(true)
    try {
      const res = await createPlatformTenant(buildTenantCreatePayload(tenantForm))

      const createdCodigo = res?.data?.codigo || res?.codigo || tenantForm.codigo.trim()
      setTenantSuccess(`Empresa creada correctamente: ${createdCodigo}`)
      showToast('Empresa creada correctamente.', 'success')

      await refreshTenants()

      setTenantForm(tenantFormDefaults())
      setAdminForm((f) => ({
        ...adminFormDefaults(),
        tenant_codigo: createdCodigo,
      }))
    } catch (err) {
      const msg = platformFriendlyError(err, 'No se pudo crear la empresa.')
      setTenantError(msg)
      showToast(msg, 'error')
      openTenantCreateDiagnostic(err)
    } finally {
      setTenantLoading(false)
    }
  }

  function applyRegimenFromParsed(html, tipoResolved) {
    const regimenTexts = Array.isArray(html?.regimenes) ? html.regimenes : []
    const tipo = tipoResolved || inferTipoContribuyenteFromRfc(html?.rfc || '')
    if (!tipo) {
      return { regimen_sat_codes: [], regimen_fiscal_principal: '', regimen_ui_locked: false }
    }
    if (regimenTexts.length === 0) {
      return { regimen_sat_codes: [], regimen_fiscal_principal: '', regimen_ui_locked: false }
    }
    const codes = mapSatRegimenTextsToCodes(regimenTexts, tipo)
    if (codes.length === 1) {
      return { regimen_sat_codes: codes, regimen_fiscal_principal: codes[0], regimen_ui_locked: true }
    }
    if (codes.length > 1) {
      return { regimen_sat_codes: codes, regimen_fiscal_principal: '', regimen_ui_locked: false }
    }
    return { regimen_sat_codes: [], regimen_fiscal_principal: '', regimen_ui_locked: false }
  }

  function applySatAutofill({ satUrl, parsedUrl, parsedHtml, source }) {
    const rfcFromUrl = parsedUrl?.rfc || null
    const html = parsedHtml || null
    const rfc = html?.rfc || rfcFromUrl
    const did = Boolean(rfc)
    setTenantForm((f) => {
      const next = { ...f }
      if (satUrl && satUrl.trim() && satUrl.trim() !== f.sat_qr_url) {
        next.sat_qr_url = satUrl.trim()
      }
      if (rfc) {
        next.rfc = rfc
      }
      const tipo = inferTipoContribuyenteFromRfc(rfc || '')
      if (tipo) next.tipo_contribuyente = tipo
      if (html?.curp) next.curp = html.curp
      if (html?.nombre) next.pf_nombre = html.nombre
      if (html?.primerApellido) next.pf_primer_apellido = html.primerApellido
      if (html?.segundoApellido) next.pf_segundo_apellido = html.segundoApellido
      if (html?.razonSocial) next.nombre_fiscal = html.razonSocial
      if (html?.codigoPostal) next.codigo_postal = html.codigoPostal
      if (html?.tipoVialidad) next.tipo_vialidad = html.tipoVialidad
      if (html?.nombreVialidad) next.calle = html.nombreVialidad
      if (html?.numeroExterior) next.numero_exterior = html.numeroExterior
      if (html?.numeroInterior != null) next.numero_interior = html.numeroInterior
      if (html?.colonia) next.colonia = html.colonia
      if (html?.localidad) next.localidad = html.localidad
      if (html?.municipio) next.municipio = html.municipio
      if (html?.estado) next.estado = html.estado
      if (html?.correoElectronico) next.correo_electronico = html.correoElectronico

      const reg = applyRegimenFromParsed(html, tipo)
      next.regimen_sat_codes = reg.regimen_sat_codes
      next.regimen_ui_locked = reg.regimen_ui_locked
      if (reg.regimen_fiscal_principal) {
        next.regimen_fiscal_principal = reg.regimen_fiscal_principal
      } else if (reg.regimen_sat_codes.length === 0) {
        next.regimen_fiscal_principal = ''
      }

      if (did) {
        next.sat_locked = true
        if (source === 'sat_url') next.origen_datos = 'sat_url'
        if (source === 'pdf') next.origen_datos = 'pdf'
        if (source === 'imagen_qr') next.origen_datos = 'imagen_qr'
      }
      next.codigo = normalizeRfcAsCodigo(next.rfc)
      next.nombre = deriveEmpresaNombreFromFiscal(next)
      return next
    })
    if (did && source) setTenantAutofillSource(source)
    return did
  }

  function hasUsefulSatHtml(parsedHtml) {
    if (!parsedHtml || typeof parsedHtml !== 'object') return false
    const keys = [
      'rfc',
      'curp',
      'nombre',
      'primerApellido',
      'segundoApellido',
      'razonSocial',
      'codigoPostal',
      'tipoVialidad',
      'nombreVialidad',
      'numeroExterior',
      'numeroInterior',
      'colonia',
      'localidad',
      'municipio',
      'estado',
      'correoElectronico',
    ]
    if (keys.some((k) => String(parsedHtml[k] || '').trim() !== '')) return true
    return Array.isArray(parsedHtml.regimenes) && parsedHtml.regimenes.length > 0
  }

  async function trySatHtmlFromUrl(satUrl) {
    const url = String(satUrl || '').trim()
    if (!url) return null
    try {
      const htmlRaw = await fetchSatValidadorHtml(url)
      if (!htmlRaw) return null
      const parsedHtml = parseSatValidadorHtml(htmlRaw)
      return hasUsefulSatHtml(parsedHtml) ? parsedHtml : null
    } catch {
      return null
    }
  }

  function handleSatAutofill() {
    setTenantError(null)
    setTenantWarning(null)
    ;(async () => {
      const parsedUrl = parseSatUrl(tenantForm.sat_qr_url)
      const parsedHtml = await trySatHtmlFromUrl(parsedUrl.satUrl)

      const did = applySatAutofill({
        satUrl: parsedUrl.satUrl || tenantForm.sat_qr_url,
        parsedUrl,
        parsedHtml,
        source: parsedHtml ? 'html_sat' : 'sat_url',
      })
      if (!did) {
        setTenantWarning('No se pudo autollenar RFC desde SAT. Se cambia a captura manual.')
        setTenantForm((f) => ({ ...f, origen_datos: 'manual', sat_locked: false }))
        setTenantAutofillSource('manual')
        return
      }
      showToast('Autollenado aplicado desde URL/QR del SAT.', 'success')
    })()
  }

  async function handleConstanciaUpload(file) {
    if (!file) return
    setTenantError(null)
    setTenantWarning(null)

    const type = String(file.type || '').toLowerCase()
    const isJpg = type === 'image/jpeg' || type === 'image/jpg'
    const isPdf = type === 'application/pdf'
    const expectingPdf = tenantForm.origen_datos === 'pdf'
    const expectingImage = tenantForm.origen_datos === 'imagen_qr'

    if (expectingPdf && !isPdf) {
      setTenantError('Origen PDF: el archivo debe ser PDF.')
      return
    }
    if (expectingImage && !isJpg) {
      setTenantError('Origen imagen con QR: el archivo debe ser JPG/JPEG.')
      return
    }

    if (isJpg) {
      try {
        const qrText = await decodeSatQrFromFile(file)
        if (qrText) {
          const parsedUrl = parseSatUrl(qrText)
          const parsedHtml = await trySatHtmlFromUrl(parsedUrl.satUrl || qrText)
          const did = applySatAutofill({
            satUrl: parsedUrl.satUrl || qrText,
            parsedUrl,
            parsedHtml,
            source: parsedHtml ? 'html_sat' : 'imagen_qr',
          })
          if (did) {
            showToast(parsedHtml ? 'Autollenado aplicado desde HTML SAT (QR imagen).' : 'QR detectado en imagen. Se aplicó autollenado básico.', 'success')
          } else {
            setTenantWarning('Se detectó un QR, pero no se pudo extraer RFC. Se cambia a captura manual.')
            setTenantForm((f) => ({ ...f, origen_datos: 'manual', sat_locked: false }))
            setTenantAutofillSource('manual')
          }
          return
        }
        setTenantWarning('No se detectó QR en la imagen. Se cambia a captura manual.')
        setTenantForm((f) => ({ ...f, origen_datos: 'manual', sat_locked: false }))
        setTenantAutofillSource('manual')
      } catch {
        setTenantWarning('No se pudo leer el QR de la imagen. Se cambia a captura manual.')
        setTenantForm((f) => ({ ...f, origen_datos: 'manual', sat_locked: false }))
        setTenantAutofillSource('manual')
      }
      return
    }

    if (isPdf) {
      try {
        // Prioridad 1: QR detectado dentro de PDF -> URL SAT -> HTML SAT
        const qrUrlFromPdf = await detectSatQrUrlFromPdf(file)
        if (qrUrlFromPdf) {
          const parsedUrl = parseSatUrl(qrUrlFromPdf)
          const parsedHtml = await trySatHtmlFromUrl(parsedUrl.satUrl || qrUrlFromPdf)
          const didFromHtml = applySatAutofill({
            satUrl: parsedUrl.satUrl || qrUrlFromPdf,
            parsedUrl,
            parsedHtml,
            source: parsedHtml ? 'html_sat' : 'pdf_qr_url',
          })
          if (didFromHtml) {
            showToast(parsedHtml ? 'Autollenado aplicado desde HTML SAT (QR PDF).' : 'Autollenado aplicado desde URL SAT del QR en PDF.', 'success')
            return
          }
        }

        // Prioridad 2: fallback texto PDF
        const parsedPdf = await extractSatDataFromPdf(file)
        const parsedUrl = parseSatUrl(parsedPdf?.satUrl || '')
        const did = applySatAutofill({
          satUrl: parsedUrl.satUrl || parsedPdf?.satUrl || '',
          parsedUrl: {
            rfc: parsedPdf?.rfc || parsedUrl.rfc,
          },
          parsedHtml: {
            curp: parsedPdf?.curp || null,
            nombre: parsedPdf?.nombre || null,
            primerApellido: parsedPdf?.primerApellido || null,
            segundoApellido: parsedPdf?.segundoApellido || null,
            razonSocial: parsedPdf?.razonSocial || null,
            codigoPostal: parsedPdf?.codigoPostal || null,
            tipoVialidad: parsedPdf?.tipoVialidad || null,
            nombreVialidad: parsedPdf?.calle || null,
            numeroExterior: parsedPdf?.numeroExterior || null,
            numeroInterior: parsedPdf?.numeroInterior || null,
            colonia: parsedPdf?.colonia || null,
            localidad: parsedPdf?.localidad || null,
            municipio: parsedPdf?.municipio || null,
            estado: parsedPdf?.estado || null,
            correoElectronico: parsedPdf?.correoElectronico || null,
            regimenes: Array.isArray(parsedPdf?.regimenes) ? parsedPdf.regimenes : [],
          },
          source: 'pdf_texto',
        })
        if (did) {
          showToast('Autollenado aplicado desde texto PDF (fallback).', 'success')
        } else {
          setTenantWarning('No se pudo extraer RFC del PDF. Se cambia a captura manual.')
          setTenantForm((f) => ({ ...f, origen_datos: 'manual', sat_locked: false }))
          setTenantAutofillSource('manual')
        }
      } catch {
        setTenantWarning('No se pudo procesar el PDF. Se cambia a captura manual.')
        setTenantForm((f) => ({ ...f, origen_datos: 'manual', sat_locked: false }))
        setTenantAutofillSource('manual')
      }
      return
    }

    setTenantError('Archivo no soportado. Solo se permite PDF o JPG/JPEG.')
    setTenantForm((f) => ({ ...f, origen_datos: 'manual', sat_locked: false }))
  }

  async function handleTrialSubscriptionChange(tenantCodigo, subscription_status) {
    setSubscriptionSaving(tenantCodigo)
    try {
      await updatePlatformTenantSubscription({ tenant_codigo: tenantCodigo, subscription_status })
      showToast('Estado de suscripción actualizado.', 'success')
      await refreshTenants()
    } catch (err) {
      showToast(mapApiError(err, 'No se pudo actualizar la suscripción.'), 'error')
    } finally {
      setSubscriptionSaving(null)
    }
  }

  function rowTenantBusy(codigo) {
    return subscriptionSaving === codigo || tenantOperationalSaving === codigo
  }

  async function handleInactivateTenantOperational(tenantCodigo) {
    if (!window.confirm('¿Inactivar esta empresa de forma operativa? No elimina usuarios ni cambia el acceso por “activo”.')) {
      return
    }
    setTenantOperationalSaving(tenantCodigo)
    try {
      await inactivatePlatformTenant(tenantCodigo)
      showToast('Empresa inactivada operativamente.', 'success')
      if (editingTenantCodigo === tenantCodigo) {
        setEditingTenantCodigo(null)
        setEditTenantForm(editTenantFormDefaults())
      }
      await refreshTenants()
    } catch (err) {
      showToast(mapApiError(err, 'No se pudo inactivar la empresa.'), 'error')
    } finally {
      setTenantOperationalSaving(null)
    }
  }

  async function handleReactivateTenantOperational(tenantCodigo) {
    if (!window.confirm('¿Reactivar esta empresa operativamente?')) {
      return
    }
    setTenantOperationalSaving(tenantCodigo)
    try {
      await reactivatePlatformTenant(tenantCodigo)
      showToast('Empresa reactivada.', 'success')
      await refreshTenants()
    } catch (err) {
      showToast(mapApiError(err, 'No se pudo reactivar la empresa.'), 'error')
    } finally {
      setTenantOperationalSaving(null)
    }
  }

  async function handleEditTenantSubmit(e) {
    e.preventDefault()
    if (!editingTenantCodigo) return
    const payload = buildTenantEditPayload(editTenantForm)
    if (Object.keys(payload).length === 0) {
      showToast('Indica al menos un campo para actualizar.', 'error')
      return
    }
    setTenantOperationalSaving(editingTenantCodigo)
    try {
      await updatePlatformTenantCompany(editingTenantCodigo, payload)
      showToast('Empresa actualizada.', 'success')
      setEditingTenantCodigo(null)
      setEditTenantForm(editTenantFormDefaults())
      await refreshTenants()
    } catch (err) {
      showToast(mapApiError(err, 'No se pudo actualizar la empresa.'), 'error')
    } finally {
      setTenantOperationalSaving(null)
    }
  }

  async function handleCreateInitialAdmin(e) {
    e.preventDefault()
    setAdminError(null)
    setAdminSuccess(null)

    setAdminLoading(true)
    try {
      const res = await createPlatformTenantInitialAdmin({
        tenant_codigo: selectedTenantCodigo,
        admin_usuario: adminForm.admin_usuario.trim(),
        admin_password: adminForm.admin_password,
        admin_password_confirmation: adminForm.admin_password_confirmation,
        admin_codigo_cliente: adminForm.admin_codigo_cliente.trim() || undefined,
      })

      const createdUser = res?.usuario || adminForm.admin_usuario.trim()
      setAdminSuccess(`Admin inicial creado correctamente: ${createdUser}`)
      showToast('Admin inicial creado correctamente.', 'success')
      await refreshTenants()
      setAdminForm(adminFormDefaults())
    } catch (err) {
      const msg = platformFriendlyError(err, 'No se pudo crear el admin inicial.')
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
        <p className="dash-hero__lead">
          Crea empresas, revisa el usuario con rol administrador por empresa y da de alta el admin inicial si falta.
        </p>
      </div>

      <div style={{ marginBottom: '1rem' }}>
        <Card
          title="Empresas existentes"
          subtitle="Suscripción, trial y columna Admin: primer usuario con rol administrador en esa empresa (suele ser el admin inicial)."
        >
          <div className="platform-admin-tenants-toolbar">
            <Button
              type="button"
              variant="soft"
              onClick={refreshTenants}
              disabled={tenantsLoading}
              data-testid="platform-tenants-refresh"
            >
              {tenantsLoading ? 'Cargando...' : 'Actualizar listado'}
            </Button>
            <label className="platform-admin-tenants-toolbar__filter">
              <span className="platform-admin-tenants-toolbar__filter-label">Empresas en listado</span>
              <SelectInput
                aria-label="Filtrar empresas operativas"
                value={operationalFilter}
                onChange={(e) => setOperationalFilter(e.target.value)}
                disabled={tenantsLoading}
                data-testid="platform-tenants-filter-operational"
              >
                <option value="active">Solo activas</option>
                <option value="inactive">Solo inactivas</option>
              </SelectInput>
            </label>
            <span className="platform-admin-tenants-toolbar__total dash-muted">Total: {tenants.length}</span>
          </div>

          {tenantsError && (
            <InlineAlert kind="error" data-testid="platform-tenants-error">
              {tenantsError}
            </InlineAlert>
          )}

          <div
            className="platform-admin-tenants-scroll"
            style={{ overflow: 'auto', maxHeight: '22rem', border: '1px solid var(--border, rgba(255,255,255,.08))', borderRadius: '12px' }}
          >
            <table className="dash-table" style={{ width: '100%', borderCollapse: 'collapse', minWidth: '96rem' }}>
              <thead>
                <tr>
                  <th style={{ textAlign: 'left', padding: '.75rem .9rem', minWidth: '9rem' }}>Código</th>
                  <th style={{ textAlign: 'left', padding: '.75rem .9rem', minWidth: '14rem' }}>Nombre de empresa</th>
                  <th style={{ textAlign: 'left', padding: '.75rem .9rem', minWidth: '8rem' }}>Empresa activa</th>
                  <th style={{ textAlign: 'left', padding: '.75rem .9rem', minWidth: '9rem' }}>Estado operativo</th>
                  <th style={{ textAlign: 'left', padding: '.75rem .9rem', minWidth: '14rem' }}>Admin inicial</th>
                  <th style={{ textAlign: 'left', padding: '.75rem .9rem', minWidth: '9rem' }}>Suscripción</th>
                  <th style={{ textAlign: 'left', padding: '.75rem .9rem', minWidth: '8rem' }}>Trial inicia</th>
                  <th style={{ textAlign: 'left', padding: '.75rem .9rem', minWidth: '8rem' }}>Trial termina</th>
                  <th style={{ textAlign: 'left', padding: '.75rem .9rem', minWidth: '11rem' }}>Acciones (trial)</th>
                  <th style={{ textAlign: 'left', padding: '.75rem .9rem', minWidth: '12rem' }}>Acciones operativas</th>
                </tr>
              </thead>
              <tbody>
                {tenants.length === 0 && !tenantsLoading ? (
                  <tr>
                    <td colSpan={10} style={{ padding: '.75rem' }}>
                      <span className="dash-muted">Sin empresas para mostrar.</span>
                    </td>
                  </tr>
                ) : (
                  tenants.map((t) => {
                    const op = t.operational_status ?? 'active'
                    const busy = rowTenantBusy(t.codigo)
                    return (
                      <Fragment key={t.codigo}>
                        <tr data-testid={`platform-tenant-row-${t.codigo}`}>
                          <td style={{ padding: '.7rem .9rem', fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace' }}>
                            {t.codigo}
                          </td>
                          <td style={{ padding: '.7rem .9rem' }}>{t.nombre}</td>
                          <td style={{ padding: '.7rem .9rem' }}>{t.activo ? 'Activa' : 'Inactiva'}</td>
                          <td style={{ padding: '.7rem .9rem' }} data-testid={`platform-tenant-op-status-${t.codigo}`}>
                            {formatOperationalStatus(t.operational_status)}
                          </td>
                          <td style={{ padding: '.7rem .9rem', maxWidth: '15rem' }}>
                            {(() => {
                              const admin = formatInitialAdminCell(t)
                              if (!admin) {
                                return (
                                  <span className="dash-muted" data-testid={`platform-tenant-no-admin-${t.codigo}`}>
                                    Sin admin
                                  </span>
                                )
                              }
                              return (
                                <span data-testid={`platform-tenant-admin-${t.codigo}`}>
                                  <span style={{ fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace' }}>
                                    {admin.usuario}
                                  </span>
                                  {admin.codigo_cliente ? (
                                    <span className="dash-muted"> · {admin.codigo_cliente}</span>
                                  ) : null}
                                  {!admin.activo ? <span className="dash-muted"> (inactivo)</span> : null}
                                </span>
                              )
                            })()}
                          </td>
                          <td style={{ padding: '.7rem .9rem' }}>{formatSubscriptionStatus(t.subscription_status, t.trial_ends_at)}</td>
                          <td style={{ padding: '.7rem .9rem' }}>{formatDateShort(t.trial_starts_at) || '—'}</td>
                          <td style={{ padding: '.7rem .9rem' }}>{formatDateShort(t.trial_ends_at) || '—'}</td>
                          <td style={{ padding: '.7rem .9rem' }}>
                            {t.subscription_status === 'trial' ? (
                              <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap' }}>
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="soft"
                                  disabled={busy}
                                  loading={subscriptionSaving === t.codigo}
                                  onClick={() => handleTrialSubscriptionChange(t.codigo, 'active')}
                                  data-testid={`platform-tenant-activate-${t.codigo}`}
                                >
                                  Activar
                                </Button>
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="outline"
                                  disabled={busy}
                                  loading={subscriptionSaving === t.codigo}
                                  onClick={() => handleTrialSubscriptionChange(t.codigo, 'suspended')}
                                  data-testid={`platform-tenant-suspend-${t.codigo}`}
                                >
                                  Suspender
                                </Button>
                              </div>
                            ) : (
                              <span className="dash-muted">—</span>
                            )}
                          </td>
                          <td style={{ padding: '.7rem .9rem' }}>
                            {op === 'expired' ? (
                              <span className="dash-muted">—</span>
                            ) : (
                              <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', alignItems: 'center' }}>
                                {op === 'active' ? (
                                  <>
                                    <Button
                                      type="button"
                                      size="sm"
                                      variant="soft"
                                      disabled={busy}
                                      onClick={() => {
                                        setEditingTenantCodigo(t.codigo)
                                        setEditTenantForm(tenantListItemToEditForm(t))
                                      }}
                                      data-testid={`platform-tenant-edit-${t.codigo}`}
                                    >
                                      {editingTenantCodigo === t.codigo ? 'Editando…' : 'Editar'}
                                    </Button>
                                    <Button
                                      type="button"
                                      size="sm"
                                      variant="outline"
                                      disabled={busy}
                                      loading={tenantOperationalSaving === t.codigo}
                                      onClick={() => handleInactivateTenantOperational(t.codigo)}
                                      data-testid={`platform-tenant-inactivate-${t.codigo}`}
                                    >
                                      Inactivar
                                    </Button>
                                  </>
                                ) : null}
                                {op === 'inactive' ? (
                                  <Button
                                    type="button"
                                    size="sm"
                                    variant="soft"
                                    disabled={busy}
                                    loading={tenantOperationalSaving === t.codigo}
                                    onClick={() => handleReactivateTenantOperational(t.codigo)}
                                    data-testid={`platform-tenant-reactivate-${t.codigo}`}
                                  >
                                    Reactivar
                                  </Button>
                                ) : null}
                              </div>
                            )}
                          </td>
                        </tr>
                        {editingTenantCodigo === t.codigo ? (
                          <tr data-testid={`platform-tenant-edit-panel-${t.codigo}`}>
                            <td colSpan={10} style={{ padding: '.5rem .9rem 1rem', background: 'var(--surface-elevated, rgba(0,0,0,.04))' }}>
                              <p className="dash-muted" style={{ margin: '0 0 .5rem', fontSize: '.9rem' }}>
                                Editar domicilio y contacto ({t.codigo}). Deja vacío lo que no cambies. No incluye RFC ni razón
                                social.
                              </p>
                              <form className="dash-form" onSubmit={handleEditTenantSubmit}>
                                <div
                                  className="platform-domicilio-fields"
                                  style={{
                                    display: 'grid',
                                    gridTemplateColumns: 'repeat(auto-fill, minmax(14rem, 1fr))',
                                    gap: '0.65rem',
                                    alignItems: 'end',
                                  }}
                                >
                                  <Field label="Correo electrónico">
                                    <TextInput
                                      type="email"
                                      value={editTenantForm.correo_electronico}
                                      onChange={(e) => setEditTenantForm((f) => ({ ...f, correo_electronico: e.target.value }))}
                                      data-testid={`platform-tenant-edit-correo-${t.codigo}`}
                                    />
                                  </Field>
                                  <Field label="Código postal">
                                    <TextInput
                                      value={editTenantForm.codigo_postal}
                                      onChange={(e) => setEditTenantForm((f) => ({ ...f, codigo_postal: e.target.value }))}
                                      data-testid={`platform-tenant-edit-cp-${t.codigo}`}
                                    />
                                  </Field>
                                  <Field label="Estado (entidad)">
                                    <TextInput
                                      value={editTenantForm.estado}
                                      onChange={(e) => setEditTenantForm((f) => ({ ...f, estado: e.target.value }))}
                                      data-testid={`platform-tenant-edit-estado-${t.codigo}`}
                                    />
                                  </Field>
                                  <Field label="Municipio">
                                    <TextInput
                                      value={editTenantForm.municipio}
                                      onChange={(e) => setEditTenantForm((f) => ({ ...f, municipio: e.target.value }))}
                                      data-testid={`platform-tenant-edit-municipio-${t.codigo}`}
                                    />
                                  </Field>
                                  <Field label="Colonia">
                                    <TextInput
                                      value={editTenantForm.colonia}
                                      onChange={(e) => setEditTenantForm((f) => ({ ...f, colonia: e.target.value }))}
                                      data-testid={`platform-tenant-edit-colonia-${t.codigo}`}
                                    />
                                  </Field>
                                  <Field label="Localidad">
                                    <TextInput
                                      value={editTenantForm.localidad}
                                      onChange={(e) => setEditTenantForm((f) => ({ ...f, localidad: e.target.value }))}
                                      data-testid={`platform-tenant-edit-localidad-${t.codigo}`}
                                    />
                                  </Field>
                                  <Field label="Calle">
                                    <TextInput
                                      value={editTenantForm.calle}
                                      onChange={(e) => setEditTenantForm((f) => ({ ...f, calle: e.target.value }))}
                                      data-testid={`platform-tenant-edit-calle-${t.codigo}`}
                                    />
                                  </Field>
                                  <Field label="Núm. exterior">
                                    <TextInput
                                      value={editTenantForm.numero_exterior}
                                      onChange={(e) => setEditTenantForm((f) => ({ ...f, numero_exterior: e.target.value }))}
                                      data-testid={`platform-tenant-edit-ext-${t.codigo}`}
                                    />
                                  </Field>
                                  <Field label="Núm. interior">
                                    <TextInput
                                      value={editTenantForm.numero_interior}
                                      onChange={(e) => setEditTenantForm((f) => ({ ...f, numero_interior: e.target.value }))}
                                      data-testid={`platform-tenant-edit-int-${t.codigo}`}
                                    />
                                  </Field>
                                  <Field label="Tipo vialidad">
                                    <TextInput
                                      value={editTenantForm.tipo_vialidad}
                                      onChange={(e) => setEditTenantForm((f) => ({ ...f, tipo_vialidad: e.target.value }))}
                                      data-testid={`platform-tenant-edit-vialidad-${t.codigo}`}
                                    />
                                  </Field>
                                </div>
                                <div className="dash-form__actions dash-form__actions--row" style={{ marginTop: '.75rem' }}>
                                  <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    disabled={busy}
                                    loading={tenantOperationalSaving === t.codigo}
                                    data-testid={`platform-tenant-edit-save-${t.codigo}`}
                                  >
                                    Guardar cambios
                                  </Button>
                                  <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() => {
                                      setEditingTenantCodigo(null)
                                      setEditTenantForm(editTenantFormDefaults())
                                    }}
                                    data-testid={`platform-tenant-edit-cancel-${t.codigo}`}
                                  >
                                    Cancelar
                                  </Button>
                                </div>
                              </form>
                            </td>
                          </tr>
                        ) : null}
                      </Fragment>
                    )
                  })
                )}
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <div className="dash-grid" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(20rem, 1fr))' }}>
        <Card title="Crear empresa" subtitle="Origen de datos → RFC → tipo detectado → régimen → resto del emisor.">
          <form className="dash-form" onSubmit={handleCreateTenant}>
            <Field label="Origen de datos">
              <SelectInput
                value={tenantForm.origen_datos}
                onChange={(e) => setTenantForm(() => ({ ...tenantFormDefaults(), origen_datos: e.target.value }))}
                data-testid="platform-tenant-origen-datos"
              >
                <option value="">Selecciona…</option>
                <option value="sat_url">URL/QR SAT</option>
                <option value="pdf">PDF</option>
                <option value="imagen_qr">Imagen con QR</option>
                <option value="manual">Captura manual</option>
              </SelectInput>
            </Field>

            {tenantSuccess ? (
              <InlineAlert kind="success" data-testid="platform-tenant-success">
                {tenantSuccess}
              </InlineAlert>
            ) : null}

            {tenantForm.origen_datos === '' ? (
              <InlineAlert kind="warning" data-testid="platform-tenant-step-hint">
                Selecciona el origen de datos para comenzar el alta fiscal del emisor.
              </InlineAlert>
            ) : (
              <>
                {tenantForm.origen_datos === 'sat_url' ? (
                  <>
                    <Field label="SAT URL/QR">
                      <TextInput
                        value={tenantForm.sat_qr_url}
                        onChange={(e) => setTenantForm((f) => ({ ...f, sat_qr_url: e.target.value }))}
                        placeholder="Pega aquí la URL del QR SAT (con re= e id= si aplica)"
                        data-testid="platform-tenant-sat-qr-url"
                      />
                    </Field>
                    <div className="dash-form__actions dash-form__actions--row" style={{ marginTop: '-.25rem', marginBottom: '.75rem' }}>
                      <Button type="button" variant="soft" onClick={handleSatAutofill} data-testid="platform-tenant-sat-autofill">
                        Autollenar
                      </Button>
                      {tenantForm.sat_locked ? (
                        <Button
                          type="button"
                          variant="outline"
                          onClick={() =>
                            setTenantForm((f) => ({
                              ...f,
                              sat_locked: false,
                              regimen_ui_locked: false,
                              regimen_sat_codes: [],
                            }))
                          }
                          data-testid="platform-tenant-unlock"
                        >
                          Editar manualmente
                        </Button>
                      ) : (
                        <span className="dash-muted" style={{ fontSize: '.9rem' }}>
                          Tras autollenar, RFC y tipo quedan bloqueados hasta “Editar manualmente”.
                        </span>
                      )}
                    </div>
                  </>
                ) : tenantForm.origen_datos === 'pdf' || tenantForm.origen_datos === 'imagen_qr' ? (
                  <>
                    <Field label={tenantForm.origen_datos === 'pdf' ? 'Subir constancia (PDF)' : 'Subir constancia (imagen con QR)'}>
                      <TextInput
                        type="file"
                        accept={tenantForm.origen_datos === 'pdf' ? '.pdf,application/pdf' : '.jpg,.jpeg,image/jpeg'}
                        onChange={(e) => handleConstanciaUpload(e.target.files?.[0] || null)}
                        data-testid="platform-tenant-constancia-upload"
                      />
                    </Field>
                    <div className="dash-form__actions dash-form__actions--row" style={{ marginTop: '-.25rem', marginBottom: '.75rem' }}>
                      {tenantForm.sat_locked ? (
                        <Button
                          type="button"
                          variant="outline"
                          onClick={() =>
                            setTenantForm((f) => ({
                              ...f,
                              sat_locked: false,
                              regimen_ui_locked: false,
                              regimen_sat_codes: [],
                            }))
                          }
                          data-testid="platform-tenant-unlock"
                        >
                          Editar manualmente
                        </Button>
                      ) : (
                        <span className="dash-muted" style={{ fontSize: '.9rem' }}>
                          Prioridad: QR/HTML SAT; si falla, podrás completar RFC en manual.
                        </span>
                      )}
                    </div>
                  </>
                ) : null}

                <Field label="RFC">
                  <TextInput
                    value={tenantForm.rfc}
                    onChange={(e) => setTenantForm((f) => ({ ...f, rfc: e.target.value.toUpperCase() }))}
                    placeholder="AAA010101AAA"
                    data-testid="platform-tenant-rfc"
                    readOnly={tenantForm.sat_locked}
                  />
                </Field>

                {tenantError ? (
                  <InlineAlert kind="error" data-testid="platform-tenant-error">
                    {tenantError}
                  </InlineAlert>
                ) : null}
                {tenantWarning ? (
                  <InlineAlert kind="warning" data-testid="platform-tenant-warning">
                    {tenantWarning}
                  </InlineAlert>
                ) : null}
                {tenantAutofillSource ? (
                  <p className="dash-muted" data-testid="platform-tenant-autofill-source" style={{ marginTop: '-.35rem' }}>
                    Fuente de autollenado: {tenantAutofillSource}
                  </p>
                ) : null}

                {DEBUG_UI_ENABLED ? (
                  <div
                    className="dash-form__actions dash-form__actions--row"
                    style={{ flexWrap: 'wrap', gap: '0.5rem', marginTop: '0.35rem' }}
                  >
                    <Button
                      type="button"
                      variant="soft"
                      onClick={() => openTenantCreateDiagnostic(null)}
                      data-testid="platform-tenant-create-diag-open"
                    >
                      Diagnóstico técnico
                    </Button>
                    <span className="dash-muted" style={{ fontSize: '.85rem', flex: '1 1 14rem' }}>
                      Informe JSON: capa probable (web / red / starter-core), motivos si el botón está deshabilitado,
                      payload que se enviaría y cuerpo del error HTTP si hubo fallo.
                    </span>
                  </div>
                ) : null}

                {rfcPasoCompleto ? (
                  <>
                    <p className="dash-muted" style={{ margin: '0 0 0.5rem' }} data-testid="platform-tenant-tipo-detectado">
                      Tipo detectado:{' '}
                      {tenantForm.tipo_contribuyente === 'persona_fisica' ? 'Persona física' : 'Persona moral'}
                    </p>
                    <Field label="Régimen fiscal principal">
                      <SelectInput
                        value={tenantForm.regimen_fiscal_principal}
                        onChange={(e) => setTenantForm((f) => ({ ...f, regimen_fiscal_principal: e.target.value }))}
                        data-testid="platform-tenant-regimen-principal"
                        disabled={regimenSelectDisabled}
                      >
                        <option value="">Selecciona…</option>
                        {regimenOpciones.map((o) => (
                          <option key={o.value} value={o.value}>
                            {o.label}
                          </option>
                        ))}
                      </SelectInput>
                    </Field>
                    <p className="dash-muted" style={{ margin: '0.25rem 0 0.75rem' }}>
                      En base de datos solo se guarda la clave (ej. 601). Obligatorio elegir un régimen.
                    </p>
                  </>
                ) : null}

                {regimenPasoCompleto ? (
                  <>
                {tenantForm.tipo_contribuyente === 'persona_fisica' ? (
                  <>
                    <Card as="div" className="dash-alert--inline" title="Persona física">
                      <Field label="CURP">
                        <TextInput
                          value={tenantForm.curp}
                          onChange={(e) => setTenantForm((f) => ({ ...f, curp: e.target.value }))}
                          data-testid="platform-tenant-curp"
                          readOnly={tenantForm.sat_locked}
                        />
                      </Field>
                      <Field label="Nombre(s)">
                        <TextInput
                          value={tenantForm.pf_nombre}
                          onChange={(e) => setTenantForm((f) => ({ ...f, pf_nombre: e.target.value }))}
                          data-testid="platform-tenant-pf-nombre"
                          readOnly={tenantForm.sat_locked}
                        />
                      </Field>
                      <Field label="Primer apellido (puede ser vacío)">
                        <TextInput
                          value={tenantForm.pf_primer_apellido}
                          onChange={(e) => setTenantForm((f) => ({ ...f, pf_primer_apellido: e.target.value }))}
                          data-testid="platform-tenant-pf-apellido1"
                          readOnly={tenantForm.sat_locked}
                        />
                      </Field>
                      <Field label="Segundo apellido (puede ser vacío)">
                        <TextInput
                          value={tenantForm.pf_segundo_apellido}
                          onChange={(e) => setTenantForm((f) => ({ ...f, pf_segundo_apellido: e.target.value }))}
                          data-testid="platform-tenant-pf-apellido2"
                          readOnly={tenantForm.sat_locked}
                        />
                      </Field>
                    </Card>

                    <Field label="Nombre (empresa)">
                      <TextInput
                        value={tenantForm.nombre}
                        disabled
                        data-testid="platform-tenant-nombre"
                        title="Se forma automáticamente con los datos fiscales"
                      />
                    </Field>
                    <p className="dash-muted" style={{ margin: '-0.35rem 0 0.5rem' }}>
                      Persona física: nombre y apellidos. Persona moral: razón social.
                    </p>
                    <Field label="Código de empresa">
                      <TextInput
                        value={tenantForm.codigo}
                        disabled
                        data-testid="platform-tenant-codigo"
                        placeholder="Igual al RFC"
                        title="Siempre coincide con el RFC"
                      />
                    </Field>

                    <Card as="div" className="dash-alert--inline" title="Domicilio registrado">
                      <div className="platform-domicilio-fields">
                        <Field label="Código postal">
                          <TextInput value={tenantForm.codigo_postal} onChange={(e) => setTenantForm((f) => ({ ...f, codigo_postal: e.target.value }))} data-testid="platform-tenant-cp" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <p className="dash-muted" style={{ margin: '-0.2rem 0 0.15rem' }}>
                          Si no aplica en domicilio fiscal, captura NA.
                        </p>
                        <Field label="Tipo de vialidad (opcional)">
                          <TextInput value={tenantForm.tipo_vialidad} onChange={(e) => setTenantForm((f) => ({ ...f, tipo_vialidad: e.target.value }))} data-testid="platform-tenant-tipo-vialidad" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Calle">
                          <TextInput value={tenantForm.calle} onChange={(e) => setTenantForm((f) => ({ ...f, calle: e.target.value }))} data-testid="platform-tenant-calle" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Número exterior">
                          <TextInput value={tenantForm.numero_exterior} onChange={(e) => setTenantForm((f) => ({ ...f, numero_exterior: e.target.value }))} data-testid="platform-tenant-num-ext" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Número interior (opcional)">
                          <TextInput value={tenantForm.numero_interior} onChange={(e) => setTenantForm((f) => ({ ...f, numero_interior: e.target.value }))} data-testid="platform-tenant-num-int" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Colonia">
                          <TextInput value={tenantForm.colonia} onChange={(e) => setTenantForm((f) => ({ ...f, colonia: e.target.value }))} data-testid="platform-tenant-colonia" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Nombre de la Localidad">
                          <TextInput value={tenantForm.localidad} onChange={(e) => setTenantForm((f) => ({ ...f, localidad: e.target.value }))} data-testid="platform-tenant-localidad" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Nombre del Municipio o Demarcación Territorial">
                          <TextInput value={tenantForm.municipio} onChange={(e) => setTenantForm((f) => ({ ...f, municipio: e.target.value }))} data-testid="platform-tenant-municipio" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Nombre de la Entidad Federativa">
                          <TextInput value={tenantForm.estado} onChange={(e) => setTenantForm((f) => ({ ...f, estado: e.target.value }))} data-testid="platform-tenant-estado" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Correo electrónico (opcional)">
                          <TextInput value={tenantForm.correo_electronico} onChange={(e) => setTenantForm((f) => ({ ...f, correo_electronico: e.target.value }))} data-testid="platform-tenant-correo" readOnly={tenantForm.sat_locked} />
                        </Field>
                      </div>
                    </Card>
                  </>
                ) : (
                  <>
                    <Field label="Denominación / Razón social">
                      <TextInput
                        value={tenantForm.nombre_fiscal}
                        onChange={(e) => setTenantForm((f) => ({ ...f, nombre_fiscal: e.target.value }))}
                        data-testid="platform-tenant-nombre-fiscal"
                        readOnly={tenantForm.sat_locked}
                      />
                    </Field>

                    <Field label="Nombre (empresa)">
                      <TextInput
                        value={tenantForm.nombre}
                        disabled
                        data-testid="platform-tenant-nombre"
                        title="Se forma automáticamente con los datos fiscales"
                      />
                    </Field>
                    <p className="dash-muted" style={{ margin: '-0.35rem 0 0.5rem' }}>
                      Persona física: nombre y apellidos. Persona moral: razón social.
                    </p>
                    <Field label="Código de empresa">
                      <TextInput
                        value={tenantForm.codigo}
                        disabled
                        data-testid="platform-tenant-codigo"
                        placeholder="Igual al RFC"
                        title="Siempre coincide con el RFC"
                      />
                    </Field>

                    <Card as="div" className="dash-alert--inline" title="Domicilio registrado">
                      <div className="platform-domicilio-fields">
                        <Field label="Código postal">
                          <TextInput value={tenantForm.codigo_postal} onChange={(e) => setTenantForm((f) => ({ ...f, codigo_postal: e.target.value }))} data-testid="platform-tenant-cp" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <p className="dash-muted" style={{ margin: '-0.2rem 0 0.15rem' }}>
                          Si no aplica en domicilio fiscal, captura NA.
                        </p>
                        <Field label="Tipo de vialidad (opcional)">
                          <TextInput value={tenantForm.tipo_vialidad} onChange={(e) => setTenantForm((f) => ({ ...f, tipo_vialidad: e.target.value }))} data-testid="platform-tenant-tipo-vialidad" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Calle">
                          <TextInput value={tenantForm.calle} onChange={(e) => setTenantForm((f) => ({ ...f, calle: e.target.value }))} data-testid="platform-tenant-calle" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Número exterior">
                          <TextInput value={tenantForm.numero_exterior} onChange={(e) => setTenantForm((f) => ({ ...f, numero_exterior: e.target.value }))} data-testid="platform-tenant-num-ext" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Número interior (opcional)">
                          <TextInput value={tenantForm.numero_interior} onChange={(e) => setTenantForm((f) => ({ ...f, numero_interior: e.target.value }))} data-testid="platform-tenant-num-int" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Colonia">
                          <TextInput value={tenantForm.colonia} onChange={(e) => setTenantForm((f) => ({ ...f, colonia: e.target.value }))} data-testid="platform-tenant-colonia" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Nombre de la Localidad">
                          <TextInput value={tenantForm.localidad} onChange={(e) => setTenantForm((f) => ({ ...f, localidad: e.target.value }))} data-testid="platform-tenant-localidad" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Nombre del Municipio o Demarcación Territorial">
                          <TextInput value={tenantForm.municipio} onChange={(e) => setTenantForm((f) => ({ ...f, municipio: e.target.value }))} data-testid="platform-tenant-municipio" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Nombre de la Entidad Federativa">
                          <TextInput value={tenantForm.estado} onChange={(e) => setTenantForm((f) => ({ ...f, estado: e.target.value }))} data-testid="platform-tenant-estado" readOnly={tenantForm.sat_locked} />
                        </Field>
                        <Field label="Correo electrónico (opcional)">
                          <TextInput value={tenantForm.correo_electronico} onChange={(e) => setTenantForm((f) => ({ ...f, correo_electronico: e.target.value }))} data-testid="platform-tenant-correo" readOnly={tenantForm.sat_locked} />
                        </Field>
                      </div>
                    </Card>
                  </>
                )}

                    <Field label="Activo">
                      <SelectInput
                        value={tenantForm.activo ? '1' : '0'}
                        onChange={(e) => setTenantForm((f) => ({ ...f, activo: e.target.value === '1' }))}
                        data-testid="platform-tenant-activo"
                      >
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                      </SelectInput>
                    </Field>

                    <Button
                      type="submit"
                      variant="primary"
                      block
                      disabled={!canCreateTenant}
                      loading={tenantLoading}
                      data-testid="platform-tenant-submit"
                    >
                      {tenantLoading ? 'Creando...' : 'Crear empresa'}
                    </Button>
                  </>
                ) : null}
              </>
            )}
          </form>
        </Card>

        <Card title="Crear admin inicial" subtitle="Selecciona la empresa existente">
          <form className="dash-form" onSubmit={handleCreateInitialAdmin}>
            <Field label="Código de empresa">
              <SelectInput
                value={adminForm.tenant_codigo}
                onChange={(e) => setAdminForm((f) => ({ ...f, tenant_codigo: e.target.value }))}
                disabled={tenantsLoading || tenants.length === 0}
                data-testid="platform-admin-tenant-select"
              >
                <option value="">{tenantsLoading ? 'Cargando empresas...' : 'Selecciona una empresa...'}</option>
                {tenantCodigoOptions.map((o) => (
                  <option key={o.codigo} value={o.codigo}>
                    {o.label}
                  </option>
                ))}
              </SelectInput>
            </Field>
            {selectedTenantCodigo !== '' && !selectedTenant && (
              <InlineAlert kind="error">La empresa seleccionada no es válida. Actualiza el listado e intenta de nuevo.</InlineAlert>
            )}
            <Field label="Admin usuario">
              <TextInput
                value={adminForm.admin_usuario}
                onChange={(e) => setAdminForm((f) => ({ ...f, admin_usuario: e.target.value }))}
                placeholder="ej. admin_empresa1"
                data-testid="platform-admin-usuario"
              />
            </Field>
            <Field label="Admin contraseña">
              <TextInput
                type="password"
                value={adminForm.admin_password}
                onChange={(e) => setAdminForm((f) => ({ ...f, admin_password: e.target.value }))}
                placeholder="Minimo 8 caracteres"
                data-testid="platform-admin-password"
              />
            </Field>
            <Field label="Confirmar contraseña">
              <TextInput
                type="password"
                value={adminForm.admin_password_confirmation}
                onChange={(e) => setAdminForm((f) => ({ ...f, admin_password_confirmation: e.target.value }))}
                placeholder="Repite la contraseña"
                data-testid="platform-admin-password-confirm"
              />
            </Field>
            <Field label="Código cliente (opcional)">
              <TextInput
                value={adminForm.admin_codigo_cliente}
                onChange={(e) => setAdminForm((f) => ({ ...f, admin_codigo_cliente: e.target.value }))}
                placeholder="CLI-X"
                data-testid="platform-admin-codigo-cliente"
              />
            </Field>

            {adminLoading && <LoadingState text="Creando admin..." />}
            {adminError && (
              <InlineAlert kind="error" data-testid="platform-admin-error">
                {adminError}
              </InlineAlert>
            )}
            {adminSuccess && (
              <InlineAlert kind="success" data-testid="platform-admin-success">
                {adminSuccess}
              </InlineAlert>
            )}

            <Button
              type="submit"
              variant="primary"
              block
              disabled={!canCreateAdmin}
              loading={adminLoading}
              data-testid="platform-admin-submit"
            >
              {adminLoading ? 'Creando...' : 'Crear admin inicial'}
            </Button>
          </form>
        </Card>
      </div>

      {DEBUG_UI_ENABLED && tenantCreateDiagOpen ? (
        <div
          className="platform-tenant-diag-backdrop"
          style={{
            position: 'fixed',
            inset: 0,
            background: 'rgba(15, 18, 28, 0.55)',
            zIndex: 10000,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '1rem',
          }}
          role="presentation"
          onClick={() => setTenantCreateDiagOpen(false)}
        >
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="platform-tenant-diag-title"
            className="dash-card"
            style={{
              maxWidth: 'min(56rem, 100%)',
              width: '100%',
              maxHeight: 'min(88vh, 100%)',
              margin: 0,
              display: 'flex',
              flexDirection: 'column',
            }}
            onClick={(e) => e.stopPropagation()}
          >
            <header className="dash-card__header">
              <div className="dash-card__head-text">
                <h2 id="platform-tenant-diag-title" className="dash-card__title">
                  Diagnóstico — crear empresa
                </h2>
                <p className="dash-card__subtitle">
                  JSON con capa probable (starter-web / red / starter-core), estado del formulario, payload que se
                  enviaría y respuesta del API si hubo error. Pégalo en el chat de soporte o en el IDE.
                </p>
              </div>
            </header>
            <div
              className="dash-card__body"
              style={{ flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column', paddingBottom: '0.5rem' }}
            >
              <textarea
                readOnly
                value={tenantCreateDiagText}
                rows={22}
                data-testid="platform-tenant-create-diag-textarea"
                style={{
                  width: '100%',
                  flex: 1,
                  minHeight: '18rem',
                  fontFamily: 'ui-monospace, Consolas, monospace',
                  fontSize: '12px',
                  lineHeight: 1.45,
                  padding: '0.65rem',
                  borderRadius: '6px',
                  border: '1px solid rgba(0,0,0,0.12)',
                  resize: 'vertical',
                }}
              />
            </div>
            <div
              className="dash-card__body"
              style={{ paddingTop: 0, display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}
            >
              <Button
                type="button"
                variant="primary"
                onClick={() => void copyTenantCreateDiagnostic()}
                data-testid="platform-tenant-create-diag-copy"
              >
                Copiar al portapapeles
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={() => setTenantCreateDiagOpen(false)}
                data-testid="platform-tenant-create-diag-close"
              >
                Cerrar
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  )
}

