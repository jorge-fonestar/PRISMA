<?php
/**
 * Prisma — Fase 2: Análisis en profundidad.
 *
 * Toma los temas pendientes del radar que superan el umbral de polarización,
 * los pasa por triage Haiku, síntesis Sonnet y auditoría Moral Core.
 * GASTA TOKENS DE IA. Ejecutar con criterio.
 *
 * Uso:
 *   php analizar.php                        # Top N según config (articulos_dia)
 *   php analizar.php --temas 3              # Máximo 3 temas
 *   php analizar.php --id 42                # Analizar un tema específico del radar
 *   php analizar.php --ambito españa        # Solo temas de un ámbito
 *
 * Cron (17:00 hora España, después del escaneo):
 *   0 17 * * * cd /ruta/a/prisma && php analizar.php >> logs/analisis.log 2>&1
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/anthropic.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/curador.php';
require_once __DIR__ . '/lib/sintetizador.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/lib/pipeline_batch.php';

// ── Args ─────────────────────────────────────────────────────────────

$opts = getopt('', array('temas:', 'ambito:', 'id:', 'tema:', 'sync', 'help'));

if (isset($opts['help'])) {
    echo "Uso: php analizar.php [--temas N] [--ambito españa|europa|global] [--id RADAR_ID] [--sync]\n";
    echo "      php analizar.php --tema \"Descripción del tema\" [--ambito españa]\n";
    echo "Fase 2: triage Haiku + síntesis Sonnet + auditoría Moral Core.\n";
    echo "Por defecto usa la Batches API (50% de coste, tarda minutos). --sync fuerza la vía directa.\n";
    echo "GASTA TOKENS. Ejecutar con criterio.\n";
    exit(0);
}

$cfg = prisma_cfg();
$max_temas = isset($opts['temas']) ? (int)$opts['temas'] : (int)$cfg['articulos_dia'];
$ambito_filter = isset($opts['ambito']) ? $opts['ambito'] : null;
$specific_id = isset($opts['id']) ? (int)$opts['id'] : 0;
$tema_libre = isset($opts['tema']) ? trim($opts['tema']) : '';

// Also check for job file (written by panel.php for manual topics)
if (!$tema_libre) {
    $job_path = __DIR__ . '/data/manual_job.json';
    if (file_exists($job_path)) {
        $job = json_decode(file_get_contents($job_path), true);
        if ($job && isset($job['tema_libre']) && $job['tema_libre']) {
            $tema_libre = $job['tema_libre'];
            if (!$ambito_filter && isset($job['ambito'])) {
                $ambito_filter = $job['ambito'];
            }
            @unlink($job_path);
        }
    }
}

// ── Log dir ──────────────────────────────────────────────────────────

$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);

// ── Load candidates ──────────────────────────────────────────────────

$db = prisma_db();
$umbral = $cfg['umbral_tension'];
$min_cuad = $cfg['min_cuadrantes'];

prisma_log("ANALYZE", "═══════════════════════════════════════════════");
prisma_log("ANALYZE", "Prisma — Análisis en profundidad (Fase 2)");
prisma_log("ANALYZE", "═══════════════════════════════════════════════");

// ── Manual topic (free text, bypasses radar) ─────────────────────────

if ($tema_libre) {
    $ambito_manual = $ambito_filter ? $ambito_filter : 'españa';
    $article_id = 'prisma_' . date('Ymd') . '_manual_' . substr(md5($tema_libre), 0, 6);

    prisma_log("ANALYZE", "Modo: tema manual");
    prisma_log("ANALYZE", "Tema: $tema_libre");
    prisma_log("ANALYZE", "Ámbito: $ambito_manual");

    try {
        $result = prisma_procesar_tema($tema_libre, $article_id, $ambito_manual, true);
        if ($result) {
            prisma_log("ANALYZE", "═══ PUBLICADO: $article_id ═══");
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            exit(0);
        } else {
            prisma_log("ANALYZE", "═══ DESCARTADO tras auditoría ═══");
            exit(1);
        }
    } catch (Exception $e) {
        prisma_log("ANALYZE", "ERROR: " . $e->getMessage());
        exit(2);
    }
}

// ── Radar-based analysis ─────────────────────────────────────────────

if ($specific_id > 0) {
    // Analyze a specific radar topic
    $stmt = $db->prepare('SELECT * FROM radar WHERE id = :id AND analizado = 0');
    $stmt->execute(array(':id' => $specific_id));
    $candidatos_raw = $stmt->fetchAll();
    if (empty($candidatos_raw)) {
        prisma_log("ANALYZE", "Tema radar #$specific_id no encontrado o ya analizado.");
        exit(1);
    }
    prisma_log("ANALYZE", "Modo: tema específico #$specific_id");
} else {
    // Cupo diario: el máximo automático descuenta lo ya publicado hoy, de forma
    // que los análisis manuales (panel, --id, --tema) lanzados antes de esta
    // ejecución cuenten para el tope y el cron no publique de más.
    // Un --temas N explícito es una orden manual y salta este ajuste.
    if (!isset($opts['temas'])) {
        $hoy = (new DateTime('now', new DateTimeZone($cfg['timezone'])))->format('Y-m-d');
        $c = $db->prepare('SELECT COUNT(*) FROM articulos WHERE id LIKE :p');
        $c->execute(array(':p' => $hoy . '-%'));
        $ya_hoy = (int)$c->fetchColumn();
        $cupo = (int)$cfg['articulos_dia'];
        $max_temas = max(0, $cupo - $ya_hoy);
        if ($ya_hoy > 0) {
            prisma_log("ANALYZE", sprintf("Ya publicados hoy: %d de %d. Cupo restante: %d.", $ya_hoy, $cupo, $max_temas));
        }
        if ($max_temas === 0) {
            prisma_log("ANALYZE", sprintf("Cupo diario (%d) ya cubierto. Nada que analizar.", $cupo));
            exit(0);
        }
    }

    // Find pending topics above threshold.
    // Ventana de recencia: solo temas de los últimos N días — sin ella, el
    // backlog antiguo (p.ej. la cola de abril-mayo tras el parón) compite por
    // h_score con la actualidad y le roba las plazas del día.
    $ventana = isset($cfg['analizar_ventana_dias']) ? (int)$cfg['analizar_ventana_dias'] : 2;
    $fecha_min = date('Y-m-d', strtotime('-' . max(0, $ventana - 1) . ' days'));

    $sql = 'SELECT * FROM radar WHERE analizado = 0 AND h_score >= :umbral
        AND fecha >= :fecha_min
        AND (triage IS NULL OR triage = \'confirmado\')';
    $params = array(':umbral' => $umbral, ':fecha_min' => $fecha_min);

    if ($ambito_filter) {
        $sql .= ' AND ambito = :ambito';
        $params[':ambito'] = $ambito_filter;
    }

    $sql .= ' ORDER BY h_score DESC LIMIT :limit';
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $max_temas * 3, PDO::PARAM_INT); // fetch extra for triage filtering
    $stmt->execute();
    $candidatos_raw = $stmt->fetchAll();

    prisma_log("ANALYZE", sprintf("Modo: top %d temas | Umbral: %.0f%% | Desde: %s | Ámbito: %s",
        $max_temas, $umbral * 100, $fecha_min, $ambito_filter ?: 'todos'));
}

if (empty($candidatos_raw)) {
    prisma_log("ANALYZE", "No hay temas pendientes que superen el umbral. Nada que analizar.");
    exit(0);
}

prisma_log("ANALYZE", count($candidatos_raw) . " candidatos encontrados.");

// Reconstruct topic data for triage (needs articulos array from fuentes_json)
$candidatos = array();
foreach ($candidatos_raw as $row) {
    $fuentes = json_decode($row['fuentes_json'], true);
    if (!$fuentes) $fuentes = array();

    // Rebuild minimal articulos array for curador_preparar_contexto
    $articulos = array();
    foreach ($fuentes as $f) {
        $articulos[] = array(
            'medio'     => $f['medio'],
            'titulo'    => $f['titulo'],
            'url'       => $f['url'],
            'cuadrante' => $f['cuadrante'],
            'descripcion' => isset($f['descripcion']) ? $f['descripcion'] : '',
        );
    }

    $candidatos[] = array(
        'radar_id'      => $row['id'],
        'titulo_tema'   => $row['titulo_tema'],
        'h_score'       => $row['h_score'],
        'h_asimetria'   => $row['h_asimetria'],
        'h_divergencia' => $row['h_divergencia'],
        'h_varianza'    => $row['h_varianza'],
        'ambito'        => $row['ambito'],
        'articulos'     => $articulos,
        'n_cuadrantes'  => count(array_unique(array_column($fuentes, 'cuadrante'))),
        'triage'        => isset($row['triage']) ? $row['triage'] : null,
        'haiku_frase'   => isset($row['haiku_frase']) ? $row['haiku_frase'] : null,
    );
}

// ── Triage Haiku (batch confirmation) ────────────────────────────────

if ($specific_id > 0) {
    // Skip triage for manual selection
    $confirmados = $candidatos;
    prisma_log("ANALYZE", "Triage omitido (selección manual).");
} else {
    // Los confirmados en ejecuciones anteriores no repagan el triage
    $pre_confirmados = array();
    $sin_triar = array();
    foreach ($candidatos as $c) {
        if ($c['triage'] === 'confirmado') $pre_confirmados[] = $c;
        else $sin_triar[] = $c;
    }
    if (!empty($pre_confirmados)) {
        prisma_log("ANALYZE", count($pre_confirmados) . " candidatos ya confirmados en ejecuciones previas.");
    }

    $confirmados = $pre_confirmados;
    if (!empty($sin_triar)) {
        prisma_log("ANALYZE", "Triage Haiku (" . count($sin_triar) . " candidatos)...");
        $confirmados = array_merge($confirmados, triage_haiku($sin_triar));
    }
    prisma_log("ANALYZE", count($confirmados) . " confirmados en total.");
}

// Top N por polarización (el orden de respuesta del triage no es fiable)
usort($confirmados, function ($a, $b) { return $b['h_score'] <=> $a['h_score']; });
$to_process = array_slice($confirmados, 0, $max_temas);

if (empty($to_process)) {
    prisma_log("ANALYZE", "Ningún tema confirmado por triage. Nada que analizar.");
    exit(0);
}

prisma_log("ANALYZE", count($to_process) . " temas a procesar.");

// ── Process topics ───────────────────────────────────────────────────

// Batches API (50% de coste) para ejecuciones de cron/CLI. El modo síncrono
// queda para --sync, --id (panel, el usuario espera) y temas manuales.
$use_batch = !empty($cfg['use_batch_api']) && !isset($opts['sync']) && $specific_id === 0;

if ($use_batch) {
    prisma_log("ANALYZE", "Modo Batches API (50% de coste) — " . count($to_process) . " temas.");
    $stats = prisma_procesar_temas_batch($to_process);

    prisma_log("ANALYZE", "");
    prisma_log("ANALYZE", "═══════════════════════════════════════════════");
    prisma_log("ANALYZE", sprintf("ANÁLISIS COMPLETO (batch): %d publicados, %d rechazados, %d errores de %d",
        $stats['publicados'], $stats['rechazados'], $stats['errores'], count($to_process)));
    prisma_log("ANALYZE", "═══════════════════════════════════════════════");

    exit($stats['publicados'] > 0 ? 0 : 1);
}

$publicados = 0;
$rechazados = 0;

foreach ($to_process as $i => $tema) {
    $seq = $i + 1;
    $article_id = prisma_gen_id($seq);
    $ambito = $tema['ambito'];

    prisma_log("ANALYZE", "");
    prisma_log("ANALYZE", "───────────────────────────────────────────────");
    prisma_log("ANALYZE", sprintf("Tema %d/%d: %s (H=%.0f%%)",
        $seq, count($to_process),
        mb_substr($tema['titulo_tema'], 0, 60, 'UTF-8'),
        $tema['h_score'] * 100
    ));
    prisma_log("ANALYZE", "───────────────────────────────────────────────");

    $contexto = curador_preparar_contexto($tema);

    try {
        $result = prisma_procesar_tema($contexto, $article_id, $ambito);
        if ($result) {
            $publicados++;
            radar_link_articulo($tema['radar_id'], $article_id);
            prisma_log("ANALYZE", "PUBLICADO: $article_id");
        } else {
            $rechazados++;
            radar_marcar_rechazado($tema['radar_id']);
            prisma_log("ANALYZE", "DESCARTADO tras auditoría.");
        }
    } catch (Exception $e) {
        prisma_log("ANALYZE", "ERROR: " . $e->getMessage());
        $rechazados++;
    }
}

// ── Summary ──────────────────────────────────────────────────────────

prisma_log("ANALYZE", "");
prisma_log("ANALYZE", "═══════════════════════════════════════════════");
prisma_log("ANALYZE", sprintf("ANÁLISIS COMPLETO: %d publicados, %d rechazados de %d",
    $publicados, $rechazados, count($to_process)));
prisma_log("ANALYZE", "═══════════════════════════════════════════════");

exit($publicados > 0 ? 0 : 1);
