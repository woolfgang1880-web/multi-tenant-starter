import { describe, expect, it } from 'vitest'
import { parseSatConstanciaText } from './satConstanciaPdf.js'

describe('parseSatConstanciaText', () => {
  it('extrae minimos de persona moral desde texto de constancia', () => {
    const text = `
      CEDULA DE IDENTIFICACION FISCAL
      RFC: FPN190109N73
      DENOMINACION/RAZON SOCIAL FARMACIA PUEBLO NUEVO, S.A. DE C.V.
      ID CIF: 19020149030
      https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=19020149030_FPN190109N73
      Código Postal: 80290 Tipo de Vialidad: AVENIDA Nombre de Vialidad: ALVARO OBREGON
      Número Exterior: 1234 Número Interior:
      Colonia: CENTRO Municipio/Delegación: CULIACAN Entidad Federativa: SINALOA
      Régimen: General de Ley Personas Morales Fecha de alta: 01-01-2019
    `
    const out = parseSatConstanciaText(text)
    expect(out?.rfc).toBe('FPN190109N73')
    expect(out?.idCif).toBe('19020149030')
    expect(out?.satUrl).toContain('validadorqr.jsf')
    expect(out?.razonSocial).toContain('FARMACIA PUEBLO NUEVO')
    expect(out?.calle).toBe('ALVARO OBREGON')
    expect(out?.municipio).toBe('CULIACAN')
    expect(out?.estado).toBe('SINALOA')
    expect(out?.regimenes?.length).toBe(1)
  })

  it('extrae codigo postal cuando viene como CP', () => {
    const out = parseSatConstanciaText('RFC:AAA010101AAA CP: 12345')
    expect(out?.codigoPostal).toBe('12345')
  })

  it('extrae codigo postal cuando viene como Codigo Postal', () => {
    const out = parseSatConstanciaText('RFC:AAA010101AAA Codigo Postal: 54321')
    expect(out?.codigoPostal).toBe('54321')
  })

  it('extrae colonia en formato Nombre de la Colonia de constancia real', () => {
    const text = `
      Código Postal:81200 Tipo de Vialidad: CALLE
      Nombre de Vialidad: VICENTE GUERRERO Número Exterior: 950
      Número Interior:24 Nombre de la Colonia: PRIMER CUADRO (CENTRO)
      Nombre de la Localidad: LOS MOCHIS Nombre del Municipio o Demarcación Territorial: AHOME
      Nombre de la Entidad Federativa: SINALOA
    `
    const out = parseSatConstanciaText(text)
    expect(out?.colonia).toBe('PRIMER CUADRO (CENTRO)')
  })

  it('extrae localidad municipio y entidad sin arrastrar entre calles', () => {
    const text = `
      Nombre de la Localidad: LOS MOCHIS Nombre del Municipio o Demarcación Territorial: AHOME
      Nombre de la Entidad Federativa: SINALOA Entre Calle: CALLE JIQUILPAN Y Calle: CALLE RODOLFO T LOAIZA
    `
    const out = parseSatConstanciaText(text)
    expect(out?.localidad).toBe('LOS MOCHIS')
    expect(out?.municipio).toBe('AHOME')
    expect(out?.estado).toBe('SINALOA')
  })

  it('extrae datos de persona fisica desde constancia SAT', () => {
    const text = `
      RFC: OEJE7508255K0
      CURP: OEXJ750825HNERXS03
      Nombre (s): JESUS ARMANDO
      Primer Apellido: ORNELAS
      Segundo Apellido:
      Fecha inicio de operaciones: 01 DE ENERO DE 2013
      Código Postal:78300
      Nombre de la Colonia: FERROCARRILERA
    `
    const out = parseSatConstanciaText(text)
    expect(out?.rfc).toBe('OEJE7508255K0')
    expect(out?.curp).toBe('OEXJ750825HNERXS03')
    expect(out?.nombre).toBe('JESUS ARMANDO')
    expect(out?.primerApellido).toBe('ORNELAS')
    expect(out?.segundoApellido).toBe(null)
    expect(out?.codigoPostal).toBe('78300')
    expect(out?.colonia).toBe('FERROCARRILERA')
  })

  it('no arrastra "Fecha inicio" cuando segundo apellido esta vacio', () => {
    const text = `
      Nombre (s): JESUS ARMANDO
      Primer Apellido: ORNELAS
      Segundo Apellido:
      Fecha inicio de operaciones: 01 DE ENERO DE 2013
    `
    const out = parseSatConstanciaText(text)
    expect(out?.primerApellido).toBe('ORNELAS')
    expect(out?.segundoApellido).toBe(null)
  })

  it('permite primer apellido vacio y segundo con valor', () => {
    const text = `
      Nombre (s): ANA
      Primer Apellido:
      Segundo Apellido: LOPEZ
      Fecha inicio de operaciones: 01 DE ENERO DE 2013
    `
    const out = parseSatConstanciaText(text)
    expect(out?.primerApellido).toBe(null)
    expect(out?.segundoApellido).toBe('LOPEZ')
  })
})
