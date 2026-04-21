# Prisma — Ampliación de la matriz de fuentes

**Anexo al documento de especificación v2.0 — sustituye a la sección 5.2 "Matriz de fuentes por cuadrante ideológico"**
Versión: 2.1 — Abril 2026

---

## 1. Diagnóstico de la matriz actual

La matriz v2.0 (26 medios, 13 cuadrantes, 3 ámbitos) cumple razonablemente bien el equilibrio ideológico para política española (7 cuadrantes cubiertos) pero presenta tres desequilibrios estructurales que afectan a axiomas clave del estándar Moral Core:

- **Europa carece de pluralidad ideológica**: sólo 3 cuadrantes representados (centro-izquierda, centro, centro-derecha). Faltan izquierda, derecha y derecha-populista, lo que limita la aplicación del axioma **A2** en temas europeos.
- **Global presenta un sesgo de bloque occidental**: 4 de 5 medios son occidentales (Guardian, BBC, Reuters, AP), con Al Jazeera como única voz no-occidental. Esto vulnera de forma sistemática el axioma **A11** (ausencia de sesgo geopolítico) en temas internacionales.
- **Ausencia total de Latinoamérica y Asia-Pacífico** como ámbitos diferenciados. La sección "Global" concentra toda la no-Europa en 5 medios insuficientes para cubrir eventos regionales relevantes fuera del eje atlántico.

Este anexo propone una matriz ampliada a **57 medios en 5 ámbitos geográficos**, con **enfoque híbrido por ámbito**: equilibrio ideológico (cuadrantes) donde el contexto es culturalmente homogéneo (España, Europa), equilibrio geopolítico (bloques) donde domina el eje civilizacional (Global, LatAm, Asia-Pacífico).

---

## 2. Nuevas fuentes por ámbito

### 2.1 España — 4 nuevas fuentes

Se conservan las 14 fuentes actuales. Se añaden 4 para completar los cuadrantes más delgados y reforzar el centro independiente.

| Medio | Cuadrante | URL del feed RSS | Justificación |
|---|---|---|---|
| Newtral | Centro / fact-check | `https://www.newtral.es/feed/` | Referente español en verificación; aporta criterio independiente al cuadrante centro |
| El Confidencial | Centro | `https://rss.elconfidencial.com/espana/` | Digital nativo con periodismo de investigación; cubre hueco entre EFE y The Objective |
| La Razón | Derecha | `https://www.larazon.es/?outputType=xml` | Refuerza cuadrante derecha junto a ABC y El Mundo; figuraba en spec original v1.0 |
| OKDIARIO | Derecha-populista | `https://okdiario.com/feed` | Uno de los digitales más leídos del país; complementa Libertad Digital y El Debate |

**Total España: 18 medios en 7 cuadrantes.**

### 2.2 Europa — 8 nuevas fuentes

Se conservan las 7 fuentes actuales. Se añaden 8 para: (i) llenar huecos ideológicos (izquierda, derecha, derecha-populista), (ii) incorporar Italia y Europa del Este.

| Medio | País | Cuadrante | URL del feed RSS | Justificación |
|---|---|---|---|---|
| Libération | Francia | Izquierda | `http://rss.liberation.fr/rss/latest/` | Referente histórico de izquierda francesa |
| Il Manifesto | Italia | Izquierda | `https://ilmanifesto.it/feed` | Voz de izquierda italiana; diversidad cultural |
| La Repubblica | Italia | Centro-izquierda | `https://www.repubblica.it/rss/homepage/rss2.0.xml` | Primera representación italiana amplia |
| Süddeutsche Zeitung | Alemania | Centro-izquierda | `https://rss.sueddeutsche.de/rss/Topthemen` | Referente alemán de calidad |
| Corriere della Sera | Italia | Centro-derecha | `https://xml2.corriereobjects.it/rss/homepage.xml` | Balance italiano del espectro |
| The Telegraph | Reino Unido | Derecha | `https://www.telegraph.co.uk/rss.xml` | Cubre hueco de derecha europea clara |
| UnHerd | Reino Unido | Derecha-populista | `https://unherd.com/feed/atom/` | Crítica influyente al consenso liberal |
| Notes from Poland | Polonia | Centro-derecha | `https://notesfrompoland.com/feed/` | Perspectiva de Europa del Este, en inglés |

**Total Europa: 15 medios en 6 cuadrantes.**

### 2.3 Global — 6 nuevas fuentes

Se conservan las 5 fuentes actuales. Se añaden 6 con prioridad al axioma **A11**: romper el sesgo occidental incorporando voces de bloques rusos, árabes e israelíes, y completar la representación de EEUU con sus cuadrantes principales.

| Medio | Bloque / cuadrante | URL del feed RSS | Justificación |
|---|---|---|---|
| The New York Times | Occidente, centro-izquierda | `https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml` | Referente de la prensa estadounidense; ausente en matriz actual |
| Wall Street Journal | Occidente, centro-derecha | `https://feeds.a.dj.com/rss/RSSWorldNews.xml` | Perspectiva económica conservadora de EEUU |
| The Economist | Occidente, centro-derecha | `https://www.economist.com/international/rss.xml` | Análisis geoeconómico de referencia |
| Meduza | Ruso, exilio / crítico | `https://meduza.io/rss2/en/all` | Contexto ruso sin propaganda estatal. **RT y TASS están bloqueados en la UE por sanciones desde 2022**, por lo que Meduza (medio ruso independiente en Letonia) es la alternativa viable |
| Middle East Eye | Árabe / crítico | `https://www.middleeasteye.net/rss` | Perspectiva no-occidental de Oriente Próximo |
| Haaretz | Israel, centro-izquierda | `https://www.haaretz.com/srv/haaretz-latest-headlines` | Contrapeso dentro de Oriente Próximo; voz crítica israelí |

**Total Global: 11 medios en 4 bloques geopolíticos.**

### 2.4 Latinoamérica — nuevo ámbito, 7 fuentes

Ámbito creado de cero. Selección por criterio mixto: 3 países con mayor peso mediático (Argentina, México, Brasil) con representación de izquierda y derecha en cada uno, más Colombia como ancla del norte de la región.

| Medio | País | Cuadrante | URL del feed RSS | Justificación |
|---|---|---|---|---|
| Página/12 | Argentina | Izquierda | `https://www.pagina12.com.ar/rss/portada` | Izquierda kirchnerista; referente progresista argentino |
| Clarín | Argentina | Centro-derecha | `https://www.clarin.com/rss/lo-ultimo/` | Diario de mayor tirada; línea editorial moderada conservadora |
| La Jornada | México | Izquierda | `https://www.jornada.com.mx/rss/edicion.xml` | Izquierda histórica mexicana; cercano a la "Cuarta Transformación" |
| El Universal | México | Centro-derecha | `https://www.eluniversal.com.mx/rss.xml` | Contrapeso a La Jornada en México |
| Folha de S.Paulo | Brasil | Centro-izquierda | `https://feeds.folha.uol.com.br/emcimadahora/rss091.xml` | Mayor calidad editorial en Brasil |
| O Estado de S.Paulo (Estadão) | Brasil | Centro-derecha | `https://www.estadao.com.br/rss/ultimas.xml` | Complemento conservador a Folha |
| El Tiempo | Colombia | Centro | `https://www.eltiempo.com/rss/section/3/home` | Referente colombiano; ancla andina de la región |

**Total Latinoamérica: 7 medios en 4 cuadrantes.**

### 2.5 Asia-Pacífico — nuevo ámbito, 7 fuentes

Ámbito creado de cero. Prioridad al axioma **A11**: enfrentar la narrativa oficial china (Global Times) con contrapesos de Hong Kong (SCMP), Taiwán (Taipei Times) y las democracias de la región (India, Japón, Corea, Australia).

| Medio | País / región | Bloque / cuadrante | URL del feed RSS | Justificación |
|---|---|---|---|---|
| South China Morning Post | Hong Kong | China moderada | `https://www.scmp.com/rss/91/feed` | Cobertura desde Hong Kong; propiedad de Alibaba, sujeta a presiones post-2020 pero aún la voz más completa sobre China en inglés |
| Global Times | China | Oficial chino | `https://www.globaltimes.cn/rss/outbrain.xml` | Voz oficial del Partido Comunista Chino; necesaria para A11 como perspectiva de bloque |
| Taipei Times | Taiwán | Contrapeso Beijing | `https://www.taipeitimes.com/xml/index.rss` | Perspectiva taiwanesa; contrapunto a Global Times |
| The Hindu | India | Centro-izquierda | `https://www.thehindu.com/feeder/default.rss` | Prensa india de referencia con línea progresista |
| The Japan Times | Japón | Centro | `https://www.japantimes.co.jp/feed/` | Referente japonés en inglés |
| The Korea Herald | Corea del Sur | Centro | `https://www.koreaherald.com/rss` | Referente surcoreano en inglés |
| Sydney Morning Herald | Australia | Centro-izquierda | `https://www.smh.com.au/rss/feed.xml` | Representación del Pacífico Sur |

**Total Asia-Pacífico: 7 medios en 5 cuadrantes/bloques.**

---

## 3. Matriz final consolidada

Esta sección sustituye íntegramente la sección **5.2 "Matriz de fuentes por cuadrante ideológico"** del documento de especificación v2.0.

### 3.1 España

| Cuadrante | Medio | Feed RSS |
|---|---|---|
| Izquierda-populista | Diario Red | `https://www.diario-red.com/rss/listado` |
| Izquierda-populista | El Salto | `https://www.elsaltodiario.com/edicion-general/feed` |
| Izquierda | Público | `https://www.publico.es/rss` |
| Izquierda | elDiario.es | `https://www.eldiario.es/rss/` |
| Centro-izquierda | El País | `https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/portada` |
| Centro-izquierda | InfoLibre | `https://www.infolibre.es/rss/rss.xml` |
| Centro / fact-check | Newtral | `https://www.newtral.es/feed/` |
| Centro | EFE | `https://efe.com/feed/` |
| Centro | 20minutos | `https://www.20minutos.es/rss/` |
| Centro | El Confidencial | `https://rss.elconfidencial.com/espana/` |
| Centro-derecha | La Vanguardia | `https://www.lavanguardia.com/rss/home.xml` |
| Centro-derecha | The Objective | `https://theobjective.com/feed/` |
| Derecha | ABC | `https://www.abc.es/rss/2.0/portada/` |
| Derecha | El Mundo | `https://e00-elmundo.uecdn.es/elmundo/rss/portada.xml` |
| Derecha | La Razón | `https://www.larazon.es/?outputType=xml` |
| Derecha-populista | Libertad Digital | `https://feeds.feedburner.com/libertaddigital/portada` |
| Derecha-populista | El Debate | `https://www.eldebate.com/rss/` |
| Derecha-populista | OKDIARIO | `https://okdiario.com/feed` |

**Subtotal: 18 medios en 7 cuadrantes.**

### 3.2 Europa

| Cuadrante | Medio | País | Feed RSS |
|---|---|---|---|
| Izquierda | Libération | FR | `http://rss.liberation.fr/rss/latest/` |
| Izquierda | Il Manifesto | IT | `https://ilmanifesto.it/feed` |
| Centro-izquierda | The Guardian | UK | `https://www.theguardian.com/world/europe-news/rss` |
| Centro-izquierda | Le Monde | FR | `https://www.lemonde.fr/europe/rss_full.xml` |
| Centro-izquierda | La Repubblica | IT | `https://www.repubblica.it/rss/homepage/rss2.0.xml` |
| Centro-izquierda | Süddeutsche Zeitung | DE | `https://rss.sueddeutsche.de/rss/Topthemen` |
| Centro | Euronews | — | `https://www.euronews.com/rss?level=theme&name=news` |
| Centro | Politico Europe | — | `https://www.politico.eu/feed/` |
| Centro | DW | DE | `https://rss.dw.com/rdf/rss-en-eu` |
| Centro-derecha | Der Spiegel | DE | `https://www.spiegel.de/international/index.rss` |
| Centro-derecha | Financial Times | UK | `https://www.ft.com/world/europe?format=rss` |
| Centro-derecha | Corriere della Sera | IT | `https://xml2.corriereobjects.it/rss/homepage.xml` |
| Centro-derecha | Notes from Poland | PL | `https://notesfrompoland.com/feed/` |
| Derecha | The Telegraph | UK | `https://www.telegraph.co.uk/rss.xml` |
| Derecha-populista | UnHerd | UK | `https://unherd.com/feed/atom/` |

**Subtotal: 15 medios en 6 cuadrantes.**

### 3.3 Global

| Bloque / cuadrante | Medio | Feed RSS |
|---|---|---|
| Occidente, centro-izquierda | The Guardian | `https://www.theguardian.com/world/rss` |
| Occidente, centro-izquierda | BBC | `https://feeds.bbci.co.uk/news/world/rss.xml` |
| Occidente, centro-izquierda | The New York Times | `https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml` |
| Occidente, centro (agencia) | Reuters | `https://www.reutersagency.com/feed/` |
| Occidente, centro (agencia) | AP News | `https://rsshub.app/apnews/topics/apf-topnews` |
| Occidente, centro-derecha | Wall Street Journal | `https://feeds.a.dj.com/rss/RSSWorldNews.xml` |
| Occidente, centro-derecha | The Economist | `https://www.economist.com/international/rss.xml` |
| Ruso, exilio / crítico | Meduza | `https://meduza.io/rss2/en/all` |
| Árabe (Qatar) | Al Jazeera | `https://www.aljazeera.com/xml/rss/all.xml` |
| Árabe / crítico | Middle East Eye | `https://www.middleeasteye.net/rss` |
| Israel, centro-izquierda | Haaretz | `https://www.haaretz.com/srv/haaretz-latest-headlines` |

**Subtotal: 11 medios en 4 bloques geopolíticos.**

### 3.4 Latinoamérica

| Cuadrante | Medio | País | Feed RSS |
|---|---|---|---|
| Izquierda | Página/12 | AR | `https://www.pagina12.com.ar/rss/portada` |
| Izquierda | La Jornada | MX | `https://www.jornada.com.mx/rss/edicion.xml` |
| Centro-izquierda | Folha de S.Paulo | BR | `https://feeds.folha.uol.com.br/emcimadahora/rss091.xml` |
| Centro | El Tiempo | CO | `https://www.eltiempo.com/rss/section/3/home` |
| Centro-derecha | Clarín | AR | `https://www.clarin.com/rss/lo-ultimo/` |
| Centro-derecha | El Universal | MX | `https://www.eluniversal.com.mx/rss.xml` |
| Centro-derecha | Estadão | BR | `https://www.estadao.com.br/rss/ultimas.xml` |

**Subtotal: 7 medios en 4 cuadrantes.**

### 3.5 Asia-Pacífico

| Bloque / cuadrante | Medio | País | Feed RSS |
|---|---|---|---|
| China, moderada | South China Morning Post | HK | `https://www.scmp.com/rss/91/feed` |
| China, oficial | Global Times | CN | `https://www.globaltimes.cn/rss/outbrain.xml` |
| Taiwán, contrapunto | Taipei Times | TW | `https://www.taipeitimes.com/xml/index.rss` |
| India, centro-izquierda | The Hindu | IN | `https://www.thehindu.com/feeder/default.rss` |
| Japón, centro | The Japan Times | JP | `https://www.japantimes.co.jp/feed/` |
| Corea, centro | The Korea Herald | KR | `https://www.koreaherald.com/rss` |
| Australia, centro-izquierda | Sydney Morning Herald | AU | `https://www.smh.com.au/rss/feed.xml` |

**Subtotal: 7 medios en 5 cuadrantes/bloques.**

### 3.6 Resumen global

| Ámbito | Medios | Cuadrantes / bloques |
|---|---|---|
| España | 18 | 7 |
| Europa | 15 | 6 |
| Global | 11 | 4 bloques geopolíticos |
| Latinoamérica | 7 | 4 |
| Asia-Pacífico | 7 | 5 |
| **Total** | **58** | **26 segmentos distintos** |

---

## 4. Notas operativas

### 4.1 Estado de verificación de los feeds

Los feeds de los 26 medios que ya estaban en la matriz v2.0 se asumen verificados en producción. De los 32 medios nuevos, la verificación se realiza por fases desde el servidor de producción.

#### Fase 1 — España (+4) y Europa (+8): validada 2026-04-21

Ejecutada desde el servidor de producción con `validate_feeds.php`. Resultado: **10/12 OK**, 2 feeds corregidos.

| Medio | Ámbito | Formato | Items | ms | Estado | Notas |
|---|---|---|---|---|---|---|
| Newtral | España | RSS 2.0 | 10 | 129 | OK | — |
| El Confidencial | España | Atom | 15 | 90 | OK | — |
| La Razón | España | RSS 2.0 | 15 | 52 | OK | URL original `/rss/portada.xml` devolvía 404. Sustituida por `/?outputType=xml` (patrón Arc XP CMS) |
| OKDIARIO | España | RSS 2.0 | 50 | 368 | OK | Feed pesado (365 KB); respuesta más lenta que la media |
| Libération | Europa | RSS 2.0 | 50 | 174 | OK | Contenido en francés |
| Il Manifesto | Europa | RSS 2.0 | 50 | 726 | OK | Contenido en italiano; servidor lento (~700ms) |
| La Repubblica | Europa | RSS 2.0 | 30 | 49 | OK | Contenido en italiano |
| Süddeutsche Zeitung | Europa | RSS 2.0 | 15 | 27 | OK | Contenido en alemán; respuesta muy rápida |
| Corriere della Sera | Europa | RSS 2.0 | 69 | 54 | OK | Contenido en italiano |
| The Telegraph | Europa | RSS 2.0 | 120 | 204 | OK | Feed con muchos items; paywall parcial |
| UnHerd | Europa | Atom | 30 | 1215 | OK | Feed RSS (`/feed/`) tiene bytes `0xA0` inválidos en UTF-8 (bug de WordPress). Sustituido por Atom (`/feed/atom/`) que codifica correctamente. Servidor lento (~1.2s) |
| Notes from Poland | Europa | RSS 2.0 | 12 | 87 | OK | — |

#### Fase 2 — Global (+6): pendiente de validación

Feeds a verificar: The New York Times, Wall Street Journal, The Economist, Meduza, Middle East Eye, Haaretz. Atención especial a NYT/WSJ (detección de scraping) y Meduza (intermitencias por DDoS).

#### Fase 3 — Latinoamérica (+7) y Asia-Pacífico (+7): pendiente de validación

Feeds a verificar: Página/12, Clarín, La Jornada, El Universal, Folha de S.Paulo, Estadão, El Tiempo, SCMP, Global Times, Taipei Times, The Hindu, Japan Times, Korea Herald, Sydney Morning Herald. Atención especial a Global Times (infraestructura inestable) y El Universal/El Tiempo (patrón estándar no verificado).

### 4.2 Paywalls parciales

Varios feeds entregan titular + resumen pero el artículo completo requiere suscripción. Esto **no afecta al pipeline Prisma**, que se limita a titulares y metadatos por diseño (uso legítimo del feed), pero conviene documentarlo porque puede reducir la calidad del clustering en el Agente Curador si el resumen es demasiado corto. Medios afectados: Financial Times, The New York Times, Wall Street Journal, The Economist, The Telegraph, Le Monde, La Repubblica, Corriere della Sera, Haaretz.

### 4.3 Medios rusos y sanciones UE

RT (Russia Today) y TASS, que serían las fuentes naturales para la perspectiva oficial rusa, **están bloqueados en la Unión Europea desde marzo de 2022** por el paquete de sanciones posterior a la invasión de Ucrania. Un servidor alojado en España no puede legalmente acceder a sus feeds.

Como alternativa se ha incluido **Meduza**, medio ruso independiente en el exilio (sede en Letonia), con redacción en ruso, equipo de periodistas ex-Lenta.ru, y línea editorial crítica al Kremlin desde dentro de la cultura rusa. No es perspectiva oficial del bloque, pero sí es la voz rusa accesible con mayor rigor y pluralismo interno. **Esto es una limitación conocida del axioma A11** que debe documentarse en la página `/ia` del sitio público.

Si en el futuro se flexibilizan las sanciones o se encuentra una alternativa viable (por ejemplo, `kommersant.ru` o `novayagazeta.eu`, esta última también en el exilio), se reevaluará.

### 4.4 Rate limiting y cortesía

La regla del spec v2.0 (máx. 1 petición por segundo por dominio) sigue siendo válida, pero algunos medios son especialmente sensibles:

- **NYT, WSJ, FT**: detección agresiva de scraping. Conviene User-Agent descriptivo (`Prisma/1.0 (+https://[dominio])`).
- **Global Times**: infraestructura inestable; esperar timeout de hasta 30s.
- **Meduza**: sufre intermitencias por ataques DDoS dirigidos. Reintentar hasta 3 veces con backoff exponencial.
- **Al Jazeera, SCMP**: feeds fiables pero actualización menos frecuente (cada 15-30 min vs cada 5 min en prensa europea).

### 4.5 Idiomas y traducción

Con la matriz ampliada, el Agente Curador recibe titulares en 10 idiomas (ES, EN, FR, DE, IT, PT, AR árabe vía Al Jazeera, HE hebreo vía Haaretz, RU ruso, ZH chino). El modelo Sonnet 4.6 maneja todos de forma nativa, por lo que no se requiere traducción previa. Sin embargo:

- El Sintetizador debe traducir al español las citas incorporadas al artefacto y marcar la traducción con `[trad.]` tras la cita.
- Al Jazeera feed `all.xml` está en inglés; la edición árabe tiene feed separado que podría añadirse en una fase posterior si se detecta que el filtrado por idioma introduce sesgo.

### 4.6 Impacto en coste del pipeline

La ampliación de 26 a 58 fuentes **no multiplica el coste proporcionalmente**. El Agente Curador procesa titulares y fragmentos de RSS (cuerpo muy ligero); el Sintetizador y Auditor trabajan sólo con los 8-12 artículos seleccionados por tema, que no varía. Estimación de sobrecoste:

| Fase | Impacto estimado |
|---|---|
| Ingesta y filtrado (Curador) | +40% tokens de entrada (de ~30k a ~42k por día) |
| Síntesis (Sintetizador) | Sin cambios (trabaja sobre selección) |
| Auditoría (Auditor) | Sin cambios |

Proyección: coste anual Paso 2 pasa de ~640 € a **~850-900 €**. Con prompt caching agresivo del Curador (los feeds suelen actualizarse en incrementos), el sobrecoste real se puede contener en torno a **+25%**.

### 4.7 Revisión periódica de la matriz

Se recomienda revisar la matriz de fuentes **cada 6 meses** para:

- Validar que todos los feeds siguen activos (medios que cierran, cambian de URL, adoptan paywall completo).
- Detectar nuevos medios relevantes o cambios de línea editorial que modifiquen el cuadrante asignado.
- Evaluar si la distribución de apariciones en artefactos publicados muestra algún sesgo sistemático hacia ciertos medios (métrica nueva a añadir al dashboard).

---

**Fin del anexo v2.1**

*Este documento sustituye la sección 5.2 del spec v2.0 y añade las secciones operativas 4.x como apéndice. El resto del spec (arquitectura, axiomas, pipeline, hoja de ruta) permanece sin cambios.*
