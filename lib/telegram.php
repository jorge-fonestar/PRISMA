<?php
/**
 * PolarPrisma — Digest diario por Telegram.
 *
 * Un único mensaje al día (cron 08:00 vía digest_telegram.php) con las
 * noticias polarizadas del día anterior — ya "aterrizadas", con las 24h
 * completas escaneadas. Una línea por tema: semáforo + % + título + link.
 *
 * Activación: definir en el .env del servidor
 *   TELEGRAM_BOT_TOKEN=123456:ABC-...   (token del bot, de @BotFather)
 *   TELEGRAM_CHAT_ID=@prismanews_dev     (canal público; el bot debe ser admin
 *                                         con permiso para publicar)
 * Sin esas variables todo es no-op.
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
        'chat_id'                  => $chat,
        'text'                     => $texto,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true, // sin tarjeta: son varios links en un mensaje
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
        prisma_log("TG", "Fallo al enviar a Telegram (HTTP $http_code): " . ($err ?: substr((string)$response, 0, 200)));
        return false;
    }
    return true;
}

/**
 * Semáforo de polarización por porcentaje.
 */
function telegram_semaforo(int $pct): string {
    if ($pct >= 60) return '🔴';
    if ($pct >= 45) return '🟠';
    return '🟡';
}

/**
 * Fecha larga en español (ej. "martes 21 de julio de 2026").
 */
function telegram_fecha_larga(string $ymd): string {
    $dias = array('domingo','lunes','martes','miércoles','jueves','viernes','sábado');
    $meses = array('enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre');
    $ts = strtotime($ymd);
    return $dias[(int)date('w', $ts)] . ' ' . (int)date('j', $ts) . ' de ' . $meses[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

/**
 * Construye el texto HTML del digest de un día. NO envía.
 *
 * @param string   $fecha  Y-m-d del día a resumir
 * @param float|null $umbral Override de polarización mínima (si null, config)
 * @param int|null   $cap    Override de máximo de líneas (si null, config)
 * @return string|null  HTML listo para telegram_enviar, o null si no hay temas
 */
function telegram_digest_construir(string $fecha, $umbral = null, $cap = null) {
    $cfg = prisma_cfg();
    $umbral = $umbral !== null ? (float)$umbral : (isset($cfg['telegram_digest_umbral']) ? (float)$cfg['telegram_digest_umbral'] : 0.35);
    $cap    = $cap !== null ? (int)$cap : (isset($cfg['telegram_digest_cap']) ? (int)$cfg['telegram_digest_cap'] : 10);
    $site   = rtrim(isset($cfg['site_url']) ? $cfg['site_url'] : 'https://polarprisma.org', '/');

    require_once __DIR__ . '/../db.php';
    $db = prisma_db();

    $stmt = $db->prepare("SELECT id, titulo_tema, ambito, h_score, analizado, articulo_id, resumen_neutral
        FROM radar
        WHERE fecha = :fecha AND relevancia IN ('alta','media') AND h_score >= :umbral
        ORDER BY h_score DESC, id DESC");
    $stmt->execute(array(':fecha' => $fecha, ':umbral' => $umbral));
    $temas = $stmt->fetchAll();
    if (empty($temas)) return null;

    $total = count($temas);
    $mostrar = array_slice($temas, 0, $cap);
    $esc = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    $bloques = array();
    foreach ($mostrar as $t) {
        $pct = (int)round($t['h_score'] * 100);
        $analizada = ($t['analizado'] && $t['articulo_id']);
        $link = $analizada
            ? $site . '/articulo.php?id=' . rawurlencode($t['articulo_id'])
            : $site . '/articulo.php?radar=' . (int)$t['id'];
        // Marca de estado: 🔬 análisis multipostura disponible; 🔹 solo en el radar
        $marca = $analizada ? ' · 🔬 <i>análisis</i>' : ' · 🔹 <i>en radar</i>';

        $b  = telegram_semaforo($pct) . ' <b>' . $pct . '%</b>' . $marca . "\n";
        $b .= '<a href="' . $link . '">' . $esc(trim($t['titulo_tema'])) . '</a>';
        if (!empty($t['resumen_neutral'])) {
            $b .= "\n<i>" . $esc(trim($t['resumen_neutral'])) . '</i>';
        }
        $bloques[] = $b;
    }

    $texto  = '🔺 <b>PolarPrisma · Radar de ' . telegram_fecha_larga($fecha) . "</b>\n";
    $texto .= "Las noticias donde más divergió el relato, según su índice de polarización:\n\n";
    $texto .= implode("\n\n", $bloques);
    if ($total > count($mostrar)) {
        $texto .= "\n\n<i>… y " . ($total - count($mostrar)) . ' temas más con polarización relevante.</i>';
    }
    $texto .= "\n\n🟡 leve · 🟠 media · 🔴 alta   ·   🔬 con análisis · 🔹 en radar";
    $texto .= "\n👉 Radar completo: " . $site . '/?vista=radar';

    return $texto;
}

/**
 * Construye y envía el digest. Por defecto, el del día anterior.
 *
 * @param string|null $fecha  Y-m-d; si null, ayer (zona horaria de config)
 * @return bool  true si se envió un mensaje
 */
function telegram_digest_enviar($fecha = null): bool {
    $cfg = prisma_cfg();
    if (empty($cfg['telegram_bot_token']) || empty($cfg['telegram_chat_id'])) return false;

    if ($fecha === null) {
        $tz = new DateTimeZone($cfg['timezone']);
        $fecha = (new DateTime('now', $tz))->modify('-1 day')->format('Y-m-d');
    }

    $texto = telegram_digest_construir($fecha);
    if ($texto === null) {
        prisma_log("TG", "Digest $fecha: sin temas polarizados, no se envía.");
        return false;
    }

    $ok = telegram_enviar($texto);
    prisma_log("TG", "Digest $fecha " . ($ok ? "enviado." : "falló el envío."));
    return $ok;
}
