<?php
/**
 * PolarPrisma — Metadatos estructurados de propiedad y financiación por medio.
 *
 * Complementan la ficha larga ('transparencia' en config.php) con campos
 * cortos y comparables para el Mapa ideológico (mapa.php):
 *
 *   propiedad    Quién es el dueño, en pocas palabras.
 *   financiacion Modelo de ingresos dominante, en pocas palabras.
 *   info         Dato complementario (+Info): contexto que NO repite los dos
 *                campos anteriores — qué más posee el grupo, historia,
 *                escala, hechos documentados relevantes.
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
        'info' => 'Nació en 2017 de la fusión del periódico Diagonal con una veintena de medios locales; funciona como red confederal con ediciones territoriales.',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'Público' => array(
        'propiedad' => 'Display Connectors, S.L.',
        'financiacion' => 'Publicidad y socios',
        'info' => 'Fundado en 2007 como diario impreso, abandonó el papel en 2012; históricamente vinculado al entorno empresarial de Jaume Roures (Mediapro).',
        'tipo' => 'inversor', 'capacidad' => 1,
    ),
    'elDiario.es' => array(
        'propiedad' => '70% de sus trabajadores',
        'financiacion' => '75.000+ socios (~35%) y publicidad; sin deuda',
        'info' => 'Publica sus cuentas cada año y reparte parte del beneficio entre la plantilla; uno de los pocos nativos digitales españoles rentables desde casi el inicio.',
        'tipo' => 'lectores', 'capacidad' => 2,
    ),
    'CTXT' => array(
        'propiedad' => 'Cooperativa de periodistas',
        'financiacion' => 'Socios suscriptores y donaciones',
        'info' => 'Revista Contexto, fundada en 2015 por veteranos de El País y Público; renuncia a la publicidad programática por política editorial.',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'La Marea' => array(
        'propiedad' => 'Cooperativa MásPúblico',
        'financiacion' => 'Socios suscriptores',
        'info' => 'La cooperativa nació del cierre de la edición en papel de Público (2012); edita también la revista de crisis climática Climática.',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'El País' => array(
        'propiedad' => 'Grupo PRISA (cotiza; Amber Capital, Vivendi, Slim…)',
        'financiacion' => 'Publicidad, suscripciones y bolsa',
        'info' => 'PRISA controla también la Cadena SER, el diario AS y El HuffPost España; mayor difusión digital de la prensa en español y ~1,5 M de suscriptores del grupo.',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'InfoLibre' => array(
        'propiedad' => 'Ediciones Prensa Libre (periodistas fundadores y socios)',
        'financiacion' => 'Suscripciones',
        'info' => 'Fundado en 2013 por exdirectivos de El Mundo; el diario francés Mediapart participa en su capital y comparte modelo de pago sin publicidad invasiva.',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'EFE' => array(
        'propiedad' => 'Estado español (SEPI, 100%)',
        'financiacion' => 'Presupuesto público y servicios a medios',
        'info' => 'Mayor agencia de noticias en español, ~3.000 empleados en 120 países; sus teletipos alimentan a buena parte de la prensa española de todos los cuadrantes.',
        'tipo' => 'publico', 'capacidad' => 2,
    ),
    'Europa Press' => array(
        'propiedad' => 'Familia Martín de Cabiedes',
        'financiacion' => 'Venta de servicios informativos a medios, empresas e instituciones',
        'info' => 'La mayor agencia privada española; entre sus clientes hay administraciones públicas de todo signo, una fuente de ingresos institucional que declara sin detallar.',
        'tipo' => 'familiar', 'capacidad' => 2,
    ),
    '20minutos' => array(
        'propiedad' => 'Grupo Henneo (familia Yarza 60%, Ibercaja 40%)',
        'financiacion' => 'Publicidad (gratuito)',
        'info' => 'Henneo posee también Heraldo de Aragón y Business Insider España, y es uno de los mayores grupos tecnológico-editoriales aragoneses (Hiberus).',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'Newtral' => array(
        'propiedad' => 'Ana Pastor (100%)',
        'financiacion' => 'Producción TV (Atresmedia), verificación y fondos UE',
        'info' => 'Produce El Objetivo (laSexta) y es verificador acreditado por la red internacional IFCN; también verifica contenido para plataformas como Meta y TikTok.',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'El Confidencial' => array(
        'propiedad' => 'Titania (J.A. Sánchez 43%, Juan Perea 15%)',
        'financiacion' => 'Publicidad y suscripciones',
        'info' => 'Uno de los nativos digitales más rentables de España (~40 M€ de facturación); referencia en periodismo económico y de investigación (papeles de Panamá, caso Cifuentes).',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'La Vanguardia' => array(
        'propiedad' => 'Grupo Godó (familia Godó desde 1887)',
        'financiacion' => 'Publicidad y suscripciones',
        'info' => 'Decano de la prensa catalana (1881); Godó posee también RAC1 (radio líder en Cataluña) y Mundo Deportivo.',
        'tipo' => 'familiar', 'capacidad' => 3,
    ),
    'The Objective' => array(
        'propiedad' => 'Paula Quinteros (90%)',
        'financiacion' => 'Ampliaciones de capital (pérdidas acumuladas)',
        'info' => 'Nació en 2013 como plataforma de blogs y se relanzó en 2021 como diario generalista, fichando firmas de la órbita liberal-conservadora.',
        'tipo' => 'inversor', 'capacidad' => 1,
    ),
    'ABC' => array(
        'propiedad' => 'Vocento (cotiza; familias Ybarra, Bergareche, Luca de Tena)',
        'financiacion' => 'Publicidad, suscripciones y bolsa',
        'info' => 'Vocento posee además El Correo, El Diario Vasco y una decena de cabeceras regionales; facturación del grupo ~340 M€.',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'El Mundo' => array(
        'propiedad' => 'Unidad Editorial → RCS MediaGroup (Italia)',
        'financiacion' => 'Publicidad, suscripciones y grupo',
        'info' => 'Unidad Editorial edita también Expansión y Marca (el diario más leído de España); RCS pertenece a la órbita del financiero Urbano Cairo.',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'La Razón' => array(
        'propiedad' => 'Grupo Planeta (familias Lara y DeAgostini)',
        'financiacion' => 'Publicidad y grupo (Planeta/Atresmedia)',
        'info' => 'Planeta es el mayor grupo editorial en español y controla Atresmedia (Antena 3, laSexta, Onda Cero), el mayor grupo audiovisual privado del país.',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'Libertad Digital' => array(
        'propiedad' => 'Jiménez Losantos, Recarte, Baldasano (~11% c/u)',
        'financiacion' => 'Publicidad y suscripciones (esRadio)',
        'info' => 'Su capital está repartido entre ~7.000 pequeños accionistas; una sentencia firme documentó 200.000€ de la caja B del PP en su financiación temprana (2004).',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'OKDIARIO' => array(
        'propiedad' => 'Eduardo Inda',
        'financiacion' => 'Publicidad (93%); préstamo público ENISA inicial',
        'info' => 'Entre los digitales españoles con más tráfico con una redacción comparativamente pequeña; su fundador es tertuliano habitual de los principales espacios televisivos.',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),

    // ── Europa ─────────────────────────────────────────────────────
    'Libération' => array(
        'propiedad' => 'Fondo de dotación (aportado por Altice/Patrick Drahi)',
        'financiacion' => 'Suscripciones y publicidad',
        'info' => 'Fundado por Jean-Paul Sartre en 1973; desde 2020 su cabecera está en un fondo sin ánimo de lucro que formalmente la aísla de los negocios de telecomunicaciones de Drahi.',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'Il Manifesto' => array(
        'propiedad' => 'Cooperativa de periodistas (desde 1969)',
        'financiacion' => 'Suscriptores y quiosco',
        'info' => 'Se autodefine «quotidiano comunista» en cabecera; sobrevive apoyado en la ley italiana de ayudas a cooperativas editoriales.',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'The Guardian' => array(
        'propiedad' => 'Scott Trust (fundación, sin accionistas)',
        'financiacion' => 'Donaciones de lectores, publicidad y fondo del Trust',
        'info' => 'El fondo del Scott Trust (~1.300 M£) blinda su operación; pionero del modelo de apoyo voluntario sin muro de pago, con >1 M de contribuyentes.',
        'tipo' => 'fundacion', 'capacidad' => 3,
    ),
    'Le Monde' => array(
        'propiedad' => 'Xavier Niel, Pigasse, Křetínský (redacción con veto)',
        'financiacion' => 'Suscripciones y publicidad',
        'info' => 'La sociedad de redactores tiene derecho de veto sobre el nombramiento del director — un contrapoder interno poco común; ~600.000 suscriptores digitales.',
        'tipo' => 'inversor', 'capacidad' => 3,
    ),
    'La Repubblica' => array(
        'propiedad' => 'GEDI → Exor (familia Agnelli/Elkann)',
        'financiacion' => 'Publicidad, suscripciones y grupo',
        'info' => 'Exor, el holding de los Agnelli, posee también The Economist (~43%), Ferrari, Stellantis y la Juventus.',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'Süddeutsche Zeitung' => array(
        'propiedad' => 'SWMH (Medien Union, familia Schaub)',
        'financiacion' => 'Suscripciones y publicidad',
        'info' => 'Mayor diario de calidad del sur de Alemania; SWMH agrupa una quincena de cabeceras regionales alemanas.',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'Euronews' => array(
        'propiedad' => 'Alpac Capital (Portugal)',
        'financiacion' => 'Publicidad y fondos institucionales europeos',
        'info' => 'La Comisión Europea aporta una parte relevante de sus ingresos vía contratos de cobertura; el CEO de Alpac es hijo de un asesor de Viktor Orbán, vínculo señalado por la prensa europea.',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'Politico Europe' => array(
        'propiedad' => 'Axel Springer (KKR 82%, Friede Springer)',
        'financiacion' => 'Suscripciones pro, publicidad y grupo',
        'info' => 'Springer posee también BILD, WELT y Business Insider; KKR es un fondo de private equity estadounidense — el mayor grupo de prensa europeo tiene hoy control financiero americano.',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'DW' => array(
        'propiedad' => 'Estado federal alemán',
        'financiacion' => '100% presupuesto federal',
        'info' => 'Emite en más de 30 idiomas con mandato exclusivamente exterior: tiene prohibida por ley la difusión doméstica en Alemania.',
        'tipo' => 'publico', 'capacidad' => 3,
    ),
    'Der Spiegel' => array(
        'propiedad' => '50,5% empleados; familia Augstein; Bertelsmann',
        'financiacion' => 'Suscripciones y publicidad',
        'info' => 'La participación mayoritaria de la plantilla es única entre los grandes medios europeos; referencia del periodismo de investigación alemán desde 1947.',
        'tipo' => 'familiar', 'capacidad' => 3,
    ),
    'Financial Times' => array(
        'propiedad' => 'Nikkei Inc. (Japón)',
        'financiacion' => 'Suscripciones y grupo',
        'info' => 'Nikkei pagó 1.320 M€ a Pearson en 2015; supera el millón de suscriptores digitales y obtiene la mayoría de ingresos del pago por lectura, no de publicidad.',
        'tipo' => 'conglomerado', 'capacidad' => 3,
    ),
    'Notes from Poland' => array(
        'propiedad' => 'Independiente (Daniel Tilles)',
        'financiacion' => 'Suscripciones y donaciones',
        'info' => 'Proyecto académico-periodístico en inglés sobre Polonia dirigido por un historiador británico afincado en Cracovia; estructura mínima sin publicidad.',
        'tipo' => 'lectores', 'capacidad' => 1,
    ),
    'The Telegraph' => array(
        'propiedad' => 'RedBird IMI (consorcio inversor, desde 2024)',
        'financiacion' => 'Suscripciones y publicidad',
        'info' => 'Su compra por capital emiratí (IMI, jeque Mansour) fue vetada por el Parlamento británico; la propiedad sigue en reordenación desde entonces.',
        'tipo' => 'inversor', 'capacidad' => 3,
    ),
    'Spiked' => array(
        'propiedad' => 'Spiked Ltd.',
        'financiacion' => 'Donaciones (incl. Charles Koch Foundation)',
        'info' => 'Heredero directo de Living Marxism, la revista del Revolutionary Communist Party británico — una trayectoria de extremo a extremo del espectro poco habitual.',
        'tipo' => 'inversor', 'capacidad' => 1,
    ),
    'Brussels Signal' => array(
        'propiedad' => 'Remedia Corp (Patrick Egan)',
        'financiacion' => 'Capital inicial de origen no revelado',
        'info' => 'Lanzado en 2023 para «cubrir la UE desde la derecha»; su editor procede de revistas conservadoras británicas y no publica quién aporta el capital.',
        'tipo' => 'no_declarado', 'capacidad' => 1,
    ),
    'UnHerd' => array(
        'propiedad' => 'Paul Marshall (hedge fund Marshall Wace)',
        'financiacion' => 'Dotación del propietario y suscripciones',
        'info' => 'Marshall financia también GB News y compró The Spectator en 2024 — un ecosistema mediático conservador británico en construcción con capital de un solo patrono.',
        'tipo' => 'inversor', 'capacidad' => 2,
    ),
    'The European Conservative' => array(
        'propiedad' => 'Nonprofit (Hungría)',
        'financiacion' => 'Fundación Batthyány Lajos (fondos estatales húngaros)',
        'info' => 'Parte de la red de medios en inglés impulsada desde Budapest para proyectar el conservadurismo nacional húngaro en Europa; RSF la documenta como vehículo de influencia gubernamental.',
        'tipo' => 'publico', 'capacidad' => 1,
    ),
    'Remix News' => array(
        'propiedad' => 'FWD Affairs (Patrick Egan; Árpád Habony)',
        'financiacion' => 'Fundación Batthyány Lajos (fondos estatales húngaros)',
        'info' => 'Habony es el estratega de comunicación no oficial de Viktor Orbán; publica en cinco idiomas seleccionando noticias de interés para el nacional-conservadurismo centroeuropeo.',
        'tipo' => 'publico', 'capacidad' => 1,
    ),
    'Hungary Today' => array(
        'propiedad' => 'Ecosistema mediático afín a Fidesz',
        'financiacion' => 'Entorno gubernamental húngaro',
        'info' => 'Editado por la fundación Friends of Hungary, creada en 2014 para mejorar la imagen exterior del país; cubre Hungría en inglés con línea pro-gubernamental.',
        'tipo' => 'publico', 'capacidad' => 1,
    ),

    // ── Global ─────────────────────────────────────────────────────
    'BBC' => array(
        'propiedad' => 'Pública británica (Royal Charter)',
        'financiacion' => 'Canon (licence fee) de los hogares británicos',
        'info' => 'Presupuesto ~5.000 M£; el World Service recibe además fondos del Foreign Office, algo que la propia BBC declara en sus memorias.',
        'tipo' => 'publico', 'capacidad' => 3,
    ),
    'Al Jazeera' => array(
        'propiedad' => 'Estado de Qatar',
        'financiacion' => '100% estatal',
        'info' => 'Creada en 1996 por el emir de Qatar; su edición en inglés mantiene una línea más internacional que la árabe, y varios países de la región la han vetado en crisis diplomáticas.',
        'tipo' => 'publico', 'capacidad' => 3,
    ),
    'Asia Times' => array(
        'propiedad' => 'No declarada públicamente',
        'financiacion' => 'No declarada',
        'info' => 'Cabecera nacida en 1995 en Bangkok y renacida como digital en 2016 con inversores no públicos; cubre Asia-Pacífico con perspectiva geopolítica regional.',
        'tipo' => 'no_declarado', 'capacidad' => null,
    ),
    'National Review' => array(
        'propiedad' => 'National Review Institute (nonprofit)',
        'financiacion' => 'Suscripciones, donaciones (incl. Koch, Bradley)',
        'info' => 'Fundada en 1955 por William F. Buckley Jr. para articular el conservadurismo estadounidense moderno; el instituto publica sus grandes donantes en la memoria anual.',
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
