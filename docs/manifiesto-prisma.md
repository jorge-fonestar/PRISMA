# Manifiesto Prisma

**Rompiendo las paredes de tu burbuja digital**

---

## I. La fractura

Vivimos en una paradoja informativa sin precedentes: acceso ilimitado a la información y, al mismo tiempo, el mayor aislamiento cognitivo de la historia. Los algoritmos de recomendación de las plataformas digitales han construido cámaras de eco invisibles donde cada ciudadano habita una realidad informativa diseñada para confirmar lo que ya cree.

No es un accidente. Es un modelo de negocio.

La forma más rentable de mantenerte en la pantalla es darte la razón una y otra vez. Hasta que dejas de entender al que piensa distinto. Hasta que el que piensa distinto deja de ser alguien con argumentos y se convierte en un enemigo.

Las consecuencias son tres y son graves:

- **Erosión del pensamiento crítico.** El usuario deja de contrastar porque su entorno informativo nunca le contradice.
- **Polarización sin precedentes.** Los grupos ideológicos se perciben cada vez más incompatibles porque consumen realidades informativas distintas.
- **Deterioro del debate democrático.** Sin un suelo compartido de hechos, la deliberación pública se vuelve imposible.

La democracia no es votar. La democracia es poder entender al que vota distinto. Y eso requiere información plural. Hoy, esa información plural no existe en ningún sitio que un ciudadano normal visite.

---

## II. Qué es Prisma

Prisma es un **sintetizador de la inteligencia colectiva humana** que presenta la realidad desde múltiples ángulos de forma simultánea.

No es un medio de comunicación. No tiene redacción ni editorial. No te dice qué pensar. Es un **cartógrafo de posturas**: muestra simultáneamente las tres o más caras de cada debate relevante, con sus argumentos, sus defensores y sus fuentes originales.

Cada día, el sistema:
1. Lee fuentes de todo el espectro ideológico
2. Detecta los temas donde la cobertura diverge más entre cuadrantes
3. Sintetiza cada tema mostrando todas las posturas enfrentadas
4. Audita el resultado contra 11 criterios objetivos de neutralidad
5. Solo publica lo que supera la auditoría

El resultado es un artefacto con cinco secciones canónicas:
- **Titular neutral** — reformulación sin carga emocional
- **Resumen factual** — qué ha ocurrido, sin posicionamiento
- **Mapa de posturas** — quién defiende qué, con qué argumentos y qué fuentes
- **Lo que no se está diciendo** — silencios, omisiones y puntos ciegos del debate
- **Preguntas para pensar** — preguntas abiertas genuinas, sin respuesta implícita

---

## III. Lo que creemos

### La polarización es un problema estructural, no de contenido

No se arregla con "mejor periodismo". Los incentivos económicos de las plataformas están diseñados para amplificarla. Se necesita una intervención de diseño: sistemas cuya arquitectura impida la burbuja.

### La neutralidad es alcanzable por diseño

Ningún humano es neutral. Pero un sistema puede serlo si se construye con reglas verificables, auditoría automatizada y transparencia radical. La imparcialidad no depende de la intención del autor; depende de la arquitectura del proceso.

### La automatización garantiza consistencia

Un sistema automatizado aplica los mismos 11 axiomas a cada tema, sin fatiga, sin presión editorial, sin sesgo del momento. Los humanos escriben el estándar; la máquina lo ejecuta sin excepción.

### La transparencia supera a la perfección

Ningún sistema es perfecto. Pero un sistema transparente y auditable es infinitamente mejor que uno opaco que dice ser imparcial. En Prisma, cada fuente está citada, cada auditoría es pública, cada criterio está explicado. No pedimos que confíes en nosotros: pedimos que verifiques.

### El coste importa

Un servicio de información pública no puede depender de capital riesgo ni de publicidad. Prisma está diseñado para ser sostenible con donaciones voluntarias, sin infraestructura costosa, sin dependencias externas.

### La simplicidad genera confianza

Los sistemas complejos ocultan fallos. Prisma es deliberadamente simple: PHP, SQLite, una API. Todo el código es legible, auditable y desplegable en un hosting compartido. No hay cajas negras.

---

## IV. Cuatro pilares

| Pilar | Descripción |
|---|---|
| **Neutralidad por diseño** | Cada publicación es auditada contra 11 axiomas verificables del estándar Moral Core antes de publicarse. No es una aspiración: es un requisito de publicación. |
| **Visión 360°** | Cada tema se presenta con al menos tres posturas enfrentadas, citadas con fuentes de distintos cuadrantes ideológicos. El lector decide con qué se queda. |
| **Transparencia radical** | Todas las fuentes citadas. Todos los criterios explicados. Auditoría pública. Sin publicidad. Sin editorial. Sin muros de pago. Licencia abierta (CC BY-SA 4.0). |
| **Accesibilidad universal** | Mismo contenido para todos los usuarios. Sin personalización algorítmica. Sin registro. Sin tracking. La información imparcial no debería ser un privilegio. |

---

## V. El estándar Moral Core

Prisma no confía en la buena voluntad. Confía en criterios verificables.

Cada publicación pasa por un auditor independiente (en contexto separado del generador, para evitar sesgo de confirmación) que evalúa 11 axiomas:

| # | Axioma | Verificación |
|---|---|---|
| A1 | Pluralidad de posturas | Se identifican al menos 3 posturas distintas y explícitas |
| A2 | Pluralidad de fuentes | Se citan fuentes de al menos 3 cuadrantes ideológicos distintos |
| A3 | Simetría de extensión | Ninguna postura ocupa más del 50% ni menos del 15% del espacio |
| A4 | Simetría léxica | El lenguaje empleado para cada postura es equivalente en carga emocional |
| A5 | Atribución verificable | Toda afirmación fáctica disputada tiene fuente concreta enlazada |
| A6 | Distinción hecho/opinión | Lo verificable está marcado como hecho; lo interpretativo, como postura |
| A7 | Sin conclusión prescriptiva | El texto no recomienda qué pensar ni qué hacer |
| A8 | Transparencia de límites | Se mencionan los puntos de incertidumbre o datos faltantes |
| A9 | Sin omisión crítica | No falta ninguna postura mayoritaria del debate público |
| A10 | Coherencia con fuentes | Cada postura se corresponde con lo que las fuentes citadas realmente dicen |
| A11 | Sin sesgo geopolítico de bloque | No se favorece la narrativa de ningún bloque geopolítico específico |

**Veredictos:**
- **APTO** (10-11 axiomas) — publicación automática
- **REVISION** (8-9 axiomas) — regeneración con feedback, máximo 2 reintentos
- **RECHAZO** (<8 axiomas) — descarte y registro para análisis posterior

---

## VI. Cómo funciona: el motor de polarización informativa

Prisma no busca las noticias más importantes ni las más virales. Busca las más **polarizadas**: aquellas donde los medios de distintos cuadrantes ideológicos cuentan la misma historia de formas radicalmente distintas, o donde un lado habla y el otro calla.

El sistema calcula un **índice de polarización informativa (H-score)** para cada tema detectado, combinando tres señales:

- **Asimetría de cobertura (60%)** — Qué proporción de fuentes de cada lado del espectro cubren el tema. Un silencio editorial es tan revelador como un titular.
- **Divergencia léxica (25%)** — Distancia entre el vocabulario que usa cada cuadrante para describir el mismo hecho. Cuando la izquierda dice "recorte" y la derecha dice "ajuste", la divergencia es alta.
- **Varianza del espectro (15%)** — Dispersión de las posiciones ideológicas que cubren el tema. Mayor dispersión indica mayor relevancia política transversal.

La asimetría domina la fórmula porque la investigación académica (MIT Media Lab, Harvard Media Cloud) ha demostrado que la selección de cobertura — qué elige contar cada medio y qué elige ignorar — es la señal más fiable de sesgo editorial.

El índice de cada tema es público y verificable.

---

## VII. Qué no es Prisma

Es tan importante lo que Prisma es como lo que no es:

- **No es un medio de comunicación.** No tiene redacción, no emite opinión editorial, no compite con periodistas. Los complementa.
- **No es un agregador.** No enlaza titulares: genera contenido original sintetizado.
- **No es un fact-checker.** No verifica la veracidad de afirmaciones; cartografía las posturas sobre los hechos.
- **No es un verificador de verdad.** No posee la verdad. Mapea dónde están los desacuerdos.
- **No fuerza el consenso.** Muestra el desacuerdo para que se pueda entender.
- **No sustituye al periodismo.** Lo ilumina mostrándolo desde fuera.
- **No personaliza.** Todos los usuarios ven exactamente lo mismo. Romper la burbuja exige que la información sea igual para todos.

---

## VIII. El mapa de fuentes

Prisma lee diariamente fuentes de todo el espectro ideológico, organizadas en cuadrantes:

**España:** Desde Público y elDiario.es (izquierda) hasta Libertad Digital y El Debate (derecha populista), pasando por El País (centro-izquierda), EFE y Newtral (centro), ABC (centro-derecha) y El Mundo (derecha).

**Europa:** Politico Europe, Euronews, Le Monde, The Guardian, Der Spiegel, entre otros.

**Global:** Reuters, AP News, BBC, Al Jazeera English.

**Latinoamérica y Asia-Pacífico:** Fuentes seleccionadas por relevancia regional.

Más de 57 medios en total, distribuidos en 7 cuadrantes ideológicos y 5 ámbitos geográficos. La matriz completa es pública.

---

## IX. El compromiso ético

### Anonimato del proyecto
Prisma es un proyecto anónimo e independiente ("Equipo Prisma"). Esta independencia es deliberada: lo que importa es si el contenido pasa la auditoría, no quién lo firma.

### Sin ánimo de lucro
No hay inversores, no hay publicidad, no hay muros de pago. El proyecto aspira a sostenerse mediante donaciones voluntarias.

### Uso responsable de IA
El proceso completo de selección, síntesis y auditoría está automatizado con agentes de inteligencia artificial. Esto no es un defecto: es precisamente lo que permite la neutralidad. El sistema no tiene intereses políticos, no tiene opiniones personales que defender, y aplica exactamente los mismos criterios a cada publicación sin excepción.

Prisma es transparente sobre esto: la página de IA detalla los modelos empleados, las limitaciones conocidas y el proceso completo.

### Respeto a las fuentes
Solo se utilizan RSS públicos y APIs oficiales. Se citan titulares y fragmentos (no textos íntegros). Se enlaza siempre la fuente original. Se respeta robots.txt y se implementa rate limiting.

### Licencia abierta
Todo el contenido publicado se distribuye bajo Creative Commons BY-SA 4.0. Queremos que la información circule.

---

## X. La visión

Prisma existe porque creemos que la democracia necesita ciudadanos que entiendan lo que piensa el que vota distinto. No para estar de acuerdo con él, sino para poder discrepar con argumentos en lugar de con caricaturas.

Un prisma descompone la luz blanca en sus colores componentes. No destruye la luz: revela su complejidad. Del mismo modo, Prisma no destruye el debate público: revela sus múltiples dimensiones.

No pretendemos eliminar la polarización. Pretendemos hacerla comprensible.

No pretendemos tener la verdad. Pretendemos mostrar dónde están los desacuerdos.

No pretendemos sustituir tu criterio. Pretendemos devolvértelo.

---

*Equipo Prisma · Abril 2026*
*Proyecto independiente, anónimo y sin ánimo de lucro*
*Licencia del contenido: Creative Commons BY-SA 4.0*
