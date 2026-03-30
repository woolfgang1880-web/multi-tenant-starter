/**
 * Catálogo fiscal (clave 3 dígitos). Alineado a config/regimenes_fiscal.php del core.
 * Persistencia: solo la clave. UI: "clave — nombre".
 */

const PF = {
  626: 'Régimen Simplificado de Confianza (RESICO)',
  605: 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
  612: 'Actividades Empresariales y Profesionales',
  625: 'Plataformas Tecnológicas',
  606: 'Arrendamiento',
  621: 'Régimen de Incorporación Fiscal',
  607: 'Enajenación o Adquisición de Bienes',
  611: 'Dividendos',
  614: 'Intereses',
  616: 'Sin obligaciones fiscales',
}

const PM = {
  601: 'Régimen General de Ley',
  623: 'RESICO Personas Morales',
  603: 'Personas Morales con Fines no Lucrativos',
  624: 'Coordinados',
  628: 'Hidrocarburos',
}

function norm(s) {
  return String(s || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .replace(/\s+/g, ' ')
    .trim()
}

export function getRegimenMapForTipo(tipoContribuyente) {
  return tipoContribuyente === 'persona_fisica' ? PF : tipoContribuyente === 'persona_moral' ? PM : {}
}

export function getRegimenOptionsForTipo(tipoContribuyente) {
  const m = getRegimenMapForTipo(tipoContribuyente)
  return Object.keys(m).map((code) => ({
    value: code,
    label: `${code} — ${m[code]}`,
  }))
}

export function getRegimenLabel(code, tipoContribuyente) {
  const m = getRegimenMapForTipo(tipoContribuyente)
  const c = String(code || '')
  if (!m[c]) return null
  return `${c} — ${m[c]}`
}

/**
 * Intenta mapear textos del SAT/HTML a claves del catálogo (heurística).
 * @param {string[]} texts
 * @param {string} tipoContribuyente persona_fisica | persona_moral
 * @returns {string[]} códigos únicos
 */
export function mapSatRegimenTextsToCodes(texts, tipoContribuyente) {
  const opts = getRegimenOptionsForTipo(tipoContribuyente)
  if (!opts.length || !Array.isArray(texts)) return []
  const codes = new Set()
  const byValue = new Map(opts.map((o) => [o.value, o]))

  for (const raw of texts) {
    const t = norm(raw)
    if (!t) continue

    const codeMatch = t.match(/\b(\d{3})\b/)
    if (codeMatch && byValue.has(codeMatch[1])) {
      codes.add(codeMatch[1])
    }

    for (const o of opts) {
      const nameOnly = norm(o.label.replace(/^\d{3}\s*[—\-]\s*/u, '')).replace(/^regimen\s+/u, '')
      const nameCore = nameOnly.replace(/\s*\([^)]*\)\s*/g, ' ').replace(/\s+/g, ' ').trim()
      for (const candidate of [nameCore, nameOnly].filter((s) => s && s.length >= 8)) {
        const piece = candidate.slice(0, 28)
        if (piece && t.includes(piece)) {
          codes.add(o.value)
          break
        }
      }
      if (codes.has(o.value)) continue
      const legacy = norm(o.label).slice(0, 28)
      if (legacy.length >= 8 && t.includes(legacy)) {
        codes.add(o.value)
      }
    }
  }

  return [...codes]
}

export function isValidRegimenCodeForTipo(code, tipoContribuyente) {
  const m = getRegimenMapForTipo(tipoContribuyente)
  return Boolean(m[String(code)])
}
