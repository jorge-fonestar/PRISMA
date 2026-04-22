<?php
/**
 * Prisma — SQLite database initialization.
 *
 * The DB file lives at data/prisma.db (gitignored).
 * Tables are created on first access.
 */

function prisma_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $pdo = new PDO('sqlite:' . $dir . '/prisma.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    $pdo->exec('CREATE TABLE IF NOT EXISTS articulos (
        id              TEXT PRIMARY KEY,
        fecha_publicacion TEXT NOT NULL,
        ambito          TEXT NOT NULL,
        titular_neutral TEXT NOT NULL,
        resumen         TEXT NOT NULL,
        payload         TEXT NOT NULL,
        veredicto       TEXT,
        puntuacion      REAL,
        fuentes_total   INTEGER,
        created_at      TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_fecha ON articulos(fecha_publicacion DESC)');

    $pdo->exec('CREATE TABLE IF NOT EXISTS radar (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        fecha           TEXT NOT NULL,
        titulo_tema     TEXT NOT NULL,
        ambito          TEXT NOT NULL,
        h_score         REAL NOT NULL,
        h_asimetria     REAL NOT NULL,
        h_divergencia   REAL NOT NULL,
        h_varianza      REAL NOT NULL,
        haiku_frase     TEXT,
        analizado       INTEGER DEFAULT 0,
        articulo_id     TEXT,
        fuentes_json    TEXT NOT NULL,
        created_at      TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_radar_fecha ON radar(fecha DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_radar_score ON radar(h_score DESC)');

    // ── Scoring v2 columns (idempotent migration) ──
    $v2_columns = array(
        'h_cobertura_mutua REAL',
        'h_framing REAL',
        'h_silencio REAL',
        'framing_divergence INTEGER',
        'framing_evidence TEXT',
        'relevancia TEXT',
        'dominio_tematico TEXT',
        "scoring_version TEXT DEFAULT 'v1'",
    );
    foreach ($v2_columns as $col) {
        try {
            $pdo->exec("ALTER TABLE radar ADD COLUMN $col");
        } catch (PDOException $e) {
            // Column already exists — ignore
        }
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_radar_relevancia ON radar(relevancia)');

    // ── Scoring anomalies ──
    $pdo->exec('CREATE TABLE IF NOT EXISTS scoring_anomalies (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        fecha       TEXT NOT NULL,
        radar_id    INTEGER,
        tipo        TEXT NOT NULL,
        detalle     TEXT,
        created_at  TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_anomalies_fecha ON scoring_anomalies(fecha DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_anomalies_tipo ON scoring_anomalies(tipo)');

    // ── Calibration labels ──
    $pdo->exec('CREATE TABLE IF NOT EXISTS etiquetas_calibracion (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        radar_id    INTEGER NOT NULL UNIQUE,
        etiqueta    INTEGER NOT NULL,
        operador    TEXT,
        created_at  TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    // ── Calibration runs ──
    $pdo->exec('CREATE TABLE IF NOT EXISTS calibraciones (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        fecha           TEXT NOT NULL,
        dataset_size    INTEGER NOT NULL,
        resultados_json TEXT NOT NULL,
        params_elegidos TEXT NOT NULL,
        precision_at_k  REAL,
        recall_at_k     REAL,
        operador        TEXT,
        created_at      TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    return $pdo;
}

// ── Base URL ─────────────────────────────────────────────

/**
 * Returns the base path where Prisma lives, with trailing slash.
 * Works whether deployed at domain root or inside a subdirectory.
 * E.g. "/" or "/prisma/" or "/projects/prisma/"
 */
function prisma_base(): string {
    static $base = null;
    if ($base !== null) return $base;

    // __DIR__ = filesystem path to this file (db.php)
    // DOCUMENT_ROOT = filesystem path the server maps to "/"
    $doc_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $app_dir  = realpath(__DIR__) ?: __DIR__;

    if ($doc_root && strpos($app_dir, $doc_root) === 0) {
        $base = str_replace('\\', '/', substr($app_dir, strlen($doc_root)));
        $base = '/' . ltrim($base, '/');
    } else {
        // Fallback: derive from SCRIPT_NAME
        $base = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        $base = rtrim(str_replace('\\', '/', $base), '/');
    }

    $base = rtrim($base, '/') . '/';
    return $base;
}

// ── Config ──────────────────────────────────────────────────────────

function prisma_api_key(): string {
    // Check env var first, fall back to config file
    $key = getenv('PRISMA_API_KEY');
    if ($key) return $key;

    $cfg = __DIR__ . '/data/api_key.txt';
    if (file_exists($cfg)) return trim(file_get_contents($cfg));

    return '';
}
