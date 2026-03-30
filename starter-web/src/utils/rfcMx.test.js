import { describe, expect, it } from 'vitest'
import { validateRfcMx } from './rfcMx.js'

describe('validateRfcMx', () => {
  it('acepta vacío', () => {
    expect(validateRfcMx('', 'persona_fisica').ok).toBe(true)
  })

  it('valida RFC persona moral (3 letras + fecha válida + homoclave)', () => {
    const r = validateRfcMx('ABC000229AAA', 'persona_moral')
    expect(r.ok).toBe(true)
    expect(r.normalized).toBe('ABC000229AAA')
  })

  it('rechaza RFC persona moral con mes inválido', () => {
    const r = validateRfcMx('ABC011329AAA', 'persona_moral')
    expect(r.ok).toBe(false)
  })

  it('valida RFC persona física (4 letras + fecha válida + homoclave)', () => {
    const r = validateRfcMx('ABCD000229AAA', 'persona_fisica')
    expect(r.ok).toBe(true)
  })

  it('rechaza RFC persona física con formato de persona moral', () => {
    const r = validateRfcMx('ABC010229AAA', 'persona_fisica')
    expect(r.ok).toBe(false)
  })

  it('rechaza RFC genérico XAXX', () => {
    const r = validateRfcMx('XAXX010101000', 'persona_moral')
    expect(r.ok).toBe(false)
    expect(r.error).toMatch(/genérico/i)
  })

  it('valida moral infiriendo tipo si no se pasa', () => {
    const r = validateRfcMx('ABC000229AAA', null)
    expect(r.ok).toBe(true)
  })
})

