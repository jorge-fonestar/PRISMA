<?php
/**
 * PolarPrisma — Regenera resumen_neutral de temas ya en el radar.
 *
 * El resumen se muestra JUSTO DEBAJO del titular, así que no debe repetirlo:
 * debe ser la "segunda frase" que aporta el dato que el titular no dice.
 * Este script reescribe los resúmenes de una ventana de días usando el titular
 * y las entradillas ya guardadas en fuentes_json (mismo criterio que el gate).
 *
 * Uso (CLI, dentro del contenedor):
 *   php regen_resumenes.php --dias 10            # regenera y guarda
 *   php regen_resumenes.php --dias 10 --dry-run  # solo muestra antes/después
 *   php regen_resumenes.php --dias 10 --cap 80   # límite de temas a procesar
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/curador.php';
require_once __DIR__ . '/lib/anthropic.php';
require_once __DIR__ . '/db.php';

// --- args ---
$dias = 10; $dry = false; $cap = 200;
foreach ($argv as $i => $a) {
    if ($a === '--dias' && isset($argv[$i + 1])) $dias = (int)$argv[$i + 1];
    if ($a === '--cap' && isset($argv[$i + 1])) $cap = (int)$argv[$i + 1];
    if ($a === '--dry-run') $dry = true;
}

$cfg = prisma_cfg();
$model = $cfg['model_triage'];
$db = prisma_db();

$fecha_min = date('Y-m-d', strtotime("-{$dias} days"));
$stmt = $db->prepare("SELECT id, titulo_tema, fuentes_json, resumen_neutral
    FROM radar
    WHERE fecha >= :fmin AND resumen_neutral IS NOT NULL AND resumen_neutral != ''
    ORDER BY fecha DESC, h_score DESC");
$stmt->execute([':fmin' => $fecha_min]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$system = 'Eres un redactor de teletipos neutrales. Recibes el TITULAR de una noticia y las ENTRADILLAS de varios medios que la cubren. Devuelves UNA sola frase de resumen que se mostrará JUSTO DEBAJO del titular, que el lector YA ha visto.

REGLAS:
- La frase NO puede repetir el titular. PROHIBIDO empezar reformulando el sujeto+verbo del titular. Ejemplo: si el titular es «El PIB creció un 0,7% en el segundo trimestre», NO escribas «El PIB español crece un 0,7%…»; escribe lo que el titular NO dice, p. ej. «Lo impulsan el consumo de los hogares y la inversión; el comercio exterior resta al avance.».
- Aporta el dato MÁS informativo que falta en el titular: cifra exacta, causa, consecuencia, trasfondo, quién reacciona o qué está en juego, tomándolo de las entradillas. Debe leerse como la SEGUNDA frase de la noticia.
- Máximo 25 palabras. En español. Sin adjetivación valorativa ni posicionamiento. Sin comillas alrededor.
- Si las entradillas no aportan NADA más allá del titular, responde exactamente: NULL

Responde SOLO con la frase (o NULL), sin comillas, sin JSON, sin explicaciones.';

$updateStmt = $db->prepare('UPDATE radar SET resumen_neutral = :r WHERE id = :id');

$n = 0; $cambiados = 0; $anulados = 0;
foreach ($rows as $row) {
    if ($n >= $cap) break;

    $fuentes = json_decode($row['fuentes_json'], true);
    if (!is_array($fuentes)) continue;

    // Bloques distintos + líneas de entradilla (mismo criterio que el gate)
    $bloques = [];
    $lineas = [];
    foreach ($fuentes as $f) {
        $c = $f['cuadrante'] ?? '';
        if (in_array($c, PRISMA_GRUPO_IZQ)) $b = 'izquierda';
        elseif (in_array($c, PRISMA_GRUPO_DER)) $b = 'derecha';
        else $b = 'centro';
        $bloques[$b] = true;
        $desc = trim((string)($f['descripcion'] ?? ''));
        if ($desc !== '') {
            $desc = trim(mb_substr(strip_tags($desc), 0, 220, 'UTF-8'));
            $lineas[] = "- [$b · " . ($f['medio'] ?? '?') . "] $desc";
        }
    }
    // Sin ≥2 bloques o sin entradillas: no hay material para mejorar → se deja igual
    if (count($bloques) < 2 || empty($lineas)) continue;

    $n++;
    $user = "TITULAR: {$row['titulo_tema']}\n\nENTRADILLAS:\n" . implode("\n", array_slice($lineas, 0, 8));

    try {
        $raw = trim(anthropic_call($model, $system, $user, 300));
    } catch (Exception $e) {
        fwrite(STDERR, "#{$row['id']} error: " . $e->getMessage() . "\n");
        continue;
    }

    // Normaliza salida
    $nuevo = trim($raw);
    $nuevo = trim($nuevo, "\"'` ");
    $es_null = ($nuevo === '' || strtoupper($nuevo) === 'NULL');
    $final = $es_null ? null : $nuevo;

    echo "#{$row['id']}  {$row['titulo_tema']}\n";
    echo "  ANTES: {$row['resumen_neutral']}\n";
    echo "  AHORA: " . ($final ?? '(null)') . "\n\n";

    if (!$dry) {
        $updateStmt->execute([':r' => $final, ':id' => $row['id']]);
    }
    if ($es_null) $anulados++; else $cambiados++;
}

echo "== " . ($dry ? "DRY-RUN" : "APLICADO") . ": $n temas procesados, $cambiados reescritos, $anulados anulados (ventana {$dias}d) ==\n";
