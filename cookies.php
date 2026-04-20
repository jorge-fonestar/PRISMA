<?php
require_once __DIR__ . '/lib/layout.php';
page_header('Política de cookies');
?>

<div class="page-top">
  <h1>Política de cookies</h1>
</div>

<div class="content">
  <p><strong>Prisma no usa cookies.</strong></p>

  <p>Este sitio web no instala cookies de ningún tipo en tu navegador: ni propias, ni de terceros, ni técnicas, ni de publicidad, ni de analítica.</p>

  <p>La única información que se almacena en tu navegador es la preferencia de tema visual (claro/oscuro/sistema), que usa <code>localStorage</code>. Esto no es una cookie: no se envía al servidor, no caduca y puedes borrarlo desde la configuración de tu navegador en cualquier momento.</p>

  <p>Al no usar cookies, no necesitamos banner de consentimiento. Si en el futuro fuera necesario instalar alguna cookie técnica, se actualizaría esta política.</p>

  <p style="color:var(--text-faintest);font-size:0.85rem;margin-top:2rem">Última actualización: <?= date('j \d\e F \d\e Y') ?></p>
</div>

<?php page_footer(); ?>
