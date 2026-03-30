<?php

/**
 * Catálogo de regímenes fiscales (clave SAT → etiqueta corta).
 * Persistir en BD solo la clave (3 dígitos). La UI muestra "clave — nombre".
 *
 * @var array<string, array<string, string>>
 */
return [
    'persona_fisica' => [
        '626' => 'Régimen Simplificado de Confianza (RESICO)',
        '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
        '612' => 'Actividades Empresariales y Profesionales',
        '625' => 'Plataformas Tecnológicas',
        '606' => 'Arrendamiento',
        '621' => 'Régimen de Incorporación Fiscal',
        '607' => 'Enajenación o Adquisición de Bienes',
        '611' => 'Dividendos',
        '614' => 'Intereses',
        '616' => 'Sin obligaciones fiscales',
    ],
    'persona_moral' => [
        '601' => 'Régimen General de Ley',
        '623' => 'RESICO Personas Morales',
        '603' => 'Personas Morales con Fines no Lucrativos',
        '624' => 'Coordinados',
        '628' => 'Hidrocarburos',
    ],
];
