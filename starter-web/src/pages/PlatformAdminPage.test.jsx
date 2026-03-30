import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { vi } from 'vitest'
import PlatformAdminPage from './PlatformAdminPage.jsx'

const createTenantMock = vi.fn()
const createAdminMock = vi.fn()
const getTenantsMock = vi.fn()
const updateSubscriptionMock = vi.fn()
const showToastMock = vi.fn()
const extractSatDataFromPdfMock = vi.fn()
const detectSatQrUrlFromPdfMock = vi.fn()
const decodeSatQrFromFileMock = vi.fn()

vi.mock('../api/client.js', () => ({
  createPlatformTenant: (...args) => createTenantMock(...args),
  createPlatformTenantInitialAdmin: (...args) => createAdminMock(...args),
  getPlatformTenants: (...args) => getTenantsMock(...args),
  updatePlatformTenantSubscription: (...args) => updateSubscriptionMock(...args),
}))

vi.mock('../context/ToastContext.jsx', () => ({
  useToast: () => ({ showToast: showToastMock, dismissToast: () => {} }),
}))

vi.mock('../utils/satConstanciaPdf.js', () => ({
  extractSatDataFromPdf: (...args) => extractSatDataFromPdfMock(...args),
  detectSatQrUrlFromPdf: (...args) => detectSatQrUrlFromPdfMock(...args),
}))
vi.mock('../utils/qrSat.js', () => ({
  decodeSatQrFromFile: (...args) => decodeSatQrFromFileMock(...args),
}))

async function fillManualPmTenantFlow({
  rfc = 'AAA010101AAA',
  regimen = '601',
  nombreFiscal = 'Razón Social SA',
} = {}) {
  fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'manual' } })
  fireEvent.change(await screen.findByLabelText(/^RFC$/i), { target: { value: rfc } })
  fireEvent.change(await screen.findByTestId('platform-tenant-regimen-principal'), { target: { value: regimen } })
  fireEvent.change(await screen.findByTestId('platform-tenant-nombre-fiscal'), { target: { value: nombreFiscal } })
  expect(screen.getByTestId('platform-tenant-codigo')).toHaveValue(rfc.toUpperCase())
  expect(screen.getByTestId('platform-tenant-nombre')).toHaveValue(nombreFiscal)
  fireEvent.change(screen.getByTestId('platform-tenant-cp'), { target: { value: '01010' } })
  fireEvent.change(screen.getByLabelText('Nombre de la Entidad Federativa'), { target: { value: 'CDMX' } })
}

describe('PlatformAdminPage (UI mínima)', () => {
  beforeEach(() => {
    createTenantMock.mockReset()
    createAdminMock.mockReset()
    getTenantsMock.mockReset()
    updateSubscriptionMock.mockReset()
    showToastMock.mockReset()
    extractSatDataFromPdfMock.mockReset()
    detectSatQrUrlFromPdfMock.mockReset()
    decodeSatQrFromFileMock.mockReset()
  })

  it('crea tenant y luego admin inicial con feedback', async () => {
    getTenantsMock.mockResolvedValueOnce({
      items: [
        {
          codigo: 'DEFAULT',
          nombre: 'Default',
          activo: true,
          subscription_status: 'trial',
          trial_starts_at: '2026-03-01T00:00:00.000Z',
          trial_ends_at: '2026-04-01T00:00:00.000Z',
          initial_admin: null,
        },
      ],
    })

    createTenantMock.mockResolvedValueOnce({
      codigo: 'AAA010101AAA',
    })
    createAdminMock.mockResolvedValueOnce({
      usuario: 'admin_tenantx',
    })
    getTenantsMock.mockResolvedValueOnce({
      items: [
        {
          codigo: 'DEFAULT',
          nombre: 'Default',
          activo: true,
          subscription_status: 'trial',
          trial_starts_at: '2026-03-01T00:00:00.000Z',
          trial_ends_at: '2026-04-01T00:00:00.000Z',
          initial_admin: null,
        },
        {
          codigo: 'AAA010101AAA',
          nombre: 'Razón Social SA',
          activo: true,
          subscription_status: 'trial',
          trial_starts_at: '2026-03-02T00:00:00.000Z',
          trial_ends_at: '2026-04-02T00:00:00.000Z',
          initial_admin: null,
        },
      ],
    })
    getTenantsMock.mockResolvedValueOnce({
      items: [
        {
          codigo: 'DEFAULT',
          nombre: 'Default',
          activo: true,
          subscription_status: 'trial',
          trial_starts_at: '2026-03-01T00:00:00.000Z',
          trial_ends_at: '2026-04-01T00:00:00.000Z',
          initial_admin: null,
        },
        {
          codigo: 'AAA010101AAA',
          nombre: 'Razón Social SA',
          activo: true,
          subscription_status: 'trial',
          trial_starts_at: '2026-03-02T00:00:00.000Z',
          trial_ends_at: '2026-04-02T00:00:00.000Z',
          initial_admin: {
            id: 99,
            usuario: 'admin_tenantx',
            codigo_cliente: 'CLI-X',
            activo: true,
          },
        },
      ],
    })

    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    await fillManualPmTenantFlow()
    fireEvent.change(screen.getByTestId('platform-tenant-activo'), { target: { value: '1' } })
    fireEvent.click(screen.getByRole('button', { name: /Crear empresa/i }))

    await waitFor(() => expect(createTenantMock).toHaveBeenCalled())
    expect(createTenantMock.mock.calls[0][0]).toMatchObject({
      codigo: 'AAA010101AAA',
      nombre: 'Razón Social SA',
      rfc: 'AAA010101AAA',
      origen_datos: 'manual',
      regimen_fiscal_principal: '601',
    })
    expect(await screen.findByText(/Empresa creada correctamente: AAA010101AAA/i)).toBeInTheDocument()

    const adminTenantSelect = screen.getByTestId('platform-admin-tenant-select')
    expect(adminTenantSelect).toHaveValue('AAA010101AAA')

    const tenantRow = screen.getByTestId('platform-tenant-row-AAA010101AAA')
    const rowUtils = within(tenantRow)
    expect(rowUtils.getByText('Prueba (trial)')).toBeInTheDocument()
    expect(rowUtils.getByText('2026-03-02')).toBeInTheDocument()
    expect(rowUtils.getByText('2026-04-02')).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('Admin usuario'), { target: { value: 'admin_tenantx' } })
    fireEvent.change(screen.getByLabelText('Admin contraseña'), { target: { value: 'Admin1234!' } })
    fireEvent.change(screen.getByLabelText('Confirmar contraseña'), { target: { value: 'Admin1234!' } })
    fireEvent.change(screen.getByLabelText('Código cliente (opcional)'), { target: { value: 'CLI-X' } })

    fireEvent.click(screen.getByRole('button', { name: /Crear admin inicial/i }))

    await waitFor(() => expect(createAdminMock).toHaveBeenCalled())
    expect(await screen.findByText(/Admin inicial creado correctamente: admin_tenantx/i)).toBeInTheDocument()

    await waitFor(() => expect(getTenantsMock).toHaveBeenCalledTimes(3))
    const rowAfter = screen.getByTestId('platform-tenant-row-AAA010101AAA')
    expect(within(rowAfter).getByTestId('platform-tenant-admin-AAA010101AAA')).toHaveTextContent('admin_tenantx')
    expect(within(rowAfter).getByTestId('platform-tenant-admin-AAA010101AAA')).toHaveTextContent('CLI-X')
  })

  it('autollena RFC desde SAT URL/QR', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockRejectedValue(new Error('blocked'))
    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'sat_url' } })
    fireEvent.change(screen.getByLabelText(/SAT URL\/QR/i), {
      target: { value: 'https://example.test/?re=AAA010101AAA&id=CIF-123' },
    })
    fireEvent.click(screen.getByTestId('platform-tenant-sat-autofill'))

    expect(await screen.findByLabelText(/^RFC$/i)).toHaveValue('AAA010101AAA')
    fetchSpy.mockRestore()
  })

  it('imagen con QR consulta HTML SAT y autollena', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: true,
      text: async () =>
        `<html><body>
          El RFC: FPN190109N73, tiene asociada la siguiente información.
          Denominación o Razón Social: FARMACIA PUEBLO NUEVO, S.A. DE C.V. Régimen de capital:
          Régimen:Régimen General de Ley Personas Morales
          Fecha de alta:01-01-2019
          Código Postal: 80290
        </body></html>`,
    })
    render(
      <PlatformAdminPage
        user={{ usuario: 'platform_admin', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'super_admin' }], is_platform_admin: true }}
      />,
    )
    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'imagen_qr' } })

    const file = new File(['fake image'], 'qr.jpg', { type: 'image/jpeg' })
    decodeSatQrFromFileMock.mockResolvedValueOnce(
      'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=19020149030_FPN190109N73',
    )
    fireEvent.change(screen.getByTestId('platform-tenant-constancia-upload'), { target: { files: [file] } })

    expect(await screen.findByLabelText(/^RFC$/i)).toHaveValue('FPN190109N73')
    fireEvent.change(screen.getByTestId('platform-tenant-regimen-principal'), { target: { value: '601' } })
    expect(await screen.findByTestId('platform-tenant-cp')).toHaveValue('80290')
    expect(screen.getByTestId('platform-tenant-codigo')).toHaveValue('FPN190109N73')
    expect(screen.getByTestId('platform-tenant-nombre')).toHaveValue('FARMACIA PUEBLO NUEVO, S.A. DE C.V.')
    expect(screen.getByTestId('platform-tenant-autofill-source')).toHaveTextContent('html_sat')
    fetchSpy.mockRestore()
  })

  it('autollena codigo postal desde HTML SAT cuando viene en respuesta', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: true,
      text: async () =>
        `<html><body>
          El RFC: FPN190109N73, tiene asociada la siguiente información.
          Denominación o Razón Social: FARMACIA PUEBLO NUEVO, S.A. DE C.V. Régimen de capital:
          Régimen:Régimen General de Ley Personas Morales
          Fecha de alta:01-01-2019
          Código Postal: 80290
        </body></html>`,
    })
    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'sat_url' } })
    fireEvent.change(screen.getByLabelText(/SAT URL\/QR/i), {
      target: { value: 'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=19020149030_FPN190109N73' },
    })
    fireEvent.click(screen.getByTestId('platform-tenant-sat-autofill'))

    expect(await screen.findByLabelText(/^RFC$/i)).toHaveValue('FPN190109N73')
    fireEvent.change(screen.getByTestId('platform-tenant-regimen-principal'), { target: { value: '601' } })
    expect(await screen.findByTestId('platform-tenant-cp')).toHaveValue('80290')
    expect(screen.getByTestId('platform-tenant-codigo')).toHaveValue('FPN190109N73')
    expect(screen.getByTestId('platform-tenant-nombre')).toHaveValue('FARMACIA PUEBLO NUEVO, S.A. DE C.V.')
    fetchSpy.mockRestore()
  })

  it('modo solo lectura tras autollenado y se puede desbloquear', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockRejectedValue(new Error('blocked'))
    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'sat_url' } })
    fireEvent.change(screen.getByLabelText(/SAT URL\/QR/i), {
      target: { value: 'https://example.test/?re=AAA010101AAA&id=CIF-123' },
    })
    fireEvent.click(screen.getByTestId('platform-tenant-sat-autofill'))

    const rfcInput = await screen.findByLabelText(/^RFC$/i)
    expect(rfcInput).toHaveAttribute('readonly')

    fireEvent.click(screen.getByTestId('platform-tenant-unlock'))
    expect(screen.getByLabelText(/^RFC$/i)).not.toHaveAttribute('readonly')
    fetchSpy.mockRestore()
  })

  it('vista inicial solo origen; PF tras RFC regimen y datos', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    expect(screen.getByTestId('platform-tenant-step-hint')).toBeInTheDocument()
    expect(screen.queryByLabelText(/^RFC$/i)).not.toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'manual' } })
    fireEvent.change(await screen.findByLabelText(/^RFC$/i), { target: { value: 'ABCD010101AAA' } })
    fireEvent.change(screen.getByTestId('platform-tenant-regimen-principal'), { target: { value: '612' } })
    expect(await screen.findByLabelText(/^CURP$/i)).toBeInTheDocument()
    expect(screen.getByTestId('platform-tenant-cp')).toBeInTheDocument()
    expect(screen.queryByLabelText(/Denominación/i)).not.toBeInTheDocument()
  })

  it('autollena desde PDF de constancia y deja campos en solo lectura', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    detectSatQrUrlFromPdfMock.mockResolvedValueOnce(null)
    extractSatDataFromPdfMock.mockResolvedValueOnce({
      satUrl: 'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=19020149030_FPN190109N73',
      rfc: 'FPN190109N73',
      razonSocial: 'FARMACIA PUEBLO NUEVO, S.A. DE C.V.',
      codigoPostal: '80290',
      calle: 'ALVARO OBREGON',
      numeroExterior: '1234',
      numeroInterior: '',
      colonia: 'CENTRO',
      localidad: 'CULIACAN',
      municipio: 'CULIACAN',
      estado: 'SINALOA',
      regimenes: ['General de Ley Personas Morales'],
    })

    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'pdf' } })

    const file = new File(['fake pdf'], 'constancia.pdf', { type: 'application/pdf' })
    fireEvent.change(screen.getByTestId('platform-tenant-constancia-upload'), { target: { files: [file] } })

    expect(await screen.findByLabelText(/^RFC$/i)).toHaveValue('FPN190109N73')
    expect(screen.getByTestId('platform-tenant-nombre-fiscal')).toHaveValue('FARMACIA PUEBLO NUEVO, S.A. DE C.V.')
    expect(screen.getByTestId('platform-tenant-codigo')).toHaveValue('FPN190109N73')
    expect(screen.getByTestId('platform-tenant-nombre')).toHaveValue('FARMACIA PUEBLO NUEVO, S.A. DE C.V.')
    expect(screen.getByTestId('platform-tenant-cp')).toHaveValue('80290')
    expect(screen.getByTestId('platform-tenant-colonia')).toHaveValue('CENTRO')
    expect(screen.getByTestId('platform-tenant-autofill-source')).toHaveTextContent('pdf_texto')
    expect(screen.getByLabelText(/^RFC$/i)).toHaveAttribute('readonly')
  })

  it('pdf con QR consulta HTML SAT y prioriza esa fuente', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    detectSatQrUrlFromPdfMock.mockResolvedValueOnce(
      'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=19020149030_FPN190109N73',
    )
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: true,
      text: async () =>
        `<html><body>
          El RFC: FPN190109N73, tiene asociada la siguiente información.
          Régimen:Régimen General de Ley Personas Morales
          Fecha de alta:01-01-2019
          Código Postal: 80290
        </body></html>`,
    })
    render(
      <PlatformAdminPage
        user={{ usuario: 'platform_admin', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'super_admin' }], is_platform_admin: true }}
      />,
    )
    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'pdf' } })
    const file = new File(['fake pdf'], 'constancia.pdf', { type: 'application/pdf' })
    fireEvent.change(screen.getByTestId('platform-tenant-constancia-upload'), { target: { files: [file] } })

    expect(await screen.findByLabelText(/^RFC$/i)).toHaveValue('FPN190109N73')
    expect(screen.getByTestId('platform-tenant-autofill-source')).toHaveTextContent('html_sat')
    fetchSpy.mockRestore()
  })

  it('autollena colonia desde PDF cuando viene como Nombre de la Colonia', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    detectSatQrUrlFromPdfMock.mockResolvedValueOnce(null)
    extractSatDataFromPdfMock.mockResolvedValueOnce({
      satUrl: 'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=19020149030_FPN190109N73',
      rfc: 'FPN190109N73',
      codigoPostal: '81200',
      colonia: 'PRIMER CUADRO (CENTRO)',
      regimenes: ['Régimen General de Ley Personas Morales'],
    })

    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'pdf' } })

    const file = new File(['fake pdf'], 'constancia.pdf', { type: 'application/pdf' })
    fireEvent.change(screen.getByTestId('platform-tenant-constancia-upload'), { target: { files: [file] } })

    expect(await screen.findByTestId('platform-tenant-colonia')).toHaveValue('PRIMER CUADRO (CENTRO)')
  })

  it('muestra etiquetas SAT para localidad municipio y entidad', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'manual' } })
    fireEvent.change(await screen.findByLabelText(/^RFC$/i), { target: { value: 'AAA010101AAA' } })
    fireEvent.change(screen.getByTestId('platform-tenant-regimen-principal'), { target: { value: '601' } })

    expect(await screen.findByLabelText('Nombre de la Localidad')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre del Municipio o Demarcación Territorial')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre de la Entidad Federativa')).toBeInTheDocument()
  })

  it('autollena persona fisica desde PDF (RFC CURP nombre y apellidos)', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    detectSatQrUrlFromPdfMock.mockResolvedValueOnce(null)
    extractSatDataFromPdfMock.mockResolvedValueOnce({
      rfc: 'OEJE7508255K0',
      curp: 'OEXJ750825HNERXS03',
      nombre: 'JESUS ARMANDO',
      primerApellido: 'ORNELAS',
      segundoApellido: '',
      codigoPostal: '78300',
      colonia: 'FERROCARRILERA',
      localidad: 'SAN LUIS POTOSI',
      municipio: 'SAN LUIS POTOSI',
      estado: 'SAN LUIS POTOSI',
      regimenes: ['Régimen Simplificado de Confianza'],
    })

    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'pdf' } })

    const file = new File(['fake pdf pf'], 'constancia-pf.pdf', { type: 'application/pdf' })
    fireEvent.change(screen.getByTestId('platform-tenant-constancia-upload'), { target: { files: [file] } })

    expect(await screen.findByLabelText(/^RFC$/i)).toHaveValue('OEJE7508255K0')
    expect(screen.getByTestId('platform-tenant-codigo')).toHaveValue('OEJE7508255K0')
    expect(screen.getByTestId('platform-tenant-nombre')).toHaveValue('JESUS ARMANDO ORNELAS')
    expect(screen.getByTestId('platform-tenant-curp')).toHaveValue('OEXJ750825HNERXS03')
    expect(screen.getByTestId('platform-tenant-pf-nombre')).toHaveValue('JESUS ARMANDO')
    expect(screen.getByTestId('platform-tenant-pf-apellido1')).toHaveValue('ORNELAS')
    expect(screen.getByTestId('platform-tenant-cp')).toHaveValue('78300')
    expect(screen.getByTestId('platform-tenant-colonia')).toHaveValue('FERROCARRILERA')
    expect(screen.getByLabelText(/^RFC$/i)).toHaveAttribute('readonly')
  })

  it('si SAT/HTML no disponible cae a manual', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({ ok: false, text: async () => '' })
    render(
      <PlatformAdminPage
        user={{ usuario: 'platform_admin', tenant: { codigo: 'DEFAULT' }, roles: [{ slug: 'super_admin' }], is_platform_admin: true }}
      />,
    )
    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'sat_url' } })
    fireEvent.change(screen.getByLabelText(/SAT URL\/QR/i), {
      target: { value: 'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=NOPE' },
    })
    fireEvent.click(screen.getByTestId('platform-tenant-sat-autofill'))

    expect(await screen.findByTestId('platform-tenant-warning')).toHaveTextContent('Se cambia a captura manual')
    expect(screen.getByTestId('platform-tenant-autofill-source')).toHaveTextContent('manual')
    fetchSpy.mockRestore()
  })

  it('usa regimen fiscal principal como select unico (sin input libre)', async () => {
    getTenantsMock.mockResolvedValueOnce({ items: [] })
    render(
      <PlatformAdminPage
        user={{
          usuario: 'platform_admin',
          tenant: { codigo: 'DEFAULT' },
          roles: [{ slug: 'super_admin' }],
          is_platform_admin: true,
        }}
      />,
    )

    fireEvent.change(screen.getByLabelText('Origen de datos'), { target: { value: 'manual' } })
    fireEvent.change(await screen.findByLabelText(/^RFC$/i), { target: { value: 'AAA010101AAA' } })

    expect(await screen.findByTestId('platform-tenant-regimen-principal')).toBeInTheDocument()
    expect(screen.queryByTestId('platform-tenant-regimenes-raw')).not.toBeInTheDocument()
  })
})
