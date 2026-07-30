# Changelog de PolarPrisma

> Histórico breve y con fechas de las operaciones del proyecto. Cada entrada se
> redacta pensando en los seguidores cercanos (canal de Telegram): claro, corto y
> sin jerga innecesaria — el detalle técnico vive en los commits y en STATUS.md.
> Se añade una entrada nueva **arriba** cada vez que Jorge pide "changelog".

---

## 2026-07-30 — Un radar más limpio y noticias mejor explicadas 🧹

- **Se acabaron las noticias repetidas.** Hasta ahora la misma noticia podía
  aparecer varias veces en el radar con titulares algo distintos (el indulto a
  Borràs llegó a salir 5 ó 6 veces). Ahora el sistema entiende que son la misma
  y las junta en una sola, reuniendo todas sus fuentes.
- **Cada análisis, en dos partes claras.** Dentro de una noticia analizada verás
  separado **"Lo que está documentado"** (los hechos, con un indicador de cuán
  sólido es el análisis) de **"Lecturas e interpretaciones"** (cómo lo cuenta cada
  lado, dejando claro que son lecturas legítimas, no una única verdad).
- **Resúmenes que aportan de verdad.** La frase que acompaña a cada titular ya no
  lo repite: ahora añade lo que falta —la cifra, la causa, quién reacciona—.
- **Menos falsas alarmas.** El detector distingue mejor la polarización real de
  las simples formas distintas de decir lo mismo.

## 2026-07-25 — Modo oscuro, más fuentes y un % más justo 🌙

- **Modo oscuro para todo el mundo.** La web arranca en oscuro (más cómoda de
  leer, sobre todo de noche) y se han corregido botones y enlaces que en oscuro
  se leían mal.
- **Más fuentes por noticia.** Se amplió la ventana de lectura a 48 h y se mejoró
  la forma de agrupar los artículos de un mismo tema: ahora se recogen mejor
  todos los medios que cubren una historia (por ejemplo, El País en la noticia
  del 016, que antes se quedaba fuera).
- **El % de polarización, más justo.** Al analizar una noticia a fondo (con el
  texto completo), el porcentaje se **recalcula** y se corrige. En la ficha se ve
  el valor afinado y, con transparencia, cuál fue la estimación inicial — la
  detección rápida solo mira titulares y a veces se pasaba.
- **Menos falsas alarmas de raíz.** El detector rápido ya no confunde "decir lo
  mismo con otras palabras" con polarización real: solo cuenta como tal si los
  medios atribuyen causas, culpables o juicios distintos al mismo hecho.
- **Ejemplo real corregido:** la noticia del 016 marcaba 66 % sin divergencia
  real → tras el análisis quedó en 15 %, que es lo justo.

## 2026-07-24 — Suscríbete por RSS 📡

- **PolarPrisma ya tiene RSS.** Puedes seguirlo desde Feedly o cualquier lector:
  un feed con los **análisis** publicados y otro con el **radar de polarización**
  del día. Basta con pegar `polarprisma.org` en Feedly y los detecta solos, o
  usar los enlaces del pie de página.

## 2026-07-24 — Más claras, mejor explicadas 🔍

- **Cada noticia estrena un resumen de una frase.** Ahora, además del titular,
  cada tema con cobertura de varios lados lleva un resumen breve y neutral de
  qué ha pasado — en la web y en el aviso de Telegram. (Redactados los de los
  últimos 7 días.)
- **El aviso diario de Telegram, renovado.** Título completo, resumen debajo y
  una marca que indica si la noticia ya tiene análisis multipostura (🔬) o
  sigue solo en el radar (🔹).
- **Las noticias analizadas muestran ahora todas sus fuentes originales**, no
  solo las citadas en el análisis.
- **Favicon propio** (el prisma) en la pestaña del navegador.
- **Arreglos**: noticias que aparecían marcadas como "analizadas" sin estarlo;
  una franja blanca en la cabecera de la página del proyecto; y márgenes justos
  en las fichas. Acceso directo a los 11 axiomas desde cada análisis.
- **Ajuste de ritmo**: 1 análisis en profundidad al día (por ahora, para
  contener costes), respetando siempre los que se lancen a mano.

## 2026-07-20 — PolarPrisma vuelve a la vida 🔺

- **El radar vuelve a funcionar solo.** Llevaba parado desde el 4 de mayo; ahora
  escanea la prensa de todo el espectro cada 4 horas y publica análisis a diario
  de forma automática. El primer artículo de la nueva etapa salió el mismo día:
  la declaración de Rajoy y Cospedal por el caso Kitchen, analizada desde todas
  las posturas y auditada contra los 11 axiomas.
- **Análisis mejores y a mitad de coste.** Motor de IA actualizado (Claude
  Sonnet 5) y procesado por lotes: cada análisis cuesta la mitad, así que pasamos
  de 1 a 2 análisis diarios con el mismo presupuesto.
- **El agrupador de noticias ahora lee la entradilla, no solo el titular** —
  tanto para detectar que dos artículos hablan del mismo tema como para juzgar
  cómo lo encuadra cada medio.
- **Menú nuevo**: Hoy · Radar (los últimos 7 días, solo lo más polarizado) ·
  Silencios · ¿Qué es PolarPrisma? Y la marca queda unificada como
  **PolarPrisma** en toda la web.
- **Nueva página, "Los silencios de la semana"**: los temas que un bloque
  ideológico contó mientras el otro callaba. Documenta el silencio; el motivo
  lo juzgas tú. → https://polarprisma.org/silencios.php
- **La web vuelve a ser usable en móvil** (el menú desaparecía en pantallas
  pequeñas).
- **Seguridad**: detectamos que un fichero de configuración del servidor era
  accesible públicamente. Se corrigió el mismo día, se rotaron todas las claves
  y se endureció el servidor. Transparencia ante todo: ningún dato de lectores
  se vio afectado (no los recogemos).
- **En el horno**: newsletter semanal con lo más polarizado de la semana y los
  silencios editoriales, y una nueva ronda de contacto con los medios.
