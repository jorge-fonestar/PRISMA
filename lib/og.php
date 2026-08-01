<?php
/**
 * PolarPrisma — Tarjetas sociales (Open Graph), 1200×630, generadas con GD.
 *
 * Cada análisis/tema se convierte en su propia imagen para compartir en
 * WhatsApp/Telegram/X/Bluesky. Se genera UNA vez y se cachea en /og/<clave>.png
 * (carpeta pública, no bloqueada por Apache). Sin dependencias externas: solo GD
 * y una fuente TTF del sistema (DejaVu, empaquetada en la imagen).
 *
 * Claves de caché: los artículos usan su id (og/2026-07-30-002.png); los temas
 * del radar usan "r<id>" (og/r1234.png). La marca de fallback es og/default.png.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/layout.php';   // PRISMA_CUADRANTE_COLORES

define('OG_W', 1200);
define('OG_H', 630);
define('OG_DIR', dirname(__DIR__) . '/og');
define('OG_FONT_REG',  '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf');
define('OG_FONT_BOLD', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');

// Espectro ideológico, izquierda → derecha (mismo orden que la web).
define('OG_ORDEN_CUADRANTES', array(
    'izquierda-populista', 'izquierda', 'centro-izquierda',
    'centro', 'centro-derecha', 'derecha', 'derecha-populista',
));

// ── Helpers de color ────────────────────────────────────────────────

function og_rgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

function og_mix($a, $b, $t) {
    return array(
        (int)round($a[0] + ($b[0] - $a[0]) * $t),
        (int)round($a[1] + ($b[1] - $a[1]) * $t),
        (int)round($a[2] + ($b[2] - $a[2]) * $t),
    );
}

/** Semáforo del proyecto: ≥60 rojo · ≥45 naranja · resto amarillo. */
function og_semaforo_hex($pct) {
    if ($pct >= 60) return '#ff4d6d';
    if ($pct >= 45) return '#ff9e4d';
    return '#f2f24a';
}

// ── Texto: ancho, ajuste de línea con elipsis ───────────────────────

function og_txt_w($size, $font, $text) {
    $bb = imagettfbbox($size, 0, $font, $text);
    return abs($bb[2] - $bb[0]);
}

function og_wrap($text, $size, $font, $maxw, $maxlines) {
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') return array();
    $words = explode(' ', $text);
    $lines = array();
    $cur = '';
    foreach ($words as $w) {
        $try = ($cur === '') ? $w : $cur . ' ' . $w;
        if (og_txt_w($size, $font, $try) <= $maxw) {
            $cur = $try;
        } else {
            if ($cur !== '') $lines[] = $cur;
            $cur = $w;
            if (count($lines) >= $maxlines) break;
        }
    }
    if ($cur !== '' && count($lines) < $maxlines) $lines[] = $cur;

    // ¿sobró texto? → elipsis en la última línea
    $incluido = trim(implode(' ', $lines));
    if (mb_strlen($incluido) < mb_strlen($text) - 1) {
        $i = min(count($lines), $maxlines) - 1;
        if ($i < 0) $i = 0;
        $last = isset($lines[$i]) ? $lines[$i] : '';
        while ($last !== '' && og_txt_w($size, $font, $last . '…') > $maxw) {
            $last = mb_substr($last, 0, -1);
        }
        $lines[$i] = rtrim($last) . '…';
    }
    return array_slice($lines, 0, $maxlines);
}

// ── Carga de datos de tarjeta ───────────────────────────────────────

function og_ruta($clave) { return OG_DIR . '/' . $clave . '.png'; }

function og_asegurar_dir() {
    if (!is_dir(OG_DIR)) @mkdir(OG_DIR, 0775, true);
}

function og_datos_componer($d) {
    $fuentes = json_decode($d['fuentes_json'], true);
    if (!is_array($fuentes)) $fuentes = array();
    $cubiertos = array();
    foreach ($fuentes as $f) {
        if (!empty($f['cuadrante'])) $cubiertos[$f['cuadrante']] = true;
    }
    $d['cubiertos'] = $cubiertos;
    $d['n_cuadrantes'] = count($cubiertos);
    return $d;
}

/** Datos de un artículo publicado (con su fila de radar vinculada). */
function og_datos_articulo($article_id) {
    $db = prisma_db();
    $st = $db->prepare('SELECT id, titular_neutral, veredicto, fuentes_total FROM articulos WHERE id = :id');
    $st->execute(array(':id' => $article_id));
    $art = $st->fetch(PDO::FETCH_ASSOC);
    if (!$art) return null;

    $r = $db->prepare('SELECT h_score, h_silencio, haiku_frase, fuentes_json FROM radar WHERE articulo_id = :id ORDER BY id DESC LIMIT 1');
    $r->execute(array(':id' => $article_id));
    $rad = $r->fetch(PDO::FETCH_ASSOC);
    if (!is_array($rad)) $rad = array();

    return og_datos_componer(array(
        'titulo'       => $art['titular_neutral'],
        'pct'          => isset($rad['h_score']) ? (int)round($rad['h_score'] * 100) : 0,
        'fuentes_json' => isset($rad['fuentes_json']) ? $rad['fuentes_json'] : '[]',
        'haiku'        => isset($rad['haiku_frase']) ? $rad['haiku_frase'] : null,
        'veredicto'    => isset($art['veredicto']) ? $art['veredicto'] : null,
        'n_fuentes'    => isset($art['fuentes_total']) ? (int)$art['fuentes_total'] : null,
        'analizado'    => true,
    ));
}

/** Datos de un tema del radar (sin analizar). */
function og_datos_radar($radar_id) {
    $db = prisma_db();
    $r = $db->prepare('SELECT titulo_tema, h_score, h_silencio, haiku_frase, fuentes_json FROM radar WHERE id = :id');
    $r->execute(array(':id' => (int)$radar_id));
    $rad = $r->fetch(PDO::FETCH_ASSOC);
    if (!$rad) return null;

    return og_datos_componer(array(
        'titulo'       => $rad['titulo_tema'],
        'pct'          => (int)round($rad['h_score'] * 100),
        'fuentes_json' => isset($rad['fuentes_json']) ? $rad['fuentes_json'] : '[]',
        'haiku'        => isset($rad['haiku_frase']) ? $rad['haiku_frase'] : null,
        'veredicto'    => null,
        'n_fuentes'    => null,
        'analizado'    => false,
    ));
}

// ── Dibujo ──────────────────────────────────────────────────────────

/** Triángulo-prisma con aristas del espectro (evoca el favicon). */
function og_dibujar_prisma($im, $x, $yTop, $s) {
    $apex = array((int)($x + $s / 2), (int)$yTop);
    $bl   = array((int)$x,            (int)($yTop + $s * 0.84));
    $br   = array((int)($x + $s),     (int)($yTop + $s * 0.84));
    imagesetthickness($im, 4);
    $izq = imagecolorallocate($im, ...og_rgb('#ff4d6d'));
    $der = imagecolorallocate($im, ...og_rgb('#a855f7'));
    $bot = imagecolorallocate($im, ...og_rgb('#4ade80'));
    imageline($im, $apex[0], $apex[1], $bl[0], $bl[1], $izq);
    imageline($im, $apex[0], $apex[1], $br[0], $br[1], $der);
    imageline($im, $bl[0], $bl[1], $br[0], $br[1], $bot);
    imagesetthickness($im, 1);
}

/**
 * Renderiza la tarjeta a un PNG. $d puede ser null → tarjeta de marca (default).
 * @return bool true si el PNG se escribió.
 */
function og_render($d, $ruta) {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) return false;
    if (!is_file(OG_FONT_BOLD) || !is_file(OG_FONT_REG)) return false;
    og_asegurar_dir();

    $M = 72;
    $im = imagecreatetruecolor(OG_W, OG_H);

    // Fondo: degradado oscuro vertical.
    $top = og_rgb('#0e0e16');
    $bot = og_rgb('#060609');
    for ($y = 0; $y < OG_H; $y++) {
        $c = og_mix($top, $bot, $y / OG_H);
        $col = imagecolorallocate($im, $c[0], $c[1], $c[2]);
        imageline($im, 0, $y, OG_W, $y, $col);
    }
    $white = imagecolorallocate($im, 245, 245, 248);
    $muted = imagecolorallocate($im, 150, 150, 165);
    $faint = imagecolorallocate($im, 108, 108, 124);
    $panel = imagecolorallocate($im, 35, 35, 46);

    // Barra de acento superior (degradado del espectro) — remate de marca.
    $espectro = array('#ff4d6d', '#ff9e4d', '#f2f24a', '#4ade80', '#4dc3ff', '#a855f7');
    $seg = OG_W / (count($espectro));
    for ($i = 0; $i < count($espectro); $i++) {
        $a = og_rgb($espectro[$i]);
        $b = og_rgb($espectro[min($i + 1, count($espectro) - 1)]);
        for ($x = 0; $x < $seg; $x++) {
            $c = og_mix($a, $b, $x / $seg);
            $col = imagecolorallocate($im, $c[0], $c[1], $c[2]);
            imageline($im, (int)($i * $seg + $x), 0, (int)($i * $seg + $x), 6, $col);
        }
    }

    // ── Header: prisma + marca ──
    og_dibujar_prisma($im, $M, 44, 38);
    imagettftext($im, 25, 0, $M + 56, 76, $white, OG_FONT_BOLD, 'PolarPrisma');
    imagettftext($im, 13, 0, $M + 56, 100, $faint, OG_FONT_REG, 'Cartografía de la polarización informativa');

    // ── % grande + semáforo (arriba derecha) ──
    if ($d !== null) {
        $pct = max(0, min(100, (int)$d['pct']));
        $sem = imagecolorallocate($im, ...og_rgb(og_semaforo_hex($pct)));
        $pctStr = $pct . '%';
        $pw = og_txt_w(90, OG_FONT_BOLD, $pctStr);
        imagettftext($im, 90, 0, OG_W - $M - $pw, 118, $sem, OG_FONT_BOLD, $pctStr);
        $lbl = 'de polarización';
        $lw = og_txt_w(14, OG_FONT_REG, $lbl);
        imagettftext($im, 14, 0, OG_W - $M - $lw, 146, $muted, OG_FONT_REG, $lbl);
    }

    // ── Título (máx 3 líneas, elipsis) ──
    $titulo = $d !== null ? $d['titulo'] : 'Cartografía de la polarización informativa';
    $tSize = 40; $lh = 56; $yT = 232;
    $lineas = og_wrap($titulo, $tSize, OG_FONT_BOLD, OG_W - 2 * $M, 3);
    foreach ($lineas as $ln) {
        imagettftext($im, $tSize, 0, $M, $yT, $white, OG_FONT_BOLD, $ln);
        $yT += $lh;
    }

    if ($d !== null) {
        // ── Franja del espectro: quién cubrió (vivo) vs quién calló (× atenuado) ──
        imagettftext($im, 14, 0, $M, 424, $muted, OG_FONT_BOLD, 'QUIÉN LO CONTÓ · QUIÉN CALLÓ');
        $barY = 440; $barH = 48; $gap = 8;
        $n = count(OG_ORDEN_CUADRANTES);
        $totalW = OG_W - 2 * $M;
        $sw = ($totalW - $gap * ($n - 1)) / $n;
        for ($i = 0; $i < $n; $i++) {
            $cua = OG_ORDEN_CUADRANTES[$i];
            $x0 = (int)($M + $i * ($sw + $gap));
            $x1 = (int)($x0 + $sw);
            if (!empty($d['cubiertos'][$cua])) {
                $c = og_rgb(PRISMA_CUADRANTE_COLORES[$cua]);
                imagefilledrectangle($im, $x0, $barY, $x1, $barY + $barH, imagecolorallocate($im, $c[0], $c[1], $c[2]));
            } else {
                imagefilledrectangle($im, $x0, $barY, $x1, $barY + $barH, $panel);
                // × del silencio
                $xc = imagecolorallocate($im, 90, 90, 108);
                imagesetthickness($im, 3);
                $cx = (int)(($x0 + $x1) / 2); $cy = (int)($barY + $barH / 2); $r = 9;
                imageline($im, $cx - $r, $cy - $r, $cx + $r, $cy + $r, $xc);
                imageline($im, $cx - $r, $cy + $r, $cx + $r, $cy - $r, $xc);
                imagesetthickness($im, 1);
            }
        }
        // Etiquetas del espectro
        imagettftext($im, 12, 0, $M, $barY + $barH + 26, $faint, OG_FONT_REG, 'IZQUIERDA');
        $cLbl = 'CENTRO';  $cw = og_txt_w(12, OG_FONT_REG, $cLbl);
        imagettftext($im, 12, 0, (int)(OG_W / 2 - $cw / 2), $barY + $barH + 26, $faint, OG_FONT_REG, $cLbl);
        $dLbl = 'DERECHA'; $dw = og_txt_w(12, OG_FONT_REG, $dLbl);
        imagettftext($im, 12, 0, (int)(OG_W - $M - $dw), $barY + $barH + 26, $faint, OG_FONT_REG, $dLbl);
    }

    // ── Pie: haiku/tagline + url (+ veredicto en analizados) ──
    $footY = 588;
    if ($d !== null && !empty($d['analizado']) && !empty($d['veredicto'])) {
        $vtxt = '✓ ' . $d['veredicto'];
        if (!empty($d['n_fuentes'])) $vtxt .= '  ·  ' . $d['n_fuentes'] . ' fuentes';
        $vc = imagecolorallocate($im, ...og_rgb('#4ade80'));
        imagettftext($im, 14, 0, $M, 556, $vc, OG_FONT_BOLD, $vtxt);
    }
    $tagline = ($d !== null && !empty($d['haiku'])) ? $d['haiku'] : 'Lo que un lado calló';
    $tagLines = og_wrap($tagline, 17, OG_FONT_REG, OG_W - 2 * $M - 240, 1);
    $tag = isset($tagLines[0]) ? $tagLines[0] : '';
    imagettftext($im, 17, 0, $M, $footY, $muted, OG_FONT_REG, $tag);
    $url = 'polarprisma.org';
    $uw = og_txt_w(17, OG_FONT_BOLD, $url);
    imagettftext($im, 17, 0, OG_W - $M - $uw, $footY, $white, OG_FONT_BOLD, $url);

    $ok = imagepng($im, $ruta, 8);
    imagedestroy($im);
    return $ok && is_file($ruta);
}

// ── API pública ─────────────────────────────────────────────────────

function og_generar_articulo($article_id) {
    $d = og_datos_articulo($article_id);
    if (!$d) return false;
    return og_render($d, og_ruta($article_id));
}

function og_generar_radar($radar_id) {
    $d = og_datos_radar($radar_id);
    if (!$d) return false;
    return og_render($d, og_ruta('r' . (int)$radar_id));
}

function og_generar_default() {
    return og_render(null, og_ruta('default'));
}
