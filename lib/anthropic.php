<?php
/**
 * Prisma — Cliente Anthropic vía cURL + control de gasto diario.
 *
 * Registra cada llamada en data/usage.json y aborta si se supera
 * el presupuesto diario configurado en config.php.
 */

// Precios por millón de tokens (USD) — actualizar si cambian
// https://docs.anthropic.com/en/docs/about-claude/models
define('ANTHROPIC_PRICING', [
    'claude-sonnet-4-6'          => ['input' => 3.00,  'output' => 15.00],
    'claude-opus-4-7'            => ['input' => 15.00, 'output' => 75.00],
    'claude-sonnet-4-20250514'   => ['input' => 3.00,  'output' => 15.00],
    'claude-opus-4-20250514'     => ['input' => 15.00, 'output' => 75.00],
    'claude-haiku-4-5-20251001'  => ['input' => 0.80,  'output' => 4.00],
    'default'                    => ['input' => 3.00,  'output' => 15.00],
]);

/**
 * Devuelve el gasto acumulado del día actual (UTC).
 */
function anthropic_daily_spend(): float {
    $usage = anthropic_load_usage();
    $today = date('Y-m-d');
    return $usage[$today]['cost_usd'] ?? 0.0;
}

/**
 * Comprueba si el presupuesto diario permite una llamada más.
 * Lanza excepción si se supera.
 */
function anthropic_check_budget(): void {
    $cfg = prisma_cfg();
    $budget = $cfg['daily_budget_usd'] ?? 999;
    $spent = anthropic_daily_spend();

    if ($spent >= $budget) {
        throw new RuntimeException(sprintf(
            "Presupuesto diario agotado: $%.2f gastados de $%.2f máximo. Abortando.",
            $spent, $budget
        ));
    }
}

/**
 * Llama a la API de Anthropic y registra el coste.
 */
function anthropic_call(string $model, string $system, string $user_msg, int $max_tokens = 8192): string {
    $cfg = prisma_cfg();
    $api_key = $cfg['anthropic_api_key'];

    if (!$api_key) {
        throw new RuntimeException('ANTHROPIC_API_KEY no configurada.');
    }

    // Comprobar presupuesto antes de llamar
    anthropic_check_budget();

    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => $max_tokens,
        'system'     => $system,
        'messages'   => [
            ['role' => 'user', 'content' => $user_msg],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
        ],
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException("cURL error: $err");
    }
    if ($http_code !== 200) {
        throw new RuntimeException("Anthropic API HTTP $http_code: $response");
    }

    $data = json_decode($response, true);
    if (!$data || empty($data['content'][0]['text'])) {
        throw new RuntimeException("Respuesta inesperada de Anthropic: $response");
    }

    // Registrar uso y coste
    $input_tokens  = $data['usage']['input_tokens'] ?? 0;
    $output_tokens = $data['usage']['output_tokens'] ?? 0;
    $cost = anthropic_calc_cost($model, $input_tokens, $output_tokens);

    anthropic_record_usage($model, $input_tokens, $output_tokens, $cost);

    $spent = anthropic_daily_spend();
    $budget = $cfg['daily_budget_usd'] ?? 999;

    prisma_log("API", sprintf(
        "%s — %d in / %d out — $%.4f (hoy: $%.2f / $%.2f)",
        $model, $input_tokens, $output_tokens, $cost, $spent, $budget
    ));

    return $data['content'][0]['text'];
}

/**
 * Calcula el coste en USD de una llamada.
 */
function anthropic_calc_cost(string $model, int $input_tokens, int $output_tokens): float {
    $prices = ANTHROPIC_PRICING[$model] ?? ANTHROPIC_PRICING['default'];
    return ($input_tokens * $prices['input'] / 1_000_000)
         + ($output_tokens * $prices['output'] / 1_000_000);
}

// ── Almacenamiento de uso en data/usage.json ────────────────────────

function anthropic_usage_path(): string {
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir . '/usage.json';
}

function anthropic_load_usage(): array {
    $path = anthropic_usage_path();
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function anthropic_record_usage(string $model, int $input, int $output, float $cost): void {
    $usage = anthropic_load_usage();
    $today = date('Y-m-d');

    if (!isset($usage[$today])) {
        $usage[$today] = ['cost_usd' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0];
    }

    $usage[$today]['cost_usd']       += $cost;
    $usage[$today]['input_tokens']   += $input;
    $usage[$today]['output_tokens']  += $output;
    $usage[$today]['calls']          += 1;

    // Guardar detalle por modelo
    $mk = "model_$model";
    if (!isset($usage[$today][$mk])) {
        $usage[$today][$mk] = ['cost_usd' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0];
    }
    $usage[$today][$mk]['cost_usd']      += $cost;
    $usage[$today][$mk]['input_tokens']  += $input;
    $usage[$today][$mk]['output_tokens'] += $output;
    $usage[$today][$mk]['calls']         += 1;

    // Limpiar entradas de hace >30 días
    $cutoff = date('Y-m-d', strtotime('-30 days'));
    foreach (array_keys($usage) as $day) {
        if ($day < $cutoff) unset($usage[$day]);
    }

    file_put_contents(anthropic_usage_path(), json_encode($usage, JSON_PRETTY_PRINT));
}

/**
 * Extrae JSON limpio de una respuesta que puede venir envuelta en markdown.
 */
function parse_json_response(string $raw): array {
    $raw = trim($raw);

    if (preg_match('/^```(?:json)?\s*\n?(.*)\n?```$/s', $raw, $m)) {
        $raw = trim($m[1]);
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('JSON inválido del modelo: ' . json_last_error_msg() . "\n" . substr($raw, 0, 500));
    }

    return $data;
}
