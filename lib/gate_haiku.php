<?php
/**
 * PolarPrisma — Gate Haiku: fusión semántica + clasificación en una llamada.
 *
 * Con el escaneo diario (una pasada al día), una única llamada Haiku recibe los
 * clusters preliminares (pre-agrupados por Jaccard determinista) y:
 *   1) AGRUPA los que son la misma noticia (fusión semántica robusta, resuelve la
 *      fragmentación que el Jaccard no ve: "indulta"/"concede el indulto", acentos…).
 *   2) CLASIFICA cada grupo resultante (relevancia, dominio, framing_divergence
 *      con evidencia, resumen_neutral).
 * El re-scoring estructural (cobertura_mutua, silencio, H-score) lo hace escanear.php
 * sobre los artículos ya fusionados.
 */

require_once __DIR__ . '/anthropic.php';
require_once __DIR__ . '/scoring.php';

/**
 * Valid enum values for Haiku output validation.
 */
define('PRISMA_RELEVANCIA_VALID', array('alta', 'media', 'baja', 'descartar'));
define('PRISMA_DOMINIO_VALID', array(
    'politica_institucional', 'economia_trabajo', 'sanidad_ciencia',
    'tecnologia_regulacion', 'cultura_identidad', 'medio_ambiente',
    'educacion', 'inmigracion', 'internacional', 'otros'
));

/**
 * Fusiona y clasifica clusters preliminares en una sola llamada Haiku.
 *
 * @param array $clusters Cada uno: ['cluster_id'=>int, 'articulos'=>[...], 'contains_political_actor'=>bool]
 * @return array Lista de grupos, cada uno:
 *   ['miembros'=>[cluster_id,...], 'articulos'=>[...], 'relevancia', 'dominio',
 *    'framing_divergence', 'framing_evidence', 'resumen_neutral', 'anomalies']
 */
function gate_haiku_agrupar_clasificar(array $clusters): array {
    $cfg = prisma_cfg();
    if (empty($clusters)) return array();

    try {
        anthropic_check_budget();
    } catch (Exception $e) {
        prisma_log("GATE", "Budget agotado — sin gate Haiku: " . $e->getMessage());
        return gate_haiku_grupos_fallback($clusters);
    }

    $incluir_desc = !empty($cfg['gate_incluir_descripcion']);
    $desc_max = isset($cfg['gate_desc_max_chars']) ? (int)$cfg['gate_desc_max_chars'] : 160;

    // Input compacto: por cluster, cuadrantes + hasta 3 muestras etiquetadas por cuadrante.
    $in = array();
    foreach ($clusters as $cl) {
        $cuads = array_values(array_unique(array_column($cl['articulos'], 'cuadrante')));

        // Ordenar artículos por longitud de titular (el más corto = más factual)
        $arts = $cl['articulos'];
        usort($arts, function ($a, $b) { return mb_strlen($a['titulo']) - mb_strlen($b['titulo']); });

        $muestras = array();
        foreach ($arts as $art) {
            $m = array('cuadrante' => $art['cuadrante'], 'titular' => $art['titulo']);
            if ($incluir_desc && !empty($art['descripcion'])) {
                $snippet = trim(mb_substr(strip_tags($art['descripcion']), 0, $desc_max, 'UTF-8'));
                if ($snippet !== '') $m['entradilla'] = $snippet;
            }
            $muestras[] = $m;
            if (count($muestras) >= 3) break;
        }

        $in[] = array(
            'cluster_id' => (int)$cl['cluster_id'],
            'contains_political_actor' => !empty($cl['contains_political_actor']),
            'cuadrantes' => $cuads,
            'muestras' => $muestras,
        );
    }

    $system = 'Eres un editor de teletipos. Recibes una lista de CLUSTERS: grupos preliminares de artículos sobre, en principio, un mismo tema. Cada cluster trae su titular (o titulares) de muestra etiquetados por cuadrante ideológico (izquierda, centro, derecha) y, tras "entradilla", contexto adicional.

TAREA 1 — AGRUPAR. Junta en un mismo GRUPO los clusters que cubren la MISMA noticia (el mismo hecho central), aunque el titular use palabras distintas.
- SÍ es la misma noticia (fusionar): «El Gobierno indulta a Laura Borràs» + «El Gobierno concede el indulto parcial a Borràs y a otras cuatro personas» + «Borràs, indultada». Distinto vocabulario, mismo hecho.
- NO es la misma noticia (no fusionar) aunque compartan protagonista o tema: «El Gobierno indulta a Borràs» vs «Page critica el indulto a Borràs» (uno es el hecho, otro una reacción concreta con actor propio) → son noticias distintas. Ante duda razonable, NO fusiones.
- Sé exhaustivo con los hechos idénticos: si dos clusters reportan el MISMO dato concreto sobre el MISMO sujeto (misma cifra de resultados empresariales, mismo dato macro, mismo balance) son la misma noticia aunque el tema sea "menor". Ej.: «BBVA ganó 6.051 millones en el semestre» y «BBVA gana 6.051 millones hasta junio, un 11% más» → mismo hecho, fusionar.
- Un cluster pertenece EXACTAMENTE a un grupo. Un grupo puede tener un solo cluster.

TAREA 2 — CLASIFICAR cada grupo resultante, considerando los cuadrantes COMBINADOS de todos sus miembros:

1. RELEVANCIA (string): "alta" | "media" | "baja" | "descartar".
   - "alta": tema político/social/económico con marcos claramente divergentes entre cuadrantes.
   - "media": potencial de divergencia no evidente en los titulares.
   - "baja": factual sin carga ideológica.
   - "descartar": deportes, loterías, entretenimiento, sucesos sin lectura ideológica, meteorología rutinaria, crónica social.

2. DOMINIO_TEMATICO (string): "politica_institucional", "economia_trabajo", "sanidad_ciencia", "tecnologia_regulacion", "cultura_identidad", "medio_ambiente", "educacion", "inmigracion", "internacional", "otros".

3. FRAMING_DIVERGENCE (integer 0-3): divergencia de ENCUADRE (qué se enfatiza u omite, qué causa/responsable se atribuye, qué juicio implícito) entre cuadrantes.
   - NO confundas variación de vocabulario con divergencia de encuadre: palabras distintas para lo mismo NO es framing. Si al parafrasear el hecho y el juicio son equivalentes, fd ≤ 1.
   - Solo hay divergencia real cuando los cuadrantes atribuyen causas, responsables, consecuencias o valoraciones distintas al MISMO hecho, o uno lo presenta como problema y otro como no-noticia o solución.
   - Si el grupo lo cubre 1 solo bloque → fd = 0. Si 2 bloques → fd máximo 2. Si 3 bloques → 0-3.
   - fd ≥ 2 EXIGE evidencia citable de marcos contrapuestos; si no puedes citarla, máximo 1.
   Escala: 0 monocorde/1 bloque · 1 mismo encuadre, diferencias menores · 2 marcos distintos con evidencia · 3 marcos opuestos con evidencia.

4. FRAMING_EVIDENCE (string o null): si fd ≥ 2, OBLIGATORIO — cita breve (<25 palabras) contrastando el marco de un cuadrante frente a otro (p. ej. «izq: "recortes sociales"; der: "ajuste responsable"»). Si fd ≤ 1, null.

5. RESUMEN_NEUTRAL (string o null): UNA frase (máx. 25 palabras, sin adjetivación valorativa) que se mostrará JUSTO DEBAJO del titular, que el lector YA ha visto, así que NO puede repetirlo.
   - PROHIBIDO empezar reformulando el sujeto+verbo del titular. Aporta el dato que el titular NO dice: cifra exacta, causa, consecuencia, trasfondo, quién reacciona o qué está en juego, tomándolo de las entradillas. Debe leerse como la SEGUNDA frase de la noticia.
   - Si las entradillas no aportan nada más allá del titular, null.
   - Solo si el grupo lo cubren ≥2 bloques ideológicos distintos; si no, null.

Si contains_political_actor es true en algún miembro, el grupo referencia actores/instituciones — calibra relevancia en consecuencia (tiende a "alta").

Responde SOLO con un JSON: un array "grupos". Cada grupo: {"miembros": [cluster_id,...], "relevancia": string, "dominio_tematico": string, "framing_divergence": int, "framing_evidence": string|null, "resumen_neutral": string|null}. Sin markdown ni explicaciones. Todo cluster_id de entrada debe aparecer en exactamente un grupo.';

    $user_msg = json_encode(array('clusters' => $in), JSON_UNESCAPED_UNICODE);

    $model = $cfg['model_triage'];
    $grupos = null;
    for ($attempt = 0; $attempt <= 1; $attempt++) {
        try {
            $raw = anthropic_call($model, $system, $user_msg, 8192);
            $parsed = parse_json_response($raw);
            if (isset($parsed['grupos']) && is_array($parsed['grupos'])) $parsed = $parsed['grupos'];
            if (is_array($parsed)) { $grupos = $parsed; break; }
        } catch (Exception $e) {
            prisma_log("GATE", "Fallo Haiku (intento " . ($attempt + 1) . "): " . $e->getMessage());
        }
    }

    if (!is_array($grupos)) {
        prisma_log("GATE", "Haiku falló tras reintentos — fallback sin fusión.");
        return gate_haiku_grupos_fallback($clusters);
    }

    // Índice por cluster_id
    $by_id = array();
    foreach ($clusters as $cl) $by_id[(int)$cl['cluster_id']] = $cl;

    $salida = array();
    $vistos = array();

    foreach ($grupos as $g) {
        $miembros_raw = isset($g['miembros']) ? $g['miembros']
            : (isset($g['cluster_ids']) ? $g['cluster_ids'] : array());
        if (!is_array($miembros_raw)) continue;

        $miembros = array();
        foreach ($miembros_raw as $m) {
            $mid = (int)$m;
            if (isset($by_id[$mid]) && !isset($vistos[$mid])) {
                $miembros[] = $mid;
                $vistos[$mid] = true;
            }
        }
        if (empty($miembros)) continue;

        // Unión de artículos (dedup por URL)
        $arts = array();
        $urls = array();
        foreach ($miembros as $mid) {
            foreach ($by_id[$mid]['articulos'] as $a) {
                $u = isset($a['url']) ? $a['url'] : '';
                if ($u !== '' && isset($urls[$u])) continue;
                if ($u !== '') $urls[$u] = true;
                $arts[] = $a;
            }
        }

        $clasif = gate_haiku_normalizar_clasificacion($g, $arts);
        $salida[] = array(
            'miembros' => $miembros,
            'articulos' => $arts,
        ) + $clasif;
    }

    // Clusters que Haiku no asignó → singleton indeterminado (no se pierde nada)
    foreach ($clusters as $cl) {
        $cid = (int)$cl['cluster_id'];
        if (isset($vistos[$cid])) continue;
        $salida[] = array(
            'miembros' => array($cid),
            'articulos' => $cl['articulos'],
            'relevancia' => 'indeterminada',
            'dominio' => null,
            'framing_divergence' => null,
            'framing_evidence' => null,
            'resumen_neutral' => null,
            'anomalies' => array(
                array('tipo' => 'ANOMALY_MISSING_CLUSTER', 'detalle' => "cluster_id=$cid ausente en la respuesta Haiku"),
            ),
        );
    }

    $n_fusiones = count($clusters) - count($salida);
    prisma_log("GATE", count($clusters) . " clusters → " . count($salida) . " grupos ($n_fusiones fusiones).");
    return $salida;
}

/**
 * Normaliza y valida la clasificación de un grupo (enums + topes de fd + evidencia).
 *
 * @param array $g    Objeto del grupo devuelto por Haiku
 * @param array $arts Artículos fusionados del grupo (para contar bloques)
 * @return array ['relevancia','dominio','framing_divergence','framing_evidence','resumen_neutral','anomalies']
 */
function gate_haiku_normalizar_clasificacion(array $g, array $arts): array {
    $b = contar_bloques($arts);
    $bloques_activos = $b['bloques_activos'];

    $rel_raw = isset($g['relevancia']) ? $g['relevancia'] : 'media';
    if ($rel_raw === true) $rel = 'alta';
    elseif ($rel_raw === false) $rel = 'baja';
    else $rel = (string)$rel_raw;

    $dom = isset($g['dominio_tematico']) ? (string)$g['dominio_tematico']
         : (isset($g['dominio']) ? (string)$g['dominio'] : 'otros');
    $fd  = isset($g['framing_divergence']) ? (int)$g['framing_divergence'] : 0;
    $ev  = isset($g['framing_evidence']) ? $g['framing_evidence'] : null;
    $res = (isset($g['resumen_neutral']) && is_string($g['resumen_neutral']) && trim($g['resumen_neutral']) !== '')
         ? trim($g['resumen_neutral']) : null;

    if (!in_array($rel, PRISMA_RELEVANCIA_VALID, true)) $rel = 'media';
    if (!in_array($dom, PRISMA_DOMINIO_VALID, true)) $dom = 'otros';
    if ($fd < 0) $fd = 0;
    if ($fd > 3) $fd = 3;

    $anomalies = array();
    // Topes por nº de bloques
    if ($bloques_activos === 1 && $fd > 0) {
        $anomalies[] = array('tipo' => 'ANOMALY_FD_CAP_VIOLATION', 'detalle' => "fd=$fd con 1 bloque, capado a 0");
        $fd = 0;
    } elseif ($bloques_activos === 2 && $fd > 2) {
        $anomalies[] = array('tipo' => 'ANOMALY_FD_CAP_VIOLATION', 'detalle' => "fd=$fd con 2 bloques, capado a 2");
        $fd = 2;
    }

    // Salvaguarda de evidencia: fd alto sin marcos contrapuestos citables → capar a 1.
    $ev_str = is_string($ev) ? trim($ev) : '';
    if ($fd >= 2 && mb_strlen($ev_str, 'UTF-8') < 12) {
        $anomalies[] = array('tipo' => 'ANOMALY_FD_SIN_EVIDENCIA', 'detalle' => "fd=$fd sin framing_evidence citable, capado a 1");
        $fd = 1;
    }

    // Resumen solo con ≥2 bloques
    if ($bloques_activos < 2) $res = null;

    return array(
        'relevancia' => $rel,
        'dominio' => $dom,
        'framing_divergence' => $fd,
        'framing_evidence' => $ev,
        'resumen_neutral' => $res,
        'anomalies' => $anomalies,
    );
}

/**
 * Fallback cuando Haiku no está disponible: cada cluster es su propio grupo,
 * sin clasificar (indeterminada → h_score 0 → no contamina digest/análisis).
 */
function gate_haiku_grupos_fallback(array $clusters): array {
    $salida = array();
    foreach ($clusters as $cl) {
        $salida[] = array(
            'miembros' => array((int)$cl['cluster_id']),
            'articulos' => $cl['articulos'],
            'relevancia' => 'indeterminada',
            'dominio' => null,
            'framing_divergence' => null,
            'framing_evidence' => null,
            'resumen_neutral' => null,
            'anomalies' => array(),
        );
    }
    return $salida;
}
