<?php
/**
 * PolarPrisma — Digest diario a Telegram (cron 08:00 hora España).
 *
 * Envía al canal un único mensaje con las noticias polarizadas del día
 * anterior, ya con las 24h completas escaneadas. Ver lib/telegram.php.
 *
 * Uso:
 *   php digest_telegram.php               # ayer → envía al canal
 *   php digest_telegram.php --fecha=YYYY-MM-DD
 *   php digest_telegram.php --dry-run     # imprime el mensaje, no lo envía
 *
 * Cron Ofelia: 0 0 6 * * *  (06:00 UTC = 08:00 Europe/Madrid en verano)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/common.php'; // prisma_log()
require_once __DIR__ . '/lib/telegram.php';

$opts = getopt('', array('fecha:', 'dry-run', 'help'));

if (isset($opts['help'])) {
    echo "Uso: php digest_telegram.php [--fecha=YYYY-MM-DD] [--dry-run]\n";
    echo "Envía a Telegram el resumen de noticias polarizadas del día (por defecto, ayer).\n";
    exit(0);
}

$cfg = prisma_cfg();
if (isset($opts['fecha'])) {
    $fecha = $opts['fecha'];
} else {
    $tz = new DateTimeZone($cfg['timezone']);
    $fecha = (new DateTime('now', $tz))->modify('-1 day')->format('Y-m-d');
}

if (isset($opts['dry-run'])) {
    $texto = telegram_digest_construir($fecha);
    if ($texto === null) {
        echo "(sin temas polarizados para $fecha)\n";
        exit(0);
    }
    echo $texto . "\n";
    exit(0);
}

$ok = telegram_digest_enviar($fecha);
exit($ok ? 0 : 1);
