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
    'generado'  => date('c'),
    'dias'      => $dias,
    'umbral'    => $umbral_exito,
    'problemas' => $problemas,
);
if (!$dry) {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents("$dir/feed_alertas.json", json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if (empty($problemas)) {
    prisma_log("FEEDCHK", "Todas las fuentes operativas sanas (ventana {$dias}d).");
    echo "OK: sin fuentes problemáticas.\n";
    exit(0);
}

// 5) Resumen + aviso
$lineas = array();
foreach ($problemas as $p) {
    $det = $p['intentos'] ? " ({$p['intentos']} intentos)" : "";
    $err = !empty($p['ultimo_error']) ? " — " . mb_substr($p['ultimo_error'], 0, 90, 'UTF-8') : "";
    $lineas[] = sprintf("• %s: %d%% de éxito%s%s", $p['medio'], round($p['tasa_exito']), $det, $err);
}
$resumen_txt = count($problemas) . " fuente(s) con fallos recurrentes (últimos {$dias} días):\n" . implode("\n", $lineas);
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
