# Rediseño del motor de detección de polarización — Documento de diseño

> **Estado:** Aprobado para implementación
> **Fecha:** 2026-04-22
> **Autor:** Claude (diseño colaborativo con operador)

---

## 1. Diagnóstico del sistema actual

### Patologías identificadas

El H-score actual (`lib/curador.php:184`) usa composición aditiva:

```
H = 0.60 × h_asimetria + 0.25 × h_divergencia + 0.15 × h_varianza
```

Problemas concretos sobre datos reales del radar (2026-04-21, ~95 clusters):

1. **`h_asimetria` = 1 en casi todos los temas.** La fórmula `abs(izq - der) / total` mide concentración en un grupo, no ausencia de cobertura en otros. Un tema que solo cubre prensa del corazón da 1.0 igual que un escándalo político silenciado.

2. **`h_divergencia` ≈ 0 en casi todos los temas.** Jaccard sobre tokens crudos no captura framing: dos cuadrantes que cuentan lo mismo con marcos opuestos comparten vocabulario temático.

3. **No existe filtro de relevancia.** Noticias triviales (lotería, deporte, curiosidades) entran al ranking con el mismo peso que noticias con carga ideológica real.

4. **Composición aditiva.** Una sola señal fuerte (asimetría=1) empuja el score a 0.60 aunque divergencia=0 y varianza=0. El rango útil se aplasta entre 0.45 y 0.62.

### Fundamento conceptual

La literatura de comunicación política distingue:

- **Agenda-setting asimétrica**: qué temas cubre cada lado. Solo es polarización significativa cuando el tema tiene carga ideológica.
- **Framing asimétrico**: ambos lados cubren el mismo hecho con marcos cognitivos distintos. Señal más limpia porque requiere solape de cobertura por construcción.

El sistema actual aplica el principio de Benkler ("selección de cobertura como señal de sesgo") sin el filtro implícito de relevancia política que el autor asumía. De ahí los falsos positivos.

### Definición de relevancia para Prisma

**"Temas donde los ejes ideológicos producen narrativas divergentes."**

Incluye: política institucional, economía/trabajo, sanidad/ciencia, tecnología/regulación, cultura/identidad, medio ambiente, educación, inmigración, relaciones internacionales.

Excluye: deportes, sucesos sin lectura ideológica, entretenimiento, loterías, meteorología rutinaria, crónica social, curiosidades.

---

## 2. Arquitectura del nuevo pipeline de scoring

```
RSS feeds
    │
    ▼
┌─────────────────────────────────┐
│  CLUSTERING (sin cambios)       │  Jaccard sobre keywords de titulares
│  curador_seleccionar()          │  Agrupa artículos en clusters ≥2
└──────────────┬──────────────────┘
               │ ~80-100 clusters/escaneo
               ▼
┌─────────────────────────────────┐
│  SEÑALES ESTRUCTURALES          │  Coste: $0 — determinista
│  (por cada cluster)             │
│                                 │
│  H_cobertura_mutua ───────────► │  Solape real entre bloques izq/der/centro
│  H_silencio ──────────────────► │  Señal auxiliar: bloques que callan
│  n_cuadrantes, n_articulos      │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│  PRE-FILTRO DETERMINISTA        │  Coste: $0
│                                 │
│  Lista NEGATIVA ──► DESCARTAR   │  deporte, lotería, farándula,
│    (keywords/patrones)          │  curiosidades, meteorología rutinaria
│                                 │
│  Resto ──────────► BATCH HAIKU  │  Incluye matches de lista positiva
│                                 │  (como hint, no como gate)
└──┬──────────────────────────┬───┘
   │                          │
   ▼                          ▼
 H=0, rel='descartar'   ┌────────────────────────────┐
 (en radar para          │  GATE HAIKU (batch)         │  ~$0.03-0.05/escaneo
  trazabilidad)          │                             │
                         │  Input: clusters con        │
                         │  titulares por cuadrante    │
                         │  + hint lista positiva      │
                         │                             │
                         │  Output por cluster:        │
                         │  · relevancia (4 niveles)   │
                         │  · dominio_tematico (enum)  │
                         │  · framing_divergence (0-3) │
                         │  · framing_evidence (texto) │
                         └─────────────┬──────────────┘
                                       │
                    ┌──────────────────┘
                    ▼
┌─────────────────────────────────┐
│  COMPOSICIÓN H-SCORE            │
│                                 │
│  Gate: relevancia ∈ {alta,media}│
│  Atajo: fd ≥ 2 → rel. confirm. │
│                                 │
│  H = H_cob^α × f(fd)^β         │
│      + bonus silencio condic.   │
│                                 │
│  Si cualquier factor ≈ 0 → H≈0 │
└──────────────┬──────────────────┘
               │
               ▼
          radar table
     (con nuevos campos)
               │
               ▼
       Fase 2 (analizar.php)
          sin cambios
```

### Funciones del diccionario positivo (no actúa como gate)

1. **Metadata al prompt de Haiku**: marca `contains_political_actor: true` por cluster cuando matchea. Ayuda a Haiku a calibrar dominio sin introducir coste.

2. **Verificador de consistencia post-hoc**: si Haiku devuelve `relevancia: baja` sobre un cluster con match en lista positiva fuerte (presidente, Congreso, TC, etc.), se loggea como anomalía de clasificación. No bloquea, alimenta diagnóstico.

### Flujos del pre-filtro

- **DESCARTAR** (~35-45% de clusters): Lista negativa matchea. H_score = 0, `relevancia = 'descartar'`. Se inserta en radar para trazabilidad pero no compite.
- **BATCH HAIKU** (~55-65%): Todo lo demás, incluidos matches de lista positiva. Haiku clasifica con metadata enriquecida.

---

## 3. Definición formal de señales

### 3.1 — `H_cobertura_mutua` (determinista, reemplaza `h_asimetria`)

Mide solape real entre bloques ideológicos. Dados los bloques B = {izq, centro, der}, sea `n_b` el número de artículos del bloque `b`:

```
                    min(n_izq, n_der, n_centro)
cobertura_mutua = ─────────────────────────────── × factor_bloques
                    max(n_izq, n_der, n_centro)

factor_bloques:
  3 bloques activos → 1.0
  2 bloques activos → 0.7
  1 bloque activo   → 0.0  (sin solape posible)
```

Propiedades:
- Loro kea (1 bloque): `0/N × 0.0 = 0.0`
- Feijóo (izq=3, centro=2, der=4): `2/4 × 1.0 = 0.5`
- Tema muy cubierto (izq=5, centro=1, der=5): `1/5 × 1.0 = 0.2`

Rango: [0.0, 1.0]. Valores altos = cobertura equilibrada entre bloques (precondición de polarización medible).

### 3.2 — `f(framing_divergence)` (de Haiku, reemplaza `h_divergencia`)

Haiku devuelve `framing_divergence ∈ {0, 1, 2, 3}`. Se normaliza a [0, 1] con mapeo no lineal:

**Mapeo B (default recomendado, progresivo-suave):**
```
fd=0 → 0.00   monocorde / insuficiente / 1 solo bloque
fd=1 → 0.15   diferencias menores de énfasis
fd=2 → 0.50   marcos claramente distintos
fd=3 → 1.00   marcos ideológicamente opuestos
```

**Mapeo A (alternativa, progresivo-agresivo):**
```
fd=0 → 0.00
fd=1 → 0.25
fd=2 → 0.65
fd=3 → 1.00
```

El mapeo se configura en `config.php` para permitir cambio sin tocar código. La decisión final entre A y B se toma tras calibración empírica (sección 7).

### Contrato de framing_divergence con cobertura parcial

```
REGLAS OBLIGATORIAS (en el prompt de Haiku):

  Si bloques_activos = 1 → fd = 0 obligatoriamente
    (no se puede medir divergencia sin al menos 2 bloques)

  Si bloques_activos = 2 → fd ≤ 2 (cap)
    (evidencia insuficiente para afirmar oposición total con solo 2 puntos de vista)

  Si bloques_activos = 3 → fd ∈ {0, 1, 2, 3} sin restricción
```

### 3.3 — `H_silencio` (señal auxiliar con bonus condicionado)

Mide si un tema políticamente relevante tiene un bloque que calla:

```
Solo se calcula si relevancia ∈ {alta, media}

H_silencio:
  3/3 bloques activos → 0.0  (sin silencio)
  2/3 bloques activos → 0.5  (un bloque calla)
  1/3 bloques activos → 1.0  (dos bloques callan)
```

**Entra en el H-score como bonus condicionado** (no como factor multiplicativo):

```
Si H_silencio > 0 AND H_score_base > 0:
    bonus = γ × H_silencio × relevancia_peso
    H_score = min(H_score_base + bonus, 1.0)

Donde:
    γ = 0.15
    relevancia_peso:
      alta  → 1.0
      media → 0.5
```

Justificación: aditivo condicionado aporta ~0.04-0.08 puntos, suficiente para desempatar pero no para catapultar temas mediocres. El gate `H_score_base > 0` impide que silencio sin cobertura mutua ni framing genere score.

**Alternativa documentada para v2: doble eje.** Separar en Eje X (polarización activa = cobertura × framing) y Eje Y (polarización pasiva = silencio × relevancia). Superior conceptualmente pero requiere dos umbrales, complica UI/UX y ORDER BY. Migrable sin romper schema.

### 3.4 — `dominio_tematico` (de Haiku, campo nuevo)

Enum de 10 valores:
```
politica_institucional | economia_trabajo | sanidad_ciencia |
tecnologia_regulacion | cultura_identidad | medio_ambiente |
educacion | inmigracion | internacional | otros
```

Lo rellena Haiku en la misma llamada de relevancia, sin coste extra. Tres usos:
1. Diagnóstico del radar
2. Diversidad por dominio en muestreo de Fase 2 (evitar que todos los días sean política pura)
3. Auditoría del comportamiento del sistema

---

## 4. Fórmula final del H-score

### Parámetros configurables (en `config.php`)

```php
'scoring_alpha'    => 0.4,    // peso exponencial de cobertura mutua
'scoring_beta'     => 0.6,    // peso exponencial de framing
'scoring_gamma'    => 0.15,   // peso del bonus de silencio
'scoring_mapeo'    => 'B',    // 'A' o 'B' (tabla de conversión fd → f(fd))
'umbral_tension'   => 0.40,   // mínimo H-score para Fase 2
```

### Flujo completo

```
INPUTS:
  H_cob        ∈ [0, 1]           — determinista
  fd           ∈ {0, 1, 2, 3}     — de Haiku (null si gate skipped)
  H_sil        ∈ {0, 0.5, 1.0}    — determinista
  relevancia   ∈ {alta, media, baja, descartar, indeterminada} — de Haiku o fallback
  lista_neg    ∈ {true, false}     — pre-filtro determinista

MAPEO CUADRANTES → BLOQUES (usa constantes existentes de curador.php):
  izq    = {izquierda-populista, izquierda, centro-izquierda}  // PRISMA_GRUPO_IZQ
  centro = {centro}                                             // PRISMA_GRUPO_CENTRO
  der    = {centro-derecha, derecha, derecha-populista}         // PRISMA_GRUPO_DER

PASO 1 — Pre-filtro:
  Si lista_neg = true:
    H_score = 0, relevancia = 'descartar'
    → Guardar en radar, SALTAR a PASO 8

PASO 2 — Señales estructurales:
  Calcular H_cob (cobertura mutua entre bloques)
  Calcular H_sil (bloques que callan)

PASO 3 — Batch Haiku:
  Si gate_haiku_enabled AND presupuesto disponible:
    Enviar clusters supervivientes con titulares por cuadrante + hint lista positiva
    Recibir: relevancia, dominio, fd, evidence
  Sino (fallback por presupuesto agotado o gate deshabilitado):
    relevancia = 'indeterminada'
    fd = null
    dominio = null

PASO 4 — Atajo de framing:
  Si fd ≥ 2 AND relevancia = 'baja':
    relevancia = 'media' (override)
    Log anomalía "FRAMING_OVERRIDE"

PASO 5 — Gate de relevancia:
  Si relevancia ∈ {baja, descartar}:
    H_score = 0
    → SALTAR a PASO 8
  Si relevancia = 'indeterminada':
    H_score = 0 (excluido del ranking hasta reclasificación)
    → SALTAR a PASO 8

PASO 6 — Score base (composición multiplicativa):
  f_fd = mapeo[fd]    // B: {0→0, 1→0.15, 2→0.50, 3→1.0}
  H_base = H_cob^α × f_fd^β

PASO 7 — Bonus de silencio:
  Si H_sil > 0 AND H_base > 0:
    rel_peso = (relevancia == 'alta') ? 1.0 : 0.5
    H_score = min(H_base + γ × H_sil × rel_peso, 1.0)
  Sino:
    H_score = H_base

PASO 8 — Anomaly checks (SIEMPRE se ejecuta, incluso tras gates):
  Si lista_positiva_match AND relevancia ∈ {baja, descartar}:
    Log "ANOMALY_POLITICAL_LOW: political actor in low-relevance cluster"
  Si relevancia = 'indeterminada':
    Log "INFO: cluster pending classification (budget/gate skip)"
```

**Nota sobre el flujo:** Los `SALTAR a PASO 8` en pasos 1 y 5 no son early-returns de función; son saltos que omiten los pasos de cálculo pero siempre ejecutan el anomaly check antes de finalizar. La implementación debe usar flags, no returns prematuros.

### Tabla de escenarios (Mapeo B)

Valores calculados con `H_base = H_cob^0.4 × f(fd)^0.6`, verificados numéricamente.

| Escenario | H_cob | fd | f(fd) | H_base | H_sil | Bonus | **H_final** | Antiguo |
|-----------|-------|----|-------|--------|-------|-------|-------------|---------|
| Loro kea (1 bloq, descartado) | 0.0 | — | — | — | — | — | **0.00** | 0.60 |
| Bonoloto (1 bloq, descartada) | 0.0 | — | — | — | — | — | **0.00** | 0.60 |
| Karol G (descartada lista neg) | — | — | — | — | — | — | **0.00** | 0.60 |
| Mutua Madrid (descartado) | — | — | — | — | — | — | **0.00** | 0.60 |
| Ventiladores techo (descartado) | — | — | — | — | — | — | **0.00** | 0.60 |
| Woody Allen (rel=baja, fd=0) | 0.30 | 0 | 0.0 | 0.0 | 0.5 | 0 | **0.00** | 0.45 |
| Woody Allen (si fd=2, atajo, media) | 0.30 | 2 | 0.5 | 0.41 | 0.5 | +0.04 | **0.45** | 0.45 |
| Feijóo (3 bloq, fd=2, alta) | 0.50 | 2 | 0.5 | 0.50 | 0.0 | 0 | **0.50** | 0.62 |
| Hungría LGTBI (3 bloq, fd=3) | 0.60 | 3 | 1.0 | 0.82 | 0.0 | 0 | **0.82** | 0.60 |
| JD Vance (3 bloq, fd=2) | 0.45 | 2 | 0.5 | 0.48 | 0.0 | 0 | **0.48** | 0.62 |
| Oesía defensa (2 bloq, fd=2, alta) | 0.35 | 2 | 0.5 | 0.43 | 0.5 | +0.08 | **0.51** | 0.62 |
| Nestlé ERE (2 bloq, fd=2, alta) | 0.40 | 2 | 0.5 | 0.46 | 0.5 | +0.08 | **0.53** | ~0.50 |
| Homeopatía (2 bloq, fd=2, alta) | 0.35 | 2 | 0.5 | 0.43 | 0.5 | +0.08 | **0.51** | ~0.50 |
| Huelga AEAT (2 bloq, fd=2, alta) | 0.40 | 2 | 0.5 | 0.46 | 0.5 | +0.08 | **0.53** | ~0.50 |
| OpenAI penal (2 bloq, fd=2, alta) | 0.35 | 2 | 0.5 | 0.43 | 0.5 | +0.08 | **0.51** | ~0.50 |
| Temp. Andalucía (2 bloq, fd=2, media) | 0.30 | 2 | 0.5 | 0.41 | 0.5 | +0.04 | **0.45** | ~0.50 |
| Tema tibio (3 bloq, fd=1) | 0.70 | 1 | 0.15 | 0.28 | 0.0 | 0 | **0.28** | ~0.55 |

Derivaciones de referencia: `0.50^0.4 = 0.758`, `0.50^0.6 = 0.660`, `0.758 × 0.660 = 0.500`.
`0.30^0.4 = 0.618`, `0.618 × 0.660 = 0.408 ≈ 0.41`.

**Separación con umbral 0.40:**
- Todos los falsos positivos: **0.00** (eliminados por lista negativa o gate de relevancia)
- Todos los relevantes genuinos: **≥0.45** (pasan el umbral con margen)
- Tema tibio (fd=1): **0.28** (no pasa — correcto, fd=1 es insuficiente)
- Gap mínimo entre clases: **0.45 - 0.28 = 0.17** (Woody Allen fd=2 vs tema tibio fd=1)
- Gap dentro de relevantes: **0.82 - 0.45 = 0.37** (buen rango dinámico)

---

## 5. Contrato del batch Haiku

### Input

```json
{
  "clusters": [
    {
      "cluster_id": 1,
      "contains_political_actor": true,
      "titulares_por_cuadrante": {
        "izquierda": ["Titular A (Público)", "Titular B (elDiario)"],
        "centro": ["Titular C (EFE)"],
        "derecha": ["Titular D (ABC)", "Titular E (El Mundo)"]
      }
    },
    {
      "cluster_id": 2,
      "contains_political_actor": false,
      "titulares_por_cuadrante": {
        "centro": ["Titular F (20minutos)", "Titular G (El Confidencial)"]
      }
    }
  ]
}
```

### System prompt

```
Eres un clasificador de temas informativos. Evalúas clusters de titulares agrupados
por cuadrante ideológico (izquierda, centro, derecha) y determinas:

1. RELEVANCIA: si el tema genera o puede generar narrativas divergentes entre ejes
   ideológicos. Incluye: política, economía/trabajo, sanidad/ciencia,
   tecnología/regulación, cultura/identidad, medio ambiente, educación, inmigración,
   relaciones internacionales. Excluye: deportes, loterías, entretenimiento,
   curiosidades, meteorología rutinaria, crónica social.

2. DOMINIO TEMÁTICO: categoría principal del tema.

3. FRAMING DIVERGENCE: grado de divergencia en el encuadre entre cuadrantes.
   REGLAS:
   - Si solo 1 bloque ideológico cubre el tema → framing_divergence = 0
   - Si solo 2 bloques cubren → framing_divergence máximo = 2
   - Si 3 bloques cubren → sin restricción (0-3)
   Escala:
   0 = cobertura monocorde, insuficiente para juzgar, o solo 1 bloque
   1 = diferencias menores de énfasis
   2 = marcos claramente distintos entre cuadrantes
   3 = marcos ideológicamente opuestos sobre el mismo hecho

4. FRAMING EVIDENCE: cita breve (<20 palabras) de los marcos detectados, o null.

Si contains_political_actor es true, ten en cuenta que el cluster contiene
referencias a actores políticos o instituciones — calibra relevancia en consecuencia.

Responde SOLO con un JSON array válido, sin markdown ni explicaciones.
```

### Output esperado

```json
[
  {
    "cluster_id": 1,
    "relevancia": "alta",
    "dominio": "politica_institucional",
    "framing_divergence": 2,
    "framing_evidence": "izq: 'recortes sociales'; der: 'ajuste presupuestario responsable'"
  },
  {
    "cluster_id": 2,
    "relevancia": "baja",
    "dominio": "otros",
    "framing_divergence": 0,
    "framing_evidence": null
  }
]
```

### Validación de respuesta

- Parsear JSON; si falla, reintentar 1 vez.
- Verificar que cada `cluster_id` del input tiene respuesta.
- Verificar que `relevancia` ∈ {alta, media, baja, descartar}.
- Verificar que `dominio` ∈ enum definido.
- Verificar que `framing_divergence` ∈ {0, 1, 2, 3}.
- Verificar reglas de cap por bloques_activos.
- Si algún cluster falta en la respuesta: `relevancia = 'media'`, `fd = 1` (conservador).

---

## 6. Lógica de anomaly logging

### Qué casos loggear

| Tipo | Condición | Severidad |
|------|-----------|-----------|
| `ANOMALY_POLITICAL_LOW` | lista_positiva match AND relevancia ∈ {baja, descartar} | warning |
| `ANOMALY_FRAMING_OVERRIDE` | fd ≥ 2 AND relevancia original = 'baja' (atajo aplicado) | info |
| `ANOMALY_FD_CAP_VIOLATION` | Haiku devuelve fd > cap permitido por bloques_activos | error |
| `ANOMALY_MISSING_CLUSTER` | Cluster del input sin respuesta de Haiku | warning |

### Persistencia

Nueva tabla `scoring_anomalies`:

```sql
CREATE TABLE IF NOT EXISTS scoring_anomalies (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha       TEXT NOT NULL,
    radar_id    INTEGER,
    tipo        TEXT NOT NULL,
    detalle     TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_anomalies_fecha ON scoring_anomalies(fecha DESC);
CREATE INDEX IF NOT EXISTS idx_anomalies_tipo ON scoring_anomalies(tipo);
```

### Surfacing en panel

Sección "Anomalías de scoring" en panel.php:
- Tabla con últimas 50 anomalías, coloreadas por severidad.
- Contador "anomalías últimos 7 días" en el dashboard de stats.
- Filtro por tipo.

---

## 7. Calibración y validación empírica

### Etiquetado desde panel.php

Vista "Calibración" en panel.php con:

- **"Siguiente tema sin etiquetar"**: muestra un tema del radar con sus titulares por cuadrante, fuentes, H_score actual.
- **Botones binarios**: "Relevante polarizado" / "No relevante".
- **Contador de progreso**: "47/300 etiquetados (16%)".
- **Persistencia**: tabla `etiquetas_calibracion`:

```sql
CREATE TABLE IF NOT EXISTS etiquetas_calibracion (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    radar_id    INTEGER NOT NULL UNIQUE,
    etiqueta    INTEGER NOT NULL,  -- 1=relevante_polarizado, 0=no
    operador    TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
```

- Estimación realista: 2-3 min/tema con lectura de fuentes, ~10-15h distribuidas en varias sesiones.
- El panel permite etiquetar en ratos muertos sin sesión dedicada.

### Script de calibración (`calibrar.php`)

Grid search sobre:
- α ∈ {0.3, 0.4, 0.5}
- β ∈ {0.5, 0.6, 0.7}
- γ ∈ {0.10, 0.15, 0.20}
- mapeo ∈ {A, B}

Total: 54 combinaciones, evaluables en segundos.

**Métricas de aceptación:**
```
precision@10 ≥ 0.80  (≤2 falsos positivos en top 10)
recall@10    ≥ 0.60  (captura ≥60% de los relevantes del día)
```

Donde k = número de temas que Fase 2 procesaría en un día típico (5-10).

### Persistencia de calibraciones

Tabla `calibraciones`:

```sql
CREATE TABLE IF NOT EXISTS calibraciones (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha           TEXT NOT NULL,
    dataset_size    INTEGER NOT NULL,
    resultados_json TEXT NOT NULL,  -- array completo de 54 combinaciones con métricas
    params_elegidos TEXT NOT NULL,  -- {alpha, beta, gamma, mapeo} del ganador
    precision_at_k  REAL,
    recall_at_k     REAL,
    operador        TEXT,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);
```

Cada ejecución guarda la matriz completa (no solo el ganador). Permite:
- Comparar ejecuciones a lo largo del tiempo.
- Detectar drift conceptual del ecosistema mediático.
- No requiere re-etiquetar desde cero: las etiquetas anteriores persisten, se añaden incrementalmente.

### Monitorización continua post-deploy

- Comparar resultados de triage Haiku (Fase 2) con predicciones del scoring: si Haiku descarta un tema que el scoring dejó pasar, es un falso positivo que escapó.
- Métrica semanal: `falsos_positivos_fase2 / total_fase2`. Si supera 30%, revisar parámetros.
- Dashboard en panel.php: tabla de "últimos 7 días" con H_score, relevancia Haiku, resultado triage Fase 2.

---

## 8. Autojustificación económica del gate Haiku

### Asunciones

| Parámetro | Valor |
|-----------|-------|
| Clusters por escaneo | ~95 |
| % descartados por lista negativa | ~40% → 38 clusters |
| Clusters al batch Haiku | ~57 |
| Tokens input por cluster (titulares + prompt) | ~200 tokens |
| Tokens output por cluster | ~50 tokens |
| Tokens system prompt (una vez) | ~300 tokens |
| Escaneos por día | 6 (cada 4h) |

### Cálculo

```
Input total por batch:
  300 (system) + 57 × 200 = 11,700 tokens

Output total por batch:
  57 × 50 = 2,850 tokens

Coste Haiku por batch (precios 2026):
  Input:  11,700 × $0.80/MTok = $0.0094
  Output:  2,850 × $4.00/MTok = $0.0114
  Total por batch: ~$0.021

Coste diario (6 escaneos):
  6 × $0.021 = $0.13/día

Con Batch API (descuento 50%, latencia 24h aceptable):
  $0.065/día
```

### Coste evitado

```
Coste de un falso positivo que llega a Fase 2:
  Triage Haiku: ~$0.005
  Si pasa triage → Síntesis Sonnet: ~$0.15
  Si pasa síntesis → Auditoría: ~$0.15
  Total máximo por FP: ~$0.31

Falsos positivos actuales estimados por día: 3-5

Coste evitado diario: 3 × $0.31 = $0.93

ROI del gate: $0.93 evitados / $0.13 gastados = 7.2x
Con Batch API: $0.93 / $0.065 = 14.3x
```

**El gate se paga solo evitando ~0.4 falsos positivos por día.** Con los datos actuales del radar (~6 falsos positivos evidentes en los 95 clusters), el ROI es holgado.

### Dentro del presupuesto

```
daily_budget_usd = $4.00
Coste gate Haiku = $0.13/día (sin Batch API)
Coste Fase 2 típico = $0.50-1.50/día
Total = $0.63-1.63/día ≪ $4.00
```

### Fallback si presupuesto agotado

Si `daily_budget_usd` está cerca del límite, el gate Haiku se salta. Los clusters en zona gris quedan con:
- `relevancia = 'indeterminada'`
- `framing_divergence = NULL`
- `dominio_tematico = NULL`
- `H_score = 0` (no compiten en el ranking)

**No se calcula un H-score parcial.** Sin framing_divergence, la fórmula multiplicativa daría un score basado solo en cobertura, que es exactamente la patología que este rediseño corrige. Los clusters indeterminados esperan al siguiente ciclo de escaneo donde el presupuesto esté disponible.

Los clusters descartados por lista negativa sí se procesan normalmente (no requieren Haiku). Los clusters con match en lista positiva fuerte quedan como `indeterminada` igualmente — la lista positiva no es gate.

---

## 9. Cambios de schema

### Tabla `radar` — columnas nuevas

```sql
ALTER TABLE radar ADD COLUMN h_cobertura_mutua REAL;
ALTER TABLE radar ADD COLUMN h_framing REAL;          -- f(framing_divergence) normalizado
ALTER TABLE radar ADD COLUMN h_silencio REAL;
ALTER TABLE radar ADD COLUMN framing_divergence INTEGER;  -- valor crudo 0-3 de Haiku
ALTER TABLE radar ADD COLUMN framing_evidence TEXT;
ALTER TABLE radar ADD COLUMN relevancia TEXT;           -- alta|media|baja|descartar|indeterminada
ALTER TABLE radar ADD COLUMN dominio_tematico TEXT;
ALTER TABLE radar ADD COLUMN scoring_version TEXT DEFAULT 'v1';  -- para migración
```

### Columnas deprecadas (no eliminadas)

- `h_asimetria` → se sigue escribiendo para compatibilidad, con el valor de `H_cobertura_mutua`. Label en UI cambia a "Cobertura mutua".
- `h_divergencia` → se sigue escribiendo con el valor de `f(framing_divergence)`. Label cambia a "Divergencia de framing".
- `h_varianza` → se escribe con 0.0. Señal eliminada del cálculo pero columna preservada para no romper queries existentes.

### Migración de datos existentes

Los registros con `scoring_version IS NULL` o `= 'v1'` y campos nuevos NULL se tratan como "scoring legacy". El panel los muestra normalmente con la nota "Scoring v1 (legacy)". No se recalculan — cuando se re-escanee, los nuevos clusters sobreescriben con scoring v2.

**Manejo de NULLs en queries:** Todas las queries que filtren por columnas nuevas deben manejar registros legacy:
```sql
-- Correcto: excluye legacy rows sin scoring v2
WHERE h_cobertura_mutua IS NOT NULL AND h_score >= :umbral

-- Para vistas mixtas (panel): usar COALESCE
SELECT COALESCE(h_cobertura_mutua, h_asimetria) AS metric_1,
       COALESCE(h_framing, h_divergencia) AS metric_2,
       COALESCE(h_silencio, h_varianza) AS metric_3,
       COALESCE(scoring_version, 'v1') AS version
FROM radar
```

Las funciones de rendering (`render_barras_tension`) reciben los valores ya resueltos desde PHP; el COALESCE se aplica al extraer datos, no en el template.

### Tablas nuevas

- `scoring_anomalies` (sección 6)
- `etiquetas_calibracion` (sección 7)
- `calibraciones` (sección 7)

### Índice nuevo

```sql
CREATE INDEX IF NOT EXISTS idx_radar_relevancia ON radar(relevancia);
```

---

## 10. Cambios de config

### Nuevos parámetros en `config.php`

```php
// ── Scoring v2 ──────────────────────────────────────────────
'scoring_alpha'       => 0.4,       // exponente cobertura mutua
'scoring_beta'        => 0.6,       // exponente framing
'scoring_gamma'       => 0.15,      // peso bonus silencio
'scoring_mapeo'       => 'B',       // 'A' o 'B'
'umbral_tension'      => 0.40,      // ajustado de 0.55 a 0.40

// ── Gate Haiku ──────────────────────────────────────────────
'gate_haiku_enabled'  => true,      // false = skip gate, solo señales estructurales
'gate_haiku_batch_api'=> false,     // true = usar Batch API (50% descuento, 24h latencia)
'gate_haiku_cache'    => true,      // persistir clasificación, no reclasificar si no cambia
                                    // Cache key: hash(titulo_tema + sorted cuadrantes activos)
                                    // Invalidación: si el cluster gana nuevos artículos en cuadrantes
                                    // distintos a los del cache (cambian bloques activos), se reclasifica.
                                    // TTL: 48h — un tema clasificado ayer no se reclasifica hoy salvo
                                    // cambio de composición. Los registros radar ya tienen la clasificación
                                    // persistida; el cache evita llamadas Haiku redundantes en re-escaneos.

// ── Listas de filtrado ──────────────────────────────────────
'lista_negativa'      => [...],     // keywords/patrones → descartar
'lista_positiva'      => [...],     // actores políticos/instituciones → hint a Haiku
```

### Algoritmo de matching del pre-filtro

```
1. Se aplica sobre el titulo_tema del cluster (el titular representativo).
2. El titulo se normaliza: lowercase, sin acentos (misma función extraer_keywords).
3. Match por WORD BOUNDARY: cada entrada de la lista se busca como palabra(s)
   completa(s), no como substring. "open" matchea "mutua madrid open" pero NO
   matchea "openai". Implementación: preg_match con \b.
4. Multi-palabra: "formula 1", "camp nou" requieren las palabras adyacentes.
5. Case-insensitive (ya normalizado).
6. Si CUALQUIER entrada de la lista negativa matchea → bucket DESCARTAR.
7. Si CUALQUIER entrada de la lista positiva matchea → flag contains_political_actor.
   (No es gate, solo hint.)
```

### Lista negativa (draft inicial, refinable)

```php
'lista_negativa' => [
    // Deportes (resultados, retransmisiones, fichajes)
    'laliga', 'champions', 'premier league', 'fichaje', 'jornada',
    'penalti', 'futbol', 'baloncesto',
    'formula 1', 'moto gp', 'ciclismo',
    'camp nou', 'bernabeu', 'mestalla', 'mutua madrid open', 'atp', 'wta',
    // Lotería / azar
    'bonoloto', 'primitiva', 'euromillones', 'loteria', 'sorteo',
    'numero premiado',
    // Entretenimiento / farándula
    'concierto', 'gira mundial', 'alfombra roja', 'look de',
    'red carpet', 'coachella', 'reality', 'gran hermano', 'eurovision',
    // Curiosidades / virales
    'curiosidad', 'no creeras', 'verdad sobre',
    // Meteorología rutinaria (no climática)
    'prevision meteorologica', 'temperaturas hoy', 'lluvias para',
],
```

**Keywords eliminadas del draft original por riesgo de false positives:**
- `liga` → matchearía "liga de derechos humanos", "ligar"
- `gol` → substring risk en contextos no deportivos
- `open` → matchearía "OpenAI", "open source", "open government"
- `mundial` → matchearía "guerra mundial", "crisis mundial"
- `olimpiada` → matchearía "olimpiada matemática" (educación)
- `entrenador` → demasiado genérico
- `tenis` → se cubre con `mutua madrid open`, `atp`, `wta`
- `estreno`, `pelicula`, `serie` → matchearían "serie de atentados", "estreno de legislatura"
- `actuara` → matchearía "actuará el fiscal", "actuará la policía"
- `comprobar` → demasiado genérico
- `hack` → matchearía ciberseguridad (relevante)
- `ola de calor` → puede ser cambio climático (zona gris → Haiku decide)
- `viral` → matchearía "virus viral" en contexto sanitario
- `gordo` → matchearía "el gordo de la patronal" (contexto político)
- `outfit` → baja frecuencia en RSS españoles, no merece la pena

### Lista positiva (draft inicial)

```php
'lista_positiva' => [
    // Instituciones
    'congreso', 'senado', 'parlamento', 'tribunal constitucional',
    'tribunal supremo', 'audiencia nacional', 'gobierno', 'moncloa',
    'comision europea', 'parlamento europeo', 'otan', 'onu', 'fmi',
    // Cargos
    'presidente', 'ministro', 'consejero', 'alcalde', 'comisario',
    'fiscal', 'juez', 'magistrado',
    // Partidos España
    'psoe', 'pp', 'vox', 'sumar', 'podemos', 'erc', 'junts',
    'pnv', 'bildu', 'ciudadanos',
    // Actores recurrentes
    'sanchez', 'feijoo', 'abascal', 'diaz', 'puigdemont',
    'trump', 'biden', 'macron', 'von der leyen',
    // Conceptos policy
    'presupuestos', 'decreto', 'ley organica', 'reforma',
    'regulacion', 'sancion', 'embargo', 'tratado',
],
```

---

## 11. Validación contra casos de prueba

### Falsos positivos actuales — deben caer a H=0

| Tema | Mecanismo de descarte | H_final |
|------|----------------------|---------|
| "Un loro kea con discapacidad..." | 1 bloque → H_cob=0, relevancia=descartar | **0.00** |
| "Comprobar Bonoloto..." | Lista negativa (bonoloto, numero premiado) | **0.00** |
| "Karol G actuará..." | Lista negativa (concierto) | **0.00** |
| "Mutua Madrid Open 2026: dónde ver..." | Lista negativa (mutua madrid open) | **0.00** |
| "Esta es la verdad sobre ventiladores..." | Lista negativa (verdad sobre) + relevancia=descartar | **0.00** |
| "Ya hay fecha para rodaje nueva película..." | Haiku gate (relevancia=baja, entretenimiento puro) | **0.00** |

### Relevantes genuinos — deben superar umbral 0.40

| Tema | H_cob | fd | H_base | Bonus | **H_final** |
|------|-------|----|--------|-------|-------------|
| Feijóo decálogo 300.000M | 0.50 | 2 | 0.50 | 0 | **0.50** |
| JD Vance retrasa viaje Pakistán | 0.45 | 2 | 0.48 | 0 | **0.48** |
| Justicia Europea vs Hungría LGTBI | 0.60 | 3 | 0.82 | 0 | **0.82** |
| Oesía fondo europeo defensa | 0.35 | 2 | 0.43 | +0.08 | **0.51** |
| Hongrie droit de l'Union | 0.60 | 3 | 0.82 | 0 | **0.82** |

### Casos ampliados — relevancia alta con fd ≥ 2

| Tema | Dominio | H_cob | fd | H_sil | Rel. | **H_final** |
|------|---------|-------|----|-------|------|-------------|
| Nestlé ERE 301 trabajadores | economia_trabajo | 0.40 | 2 | 0.5 | alta | **0.53** |
| Huelga Agencia Tributaria | economia_trabajo | 0.40 | 2 | 0.5 | alta | **0.53** |
| Homeopatía es placebo | sanidad_ciencia | 0.35 | 2 | 0.5 | alta | **0.51** |
| Investigación penal OpenAI | tecnologia_regulacion | 0.35 | 2 | 0.5 | alta | **0.51** |
| Récord temperaturas Andalucía | medio_ambiente | 0.30 | 2 | 0.5 | media | **0.45** |

**Nota sobre temperaturas Andalucía:** 2 bloques activos (izq + centro, der calla → H_sil=0.5). Relevancia `media` porque el framing climático es contextual. Bonus = 0.15 × 0.5 × 0.5 = 0.038. H_final = 0.408 + 0.038 = **0.45**. Cruza umbral si el encuadre tiene carga ideológica; si se cubre como meteorología rutinaria, Haiku clasificaría fd=0 y no entraría.

### Caso ambiguo documentado: Woody Allen

**Escenario A — Sin carga ideológica (default):** Woody Allen rueda película en Madrid. Solo medios de entretenimiento cubren. Haiku clasifica `relevancia: baja`, `fd: 0`. → H_score = 0.00.

**Escenario B — Con carga ideológica:** Si aparece framing de cancelación ("acusado de abuso") vs rehabilitación ("genio del cine"), Haiku detecta `fd: 2`. El atajo promueve `relevancia: baja → media`. H_score se calcula: `0.30^0.4 × 0.50^0.6 = 0.618 × 0.660 = 0.408`. Bonus silencio (2 bloques, media): `0.15 × 0.5 × 0.5 = 0.038`. H_final = `0.41 + 0.04 = 0.45`. Cruza el umbral.

**Mecanismo:** El diseño no necesita decidir a priori si Woody Allen es relevante. La relevancia emerge del framing detectado. Si los medios lo cubren como noticia de entretenimiento → descartado. Si lo cubren con marcos ideológicos opuestos → entra al radar por la propia polarización.

---

## 12. Cambios en UI/rendering

### Barras de tensión (`render_barras_tension`)

Actualizar labels:
- "Asimetría cobertura" → "Cobertura mutua"
- "Divergencia léxica" → "Divergencia de framing"
- "Varianza espectro" → "Silencio editorial"

Los valores se mapean desde los nuevos campos:
- Barra 1: `h_cobertura_mutua` (antes `h_asimetria`)
- Barra 2: `h_framing` (antes `h_divergencia`)
- Barra 3: `h_silencio` (antes `h_varianza`)

### Frase genérica (`tension_frase_generica`)

Actualizar lógica para usar nuevas señales:
```
Si relevancia = 'descartar': "Tema sin carga ideológica detectada"
Si fd = 0: "Cobertura insuficiente para medir divergencia"
Si fd = 1: "Diferencias menores de énfasis entre fuentes"
Si fd ≥ 2 y H_cob < 0.3: "Framing divergente con cobertura limitada"
Default: usar haiku_frase si disponible
```

### Badge de dominio temático

Nuevo badge opcional junto al ámbito (españa/europa/global) mostrando el `dominio_tematico` con icono o color. Implementación en v2 si no es trivial.

---

## 13. Plan de implementación por fases

### Fase 1 — Schema y scaffolding (no rompe nada)

1. Migración de schema: añadir columnas nuevas a `radar` (ALTER TABLE con defaults NULL).
2. Crear tablas nuevas (`scoring_anomalies`, `etiquetas_calibracion`, `calibraciones`).
3. Añadir parámetros nuevos a `config.php` con defaults.
4. Crear `lib/scoring.php` con funciones puras: `calcular_cobertura_mutua()`, `calcular_silencio()`, `calcular_h_score_v2()`, `aplicar_lista_negativa()`.
5. Crear `lib/diccionarios.php` con listas negativa y positiva.
6. Tests manuales: ejecutar funciones puras contra datos conocidos.

**Compatibilidad:** `escanear.php` sigue usando scoring v1. Los nuevos campos quedan NULL.

### Fase 2 — Gate Haiku y scoring v2

1. Crear `lib/gate_haiku.php`: batch prompt, parsing, validación, cache.
2. Integrar en `escanear.php`: después del clustering, ejecutar scoring v2 en paralelo al v1.
3. Escribir ambos scores en radar (v1 en campos legacy, v2 en campos nuevos).
4. Logging por componente: H_cob, fd, H_sil, relevancia, dominio, H_final.
5. Anomaly logging funcional.

**Compatibilidad:** `analizar.php` sigue leyendo `h_score` (campo existente). Se actualiza `h_score` con el valor v2 cuando el scoring v2 está activo. Flag `scoring_version = 'v2'` en cada registro.

### Fase 3 — UI y panel

1. Actualizar `render_barras_tension()` para usar nuevos campos (con fallback a legacy).
2. Actualizar `tension_frase_generica()`.
3. Sección "Anomalías" en panel.
4. Vista de etiquetado para calibración.
5. Badge de relevancia/dominio en index.php y articulo.php.

### Fase 4 — Calibración

1. Implementar `calibrar.php` con grid search.
2. Acumular 250-300 temas etiquetados (distribuido en el tiempo).
3. Ejecutar calibración, ajustar α/β/γ/mapeo.
4. Subir umbral de Fase 2 basado en resultados.

### Fase 5 — Limpieza (opcional, post-validación)

1. Eliminar código de scoring v1 en `curador.php`.
2. Dejar de escribir campos legacy (`h_asimetria`, `h_divergencia`, `h_varianza`) una vez que el panel no los necesite.
3. Considerar migración a doble eje (v2 de silencio).

---

*Documento generado: 2026-04-22. Pendiente de validación empírica antes de ajustar parámetros finales.*
