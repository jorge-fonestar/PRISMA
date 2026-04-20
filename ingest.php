<?php
/**
 * Prisma — REST ingest endpoint.
 *
 * POST /ingest.php
 * Header:  X-API-Key: <key>
 * Body:    JSON artifact (same schema as news/*.json)
 *
 * Returns 201 on success, 4xx on client error, 500 on server error.
 */

header('Content-Type: application/json; charset=utf-8');

// ── Only POST ───────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// ── Auth ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/db.php';

$expected_key = prisma_api_key();
if ($expected_key === '') {
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured. Set PRISMA_API_KEY env var or create data/api_key.txt.']);
    exit;
}

$provided_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals($expected_key, $provided_key)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing X-API-Key header.']);
    exit;
}

// ── Parse body ───────────────────────────────────────────────────────

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

// ── Validate required fields ─────────────────────────────────────────

$required = ['id', 'fecha_publicacion', 'ambito', 'titular_neutral', 'resumen', 'mapa_posturas'];
$missing = [];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === []) {
        $missing[] = $field;
    }
}
if ($missing) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing)]);
    exit;
}

if (!is_array($data['mapa_posturas']) || count($data['mapa_posturas']) < 3) {
    http_response_code(400);
    echo json_encode(['error' => 'mapa_posturas must contain at least 3 positions.']);
    exit;
}

// ── Insert ───────────────────────────────────────────────────────────

try {
    $db = prisma_db();

    $stmt = $db->prepare('INSERT OR REPLACE INTO articulos
        (id, fecha_publicacion, ambito, titular_neutral, resumen, payload, veredicto, puntuacion, fuentes_total)
        VALUES (:id, :fecha, :ambito, :titular, :resumen, :payload, :veredicto, :puntuacion, :fuentes)');

    $stmt->execute([
        ':id'        => $data['id'],
        ':fecha'     => $data['fecha_publicacion'],
        ':ambito'    => $data['ambito'],
        ':titular'   => $data['titular_neutral'],
        ':resumen'   => $data['resumen'],
        ':payload'   => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':veredicto' => $data['auditoria_moralcore']['veredicto'] ?? null,
        ':puntuacion'=> $data['auditoria_moralcore']['puntuacion'] ?? null,
        ':fuentes'   => $data['fuentes_consultadas_total'] ?? null,
    ]);

    http_response_code(201);
    echo json_encode([
        'ok'   => true,
        'id'   => $data['id'],
        'msg'  => 'Artifact ingested successfully.',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
