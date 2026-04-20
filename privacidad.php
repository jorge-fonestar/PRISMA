<?php
require_once __DIR__ . '/lib/layout.php';
page_header('Política de privacidad');
?>

<div class="page-top">
  <h1>Política de privacidad</h1>
</div>

<div class="content">
  <p><strong>Prisma no recoge datos personales.</strong></p>

  <h2>Qué no hacemos</h2>
  <ul>
    <li>No usamos cookies de seguimiento ni de publicidad.</li>
    <li>No hay cuentas de usuario, formularios de registro ni newsletter.</li>
    <li>No hay analítica de terceros (ni Google Analytics, ni Meta Pixel, ni equivalentes).</li>
    <li>No hay personalización algorítmica: todos los visitantes ven exactamente el mismo contenido.</li>
    <li>No vendemos, cedemos ni compartimos datos con nadie.</li>
  </ul>

  <h2>Qué sí ocurre</h2>
  <ul>
    <li><strong>Logs del servidor</strong>: como cualquier servidor web, el hosting registra automáticamente la IP, fecha/hora y página solicitada. Estos logs los gestiona el proveedor de hosting y se eliminan según su política. Prisma no accede a ellos ni los procesa.</li>
    <li><strong>Preferencia de tema</strong>: si cambias entre modo claro/oscuro, se guarda en el almacenamiento local de tu navegador (localStorage). No es una cookie y no se envía a ningún servidor.</li>
  </ul>

  <h2>Base legal</h2>
  <p>Al no recoger datos personales, no aplica la necesidad de consentimiento bajo el RGPD (Reglamento UE 2016/679). Si en el futuro se añadiera cualquier tratamiento de datos, se actualizaría esta política y se solicitaría consentimiento previo.</p>

  <p style="color:var(--text-faintest);font-size:0.85rem;margin-top:2rem">Última actualización: <?= date('j \d\e F \d\e Y') ?></p>
</div>

<?php page_footer(); ?>
