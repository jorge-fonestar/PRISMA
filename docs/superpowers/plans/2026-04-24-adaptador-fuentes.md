# Adaptador de Fuentes Multi-Modal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add front-page scraping as a second ingestion modality alongside RSS, with feed health tracking, so the pipeline can ingest sources that don't offer RSS feeds.

**Architecture:** New `lib/fuentes/` directory with three modules (normalizar.php, captura_portada.php, feed_health.php). `rss_fetch_all()` dispatches to the correct module based on source config. All modalities produce identical normalized article arrays. Feed health is tracked in `prisma_logs.db`.

**Tech Stack:** PHP 7.x, cURL, libxml/DOMDocument, SQLite (prisma_logs.db via `prisma_logger_db()`)

**Spec:** `docs/superpowers/specs/2026-04-24-adaptador-fuentes-design.md`

**Constraints:**
- PHP 7.x only (no arrow functions, no named args, no union types)
- No external dependencies (cURL + libxml from PHP stdlib only)
- Existing `rss_fetch_feed()` is NOT modified
- Legacy config format `array('Name', 'URL'[, 'Note'])` must keep working

---

## File Structure

| File | Responsibility | Status |
|------|---------------|--------|
| `lib/fuentes/normalizar.php` | `rss_normalizar_fuente()` — converts legacy/new config formats | **Create** |
| `lib/fuentes/feed_health.php` | `feed_health_*` functions + table creation in prisma_logs.db | **Create** |
| `lib/fuentes/captura_portada.php` | HTML front-page scraping: robots.txt, rate limit, fetch, parse | **Create** |
| `lib/rss.php` | Modify `rss_fetch_all()` for dispatch + UA change | **Modify** |
| `escanear.php` | Add require + feed_health purge | **Modify** |
| `config.php` | Add CTXT + La Marea + no_disponible entries | **Modify** |
| `fuentes.php` | Render modalidad, transparencia, perfil_editorial | **Modify** |
| `panel.php` | Feed health widget | **Modify** |
| `validar_feeds.php` | New `salud` mode with history table + ejes matrix | **Modify** |

---

## Task 1: lib/fuentes/normalizar.php

**Files:**
- Create: `lib/fuentes/normalizar.php`
- Test: manual — called by Task 3

This is a pure function with no dependencies. Foundation for everything else.

- [ ] **Step 1: Create directory and file**

```bash
mkdir -p lib/fuentes
```

- [ ] **Step 2: Write normalizar.php**

```php
<?php
/**
 * Prisma — Normalizador de fuentes.
 *
 * Convierte formato legacy array('Nombre','URL'[,'Nota']) al formato
 * asociativo nuevo. Compartido entre rss.php, fuentes.php y otros.
 */

/**
 * Normaliza un array de fuente (legacy o nuevo) al formato asociativo.
 *
 * @param array  $fuente    Array de config del medio (legacy o asociativo)
 * @param string $cuadrante Cuadrante ideológico
 * @param string $ambito    Ámbito geográfico
 * @return array Formato asociativo normalizado
 */
function rss_normalizar_fuente($fuente, $cuadrante, $ambito) {
    // Legacy: array('Nombre', 'URL'[, 'Transparencia'])
    if (isset($fuente[0]) && is_string($fuente[0])) {
        return array(
            'medio'         => $fuente[0],
            'url'           => $fuente[1],
            'modalidad'     => 'rss_nativo',
            'transparencia' => isset($fuente[2]) ? $fuente[2] : '',
            'cuadrante'     => $cuadrante,
            'ambito'        => $ambito,
        );
    }
    // New associative format — inject cuadrante/ambito from iteration context
    $fuente['cuadrante'] = $cuadrante;
    $fuente['ambito'] = $ambito;
    return $fuente;
}
```

- [ ] **Step 3: Verify syntax**

```bash
php -l lib/fuentes/normalizar.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add lib/fuentes/normalizar.php
git commit -m "feat: add rss_normalizar_fuente() for legacy/new config compat"
```

---

## Task 2: lib/fuentes/feed_health.php

**Files:**
- Create: `lib/fuentes/feed_health.php`
- Reference: `lib/logger.php` (for `prisma_logger_db()` pattern)

Feed health tracking with table auto-creation, registration, update, and query functions.

- [ ] **Step 1: Write feed_health.php**

```php
<?php
/**
 * Prisma — Feed health tracking.
 *
 * Registra resultados de cada fetch (RSS o captura) en prisma_logs.db.
 * Tabla feed_health se auto-crea al primer uso.
 */
require_once __DIR__ . '/../logger.php';

/**
 * Ensures feed_health table exists in prisma_logs.db.
 * Called once per process via static flag.
 */
function feed_health_init() {
    static $done = false;
    if ($done) return;
    $db = prisma_logger_db();
    $db->exec('CREATE TABLE IF NOT EXISTS feed_health (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        medio       TEXT NOT NULL,
        ambito      TEXT NOT NULL,
        modalidad   TEXT NOT NULL,
        resultado   TEXT NOT NULL,
        items_count INTEGER DEFAULT 0,
        http_status INTEGER DEFAULT NULL,
        error_msg   TEXT DEFAULT NULL,
        latencia_ms INTEGER DEFAULT NULL,
        created_at  TEXT DEFAULT (datetime(\'now\'))
    )');
    // Check if index exists before creating (SQLite doesn't support IF NOT EXISTS for indexes in all versions)
    $idx = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_fh_medio_fecha'")->fetch();
    if (!$idx) {
        $db->exec('CREATE INDEX idx_fh_medio_fecha ON feed_health(medio, created_at)');
    }
    $done = true;
}

/**
 * Registers a feed health event.
 *
 * @param string $medio      Media name
 * @param string $ambito     Geographic scope
 * @param string $resultado  ok|fail|skip|throttle|started
 * @param string $modalidad  rss_nativo|captura_portada|no_disponible
 * @param int    $items      Number of items fetched
 * @param array  $extras     Optional: http_status, error, latencia_ms
 * @return int  Inserted row ID (for later update via feed_health_update)
 */
function feed_health_registrar($medio, $ambito, $resultado, $modalidad, $items = 0, $extras = array()) {
    feed_health_init();
    $db = prisma_logger_db();
    $stmt = $db->prepare('INSERT INTO feed_health (medio, ambito, modalidad, resultado, items_count, http_status, error_msg, latencia_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute(array(
        $medio, $ambito, $modalidad, $resultado, $items,
        isset($extras['http_status']) ? $extras['http_status'] : null,
        isset($extras['error']) ? $extras['error'] : null,
        isset($extras['latencia_ms']) ? $extras['latencia_ms'] : null,
    ));
    return (int)$db->lastInsertId();
}

/**
 * Updates an existing feed_health record (e.g., started → ok/fail).
 *
 * @param int    $id         Row ID from feed_health_registrar
 * @param string $resultado  New resultado value
 * @param int    $items      Updated items count
 * @param array  $extras     Updated extras
 */
function feed_health_update($id, $resultado, $items = 0, $extras = array()) {
    feed_health_init();
    $db = prisma_logger_db();
    $stmt = $db->prepare('UPDATE feed_health SET resultado = ?, items_count = ?, http_status = ?, error_msg = ?, latencia_ms = ? WHERE id = ?');
    $stmt->execute(array(
        $resultado, $items,
        isset($extras['http_status']) ? $extras['http_status'] : null,
        isset($extras['error']) ? $extras['error'] : null,
        isset($extras['latencia_ms']) ? $extras['latencia_ms'] : null,
        $id,
    ));
}

/**
 * Purges feed_health records older than N days.
 *
 * @param int $days Retention period (default 90)
 * @return int Number of rows deleted
 */
function feed_health_purgar($days = 90) {
    feed_health_init();
    $db = prisma_logger_db();
    $stmt = $db->prepare("DELETE FROM feed_health WHERE created_at < datetime('now', ? || ' days')");
    $stmt->execute(array(-$days));
    return $stmt->rowCount();
}

/**
 * Returns feed health summary for all sources (last 30 days).
 *
 * @return array [ ['medio'=>..., 'modalidad'=>..., 'total'=>N, 'exitos'=>N, 'tasa_exito'=>N.N], ... ]
 */
function feed_health_resumen($dias = 30) {
    feed_health_init();
    $db = prisma_logger_db();
    $stmt = $db->prepare("SELECT medio, modalidad,
        COUNT(*) as total,
        SUM(CASE WHEN resultado = 'ok' THEN 1 ELSE 0 END) as exitos,
        ROUND(100.0 * SUM(CASE WHEN resultado = 'ok' THEN 1 ELSE 0 END) / COUNT(*), 1) as tasa_exito
        FROM feed_health
        WHERE created_at >= datetime('now', ? || ' days')
          AND resultado NOT IN ('skip', 'throttle', 'started')
        GROUP BY medio");
    $stmt->execute(array(-$dias));
    return $stmt->fetchAll();
}

/**
 * Returns sources with no successful fetch in N days.
 *
 * @return array [ ['medio'=>..., 'modalidad'=>..., 'ultimo_exito'=>...], ... ]
 */
function feed_health_alertas($dias = 7) {
    feed_health_init();
    $db = prisma_logger_db();
    $stmt = $db->prepare("SELECT medio, modalidad, MAX(created_at) as ultimo_exito
        FROM feed_health
        WHERE resultado = 'ok'
        GROUP BY medio
        HAVING ultimo_exito < datetime('now', ? || ' days')");
    $stmt->execute(array(-$dias));
    return $stmt->fetchAll();
}

/**
 * Returns last result per source.
 *
 * @return array [ ['medio'=>..., 'modalidad'=>..., 'resultado'=>..., 'items_count'=>N, 'created_at'=>...], ... ]
 */
function feed_health_ultimo_por_fuente() {
    feed_health_init();
    $db = prisma_logger_db();
    return $db->query("SELECT medio, modalidad, resultado, items_count, created_at
        FROM feed_health
        WHERE id IN (SELECT MAX(id) FROM feed_health GROUP BY medio)")->fetchAll();
}

/**
 * Returns history for a specific source.
 *
 * @param string $medio  Source name
 * @param int    $limit  Max records
 * @return array
 */
function feed_health_historial($medio, $limit = 30) {
    feed_health_init();
    $db = prisma_logger_db();
    $stmt = $db->prepare("SELECT * FROM feed_health WHERE medio = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute(array($medio, $limit));
    return $stmt->fetchAll();
}

/**
 * Checks if a domain has been fetched within the rate limit window.
 *
 * @param string $dominio  Domain name
 * @param int    $minutos  Window in minutes (default 60)
 * @return bool  true if rate limited (should NOT fetch)
 */
function feed_health_rate_limited($dominio, $minutos = 60) {
    feed_health_init();
    $db = prisma_logger_db();
    // Match by domain substring in medio — not perfect, but captura_portada
    // will call with the actual medio name. We check by created_at window.
    // This is called from captura_portada with the medio name, not domain.
    // The rate check uses the feed_health table directly.
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM feed_health
        WHERE medio = ? AND created_at >= datetime('now', ? || ' minutes')");
    $stmt->execute(array($dominio, -$minutos));
    $row = $stmt->fetch();
    return $row && $row['c'] > 0;
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l lib/fuentes/feed_health.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add lib/fuentes/feed_health.php
git commit -m "feat: add feed_health tracking module for prisma_logs.db"
```

---

## Task 3: Modify lib/rss.php — dispatch + UA

**Files:**
- Modify: `lib/rss.php:1-88` (rss_fetch_all) and `lib/rss.php:101` (UA string)
- Reference: `lib/fuentes/normalizar.php`, `lib/fuentes/feed_health.php`

Two changes: (1) `rss_fetch_all()` dispatches by modalidad, (2) UA unified to PrismaBot.

- [ ] **Step 1: Add requires at top of lib/rss.php**

After line 8 (`require_once __DIR__ . '/common.php';`), add:

```php
require_once __DIR__ . '/fuentes/normalizar.php';
require_once __DIR__ . '/fuentes/feed_health.php';
```

- [ ] **Step 2: Modify rss_fetch_all() dispatch loop**

Replace the inner loop body in `rss_fetch_all()` (lines 40-80). The new loop body handles three modalidades:

```php
            foreach ($medios as $medio_arr) {
            // Normalize legacy or new format
            $cfg_medio = rss_normalizar_fuente($medio_arr, $cuadrante, $amb);
            $nombre = $cfg_medio['medio'];

            // Skip no_disponible sources
            if ($cfg_medio['modalidad'] === 'no_disponible') {
                feed_health_registrar($nombre, $amb, 'skip', 'no_disponible');
                prisma_log("RSS", "  $nombre: no disponible — saltando");
                continue;
            }

            // Dispatch by modalidad
            if ($cfg_medio['modalidad'] === 'captura_portada') {
                // Lazy-load captura_portada module
                if (!function_exists('captura_portada_fetch')) {
                    require_once __DIR__ . '/fuentes/captura_portada.php';
                }
                $resp = captura_portada_fetch($cfg_medio);
                $items = $resp['items'];
                $resultado = $resp['resultado'];
                $extras = $resp['extras'];
            } else {
                // RSS nativo — existing rss_fetch_feed()
                $rss_url = $cfg_medio['url'];
                // Rate limit por dominio
                $domain = parse_url($rss_url, PHP_URL_HOST);
                if ($domain === $last_domain) {
                    $wait = $rate_limit - (time() - $last_time);
                    if ($wait > 0) sleep($wait);
                }
                $last_domain = $domain;
                $last_time = time();

                prisma_log("RSS", "Leyendo $nombre ($cuadrante)...");
                $items = rss_fetch_feed($rss_url, $timeout);
                if ($items === null) { $items = array(); $resultado = 'fail'; }
                else { $resultado = count($items) > 0 ? 'ok' : 'fail'; }
                $extras = array();
            }

            // Register health
            feed_health_registrar($nombre, $amb, $resultado, $cfg_medio['modalidad'], count($items), $extras);

            if ($resultado === 'fail') {
                prisma_log("RSS", "  ERROR leyendo $nombre — saltando");
                continue;
            }
            if ($resultado === 'throttle' || $resultado === 'skip') {
                prisma_log("RSS", "  $nombre: $resultado — saltando");
                continue;
            }

            $count = 0;
            foreach ($items as $item) {
                // Filtrar por fecha (últimas 24h)
                $ts = isset($item['fecha_ts']) ? $item['fecha_ts'] : 0;
                if ($ts > 0 && $ts < $cutoff) continue;

                $articles[] = array(
                    'titulo'        => $item['titulo'],
                    'url'           => $item['url'],
                    'fecha'         => $item['fecha'],
                    'medio'         => $nombre,
                    'cuadrante'     => $cuadrante,
                    'descripcion'   => isset($item['descripcion']) ? $item['descripcion'] : '',
                    'fecha_inferida'=> isset($item['fecha_inferida']) ? $item['fecha_inferida'] : false,
                );
                $count++;
            }

            prisma_log("RSS", "  $nombre: $count artículos (24h)");
            }
```

- [ ] **Step 3: Unify User-Agent in rss_fetch_feed()**

In `lib/rss.php:101`, change:

```php
// Old:
CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Prisma/1.0; +https://prisma.example)',
// New:
CURLOPT_USERAGENT      => 'PrismaBot/1.0 (+https://prisma.example/bot)',
```

- [ ] **Step 4: Verify syntax**

```bash
php -l lib/rss.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add lib/rss.php
git commit -m "feat: rss_fetch_all dispatches by modalidad, unify UA to PrismaBot"
```

---

## Task 4: lib/fuentes/captura_portada.php

**Files:**
- Create: `lib/fuentes/captura_portada.php`
- Reference: `lib/fuentes/feed_health.php` (for rate check + pre-registration)

The core scraping module. Pure functions, no state.

- [ ] **Step 1: Write captura_portada.php**

```php
<?php
/**
 * Prisma — Captura de portada.
 *
 * Extrae titulares y URLs de portadas HTML de medios sin RSS.
 * Solo titular + URL + fecha (Art. 15 DSM). Nunca entradilla ni contenido.
 * HTML crudo se descarta tras parsing.
 */
require_once __DIR__ . '/feed_health.php';

define('PRISMA_BOT_UA', 'PrismaBot/1.0 (+https://prisma.example/bot)');

/**
 * Fetches front page of a media outlet and extracts article items.
 *
 * @param array $cfg  Normalized source config (from rss_normalizar_fuente)
 * @return array ['items' => [...], 'resultado' => 'ok'|'fail'|'throttle'|'skip', 'extras' => [...]]
 */
function captura_portada_fetch($cfg) {
    $url = $cfg['url'];
    $medio = $cfg['medio'];
    $empty = array('items' => array(), 'resultado' => 'fail', 'extras' => array());

    // 1. robots.txt check
    if (!captura_portada_robots_allowed($url, PRISMA_BOT_UA)) {
        return array('items' => array(), 'resultado' => 'skip', 'extras' => array('error' => 'robots.txt disallow'));
    }

    // 2. Rate limit check (by medio name, 60 min window)
    if (feed_health_rate_limited($medio, 60)) {
        return array('items' => array(), 'resultado' => 'throttle', 'extras' => array());
    }

    // 2b. Pre-register started record
    $health_id = feed_health_registrar($medio, $cfg['ambito'], 'started', 'captura_portada');

    // 3. HTTP fetch
    $t0 = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => PRISMA_BOT_UA,
        CURLOPT_HTTPHEADER     => array('Accept: text/html'),
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HEADER         => true,
    ));

    $response = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    $latencia = (int)((microtime(true) - $t0) * 1000);
    $extras = array('http_status' => $http_code, 'latencia_ms' => $latencia);

    if (!$response || $http_code < 200 || $http_code >= 400 || $curl_err) {
        $extras['error'] = $curl_err ? $curl_err : "HTTP $http_code";
        feed_health_update($health_id, 'fail', 0, $extras);
        return array('items' => array(), 'resultado' => 'fail', 'extras' => $extras);
    }

    $headers_raw = substr($response, 0, $header_size);
    $html = substr($response, $header_size);

    // 4. Encoding detection
    $html = captura_portada_ensure_utf8($html, $headers_raw);

    // 5. HTML parse
    $selectores = array(
        'articulos' => isset($cfg['selector_articulos']) ? $cfg['selector_articulos'] : '',
        'titulo'    => isset($cfg['selector_titulo']) ? $cfg['selector_titulo'] : '',
        'url'       => isset($cfg['selector_url']) ? $cfg['selector_url'] : '',
        'fecha'     => isset($cfg['selector_fecha']) ? $cfg['selector_fecha'] : '',
    );

    $items = captura_portada_parse($html, $selectores, $url);

    // 6. Update health record
    $resultado = count($items) > 0 ? 'ok' : 'fail';
    $extras['error'] = null;
    feed_health_update($health_id, $resultado, count($items), $extras);

    return array('items' => $items, 'resultado' => $resultado, 'extras' => $extras);
}

/**
 * Checks robots.txt for the given URL.
 *
 * @param string $url       URL to check
 * @param string $ua        User-agent string
 * @return bool  true if allowed
 */
function captura_portada_robots_allowed($url, $ua) {
    static $cache = array();
    $parts = parse_url($url);
    $base = $parts['scheme'] . '://' . $parts['host'];

    if (isset($cache[$base])) return $cache[$base];

    $robots_url = $base . '/robots.txt';
    $ch = curl_init($robots_url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
    ));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 404 → everything allowed
    if ($code === 404 || $code === 410) {
        $cache[$base] = true;
        return true;
    }
    // 5xx or timeout → conservative deny
    if ($code >= 500 || $code === 0) {
        $cache[$base] = false;
        return false;
    }
    // Non-parseable or empty → deny
    if (!$body || strpos($body, '<html') !== false) {
        $cache[$base] = false;
        return false;
    }

    // Simple robots.txt parser: look for our UA or wildcard
    $path = isset($parts['path']) ? $parts['path'] : '/';
    $allowed = captura_portada_parse_robots($body, $ua, $path);
    $cache[$base] = $allowed;
    return $allowed;
}

/**
 * Parses robots.txt content and checks if path is allowed for user-agent.
 *
 * @param string $body  robots.txt content
 * @param string $ua    Our user-agent
 * @param string $path  Path to check
 * @return bool
 */
function captura_portada_parse_robots($body, $ua, $path) {
    $lines = explode("\n", str_replace("\r", "", $body));
    $ua_short = 'prismabot'; // Match against our bot name
    $current_applies = false;
    $found_specific = false;
    $specific_allowed = true;
    $wildcard_allowed = true;
    $in_wildcard = false;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;

        if (stripos($line, 'user-agent:') === 0) {
            $agent = strtolower(trim(substr($line, 11)));
            if (strpos($agent, $ua_short) !== false) {
                $current_applies = true;
                $found_specific = true;
                $in_wildcard = false;
            } elseif ($agent === '*') {
                $current_applies = false;
                $in_wildcard = true;
            } else {
                $current_applies = false;
                $in_wildcard = false;
            }
            continue;
        }

        if ($current_applies && stripos($line, 'disallow:') === 0) {
            $disallowed = trim(substr($line, 9));
            if ($disallowed === '' || $disallowed === '/') {
                $specific_allowed = false;
            } elseif (strpos($path, $disallowed) === 0) {
                $specific_allowed = false;
            }
        }

        if ($in_wildcard && stripos($line, 'disallow:') === 0) {
            $disallowed = trim(substr($line, 9));
            if ($disallowed === '' || $disallowed === '/') {
                // Disallow: / for * blocks everything
                if ($disallowed === '/') $wildcard_allowed = false;
            } elseif (strpos($path, $disallowed) === 0) {
                $wildcard_allowed = false;
            }
        }

        if ($current_applies && stripos($line, 'allow:') === 0) {
            $allow_path = trim(substr($line, 6));
            if (strpos($path, $allow_path) === 0) {
                $specific_allowed = true;
            }
        }

        if ($in_wildcard && stripos($line, 'allow:') === 0) {
            $allow_path = trim(substr($line, 6));
            if (strpos($path, $allow_path) === 0) {
                $wildcard_allowed = true;
            }
        }
    }

    return $found_specific ? $specific_allowed : $wildcard_allowed;
}

/**
 * Ensures HTML is UTF-8 encoded.
 *
 * @param string $html         Raw HTML body
 * @param string $headers_raw  HTTP response headers
 * @return string  UTF-8 encoded HTML
 */
function captura_portada_ensure_utf8($html, $headers_raw) {
    $charset = null;

    // Check HTTP Content-Type header
    if (preg_match('/Content-Type:.*charset=([^\s;\r\n]+)/i', $headers_raw, $m)) {
        $charset = strtolower(trim($m[1]));
    }

    // Fallback: check <meta charset> or <meta http-equiv>
    if (!$charset) {
        if (preg_match('/<meta[^>]+charset=["\']?([^"\'\s;>]+)/i', substr($html, 0, 4096), $m)) {
            $charset = strtolower(trim($m[1]));
        }
    }

    if ($charset && $charset !== 'utf-8' && $charset !== 'utf8') {
        $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
        if ($converted !== false) return $converted;
    }

    return $html;
}

/**
 * Parses HTML and extracts article items using configured selectors.
 *
 * @param string $html        UTF-8 HTML content
 * @param array  $selectores  ['articulos'=>..., 'titulo'=>..., 'url'=>..., 'fecha'=>...]
 * @param string $base_url    Base URL for resolving relative links
 * @return array Normalized items
 */
function captura_portada_parse($html, $selectores, $base_url) {
    if (empty($html) || empty($selectores['titulo'])) return array();

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $items = array();
    $seen_urls = array();

    // Parse selector_titulo for @attribute notation
    $titulo_parts = captura_portada_split_selector($selectores['titulo']);
    $url_parts = captura_portada_split_selector($selectores['url']);
    $fecha_parts = captura_portada_split_selector($selectores['fecha']);

    // If we have an articulos selector, scope within those containers
    if (!empty($selectores['articulos'])) {
        $container_xpath = captura_portada_css_to_xpath($selectores['articulos']);
        $containers = $xpath->query($container_xpath);
    } else {
        // Use document body as single container
        $containers = $xpath->query('//body');
    }

    if (!$containers || $containers->length === 0) return array();

    foreach ($containers as $container) {
        // Extract title
        $titulo_xpath = captura_portada_css_to_xpath($titulo_parts['selector']);
        $titulo_nodes = $xpath->query($titulo_xpath, $container);
        if (!$titulo_nodes || $titulo_nodes->length === 0) continue;

        $titulo = trim($titulo_nodes->item(0)->textContent);
        if (empty($titulo)) continue;
        $titulo = html_entity_decode($titulo, ENT_QUOTES, 'UTF-8');

        // Extract URL
        $article_url = '';
        if (!empty($url_parts['selector'])) {
            $url_xpath = captura_portada_css_to_xpath($url_parts['selector']);
            $url_nodes = $xpath->query($url_xpath, $container);
            if ($url_nodes && $url_nodes->length > 0) {
                $attr = $url_parts['attribute'] ? $url_parts['attribute'] : 'href';
                $article_url = $url_nodes->item(0)->getAttribute($attr);
            }
        }
        if (empty($article_url)) continue;
        $article_url = captura_portada_normalizar_url($article_url, $base_url);

        // Dedup by URL
        if (isset($seen_urls[$article_url])) continue;
        $seen_urls[$article_url] = true;

        // Extract date
        $fecha = '';
        $fecha_ts = 0;
        $fecha_inferida = true;
        if (!empty($fecha_parts['selector'])) {
            $fecha_xpath = captura_portada_css_to_xpath($fecha_parts['selector']);
            $fecha_nodes = $xpath->query($fecha_xpath, $container);
            if ($fecha_nodes && $fecha_nodes->length > 0) {
                $attr = $fecha_parts['attribute'] ? $fecha_parts['attribute'] : null;
                $raw = $attr ? $fecha_nodes->item(0)->getAttribute($attr) : $fecha_nodes->item(0)->textContent;
                $raw = trim($raw);
                if ($raw) {
                    $ts = strtotime($raw);
                    if ($ts !== false && $ts > 0) {
                        $fecha = date('Y-m-d H:i:s', $ts);
                        $fecha_ts = $ts;
                        $fecha_inferida = false;
                    }
                }
            }
        }
        // Fallback to current time
        if ($fecha_inferida) {
            $fecha = date('Y-m-d H:i:s');
            $fecha_ts = time();
        }

        $items[] = array(
            'titulo'         => $titulo,
            'url'            => $article_url,
            'fecha'          => $fecha,
            'fecha_ts'       => $fecha_ts,
            'descripcion'    => '',
            'fecha_inferida' => $fecha_inferida,
        );
    }

    return $items;
}

/**
 * Splits a selector string on @ to separate CSS selector from attribute name.
 * Example: 'h2 a@href' → ['selector' => 'h2 a', 'attribute' => 'href']
 *
 * @param string $selector
 * @return array ['selector' => string, 'attribute' => string|null]
 */
function captura_portada_split_selector($selector) {
    if (empty($selector)) return array('selector' => '', 'attribute' => null);
    $pos = strrpos($selector, '@');
    if ($pos === false) return array('selector' => $selector, 'attribute' => null);
    return array(
        'selector'  => substr($selector, 0, $pos),
        'attribute' => substr($selector, $pos + 1),
    );
}

/**
 * Converts a simple CSS selector to XPath.
 *
 * Supported subset:
 *   - Tag names: article, h2, time
 *   - Class selectors: .noticia, .post
 *   - Descendant combinator (space): article .titulo a
 *   - XPath passthrough: prefix 'xpath:' → returned as-is
 *
 * @param string $css
 * @return string XPath expression
 */
function captura_portada_css_to_xpath($css) {
    $css = trim($css);
    if (empty($css)) return '.';

    // XPath passthrough
    if (strpos($css, 'xpath:') === 0) {
        return substr($css, 6);
    }

    // Split by descendant combinator (space)
    $parts = preg_split('/\s+/', $css);
    $xpath_parts = array();

    foreach ($parts as $part) {
        if (strpos($part, '.') !== false) {
            // Has class selector
            $segments = explode('.', $part, 2);
            $tag = $segments[0] ? $segments[0] : '*';
            $class = $segments[1];
            $xpath_parts[] = $tag . "[contains(concat(' ',normalize-space(@class),' '),' " . $class . " ')]";
        } else {
            // Tag name only
            $xpath_parts[] = $part;
        }
    }

    return './/' . implode('//', $xpath_parts);
}

/**
 * Normalizes a URL: absolute, relative, or protocol-relative.
 *
 * @param string $url       URL to normalize
 * @param string $base_url  Base URL for context
 * @return string Absolute URL
 */
function captura_portada_normalizar_url($url, $base_url) {
    $url = trim($url);
    if (empty($url)) return '';

    // Already absolute
    if (preg_match('#^https?://#i', $url)) return $url;

    $base_parts = parse_url($base_url);
    $scheme = isset($base_parts['scheme']) ? $base_parts['scheme'] : 'https';
    $host = isset($base_parts['host']) ? $base_parts['host'] : '';

    // Protocol-relative
    if (strpos($url, '//') === 0) {
        return $scheme . ':' . $url;
    }

    // Relative path
    if (strpos($url, '/') === 0) {
        return $scheme . '://' . $host . $url;
    }

    // Relative without leading slash
    $base_path = isset($base_parts['path']) ? dirname($base_parts['path']) : '';
    return $scheme . '://' . $host . $base_path . '/' . $url;
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l lib/fuentes/captura_portada.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add lib/fuentes/captura_portada.php
git commit -m "feat: add captura_portada module for front-page HTML scraping"
```

---

## Task 5: Modify escanear.php — purge + require

**Files:**
- Modify: `escanear.php:16-22` (requires section)

- [ ] **Step 1: Add feed_health purge after requires**

After the existing require block (line 22), add:

```php
require_once __DIR__ . '/lib/fuentes/feed_health.php';

// Purge old feed_health records (>90 days)
$purged = feed_health_purgar(90);
if ($purged > 0) {
    prisma_log("SCAN", "Purgados $purged registros de feed_health (>90 días)");
}
```

Note: `captura_portada.php` is NOT required here — it's lazy-loaded in `rss_fetch_all()` only when a `captura_portada` source is encountered.

- [ ] **Step 2: Verify syntax**

```bash
php -l escanear.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add escanear.php
git commit -m "feat: add feed_health purge on scan startup"
```

---

## Task 6: Update config.php — add new sources + no_disponible entries

**Files:**
- Modify: `config.php:105-120` (españa fuentes section)

- [ ] **Step 1: Add CTXT and La Marea to izquierda cuadrante**

In `config.php`, after the elDiario.es entry in `izquierda`:

```php
            'izquierda' => array(
                // Público: no RSS feed found in source — may have discontinued RSS
                array('elDiario.es',      'https://www.eldiario.es/rss/'),
                array('CTXT',             'https://ctxt.es/es/rss.xml'),
                array('La Marea',         'https://www.lamarea.com/feed/'),
            ),
```

- [ ] **Step 2: Verify feeds work**

```bash
php validar_feeds.php --url https://ctxt.es/es/rss.xml
php validar_feeds.php --url https://www.lamarea.com/feed/
```

Expected: Both should show OK status with recent items.

- [ ] **Step 3: Commit**

```bash
git add config.php
git commit -m "feat: add CTXT and La Marea to izquierda cuadrante"
```

---

## Task 7: Update fuentes.php — modalidad rendering

**Files:**
- Modify: `fuentes.php:1-110`

- [ ] **Step 1: Add require for normalizar.php**

After line 2 (`require_once __DIR__ . '/lib/layout.php';`):

```php
require_once __DIR__ . '/lib/fuentes/normalizar.php';
```

- [ ] **Step 2: Replace introductory paragraph**

Replace line 12:

```php
// Old:
  <p>Prisma consulta diariamente los RSS públicos de estos medios, clasificados por ámbito geográfico y cuadrante ideológico.</p>
// New:
  <p>Prisma consulta diariamente estos medios, clasificados por ámbito geográfico y cuadrante ideológico.</p>
  <p style="font-size:0.88rem;color:var(--text-muted);line-height:1.6;margin-top:0.5rem">Prisma accede al contenido público de los medios mediante tres vías: RSS nativo cuando el medio lo ofrece; captura de portada (solo titulares y enlaces, respetando robots.txt y con user-agent identificable) cuando el medio no ha implementado RSS; y autorización explícita solicitada por correo cuando el medio ha retirado su RSS deliberadamente. Cualquier medio que desee no ser incluido puede escribir a contacto@prisma.example y será retirado del corpus en 48 horas.</p>
```

- [ ] **Step 3: Update table rendering to show modalidad**

Replace the table header and row rendering (lines 45-58):

```php
      <thead><tr><th>Cuadrante</th><th>Medio</th><th>Acceso</th><th>Propiedad y financiación</th></tr></thead>
      <tbody>
      <?php foreach ($cuadrantes as $cuadrante => $medios): ?>
        <?php foreach ($medios as $i => $medio): ?>
          <?php $cfg_m = rss_normalizar_fuente($medio, $cuadrante, $ambito); ?>
          <tr>
            <?php if ($i === 0): ?>
              <td style="white-space:nowrap;vertical-align:top" rowspan="<?= count($medios) ?>"><strong><?= htmlspecialchars(ucfirst($cuadrante)) ?></strong></td>
            <?php endif; ?>
            <td style="white-space:nowrap;vertical-align:top"><strong><?= htmlspecialchars($cfg_m['medio']) ?></strong></td>
            <td style="white-space:nowrap;vertical-align:top;font-size:0.8rem"><?php
              $mod = isset($cfg_m['modalidad']) ? $cfg_m['modalidad'] : 'rss_nativo';
              if ($mod === 'rss_nativo') echo '<span style="color:var(--ok,#4ade80)">RSS</span>';
              elseif ($mod === 'captura_portada') echo '<span style="color:var(--warn,#f2f24a)">Portada</span>';
              else echo '<span style="color:var(--err,#ff4d6d)">No disponible</span>';
            ?></td>
            <td style="font-size:0.85rem;color:var(--text-muted);line-height:1.5"><?php
              $trans = isset($cfg_m['transparencia']) ? $cfg_m['transparencia'] : '';
              echo htmlspecialchars($trans ? $trans : '—');
            ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
```

- [ ] **Step 4: Update policy section**

Replace lines 100-107 (Política de acceso):

```php
  <h2>Política de acceso</h2>
  <ul>
    <li><strong>RSS nativo:</strong> lectura de feeds RSS/Atom públicos.</li>
    <li><strong>Captura de portada:</strong> solo titulares y URLs de portada, respetando robots.txt, con User-Agent identificable (PrismaBot/1.0), máximo 1 petición por medio por hora.</li>
    <li><strong>Medios sin acceso:</strong> cuando un medio retira su RSS deliberadamente, se solicita autorización explícita. Sin respuesta o denegación se declara públicamente.</li>
    <li>Siempre se cita la fuente original con enlace directo.</li>
    <li>Nunca se republica el texto íntegro del artículo.</li>
    <li>Cualquier medio puede solicitar su exclusión del corpus.</li>
  </ul>
```

- [ ] **Step 5: Verify syntax**

```bash
php -l fuentes.php
```

Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add fuentes.php
git commit -m "feat: fuentes.php shows modalidad, transparencia, updated access policy"
```

---

## Task 8: Panel widget — feed health summary

**Files:**
- Modify: `panel.php` (add widget in dashboard section)

- [ ] **Step 1: Add require for feed_health.php**

After the existing requires in `panel.php` (line 10-11):

```php
require_once __DIR__ . '/lib/fuentes/feed_health.php';
```

- [ ] **Step 2: Find the dashboard stats section and add feed health widget**

Locate the dashboard stats section in `panel.php` (after authentication, where system state is displayed). Add the feed health widget block. The exact insertion point depends on `panel.php` layout — insert after the existing stat counters.

The widget code:

```php
<?php
// Feed health widget
$fh_resumen = feed_health_resumen(30);
$fh_alertas = feed_health_alertas(7);
$fh_total_fuentes = count($fh_resumen);
$fh_operativas = 0;
foreach ($fh_resumen as $fh) {
    if ($fh['tasa_exito'] > 0) $fh_operativas++;
}
$fh_pct = $fh_total_fuentes > 0 ? round(100 * $fh_operativas / $fh_total_fuentes) : 0;
$fh_color = $fh_pct > 90 ? '#4ade80' : ($fh_pct > 70 ? '#f2f24a' : '#ff4d6d');
?>
<div style="margin:1rem 0;padding:1rem;background:rgba(255,255,255,0.03);border-radius:8px;border:1px solid rgba(255,255,255,0.06)">
  <strong style="color:<?= $fh_color ?>">Fuentes: <?= $fh_operativas ?>/<?= $fh_total_fuentes ?> operativas</strong>
  <?php if (!empty($fh_alertas)): ?>
    <ul style="margin:0.5rem 0 0;padding-left:1.2rem;font-size:0.85rem;color:var(--text-muted,#999)">
      <?php foreach (array_slice($fh_alertas, 0, 5) as $alerta): ?>
        <li><?= htmlspecialchars($alerta['medio']) ?> — sin actividad desde <?= htmlspecialchars($alerta['ultimo_exito']) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p style="margin:0.5rem 0 0;font-size:0.8rem"><a href="validar_feeds.php?pass=<?= urlencode($cfg['panel_pass']) ?>&mode=salud" style="color:var(--accent,#6c8aff)">Ver salud completa de fuentes →</a></p>
</div>
```

- [ ] **Step 3: Verify syntax**

```bash
php -l panel.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add panel.php
git commit -m "feat: add feed health widget to panel dashboard"
```

---

## Task 9: validar_feeds.php — salud mode

**Files:**
- Modify: `validar_feeds.php`

- [ ] **Step 1: Add require and mode detection**

After the existing requires (line 21), add:

```php
require_once __DIR__ . '/lib/fuentes/feed_health.php';
require_once __DIR__ . '/lib/fuentes/normalizar.php';
```

- [ ] **Step 2: Add salud mode handler**

After the web mode setup section and before the candidatos section, add the salud mode handler. When `$mode === 'salud'` (web) or `--salud` (CLI), display the feed health dashboard:

```php
// ── Salud mode ──────────────────────────────────────────────────────
$mode = '';
if ($is_web) {
    $mode = isset($_GET['mode']) ? $_GET['mode'] : '';
} else {
    $cli_opts = getopt('', array('salud', 'candidatos', 'todos', 'url:'));
    if (isset($cli_opts['salud'])) $mode = 'salud';
}

if ($mode === 'salud') {
    $resumen = feed_health_resumen(30);
    $alertas = feed_health_alertas(7);

    if ($is_web) {
        echo '<h3>Salud de fuentes (últimos 30 días)</h3>';
        echo '<table><thead><tr><th>Medio</th><th>Modalidad</th><th>Fetches</th><th>Éxitos</th><th>Tasa</th></tr></thead><tbody>';
        // Sort by success rate ascending (worst first)
        usort($resumen, function($a, $b) { return $a['tasa_exito'] - $b['tasa_exito']; });
        foreach ($resumen as $r) {
            $color = $r['tasa_exito'] > 90 ? 'ok' : ($r['tasa_exito'] > 70 ? 'warn' : 'err');
            echo '<tr>';
            echo '<td>' . htmlspecialchars($r['medio']) . '</td>';
            echo '<td>' . htmlspecialchars($r['modalidad']) . '</td>';
            echo '<td>' . (int)$r['total'] . '</td>';
            echo '<td>' . (int)$r['exitos'] . '</td>';
            echo '<td class="' . $color . '">' . $r['tasa_exito'] . '%</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        if (!empty($alertas)) {
            echo '<h3 style="margin-top:2rem">Alertas (>7 días sin éxito)</h3>';
            echo '<ul>';
            foreach ($alertas as $a) {
                echo '<li class="err">' . htmlspecialchars($a['medio']) . ' — último éxito: ' . htmlspecialchars($a['ultimo_exito']) . '</li>';
            }
            echo '</ul>';
        }

        // Ejes × cuadrantes matrix
        $cfg_fuentes = prisma_cfg();
        $ejes_matrix = array();
        foreach ($cfg_fuentes['fuentes'] as $amb => $cuads) {
            foreach ($cuads as $cuad => $medios_arr) {
                foreach ($medios_arr as $m) {
                    $cfg_m = rss_normalizar_fuente($m, $cuad, $amb);
                    $ejes = isset($cfg_m['ejes_cubiertos']) ? $cfg_m['ejes_cubiertos'] : array();
                    foreach ($ejes as $eje) {
                        if (!isset($ejes_matrix[$eje])) $ejes_matrix[$eje] = array();
                        if (!isset($ejes_matrix[$eje][$cuad])) $ejes_matrix[$eje][$cuad] = 0;
                        $ejes_matrix[$eje][$cuad]++;
                    }
                }
            }
        }

        if (!empty($ejes_matrix)) {
            $all_cuads = array_keys($cfg_fuentes['fuentes']['españa']);
            echo '<h3 style="margin-top:2rem">Matriz ejes × cuadrantes</h3>';
            echo '<table><thead><tr><th>Eje</th>';
            foreach ($all_cuads as $c) echo '<th>' . htmlspecialchars($c) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($ejes_matrix as $eje => $cuads) {
                echo '<tr><td>' . htmlspecialchars($eje) . '</td>';
                foreach ($all_cuads as $c) {
                    $n = isset($cuads[$c]) ? $cuads[$c] : 0;
                    $style = $n === 0 ? ' class="err"' : '';
                    echo '<td' . $style . '>' . $n . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    } else {
        // CLI output
        echo "=== Salud de fuentes (30 días) ===\n";
        foreach ($resumen as $r) {
            printf("%-25s %-18s %3d fetches  %3d ok  %5.1f%%\n",
                $r['medio'], $r['modalidad'], $r['total'], $r['exitos'], $r['tasa_exito']);
        }
        if (!empty($alertas)) {
            echo "\n=== Alertas (>7 días sin éxito) ===\n";
            foreach ($alertas as $a) {
                echo "  {$a['medio']} — último éxito: {$a['ultimo_exito']}\n";
            }
        }
    }

    if ($is_web) echo '</body></html>';
    exit;
}
```

- [ ] **Step 3: Update usage docs at top of file**

Add to the CLI usage section:

```
 *   php validar_feeds.php --salud             # Muestra salud de fuentes (30 días)
```

And to web usage:

```
 *   validar_feeds.php?pass=PANEL_PASS&mode=salud      # Salud de fuentes
```

- [ ] **Step 4: Verify syntax**

```bash
php -l validar_feeds.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add validar_feeds.php
git commit -m "feat: add salud mode to validar_feeds with health history and ejes matrix"
```

---

## Task 10: Integration test — full pipeline with mixed sources

**Files:**
- No new files — manual verification

- [ ] **Step 1: Run feed validation on new sources**

```bash
php validar_feeds.php --url https://ctxt.es/es/rss.xml
php validar_feeds.php --url https://www.lamarea.com/feed/
```

Both should return OK with recent items.

- [ ] **Step 2: Run a scan for españa only**

```bash
php escanear.php --ambito españa
```

Verify in output:
- CTXT and La Marea appear in the log with article counts
- No errors related to normalizar or feed_health
- Feed health purge message appears (or 0 purged on first run)

- [ ] **Step 3: Check feed health was recorded**

```bash
php -r "
require_once 'config.php';
require_once 'lib/logger.php';
require_once 'lib/fuentes/feed_health.php';
\$r = feed_health_resumen(1);
foreach (\$r as \$row) echo \$row['medio'] . ': ' . \$row['tasa_exito'] . '%%\n';
"
```

Should show all españa sources with their success rates.

- [ ] **Step 4: Check salud mode works**

```bash
php validar_feeds.php --salud
```

Should display the health table with all sources.

- [ ] **Step 5: Verify fuentes.php renders correctly**

Open `fuentes.php` in browser. Verify:
- New "Acceso" column shows RSS/Portada/No disponible
- CTXT and La Marea appear in izquierda cuadrante
- Transparency notes display correctly
- Updated access policy paragraph visible

- [ ] **Step 6: Verify panel widget**

Open `panel.php` in browser. Verify:
- Feed health widget appears with "Fuentes: X/Y operativas"
- Link to salud mode works

- [ ] **Step 7: Final commit if any fixes needed**

```bash
git add -A
git commit -m "fix: integration adjustments from end-to-end testing"
```

---

## Summary

| Task | Component | Depends on |
|------|-----------|-----------|
| 1 | normalizar.php | — |
| 2 | feed_health.php | — |
| 3 | rss.php dispatch | 1, 2 |
| 4 | captura_portada.php | 2 |
| 5 | escanear.php purge | 2 |
| 6 | config.php new sources | — |
| 7 | fuentes.php rendering | 1 |
| 8 | panel.php widget | 2 |
| 9 | validar_feeds.php salud | 1, 2 |
| 10 | Integration test | All |

Tasks 1, 2, 6 are independent and can run in parallel. Tasks 3-5, 7-9 depend on 1 and/or 2. Task 10 requires all others complete.
