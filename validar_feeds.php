<?php
/**
 * Prisma — Feed Validator.
 *
 * Diagnostica feeds RSS: HTTP status, redirects, SSL, parseo XML,
 * número de artículos, frescura. Funciona con feeds existentes y candidatos.
 *
 * Uso CLI:
 *   php validar_feeds.php                     # Valida todos los feeds de config.php
 *   php validar_feeds.php --candidatos        # Valida solo los feeds candidatos
 *   php validar_feeds.php --todos             # Valida existentes + candidatos
 *   php validar_feeds.php --url URL           # Valida una URL concreta
 *
 * Uso web:
 *   validar_feeds.php?pass=PANEL_PASS                    # Valida config
 *   validar_feeds.php?pass=PANEL_PASS&mode=candidatos    # Valida candidatos
 *   validar_feeds.php?pass=PANEL_PASS&mode=todos         # Todo
 *   validar_feeds.php?pass=PANEL_PASS&url=URL            # Una URL concreta
 */

require_once __DIR__ . '/config.php';

// ── Web mode ────────────────────────────────────────────────────────
$is_web = php_sapi_name() !== 'cli';

if ($is_web) {
    $cfg_tmp = prisma_cfg();
    $pass = isset($_GET['pass']) ? $_GET['pass'] : '';
    if ($pass !== $cfg_tmp['panel_pass']) {
        http_response_code(403);
        echo 'Acceso denegado. Usa ?pass=TU_CONTRASEÑA';
        exit;
    }
    set_time_limit(120);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Prisma — Validar Feeds</title>'
        . '<style>body{background:#08080f;color:#c8c8d4;font:13px/1.6 Menlo,Consolas,monospace;padding:2rem;max-width:1100px;margin:0 auto}'
        . '.ok{color:#4ade80}.err{color:#ff4d6d}.warn{color:#f2f24a}.dim{color:#6a6a7a}'
        . 'table{border-collapse:collapse;width:100%;margin:1rem 0}th,td{padding:4px 10px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.06)}'
        . 'th{color:#6a6a7a;font-size:0.8em;text-transform:uppercase;letter-spacing:0.05em}</style></head><body>';
    echo '<h2>Prisma — Validación de feeds RSS</h2>';
    ob_flush(); flush();
}

// ── Candidatos para ampliación ──────────────────────────────────────

$candidatos = array(
    'europa' => array(
        'derecha' => array(
            array('Brussels Signal',          'https://brusselssignal.eu/feed/'),
            array('The European Conservative', 'https://europeanconservative.com/feed/'),
            array('Remix News',               'https://rmx.news/feed/'),
            array('Hungary Today',            'https://hungarytoday.hu/feed/'),
            array('Spiked',                   'https://www.spiked-online.com/feed/'),
        ),
        'centro' => array(
            array('Euractiv',                 'https://www.euractiv.com/feed/'),
            array('EUobserver',               'https://euobserver.com/rss.rss'),
        ),
    ),
    'global' => array(
        'derecha' => array(
            array('The Spectator',            'https://www.spectator.co.uk/feed'),
            array('National Review',          'https://www.nationalreview.com/feed/'),
        ),
        'centro' => array(
            array('Asia Times',               'https://asiatimes.com/feed/'),
        ),
    ),
);

// ── Args (CLI or web) ────────────────────────────────────────────────

if ($is_web) {
    $opts = array();
    $mode = isset($_GET['mode']) ? $_GET['mode'] : '';
    if ($mode === 'candidatos') $opts['candidatos'] = true;
    if ($mode === 'todos') $opts['todos'] = true;
    if (isset($_GET['url']) && $_GET['url'] !== '') $opts['url'] = $_GET['url'];
} else {
    $opts = getopt('', array('candidatos', 'todos', 'url:', 'help'));

    if (isset($opts['help'])) {
        echo "Uso: php validar_feeds.php [--candidatos] [--todos] [--url URL]\n";
        echo "  (sin args)     Valida feeds de config.php\n";
        echo "  --candidatos   Valida solo candidatos para ampliación\n";
        echo "  --todos        Valida existentes + candidatos\n";
        echo "  --url URL      Valida una URL concreta\n";
        exit(0);
    }
}

// ── Single URL mode ─────────────────────────────────────────────────

if (isset($opts['url'])) {
    $result = validar_feed($opts['url']);
    print_result('(manual)', '(manual)', $opts['url'], $result);
    if ($is_web) echo '</body></html>';
    exit($result['ok'] ? 0 : 1);
}

// ── Build feed list ─────────────────────────────────────────────────

$feeds_to_check = array();

$check_existing = !isset($opts['candidatos']);
$check_candidates = isset($opts['candidatos']) || isset($opts['todos']);

if ($check_existing) {
    $cfg = prisma_cfg();
    foreach ($cfg['fuentes'] as $ambito => $cuadrantes) {
        foreach ($cuadrantes as $cuadrante => $medios) {
            foreach ($medios as $medio) {
                $feeds_to_check[] = array(
                    'nombre'    => $medio[0],
                    'url'       => $medio[1],
                    'ambito'    => $ambito,
                    'cuadrante' => $cuadrante,
                    'tipo'      => 'existente',
                );
            }
        }
    }
}

if ($check_candidates) {
    foreach ($candidatos as $ambito => $cuadrantes) {
        foreach ($cuadrantes as $cuadrante => $medios) {
            foreach ($medios as $medio) {
                $feeds_to_check[] = array(
                    'nombre'    => $medio[0],
                    'url'       => $medio[1],
                    'ambito'    => $ambito,
                    'cuadrante' => $cuadrante,
                    'tipo'      => 'candidato',
                );
            }
        }
    }
}

// ── Validate ────────────────────────────────────────────────────────

$n_feeds = count($feeds_to_check);
if ($is_web) {
    echo "<p>Feeds a validar: <strong>$n_feeds</strong></p>";
    echo '<table><thead><tr><th>Estado</th><th>Nombre</th><th>Cuadrante</th><th>HTTP</th><th>Items</th><th>Frescura</th><th>Latencia</th><th>Detalle</th></tr></thead><tbody>';
    ob_flush(); flush();
} else {
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "Prisma — Validación de feeds RSS\n";
    echo "Feeds a validar: $n_feeds\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
}

$results = array(
    'ok' => array(),
    'warn' => array(),
    'fail' => array(),
);

foreach ($feeds_to_check as $feed) {
    $result = validar_feed($feed['url']);
    $result['nombre'] = $feed['nombre'];
    $result['ambito'] = $feed['ambito'];
    $result['cuadrante'] = $feed['cuadrante'];
    $result['tipo'] = $feed['tipo'];

    if ($is_web) {
        print_result_html($result);
        ob_flush(); flush();
    } else {
        print_result($feed['nombre'], $feed['cuadrante'], $feed['url'], $result);
    }

    if ($result['ok']) {
        $results['ok'][] = $result;
    } elseif ($result['http_code'] > 0) {
        $results['warn'][] = $result;
    } else {
        $results['fail'][] = $result;
    }

    // Rate limit
    usleep(500000); // 0.5s between requests
}

// ── Summary ─────────────────────────────────────────────────────────

if ($is_web) {
    echo '</tbody></table>';
    echo '<h3>Resumen</h3>';
    echo '<p><span class="ok">OK: ' . count($results['ok']) . '</span> &nbsp; ';
    echo '<span class="warn">WARN: ' . count($results['warn']) . '</span> &nbsp; ';
    echo '<span class="err">FAIL: ' . count($results['fail']) . '</span></p>';
    echo '</body></html>';
} else {
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "RESUMEN\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  OK:    " . count($results['ok']) . "\n";
    echo "  WARN:  " . count($results['warn']) . "\n";
    echo "  FAIL:  " . count($results['fail']) . "\n\n";

    if (!empty($results['fail'])) {
        echo "── FEEDS FALLIDOS ────────────────────────────────────────────\n";
        foreach ($results['fail'] as $r) {
            $tag = $r['tipo'] === 'candidato' ? ' [candidato]' : '';
            echo sprintf("  ✗ %-25s (%s) — %s%s\n", $r['nombre'], $r['cuadrante'], $r['error'], $tag);
        }
        echo "\n";
    }
    if (!empty($results['warn'])) {
        echo "── FEEDS CON PROBLEMAS ───────────────────────────────────────\n";
        foreach ($results['warn'] as $r) {
            $tag = $r['tipo'] === 'candidato' ? ' [candidato]' : '';
            echo sprintf("  ⚠ %-25s (%s) — HTTP %d, %d items%s\n",
                $r['nombre'], $r['cuadrante'], $r['http_code'], $r['n_items'], $tag);
        }
        echo "\n";
    }
    if (!empty($results['ok'])) {
        echo "── FEEDS OK ──────────────────────────────────────────────────\n";
        foreach ($results['ok'] as $r) {
            $tag = $r['tipo'] === 'candidato' ? ' [candidato]' : '';
            $fresh = $r['newest_hours'] !== null
                ? sprintf("más reciente: hace %.0fh", $r['newest_hours'])
                : 'sin fechas';
            echo sprintf("  ✓ %-25s (%s) — %d items, %s%s\n",
                $r['nombre'], $r['cuadrante'], $r['n_items'], $fresh, $tag);
        }
    }
    echo "\n";
}

exit(empty($results['fail']) ? 0 : 1);

// ═════════════════════════════════════════════════════════════════════
// Functions
// ═════════════════════════════════════════════════════════════════════

function validar_feed(string $url): array {
    $result = array(
        'url'           => $url,
        'ok'            => false,
        'http_code'     => 0,
        'content_type'  => '',
        'redirect_url'  => null,
        'ssl_error'     => false,
        'n_items'       => 0,
        'format'        => null,
        'newest_hours'  => null,
        'oldest_hours'  => null,
        'error'         => null,
        'duration_ms'   => 0,
        'response_bytes'=> 0,
    );

    $t_start = microtime(true);

    // Use cURL for better diagnostics than file_get_contents
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Prisma/1.0; +https://prisma.example)',
        CURLOPT_ENCODING       => '',  // Accept gzip
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HEADER         => false,
    ));

    $body = curl_exec($ch);
    $result['duration_ms'] = (int)((microtime(true) - $t_start) * 1000);
    $result['http_code'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $result['content_type'] = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $result['response_bytes'] = strlen($body ?: '');

    $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    if ($effective_url !== $url) {
        $result['redirect_url'] = $effective_url;
    }

    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Check cURL errors
    if ($curl_errno) {
        if ($curl_errno === 60 || $curl_errno === 77) {
            $result['ssl_error'] = true;
            $result['error'] = "SSL: $curl_error";
        } elseif ($curl_errno === 28) {
            $result['error'] = "Timeout (20s)";
        } elseif ($curl_errno === 6) {
            $result['error'] = "DNS resolution failed";
        } else {
            $result['error'] = "cURL[$curl_errno]: $curl_error";
        }
        return $result;
    }

    // Check HTTP status
    if ($result['http_code'] === 0) {
        $result['error'] = "No HTTP response";
        return $result;
    }
    if ($result['http_code'] === 403) {
        $result['error'] = "HTTP 403 Forbidden (likely bot block)";
        return $result;
    }
    if ($result['http_code'] === 404) {
        $result['error'] = "HTTP 404 Not Found (URL changed?)";
        return $result;
    }
    if ($result['http_code'] >= 400) {
        $result['error'] = "HTTP " . $result['http_code'];
        return $result;
    }

    // Check body
    if (!$body || strlen($body) < 100) {
        $result['error'] = "Empty or tiny response (" . strlen($body ?: '') . " bytes)";
        return $result;
    }

    // Check if it's actually XML
    if (strpos($body, '<html') !== false && strpos($body, '<rss') === false && strpos($body, '<feed') === false) {
        $result['error'] = "HTML response, not XML feed";
        return $result;
    }

    // Parse XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body);
    if (!$xml) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        $first_err = !empty($errors) ? $errors[0]->message : 'unknown';
        $result['error'] = "XML parse failed: " . trim($first_err);
        return $result;
    }

    // Detect format and count items
    $timestamps = array();

    if (isset($xml->channel->item)) {
        $result['format'] = 'RSS 2.0';
        $result['n_items'] = count($xml->channel->item);
        foreach ($xml->channel->item as $item) {
            $ts = @strtotime((string)$item->pubDate);
            if ($ts > 0) $timestamps[] = $ts;
        }
    } elseif (isset($xml->entry)) {
        $result['format'] = 'Atom';
        $result['n_items'] = count($xml->entry);
        foreach ($xml->entry as $entry) {
            $date = (string)($entry->published ?: $entry->updated);
            $ts = @strtotime($date);
            if ($ts > 0) $timestamps[] = $ts;
        }
    } elseif ($xml->getName() === 'RDF' || isset($xml->item)) {
        $result['format'] = 'RDF/RSS 1.0';
        $items = $xml->item ?: array();
        $result['n_items'] = count($items);
        foreach ($items as $item) {
            $date = (string)($item->pubDate ?: '');
            if (!$date) {
                // Try dc:date namespace
                $dc = $item->children('http://purl.org/dc/elements/1.1/');
                $date = (string)($dc->date ?: '');
            }
            $ts = @strtotime($date);
            if ($ts > 0) $timestamps[] = $ts;
        }
    } else {
        $result['error'] = "Unknown feed format (root: " . $xml->getName() . ")";
        return $result;
    }

    // Calculate freshness
    if (!empty($timestamps)) {
        $newest = max($timestamps);
        $oldest = min($timestamps);
        $result['newest_hours'] = (time() - $newest) / 3600;
        $result['oldest_hours'] = (time() - $oldest) / 3600;
    }

    // Evaluate
    if ($result['n_items'] === 0) {
        $result['error'] = "Feed parsed OK but 0 items";
        return $result;
    }

    $result['ok'] = true;
    return $result;
}

function print_result(string $nombre, string $cuadrante, string $url, array $r): void {
    $status = $r['ok'] ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
    echo sprintf("%s %-25s (%s)\n", $status, $nombre, $cuadrante);
    echo "  URL: $url\n";
    echo sprintf("  HTTP: %d | %dms | %s bytes",
        $r['http_code'], $r['duration_ms'], number_format($r['response_bytes']));

    if ($r['redirect_url']) {
        echo " | redirect → " . $r['redirect_url'];
    }
    echo "\n";

    if ($r['ok']) {
        echo sprintf("  Format: %s | Items: %d", $r['format'], $r['n_items']);
        if ($r['newest_hours'] !== null) {
            echo sprintf(" | Newest: %.0fh ago | Oldest: %.0fh ago", $r['newest_hours'], $r['oldest_hours']);
        }
        echo "\n";
    } else {
        echo "  \033[31mError: " . $r['error'] . "\033[0m\n";
        if ($r['ssl_error']) {
            echo "  Hint: SSL error — server may need CA bundle update or feed moved to HTTP\n";
        }
    }
    echo "\n";
}

function print_result_html(array $r): void {
    $h = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    if ($r['ok']) {
        $cls = 'ok';
        $icon = '&#10003;';
    } else {
        $cls = 'err';
        $icon = '&#10007;';
    }

    $fresh = '';
    if ($r['ok'] && $r['newest_hours'] !== null) {
        $hrs = $r['newest_hours'];
        if ($hrs > 720) {
            $fresh = '<span class="err">' . sprintf('%.0f días', $hrs / 24) . '</span>';
        } elseif ($hrs > 48) {
            $fresh = '<span class="warn">' . sprintf('%.0f días', $hrs / 24) . '</span>';
        } else {
            $fresh = sprintf('%.0fh', $hrs);
        }
    } elseif (!$r['ok']) {
        $fresh = '-';
    }

    $detail = '';
    if ($r['error']) {
        $detail = '<span class="err">' . $h($r['error']) . '</span>';
    } else {
        $parts = array();
        if ($r['format']) $parts[] = $r['format'];
        if ($r['redirect_url']) $parts[] = 'redirect';
        $detail = '<span class="dim">' . implode(', ', $parts) . '</span>';
    }

    $tag = $r['tipo'] === 'candidato' ? ' <span class="warn">[new]</span>' : '';

    echo '<tr>';
    echo '<td><span class="' . $cls . '">' . $icon . '</span></td>';
    echo '<td>' . $h($r['nombre']) . $tag . '</td>';
    echo '<td class="dim">' . $h($r['cuadrante']) . '</td>';
    echo '<td>' . ($r['http_code'] ?: '-') . '</td>';
    echo '<td>' . ($r['ok'] ? $r['n_items'] : '-') . '</td>';
    echo '<td>' . $fresh . '</td>';
    echo '<td class="dim">' . $r['duration_ms'] . 'ms</td>';
    echo '<td>' . $detail . '</td>';
    echo '</tr>' . "\n";
}
