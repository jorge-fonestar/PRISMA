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
