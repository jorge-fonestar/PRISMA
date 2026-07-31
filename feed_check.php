<?php
/**
 * PolarPrisma — Chequeo semanal de salud de feeds.
 *
 * Detecta fuentes que DEBERÍAN funcionar (RSS/portada) pero fallan de forma
 * recurrente, y avisa:
 *   - escribe data/feed_alertas.json → el panel lo muestra como banner.
 *   - si TELEGRAM_ADMIN_CHAT_ID está configurado, envía aviso a ese chat privado
 *     (nunca al canal público).
 *
 * A diferencia del widget del panel, aquí SÍ se detectan las fuentes con 0% de
 * éxito (p. ej. EFE): `feed_health_alertas` solo veía las que dejaron de responder
 * tras haber funcionado.
 *
 * Cron semanal (Ofelia). Uso CLI:
 *   php feed_check.php            # comprueba, guarda estado y avisa
 *   php feed_check.php --dry-run  # solo muestra, no avisa ni guarda
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/logger.php';
require_once __DIR__ . '/lib/fuentes/feed_health.php';
require_once __DIR__ . '/lib/telegram.php';

/** Reprueba una URL de feed: true si responde 2xx con items RSS/Atom. */
function feed_check_probe($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; PolarPrismaBot/1.0; +https://polarprisma.org)',
    ));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300 || !$body) return false;
    return (strpos($body, '<item') !== false || strpos($body, '<entry') !== false);
}

$dry = in_array('--dry-run', $argv, true);
$cfg = prisma_cfg();
$dias = 7;
$umbral_exito = 60.0;   // % de éxito por debajo del cual una fuente operativa es problemática

// 1) Fuentes operativas con baja tasa de éxito (capta EFE al 0%)
$problemas = array();
foreach (feed_health_resumen($dias) as $r) {
    if ($r['modalidad'] === 'no_disponible') continue;   // caídas conocidas/aceptadas
    if ((int)$r['total'] < 3) continue;                   // pocos intentos: sin señal fiable
    if ((float)$r['tasa_exito'] >= $umbral_exito) continue;
    $problemas[$r['medio']] = array(
        'medio'      => $r['medio'],
        'modalidad'  => $r['modalidad'],
        'tasa_exito' => (float)$r['tasa_exito'],
        'intentos'   => (int)$r['total'],
    );
}

// 2) Fuentes que dejaron de responder (tenían éxito y pararon)
foreach (feed_health_alertas($dias) as $a) {
    if (isset($problemas[$a['medio']])) continue;
    if (($a['modalidad'] ?? '') === 'no_disponible') continue;
    $problemas[$a['medio']] = array(
        'medio'      => $a['medio'],
        'modalidad'  => $a['modalidad'],
        'tasa_exito' => 0.0,
        'intentos'   => 0,
        'ultimo_ok'  => $a['ultimo_exito'] ?? null,
    );
}

// Solo alertar de fuentes que SIGUEN en la config y se ESPERA que funcionen: se usa
// la modalidad ACTUAL de la config (no la histórica de feed_health), de modo que un
// feed marcado hoy como no_disponible (p.ej. EFE) deja de alertar de inmediato, y los
// feeds ya retirados (RTVE) tampoco aparecen.
require_once __DIR__ . '/lib/fuentes/normalizar.php';
$modalidad_actual = array();
foreach ($cfg['fuentes'] as $amb => $cuads) {
    foreach ($cuads as $c => $medios) {
        foreach ($medios as $entrada) {
            $nf = rss_normalizar_fuente($entrada, $c, $amb);
            $modalidad_actual[$nf['medio']] = isset($nf['modalidad']) ? $nf['modalidad'] : 'rss_nativo';
        }
    }
}
$problemas = array_filter($problemas, function ($p) use ($modalidad_actual) {
    return isset($modalidad_actual[$p['medio']]) && $modalidad_actual[$p['medio']] !== 'no_disponible';
});

// 2b) Sonda de recuperación: reprueba los feeds caídos que tengan url_candidata.
//     Si uno vuelve a servir RSS, se avisa para reactivarlo A MANO (un cron editando
//     la config sería frágil). Así "vuelve solo" a estar en el radar en un cambio de 1 línea.
$recuperadas = array();
foreach ($cfg['fuentes'] as $amb => $cuads) {
    foreach ($cuads as $c => $medios) {
        foreach ($medios as $entrada) {
            if (!is_array($entrada) || !isset($entrada['medio'])) continue;
            if (($entrada['modalidad'] ?? '') !== 'no_disponible') continue;
            $cand = isset($entrada['url_candidata']) ? $entrada['url_candidata'] : null;
            if ($cand && feed_check_probe($cand)) {
                $recuperadas[] = array('medio' => $entrada['medio'], 'url' => $cand);
            }
        }
    }
}

// 3) Último mensaje de error de cada fuente problemática (para el detalle)
$ldb = prisma_logger_db();
foreach ($problemas as $medio => &$p) {
    $st = $ldb->prepare("SELECT error_msg FROM feed_health WHERE medio = ? AND error_msg IS NOT NULL ORDER BY id DESC LIMIT 1");
    $st->execute(array($medio));
    $err = $st->fetchColumn();
    $p['ultimo_error'] = $err !== false ? $err : null;
}
unset($p);
$problemas = array_values($problemas);

// 4) Persistir estado para el panel
$estado = array(
    'generado'    => date('c'),
    'dias'        => $dias,
    'umbral'      => $umbral_exito,
    'problemas'   => $problemas,
    'recuperadas' => $recuperadas,
);
if (!$dry) {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents("$dir/feed_alertas.json", json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if (empty($problemas) && empty($recuperadas)) {
    prisma_log("FEEDCHK", "Todas las fuentes operativas sanas (ventana {$dias}d).");
    echo "OK: sin fuentes problemáticas.\n";
    exit(0);
}

// 5) Resumen + aviso
$secciones = array();
if (!empty($problemas)) {
    $lineas = array();
    foreach ($problemas as $p) {
        $det = $p['intentos'] ? " ({$p['intentos']} intentos)" : "";
        $err = !empty($p['ultimo_error']) ? " — " . mb_substr($p['ultimo_error'], 0, 90, 'UTF-8') : "";
        $lineas[] = sprintf("• %s: %d%% de éxito%s%s", $p['medio'], round($p['tasa_exito']), $det, $err);
    }
    $secciones[] = count($problemas) . " fuente(s) con fallos recurrentes (últimos {$dias} días):\n" . implode("\n", $lineas);
}
if (!empty($recuperadas)) {
    $lineas = array();
    foreach ($recuperadas as $r) $lineas[] = "• {$r['medio']} → {$r['url']}";
    $secciones[] = "✅ " . count($recuperadas) . " fuente(s) caída(s) parecen haber VUELTO — reactívalas en config:\n" . implode("\n", $lineas);
}
$resumen_txt = implode("\n\n", $secciones);
prisma_log("FEEDCHK", str_replace("\n", " | ", $resumen_txt));
echo $resumen_txt . "\n";

$admin = isset($cfg['telegram_admin_chat_id']) ? $cfg['telegram_admin_chat_id'] : '';
if ($dry) {
    echo "(dry-run: no se guarda estado ni se avisa)\n";
} elseif ($admin !== '') {
    $msg = "⚠️ <b>PolarPrisma · salud de fuentes</b>\n\n"
        . htmlspecialchars($resumen_txt, ENT_QUOTES, 'UTF-8')
        . "\n\nRevisa el panel para el detalle y corregir la fuente.";
    $ok = telegram_enviar($msg, $admin);
    prisma_log("FEEDCHK", "Aviso Telegram admin: " . ($ok ? "enviado" : "falló el envío"));
} else {
    prisma_log("FEEDCHK", "TELEGRAM_ADMIN_CHAT_ID sin configurar — aviso solo en el panel.");
}
exit(0);
