<?php
/**
 * Prisma — Feed health tracking.
 *
 * Registra resultados de cada fetch (RSS o captura) en prisma_logs.db.
 * Tabla feed_health se auto-crea al primer uso.
 */
require_once __DIR__ . '/../logger.php';

/**
 * Ensures feed_health table exists in prisma_logs.db.
 * Called once per process via static flag.
 */
function feed_health_init() {
    static $done = false;
    if ($done) return;
    $db = prisma_logger_db();
    $db->exec("CREATE TABLE IF NOT EXISTS feed_health (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        medio       TEXT NOT NULL,
        ambito      TEXT NOT NULL,
        modalidad   TEXT NOT NULL,
        resultado   TEXT NOT NULL,
        items_count INTEGER DEFAULT 0,
        http_status INTEGER DEFAULT NULL,
        error_msg   TEXT DEFAULT NULL,
        latencia_ms INTEGER DEFAULT NULL,
        created_at  TEXT DEFAULT (datetime('now'))
    )");
    $idx = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_fh_medio_fecha'")->fetch();
    if (!$idx) {
        $db->exec('CREATE INDEX idx_fh_medio_fecha ON feed_health(medio, created_at)');
    }
    $done = true;
}

/**
 * Registers a feed health event.
 *
 * @param string $medio      Media name
 * @param string $ambito     Geographic scope
 * @param string $resultado  ok|fail|skip|throttle|started
 * @param string $modalidad  rss_nativo|captura_portada|no_disponible
 * @param int    $items      Number of items fetched
 * @param array  $extras     Optional: http_status, error, latencia_ms
 * @return int  Inserted row ID (for later update via feed_health_update)
 */
function feed_health_registrar($medio, $ambito, $resultado, $modalidad, $items = 0, $extras = array()) {
    feed_health_init();
    $db = prisma_logger_db();
    $stmt = $db->prepare('INSERT INTO feed_health (medio, ambito, modalidad, resultado, items_count, http_status, error_msg, latencia_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute(array(
        $medio, $ambito, $modalidad, $resultado, $items,
        isset($extras['http_status']) ? $extras['http_status'] : null,
        isset($extras['error']) ? $extras['error'] : null,
        isset($extras['latencia_ms']) ? $extras['latencia_ms'] : null,
    ));
    return (int)$db->lastInsertId();
}

/**
 * Updates an existing feed_health record (e.g., started -> ok/fail).
 *
 * @param int    $id         Row ID from feed_health_registrar
 * @param string $resultado  New resultado value
 * @param int    $items      Updated items count
 * @param array  $extras     Updated extras
 */
function feed_health_update($id, $resultado, $items = 0, $extras = array()) {
    feed_health_init();
    $db = prisma_logger_db();
    $stmt = $db->prepare('UPDATE feed_health SET resultado = ?, items_count = ?, http_status = ?, error_msg = ?, latencia_ms = ? WHERE id = ?');
    $stmt->execute(array(
        $resultado, $items,
        isset($extras['http_status']) ? $extras['http_status'] : null,
        isset($extras['error']) ? $extras['error'] : null,
        isset($extras['latencia_ms']) ? $extras['latencia_ms'] : null,
        $id,
    ));
}

/**
 * Purges feed_health records older than N days.
 *
 * @param int $days Retention period (default 90)
 * @return int Number of rows deleted
 */
function feed_health_purgar($days = 90) {
    feed_health_init();
    $db = prisma_logger_db();
    $interval = '-' . (int)$days . ' days';
    $stmt = $db->prepare("DELETE FROM feed_health WHERE created_at < datetime('now', ?)");
    $stmt->execute(array($interval));
    return $stmt->rowCount();
}

/**
 * Returns feed health summary for all sources (last N days).
 *
 * @param int $dias Days to look back (default 30)
 * @return array
 */
function feed_health_resumen($dias = 30) {
    feed_health_init();
    $db = prisma_logger_db();
    $interval = '-' . (int)$dias . ' days';
    $stmt = $db->prepare("SELECT medio, modalidad,
        COUNT(*) as total,
        SUM(CASE WHEN resultado = 'ok' THEN 1 ELSE 0 END) as exitos,
        ROUND(100.0 * SUM(CASE WHEN resultado = 'ok' THEN 1 ELSE 0 END) / COUNT(*), 1) as tasa_exito
        FROM feed_health
        WHERE created_at >= datetime('now', ?)
          AND resultado NOT IN ('skip', 'throttle', 'started')
        GROUP BY medio");
    $stmt->execute(array($interval));
    return $stmt->fetchAll();
}

/**
 * Returns sources with no successful fetch in N days.
 *
 * @param int $dias Days threshold (default 7)
 * @return array
 */
function feed_health_alertas($dias = 7) {
    feed_health_init();
    $db = prisma_logger_db();
    $interval = '-' . (int)$dias . ' days';
    $stmt = $db->prepare("SELECT medio, modalidad, MAX(created_at) as ultimo_exito
        FROM feed_health
        WHERE resultado = 'ok'
        GROUP BY medio
        HAVING ultimo_exito < datetime('now', ?)");
    $stmt->execute(array($interval));
    return $stmt->fetchAll();
}

/**
 * Returns last result per source.
 *
 * @return array
 */
function feed_health_ultimo_por_fuente() {
    feed_health_init();
    $db = prisma_logger_db();
    return $db->query("SELECT medio, modalidad, resultado, items_count, created_at
        FROM feed_health
        WHERE id IN (SELECT MAX(id) FROM feed_health GROUP BY medio)")->fetchAll();
}

/**
 * Returns history for a specific source.
 *
 * @param string $medio  Source name
 * @param int    $limit  Max records
 * @return array
 */
function feed_health_historial($medio, $limit = 30) {
    feed_health_init();
    $db = prisma_logger_db();
    $stmt = $db->prepare("SELECT * FROM feed_health WHERE medio = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute(array($medio, $limit));
    return $stmt->fetchAll();
}

/**
 * Checks if a medio has been fetched within the rate limit window.
 *
 * @param string $medio    Media name
 * @param int    $minutos  Window in minutes (default 60)
 * @return bool  true if rate limited (should NOT fetch)
 */
function feed_health_rate_limited($medio, $minutos = 60) {
    feed_health_init();
    $db = prisma_logger_db();
    $interval = '-' . (int)$minutos . ' minutes';
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM feed_health
        WHERE medio = ? AND created_at >= datetime('now', ?)");
    $stmt->execute(array($medio, $interval));
    $row = $stmt->fetch();
    return $row && $row['c'] > 0;
}
