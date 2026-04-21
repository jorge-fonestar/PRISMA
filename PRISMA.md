# Prisma — Estado del proyecto

> Servicio de información neutral. Cada noticia analizada desde todas las posturas, auditada contra 11 axiomas de neutralidad.

**Stack:** PHP 7.x + SQLite + Anthropic API (Claude). Sin dependencias externas.

---

## Arquitectura en dos fases

### Fase 1 — Escaneo (`escanear.php`)
- Lee RSS de todos los ámbitos (España, Europa, Global)
- Agrupa artículos por tema (similitud Jaccard)
- Calcula el **índice de tensión informativa** (H-score):
  - 45% asimetría de cobertura izq/der
  - 40% divergencia léxica entre cuadrantes
  - 15% varianza del espectro ideológico
- Inserta todos los temas en tabla `radar` (con dedup)
- **Coste: $0** — solo HTTP + math. Ejecutable varias veces al día.

### Fase 2 — Análisis (`analizar.php`)
- Filtra temas pendientes por umbral de tensión (`umbral_tension` en config)
- **Triage Haiku**: confirma tensión genuina, descarta falsos positivos (batch, ~$0.005/día)
- **Síntesis Sonnet**: genera artefacto multi-postura con fuentes citadas
- **Auditoría Moral Core**: segundo agente evalúa contra 11 axiomas
- Publica en tabla `articulos` si veredicto = APTO; reintenta o descarta si no
- **Coste: ~$0.15-0.50 por tema** — ejecutar selectivamente

---

## Base de datos (SQLite)

### `radar`
Todos los temas detectados por escaneo. Es el listado público del index.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INTEGER PK | Auto-increment |
| fecha | TEXT | Fecha de detección (YYYY-MM-DD) |
| titulo_tema | TEXT | Título del tema agrupado |
| ambito | TEXT | `españa` / `europa` / `global` |
| h_score | REAL | Índice de tensión [0-1] |
| h_asimetria | REAL | Componente asimetría |
| h_divergencia | REAL | Componente divergencia léxica |
| h_varianza | REAL | Componente varianza espectro |
| haiku_frase | TEXT | Frase explicativa (post-triage) |
| analizado | INTEGER | 0=pendiente, 1=analizado |
| articulo_id | TEXT | FK a `articulos.id` si analizado |
| fuentes_json | TEXT | JSON con medios/urls/cuadrantes |

### `articulos`
Análisis completos publicados. Un subconjunto del radar.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | TEXT PK | ID generado (prisma_YYYYMMDD_N) |
| fecha_publicacion | TEXT | Timestamp ISO |
| ambito | TEXT | Ámbito geográfico |
| titular_neutral | TEXT | Titular sintetizado |
| resumen | TEXT | Resumen del análisis |
| payload | TEXT | JSON completo del artefacto |
| veredicto | TEXT | APTO / REVISIÓN / RECHAZO |
| puntuacion | REAL | Score Moral Core [0-1] |
| fuentes_total | INTEGER | Nro. fuentes consultadas |

**Relación:** `radar.articulo_id` → `articulos.id` (1:1 opcional)

---

## Archivos principales

```
escanear.php          Fase 1: RSS → radar (gratis)
analizar.php          Fase 2: radar → artículos (tokens)
panel.php             Panel privado de administración
index.php             Web pública: radar con filtros
articulo.php          Detalle de tema/artículo
config.php            Config + fuentes RSS por ámbito/cuadrante
db.php                Init SQLite + helper base URL

lib/
  rss.php             Lector RSS multi-feed
  curador.php         Agrupación + cálculo tensión
  common.php          Funciones radar, triage, pipeline
  sintetizador.php    Prompt síntesis Sonnet
  auditor.php         Prompt auditoría Moral Core
  anthropic.php       Wrapper API Anthropic
  layout.php          Template HTML pages
  theme.php           Dark/light mode CSS vars
```

---

## Panel privado (`panel.php`)

### Acciones
1. **Escanear fuentes** (Fase 1) — Puebla el radar. $0. Sin confirmación.
2. **Analizar pendientes** (Fase 2) — Top N temas por tensión. Confirmación requerida.
3. **Analizar tema individual** — Botón por fila en tabla de temas. Confirmación requerida.
4. **Analizar tema manual** — Texto libre, bypasses radar. Síntesis + auditoría directa. Confirmación.
5. **Buscar tema en fuentes** — Texto libre, busca en RSS por keywords.
6. **Resetear DB** — Elimina todo. Doble confirmación.

### Vista unificada de temas
- Una sola tabla `radar LEFT JOIN articulos`
- Agrupados por fecha, ordenados por tensión
- Pendientes: badge + botón "Analizar"
- Analizados: badge veredicto + expandible con resumen/puntuación/link

---

## Config clave (`config.php` → `.env`)

| Parámetro | Default | Descripción |
|-----------|---------|-------------|
| umbral_tension | 0.55 | H-score mínimo para Fase 2 |
| min_cuadrantes | 3 | Cuadrantes mínimos para análisis |
| articulos_dia | 1 | Máximo análisis por ejecución |
| daily_budget_usd | 4.00 | Límite gasto API diario |
| model_synth | claude-sonnet-4-6 | Modelo síntesis |
| model_audit | claude-sonnet-4-6 | Modelo auditoría |
| model_triage | claude-haiku-4-5 | Modelo triage |

---

## Cron recomendado

```cron
# Escaneo cada 4 horas (gratis)
0 */4 * * * cd /ruta/prisma && php escanear.php >> logs/escaneo.log 2>&1

# Análisis una vez al día (tokens)
0 17 * * * cd /ruta/prisma && php analizar.php >> logs/analisis.log 2>&1
```

---

## Web pública (`index.php`)

- Banner compacto anti-cámaras-de-eco
- Toolbar: filtros por fecha, ámbito y polarización (%)
- Sort: tensión / A-Z
- Cards: círculo tensión + título + frase + fuentes por cuadrante
- Los analizados enlazan al artículo completo

---

## Páginas estáticas

| Página | Contenido |
|--------|-----------|
| manifiesto.php | Qué es Prisma, por qué, cómo funciona (2 fases) |
| ia.php | Aviso de IA: proceso, limitaciones, modelos |
| axiomas.php | Los 11 axiomas Moral Core |
| fuentes.php | Matriz de medios + explicación algoritmo tensión |

---

*Última actualización: 2026-04-21*
*Para actualizar este documento: revisar tras cambios en schema, pipeline o config.*
