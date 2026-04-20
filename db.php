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
