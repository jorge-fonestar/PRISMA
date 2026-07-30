# Prisma — Estado del proyecto

> Actualizado: **2026-07-25**. Este fichero es el punto de entrada: qué hay
> desplegado, qué se decidió y qué está pendiente. La arquitectura de referencia
> está en [ARQUITECTURA.md](ARQUITECTURA.md).

## Estado actual

- **Producción:** https://polarprisma.org en el VPS personal (serviyorch, Hetzner).
  Contenedor `polarprisma` (imagen propia desde `Dockerfile.web`, Apache endurecido),
  proxy Traefik, crons vía Ofelia:
  - `escanear.php` cada 4 h (00/04/08/12/16/20 UTC) — Fase 1, gratis.
  - `analizar.php` diario 17:30 UTC — Fase 2, gasta tokens (Batches API, 50% dto.).
- **El pipeline estuvo parado del 4-may al 20-jul-2026** (key de Anthropic revocada
  el 3-jun; los escaneos de abril/mayo se lanzaban a mano, nunca hubo cron real).
- **Incidente de seguridad corregido (20-jul-2026):** `/.env` (con claves) y
  `data/prisma_logs.db` se servían públicos. La imagen ahora bloquea dotfiles,
  `data/`, `logs/`, `docs/`, `lib/`, `deploy/`, `rechazados/` y `output/`.
  La key de Anthropic expuesta ya estaba revocada; **la contraseña del panel y la
  ingest key estuvieron expuestas y hay que rotarlas** (ver decisiones).

## Cambios de julio 2026 (retomada del proyecto)

| Cambio | Dónde |
|---|---|
| Fase 2 sobre Message Batches API (50% coste; rondas síntesis→auditoría con reintentos) | `lib/pipeline_batch.php`, `lib/anthropic.php`, `analizar.php`, flag `use_batch_api` |
| Clustering semántico con descripción RSS (Jaccard ponderado 1.0 titular / 0.5 descripción) | `lib/curador.php`, flags `cluster_*` en `config.php` |
| La descripción viaja al radar (`fuentes_json`) y enriquece el contexto de síntesis | `lib/common.php`, `analizar.php` |
| Prefill: allowlist segura (solo modelos legacy); precios de modelos actualizados | `lib/anthropic.php` |
| Moral Core y "cartografía, no juicio" visibles + FAQ de falsa equivalencia | `presentacion.php` |
| Endurecimiento Apache + imagen propia | `Dockerfile.web`, `deploy/apache-hardening.conf` |
| Documentación reorganizada en `/docs` | este directorio (`README.md` como índice) |
| Diseño de newsletter semanal (ranking polarización + silencios) | `diseno/NEWSLETTER-SILENCIOS.md` |
| Síntesis/auditoría en `claude-sonnet-5` con `max_tokens_pipeline=8192` (thinking adaptativo) | `config.php`, sintetizador/auditor/pipeline_batch |
| `articulos_dia` 1 → 2 | `config.php` |
| Gate Haiku con entradillas (en pruebas, evaluar en unos días) | `lib/gate_haiku.php`, flags `gate_incluir_descripcion`/`gate_desc_max_chars` |
| Credenciales del panel e ingest **rotadas** (20-jul, tras la exposición) | `.env` del servidor |
| Borrador email opt-out a medios | `comunicacion/EMAIL-MEDIOS-OPTOUT.md` |
| Menú Hoy/Radar/Silencios/¿Qué es? + página silencios.php + rebrand PolarPrisma + nav móvil | `lib/layout.php`, `index.php` (`?vista=radar`), `silencios.php` |
| **Fix cola Fase 2 (21-jul)**: ventana de recencia (`analizar_ventana_dias=2`), columna `radar.triage` (el triage ya no consume la cola ni se repaga), top N por h_score | `analizar.php`, `lib/common.php`, `db.php` |
| **Fix ids de artículo (21-jul)**: numeración continúa la del día (no pisa artículos entre ejecuciones) y el id/ámbito los impone el servidor (el modelo llegó a "corregir" el id) | `lib/common.php`, `lib/pipeline_batch.php` |
| **Fix Sonnet 5 (21-jul)**: `anthropic_call` busca el primer bloque `text` (con thinking adaptativo, `content[0]` es thinking) | `lib/anthropic.php` |
| Aviso Telegram al publicar (canal @prismanews_dev; token/chat en `.env` del servidor) | `lib/telegram.php`, hook en `prisma_publicar` |
| **Mapa ideológico y de financiación** (menú "Mapa"): propietario, modelo y capacidad €/€€/€€€ por medio + línea +Info curada + lectura objetiva de la asimetría; ámbitos plegables | `mapa.php`, `lib/mapa_datos.php` |
| **Lote feedback (24-jul)**: mini-resumen `resumen_neutral` por tema (gate Haiku, ≥2 cuadrantes; backfill manual de 122 temas), digest Telegram v2 (título completo + resumen + marca 🔬/🔹), fuentes originales en artículos analizados, favicon, enlace a axiomas, fixes badge-analizado / skip-link / margen; `articulos_dia` 2→1 | `lib/gate_haiku.php`, `lib/telegram.php`, `articulo.php`, `favicon.svg`, `api_radar.php` |
| **Fix radar (22-jul)**: fuentes_json se refresca en cada re-escaneo y las historias que continúan se arrastran de ayer a hoy (Jaccard ≥0,6) en vez de duplicarse | `lib/common.php` (radar_insertar_todos) |
| Feeds (22-jul): CTXT → no_disponible (403 WAF), RTVE descartado (RSS congelado desde 2022), **Europa Press** añadido al centro; boletines recurrentes a lista negativa | `config.php` |
| Análisis de estrategia de escaneo: ventana real = 24h rodantes; "silencio por timing" casi inexistente (1/26); intervalos de 4h correctos | conclusiones en el chat del 22-jul |

## Aparcado

- **Observatorio Overton** (`diseno/DISEÑO_OVERTON.md` + plan en `superpowers/`):
  diseño completo, 0 de 49 tareas. Decisión de jul-2026: retomar más adelante.
  Al retomarlo, revisar modelos (el diseño asumía Opus + Extended Thinking con
  `budget_tokens`, ya retirado — hoy sería adaptive thinking).

## Decisiones tomadas el 20-jul-2026

- Credenciales del panel/ingest **rotadas** (las anteriores estuvieron expuestas).
- Síntesis/auditoría migradas a **claude-sonnet-5**; `articulos_dia` → **2**.
- **Gate con entradillas activado en pruebas**.
- Newsletter: dos secciones (ranking de polarización descendente + silencios).
- Contacto con medios: pasar de pedir permiso a **notificación con opt-out**
  (borrador en `comunicacion/EMAIL-MEDIOS-OPTOUT.md`). Aclarado: el contacto es
  para extracción de contenido con su conocimiento, no difusión.

## Tema visual (24-jul)

- **Modo oscuro forzado para todos**; selector de tema oculto (`lib/theme.php`:
  `theme_toggle`/`theme_js` vacíos, `theme_head_script` fuerza `data-theme=dark`).
  El CSS del light-mode y del toggle sigue inerte en el código para reactivarlo
  fácil. Corregido el botón "Aplicar" de filtros (texto blanco sobre amarillo → oscuro).

## Ajustes 25-jul (feedback del 016)

- **016 corregido a mano**: añadido El País (4 fuentes) y `framing_divergence`
  bajado a 1 → h_score 66%→24% (era falso positivo: cobertura alta pero sin
  divergencia real, como decía su propia `haiku_frase`).
- **Ficha coherente con la tarjeta**: la ficha de un tema que supera umbral pero
  sin analizar muestra ahora también la `haiku_frase` (antes solo texto genérico).
- **Prompt del resumen**: el `resumen_neutral` debe aportar contexto de las
  entradillas, no parafrasear el titular.

## Pendientes

- **Recalcular el % en Fase 2 — HECHO (25-jul, opción A)**: el sintetizador
  devuelve `indice_polarizacion` (0-100, con rúbrica) con el texto completo; sustituye
  al estructural en las noticias analizadas (`radar_afinar_polarizacion`), que se
  conserva en `h_score_estructural`. La ficha muestra ambos. Explicado en
  `fuentes.php` y `presentacion.php`. Validado: 016 → 66% inicial / 24% estructural
  corregido a mano / 15% tras análisis.
- **Gate de Fase 1 endurecido — HECHO (30-jul)**: el prompt distingue variación
  léxica de divergencia real de encuadre, y `framing_divergence ≥ 2` exige evidencia
  citable (salvaguarda en código que lo capa a 1 sin `framing_evidence`).
- **Entradilla/resumen que ya no repite el titular — HECHO (30-jul)**: `resumen_neutral`
  reescrito como "segunda frase" que aporta el dato que el titular no dice (prompt del
  gate con ejemplo). Nuevo `regen_resumenes.php` (CLI) regeneró 264 resúmenes recientes.
- **Ficha en dos zonas + confianza — HECHO (30-jul, #2 y parte de #3)**: "Lo que está
  documentado" (resumen + confianza mecánica Alta/Media/Baja por cobertura + silencio
  editorial) vs "Lecturas e interpretaciones" (posturas como lecturas legítimas).

### Hoja de ruta activa (rescatada de la conversación de diseño)
- **En curso**: #1 verificación factual con `web_search` en la síntesis (+ separar zona
  factual con verificaciones/discrepancias); resto de #3 (cita literal por postura,
  microcitas [1][3]); #6 prompt caching del system de síntesis/auditoría.
- **PENDIENTE (recordar al cerrar cada tema)**: #4 calibración del scoring
  (grid-search precision@10/recall@10 + etiquetado desde panel + botón "marcar trivial")
  y #5 equilibrio del corpus (recuperar flanco izquierdo Público/InfoLibre/CTXT vía
  captura de portada; auditoría ejes×cuadrantes×ámbitos; ampliar fuentes).

- **Cobertura de fuentes por tema — RESUELTO (25-jul)**: ventana de lectura RSS
  a 48h (`rss_ventana_horas`) y clustering por **mejor match** (cada artículo al
  cluster más similar, no al primero; `curador_seleccionar`). Validado: el caso
  "016" agrupa a El País correctamente sin que el ruido "España/Mundial" lo robe;
  en producción, cluster mayor de 9 fuentes (sin sobre-fusión). Nota: el caso
  histórico del 016 no se recupera retroactivamente (esa noticia ya supera las 48h).

1. **Calibrar `cluster_umbral` (0,3)** cuando haya ~1 semana de escaneos con el
   Jaccard ponderado: comparar tamaño/coherencia de clusters (Claude lo analiza
   directamente o pide criterio a Jorge). *Anotado: revisar hacia el 27-jul.*
2. **Evaluar el gate con entradillas** a los pocos días: comparar distribución de
   `framing_divergence`/`relevancia` y coste del gate vs histórico. *Revisar ~24-jul.*
3. **Newsletter — detalles finos** ([diseno/NEWSLETTER-SILENCIOS.md](diseno/NEWSLETTER-SILENCIOS.md) §5):
   nombre del boletín, cadencia, nº de ítems, frase IA sí/no, email sí/no. Después: implementar.
4. **Email a medios**: Jorge pasa el email original de primavera → pulir el
   borrador opt-out → (idealmente) consulta legal → envío escalonado. La captura
   de portada con medios A/B sigue bloqueada hasta la consulta.
5. **Campaña de difusión + estrategia de fondos** (donaciones para financiar más
   procesado de noticias): por diseñar — canales, mensaje, mecanismo de donación
   (el FAQ ya anuncia "donaciones voluntarias en fases futuras").
6. **1-sep-2026**: revisar la tabla de precios en `lib/anthropic.php` — el tracking
   usa la tarifa plena de sonnet-5 ($3/$15, conservador) mientras dura la intro $2/$10.

## Operativa rápida

```bash
# Servidor
ssh ea1jxe                     # alias en ~/.ssh/config (VPS serviyorch)
cd ~/sites/polarprisma && git pull            # desplegar código
cd ~/docker/serviyorch/sites/polarprisma && docker compose up -d --build
docker restart ofelia                          # re-registrar jobs tras recrear
docker logs -f ofelia                          # ver ejecuciones de cron
docker exec -u www-data polarprisma php /var/www/html/escanear.php   # escaneo manual

# Local (sin PHP instalado): lint vía el contenedor remoto
cat fichero.php | ssh ea1jxe 'docker exec -i polarprisma php -l'
```
