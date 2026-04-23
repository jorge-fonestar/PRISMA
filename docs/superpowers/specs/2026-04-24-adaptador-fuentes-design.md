# Adaptador de Fuentes Multi-Modal para Prisma

**Fecha:** 2026-04-24
**Estado:** Aprobado (pendiente de implementación)
**Contexto:** Público e InfoLibre han abandonado RSS como decisión editorial. El desequilibrio en los cuadrantes de izquierda degrada la señal de H_cobertura_mutua y f(framing_divergence) del scoring.

## Problema

El pipeline de Prisma depende exclusivamente de RSS para la ingesta de fuentes. Varios medios relevantes han dejado de ofrecer RSS (modelo suscripción, pérdida de tráfico a agregadores), y la tendencia va a empeorar. El resultado es un desequilibrio estructural: `izquierda-populista` tiene 1 medio (El Salto) e `izquierda` tiene 1 medio (elDiario.es), mientras que `centro` tiene 4, `centro-derecha` tiene 2 y `derecha` tiene 3. Los 7 cuadrantes del ámbito España (`izquierda-populista`, `izquierda`, `centro-izquierda`, `centro`, `centro-derecha`, `derecha`, `derecha-populista`) muestran un desequilibrio claro hacia la izquierda del espectro.

Este desequilibrio no es solo cuantitativo. Produce falsos silencios en el scoring: cuando la izquierda no cubre un tema, puede ser un artefacto de selección del corpus (los medios elegidos no cubren ese beat) y no una señal real de polarización editorial.

## Decisión de Arquitectura

Implementar un adaptador de fuentes con dos modalidades operativas: `rss_nativo` y `captura_portada`. Desde el punto de vista del pipeline, ambos producen el mismo objeto normalizado (lista de artículos con titular, URL, fecha). El pipeline aguas abajo es completamente agnóstico a la modalidad.

**Enfoque elegido:** Módulo separado con interfaz común (Enfoque 2). Nuevo `lib/fuentes/captura_portada.php` con funciones puras. `rss_fetch_all()` despacha según modalidad. No se toca el código existente de `rss_fetch_feed()`.

**Descartados:**
- Enfoque 1 (inline en rss.php): mezcla responsabilidades, dificulta testing
- Enfoque 3 (factory completa): sobreingeniería para 2 modalidades, rompe imports existentes

---

## Sección 1: Modelo de Datos del Config Extendido

Cada medio en `config.php` pasa de un array simple `array('Nombre', 'URL')` a un array asociativo. El formato legacy sigue funcionando indefinidamente — migración gradual, sin big-bang.

### Modalidad rss_nativo

```php
array(
    'medio' => 'elDiario.es',
    'url' => 'https://www.eldiario.es/rss/',
    'modalidad' => 'rss_nativo',
    'transparencia' => '',
    'perfil_editorial' => 'Generalista progresista. Política institucional, economía, derechos sociales, medio ambiente.',
    'ejes_cubiertos' => array('politica_partidista', 'economia_laboral', 'feminismo_genero', 'ecologia_clima', 'vivienda', 'sanidad'),
),
```

### Modalidad captura_portada (Categoría A/B)

```php
array(
    'medio' => 'Diario Red',
    'url' => 'https://www.diariored.com/',
    'modalidad' => 'captura_portada',
    'categoria_acceso' => 'B',
    'selector_articulos' => 'article.post',
    'selector_titulo' => 'h2 a',
    'selector_url' => 'h2 a@href',
    'selector_fecha' => 'time@datetime',
    'transparencia' => 'Medio sin RSS nativo. Leído vía captura de portada con user-agent identificable.',
    'perfil_editorial' => 'Izquierda política, laboralismo, antimilitarismo.',
    'ejes_cubiertos' => array('politica_partidista', 'laboralismo', 'movimientos_sociales'),
),
```

### Modalidad no_disponible (Categoría C)

```php
array(
    'medio' => 'Público',
    'url' => null,
    'modalidad' => 'no_disponible',
    'categoria_acceso' => 'C',
    'transparencia' => 'Medio sin RSS nativo (política editorial). Autorización solicitada el 2026-04-XX, pendiente de respuesta.',
    'perfil_editorial' => 'Generalista progresista español.',
    'ejes_cubiertos' => array(),
),
```

### Campos

| Campo | Obligatorio | Descripción |
|-------|-------------|-------------|
| `medio` | Sí | Nombre del medio |
| `url` | Sí (null para no_disponible) | URL del feed RSS o de la portada |
| `modalidad` | Sí | `rss_nativo` / `captura_portada` / `no_disponible` |
| `categoria_acceso` | Solo captura_portada y no_disponible | A / B / C |
| `selector_articulos` | Solo captura_portada | Selector CSS del contenedor de artículos |
| `selector_titulo` | Solo captura_portada | Selector CSS del título (texto). Notación `@atributo`: se separa por `@`, la parte izquierda es selector CSS, la derecha es nombre de atributo a extraer. |
| `selector_url` | Solo captura_portada | Selector CSS + `@atributo` para href. Ejemplo: `h2 a@href` → selector `h2 a`, atributo `href`. |
| `selector_fecha` | Solo captura_portada | Selector CSS + `@atributo` para datetime. Ejemplo: `time@datetime`. |
| `transparencia` | No | Nota pública renderizada en fuentes.php. Preserva notas existentes del formato legacy. |
| `perfil_editorial` | No | Texto libre descriptivo del perfil del medio. Renderizado en fuentes.php. |
| `ejes_cubiertos` | No | Array de etiquetas ligeras. Herramienta de gestión del corpus, no alimenta el scoring. |

### Subset CSS soportado en selectores

Los selectores CSS del config usan un subset limitado, convertido a XPath por `captura_portada_css_to_xpath()`:

- **Tag names:** `article`, `h2`, `time`
- **Class selectors:** `.noticia`, `.post`
- **Descendant combinator (espacio):** `article .titulo a`
- **Notación `@atributo`** (Prisma-specific): `h2 a@href` se parsea como selector `h2 a` + extraer atributo `href`

**No soportados:** pseudo-selectors (`:first-child`), sibling combinators (`+`, `~`), attribute selectors (`[data-x]`), ID selectors (`#id`). Si un medio requiere selectores complejos, usar XPath directo en el config (prefijo `xpath:` para distinguir).

### Compatibilidad Legacy

`rss_normalizar_fuente()` convierte formato legacy a formato nuevo. Se ubica en `lib/fuentes/normalizar.php` (fichero compartido entre `rss.php`, `fuentes.php` y otros consumidores del config de fuentes):

```php
function rss_normalizar_fuente($fuente, $cuadrante, $ambito) {
    if (isset($fuente[0]) && is_string($fuente[0])) {
        return array(
            'medio' => $fuente[0],
            'url' => $fuente[1],
            'modalidad' => 'rss_nativo',
            'transparencia' => isset($fuente[2]) ? $fuente[2] : '',
            'cuadrante' => $cuadrante,
            'ambito' => $ambito,
        );
    }
    $fuente['cuadrante'] = $cuadrante;
    $fuente['ambito'] = $ambito;
    return $fuente;
}
```

---

## Sección 2: Módulo lib/fuentes/captura_portada.php

Funciones puras, sin estado, sin dependencias externas más allá de cURL + libxml (ya presentes en el proyecto).

### Función principal

```php
function captura_portada_fetch($fuente_cfg)
```

Recibe el array de config del medio. Devuelve una estructura informativa que permite al llamador distinguir el motivo del resultado:

```php
array(
    'items' => array(
        array(
            'titulo'         => 'Titular del artículo',
            'url'            => 'https://medio.com/articulo-completo',
            'fecha'          => '2026-04-24 14:30:00',
            'fecha_ts'       => 1745502600,
            'descripcion'    => '',     // Siempre vacío — nunca extraemos entradilla (art. 15)
            'fecha_inferida' => false,  // true si la fecha no pudo extraerse y se usó la actual
        ),
    ),
    'resultado' => 'ok',    // ok / fail / throttle / skip
    'extras' => array(
        'http_status'  => 200,
        'error'        => null,
        'latencia_ms'  => 340,
    ),
)
```

Valores de `resultado`:
- `ok`: fetch exitoso con items
- `fail`: error HTTP, parse fallido, o 0 items tras parse exitoso
- `throttle`: rate limit activo, no se hizo fetch (esperado, no es error)
- `skip`: robots.txt deniega acceso

Nota: `fecha_inferida` indica que la fecha de publicación no pudo extraerse del HTML y se usó la fecha actual como aproximación. El curador puede usar este flag para penalizar items con fecha incierta en el clustering (menor confianza en la ventana temporal).

### Flujo interno

1. **robots.txt check** — `captura_portada_robots_allowed($url, $user_agent)`. Parsea robots.txt del dominio, cachea en memoria por dominio durante la ejecución. Si Disallow, retorna array vacío + log warning. **Manejo de errores en robots.txt:** HTTP 404 → todo permitido (no hay restricciones). HTTP 5xx o timeout → todo denegado (conservador, se reintenta en siguiente ciclo). HTTP 200 con contenido no parseable → todo denegado + log warning.

2. **Rate limit** — `captura_portada_rate_check($dominio)`. Consulta tabla `feed_health` en `prisma_logs.db` para el último registro (cualquier resultado) de ese dominio. Si < 60 minutos, retorna array vacío (throttle esperado, no error). **Nota:** el rate check consulta registros existentes incluyendo los de tipo "started" — ver paso 2b.

   2b. **Pre-registro** — Antes del fetch HTTP, se escribe un registro `feed_health` con resultado `started` para evitar race conditions si dos ejecuciones coinciden. Si el fetch posterior tiene éxito, se actualiza a `ok`; si falla, a `fail`.

3. **HTTP fetch** — cURL con:
   - User-Agent: `PrismaBot/1.0 (+https://prisma.example/bot)` (mismo UA que rss.php para consistencia en logs de los medios)
   - Timeout: 15s (consistente con rss.php)
   - Follow redirects: sí (max 3)
   - Accept: `text/html`
   - No cookies, no JavaScript

4. **Encoding detection** — Antes de parsear, detectar charset del HTML: primero desde header HTTP `Content-Type`, luego desde `<meta charset>` o `<meta http-equiv>`. Si no se detecta, asumir UTF-8. Convertir a UTF-8 con `mb_convert_encoding()` si es necesario (ISO-8859-1 es común en medios españoles antiguos).

5. **HTML parse** — `captura_portada_parse($html, $selectores)`. Usa DOMDocument + DOMXPath (libxml). Convierte selectores CSS del config a XPath internamente. Extrae:
   - Título: texto del nodo selector_titulo
   - URL: atributo href de selector_url, normalizado a absoluto
   - Fecha: atributo datetime de selector_fecha. Si no existe, fecha actual con flag `fecha_inferida = true`

6. **Sanitización** — Misma normalización que rss.php: trim, decode HTML entities, dedup por URL.

7. **Descarte del HTML** — El string HTML crudo no se almacena ni retorna. Solo los items normalizados salen de la función.

### Funciones auxiliares

| Función | Propósito |
|---------|-----------|
| `captura_portada_robots_allowed($url, $ua)` | Parsea robots.txt, evalúa si URL permitida |
| `captura_portada_rate_check($dominio)` | Verifica rate limit contra feed_health |
| `captura_portada_css_to_xpath($selector)` | Convierte selector CSS simple a XPath |
| `captura_portada_parse($html, $selectores)` | Extrae items de HTML según selectores |
| `captura_portada_normalizar_url($rel, $base)` | Normaliza URL relativa a absoluta. Cubre 3 casos: absoluta (`https://...`) se retorna tal cual; relativa (`/ruta`) se prepende esquema+dominio de base; protocol-relative (`//cdn.ejemplo/...`) hereda esquema de la URL base. |

### Limitaciones explícitas

- No ejecuta JavaScript (medio requiere JS → incompatible con esta modalidad)
- No almacena HTML crudo
- No extrae entradillas, subtítulos ni imágenes
- No gestiona sesiones ni cookies
- No reintentos automáticos (fallo se registra, se espera al siguiente ciclo)

---

## Sección 3: Despacho y Registro de Salud

### Modificación de rss_fetch_all()

```php
function rss_fetch_all($ambito = null) {
    // ... iteración existente por ámbitos/cuadrantes ...

    foreach ($medios as $fuente) {
        $cfg = rss_normalizar_fuente($fuente, $cuadrante, $ambito_actual);

        if ($cfg['modalidad'] === 'no_disponible') {
            feed_health_registrar($cfg['medio'], $ambito_actual, 'skip', 'no_disponible');
            continue;
        }

        if ($cfg['modalidad'] === 'captura_portada') {
            $resp = captura_portada_fetch($cfg);
            $items = $resp['items'];
            $resultado = $resp['resultado'];
            $extras = $resp['extras'];
        } else {
            $items = rss_fetch_feed($cfg['url']);
            if ($items === null) { $items = array(); $resultado = 'fail'; }
            else { $resultado = count($items) > 0 ? 'ok' : 'fail'; }
            $extras = array();
        }

        feed_health_registrar($cfg['medio'], $ambito_actual,
            $resultado, $cfg['modalidad'], count($items), $extras
        );

        // ... resto del pipeline igual ...
    }
}
```

### Tabla feed_health (prisma_logs.db)

```sql
CREATE TABLE IF NOT EXISTS feed_health (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    medio       TEXT NOT NULL,
    ambito      TEXT NOT NULL,
    modalidad   TEXT NOT NULL,
    resultado   TEXT NOT NULL,       -- ok / fail / skip / throttle
    items_count INTEGER DEFAULT 0,
    http_status INTEGER DEFAULT NULL,
    error_msg   TEXT DEFAULT NULL,
    latencia_ms INTEGER DEFAULT NULL,
    created_at  TEXT DEFAULT (datetime('now'))
);
CREATE INDEX idx_fh_medio_fecha ON feed_health(medio, created_at);
```

### Función feed_health_registrar()

```php
function feed_health_registrar($medio, $ambito, $resultado, $modalidad, $items = 0, $extras = array()) {
    $db = prisma_logger_db();
    $stmt = $db->prepare('INSERT INTO feed_health (medio, ambito, modalidad, resultado, items_count, http_status, error_msg, latencia_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute(array(
        $medio, $ambito, $modalidad, $resultado, $items,
        isset($extras['http_status']) ? $extras['http_status'] : null,
        isset($extras['error']) ? $extras['error'] : null,
        isset($extras['latencia_ms']) ? $extras['latencia_ms'] : null,
    ));
}
```

### Consultas clave

```sql
-- Tasa de éxito últimos 30 días por medio
SELECT medio, modalidad,
    COUNT(*) as total,
    SUM(CASE WHEN resultado = 'ok' THEN 1 ELSE 0 END) as exitos,
    ROUND(100.0 * SUM(CASE WHEN resultado = 'ok' THEN 1 ELSE 0 END) / COUNT(*), 1) as tasa_exito
FROM feed_health
WHERE created_at >= datetime('now', '-30 days')
  AND resultado NOT IN ('skip', 'throttle', 'started')
GROUP BY medio;

-- Fuentes sin actividad exitosa > 7 días
SELECT medio, modalidad, MAX(created_at) as ultimo_exito
FROM feed_health
WHERE resultado = 'ok'
GROUP BY medio
HAVING ultimo_exito < datetime('now', '-7 days');

-- Último resultado por fuente
SELECT medio, modalidad, resultado, items_count, created_at
FROM feed_health
WHERE id IN (SELECT MAX(id) FROM feed_health GROUP BY medio);
```

### Purga automática

Registros de `feed_health` más antiguos de 90 días se eliminan al inicio de cada ejecución de `escanear.php`. Con ~42 fuentes y ejecuciones de escaneo 1-2 veces al día, esto supone ~4.000-8.000 registros en 90 días. Holgado para SQLite.

---

## Sección 4: Widget de Panel y Vista de Salud

### Widget resumen en panel.php

Bloque compacto en la zona superior del dashboard, junto a contadores existentes:

- **Semáforo:** "Fuentes: 38/42 operativas" (verde >90%, amarillo 70-90%, rojo <70%)
- **Alertas activas:** Lista de fuentes con problemas (máx. 5 visibles):
  - `EFE — 3 fallos consecutivos (429 rate limit)`
  - `Diario Red — sin actividad 12 días`
- **Enlace:** "Ver salud completa de fuentes →" a `validar_feeds.php?mode=salud`

### Vista completa en validar_feeds.php (modo salud)

Nuevo modo complementario a los existentes (candidatos, todos, url):

- **Tabla por medio:** Medio | Ámbito | Cuadrante | Modalidad | Tasa éxito 30d | Último éxito | Items último fetch | Estado
- **Ordenable por tasa de éxito** (peores primero)
- **Filtrable por ámbito** (tabs: España / Europa / Global / Todos)
- **Matriz ejes × cuadrantes** (si campos `ejes_cubiertos` rellenados): tabla cruzada, celdas con 0 medios en rojo. Herramienta de auditoría del corpus.
- **Historial por medio** (expandible): últimos 30 registros con timestamp, resultado, items, latencia, error.

### Renderizado en fuentes.php

Extensión de la página pública de fuentes:

- Nombre + cuadrante (existente)
- Etiqueta de modalidad: `RSS` / `Portada` / `No disponible`
- Campo `transparencia` si existe
- Campo `perfil_editorial` si existe
- Para medios `no_disponible` Categoría C: nombre, cuadrante y texto de `transparencia` visible

Párrafo introductorio nuevo:

> Prisma accede al contenido público de los medios mediante tres vías: RSS nativo cuando el medio lo ofrece; captura de portada (solo titulares y enlaces, respetando robots.txt y con user-agent identificable) cuando el medio no ha implementado RSS; y autorización explícita solicitada por correo cuando el medio ha retirado su RSS deliberadamente. Cualquier medio que desee no ser incluido puede escribir a {email} y será retirado del corpus en 48 horas.

---

## Sección 5: Viabilidad Legal y Gobernanza del Corpus

### Marco legal aplicable

- **Art. 15 DSM (RDL 24/2021):** Prisma cae dentro como prestador de servicios que usa publicaciones de prensa online. Excepciones aplicables: "extractos muy breves" (titular aislado — zona gris favorable) e "hipervínculos" (clara). Captura_portada extrae exclusivamente titular + URL + fecha.
- **Directiva 96/9/CE (bases de datos):** Riesgo mitigado al no almacenar portadas completas. HTML crudo se descarta tras parsing.
- **AI Act:** Riesgo bajo. Prisma no entrena modelos, usa APIs externas, no automatiza decisiones sobre personas.

### Taxonomía de acceso

| Categoría | Criterio | Modalidad permitida |
|-----------|----------|-------------------|
| A | Nunca tuvo RSS, sin política editorial contra agregación | `captura_portada` |
| B | RSS falla por configuración técnica, sin política en contra | `captura_portada` con user-agent identificable |
| C | RSS retirado deliberadamente como decisión editorial | `no_disponible` hasta autorización explícita |

### Requisitos para activar captura_portada

1. robots.txt permite el crawl
2. Medio clasificado como categoría A o B (nunca C sin autorización)
3. Campo `categoria_acceso` obligatorio en config
4. User-Agent siempre `PrismaBot/1.0 (+URL_proyecto)`
5. Rate limit: máximo 1 request por medio por hora

### Proceso para medios Categoría C

1. Email al equipo editorial: descripción de Prisma, carácter sin ánimo de lucro, enlace a manifiesto/axiomas, solicitud de autorización
2. Si autorizan: modalidad `captura_portada`, transparencia "Autorizado por el medio (fecha)"
3. Si deniegan o no responden en 30 días: `no_disponible` con motivo visible en fuentes.php
4. Cualquier medio puede solicitar exclusión en cualquier momento — retirada en 48 horas

### Derecho de exclusión universal

Declaración pública en `fuentes.php`. Cualquier medio incluido puede solicitar exclusión. Retirada en 48 horas sin discusión.

### Auditoría del corpus

- Matriz ejes × cuadrantes en `validar_feeds.php?mode=salud`
- Revisión manual trimestral: clasificaciones A/B/C, selectores CSS, políticas editoriales
- Log de cambios de corpus en campo `transparencia` con fecha

### Recomendación pre-despliegue

Consulta breve con abogado de propiedad intelectual digital antes de puesta en producción con medios Categoría A/B activos. No bloquea desarrollo ni testing.

---

## Sección 6: Incorporación de Nuevos Medios

### Candidatos inmediatos (RSS nativo)

| Medio | Feed | Cuadrante | Prioridad | Justificación |
|-------|------|-----------|-----------|---------------|
| CTXT | `https://ctxt.es/es/rss.xml` | izquierda | Alta | Generalista progresista. Política institucional + economía crítica. Compensa el mayor agujero actual. |
| La Marea | `https://www.lamarea.com/feed/` | izquierda | Media-alta | Investigativo, laboralismo, corrupción, feminismo. Complementario a CTXT y elDiario.es. |
| Mundo Obrero | `https://mundoobrero.es/feed` | izquierda-populista | Media | Laboralismo, política PCE. Complemento a El Salto. Perfil militante específico. |
| Nueva Revolución | Verificar | izquierda-populista | Baja | Verificar si es medio con redacción editorial o blog de opinión. Si blog, descartar. |

### Criterio de selección de medios

No paridad numérica ni diversidad temática pura. El objetivo es **solape suficiente en los ejes que generan polarización**, para que H_cobertura_mutua y f(framing_divergence) operen sobre señal real.

Ejes polarizantes en España (~12): `politica_partidista`, `inmigracion`, `memoria_historica`, `feminismo_genero`, `economia_laboral`, `vivienda`, `ecologia_clima`, `educacion`, `sanidad`, `autonomias_territorial`, `ue`, `justicia`.

Criterio operativo: para cada eje, al menos un medio por cuadrante debe cubrirlo regularmente.

### Proceso de incorporación

1. Verificar feed técnicamente (`validar_feeds.php --url URL`)
2. Clasificar categoría de acceso (A/B/C)
3. Leer portada 2-3 días para determinar `ejes_cubiertos` reales
4. Rellenar array completo en config (nuevo formato asociativo)
5. Ejecutar ciclo de `escanear.php` solo con ese ámbito
6. Verificar renderizado correcto en `fuentes.php`

### Retrofit de medios existentes

Migración gradual del formato legacy al nuevo formato asociativo:
- Rellenar `perfil_editorial` y `ejes_cubiertos` por medio (tarea manual)
- Migrar notas de transparencia existentes al campo `transparencia`
- No bloqueante: formato legacy funciona indefinidamente
- Recomendación: migrar por ámbito, empezando por España

### Lista inicial de ejes

```
politica_partidista, inmigracion, memoria_historica, feminismo_genero,
economia_laboral, vivienda, ecologia_clima, educacion, sanidad,
autonomias_territorial, ue, justicia
```

Lista no cerrada. Nuevos ejes se añaden orgánicamente cuando se observa polarización recurrente en un tema no cubierto.

---

## Resumen de ficheros afectados

| Fichero | Cambio |
|---------|--------|
| `config.php` | Estructura de fuentes extendida (retrocompatible) |
| `lib/rss.php` | Modificar `rss_fetch_all()` para despacho por modalidad, unificar UA a PrismaBot |
| `lib/fuentes/normalizar.php` | **Nuevo.** `rss_normalizar_fuente()` — compartido entre rss.php, fuentes.php y otros consumidores |
| `lib/fuentes/captura_portada.php` | **Nuevo.** Módulo de captura de portada |
| `lib/fuentes/feed_health.php` | **Nuevo.** Funciones de registro/consulta de salud + creación de tabla en `prisma_logs.db` (usa `prisma_logger_db()`) |
| `escanear.php` | Añadir require condicional de captura_portada + purga de feed_health >90 días |
| `panel.php` | Widget resumen de salud de fuentes |
| `validar_feeds.php` | Nuevo modo `salud` con historial y matriz ejes×cuadrantes |
| `fuentes.php` | Renderizado de modalidad, transparencia, perfil_editorial, declaración de acceso (usa normalizar.php) |
