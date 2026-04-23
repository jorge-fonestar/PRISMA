# Observatorio de la Ventana de Overton — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Overton Observatory — a system that maps framing evolution across ideological blocs per debate topic, with transparent taxonomy, atomic per-topic analysis via Opus + Extended Thinking, and a public scroll-guided visual page.

**Architecture:** Three new PHP scripts (taxonomy generation, per-topic analysis, panel management) + one public page, backed by 4 new SQLite tables. Opus + Extended Thinking analyzes one topic per execution, PHP computes deltas and estado_vs_anterior mechanically. Labels are stable across analyses via prompt invariant + declared transformations (renames, merges, splits). Taxonomy uses a propose→activate manual gate.

**Tech Stack:** PHP 7.x, SQLite, Anthropic Claude API (Opus + Extended Thinking), SVG inline charts, no external dependencies.

**Spec:** `DISEÑO_OVERTON.md` (root of repo)

**Codebase conventions:**
- Config via `prisma_cfg()` global array in `config.php`
- DB init + migrations via idempotent `prisma_db()` in `db.php` (ALTER TABLE wrapped in try/catch)
- API calls via `anthropic_call()` in `lib/anthropic.php` with budget tracking in `data/usage.json`
- Logging via `prisma_log($category, $message)` in `lib/common.php`
- Page layout via `page_header()` / `page_footer()` / `render_nav()` in `lib/layout.php`
- Panel auth: session-based password from config + fail2ban IP banning in `panel.php`
- No test framework — verification via standalone PHP scripts (pattern: `test_scoring.php`)
- PHP 7.x only — no `match`, no named args, no union types, use `array()` not `[]` for top-level arrays in config

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `prompts/overton_v1.txt` | Versioned prompt for per-topic Opus analysis |
| `prompts/overton_taxonomia_v1.txt` | Versioned prompt for taxonomy generation |
| `lib/overton.php` | Core Overton functions: dataset extraction, delta computation, label reconciliation, validation, semaphore logic |
| `lib/overton_charts.php` | SVG inline chart generators (stacked bars, line charts) |
| `overton-taxonomia.php` | CLI/panel script: generate/regenerate topic catalog via Opus + ET |
| `analisis-overton.php` | CLI/panel script: run single-topic Overton analysis via Opus + ET |
| `panel-overton.php` | Admin panel: topic list, semaphore, execution, review, catalog management |
| `overton.php` | Public page: scroll-guided visual essay of published analyses |
| `test_overton.php` | Verification script for Overton pure functions |

### Modified Files

| File | Changes |
|------|---------|
| `db.php` | Add 4 new tables + `tema_slug` column on `radar` |
| `config.php` | Add Overton parameters (thresholds, models, catalog age) |
| `lib/anthropic.php` | Add `anthropic_call_extended_thinking()` + update `anthropic_calc_cost()` for thinking tokens |
| `lib/layout.php` | Add "Observatorio" to nav + footer |
| `lib/gate_haiku.php` | Extend batch contract with `tema_sugerido` output field |
| `lib/common.php` | Extend `radar_insertar_todos()` to write `tema_slug` |
| `escanear.php` | Pass active tema slugs to Haiku, write `tema_slug` to radar |
| `panel.php` | Add Overton semaphore widget in dashboard stats section |

---

## Task 1: Schema & Config

**Files:**
- Modify: `db.php` (add tables after existing calibraciones table, ~line 112)
- Modify: `config.php` (add Overton params after scoring v2 section, ~line 63)
- Create: `test_overton.php`

- [ ] **Step 1: Add Overton tables to `db.php`**

Add after the `calibraciones` table creation (before `return $pdo;`):

```php
// ── Overton: Catálogo de temas ──
$pdo->exec('CREATE TABLE IF NOT EXISTS overton_temas (
    slug                      TEXT PRIMARY KEY,
    etiquetas_paralelas_json  TEXT NOT NULL,
    dominio_tematico          TEXT NOT NULL,
    descripcion               TEXT NOT NULL,
    justificacion_agrupacion  TEXT NOT NULL,
    decisiones_ambiguas       TEXT,
    autoevaluacion_json       TEXT,
    fecha_primera_aparicion   TEXT NOT NULL,
    version_catalogo          TEXT NOT NULL DEFAULT \'v1.0\',
    estado                    TEXT NOT NULL DEFAULT \'propuesto\',
    creado_por                TEXT,
    created_at                TEXT NOT NULL DEFAULT (datetime(\'now\')),
    updated_at                TEXT NOT NULL DEFAULT (datetime(\'now\'))
)');

// ── Overton: Baselines globales ──
$pdo->exec('CREATE TABLE IF NOT EXISTS overton_baselines (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha_creacion          TEXT NOT NULL,
    periodo_cubierto_desde  TEXT NOT NULL,
    periodo_cubierto_hasta  TEXT NOT NULL,
    descripcion             TEXT,
    es_baseline_activa      INTEGER NOT NULL DEFAULT 0,
    archivada_fecha         TEXT,
    created_at              TEXT NOT NULL DEFAULT (datetime(\'now\'))
)');

// ── Overton: Análisis (uno por tema por ejecución) ──
$pdo->exec('CREATE TABLE IF NOT EXISTS overton_analisis (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    tema_slug               TEXT NOT NULL REFERENCES overton_temas(slug),
    fecha_ejecucion         TEXT NOT NULL,
    periodo_cubierto_desde  TEXT NOT NULL,
    periodo_cubierto_hasta  TEXT NOT NULL,
    n_articulos_capa_a      INTEGER NOT NULL DEFAULT 0,
    n_articulos_capa_b      INTEGER NOT NULL DEFAULT 0,
    payload_json            TEXT NOT NULL,
    analisis_anterior_id    INTEGER REFERENCES overton_analisis(id),
    baseline_id             INTEGER REFERENCES overton_baselines(id),
    estado                  TEXT NOT NULL DEFAULT \'borrador\',
    coste_usd               REAL NOT NULL DEFAULT 0,
    tokens_input            INTEGER NOT NULL DEFAULT 0,
    tokens_output           INTEGER NOT NULL DEFAULT 0,
    tokens_thinking         INTEGER NOT NULL DEFAULT 0,
    prompt_version          TEXT NOT NULL DEFAULT \'v1\',
    operador                TEXT,
    notas_revision          TEXT,
    error_detalle           TEXT,
    thinking_raw            TEXT,
    created_at              TEXT NOT NULL DEFAULT (datetime(\'now\'))
)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_overton_analisis_tema ON overton_analisis(tema_slug, fecha_ejecucion DESC)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_overton_analisis_estado ON overton_analisis(estado)');

// ── Overton: Historial de catálogos ──
$pdo->exec('CREATE TABLE IF NOT EXISTS overton_catalogo_versiones (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    version             TEXT NOT NULL,
    fecha_generacion    TEXT NOT NULL,
    payload_json        TEXT NOT NULL,
    prompt_version      TEXT NOT NULL,
    modelo              TEXT NOT NULL,
    n_temas             INTEGER NOT NULL,
    cambios_vs_anterior TEXT,
    thinking_raw        TEXT,
    coste_usd           REAL NOT NULL DEFAULT 0,
    tokens_input        INTEGER NOT NULL DEFAULT 0,
    tokens_output       INTEGER NOT NULL DEFAULT 0,
    tokens_thinking     INTEGER NOT NULL DEFAULT 0,
    operador            TEXT,
    created_at          TEXT NOT NULL DEFAULT (datetime(\'now\'))
)');

// ── Overton: tema_slug en radar (idempotent migration) ──
try {
    $pdo->exec('ALTER TABLE radar ADD COLUMN tema_slug TEXT');
} catch (PDOException $e) {
    // Column already exists
}
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_radar_tema_slug ON radar(tema_slug)');
```

- [ ] **Step 2: Add Overton config parameters to `config.php`**

Add after the `lista_positiva` array (before `'fuentes' => array(`):

```php
// ── Overton Observatory ──────────────────────────────────────
'overton_modelo'                    => 'claude-opus-4-7',
'overton_taxonomia_modelo'          => 'claude-opus-4-7',
'overton_min_articulos_por_tema'    => 15,
'overton_min_articulos_capa_a'      => 5,
'overton_min_dias_desde_ultimo'     => 30,
'overton_baseline_meses_sugeridos'  => 15,
'overton_catalogo_meses_sugeridos'  => 9,
'overton_thinking_budget'           => 16384,
'overton_taxonomia_thinking_budget' => 20480,
```

- [ ] **Step 3: Write verification script `test_overton.php`**

```php
<?php
/**
 * Prisma — Overton Observatory verification.
 * Run: php test_overton.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = prisma_db();
$pass = 0;
$fail = 0;

function check($name, $ok) {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  OK  $name\n"; }
    else     { $fail++; echo "  FAIL $name\n"; }
}

echo "=== Schema verification ===\n";

// Check all Overton tables exist
$tables = array('overton_temas', 'overton_baselines', 'overton_analisis', 'overton_catalogo_versiones');
foreach ($tables as $t) {
    $r = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$t'")->fetch();
    check("Table $t exists", $r !== false);
}

// Check tema_slug column in radar
$cols = $pdo->query("PRAGMA table_info(radar)")->fetchAll();
$col_names = array_column($cols, 'name');
check("radar.tema_slug column exists", in_array('tema_slug', $col_names));

// Check config params
$cfg = prisma_cfg();
check("overton_modelo configured", !empty($cfg['overton_modelo']));
check("overton_min_articulos_por_tema = 15", $cfg['overton_min_articulos_por_tema'] === 15);
check("overton_min_articulos_capa_a = 5", $cfg['overton_min_articulos_capa_a'] === 5);
check("overton_thinking_budget = 16384", $cfg['overton_thinking_budget'] === 16384);

echo "\n=== Results: $pass passed, $fail failed ===\n";
exit($fail > 0 ? 1 : 0);
```

- [ ] **Step 4: Run verification**

Run: `php test_overton.php`
Expected: All checks pass (8 OK, 0 FAIL)

- [ ] **Step 5: Commit**

```bash
git add db.php config.php test_overton.php
git commit -m "feat(overton): add schema tables and config parameters for Overton Observatory"
```

---

## Task 2: Extended Thinking API Function

**Files:**
- Modify: `lib/anthropic.php` (add new function after `anthropic_call()`, ~line 115; update `anthropic_calc_cost()` ~line 120)

- [ ] **Step 1: Update `anthropic_calc_cost()` to accept thinking tokens**

In `lib/anthropic.php`, change the function signature and body:

```php
function anthropic_calc_cost(string $model, int $input_tokens, int $output_tokens, int $thinking_tokens = 0): float {
    $prices = ANTHROPIC_PRICING[$model] ?? ANTHROPIC_PRICING['default'];
    return ($input_tokens * $prices['input'] / 1_000_000)
         + (($output_tokens + $thinking_tokens) * $prices['output'] / 1_000_000);
}
```

- [ ] **Step 2: Update `anthropic_record_usage()` to track thinking tokens**

Add `thinking_tokens` parameter and recording:

```php
function anthropic_record_usage(string $model, int $input, int $output, float $cost, int $thinking = 0): void {
    $usage = anthropic_load_usage();
    $today = date('Y-m-d');

    if (!isset($usage[$today])) {
        $usage[$today] = array('cost_usd' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'thinking_tokens' => 0, 'calls' => 0);
    }

    $usage[$today]['cost_usd']         += $cost;
    $usage[$today]['input_tokens']     += $input;
    $usage[$today]['output_tokens']    += $output;
    $usage[$today]['thinking_tokens']  += $thinking;
    $usage[$today]['calls']            += 1;

    $mk = "model_$model";
    if (!isset($usage[$today][$mk])) {
        $usage[$today][$mk] = array('cost_usd' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'thinking_tokens' => 0, 'calls' => 0);
    }
    $usage[$today][$mk]['cost_usd']        += $cost;
    $usage[$today][$mk]['input_tokens']    += $input;
    $usage[$today][$mk]['output_tokens']   += $output;
    $usage[$today][$mk]['thinking_tokens'] += $thinking;
    $usage[$today][$mk]['calls']           += 1;

    $cutoff = date('Y-m-d', strtotime('-30 days'));
    foreach (array_keys($usage) as $day) {
        if ($day < $cutoff) unset($usage[$day]);
    }

    file_put_contents(anthropic_usage_path(), json_encode($usage, JSON_PRETTY_PRINT));
}
```

- [ ] **Step 3: Add `anthropic_call_extended_thinking()` function**

Add after `anthropic_call()`:

```php
/**
 * Calls Anthropic API with Extended Thinking enabled.
 * Returns array with 'text', 'thinking', and 'usage' keys.
 */
function anthropic_call_extended_thinking(
    string $model,
    string $system,
    string $user_msg,
    int $max_tokens = 16384,
    int $thinking_budget = 16384
): array {
    $cfg = prisma_cfg();
    $api_key = $cfg['anthropic_api_key'];

    if (!$api_key) {
        throw new RuntimeException('ANTHROPIC_API_KEY no configurada.');
    }

    anthropic_check_budget();

    $payload = json_encode(array(
        'model'      => $model,
        'max_tokens' => $max_tokens,
        'temperature' => 1,  // Required for Extended Thinking
        'thinking'   => array(
            'type'          => 'enabled',
            'budget_tokens' => $thinking_budget,
        ),
        'system'     => $system,
        'messages'   => array(
            array('role' => 'user', 'content' => $user_msg),
        ),
    ), JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 600,  // Extended thinking can take longer
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
        ),
    ));

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
    if (!$data || empty($data['content'])) {
        throw new RuntimeException("Respuesta inesperada de Anthropic: $response");
    }

    // Extract thinking and text from content blocks
    $thinking_text = '';
    $response_text = '';
    foreach ($data['content'] as $block) {
        if ($block['type'] === 'thinking') {
            $thinking_text = $block['thinking'];
        } elseif ($block['type'] === 'text') {
            $response_text = $block['text'];
        }
    }

    if ($response_text === '') {
        throw new RuntimeException("No text content in Anthropic response: $response");
    }

    // Record usage including thinking tokens
    $input_tokens    = $data['usage']['input_tokens'] ?? 0;
    $output_tokens   = $data['usage']['output_tokens'] ?? 0;
    // Thinking tokens are reported separately in the usage block
    $thinking_tokens = 0;
    if (isset($data['usage']['cache_read_input_tokens'])) {
        // Extended thinking usage may vary by API version; capture what's available
    }
    // Anthropic reports thinking tokens within output_tokens for billing
    // but may also report them separately — handle both cases
    if (isset($data['usage']['thinking_tokens'])) {
        $thinking_tokens = $data['usage']['thinking_tokens'];
    }

    $cost = anthropic_calc_cost($model, $input_tokens, $output_tokens, $thinking_tokens);
    anthropic_record_usage($model, $input_tokens, $output_tokens, $cost, $thinking_tokens);

    $spent = anthropic_daily_spend();
    $budget = $cfg['daily_budget_usd'] ?? 999;

    prisma_log("API-ET", sprintf(
        "%s — %d in / %d out / %d think — $%.4f (hoy: $%.2f / $%.2f)",
        $model, $input_tokens, $output_tokens, $thinking_tokens, $cost, $spent, $budget
    ));

    return array(
        'text'     => $response_text,
        'thinking' => $thinking_text,
        'usage'    => array(
            'input_tokens'    => $input_tokens,
            'output_tokens'   => $output_tokens,
            'thinking_tokens' => $thinking_tokens,
            'cost_usd'        => $cost,
        ),
    );
}
```

- [ ] **Step 4: Verify existing `anthropic_call()` still works with updated `anthropic_record_usage`**

The existing call in `anthropic_call()` passes 4 args to `anthropic_record_usage()`. The new signature has `$thinking = 0` as optional 5th param, so existing calls are backward-compatible. Verify by reading the function.

- [ ] **Step 5: Commit**

```bash
git add lib/anthropic.php
git commit -m "feat(overton): add anthropic_call_extended_thinking with thinking token tracking"
```

---

## Task 3: Versioned Prompt Files

**Files:**
- Create: `prompts/overton_v1.txt`
- Create: `prompts/overton_taxonomia_v1.txt`

- [ ] **Step 1: Create `prompts/` directory**

```bash
mkdir -p prompts
```

- [ ] **Step 2: Write `prompts/overton_v1.txt`**

Copy the complete prompt text from `DISEÑO_OVERTON.md` section 3 (the text inside the code fence block starting with "Eres un cartógrafo descriptivo de marcos informativos"). This is the system prompt for per-topic analysis.

- [ ] **Step 3: Write `prompts/overton_taxonomia_v1.txt`**

Copy the complete prompt text from `DISEÑO_OVERTON.md` section 10 (the text inside the code fence block starting with "Eres un taxónomo de debates públicos"). This is the system prompt for taxonomy generation.

- [ ] **Step 4: Commit**

```bash
git add prompts/
git commit -m "feat(overton): add versioned prompt files for analysis and taxonomy"
```

---

## Task 4: Core Overton Library (`lib/overton.php`)

**Files:**
- Create: `lib/overton.php`
- Modify: `test_overton.php` (add function tests)

This is the largest task. It contains all pure functions for the Overton system: dataset extraction, semaphore logic, label reconciliation, delta computation, validation.

- [ ] **Step 1: Write `lib/overton.php` — dataset extraction functions**

```php
<?php
/**
 * Prisma — Overton Observatory core functions.
 *
 * Pure functions for: dataset extraction, semaphore logic,
 * label reconciliation, delta computation, JSON validation.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// ── Dataset extraction ─────────────────────────────────────────

/**
 * Extracts Capa A articles for a topic since a given date.
 * @return array of article rows with radar fields joined
 */
function overton_extraer_capa_a(string $tema_slug, string $fecha_desde): array {
    $pdo = prisma_db();
    $stmt = $pdo->prepare('
        SELECT a.id, a.fecha_publicacion, a.ambito, a.titular_neutral,
               a.resumen, a.payload, a.fuentes_total,
               r.framing_evidence, r.framing_divergence, r.dominio_tematico,
               r.relevancia, r.fuentes_json, r.fecha
        FROM articulos a
        JOIN radar r ON r.articulo_id = a.id
        WHERE a.veredicto = \'APTO\'
          AND r.tema_slug = :tema_slug
          AND r.fecha >= :fecha_desde
        ORDER BY r.fecha ASC
    ');
    $stmt->execute(array('tema_slug' => $tema_slug, 'fecha_desde' => $fecha_desde));
    return $stmt->fetchAll();
}

/**
 * Extracts Capa B clusters (radar entries without APTO article) for a topic.
 * @return array of radar rows
 */
function overton_extraer_capa_b(string $tema_slug, string $fecha_desde): array {
    $pdo = prisma_db();
    $stmt = $pdo->prepare('
        SELECT r.id, r.titulo_tema, r.fecha, r.fuentes_json, r.relevancia,
               r.framing_divergence, r.framing_evidence, r.h_score
        FROM radar r
        WHERE r.tema_slug = :tema_slug
          AND r.articulo_id IS NULL
          AND r.relevancia IN (\'alta\', \'media\')
          AND r.fecha >= :fecha_desde
        ORDER BY r.fecha ASC
    ');
    $stmt->execute(array('tema_slug' => $tema_slug, 'fecha_desde' => $fecha_desde));
    return $stmt->fetchAll();
}
```

- [ ] **Step 2: Add semaphore logic**

Append to `lib/overton.php`:

```php
// ── Semaphore logic ─────────────────────────────────────────

/**
 * Computes semaphore state for a topic.
 * @return array ['estado' => string, 'color' => string, 'n_capa_a' => int, 'n_capa_b' => int, 'dias' => int|null]
 */
function overton_semaforo(string $tema_slug): array {
    $cfg = prisma_cfg();
    $pdo = prisma_db();
    $min_total = $cfg['overton_min_articulos_por_tema'];
    $min_a     = $cfg['overton_min_articulos_capa_a'];
    $min_dias  = $cfg['overton_min_dias_desde_ultimo'];

    // Find last analysis date
    $stmt = $pdo->prepare('
        SELECT fecha_ejecucion FROM overton_analisis
        WHERE tema_slug = :slug AND estado != \'error\'
        ORDER BY fecha_ejecucion DESC LIMIT 1
    ');
    $stmt->execute(array('slug' => $tema_slug));
    $last = $stmt->fetchColumn();

    // Find topic first appearance
    $stmt2 = $pdo->prepare('SELECT fecha_primera_aparicion FROM overton_temas WHERE slug = :slug');
    $stmt2->execute(array('slug' => $tema_slug));
    $primera = $stmt2->fetchColumn();

    $fecha_desde = $last ? substr($last, 0, 10) : ($primera ?: '2020-01-01');

    $capa_a = overton_extraer_capa_a($tema_slug, $fecha_desde);
    $capa_b = overton_extraer_capa_b($tema_slug, $fecha_desde);
    $n_a = count($capa_a);
    $n_b = count($capa_b);
    $n_total = $n_a + $n_b;

    $dias = null;
    if ($last) {
        $dias = (int)((time() - strtotime($last)) / 86400);
    }

    if ($n_total === 0) {
        return array('estado' => 'sin_datos', 'color' => 'gris', 'n_capa_a' => 0, 'n_capa_b' => 0, 'dias' => $dias);
    }
    if ($n_total < $min_total) {
        return array('estado' => 'datos_insuficientes', 'color' => 'gris', 'n_capa_a' => $n_a, 'n_capa_b' => $n_b, 'dias' => $dias);
    }
    if ($n_a < $min_a) {
        return array('estado' => 'evidencia_detallada_insuficiente', 'color' => 'amarillo', 'n_capa_a' => $n_a, 'n_capa_b' => $n_b, 'dias' => $dias);
    }
    if ($last !== null && $dias < $min_dias) {
        return array('estado' => 'tiempo_insuficiente', 'color' => 'amarillo', 'n_capa_a' => $n_a, 'n_capa_b' => $n_b, 'dias' => $dias);
    }
    if ($last === null) {
        return array('estado' => 'nunca_analizado', 'color' => 'verde_pulsante', 'n_capa_a' => $n_a, 'n_capa_b' => $n_b, 'dias' => null);
    }
    return array('estado' => 'listo', 'color' => 'verde', 'n_capa_a' => $n_a, 'n_capa_b' => $n_b, 'dias' => $dias);
}
```

- [ ] **Step 3: Add label reconciliation and delta computation**

Append to `lib/overton.php`:

```php
// ── Label reconciliation ────────────────────────────────────

/**
 * Builds a label mapping from transformation arrays produced by Opus.
 * Returns array ['label_map' => [...], 'fused' => [...], 'split' => [...]]
 */
function overton_reconciliar_labels(array $opus_json): array {
    $label_map = array(); // old_label => new_label
    $fused = array();     // new_label => [old_labels]
    $split = array();     // old_label => [new_labels]

    // Renames
    foreach ($opus_json['renombrados_desde_anterior'] ?? array() as $r) {
        $label_map[$r['marco_label_anterior']] = $r['marco_label_nuevo'];
    }

    // Fusions
    foreach ($opus_json['fusionados_desde_anterior'] ?? array() as $f) {
        foreach ($f['marcos_labels_anteriores'] as $old) {
            $label_map[$old] = $f['marco_label_nuevo'];
        }
        $fused[$f['marco_label_nuevo']] = $f['marcos_labels_anteriores'];
    }

    // Splits
    foreach ($opus_json['divididos_desde_anterior'] ?? array() as $d) {
        $split[$d['marco_label_anterior']] = $d['marcos_labels_nuevos'];
    }

    return array('label_map' => $label_map, 'fused' => $fused, 'split' => $split);
}

// ── Delta computation ───────────────────────────────────────

/**
 * Computes estado_vs_anterior for each marco in current analysis.
 * @param array $marcos_actuales marcos_detectados from current Opus output
 * @param array $marcos_anteriores marcos_detectados from previous analysis payload
 * @param array $reconciliacion output of overton_reconciliar_labels()
 * @return array marcos_actuales with 'estado_vs_anterior' field added
 */
function overton_computar_estados(array $marcos_actuales, array $marcos_anteriores, array $reconciliacion): array {
    $label_map = $reconciliacion['label_map'];
    $split = $reconciliacion['split'];

    // Build lookup of previous marcos by label (after applying renames/fusions)
    $prev_by_label = array();
    foreach ($marcos_anteriores as $m) {
        $label = $m['marco_label'];
        // Apply rename/fusion mapping
        if (isset($label_map[$label])) {
            $label = $label_map[$label];
        }
        if (isset($prev_by_label[$label])) {
            // Fusion: sum prevalences
            foreach (array('izquierda', 'centro', 'derecha') as $bloque) {
                $prev_by_label[$label]['presencia_por_bloque'][$bloque] =
                    ($prev_by_label[$label]['presencia_por_bloque'][$bloque] ?? 0)
                    + ($m['presencia_por_bloque'][$bloque] ?? 0);
            }
        } else {
            $prev_by_label[$label] = $m;
        }
    }

    // Compute estado for each current marco
    foreach ($marcos_actuales as &$marco) {
        $label = $marco['marco_label'];

        // Check if it's a product of a split
        $is_split = false;
        foreach ($split as $old => $new_labels) {
            if (in_array($label, $new_labels)) {
                $is_split = true;
                break;
            }
        }

        if ($is_split) {
            $marco['estado_vs_anterior'] = 'nuevo'; // surgido por división, no hay delta directo
        } elseif (!isset($prev_by_label[$label])) {
            $marco['estado_vs_anterior'] = 'nuevo';
        } else {
            $prev = $prev_by_label[$label];
            $max_delta = 0;
            foreach (array('izquierda', 'centro', 'derecha') as $bloque) {
                $curr_val = $marco['presencia_por_bloque'][$bloque] ?? 0;
                $prev_val = $prev['presencia_por_bloque'][$bloque] ?? 0;
                $delta = $curr_val - $prev_val;
                if (abs($delta) > abs($max_delta)) {
                    $max_delta = $delta;
                }
            }
            if (abs($max_delta) >= 0.10) {
                $marco['estado_vs_anterior'] = $max_delta > 0 ? 'expandido' : 'contraido';
            } else {
                $marco['estado_vs_anterior'] = 'persistente';
            }
        }
    }
    unset($marco);

    return $marcos_actuales;
}

/**
 * Computes deltas_contra_anterior array.
 * @return array of delta entries
 */
function overton_computar_deltas(array $marcos_actuales, array $marcos_anteriores, array $reconciliacion): array {
    $label_map = $reconciliacion['label_map'];
    $deltas = array();

    // Build prev lookup (same logic as computar_estados)
    $prev_by_label = array();
    foreach ($marcos_anteriores as $m) {
        $label = isset($label_map[$m['marco_label']]) ? $label_map[$m['marco_label']] : $m['marco_label'];
        if (isset($prev_by_label[$label])) {
            foreach (array('izquierda', 'centro', 'derecha') as $b) {
                $prev_by_label[$label]['presencia_por_bloque'][$b] =
                    ($prev_by_label[$label]['presencia_por_bloque'][$b] ?? 0)
                    + ($m['presencia_por_bloque'][$b] ?? 0);
            }
        } else {
            $prev_by_label[$label] = $m;
        }
    }

    foreach ($marcos_actuales as $marco) {
        $label = $marco['marco_label'];
        if (!isset($prev_by_label[$label])) continue; // new marco, no delta

        $prev = $prev_by_label[$label];
        $max_delta = 0;
        $bloque_afectado = 'centro';
        foreach (array('izquierda', 'centro', 'derecha') as $b) {
            $curr = $marco['presencia_por_bloque'][$b] ?? 0;
            $p = $prev['presencia_por_bloque'][$b] ?? 0;
            $d = $curr - $p;
            if (abs($d) > abs($max_delta)) {
                $max_delta = $d;
                $bloque_afectado = $b;
            }
        }

        // Global prevalence (average across blocs)
        $prev_global = 0;
        $curr_global = 0;
        foreach (array('izquierda', 'centro', 'derecha') as $b) {
            $prev_global += ($prev['presencia_por_bloque'][$b] ?? 0);
            $curr_global += ($marco['presencia_por_bloque'][$b] ?? 0);
        }
        $prev_global /= 3;
        $curr_global /= 3;

        $deltas[] = array(
            'marco_id'             => $marco['marco_id'],
            'marco_label'          => $label,
            'prevalencia_anterior' => round($prev_global, 2),
            'prevalencia_actual'   => round($curr_global, 2),
            'delta_puntos'         => round($curr_global - $prev_global, 2),
            'bloque_afectado'      => $bloque_afectado,
            'direccion'            => $max_delta >= 0 ? 'expansion' : 'contraccion',
        );
    }

    return $deltas;
}

/**
 * Detects marcos present in previous but absent in current (after reconciliation).
 * @return array of extinct marco entries
 */
function overton_detectar_extintos(array $marcos_actuales, array $marcos_anteriores, array $reconciliacion, string $origen): array {
    $label_map = $reconciliacion['label_map'];
    $split = $reconciliacion['split'];
    $extintos = array();

    $current_labels = array();
    foreach ($marcos_actuales as $m) {
        $current_labels[$m['marco_label']] = true;
    }

    foreach ($marcos_anteriores as $m) {
        $label = $m['marco_label'];
        $mapped = isset($label_map[$label]) ? $label_map[$label] : $label;

        // Skip if it was split (handled separately)
        if (isset($split[$label])) continue;

        if (!isset($current_labels[$mapped])) {
            $prev_global = 0;
            foreach (array('izquierda', 'centro', 'derecha') as $b) {
                $prev_global += ($m['presencia_por_bloque'][$b] ?? 0);
            }
            $extintos[] = array(
                'marco_label'               => $label,
                'ultima_prevalencia'         => round($prev_global / 3, 2),
                'ultimo_periodo_relevante'   => $m['evolucion_temporal'][count($m['evolucion_temporal']) - 1]['periodo'] ?? '',
                'origen'                     => $origen,
            );
        }
    }

    return $extintos;
}
```

- [ ] **Step 4: Add JSON validation functions**

Append to `lib/overton.php`:

```php
// ── Validation ──────────────────────────────────────────────

/**
 * Counts words in a string.
 */
function overton_contar_palabras(string $text): int {
    return count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY));
}

/**
 * Validates Opus JSON output for a topic analysis.
 * @return array ['valid' => bool, 'errors' => string[], 'warnings' => string[]]
 */
function overton_validar_json_analisis(array $json, int $n_capa_a_esperado, array $anterior_marcos = null): array {
    $errors = array();
    $warnings = array();

    // Required fields
    if (empty($json['marcos_detectados'])) {
        $errors[] = 'Campo obligatorio marcos_detectados ausente o vacío';
    }

    // Unique marco_ids
    $ids = array();
    foreach ($json['marcos_detectados'] ?? array() as $m) {
        if (empty($m['marco_id'])) {
            $errors[] = 'marco sin marco_id';
        } elseif (isset($ids[$m['marco_id']])) {
            $errors[] = 'marco_id duplicado: ' . $m['marco_id'];
        }
        $ids[$m['marco_id'] ?? ''] = true;

        // Min evidence
        if (($m['n_articulos_evidencia'] ?? 0) < 3) {
            $warnings[] = 'Marco "' . ($m['marco_label'] ?? '?') . '" con < 3 artículos de evidencia';
        }

        // Presencia range
        foreach (array('izquierda', 'centro', 'derecha') as $b) {
            $v = $m['presencia_por_bloque'][$b] ?? null;
            if ($v !== null && ($v < 0 || $v > 1)) {
                $errors[] = 'presencia_por_bloque fuera de rango [0,1] en ' . ($m['marco_label'] ?? '?');
            }
        }
    }

    // Word length limits on señales débiles
    foreach ($json['senales_debiles'] ?? array() as $s) {
        if (isset($s['descripcion_factual']) && overton_contar_palabras($s['descripcion_factual']) > 30) {
            $warnings[] = 'señal débil excede 30 palabras: ' . substr($s['descripcion_factual'], 0, 60) . '...';
        }
    }

    // Word length limits on transformations
    foreach (array('renombrados_desde_anterior', 'fusionados_desde_anterior', 'divididos_desde_anterior') as $key) {
        foreach ($json[$key] ?? array() as $t) {
            if (isset($t['justificacion']) && overton_contar_palabras($t['justificacion']) > 40) {
                $warnings[] = "justificacion en $key excede 40 palabras";
            }
        }
    }

    // Transformation consistency (if previous analysis exists)
    if ($anterior_marcos !== null) {
        $prev_labels = array();
        foreach ($anterior_marcos as $m) {
            $prev_labels[$m['marco_label']] = true;
        }

        foreach ($json['renombrados_desde_anterior'] ?? array() as $r) {
            if (!isset($prev_labels[$r['marco_label_anterior']])) {
                $errors[] = 'renombrado: label anterior "' . $r['marco_label_anterior'] . '" no existe en análisis previo';
            }
        }
        foreach ($json['fusionados_desde_anterior'] ?? array() as $f) {
            foreach ($f['marcos_labels_anteriores'] as $old) {
                if (!isset($prev_labels[$old])) {
                    $errors[] = 'fusión: label anterior "' . $old . '" no existe en análisis previo';
                }
            }
        }
        foreach ($json['divididos_desde_anterior'] ?? array() as $d) {
            if (!isset($prev_labels[$d['marco_label_anterior']])) {
                $errors[] = 'división: label anterior "' . $d['marco_label_anterior'] . '" no existe en análisis previo';
            }
        }
    }

    // Interpretive text heuristic
    $interpretive_patterns = array('/\bindica que\b/i', '/\bsugiere que\b/i', '/\bpreocupante\b/i', '/\balentador\b/i', '/\bsignifica que\b/i');
    $text_fields = array();
    foreach ($json['senales_debiles'] ?? array() as $s) {
        $text_fields[] = $s['descripcion_factual'] ?? '';
    }
    foreach ($text_fields as $tf) {
        foreach ($interpretive_patterns as $pat) {
            if (preg_match($pat, $tf)) {
                $warnings[] = 'Posible texto interpretativo detectado: ' . substr($tf, 0, 60);
                break;
            }
        }
    }

    return array(
        'valid'    => empty($errors),
        'errors'   => $errors,
        'warnings' => $warnings,
    );
}
```

- [ ] **Step 5: Add tests for delta computation to `test_overton.php`**

Append to `test_overton.php` after the schema checks:

```php
require_once __DIR__ . '/lib/overton.php';

echo "\n=== Delta computation tests ===\n";

// Test label reconciliation
$opus_json = array(
    'renombrados_desde_anterior' => array(
        array('marco_label_anterior' => 'old_marco', 'marco_label_nuevo' => 'new_marco', 'justificacion' => 'test')
    ),
    'fusionados_desde_anterior' => array(
        array('marcos_labels_anteriores' => array('a', 'b'), 'marco_label_nuevo' => 'ab', 'justificacion' => 'test')
    ),
);
$rec = overton_reconciliar_labels($opus_json);
check("Rename maps old→new", $rec['label_map']['old_marco'] === 'new_marco');
check("Fusion maps a→ab", $rec['label_map']['a'] === 'ab');
check("Fusion maps b→ab", $rec['label_map']['b'] === 'ab');
check("Fused records ab→[a,b]", $rec['fused']['ab'] === array('a', 'b'));

// Test estado computation
$marcos_prev = array(
    array('marco_id' => 'm1', 'marco_label' => 'stable', 'presencia_por_bloque' => array('izquierda' => 0.5, 'centro' => 0.3, 'derecha' => 0.2)),
    array('marco_id' => 'm2', 'marco_label' => 'growing', 'presencia_por_bloque' => array('izquierda' => 0.2, 'centro' => 0.1, 'derecha' => 0.1)),
    array('marco_id' => 'm3', 'marco_label' => 'dying', 'presencia_por_bloque' => array('izquierda' => 0.4, 'centro' => 0.3, 'derecha' => 0.2)),
);
$marcos_curr = array(
    array('marco_id' => 'n1', 'marco_label' => 'stable', 'presencia_por_bloque' => array('izquierda' => 0.52, 'centro' => 0.28, 'derecha' => 0.19)),
    array('marco_id' => 'n2', 'marco_label' => 'growing', 'presencia_por_bloque' => array('izquierda' => 0.5, 'centro' => 0.3, 'derecha' => 0.1)),
    array('marco_id' => 'n3', 'marco_label' => 'brand_new', 'presencia_por_bloque' => array('izquierda' => 0.3, 'centro' => 0.2, 'derecha' => 0.1)),
);
$empty_rec = overton_reconciliar_labels(array());
$result = overton_computar_estados($marcos_curr, $marcos_prev, $empty_rec);
check("Stable marco → persistente", $result[0]['estado_vs_anterior'] === 'persistente');
check("Growing marco → expandido", $result[1]['estado_vs_anterior'] === 'expandido');
check("Brand new marco → nuevo", $result[2]['estado_vs_anterior'] === 'nuevo');

// Test extintos detection
$extintos = overton_detectar_extintos($marcos_curr, $marcos_prev, $empty_rec, 'anterior');
check("Dying marco detected as extinto", count($extintos) === 1 && $extintos[0]['marco_label'] === 'dying');

// Test word counter
check("Word count 'hello world' = 2", overton_contar_palabras('hello world') === 2);
check("Word count empty = 0", overton_contar_palabras('') === 0);
check("Word count 30 words", overton_contar_palabras(str_repeat('word ', 30)) === 30);

echo "\n=== Results: $pass passed, $fail failed ===\n";
```

- [ ] **Step 6: Run tests**

Run: `php test_overton.php`
Expected: All checks pass

- [ ] **Step 7: Commit**

```bash
git add lib/overton.php test_overton.php
git commit -m "feat(overton): core library with dataset extraction, semaphore, deltas, and validation"
```

---

## Task 5: Taxonomy Process (`overton-taxonomia.php`)

**Files:**
- Create: `overton-taxonomia.php`

- [ ] **Step 1: Write `overton-taxonomia.php`**

This script:
1. Accepts `--completo` or `--incremental` mode
2. Extracts relevant articles grouped by `dominio_tematico`
3. Calls Opus + ET with taxonomy prompt
4. Validates output
5. Inserts topics as `propuesto` in `overton_temas`
6. Records version in `overton_catalogo_versiones`

The implementation should follow the pattern of `analizar.php`: CLI-executable but also callable from panel via `include` with output buffering.

Key points from spec:
- Read prompt from `prompts/overton_taxonomia_v1.txt`
- Use model from `overton_taxonomia_modelo` config
- ET budget from `overton_taxonomia_thinking_budget` config
- All topics inserted with `estado='propuesto'`
- Validate word limits: `justificacion_agrupacion` ≤ 40, `decisiones_ambiguas` ≤ 60

Refer to `DISEÑO_OVERTON.md` section 10 for full architecture and prompt format.

- [ ] **Step 2: Commit**

```bash
git add overton-taxonomia.php
git commit -m "feat(overton): taxonomy generation process with Opus + Extended Thinking"
```

---

## Task 6: Analysis Process (`analisis-overton.php`)

**Files:**
- Create: `analisis-overton.php`

- [ ] **Step 1: Write `analisis-overton.php`**

This script:
1. Accepts `tema_slug` as parameter
2. Checks semaphore (must be green)
3. Loads previous analysis of same topic (if exists)
4. Extracts Capa A + Capa B dataset
5. Formats user prompt per spec section 4
6. Calls Opus + ET with analysis prompt from `prompts/overton_v1.txt`
7. Validates JSON output (schema, uniqueness, word limits, transformation consistency)
8. If validation fails: retry once with error feedback
9. Reconciles labels, computes estado_vs_anterior, computes deltas (PHP)
10. Assembles final payload_json
11. Inserts as borrador in overton_analisis
12. If first analysis ever: creates baseline automatically

Key points from spec:
- Use model from `overton_modelo` config
- ET budget from `overton_thinking_budget` config
- Read prompt from `prompts/overton_v1.txt`
- Persist `thinking_raw` in BD
- On second validation failure: `estado='error'` with `error_detalle`

Refer to `DISEÑO_OVERTON.md` sections 1, 3, 4, 5, 12 for full details.

- [ ] **Step 2: Commit**

```bash
git add analisis-overton.php
git commit -m "feat(overton): per-topic analysis process with label reconciliation and delta computation"
```

---

## Task 7: Panel Management (`panel-overton.php`)

**Files:**
- Create: `panel-overton.php`
- Modify: `panel.php` (add Overton widget in stats section)

- [ ] **Step 1: Write `panel-overton.php`**

This page follows the auth pattern of `panel.php` (session check, same password). Sections:

1. **Topic list** with semaphore per row, execute button
2. **Clusters without tema_slug** with count per dominio
3. **Analysis history** filterable by topic/status, with View/Publish/Discard buttons
4. **Catalog management**: proposed topics → activate, version history, regenerate
5. **Baseline management**: create, archive, age warning
6. **Borrador review**: rendered JSON + SVG charts + collapsible thinking_raw + raw JSON

Refer to `DISEÑO_OVERTON.md` section 9 for wireframes.

- [ ] **Step 2: Add Overton widget to `panel.php` stats section**

Find the stats dashboard area and add:

```php
// Overton Observatory status
$overton_temas = $pdo->query("SELECT COUNT(*) FROM overton_temas WHERE estado = 'activo'")->fetchColumn();
if ($overton_temas > 0) {
    $overton_listos = 0; // computed via overton_semaforo per topic
    echo '<div class="stat-card">';
    echo '<strong>Observatorio Overton:</strong> ';
    echo $overton_listos . ' temas listos | ' . $overton_temas . ' activos';
    echo ' <a href="panel-overton.php">Gestionar</a>';
    echo '</div>';

    // Catalog age warning
    $cat_version = $pdo->query("SELECT fecha_generacion FROM overton_catalogo_versiones ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($cat_version) {
        $cat_meses = (time() - strtotime($cat_version)) / (30 * 86400);
        $cfg = prisma_cfg();
        if ($cat_meses > ($cfg['overton_catalogo_meses_sugeridos'] ?? 9)) {
            echo '<div class="stat-card warning">El catálogo de temas tiene ' . round($cat_meses) . ' meses. Considere regenerar.</div>';
        }
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add panel-overton.php panel.php
git commit -m "feat(overton): admin panel with topic management, semaphore, and catalog gate"
```

---

## Task 8: Haiku Extension for `tema_sugerido`

**Files:**
- Modify: `lib/gate_haiku.php` (extend prompt + output parsing)
- Modify: `lib/common.php` (extend `radar_insertar_todos()`)
- Modify: `escanear.php` (pass active tema slugs, write tema_slug)

**Prerequisite:** Requires active topics in `overton_temas` (promoted from `propuesto` via panel).

- [ ] **Step 1: Extend Haiku system prompt in `lib/gate_haiku.php`**

Add to the system prompt (after the framing_evidence instruction):

```
5. TEMA SUGERIDO (opcional): si el cluster encaja claramente en uno de los
   temas activos del catálogo proporcionado, devuelve su slug. Si no encaja
   o es ambiguo: null.
```

Add the active tema slugs to the user prompt:

```php
// Before building the batch JSON, query active temas
$temas_activos = $pdo->query("SELECT slug, label, dominio_tematico FROM overton_temas WHERE estado = 'activo'")->fetchAll();
// Include in prompt as context
```

- [ ] **Step 2: Extend output validation to accept `tema_sugerido`**

In the response parsing section, add:

```php
$resultado['tema_sugerido'] = $item['tema_sugerido'] ?? null;
// Validate: if not null, must be a slug in the active temas list
```

- [ ] **Step 3: Extend `radar_insertar_todos()` in `lib/common.php`**

Add `tema_slug` to the INSERT statement and bind it from the Haiku result's `tema_sugerido`.

- [ ] **Step 4: Update `escanear.php` to pass tema_slug through**

After receiving Haiku results, map `tema_sugerido` to the theme data structure that feeds into `radar_insertar_todos()`.

- [ ] **Step 5: Commit**

```bash
git add lib/gate_haiku.php lib/common.php escanear.php
git commit -m "feat(overton): extend Haiku batch to suggest tema_slug for radar entries"
```

---

## Task 9: SVG Chart Library (`lib/overton_charts.php`)

**Files:**
- Create: `lib/overton_charts.php`

- [ ] **Step 1: Write stacked bar chart function**

```php
<?php
/**
 * Prisma — Overton SVG chart generators.
 * Pure functions that return SVG strings. No JS, no dependencies.
 */

/**
 * Renders horizontal stacked bars showing marco distribution per bloc.
 *
 * @param array $marcos_detectados from analysis payload
 * @param array $palette color palette indexed 0..N
 * @return string SVG markup
 */
function overton_chart_barras_bloque(array $marcos_detectados, array $palette = null): string {
    // Implementation: for each bloc (izquierda, centro, derecha),
    // render a horizontal bar with segments proportional to each marco's presencia.
    // Use theme-compatible colors via CSS variables where possible.
    // Reference: DISEÑO_OVERTON.md section 8 (stacked bars wireframe).
}
```

- [ ] **Step 2: Write line chart function**

```php
/**
 * Renders line chart showing temporal evolution of marcos per bloc.
 *
 * @param array $marcos_detectados with evolucion_temporal
 * @return string SVG markup (empty string if only 1 period)
 */
function overton_chart_evolucion(array $marcos_detectados): string {
    // Implementation: X axis = periods, Y axis = prevalence [0,1].
    // One line per marco, differentiated by stroke style.
    // Only render if >1 period in evolucion_temporal.
    // Reference: DISEÑO_OVERTON.md section 8 (line chart wireframe).
}
```

- [ ] **Step 3: Commit**

```bash
git add lib/overton_charts.php
git commit -m "feat(overton): SVG inline chart generators for stacked bars and evolution lines"
```

---

## Task 10: Public Page (`overton.php`)

**Files:**
- Create: `overton.php`
- Modify: `lib/layout.php` (add nav + footer link)

- [ ] **Step 1: Write `overton.php`**

Structure from `DISEÑO_OVERTON.md` section 8:
1. Hero with eyebrow + title + subtitle
2. Non-normativity block (text from section 11 of spec)
3. Catalog transparency block
4. Per-topic sections with SVG charts, marcos, pairs, deltas, transformations
5. Footer metadata

All descriptive prose is mechanical PHP templates, not model-generated text.

Use `page_header('Observatorio', '...', 'overton')` and `page_footer()`.

- [ ] **Step 2: Add "Observatorio" to nav in `lib/layout.php`**

In `render_nav()`, add to `$nav_items`:

```php
'overton' => array('Observatorio', $B . 'overton.php'),
```

- [ ] **Step 3: Add "Observatorio" to footer grid in `render_footer_grid()`**

Add under the "Estándar" section:

```php
'<li><a href="' . $B . 'overton.php">Observatorio</a></li>'
```

- [ ] **Step 4: Commit**

```bash
git add overton.php lib/layout.php
git commit -m "feat(overton): public page with scroll-guided visual essay and nav integration"
```

---

## Task 11: Integration & Polish

**Files:**
- Various CSS additions in page files
- Auth protection on panel-overton.php and process scripts

- [ ] **Step 1: Verify auth protection**

Ensure `panel-overton.php` checks session auth same as `panel.php`. Ensure `analisis-overton.php` and `overton-taxonomia.php` are only callable from panel context or CLI.

- [ ] **Step 2: Dark/light mode CSS compatibility**

All new pages use `var(--text)`, `var(--bg)`, `var(--border)` etc. from `theme.php`. Test both modes in browser.

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "feat(overton): integration polish — auth, theming, and CSS consistency"
```

---

## Task 12: Sonnet vs Opus Comparativa (Fase 2-bis)

**Manual task — no code changes required.**

- [ ] **Step 1: Verify Sonnet supports Extended Thinking**

Check Anthropic docs for `claude-sonnet-4-6` ET support.

- [ ] **Step 2: Run 2-3 topic analyses with Sonnet**

Change `overton_modelo` in config temporarily to `claude-sonnet-4-6`. Execute analysis for the same topics previously analyzed with Opus.

- [ ] **Step 3: Compare outputs manually**

Evaluate: marco detection quality, label stability, pair richness, signal quality, transformation declarations. Document findings.

- [ ] **Step 4: Decision and documentation**

If Sonnet equivalent → update `overton_modelo` default. If not → keep Opus. Document decision with example outputs.

- [ ] **Step 5: Commit config change (if any)**

```bash
git add config.php
git commit -m "feat(overton): canonize model choice after Sonnet/Opus comparativa"
```
