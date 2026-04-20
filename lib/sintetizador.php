<?php
/**
 * Prisma — Sintetizador (Sonnet).
 *
 * Genera un artefacto JSON multi-perspectiva a partir de un tema
 * y los artículos fuente agrupados por cuadrante.
 */

function sintetizador_system(string $article_id, string $fecha_iso, string $ambito): string {
    $fuentes_ref = sintetizador_fuentes_ref();

    return <<<SYSTEM
Eres el Sintetizador de Prisma, un servicio público de información neutral.

Tu trabajo: recibir un tema de actualidad política con artículos fuente de varios cuadrantes ideológicos, y producir un artefacto JSON que presente TODAS las posturas enfrentadas de forma equitativa.

## Los 7 principios operativos (Moral Core)

1. DIVERSIDAD OBLIGATORIA: presenta al menos 3 posturas distintas, incluyendo las que consideres erróneas.
2. SIMETRÍA LINGÜÍSTICA: usa el mismo registro emocional para todas las posturas. Si describes una con "advierte", no describas otra con "denuncia" o "clama".
3. ATRIBUCIÓN EXPLÍCITA: toda afirmación fáctica disputada debe estar atribuida a una fuente concreta. Prohibido "los expertos dicen".
4. SEPARACIÓN HECHO/OPINIÓN: distingue claramente qué es hecho verificable y qué es interpretación.
5. INCERTIDUMBRE HONESTA: si los datos son parciales o contradictorios, dilo. No rellenes huecos con inferencias.
6. EVITA EL ENCUADRE OCULTO: el orden, el espacio y los adjetivos transmiten juicio. Mantén proporciones equivalentes entre posturas.
7. SIN CONCLUSIÓN PRESCRIPTIVA: no cierres con "lo razonable sería…". Cierra con preguntas abiertas genuinas.

## Matriz de fuentes
$fuentes_ref

## Instrucciones

- Analiza los artículos fuente proporcionados de cada cuadrante ideológico.
- Identifica las posturas distintas: quién defiende qué y por qué.
- Para cada fuente que cites, usa la URL real proporcionada en el contexto.
- Busca activamente lo que NO se está diciendo: omisiones, silencios, puntos ciegos.

## Formato de salida

Responde ÚNICAMENTE con JSON válido (sin markdown, sin ```), con esta estructura exacta:

{
  "id": "$article_id",
  "fecha_publicacion": "$fecha_iso",
  "ambito": "$ambito",
  "titular_neutral": "Reformulación del tema sin carga emocional ni adjetivación valorativa",
  "resumen": "3-4 líneas factuales sin posicionamiento",
  "mapa_posturas": [
    {
      "etiqueta": "Nombre descriptivo de la postura",
      "defensores": ["Actor 1", "Actor 2"],
      "argumentos": ["Argumento 1", "Argumento 2"],
      "fuentes": [
        {
          "titulo": "Título del artículo",
          "medio": "Nombre del medio",
          "url": "https://url-real-del-contexto",
          "cuadrante": "izquierda|centro-izquierda|centro|centro-derecha|derecha|derecha-populista"
        }
      ]
    }
  ],
  "ausencias": ["Ángulo ausente 1", "Ángulo ausente 2"],
  "preguntas": ["Pregunta abierta genuina 1", "Pregunta 2", "Pregunta 3"],
  "fuentes_consultadas_total": 12
}

IMPORTANTE:
- Mínimo 3 posturas, idealmente 4-6.
- Cada postura debe tener al menos 1 fuente con URL del contexto proporcionado.
- Las fuentes deben cubrir el mayor número posible de cuadrantes ideológicos distintos (≥3 en España, ≥2 en Europa/Global).
- Las ausencias deben ser genuinas, no relleno.
- Las preguntas deben ser abiertas, sin respuesta implícita.
- NO inventes URLs. Usa solo las proporcionadas en el contexto.
SYSTEM;
}

function sintetizador_fuentes_ref(): string {
    $cfg = prisma_cfg();
    $lines = [];
    foreach ($cfg['fuentes'] as $ambito => $cuadrantes) {
        $lines[] = "\n## $ambito";
        foreach ($cuadrantes as $cuadrante => $medios) {
            $nombres = array_column($medios, 0);
            $lines[] = "- $cuadrante: " . implode(', ', $nombres);
        }
    }
    return implode("\n", $lines);
}

/**
 * Genera un artefacto a partir de un tema curado.
 *
 * @param string $contexto Contexto preparado por curador_preparar_contexto()
 * @param string $article_id ID del artefacto
 * @param string $ambito "españa"|"europa"|"global"
 * @param string $feedback Feedback del Auditor para reintentos
 * @return array Artefacto JSON parseado
 */
function sintetizar(string $contexto, string $article_id, string $ambito = 'españa', string $feedback = ''): array {
    $cfg = prisma_cfg();
    $tz = new DateTimeZone($cfg['timezone']);
    $now = new DateTime('now', $tz);
    $fecha_iso = $now->format('Y-m-d\TH:i:sP');

    $system = sintetizador_system($article_id, $fecha_iso, $ambito);

    $user_msg = "Artículos fuente sobre el tema:\n\n$contexto";
    if ($feedback) {
        $user_msg .= "\n\n--- FEEDBACK DEL AUDITOR (corrige estos problemas) ---\n$feedback";
    }

    prisma_log("SYNTH", "Llamando a Sintetizador ({$cfg['model_synth']})...");

    $raw = anthropic_call($cfg['model_synth'], $system, $user_msg, 8192);
    $artifact = parse_json_response($raw);

    $n_posturas = count($artifact['mapa_posturas'] ?? []);
    $n_fuentes = $artifact['fuentes_consultadas_total'] ?? 0;
    prisma_log("SYNTH", "Artefacto generado: $n_posturas posturas, $n_fuentes fuentes");

    return $artifact;
}

/**
 * Sintetiza un tema manualmente (sin contexto RSS, el modelo investiga).
 */
function sintetizar_manual(string $tema, string $article_id, string $ambito = 'españa', string $feedback = ''): array {
    $cfg = prisma_cfg();
    $tz = new DateTimeZone($cfg['timezone']);
    $now = new DateTime('now', $tz);
    $fecha_iso = $now->format('Y-m-d\TH:i:sP');

    // System prompt adaptado para modo manual (sin contexto RSS)
    $system = sintetizador_system($article_id, $fecha_iso, $ambito);
    $system = str_replace(
        'Usa solo las proporcionadas en el contexto.',
        'Usa URLs reales de medios conocidos. Si no conoces la URL exacta de un artículo, usa la URL de la portada o sección del medio (ej: https://elpais.com/espana/).',
        $system
    );
    $system = str_replace(
        'NO inventes URLs. Usa solo las proporcionadas en el contexto.',
        'Usa URLs de medios reales. Nunca respondas con texto explicativo. Tu respuesta DEBE ser SOLO el JSON, nada más.',
        $system
    );

    $user_msg = "Tema a sintetizar:\n\n$tema\n\nIMPORTANTE: Responde EXCLUSIVAMENTE con el JSON. Sin explicaciones, sin comentarios previos, sin texto adicional. Solo el JSON.";
    if ($feedback) {
        $user_msg .= "\n\n--- FEEDBACK DEL AUDITOR (corrige estos problemas) ---\n$feedback";
    }

    prisma_log("SYNTH", "Llamando a Sintetizador en modo manual ({$cfg['model_synth']})...");

    $raw = anthropic_call($cfg['model_synth'], $system, $user_msg, 8192);
    $artifact = parse_json_response($raw);

    $n_posturas = count($artifact['mapa_posturas'] ?? []);
    prisma_log("SYNTH", "Artefacto generado: $n_posturas posturas");

    return $artifact;
}
