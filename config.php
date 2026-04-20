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
    'model_synth'         => 'claude-sonnet-4-6-20250514',
    'model_audit'         => 'claude-opus-4-6-20250514',

    // ── Ingest ──────────────────────────────────────────────────────
    'ingest_key'          => getenv('PRISMA_INGEST_KEY') ?: '',

    // ── Panel ───────────────────────────────────────────────────────
    'panel_pass'          => getenv('PRISMA_PANEL_PASS') ?: 'prisma2026',

    // ── Límites de coste ────────────────────────────────────────────
    'daily_budget_usd'    => 4.00,
    'total_credit_usd'    => (float)(getenv('ANTHROPIC_CREDIT_USD') ?: 5.00),

    // ── Publicación ─────────────────────────────────────────────────
    'timezone'            => 'Europe/Madrid',
    'articulos_dia'       => 5,

    // ── RSS por fuente ──────────────────────────────────────────────
    // cuadrante => [ [nombre, rss_url], ... ]
    'fuentes' => [
        'izquierda' => [
            ['Público',       'https://www.publico.es/rss'],
            ['elDiario.es',   'https://www.eldiario.es/rss/'],
        ],
        'centro-izquierda' => [
            ['El País',       'https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/portada'],
            ['InfoLibre',     'https://www.infolibre.es/rss/rss.xml'],
        ],
        'centro' => [
            ['EFE',           'https://efe.com/feed/'],
            ['Newtral',       'https://www.newtral.es/feed/'],
        ],
        'centro-derecha' => [
            ['ABC',           'https://www.abc.es/rss/2.0/portada/'],
            ['The Objective',  'https://theobjective.com/feed/'],
        ],
        'derecha' => [
            ['El Mundo',      'https://e00-elmundo.uecdn.es/elmundo/rss/portada.xml'],
            ['La Razón',      'https://www.larazon.es/rss/portada.xml'],
        ],
        'derecha-populista' => [
            ['Libertad Digital', 'https://feeds.feedburner.com/libertaddigital/portada'],
            ['El Debate',        'https://www.eldebate.com/rss/'],
        ],
    ],

    // Rate limiting: segundos entre peticiones al mismo dominio
    'rss_rate_limit' => 1,
    'rss_timeout'    => 15,
]);
