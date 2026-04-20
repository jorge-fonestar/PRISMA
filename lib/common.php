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
 */
function prisma_gen_id(int $seq = 1): string {
    $cfg = prisma_cfg();
    $tz = new DateTimeZone($cfg['timezone']);
    $today = (new DateTime('now', $tz))->format('Y-m-d');
    return sprintf('%s-%03d', $today, $seq);
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
        if ($manual) {
            $artifact = sintetizar_manual($contexto, $article_id, $ambito, $feedback);
        } else {
            $artifact = sintetizar($contexto, $article_id, $ambito, $feedback);
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
