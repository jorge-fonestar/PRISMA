<?php
/**
 * Prisma — Captura de portada.
 *
 * Extrae titulares y URLs de portadas HTML de medios sin RSS.
 * Solo titular + URL + fecha (Art. 15 DSM). Nunca entradilla ni contenido.
 * HTML crudo se descarta tras parsing.
 */
require_once __DIR__ . '/feed_health.php';
require_once __DIR__ . '/normalizar.php';

/**
 * Fetches front page of a media outlet and extracts article items.
 *
 * @param array $cfg  Normalized source config (from rss_normalizar_fuente)
 * @return array ['items' => [...], 'resultado' => 'ok'|'fail'|'throttle'|'skip', 'extras' => [...]]
 */
function captura_portada_fetch($cfg) {
    $url = $cfg['url'];
    $medio = $cfg['medio'];
    $ambito = isset($cfg['ambito']) ? $cfg['ambito'] : '';

    // 1. robots.txt check
    if (!captura_portada_robots_allowed($url, PRISMA_BOT_UA)) {
        return array('items' => array(), 'resultado' => 'skip', 'extras' => array('error' => 'robots.txt disallow'));
    }

    // 2. Rate limit check (by medio name, 60 min window)
    if (feed_health_rate_limited($medio, 60)) {
        return array('items' => array(), 'resultado' => 'throttle', 'extras' => array());
    }

    // 2b. Pre-register started record
    $health_id = feed_health_registrar($medio, $ambito, 'started', 'captura_portada');

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
 * @param string $url  URL to check
 * @param string $ua   User-agent string
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

    // 404 -> everything allowed
    if ($code === 404 || $code === 410) {
        $cache[$base] = true;
        return true;
    }
    // 5xx or timeout -> conservative deny
    if ($code >= 500 || $code === 0) {
        $cache[$base] = false;
        return false;
    }
    // Non-parseable or empty -> deny
    if (!$body || strpos($body, '<html') !== false) {
        $cache[$base] = false;
        return false;
    }

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
    $ua_short = 'prismabot';
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
            if ($disallowed === '') continue; // Empty Disallow = allow all (RFC 9309)
            if ($disallowed === '/') {
                $specific_allowed = false;
            } elseif (strpos($path, $disallowed) === 0) {
                $specific_allowed = false;
            }
        }

        if ($in_wildcard && stripos($line, 'disallow:') === 0) {
            $disallowed = trim(substr($line, 9));
            if ($disallowed === '') continue; // Empty Disallow = allow all (RFC 9309)
            if ($disallowed === '/') {
                $wildcard_allowed = false;
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

    // Parse selector for @attribute notation
    $titulo_parts = captura_portada_split_selector($selectores['titulo']);
    $url_parts = captura_portada_split_selector($selectores['url']);
    $fecha_parts = captura_portada_split_selector($selectores['fecha']);

    // If we have an articulos selector, scope within those containers
    if (!empty($selectores['articulos'])) {
        $container_xpath = captura_portada_css_to_xpath($selectores['articulos']);
        $containers = $xpath->query($container_xpath);
    } else {
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
 * Example: 'h2 a@href' -> ['selector' => 'h2 a', 'attribute' => 'href']
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
 *   - Class selectors: .noticia, .post (single class only)
 *   - Descendant combinator (space): article .titulo a
 *   - XPath passthrough: prefix 'xpath:' returned as-is
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
            $segments = explode('.', $part, 2);
            $tag = $segments[0] ? $segments[0] : '*';
            $class = $segments[1];
            $xpath_parts[] = $tag . "[contains(concat(' ',normalize-space(@class),' '),' " . $class . " ')]";
        } else {
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

    // Relative path with leading slash
    if (strpos($url, '/') === 0) {
        return $scheme . '://' . $host . $url;
    }

    // Relative without leading slash
    $base_path = isset($base_parts['path']) ? dirname($base_parts['path']) : '';
    return $scheme . '://' . $host . $base_path . '/' . $url;
}
