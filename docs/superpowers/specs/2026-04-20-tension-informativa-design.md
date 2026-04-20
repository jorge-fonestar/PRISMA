# Algoritmo de Tensión Informativa + Radar

**Fecha**: 2026-04-20
**Estado**: Aprobado por usuario — pendiente de implementación

## Contexto

Prisma selecciona actualmente los temas del día con una fórmula básica: `score = count(artículos) × count(cuadrantes)`. Esto prioriza volumen y diversidad bruta, pero no distingue entre un tema de consenso (todos lo cuentan igual) y uno genuinamente polarizado (cada lado lo encuadra de forma radicalmente distinta).

El Moral Core establece que "la selección no debe ser una decisión, sino un resultado matemático". Este diseño implementa esa visión.

## Objetivos

1. Reemplazar el scoring del curador por un **índice de tensión informativa** basado en señales medibles de polarización editorial
2. Añadir un **triage LLM** ligero (Haiku) para confirmar candidatos y descartar falsos positivos
3. Publicar **todos los temas detectados** (analizados o no) en un listado unificado con transparencia total sobre por qué se seleccionó o descartó cada uno
4. Actualizar todos los **contenidos estáticos** para reflejar el nuevo proceso de selección

## No-objetivos

- No se implementa scraping de comentarios ni acceso a APIs de redes sociales
- No se reemplaza el pipeline de síntesis/auditoría (Sonnet) — solo se mejora qué entra en él
- No se cambia la estructura de la tabla `articulos` existente

---

## 1. Algoritmo de Tensión Informativa

### 1.1 Fórmula

```
H = 0.45 × Asimetría + 0.40 × Divergencia + 0.15 × Varianza
```

H es un valor [0.0, 1.0] que se muestra como porcentaje (ej. 0.85 = 85%).

### 1.2 Señal A — Asimetría de Cobertura (peso 45%)

Mide si ambos lados del espectro cubren el tema o si uno calla.

```
Grupos:
  izq = {izquierda-populista, izquierda, centro-izquierda}
  der = {centro-derecha, derecha, derecha-populista}
  centro = {centro}

izq_n = artículos del cluster en grupo izq
der_n = artículos del cluster en grupo der
total = izq_n + der_n + centro_n

asimetria = abs(izq_n - der_n) / total    // [0.0, 1.0]
```

- Valor 1.0 = solo un lado cubre el tema (máxima tensión / "Comodín del Silencio")
- Valor 0.0 = cobertura perfectamente simétrica
- Si `total == 0`: asimetría = 0

Respaldada por investigación del MIT Media Lab y Harvard (proyecto Media Cloud): la selección de cobertura es la señal más fiable de sesgo editorial.

**Nota sobre ámbitos Europa/Global**: Estos ámbitos tienen menos cuadrantes configurados (solo centro-izquierda, centro, centro-derecha). La fórmula funciona igual — centro-izquierda cuenta como izq, centro-derecha como der — pero con solo 2-3 fuentes por lado, la señal es más ruidosa. Esto es aceptable: el triage Haiku filtra falsos positivos, y la menor diversidad de fuentes en estos ámbitos refleja una realidad (menos polarización mediática disponible para medir).

### 1.3 Señal B — Divergencia Léxica (peso 40%)

Mide si cada lado usa vocabulario distinto para describir el mismo hecho.

```
keywords_izq = unión de extraer_keywords() de artículos en grupo izq
keywords_der = unión de extraer_keywords() de artículos en grupo der

divergencia = 1.0 - jaccard_similarity(keywords_izq, keywords_der)    // [0.0, 1.0]
```

- Reutiliza `extraer_keywords()` y `keywords_similarity()` existentes en `curador.php`
- Si solo hay artículos de un lado: divergencia = 0 (no hay contra qué comparar)
- **Limitación cross-language**: Para Europa/Global, las fuentes son en inglés/francés/alemán. `extraer_keywords()` tiene stopwords solo en español. Titulares en distintos idiomas tendrán divergencia léxica alta por diferencia de idioma, no de framing. Esto es aceptable por ahora: (a) la divergencia tiene peso 40%, no 100%; (b) Haiku filtra falsos positivos; (c) una mejora futura podría añadir stopwords multi-idioma a `extraer_keywords()`.

### 1.4 Señal C — Varianza del Espectro (peso 15%)

Mide la dispersión de las posiciones ideológicas que cubren el tema.

```
Mapa de posiciones:
  izquierda-populista = -3
  izquierda = -2
  centro-izquierda = -1
  centro = 0
  centro-derecha = 1
  derecha = 2
  derecha-populista = 3

posiciones = [posición numérica de cada cuadrante que cubre el tema]
varianza = var(posiciones)    // varianza estadística
varianza_normalizada = min(varianza / 9.0, 1.0)    // 9.0 = varianza máxima (solo extremos {-3,+3})
```

Nota: La varianza máxima teórica ocurre cuando solo los dos extremos cubren el tema ({-3, +3}), dando var = 9.0. La varianza de todo el espectro completo ({-3..+3}) es 4.0. Se usa 9.0 como denominador para normalizar al peor caso real.

### 1.5 Implementación

Modificar `curador_seleccionar()` en `lib/curador.php`:
- Reemplazar `$score = count($cluster) * count($cuadrantes)` por el cálculo de H
- Añadir los sub-scores al array de cada tema: `h_score`, `h_asimetria`, `h_divergencia`, `h_varianza`
- Nueva función privada `calcular_tension()` que recibe un cluster y devuelve los 4 valores

---

## 2. Haiku Triage

### 2.1 Rol

Haiku no puntúa — el score es la fórmula matemática. Haiku **confirma** que la tensión es genuina (no un falso positivo por vocabulario técnico diverso) y **genera una frase explicativa** en lenguaje natural para la web.

### 2.2 Flujo

```
20+ temas detectados por RSS
  → Heurísticas calculan H para todos
  → INSERT todos en tabla radar (haiku_frase = NULL)
  → Filtro: H >= umbral_tension (configurable, default 0.60)
  → Los que pasan: 1 batch call a Haiku
  → UPDATE radar: haiku_frase para cada candidato evaluado
  → Haiku confirma → UPDATE radar: analizado = 1
  → Top articulos_dia (configurable, default 5) → Pipeline Sonnet
  → Tras publicar: UPDATE radar: articulo_id = id del artículo creado
```

**Escritura en dos fases**: Todos los temas se insertan en `radar` con `haiku_frase = NULL` antes de la llamada a Haiku. Tras el triage, se actualizan los registros con la frase y el estado.

**Fallback si Haiku falla** (error de red, respuesta inválida, presupuesto agotado): se salta el triage y se seleccionan los top `articulos_dia` temas por H score bruto. Se loguea el error. Los registros de radar quedan con `haiku_frase = NULL`, y la UI muestra solo el score numérico sin frase explicativa. El pipeline no se detiene.

### 2.3 Prompt de Haiku

Una sola llamada batch. Input: los N temas candidatos con sus titulares agrupados por cuadrante y el score H. Output: para cada tema, confirmación (sí/no) + frase explicativa de una línea.

El prompt numera los temas secuencialmente (1, 2, 3...) y pide al modelo que use esos mismos números en la respuesta. El caller mapea por índice posicional al `radar.id` correspondiente.

Ejemplo de output esperado:
```json
[
  {"tema": 1, "confirma": true, "frase": "Framing radicalmente opuesto entre izquierda y derecha sobre la reforma fiscal"},
  {"tema": 2, "confirma": true, "frase": "Derecha ignora la propuesta; izquierda la celebra masivamente"},
  {"tema": 3, "confirma": false, "frase": "Cobertura amplia pero framing similar entre fuentes"}
]
```

Los temas donde `confirma: false` se marcan en el radar pero no entran al pipeline. Su frase explica por qué.

Los temas donde `confirma: true` también reciben su frase, que se muestra en la web.

**Temas bajo el umbral** (H < umbral_tension) nunca se envían a Haiku. Su `haiku_frase` queda NULL. En la UI se muestra una frase genérica derivada del sub-score dominante: si la asimetría es baja → "Cobertura equilibrada entre cuadrantes"; si la divergencia es baja → "Las fuentes coinciden en vocabulario y enfoque".

### 2.4 Config

Nuevos campos en `config.php`:
```php
'model_triage'    => 'claude-haiku-4-5-20251001',
'umbral_tension'  => 0.60,   // H mínimo para ser candidato a análisis
```

El campo `articulos_dia` ya existe (default 5) y se reutiliza como máximo de temas a analizar en profundidad.

Añadir Haiku al pricing table en `lib/anthropic.php`:
```php
'claude-haiku-4-5-20251001' => ['input' => 0.80, 'output' => 4.00]
```

### 2.5 Coste

- ~3,500 tokens input + ~400 output por batch diario
- $0.0044/día = ~$0.13/mes
- Ahorra más de lo que cuesta al evitar procesar temas de baja tensión con Sonnet

---

## 3. Modelo de Datos

### 3.1 Nueva tabla `radar`

```sql
CREATE TABLE IF NOT EXISTS radar (
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
    created_at      TEXT DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_radar_fecha ON radar(fecha DESC);
CREATE INDEX IF NOT EXISTS idx_radar_score ON radar(h_score DESC);
```

### 3.2 Formato de `fuentes_json`

```json
[
  {
    "medio": "El País",
    "titulo": "Título del artículo RSS",
    "url": "https://elpais.com/...",
    "cuadrante": "centro-izquierda"
  }
]
```

### 3.3 Relaciones

- `radar.articulo_id` → `articulos.id` (nullable, solo si fue analizado)
- Un registro en `radar` con `analizado = 1` siempre tiene `articulo_id` apuntando a un artículo existente
- No se declara FOREIGN KEY en DDL: el registro radar se crea antes que el artículo (INSERT radar → pipeline Sonnet → INSERT artículo → UPDATE radar.articulo_id). Un FK impediría el INSERT inicial.

### 3.4 Aislamiento por ámbito

El pipeline se ejecuta por ámbito (españa, europa, global) de forma independiente. Cada ejecución genera sus propios clusters y registros de radar. Un mismo tema real (ej. "relaciones UE-España") puede aparecer como dos registros de radar con ámbitos distintos — esto es intencional y correcto, ya que las fuentes y cuadrantes son distintos en cada ámbito.

### 3.5 Retención

Los registros de `radar` se limpian automáticamente tras 90 días (alineado con la vida útil del archivo). Se ejecuta un `DELETE FROM radar WHERE fecha < date('now', '-90 days')` al inicio de cada pipeline diario.

### 3.6 Filtros de cluster para radar

El curador actual exige `count(cluster) >= 2` y `count(cuadrantes) >= min_cuadrantes`. Para la población del radar se **relajan estos filtros**: cualquier cluster con >= 2 artículos entra en radar (sin mínimo de cuadrantes). El filtro de cuadrantes mínimos sigue aplicándose solo para determinar qué temas son candidatos al pipeline Sonnet.

---

## 4. UX — Listado Unificado

### 4.1 Index (`index.php`)

El index.php pasa a leer de la tabla `radar` en lugar de `articulos`. Muestra `SELECT * FROM radar WHERE fecha = :today ORDER BY h_score DESC`. Si no hay registros para hoy (pipeline aún no ha corrido), muestra el último día con datos disponibles con una nota "Última actualización: [fecha]".

Los artículos analizados se siguen accediendo via `articulo.php?id=X` (tabla `articulos`). El index solo usa `radar` como índice/listado.

La página `archivo.php` sigue leyendo de `articulos` (solo artículos analizados) — no se modifica.

Listado único de todos los temas del día, ordenado por `h_score` descendente. Cada tema muestra:

- **Círculo SVG progresivo** a la izquierda: anillo que se rellena proporcionalmente al % de tensión, coloreado por nivel (rojo >75%, naranja 50-75%, amarillo 25-50%, gris <25%). Número del % dentro del círculo.
- **Título del tema**: link a `articulo.php?id=X` si analizado, link a `articulo.php?radar=N` si no.
- **Badge ANALIZADO** (verde) si pasó el pipeline completo.
- **Frase de Haiku** como subtítulo en gris.
- **Links a fuentes** coloreados por cuadrante ideológico según este mapa:

```
Mapa de colores por cuadrante:
  izquierda-populista  → #ff4d6d (rojo)
  izquierda            → #ff6b81 (rojo claro)
  centro-izquierda     → #ff9e4d (naranja)
  centro               → #f2f24a (amarillo)
  centro-derecha       → #4dc3ff (azul claro)
  derecha              → #4d9eff (azul)
  derecha-populista    → #a855f7 (púrpura)
```

### 4.2 Artículo (`articulo.php`) — Modo dual

**Routing:**
- `articulo.php?id=X` → busca en `articulos`, renderiza modo analizado
- `articulo.php?radar=N` → busca en `radar`
  - Si `articulo_id` no es null → redirect 301 a `articulo.php?id=X`
  - Si es null → renderiza modo radar

**Modo analizado** — Como ahora, más:
- Círculo SVG de tensión en el header junto a fecha y ámbito
- **Desglose de tensión** con barras de progreso (asimetría, divergencia léxica, varianza del espectro) — se muestra en todos los artículos analizados
- Barra de auditoría Moral Core (sin cambios)
- Resto del artículo (resumen, posturas, ausencias, preguntas) sin cambios

**Modo radar** — Página para temas no analizados:
- Header con fecha, ámbito, círculo SVG
- **Box explicativo**: "Este tema no superó el umbral mínimo de tensión informativa (X%) configurado para activar el análisis multi-postura de Prisma." + frase de Haiku en cursiva
- **Desglose de tensión** con barras de progreso (mismas que modo analizado)
- **Listado de fuentes**: cada una con medio, título del artículo, URL clickeable, y badge de cuadrante
- **Párrafo de cierre**: "Prisma analiza en profundidad los temas con mayor tensión informativa. Este tema no cruza ese umbral — puedes consultar las fuentes directamente para formarte tu propia opinión."

**SEO para modo radar**:
- `<title>`: `[titulo_tema] — Radar Prisma`
- `<meta name="description">`: `Tema detectado el [fecha] con [XX]% de tensión informativa. [N] fuentes de [N] cuadrantes. [haiku_frase o frase genérica].`
- `<meta name="robots" content="noindex, follow">` — las páginas de radar no se indexan (contenido efímero sin análisis propio), pero los links a fuentes sí se siguen.

### 4.3 Círculo SVG — Especificación

Generado server-side con PHP inline (sin JS):

```
Radio: 15, viewBox 36×36, stroke-width 3
Circumferencia: 2π×15 = 94.2
stroke-dashoffset = 94.2 × (1 - score)

Colores:
  score >= 0.75 → #ff4d6d (rojo)
  score >= 0.50 → #ff9e4d (naranja)
  score >= 0.25 → #f2f24a (amarillo)
  score <  0.25 → rgba(255,255,255,0.3) (gris)
```

Estructura HTML del wrapper:
```html
<div style="position:relative; width:36px; height:36px;">
  <svg width="36" height="36" viewBox="0 0 36 36">
    <circle cx="18" cy="18" r="15" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="3"/>
    <circle cx="18" cy="18" r="15" fill="none" stroke="COLOR" stroke-width="3"
            stroke-dasharray="94.2" stroke-dashoffset="OFFSET"
            transform="rotate(-90 18 18)" stroke-linecap="round"/>
  </svg>
  <span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
               font-size:0.65em;font-weight:700;color:COLOR;">XX</span>
</div>
```

Se genera con una función PHP helper `render_circulo_tension($score)` en `lib/layout.php`.

### 4.4 Barras de desglose — Especificación

Tres barras horizontales, una por señal:

```
Label (120px)  [====barra====]  XX%
```

- Barra de fondo: `rgba(255,255,255,0.06)`, height 6px, border-radius 3px
- Barra de relleno: mismo color que el círculo SVG del tema, width = porcentaje de la señal
- Labels: "Asimetría cobertura", "Divergencia léxica", "Varianza espectro"

Se muestra en ambos modos (analizado y radar).

---

## 5. Actualización de Contenidos Estáticos

### 5.1 `manifiesto.php` — Sección `#how`, paso 2

**Titular**: "Detección de tensión informativa"

**Párrafo principal**:
> Nuestro sistema no busca las noticias más importantes ni las más virales. Busca las más tensas: aquellas donde los medios de distintos cuadrantes ideológicos cuentan la misma historia de formas radicalmente distintas — o donde un lado habla y el otro calla. Son los temas que más necesitan una visión multi-postura.

**Párrafo técnico** (en `<details>` o tipografía menor):
> El algoritmo calcula un índice de tensión informativa para cada tema detectado, combinando tres señales matemáticas: la asimetría de cobertura (qué proporción de fuentes de cada lado del espectro cubren el tema — un silencio editorial es tan revelador como un titular), la divergencia léxica (distancia Jaccard entre el vocabulario que usa cada cuadrante para describir el mismo hecho) y la varianza del espectro (dispersión de las posiciones ideológicas que cubren el tema). Investigadores del MIT Media Lab y Harvard (proyecto Media Cloud) han demostrado que la selección de cobertura — qué elige contar cada medio y qué elige ignorar — es la señal más fiable de sesgo editorial, por encima del análisis de sentimiento o del framing textual. El índice de tensión de cada tema es público y verificable en su ficha.

### 5.2 `manifiesto.php` — FAQ "¿Cómo elegís qué noticias cubrir?"

> No las elegimos: las calcula un algoritmo. Cada día, el sistema lee los titulares de más de 28 fuentes de todo el espectro ideológico, agrupa las que hablan del mismo tema, y calcula un índice de tensión informativa para cada uno. Los temas con mayor tensión — donde hay más divergencia entre cómo los cuenta cada lado — son seleccionados automáticamente para análisis. El índice es una fórmula matemática transparente, no una decisión editorial. Puedes ver el porcentaje de tensión y su desglose en cada tema.

### 5.3 `manifiesto.php` — Schema.org FAQPage

Actualizar `acceptedAnswer` de "¿Cómo elegís qué noticias cubrir?":
> No las elegimos editorialmente. Un algoritmo calcula un índice de tensión informativa para cada tema detectado, midiendo la divergencia entre cómo lo cubren medios de distintos cuadrantes ideológicos. Los temas con mayor tensión se analizan automáticamente. El índice es público y verificable en cada tema.

### 5.4 `ia.php` — Punto 2 del proceso

Reemplazar "Selección por transversalidad":
> Detección y triage por tensión informativa — Un algoritmo matemático puntúa cada tema detectado según tres señales: asimetría de cobertura entre cuadrantes ideológicos, divergencia de vocabulario entre fuentes, y dispersión del espectro que lo cubre. Los temas que superan el umbral mínimo configurado son evaluados por un modelo ligero de IA (Claude Haiku) que confirma la tensión genuina y descarta falsos positivos. El score de tensión es 100% auditable: se muestra públicamente en cada tema con el desglose de cada señal.

Añadir a "Modelos utilizados":
> Triage: Claude Haiku (Anthropic) — confirmación rápida de candidatos. Una sola llamada diaria que evalúa todos los temas candidatos en batch. Coste aproximado: medio céntimo de dólar al día.

### 5.5 `fuentes.php` — "Criterios de selección de temas"

Reemplazar los 3 bullets actuales por sección expandida:

**Titular**: "Cómo decide el sistema qué analizar"

**Intro**: Cada día, el sistema lee los RSS de todas las fuentes listadas arriba, agrupa los artículos que hablan del mismo tema, y calcula un índice de tensión informativa para cada uno. Este índice combina tres señales:

**Asimetría de cobertura** — ¿Cuántas fuentes de cada lado del espectro cubren el tema? Si solo un lado habla, hay tensión editorial. Un tema cubierto por 5 medios de derecha y ninguno de izquierda (o viceversa) tiene la máxima asimetría: el silencio es tan editorial como el titular. Esta es la señal con más peso en la fórmula, respaldada por investigadores del MIT Media Lab y Harvard (proyecto Media Cloud), que demostraron que lo que un medio elige cubrir — y lo que elige ignorar — es el indicador más fiable de sesgo editorial.

**Divergencia léxica** — ¿Usan las mismas palabras para contar la misma historia? El sistema extrae las palabras clave de los titulares de cada cuadrante y mide la distancia entre los vocabularios (coeficiente de Jaccard). Si la izquierda dice "recorte" y la derecha dice "ajuste responsable" sobre el mismo hecho, la divergencia es alta. Cuanto más distintas son las palabras, más distinto es el encuadre.

**Varianza del espectro** — ¿Quién cubre el tema? Un tema que solo aparece en los extremos (izquierda-populista y derecha-populista) pero no en el centro tiene un patrón distinto a uno que aparece en todo el espectro. La varianza de las posiciones ideológicas captura esta distribución.

**Cierre**: Los temas que superan el umbral mínimo de tensión configurado son candidatos a análisis completo. De esos, se seleccionan los que mayor tensión presentan hasta el máximo diario configurado. El índice de tensión de cada tema — analizado o no — es público y verificable en su ficha.

### 5.6 `axiomas.php` — Nota introductoria

Añadir tras el párrafo introductorio existente:
> Los 11 axiomas evalúan la calidad del análisis una vez producido. La selección de qué temas analizar se rige por un criterio distinto: el índice de tensión informativa, una fórmula matemática que mide la divergencia editorial entre fuentes. Más información en la página de fuentes consultadas.

---

## 6. Resumen de Ficheros Afectados

| Fichero | Cambio |
|---------|--------|
| `config.php` | Añadir `model_triage`, `umbral_tension` |
| `db.php` | Crear tabla `radar` en inicialización |
| `lib/curador.php` | Reemplazar scoring por fórmula de tensión, nueva función `calcular_tension()` |
| `lib/anthropic.php` | Añadir Haiku al pricing table |
| `lib/common.php` | Integrar triage Haiku en el flujo del pipeline, guardar en tabla radar |
| `index.php` | Nuevo listado unificado con círculos SVG, badges, fuentes |
| `articulo.php` | Modo dual (analizado/radar), barras de desglose en ambos modos |
| `manifiesto.php` | Actualizar paso 2, FAQ, Schema.org |
| `ia.php` | Actualizar proceso, añadir Haiku a modelos |
| `fuentes.php` | Reescribir criterios de selección |
| `axiomas.php` | Añadir nota introductoria con link |

## 7. Restricciones

- PHP 7.x — sin sintaxis PHP 8+ (arrow functions `fn()` ya se usan en el código existente, son PHP 7.4)
- SQLite — sin migraciones complejas, solo `CREATE TABLE IF NOT EXISTS`
- Sin dependencias externas — todo PHP puro
- Budget diario de $4 USD no se ve afectado (Haiku añade $0.005/día)
