<?php
/**
 * Prisma — Panel de control web.
 *
 * Permite lanzar el pipeline, procesar temas manuales y ver estado.
 * Protegido con la misma PRISMA_INGEST_KEY.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$B = prisma_base();
$cfg = PRISMA_CONFIG;

// ── Auth por cookie/form ─────────────────────────────────────────────

session_start();
$authed = ($_SESSION['prisma_auth'] ?? '') === 'ok';

if (isset($_POST['api_key'])) {
    if (hash_equals($cfg['ingest_key'], $_POST['api_key'])) {
        $_SESSION['prisma_auth'] = 'ok';
        $authed = true;
    } else {
        $auth_error = 'Clave incorrecta';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: {$B}panel.php");
    exit;
}

// ── Acciones (solo si autenticado) ───────────────────────────────────

$action_result = null;
$action_output = '';

if ($authed && isset($_POST['action'])) {
    $action = $_POST['action'];
    $max_time = 300; // 5 min para pipeline completo
    set_time_limit($max_time);

    ob_start();

    if ($action === 'dry-run') {
        // Ejecutar pipeline en dry-run
        $cmd = 'cd ' . escapeshellarg(__DIR__) . ' && php pipeline.php --dry-run 2>&1';
        passthru($cmd, $exit_code);
        $action_result = $exit_code === 0 ? 'ok' : 'error';

    } elseif ($action === 'pipeline') {
        $temas = max(1, min(5, (int)($_POST['temas'] ?? 5)));
        $cmd = 'cd ' . escapeshellarg(__DIR__) . " && php pipeline.php --temas $temas 2>&1";
        passthru($cmd, $exit_code);
        $action_result = $exit_code === 0 ? 'ok' : 'error';

    } elseif ($action === 'manual') {
        $tema = trim($_POST['tema'] ?? '');
        $ambito = $_POST['ambito'] ?? 'españa';
        if ($tema) {
            $cmd = 'cd ' . escapeshellarg(__DIR__)
                 . ' && php procesar.php --ambito ' . escapeshellarg($ambito)
                 . ' ' . escapeshellarg($tema) . ' 2>&1';
            passthru($cmd, $exit_code);
            $action_result = $exit_code === 0 ? 'ok' : 'error';
        }
    }

    $action_output = ob_get_clean();
}

// ── Datos para el panel ──────────────────────────────────────────────

$stats = [];
if ($authed) {
    $db = prisma_db();
    $stats['total'] = $db->query("SELECT COUNT(*) FROM articulos")->fetchColumn();
    $stats['hoy'] = $db->query("SELECT COUNT(*) FROM articulos WHERE fecha_publicacion >= date('now', 'start of day')")->fetchColumn();
    $stats['ultimo'] = $db->query("SELECT fecha_publicacion FROM articulos ORDER BY fecha_publicacion DESC LIMIT 1")->fetchColumn() ?: '—';

    // Gasto del día
    $usage_file = __DIR__ . '/data/usage.json';
    $usage = [];
    if (file_exists($usage_file)) {
        $usage = json_decode(file_get_contents($usage_file), true) ?: [];
    }
    $today = date('Y-m-d');
    $stats['gasto_hoy'] = $usage[$today]['cost_usd'] ?? 0;
    $stats['calls_hoy'] = $usage[$today]['calls'] ?? 0;
    $stats['budget'] = $cfg['daily_budget_usd'] ?? 4.00;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prisma — Panel</title>
  <meta name="robots" content="noindex, nofollow">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
      font-size: 15px; line-height: 1.5; color: #e8e8ec; background: #0a0a12;
    }
    .wrap { max-width: 820px; margin: 0 auto; padding: 2rem 1.5rem; }
    h1 { font-size: 1.5rem; font-weight: 600; margin: 0 0 0.5rem 0; color: #fff; }
    h2 { font-size: 1.1rem; font-weight: 600; margin: 2rem 0 0.8rem 0; color: #fff; }
    p { margin: 0 0 1rem 0; color: #9a9aaa; }
    a { color: #4dc3ff; text-decoration: none; }
    a:hover { color: #fff; }

    /* Cards */
    .card { padding: 1.2rem; border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; background: rgba(255,255,255,0.02); margin-bottom: 1rem; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 1.5rem; }
    .stat-card { padding: 1rem; border: 1px solid rgba(255,255,255,0.06); border-radius: 6px; background: rgba(255,255,255,0.015); }
    .stat-val { font-size: 1.8rem; font-weight: 700; color: #fff; line-height: 1; }
    .stat-val.green { color: #4ade80; }
    .stat-val.yellow { color: #f2f24a; }
    .stat-val.red { color: #ff4d6d; }
    .stat-label { font-size: 0.72rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #7a7a8a; margin-top: 0.3rem; }

    /* Forms */
    label { display: block; font-size: 0.82rem; font-weight: 600; color: #c8c8d0; margin-bottom: 0.3rem; }
    input[type="text"], input[type="password"], select, textarea {
      width: 100%; padding: 10px 12px; border: 1px solid rgba(255,255,255,0.12);
      border-radius: 4px; background: rgba(255,255,255,0.05); color: #e8e8ec;
      font-family: inherit; font-size: 0.92rem;
    }
    input:focus, select:focus, textarea:focus { outline: 2px solid #f2f24a; border-color: transparent; }
    textarea { resize: vertical; min-height: 60px; }
    .btn {
      display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px;
      border: none; border-radius: 4px; font-family: inherit; font-size: 0.88rem;
      font-weight: 600; cursor: pointer; transition: background 0.15s;
    }
    .btn-primary { background: #f2f24a; color: #0a0a12; }
    .btn-primary:hover { background: #fff; }
    .btn-green { background: #4ade80; color: #0a0a12; }
    .btn-green:hover { background: #6bf09a; }
    .btn-outline { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #c8c8d0; }
    .btn-outline:hover { border-color: #fff; color: #fff; }
    .btn + .btn { margin-left: 8px; }
    .form-row { margin-bottom: 1rem; }
    .form-row-inline { display: flex; gap: 12px; align-items: flex-end; }
    .form-row-inline > * { flex: 1; }

    /* Output */
    .output {
      margin-top: 1rem; padding: 1rem; border-radius: 4px;
      background: #050509; border: 1px solid rgba(255,255,255,0.06);
      font-family: 'Menlo', 'Consolas', monospace; font-size: 0.78rem;
      color: #9a9aaa; white-space: pre-wrap; word-break: break-all;
      max-height: 500px; overflow-y: auto;
    }
    .output .ok { color: #4ade80; }
    .output .err { color: #ff4d6d; }

    /* Login */
    .login-box { max-width: 360px; margin: 4rem auto; }
    .error { color: #ff4d6d; font-size: 0.88rem; margin-bottom: 1rem; }

    /* Nav */
    .nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
    .nav-links { display: flex; gap: 16px; }
  </style>
</head>
<body>
<div class="wrap">

<?php if (!$authed): ?>
  <!-- Login -->
  <div class="login-box">
    <h1>Prisma — Panel</h1>
    <p>Introduce la clave de API para acceder.</p>
    <?php if (!empty($auth_error)): ?>
      <p class="error"><?= htmlspecialchars($auth_error) ?></p>
    <?php endif; ?>
    <form method="post">
      <div class="form-row">
        <label for="api_key">PRISMA_INGEST_KEY</label>
        <input type="password" name="api_key" id="api_key" autofocus>
      </div>
      <button type="submit" class="btn btn-primary">Entrar</button>
    </form>
  </div>

<?php else: ?>
  <!-- Panel -->
  <div class="nav">
    <h1>Prisma — Panel</h1>
    <div class="nav-links">
      <a href="<?= $B ?>">Ver web</a>
      <a href="<?= $B ?>panel.php?logout=1">Cerrar sesión</a>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-val"><?= $stats['total'] ?></div>
      <div class="stat-label">Artículos total</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $stats['hoy'] ?></div>
      <div class="stat-label">Publicados hoy</div>
    </div>
    <div class="stat-card">
      <?php
        $pct = $stats['budget'] > 0 ? ($stats['gasto_hoy'] / $stats['budget']) * 100 : 0;
        $color = $pct < 50 ? 'green' : ($pct < 80 ? 'yellow' : 'red');
      ?>
      <div class="stat-val <?= $color ?>">$<?= number_format($stats['gasto_hoy'], 2) ?></div>
      <div class="stat-label">Gasto hoy / $<?= number_format($stats['budget'], 2) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $stats['calls_hoy'] ?></div>
      <div class="stat-label">Llamadas API hoy</div>
    </div>
  </div>

  <!-- Dry Run -->
  <h2>Probar RSS + selección de temas</h2>
  <div class="card">
    <p>Lee todos los RSS, agrupa por tema y muestra los 5 seleccionados. No llama a la API de Anthropic ni publica.</p>
    <form method="post">
      <input type="hidden" name="action" value="dry-run">
      <button type="submit" class="btn btn-outline">Ejecutar dry-run</button>
    </form>
  </div>

  <!-- Pipeline completo -->
  <h2>Lanzar pipeline completo</h2>
  <div class="card">
    <p>RSS → curación → síntesis → auditoría → publicación. Consume tokens de la API.</p>
    <form method="post">
      <input type="hidden" name="action" value="pipeline">
      <div class="form-row-inline">
        <div>
          <label for="temas">Número de temas</label>
          <select name="temas" id="temas">
            <option value="1">1 (prueba)</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="5" selected>5 (normal)</option>
          </select>
        </div>
        <div style="flex:0">
          <label>&nbsp;</label>
          <button type="submit" class="btn btn-green">Lanzar pipeline</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Manual -->
  <h2>Procesar tema manual</h2>
  <div class="card">
    <p>Sintetiza un tema concreto sin pasar por RSS. El modelo investiga por su cuenta.</p>
    <form method="post">
      <input type="hidden" name="action" value="manual">
      <div class="form-row">
        <label for="tema">Tema o noticia</label>
        <textarea name="tema" id="tema" placeholder="Ej: Manifestación por la educación pública en Madrid el 19 de abril"></textarea>
      </div>
      <div class="form-row-inline">
        <div>
          <label for="ambito">Ámbito</label>
          <select name="ambito" id="ambito">
            <option value="españa">España</option>
            <option value="europa">Europa</option>
            <option value="global">Global</option>
          </select>
        </div>
        <div style="flex:0">
          <label>&nbsp;</label>
          <button type="submit" class="btn btn-primary">Procesar</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Output -->
  <?php if ($action_output): ?>
    <h2>Resultado</h2>
    <div class="output"><?php
      // Colorear líneas de ok/error
      $lines = explode("\n", htmlspecialchars($action_output));
      foreach ($lines as $line) {
        if (strpos($line, '✓') !== false || strpos($line, 'APTO') !== false || strpos($line, 'PUBLICADO') !== false) {
          echo '<span class="ok">' . $line . "</span>\n";
        } elseif (strpos($line, '✗') !== false || strpos($line, 'ERROR') !== false || strpos($line, 'RECHAZO') !== false) {
          echo '<span class="err">' . $line . "</span>\n";
        } else {
          echo $line . "\n";
        }
      }
    ?></div>
  <?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
