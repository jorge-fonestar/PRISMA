# Newsletter semanal de Prisma — Documento de diseño

> **Estado:** Validada la dirección por Jorge (20-jul-2026): dos secciones —
> ranking semanal de polarización + silencios editoriales. Pendientes los
> detalles del §5.
> **Inspiración:** El "Blindspot Report" es la función más reconocida de Ground News
> (newsletter semanal de historias que un lado ignora). Prisma ya calcula las dos
> señales (`h_score`, `h_silencio`) en cada escaneo — empaquetarlas como producto
> semanal es casi gratis y es un gancho de difusión natural.

---

## 1. Concepto y estructura

Boletín semanal con dos secciones fijas, ambas derivadas del radar:

**§A — Lo más polarizado de la semana.** Resumen de los titulares con mayor
polarización detectada, en orden descendente de `h_score` (top 5-7 de la semana).
Cada ítem: titular del tema, % de polarización, qué bloques lo cubrieron y la
`haiku_frase` explicativa. Es la foto semanal del termómetro: qué historias
dividieron más el relato.

**§B — Los silencios de la semana.** La parte tipo Blindspot: **¿qué historias
contó un bloque ideológico mientras el otro callaba?** La materialización más
visceral del propósito de Prisma — hacer visible la selección editorial, que es
la forma de sesgo más difícil de percibir para el lector (nadie nota lo que su
medio *no* le cuenta).

Principios heredados del proyecto:
- **Simetría estructural**: mismo número de silencios de cada lado en cada edición.
  Si una semana un bloque calló más, se dice con datos, pero la lista se equilibra
  para que la propia newsletter no se convierta en munición partidista.
- **Cartografía, no juicio**: se documenta el silencio (qué medios cubrieron, cuáles
  no), sin especular sobre el motivo.
- **Verificable**: cada ítem enlaza a su ficha del radar con las fuentes y el desglose.

## 2. Datos — ya los tenemos

Todo sale de la tabla `radar` sin tocar el pipeline:

| Campo | Uso en la newsletter |
|---|---|
| `h_silencio` | 0.5 = un bloque calló, 1.0 = dos bloques callaron → filtro principal |
| `fuentes_json` | Cuadrantes que SÍ cubrieron → se deriva qué bloque calló |
| `h_score`, `relevancia` | Ranking y filtro de calidad (alta/media) |
| `haiku_frase` | Frase explicativa ya generada para los temas triados |
| `fecha`, `ambito`, `dominio_tematico` | Agrupación y contexto |

**Consulta §A:** radar de los últimos 7 días, `relevancia IN ('alta','media')`,
`ORDER BY h_score DESC LIMIT 5-7`, dedup de temas repetidos entre días (mismo
título ≈ mismo tema: quedarse con el de mayor h_score).

**Consulta §B:** radar de los últimos 7 días, `h_silencio > 0`, `relevancia IN
('alta','media')`, ordenado por `h_score`. Se clasifica cada tema según el bloque
ausente (izquierda / derecha / centro) y se toman los **top 3 de cada lado**.

## 3. Generación

Script nuevo `newsletter.php` (CLI, cron semanal):

1. Consulta y clasificación (determinista, $0).
2. Para cada tema sin `haiku_frase`: una llamada batch a Haiku genera la línea
   explicativa ("La izquierda cubrió X; ningún medio de derecha lo mencionó").
   Coste estimado: **< $0.01/semana**.
3. Render a tres salidas desde la misma estructura:
   - **Página web** `newsletter.php?edicion=2026-w30` (+ índice de ediciones) — archivo público.
   - **RSS** `newsletter-rss.php` — suscripción sin datos personales.
   - **HTML email** (plantilla inline-CSS) — solo si se activa el envío por correo.
4. Guardado en tabla nueva `newsletter_ediciones (id, semana, payload_json, publicado_at)`.

Cron Ofelia: lunes 08:00 (tras el escaneo de las 08:00, con la semana completa).

## 4. Distribución — decisión por fases (propuesta)

| Fase | Canal | Coste/esfuerzo | Datos personales |
|---|---|---|---|
| **F1 (ya)** | Página + RSS + enlace destacado en index.php | Solo el render | Ninguno |
| **F2 (si hay demanda)** | Email con [Brevo](https://www.brevo.com) (free tier 300/día) o Amazon SES (~$0.10/1000). Tabla `suscriptores` propia con double opt-in y baja de un clic | Media jornada + plantilla | Sí → RGPD: actualizar privacidad.php, DPA con el proveedor |
| **Alternativa F2** | Buttondown/Substack (externo) | Mínimo | Los gestiona el tercero |

Recomendación: **arrancar con F1** (cero fricción legal, indexable, compartible)
y decidir F2 cuando haya tráfico que lo justifique.

## 5. Decisiones pendientes (para Jorge)

Decidido el 20-jul-2026: estructura de dos secciones (§A ranking de polarización
+ §B silencios); "Los silencios de la semana" funciona como nombre de la sección §B.

1. **Nombre del boletín** (propuesta: "El Radar semanal de Prisma").
2. **Cadencia y momento**: ¿lunes 08:00? ¿domingo tarde?
3. **Nº de ítems**: §A ¿top 5 o 7?; §B ¿3+3 (izq/der)? ¿Incluir silencios del centro?
4. **¿Frase explicativa con IA o solo datos** (titular + medios + % cobertura)?
5. **Fase 2 email**: ¿se activa desde ya, se espera, o se descarta (solo RSS)?

## 6. No-objetivos

- No es un digest de todos los artículos publicados (eso ya lo hace el index).
- No personaliza por lector (anti-principio fundacional).
- No opina sobre por qué un medio calló.
