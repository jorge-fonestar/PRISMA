<?php
/**
 * Prisma — Validador de feeds RSS (Fase 1: España + Europa nuevos).
 *
 * Prueba cada feed y reporta: HTTP status, formato, items encontrados,
 * ejemplo de titular, y tiempo de respuesta.
 *
 * Uso: php validate_feeds.php
 */

$feeds_fase1 = array(
    // ── España (+4) ──────────────────────────────────────────────────
    array('España', 'centro',             'Newtral',              'https://www.newtral.es/feed/'),
    array('España', 'centro',             'El Confidencial',      'https://rss.elconfidencial.com/espana/'),
    array('España', 'derecha',            'La Razón',             'https://www.larazon.es/?outputType=xml'),
    array('España', 'derecha-populista',  'OKDIARIO',             'https://okdiario.com/feed'),

    // ── Europa (+8) ──────────────────────────────────────────────────
    array('Europa', 'izquierda',          'Libération',           'http://rss.liberation.fr/rss/latest/'),
    array('Europa', 'izquierda',          'Il Manifesto',         'https://ilmanifesto.it/feed'),
    array('Europa', 'centro-izquierda',   'La Repubblica',        'https://www.repubblica.it/rss/homepage/rss2.0.xml'),
    array('Europa', 'centro-izquierda',   'Süddeutsche Zeitung',  'https://rss.sueddeutsche.de/rss/Topthemen'),
    array('Europa', 'centro-derecha',     'Corriere della Sera',  'https://xml2.corriereobjects.it/rss/homepage.xml'),
    array('Europa', 'derecha',            'The Telegraph',        'https://www.telegraph.co.uk/rss.xml'),
    array('Europa', 'derecha-populista',  'UnHerd',               'https://unherd.com/feed/atom/'),
    array('Europa', 'centro-derecha',     'Notes from Poland',    'https://notesfrompoland.com/feed/'),
);

echo "═══════════════════════════════════════════════════════════════\n";
echo "  PRISMA — Validación de feeds Fase 1 (" . date('Y-m-d H:i:s') . ")\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$results = array();

foreach ($feeds_fase1 as $feed) {
    list($ambito, $cuadrante, $nombre, $url) = $feed;

    echo "[$ambito / $cuadrante] $nombre\n";
    echo "  URL: $url\n";

    $t0 = microtime(true);

    // Fetch
    $ctx = stream_context_create(array(
        'http' => array(
            'timeout' => 20,
            'user_agent' => 'Prisma/1.0 (RSS reader; +https://prisma.example)',
            'ignore_errors' => true,
            'follow_location' => true,
            'max_redirects' => 5,
        ),
        'ssl' => array(
            'verify_peer' => true,
        ),
    ));

    $xml_str = @file_get_contents($url, false, $ctx);
    $elapsed = round((microtime(true) - $t0) * 1000);

    // HTTP status from headers
    $http_status = '???';
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $h, $m)) {
                $http_status = $m[1];
            }
        }
    }

    $result = array(
        'ambito'     => $ambito,
        'cuadrante'  => $cuadrante,
        'nombre'     => $nombre,
        'url'        => $url,
        'http'       => $http_status,
        'ms'         => $elapsed,
        'formato'    => '-',
        'items'      => 0,
        'ejemplo'    => '',
        'status'     => 'FAIL',
        'nota'       => '',
    );

    if (!$xml_str) {
        $result['nota'] = "Sin respuesta (timeout o conexion rechazada)";
        echo "  FAIL: sin respuesta ({$elapsed}ms)\n\n";
        $results[] = $result;
        sleep(1);
        continue;
    }

    echo "  HTTP: $http_status ({$elapsed}ms, " . strlen($xml_str) . " bytes)\n";

    // Parse XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xml_str);
    if (!$xml) {
        $errors = libxml_get_errors();
        $first_err = !empty($errors) ? $errors[0]->message : 'unknown';
        libxml_clear_errors();
        $result['nota'] = "XML invalido: " . trim($first_err);
        echo "  FAIL: XML inválido\n\n";
        $results[] = $result;
        sleep(1);
        continue;
    }

    // Detect format and count items
    $items = array();
    $formato = 'desconocido';

    // RSS 2.0
    if (isset($xml->channel->item)) {
        $formato = 'RSS 2.0';
        foreach ($xml->channel->item as $item) {
            $items[] = array(
                'titulo' => (string)$item->title,
                'fecha'  => (string)$item->pubDate,
            );
        }
    }
    // Atom
    elseif (isset($xml->entry)) {
        $formato = 'Atom';
        foreach ($xml->entry as $entry) {
            $items[] = array(
                'titulo' => (string)$entry->title,
                'fecha'  => (string)($entry->published ?: $entry->updated),
            );
        }
    }
    // RDF/RSS 1.0
    elseif ($xml->getName() === 'RDF' || isset($xml->item)) {
        $formato = 'RDF/RSS 1.0';
        $ns_items = $xml->item ?: array();
        foreach ($ns_items as $item) {
            $items[] = array(
                'titulo' => (string)$item->title,
                'fecha'  => (string)($item->pubDate ?: ''),
            );
        }
    }

    $n_items = count($items);
    $ejemplo = $n_items > 0 ? mb_substr($items[0]['titulo'], 0, 80) : '';
    $fecha_ejemplo = $n_items > 0 ? $items[0]['fecha'] : '';

    // Check date parsing on first item
    $fecha_ok = false;
    if ($fecha_ejemplo) {
        $ts = strtotime($fecha_ejemplo);
        $fecha_ok = ($ts !== false && $ts > 0);
    }

    $result['formato'] = $formato;
    $result['items'] = $n_items;
    $result['ejemplo'] = $ejemplo;
    $result['status'] = ($n_items > 0) ? 'OK' : 'EMPTY';
    if ($n_items > 0 && !$fecha_ok) {
        $result['nota'] = "Fechas no parseables (strtotime falla): '$fecha_ejemplo'";
    }
    if ($http_status !== '200') {
        $result['nota'] .= ($result['nota'] ? '; ' : '') . "HTTP $http_status (no 200)";
    }

    $status_icon = $result['status'] === 'OK' ? 'OK' : 'WARN';
    echo "  $status_icon: $formato, $n_items items\n";
    if ($ejemplo) echo "  Ejemplo: \"$ejemplo\"\n";
    if ($fecha_ejemplo) echo "  Fecha:   $fecha_ejemplo" . ($fecha_ok ? " (parseable)" : " (NO parseable)") . "\n";
    if ($result['nota']) echo "  Nota:    {$result['nota']}\n";
    echo "\n";

    $results[] = $result;
    sleep(1); // rate limit
}

// ── Resumen ──────────────────────────────────────────────────────────
echo "═══════════════════════════════════════════════════════════════\n";
echo "  RESUMEN\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$ok = 0; $warn = 0; $fail = 0;
foreach ($results as $r) {
    $icon = '?';
    if ($r['status'] === 'OK' && !$r['nota']) { $icon = 'OK'; $ok++; }
    elseif ($r['status'] === 'OK') { $icon = 'WARN'; $warn++; }
    else { $icon = 'FAIL'; $fail++; }

    printf("  [%-4s] %-25s %-15s %4d items  %5dms  %s\n",
        $icon, $r['nombre'], $r['formato'], $r['items'], $r['ms'],
        $r['nota'] ? "({$r['nota']})" : '');
}

echo "\n  Total: " . count($results) . " feeds | OK: $ok | WARN: $warn | FAIL: $fail\n\n";

// Save JSON for later use
$json_path = __DIR__ . '/data/feed_validation_fase1.json';
if (!is_dir(dirname($json_path))) @mkdir(dirname($json_path), 0755, true);
file_put_contents($json_path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "  Resultados guardados en: $json_path\n";
