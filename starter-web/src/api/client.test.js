import {
  apiFetch,
  API_ACCESS_TOKEN_KEY,
  API_AUTH_NOTICE_KEY,
  API_REFRESH_TOKEN_KEY,
  API_USER_KEY,
  buildAuthErrorDebugReport,
  login,
  logout,
  selectLoginTenant,
  switchSessionTenant,
} from './client.js'

describe('buildAuthErrorDebugReport', () => {
  it('incluye pistas y metadatos sin contraseña de contexto', () => {
    const err = new Error('bad')
    err.status = 401
    err.code = 'INVALID_CREDENTIALS'
    err.requestPath = '/auth/login'
    const r = buildAuthErrorDebugReport(err, { flow: 'login', usuario: 'u1' })
    expect(r.usuario).toBe('u1')
    expect(r.httpStatus).toBe(401)
    expect(r.apiCode).toBe('INVALID_CREDENTIALS')
    expect(Array.isArray(r.hints)).toBe(true)
    expect(r.hints.length).toBeGreaterThan(0)
  })
})

describe('client session behavior', () => {
  beforeEach(() => {
    localStorage.clear()
    sessionStorage.clear()
  })

  it('si auth falla y refresh falla, limpia sesion', async () => {
    localStorage.setItem(API_ACCESS_TOKEN_KEY, 'access-old')
    localStorage.setItem(API_REFRESH_TOKEN_KEY, 'refresh-old')
    localStorage.setItem(API_USER_KEY, JSON.stringify({ id: 1 }))

    const fetchMock = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ code: 'UNAUTHENTICATED', message: 'unauth' }), { status: 401 }),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ code: 'REFRESH_INVALID', message: 'invalid' }), { status: 401 }),
      )

    await expect(apiFetch('/users')).rejects.toBeTruthy()

    expect(localStorage.getItem(API_ACCESS_TOKEN_KEY)).toBeNull()
    expect(localStorage.getItem(API_REFRESH_TOKEN_KEY)).toBeNull()
    expect(localStorage.getItem(API_USER_KEY)).toBeNull()
    expect(sessionStorage.getItem(API_AUTH_NOTICE_KEY)).toBeTruthy()

    fetchMock.mockRestore()
  })

  it('logout limpia estado aun si backend responde error', async () => {
    localStorage.setItem(API_ACCESS_TOKEN_KEY, 'a')
    localStorage.setItem(API_REFRESH_TOKEN_KEY, 'r')
    localStorage.setItem(API_USER_KEY, JSON.stringify({ id: 1 }))

    const fetchMock = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ code: 'ERR', message: 'x' }), { status: 500 }),
      )

    await logout()

    expect(localStorage.getItem(API_ACCESS_TOKEN_KEY)).toBeNull()
    expect(localStorage.getItem(API_REFRESH_TOKEN_KEY)).toBeNull()
    expect(localStorage.getItem(API_USER_KEY)).toBeNull()
    expect(sessionStorage.getItem(API_AUTH_NOTICE_KEY)).toBeNull()

    fetchMock.mockRestore()
  })

  it('login usa endpoint versionado /api/v1/auth/login', async () => {
    const fetchMock = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            code: 'OK',
            message: 'ok',
            data: {
              access_token: 'a',
              refresh_token: 'r',
              token_type: 'Bearer',
              expires_in: 3600,
              session_uuid: 's1',
            },
          }),
          { status: 200 },
        ),
      )
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            code: 'OK',
            message: 'ok',
            data: {
              user: {
                id: 1,
                usuario: 'admin_demo',
                activo: true,
                tenant_id: 1,
                codigo_cliente: 'C1',
                roles: [{ id: 1, nombre: 'Administrador', slug: 'admin' }],
              },
              tenant: { id: 1, codigo: 'DEFAULT' },
              accessible_tenants: [{ id: 1, codigo: 'DEFAULT', nombre: 'D', slug: 'd' }],
            },
          }),
          { status: 200 },
        ),
      )

    await login({ tenant_codigo: 'DEFAULT', usuario: 'admin_demo', password: 'Admin123!' })

    expect(fetchMock).toHaveBeenCalled()
    const firstUrl = String(fetchMock.mock.calls[0][0])
    expect(firstUrl).toMatch(/\/api\/v1\/auth\/login$/)

    const stored = JSON.parse(localStorage.getItem(API_USER_KEY) || '{}')
    expect(stored.roles).toEqual([{ id: 1, nombre: 'Administrador', slug: 'admin' }])
    expect(stored.codigo_cliente).toBe('C1')
    expect(stored.accessible_tenants).toHaveLength(1)

    fetchMock.mockRestore()
  })

  it('login revierte tokens si /auth/me falla despues del login', async () => {
    const loginEnvelope = {
      code: 'OK',
      message: 'ok',
      data: {
        access_token: 'a',
        refresh_token: 'r',
        token_type: 'Bearer',
        expires_in: 3600,
        session_uuid: 's1',
      },
    }

    const fetchMock = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(new Response(JSON.stringify(loginEnvelope), { status: 200 }))
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ code: 'SERVER', message: 'fallo interno' }), { status: 500 }),
      )

    await expect(
      login({ tenant_codigo: 'DEFAULT', usuario: 'x', password: 'y' }),
    ).rejects.toBeTruthy()

    expect(localStorage.getItem(API_ACCESS_TOKEN_KEY)).toBeNull()
    expect(localStorage.getItem(API_REFRESH_TOKEN_KEY)).toBeNull()
    expect(localStorage.getItem(API_USER_KEY)).toBeNull()

    fetchMock.mockRestore()
  })

  it('login global sin tenant: TENANT_SELECTION_REQUIRED no guarda tokens ni llama /me', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
      new Response(
        JSON.stringify({
          code: 'TENANT_SELECTION_REQUIRED',
          message: 'Seleccione empresa',
          data: {
            selection_token: 'sel-plain',
            expires_in: 600,
            tenants: [{ id: 1, codigo: 'DEFAULT', nombre: 'A', slug: 'a' }],
          },
        }),
        { status: 200 },
      ),
    )

    const out = await login({ usuario: 'multi_demo', password: 'MultiDemo123!' })

    expect(out).toMatchObject({
      needsTenantSelection: true,
      selection_token: 'sel-plain',
      tenants: [{ codigo: 'DEFAULT' }],
    })
    expect(localStorage.getItem(API_ACCESS_TOKEN_KEY)).toBeNull()
    expect(fetchMock).toHaveBeenCalledTimes(1)

    fetchMock.mockRestore()
  })

  it('selectLoginTenant intercambia token y persiste sesion con /me', async () => {
    const fetchMock = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            code: 'OK',
            message: 'ok',
            data: {
              access_token: 'a2',
              refresh_token: 'r2',
              token_type: 'Bearer',
              expires_in: 3600,
              session_uuid: 's2',
            },
          }),
          { status: 200 },
        ),
      )
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            code: 'OK',
            message: 'ok',
            data: {
              user: {
                id: 2,
                usuario: 'multi_demo',
                activo: true,
                tenant_id: 2,
                roles: [],
              },
              tenant: { id: 2, codigo: 'PRUEBA1' },
              accessible_tenants: [],
            },
          }),
          { status: 200 },
        ),
      )

    await selectLoginTenant({ selection_token: 'sel', tenant_codigo: 'PRUEBA1' })

    const secondUrl = String(fetchMock.mock.calls[1][0])
    expect(secondUrl).toMatch(/\/api\/v1\/auth\/me$/)
    expect(localStorage.getItem(API_ACCESS_TOKEN_KEY)).toBe('a2')

    fetchMock.mockRestore()
  })

  it('switchSessionTenant llama switch-tenant y luego auth/me', async () => {
    localStorage.setItem(API_ACCESS_TOKEN_KEY, 'access-x')
    const mePayload = {
      code: 'OK',
      message: 'ok',
      data: {
        user: {
          id: 2,
          usuario: 'multi_demo',
          activo: true,
          tenant_id: 1,
          roles: [{ slug: 'user' }],
        },
        tenant: { id: 2, codigo: 'PRUEBA1' },
        accessible_tenants: [
          { id: 1, codigo: 'DEFAULT', nombre: 'A', slug: 'a' },
          { id: 2, codigo: 'PRUEBA1', nombre: 'B', slug: 'b' },
        ],
      },
    }
    const fetchMock = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            code: 'OK',
            message: 'ok',
            data: { tenant: { id: 2, codigo: 'PRUEBA1', nombre: 'B', slug: 'b' } },
          }),
          { status: 200 },
        ),
      )
      .mockResolvedValueOnce(new Response(JSON.stringify(mePayload), { status: 200 }))

    await switchSessionTenant({ tenant_codigo: 'PRUEBA1' })

    expect(String(fetchMock.mock.calls[0][0])).toMatch(/\/api\/v1\/auth\/switch-tenant$/)
    expect(String(fetchMock.mock.calls[1][0])).toMatch(/\/api\/v1\/auth\/me$/)

    const stored = JSON.parse(localStorage.getItem(API_USER_KEY) || '{}')
    expect(stored.tenant?.codigo).toBe('PRUEBA1')
    expect(stored.accessible_tenants).toHaveLength(2)

    fetchMock.mockRestore()
  })
})

