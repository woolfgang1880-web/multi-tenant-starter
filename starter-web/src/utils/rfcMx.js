function isLeapYear(year) {
  return year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)
}

function daysInMonth(year, month) {
  if (month === 2) return isLeapYear(year) ? 29 : 28
  if (month === 4 || month === 6 || month === 9 || month === 11) return 30
  return 31
}

function inferFullYear(twoDigitYear) {
  const yy = Number(twoDigitYear)
  if (!Number.isInteger(yy) || yy < 0 || yy > 99) return null
  const currentYY = new Date().getFullYear() % 100
  return yy <= currentYY ? 2000 + yy : 1900 + yy
}

const GENERIC_RFCS = new Set(['XAXX010101000', 'XEXX010101000'])

export function isGenericRfc(rfcRaw) {
  const rfc = String(rfcRaw || '').trim().toUpperCase()
  return GENERIC_RFCS.has(rfc)
}

/**
 * Heurística SAT: 3 letras + fecha → moral; 4 letras + fecha → física.
 * @returns {'persona_fisica'|'persona_moral'|null}
 */
export function inferTipoContribuyenteFromRfc(rfcRaw) {
  const rfc = String(rfcRaw || '').trim().toUpperCase()
  if (!rfc || rfc.length < 10) return null
  if (/^[A-ZÑ&]{3}\d{2}/u.test(rfc)) return 'persona_moral'
  if (/^[A-ZÑ&]{4}\d{2}/u.test(rfc)) return 'persona_fisica'
  return null
}

export function validateRfcMx(rfcRaw, tipoContribuyente) {
  const rfc = String(rfcRaw || '').trim().toUpperCase()
  if (!rfc) return { ok: true, normalized: '', warning: null }

  if (isGenericRfc(rfc)) {
    return { ok: false, normalized: rfc, error: 'RFC genérico no permitido para el emisor fiscal.', warning: null }
  }

  const tipo = tipoContribuyente || inferTipoContribuyenteFromRfc(rfc)
  if (!tipo || (tipo !== 'persona_fisica' && tipo !== 'persona_moral')) {
    return {
      ok: false,
      normalized: rfc,
      error: 'No se pudo determinar el tipo de contribuyente a partir del RFC.',
      warning: null,
    }
  }

  const type = tipo === 'persona_fisica' ? 'pf' : 'pm'

  const prefixRe = type === 'pf' ? '[A-Z&Ñ]{4}' : '[A-Z&Ñ]{3}'
  const re = new RegExp(`^${prefixRe}([0-9]{2})([0-9]{2})([0-9]{2})([A-Z0-9]{3})$`, 'u')
  const m = rfc.match(re)
  if (!m) {
    return {
      ok: false,
      normalized: rfc,
      error:
        type === 'pf'
          ? 'RFC inválido para persona física. Formato esperado: 4 letras + 6 dígitos (AAMMDD) + 3 alfanuméricos.'
          : 'RFC inválido para persona moral. Formato esperado: 3 letras + 6 dígitos (AAMMDD) + 3 alfanuméricos.',
      warning: null,
    }
  }

  const yy = Number(m[1])
  const mm = Number(m[2])
  const dd = Number(m[3])

  if (mm < 1 || mm > 12) {
    return { ok: false, normalized: rfc, error: 'RFC inválido: mes fuera de rango (01-12).', warning: null }
  }

  const fullYear = inferFullYear(yy)
  if (fullYear == null) {
    return { ok: false, normalized: rfc, error: 'RFC inválido: año fuera de rango.', warning: null }
  }

  const dim = daysInMonth(fullYear, mm)
  if (dd < 1 || dd > dim) {
    return { ok: false, normalized: rfc, error: 'RFC inválido: día fuera de rango para ese mes.', warning: null }
  }

  let warning = null
  if (type === 'pf' && fullYear < 1930) {
    warning = 'RFC: el año de nacimiento inferido parece inusual. Verifica que el RFC sea correcto.'
  }

  return { ok: true, normalized: rfc, warning }
}
