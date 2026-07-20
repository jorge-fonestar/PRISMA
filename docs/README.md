# Documentación de Prisma

Toda la documentación del proyecto vive aquí. Estructura:

| Ruta | Contenido |
|---|---|
| [STATUS.md](STATUS.md) | **Empieza aquí**: estado actual, qué está desplegado y decisiones pendientes |
| [CHANGELOG.md](CHANGELOG.md) | Histórico breve con fechas, redactado para los seguidores (canal de Telegram) |
| [ARQUITECTURA.md](ARQUITECTURA.md) | Pipeline de dos fases, esquema de BD, ficheros, config y cron |
| [manifiesto-prisma.md](manifiesto-prisma.md) | Manifiesto público (lo renderiza `manifiesto.php`) |
| `diseno/` | Documentos de diseño vivos |
| `diseno/DISEÑO_POLARIZACION.md` | Scoring v2 del índice de polarización (implementado) |
| `diseno/prisma-fuentes-v2.1.md` | Matriz de fuentes v2.1 (implementado) |
| `diseno/NEWSLETTER-SILENCIOS.md` | Newsletter semanal de silencios editoriales (propuesta) |
| `diseno/DISEÑO_OVERTON.md` | Observatorio de la Ventana de Overton (**aparcado**, jul-2026) |
| `superpowers/` | Planes y specs de implementación generados por agentes (histórico técnico) |
| `historico/` | Documentos fundacionales y material de referencia ya superado |

Reglas:
- `STATUS.md` se actualiza al cerrar cualquier bloque de trabajo relevante.
- Un diseño que se implementa se anota como "implementado" aquí; uno descartado se mueve a `historico/`.
- La documentación no se sirve por web (Apache la bloquea); `manifiesto-prisma.md` la lee PHP directamente.
