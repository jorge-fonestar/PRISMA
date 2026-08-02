<?php
/**
 * PolarPrisma — Autopublicación en redes del análisis más polarizado del día.
 *
 * Salvaguardas: umbral mínimo de polarización, tope de 1/día, idempotencia
 * (ledger en data/autopost_hechos.json — nunca repite), y DESACTIVADO por
 * defecto (config autopost_enabled). MVP: solo canal Telegram propio.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/telegram.php';

define('AUTOPOST_LEDGER', dirname(__DIR__) . '/data/autopost_hechos.json');

function autopost_ledger() {
    if (!is_file(AUTOPOST_LEDGER)) return array();
    $j = json_decode(file_get_contents(AUTOPOST_LEDGER), true);
    return is_array($j) ? $j : array();
}

function autopost_marcar($id) {
    $l = autopost_ledger();
    if (!in_array($id, $l, true)) {
        $l[] = $id;
        @file_put_contents(AUTOPOST_LEDGER, json_encode($l, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

/** Datos de publicación de un artículo concreto (artículo + su radar). */
function autopost_datos($id) {
    $db = prisma_db();
    $st = $db->prepare("SELECT a.id, a.titular_neutral, a.veredicto, a.fuentes_total,
                               r.h_score, r.fuentes_json, r.haiku_frase
                        FROM articulos a
                        LEFT JOIN radar r ON r.articulo_id = a.id
                        WHERE a.id = :id LIMIT 1");
    $st->execute(array(':id' => $id));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['pct'] = (int)round(((float)$row['h_score']) * 100);
    return $row;
}

/** Candidato: análisis publicado HOY, más polarizado, sobre umbral y no posteado. */
function autopost_candidato() {
    $cfg = prisma_cfg();
    $umbral = isset($cfg['autopost_umbral']) ? (float)$cfg['autopost_umbral'] : 0.55;
    $db = prisma_db();
    $hoy = (new DateTime('now', new DateTimeZone($cfg['timezone'])))->format('Y-m-d');

    $st = $db->prepare("SELECT a.id, a.titular_neutral, a.veredicto, a.fuentes_total,
                               r.h_score, r.fuentes_json, r.haiku_frase
                        FROM articulos a
                        LEFT JOIN radar r ON r.articulo_id = a.id
                        WHERE a.id LIKE :p
                        ORDER BY r.h_score DESC");
    $st->execute(array(':p' => $hoy . '-%'));

    $ledger = autopost_ledger();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (in_array($row['id'], $ledger, true)) continue;
        $pct = (int)round(((float)$row['h_score']) * 100);
        if ($pct < $umbral * 100) continue;
        $row['pct'] = $pct;
        return $row;
    }
    return null;
}

/** Construye el pie (caption) HTML para Telegram (≤1024 chars). */
function autopost_caption($d) {
    $cfg = prisma_cfg();
    $site = rtrim(isset($cfg['site_url']) ? $cfg['site_url'] : 'https://polarprisma.org', '/');
    $pct = (int)$d['pct'];
    $sem = $pct >= 60 ? '🔴' : ($pct >= 45 ? '🟠' : '🟡');

    $fuentes = json_decode(isset($d['fuentes_json']) ? $d['fuentes_json'] : '[]', true);
    if (!is_array($fuentes)) $fuentes = array();
    $cub = array();
    foreach ($fuentes as $f) if (!empty($f['cuadrante'])) $cub[$f['cuadrante']] = true;
    $callaron = 7 - count($cub);

    $url = $site . '/articulo.php?id=' . rawurlencode($d['id']);
    $esc = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    $cap  = "🔺 <b>" . $esc($d['titular_neutral']) . "</b>\n\n";
    $cap .= "$sem <b>$pct%</b> de polarización";
    if ($callaron > 0) $cap .= " · $callaron de 7 posiciones del espectro callaron";
    $cap .= ".\n\n👉 " . $url;

    return mb_substr($cap, 0, 1000, 'UTF-8');
}

/**
 * Ejecuta la autopublicación.
 * @param bool        $dry       true = previsualiza, no publica
 * @param string|null $force_id  id de artículo a forzar (para pruebas/dry-run)
 * @return bool
 */
function autopost_ejecutar($dry = false, $force_id = null) {
    $cfg = prisma_cfg();

    $d = $force_id ? autopost_datos($force_id) : autopost_candidato();
    if (!$d) {
        prisma_log("AUTOPOST", "Sin candidato (ninguno supera el umbral, ya posteado, o id inexistente).");
        echo "Sin candidato para publicar.\n";
        return false;
    }

    $site = rtrim(isset($cfg['site_url']) ? $cfg['site_url'] : 'https://polarprisma.org', '/');
    $foto = $site . '/og/' . rawurlencode($d['id']) . '.png';
    $caption = autopost_caption($d);

    echo "Candidato: {$d['id']} ({$d['pct']}%)\nFoto: $foto\n--- pie ---\n" . strip_tags(str_replace(array('<b>','</b>'), '*', $caption)) . "\n-----------\n";

    if ($dry) { echo "(dry-run: no se publica nada)\n"; return true; }

    $enviado = false;
    if (!empty($cfg['autopost_telegram'])) {
        $ok = telegram_enviar_foto($foto, $caption);
        prisma_log("AUTOPOST", "Telegram: " . ($ok ? "publicado" : "falló") . " {$d['id']} ({$d['pct']}%)");
        $enviado = $enviado || $ok;
    }
    if ($enviado) autopost_marcar($d['id']);
    return $enviado;
}
