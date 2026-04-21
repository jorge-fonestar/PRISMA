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
    'daily_budget_usd'    => 4.00,
    'total_credit_usd'    => (float)(getenv('ANTHROPIC_CREDIT_USD') ?: 5.00),

    // ── Publicación ─────────────────────────────────────────────────
    'timezone'            => 'Europe/Madrid',
    'articulos_dia'       => 1,
    'min_cuadrantes'      => 3,             // Mínimo de cuadrantes para ir al pipeline Sonnet
    'umbral_tension'      => 0.55,          // H mínimo para ser candidato a análisis

    // ── RSS por ámbito y cuadrante ──────────────────────────────────
    'fuentes' => array(
        'españa' => array(
            'izquierda-populista' => array(
                array('Diario Red',       'https://www.diario-red.com/rss/listado'),
                array('El Salto',         'https://www.elsaltodiario.com/edicion-general/feed'),
            ),
            'izquierda' => array(
                array('Público',          'https://www.publico.es/rss'),
                array('elDiario.es',      'https://www.eldiario.es/rss/'),
            ),
            'centro-izquierda' => array(
                array('El País',          'https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/portada'),
                array('InfoLibre',        'https://www.infolibre.es/rss/rss.xml'),
            ),
            'centro' => array(
                array('EFE',              'https://efe.com/feed/'),
                array('20minutos',        'https://www.20minutos.es/rss/'),
                array('Newtral',          'https://www.newtral.es/feed/'),
                array('El Confidencial',  'https://rss.elconfidencial.com/espana/'),
            ),
            'centro-derecha' => array(
                array('La Vanguardia',    'https://www.lavanguardia.com/rss/home.xml'),
                array('The Objective',    'https://theobjective.com/feed/'),
            ),
            'derecha' => array(
                array('ABC',              'https://www.abc.es/rss/2.0/portada/'),
                array('El Mundo',         'https://e00-elmundo.uecdn.es/elmundo/rss/portada.xml'),
                array('La Razón',         'https://www.larazon.es/?outputType=xml'),
            ),
            'derecha-populista' => array(
                array('Libertad Digital', 'https://feeds.feedburner.com/libertaddigital/portada'),
                array('El Debate',        'https://www.eldebate.com/rss/'),
                array('OKDIARIO',         'https://okdiario.com/feed'),
            ),
        ),
        'europa' => array(
            'izquierda' => array(
                array('Libération',       'http://rss.liberation.fr/rss/latest/'),
                array('Il Manifesto',     'https://ilmanifesto.it/feed'),
            ),
            'centro-izquierda' => array(
                array('The Guardian',     'https://www.theguardian.com/world/europe-news/rss'),
                array('Le Monde',         'https://www.lemonde.fr/europe/rss_full.xml'),
                array('La Repubblica',    'https://www.repubblica.it/rss/homepage/rss2.0.xml'),
                array('Süddeutsche Zeitung', 'https://rss.sueddeutsche.de/rss/Topthemen'),
            ),
            'centro' => array(
                array('Euronews',         'https://www.euronews.com/rss?level=theme&name=news'),
                array('Politico Europe',  'https://www.politico.eu/feed/'),
                array('DW',               'https://rss.dw.com/rdf/rss-en-eu'),
            ),
            'centro-derecha' => array(
                array('Der Spiegel',      'https://www.spiegel.de/international/index.rss'),
                array('Financial Times',  'https://www.ft.com/world/europe?format=rss'),
                array('Corriere della Sera', 'https://xml2.corriereobjects.it/rss/homepage.xml'),
                array('Notes from Poland', 'https://notesfrompoland.com/feed/'),
            ),
            'derecha' => array(
                array('The Telegraph',    'https://www.telegraph.co.uk/rss.xml'),
            ),
            'derecha-populista' => array(
                array('UnHerd',           'https://unherd.com/feed/atom/'),
            ),
        ),
        'global' => array(
            'centro-izquierda' => array(
                array('The Guardian',     'https://www.theguardian.com/world/rss'),
                array('BBC',              'https://feeds.bbci.co.uk/news/world/rss.xml'),
            ),
            'centro' => array(
                array('Reuters',          'https://www.reutersagency.com/feed/'),
                array('AP News',          'https://rsshub.app/apnews/topics/apf-topnews'),
            ),
            'centro-derecha' => array(
                array('Al Jazeera',       'https://www.aljazeera.com/xml/rss/all.xml'),
            ),
        ),
    ),

    // Rate limiting
    'rss_rate_limit' => 1,
    'rss_timeout'    => 15,
);
