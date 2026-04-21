# Algoritmo de Tensión Informativa — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Prisma's naive topic scoring with a mathematically transparent tension index, add Haiku triage, and publish all detected topics in a unified radar listing.

**Architecture:** Evolve `curador.php` scoring formula, add Haiku batch triage in `common.php`, create `radar` SQLite table, rebuild `index.php` as unified radar listing, add dual-mode rendering to `articulo.php`, update all static content pages.

**Tech Stack:** PHP 7.4, SQLite, Anthropic API (Claude Haiku for triage, Sonnet for synthesis), pure CSS/SVG (no JS frameworks).

**Spec:** `docs/superpowers/specs/2026-04-20-tension-informativa-design.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|----------------|
| `config.php` | Modify | Add `model_triage`, `umbral_tension` params |
| `db.php` | Modify | Add `radar` table creation + retention cleanup |
| `lib/curador.php` | Modify | Replace scoring with tension formula, relax cluster filters for radar |
| `lib/anthropic.php` | Modify | Add Haiku to pricing table |
| `lib/common.php` | Modify | Add radar INSERT/UPDATE, Haiku triage integration, pipeline link-back |
| `lib/layout.php` | Modify | Add `render_circulo_tension()` and `render_barras_tension()` helpers |
| `pipeline.php` | Modify | Integrate radar population and triage into pipeline flow |
| `index.php` | Rewrite | Unified radar listing from `radar` table |
| `articulo.php` | Modify | Add dual-mode routing (analyzed vs radar), tension UI in both |
| `manifiesto.php` | Modify | Update step 2, FAQ, Schema.org |
| `ia.php` | Modify | Update process description, add Haiku to models |
| `fuentes.php` | Modify | Rewrite selection criteria section |
| `axiomas.php` | Modify | Add introductory note linking to tension index |

---

## Task 1: Config + Database Foundation

**Files:**
- Modify: `config.php:32-118`
- Modify: `db.php:9-38`
- Modify: `lib/anthropic.php:11-17`

- [ ] **Step 1: Add new config params**

In `config.php`, add after line 37 (`'model_audit'`):

```php
'model_triage'        => 'claude-haiku-4-5-20251001',
```

Add after line 51 (`'articulos_dia'`):

```php
'min_cuadrantes'      => 3,             // Mínimo de cuadrantes para ir al pipeline Sonnet
'umbral_tension'      => 0.60,          // H mínimo para ser candidato a análisis
```

- [ ] **Step 2: Add Haiku to pricing table**

In `lib/anthropic.php`, add to the `ANTHROPIC_PRICING` array after line 16 (`'default'`), before the closing `]`:

```php
'claude-haiku-4-5-20251001'  => ['input' => 0.80,  'output' => 4.00],
```

- [ ] **Step 3: Add radar table to db.php**

In `db.php`, add after line 35 (after the `idx_fecha` index creation):

```php
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
```

- [ ] **Step 4: Verify DB init works**

Run: `php -r "require 'db.php'; \$db = prisma_db(); echo 'OK'; "`

Expected: `OK` with no errors. Check that `data/prisma.db` now has a `radar` table.

- [ ] **Step 5: Commit**

```bash
git add config.php db.php lib/anthropic.php
git commit -m "feat: add radar table, triage config, and Haiku pricing"
```

---

## Task 2: Tension Formula in Curador

**Files:**
- Modify: `lib/curador.php:16-101`

- [ ] **Step 1: Add quadrant position map and group constants**

Add at the top of `lib/curador.php`, after the docblock (line 7), before `function curador_seleccionar`:

```php
// Ideological spectrum positions for tension calculation
define('PRISMA_CUADRANTE_POS', [
    'izquierda-populista' => -3,
    'izquierda'           => -2,
    'centro-izquierda'    => -1,
    'centro'              =>  0,
    'centro-derecha'      =>  1,
    'derecha'             =>  2,
    'derecha-populista'   =>  3,
]);

define('PRISMA_GRUPO_IZQ', ['izquierda-populista', 'izquierda', 'centro-izquierda']);
define('PRISMA_GRUPO_DER', ['centro-derecha', 'derecha', 'derecha-populista']);
define('PRISMA_GRUPO_CENTRO', ['centro']);
```

- [ ] **Step 2: Add `calcular_tension()` function**

Add before `function extraer_keywords` (before line 106):

```php
/**
 * Calcula el índice de tensión informativa de un cluster.
 *
 * @param array $articles Artículos del cluster (cada uno con 'cuadrante')
 * @return array ['h_score'=>float, 'h_asimetria'=>float, 'h_divergencia'=>float, 'h_varianza'=>float]
 */
function calcular_tension(array $articles): array {
    // --- Signal A: Coverage Asymmetry (45%) ---
    $izq_n = 0;
    $der_n = 0;
    $centro_n = 0;
    foreach ($articles as $art) {
        $c = $art['cuadrante'];
        if (in_array($c, PRISMA_GRUPO_IZQ)) $izq_n++;
        elseif (in_array($c, PRISMA_GRUPO_DER)) $der_n++;
        else $centro_n++;
    }
    $total = $izq_n + $der_n + $centro_n;
    $asimetria = ($total > 0) ? abs($izq_n - $der_n) / $total : 0.0;

    // --- Signal B: Lexical Divergence (40%) ---
    $kw_izq = [];
    $kw_der = [];
    foreach ($articles as $art) {
        $kw = extraer_keywords($art['titulo']);
        $c = $art['cuadrante'];
        if (in_array($c, PRISMA_GRUPO_IZQ)) {
            $kw_izq = array_merge($kw_izq, array_keys($kw));
        } elseif (in_array($c, PRISMA_GRUPO_DER)) {
            $kw_der = array_merge($kw_der, array_keys($kw));
        }
    }
    $kw_izq = array_flip(array_unique($kw_izq));
    $kw_der = array_flip(array_unique($kw_der));

    if (empty($kw_izq) || empty($kw_der)) {
        $divergencia = 0.0;
    } else {
        $divergencia = 1.0 - keywords_similarity($kw_izq, $kw_der);
    }

    // --- Signal C: Spectrum Variance (15%) ---
    $cuadrantes = array_unique(array_column($articles, 'cuadrante'));
    $posiciones = [];
    foreach ($cuadrantes as $c) {
        if (isset(PRISMA_CUADRANTE_POS[$c])) {
            $posiciones[] = PRISMA_CUADRANTE_POS[$c];
        }
    }
    $varianza_norm = 0.0;
    if (count($posiciones) >= 2) {
        $mean = array_sum($posiciones) / count($posiciones);
        $sq_diff = 0.0;
        foreach ($posiciones as $p) {
            $sq_diff += ($p - $mean) * ($p - $mean);
        }
        $variance = $sq_diff / count($posiciones);
        $varianza_norm = min($variance / 9.0, 1.0);
    }

    // --- Composite Score ---
    $h = 0.45 * $asimetria + 0.40 * $divergencia + 0.15 * $varianza_norm;

    return [
        'h_score'       => round($h, 4),
        'h_asimetria'   => round($asimetria, 4),
        'h_divergencia' => round($divergencia, 4),
        'h_varianza'    => round($varianza_norm, 4),
    ];
}
```

- [ ] **Step 3: Modify `curador_seleccionar()` to use tension formula and relax filters for radar**

Replace lines 61-100 (from `// 3. Puntuar cada cluster` through `return $selected;`) with this single block:

```php
// 3. Score each cluster with tension formula — no min_cuadrantes filter (all go to radar)
$scored = [];
foreach ($clusters as $cluster) {
    $arts = array_map(fn($i) => $indexed[$i]['article'], $cluster);
    $cuadrantes = array_unique(array_column($arts, 'cuadrante'));

    // Título representativo: el más corto (suele ser el más factual)
    usort($arts, fn($a, $b) => mb_strlen($a['titulo']) - mb_strlen($b['titulo']));
    $titulo_tema = $arts[0]['titulo'];

    $tension = calcular_tension($arts);

    $scored[] = [
        'titulo_tema'   => $titulo_tema,
        'articulos'     => $arts,
        'cuadrantes'    => array_values($cuadrantes),
        'n_articulos'   => count($cluster),
        'n_cuadrantes'  => count($cuadrantes),
        'score'         => $tension['h_score'],
        'h_score'       => $tension['h_score'],
        'h_asimetria'   => $tension['h_asimetria'],
        'h_divergencia' => $tension['h_divergencia'],
        'h_varianza'    => $tension['h_varianza'],
    ];
}

// 4. Sort by tension score descending
usort($scored, fn($a, $b) => $b['h_score'] <=> $a['h_score']);

foreach ($scored as $tema) {
    prisma_log("CURADOR", sprintf(
        "Tema: \"%s\" — %d arts, %d cuad, H=%.0f%% (A=%.0f%% D=%.0f%% V=%.0f%%)",
        mb_substr($tema['titulo_tema'], 0, 60),
        $tema['n_articulos'],
        $tema['n_cuadrantes'],
        $tema['h_score'] * 100,
        $tema['h_asimetria'] * 100,
        $tema['h_divergencia'] * 100,
        $tema['h_varianza'] * 100
    ));
}

return $scored;
```

Also update the function signature: remove `$min_cuadrantes` param (no longer used for initial clustering), and remove `$max_temas` (pipeline handles slicing). The new signature becomes:

```php
function curador_seleccionar(array $articles): array {
```

Note: `curador_seleccionar()` now returns ALL scored topics (no min_cuadrantes filter, no slicing). The pipeline applies the `min_cuadrantes` filter at the Sonnet stage and slices after triage.

- [ ] **Step 4: Test the formula manually**

Run: `php -r "
require 'config.php';
require 'lib/curador.php';
require 'lib/common.php';

\$arts = [
    ['titulo'=>'Gobierno aprueba reforma fiscal progresiva','cuadrante'=>'centro-izquierda'],
    ['titulo'=>'Subida masiva de impuestos aprobada por el Gobierno','cuadrante'=>'derecha'],
    ['titulo'=>'Reforma fiscal entra en vigor','cuadrante'=>'centro'],
];
\$t = calcular_tension(\$arts);
echo json_encode(\$t, JSON_PRETTY_PRINT);
"`

Expected: JSON output with all four scores as floats between 0 and 1. Asymmetry should be moderate (1 izq, 1 der, 1 centro), divergence should be moderate-high (different vocabulary), variance should be non-zero.

- [ ] **Step 5: Commit**

```bash
git add lib/curador.php
git commit -m "feat: replace curador scoring with tension informativa formula"
```

---

## Task 3: Radar Population + Haiku Triage

**Files:**
- Modify: `lib/common.php`
- Modify: `pipeline.php`

- [ ] **Step 1: Add radar DB functions to `lib/common.php`**

Add at the end of `lib/common.php`:

```php
/**
 * Inserts all detected topics into the radar table.
 *
 * @param array $temas All scored topics from curador_seleccionar()
 * @param string $ambito Current ambito
 * @param string $fecha Date string YYYY-MM-DD
 * @return array Same topics with 'radar_id' added to each
 */
function radar_insertar_todos(array $temas, string $ambito, string $fecha): array {
    require_once __DIR__ . '/../db.php';
    $db = prisma_db();

    $stmt = $db->prepare('INSERT INTO radar
        (fecha, titulo_tema, ambito, h_score, h_asimetria, h_divergencia, h_varianza, fuentes_json)
        VALUES (:fecha, :titulo, :ambito, :h_score, :h_asim, :h_div, :h_var, :fuentes)');

    foreach ($temas as &$tema) {
        $fuentes = [];
        foreach ($tema['articulos'] as $art) {
            $fuentes[] = [
                'medio'     => $art['medio'],
                'titulo'    => $art['titulo'],
                'url'       => $art['url'],
                'cuadrante' => $art['cuadrante'],
            ];
        }

        $stmt->execute([
            ':fecha'   => $fecha,
            ':titulo'  => $tema['titulo_tema'],
            ':ambito'  => $ambito,
            ':h_score' => $tema['h_score'],
            ':h_asim'  => $tema['h_asimetria'],
            ':h_div'   => $tema['h_divergencia'],
            ':h_var'   => $tema['h_varianza'],
            ':fuentes' => json_encode($fuentes, JSON_UNESCAPED_UNICODE),
        ]);

        $tema['radar_id'] = $db->lastInsertId();
    }
    unset($tema);

    prisma_log("RADAR", count($temas) . " temas insertados en radar.");
    return $temas;
}

/**
 * Updates a radar record with Haiku triage results.
 */
function radar_actualizar_triage(int $radar_id, string $frase, bool $analizado): void {
    require_once __DIR__ . '/../db.php';
    $db = prisma_db();
    $stmt = $db->prepare('UPDATE radar SET haiku_frase = :frase, analizado = :anal WHERE id = :id');
    $stmt->execute([':frase' => $frase, ':anal' => $analizado ? 1 : 0, ':id' => $radar_id]);
}

/**
 * Links a radar record to a published article.
 */
function radar_link_articulo(int $radar_id, string $articulo_id): void {
    require_once __DIR__ . '/../db.php';
    $db = prisma_db();
    $stmt = $db->prepare('UPDATE radar SET articulo_id = :aid WHERE id = :id');
    $stmt->execute([':aid' => $articulo_id, ':id' => $radar_id]);
}

/**
 * Cleans up radar entries older than 90 days.
 */
function radar_limpiar(): void {
    require_once __DIR__ . '/../db.php';
    $db = prisma_db();
    $deleted = $db->exec("DELETE FROM radar WHERE fecha < date('now', '-90 days')");
    if ($deleted > 0) {
        prisma_log("RADAR", "Limpieza: $deleted registros antiguos eliminados.");
    }
}

/**
 * Haiku triage: confirms tension and generates explanatory phrases.
 *
 * @param array $candidatos Topics with H >= umbral (must have 'radar_id', 'titulo_tema', 'articulos')
 * @return array Filtered array of confirmed topics, each with 'haiku_frase' added
 */
function triage_haiku(array $candidatos): array {
    $cfg = prisma_cfg();

    // Build batch prompt
    $temas_text = '';
    foreach ($candidatos as $i => $tema) {
        $num = $i + 1;
        $temas_text .= "\nTema $num: \"{$tema['titulo_tema']}\" (H={$tema['h_score']})\n";

        $por_cuadrante = [];
        foreach ($tema['articulos'] as $art) {
            $por_cuadrante[$art['cuadrante']][] = $art['titulo'];
        }
        foreach ($por_cuadrante as $cuadrante => $titulares) {
            $temas_text .= "  [$cuadrante]: " . implode(' | ', array_slice($titulares, 0, 3)) . "\n";
        }
    }

    $system = 'Eres un analista de medios. Evalúas si la tensión informativa detectada entre fuentes de distintos cuadrantes ideológicos es genuina o un falso positivo (ej. vocabulario técnico diverso que no refleja divergencia editorial real). Respondes SOLO en JSON válido, sin markdown.';

    $user_msg = "Evalúa estos " . count($candidatos) . " temas. Para cada uno, indica si la tensión es genuina (confirma: true/false) y una frase explicativa de una línea en español describiendo la naturaleza de la tensión o la razón del descarte.

Responde con un array JSON:
[{\"tema\": 1, \"confirma\": true/false, \"frase\": \"...\"}]

$temas_text";

    try {
        $model = $cfg['model_triage'];
        $raw = anthropic_call($model, $system, $user_msg, 2048);
        $results = parse_json_response($raw);
    } catch (Throwable $e) {
        // Fallback: skip triage, use all candidates
        prisma_log("TRIAGE", "ERROR Haiku: " . $e->getMessage() . " — usando H score bruto.");
        foreach ($candidatos as &$tema) {
            $tema['haiku_frase'] = null;
            radar_actualizar_triage($tema['radar_id'], '', true);
        }
        unset($tema);
        return $candidatos;
    }

    // Map results back to candidates
    $confirmados = [];
    foreach ($results as $r) {
        $idx = ($r['tema'] ?? 0) - 1;
        if ($idx < 0 || $idx >= count($candidatos)) continue;

        $tema = $candidatos[$idx];
        $frase = $r['frase'] ?? '';
        $confirma = !empty($r['confirma']);

        radar_actualizar_triage($tema['radar_id'], $frase, $confirma);
        $tema['haiku_frase'] = $frase;

        if ($confirma) {
            $confirmados[] = $tema;
        } else {
            prisma_log("TRIAGE", "Descartado: \"{$tema['titulo_tema']}\" — $frase");
        }
    }

    prisma_log("TRIAGE", count($confirmados) . " de " . count($candidatos) . " confirmados por Haiku.");
    return $confirmados;
}
```

- [ ] **Step 2: Rewrite `pipeline.php` to integrate radar + triage**

Replace the pipeline section (lines 50-110) with the new flow:

```php
// 0. Cleanup old radar entries
radar_limpiar();

// 1. Read RSS
prisma_log("MAIN", "Paso 1: Leyendo RSS ($ambito)...");
$articles = rss_fetch_all($ambito);

if (empty($articles)) {
    prisma_log("MAIN", "No se obtuvieron artículos. Abortando.");
    exit(1);
}

// 2. Score ALL topics with tension formula
prisma_log("MAIN", "Paso 2: Calculando tensión informativa...");
$all_temas = curador_seleccionar($articles);

if (empty($all_temas)) {
    prisma_log("MAIN", "No hay temas con suficientes artículos. Abortando.");
    exit(1);
}

prisma_log("MAIN", count($all_temas) . " temas detectados.");

// 3. Insert ALL topics into radar
$cfg = prisma_cfg();
$tz = new DateTimeZone($cfg['timezone']);
$fecha = (new DateTime('now', $tz))->format('Y-m-d');
$all_temas = radar_insertar_todos($all_temas, $ambito, $fecha);

// 4. Filter by tension threshold
$umbral = $cfg['umbral_tension'];
$min_cuad = $cfg['min_cuadrantes'];
$candidatos = array_filter($all_temas, fn($t) => $t['h_score'] >= $umbral && $t['n_cuadrantes'] >= $min_cuad);
$candidatos = array_values($candidatos);

prisma_log("MAIN", count($candidatos) . " temas superan el umbral de tensión (" . ($umbral * 100) . "%) y mínimo de cuadrantes ($min_cuad).");

if (empty($candidatos)) {
    prisma_log("MAIN", "Ningún tema supera el umbral. Radar publicado sin análisis.");
    exit(0);
}

// 5. Haiku triage (confirms + generates phrases)
prisma_log("MAIN", "Paso 3: Triage Haiku...");
$confirmados = $dry_run ? $candidatos : triage_haiku($candidatos);

// 6. Take top N for Sonnet pipeline
$to_process = array_slice($confirmados, 0, $max_temas);

prisma_log("MAIN", count($to_process) . " temas seleccionados para análisis.");

// 7. Process each topic through Sonnet pipeline
$publicados = 0;
$rechazados = 0;

foreach ($to_process as $i => $tema) {
    $seq = $i + 1;
    $article_id = prisma_gen_id($seq);

    prisma_log("MAIN", "");
    prisma_log("MAIN", "───────────────────────────────────────────────");
    prisma_log("MAIN", sprintf("Tema %d/%d: %s (H=%.0f%%)",
        $seq, count($to_process),
        mb_substr($tema['titulo_tema'], 0, 60),
        $tema['h_score'] * 100
    ));
    prisma_log("MAIN", "───────────────────────────────────────────────");

    $contexto = curador_preparar_contexto($tema);

    if ($dry_run) {
        prisma_log("MAIN", "[DRY-RUN] Saltando procesamiento.");
        continue;
    }

    try {
        $result = prisma_procesar_tema($contexto, $article_id, $ambito);
        if ($result) {
            $publicados++;
            radar_link_articulo($tema['radar_id'], $article_id);
        } else {
            $rechazados++;
        }
    } catch (Throwable $e) {
        prisma_log("MAIN", "ERROR: " . $e->getMessage());
        $rechazados++;
    }
}

// 8. Summary
prisma_log("MAIN", "");
prisma_log("MAIN", "═══════════════════════════════════════════════");
prisma_log("MAIN", sprintf("RESUMEN: %d publicados, %d rechazados de %d temas | %d en radar total",
    $publicados, $rechazados, count($to_process), count($all_temas)));
prisma_log("MAIN", "═══════════════════════════════════════════════");

exit($publicados > 0 ? 0 : 1);
```

- [ ] **Step 3: Test pipeline dry-run**

Run: `php pipeline.php --dry-run --temas 3`

Expected: Pipeline logs showing tension scores for detected topics, radar insert count, threshold filter, triage skip (dry-run), and dry-run skip for processing. Check `data/prisma.db` has radar entries.

- [ ] **Step 4: Commit**

```bash
git add lib/common.php pipeline.php
git commit -m "feat: integrate radar population and Haiku triage into pipeline"
```

---

## Task 4: UI Helpers (SVG Circle + Tension Bars)

**Files:**
- Modify: `lib/layout.php`

- [ ] **Step 1: Add quadrant color map and tension UI helpers**

Add at the end of `lib/layout.php`, before the closing `?>` (or at EOF if no closing tag):

```php
// ── Tension UI Helpers ───────────────────────────────────────────────

define('PRISMA_CUADRANTE_COLORES', [
    'izquierda-populista' => '#ff4d6d',
    'izquierda'           => '#ff6b81',
    'centro-izquierda'    => '#ff9e4d',
    'centro'              => '#f2f24a',
    'centro-derecha'      => '#4dc3ff',
    'derecha'             => '#4d9eff',
    'derecha-populista'   => '#a855f7',
]);

/**
 * Returns the color for a tension score.
 */
function tension_color(float $score): string {
    if ($score >= 0.75) return '#ff4d6d';
    if ($score >= 0.50) return '#ff9e4d';
    if ($score >= 0.25) return '#f2f24a';
    return 'rgba(255,255,255,0.3)';
}

/**
 * Renders the SVG tension circle.
 *
 * @param float $score 0.0 to 1.0
 * @param int $size Pixel size (default 36)
 * @return string HTML
 */
function render_circulo_tension(float $score, int $size = 36): string {
    $pct = round($score * 100);
    $color = tension_color($score);
    $r = round($size * 15 / 36);
    $circum = round(2 * 3.14159 * $r, 1);
    $offset = round($circum * (1 - $score), 1);
    $sw = round($size * 3 / 36, 1);
    $cx = round($size / 2);
    $fs = round($size * 0.018, 3);

    return '<div style="position:relative;width:' . $size . 'px;height:' . $size . 'px;flex-shrink:0">'
        . '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
        . '<circle cx="' . $cx . '" cy="' . $cx . '" r="' . $r . '" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="' . $sw . '"/>'
        . '<circle cx="' . $cx . '" cy="' . $cx . '" r="' . $r . '" fill="none" stroke="' . $color . '" stroke-width="' . $sw . '"'
        . ' stroke-dasharray="' . $circum . '" stroke-dashoffset="' . $offset . '"'
        . ' transform="rotate(-90 ' . $cx . ' ' . $cx . ')" stroke-linecap="round"/>'
        . '</svg>'
        . '<span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;'
        . 'font-family:Inter,Arial,sans-serif;font-size:' . $fs . 'em;font-weight:700;color:' . $color . '">'
        . $pct . '</span></div>';
}

/**
 * Renders the three tension breakdown bars.
 *
 * @param float $asimetria 0.0 to 1.0
 * @param float $divergencia 0.0 to 1.0
 * @param float $varianza 0.0 to 1.0
 * @param float $h_score For color selection
 * @return string HTML
 */
function render_barras_tension(float $asimetria, float $divergencia, float $varianza, float $h_score): string {
    $color = tension_color($h_score);
    $signals = [
        ['Asimetría cobertura', $asimetria],
        ['Divergencia léxica', $divergencia],
        ['Varianza espectro', $varianza],
    ];

    $html = '<div style="display:flex;flex-direction:column;gap:6px">';
    foreach ($signals as list($label, $val)) {
        $pct = round($val * 100);
        $html .= '<div style="display:flex;align-items:center;gap:8px">'
            . '<span style="font-family:Inter,Arial,sans-serif;font-size:0.72em;color:var(--text-faint);width:130px;flex-shrink:0">' . $label . '</span>'
            . '<div style="flex:1;height:6px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden">'
            . '<div style="width:' . $pct . '%;height:100%;background:' . $color . ';border-radius:3px"></div></div>'
            . '<span style="font-family:Inter,Arial,sans-serif;font-size:0.68em;color:var(--text-faint);width:32px;text-align:right">' . $pct . '%</span>'
            . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Generates a generic tension phrase for topics below threshold (no Haiku frase).
 */
function tension_frase_generica(float $asimetria, float $divergencia): string {
    if ($asimetria <= $divergencia) {
        return 'Cobertura equilibrada entre cuadrantes';
    }
    return 'Las fuentes coinciden en vocabulario y enfoque';
}

/**
 * Returns color for a quadrant.
 */
function cuadrante_color(string $cuadrante): string {
    return PRISMA_CUADRANTE_COLORES[$cuadrante] ?? 'var(--text-faint)';
}
```

- [ ] **Step 2: Verify helpers render**

Run: `php -r "
require 'config.php';
require 'lib/layout.php';
echo render_circulo_tension(0.85, 36);
echo render_barras_tension(0.92, 0.61, 0.45, 0.85);
"`

Expected: HTML output with SVG circle and three bar divs.

- [ ] **Step 3: Commit**

```bash
git add lib/layout.php
git commit -m "feat: add tension circle SVG and breakdown bars UI helpers"
```

---

## Task 5: Unified Index Page

**Files:**
- Rewrite: `index.php`

- [ ] **Step 1: Replace PHP header and data-fetching section (lines 1-29)**

Replace lines 1-29 of `index.php` with:

```php
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/theme.php';
require_once __DIR__ . '/lib/layout.php';

$db = prisma_db();

// Get latest date with radar data
$stmt = $db->prepare("SELECT fecha FROM radar ORDER BY fecha DESC LIMIT 1");
$stmt->execute();
$latest = $stmt->fetchColumn();

$temas = [];
$ambitos_count = [];
if ($latest) {
    $stmt = $db->prepare("SELECT * FROM radar WHERE fecha = :f ORDER BY h_score DESC");
    $stmt->execute([':f' => $latest]);
    $temas = $stmt->fetchAll();
    foreach ($temas as $t) {
        $a = $t['ambito'];
        $ambitos_count[$a] = ($ambitos_count[$a] ?? 0) + 1;
    }
}

// Also fetch analyzed articles (for backwards compat until radar is populated)
$articles = [];
if (empty($temas)) {
    $rows = $db->query('SELECT id, fecha_publicacion, ambito, titular_neutral, resumen, payload, veredicto FROM articulos ORDER BY fecha_publicacion DESC LIMIT 50')->fetchAll();
    foreach ($rows as $row) {
        $art = json_decode($row['payload'], true);
        $art['_id'] = $row['id'];
        $articles[] = $art;
        $a = $art['ambito'] ?? '';
        $ambitos_count[$a] = ($ambitos_count[$a] ?? 0) + 1;
    }
}

function format_fecha($iso) {
    $ts = strtotime($iso);
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return date('j', $ts) . ' de ' . $meses[date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

function ambito_label($ambito) {
    $map = ['españa' => 'España', 'europa' => 'Europa', 'global' => 'Global'];
    return $map[$ambito] ?? ucfirst($ambito);
}

$B = prisma_base();
$cfg = prisma_cfg();
?>
```

- [ ] **Step 2: Replace articles-list section (lines 346-374) with radar+articles dual rendering**

Replace the articles rendering block (`<?php if (empty($articles)):` through `<?php endif; ?>` at line 374) with the new dual-mode listing:

```php
<?php if (empty($temas) && empty($articles)): ?>
  <div class="empty-state">
    <h2>No hay noticias disponibles</h2>
    <p>Todavía no se han publicado artefactos. Vuelve pronto.</p>
  </div>

<?php elseif (!empty($temas)): ?>
  <?php if ($latest !== date('Y-m-d')): ?>
    <p style="font-family:'Inter',Arial,sans-serif;font-size:0.78rem;color:var(--text-faint);margin-bottom:1.5rem">
      Última actualización: <?= format_fecha($latest) ?>
    </p>
  <?php endif; ?>
  <div class="articles-list">
    <?php foreach ($temas as $tema):
      $fuentes = json_decode($tema['fuentes_json'], true) ?: [];
      $link = $tema['analizado'] && $tema['articulo_id']
          ? $B . 'articulo.php?id=' . urlencode($tema['articulo_id'])
          : $B . 'articulo.php?radar=' . urlencode($tema['id']);
      $frase = $tema['haiku_frase'] ?: tension_frase_generica($tema['h_asimetria'], $tema['h_divergencia']);
    ?>
      <a href="<?= $link ?>" class="article-card" data-ambito="<?= htmlspecialchars($tema['ambito']) ?>" style="display:flex;gap:20px;align-items:flex-start">
        <?= render_circulo_tension($tema['h_score']) ?>
        <div style="flex:1;min-width:0">
          <div class="article-meta">
            <span class="badge-ambito"><?= htmlspecialchars(ambito_label($tema['ambito'])) ?></span>
            <?php if ($tema['analizado']): ?>
              <span class="badge-apto" style="background:var(--green-bg);color:var(--green);border-color:var(--green-border)">Analizado</span>
            <?php endif; ?>
          </div>
          <h2 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.3em"><?= htmlspecialchars($tema['titulo_tema']) ?></h2>
          <p style="color:var(--text-faint);font-size:0.88rem;font-style:italic;margin:0 0 0.8em 0"><?= htmlspecialchars($frase) ?></p>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php foreach ($fuentes as $f): ?>
              <span class="postura-chip" style="border-left:3px solid <?= cuadrante_color($f['cuadrante']) ?>;padding-left:8px">
                <?= htmlspecialchars($f['medio']) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <!-- Fallback: articles mode (no radar data yet) -->
  <div class="articles-list">
    <?php foreach ($articles as $art): ?>
      <a href="<?= $B ?>articulo.php?id=<?= urlencode($art['_id']) ?>" class="article-card" data-ambito="<?= htmlspecialchars($art['ambito'] ?? '') ?>">
        <div class="article-meta">
          <span class="article-date"><?= format_fecha($art['fecha_publicacion']) ?></span>
          <span class="badge-ambito"><?= htmlspecialchars(ambito_label($art['ambito'])) ?></span>
          <?php if (($art['auditoria_moralcore']['veredicto'] ?? '') === 'APTO'): ?>
            <span class="badge-apto">Moral Core · APTO</span>
          <?php endif; ?>
        </div>
        <h2><?= htmlspecialchars($art['titular_neutral']) ?></h2>
        <p class="resumen"><?= htmlspecialchars($art['resumen']) ?></p>
        <?php if (!empty($art['mapa_posturas'])): ?>
          <div class="posturas-preview">
            <?php foreach ($art['mapa_posturas'] as $postura): ?>
              <span class="postura-chip"><?= htmlspecialchars($postura['etiqueta']) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
```

Also update the filter "Todos" count badge (line 339) to use a unified count:

Replace:
```php
<button class="filter-btn active" data-filter="all">Todos <span class="count"><?= count($articles) ?></span></button>
```
With:
```php
<button class="filter-btn active" data-filter="all">Todos <span class="count"><?= !empty($temas) ? count($temas) : count($articles) ?></span></button>
```

Note: The hero section, header, footer, CSS, and filter JS all remain **unchanged** from the current `index.php`. Only the data-fetching, filter count, and article rendering sections change.

- [ ] **Step 3: Update section header text**

Replace the section header (lines 332-335):
```html
<div class="section-header">
  <p class="eyebrow">Noticias</p>
  <h2>Hoy en Prisma</h2>
</div>
```

With:
```html
<div class="section-header">
  <p class="eyebrow">Radar informativo</p>
  <h2>Hoy en Prisma</h2>
  <p>Todos los temas detectados, ordenados por tensión informativa. Los de mayor tensión se analizan en profundidad.</p>
</div>
```

- [ ] **Step 4: Update meta description**

Replace line 37:
```html
<meta name="description" content="Las noticias políticas más relevantes del día, presentadas desde todas las posturas enfrentadas. Sin editorial, sin algoritmo, sin cámaras de eco.">
```
With:
```html
<meta name="description" content="Radar informativo: todos los temas políticos del día puntuados por tensión informativa. Los más tensos se analizan en profundidad desde todas las posturas. Sin editorial, sin cámaras de eco.">
```

- [ ] **Step 5: Test index loads**

Open in browser or run: `php -S localhost:8080` and visit `http://localhost:8080/index.php`

Expected: Page renders. If no radar data exists, falls back to articles mode showing existing published articles. If radar data exists, shows unified listing with circles and source chips.

- [ ] **Step 6: Commit**

```bash
git add index.php
git commit -m "feat: rewrite index as unified radar listing with tension circles"
```

---

## Task 6: Dual-Mode Article Page

**Files:**
- Modify: `articulo.php`

- [ ] **Step 1: Add requires and radar routing logic**

At the top of `articulo.php`, after line 3 (`require_once __DIR__ . '/lib/theme.php';`), add:

```php
require_once __DIR__ . '/lib/layout.php';
```

Then after the existing article fetch (line 16), add radar mode:

```php
$radar = null;
$radar_id = $_GET['radar'] ?? '';

if ($radar_id) {
    $db = prisma_db();
    $stmt = $db->prepare('SELECT * FROM radar WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $radar_id]);
    $radar = $stmt->fetch();

    if ($radar && $radar['articulo_id']) {
        // Redirect to analyzed article
        header('Location: ' . prisma_base() . 'articulo.php?id=' . urlencode($radar['articulo_id']), true, 301);
        exit;
    }
}
```

- [ ] **Step 2: Add tension data lookup for analyzed articles**

After the article fetch, look up the radar record to get tension scores:

```php
$tension_data = null;
if ($art) {
    $db = prisma_db();
    $stmt = $db->prepare('SELECT * FROM radar WHERE articulo_id = :aid LIMIT 1');
    $stmt->execute([':aid' => $id]);
    $tension_data = $stmt->fetch();
}
```

- [ ] **Step 3: Add tension circle + breakdown bars to analyzed article header**

In the article header section (after the `badge-ambito` around line 402), add:

```php
<?php if ($tension_data): ?>
  <?= render_circulo_tension($tension_data['h_score']) ?>
  <span style="font-family:'Inter',Arial,sans-serif;font-size:0.72rem;font-weight:700;color:<?= tension_color($tension_data['h_score']) ?>"><?= round($tension_data['h_score'] * 100) ?>% tensión</span>
<?php endif; ?>
```

After the audit bar (around line 436), add the breakdown bars:

```php
<?php if ($tension_data): ?>
  <div style="margin-bottom:2rem">
    <p class="section-label" style="margin-bottom:0.8rem">Tensión informativa</p>
    <?= render_barras_tension($tension_data['h_asimetria'], $tension_data['h_divergencia'], $tension_data['h_varianza'], $tension_data['h_score']) ?>
  </div>
<?php endif; ?>
```

- [ ] **Step 4: Add radar mode rendering**

After the `<?php else: ?>` for the 404 case (line 391), add a radar mode before it:

Add a new condition: if `$radar` is set and valid, render radar mode. This means restructuring the conditional to be: if `$art` → analyzed mode, elseif `$radar` → radar mode, else → 404.

The radar mode renders:
- Header with fecha, ambito, circle
- Explanation box with threshold and haiku_frase
- Breakdown bars
- Source list from `fuentes_json`
- Closing paragraph

- [ ] **Step 5: Test both modes**

Test analyzed mode: `articulo.php?id=2026-04-20-001` (requires a published article)
Test radar mode: `articulo.php?radar=1` (requires a radar entry)

- [ ] **Step 6: Commit**

```bash
git add articulo.php
git commit -m "feat: add dual-mode article page with tension UI in both modes"
```

---

## Task 7: Static Content Updates

**Files:**
- Modify: `manifiesto.php:958-985` (section #how, step 2)
- Modify: `manifiesto.php:1019-1026` (FAQ item)
- Modify: `manifiesto.php:96-100` (Schema.org FAQ)
- Modify: `ia.php:19-24` (process step 2)
- Modify: `ia.php:35-38` (models list)
- Modify: `fuentes.php:58-65` (selection criteria)
- Modify: `axiomas.php:9-10` (introductory note)

- [ ] **Step 1: Update manifiesto.php — step 2 in #how section**

Replace the second `<article class="step">` (lines 968-971) — "Selección neutral" — with the new content from the spec section 5.1. Use a `<details>` element for the technical paragraph.

- [ ] **Step 2: Update manifiesto.php — FAQ**

Replace the FAQ item "¿Cómo elegís qué noticias cubrir?" answer (lines 1021-1025) with the spec section 5.2 text.

- [ ] **Step 3: Update manifiesto.php — Schema.org**

Add a **new FAQ entry** to the FAQPage Schema.org block. Insert after the "¿Es gratis?" entry (around line 127, before the closing `]`):

```json
,{
  "@type": "Question",
  "name": "¿Cómo elegís qué noticias cubrir?",
  "acceptedAnswer": {
    "@type": "Answer",
    "text": "No las elegimos editorialmente. Un algoritmo calcula un índice de tensión informativa para cada tema detectado, midiendo la divergencia entre cómo lo cubren medios de distintos cuadrantes ideológicos. Los temas con mayor tensión se analizan automáticamente. El índice es público y verificable en cada tema."
  }
}
```

- [ ] **Step 4: Update ia.php — process step 2**

Replace the second `<li>` in the process list (line 21) with the spec section 5.4 text.

- [ ] **Step 5: Update ia.php — add Haiku to models**

Add a third `<li>` to the models list (after line 37) with the Haiku description from spec section 5.4.

- [ ] **Step 6: Update fuentes.php — selection criteria**

Replace lines 58-65 (the "Criterios de selección de temas" section) with the expanded version from spec section 5.5.

- [ ] **Step 7: Update axiomas.php — introductory note**

After line 9 (the intro paragraph), add a new `<p>` with the linking text from spec section 5.6.

- [ ] **Step 8: Verify all pages render**

Open each page in browser and check that the new text renders correctly with no PHP errors.

- [ ] **Step 9: Commit**

```bash
git add manifiesto.php ia.php fuentes.php axiomas.php
git commit -m "docs: update static pages to reflect tension informativa algorithm"
```

---

## Task 8: End-to-End Verification

- [ ] **Step 1: Run full pipeline dry-run**

```bash
php pipeline.php --dry-run --temas 3 --ambito españa
```

Verify: Tension scores in logs, radar entries in DB, no errors.

- [ ] **Step 2: Run full pipeline (live, 1 topic)**

```bash
php pipeline.php --temas 1 --ambito españa
```

Verify: Haiku triage call in logs, radar entries updated with `haiku_frase`, one article published, `articulo_id` linked in radar.

- [ ] **Step 3: Check index.php renders radar listing**

Open `index.php` in browser. Verify: all detected topics shown, SVG circles, badges, source links.

- [ ] **Step 4: Check articulo.php dual mode**

- Open an analyzed article: verify tension bars + Moral Core audit both visible
- Open a radar-only topic: verify explanation box + source links

- [ ] **Step 5: Final commit with any fixes**

```bash
git add -A
git commit -m "fix: end-to-end verification fixes"
```
