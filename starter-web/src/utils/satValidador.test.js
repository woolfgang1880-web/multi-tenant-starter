import { describe, expect, it } from 'vitest'
import { parseSatUrl, parseSatValidadorHtml } from './satValidador.js'

describe('parseSatUrl', () => {
  it('extrae idCIF y RFC desde D3', () => {
    const out = parseSatUrl('https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=19020149030_FPN190109N73')
    expect(out.idCif).toBe('19020149030')
    expect(out.rfc).toBe('FPN190109N73')
  })

  it('extrae re/id cuando existen', () => {
    const out = parseSatUrl('https://ejemplo.local/?re=AAA010101AAA&id=CIF-123')
    expect(out.rfc).toBe('AAA010101AAA')
    expect(out.idCif).toBe('CIF-123')
  })
})

describe('parseSatValidadorHtml', () => {
  it('extrae campos clave de html SAT', () => {
    const html = `
      <html><body>
      El RFC: CUAM791210A68, tiene asociada la siguiente información.
      Datos de Identificación
      CURP:CUAM791210HSPLLN05Nombre:MANUELApellido Paterno:CUELLARApellido Materno:ALVAREZ
      Datos de Ubicación (domicilio fiscal, vigente)
      Entidad Federativa:SAN LUIS POTOSI
      Municipio o delegación:SAN LUIS POTOSI
      Colonia:TEQUISQUIAPAN
      Tipo de vialidad:CERRADA (CDA) O PRIVADA (PRIV)
      Nombre de la vialidad:2A PRIVADA DE MELCHOR OCAMPO
      Número exterior:40
      Número interior:
      CP:78250
      Características fiscales (vigente)
      Régimen:Régimen de Sueldos y Salarios e Ingresos Asimilados a Salarios
      Fecha de alta:01-02-2011
      Régimen:Régimen de las Personas Físicas con Actividades Empresariales y Profesionales
      Fecha de alta:01-08-2024
      </body></html>
    `
    const out = parseSatValidadorHtml(html)
    expect(out?.rfc).toBe('CUAM791210A68')
    expect(out?.curp).toBe('CUAM791210HSPLLN05')
    expect(out?.nombre).toBe('MANUEL')
    expect(out?.primerApellido).toBe('CUELLAR')
    expect(out?.codigoPostal).toBe('78250')
    expect(out?.municipio).toBe('SAN LUIS POTOSI')
    expect(out?.regimenes?.length).toBe(2)
  })

  it('extrae codigo postal cuando html trae etiqueta Codigo Postal', () => {
    const html = `
      <html><body>
      El RFC: AAA010101AAA, tiene asociada la siguiente información.
      Código Postal: 11590
      </body></html>
    `
    const out = parseSatValidadorHtml(html)
    expect(out?.codigoPostal).toBe('11590')
  })

  it('extrae colonia cuando html trae Nombre de la Colonia', () => {
    const html = `
      <html><body>
      El RFC: FPN190109N73, tiene asociada la siguiente información.
      Nombre de la Colonia: PRIMER CUADRO (CENTRO) Nombre de la Localidad: LOS MOCHIS
      </body></html>
    `
    const out = parseSatValidadorHtml(html)
    expect(out?.colonia).toBe('PRIMER CUADRO (CENTRO)')
  })

  it('extrae localidad municipio y entidad en formato nombre completo', () => {
    const html = `
      <html><body>
      El RFC: FPN190109N73, tiene asociada la siguiente información.
      Nombre de la Localidad: LOS MOCHIS
      Nombre del Municipio o Demarcación Territorial: AHOME
      Nombre de la Entidad Federativa: SINALOA Entre Calle: CALLE JIQUILPAN
      </body></html>
    `
    const out = parseSatValidadorHtml(html)
    expect(out?.localidad).toBe('LOS MOCHIS')
    expect(out?.municipio).toBe('AHOME')
    expect(out?.estado).toBe('SINALOA')
  })

  it('no arrastra etiquetas cuando segundo apellido esta vacio', () => {
    const html = `
      <html><body>
      Nombre (s): JESUS ARMANDO
      Primer Apellido: ORNELAS
      Segundo Apellido:
      Fecha inicio de operaciones: 01 DE ENERO DE 2013
      </body></html>
    `
    const out = parseSatValidadorHtml(html)
    expect(out?.primerApellido).toBe('ORNELAS')
    expect(out?.segundoApellido).toBe(null)
  })

  it('extrae correo limpio en persona fisica sin concatenar AL', () => {
    const html = `
      <html><body>
      El RFC: CUAM791210A68, tiene asociada la siguiente información.
      Correo electrónico: guarderiasantacecilia@yahoo.com.mxAL Correo electrónico: guarderiasantacecilia@yahoo.com.mx AL: SAN LUIS POTOSI 1
      </body></html>
    `
    const out = parseSatValidadorHtml(html)
    expect(out?.correoElectronico).toBe('guarderiasantacecilia@yahoo.com.mx')
  })

  it('extrae correo limpio en persona moral cuando viene etiqueta posterior', () => {
    const html = `
      <html><body>
      El RFC: FPN190109N73, tiene asociada la siguiente información.
      Correo electrónico: contacto@farmaciapueblo.com.mx AL: CULIACAN 1
      Denominación o Razón Social: FARMACIA PUEBLO NUEVO, S.A. DE C.V. Régimen de capital:
      </body></html>
    `
    const out = parseSatValidadorHtml(html)
    expect(out?.correoElectronico).toBe('contacto@farmaciapueblo.com.mx')
  })
})

