<?php
/**
 * Prisma — Configuración central.
 *
 * Carga .env si existe y expone toda la config como array.
 */

// Cargar .env
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (!getenv($key)) putenv("$key=$val");
    }
}

// Using $GLOBALS instead of define() — PHP 7.4 on shared hosting
// silently fails with deeply nested array constants.

/**
 * Access config from anywhere. Replaces the old PRISMA_CONFIG constant.
 */
function prisma_cfg() {
    return $GLOBALS['_PRISMA_CFG'];
}

$GLOBALS['_PRISMA_CFG'] = array(

    // ── API Anthropic ───────────────────────────────────────────────
    'anthropic_api_key'   => getenv('ANTHROPIC_API_KEY') ?: '',
    'model_synth'         => 'claude-sonnet-4-6',
    'model_audit'         => 'claude-sonnet-4-6',
    'model_triage'        => 'claude-haiku-4-5-20251001',

    // ── Ingest ──────────────────────────────────────────────────────
    'ingest_key'          => getenv('PRISMA_INGEST_KEY') ?: '',

    // ── Panel ───────────────────────────────────────────────────────
    'panel_pass'          => getenv('PRISMA_PANEL_PASS') ?: 'prisma2026',

    // ── Límites de coste ────────────────────────────────────────────
    'daily_budget_usd'    => 3.00,

    // ── Publicación ─────────────────────────────────────────────────
    'timezone'            => 'Europe/Madrid',
    'articulos_dia'       => 1,
    'min_cuadrantes'      => 3,             // Mínimo de cuadrantes para ir al pipeline Sonnet
    'umbral_tension'      => 0.40,          // H mínimo para ser candidato a análisis (v2: era 0.55)

    // ── Scoring v2 ──────────────────────────────────────────────
    'scoring_alpha'       => 0.4,
    'scoring_beta'        => 0.6,
    'scoring_gamma'       => 0.15,
    'scoring_mapeo'       => 'B',
    'gate_haiku_enabled'  => true,
    'gate_haiku_cache'    => true,
    // gate_haiku_batch_api deferred for later iteration (Anthropic Batch API, 50% discount)

    // ── Listas de filtrado scoring v2 ────────────────────────────
    'lista_negativa' => array(
        // Deportes
        'laliga', 'champions', 'premier league', 'fichaje', 'jornada',
        'penalti', 'futbol', 'baloncesto',
        'formula 1', 'moto gp', 'ciclismo',
        'camp nou', 'bernabeu', 'mestalla', 'mutua madrid open', 'atp', 'wta',
        // Lotería
        'bonoloto', 'primitiva', 'euromillones', 'loteria', 'sorteo',
        'numero premiado',
        // Entretenimiento
        'concierto', 'gira mundial', 'alfombra roja', 'look de',
        'red carpet', 'coachella', 'reality', 'gran hermano', 'eurovision',
        // Curiosidades
        'curiosidad', 'no creeras', 'verdad sobre',
        // Meteorología rutinaria
        'prevision meteorologica', 'temperaturas hoy', 'lluvias para',
    ),
    'lista_positiva' => array(
        // Instituciones
        'congreso', 'senado', 'parlamento', 'tribunal constitucional',
        'tribunal supremo', 'audiencia nacional', 'gobierno', 'moncloa',
        'comision europea', 'parlamento europeo', 'otan', 'onu', 'fmi',
        // Cargos
        'presidente', 'ministro', 'consejero', 'alcalde', 'comisario',
        'fiscal', 'juez', 'magistrado',
        // Partidos
        'psoe', 'pp', 'vox', 'sumar', 'podemos', 'erc', 'junts',
        'pnv', 'bildu', 'ciudadanos',
        // Actores
        'sanchez', 'feijoo', 'abascal', 'diaz', 'puigdemont',
        'trump', 'biden', 'macron', 'von der leyen',
        // Conceptos policy
        'presupuestos', 'decreto', 'ley organica', 'reforma',
        'regulacion', 'sancion', 'embargo', 'tratado',
    ),

    // ── RSS por ámbito y cuadrante ──────────────────────────────────
    // Formato: array('Nombre', 'URL', 'Nota de transparencia')
    // La nota de transparencia se publica en la página pública (A8).
    'fuentes' => array(
        'españa' => array(
            'izquierda-populista' => array(
                // Diario Red: 403 Forbidden (bot block) — removed until URL change
                array('El Salto', 'https://www.elsaltodiario.com/general/feed',
                    'Cooperativa de trabajadores y lectores. 70% financiado por ~10.000 socios suscriptores. Publicidad limitada al 20% por estatutos. Sin accionistas externos.'),
            ),
            'izquierda' => array(
                // Público: no RSS feed found in source — may have discontinued RSS
                array('elDiario.es', 'https://www.eldiario.es/rss/',
                    '70% propiedad de sus trabajadores. ~35% de ingresos por 75.000+ socios lectores, resto publicidad. Sin deuda. Fundado 2012 por Ignacio Escolar.'),
            ),
            'centro-izquierda' => array(
                array('El País', 'https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/portada',
                    'Grupo PRISA. Accionistas principales: Amber Capital (Joseph Oughourlian, 29,6%), Vivendi (11,9%), Global Alconaba (ex-Telefónica, 7,6%), familia Polanco (7,3%), Carlos Slim (6,5%). Cotiza en bolsa.'),
                // InfoLibre: no RSS feed found in source — may have discontinued RSS
            ),
            'centro' => array(
                // EFE: 429 rate limit — kept but expect sporadic failures
                array('EFE', 'https://efe.com/feed/',
                    'Agencia estatal española de noticias. Propiedad 100% de la SEPI (Sociedad Estatal de Participaciones Industriales). Financiación pública.'),
                array('20minutos', 'https://www.20minutos.es/rss/',
                    'Grupo Henneo. 60% familia Yarza, 40% Ibercaja. Henneo posee también Heraldo de Aragón y Business Insider España.'),
                array('Newtral', 'https://www.newtral.es/feed/',
                    'Fundada y 100% propiedad de Ana Pastor. Ingresos por producción audiovisual para La Sexta (Atresmedia) y verificación. Ha recibido financiación europea (Horizonte 2020) y créditos públicos (CERSA).'),
                array('El Confidencial', 'https://rss.elconfidencial.com/espana/',
                    'Titania Compañía Editorial. 43% José Antonio Sánchez (fundador), 15% Juan Perea (ex-Telefónica). Modelo mixto publicidad + suscripciones. Fundado 2001.'),
            ),
            'centro-derecha' => array(
                array('La Vanguardia', 'https://www.lavanguardia.com/rss/home.xml',
                    'Grupo Godó. 100% familia Godó desde 1887. Presidencia cedida a Carlos Godó Valls (5ª generación). Sin accionistas externos.'),
                array('The Objective', 'https://theobjective.com/feed/',
                    '90% Paula Quinteros (fundadora), 10% repartido entre 13 socios minoritarios. Financiado por ampliaciones de capital sucesivas. Pérdidas acumuladas >5M€.'),
            ),
            'derecha' => array(
                array('ABC', 'https://www.abc.es/rss/2.0/portada/',
                    'Grupo Vocento (cotiza en bolsa). Principales accionistas: familias vascas Ybarra y Bergareche, familia Luca de Tena (10,1%, fundadores de ABC). Fusión 2001 Grupo Correo + Prensa Española.'),
                array('El Mundo', 'https://e00-elmundo.uecdn.es/elmundo/rss/portada.xml',
                    'Unidad Editorial, filial de RCS MediaGroup (Italia, cotiza en Milán). RCS posee también Corriere della Sera y Gazzetta dello Sport.'),
                array('La Razón', 'https://www.larazon.es/?outputType=xml',
                    'Grupo Planeta. Propiedad de las familias Lara (48%) y DeAgostini (Italia). Planeta posee también Atresmedia (Antena 3, La Sexta) y editorial Planeta.'),
            ),
            'derecha-populista' => array(
                array('Libertad Digital', 'https://feeds.feedburner.com/libertaddigital/portada',
                    'Fundado 2000 por Federico Jiménez Losantos. Accionistas de referencia: Losantos, Alberto Recarte, Arturo Baldasano (~11% cada uno). Sentencia judicial probó financiación de 200.000€ procedente de la caja B del PP (2004).'),
                // El Debate: 403 Forbidden (bot block) — removed until URL change
                array('OKDIARIO', 'https://okdiario.com/feed',
                    'Fundado 2015 por Eduardo Inda (ex-subdirector de El Mundo). Financiación inicial: 500.000€ propios + 300.000€ préstamo ENISA (ente público). 93% ingresos por publicidad.'),
            ),
        ),
        'europa' => array(
            'izquierda' => array(
                array('Libération', 'http://rss.liberation.fr/rss/latest/',
                    'Fundado 1973 por Jean-Paul Sartre. Accionista mayoritario: fondo SFR Presse (Patrick Drahi/Altice). Modelo mixto suscripciones + publicidad.'),
                array('Il Manifesto', 'https://ilmanifesto.it/feed',
                    'Cooperativa de periodistas italiana. Fundado 1969. Financiación por suscriptores y venta en quiosco. Sin propietario corporativo.'),
            ),
            'centro-izquierda' => array(
                array('The Guardian', 'https://www.theguardian.com/world/europe-news/rss',
                    'Propiedad del Scott Trust Limited (fundación sin ánimo de lucro desde 1936). Sin accionistas ni propietario privado. Financiado por donaciones de lectores, publicidad y fondo de inversión del Trust.'),
                array('Le Monde', 'https://www.lemonde.fr/europe/rss_full.xml',
                    'Grupo Le Monde. Accionistas: Xavier Niel (28,5%), Matthieu Pigasse y fondo checo Daniel Křetínský. Sociedad de redactores tiene poder de veto en nombramientos editoriales.'),
                array('La Repubblica', 'https://www.repubblica.it/rss/homepage/rss2.0.xml',
                    'Grupo GEDI (antes L\'Espresso). Propiedad de Exor (holding de la familia Agnelli/Elkann). Exor posee también The Economist y Ferrari.'),
                array('Süddeutsche Zeitung', 'https://rss.sueddeutsche.de/rss/Topthemen',
                    'Südwestdeutsche Medien Holding (SWMH). Propiedad de Medien Union (familia Schaub) y Grupo Stuttgarter Zeitung. Capital alemán regional.'),
            ),
            'centro' => array(
                array('Euronews', 'https://www.euronews.com/rss?level=theme&name=news',
                    'Propiedad mayoritaria de Alpac Capital (Portugal). Anteriormente participado por NBC Universal y fondos europeos. Sede en Lyon, emite en 12 idiomas.'),
                array('Politico Europe', 'https://www.politico.eu/feed/',
                    'Propiedad de Axel Springer SE (Alemania), a su vez propiedad del fondo KKR (82,4%) y Friede Springer (12,7%). Springer posee también Bild e Insider.'),
                array('DW', 'https://rss.dw.com/rdf/rss-en-eu',
                    'Deutsche Welle. Medio público internacional de Alemania. 100% financiado con presupuesto federal alemán. Mandato legal de difusión internacional.'),
            ),
            'centro-derecha' => array(
                array('Der Spiegel', 'https://www.spiegel.de/international/index.rss',
                    '50,5% propiedad de los empleados (KG Beteiligungsgesellschaft). 25,5% familia fundadora Augstein. 24% Gruner + Jahr (Bertelsmann). Estructura única de copropiedad de redacción.'),
                array('Financial Times', 'https://www.ft.com/world/europe?format=rss',
                    'Propiedad de Nikkei Inc. (Japón) desde 2015. Adquirido por 1.320M€ a Pearson. Principal diario financiero global en inglés.'),
                array('Notes from Poland', 'https://notesfrompoland.com/feed/',
                    'Medio independiente fundado por Daniel Tilles (académico británico en Polonia). Financiado por suscripciones y donaciones de lectores. Sin propietario corporativo.'),
            ),
            'derecha' => array(
                array('The Telegraph', 'https://www.telegraph.co.uk/rss.xml',
                    'Propiedad de RedBird IMI (consorcio inversor) desde 2024, tras veto parlamentario a la compra por parte de un fondo emiratí. Anteriormente familia Barclay.'),
                array('Spiked', 'https://www.spiked-online.com/feed/',
                    'Sucesor de Living Marxism (revista del Revolutionary Communist Party, disuelto 1996). Ha recibido 300.000$ de la Charles Koch Foundation (2016-2018) para programas de "libertad de expresión". Editor: Brendan O\'Neill.'),
                array('Brussels Signal', 'https://brusselssignal.eu/feed/',
                    'Fundado 2023 por Patrick Egan (consultor político republicano estadounidense) vía Remedia Corp. Capital inicial: 275.000€ de fuente no revelada. Editor: Michael Mosbacher (ex-editor de revistas conservadoras británicas).'),
            ),
            'derecha-populista' => array(
                array('UnHerd', 'https://unherd.com/feed/atom/',
                    'Fundado 2017. Propiedad y financiación principal de Paul Marshall (cofundador del hedge fund Marshall Wace, patrimonio ~1.000M£). Modelo de suscripción + dotación de Marshall.'),
                array('The European Conservative', 'https://europeanconservative.com/feed/',
                    'Nonprofit registrada en Hungría (2021). Financiada por la Fundación Batthyány Lajos (BLA), que recibió 4,3M€ de fondos estatales húngaros vinculados a Fidesz/Orbán. RSF lo documenta como vehículo de influencia gubernamental húngara.'),
                array('Remix News', 'https://rmx.news/feed/',
                    'Fundado por Patrick Egan (FWD Affairs Kft, Budapest). Financiación parcial de la Fundación Batthyány Lajos (misma que European Conservative). Accionista: Árpád Habony, asesor no oficial de Viktor Orbán. Documentado como proyecto de influencia mediática del gobierno húngaro.'),
                array('Hungary Today', 'https://hungarytoday.hu/feed/',
                    'Parte del ecosistema mediático húngaro en inglés. Edita noticias de Hungría con perspectiva pro-gubernamental. Vinculado al entorno de medios afines a Fidesz.'),
            ),
        ),
        'global' => array(
            'centro-izquierda' => array(
                array('The Guardian', 'https://www.theguardian.com/world/rss',
                    'Propiedad del Scott Trust Limited (fundación sin ánimo de lucro). Sin accionistas. Ver nota en sección Europa.'),
                array('BBC', 'https://feeds.bbci.co.uk/news/world/rss.xml',
                    'Medio público británico. Financiado por canon televisivo (licence fee) pagado por hogares británicos. Mandato de imparcialidad regulado por Royal Charter.'),
            ),
            'centro' => array(
                array('Al Jazeera', 'https://www.aljazeera.com/xml/rss/all.xml',
                    'Propiedad del Estado de Qatar vía Al Jazeera Media Network. 100% financiación estatal catarí. Mayor red de noticias del mundo árabe.'),
                array('Asia Times', 'https://asiatimes.com/feed/',
                    'Fundado 1995 en Bangkok. Propiedad actual no declarada públicamente. Medio enfocado en Asia-Pacífico, con perspectiva geopolítica desde la región.'),
            ),
            'centro-derecha' => array(
                array('National Review', 'https://www.nationalreview.com/feed/',
                    'Fundado 1955 por William F. Buckley Jr. Desde 2015, filial del National Review Institute (nonprofit 501c3). Sin publicidad corporativa significativa; financiado por suscripciones, donaciones y galas. Ha recibido fondos de la Charles Koch Foundation y la Bradley Foundation.'),
            ),
        ),
    ),

    // Rate limiting
    'rss_rate_limit' => 1,
    'rss_timeout'    => 15,
);
