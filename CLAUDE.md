# PolarPrisma — Guía operativa para Claude Code

Adjunta este archivo al inicio de una conversación nueva (o ábrelo con el repo:
Claude Code lo autocarga). Da el contexto mínimo para trabajar sin redescubrirlo.
El detalle vivo está en [docs/STATUS.md](docs/STATUS.md) (estado + decisiones) y
[docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) (pipeline, BD, config).

## Qué es

Servicio de información neutral **en producción en https://polarprisma.org**.
Detecta la polarización informativa (cómo medios de distinto signo cuentan la
misma noticia, o qué callan) y genera análisis multipostura auditados contra 11
axiomas ("Moral Core"). **PHP 7/8 + SQLite + API de Anthropic**, sin frameworks.

Pipeline en dos fases:
- **Fase 1 — `escanear.php`** (cron 4h, gratis): lee RSS de todo el espectro,
  agrupa por tema (Jaccard ponderado titular+entradilla), puntúa polarización
  (H-score v2) con un **gate Haiku** (batch) y llena la tabla `radar`.
- **Fase 2 — `analizar.php`** (cron diario): toma los temas más polarizados,
  triage Haiku → síntesis Sonnet → auditoría Moral Core, sobre la **Batches API**
  (50% de coste). Publica en `articulos`.

## Dónde vive todo

| Cosa | Ubicación |
|---|---|
| Código (este repo) | local `C:\Users\JorgedelOlmo\Proyectos\Z.Personales\PRISMA` · GitHub `jorge-fonestar/PRISMA` (rama **master** = producción) |
| Infra (compose, Ofelia, Traefik) | repo `jorge-fonestar/serviyorch`, local en `…\Z.Personales\serviyorch` (rama **main**) |
| Servidor | VPS Hetzner "serviyorch" · **`ssh ea1jxe`** (alias en `~/.ssh/config`) |
| Código en el servidor | `~/sites/polarprisma` (checkout de este repo, montado en el contenedor como `/var/www/html`) |
| Compose del sitio | `~/docker/serviyorch/sites/polarprisma/docker-compose.yml` |
| Base de datos | `~/sites/polarprisma/data/prisma.db` (+ `prisma_logs.db`, `usage.json`) |
| Secretos | `~/sites/polarprisma/.env` en el servidor (fuera de git). Contiene `ANTHROPIC_API_KEY`, `PRISMA_PANEL_PASS`, `PRISMA_INGEST_KEY`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`. **Nunca** commitear valores. |

## Cómo operar (recetas)

**No hay PHP en local (Windows).** Lint vía el contenedor remoto:
```bash
cat fichero.php | ssh ea1jxe 'docker exec -i polarprisma php -l'
```

**Desplegar** (tras commit+push a master):
```bash
ssh ea1jxe 'cd ~/sites/polarprisma && git pull --ff-only -q'
# Solo si cambió Dockerfile.web / apache: reconstruir imagen
ssh ea1jxe 'cd ~/docker/serviyorch/sites/polarprisma && docker compose up -d --build'
# Si cambian labels de Ofelia (crons): docker restart ofelia
```

**Ejecutar a mano** (como www-data para no dejar ficheros de root en `data/`):
```bash
ssh ea1jxe 'docker exec -u www-data polarprisma php /var/www/html/escanear.php'
ssh ea1jxe 'docker exec -u www-data polarprisma php /var/www/html/analizar.php --id N'   # un tema concreto (síncrono)
ssh ea1jxe 'docker exec -u www-data polarprisma php /var/www/html/analizar.php --sync'    # forzar vía directa (no batch)
ssh ea1jxe 'docker exec -u www-data polarprisma php /var/www/html/digest_telegram.php --dry-run'  # previsualizar digest sin enviar
```

**Ver crons / logs:**
```bash
ssh ea1jxe 'docker logs -f ofelia'          # ejecuciones programadas
ssh ea1jxe 'docker logs polarprisma'        # errores de Apache/PHP
```

**Consultar la BD:**
```bash
ssh ea1jxe 'docker exec polarprisma php -r "\$d=new PDO(\"sqlite:/var/www/html/data/prisma.db\"); ...;"'
```

## Crons (Ofelia, en el compose; horas UTC)

- `prisma-escanear` — `0 0 */4 * * *` — Fase 1.
- `prisma-analizar` — `0 30 17 * * *` — Fase 2.
- `prisma-digest` — `0 0 6 * * *` (08:00 Madrid en verano) — digest Telegram del día anterior.

## Modelos de IA (config.php)

- Síntesis y auditoría: **`claude-sonnet-5`**. Triage/gate: **`claude-haiku-4-5-20251001`**.
- `max_tokens_pipeline=8192` (Sonnet 5 usa thinking adaptativo: dejar margen).
- **Prefill**: `anthropic_supports_prefill()` es allowlist (solo modelos legacy). No enviar prefill a modelos 4.6+/sonnet-5 (da 400).
- Precios en `lib/anthropic.php`; revisar tarifa intro de Sonnet 5 tras 2026-08-31.

## Convenciones y flujo

- **Git**: push solo a `master` (es producción; desplegar es un `git pull` en el server). Commits en español, con `Co-Authored-By: Claude …`.
- **Documentación**: al cerrar un bloque de trabajo, actualizar `docs/STATUS.md`. Cuando el usuario diga **"changelog"**: añadir entrada NUEVA arriba en `docs/CHANGELOG.md` (tono para seguidores del canal de Telegram) y dar el texto listo para pegar.
- **⚠️ Textos fijos vs mecánica real**: siempre que cambie la mecánica del proyecto (scoring/H-score, cómo se calcula o **afina** la polarización, fases del pipeline, clustering, fuentes, umbrales, modelos), **revisar y actualizar los textos públicos que la describen** para que la web nunca explique un funcionamiento que ya no es el real. Textos a vigilar: `presentacion.php` (sección "Cómo se construye una noticia"), `fuentes.php` (algoritmo del índice y las dos fases), `ia.php` (aviso de IA), `axiomas.php`, `manifiesto` (`docs/manifiesto-prisma.md`). Parte de cada cambio de mecánica, no un extra opcional.
- **Diseño de página**: cabecera/pie compartidos en `lib/layout.php` (`page_header/footer`, `render_nav`); `index.php`, `articulo.php` y `presentacion.php` tienen `<head>` y CSS propios (cualquier cambio de nav/favicon/RSS hay que replicarlo en los cuatro). Ancho de listado 1100px (`page_header(..., true)`), lectura 820px.

## Gotchas (aprendidos a base de tropezar)

- **`data/` debe ser escribible por www-data**; si `usage.json` da "Permission denied", `docker exec polarprisma chown www-data:www-data /var/www/html/data/<fichero>`.
- **El checkout del servidor tiene `core.fileMode false`** (los permisos g+w marcaban todo como modificado y bloqueaban `git pull`). Si un pull se queja de cambios locales, revisar que no sea eso.
- **Apache bloquea del webroot** dotfiles, `data/`, `logs/`, `docs/`, `lib/`, `deploy/`, `.md`, `.db` (ver `deploy/apache-hardening.conf`, horneado en `Dockerfile.web`). No poner contenido público bajo esas rutas.
- **`/tmp` en Git Bash (Windows) es inconsistente entre invocaciones** y `python` de Windows no entiende rutas `/c/...`. Para ficheros temporales usar el scratchpad de la sesión y leerlos con el tool Read (ruta Windows), no con `python -c`.
- **IDs de artículo**: los impone el servidor (`prisma_gen_id` continúa la numeración del día); el modelo no debe fijar `id`/`ambito` (se sobrescriben en `prisma_procesar_tema` y el pipeline batch).
- **Cupo diario**: el cron analiza `articulos_dia − (publicados hoy)`, contando los manuales. `--temas N` explícito salta el ajuste.
- **RSS** (`rss.php`) y **favicon** (`favicon.svg`) usan `site_url` absoluto de config para URLs; el autodiscovery está en las 4 cabeceras.

## Estado y pendientes

Ver [docs/STATUS.md](docs/STATUS.md). Aparcado: el Observatorio Overton
(`docs/diseno/DISEÑO_OVERTON.md`). Al retomarlo, revisar modelos (asumía Extended
Thinking con `budget_tokens`, ya retirado → hoy sería thinking adaptativo).
