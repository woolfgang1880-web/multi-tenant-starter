import { describe, it, expect } from 'vitest'
import { getStoredUser, syncStoredUserFromMe } from './client.js'

describe('syncStoredUserFromMe', () => {
  it('persiste is_platform_admin para habilitar plataforma', () => {
    const me = {
      user: {
        id: 1,
        usuario: 'x',
        activo: true,
        tenant_id: 1,
        codigo_cliente: null,
        is_platform_admin: true,
        roles: [],
        abilities: [],
      },
      tenant: { codigo: 'DEFAULT' },
      accessible_tenants: [],
    }

    syncStoredUserFromMe(me)
    const stored = getStoredUser()
    expect(stored).not.toBeNull()
    expect(stored.is_platform_admin).toBe(true)
  })
})

