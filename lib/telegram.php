<?php
/**
 * PolarPrisma — Aviso por Telegram al publicar un artículo.
 *
 * Envía un mensaje al grupo/canal configurado cada vez que se publica un
 * análisis (vía prisma_publicar, así cubre cron batch, panel, --id y manuales).
 *
 * Activación: definir en el .env del servidor
 *   TELEGRAM_BOT_TOKEN=123456:ABC-...   (token del bot, de @BotFather)
 *   TELEGRAM_CHAT_ID=-1001234567890     (id del grupo/canal; el bot debe ser miembro
 *                                        con permiso para publicar)
 * Sin esas variables la función es un no-op. Un fallo de envío se loguea y
 * NUNCA rompe el pipeline de publicación.
 *
 * Prueba manual:
 *   docker exec polarprisma php -r 'require "/var/www/html/config.php";
 *     require "/var/www/html/lib/logger.php"; require "/var/www/html/lib/telegram.php";
 *     function prisma_log($a,$b){echo "[$a] $b\n";}
 *     var_dump(telegram_enviar("Prueba de PolarPrisma \xF0\x9F\x94\xBA"));'
 */

/**
 * Envía un texto (HTML de Telegram) al chat configurado.
 *
 * @return bool true si Telegram aceptó el mensaje
 */
function telegram_enviar(string $texto): bool {
    $cfg = prisma_cfg();
    $token = isset($cfg['telegram_bot_token']) ? $cfg['telegram_bot_token'] : '';
    $chat  = isset($cfg['telegram_chat_id']) ? $cfg['telegram_chat_id'] : '';
    if ($token === '' || $chat === '') return false;

    $payload = array(
        'chat_id'    => $chat,
        'text'       => $texto,
        'parse_mode' => 'HTML',
    );

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ));
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $http_code !== 200) {
        prisma_log("TG", "Fallo al enviar aviso Telegram (HTTP $http_code): " . ($err ?: substr((string)$response, 0, 200)));
        return false;
    }
    return true;
}

/**
 * Avisa de los titulares del día que superan el umbral de polarización.
 *
 * Se llama al final de escanear.php: busca temas de hoy con
 * h_score >= telegram_umbral_titular, relevancia alta/media y sin aviso
 * previo (tg_notificado=0), envía un mensaje por tema y los marca.
 * Idempotente entre escaneos: cada tema avisa una sola vez, aunque el
 * aviso llega en cuanto el tema cruza el umbral en cualquier pasada.
 *
 * @param float|null $umbral_override Solo para pruebas manuales.
 * @return int Nº de avisos enviados
 */
function telegram_notificar_titulares($umbral_override = null): int {
    $cfg = prisma_cfg();
    if (empty($cfg['telegram_bot_token']) || empty($cfg['telegram_chat_id'])) return 0;
    if (empty($cfg['telegram_avisar_titulares']) && $umbral_override === null) return 0;

    require_once __DIR__ . '/../db.php';
    $db = prisma_db();

    $umbral = $umbral_override !== null ? (float)$umbral_override
        : (isset($cfg['telegram_umbral_titular']) ? (float)$cfg['telegram_umbral_titular'] : 0.50);

    $tz = new DateTimeZone($cfg['timezone']);
    $hoy = (new DateTime('now', $tz))->format('Y-m-d');

    $stmt = $db->prepare("SELECT id, titulo_tema, ambito, h_score, h_silencio, fuentes_json
        FROM radar
        WHERE fecha = :hoy AND tg_notificado = 0 AND h_score >= :umbral
        AND relevancia IN ('alta','media')
        ORDER BY h_score DESC LIMIT 5");
    $stmt->execute(array(':hoy' => $hoy, ':umbral' => $umbral));
    $temas = $stmt->fetchAll();
    if (empty($temas)) return 0;

    require_once __DIR__ . '/curador.php'; // PRISMA_GRUPO_*
    $site = rtrim(isset($cfg['site_url']) ? $cfg['site_url'] : 'https://polarprisma.org', '/');
    $marcar = $db->prepare('UPDATE radar SET tg_notificado = 1 WHERE id = :id');
    $esc = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
    $enviados = 0;

    foreach ($temas as $t) {
        $fuentes = json_decode($t['fuentes_json'], true) ?: array();
        $bloques = array('izquierda' => 0, 'centro' => 0, 'derecha' => 0);
        foreach ($fuentes as $f) {
            $c = isset($f['cuadrante']) ? $f['cuadrante'] : '';
            if (in_array($c, PRISMA_GRUPO_IZQ)) $bloques['izquierda']++;
            elseif (in_array($c, PRISMA_GRUPO_DER)) $bloques['derecha']++;
            else $bloques['centro']++;
        }
        $cubren = array();
        $callan = array();
        foreach ($bloques as $nombre => $n) {
            if ($n > 0) $cubren[] = "$nombre ($n)";
            else $callan[] = $nombre;
        }

        $pct = round($t['h_score'] * 100);
        $texto = "🔴 <b>Polarización $pct%</b> · " . $esc(ucfirst($t['ambito'])) . "\n\n"
            . '<b>' . $esc($t['titulo_tema']) . "</b>\n\n"
            . '<i>Cubren: ' . $esc(implode(' · ', $cubren)) . '</i>';
        if (!empty($callan)) {
            $texto .= "\n<i>Sin cobertura del bloque: " . $esc(implode(', ', $callan)) . '</i>';
        }
        $texto .= "\n\n👉 " . $site . '/articulo.php?radar=' . (int)$t['id'];

        if (telegram_enviar($texto)) {
            $marcar->execute(array(':id' => $t['id']));
            $enviados++;
        }
    }

    if ($enviados > 0) prisma_log("TG", "$enviados avisos de titular enviados a Telegram.");
    return $enviados;
}

/**
 * Construye y envía el aviso de artículo publicado.
 * Llamar tras la publicación; tolera artefactos incompletos.
 */
function telegram_notificar_articulo(array $artifact): void {
    $cfg = prisma_cfg();
    if (empty($cfg['telegram_bot_token']) || empty($cfg['telegram_chat_id'])) return;

    try {
        $site = rtrim(isset($cfg['site_url']) ? $cfg['site_url'] : 'https://polarprisma.org', '/');
        $url = $site . '/articulo.php?id=' . rawurlencode($artifact['id']);

        $titular = isset($artifact['titular_neutral']) ? $artifact['titular_neutral'] : $artifact['id'];
        $n_posturas = count(isset($artifact['mapa_posturas']) ? $artifact['mapa_posturas'] : array());
        $n_fuentes = isset($artifact['fuentes_consultadas_total']) ? (int)$artifact['fuentes_consultadas_total'] : 0;
        $audit = isset($artifact['auditoria_moralcore']) ? $artifact['auditoria_moralcore'] : array();
        $veredicto = isset($audit['veredicto']) ? $audit['veredicto'] : '';
        $punt = isset($audit['puntuacion']) ? round($audit['puntuacion'] * 11) : null;

        $meta = array();
        if ($n_posturas > 0) $meta[] = "$n_posturas posturas";
        if ($n_fuentes > 0) $meta[] = "$n_fuentes fuentes";
        if ($veredicto !== '') $meta[] = "Moral Core: $veredicto" . ($punt !== null ? " ($punt/11)" : '');

        $resumen = isset($artifact['resumen']) ? trim($artifact['resumen']) : '';
        if (mb_strlen($resumen, 'UTF-8') > 250) {
            $resumen = mb_substr($resumen, 0, 247, 'UTF-8') . '…';
        }

        $esc = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

        $texto = "🔺 <b>Nuevo análisis en PolarPrisma</b>\n\n"
            . '<b>' . $esc($titular) . "</b>\n";
        if ($resumen !== '') $texto .= "\n" . $esc($resumen) . "\n";
        if (!empty($meta)) $texto .= "\n<i>" . $esc(implode(' · ', $meta)) . "</i>\n";
        $texto .= "\n👉 " . $url;

        telegram_enviar($texto);
    } catch (Throwable $e) {
        prisma_log("TG", "Error construyendo aviso Telegram: " . $e->getMessage());
    }
}
