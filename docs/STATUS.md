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
| Diseño de newsletter semanal de silencios | `diseno/NEWSLETTER-SILENCIOS.md` |

## Aparcado

- **Observatorio Overton** (`diseno/DISEÑO_OVERTON.md` + plan en `superpowers/`):
  diseño completo, 0 de 49 tareas. Decisión de jul-2026: retomar más adelante.
  Al retomarlo, revisar modelos (el diseño asumía Opus + Extended Thinking con
  `budget_tokens`, ya retirado — hoy sería adaptive thinking).

## Decisiones pendientes (Jorge)

1. **Rotar `PRISMA_PANEL_PASS` y `PRISMA_INGEST_KEY`** en el `.env` del servidor
   (estuvieron expuestas públicamente durante meses).
2. **Migrar síntesis/auditoría a `claude-sonnet-5`** (mismo precio de tarifa,
   intro $2/$10 hasta 31-ago-2026, mejor calidad; ~30% más tokens por el nuevo
   tokenizador y thinking adaptativo por defecto). El código ya está preparado
   (prefill allowlist + precios). Cambio: 2 líneas en `config.php`.
3. **`articulos_dia`**: sigue en 1. Con Batches al 50% podría subirse (3 temas/día
   ≈ $0,50-0,90/día con sonnet-4-6).
4. **Umbral de clustering** (`cluster_umbral` = 0,3): el Jaccard ponderado con
   descripciones cambia la distribución de similitudes; calibrar tras unos días
   de escaneos reales comparando clusters.
5. **Newsletter**: nombre, cadencia, con/sin email — ver
   [diseno/NEWSLETTER-SILENCIOS.md](diseno/NEWSLETTER-SILENCIOS.md) §5.
6. **Gate Haiku con snippet de descripción**: hoy clasifica solo con titulares
   (3 por bloque). Incluir 1-2 líneas de descripción mejoraría el framing_divergence
   a cambio de más tokens (~2-3× el coste del gate, que es ~$0,13/día). ¿Se prueba?
7. **Consulta legal pendiente** (de la spec del adaptador de fuentes): propiedad
   intelectual antes de activar captura de portada con medios categoría A/B.
8. **Difusión**: la ronda de emails a medios de primavera no obtuvo respuesta.
   ¿Siguiente intento tras tener la newsletter y un mes de datos frescos?

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
