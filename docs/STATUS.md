# Prisma — Estado del proyecto

> Actualizado: **2026-07-20**. Este fichero es el punto de entrada: qué hay
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

## Pendientes

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
