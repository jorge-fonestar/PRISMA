# Observatorio de la Ventana de Overton — Documento de diseño

> **Estado:** Pendiente de validación
> **Fecha:** 2026-04-23
> **Autor:** Claude (diseño colaborativo con operador)

---

## 0. Principios de diseño innegociables

Estos principios están por encima de cualquier decisión de implementación. Si cualquier detalle del diseño entra en conflicto con ellos, ganan los principios:

1. **El JSON de salida es estrictamente factual.** Solo mediciones, distribuciones, citas textuales y deltas. Cualquier campo de texto interpretativo ("significa que...", "indica una tendencia...", "preocupa que...") queda prohibido. La página renderiza prosa neutra derivada mecánicamente de los datos, no prosa generada libremente por el modelo.

2. **Cartografía, no juicio.** La página describe desplazamientos ("el marco X ha pasado del 12% al 38% en el bloque Y entre 2024 y 2026"), nunca los evalúa como positivos o negativos. El usuario saca conclusiones; Prisma aporta el mapa.

3. **Estrategia combinada contra el efecto ancla.** El primer análisis de cada tema queda archivado como *baseline* de referencia. Todos los análisis posteriores comparan contra esa baseline Y contra el análisis anterior, no solo contra uno de los dos. Adicionalmente, la página siempre muestra distribuciones completas por bloque (izquierda / centro / derecha), nunca "distancia al centro". Esto hace visibles los desplazamientos globales aunque todo el espectro se mueva.

4. **Transparencia radical sobre el método.** La página lleva en cabecera un bloque permanente explicando: qué es la ventana de Overton, qué intenta mostrar esta página, qué no pretende mostrar, con qué datos se construye, qué modelo la genera, cuándo fue el último análisis, link al JSON crudo. El catálogo de temas es auditable públicamente (ver sección 10).

5. **El usuario ve el JSON completo renderizado.** No hay curación editorial intermedia. Esto obliga a que el JSON sea diseñado para ser seguro por construcción (ver principio 1).

6. **El catálogo de temas es en sí una ventana de Overton.** Decidir qué es un tema y cómo se nombra implica decisiones ideológicas. El sistema no busca neutralidad taxonómica (imposible) sino hacer las decisiones transparentes, justificadas y criticables. Cada tema tiene etiquetas paralelas de distintos cuadrantes, y el catálogo completo se publica con prompt, justificaciones y autoevaluación del modelo.

---

## 1. Arquitectura completa

### Unidad atómica: un tema, una ejecución

La unidad de análisis del Observatorio Overton es un **tema individual** (`overton_temas.slug`), no un batch del radar ni un dominio temático. Un análisis = un tema = una llamada Opus = una fila en `overton_analisis`.

**Fundamento:**
- **Ritmo editorial diferenciado:** cada tema se analiza cuando sus datos lo justifican, no cuando toca ejecución periódica.
- **Continuidad narrativa:** cada análisis recibe como contexto el análisis anterior del mismo tema, haciendo el proceso iterativo-acumulativo. Los deltas se enriquecen con evolución respecto al análisis previo, no solo respecto a baseline.
- **Ejecución atómica simple:** una llamada Opus = una fila en BD. No hay proceso multi-paso que pueda romperse a mitad.
- **Iteración de prompts por tema:** se pueden versionar prompts distintos por tema sin reorganizar nada.

### Fundamento epistémico del pre-clustering por dominio

Los ejes ideológicos son específicos de cada campo semántico: "ajuste vs recorte" en economía, "flujo vs invasión" en inmigración. Mezclar dominios produce abstracciones vacías. El pre-clustering por `dominio_tematico` no optimiza el análisis, lo hace posible. Dentro de cada dominio, los temas son ejes de debate más granulares (p.ej. `economia_trabajo` → `vivienda`, `mercado_laboral`, `pensiones`).

### Componentes del sistema

```
┌──────────────────────────────────────────────────────────────────────┐
│  overton-taxonomia.php (CLI/panel)                                   │
│  Genera/regenera catálogo de temas con Opus + Extended Thinking      │
│  Input: todos los artículos APTO + clusters radar relevantes         │
│  Output: catálogo overton_temas con etiquetas paralelas, justif.     │
│  Frecuencia: inicial + regeneración cada 6-12 meses                  │
└───────────────┬──────────────────────────────────────────────────────┘
                │ puebla overton_temas
                ▼
┌──────────────────────────────────────────────────────────────────────┐
│  Fase 1 — escanear.php (ampliado)                                    │
│  Gate Haiku ahora también sugiere tema_slug si cluster encaja        │
│  en catálogo existente. Coste marginal: $0 (misma llamada).          │
│  + Curación manual de tema_slug desde panel                          │
│  + Vista "candidatos a tema nuevo" cuando N clusters sin encajar     │
└───────────────┬──────────────────────────────────────────────────────┘
                │ radar.tema_slug poblado
                ▼
┌──────────────────────────────────────────────────────────────────────┐
│  panel-overton.php (gestión)                                         │
│  ├─ Lista de overton_temas activos con semáforo por tema             │
│  ├─ Botón "Ejecutar análisis" por fila                               │
│  ├─ Vista "Clusters sin tema asignado" con sugerencias               │
│  ├─ Gestión de catálogo (crear tema → pasa por taxonomia.php)        │
│  ├─ Revisión de borradores, publicación, historial por tema          │
│  └─ Gestión de baselines (crear, archivar)                           │
└───────────────┬──────────────────────────────────────────────────────┘
                │ POST: ejecutar análisis para tema X
                ▼
┌──────────────────────────────────────────────────────────────────────┐
│  analisis-overton.php (CLI/panel) — ejecución atómica por tema       │
│                                                                      │
│  1. Recibe tema_slug como parámetro                                  │
│  2. Consultar prerrequisitos (semáforo del tema)                     │
│  3. Cargar análisis anterior del mismo tema (si existe)              │
│  4. Extraer dataset del tema:                                        │
│     ├─ Capa A: articulos APTO con radar.tema_slug = tema             │
│     │   (titular_neutral, resumen, framing_evidence, marcos,         │
│     │    fuentes, fecha, payload)                                    │
│     └─ Capa B: clusters radar con tema_slug = tema                   │
│         que NO tienen articulo APTO                                  │
│         (titular, fecha, bloques, relevancia, framing_divergence)    │
│  5. Verificar N >= overton_min_articulos_por_tema                    │
│  6. Llamar Opus + Extended Thinking con:                             │
│     ├─ Prompt versionado (prompts/overton_v1.txt)                    │
│     ├─ Análisis anterior como contexto (si existe)                   │
│     ├─ Corpus capa A + capa B del tema                               │
│     └─ Budget ET: 16384 tokens                                      │
│  7. Validar JSON: schema, unicidad, trazabilidad                     │
│  8. Si falla: reintentar 1 vez con error como feedback               │
│  9. Calcular deltas contra análisis anterior + baseline activa       │
│  10. Insertar en overton_analisis (estado='borrador')                │
│  11. Si es primer análisis del tema → marcar como baseline del tema  │
└───────────────┬──────────────────────────────────────────────────────┘
                │
                ▼
┌──────────────────────────────────────────────────────────────────────┐
│  Base de datos SQLite                                                │
│                                                                      │
│  overton_temas ──────────── overton_analisis                         │
│  ├─ slug (PK)               ├─ id                                    │
│  ├─ etiquetas_paralelas     ├─ tema_slug (FK)                        │
│  ├─ dominio_tematico        ├─ analisis_anterior_id (FK nullable)    │
│  ├─ justificacion           ├─ baseline_id (FK nullable)             │
│  ├─ decisiones_ambiguas     ├─ payload_json                          │
│  └─ version_catalogo        ├─ estado                                │
│                              └─ coste_usd, tokens_*                  │
│  overton_baselines                                                   │
│  ├─ id                      radar (campo nuevo)                      │
│  ├─ payload_json            ├─ tema_slug (nullable)                  │
│  └─ es_baseline_activa                                               │
└───────────────┬──────────────────────────────────────────────────────┘
                │
                ▼
┌──────────────────────────────────────────────────────────────────────┐
│  overton.php (público)                                               │
│                                                                      │
│  Lee último análisis publicado POR CADA tema activo                  │
│  Renderiza ensayo visual scroll-guiado:                              │
│    Hero → Bloque no-normatividad → Bloque catálogo →                 │
│    Sección por tema (con etiquetas paralelas) →                      │
│    Observaciones globales → Footer metadata                          │
│  Gráficos: SVG inline (sin dependencias externas)                    │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 2. Contrato del JSON de salida

### Tipos y convenciones

- **Fechas:** ISO 8601 (`YYYY-MM-DD` o `YYYY-MM-DDTHH:MM:SS+TZ`)
- **Proporciones:** `float` en [0.0, 1.0], redondeadas a 2 decimales
- **Campos opcionales:** se omiten (no se pasan como `null`), salvo indicación contraria
- **Texto interpretativo:** PROHIBIDO. Si un campo podría contener juicio de valor, no existe

### Schema del JSON de un análisis individual (por tema)

```json
{
  "metadata": {
    "tema_slug": "string — PK del tema en overton_temas",
    "tema_label": "string — etiqueta neutra del tema",
    "dominio_tematico": "string — enum de dominio",
    "periodo_analizado": {
      "desde": "ISO-date",
      "hasta": "ISO-date"
    },
    "analisis_anterior_id": "int|null — id del análisis previo de este tema",
    "baseline_id": "int|null — id de la baseline activa, null si este ES la baseline",
    "n_articulos_capa_a": "int — artículos APTO usados",
    "n_articulos_capa_b": "int — clusters radar adicionales usados",
    "n_medios_distintos": "int",
    "fecha_generacion": "ISO-datetime",
    "modelo": "string — modelo Opus usado (ej: claude-opus-4-7)",
    "extended_thinking_tokens": "int — tokens de thinking consumidos",
    "prompt_version": "string — versión del prompt (ej: v1)"
  },

  "marcos_detectados": [
    {
      "marco_id": "string — identificador único dentro del análisis (ej: marco_01)",
      "marco_label": "string — 1-3 palabras descriptivas neutras. INVARIANTE: reutilizar labels del análisis anterior si el marco persiste",
      "estado_vs_anterior": "string|omitido — computado por PHP: persistente|expandido|contraido|nuevo|extinto. Omitido si no hay análisis anterior",
      "presencia_por_bloque": {
        "izquierda": "float [0,1]",
        "centro": "float [0,1]",
        "derecha": "float [0,1]"
      },
      "evolucion_temporal": [
        {
          "periodo": "string — YYYY-QN o YYYY-MM según granularidad",
          "por_bloque": {
            "izquierda": "float [0,1]",
            "centro": "float [0,1]",
            "derecha": "float [0,1]"
          }
        }
      ],
      "ejemplos": [
        {
          "titular": "string — titular literal del artículo",
          "medio": "string — nombre del medio",
          "fecha": "ISO-date",
          "bloque": "string — izquierda|centro|derecha",
          "cuadrante": "string — cuadrante original (ej: centro-izquierda)"
        }
      ],
      "n_articulos_evidencia": "int — artículos que usan este marco"
    }
  ],

  "pares_contrapuestos": [
    {
      "marco_a_id": "string — ref a marco_id",
      "marco_b_id": "string — ref a marco_id",
      "bloques_marco_a": ["string — bloques donde domina A"],
      "bloques_marco_b": ["string — bloques donde domina B"],
      "separacion": "string — alta|media|baja",
      "evidencia_contraste": [
        {
          "titular_a": "string",
          "medio_a": "string",
          "titular_b": "string",
          "medio_b": "string",
          "fecha_aproximada": "ISO-date"
        }
      ]
    }
  ],

  "deltas_contra_anterior": [
    {
      "marco_id": "string",
      "marco_label": "string",
      "prevalencia_anterior": "float [0,1]",
      "prevalencia_actual": "float [0,1]",
      "delta_puntos": "float — diferencia aritmética",
      "bloque_afectado": "string — bloque con mayor cambio",
      "direccion": "string — expansion|contraccion"
    }
  ],

  "deltas_contra_baseline": [
    {
      "marco_id": "string",
      "marco_label": "string",
      "prevalencia_baseline": "float [0,1]",
      "prevalencia_actual": "float [0,1]",
      "delta_puntos": "float",
      "bloque_afectado": "string",
      "direccion": "string — expansion|contraccion"
    }
  ],

  "marcos_extintos": [
    {
      "marco_label": "string",
      "ultima_prevalencia": "float [0,1]",
      "ultimo_periodo_relevante": "string — YYYY-QN o YYYY-MM",
      "origen": "string — anterior|baseline"
    }
  ],

  "senales_debiles": [
    {
      "descripcion_factual": "string — observación medible, sin juicio. MÁXIMO 30 palabras",
      "fuente_capa": "string — B",
      "n_apariciones": "int",
      "bloques": ["string"]
    }
  ],

  "renombrados_desde_anterior": [
    {
      "marco_label_anterior": "string",
      "marco_label_nuevo": "string",
      "justificacion": "string — MÁXIMO 40 palabras"
    }
  ],

  "fusionados_desde_anterior": [
    {
      "marcos_labels_anteriores": ["string", "string"],
      "marco_label_nuevo": "string",
      "justificacion": "string — MÁXIMO 40 palabras"
    }
  ],

  "divididos_desde_anterior": [
    {
      "marco_label_anterior": "string",
      "marcos_labels_nuevos": ["string", "string"],
      "justificacion": "string — MÁXIMO 40 palabras"
    }
  ],

  "articulos_sin_marco_identificable": {
    "n": "int — artículos del tema que no encajan en ningún marco",
    "porcentaje_del_total": "float [0,1]"
  }
}
```

### Origen de cada campo (Opus vs PHP)

El JSON almacenado en `overton_analisis.payload_json` es un compuesto:

| Campo | Producido por | Notas |
|-------|---------------|-------|
| `metadata` | **PHP** | PHP construye toda la metadata (fechas, conteos, modelo, etc.) |
| `marcos_detectados` | **Opus** | Opus produce la lista completa. Labels estables por invariante de prompt |
| `pares_contrapuestos` | **Opus** | Opus identifica pares y evidencia |
| `senales_debiles` | **Opus** | Opus detecta señales de capa B (≤30 palabras por descripción) |
| `articulos_sin_marco_identificable` | **Opus** | Opus contabiliza artículos sin marco |
| `renombrados_desde_anterior` | **Opus** | Opus declara cambios de label con justificación (≤40 palabras) |
| `fusionados_desde_anterior` | **Opus** | Opus declara fusiones con justificación (≤40 palabras) |
| `divididos_desde_anterior` | **Opus** | Opus declara divisiones con justificación (≤40 palabras) |
| `estado_vs_anterior` | **PHP** | PHP computa mecánicamente: delta ≥0.10 → expandido/contraído; <0.10 → persistente; ausente → extinto/nuevo |
| `deltas_contra_anterior` | **PHP** | PHP computa deltas numéricos, usando `renombrados_desde_anterior` para reconciliar labels |
| `deltas_contra_baseline` | **PHP** | PHP computa deltas numéricos comparando con el análisis más antiguo post-baseline |
| `marcos_extintos` | **PHP** | PHP detecta marcos del anterior/baseline ausentes en el actual (tras reconciliación de labels) |

**Flujo de ensamblaje:**
1. Opus devuelve JSON con `marcos_detectados`, `pares_contrapuestos`, `senales_debiles`, `articulos_sin_marco_identificable`, y opcionalmente `renombrados_desde_anterior`, `fusionados_desde_anterior`, `divididos_desde_anterior`
2. PHP valida el JSON de Opus (schema + límites de longitud)
3. PHP aplica reconciliación de labels: usa `renombrados_desde_anterior` para mapear labels anteriores a nuevos antes de comparar
4. PHP aplica fusiones: suma prevalencias de marcos anteriores fusionados antes de comparar con el nuevo
5. PHP aplica divisiones: marca marcos divididos como "surgido por división" (sin delta directo)
6. PHP construye `metadata`
7. PHP computa `estado_vs_anterior` mecánicamente: delta absoluto en algún bloque ≥0.10 → expandido/contraído; <0.10 → persistente; ausente en actual → extinto; ausente en anterior → nuevo
8. PHP computa `deltas_contra_anterior`, `deltas_contra_baseline`, `marcos_extintos`
9. PHP ensambla el JSON final unificado y lo almacena en `payload_json`

**Invariante de estabilidad de labels:** Opus reutiliza los `marco_label` del análisis anterior siempre que el marco siga existiendo. Si un label debe cambiar, Opus lo declara explícitamente en `renombrados_desde_anterior` con justificación. Esto permite que PHP opere mecánicamente por match de labels, sin juicio semántico. Los renombrados, fusiones y divisiones son eventos auditables: se publican íntegramente en la página pública y en el panel.

### Reglas del schema

| Regla | Descripción |
|-------|-------------|
| Campos obligatorios | `metadata`, `marcos_detectados` |
| Campos opcionales | `pares_contrapuestos`, `deltas_contra_anterior`, `deltas_contra_baseline`, `marcos_extintos`, `senales_debiles`, `articulos_sin_marco_identificable` |
| `deltas_contra_anterior` | Solo presente si `analisis_anterior_id` no es null. Computado por PHP con reconciliación de labels |
| `deltas_contra_baseline` | Solo presente si `baseline_id` no es null Y baseline ≠ análisis anterior. Computado por PHP |
| `marcos_extintos` | Solo presente si hay análisis anterior o baseline con marcos que ya no aparecen. Computado por PHP |
| `senales_debiles` | Solo señales de capa B. Si capa B vacía, se omite. `descripcion_factual` ≤ 30 palabras |
| `estado_vs_anterior` | Computado por PHP mecánicamente (delta absoluto ≥0.10 → expandido/contraído). Solo presente si hay análisis anterior. NUNCA producido por Opus; el prompt lo prohíbe explícitamente |
| `renombrados_desde_anterior` | Producido por Opus. Solo presente si hay cambios de label. Justificación ≤ 40 palabras |
| `fusionados_desde_anterior` | Producido por Opus. Solo presente si hay fusiones. Justificación ≤ 40 palabras |
| `divididos_desde_anterior` | Producido por Opus. Solo presente si hay divisiones. Justificación ≤ 40 palabras |
| Valor incalculable | Se omite el campo. Nunca `null`, nunca valor por defecto |
| Texto interpretativo | PROHIBIDO. Todo campo de texto es cita literal, etiqueta o descripción factual medible |
| `marco_label` | 1-3 palabras descriptivas neutras. Prohibido vocabulario de cuadrante específico |
| `presencia_por_bloque` | Suma de los 3 bloques NO necesariamente 1.0 (un marco puede usarse por múltiples bloques simultáneamente) |
| `n_articulos_evidencia` | Mínimo 3 para que un marco sea incluido (criterio de tema recurrente) |

---

## 3. Prompt del análisis Opus (v1)

Archivo: `prompts/overton_v1.txt`

```
Eres un cartógrafo descriptivo de marcos informativos. Tu trabajo es documentar
cómo los distintos bloques ideológicos (izquierda, centro, derecha) encuadran
un eje de debate específico, y cómo ese encuadre evoluciona en el tiempo.

═══════════════════════════════════════════════════════
REGLAS FUNDAMENTALES — incumplir cualquiera invalida el análisis
═══════════════════════════════════════════════════════

1. SOLO DATOS MEDIBLES. Tu output es un JSON con mediciones, distribuciones,
   citas textuales y deltas. Cualquier campo de texto interpretativo
   ("significa que...", "indica una tendencia...", "preocupa que...") está
   PROHIBIDO. Si dudas si un texto es interpretativo: lo es. Omítelo.

2. TRAZABILIDAD OBLIGATORIA. Cada afirmación numérica debe ser verificable
   contra los artículos de entrada. Si un dato no es verificable desde el
   corpus proporcionado, se omite. Nunca inventes datos ni extrapoles más
   allá de lo que los artículos dicen.

3. CARTOGRAFÍA, NO JUICIO. Describes desplazamientos ("el marco X ha pasado
   del 12% al 38%"), nunca los evalúas como positivos, negativos,
   preocupantes o alentadores.

4. NEUTRALIDAD LÉXICA EN ETIQUETAS. Los `marco_label` deben ser descriptivos
   y neutros (1-3 palabras). Prohibido usar vocabulario de un cuadrante
   específico como etiqueta canónica. Si el marco ES un término de cuadrante
   (ej: "invasión"), la etiqueta lo cita como tal pero no lo adopta como
   descriptor neutral.

═══════════════════════════════════════════════════════
DEFINICIÓN DE TEMA RECURRENTE
═══════════════════════════════════════════════════════

Un tema recurrente es un eje de debate que cumple TODOS estos criterios:
- Presente en al menos 3 artículos del corpus proporcionado
- Artículos separados temporalmente por al menos 2 semanas
- Artículos de al menos 2 medios distintos

Artículos que no encajen en ningún tema recurrente quedan fuera del análisis
de marcos. Contabilízalos en `articulos_sin_marco_identificable`.

═══════════════════════════════════════════════════════
DETECCIÓN DE MARCOS
═══════════════════════════════════════════════════════

1. Usa `framing_evidence` de los artículos como SEMILLA. Cada framing_evidence
   es una señal de cómo un bloque encuadra el tema.

2. Agrupa marcos semánticamente equivalentes. "recorte social" y "recortes en
   servicios públicos" son el mismo marco. "ajuste presupuestario" y "recorte"
   son marcos DISTINTOS (encuadres opuestos del mismo hecho).

3. Nombra cada marco con 1-3 palabras descriptivas neutras.

4. Para cada marco, calcula presencia_por_bloque: proporción de artículos del
   bloque que usan ese marco, sobre el total de artículos del bloque en el tema.

5. Para evolucion_temporal: agrupa por trimestres (YYYY-QN) si el periodo
   cubre ≥6 meses. Si cubre <6 meses, agrupa por mes (YYYY-MM).

═══════════════════════════════════════════════════════
CONTINUIDAD CON ANÁLISIS ANTERIOR — ESTABILIDAD DE LABELS
═══════════════════════════════════════════════════════

Si se proporciona un análisis anterior de este mismo tema:

REGLA CARDINAL: Los marco_label del análisis anterior son LA VERDAD CANÓNICA
de identidad de marcos. Manténlos aunque el vocabulario del corpus actual haya
evolucionado ligeramente, a menos que el cambio sea sustantivo.

1. REUTILIZA LABELS ANTERIORES. Para cada marco del análisis anterior que
   siga existiendo en el corpus actual, usa EXACTAMENTE el mismo marco_label.
   Prefiere reutilizar labels antes que crear nuevos. Un marco nuevo requiere
   que no exista en el análisis anterior ningún label semánticamente equivalente.

2. RENOMBRADOS DECLARADOS. Si un label anterior ya no es representativo
   del marco (cambio sustantivo, no cosmético), decláralo explícitamente:
   renombrados_desde_anterior: [{"marco_label_anterior": "...",
   "marco_label_nuevo": "...", "justificacion": "..."}]
   Justificación OBLIGATORIA, MÁXIMO 40 palabras.

3. FUSIONES DECLARADAS. Si dos marcos del análisis anterior se han
   convergido en uno solo:
   fusionados_desde_anterior: [{"marcos_labels_anteriores": ["...", "..."],
   "marco_label_nuevo": "...", "justificacion": "..."}]
   Justificación OBLIGATORIA, MÁXIMO 40 palabras.

4. DIVISIONES DECLARADAS. Si un marco del análisis anterior se ha
   escindido en dos o más marcos distinguibles:
   divididos_desde_anterior: [{"marco_label_anterior": "...",
   "marcos_labels_nuevos": ["...", "..."], "justificacion": "..."}]
   Justificación OBLIGATORIA, MÁXIMO 40 palabras.

5. Identifica marcos NUEVOS que no estaban en el análisis anterior
   (ni como label directo ni como componente de fusión/división).
   Requieren la misma evidencia mínima (3 artículos, 2 semanas, 2 medios).

6. NO produzcas estado_vs_anterior ni deltas numéricos — los computa
   el sistema orquestador mecánicamente a partir de tus labels y
   transformaciones declaradas.

Las operaciones de transformación (renombrado, fusión, división) son
AUDITABLES: se publican íntegramente. No las uses para disfrazar
cambios arbitrarios de framing.

═══════════════════════════════════════════════════════
SEÑALES DÉBILES (CAPA B)
═══════════════════════════════════════════════════════

Los artículos de Capa B (clusters radar sin análisis APTO) tienen información
limitada: titular, fecha, bloques, framing_divergence. Úsalos SOLO para:
- Detectar marcos emergentes que aún no tienen masa en Capa A
- Confirmar tendencias observadas en Capa A

Las señales débiles van en `senales_debiles` con descripción estrictamente
factual y conteos. Nunca como marcos confirmados.
Cada `descripcion_factual` debe tener MÁXIMO 30 palabras.

═══════════════════════════════════════════════════════
FORMATO DE SALIDA
═══════════════════════════════════════════════════════

Responde ÚNICAMENTE con JSON válido (sin markdown, sin ```), siguiendo
exactamente el schema proporcionado en las instrucciones del sistema.

Campos obligatorios que TÚ produces: marcos_detectados.
Campos opcionales que TÚ produces (si aplica): pares_contrapuestos,
senales_debiles, articulos_sin_marco_identificable,
renombrados_desde_anterior, fusionados_desde_anterior, divididos_desde_anterior.
NO produzcas metadata, estado_vs_anterior, ni deltas — los calcula el sistema.
Campos opcionales: se incluyen SOLO si hay datos para llenarlos.
Campo incalculable: se OMITE. Nunca null, nunca valor por defecto.

Verifica antes de responder:
- Cada marco_id es único dentro del análisis
- Cada marco tiene ≥3 artículos de evidencia
- Cada presencia_por_bloque tiene valores en [0.0, 1.0]
- No hay texto interpretativo en ningún campo
- Cada ejemplo tiene titular literal (no parafraseado) del corpus
- Labels del análisis anterior reutilizados salvo transformación declarada
- Todas las justificaciones de transformación ≤ 40 palabras
- Todas las descripciones de señales débiles ≤ 30 palabras
- No produces metadata, estado_vs_anterior ni deltas numéricos
```

### Parámetros de Extended Thinking

| Parámetro | Valor |
|-----------|-------|
| Budget de thinking tokens | 16384 |
| Justificación | Análisis mensual de 15-50 artículos por tema. Budget generoso para razonamiento profundo sobre evolución de marcos, comparación con análisis anterior, y resolución de ambigüedades de agrupación. Coste único por ejecución. |

### Captura del thinking block

- Se persiste en BD: `overton_analisis.thinking_raw` (TEXT, nullable)
- **NO se renderiza en la página pública** — puede contener texto crudo no apto para publicación
- Accesible desde panel privado para auditoría del razonamiento del modelo
- Mismo tratamiento en `overton_catalogo_versiones.thinking_raw`
- El JSON final validado va en `payload_json` (separado del thinking)

---

## 4. Formato del dataset de entrada a Opus

### Selección de artículos

**Capa A** — artículos analizados en detalle:
```sql
SELECT a.*, r.framing_evidence, r.framing_divergence, r.dominio_tematico,
       r.relevancia, r.fuentes_json, r.tema_slug
FROM articulos a
JOIN radar r ON r.articulo_id = a.id
WHERE a.veredicto = 'APTO'
  AND r.tema_slug = :tema_slug
  AND r.fecha >= :fecha_desde
ORDER BY r.fecha ASC
```

Donde `fecha_desde` = fecha del último análisis del tema (o fecha_primera_aparicion si es el primer análisis).

**Capa B** — clusters radar adicionales (señales débiles):
```sql
SELECT r.titulo_tema, r.fecha, r.fuentes_json, r.relevancia,
       r.framing_divergence, r.framing_evidence, r.h_score
FROM radar r
WHERE r.tema_slug = :tema_slug
  AND r.articulo_id IS NULL
  AND r.relevancia IN ('alta', 'media')
  AND r.fecha >= :fecha_desde
ORDER BY r.fecha ASC
```

### Campos pasados por capa

| Campo | Capa A | Capa B |
|-------|--------|--------|
| titular_neutral / titulo_tema | Si | Si |
| resumen | Si | No |
| framing_evidence | Si | Si (si disponible) |
| framing_divergence | Si | Si |
| fuentes_json (medios, cuadrantes) | Si | Si |
| fecha | Si | Si |
| payload (mapa_posturas, ausencias) | Si | No |

### Umbral de masa mínima

```php
'overton_min_articulos_por_tema' => 15,  // Capa A + Capa B combinados
'overton_min_articulos_capa_a'  => 5,   // Mínimo artículos APTO para evidencia detallada
```

**Doble umbral:**
- Si Capa A + Capa B < 15 → semáforo gris ("datos insuficientes")
- Si Capa A < 5 (pero total ≥ 15) → semáforo amarillo ("evidencia detallada insuficiente"). Protege contra análisis construidos mayoritariamente sobre capa B, que tiene información limitada.
- Ambos umbrales superados → semáforo verde (si también pasa el umbral de días)

Los temas por debajo del umbral NO se ocultan — aparecen en la lista del panel con su N actual y un indicador de color correspondiente.

### Formato del prompt de usuario (una llamada por ejecución)

```
=== TEMA: {tema_slug} ({tema_label}) ===
=== DOMINIO: {dominio_tematico} ===
=== PERIODO: desde {fecha_ultimo_analisis|fecha_primera_aparicion} hasta {fecha_hoy} ===
=== ETIQUETAS PARALELAS: {etiquetas_paralelas_json renderizado} ===

=== ANÁLISIS ANTERIOR DE ESTE TEMA (fecha: {fecha}, id: {id}) ===
{payload_json del análisis anterior, o "No hay análisis anterior de este tema." si es el primero}

=== ARTÍCULOS ANALIZADOS EN DETALLE — CAPA A (N={n}) ===

[Artículo 1]
Titular: {titular_neutral}
Fecha: {fecha}
Medio: {medio} | Cuadrante: {cuadrante} | Bloque: {bloque}
Resumen: {resumen}
Framing evidence: {framing_evidence}
Posturas detectadas: {mapa_posturas resumido — etiqueta + defensores}
Fuentes: {lista de medios y cuadrantes}

[Artículo 2]
...

=== CLUSTERS ADICIONALES — CAPA B (N={n}) ===

[Cluster 1]
Titular: {titulo_tema}
Fecha: {fecha}
Bloques activos: {izq, centro, der}
Relevancia: {relevancia}
Framing divergence: {fd}
Framing evidence: {framing_evidence}

[Cluster 2]
...
```

### Estimación de tokens de entrada

| Componente | Tokens estimados |
|------------|-----------------|
| System prompt (overton_v1.txt) | ~1,200 |
| Análisis anterior (si existe) | ~2,000-5,000 |
| Artículo Capa A (cada uno) | ~300-500 |
| Cluster Capa B (cada uno) | ~80-120 |
| 30 artículos Capa A + 20 Capa B | ~12,000-18,000 |
| **Total estimado por llamada** | **~15,000-24,000** |

Holgadamente dentro de los 200k tokens de Opus. Incluso con 100 artículos, el input no supera 60k tokens.

---

## 5. Lógica de baselines y deltas

### Baselines

Las baselines son **hitos temporales globales del sistema**, no específicas por tema.

```
overton_baselines:
  id              INTEGER PK AUTOINCREMENT
  fecha_creacion  TEXT NOT NULL — ISO-datetime
  periodo_cubierto_desde  TEXT NOT NULL — ISO-date
  periodo_cubierto_hasta  TEXT NOT NULL — ISO-date
  descripcion     TEXT — nota del operador sobre por qué se crea esta baseline
  es_baseline_activa  INTEGER DEFAULT 0 — solo una activa a la vez
  archivada_fecha TEXT — null si activa o nunca archivada
  created_at      TEXT NOT NULL DEFAULT (datetime('now'))
```

### Creación de baselines

- **Primera baseline:** se crea automáticamente cuando el primer análisis Overton se publica. `periodo_cubierto_desde` = fecha más antigua de los artículos del análisis, `periodo_cubierto_hasta` = fecha de ejecución.
- **Baselines posteriores:** creación manual desde panel. El operador decide cuándo el corpus ha cambiado lo suficiente como para establecer un nuevo punto de referencia (orientativamente cada 12-18 meses, pero no automatizado).
- **Archivo:** al crear nueva baseline, la anterior se archiva (`archivada_fecha` = now). Las baselines archivadas son permanentes, nunca se eliminan.

### Cálculo de deltas

Cada `overton_analisis` tiene dos FK:
- `analisis_anterior_id` — el análisis inmediatamente anterior del mismo tema
- `baseline_id` — la baseline activa en el momento de ejecución

Los deltas se calculan **en PHP después de recibir el JSON de Opus**, no por Opus:

1. **Reconciliación de labels:** PHP primero aplica las transformaciones declaradas por Opus:
   - `renombrados_desde_anterior`: mapea label anterior → label nuevo
   - `fusionados_desde_anterior`: suma prevalencias de marcos anteriores fusionados
   - `divididos_desde_anterior`: marca marcos divididos como "surgido por división" (sin delta directo)

2. **Deltas contra anterior:** para cada `marco_label` presente en ambos análisis (tras reconciliación), delta = prevalencia_actual - prevalencia_anterior. Marcos nuevos y extintos se detectan por presencia/ausencia.

3. **`estado_vs_anterior`:** PHP lo computa mecánicamente para cada marco:
   - Delta absoluto en algún bloque ≥ 0.10 y positivo → `expandido`
   - Delta absoluto en algún bloque ≥ 0.10 y negativo → `contraido`
   - Delta < 0.10 en todos los bloques → `persistente`
   - Presente en actual, ausente en anterior (tras reconciliación) → `nuevo`
   - Ausente en actual, presente en anterior (tras reconciliación) → `extinto`

4. **Deltas contra baseline:** si baseline ≠ análisis anterior, se repite el cálculo contra el análisis más antiguo del tema que sea posterior o igual a la fecha de la baseline. Si el tema no existía cuando se creó la baseline, `deltas_contra_baseline` se omite.

**Opus NO produce `estado_vs_anterior` ni deltas numéricos.** Opus produce labels estables + transformaciones declaradas. PHP opera mecánicamente sobre esos datos.

### Marcos nuevos en análisis actual (sin equivalente en baseline)

Se registran en `deltas_contra_baseline` con `prevalencia_baseline: 0.0` y `direccion: "expansion"`. Indica que el marco no existía en el punto de referencia.

### Marcos de baseline ausentes en análisis actual

Van a `marcos_extintos` con `origen: "baseline"`. Indica que el marco dejó de tener masa crítica.

### Refresco de baseline

Parametrizable en config:
```php
'overton_baseline_meses_sugeridos' => 15,  // orientativo, no automático
```

El panel muestra un aviso cuando la baseline activa tiene más de N meses. La decisión de crear nueva baseline es siempre manual.

---

## 6. Sistema de semáforo del panel

### Umbrales configurables

```php
// config.php
'overton_min_articulos_por_tema'   => 15,   // Capa A + B combinados desde último análisis
'overton_min_articulos_capa_a'     => 5,    // Mínimo artículos APTO (capa A) para análisis fiable
'overton_min_dias_desde_ultimo'    => 30,   // días mínimos entre análisis del mismo tema
'overton_baseline_meses_sugeridos' => 15,   // meses antes de sugerir nueva baseline
'overton_catalogo_meses_sugeridos' => 9,    // meses antes de sugerir regeneración del catálogo
'overton_modelo'                   => 'claude-opus-4-7',   // modelo para análisis (configurable para comparativa)
'overton_taxonomia_modelo'         => 'claude-opus-4-7',   // modelo para taxonomía (configurable)
```

### Estados del semáforo (por tema)

| Estado | Color | Condición |
|--------|-------|-----------|
| Sin datos | Gris | Tema nuevo sin artículos asignados, o N = 0 |
| Datos insuficientes | Gris | N total < `overton_min_articulos_por_tema` |
| Evidencia detallada insuficiente | Amarillo | N total >= mínimo PERO Capa A < `overton_min_articulos_capa_a` |
| Tiempo insuficiente | Amarillo | N total >= mínimo Y Capa A >= mínimo PERO días < `overton_min_dias_desde_ultimo` |
| Listo | Verde | N total >= mínimo Y Capa A >= mínimo Y días >= mínimo |
| Nunca analizado | Verde pulsante | Datos suficientes y sin análisis previo |

### Semáforo global en panel principal

En `panel.php` (no solo en `panel-overton.php`), sección de stats del dashboard:

```
Observatorio Overton: {N} temas listos para análisis | {M} temas activos
```

Con link a `panel-overton.php`. Solo visible si hay al menos 1 tema en `overton_temas`.

**Aviso de deuda de regeneración del catálogo:** si la versión activa del catálogo (`overton_catalogo_versiones`) supera `overton_catalogo_meses_sugeridos` (default 9 meses), el panel principal y `panel-overton.php` muestran un aviso: "El catálogo de temas tiene {N} meses. Considere regenerar." No se automatiza, solo se hace visible.

### Cadencia operativa recomendada

El semáforo verde indica que un tema es *analizable*, no que *deba* analizarse. Priorizar análisis de temas con mayor volumen de artículos nuevos desde el análisis anterior sobre temas estables. Un tema con baja actividad puede mantenerse sin análisis durante 3-6 meses sin perder valor narrativo — la estabilidad también es información, y se registra naturalmente en el gap temporal entre análisis.

**Coste operativo estimado según cadencia:**

| Cadencia | Coste estimado/mes |
|----------|-------------------|
| Análisis mensual de 10 temas | ~$20/mes |
| Análisis bimensual de 10 temas | ~$10/mes |
| Análisis selectivo (3-4 temas activos por ciclo) | ~$4-8/mes |

La cadencia final se ajustará tras la Fase 2-bis (comparativa Sonnet vs Opus). Si Sonnet da calidad equivalente, la cadencia puede ser más agresiva por coste ~5x menor.

---

## 7. Schema de BD

### Nuevas tablas

```sql
-- ── Catálogo de temas Overton ──
CREATE TABLE IF NOT EXISTS overton_temas (
    slug                      TEXT PRIMARY KEY,
    etiquetas_paralelas_json  TEXT NOT NULL,    -- JSON array de etiquetas por cuadrante
    dominio_tematico          TEXT NOT NULL,    -- FK lógica al enum de dominios
    descripcion               TEXT NOT NULL,    -- neutra, generada por taxonomia.php
    justificacion_agrupacion  TEXT NOT NULL,    -- por qué estos artículos juntos
    decisiones_ambiguas       TEXT,             -- qué fue difícil de decidir
    autoevaluacion_json       TEXT,             -- autoevaluación del modelo al generar
    fecha_primera_aparicion   TEXT NOT NULL,    -- ISO-date
    version_catalogo          TEXT NOT NULL DEFAULT 'v1.0',
    estado                    TEXT NOT NULL DEFAULT 'propuesto',
    -- estados: propuesto | activo | archivado | fusionado_en:{slug} | dividido_en:{slug1},{slug2}
    -- taxonomia.php inserta como 'propuesto'; operador promueve a 'activo' desde panel
    -- Haiku solo sugiere tema_slug de temas con estado='activo'
    creado_por                TEXT,             -- 'taxonomia_v1' | 'manual' | 'taxonomia_incremental'
    created_at                TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at                TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ── Baselines globales ──
CREATE TABLE IF NOT EXISTS overton_baselines (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha_creacion          TEXT NOT NULL,
    periodo_cubierto_desde  TEXT NOT NULL,
    periodo_cubierto_hasta  TEXT NOT NULL,
    descripcion             TEXT,
    es_baseline_activa      INTEGER NOT NULL DEFAULT 0,
    archivada_fecha         TEXT,
    created_at              TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ── Análisis Overton (uno por tema por ejecución) ──
CREATE TABLE IF NOT EXISTS overton_analisis (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    tema_slug               TEXT NOT NULL REFERENCES overton_temas(slug),
    fecha_ejecucion         TEXT NOT NULL,
    periodo_cubierto_desde  TEXT NOT NULL,
    periodo_cubierto_hasta  TEXT NOT NULL,
    n_articulos_capa_a      INTEGER NOT NULL DEFAULT 0,
    n_articulos_capa_b      INTEGER NOT NULL DEFAULT 0,
    payload_json            TEXT NOT NULL,
    analisis_anterior_id    INTEGER REFERENCES overton_analisis(id),
    baseline_id             INTEGER REFERENCES overton_baselines(id),
    estado                  TEXT NOT NULL DEFAULT 'borrador',
    -- estados: borrador | publicado | archivado | error
    coste_usd               REAL NOT NULL DEFAULT 0,
    tokens_input            INTEGER NOT NULL DEFAULT 0,
    tokens_output           INTEGER NOT NULL DEFAULT 0,
    tokens_thinking         INTEGER NOT NULL DEFAULT 0,
    prompt_version          TEXT NOT NULL DEFAULT 'v1',
    operador                TEXT,
    notas_revision          TEXT,
    error_detalle           TEXT,     -- si estado='error', detalle del fallo
    thinking_raw            TEXT,     -- Extended Thinking capturado. NO se renderiza en público. Solo auditoría panel
    created_at              TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_overton_analisis_tema ON overton_analisis(tema_slug, fecha_ejecucion DESC);
CREATE INDEX IF NOT EXISTS idx_overton_analisis_estado ON overton_analisis(estado);

-- ── Historial de catálogos (regeneraciones completas) ──
CREATE TABLE IF NOT EXISTS overton_catalogo_versiones (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    version             TEXT NOT NULL,           -- v1.0, v2.0, etc.
    fecha_generacion    TEXT NOT NULL,
    payload_json        TEXT NOT NULL,            -- snapshot completo del catálogo
    prompt_version      TEXT NOT NULL,
    modelo              TEXT NOT NULL,
    n_temas             INTEGER NOT NULL,
    cambios_vs_anterior TEXT,                     -- JSON: fusiones, divisiones, nuevos, archivados
    thinking_raw        TEXT,                     -- Extended Thinking capturado. NO se renderiza en público
    coste_usd           REAL NOT NULL DEFAULT 0,
    tokens_input        INTEGER NOT NULL DEFAULT 0,
    tokens_output       INTEGER NOT NULL DEFAULT 0,
    tokens_thinking     INTEGER NOT NULL DEFAULT 0,
    operador            TEXT,
    created_at          TEXT NOT NULL DEFAULT (datetime('now'))
);
```

### Campo nuevo en tabla `radar`

```sql
ALTER TABLE radar ADD COLUMN tema_slug TEXT;
CREATE INDEX IF NOT EXISTS idx_radar_tema_slug ON radar(tema_slug);
```

Migración idempotente en `db.php` siguiendo el patrón existente (try/catch en ALTER TABLE).

---

## 8. Diseño de la página `overton.php`

### Estructura de secciones

```
┌─────────────────────────────────────────────────────────────────┐
│  HEADER (nav global con link "Observatorio" activo)             │
├─────────────────────────────────────────────────────────────────┤
│  HERO                                                           │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Eyebrow: OBSERVATORIO                                     │  │
│  │ H1: La ventana de Overton en los medios españoles          │  │
│  │ Subtítulo: Cartografía de la evolución de marcos           │  │
│  │ informativos por bloque ideológico                         │  │
│  └───────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────┤
│  BLOQUE PERMANENTE DE NO-NORMATIVIDAD                           │
│  (card destacada — ver sección 11 para texto completo)          │
│  Qué es Overton · Qué muestra · Qué NO muestra · Datos ·      │
│  Modelo · Última actualización · Link JSON crudo                │
├─────────────────────────────────────────────────────────────────┤
│  BLOQUE: SOBRE EL CATÁLOGO DE TEMAS                             │
│  Versión · Fecha · Link al catálogo completo ·                  │
│  Link al prompt de taxonomía · Decisiones ambiguas              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  SECCIÓN POR TEMA (repetida N veces)                            │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ H2: Etiquetas paralelas del tema                          │  │
│  │ "El debate sobre aquello que los medios llaman             │  │
│  │  inmigración | flujos migratorios | crisis migratoria"     │  │
│  │                                                            │  │
│  │ Metadata: dominio · N artículos · periodo · último análisis│  │
│  │                                                            │  │
│  │ GRÁFICO: Distribución de marcos por bloque                 │  │
│  │ ┌──────────────────────────────────────────────────────┐   │  │
│  │ │  SVG horizontal stacked bars                         │   │  │
│  │ │  Izquierda: ████ marco1 ██ marco2 █ marco3          │   │  │
│  │ │  Centro:    ██ marco1 ████ marco2 ██ marco3          │   │  │
│  │ │  Derecha:   █ marco1 █ marco2 ██████ marco4          │   │  │
│  │ └──────────────────────────────────────────────────────┘   │  │
│  │                                                            │  │
│  │ MARCOS DETECTADOS (lista)                                  │  │
│  │ · marco_label: presencia por bloque, N artículos           │  │
│  │   Ejemplos: titulares literales con medio y fecha          │  │
│  │   Estado vs anterior: persistente/expandido/etc.           │  │
│  │   Transformaciones: "marco reetiquetado de X a Y porque…" │  │
│  │                                                            │  │
│  │ PARES CONTRAPUESTOS                                        │  │
│  │ · "marco_a" vs "marco_b"                                   │  │
│  │   Bloques: izq+centro vs derecha                           │  │
│  │   Evidencia: titular_a ↔ titular_b                         │  │
│  │                                                            │  │
│  │ GRÁFICO: Evolución temporal (si >1 periodo)                │  │
│  │ ┌──────────────────────────────────────────────────────┐   │  │
│  │ │  SVG line chart por marco × bloque                   │   │  │
│  │ │  Eje X: periodos (Q1, Q2, Q3...)                     │   │  │
│  │ │  Eje Y: prevalencia [0, 1]                           │   │  │
│  │ │  Líneas: una por marco, color por bloque             │   │  │
│  │ └──────────────────────────────────────────────────────┘   │  │
│  │                                                            │  │
│  │ DELTAS (si existen)                                        │  │
│  │ · vs anterior: marco X pasó de 12% a 38% en bloque Y      │  │
│  │ · vs baseline: marco Z no existía en la baseline           │  │
│  │                                                            │  │
│  │ SEÑALES DÉBILES (si existen, capa B)                       │  │
│  │ · Observación factual con N apariciones                    │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                 │
│  (repetir para cada tema con análisis publicado)                │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│  FOOTER METADATA                                                │
│  Temas analizados · Periodo total · Baseline vigente ·          │
│  Link a análisis anteriores · Link al JSON crudo completo       │
├─────────────────────────────────────────────────────────────────┤
│  FOOTER GLOBAL (render_footer_grid + render_footer_bottom)      │
└─────────────────────────────────────────────────────────────────┘
```

### Gráficos: SVG inline

**Sin dependencias externas.** Todos los gráficos son SVG generado en PHP:

1. **Barras horizontales apiladas** (distribución de marcos por bloque): cada bloque es una barra horizontal, segmentos proporcionales a la prevalencia de cada marco. Colores: paleta de 6-8 colores distinguibles, asignados por orden de prevalencia. Leyenda debajo.

2. **Gráfico de líneas** (evolución temporal): ejes X (periodos) e Y (prevalencia 0-1). Una línea por marco, estilo de línea diferenciado por bloque (sólida/punteada/discontinua). Solo se renderiza si hay >1 periodo en `evolucion_temporal`.

Ambos tipos son funciones PHP en `lib/overton_charts.php` que devuelven strings SVG. Sin JavaScript. Sin canvas. Sin dependencias.

### Prosa derivada mecánicamente

La página NO contiene texto generado por el modelo más allá de las etiquetas y citas del JSON. Las frases descriptivas se construyen en PHP con templates:

```php
// Ejemplo: descripción de un delta
"El marco «{marco_label}» pasó del {prev}% al {actual}% en el bloque {bloque} "
. "entre {periodo_anterior} y {periodo_actual}."
```

Esto cumple el principio 1: la prosa es mecánica, no generativa.

---

## 9. Diseño del panel `panel-overton.php`

### Vista principal: lista de temas

```
┌─────────────────────────────────────────────────────────────────────┐
│  OBSERVATORIO OVERTON — Panel de gestión                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─ Baseline activa: v1 (2026-04-23 → 2026-04-23) ── [Gestionar] ─┐│
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │ Tema         │ Dominio    │ Arts │ Días │ Semáf. │ Acción      ││
│  ├──────────────┼────────────┼──────┼──────┼────────┼─────────────┤│
│  │ inmigración  │ inmigrac.  │  23  │  45  │  🟢   │ [Ejecutar]  ││
│  │ vivienda     │ econ_trab  │  18  │  32  │  🟢   │ [Ejecutar]  ││
│  │ ucrania      │ internac.  │   8  │  15  │  ⚫   │ (insuf.)    ││
│  │ pensiones    │ econ_trab  │  31  │  12  │  🟡   │ (esperar)   ││
│  └─────────────────────────────────────────────────────────────────┘│
│                                                                     │
│  Columnas:                                                          │
│  · Tema: slug + label (expandible para ver etiquetas paralelas)     │
│  · Dominio: dominio_tematico abreviado                              │
│  · Arts: N artículos Capa A+B desde último análisis                 │
│  · Días: días desde último análisis (o "nunca")                     │
│  · Semáforo: color según umbrales                                   │
│  · Acción: botón activo solo si semáforo verde                      │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│  CLUSTERS SIN TEMA ASIGNADO                                         │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │ politica_institucional: 12 clusters sin tema                    ││
│  │ economia_trabajo: 8 clusters sin tema                           ││
│  │ internacional: 5 clusters sin tema                              ││
│  │ [Ver detalle] [Ejecutar taxonomía incremental]                  ││
│  └─────────────────────────────────────────────────────────────────┘│
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│  HISTORIAL DE ANÁLISIS                                              │
│  (tabla con todos los overton_analisis, filtrable por tema/estado)  │
│  · ID · Tema · Fecha · Estado · Coste · Tokens · [Ver] [Publicar]  │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│  GESTIÓN DE CATÁLOGO                                                │
│  · Versión actual: v1.0 (fecha)                                     │
│  · [Ver catálogo completo] [Regenerar catálogo]                     │
│  · Historial de versiones                                           │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│  GESTIÓN DE BASELINES                                               │
│  · Baseline activa: id, fecha, periodo                              │
│  · [Crear nueva baseline] [Ver historial]                           │
│  · Aviso si baseline > N meses                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### Flujo de ejecución de un análisis

1. Operador pulsa "Ejecutar" en un tema con semáforo verde
2. Confirmación: "Ejecutar análisis Overton para «{tema_label}»? Coste estimado: $X-Y (Opus + ET)"
3. `analisis-overton.php` se ejecuta con `tema_slug` como parámetro
4. Output en real-time (patrón existente de panel.php con ob_flush)
5. Al terminar: resultado insertado como borrador
6. Panel muestra el borrador con JSON renderizado y botones: "Publicar" / "Descartar" / "Notas"
7. "Publicar" cambia estado a `publicado`, visible en `overton.php`

### Vista de borrador/revisión

Muestra el JSON parseado con rendering visual (mismos gráficos SVG que la página pública) + el JSON crudo en `<pre>` colapsable. El operador revisa antes de publicar. Incluye acceso al `thinking_raw` en sección colapsable para auditoría del razonamiento.

### Vista "Historial taxonómico del tema"

Pestaña en la vista de detalle de cada tema que muestra todas las transformaciones de marcos desde el primer análisis: renombrados, fusiones, divisiones, con fechas y justificaciones. Sirve para auditar la estabilidad de labels y detectar drift.

### Gestión de catálogo: gate manual

La vista de gestión del catálogo incluye:
- Lista de temas `propuestos` pendientes de aprobación, con botón "Activar" por fila
- Vista de etiquetas paralelas, justificación y decisiones ambiguas para cada tema propuesto
- El operador revisa antes de activar. Solo temas `activo` son elegibles para análisis y sugerencia de Haiku

---

## 10. Generación y mantenimiento del catálogo de temas

### Fundamento

El catálogo de temas es en sí mismo una ventana de Overton. Decidir qué es un tema, cómo se nombra, qué se agrupa y qué se separa implica decisiones con carga ideológica. No existe taxonomía neutral. Lo que existe es taxonomía **transparente**: decisiones declaradas, justificadas y criticables.

### Arquitectura del proceso `overton-taxonomia.php`

```
┌──────────────────────────────────────────────────────────────────┐
│  overton-taxonomia.php                                           │
│                                                                  │
│  Modos de ejecución:                                             │
│  · --completo : regeneración total del catálogo desde cero       │
│  · --incremental : añadir temas nuevos sin modificar existentes  │
│                                                                  │
│  Input:                                                          │
│  · Modo completo: todos los artículos APTO + clusters radar      │
│    con relevancia ∈ {alta, media} del histórico completo         │
│  · Modo incremental: clusters sin tema_slug asignado              │
│    + catálogo existente como contexto                            │
│                                                                  │
│  Proceso:                                                        │
│  1. Extraer dataset según modo                                   │
│  2. Pre-agrupar por dominio_tematico (determinista)              │
│  3. Llamar Opus + ET con prompt de taxonomía                     │
│  4. Validar JSON de salida                                       │
│  5. Insertar/actualizar overton_temas                            │
│  6. Registrar versión en overton_catalogo_versiones              │
│  7. Log de ejecución (tokens, coste, thinking capturado)         │
│                                                                  │
│  Output: catálogo actualizado en BD + snapshot en versiones       │
└──────────────────────────────────────────────────────────────────┘
```

### Prompt de taxonomía v1

Archivo: `prompts/overton_taxonomia_v1.txt`

```
Eres un taxónomo de debates públicos. Tu trabajo es identificar los ejes de
debate recurrentes en un corpus de artículos periodísticos y organizarlos
en un catálogo estructurado.

═══════════════════════════════════════════════════════
PRINCIPIOS DE NEUTRALIDAD TAXONÓMICA
═══════════════════════════════════════════════════════

1. NAMING SIN VOCABULARIO DE CUADRANTE. El slug canónico de un tema debe ser
   descriptivo y neutro. Prohibido usar como nombre principal un término que
   pertenece predominantemente a un cuadrante ideológico.
   Correcto: "inmigracion" (descriptivo)
   Incorrecto: "crisis_migratoria" (frame de derecha) o "movilidad_humana" (frame de izquierda)

2. ETIQUETAS PARALELAS OBLIGATORIAS. Cada tema lleva 3-5 etiquetas que
   reflejan cómo lo nombran distintos cuadrantes ideológicos. Esto es parte
   del análisis, no decoración. Las etiquetas hacen visible que el propio
   NOMBRE del debate está en disputa.

   Formato por etiqueta:
   {
     "etiqueta": "término usado",
     "cuadrantes_dominantes": ["cuadrantes donde prevalece"],
     "prevalencia_aproximada": float [0,1] — proporción del corpus que usa este término
   }

3. JUSTIFICACIÓN OBLIGATORIA. Para cada tema, explica por qué estos artículos
   van juntos y no separados. MÁXIMO 40 palabras. La justificación debe ser
   verificable contra el corpus.

4. DECISIONES AMBIGUAS DECLARADAS. Si dudaste entre agrupar o separar,
   entre un nombre u otro, decláralo explícitamente. MÁXIMO 60 palabras.
   Las decisiones ambiguas son información, no debilidad.

5. MASA CRÍTICA MÍNIMA. Un tema requiere:
   - ≥ 5 artículos en el corpus
   - ≥ 3 medios distintos
   - ≥ 2 meses de presencia temporal
   Artículos que no encajan en ningún tema quedan fuera del catálogo.
   Contabilízalos en `articulos_sin_tema`.

6. GRANULARIDAD CORRECTA. Un tema no es:
   - Un actor ("Sánchez", "Feijóo") — los actores participan en temas
   - Un evento puntual ("moción de censura del 15-M") — los eventos son
     instancias de temas
   - Un dominio entero ("política", "economía") — demasiado grueso
   Un tema ES un eje de debate recurrente: "vivienda", "pensiones",
   "relaciones_ue_uk", "regulacion_ia".

═══════════════════════════════════════════════════════
ANTIPATRONES PROHIBIDOS
═══════════════════════════════════════════════════════

- Temas definidos por el actor en el poder ("gobierno de Sánchez")
- Temas definidos por evento puntual sin recurrencia
- Naming emocional ("crisis de...", "amenaza de...", "oportunidad de...")
- Jerarquías solapantes donde un artículo encaja en 2+ temas
- Temas construidos para encajar en una narrativa política predefinida
- Temas-cajón-de-sastre ("otros", "miscelánea", "varios")

═══════════════════════════════════════════════════════
AUTOEVALUACIÓN OBLIGATORIA
═══════════════════════════════════════════════════════

Al final del JSON, responde por escrito a estas tres preguntas:

1. "¿Un lector de cada cuadrante ideológico (izquierda, centro, derecha)
    se sentiría representado por este catálogo? ¿Hay temas que un cuadrante
    consideraría cruciales y que no están?"

2. "¿Hay temas ausentes que el corpus sugiere pero que no alcanzaron
    masa crítica? Listarlos con su N de artículos."

3. "¿Qué decisiones de agrupación o naming podrían parecer sesgadas
    vistas desde un cuadrante específico? Declararlas."

Las respuestas a estas preguntas son parte del JSON de salida y se
publican íntegramente en la página pública.

═══════════════════════════════════════════════════════
FORMATO DE SALIDA
═══════════════════════════════════════════════════════

Responde ÚNICAMENTE con JSON válido (sin markdown, sin ```):

{
  "version": "v1.0",
  "fecha_generacion": "ISO-datetime",
  "n_articulos_analizados": int,
  "n_temas_identificados": int,
  "temas": [
    {
      "slug": "string — identificador canónico, snake_case, neutro",
      "label": "string — etiqueta principal corta y neutra",
      "etiquetas_paralelas": [
        {
          "etiqueta": "string",
          "cuadrantes_dominantes": ["string"],
          "prevalencia_aproximada": float
        }
      ],
      "dominio_tematico": "string — enum de dominio existente en Prisma",
      "descripcion": "string — 1-2 frases neutras describiendo el eje de debate",
      "justificacion_agrupacion": "string — por qué estos artículos van juntos. MÁXIMO 40 palabras",
      "decisiones_ambiguas": "string|null — qué fue difícil de decidir. MÁXIMO 60 palabras",
      "n_articulos": int,
      "n_medios": int,
      "primera_aparicion": "ISO-date",
      "ultima_aparicion": "ISO-date"
    }
  ],
  "articulos_sin_tema": {
    "n": int,
    "porcentaje": float,
    "dominios_predominantes": ["string"]
  },
  "autoevaluacion": {
    "representatividad_cuadrantes": "string — respuesta a pregunta 1",
    "temas_insuficientes": [
      {
        "tema_candidato": "string",
        "n_articulos": int,
        "razon_exclusion": "string"
      }
    ],
    "decisiones_potencialmente_sesgadas": "string — respuesta a pregunta 3"
  }
}
```

### Parámetros de Extended Thinking para taxonomía

| Parámetro | Valor |
|-----------|-------|
| Budget de thinking tokens | 20480 |
| Justificación | Proceso de alta responsabilidad editorial ejecutado rara vez (inicial + cada 6-12 meses). Requiere razonamiento profundo sobre agrupación, naming y evaluación de sesgos propios. |

### Publicación pública del catálogo

La página `overton.php` incluye una sección permanente "Sobre el catálogo de temas":

- Versión y fecha del catálogo vigente
- Prompt completo usado para generarlo (sin censura, link al fichero)
- Modelo y parámetros (modelo, ET budget)
- Justificaciones del catálogo por tema (de `justificacion_agrupacion`)
- Decisiones ambiguas declaradas (de `decisiones_ambiguas`)
- Autoevaluación completa del modelo (las 3 preguntas y respuestas)
- Link a versiones anteriores del catálogo (historial en `overton_catalogo_versiones`)
- Mecanismo de feedback (email del proyecto)

Esto convierte la taxonomía en objeto auditable públicamente, coherente con el axioma A8 (transparencia de límites).

### Mantenimiento del catálogo

1. **Haiku sugiere `tema_slug`** en clasificación de Fase 1. Ampliación del contrato batch:
   - Nuevo campo opcional en output: `"tema_sugerido": "slug"|null`
   - Haiku solo sugiere slugs de temas con `estado='activo'` (no propuestos ni archivados)
   - Si el cluster encaja claramente en un tema activo del catálogo, Haiku devuelve su slug
   - Si no encaja o es ambiguo: null
   - Coste marginal: $0 (misma llamada, tokens de output marginales)

2. **Curación manual desde panel** para asignar clusters sin `tema_slug` o corregir asignaciones.

3. **Sugerencia de tema nuevo:** cuando N clusters sin tema_slug supera un umbral (configurable, default 20), el panel muestra aviso. Crear tema nuevo **siempre pasa por `overton-taxonomia.php --incremental`**: genera etiquetas paralelas y justificación. Los temas se insertan con `estado='propuesto'`. El operador los revisa y promueve a `activo` desde el panel. Solo los temas activos son visibles para Haiku y elegibles para análisis Overton.

4. **Regeneración completa** cada 6-12 meses: `overton-taxonomia.php --completo`. Produce informe de cambios (fusiones, divisiones, emergencias, archivos) que se registra en `overton_catalogo_versiones.cambios_vs_anterior`. Este informe es contenido editorial destacado de la página pública.

5. **Clustering léxico NO como mecanismo primario.** Puede usarse como señal secundaria para detectar candidatos a división (clusters con vocabulario internamente divergente), pero la decisión final siempre pasa por el prompt de taxonomía con sus reglas de neutralidad.

---

## 11. Contenido editorial del bloque permanente

Texto completo para la cabecera de `overton.php` (máximo 200 palabras, tono del manifiesto Prisma):

---

> **Qué es la ventana de Overton**
>
> En ciencia política, la ventana de Overton describe el rango de ideas que una sociedad considera aceptables en un momento dado. Esa ventana se mueve: lo impensable hoy puede ser sentido común mañana, y viceversa.
>
> **Qué muestra esta página**
>
> Cartografía factual. Medimos qué marcos usan los medios de cada bloque ideológico (izquierda, centro, derecha) para hablar de los mismos temas, y cómo esas proporciones cambian con el tiempo. Los datos provienen de los artículos analizados por Prisma.
>
> **Qué NO muestra**
>
> Esta página no evalúa si un desplazamiento es bueno o malo, deseable o peligroso. No prescribe ni diagnostica. Los números describen; el juicio es del lector.
>
> **Método y limitaciones**
>
> Los marcos son identificados por Claude (Opus) sobre artículos auditados. El catálogo de temas es público y auditable. El modelo puede errar en la clasificación de marcos o en la detección de prevalencias. Cada dato es trazable al corpus fuente.
>
> Modelo: {modelo} · Último análisis: {fecha} · [Ver JSON crudo] · [Ver catálogo de temas]

---

*196 palabras.*

---

## 12. Consideraciones de Opus + Extended Thinking

### Función nueva en `lib/anthropic.php`

`anthropic_call()` actual no soporta Extended Thinking. Se necesita una nueva función:

```php
function anthropic_call_extended_thinking(
    string $model,
    string $system,
    string $user_msg,
    int $max_tokens = 16384,
    int $thinking_budget = 16384
): array
```

**Diferencias con `anthropic_call()`:**
- Retorna `array` con `['text' => string, 'thinking' => string, 'usage' => array]` en vez de solo string
- Payload incluye `thinking` block en la API request
- Captura `thinking` block del response para persistencia en BD (`thinking_raw`)
- El `thinking_raw` se guarda en BD para auditoría privada pero NO se renderiza en público
- Mismo control de presupuesto y registro de coste (incluyendo thinking tokens a tasa de output)
- Usa modelo de `overton_modelo` o `overton_taxonomia_modelo` de config (no hardcoded)

### Validación del output

Después de recibir el JSON de Opus:

1. **Parsear JSON** — si falla, reintentar 1 vez con error como feedback
2. **Verificar schema** — campos obligatorios presentes, tipos correctos
3. **Verificar unicidad** — todos los `marco_id` son únicos
4. **Verificar trazabilidad** — `n_articulos_capa_a` en metadata coincide con el input enviado
5. **Verificar límites de longitud** — `descripcion_factual` ≤ 30 palabras; justificaciones de transformación ≤ 40 palabras; `decisiones_ambiguas` de catálogo ≤ 60 palabras. Si se exceden, truncar con warning en el borrador.
6. **Verificar ausencia de texto interpretativo** — heurística: buscar patrones como "indica que", "sugiere que", "preocupante", "alentador" en campos de texto. Si se detectan, flag de warning en el borrador (no rechazo automático — el operador revisa).
7. **Verificar consistencia de transformaciones** — cada label en `renombrados_desde_anterior`, `fusionados_desde_anterior`, `divididos_desde_anterior` debe existir en el análisis anterior o en `marcos_detectados` respectivamente. Si falla, se incluye en el feedback del reintento. Tras segundo fallo, `estado='error'` con `error_detalle` indicando la transformación inconsistente detectada.
8. **Si falla validación:** reintentar 1 vez con el error como feedback en el prompt. Si falla de nuevo, guardar como `estado='error'` con `error_detalle` poblado y alertar en panel.

### Pricing

Ya registrado en `ANTHROPIC_PRICING`:
```php
'claude-opus-4-7' => ['input' => 15.00, 'output' => 75.00]
```

**Nota importante:** Los thinking tokens de Extended Thinking se facturan a tasa de output ($75/MTok para Opus). La función `anthropic_calc_cost` debe ampliarse para aceptar un tercer parámetro `thinking_tokens` y sumarlo al coste de output. El registro en `data/usage.json` debe incluir `thinking_tokens` como campo separado para trazabilidad.

**Coste estimado por análisis de tema** (30 artículos Capa A + 20 Capa B):
- Input: ~20,000 tokens × $15/MTok = $0.30
- Output: ~3,000 tokens × $75/MTok = $0.23
- Thinking: ~16,000 tokens × $75/MTok = $1.20
- **Total estimado: ~$1.70-2.00 por ejecución**

**Coste estimado de taxonomía completa** (300 artículos):
- Input: ~80,000 tokens × $15/MTok = $1.20
- Output: ~5,000 tokens × $75/MTok = $0.38
- Thinking: ~20,000 tokens × $75/MTok = $1.50
- **Total estimado: ~$3.00-3.50 por regeneración**

**Implicación presupuestaria:** Un análisis Overton de un solo tema cuesta ~$1.70-2.00, comparable a 4-5 síntesis Sonnet de Fase 2. La ejecución es manual (no cron), así que el operador controla el gasto. No obstante, `anthropic_check_budget()` debe contabilizar correctamente los thinking tokens para que el daily budget no se exceda silenciosamente.

---

## 13. Plan de implementación por fases

### Fase 0 — Schema y config (no rompe nada)

1. Añadir tablas nuevas en `db.php`: `overton_temas`, `overton_baselines`, `overton_analisis`, `overton_catalogo_versiones`
2. Añadir `tema_slug` a tabla `radar` (ALTER TABLE idempotente)
3. Añadir parámetros Overton a `config.php`
4. Crear directorio `prompts/` con `overton_v1.txt` y `overton_taxonomia_v1.txt`

### Fase 1 — Infraestructura API

1. Añadir `anthropic_call_extended_thinking()` a `lib/anthropic.php`
2. Tests manuales contra la API de Opus con ET

### Fase 2 — Catálogo de temas

1. Implementar `overton-taxonomia.php` (modo completo)
2. Ejecutar primera generación de catálogo
3. Revisar y ajustar catálogo manualmente si necesario
4. Implementar modo incremental

### Fase 2-bis — Comparativa Sonnet vs Opus

1. Ejecutar análisis de 2-3 temas con Sonnet + ET y con Opus + ET (mismos datos de entrada)
2. Comparar outputs manualmente: calidad de detección de marcos, estabilidad de labels, riqueza de pares contrapuestos, calidad de señales débiles
3. Si Sonnet da calidad equivalente → pasa a ser default en `overton_modelo` (coste ~5x menor)
4. Si no → se canoniza Opus
5. Decisión documentada con outputs de ejemplo
6. **Prerequisito:** verificar que Sonnet soporta Extended Thinking antes de diseñar la comparativa

### Fase 3 — Proceso de análisis

1. Implementar `analisis-overton.php`
2. Lógica de preparación de dataset (Capa A + Capa B)
3. Llamada al modelo configurado en `overton_modelo` + validación
4. Lógica de reconciliación de labels (renombrados, fusiones, divisiones)
5. Cálculo mecánico de `estado_vs_anterior` y deltas en PHP
6. Validación de límites de longitud (30/40/60 palabras)
7. Inserción en BD como borrador
8. Lógica de baseline (primera creación automática)

### Fase 4 — Panel de gestión

1. Implementar `panel-overton.php` con vistas de temas, historial, catálogo
2. Integrar botones de ejecución con confirmación
3. Vista de borrador/revisión con rendering + acceso a thinking_raw
4. Historial taxonómico por tema (transformaciones de marcos)
5. Gate manual de catálogo: vista de temas propuestos → botón activar
6. Gestión de baselines
7. Semáforo global en `panel.php` principal (incluye aviso deuda catálogo)

### Fase 5 — Ampliación de Haiku

**Prerequisito:** Requiere que existan temas en estado `activo` en `overton_temas` (promovidos desde `propuesto` mediante el gate manual de Fase 4). Si no hay temas activos, `tema_sugerido` quedará `null` en todas las clasificaciones de Haiku hasta que el operador promueva temas.

1. Ampliar contrato batch de Haiku para incluir `tema_sugerido`
2. Actualizar `lib/gate_haiku.php`
3. Actualizar `escanear.php` para escribir `tema_slug` en radar

### Fase 6 — Página pública

1. Implementar `overton.php` con estructura de secciones
2. Implementar `lib/overton_charts.php` (SVG inline)
3. Prosa mecánica derivada del JSON
4. Rendering de transformaciones: "En el último análisis, el marco X se renombró como Y porque..."
5. Bloque de no-normatividad
6. Sección de catálogo con transparencia (prompt, justificaciones, autoevaluación)
7. Añadir link "Observatorio" a `render_nav()` en `layout.php`

### Fase 7 — Integración y pulido

1. Añadir "Observatorio" al footer grid
2. CSS coherente con theme.php (dark/light mode)
3. Protección de endpoints con autenticación del panel
4. Revisión de rendimiento (SQLite queries, tamaño de payloads)

**Ninguna fase rompe el pipeline existente.** Las fases 0-3 son backend puro. La fase 5 es una ampliación no destructiva del contrato Haiku. La fase 6 es frontend nuevo sin tocar páginas existentes (excepto `layout.php` para el link de nav).

---

*Documento generado: 2026-04-23. Pendiente de validación del operador.*
