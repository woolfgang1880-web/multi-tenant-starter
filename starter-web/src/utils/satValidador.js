function norm(text) {
  return String(text || '').replace(/\s+/g, ' ').trim()
}

function cleanPersonField(value) {
  const v = norm(value)
  if (!v) return null
  if (/:/.test(v)) return null
  if (/^(FECHA|ESTATUS|NOMBRE COMERCIAL|DATOS DE UBICACI[OÓ]N)/i.test(v)) return null
  return v
}

function extractSatEmail(bodyText) {
  const scoped =
    bodyText.match(
      /Correo electr[oó]nico:\s*([^]+?)\s*(?=AL:|AL\b|E-mail:|Correo electr[oó]nico:|Caracter[ií]sticas fiscales|Régimen:|Regimen:|$)/i,
    )?.[1] ||
    bodyText.match(/E-mail:\s*([^]+?)\s*(?=AL:|AL\b|Correo electr[oó]nico:|Caracter[ií]sticas fiscales|Régimen:|Regimen:|$)/i)?.[1] ||
    ''

  const candidate = norm(scoped)
  if (!candidate) return null

  const email =
    candidate.match(
      /([A-Z0-9._%+-]+@[A-Z0-9.-]+?\.(?:COM\.MX|GOB\.MX|ORG\.MX|NET\.MX|EDU\.MX|MX|[A-Z]{2,10}?))(?=AL\b|[^A-Z0-9]|$)/i,
    )?.[1] || null

  return email ? norm(email) : null
}

export function parseSatUrl(urlRaw) {
  const raw = String(urlRaw || '').trim()
  if (!raw) return { satUrl: '', rfc: null, idCif: null }

  let satUrl = raw
  let rfc = null
  let idCif = null
  try {
    const u = new URL(raw)
    satUrl = u.toString()
    const d3 = u.searchParams.get('D3') || u.searchParams.get('d3') || ''
    if (d3.includes('_')) {
      const [idPart, rfcPart] = d3.split('_')
      const idClean = norm(idPart)
      const rfcClean = norm(rfcPart).toUpperCase()
      if (/^\d+$/.test(idClean)) idCif = idClean
      if (/^[A-Z0-9&Ñ]{12,13}$/u.test(rfcClean)) rfc = rfcClean
    }

    const re = u.searchParams.get('re') || u.searchParams.get('rfc')
    const id = u.searchParams.get('id') || u.searchParams.get('idCIF') || u.searchParams.get('idcif')
    if (!rfc && re) rfc = norm(re).toUpperCase()
    if (!idCif && id) idCif = norm(id)
  } catch {
    // Ignorar: se conservará sólo raw.
  }

  return { satUrl, rfc, idCif }
}

export function parseSatValidadorHtml(htmlRaw) {
  const html = String(htmlRaw || '')
  if (!html.trim()) return null
  const parser = new DOMParser()
  const doc = parser.parseFromString(html, 'text/html')
  const bodyText = norm(doc.body?.textContent || '')
  if (!bodyText) return null

  const out = {
    rfc: null,
    idCif: null,
    curp: null,
    nombre: null,
    primerApellido: null,
    segundoApellido: null,
    razonSocial: null,
    codigoPostal: null,
    tipoVialidad: null,
    nombreVialidad: null,
    numeroExterior: null,
    numeroInterior: null,
    colonia: null,
    localidad: null,
    municipio: null,
    estado: null,
    correoElectronico: null,
    regimenes: [],
  }

  const pick = (re) => {
    const m = bodyText.match(re)
    return m?.[1] ? norm(m[1]) : null
  }

  out.rfc = pick(/El RFC:\s*([A-Z0-9&Ñ]{12,13})\s*,?\s*tiene asociada/i) || pick(/RFC:\s*([A-Z0-9&Ñ]{12,13})/i)
  out.idCif = pick(/idCIF:\s*([0-9]+)/i)
  out.curp = pick(/CURP:\s*([A-Z0-9]{18})/i)
  out.nombre = cleanPersonField(
    pick(/Nombre:\s*([^]+?)\s*(?=Apellido Paterno:|Primer Apellido:|Apellido Materno:|Segundo Apellido:|Fecha|$)/i) ||
      pick(/Nombre \(s\):\s*([^]+?)\s*(?=Primer Apellido:|Segundo Apellido:|Fecha|$)/i),
  )
  out.primerApellido = cleanPersonField(
    pick(/Apellido Paterno:\s*([^]+?)\s*(?=Apellido Materno:|Fecha|$)/i) ||
      pick(/Primer Apellido:\s*([^]+?)\s*(?=Segundo Apellido:|Fecha|$)/i),
  )
  out.segundoApellido = cleanPersonField(
    pick(/Apellido Materno:\s*([^]+?)\s*(?=Fecha|Estatus|Nombre Comercial|$)/i) ||
      pick(/Segundo Apellido:\s*([^]+?)\s*(?=Fecha|Estatus|Nombre Comercial|$)/i),
  )
  out.razonSocial = pick(/Denominación o Razón Social:\s*([A-Z0-9ÁÉÍÓÚÜÑ .,&\-]+?)\s*Régimen de capital:/i)

  out.estado =
    pick(/Entidad Federativa:\s*([A-ZÁÉÍÓÚÜÑ ]+?)\s*Municipio o delegación:/i) ||
    pick(/Nombre de la Entidad Federativa:\s*([A-ZÁÉÍÓÚÜÑ ]+?)\s*(?:Entre Calle:|Régimen:|Regimen:|$)/i)
  out.municipio =
    pick(/Municipio o delegación:\s*([A-ZÁÉÍÓÚÜÑ ]+?)\s*Colonia:/i) ||
    pick(/Nombre del Municipio o Demarcación Territorial:\s*([A-ZÁÉÍÓÚÜÑ ]+?)\s*Nombre de la Entidad Federativa:/i) ||
    pick(/Nombre del Municipio o Demarcación Territorial:\s*([A-ZÁÉÍÓÚÜÑ ]+?)\s*Entre Calle:/i)
  out.colonia =
    pick(/(?:Nombre de la Colonia|Colonia):\s*([A-Z0-9ÁÉÍÓÚÜÑ ()\-]+?)\s*(?:Tipo de vialidad:|Nombre de la Localidad:|Localidad:)/i) ||
    pick(/Colonia:\s*([A-Z0-9ÁÉÍÓÚÜÑ ()\-]+?)\s*Tipo de vialidad:/i)
  out.tipoVialidad = pick(/Tipo de vialidad:\s*([A-Z0-9ÁÉÍÓÚÜÑ ()\-]+?)\s*Nombre de la vialidad:/i)
  out.nombreVialidad = pick(/Nombre de la vialidad:\s*([A-Z0-9ÁÉÍÓÚÜÑ .()\-]+?)\s*Número exterior:/i)
  out.numeroExterior = pick(/Número exterior:\s*([A-Z0-9\-\/]+?)\s*Número interior:/i)
  out.numeroInterior = pick(/Número interior:\s*([A-Z0-9\-\/]*)\s*CP:/i) || pick(/Número interior:\s*([A-Z0-9\-\/]*)\s*Correo electrónico:/i)
  out.codigoPostal = pick(/(?:CP:|C[oó]digo Postal:)\s*([0-9]{5})/i)
  out.localidad =
    pick(/Nombre de la Localidad:\s*([A-ZÁÉÍÓÚÜÑ ]+?)\s*Nombre del Municipio/i) ||
    pick(/Nombre de la Localidad:\s*([A-ZÁÉÍÓÚÜÑ ]+?)\s*(?:Municipio o delegación:|Municipio\/Delegación:)/i)
  out.correoElectronico = extractSatEmail(bodyText)

  const regimenes = []
  const reReg = /Régimen:\s*([^:]+?)\s*Fecha de alta:/gi
  let m
  while ((m = reReg.exec(bodyText)) !== null) {
    const label = norm(m[1])
    if (label && !regimenes.includes(label)) regimenes.push(label)
  }
  out.regimenes = regimenes

  return out
}

export async function fetchSatValidadorHtml(satUrl) {
  const url = String(satUrl || '').trim()
  if (!url) return null
  const res = await fetch(url, {
    method: 'GET',
    headers: {
      Accept: 'text/html,application/xhtml+xml',
    },
  })
  if (!res.ok) return null
  return res.text()
}

