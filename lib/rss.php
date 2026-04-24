<?php
/**
 * Prisma — Lector de RSS.
 *
 * Lee feeds RSS/Atom y devuelve artículos normalizados.
 * Sin dependencias externas: parsea XML nativo.
 */
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/fuentes/normalizar.php';
require_once __DIR__ . '/fuentes/feed_health.php';

/**
 * Lee los RSS del ámbito indicado y devuelve artículos de las últimas 24h.
 *
 * @param string $ambito "españa"|"europa"|"global" — si vacío, lee todos
 * @return array [ ['titulo'=>..., 'url'=>..., 'fecha'=>..., 'medio'=>..., 'cuadrante'=>..., 'descripcion'=>...], ... ]
 */
function rss_fetch_all(string $ambito = ''): array {
    $cfg = prisma_cfg();
    $all_fuentes = $cfg['fuentes'];
    $timeout = $cfg['rss_timeout'] ?? 15;
    $rate_limit = $cfg['rss_rate_limit'] ?? 1;
    $cutoff = time() - 86400; // últimas 24h

    // Seleccionar ámbitos a leer
    if ($ambito && isset($all_fuentes[$ambito])) {
        $ambitos = [$ambito => $all_fuentes[$ambito]];
    } elseif ($ambito) {
        prisma_log("RSS", "Ámbito '$ambito' no encontrado en config. Leyendo todos.");
        $ambitos = $all_fuentes;
    } else {
        $ambitos = $all_fuentes;
    }

    $articles = [];
    $last_domain = '';
    $last_time = 0;

    foreach ($ambitos as $amb => $cuadrantes) {
        prisma_log("RSS", "═ Ámbito: $amb ═");
        foreach ($cuadrantes as $cuadrante => $medios) {
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

            // Register health (captura_portada handles its own registration internally)
            if ($cfg_medio['modalidad'] !== 'captura_portada') {
                feed_health_registrar($nombre, $amb, $resultado, $cfg_medio['modalidad'], count($items), $extras);
            }

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
        }
    }

    $n_cuadrantes = 0;
    foreach ($ambitos as $cs) { $n_cuadrantes += count($cs); }
    prisma_log("RSS", "Total: " . count($articles) . " artículos de $n_cuadrantes cuadrantes");
    return $articles;
}

/**
 * Parsea un feed RSS/Atom individual.
 */
function rss_fetch_feed(string $url, int $timeout = 15): ?array {
    // Use cURL for better redirect handling, compression, and User-Agent control
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_USERAGENT      => PRISMA_BOT_UA,
        CURLOPT_ENCODING       => '',  // Accept gzip/deflate
        CURLOPT_SSL_VERIFYPEER => true,
    ));

    $xml_str = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if (!$xml_str || $http_code < 200 || $http_code >= 400) return null;

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xml_str);
    if (!$xml) return null;

    $items = [];

    // RSS 2.0
    if (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $items[] = rss_normalize_item(
                (string)$item->title,
                (string)($item->link ?: $item->guid),
                (string)$item->pubDate,
                (string)$item->description
            );
        }
    }
    // Atom
    elseif (isset($xml->entry)) {
        foreach ($xml->entry as $entry) {
            $link = '';
            foreach ($entry->link as $l) {
                if ((string)$l['rel'] === 'alternate' || !$link) {
                    $link = (string)$l['href'];
                }
            }
            $items[] = rss_normalize_item(
                (string)$entry->title,
                $link,
                (string)($entry->published ?: $entry->updated),
                (string)$entry->summary
            );
        }
    }
    // RDF/RSS 1.0
    elseif ($xml->getName() === 'RDF' || isset($xml->item)) {
        $ns_items = $xml->item ?? [];
        foreach ($ns_items as $item) {
            $items[] = rss_normalize_item(
                (string)$item->title,
                (string)$item->link,
                (string)($item->pubDate ?? $item->date ?? ''),
                (string)$item->description
            );
        }
    }

    return $items;
}

function rss_normalize_item(string $titulo, string $url, string $fecha, string $desc): array {
    $titulo = html_entity_decode(strip_tags(trim($titulo)), ENT_QUOTES, 'UTF-8');
    $desc = html_entity_decode(strip_tags(trim($desc)), ENT_QUOTES, 'UTF-8');
    $desc = mb_substr($desc, 0, 500);

    $ts = 0;
    if ($fecha) {
        $ts = strtotime($fecha);
        if ($ts === false) $ts = 0;
    }

    return [
        'titulo'      => $titulo,
        'url'         => trim($url),
        'fecha'       => $fecha,
        'fecha_ts'    => $ts,
        'descripcion' => $desc,
    ];
}
