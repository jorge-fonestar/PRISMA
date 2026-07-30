<?php
/**
 * PolarPrisma — Sintetizador (Sonnet).
 *
 * Genera un artefacto JSON multi-perspectiva a partir de un tema y los
 * artículos fuente agrupados por cuadrante. Verifica los datos centrales
 * contra fuentes primarias con la herramienta web_search.
 *
 * El system prompt es ESTÁTICO (id/fecha/ámbito van en el mensaje de usuario o
 * los impone el servidor) para que el prompt caching sea efectivo.
 */

function sintetizador_system(): string {
    $fuentes_ref = sintetizador_fuentes_ref();

    return <<<SYSTEM
Eres el Sintetizador de PolarPrisma, un servicio público de información neutral.

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

## Verificación factual con fuentes primarias (herramienta web_search — ÚSALA CON PARSIMONIA)

La búsqueda web es CARA. Úsala SOLO si, al analizar el tema, se da alguno de estos dos casos:
(a) las fuentes se CONTRADICEN en un dato concreto (una cifra, fecha, magnitud o autoría distinta para el mismo hecho), o
(b) hay una cifra o dato llamativo y CENTRAL atribuido a UNA SOLA parte interesada (gobierno, partido, empresa) y presentado como hecho establecido.
Si las fuentes coinciden en los hechos y solo difieren en la INTERPRETACIÓN o el encuadre, NO busques: no hay nada que contrastar. Deja "verificaciones" y "discrepancias" como listas vacías.
Cuando sí busques (máximo 2 búsquedas, solo lo esencial): prioriza fuentes primarias — instituciones españolas (ine.es, boe.es, dominios .gob.es), europeas (europa.eu, eurostat), luego organismos internacionales oficiales.
- Verifica SOLO hechos comprobables, nunca opiniones ni interpretaciones.
- Si un dato NO se confirma, o la fuente primaria lo contradice o matiza, NO lo presentes como hecho establecido: recógelo en "discrepancias".
- Registra lo comprobado en "verificaciones", con la fuente primaria y su URL real.

## Instrucciones

- Analiza los artículos fuente proporcionados de cada cuadrante ideológico.
- Identifica las posturas distintas: quién defiende qué y por qué.
- Para cada fuente del contexto que cites, usa su URL real.
- Busca activamente lo que NO se está diciendo: omisiones, silencios, puntos ciegos.
- El id, la fecha y el ámbito los fija el servidor: en el JSON ponlos como indica el esquema (null); no te preocupes por su valor.

## Formato de salida

Puede haber búsquedas web antes, pero tu respuesta debe TERMINAR con el JSON del artefacto (de { a }), sin markdown ni texto después.
Estructura exacta:

{
  "id": null,
  "fecha_publicacion": null,
  "ambito": null,
  "titular_neutral": "Reformulación del tema sin carga emocional ni adjetivación valorativa",
  "resumen": "3-4 líneas factuales sin posicionamiento",
  "contexto": ["Apunte factual de fondo que ayuda a entender la noticia: definición de un término, fecha clave, comparador o antecedente NO disputado", "Otro apunte factual de fondo"],
  "mapa_posturas": [
    {
      "etiqueta": "Nombre descriptivo de la postura",
      "defensores": ["Actor 1", "Actor 2"],
      "argumentos": ["Argumento 1", "Argumento 2"],
      "cita_literal": "Cita textual breve (≤15 palabras) de una de sus fuentes que capte su encuadre, entre comillas; o null si ninguna es adecuada",
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
  "verificaciones": [
    {
      "afirmacion": "Dato o hecho concreto contrastado",
      "veredicto": "confirmado|matizado|no_verificable",
      "fuente": "Organismo o fuente primaria",
      "url": "https://url-real-de-la-fuente-primaria"
    }
  ],
  "discrepancias": ["Dato que los medios dan por bueno pero la fuente primaria no confirma o matiza"],
  "ausencias": ["Ángulo ausente 1", "Ángulo ausente 2"],
  "preguntas": ["Pregunta abierta genuina 1", "Pregunta 2", "Pregunta 3"],
  "fuentes_consultadas_total": 12,
  "indice_polarizacion": 55,
  "polarizacion_nota": "Justificación en una frase del índice asignado"
}

## Reparto entre "contexto" y "preguntas" (IMPORTANTE)

- "contexto": de 2 a 5 apuntes DE HECHO que ayuden a entender la noticia — definiciones de términos, fechas clave, comparadores, antecedentes NO disputados. Deben ser comprobables y neutrales: NO insinúes causa, culpa ni valoración. Un dato dudoso o de una sola parte NO va aquí (va a "verificaciones"/"discrepancias").
- "preguntas": SOLO cuestiones genuinamente abiertas, DIFÍCILES DE CONTRASTAR y que llevan a juicios personales (causalidad en disputa, disyuntivas de valores o prioridades, marcos en pugna).
- REGLA DE ORO: si una pregunta tiene una respuesta factual cerrada (un dato, una fecha), NO es pregunta para pensar → conviértela en un apunte de "contexto". Ninguna pregunta debe tener respuesta objetiva evidente.

## Índice de polarización (revisión con contexto completo)

Ahora que tienes el texto y todas las posturas, reevalúa cuánto divergen REALMENTE los relatos y asigna "indice_polarizacion" (0-100). Este índice sustituye al de detección inicial (que solo miró titulares). Rúbrica:
- 0-20: todas las fuentes cuentan lo mismo; diferencias solo terminológicas o de estilo, sin divergencia editorial.
- 21-40: diferencias de énfasis o de selección de datos, sin oposición de fondo.
- 41-60: marcos distintos conviven; discrepancia interpretativa moderada.
- 61-80: posturas claramente enfrentadas sobre los mismos hechos.
- 81-100: relatos opuestos, hechos en disputa y atribuciones de responsabilidad contradictorias.
No confundas variación de vocabulario con divergencia de encuadre: si el hecho narrado es el mismo y solo cambian las palabras, el índice es bajo.
"polarizacion_nota": una frase neutra que justifique el valor.

IMPORTANTE:
- Mínimo 3 posturas, idealmente 4-6.
- Cada postura debe tener al menos 1 fuente con URL del contexto + su cita_literal (o null).
- Las fuentes deben cubrir el mayor número posible de cuadrantes ideológicos distintos (≥3 en España, ≥2 en Europa/Global; el ámbito se indica en el mensaje).
- verificaciones: como mucho 5; usa [] si no hay nada verificable. discrepancias: [] si no hay.
- Las ausencias deben ser genuinas, no relleno. Las preguntas, abiertas.
- NO inventes URLs de las fuentes del contexto. Las URLs de web_search sí son reales y van en "verificaciones".
- resumen: máximo 80 palabras. contexto: 2-5 apuntes de ≤30 palabras cada uno. argumentos: máximo 50 palabras. ausencias: máximo 30. cita_literal: máximo 15.
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
 * Definición de la tool web_search (server-side de Anthropic).
 */
function sintetizador_web_search_tool(): array {
    $cfg = prisma_cfg();
    $max = isset($cfg['synth_web_search_max']) ? (int)$cfg['synth_web_search_max'] : 4;
    return array(array(
        'type'     => 'web_search_20250305',
        'name'     => 'web_search',
        'max_uses' => $max,
    ));
}

/**
 * Construye los prompts de síntesis sin llamar a la API.
 * Compartido entre la vía síncrona (sintetizar) y la Batches API.
 *
 * @return array ['system' => string, 'user_msg' => string]
 */
function sintetizador_build(string $contexto, string $article_id, string $ambito = 'españa', string $feedback = ''): array {
    $system = sintetizador_system();

    $user_msg = "Ámbito: $ambito\n\nArtículos fuente sobre el tema:\n\n$contexto";
    if ($feedback) {
        $user_msg .= "\n\n--- FEEDBACK DEL AUDITOR (corrige estos problemas) ---\n$feedback";
    }

    return ['system' => $system, 'user_msg' => $user_msg];
}

function sintetizar(string $contexto, string $article_id, string $ambito = 'españa', string $feedback = ''): array {
    $cfg = prisma_cfg();
    $req = sintetizador_build($contexto, $article_id, $ambito, $feedback);

    prisma_log("SYNTH", "Llamando a Sintetizador ({$cfg['model_synth']})...");

    $max_tok = $cfg['max_tokens_pipeline'] ?? 4096;
    $tools = !empty($cfg['synth_web_search']) ? sintetizador_web_search_tool() : array();
    // Con web_search el modelo gasta tokens pensando + buscando antes del JSON final:
    // dale margen para que no se quede sin presupuesto a mitad (daría "Respuesta inesperada").
    if (!empty($tools)) $max_tok = max($max_tok, 16000);
    $cache = !empty($cfg['synth_prompt_cache']);
    $raw = anthropic_call($cfg['model_synth'], $req['system'], $req['user_msg'], $max_tok, '', $tools, $cache);
    $artifact = parse_json_response($raw);

    $n_posturas = count($artifact['mapa_posturas'] ?? []);
    $n_fuentes = $artifact['fuentes_consultadas_total'] ?? 0;
    $n_verif = count($artifact['verificaciones'] ?? []);
    prisma_log("SYNTH", "Artefacto generado: $n_posturas posturas, $n_fuentes fuentes, $n_verif verificaciones");

    return $artifact;
}

/**
 * Sintetiza un tema manualmente (sin contexto RSS: el modelo busca con web_search).
 */
function sintetizar_manual(string $tema, string $article_id, string $ambito = 'españa', string $feedback = ''): array {
    $cfg = prisma_cfg();

    $system = sintetizador_system();

    $user_msg = "Ámbito: $ambito\n\nTema a sintetizar (SIN artículos del contexto): $tema\n\n"
        . "Como no hay artículos del contexto, usa la herramienta web_search para localizar cobertura real "
        . "de distintos cuadrantes ideológicos y cítala con sus URLs reales en cada postura. "
        . "Responde EXCLUSIVAMENTE con el JSON del artefacto.";
    if ($feedback) {
        $user_msg .= "\n\n--- FEEDBACK DEL AUDITOR (corrige estos problemas) ---\n$feedback";
    }

    prisma_log("SYNTH", "Llamando a Sintetizador en modo manual ({$cfg['model_synth']})...");

    $max_tok = $cfg['max_tokens_pipeline'] ?? 4096;
    // En modo manual, web_search es imprescindible (no hay contexto): se fuerza.
    $tools = sintetizador_web_search_tool();
    $max_tok = max($max_tok, 16000);   // margen para buscar + emitir el JSON final
    $cache = !empty($cfg['synth_prompt_cache']);
    $raw = anthropic_call($cfg['model_synth'], $system, $user_msg, $max_tok, '', $tools, $cache);
    $artifact = parse_json_response($raw);

    $n_posturas = count($artifact['mapa_posturas'] ?? []);
    prisma_log("SYNTH", "Artefacto generado: $n_posturas posturas");

    return $artifact;
}
