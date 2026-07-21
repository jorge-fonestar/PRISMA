<?php
/**
 * PolarPrisma — Mapa ideológico y de financiación.
 *
 * El espectro completo de fuentes monitorizadas, cuadrante a cuadrante, con
 * quién es el dueño de cada medio, cómo se financia y un icono de capacidad
 * económica (€/€€/€€€, orden de magnitud del grupo editor). La ficha larga
 * de transparencia (config.php) se muestra expandible por medio.
 */
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/curador.php';          // PRISMA_GRUPO_*, PRISMA_CUADRANTE_POS
require_once __DIR__ . '/lib/fuentes/normalizar.php';
require_once __DIR__ . '/lib/mapa_datos.php';

$cfg = prisma_cfg();
$B = prisma_base();

$ambito_labels = array('españa' => 'España', 'europa' => 'Europa', 'global' => 'Global');
$cuadrante_labels = array(
    'izquierda-populista' => 'Izquierda populista',
    'izquierda'           => 'Izquierda',
    'centro-izquierda'    => 'Centro-izquierda',
    'centro'              => 'Centro',
    'centro-derecha'      => 'Centro-derecha',
    'derecha'             => 'Derecha',
    'derecha-populista'   => 'Derecha populista',
);
$orden_cuadrantes = array_keys(PRISMA_CUADRANTE_POS); // ya en orden izquierda→derecha

// ── Estadísticas por bloque (España) para el resumen y la reflexión ──
$stats = array(
    'izq'    => array('n' => 0, 'cap' => array(1 => 0, 2 => 0, 3 => 0), 'lectores' => 0, 'corporativo' => 0),
    'centro' => array('n' => 0, 'cap' => array(1 => 0, 2 => 0, 3 => 0), 'lectores' => 0, 'corporativo' => 0),
    'der'    => array('n' => 0, 'cap' => array(1 => 0, 2 => 0, 3 => 0), 'lectores' => 0, 'corporativo' => 0),
);
foreach ($cfg['fuentes']['españa'] as $cuadrante => $medios) {
    if (in_array($cuadrante, PRISMA_GRUPO_IZQ)) $bloque = 'izq';
    elseif (in_array($cuadrante, PRISMA_GRUPO_DER)) $bloque = 'der';
    else $bloque = 'centro';

    foreach ($medios as $entrada) {
        $f = rss_normalizar_fuente($entrada, $cuadrante, 'españa');
        $d = mapa_datos_medio($f['medio']);
        if ($d === null) continue;
        $stats[$bloque]['n']++;
        if ($d['capacidad'] !== null) $stats[$bloque]['cap'][$d['capacidad']]++;
        if ($d['tipo'] === 'lectores') $stats[$bloque]['lectores']++;
        if ($d['tipo'] === 'conglomerado' || $d['tipo'] === 'familiar') $stats[$bloque]['corporativo']++;
    }
}

page_header(
    'Mapa ideológico y de financiación',
    'Todos los medios que monitoriza PolarPrisma, por cuadrante ideológico, con su propietario, su modelo de financiación y su capacidad económica.',
    'mapa',
    true
);
?>

<div class="page-top">
  <p class="eyebrow">Transparencia radical</p>
  <h1>El mapa: quién cuenta las noticias y quién lo financia</h1>
  <p>Cada medio del radar, ordenado en su cuadrante ideológico, con tres datos que casi nunca
  aparecen junto a un titular: <strong>de quién es</strong>, <strong>de dónde sale su dinero</strong>
  y <strong>cuánto dinero es</strong>. La asignación de cuadrantes se explica en
  <a href="<?= $B ?>fuentes.php">Fuentes consultadas</a>; aquí el foco es la propiedad.</p>
</div>

<div class="content">

  <!-- Leyenda -->
  <div class="card" style="font-family:Inter,Arial,sans-serif;font-size:0.82rem;display:flex;flex-wrap:wrap;gap:1.6rem;align-items:center">
    <span><?= mapa_icono_capacidad(1) ?> &lt;10 M€/año</span>
    <span><?= mapa_icono_capacidad(2) ?> 10–100 M€/año</span>
    <span><?= mapa_icono_capacidad(3) ?> &gt;100 M€/año</span>
    <span><?= mapa_icono_capacidad(null) ?> sin datos</span>
    <span style="color:var(--text-faint)">La capacidad es el orden de magnitud de ingresos del <em>grupo editor</em>, estimado con cuentas anuales e informes públicos.</span>
  </div>

  <?php foreach (array('españa', 'europa', 'global') as $ambito): ?>
    <?php if (empty($cfg['fuentes'][$ambito])) continue; ?>
    <h2><?= $ambito_labels[$ambito] ?></h2>

    <?php foreach ($orden_cuadrantes as $cuadrante): ?>
      <?php if (empty($cfg['fuentes'][$ambito][$cuadrante])) continue; ?>
      <h3 style="display:flex;align-items:center;gap:10px;margin-top:1.8rem">
        <span style="width:14px;height:14px;border-radius:3px;background:<?= cuadrante_color($cuadrante) ?>;flex-shrink:0"></span>
        <?= $cuadrante_labels[$cuadrante] ?>
      </h3>

      <?php foreach ($cfg['fuentes'][$ambito][$cuadrante] as $entrada):
          $f = rss_normalizar_fuente($entrada, $cuadrante, $ambito);
          $d = mapa_datos_medio($f['medio']);
          $ficha = isset($f['transparencia']) ? trim($f['transparencia']) : '';
      ?>
      <div style="border-left:3px solid <?= cuadrante_color($cuadrante) ?>;padding:0.7rem 0 0.7rem 14px;margin:0.5rem 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:baseline;gap:14px;flex-wrap:wrap">
          <strong style="color:var(--text);font-size:1.02rem"><?= htmlspecialchars($f['medio']) ?></strong>
          <?= mapa_icono_capacidad($d ? $d['capacidad'] : null) ?>
          <?php if ($d): ?>
          <span style="font-family:Inter,Arial,sans-serif;font-size:0.7rem;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);border:1px solid var(--border-card);border-radius:999px;padding:2px 10px">
            <?= PRISMA_MAPA_TIPOS[$d['tipo']] ?>
          </span>
          <?php endif; ?>
        </div>
        <?php if ($d): ?>
        <div style="font-size:0.9rem;color:var(--text-muted);margin-top:0.35rem">
          <strong style="color:var(--text)">Propiedad:</strong> <?= htmlspecialchars($d['propiedad']) ?>
          &nbsp;·&nbsp; <strong style="color:var(--text)">Financiación:</strong> <?= htmlspecialchars($d['financiacion']) ?>
        </div>
        <?php else: ?>
        <div style="font-size:0.9rem;color:var(--text-faint);margin-top:0.35rem">Ficha estructurada pendiente.</div>
        <?php endif; ?>
        <?php if ($ficha !== ''): ?>
        <details style="margin-top:0.4rem">
          <summary style="font-family:Inter,Arial,sans-serif;font-size:0.78rem;color:var(--text-faint);cursor:pointer">Ficha completa</summary>
          <p style="font-size:0.88rem;margin:0.4rem 0 0 0"><?= htmlspecialchars($ficha) ?></p>
        </details>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <!-- Resumen por bloques (España) -->
  <h2>El mapa español, en cifras</h2>
  <table>
    <tr><th>Bloque</th><th>Medios</th><th>€€€</th><th>€€</th><th>€</th><th>De lectores/cooperativa</th><th>Grupo empresarial/familiar</th></tr>
    <?php
    $bloque_labels = array('izq' => 'Izquierda (3 cuadrantes)', 'centro' => 'Centro', 'der' => 'Derecha (3 cuadrantes)');
    foreach ($stats as $b => $s): ?>
    <tr>
      <td><?= $bloque_labels[$b] ?></td>
      <td><?= $s['n'] ?></td>
      <td><?= $s['cap'][3] ?></td>
      <td><?= $s['cap'][2] ?></td>
      <td><?= $s['cap'][1] ?></td>
      <td><?= $s['lectores'] ?></td>
      <td><?= $s['corporativo'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <!-- Reflexión -->
  <h2>¿Está descompensado el mapa? Una lectura objetiva</h2>
  <p>Los datos de esta página muestran un patrón claro en España, y conviene describirlo con
  precisión porque <strong>no es una simple asimetría izquierda-derecha</strong>:</p>
  <ul>
    <li><strong>La escala económica grande se concentra del centro-izquierda hacia la derecha.</strong>
    Los tres medios del cuadrante derecha (ABC, El Mundo, La Razón) pertenecen a grupos
    empresariales que facturan cientos de millones (Vocento, RCS, Planeta), igual que
    La Vanguardia (Godó), 20minutos (Henneo) y la gran excepción del otro lado: El País,
    cuyo dueño (PRISA) es el mayor grupo de prensa español.</li>
    <li><strong>A la izquierda del centro-izquierda no hay ningún gran grupo.</strong> El Salto,
    elDiario.es, CTXT, La Marea, Público o InfoLibre funcionan con estructuras de socios,
    cooperativas o pequeñas sociedades, entre uno y dos órdenes de magnitud por debajo. La prensa
    de masas de izquierdas desapareció con el franquismo, y cuando ese espacio se reconstruyó
    (2012-2015, tras la crisis) fue en digital y con el único capital disponible: los lectores.</li>
    <li><strong>En los polos, la asimetría no es de tamaño sino de modelo.</strong> OKDIARIO o
    Libertad Digital son digitales medianos comparables en escala a elDiario.es, pero se financian
    con capital de accionistas privados y publicidad, mientras la izquierda populista depende de
    socios-suscriptores. En Europa el patrón se acentúa: parte de la derecha populista monitorizada
    (Remix News, The European Conservative, Hungary Today) se financia con fondos estatales
    húngaros, y UnHerd con el patrimonio personal de un gestor de hedge funds.</li>
    <li><strong>Por qué:</strong> es sobre todo historia industrial. Las cabeceras centenarias
    españolas que sobrevivieron al siglo XX eran conservadoras o de la burguesía industrial, y se
    consolidaron como grupos cotizados o familiares; sus equivalentes de izquierda no sobrevivieron
    a la dictadura. El resultado es que hoy <em>el modelo de financiación se correlaciona con el
    cuadrante casi tanto como la línea editorial</em>: capital corporativo y publicidad hacia el
    centro y la derecha; suscripción militante hacia la izquierda; y mecenas individuales o
    estados en los polos.</li>
  </ul>
  <p><strong>Lo que este mapa no dice:</strong> más capacidad económica no implica más influencia
  por lector, ni menos rigor; y ningún modelo garantiza independencia — un medio de socios depende
  de no incomodar a sus socios igual que uno corporativo depende de no incomodar a su consejo o a
  sus anunciantes. El mapa muestra <em>de quién depende cada uno</em>. La conclusión es tuya.</p>

  <h2>Método y correcciones</h2>
  <p>Las fichas proceden de cuentas anuales, registros mercantiles, informes públicos y las
  propias declaraciones de los medios; la capacidad económica es una estimación por orden de
  magnitud del grupo editor, no una cifra auditada. Si detectas un error o un dato desactualizado,
  agradecemos la corrección — la transparencia también se audita.</p>

</div>

<?php page_footer(); ?>
