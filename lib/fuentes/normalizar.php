<?php
/**
 * Prisma — Normalizador de fuentes.
 *
 * Convierte formato legacy array('Nombre','URL'[,'Nota']) al formato
 * asociativo nuevo. Compartido entre rss.php, fuentes.php y otros.
 */

if (!defined('PRISMA_BOT_UA')) {
    define('PRISMA_BOT_UA', 'PrismaBot/1.0 (+https://prisma.example/bot)');
}

/**
 * Normaliza un array de fuente (legacy o nuevo) al formato asociativo.
 *
 * @param array  $fuente    Array de config del medio (legacy o asociativo)
 * @param string $cuadrante Cuadrante ideológico
 * @param string $ambito    Ámbito geográfico
 * @return array Formato asociativo normalizado
 */
function rss_normalizar_fuente($fuente, $cuadrante, $ambito) {
    // Legacy: array('Nombre', 'URL'[, 'Transparencia'])
    if (isset($fuente[0]) && is_string($fuente[0])) {
        return array(
            'medio'         => $fuente[0],
            'url'           => $fuente[1],
            'modalidad'     => 'rss_nativo',
            'transparencia' => isset($fuente[2]) ? $fuente[2] : '',
            'cuadrante'     => $cuadrante,
            'ambito'        => $ambito,
        );
    }
    // New associative format — inject cuadrante/ambito from iteration context
    $fuente['cuadrante'] = $cuadrante;
    $fuente['ambito'] = $ambito;
    return $fuente;
}
