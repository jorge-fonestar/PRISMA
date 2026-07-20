# Proyecto Prisma

**Rompiendo las paredes de tu burbuja digital**

Documento de especificación completa — v2.0
Abril 2026

---

## Índice

1. [Visión y contexto](#1-visión-y-contexto)
2. [Arquitectura del proyecto](#2-arquitectura-del-proyecto)
3. [El estándar Moral Core aplicado](#3-el-estándar-moral-core-aplicado)
4. [Estructura del artefacto](#4-estructura-del-artefacto)
5. [Fuentes de información](#5-fuentes-de-información)
6. [Hoja de ruta por pasos](#6-hoja-de-ruta-por-pasos)
7. [Stack técnico](#7-stack-técnico)
8. [Web y publicación](#8-web-y-publicación)
9. [Legal y gobernanza](#9-legal-y-gobernanza)
10. [Presupuesto y métricas](#10-presupuesto-y-métricas)
11. [Riesgos y mitigaciones](#11-riesgos-y-mitigaciones)
12. [Decisiones de diseño registradas](#12-decisiones-de-diseño-registradas)

---

## 1. Visión y contexto

### 1.1 El problema

Los algoritmos de recomendación de las plataformas digitales han creado cámaras de eco: espacios donde cada usuario recibe únicamente información que refuerza sus sesgos previos. Esto ha producido tres efectos sociales graves:

- **Erosión del pensamiento crítico**: el usuario deja de contrastar porque su entorno informativo nunca le contradice.
- **Polarización sin precedentes**: los grupos ideológicos se perciben cada vez más incompatibles porque consumen realidades informativas distintas.
- **Deterioro del debate democrático**: sin un suelo compartido de hechos, la deliberación pública se vuelve imposible.

### 1.2 La propuesta

Prisma es un **sintetizador de la inteligencia colectiva humana** que presenta la realidad desde múltiples ángulos de forma simultánea. No es un medio de comunicación tradicional. No tiene editorial. No te dice qué pensar. Es un **cartógrafo de posturas** que muestra simultáneamente las tres o más caras de cada debate relevante.

### 1.3 Pilares

| Pilar | Descripción |
|---|---|
| **Neutralidad por diseño** | Basado en el estándar Moral Core, con auditoría automatizada de sesgos en cada publicación |
| **Visión 360°** | Cada tema se presenta con un mapa de posturas enfrentadas, permitiendo al lector entender la complejidad del debate |
| **Accesibilidad universal** | Web simplificada + canal de Telegram + email (en fases posteriores) |
| **Transparencia radical** | Todas las fuentes citadas, la auditoría Moral Core visible, el proceso 100% explicado |

### 1.4 Naturaleza del proyecto

- **Promotor**: proyecto personal e independiente
- **Identidad pública**: anónimo ("Equipo Prisma")
- **Ánimo de lucro**: no. Donaciones voluntarias en fases futuras
- **Automatización**: 100% generado y auditado por agentes de IA
- **Intervención humana**: nula en el proceso editorial
- **Idioma**: español
- **Ámbito**: política española, europea y global
- **Volumen diario**: 5 noticias/día
- **Hora de publicación**: 17:00 hora local España (horario de verano: 18:00)
- **Licencia de contenido**: Creative Commons BY-SA 4.0

### 1.5 Qué NO es Prisma

- No es un medio de comunicación con redacción
- No es un agregador pasivo tipo Google News
- No es un fact-checker (aunque cita fuentes verificables)
- No es un verificador de veracidad: es un cartógrafo de posturas
- No emite opinión editorial
- No recomienda qué pensar
- No prioriza algorítmicamente en función del usuario (sin personalización)

---

## 2. Arquitectura del proyecto

### 2.1 Flujo general

```
┌─────────────────────────┐
│  Fuentes (RSS oficiales)│
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│  Agente Curador         │  ← Paso 2 (automático)
│  (selecciona 5 temas)   │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│  Agente Sintetizador    │  ← Paso 1 (manual, luego auto)
│  (Claude Sonnet 4.6)    │
│  Genera artefacto JSON  │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│  Agente Auditor         │  ← Paso 1 (manual, luego auto)
│  (Claude Opus 4.7)      │
│  Evalúa 11 axiomas      │
└────────────┬────────────┘
             │
       ┌─────┴─────┐
  APTO │           │ RECHAZO
       ▼           ▼
┌──────────┐  ┌──────────┐
│ Publica  │  │ Descarta │
│ vía API  │  │ + log    │
└────┬─────┘  └──────────┘
     │
     ▼
┌─────────────────────────┐
│  Web PHP + JSON         │
│  (renderizado público)  │
└─────────────────────────┘
```

### 2.2 Agentes (descripción funcional)

#### Agente Curador (Paso 2)
- Lee los RSS de las fuentes definidas
- Agrupa artículos por tema mediante clustering de similitud
- Filtra temas con cobertura en ≥3 cuadrantes ideológicos distintos
- Selecciona los 5 temas más relevantes del día (criterio: frecuencia × diversidad)
- Entrega al Sintetizador un paquete por tema con 8-12 artículos fuente

#### Agente Sintetizador
- Recibe un paquete de fuentes sobre un tema
- Genera el artefacto con las 5 secciones canónicas
- Aplica el prompt sistémico Moral Core (7 principios operativos)
- Modelo: **Claude Sonnet 4.6**
- Justificación: mejor relación coste/calidad para síntesis multi-fuente

#### Agente Auditor
- Recibe el artefacto generado + las fuentes originales
- Evalúa los 11 axiomas Moral Core
- Emite veredicto JSON estructurado
- Modelo: **Claude Opus 4.7**
- Justificación: máxima calidad en evaluación crítica y detección de sesgos sutiles
- **Independencia**: context separado del Sintetizador para evitar sesgo de confirmación

### 2.3 Decisión de infraestructura: API directa vs Managed Agents

**Decisión adoptada: API directa de Anthropic con SDK Python.**

**Razonamiento:**

| Criterio | API directa | Managed Agents |
|---|---|---|
| Coste base | Solo tokens (~640 €/año) | Tokens + 0,07 €/h runtime (~715 €/año) |
| Duración del pipeline | 2-3 min → innecesario | Diseñado para agentes de horas/días |
| Estado persistente | No se necesita | Ventaja clave, no aplica aquí |
| Sandboxing | No se necesita | Ventaja clave, no aplica aquí |
| Complejidad integración | Mínima | Moderada |

Managed Agents aporta valor cuando hay estado complejo, ejecución de código arbitrario o pipelines largos. Prisma no tiene ninguno de estos requisitos, por lo que el overhead no se justifica.

---

## 3. El estándar Moral Core aplicado

### 3.1 Prompt sistémico del Sintetizador

Los 7 principios operativos que rigen la generación:

1. **Diversidad obligatoria**: presenta al menos 3 posturas distintas sobre cada tema, incluyendo las que personalmente puedas considerar erróneas.
2. **Simetría lingüística**: usa el mismo registro emocional para todas las posturas. Si describes una con "advierte", no describas otra con "denuncia" o "clama".
3. **Atribución explícita**: toda afirmación fáctica disputada debe estar atribuida a una fuente concreta. Prohibido "los expertos dicen".
4. **Separación hecho/opinión**: marca visualmente qué es hecho verificable y qué es interpretación.
5. **Incertidumbre honesta**: si los datos son parciales o contradictorios, dilo. No rellenes huecos con inferencias.
6. **Evita el encuadre oculto**: el orden, el espacio y los adjetivos transmiten juicio. Mantén proporciones equivalentes entre posturas.
7. **Sin conclusión prescriptiva**: no cierres con "lo razonable sería…". Cierra con las preguntas abiertas que quedan.

### 3.2 Los 11 axiomas del Auditor

El Auditor evalúa cada artefacto generado contra los siguientes axiomas, emitiendo un `pass/fail` individual y un veredicto global:

| # | Axioma | Qué verifica |
|---|---|---|
| **A1** | Pluralidad de posturas | ¿El artefacto identifica ≥3 posturas distintas de forma explícita? |
| **A2** | Pluralidad de fuentes | ¿Se citan fuentes de al menos 3 cuadrantes ideológicos distintos? |
| **A3** | Simetría de extensión | ¿Ninguna postura ocupa >50% del espacio total ni <15%? |
| **A4** | Simetría léxica | ¿El lenguaje usado para cada postura es equivalente en carga emocional? |
| **A5** | Atribución verificable | ¿Toda afirmación fáctica disputada tiene fuente concreta enlazada? |
| **A6** | Distinción hecho/opinión | ¿Los elementos marcados como "hecho" son verificables y los marcados como "postura" son opiniones? |
| **A7** | Ausencia de conclusión prescriptiva | ¿El texto evita recomendar qué pensar o hacer? |
| **A8** | Transparencia de límites | ¿Se mencionan explícitamente los puntos de incertidumbre o datos faltantes? |
| **A9** | Ausencia de omisión crítica | ¿Hay alguna postura mayoritaria en el debate público que no esté recogida? |
| **A10** | Coherencia con fuentes | ¿Cada postura se corresponde con lo que las fuentes citadas realmente dicen? (anti-alucinación) |
| **A11** | Ausencia de sesgo geopolítico de bloque | ¿El artefacto evita favorecer narrativas de un bloque geopolítico específico en temas internacionales? |

### 3.3 Formato de salida del Auditor

```json
{
  "veredicto": "APTO | REVISIÓN | RECHAZO",
  "puntuacion_global": 0.91,
  "axiomas": {
    "A1": { "pasa": true, "evidencia": "Identifica 4 posturas distintas..." },
    "A4": { "pasa": false, "evidencia": "Asimetría léxica en párrafo 3: 'denuncia' vs 'sostiene'" }
  },
  "recomendacion": "Regenerar con instrucción: equilibrar léxico del párrafo 3",
  "version_estandar": "MC-1.0"
}
```

### 3.4 Reglas de publicación

- **APTO** (≥10/11 axiomas pasan) → publicación automática
- **REVISIÓN** (8-9/11 axiomas pasan) → regeneración automática con feedback del Auditor. Máximo 2 intentos. Si al tercer intento sigue en REVISIÓN, se publica marcado como tal o se descarta (decisión pendiente).
- **RECHAZO** (<8/11 axiomas pasan) → descarte del tema + log en `/rechazados/` para análisis posterior

---

## 4. Estructura del artefacto

### 4.1 Las 5 secciones canónicas

Cada artefacto publicado contiene exactamente estas 5 secciones, en este orden:

1. **Titular neutral**: reformulación del tema sin carga emocional ni adjetivación valorativa.
2. **Resumen**: 3-4 líneas que describen el tema de forma factual y sin posicionamiento.
3. **Mapa de posturas**: tabla con al menos 3 perspectivas enfrentadas. Cada postura incluye:
   - Etiqueta descriptiva de la postura
   - Quién la defiende (actores, partidos, medios, corrientes)
   - Por qué la defienden (argumentos principales)
   - **Al menos una fuente enlazada** (coherente con axiomas A5 y A10)
4. **Lo que no se está diciendo**: ángulos ausentes en la cobertura dominante. Identificación explícita de omisiones, silencios o puntos ciegos del debate público.
5. **Preguntas para pensar**: 2-3 preguntas abiertas dirigidas al lector crítico. No preguntas retóricas; preguntas genuinas sin respuesta implícita.

### 4.2 Contrato de datos JSON

```json
{
  "id": "2026-04-20-001",
  "fecha_publicacion": "2026-04-20T17:00:00+02:00",
  "ambito": "españa | europa | global",
  "titular_neutral": "...",
  "resumen": "...",
  "mapa_posturas": [
    {
      "etiqueta": "...",
      "defensores": ["..."],
      "argumentos": ["..."],
      "fuentes": [
        {
          "titulo": "...",
          "medio": "...",
          "url": "...",
          "cuadrante": "centro-izquierda"
        }
      ]
    }
  ],
  "ausencias": ["..."],
  "preguntas": ["...", "...", "..."],
  "auditoria_moralcore": {
    "veredicto": "APTO",
    "puntuacion": 0.91,
    "axiomas_detalle": {
      "A1": true, "A2": true, "A3": true, "A4": true,
      "A5": true, "A6": true, "A7": true, "A8": true,
      "A9": true, "A10": true, "A11": true
    },
    "version_estandar": "MC-1.0"
  },
  "fuentes_consultadas_total": 12
}
```

---

## 5. Fuentes de información

### 5.1 Acceso: solo APIs y RSS oficiales

- Se usan exclusivamente RSS públicos y APIs oficiales
- Solo se utilizan titulares, fragmentos y metadatos (uso legítimo de feed público)
- Se cita siempre la fuente original con enlace directo
- Nunca se republica el texto íntegro del artículo
- Se respeta `robots.txt` y se implementa rate limiting (máx. 1 req/s por dominio)

### 5.2 Matriz de fuentes por cuadrante ideológico

#### España

| Cuadrante | Medios |
|---|---|
| Izquierda | Público, elDiario.es |
| Centro-izquierda | El País, InfoLibre |
| Centro / independiente | Newtral, Maldita.es, EFE |
| Centro-derecha | ABC, The Objective |
| Derecha | El Mundo, La Razón |
| Derecha populista | Libertad Digital, El Debate |

#### Europa

Politico Europe, Euronews, Le Monde, The Guardian, Der Spiegel

#### Global

Reuters, AP News, BBC, Al Jazeera English

### 5.3 Reglas de selección de temas

Un tema solo se considera candidato si cumple:

- Aparece en ≥3 cuadrantes ideológicos distintos de la matriz
- Tiene cobertura sustantiva (no mención marginal)
- Es actualidad política (no crónica rosa, deportes, etc.)

La selección final de los 5 temas diarios se basa en:
- Frecuencia de aparición entre fuentes
- Diversidad de cuadrantes que lo cubren
- Relevancia política declarada

---

## 6. Hoja de ruta por pasos

**Filosofía**: avance incremental, priorizando mínimo esfuerzo y mínimo coste hasta validar cada fase.

### Paso 1 — Generador manual

**Objetivo**: script que recibe una noticia y devuelve un artefacto Moral Core publicable, invocado manualmente por el operador.

**Flujo**:
```
Operador → [URL o texto] → Script → [JSON artefacto] → endpoint web → Publicado
```

**Componentes**:

1. **Script Python único** (`prisma.py`):
   - `sintetizar(noticia)` → Sonnet 4.6 con prompt Moral Core → artefacto
   - `auditar(artefacto, noticia)` → Opus 4.7 con 11 axiomas → veredicto
   - Lógica de decisión: APTO → publica, REVISIÓN → 1 reintento, RECHAZO → descarta

2. **Publicación vía endpoint web**:
   - `POST https://[dominio]/api/ingest`
   - Header: `X-API-Key: [clave]`
   - Body: JSON del artefacto
   - Validación de API key + estructura → escritura a `/data/artefactos/YYYY-MM-DD-NNN.json`

3. **Frontend mínimo PHP**:
   - `index.php`: lista artefactos del directorio, orden descendente por fecha
   - `articulo.php?id=...`: renderizado individual
   - `ingest.php`: endpoint de escritura
   - Diseño sobrio, sin frameworks

4. **Landing manifiesto** (página estática):
   - Explica el problema de las cámaras de eco
   - Describe el objetivo social del proyecto
   - Transparencia sobre el origen 100% IA

5. **Páginas legales estáticas**:
   - Aviso legal
   - Política de privacidad
   - Política de cookies
   - Aviso de IA (página dedicada)

**Uso**:
```bash
python prisma.py --url "https://elpais.com/articulo-xyz"
python prisma.py --texto "..." --contexto "..."
```

**Entregables**:
- [ ] Documento de prompts (Sintetizador + Auditor + esquema JSON)
- [ ] Script `prisma.py`
- [ ] `ingest.php`, `index.php`, `articulo.php`
- [ ] Landing manifiesto
- [ ] Páginas legales

**Coste**: ~0,32 €/noticia. Uso manual esporádico → céntimos al mes.

---

### Paso 2 — Trigger automático

**Prerrequisito**: Paso 1 funcionando bien y generando artefactos de calidad consistente.

**Objetivo**: selección automática diaria de 5 temas relevantes y ejecución desatendida del pipeline.

**Qué se añade**:

1. **Agente Curador** (`curador.py`):
   - Lee los RSS configurados en `sources.yaml`
   - Clustering simple de artículos por similitud de titulares
   - Filtra temas con cobertura ≥3 cuadrantes
   - Selecciona los 5 temas más relevantes del día
   - Genera paquetes de 8-12 artículos por tema

2. **Orquestador diario** (`run_daily.py`):
   - Ejecuta el Curador
   - Por cada tema: `sintetizar()` + `auditar()`
   - Publica los APTOS vía el endpoint del Paso 1

3. **Trigger cron** en el hosting:
   ```
   0 17 * * * /usr/bin/python3 /home/tu/prisma/run_daily.py
   ```

4. **Sistema de logging**:
   - Log estructurado por ejecución
   - Email resumen diario: publicados, rechazados, coste del día, axiomas que más fallan

5. **Archivo navegable** en la web:
   - Paginación por fecha
   - Filtro por ámbito

**Entregables**:
- [ ] `sources.yaml`
- [ ] `curador.py`
- [ ] `run_daily.py`
- [ ] Sistema de logging + email
- [ ] Página de archivo

**Coste**: ~1,75 €/día = ~53 €/mes.

---

### Paso 3 — Promoción y donaciones

**Prerrequisito**: Paso 2 operativo ≥30 días con calidad consistente y métricas estables.

**Alcance** (se definirá en detalle cuando llegue el momento):

- Canal Telegram como notificador
- Donaciones anónimas (Liberapay + BTCPay Server)
- Difusión en comunidades afines
- Página "Cómo funciona" detallada
- Posible dashboard público de auditorías

**Por ahora fuera del alcance.**

---

## 7. Stack técnico

### 7.1 Backend

| Componente | Tecnología |
|---|---|
| Lenguaje principal | Python 3.11+ |
| SDK IA | `anthropic` oficial |
| Modelos | Claude Sonnet 4.6 (síntesis) + Claude Opus 4.7 (auditoría) |
| Gestión de fuentes (Paso 2) | `feedparser` para RSS |
| Clustering temas (Paso 2) | Embeddings + similitud coseno (scikit-learn o equivalente) |
| Orquestación | Script Python invocado por cron del hosting |
| Configuración | YAML (fuentes, prompts versionados) |

### 7.2 Frontend / web

| Componente | Tecnología |
|---|---|
| Lenguaje | PHP plano |
| Almacenamiento | Archivos JSON en filesystem (sin BBDD en MVP) |
| Estructura | `/data/artefactos/YYYY-MM-DD-NNN.json` |
| Renderizado | Lectura del directorio + plantillas PHP simples |
| Estilos | CSS propio, sin frameworks |

### 7.3 Integraciones

| Servicio | Uso | Fase |
|---|---|---|
| API Anthropic | Sintetizador y Auditor | Paso 1 |
| RSS públicos | Fuentes | Paso 2 |
| Cron hosting | Trigger diario | Paso 2 |
| Bot Telegram | Notificaciones | Paso 3 |

### 7.4 Prompt caching

La API de Anthropic permite cachear porciones estáticas del prompt (hasta 90% de descuento). Se aplicará a:
- El estándar Moral Core completo (sistémico del Sintetizador)
- Los 11 axiomas y criterios (sistémico del Auditor)
- La matriz de fuentes y su clasificación

Solo la parte variable (la noticia concreta) consume tokens a precio completo.

---

## 8. Web y publicación

### 8.1 Estructura del sitio

| Página | Propósito | Fase |
|---|---|---|
| `/` (Home/Landing) | Manifiesto del proyecto, explicación del problema | Paso 1 |
| `/hoy` | Los 5 artefactos del día (Paso 1: todos los generados) | Paso 1 |
| `/articulo/{id}` | Artefacto individual | Paso 1 |
| `/archivo` | Histórico navegable | Paso 2 |
| `/como-funciona` | Explicación técnica y axiomas | Paso 3 |
| `/aviso-legal` | Obligatorio | Paso 1 |
| `/privacidad` | GDPR obligatorio | Paso 1 |
| `/cookies` | GDPR obligatorio | Paso 1 |
| `/ia` | Aviso de IA detallado | Paso 1 |

### 8.2 Aviso de IA

Banner permanente en la home + página dedicada (`/ia`), explicando:
- 100% del contenido es generado por agentes de IA
- Descripción del proceso Sintetizador + Auditor
- Los 11 axiomas Moral Core
- Lista de fuentes utilizadas
- Limitaciones conocidas de la IA

**No aparece en cada artefacto individual** (decisión tomada: el aviso está en el proyecto base, no en cada noticia).

### 8.3 Licencia

Creative Commons BY-SA 4.0 declarada en el footer global.

### 8.4 Dominio

Decisión pendiente. Se evaluará en el momento de desplegar.

---

## 9. Legal y gobernanza

### 9.1 Obligaciones mínimas España

- **Aviso legal**: identificación del responsable (puede usar alias público + contacto email, pero legalmente requiere una persona responsable real identificable ante la autoridad si se requiere)
- **Política de privacidad y cookies** GDPR
- **Declaración de donaciones** en IRPF (cuando aplique, Paso 3)
- **Si superas ~3.000 €/año en donaciones**: valorar alta como autónomo o constitución de asociación sin ánimo de lucro

### 9.2 Anonimato vs. identificabilidad

| Capa | Visibilidad |
|---|---|
| Públicamente | Proyecto anónimo firmado por "Equipo Prisma" |
| Legalmente | Persona física responsable registrada en dominio, aviso legal y cuenta bancaria |
| WHOIS | Servicio de privacidad WHOIS (~5 €/año) |

### 9.3 Transparencia radical como gobernanza

El proyecto se gobierna por principios de transparencia auditable:
- Todas las fuentes citadas con enlace directo
- Auditoría Moral Core publicada junto a cada artefacto
- Versionado del estándar Moral Core (v1.0, v1.1...)
- Posible dashboard público de estadísticas de auditoría (Paso 3)

---

## 10. Presupuesto y métricas

### 10.1 Desglose de costes por noticia

Asunciones del pipeline (revisables tras pruebas reales):

| Fase | Input tokens | Output tokens |
|---|---|---|
| Ingesta y filtrado de fuentes | ~30.000 | ~2.000 |
| Síntesis multi-perspectiva | ~35.000 | ~4.000 |
| Validación Moral Core | ~8.000 | ~1.500 |
| Generación formatos de salida | ~6.000 | ~2.000 |
| **Total por noticia** | **~79.000** | **~9.500** |

Mix de modelos: 90% Sonnet 4.6 + 10% Opus 4.7 (solo validación crítica).

**Coste estimado: ~0,32 €/noticia.**

### 10.2 Proyección por escenarios

| Escenario | Coste/día | Coste/mes | Coste/año |
|---|---|---|---|
| **Paso 1** (uso manual esporádico) | <0,50 € | <5 € | <50 € |
| **Paso 2** (5 noticias/día automáticas) | ~1,75 € | ~53 € | ~640 € |
| Paso 2+ ampliado a 10/día | ~3,50 € | ~105 € | ~1.260 € |
| Paso 2+ ampliado a 20/día | ~7,00 € | ~210 € | ~2.520 € |

Con prompt caching agresivo, reducción adicional del 20-30%.

### 10.3 Métricas clave a monitorizar

- **Coste diario** (alerta si >3 € en Paso 2)
- **Tasa de APTO** (objetivo: >70%)
- **Tasa de REVISIÓN** (alerta si >50%)
- **Tasa de RECHAZO** (alerta si >30%)
- **Axiomas más fallados** (señal para ajustar prompts)
- **Latencia del pipeline** (informativo)

---

## 11. Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Alucinaciones del Sintetizador no detectadas | Media | Alto | Axioma A10 + Auditor Opus 4.7 independiente |
| Sesgo sistemático del modelo | Media | Alto | A4 (simetría léxica) + pluralidad de fuentes obligatoria |
| Reclamación legal de un medio por uso de contenido | Baja | Medio | Solo RSS público + citas + enlace a original + uso legítimo |
| Fallo técnico de la API Anthropic | Baja | Medio | Reintento automático + log + alerta email |
| Coste inesperado (picos de tokens) | Baja | Bajo | Límite diario configurable + monitorización |
| Críticas de sesgo del propio sistema | **Alta** | Medio | Transparencia total: auditoría pública junto al artefacto |
| Abandono por desmotivación | Media | Alto | Diseño 100% autónomo a partir de Paso 2 |
| Cambio de precios / disponibilidad de modelos | Media | Medio | Arquitectura desacoplada del proveedor (OpenAI como fallback) |
| Fuentes que cierran RSS o exigen acceso pago | Media | Bajo | Redundancia: matriz de 18+ fuentes permite pérdidas |

---

## 12. Decisiones de diseño registradas

Registro de las decisiones tomadas durante el diseño del proyecto, con su razonamiento. Sirve como memoria institucional.

| # | Decisión | Razonamiento |
|---|---|---|
| D1 | API directa Anthropic en lugar de Managed Agents | Pipeline corto, sin estado persistente, sin sandboxing → Managed Agents no aporta valor para este caso de uso y añade coste/complejidad |
| D2 | Claude (Sonnet + Opus) en lugar de GPT | Mejor instruction-following en tareas multi-criterio, prompt caching agresivo, dominio existente del ecosistema |
| D3 | 5 noticias/día en MVP | Volumen manejable, coste controlado, permite medir calidad antes de escalar |
| D4 | Publicación 17:00 hora local España | Cubre ciclo informativo europeo de la mañana, permite lectura vespertina |
| D5 | PHP plano + JSON en filesystem | Hosting ya disponible, sin dependencias nuevas, simplicidad máxima para MVP |
| D6 | Endpoint `/api/ingest` en lugar de FTP | Más limpio, más seguro (API key), más fácil de depurar |
| D7 | 11 axiomas (incluye A11 sesgo geopolítico) | Cobertura completa de dimensiones de sesgo relevantes para política internacional |
| D8 | Umbrales 10/11 APTO, 8-9/11 REVISIÓN, <8/11 RECHAZO | Equilibrio entre rigor y viabilidad operativa |
| D9 | Aviso de IA en proyecto base, no en cada noticia | Evita ruido visual en el artefacto; el usuario conoce la naturaleza del proyecto al entrar |
| D10 | Licencia CC BY-SA 4.0 | Coherente con filosofía de transparencia y favorece viralidad |
| D11 | Matriz de fuentes por cuadrante ideológico explícito | Garantiza diversidad obligatoria por diseño, no por inferencia del modelo |
| D12 | Auditor con context separado del Sintetizador | Evita sesgo de confirmación; el Auditor no "justifica" lo que acaba de generar |
| D13 | Donaciones fuera del MVP | Priorizar simplicidad. Monetización solo si el producto demuestra calidad |
| D14 | Proyecto anónimo públicamente | Desplaza el foco del autor al contenido; coherente con "no es un medio con editorial" |
| D15 | Hoja de ruta en 3 pasos sin fechas | Proyecto personal: ritmo autoimpuesto, no plazos externos |

---

## Anexo A — Glosario

- **Artefacto**: cada una de las piezas de contenido publicadas, con las 5 secciones canónicas y su auditoría asociada.
- **Cuadrante ideológico**: clasificación de las fuentes en 6 categorías (izquierda, centro-izquierda, centro, centro-derecha, derecha, derecha populista) para garantizar diversidad.
- **Moral Core**: estándar ético open-source con manifiesto propio, que establece los principios de neutralidad y los axiomas auditables aplicados en Prisma.
- **Axioma**: criterio concreto verificable que debe cumplir un artefacto para ser considerado conforme al estándar Moral Core.
- **Sintetizador**: agente Claude Sonnet 4.6 que genera el artefacto.
- **Auditor**: agente Claude Opus 4.7 que evalúa el artefacto contra los 11 axiomas.
- **Curador**: agente (en Paso 2) que selecciona los 5 temas diarios a partir de los RSS.

---

## Anexo B — Próximos entregables

Una vez validado este documento como contexto base, los siguientes entregables del Paso 1 son:

1. **Documento de prompts completo**:
   - System prompt del Sintetizador (con los 7 principios + estructura de 5 secciones)
   - System prompt del Auditor (con los 11 axiomas y formato JSON)
   - Esquema JSON final del artefacto publicable
   - Ejemplo end-to-end con una noticia real para validar

2. **Código Python del script `prisma.py`**:
   - Función `sintetizar()`
   - Función `auditar()`
   - Lógica de decisión (APTO/REVISIÓN/RECHAZO)
   - Publicación vía endpoint

3. **Código PHP del hosting**:
   - `ingest.php` (endpoint de escritura)
   - `index.php` (listado)
   - `articulo.php` (individual)
   - Plantilla CSS sobria

4. **Contenido de páginas estáticas**:
   - Landing manifiesto (copy completo)
   - Aviso legal (plantilla adaptada)
   - Política de privacidad (plantilla GDPR)
   - Página `/ia`

---

**Fin del documento**

*Este archivo sirve como contexto total del proyecto Prisma. Debe acompañar cualquier sesión de trabajo futura para evitar redefinir decisiones ya tomadas.*
