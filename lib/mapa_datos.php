<?php
/**
 * PolarPrisma — Metadatos estructurados de propiedad y financiación por medio.
 *
 * Complementan la ficha larga ('transparencia' en config.php) con campos
 * cortos y comparables para el Mapa ideológico (mapa.php):
 *
 *   propiedad    Quién es el dueño, en pocas palabras.
 *   financiacion Modelo de ingresos dominante, en pocas palabras.
 *   tipo         Categoría de propiedad, para agregación:
 *                  conglomerado | familiar | inversor | publico |
 *                  lectores | fundacion | no_declarado
 *   capacidad    Orden de magnitud económica del grupo editor:
 *                  3 = >100 M€/año · 2 = 10-100 M€ · 1 = <10 M€ · null = sin datos
 *
 * Fuentes: cuentas anuales, registros mercantiles, informes públicos y las
 * fichas de transparencia del propio config. La capacidad es una estimación
 * por orden de magnitud, no una cifra auditada. Correcciones bienvenidas.
 */

define('PRISMA_MAPA_TIPOS', array(
    'conglomerado' => 'Grupo empresarial',
    'familiar'     => 'Propiedad familiar',
    'inversor'     => 'Inversores privados',
    'publico'      => 'Público / estatal',
    'lectores'     => 'Lectores / cooperativa',
    'fundacion'    => 'Fundación / nonprofit',
    'no_declarado' => 'No declarado',
));

define('PRISMA_MAPA_MEDIOS', array(
    // ── España ─────────────────────────────────────────────────────
    'El Salto' => array(
        'propiedad' => 'Cooperativa de trabajadores y lectores',
        'financiacion' => '~10.000 socios (70%); publicidad limitada por estatutos',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'Público' => array(
        'propiedad' => 'Display Connectors, S.L.',
        'financiacion' => 'Publicidad y socios',
        'tipo' => 'inversor', 'capacidad' => 1,
    ),
    'elDiario.es' => array(
        'propiedad' => '70% de sus trabajadores',
        'financiacion' => '75.000+ socios (~35%) y publicidad; sin deuda',
        'tipo' => 'lectores', 'capacidad' => 2,
    ),
    'CTXT' => array(
        'propiedad' => 'Cooperativa de periodistas',
        'financiacion' => 'Socios suscriptores y donaciones',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'La Marea' => array(
        'propiedad' => 'Cooperativa MásPúblico',
        'financiacion' => 'Socios suscriptores',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'El País' => array(
        'propiedad' => 'Grupo PRISA (cotiza; Amber Capital, Vivendi, Slim…)',
        'financiacion' => 'Publicidad, suscripciones y bolsa',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'InfoLibre' => array(
        'propiedad' => 'Ediciones Prensa Libre (periodistas fundadores y socios)',
        'financiacion' => 'Suscripciones',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'EFE' => array(
        'propiedad' => 'Estado español (SEPI, 100%)',
        'financiacion' => 'Presupuesto público y servicios a medios',
        'tipo' => 'publico', 'capacidad' => 2,
    ),
    '20minutos' => array(
        'propiedad' => 'Grupo Henneo (familia Yarza 60%, Ibercaja 40%)',
        'financiacion' => 'Publicidad (gratuito)',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'Newtral' => array(
        'propiedad' => 'Ana Pastor (100%)',
        'financiacion' => 'Producción TV (Atresmedia), verificación y fondos UE',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'El Confidencial' => array(
        'propiedad' => 'Titania (J.A. Sánchez 43%, Juan Perea 15%)',
        'financiacion' => 'Publicidad y suscripciones',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'La Vanguardia' => array(
        'propiedad' => 'Grupo Godó (familia Godó desde 1887)',
        'financiacion' => 'Publicidad y suscripciones',
        'tipo' => 'familiar', 'capacidad' => 3,
    ),
    'The Objective' => array(
        'propiedad' => 'Paula Quinteros (90%)',
        'financiacion' => 'Ampliaciones de capital (pérdidas acumuladas)',
        'tipo' => 'inversor', 'capacidad' => 1,
    ),
    'ABC' => array(
        'propiedad' => 'Vocento (cotiza; familias Ybarra, Bergareche, Luca de Tena)',
        'financiacion' => 'Publicidad, suscripciones y bolsa',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'El Mundo' => array(
        'propiedad' => 'Unidad Editorial → RCS MediaGroup (Italia)',
        'financiacion' => 'Publicidad, suscripciones y grupo',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'La Razón' => array(
        'propiedad' => 'Grupo Planeta (familias Lara y DeAgostini)',
        'financiacion' => 'Publicidad y grupo (Planeta/Atresmedia)',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'Libertad Digital' => array(
        'propiedad' => 'Jiménez Losantos, Recarte, Baldasano (~11% c/u)',
        'financiacion' => 'Publicidad y suscripciones (esRadio)',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'OKDIARIO' => array(
        'propiedad' => 'Eduardo Inda',
        'financiacion' => 'Publicidad (93%); préstamo público ENISA inicial',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),

    // ── Europa ─────────────────────────────────────────────────────
    'Libération' => array(
        'propiedad' => 'Fondo de dotación (aportado por Altice/Patrick Drahi)',
        'financiacion' => 'Suscripciones y publicidad',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'Il Manifesto' => array(
        'propiedad' => 'Cooperativa de periodistas (desde 1969)',
        'financiacion' => 'Suscriptores y quiosco',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'The Guardian' => array(
        'propiedad' => 'Scott Trust (fundación, sin accionistas)',
        'financiacion' => 'Donaciones de lectores, publicidad y fondo del Trust',
        'tipo' => 'fundacion', 'capacidad' => 3,
    ),
    'Le Monde' => array(
        'propiedad' => 'Xavier Niel, Pigasse, Křetínský (redacción con veto)',
        'financiacion' => 'Suscripciones y publicidad',
        'tipo' => 'inversor', 'capacidad' => 3,
    ),
    'La Repubblica' => array(
        'propiedad' => 'GEDI → Exor (familia Agnelli/Elkann)',
        'financiacion' => 'Publicidad, suscripciones y grupo',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'Süddeutsche Zeitung' => array(
        'propiedad' => 'SWMH (Medien Union, familia Schaub)',
        'financiacion' => 'Suscripciones y publicidad',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'Euronews' => array(
        'propiedad' => 'Alpac Capital (Portugal)',
        'financiacion' => 'Publicidad y fondos institucionales europeos',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'Politico Europe' => array(
        'propiedad' => 'Axel Springer (KKR 82%, Friede Springer)',
        'financiacion' => 'Suscripciones pro, publicidad y grupo',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'DW' => array(
        'propiedad' => 'Estado federal alemán',
        'financiacion' => '100% presupuesto federal',
        'tipo' => 'publico', 'capacidad' => 3,
    ),
    'Der Spiegel' => array(
        'propiedad' => '50,5% empleados; familia Augstein; Bertelsmann',
        'financiacion' => 'Suscripciones y publicidad',
        'tipo' => 'familiar', 'capacidad' => 3,
    ),
    'Financial Times' => array(
        'propiedad' => 'Nikkei Inc. (Japón)',
        'financiacion' => 'Suscripciones y grupo',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'Notes from Poland' => array(
        'propiedad' => 'Independiente (Daniel Tilles)',
        'financiacion' => 'Suscripciones y donaciones',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'The Telegraph' => array(
        'propiedad' => 'RedBird IMI (consorcio inversor, desde 2024)',
        'financiacion' => 'Suscripciones y publicidad',
        'tipo' => 'inversor', 'capacidad' => 3,
    ),
    'Spiked' => array(
        'propiedad' => 'Spiked Ltd.',
        'financiacion' => 'Donaciones (incl. Charles Koch Foundation)',
        'tipo' => 'inversor', 'capacidad' => 1,
    ),
    'Brussels Signal' => array(
        'propiedad' => 'Remedia Corp (Patrick Egan)',
        'financiacion' => 'Capital inicial de origen no revelado',
        'tipo' => 'no_declarado', 'capacidad' => 1,
    ),
    'UnHerd' => array(
        'propiedad' => 'Paul Marshall (hedge fund Marshall Wace)',
        'financiacion' => 'Dotación del propietario y suscripciones',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'The European Conservative' => array(
        'propiedad' => 'Nonprofit (Hungría)',
        'financiacion' => 'Fundación Batthyány Lajos (fondos estatales húngaros)',
        'tipo' => 'publico', 'capacidad' => 1,
    ),
    'Remix News' => array(
        'propiedad' => 'FWD Affairs (Patrick Egan; Árpád Habony)',
        'financiacion' => 'Fundación Batthyány Lajos (fondos estatales húngaros)',
        'tipo' => 'publico', 'capacidad' => 1,
    ),
    'Hungary Today' => array(
        'propiedad' => 'Ecosistema mediático afín a Fidesz',
        'financiacion' => 'Entorno gubernamental húngaro',
        'tipo' => 'publico', 'capacidad' => 1,
    ),

    // ── Global ─────────────────────────────────────────────────────
    'BBC' => array(
        'propiedad' => 'Pública británica (Royal Charter)',
        'financiacion' => 'Canon (licence fee) de los hogares británicos',
        'tipo' => 'publico', 'capacidad' => 3,
    ),
    'Al Jazeera' => array(
        'propiedad' => 'Estado de Qatar',
        'financiacion' => '100% estatal',
        'tipo' => 'publico', 'capacidad' => 3,
    ),
    'Asia Times' => array(
        'propiedad' => 'No declarada públicamente',
        'financiacion' => 'No declarada',
        'tipo' => 'no_declarado', 'capacidad' => null,
    ),
    'National Review' => array(
        'propiedad' => 'National Review Institute (nonprofit)',
        'financiacion' => 'Suscripciones, donaciones (incl. Koch, Bradley)',
        'tipo' => 'fundacion', 'capacidad' => 2,
    ),
));

/**
 * Devuelve los metadatos del mapa para un medio, o null si no hay ficha.
 */
function mapa_datos_medio(string $medio) {
    $m = PRISMA_MAPA_MEDIOS;
    return isset($m[$medio]) ? $m[$medio] : null;
}

/**
 * Icono de capacidad económica.
 */
function mapa_icono_capacidad($capacidad): string {
    if ($capacidad === null) return '<span style="color:var(--text-faint)" title="Sin datos públicos">€?</span>';
    $n = max(1, min(3, (int)$capacidad));
    $labels = array(1 => 'Menos de 10 M€/año (grupo editor)', 2 => 'Entre 10 y 100 M€/año (grupo editor)', 3 => 'Más de 100 M€/año (grupo editor)');
    $filled = str_repeat('€', $n);
    $empty  = str_repeat('€', 3 - $n);
    return '<span title="' . $labels[$n] . '" style="font-family:Inter,Arial,sans-serif;font-weight:700;letter-spacing:0.05em">'
        . '<span style="color:var(--accent)">' . $filled . '</span>'
        . '<span style="color:var(--text-faintest)">' . $empty . '</span></span>';
}
