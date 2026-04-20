<?php
/**
 * Prisma — Auditor independiente (Opus).
 *
 * Evalúa un artefacto contra los 11 axiomas del estándar Moral Core.
 * Contexto completamente separado del Sintetizador.
 */

define('AUDITOR_SYSTEM', <<<'SYSTEM'
Eres el Auditor independiente de Prisma, un servicio público de información neutral.

Tu trabajo: evaluar un artefacto generado contra los 11 axiomas del estándar Moral Core.
Eres completamente independiente del Sintetizador. Tu único compromiso es con la calidad y neutralidad.

## Los 11 axiomas

A1 — Pluralidad de posturas: ¿El artefacto identifica ≥3 posturas distintas de forma explícita?
A2 — Pluralidad de fuentes: ¿Se citan fuentes de múltiples cuadrantes ideológicos distintos (≥3 en España, ≥2 en Europa/Global)?
A3 — Simetría de extensión: ¿Ninguna postura ocupa >50% del espacio total ni <15%?
A4 — Simetría léxica: ¿El lenguaje usado para cada postura es equivalente en carga emocional?
A5 — Atribución verificable: ¿Toda afirmación fáctica disputada tiene fuente concreta enlazada?
A6 — Distinción hecho/opinión: ¿Los elementos presentados como hechos son verificables y los presentados como posturas son opiniones?
A7 — Ausencia de conclusión prescriptiva: ¿El texto evita recomendar qué pensar o hacer?
A8 — Transparencia de límites: ¿Se mencionan explícitamente los puntos de incertidumbre o datos faltantes?
A9 — Ausencia de omisión crítica: ¿Hay alguna postura mayoritaria en el debate público que NO esté recogida?
A10 — Coherencia con fuentes: ¿Cada postura se corresponde con lo que las fuentes citadas realmente dicen? (anti-alucinación)
A11 — Ausencia de sesgo geopolítico de bloque: ¿El artefacto evita favorecer narrativas de un bloque geopolítico específico?

## Reglas de puntuación

- Evalúa CADA axioma como true (pasa) o false (no pasa).
- APTO: ≥10 de 11 axiomas pasan.
- REVISIÓN: 8-9 de 11 pasan.
- RECHAZO: <8 de 11 pasan.
- puntuacion = número de axiomas que pasan / 11.

## Formato de salida

Responde ÚNICAMENTE con JSON válido (sin markdown, sin ```):

{
  "veredicto": "APTO|REVISIÓN|RECHAZO",
  "puntuacion": 0.91,
  "axiomas_detalle": {
    "A1": true, "A2": true, "A3": true, "A4": true, "A5": true,
    "A6": true, "A7": true, "A8": true, "A9": true, "A10": true, "A11": true
  },
  "evidencia": {
    "A1": "Breve justificación",
    "A4": "Breve justificación si falla"
  },
  "recomendacion": "Si hay axiomas que fallan, instrucción concreta para corregir",
  "version_estandar": "MC-1.0"
}

Sé riguroso. No seas complaciente. Si un axioma no se cumple claramente, márcalo como false.
SYSTEM);

/**
 * Audita un artefacto contra los 11 axiomas Moral Core.
 *
 * @param array $artifact Artefacto JSON generado por el Sintetizador
 * @param string $ambito Ámbito para contextualizar la evaluación
 * @return array Resultado de la auditoría
 */
function auditar(array $artifact, string $ambito = ''): array {
    $cfg = PRISMA_CONFIG;

    prisma_log("AUDIT", "Llamando al Auditor ({$cfg['model_audit']})...");

    // Contexto sobre limitaciones de fuentes según ámbito
    $context = '';
    if ($ambito && $ambito !== 'españa') {
        $cuadrantes = array_keys($cfg['fuentes'][$ambito] ?? []);
        $medios = [];
        foreach ($cfg['fuentes'][$ambito] ?? [] as $ms) {
            foreach ($ms as $m) $medios[] = $m[0];
        }
        $context = "\n\nCONTEXTO IMPORTANTE: Este artefacto es del ámbito '$ambito'. "
            . "Las fuentes RSS disponibles para este ámbito son limitadas: "
            . implode(', ', $medios) . " (cuadrantes: " . implode(', ', $cuadrantes) . "). "
            . "Evalúa A2 y A11 en proporción a las fuentes disponibles, no exijas cuadrantes "
            . "que no existen en la matriz. El criterio es: ¿se ha aprovechado la diversidad "
            . "disponible al máximo?";
    }

    $user_msg = "Evalúa el siguiente artefacto Prisma:\n\n"
        . json_encode($artifact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        . $context;

    $raw = anthropic_call($cfg['model_audit'], AUDITOR_SYSTEM, $user_msg, 4096);
    $audit = parse_json_response($raw);

    $passed = 0;
    $total = 0;
    foreach ($audit['axiomas_detalle'] ?? [] as $v) {
        $total++;
        if ($v) $passed++;
    }

    prisma_log("AUDIT", sprintf(
        "%s — %d/%d axiomas (%.0f%%)",
        $audit['veredicto'] ?? '???',
        $passed, $total,
        ($audit['puntuacion'] ?? 0) * 100
    ));

    if (!empty($audit['recomendacion'])) {
        prisma_log("AUDIT", "Recomendación: " . $audit['recomendacion']);
    }

    return $audit;
}

/**
 * Construye feedback para el Sintetizador a partir de una auditoría fallida.
 */
function auditor_build_feedback(array $audit): string {
    $lines = [];
    foreach ($audit['axiomas_detalle'] ?? [] as $axiom => $passed) {
        if (!$passed) {
            $ev = $audit['evidencia'][$axiom] ?? '';
            $lines[] = "- $axiom FALLA: $ev";
        }
    }
    if (!empty($audit['recomendacion'])) {
        $lines[] = "\nInstrucción: " . $audit['recomendacion'];
    }
    return implode("\n", $lines);
}
