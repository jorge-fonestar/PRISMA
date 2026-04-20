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

define('PRISMA_CONFIG', [

    // ── API Anthropic ───────────────────────────────────────────────
    'anthropic_api_key'   => getenv('ANTHROPIC_API_KEY') ?: '',
    'model_synth'         => 'claude-sonnet-4-6',
    'model_audit'         => 'claude-sonnet-4-6',  // Opus es 10x más caro; usar Sonnet para auditoría también hasta validar calidad

    // ── Ingest ──────────────────────────────────────────────────────
    'ingest_key'          => getenv('PRISMA_INGEST_KEY') ?: '',

    // ── Panel ───────────────────────────────────────────────────────
    'panel_pass'          => getenv('PRISMA_PANEL_PASS') ?: 'prisma2026',

    // ── Límites de coste ────────────────────────────────────────────
    'daily_budget_usd'    => 4.00,
    // Crédito total cargado (actualizar en .env al recargar).
    // Se resta el gasto acumulado de usage.json para calcular lo que queda.
    // Anthropic no ofrece endpoint para consultar saldo por API.
    'total_credit_usd'    => (float)(getenv('ANTHROPIC_CREDIT_USD') ?: 5.00),

    // ── Publicación ─────────────────────────────────────────────────
    'timezone'            => 'Europe/Madrid',
    'articulos_dia'       => 5,

    // ── RSS por ámbito y cuadrante ──────────────────────────────────
    // ambito => cuadrante => [ [nombre, rss_url], ... ]
    'fuentes' => [
        'españa' => [
            'izquierda-populista' => [
                ['Diario Red',       'https://www.diario-red.com/rss/listado'],
                ['El Salto',         'https://www.elsaltodiario.com/edicion-general/feed'],
            ],
            'izquierda' => [
                ['Público',          'https://www.publico.es/rss'],
                ['elDiario.es',      'https://www.eldiario.es/rss/'],
            ],
            'centro-izquierda' => [
                ['El País',          'https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/portada'],
                ['InfoLibre',        'https://www.infolibre.es/rss/rss.xml'],
            ],
            'centro' => [
                ['EFE',              'https://efe.com/feed/'],
                ['20minutos',        'https://www.20minutos.es/rss/'],
            ],
            'centro-derecha' => [
                ['La Vanguardia',    'https://www.lavanguardia.com/rss/home.xml'],
                ['The Objective',    'https://theobjective.com/feed/'],
            ],
            'derecha' => [
                ['ABC',              'https://www.abc.es/rss/2.0/portada/'],
                ['El Mundo',         'https://e00-elmundo.uecdn.es/elmundo/rss/portada.xml'],
            ],
            'derecha-populista' => [
                ['Libertad Digital', 'https://feeds.feedburner.com/libertaddigital/portada'],
                ['El Debate',        'https://www.eldebate.com/rss/'],
            ],
        ],
        'europa' => [
            'centro-izquierda' => [
                ['The Guardian',     'https://www.theguardian.com/world/europe-news/rss'],
                ['Le Monde',         'https://www.lemonde.fr/europe/rss_full.xml'],
            ],
            'centro' => [
                ['Euronews',         'https://www.euronews.com/rss?level=theme&name=news'],
                ['Politico Europe',  'https://www.politico.eu/feed/'],
                ['DW',               'https://rss.dw.com/rdf/rss-en-eu'],
            ],
            'centro-derecha' => [
                ['Der Spiegel',      'https://www.spiegel.de/international/index.rss'],
                ['Financial Times',  'https://www.ft.com/world/europe?format=rss'],
            ],
        ],
        'global' => [
            'centro-izquierda' => [
                ['The Guardian',     'https://www.theguardian.com/world/rss'],
                ['BBC',              'https://feeds.bbci.co.uk/news/world/rss.xml'],
            ],
            'centro' => [
                ['Reuters',          'https://www.reutersagency.com/feed/'],
                ['AP News',          'https://rsshub.app/apnews/topics/apf-topnews'],
            ],
            'centro-derecha' => [
                ['Al Jazeera',       'https://www.aljazeera.com/xml/rss/all.xml'],
            ],
        ],
    ],

    // Rate limiting: segundos entre peticiones al mismo dominio
    'rss_rate_limit' => 1,
    'rss_timeout'    => 15,
]);
