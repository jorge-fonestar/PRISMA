<?php
/**
 * Prisma — Funciones comunes del pipeline.
 */

/**
 * Log con timestamp y categoría.
 */
function prisma_log($cat, $msg) {
    $ts = date('H:i:s');
    $line = "[$ts] [$cat] $msg\n";
    // STDERR solo existe en CLI real; en CGI/web usamos stdout
    if (defined('STDERR') && is_resource(STDERR)) {
        fwrite(STDERR, $line);
    } else {
        echo $line;
        if (ob_get_level()) ob_flush();
        flush();
    }
}

/**
 * Genera un ID de artículo: YYYY-MM-DD-NNN
 *
 * Continúa la numeración ya publicada hoy: antes numeraba solo por posición
 * en la ejecución, y una segunda ejecución el mismo día (cron + panel/--id)
 * sobrescribía los artículos de la primera vía INSERT OR REPLACE.
 *
 * @param int $seq Desplazamiento dentro de la misma ejecución (1-based):
 *                 el lote reserva N ids consecutivos a partir del siguiente libre.
 */
function prisma_gen_id(int $seq = 1): string {
    $cfg = prisma_cfg();
    $tz = new DateTimeZone($cfg['timezone']);
    $today = (new DateTime('now', $tz))->format('Y-m-d');

    require_once __DIR__ . '/../db.php';
    $db = prisma_db();
    $stmt = $db->prepare('SELECT id FROM articulos WHERE id LIKE :pref ORDER BY id DESC LIMIT 1');
    $stmt->execute([':pref' => $today . '-%']);
    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last && preg_match('/-(\d{3})$/', $last, $m)) {
        $next = (int)$m[1] + 1;
    }

    return sprintf('%s-%03d', $today, $next + $seq - 1);
}

/**
 * Publica un artefacto insertándolo directamente en la DB local.
 * Más fiable que llamar al endpoint HTTP desde el mismo servidor.
 */
function prisma_publicar(array $artifact): bool {
    require_once __DIR__ . '/../db.php';

    $db = prisma_db();
    $data = $artifact;

    $stmt = $db->prepare('INSERT OR REPLACE INTO articulos
        (id, fecha_publicacion, ambito, titular_neutral, resumen, payload, veredicto, puntuacion, fuentes_total)
        VALUES (:id, :fecha, :ambito, :titular, :resumen, :payload, :veredicto, :puntuacion, :fuentes)');

    $stmt->execute([
        ':id'        => $data['id'],
        ':fecha'     => $data['fecha_publicacion'],
        ':ambito'    => $data['ambito'],
        ':titular'   => $data['titular_neutral'],
        ':resumen'   => $data['resumen'],
        ':payload'   => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':veredicto' => $data['auditoria_moralcore']['veredicto'] ?? null,
        ':puntuacion'=> $data['auditoria_moralcore']['puntuacion'] ?? null,
        ':fuentes'   => $data['fuentes_consultadas_total'] ?? null,
    ]);

    prisma_log("PUB", "Publicado: {$data['id']}");

    // Tarjeta social (OG) — se genera y cachea al publicar. Nunca debe romper la
    // publicación: si GD/fuente no están o falla, se registra y sigue.
    try {
        require_once __DIR__ . '/og.php';
        $og_ok = og_generar_articulo($data['id']);
        prisma_log("OG", "Tarjeta " . ($og_ok ? "generada" : "no generada") . ": {$data['id']}");
    } catch (\Throwable $e) {
        prisma_log("OG", "Error generando tarjeta {$data['id']}: " . $e->getMessage());
    }

    // Los avisos a Telegram se envían en el digest diario (digest_telegram.php),
    // no en cada publicación.

    return true;
}

/**
 * Guarda un artefacto rechazado para análisis posterior.
 */
function prisma_guardar_rechazado(array $artifact, array $audit): void {
    $dir = __DIR__ . '/../rechazados';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $payload = ['artifact' => $artifact, 'audit' => $audit];
    $id = $artifact['id'] ?? 'unknown';
    $path = "$dir/$id.json";

    file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    prisma_log("PUB", "Rechazado guardado en $path");
}

/**
 * Ejecuta el pipeline para un tema: sintetizar → auditar → decidir.
 *
 * @param string $contexto Contexto del tema (de curador o manual)
 * @param string $article_id ID del artefacto
 * @param string $ambito "españa"|"europa"|"global"
 * @param bool $manual true = modo manual (sin contexto RSS)
 * @return array|null Artefacto publicado, o null si rechazado
 */
function prisma_procesar_tema(string $contexto, string $article_id, string $ambito = 'españa', bool $manual = false): ?array {
    $max_retries = 2;
    $feedback = '';

    for ($attempt = 1; $attempt <= $max_retries + 1; $attempt++) {
        prisma_log("PIPE", "═══ Intento $attempt/" . ($max_retries + 1) . " ═══");

        // ── Sintetizar ──
        try {
            if ($manual) {
                $artifact = sintetizar_manual($contexto, $article_id, $ambito, $feedback);
            } else {
                $artifact = sintetizar($contexto, $article_id, $ambito, $feedback);
            }
            // El id y el ámbito son autoridad del servidor: el modelo a veces
            // los "corrige" (p.ej. pone la fecha de la noticia en el id), y
            // publicar con otro id rompe el enlace del radar o pisa artículos.
            $artifact['id'] = $article_id;
            $artifact['ambito'] = $ambito;
            // La fecha también la fija el servidor (el prompt es estático para el caching).
            $artifact['fecha_publicacion'] = (new DateTime('now', new DateTimeZone(prisma_cfg()['timezone'])))->format('c');
        } catch (RuntimeException $e) {
            // Fallo de formato o respuesta incompleta (p.ej. se quedó sin tokens
            // buscando y no emitió el JSON) — reintentar pidiendo JSON directo y conciso.
            $emsg = $e->getMessage();
            if ((strpos($emsg, 'JSON inválido') !== false || strpos($emsg, 'Respuesta inesperada') !== false) && $attempt <= $max_retries) {
                prisma_log("PIPE", "ERROR formato/respuesta: " . $emsg);
                $feedback = "ERROR CRÍTICO: tu respuesta anterior no terminó en un JSON válido. "
                    . "Sé conciso, limita las búsquedas web a lo imprescindible y asegúrate de "
                    . "TERMINAR con el JSON completo del artefacto (de { a }), sin texto después.";
                continue;
            }
            throw $e;
        }

        // ── Auditar ──
        $audit = auditar($artifact, $ambito);
        $veredicto = $audit['veredicto'] ?? 'RECHAZO';

        // Inyectar auditoría en el artefacto
        $artifact['auditoria_moralcore'] = [
            'veredicto'       => $veredicto,
            'puntuacion'      => $audit['puntuacion'] ?? 0,
            'axiomas_detalle' => $audit['axiomas_detalle'] ?? [],
            'version_estandar'=> $audit['version_estandar'] ?? 'MC-1.0',
        ];

        // ── Decidir ──
        if ($veredicto === 'APTO') {
            prisma_log("PIPE", "✓ APTO — Publicando...");
            prisma_publicar($artifact);
            return $artifact;
        }

        if ($veredicto === 'REVISIÓN') {
            if ($attempt > $max_retries) {
                // Tras agotar reintentos, publicar marcado como REVISIÓN
                // Mejor publicar contenido imperfecto pero útil que descartarlo
                prisma_log("PIPE", "REVISIÓN tras $attempt intentos — publicando con marca de revisión.");
                prisma_publicar($artifact);
                return $artifact;
            }
            $feedback = auditor_build_feedback($audit);
            prisma_log("PIPE", "REVISIÓN — reintentando con feedback...");
            continue;
        }

        // RECHAZO
        prisma_log("PIPE", "✗ RECHAZO — descartando tema.");
        prisma_guardar_rechazado($artifact, $audit);
        return null;
    }

    return null;
}

// ── Radar ────────────────────────────────────────────────────────────

/**
 * Inserts all detected topics into the radar table.
 *
 * @param array $temas All scored topics from curador_seleccionar()
 * @param string $ambito Current ambito
 * @param string $fecha Date string YYYY-MM-DD
 * @return array Same topics with 'radar_id' added to each
 */
function radar_insertar_todos(array $temas, string $ambito, string $fecha): array {
    require_once __DIR__ . '/../db.php';
    require_once __DIR__ . '/curador.php'; // extraer_keywords / keywords_similarity
    $db = prisma_db();

    // Filas de HOY (dedup exacto por título en re-escaneos del mismo día)
    $check = $db->prepare('SELECT id, titulo_tema FROM radar WHERE fecha = :fecha AND ambito = :ambito');
    $check->execute(array(':fecha' => $fecha, ':ambito' => $ambito));
    $existentes = array();
    while ($row = $check->fetch()) {
        $existentes[mb_strtolower(trim($row['titulo_tema']), 'UTF-8')] = $row['id'];
    }

    $stmt = $db->prepare('INSERT INTO radar
        (fecha, titulo_tema, ambito, h_score, h_asimetria, h_divergencia, h_varianza,
         h_cobertura_mutua, h_framing, h_silencio, framing_divergence, framing_evidence,
         relevancia, dominio_tematico, scoring_version, resumen_neutral, fuentes_json)
        VALUES (:fecha, :titulo, :ambito, :h_score, :h_asim, :h_div, :h_var,
                :h_cob, :h_frm, :h_sil, :fd, :fev, :rel, :dom, :sv, :resumen, :fuentes)');

    // UPDATE de señales + fuentes: la lista de fuentes DEBE refrescarse con el
    // cluster actual — antes se congelaba en la primera foto del día y la
    // Fase 2 / página de silencios trabajaban con datos desactualizados.
    $upd_hoy = $db->prepare('UPDATE radar SET
        h_score = :h_score, h_asimetria = :h_asim, h_divergencia = :h_div, h_varianza = :h_var,
        h_cobertura_mutua = :h_cob, h_framing = :h_frm, h_silencio = :h_sil,
        framing_divergence = :fd, framing_evidence = :fev,
        relevancia = :rel, dominio_tematico = :dom, scoring_version = :sv,
        resumen_neutral = :resumen, fuentes_json = :fuentes
        WHERE id = :id');

    $insertados = 0;
    $duplicados = 0;

    foreach ($temas as &$tema) {
        $key = mb_strtolower(trim($tema['titulo_tema']), 'UTF-8');

        $fuentes = array();
        foreach ($tema['articulos'] as $art) {
            $f = array(
                'medio'     => $art['medio'],
                'titulo'    => $art['titulo'],
                'url'       => $art['url'],
                'cuadrante' => $art['cuadrante'],
            );
            // Entradilla truncada: enriquece el contexto de la Fase 2
            // (curador_preparar_contexto) cuando el tema viene del radar.
            if (!empty($art['descripcion'])) {
                $f['descripcion'] = mb_substr(strip_tags($art['descripcion']), 0, 300, 'UTF-8');
            }
            $fuentes[] = $f;
        }
        $fuentes_json = json_encode($fuentes, JSON_UNESCAPED_UNICODE);

        $senales = array(
            ':h_score' => $tema['h_score'],
            ':h_asim'  => isset($tema['h_cobertura_mutua']) ? $tema['h_cobertura_mutua'] : $tema['h_asimetria'],
            ':h_div'   => isset($tema['h_framing']) ? $tema['h_framing'] : $tema['h_divergencia'],
            ':h_var'   => isset($tema['h_silencio']) ? $tema['h_silencio'] : $tema['h_varianza'],
            ':h_cob'   => isset($tema['h_cobertura_mutua']) ? $tema['h_cobertura_mutua'] : null,
            ':h_frm'   => isset($tema['h_framing']) ? $tema['h_framing'] : null,
            ':h_sil'   => isset($tema['h_silencio']) ? $tema['h_silencio'] : null,
            ':fd'      => isset($tema['framing_divergence']) ? $tema['framing_divergence'] : null,
            ':fev'     => isset($tema['framing_evidence']) ? $tema['framing_evidence'] : null,
            ':rel'     => isset($tema['relevancia']) ? $tema['relevancia'] : null,
            ':dom'     => isset($tema['dominio_tematico']) ? $tema['dominio_tematico'] : null,
            ':resumen' => isset($tema['resumen_neutral']) ? $tema['resumen_neutral'] : null,
            ':fuentes' => $fuentes_json,
        );

        // 1) Mismo título hoy → refrescar señales y fuentes
        if (isset($existentes[$key])) {
            $tema['radar_id'] = $existentes[$key];
            $duplicados++;

            $sv = isset($tema['scoring_version']) ? $tema['scoring_version'] : null;
            if ($sv === 'v2') {
                $upd_hoy->execute($senales + array(':sv' => 'v2', ':id' => $existentes[$key]));
            }
            continue;
        }

        // 2) Historia nueva → insertar
        $stmt->execute($senales + array(
            ':fecha'  => $fecha,
            ':titulo' => $tema['titulo_tema'],
            ':ambito' => $ambito,
            ':sv'     => isset($tema['scoring_version']) ? $tema['scoring_version'] : 'v1',
        ));

        $tema['radar_id'] = $db->lastInsertId();
        $insertados++;
    }
    unset($tema);

    $msg = "$insertados temas insertados en radar.";
    if ($duplicados > 0) $msg .= " $duplicados actualizados (mismo día).";
    prisma_log("RADAR", $msg);
    return $temas;
}

/**
 * Updates a radar record with Haiku triage results.
 *
 * Guarda la frase y el estado del triage SIN tocar `analizado`: un tema
 * confirmado que no entre en el top N del día debe seguir en cola (antes se
 * marcaba analizado=1 y se perdía sin llegar a sintetizarse).
 *
 * @param bool $confirma true = polarización genuina, false = falso positivo
 */
function radar_actualizar_triage(int $radar_id, string $frase, bool $confirma): void {
    require_once __DIR__ . '/../db.php';
    $db = prisma_db();
    $stmt = $db->prepare('UPDATE radar SET haiku_frase = :frase, triage = :triage WHERE id = :id');
    $stmt->execute([
        ':frase'  => $frase,
        ':triage' => $confirma ? 'confirmado' : 'descartado',
        ':id'     => $radar_id,
    ]);
}

/**
 * Marca un tema como consumido tras RECHAZO de la auditoría Moral Core:
 * sale de la cola de pendientes y no se reintenta en ejecuciones futuras.
 */
function radar_marcar_rechazado(int $radar_id): void {
    require_once __DIR__ . '/../db.php';
    $db = prisma_db();
    $stmt = $db->prepare("UPDATE radar SET analizado = 1, triage = 'rechazado' WHERE id = :id");
    $stmt->execute([':id' => $radar_id]);
}

/**
 * Links a radar record to a published article.
 */
function radar_link_articulo(int $radar_id, string $articulo_id): void {
    require_once __DIR__ . '/../db.php';
    $db = prisma_db();
    $stmt = $db->prepare('UPDATE radar SET articulo_id = :aid, analizado = 1 WHERE id = :id');
    $stmt->execute([':aid' => $articulo_id, ':id' => $radar_id]);
}

/**
 * Afina el índice de polarización del radar con el valor que el análisis
 * profundo (sintetizador, contexto completo) calcula. Sustituye h_score
 * (opción A) preservando el estructural en h_score_estructural para trazar
 * la corrección. Solo actúa si el artefacto trae un indice_polarizacion válido.
 */
function radar_afinar_polarizacion(int $radar_id, array $artifact): void {
    if (!isset($artifact['indice_polarizacion'])) return;
    $indice = (int)$artifact['indice_polarizacion'];
    if ($indice < 0 || $indice > 100) return;

    require_once __DIR__ . '/../db.php';
    $db = prisma_db();
    // Preservar el h_score estructural la primera vez (si aún no se guardó)
    $db->prepare('UPDATE radar SET h_score_estructural = h_score
        WHERE id = :id AND h_score_estructural IS NULL')->execute([':id' => $radar_id]);
    $db->prepare('UPDATE radar SET h_score = :hs WHERE id = :id')
       ->execute([':hs' => $indice / 100.0, ':id' => $radar_id]);
    prisma_log("PIPE", "#$radar_id índice de polarización revisado por análisis: {$indice}%");
}

/**
 * Cleans up radar entries older than 90 days.
 */
function radar_limpiar(): void {
    require_once __DIR__ . '/../db.php';
    $db = prisma_db();
    $deleted = $db->exec("DELETE FROM radar WHERE fecha < date('now', '-90 days')");
    if ($deleted > 0) {
        prisma_log("RADAR", "Limpieza: $deleted registros antiguos eliminados.");
    }
}

// ── Haiku Triage ─────────────────────────────────────────────────────

/**
 * Haiku triage: confirms tension and generates explanatory phrases.
 *
 * @param array $candidatos Topics with H >= umbral (must have 'radar_id', 'titulo_tema', 'articulos')
 * @return array Filtered array of confirmed topics, each with 'haiku_frase' added
 */
function triage_haiku(array $candidatos): array {
    require_once __DIR__ . '/anthropic.php';
    $cfg = prisma_cfg();

    // Build batch prompt
    $temas_text = '';
    foreach ($candidatos as $i => $tema) {
        $num = $i + 1;
        $temas_text .= "\nTema $num: \"{$tema['titulo_tema']}\" (H={$tema['h_score']})\n";

        $por_cuadrante = [];
        foreach ($tema['articulos'] as $art) {
            $por_cuadrante[$art['cuadrante']][] = $art['titulo'];
        }
        foreach ($por_cuadrante as $cuadrante => $titulares) {
            $temas_text .= "  [$cuadrante]: " . implode(' | ', array_slice($titulares, 0, 3)) . "\n";
        }
    }

    $system = 'Eres un analista de medios. Evalúas si la polarización informativa detectada entre fuentes de distintos cuadrantes ideológicos es genuina o un falso positivo (ej. vocabulario técnico diverso que no refleja divergencia editorial real). Respondes SOLO en JSON válido, sin markdown.';

    $user_msg = "Evalúa estos " . count($candidatos) . " temas. Para cada uno, indica si la polarización es genuina (confirma: true/false) y una frase explicativa de una línea en español describiendo la naturaleza de la polarización o la razón del descarte.

Responde con un array JSON:
[{\"tema\": 1, \"confirma\": true/false, \"frase\": \"...\"}]

$temas_text";

    try {
        $model = $cfg['model_triage'];
        $raw = anthropic_call($model, $system, $user_msg, 2048);
        $results = parse_json_response($raw);
    } catch (Throwable $e) {
        // Fallback: skip triage, use all candidates
        prisma_log("TRIAGE", "ERROR Haiku: " . $e->getMessage() . " — usando H score bruto.");
        foreach ($candidatos as &$tema) {
            $tema['haiku_frase'] = null;
            radar_actualizar_triage($tema['radar_id'], '', true);
        }
        unset($tema);
        return $candidatos;
    }

    // Map results back to candidates
    $confirmados = [];
    foreach ($results as $r) {
        $idx = ($r['tema'] ?? 0) - 1;
        if ($idx < 0 || $idx >= count($candidatos)) continue;

        $tema = $candidatos[$idx];
        $frase = $r['frase'] ?? '';
        $confirma = !empty($r['confirma']);

        radar_actualizar_triage($tema['radar_id'], $frase, $confirma);
        $tema['haiku_frase'] = $frase;

        if ($confirma) {
            $confirmados[] = $tema;
        } else {
            prisma_log("TRIAGE", "Descartado: \"{$tema['titulo_tema']}\" — $frase");
        }
    }

    prisma_log("TRIAGE", count($confirmados) . " de " . count($candidatos) . " confirmados por Haiku.");
    return $confirmados;
}

// ── Scoring v2 Anomalies ──────────────────────────────────────────

/**
 * Logs a scoring anomaly to the database.
 *
 * @param string $fecha Date string
 * @param int|null $radar_id Radar record ID
 * @param string $tipo Anomaly type (ANOMALY_POLITICAL_LOW, etc.)
 * @param string $detalle Description
 */
function scoring_log_anomaly(string $fecha, $radar_id, string $tipo, string $detalle): void {
    require_once __DIR__ . '/../db.php';
    $db = prisma_db();
    $stmt = $db->prepare('INSERT INTO scoring_anomalies (fecha, radar_id, tipo, detalle) VALUES (:f, :r, :t, :d)');
    $stmt->execute(array(':f' => $fecha, ':r' => $radar_id, ':t' => $tipo, ':d' => $detalle));
    prisma_log("ANOMALY", "[$tipo] $detalle");
}
