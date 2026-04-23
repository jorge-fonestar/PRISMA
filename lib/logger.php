<?php
/**
 * Prisma — API call logger.
 *
 * SQLite independiente en data/prisma_logs.db.
 * Borrar el fichero = reset limpio. Se recrea solo.
 */

/**
 * Returns the logger PDO (singleton, separate from prisma_db).
 */
function prisma_logger_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $pdo = new PDO('sqlite:' . $dir . '/prisma_logs.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');

    $pdo->exec('CREATE TABLE IF NOT EXISTS api_calls (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp       TEXT NOT NULL,
        caller          TEXT,
        model           TEXT NOT NULL,
        system_prompt   TEXT,
        user_msg        TEXT,
        response_raw    TEXT,
        http_code       INTEGER,
        error           TEXT,
        input_tokens    INTEGER DEFAULT 0,
        output_tokens   INTEGER DEFAULT 0,
        cost_usd        REAL DEFAULT 0,
        duration_ms     INTEGER DEFAULT 0,
        metadata_json   TEXT
    )');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_log_ts ON api_calls(timestamp DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_log_caller ON api_calls(caller)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_log_model ON api_calls(model)');

    return $pdo;
}

/**
 * Logs an API call to the isolated logs DB.
 *
 * @param array $entry Associative array with keys matching api_calls columns.
 *                     Only 'model' is required; rest have defaults.
 */
function prisma_log_api_call(array $entry): void {
    try {
        $db = prisma_logger_db();

        $stmt = $db->prepare('INSERT INTO api_calls
            (timestamp, caller, model, system_prompt, user_msg, response_raw,
             http_code, error, input_tokens, output_tokens, cost_usd, duration_ms, metadata_json)
            VALUES (:ts, :caller, :model, :sys, :usr, :resp,
                    :http, :err, :in_tok, :out_tok, :cost, :dur, :meta)');

        $cfg = prisma_cfg();
        $tz = new DateTimeZone(isset($cfg['timezone']) ? $cfg['timezone'] : 'UTC');
        $now = (new DateTime('now', $tz))->format('Y-m-d\TH:i:sP');

        $stmt->execute(array(
            ':ts'      => isset($entry['timestamp']) ? $entry['timestamp'] : $now,
            ':caller'  => isset($entry['caller']) ? $entry['caller'] : null,
            ':model'   => $entry['model'],
            ':sys'     => isset($entry['system_prompt']) ? $entry['system_prompt'] : null,
            ':usr'     => isset($entry['user_msg']) ? $entry['user_msg'] : null,
            ':resp'    => isset($entry['response_raw']) ? $entry['response_raw'] : null,
            ':http'    => isset($entry['http_code']) ? $entry['http_code'] : null,
            ':err'     => isset($entry['error']) ? $entry['error'] : null,
            ':in_tok'  => isset($entry['input_tokens']) ? $entry['input_tokens'] : 0,
            ':out_tok' => isset($entry['output_tokens']) ? $entry['output_tokens'] : 0,
            ':cost'    => isset($entry['cost_usd']) ? $entry['cost_usd'] : 0,
            ':dur'     => isset($entry['duration_ms']) ? $entry['duration_ms'] : 0,
            ':meta'    => isset($entry['metadata_json']) ? $entry['metadata_json'] : null,
        ));
    } catch (Exception $e) {
        // Logging must never break the pipeline
        prisma_log("LOG", "Error writing API log: " . $e->getMessage());
    }
}

/**
 * Detects the caller from the PHP call stack.
 * Returns a short label like 'gate_haiku', 'triage', 'synth', 'audit'.
 */
function prisma_detect_caller(): string {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

    // Walk the stack looking for known callers
    $map = array(
        'gate_haiku_clasificar' => 'gate_haiku',
        'triage_haiku'          => 'triage',
        'sintetizar'            => 'synth',
        'sintetizar_manual'     => 'synth_manual',
        'auditar'               => 'audit',
        'analisis_overton'      => 'overton',
    );

    foreach ($trace as $frame) {
        $fn = isset($frame['function']) ? $frame['function'] : '';
        if (isset($map[$fn])) return $map[$fn];
    }

    // Fallback: use the file that called anthropic_call
    foreach ($trace as $frame) {
        if (isset($frame['file'])) {
            $base = basename($frame['file'], '.php');
            if ($base !== 'anthropic' && $base !== 'logger') {
                return $base;
            }
        }
    }

    return 'unknown';
}

/**
 * Returns log stats for the dashboard.
 */
function prisma_log_stats(): array {
    try {
        $db = prisma_logger_db();

        $today = date('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));

        $stats = array();

        $stats['total'] = (int)$db->query('SELECT COUNT(*) FROM api_calls')->fetchColumn();
        $stats['today'] = (int)$db->query(
            "SELECT COUNT(*) FROM api_calls WHERE timestamp >= '$today'"
        )->fetchColumn();
        $stats['errors_7d'] = (int)$db->query(
            "SELECT COUNT(*) FROM api_calls WHERE error IS NOT NULL AND timestamp >= '$week_ago'"
        )->fetchColumn();
        $stats['db_size_mb'] = 0;
        $db_path = dirname(__DIR__) . '/data/prisma_logs.db';
        if (file_exists($db_path)) {
            $stats['db_size_mb'] = round(filesize($db_path) / 1048576, 2);
        }

        return $stats;
    } catch (Exception $e) {
        return array('total' => 0, 'today' => 0, 'errors_7d' => 0, 'db_size_mb' => 0);
    }
}
