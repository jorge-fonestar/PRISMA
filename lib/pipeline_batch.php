<?php
/**
 * Prisma — Pipeline de Fase 2 sobre la Message Batches API (50% de coste).
 *
 * Mismo comportamiento que prisma_procesar_tema() pero agrupando todos los
 * temas de la ejecución en batches por rondas:
 *
 *   Ronda r: batch de síntesis (todos los temas activos)
 *            → batch de auditorías (los artefactos válidos)
 *            → APTO publica, RECHAZO descarta,
 *              REVISIÓN reintenta en la ronda r+1 con feedback del auditor.
 *
 * Máximo 3 rondas (1 + 2 reintentos), igual que la vía síncrona.
 * Pensado para el cron nocturno: cada batch puede tardar minutos
 * (normalmente <1h; el poll espera hasta 4h).
 *
 * La vía síncrona (prisma_procesar_tema) se mantiene para el panel,
 * temas manuales y --sync.
 */

require_once __DIR__ . '/anthropic.php';
require_once __DIR__ . '/sintetizador.php';
require_once __DIR__ . '/auditor.php';
require_once __DIR__ . '/common.php';

/**
 * Procesa un lote de temas del radar vía Batches API.
 *
 * @param array $temas Cada uno: ['radar_id', 'titulo_tema', 'ambito', 'articulos', ...]
 *                     (formato de los candidatos confirmados de analizar.php)
 * @return array ['publicados' => int, 'rechazados' => int, 'errores' => int]
 */
function prisma_procesar_temas_batch(array $temas): array {
    $cfg = prisma_cfg();
    $max_rondas = 3; // 1 intento + 2 reintentos, como la vía síncrona

    // Estado por tema, indexado por radar_id
    $estado = array();
    foreach ($temas as $i => $tema) {
        $rid = (int)$tema['radar_id'];
        $estado[$rid] = array(
            'tema'       => $tema,
            'article_id' => prisma_gen_id($i + 1),
            'contexto'   => curador_preparar_contexto($tema),
            'feedback'   => '',
            'artifact'   => null,
            'done'       => false,
            'resultado'  => null, // 'publicado' | 'rechazado' | null
        );
    }

    $stats = array('publicados' => 0, 'rechazados' => 0, 'errores' => 0);

    for ($ronda = 1; $ronda <= $max_rondas; $ronda++) {
        $activos = array_filter($estado, function ($e) { return !$e['done']; });
        if (empty($activos)) break;

        $es_ultima = ($ronda === $max_rondas);
        prisma_log("PIPE", "═══ Ronda batch $ronda/$max_rondas — " . count($activos) . " temas ═══");

        // ── Batch de síntesis ────────────────────────────────────────
        $requests = array();
        foreach ($activos as $rid => $e) {
            $req = sintetizador_build($e['contexto'], $e['article_id'], $e['tema']['ambito'], $e['feedback']);
            $requests[] = array(
                'custom_id'  => "synth-$rid",
                'model'      => $cfg['model_synth'],
                'system'     => $req['system'],
                'user_msg'   => $req['user_msg'],
                'max_tokens' => isset($cfg['max_tokens_pipeline']) ? $cfg['max_tokens_pipeline'] : 4096,
            );
        }

        $batch_id = anthropic_batch_submit($requests);
        $batch = anthropic_batch_wait($batch_id);
        $results = anthropic_batch_results($batch, 'synth');

        // Parsear artefactos; los fallos de formato reintentan con feedback
        $a_auditar = array();
        foreach ($activos as $rid => $e) {
            $r = isset($results["synth-$rid"]) ? $results["synth-$rid"] : null;

            if ($r === null || !$r['ok']) {
                $err = $r ? $r['error'] : 'sin resultado en el batch';
                prisma_log("PIPE", "Síntesis #$rid falló ($err)" . ($es_ultima ? ' — agotado.' : ' — reintento.'));
                if ($es_ultima) { $estado[$rid]['done'] = true; $stats['errores']++; }
                continue;
            }

            try {
                $artifact = parse_json_response($r['text']);
            } catch (RuntimeException $ex) {
                prisma_log("PIPE", "Síntesis #$rid: JSON inválido" . ($es_ultima ? ' — agotado.' : ' — reintento con feedback de formato.'));
                if ($es_ultima) {
                    $estado[$rid]['done'] = true;
                    $stats['errores']++;
                } else {
                    $estado[$rid]['feedback'] = "ERROR CRÍTICO: Tu respuesta anterior NO era JSON válido. "
                        . "Empezó con texto explicativo en lugar de JSON. "
                        . "Tu respuesta DEBE empezar directamente con { y ser JSON puro. "
                        . "No incluyas ningún texto antes ni después del JSON.";
                }
                continue;
            }

            $estado[$rid]['artifact'] = $artifact;
            $a_auditar[$rid] = $artifact;
        }

        if (empty($a_auditar)) continue;

        // ── Batch de auditorías ──────────────────────────────────────
        $requests = array();
        foreach ($a_auditar as $rid => $artifact) {
            $req = auditor_build($artifact, $estado[$rid]['tema']['ambito']);
            $requests[] = array(
                'custom_id'  => "audit-$rid",
                'model'      => $cfg['model_audit'],
                'system'     => $req['system'],
                'user_msg'   => $req['user_msg'],
                'max_tokens' => isset($cfg['max_tokens_pipeline']) ? $cfg['max_tokens_pipeline'] : 4096,
            );
        }

        $batch_id = anthropic_batch_submit($requests);
        $batch = anthropic_batch_wait($batch_id);
        $results = anthropic_batch_results($batch, 'audit');

        // ── Veredictos ───────────────────────────────────────────────
        foreach ($a_auditar as $rid => $artifact) {
            $r = isset($results["audit-$rid"]) ? $results["audit-$rid"] : null;

            $audit = null;
            if ($r !== null && $r['ok']) {
                try {
                    $audit = parse_json_response($r['text']);
                } catch (RuntimeException $ex) {
                    $audit = null;
                }
            }

            if ($audit === null) {
                prisma_log("PIPE", "Auditoría #$rid falló" . ($es_ultima ? ' — agotado.' : ' — reintento en la siguiente ronda.'));
                if ($es_ultima) { $estado[$rid]['done'] = true; $stats['errores']++; }
                continue;
            }

            $veredicto = isset($audit['veredicto']) ? $audit['veredicto'] : 'RECHAZO';
            $detalle = isset($audit['axiomas_detalle']) ? $audit['axiomas_detalle'] : array();
            $passed = 0;
            foreach ($detalle as $v) {
                if ($v) $passed++;
            }
            prisma_log("AUDIT", sprintf("#%d %s — %d/11 axiomas", $rid, $veredicto, $passed));

            $artifact['auditoria_moralcore'] = array(
                'veredicto'        => $veredicto,
                'puntuacion'       => isset($audit['puntuacion']) ? $audit['puntuacion'] : 0,
                'axiomas_detalle'  => isset($audit['axiomas_detalle']) ? $audit['axiomas_detalle'] : array(),
                'version_estandar' => isset($audit['version_estandar']) ? $audit['version_estandar'] : 'MC-1.0',
            );

            if ($veredicto === 'APTO' || ($veredicto === 'REVISIÓN' && $es_ultima)) {
                // Igual que la vía síncrona: tras agotar reintentos, REVISIÓN se
                // publica con su marca — mejor imperfecto que descartado.
                if ($veredicto === 'REVISIÓN') {
                    prisma_log("PIPE", "#$rid REVISIÓN tras $ronda rondas — publicando con marca.");
                }
                prisma_publicar($artifact);
                radar_link_articulo($rid, $estado[$rid]['article_id']);
                $estado[$rid]['done'] = true;
                $estado[$rid]['resultado'] = 'publicado';
                $stats['publicados']++;
                prisma_log("PIPE", "✓ PUBLICADO: " . $estado[$rid]['article_id']);
                continue;
            }

            if ($veredicto === 'REVISIÓN') {
                $estado[$rid]['feedback'] = auditor_build_feedback($audit);
                prisma_log("PIPE", "#$rid REVISIÓN — reintento con feedback en la ronda siguiente.");
                continue;
            }

            // RECHAZO
            prisma_guardar_rechazado($artifact, $audit);
            radar_marcar_rechazado($rid);
            $estado[$rid]['done'] = true;
            $estado[$rid]['resultado'] = 'rechazado';
            $stats['rechazados']++;
            prisma_log("PIPE", "✗ #$rid RECHAZO — descartado.");
        }
    }

    // Temas que agotaron rondas sin veredicto ya cuentan como errores arriba
    return $stats;
}
