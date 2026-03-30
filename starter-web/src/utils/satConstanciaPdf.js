import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.mjs'
import jsQR from 'jsqr'
import { parseSatUrl } from './satValidador.js'

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString()

function norm(text) {
  return String(text || '')
    .replace(/\s+/g, ' ')
    .trim()
}

function pick(bodyText, re) {
  const m = bodyText.match(re)
  return m?.[1] ? norm(m[1]) : null
}

function cleanPersonField(value) {
  const v = norm(value)
  if (!v) return null
  // Si accidentalmente capturamos otra etiqueta, se descarta.
  if (/:/.test(v)) return null
  if (/^(FECHA|ESTATUS|NOMBRE COMERCIAL|DATOS DEL DOMICILIO)/i.test(v)) return null
  return v
}

function firstSatUrl(text) {
  const m = String(text || '').match(/https:\/\/siat\.sat\.gob\.mx\/app\/qr\/faces\/pages\/mobile\/validadorqr\.jsf\?[^\s)]+/i)
  return m?.[0] || null
}

export function parseSatConstanciaText(rawText) {
  const bodyText = norm(rawText)
  if (!bodyText) return null

  const satUrlRaw = firstSatUrl(bodyText)
  const satUrlParsed = satUrlRaw ? parseSatUrl(satUrlRaw) : { satUrl: '', rfc: null, idCif: null }

  const out = {
    satUrl: satUrlParsed.satUrl || satUrlRaw || null,
    rfc: satUrlParsed.rfc || pick(bodyText, /\bRFC:\s*([A-Z0-9&Ñ]{12,13})\b/i),
    idCif: satUrlParsed.idCif || pick(bodyText, /\bID\s*CIF:\s*([0-9]+)\b/i),
    curp: pick(bodyText, /\bCURP:\s*([A-Z0-9]{18})\b/i),
    nombre: cleanPersonField(
      pick(bodyText, /Nombre \(s\):\s*([^]+?)\s*(?=Primer Apellido:|Segundo Apellido:|Fecha inicio de operaciones:|Estatus en el padrón:|Nombre Comercial:|Datos del domicilio registrado|$)/i) ||
        pick(bodyText, /Nombre:\s*([^]+?)\s*(?=Apellido Paterno:|Primer Apellido:|Apellido Materno:|Segundo Apellido:|Fecha inicio de operaciones:|$)/i),
    ),
    primerApellido: cleanPersonField(
      pick(bodyText, /Primer Apellido:\s*([^]+?)\s*(?=Segundo Apellido:|Fecha inicio de operaciones:|Estatus en el padrón:|Nombre Comercial:|Datos del domicilio registrado|$)/i) ||
        pick(bodyText, /Apellido Paterno:\s*([^]+?)\s*(?=Apellido Materno:|Fecha inicio de operaciones:|Estatus en el padrón:|Nombre Comercial:|Datos del domicilio registrado|$)/i),
    ),
    segundoApellido: cleanPersonField(
      pick(bodyText, /Segundo Apellido:\s*([^]+?)\s*(?=Fecha inicio de operaciones:|Estatus en el padrón:|Nombre Comercial:|Datos del domicilio registrado|$)/i) ||
        pick(bodyText, /Apellido Materno:\s*([^]+?)\s*(?=Fecha inicio de operaciones:|Estatus en el padrón:|Nombre Comercial:|Datos del domicilio registrado|$)/i),
    ),
    razonSocial:
      pick(bodyText, /Denominación\/Razón Social:\s*([^]+?)\s*(?:Régimen Capital|Nombre Comercial:|Fecha inicio de operaciones:)/i) ||
      pick(bodyText, /DENOMINACION\/RAZON SOCIAL\s*([^]+?)\s*(?:RÉGIMEN CAPITAL|REGIMEN CAPITAL|NOMBRE COMERCIAL:|FECHA INICIO DE OPERACIONES:|ID CIF:)/i) ||
      pick(bodyText, /Denominación o Razón Social:\s*([^]+?)\s*(?:Régimen de capital:|Nombre comercial:|Fecha de inicio de operaciones:)/i),
    codigoPostal: pick(bodyText, /(?:\bCP:|C[oó]digo Postal:)\s*([0-9]{5})\b/i),
    tipoVialidad:
      pick(bodyText, /Tipo de Vialidad:\s*([^]+?)\s*(?:Nombre de Vialidad:|Número Exterior:|Numero Exterior:)/i) ||
      pick(bodyText, /Tipo de vialidad:\s*([^]+?)\s*Nombre de la vialidad:/i),
    calle:
      pick(bodyText, /Nombre de Vialidad:\s*([^]+?)\s*(?:Número Exterior:|Numero Exterior:)/i) ||
      pick(bodyText, /Nombre de la vialidad:\s*([^]+?)\s*Número exterior:/i),
    numeroExterior:
      pick(bodyText, /(?:Número Exterior|Numero Exterior):\s*([A-Z0-9\-\/]+)\s*(?:Número Interior|Numero Interior|Colonia:|CP:)/i) ||
      pick(bodyText, /Número exterior:\s*([A-Z0-9\-\/]+)\s*Número interior:/i),
    numeroInterior:
      pick(bodyText, /(?:Número Interior|Numero Interior):\s*([A-Z0-9\-\/]*)\s*(?:Colonia:|CP:|Correo Electrónico:)/i) ||
      pick(bodyText, /Número interior:\s*([A-Z0-9\-\/]*)\s*CP:/i),
    colonia: pick(
      bodyText,
      /(?:Nombre de la Colonia|Colonia):\s*([^]+?)\s*(?:Localidad:|Nombre de la Localidad:|Nombre del Municipio o Demarcación Territorial:|Municipio\/Delegación:|Municipio o delegación:|Nombre de la Entidad Federativa:|Entidad Federativa:|$)/i,
    ),
    localidad: pick(bodyText, /(?:Localidad|Nombre de la Localidad):\s*([^]+?)\s*(?:Municipio\/Delegación:|Nombre del Municipio|Municipio o delegación:)/i),
    municipio:
      pick(bodyText, /(?:Municipio\/Delegación|Municipio o delegación):\s*([^]+?)\s*(?:Entidad Federativa:|Colonia:)/i) ||
      pick(bodyText, /Nombre del Municipio o Demarcación Territorial:\s*([^]+?)\s*Nombre de la Entidad Federativa:/i) ||
      pick(bodyText, /Nombre del Municipio o Demarcación Territorial:\s*([^]+?)\s*Entre Calle:/i),
    estado:
      pick(bodyText, /Entidad Federativa:\s*([^]+?)\s*(?:Municipio\/Delegación:|Municipio o delegación:|Código Postal:)/i) ||
      pick(bodyText, /Nombre de la Entidad Federativa:\s*([^]+?)\s*(?:Entre Calle:|Régimen:|Regimen:|$)/i) ||
      pick(bodyText, /Entidad Federativa:\s*([^]+?)\s*(?:Régimen:|Regimen:|$)/i),
    correoElectronico:
      pick(bodyText, /Correo Electr[oó]nico:\s*([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})/i) ||
      pick(bodyText, /E-mail:\s*([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})/i),
    regimenes: [],
  }

  const regimenes = []
  const reReg = /Régimen:\s*([^]+?)\s*Fecha de alta:/gi
  let m
  while ((m = reReg.exec(bodyText)) !== null) {
    const label = norm(m[1])
    if (label && !regimenes.includes(label)) regimenes.push(label)
  }
  out.regimenes = regimenes

  return out
}

export async function extractSatDataFromPdf(file) {
  if (!file || String(file.type || '').toLowerCase() !== 'application/pdf') return null
  const data = new Uint8Array(await file.arrayBuffer())
  const pdf = await pdfjsLib.getDocument({ data }).promise
  let text = ''
  for (let i = 1; i <= pdf.numPages; i += 1) {
    const page = await pdf.getPage(i)
    const content = await page.getTextContent()
    const chunk = content.items
      .map((it) => ('str' in it ? it.str : ''))
      .join(' ')
    text += ` ${chunk}`
  }
  return parseSatConstanciaText(text)
}

export async function detectSatQrUrlFromPdf(file) {
  if (!file || String(file.type || '').toLowerCase() !== 'application/pdf') return null
  const data = new Uint8Array(await file.arrayBuffer())
  const pdf = await pdfjsLib.getDocument({ data }).promise
  const maxPages = Math.min(pdf.numPages, 3)

  for (let i = 1; i <= maxPages; i += 1) {
    const page = await pdf.getPage(i)
    const viewport = page.getViewport({ scale: 2 })
    const canvas = document.createElement('canvas')
    canvas.width = Math.max(1, Math.floor(viewport.width))
    canvas.height = Math.max(1, Math.floor(viewport.height))
    const ctx = canvas.getContext('2d', { willReadFrequently: true })
    if (!ctx) continue
    await page.render({ canvasContext: ctx, viewport }).promise
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height)
    const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' })
    const qrText = String(code?.data || '').trim()
    if (!qrText) continue
    const parsed = parseSatUrl(qrText)
    if (parsed.satUrl) return parsed.satUrl
    if (/^https?:\/\//i.test(qrText)) return qrText
  }

  return null
}
