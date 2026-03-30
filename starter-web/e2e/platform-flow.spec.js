import { expect, test } from '@playwright/test'

function uniqueSuffix() {
  return `${Date.now()}${Math.floor(Math.random() * 1000)}`
}

/** RFC persona moral válido (fecha del día + sufijo para unicidad en BD). */
function uniqueRfcMoral() {
  const d = new Date()
  const yy = String(d.getFullYear() % 100).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  const suf = uniqueSuffix()
  const h = `${String.fromCharCode(65 + (Number(suf) % 26))}${String(Number(suf) % 10)}${String.fromCharCode(65 + ((Number(suf) >> 3) % 26))}`
  return `ZZZ${yy}${mm}${dd}${h}`.toUpperCase()
}

async function login(page, { tenantCodigo, usuario, password }) {
  await page.goto('/#/login')

  await page.getByTestId('login-tenant-codigo').fill(tenantCodigo)
  await page.getByTestId('login-usuario').fill(usuario)
  await page.getByTestId('login-password').fill(password)
  await page.getByTestId('login-submit').click()

  // Login exitoso: layout del dashboard (sidebar)
  await expect(page.getByTestId('nav-dashboard')).toBeVisible()
}

async function openPlatform(page) {
  await page.goto('/#/platform')
  await expect(page.getByRole('heading', { name: 'Plataforma' })).toBeVisible()
}

async function logoutFromHeader(page) {
  await page.getByTestId('header-profile-toggle').click()
  await page.getByTestId('header-logout').click()
  await expect(page.getByTestId('login-submit')).toBeVisible()
}

test('Plataforma: crear empresa + admin inicial + login admin (sin mocks)', async ({ page }) => {
  test.setTimeout(120_000)

  const suffix = uniqueSuffix()
  const tenantRfc = uniqueRfcMoral()
  const tenantNombreFiscal = `Empresa E2E ${suffix} SA de CV`
  const adminUsuario = `e2e_admin_${suffix}`
  const adminPassword = 'Admin1234!'

  // Super admin demo (starter-core DemoUserSeeder)
  await login(page, { tenantCodigo: 'DEFAULT', usuario: 'admin_demo', password: 'Admin123!' })

  await expect(page.getByTestId('nav-platform')).toBeVisible()
  await openPlatform(page)

  // Crear empresa (manual, PM): código = RFC y nombre = razón social (solo lectura en UI)
  await page.getByTestId('platform-tenant-origen-datos').selectOption('manual')
  await page.getByTestId('platform-tenant-rfc').fill(tenantRfc)
  await page.getByTestId('platform-tenant-regimen-principal').selectOption('601')
  await page.getByTestId('platform-tenant-nombre-fiscal').fill(tenantNombreFiscal)
  await expect(page.getByTestId('platform-tenant-codigo')).toHaveValue(tenantRfc)
  await expect(page.getByTestId('platform-tenant-nombre')).toHaveValue(tenantNombreFiscal)
  await page.getByTestId('platform-tenant-cp').fill('01010')
  await page.getByTestId('platform-tenant-estado').fill('CDMX')
  await page.getByTestId('platform-tenant-activo').selectOption('1')
  await page.getByTestId('platform-tenant-submit').click()

  await expect(page.getByTestId('platform-tenant-success')).toContainText(`Empresa creada correctamente: ${tenantRfc}`)
  await expect(page.getByTestId(`platform-tenant-row-${tenantRfc}`)).toBeVisible()

  // Crear admin inicial (selección explícita del tenant existente)
  await expect(page.getByTestId('platform-admin-tenant-select')).toBeEnabled()
  await page.getByTestId('platform-admin-tenant-select').selectOption(tenantRfc)
  await page.getByTestId('platform-admin-usuario').fill(adminUsuario)
  await page.getByTestId('platform-admin-password').fill(adminPassword)
  await page.getByTestId('platform-admin-password-confirm').fill(adminPassword)
  await page.getByTestId('platform-admin-submit').click()

  await expect(page.getByTestId('platform-admin-success')).toContainText(`Admin inicial creado correctamente: ${adminUsuario}`)

  await logoutFromHeader(page)

  // Login real del admin creado
  await login(page, { tenantCodigo: tenantRfc, usuario: adminUsuario, password: adminPassword })

  await expect(page.getByTestId('nav-users')).toBeVisible()
  await expect(page.getByTestId('nav-platform')).toHaveCount(0)
})
