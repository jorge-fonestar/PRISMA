<?php
/**
 * Prisma — Verification script for scoring v2 functions.
 *
 * Runs all test cases from DISEÑO_POLARIZACION.md section 4 table.
 * Usage: php test_scoring.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/curador.php';
require_once __DIR__ . '/lib/scoring.php';
require_once __DIR__ . '/lib/diccionarios.php';

$pass = 0;
$fail = 0;

function assert_near($label, $expected, $actual, $tolerance = 0.02) {
    global $pass, $fail;
    if (abs($expected - $actual) <= $tolerance) {
        echo "  PASS: $label = $actual (expected $expected)\n";
        $pass++;
    } else {
        echo "  FAIL: $label = $actual (expected $expected, diff=" . abs($expected - $actual) . ")\n";
        $fail++;
    }
}

function assert_eq($label, $expected, $actual) {
    global $pass, $fail;
    if ($expected === $actual) {
        echo "  PASS: $label = " . var_export($actual, true) . "\n";
        $pass++;
    } else {
        echo "  FAIL: $label = " . var_export($actual, true) . " (expected " . var_export($expected, true) . ")\n";
        $fail++;
    }
}

// ── Structural signals ──

echo "\n=== calcular_cobertura_mutua ===\n";

// 1 bloque → 0.0
$arts_1bloc = array(
    array('cuadrante' => 'centro'),
    array('cuadrante' => 'centro'),
);
assert_near("1 bloc", 0.0, calcular_cobertura_mutua($arts_1bloc));

// 3 bloques: izq=3, centro=2, der=4 → 2/4 × 1.0 = 0.5
$arts_3bloc = array(
    array('cuadrante' => 'izquierda'), array('cuadrante' => 'izquierda'), array('cuadrante' => 'centro-izquierda'),
    array('cuadrante' => 'centro'), array('cuadrante' => 'centro'),
    array('cuadrante' => 'derecha'), array('cuadrante' => 'derecha'), array('cuadrante' => 'centro-derecha'), array('cuadrante' => 'derecha-populista'),
);
assert_near("3 blocs (3,2,4)", 0.5, calcular_cobertura_mutua($arts_3bloc));

// 2 bloques: izq=3, der=2 → 2/3 × 0.7 = 0.4667
$arts_2bloc = array(
    array('cuadrante' => 'izquierda'), array('cuadrante' => 'izquierda'), array('cuadrante' => 'centro-izquierda'),
    array('cuadrante' => 'derecha'), array('cuadrante' => 'centro-derecha'),
);
assert_near("2 blocs (3,0,2)", 0.4667, calcular_cobertura_mutua($arts_2bloc));

echo "\n=== calcular_silencio ===\n";

assert_near("3 blocs active", 0.0, calcular_silencio($arts_3bloc));
assert_near("2 blocs active", 0.5, calcular_silencio($arts_2bloc));
assert_near("1 bloc active", 1.0, calcular_silencio($arts_1bloc));

echo "\n=== normalizar_framing (Mapeo B) ===\n";

assert_near("fd=0", 0.0, normalizar_framing(0));
assert_near("fd=1", 0.15, normalizar_framing(1));
assert_near("fd=2", 0.50, normalizar_framing(2));
assert_near("fd=3", 1.00, normalizar_framing(3));
assert_eq("fd=null", null, normalizar_framing(null));

// ── Full H-score v2 ──

echo "\n=== calcular_h_score_v2 (spec scenarios) ===\n";

// Feijóo: 3 bloq, fd=2, alta → 0.50
$r = calcular_h_score_v2(array('h_cob'=>0.50, 'h_sil'=>0.0, 'fd'=>2, 'relevancia'=>'alta', 'lista_positiva'=>true));
assert_near("Feijoo", 0.50, $r['h_score']);

// Hungría: 3 bloq, fd=3 → 0.82
$r = calcular_h_score_v2(array('h_cob'=>0.60, 'h_sil'=>0.0, 'fd'=>3, 'relevancia'=>'alta', 'lista_positiva'=>false));
assert_near("Hungria", 0.82, $r['h_score']);

// JD Vance: 3 bloq, fd=2 → 0.48
$r = calcular_h_score_v2(array('h_cob'=>0.45, 'h_sil'=>0.0, 'fd'=>2, 'relevancia'=>'alta', 'lista_positiva'=>false));
assert_near("JD Vance", 0.48, $r['h_score']);

// Oesía: 2 bloq, fd=2, alta, sil=0.5 → 0.51
$r = calcular_h_score_v2(array('h_cob'=>0.35, 'h_sil'=>0.5, 'fd'=>2, 'relevancia'=>'alta', 'lista_positiva'=>false));
assert_near("Oesia", 0.51, $r['h_score']);

// Nestlé: 2 bloq, fd=2, alta, sil=0.5 → 0.53
$r = calcular_h_score_v2(array('h_cob'=>0.40, 'h_sil'=>0.5, 'fd'=>2, 'relevancia'=>'alta', 'lista_positiva'=>false));
assert_near("Nestle", 0.53, $r['h_score']);

// Tema tibio: 3 bloq, fd=1 → 0.28
$r = calcular_h_score_v2(array('h_cob'=>0.70, 'h_sil'=>0.0, 'fd'=>1, 'relevancia'=>'alta', 'lista_positiva'=>false));
assert_near("Tema tibio", 0.28, $r['h_score']);

// Woody Allen fd=2, atajo: baja→media, sil=0.5 → 0.45
$r = calcular_h_score_v2(array('h_cob'=>0.30, 'h_sil'=>0.5, 'fd'=>2, 'relevancia'=>'baja', 'lista_positiva'=>false));
assert_near("Woody Allen fd=2", 0.45, $r['h_score']);
assert_eq("Woody Allen override", 'media', $r['relevancia_final']);

// Gate: relevancia=baja, fd=0 → 0.0
$r = calcular_h_score_v2(array('h_cob'=>0.30, 'h_sil'=>0.5, 'fd'=>0, 'relevancia'=>'baja', 'lista_positiva'=>false));
assert_near("Gated baja", 0.0, $r['h_score']);

// Gate: relevancia=indeterminada → 0.0
$r = calcular_h_score_v2(array('h_cob'=>0.50, 'h_sil'=>0.0, 'fd'=>null, 'relevancia'=>'indeterminada', 'lista_positiva'=>false));
assert_near("Indeterminada", 0.0, $r['h_score']);

// Anomaly: lista_positiva + baja
$r = calcular_h_score_v2(array('h_cob'=>0.40, 'h_sil'=>0.0, 'fd'=>1, 'relevancia'=>'baja', 'lista_positiva'=>true));
assert_eq("Anomaly political low", 'ANOMALY_POLITICAL_LOW', $r['anomalies'][0]['tipo']);

// ── Diccionarios ──

echo "\n=== Lista negativa ===\n";

assert_eq("Bonoloto match", true, aplicar_lista_negativa("Comprobar Bonoloto: resultado y número premiado hoy")['descartado']);
assert_eq("Mutua Madrid Open match", true, aplicar_lista_negativa("Mutua Madrid Open 2026: dónde ver por televisión")['descartado']);
assert_eq("Karol G match", true, aplicar_lista_negativa("Karol G actuará en Barcelona — concierto 2027")['descartado']);
assert_eq("OpenAI NO match", false, aplicar_lista_negativa("Investigación penal contra OpenAI por ChatGPT")['descartado']);
assert_eq("Feijóo NO match", false, aplicar_lista_negativa("Feijóo presenta su decálogo para infraestructuras")['descartado']);
assert_eq("Liga derechos NO match", false, aplicar_lista_negativa("La liga de derechos humanos denuncia abusos")['descartado']);

echo "\n=== Lista positiva ===\n";

assert_eq("Feijóo has political", true, detectar_lista_positiva("Feijóo presenta su decálogo"));
assert_eq("Congreso has political", true, detectar_lista_positiva("El Congreso debate la reforma"));
assert_eq("Loro kea no political", false, detectar_lista_positiva("Un loro kea con discapacidad"));

// ── Summary ──

echo "\n" . str_repeat('=', 50) . "\n";
echo "Results: $pass PASS, $fail FAIL\n";
echo str_repeat('=', 50) . "\n";
exit($fail > 0 ? 1 : 0);
