<?php
/**
 * Prisma — Configuración central.
 *
 * Carga .env si existe y expone toda la config como array.
 */

// Cargar .env
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (!getenv($key)) putenv("$key=$val");
    }
}

// Using $GLOBALS instead of define() — PHP 7.4 on shared hosting
// silently fails with deeply nested array constants.

/**
 * Access config from anywhere. Replaces the old PRISMA_CONFIG constant.
 */
function prisma_cfg() {
    return $GLOBALS['_PRISMA_CFG'];
}

$GLOBALS['_PRISMA_CFG'] = array(

    // ── API Anthropic ───────────────────────────────────────────────
    'anthropic_api_key'   => getenv('ANTHROPIC_API_KEY') ?: '',
    // sonnet-5 desde jul-2026: mejor calidad, mismo precio de tarifa (intro
    // $2/$10 hasta 31-ago-2026). Ojo: tokenizador nuevo (~30% más tokens) y
    // thinking adaptativo por defecto — por eso max_tokens de síntesis y
    // auditoría van a 8192 (el thinking consume presupuesto de salida).
    'model_synth'         => 'claude-sonnet-5',
    'model_audit'         => 'claude-sonnet-5',
    'model_triage'        => 'claude-haiku-4-5-20251001',
    // Presupuesto de salida para síntesis/auditoría (sync y batch)
    'max_tokens_pipeline' => 8192,
    // Verificación factual: el sintetizador contrasta cifras/fechas/decisiones con
    // fuentes primarias (INE/BOE/Eurostat…) vía web_search. Fuerza la vía síncrona
    // (la Batches API no lleva tools). Con articulos_dia=1 el sobrecoste es asumible.
    'synth_web_search'     => true,
    'synth_web_search_max' => 2,      // tope de búsquedas por análisis
    // Prompt caching del system (largo y fijo): abarata el input repetido de los
    // reintentos del bucle auditor; rinde más cuanto más volumen de análisis.
    'synth_prompt_cache'   => true,

    // ── Ingest ──────────────────────────────────────────────────────
    'ingest_key'          => getenv('PRISMA_INGEST_KEY') ?: '',

    // ── Sitio / notificaciones ──────────────────────────────────────
    'site_url'            => getenv('PRISMA_SITE_URL') ?: 'https://polarprisma.org',
    // Digest diario por Telegram (lib/telegram.php). Sin token/chat_id, desactivado.
    // Un único mensaje/día (cron 08:00) con las noticias polarizadas de ayer:
    // una línea por tema (semáforo + % + título + link). Se envía con
    // digest_telegram.php.
    'telegram_bot_token'    => getenv('TELEGRAM_BOT_TOKEN') ?: '',
    'telegram_chat_id'      => getenv('TELEGRAM_CHAT_ID') ?: '',
    // Chat privado para avisos de OPERACIÓN (salud de feeds), NUNCA el canal público.
    // Opcional: si está vacío, los avisos solo aparecen en el panel.
    'telegram_admin_chat_id' => getenv('TELEGRAM_ADMIN_CHAT_ID') ?: '',
    'telegram_digest_umbral' => 0.35, // polarización mínima para entrar en el digest
    'telegram_digest_cap'    => 10,   // máximo de líneas (el resto se resume)

    // ── Panel ───────────────────────────────────────────────────────
    'panel_pass'          => getenv('PRISMA_PANEL_PASS') ?: 'prisma2026',

    // ── Límites de coste ────────────────────────────────────────────
    'daily_budget_usd'    => 6.00,
    // Fase 2 vía Message Batches API (50% de coste; el cron tarda minutos más).
    // El panel y los temas manuales siguen usando la vía síncrona.
    'use_batch_api'       => true,

    // ── Publicación ─────────────────────────────────────────────────
    'timezone'            => 'Europe/Madrid',
    'articulos_dia'       => 1,
    // Solo se analizan temas del radar de los últimos N días: sin esta
    // ventana, el backlog antiguo compite por h_score con la actualidad.
    'analizar_ventana_dias' => 2,
    'min_cuadrantes'      => 3,             // Mínimo de cuadrantes para ir al pipeline Sonnet
    'umbral_tension'      => 0.40,          // H mínimo para ser candidato a análisis (v2: era 0.55)

    // ── Clustering semántico ────────────────────────────────────
    // Usar también la descripción RSS (no solo el titular) para agrupar
    // artículos y para la señal de divergencia léxica. Jaccard ponderado:
    // keywords del titular pesan 1.0, las de la descripción cluster_desc_peso.
    'cluster_usar_descripcion' => true,
    'cluster_desc_peso'        => 0.5,
    'cluster_desc_max_chars'   => 500,
    'cluster_umbral'           => 0.3,   // similitud mínima para agrupar

    // ── Scoring v2 ──────────────────────────────────────────────
    'scoring_alpha'       => 0.4,
    'scoring_beta'        => 0.6,
    'scoring_gamma'       => 0.15,
    'scoring_mapeo'       => 'B',
    'gate_haiku_enabled'  => true,
    'gate_haiku_cache'    => true,
    // Incluir la entradilla (truncada) junto a cada titular en el input del
    // gate: mejora el juicio de framing_divergence a cambio de ~2-3× el coste
    // del gate (que es ~céntimos/día). Activado en pruebas desde 20-jul-2026;
    // evaluar a los pocos días comparando fd/relevancia con el histórico.
    'gate_incluir_descripcion' => true,
    'gate_desc_max_chars'      => 160,
    // gate_haiku_batch_api deferred for later iteration (Anthropic Batch API, 50% discount)

    // ── Listas de filtrado scoring v2 ────────────────────────────
    'lista_negativa' => array(
        // Deportes
        'laliga', 'champions', 'premier league', 'fichaje', 'jornada',
        'penalti', 'futbol', 'baloncesto',
        'formula 1', 'moto gp', 'ciclismo',
        'camp nou', 'bernabeu', 'mestalla', 'mutua madrid open', 'atp', 'wta',
        // Lotería
        'bonoloto', 'primitiva', 'euromillones', 'loteria', 'sorteo',
        'numero premiado',
        // Entretenimiento
        'concierto', 'gira mundial', 'alfombra roja', 'look de',
        'red carpet', 'coachella', 'reality', 'gran hermano', 'eurovision',
        // Curiosidades
        'curiosidad', 'no creeras', 'verdad sobre',
        // Boletines recurrentes de feeds (Euronews etc.): forman un cluster
        // basura cada día al parecerse entre sí los títulos de boletín
        'latest news bulletin', 'news bulletin |',
        // Meteorología rutinaria
        'prevision meteorologica', 'temperaturas hoy', 'lluvias para',
    ),
    'lista_positiva' => array(
        // Instituciones
        'congreso', 'senado', 'parlamento', 'tribunal constitucional',
        'tribunal supremo', 'audiencia nacional', 'gobierno', 'moncloa',
        'comision europea', 'parlamento europeo', 'otan', 'onu', 'fmi',
        // Cargos
        'presidente', 'ministro', 'consejero', 'alcalde', 'comisario',
        'fiscal', 'juez', 'magistrado',
        // Partidos
        'psoe', 'pp', 'vox', 'sumar', 'podemos', 'erc', 'junts',
        'pnv', 'bildu', 'ciudadanos',
        // Actores
        'sanchez', 'feijoo', 'abascal', 'diaz', 'puigdemont',
        'trump', 'biden', 'macron', 'von der leyen',
        // Conceptos policy
        'presupuestos', 'decreto', 'ley organica', 'reforma',
        'regulacion', 'sancion', 'embargo', 'tratado',
    ),

    // ── RSS por ámbito y cuadrante ──────────────────────────────────
    // Formato: array('Nombre', 'URL', 'Nota de transparencia')
    // La nota de transparencia se publica en la página pública (A8).
    'fuentes' => array(
        'españa' => array(
            'izquierda-populista' => array(
                // Diario Red: 403 Forbidden (bot block) — removed until URL change
                array('El Salto', 'https://www.elsaltodiario.com/general/feed',
                    'Cooperativa de trabajadores y lectores. 70% financiado por ~10.000 socios suscriptores. Publicidad limitada al 20% por estatutos. Sin accionistas externos.'),
                array('Kaos en la Red', 'https://kaosenlared.net/feed/',
                    'Medio digital de izquierda alternativa/anticapitalista, gestión colectiva sin ánimo de lucro. Añadido jul-2026 para compensar el flanco izquierdo (Público/CTXT sin RSS).'),
            ),
            'izquierda' => array(
                array(
                    'medio' => 'Público',
                    'url' => null,
                    'modalidad' => 'no_disponible',
                    'categoria_acceso' => 'C',
                    'transparencia' => 'Medio sin RSS nativo (política editorial). Autorización pendiente de solicitud.',
                    'perfil_editorial' => 'Generalista progresista español.',
                    'ejes_cubiertos' => array(),
                ),
                array('elDiario.es', 'https://www.eldiario.es/rss/',
                    '70% propiedad de sus trabajadores. ~35% de ingresos por 75.000+ socios lectores, resto publicidad. Sin deuda. Fundado 2012 por Ignacio Escolar.'),
                array(
                    'medio' => 'CTXT',
                    'url' => null,
                    'modalidad' => 'no_disponible',
                    'categoria_acceso' => 'C',
                    'transparencia' => 'Feed RSS bloqueado a nivel de WAF (403 también con user-agent de navegador, verificado jul-2026). Candidato a la ronda de contacto con medios.',
                    'perfil_editorial' => 'Revista Contexto: análisis progresista, sin publicidad programática.',
                    'ejes_cubiertos' => array(),
                ),
                array('La Marea', 'https://www.lamarea.com/feed/'),
                array('Nueva Tribuna', 'https://www.nuevatribuna.es/rss/',
                    'Diario digital progresista (Página 7 S.L.). Añadido jul-2026 para reforzar el flanco izquierdo tras la caída de Público e InfoLibre.'),
            ),
            'centro-izquierda' => array(
                array('El País', 'https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/portada',
                    'Grupo PRISA. Accionistas principales: Amber Capital (Joseph Oughourlian, 29,6%), Vivendi (11,9%), Global Alconaba (ex-Telefónica, 7,6%), familia Polanco (7,3%), Carlos Slim (6,5%). Cotiza en bolsa.'),
                array(
                    'medio' => 'InfoLibre',
                    'url' => null,
                    'modalidad' => 'no_disponible',
                    'categoria_acceso' => 'C',
                    'transparencia' => 'RSS descontinuado (política editorial). Autorización pendiente de solicitud.',
                    'perfil_editorial' => 'Progresista, modelo suscripción.',
                    'ejes_cubiertos' => array(),
                ),
            ),
            'centro' => array(
                // EFE: 429 rate limit — kept but expect sporadic failures
                array('EFE', 'https://efe.com/feed/',
                    'Agencia estatal española de noticias. Propiedad 100% de la SEPI (Sociedad Estatal de Participaciones Industriales). Financiación pública.'),
                // RTVE descartado: sus RSS están congelados desde jun-2022 (verificado jul-2026)
                array('Europa Press', 'https://www.europapress.es/rss/rss.aspx',
                    'Agencia de noticias privada fundada en 1957. Propiedad de la familia Martín de Cabiedes. Financiación por venta de servicios informativos a medios, empresas e instituciones públicas.'),
                array('20minutos', 'https://www.20minutos.es/rss/',
                    'Grupo Henneo. 60% familia Yarza, 40% Ibercaja. Henneo posee también Heraldo de Aragón y Business Insider España.'),
                array('Newtral', 'https://www.newtral.es/feed/',
                    'Fundada y 100% propiedad de Ana Pastor. Ingresos por producción audiovisual para La Sexta (Atresmedia) y verificación. Ha recibido financiación europea (Horizonte 2020) y créditos públicos (CERSA).'),
                array('El Confidencial', 'https://rss.elconfidencial.com/espana/',
                    'Titania Compañía Editorial. 43% José Antonio Sánchez (fundador), 15% Juan Perea (ex-Telefónica). Modelo mixto publicidad + suscripciones. Fundado 2001.'),
            ),
            'centro-derecha' => array(
                array('La Vanguardia', 'https://www.lavanguardia.com/rss/home.xml',
                    'Grupo Godó. 100% familia Godó desde 1887. Presidencia cedida a Carlos Godó Valls (5ª generación). Sin accionistas externos.'),
                array('The Objective', 'https://theobjective.com/feed/',
                    '90% Paula Quinteros (fundadora), 10% repartido entre 13 socios minoritarios. Financiado por ampliaciones de capital sucesivas. Pérdidas acumuladas >5M€.'),
            ),
            'derecha' => array(
                array('ABC', 'https://www.abc.es/rss/2.0/portada/',
                    'Grupo Vocento (cotiza en bolsa). Principales accionistas: familias vascas Ybarra y Bergareche, familia Luca de Tena (10,1%, fundadores de ABC). Fusión 2001 Grupo Correo + Prensa Española.'),
                array('El Mundo', 'https://e00-elmundo.uecdn.es/elmundo/rss/portada.xml',
                    'Unidad Editorial, filial de RCS MediaGroup (Italia, cotiza en Milán). RCS posee también Corriere della Sera y Gazzetta dello Sport.'),
                array('La Razón', 'https://www.larazon.es/?outputType=xml',
                    'Grupo Planeta. Propiedad de las familias Lara (48%) y DeAgostini (Italia). Planeta posee también Atresmedia (Antena 3, La Sexta) y editorial Planeta.'),
            ),
            'derecha-populista' => array(
                array('Libertad Digital', 'https://feeds.feedburner.com/libertaddigital/portada',
                    'Fundado 2000 por Federico Jiménez Losantos. Accionistas de referencia: Losantos, Alberto Recarte, Arturo Baldasano (~11% cada uno). Sentencia judicial probó financiación de 200.000€ procedente de la caja B del PP (2004).'),
                // El Debate: 403 Forbidden (bot block) — removed until URL change
                array('OKDIARIO', 'https://okdiario.com/feed',
                    'Fundado 2015 por Eduardo Inda (ex-subdirector de El Mundo). Financiación inicial: 500.000€ propios + 300.000€ préstamo ENISA (ente público). 93% ingresos por publicidad.'),
            ),
        ),
        'europa' => array(
            'izquierda' => array(
                array('Libération', 'http://rss.liberation.fr/rss/latest/',
                    'Fundado 1973 por Jean-Paul Sartre. Accionista mayoritario: fondo SFR Presse (Patrick Drahi/Altice). Modelo mixto suscripciones + publicidad.'),
                array('Il Manifesto', 'https://ilmanifesto.it/feed',
                    'Cooperativa de periodistas italiana. Fundado 1969. Financiación por suscriptores y venta en quiosco. Sin propietario corporativo.'),
            ),
            'centro-izquierda' => array(
                array('The Guardian', 'https://www.theguardian.com/world/europe-news/rss',
                    'Propiedad del Scott Trust Limited (fundación sin ánimo de lucro desde 1936). Sin accionistas ni propietario privado. Financiado por donaciones de lectores, publicidad y fondo de inversión del Trust.'),
                array('Le Monde', 'https://www.lemonde.fr/europe/rss_full.xml',
                    'Grupo Le Monde. Accionistas: Xavier Niel (28,5%), Matthieu Pigasse y fondo checo Daniel Křetínský. Sociedad de redactores tiene poder de veto en nombramientos editoriales.'),
                array('La Repubblica', 'https://www.repubblica.it/rss/homepage/rss2.0.xml',
                    'Grupo GEDI (antes L\'Espresso). Propiedad de Exor (holding de la familia Agnelli/Elkann). Exor posee también The Economist y Ferrari.'),
                array('Süddeutsche Zeitung', 'https://rss.sueddeutsche.de/rss/Topthemen',
                    'Südwestdeutsche Medien Holding (SWMH). Propiedad de Medien Union (familia Schaub) y Grupo Stuttgarter Zeitung. Capital alemán regional.'),
            ),
            'centro' => array(
                array('Euronews', 'https://www.euronews.com/rss?level=theme&name=news',
                    'Propiedad mayoritaria de Alpac Capital (Portugal). Anteriormente participado por NBC Universal y fondos europeos. Sede en Lyon, emite en 12 idiomas.'),
                array('Politico Europe', 'https://www.politico.eu/feed/',
                    'Propiedad de Axel Springer SE (Alemania), a su vez propiedad del fondo KKR (82,4%) y Friede Springer (12,7%). Springer posee también Bild e Insider.'),
                array('DW', 'https://rss.dw.com/rdf/rss-en-eu',
                    'Deutsche Welle. Medio público internacional de Alemania. 100% financiado con presupuesto federal alemán. Mandato legal de difusión internacional.'),
            ),
            'centro-derecha' => array(
                array('Der Spiegel', 'https://www.spiegel.de/international/index.rss',
                    '50,5% propiedad de los empleados (KG Beteiligungsgesellschaft). 25,5% familia fundadora Augstein. 24% Gruner + Jahr (Bertelsmann). Estructura única de copropiedad de redacción.'),
                array('Financial Times', 'https://www.ft.com/world/europe?format=rss',
                    'Propiedad de Nikkei Inc. (Japón) desde 2015. Adquirido por 1.320M€ a Pearson. Principal diario financiero global en inglés.'),
                array('Notes from Poland', 'https://notesfrompoland.com/feed/',
                    'Medio independiente fundado por Daniel Tilles (académico británico en Polonia). Financiado por suscripciones y donaciones de lectores. Sin propietario corporativo.'),
            ),
            'derecha' => array(
                array('The Telegraph', 'https://www.telegraph.co.uk/rss.xml',
                    'Propiedad de RedBird IMI (consorcio inversor) desde 2024, tras veto parlamentario a la compra por parte de un fondo emiratí. Anteriormente familia Barclay.'),
                array('Spiked', 'https://www.spiked-online.com/feed/',
                    'Sucesor de Living Marxism (revista del Revolutionary Communist Party, disuelto 1996). Ha recibido 300.000$ de la Charles Koch Foundation (2016-2018) para programas de "libertad de expresión". Editor: Brendan O\'Neill.'),
                array('Brussels Signal', 'https://brusselssignal.eu/feed/',
                    'Fundado 2023 por Patrick Egan (consultor político republicano estadounidense) vía Remedia Corp. Capital inicial: 275.000€ de fuente no revelada. Editor: Michael Mosbacher (ex-editor de revistas conservadoras británicas).'),
            ),
            'derecha-populista' => array(
                array('UnHerd', 'https://unherd.com/feed/atom/',
                    'Fundado 2017. Propiedad y financiación principal de Paul Marshall (cofundador del hedge fund Marshall Wace, patrimonio ~1.000M£). Modelo de suscripción + dotación de Marshall.'),
                array('The European Conservative', 'https://europeanconservative.com/feed/',
                    'Nonprofit registrada en Hungría (2021). Financiada por la Fundación Batthyány Lajos (BLA), que recibió 4,3M€ de fondos estatales húngaros vinculados a Fidesz/Orbán. RSF lo documenta como vehículo de influencia gubernamental húngara.'),
                array('Remix News', 'https://rmx.news/feed/',
                    'Fundado por Patrick Egan (FWD Affairs Kft, Budapest). Financiación parcial de la Fundación Batthyány Lajos (misma que European Conservative). Accionista: Árpád Habony, asesor no oficial de Viktor Orbán. Documentado como proyecto de influencia mediática del gobierno húngaro.'),
                array('Hungary Today', 'https://hungarytoday.hu/feed/',
                    'Parte del ecosistema mediático húngaro en inglés. Edita noticias de Hungría con perspectiva pro-gubernamental. Vinculado al entorno de medios afines a Fidesz.'),
            ),
        ),
        'global' => array(
            'centro-izquierda' => array(
                array('The Guardian', 'https://www.theguardian.com/world/rss',
                    'Propiedad del Scott Trust Limited (fundación sin ánimo de lucro). Sin accionistas. Ver nota en sección Europa.'),
                array('BBC', 'https://feeds.bbci.co.uk/news/world/rss.xml',
                    'Medio público británico. Financiado por canon televisivo (licence fee) pagado por hogares británicos. Mandato de imparcialidad regulado por Royal Charter.'),
            ),
            'centro' => array(
                array('Al Jazeera', 'https://www.aljazeera.com/xml/rss/all.xml',
                    'Propiedad del Estado de Qatar vía Al Jazeera Media Network. 100% financiación estatal catarí. Mayor red de noticias del mundo árabe.'),
                array('Asia Times', 'https://asiatimes.com/feed/',
                    'Fundado 1995 en Bangkok. Propiedad actual no declarada públicamente. Medio enfocado en Asia-Pacífico, con perspectiva geopolítica desde la región.'),
            ),
            'centro-derecha' => array(
                array('National Review', 'https://www.nationalreview.com/feed/',
                    'Fundado 1955 por William F. Buckley Jr. Desde 2015, filial del National Review Institute (nonprofit 501c3). Sin publicidad corporativa significativa; financiado por suscripciones, donaciones y galas. Ha recibido fondos de la Charles Koch Foundation y la Bradley Foundation.'),
            ),
        ),
    ),

    // Rate limiting
    'rss_rate_limit' => 1,
    'rss_timeout'    => 15,
    // Ventana de lectura RSS (horas): con un único escaneo diario, 36h da solape
    // suficiente para no perder nada entre días sin arrastrar demasiado ruido viejo.
    'rss_ventana_horas' => 36,
);
