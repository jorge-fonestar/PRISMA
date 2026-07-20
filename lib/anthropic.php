<?php
/**
 * Prisma — Cliente Anthropic vía cURL + control de gasto diario.
 *
 * Registra cada llamada en data/usage.json y aborta si se supera
 * el presupuesto diario configurado en config.php.
 */

// Precios por millón de tokens (USD) — actualizar si cambian
// https://platform.claude.com/docs/en/pricing
define('ANTHROPIC_PRICING', [
    'claude-sonnet-5'            => ['input' => 3.00,  'output' => 15.00],
    'claude-sonnet-4-6'          => ['input' => 3.00,  'output' => 15.00],
    'claude-opus-4-8'            => ['input' => 5.00,  'output' => 25.00],
    'claude-opus-4-7'            => ['input' => 5.00,  'output' => 25.00],
    'claude-sonnet-4-20250514'   => ['input' => 3.00,  'output' => 15.00],
    'claude-haiku-4-5-20251001'  => ['input' => 1.00,  'output' => 5.00],
    'claude-haiku-4-5'           => ['input' => 1.00,  'output' => 5.00],
    'default'                    => ['input' => 3.00,  'output' => 15.00],
]);

// Descuento de la Message Batches API (50% sobre precio estándar)
define('ANTHROPIC_BATCH_DISCOUNT', 0.5);

/**
 * ¿Soporta el modelo prefill de assistant?
 * La API lo rechaza (400) en la familia 4.6+ (sonnet-4-6, opus-4-6/4-7/4-8),
 * sonnet-5 y fable/mythos. Allowlist conservadora: solo modelos legacy 3.x
 * y haiku lo mantienen. Ante la duda, no enviar prefill.
 */
function anthropic_supports_prefill(string $model): bool {
    return strpos($model, 'claude-3') === 0
        || strpos($model, 'claude-haiku') === 0;
}

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
function anthropic_call(string $model, string $system, string $user_msg, int $max_tokens = 8192, string $prefill = ''): string {
    require_once __DIR__ . '/logger.php';

    $cfg = prisma_cfg();
    $api_key = $cfg['anthropic_api_key'];

    if (!$api_key) {
        throw new RuntimeException('ANTHROPIC_API_KEY no configurada.');
    }

    // Comprobar presupuesto antes de llamar
    anthropic_check_budget();

    $caller = prisma_detect_caller();
    $t_start = microtime(true);

    $messages = [
        ['role' => 'user', 'content' => $user_msg],
    ];
    if ($prefill !== '' && anthropic_supports_prefill($model)) {
        $messages[] = ['role' => 'assistant', 'content' => $prefill];
    }
    $supports_prefill = anthropic_supports_prefill($model);

    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => $max_tokens,
        'system'     => $system,
        'messages'   => $messages,
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

    $duration_ms = (int)((microtime(true) - $t_start) * 1000);

    if ($err) {
        prisma_log_api_call(array(
            'caller'        => $caller,
            'model'         => $model,
            'system_prompt' => $system,
            'user_msg'      => $user_msg,
            'http_code'     => 0,
            'error'         => "cURL error: $err",
            'duration_ms'   => $duration_ms,
        ));
        throw new RuntimeException("cURL error: $err");
    }
    if ($http_code !== 200) {
        prisma_log_api_call(array(
            'caller'        => $caller,
            'model'         => $model,
            'system_prompt' => $system,
            'user_msg'      => $user_msg,
            'response_raw'  => $response,
            'http_code'     => $http_code,
            'error'         => "HTTP $http_code",
            'duration_ms'   => $duration_ms,
        ));
        throw new RuntimeException("Anthropic API HTTP $http_code: $response");
    }

    $data = json_decode($response, true);

    // El texto puede no ser el primer bloque: con thinking adaptativo
    // (sonnet-5+) content[0] es un bloque 'thinking' y el texto va después.
    $text = '';
    if ($data && !empty($data['content']) && is_array($data['content'])) {
        foreach ($data['content'] as $block) {
            if (isset($block['type']) && $block['type'] === 'text' && isset($block['text']) && $block['text'] !== '') {
                $text = $block['text'];
                break;
            }
        }
    }

    if ($text === '') {
        prisma_log_api_call(array(
            'caller'        => $caller,
            'model'         => $model,
            'system_prompt' => $system,
            'user_msg'      => $user_msg,
            'response_raw'  => $response,
            'http_code'     => $http_code,
            'error'         => 'Unexpected response format',
            'duration_ms'   => $duration_ms,
        ));
        throw new RuntimeException("Respuesta inesperada de Anthropic: $response");
    }

    // Registrar uso y coste
    $input_tokens  = $data['usage']['input_tokens'] ?? 0;
    $output_tokens = $data['usage']['output_tokens'] ?? 0;
    $cost = anthropic_calc_cost($model, $input_tokens, $output_tokens);

    anthropic_record_usage($model, $input_tokens, $output_tokens, $cost);

    // Log to isolated DB — prepend prefill to reconstruct full response (only if prefill was actually sent)
    if ($prefill !== '' && $supports_prefill) {
        $text = $prefill . $text;
    }
    prisma_log_api_call(array(
        'caller'        => $caller,
        'model'         => $model,
        'system_prompt' => $system,
        'user_msg'      => $user_msg,
        'response_raw'  => $text,
        'http_code'     => $http_code,
        'input_tokens'  => $input_tokens,
        'output_tokens' => $output_tokens,
        'cost_usd'      => $cost,
        'duration_ms'   => $duration_ms,
    ));

    $spent = anthropic_daily_spend();
    $budget = $cfg['daily_budget_usd'] ?? 999;

    prisma_log("API", sprintf(
        "%s — %d in / %d out — $%.4f (hoy: $%.2f / $%.2f) [%dms]",
        $model, $input_tokens, $output_tokens, $cost, $spent, $budget, $duration_ms
    ));

    return $text;
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

// ── Message Batches API (50% de descuento) ──────────────────────────
// https://platform.claude.com/docs/en/build-with-claude/batch-processing
// Pensado para la Fase 2 (cron nocturno, sin exigencia de latencia).

/**
 * Helper cURL para los endpoints de batches.
 */
function anthropic_batch_http(string $method, string $url, $payload = null): array {
    $cfg = prisma_cfg();
    $api_key = $cfg['anthropic_api_key'];
    if (!$api_key) {
        throw new RuntimeException('ANTHROPIC_API_KEY no configurada.');
    }

    $ch = curl_init($url);
    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) throw new RuntimeException("cURL error (batch): $err");
    if ($http_code < 200 || $http_code >= 300) {
        throw new RuntimeException("Anthropic Batches HTTP $http_code: " . substr($response, 0, 800));
    }
    return ['body' => $response, 'code' => $http_code];
}

/**
 * Envía un batch de peticiones.
 *
 * @param array $requests Cada una: ['custom_id'=>string, 'model'=>string,
 *                        'system'=>string, 'user_msg'=>string, 'max_tokens'=>int]
 * @return string batch_id
 */
function anthropic_batch_submit(array $requests): string {
    anthropic_check_budget();

    $items = [];
    foreach ($requests as $r) {
        $items[] = [
            'custom_id' => $r['custom_id'],
            'params' => [
                'model'      => $r['model'],
                'max_tokens' => isset($r['max_tokens']) ? $r['max_tokens'] : 4096,
                'system'     => $r['system'],
                'messages'   => [['role' => 'user', 'content' => $r['user_msg']]],
            ],
        ];
    }

    $res = anthropic_batch_http('POST', 'https://api.anthropic.com/v1/messages/batches', ['requests' => $items]);
    $data = json_decode($res['body'], true);
    if (!$data || empty($data['id'])) {
        throw new RuntimeException('Respuesta inesperada al crear batch: ' . substr($res['body'], 0, 500));
    }

    prisma_log("BATCH", sprintf("Batch %s enviado (%d peticiones).", $data['id'], count($items)));
    return $data['id'];
}

/**
 * Espera a que un batch termine (poll cada $poll_s segundos).
 *
 * @return array Objeto batch final (con results_url)
 */
function anthropic_batch_wait(string $batch_id, int $timeout_s = 14400, int $poll_s = 30): array {
    $t0 = time();
    $last_status = '';
    while (true) {
        $res = anthropic_batch_http('GET', "https://api.anthropic.com/v1/messages/batches/$batch_id");
        $data = json_decode($res['body'], true);
        $status = isset($data['processing_status']) ? $data['processing_status'] : '?';

        if ($status !== $last_status) {
            prisma_log("BATCH", "Batch $batch_id: $status");
            $last_status = $status;
        }
        if ($status === 'ended') return $data;

        if (time() - $t0 > $timeout_s) {
            throw new RuntimeException("Batch $batch_id no terminó en {$timeout_s}s (estado: $status).");
        }
        sleep($poll_s);
    }
}

/**
 * Descarga y parsea los resultados de un batch terminado.
 * Registra usage/coste (con descuento batch) y loguea cada item.
 *
 * @param array $batch Objeto batch devuelto por anthropic_batch_wait()
 * @param string $caller Etiqueta para el log de API (ej. 'synth', 'audit')
 * @return array custom_id => ['ok'=>bool, 'text'=>string|null, 'error'=>string|null]
 */
function anthropic_batch_results(array $batch, string $caller = 'batch'): array {
    require_once __DIR__ . '/logger.php';

    if (empty($batch['results_url'])) {
        throw new RuntimeException('Batch sin results_url (¿terminó correctamente?).');
    }

    $res = anthropic_batch_http('GET', $batch['results_url']);
    $out = [];

    foreach (explode("\n", trim($res['body'])) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $item = json_decode($line, true);
        if (!$item || empty($item['custom_id'])) continue;

        $cid = $item['custom_id'];
        $rtype = isset($item['result']['type']) ? $item['result']['type'] : 'errored';

        if ($rtype === 'succeeded') {
            $msg = $item['result']['message'];
            $text = '';
            foreach ($msg['content'] as $block) {
                if ($block['type'] === 'text') { $text = $block['text']; break; }
            }
            $in_tok  = isset($msg['usage']['input_tokens']) ? $msg['usage']['input_tokens'] : 0;
            $out_tok = isset($msg['usage']['output_tokens']) ? $msg['usage']['output_tokens'] : 0;
            $model   = isset($msg['model']) ? $msg['model'] : 'default';
            $cost = anthropic_calc_cost($model, $in_tok, $out_tok) * ANTHROPIC_BATCH_DISCOUNT;

            anthropic_record_usage($model, $in_tok, $out_tok, $cost);
            prisma_log_api_call(array(
                'caller'        => $caller,
                'model'         => $model,
                'system_prompt' => '(batch) ' . $cid,
                'user_msg'      => '(batch) ' . $cid,
                'response_raw'  => $text,
                'http_code'     => 200,
                'input_tokens'  => $in_tok,
                'output_tokens' => $out_tok,
                'cost_usd'      => $cost,
                'duration_ms'   => 0,
            ));

            $out[$cid] = ['ok' => true, 'text' => $text, 'error' => null];
        } else {
            $err_msg = $rtype;
            if (isset($item['result']['error'])) {
                $err_msg .= ': ' . json_encode($item['result']['error'], JSON_UNESCAPED_UNICODE);
            }
            prisma_log("BATCH", "Item $cid falló ($err_msg)");
            $out[$cid] = ['ok' => false, 'text' => null, 'error' => $err_msg];
        }
    }

    prisma_log("BATCH", count($out) . " resultados recuperados (hoy: $" . sprintf('%.2f', anthropic_daily_spend()) . ")");
    return $out;
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
