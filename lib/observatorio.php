<?php
/**
 * PolarPrisma — Observatorio: temas persistentes (hilos de la agenda).
 *
 * Un TEMA agrupa muchos clusters del radar a lo largo del tiempo. La prioridad es
 * la AGRUPACIÓN FUERTE: temas anchos y legibles (una docena larga, tipo secciones
 * duraderas — "Inmigración y fronteras", "Amnistía y Cataluña", "Vivienda"…), NUNCA
 * eventos concretos, y sin casi-duplicados. La asignación la hace Haiku prefiriendo
 * SIEMPRE emparejar a un tema existente; solo crea uno nuevo si no cabe en ninguno.
 * Una pasada de consolidación fusiona los duplicados que se cuelen.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/anthropic.php';
require_once __DIR__ . '/curador.php';   // PRISMA_CUADRANTE_POS, PRISMA_GRUPO_*
require_once __DIR__ . '/layout.php';     // PRISMA_CUADRANTE_COLORES, cuadrante_color

/** Slug URL-safe a partir de un nombre. */
function observatorio_slug(string $nombre): string {
    $s = mb_strtolower(trim($nombre), 'UTF-8');
    $s = strtr($s, array('á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? mb_substr($s, 0, 60) : 'tema';
}

/** Temas activos (id, nombre, slug, descripcion). */
function observatorio_temas(bool $solo_activos = true): array {
    $db = prisma_db();
    $sql = 'SELECT id, nombre, slug, descripcion, last_seen FROM temas';
    if ($solo_activos) $sql .= ' WHERE activo = 1';
    $sql .= ' ORDER BY nombre';
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function observatorio_tema_por_slug(string $slug) {
    $db = prisma_db();
    $st = $db->prepare('SELECT * FROM temas WHERE slug = :s AND activo = 1');
    $st->execute(array(':s' => $slug));
    $t = $st->fetch(PDO::FETCH_ASSOC);
    return $t ?: null;
}

/** Crea (o recupera) un tema por nombre; devuelve su id. */
function observatorio_crear_tema(string $nombre, string $descripcion = ''): int {
    $db = prisma_db();
    $slug = observatorio_slug($nombre);
    $st = $db->prepare('SELECT id FROM temas WHERE slug = :s');
    $st->execute(array(':s' => $slug));
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
    $ins = $db->prepare('INSERT INTO temas (nombre, slug, descripcion, last_seen) VALUES (:n, :s, :d, :ls)');
    $ins->execute(array(':n' => $nombre, ':s' => $slug, ':d' => $descripcion, ':ls' => date('Y-m-d')));
    return (int)$db->lastInsertId();
}

// ── Agregados por tema (peso ideológico, timeline) ──────────────────

/**
 * Estadísticas de un tema: nº de clusters, distribución por cuadrante/bloque,
 * centro de gravedad ideológico y lado dominante.
 *
 * @param int      $tema_id
 * @param int|null $dias  si se indica, solo cuenta clusters de los últimos N días
 */
function observatorio_stats(int $tema_id, $dias = null): array {
    $db = prisma_db();
    $sql = 'SELECT fecha, fuentes_json FROM radar WHERE tema_id = :t';
    $params = array(':t' => $tema_id);
    if ($dias !== null) {
        $sql .= " AND fecha >= :fmin";
        $params[':fmin'] = date('Y-m-d', strtotime("-{$dias} days"));
    }
    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $orden = array_keys(PRISMA_CUADRANTE_COLORES);
    $cuad = array_fill_keys($orden, 0);
    $blocs = array('izq' => 0, 'centro' => 0, 'der' => 0);
    $cog_num = 0.0; $cog_den = 0;
    $primera = null; $ultima = null;
    foreach ($rows as $r) {
        if ($primera === null || $r['fecha'] < $primera) $primera = $r['fecha'];
        if ($ultima === null || $r['fecha'] > $ultima) $ultima = $r['fecha'];
        $fuentes = json_decode($r['fuentes_json'], true);
        if (!is_array($fuentes)) continue;
        foreach ($fuentes as $f) {
            $c = isset($f['cuadrante']) ? $f['cuadrante'] : '';
            if (!isset($cuad[$c])) continue;
            $cuad[$c]++;
            if (in_array($c, PRISMA_GRUPO_IZQ)) $blocs['izq']++;
            elseif (in_array($c, PRISMA_GRUPO_DER)) $blocs['der']++;
            else $blocs['centro']++;
            if (isset(PRISMA_CUADRANTE_POS[$c])) { $cog_num += PRISMA_CUADRANTE_POS[$c]; $cog_den++; }
        }
    }
    $cog = $cog_den > 0 ? $cog_num / $cog_den : 0.0;   // -3 (izq) .. +3 (der)
    $lado = $cog <= -0.5 ? 'izq' : ($cog >= 0.5 ? 'der' : 'centro');

    return array(
        'n_clusters'    => count($rows),
        'cuadrantes'    => $cuad,
        'bloques'       => $blocs,
        'menciones'     => array_sum($cuad),
        'cog'           => $cog,
        'lado'          => $lado,
        'primera'       => $primera,
        'ultima'        => $ultima,
    );
}

/** Color del lado dominante (para el subrayado del tema). */
function observatorio_lado_color(string $lado): string {
    if ($lado === 'izq') return '#ff6b81';
    if ($lado === 'der') return '#4d9eff';
    return '#f2f24a';
}

/** Timeline: menciones (clusters) por día para un tema, primera→última. */
function observatorio_timeline(int $tema_id): array {
    $db = prisma_db();
    $st = $db->prepare('SELECT fecha, COUNT(*) n FROM radar WHERE tema_id = :t GROUP BY fecha ORDER BY fecha');
    $st->execute(array(':t' => $tema_id));
    $out = array();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['fecha']] = (int)$r['n'];
    return $out;
}

/** Clusters/noticias de un tema (para la ficha), recientes primero. */
function observatorio_noticias(int $tema_id, int $limit = 60): array {
    $db = prisma_db();
    $st = $db->prepare('SELECT id, fecha, titulo_tema, h_score, analizado, articulo_id, resumen_neutral
        FROM radar WHERE tema_id = :t ORDER BY fecha DESC, h_score DESC LIMIT :l');
    $st->bindValue(':t', $tema_id, PDO::PARAM_INT);
    $st->bindValue(':l', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// ── Asignación (Haiku, agrupación fuerte) ───────────────────────────

/**
 * Asigna un tema a cada cluster (radar_row) de la lista. Empareja con generosidad
 * a los temas existentes; crea nuevos solo si no cabe en ninguno.
 *
 * @param array $rows filas de radar (id, titulo_tema, dominio_tematico, fuentes_json, resumen_neutral)
 * @return int nº de clusters asignados
 */
function observatorio_asignar_clusters(array $rows): int {
    if (empty($rows)) return 0;
    $cfg = prisma_cfg();

    try { anthropic_check_budget(); }
    catch (Exception $e) { prisma_log("OBS", "Budget agotado — sin asignar: " . $e->getMessage()); return 0; }

    $temas = observatorio_temas(true);
    $temas_json = array();
    foreach ($temas as $t) $temas_json[] = array('slug' => $t['slug'], 'nombre' => $t['nombre'], 'descripcion' => $t['descripcion']);

    $noticias = array();
    foreach ($rows as $r) {
        $noticias[] = array(
            'id'      => (int)$r['id'],
            'titular' => $r['titulo_tema'],
            'dominio' => isset($r['dominio_tematico']) ? $r['dominio_tematico'] : null,
        );
    }

    $system = 'Eres el bibliotecario del Observatorio de PolarPrisma. Mantienes una lista CORTA y ESTABLE de TEMAS (hilos de la agenda pública), anchos y duraderos —como secciones temáticas: "Inmigración y fronteras", "Amnistía y Cataluña", "Vivienda", "Corrupción y justicia", "Sanidad pública", "Guerra en Ucrania", "Oriente Medio", "Energía y precios", "Memoria y cultura"…—, NUNCA eventos concretos ni titulares.

Recibes los TEMAS existentes y una lista de NOTICIAS nuevas. Asigna a cada noticia el tema existente que mejor encaje.

REGLAS (la prioridad es AGRUPAR, no dividir):
- Prefiere SIEMPRE un tema existente, aunque el encaje no sea perfecto. Ante la duda, empareja.
- Crea un tema NUEVO solo si la noticia no cabe RAZONABLEMENTE en ninguno de los existentes.
- Si creas uno nuevo, que sea ANCHO y con nombre corto de sección (2-4 palabras), NO un evento. Nunca crees dos temas casi iguales.
- Una noticia = un solo tema (el principal).

Devuelve SOLO un JSON: {"asignaciones": [{"id": <id_noticia>, "tema": "<slug-existente>"} | {"id": <id_noticia>, "nuevo": {"nombre": "...", "descripcion": "una frase"}}]}. Sin markdown.';

    $user = json_encode(array('temas_existentes' => $temas_json, 'noticias' => $noticias), JSON_UNESCAPED_UNICODE);

    $model = $cfg['model_triage'];
    $parsed = null;
    for ($i = 0; $i <= 1; $i++) {
        try {
            $raw = anthropic_call($model, $system, $user, 4096);
            $p = parse_json_response($raw);
            if (isset($p['asignaciones']) && is_array($p['asignaciones'])) { $parsed = $p['asignaciones']; break; }
        } catch (Exception $e) { prisma_log("OBS", "Fallo Haiku asignación (intento " . ($i+1) . "): " . $e->getMessage()); }
    }
    if (!is_array($parsed)) { prisma_log("OBS", "Sin asignaciones válidas."); return 0; }

    $db = prisma_db();
    $slug2id = array();
    foreach ($temas as $t) $slug2id[$t['slug']] = (int)$t['id'];
    $upd = $db->prepare('UPDATE radar SET tema_id = :t WHERE id = :id');
    $touch = $db->prepare('UPDATE temas SET last_seen = :ls WHERE id = :id');

    $by_id = array();
    foreach ($rows as $r) $by_id[(int)$r['id']] = $r;

    $n = 0;
    foreach ($parsed as $a) {
        $rid = isset($a['id']) ? (int)$a['id'] : 0;
        if (!isset($by_id[$rid])) continue;
        $tema_id = 0;
        if (!empty($a['tema']) && isset($slug2id[$a['tema']])) {
            $tema_id = $slug2id[$a['tema']];
        } elseif (!empty($a['nuevo']['nombre'])) {
            $tema_id = observatorio_crear_tema($a['nuevo']['nombre'], isset($a['nuevo']['descripcion']) ? $a['nuevo']['descripcion'] : '');
            // registrar en el mapa por si otra noticia del lote cae en el mismo nuevo
            $ns = observatorio_slug($a['nuevo']['nombre']);
            $slug2id[$ns] = $tema_id;
        } elseif (!empty($a['tema'])) {
            // slug propuesto pero inexistente → trátalo como nuevo con ese nombre
            $tema_id = observatorio_crear_tema($a['tema']);
            $slug2id[observatorio_slug($a['tema'])] = $tema_id;
        }
        if ($tema_id > 0) {
            $upd->execute(array(':t' => $tema_id, ':id' => $rid));
            $touch->execute(array(':ls' => date('Y-m-d'), ':id' => $tema_id));
            $n++;
        }
    }
    prisma_log("OBS", "$n clusters asignados a temas (" . count(observatorio_temas(true)) . " temas activos).");
    return $n;
}

/** Asigna los clusters relevantes de un día que aún no tienen tema. */
function observatorio_asignar_dia(string $fecha): int {
    $db = prisma_db();
    $st = $db->prepare("SELECT id, titulo_tema, dominio_tematico, fuentes_json, resumen_neutral
        FROM radar
        WHERE fecha = :f AND tema_id IS NULL AND relevancia IN ('alta','media')
        ORDER BY h_score DESC");
    $st->execute(array(':f' => $fecha));
    return observatorio_asignar_clusters($st->fetchAll(PDO::FETCH_ASSOC));
}

// ── Consolidación (fusiona casi-duplicados) ─────────────────────────

/** Reasigna los clusters de $from a $into y jubila $from. */
function observatorio_merge(int $from_id, int $into_id): void {
    if ($from_id === $into_id) return;
    $db = prisma_db();
    $db->prepare('UPDATE radar SET tema_id = :to WHERE tema_id = :from')->execute(array(':to' => $into_id, ':from' => $from_id));
    $db->prepare('UPDATE temas SET activo = 0 WHERE id = :id')->execute(array(':id' => $from_id));
    prisma_log("OBS", "Tema #$from_id fusionado en #$into_id.");
}

/**
 * Revisa la lista de temas y fusiona los casi-duplicados (mismo hilo con nombres
 * distintos). Devuelve el nº de fusiones aplicadas.
 */
function observatorio_consolidar(): int {
    $cfg = prisma_cfg();
    $temas = observatorio_temas(true);
    if (count($temas) < 2) return 0;
    try { anthropic_check_budget(); } catch (Exception $e) { return 0; }

    $lista = array();
    foreach ($temas as $t) $lista[] = array('slug' => $t['slug'], 'nombre' => $t['nombre'], 'descripcion' => $t['descripcion']);

    $system = 'Eres el bibliotecario del Observatorio. Recibes la lista de TEMAS (hilos de la agenda). Identifica los que son en realidad EL MISMO tema con nombres distintos o solapamiento fuerte, y propón fusiones para dejar una lista corta, ancha y sin duplicados. Conserva el nombre más ancho y claro como destino. NO fusiones temas genuinamente distintos.

Devuelve SOLO JSON: {"fusiones": [{"de": "<slug>", "a": "<slug-destino>"}]}. Vacío si no hay duplicados. Sin markdown.';
    $user = json_encode(array('temas' => $lista), JSON_UNESCAPED_UNICODE);

    $parsed = null;
    try {
        $raw = anthropic_call($cfg['model_triage'], $system, $user, 2048);
        $p = parse_json_response($raw);
        if (isset($p['fusiones']) && is_array($p['fusiones'])) $parsed = $p['fusiones'];
    } catch (Exception $e) { prisma_log("OBS", "Fallo consolidación: " . $e->getMessage()); return 0; }
    if (!is_array($parsed)) return 0;

    $slug2id = array();
    foreach ($temas as $t) $slug2id[$t['slug']] = (int)$t['id'];
    $n = 0;
    foreach ($parsed as $f) {
        $de = isset($f['de']) ? $f['de'] : '';
        $a  = isset($f['a']) ? $f['a'] : '';
        if (isset($slug2id[$de], $slug2id[$a]) && $slug2id[$de] !== $slug2id[$a]) {
            observatorio_merge($slug2id[$de], $slug2id[$a]);
            unset($slug2id[$de]); // ya jubilado; evita fusionar en cadena hacia él
            $n++;
        }
    }
    if ($n > 0) prisma_log("OBS", "$n temas fusionados en la consolidación.");
    return $n;
}
