<?php
/**
 * Prisma — Panel de mando.
 *
 * Dashboard con consumo, acciones y estado del sistema.
 * Protegido por contraseña con fail2ban básico por IP+cookies.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$B = prisma_base();
$cfg = PRISMA_CONFIG;

// ── Fail2ban básico ──────────────────────────────────────────────────

$ban_file = __DIR__ . '/data/panel_bans.json';
$bans = file_exists($ban_file) ? (json_decode(file_get_contents($ban_file), true) ?: []) : [];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ban_window = 900;  // 15 min
$max_attempts = 5;

// Limpiar intentos caducados
if (isset($bans[$client_ip])) {
    $bans[$client_ip] = array_filter($bans[$client_ip], fn($ts) => $ts > time() - $ban_window);
    if (empty($bans[$client_ip])) unset($bans[$client_ip]);
}

$is_banned = count($bans[$client_ip] ?? []) >= $max_attempts;

// ── Auth ─────────────────────────────────────────────────────────────

session_start();
$authed = ($_SESSION['prisma_panel'] ?? '') === 'ok';
$auth_error = '';

if (!$authed && isset($_POST['pass']) && !$is_banned) {
    if ($_POST['pass'] === $cfg['panel_pass']) {
        $_SESSION['prisma_panel'] = 'ok';
        $authed = true;
        // Limpiar intentos fallidos
        unset($bans[$client_ip]);
        file_put_contents($ban_file, json_encode($bans));
    } else {
        $auth_error = 'Contraseña incorrecta';
        $bans[$client_ip][] = time();
        file_put_contents($ban_file, json_encode($bans));
        $is_banned = count($bans[$client_ip]) >= $max_attempts;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: {$B}panel.php");
    exit;
}

// ── Acciones ─────────────────────────────────────────────────────────

$action_output = '';
$action_result = null;

if ($authed && isset($_POST['action'])) {
    set_time_limit(300);
    ob_start();

    $action = $_POST['action'];

    $safe_ambito = escapeshellarg($_POST['ambito'] ?? 'españa');

    if ($action === 'dry-run') {
        passthru('cd ' . escapeshellarg(__DIR__) . " && php pipeline.php --ambito $safe_ambito --dry-run 2>&1", $rc);
        $action_result = $rc === 0 ? 'ok' : 'error';
    } elseif ($action === 'pipeline') {
        $n = max(1, min(5, (int)($_POST['temas'] ?? 5)));
        passthru('cd ' . escapeshellarg(__DIR__) . " && php pipeline.php --ambito $safe_ambito --temas $n 2>&1", $rc);
        $action_result = $rc === 0 ? 'ok' : 'error';
    } elseif ($action === 'manual') {
        $tema = trim($_POST['tema'] ?? '');
        $ambito = $_POST['ambito'] ?? 'españa';
        if ($tema) {
            // Write a temp job file — avoids all shell escaping problems
            $job = ['tema' => $tema, 'ambito' => $ambito];
            $job_path = __DIR__ . '/data/manual_job.json';
            file_put_contents($job_path, json_encode($job, JSON_UNESCAPED_UNICODE));
            passthru("cd " . escapeshellarg(__DIR__) . " && php procesar.php 2>&1", $rc);
            @unlink($job_path);
            $action_result = $rc === 0 ? 'ok' : 'error';
        }
    } elseif ($action === 'update-credit') {
        $new_credit = (float)($_POST['credit'] ?? 0);
        if ($new_credit > 0) {
            // Actualizar .env
            $env_path = __DIR__ . '/.env';
            $env_content = file_exists($env_path) ? file_get_contents($env_path) : '';
            if (preg_match('/^ANTHROPIC_CREDIT_USD=.*/m', $env_content)) {
                $env_content = preg_replace('/^ANTHROPIC_CREDIT_USD=.*/m', "ANTHROPIC_CREDIT_USD=$new_credit", $env_content);
            } else {
                $env_content = rtrim($env_content) . "\nANTHROPIC_CREDIT_USD=$new_credit\n";
            }
            file_put_contents($env_path, $env_content);
            echo "Crédito actualizado a \$$new_credit\n";
            $action_result = 'ok';
            // Reload para que se vea reflejado
            $cfg['total_credit_usd'] = $new_credit;
        }
    }

    $action_output = ob_get_clean();
}

// ── Datos del dashboard ──────────────────────────────────────────────

$data = [];
if ($authed) {
    $db = prisma_db();

    // Artículos
    $data['total'] = (int)$db->query("SELECT COUNT(*) FROM articulos")->fetchColumn();
    $data['hoy'] = (int)$db->query("SELECT COUNT(*) FROM articulos WHERE date(fecha_publicacion) = date('now')")->fetchColumn();
    $data['ultimo'] = $db->query("SELECT fecha_publicacion FROM articulos ORDER BY fecha_publicacion DESC LIMIT 1")->fetchColumn() ?: '—';
    $data['ultimos'] = $db->query("SELECT id, titular_neutral, veredicto, fecha_publicacion FROM articulos ORDER BY fecha_publicacion DESC LIMIT 5")->fetchAll();

    // Tasa APTO
    $total_audited = (int)$db->query("SELECT COUNT(*) FROM articulos WHERE veredicto IS NOT NULL")->fetchColumn();
    $aptos = (int)$db->query("SELECT COUNT(*) FROM articulos WHERE veredicto = 'APTO'")->fetchColumn();
    $data['tasa_apto'] = $total_audited > 0 ? round($aptos / $total_audited * 100) : 0;

    // Uso API
    $usage_file = __DIR__ . '/data/usage.json';
    $usage = file_exists($usage_file) ? (json_decode(file_get_contents($usage_file), true) ?: []) : [];

    $today = date('Y-m-d');
    $month_prefix = date('Y-m');
    $data['day'] = $usage[$today] ?? ['cost_usd' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0];

    // Mes actual
    $data['month'] = ['cost_usd' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0, 'days' => 0];
    foreach ($usage as $day => $d) {
        if (strpos($day, $month_prefix) === 0 && isset($d['cost_usd'])) {
            $data['month']['cost_usd']      += $d['cost_usd'];
            $data['month']['input_tokens']  += $d['input_tokens'] ?? 0;
            $data['month']['output_tokens'] += $d['output_tokens'] ?? 0;
            $data['month']['calls']         += $d['calls'] ?? 0;
            $data['month']['days']++;
        }
    }

    // Histórico últimos 7 días para mini-gráfico
    $data['week'] = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $data['week'][] = [
            'date'  => $d,
            'label' => date('D j', strtotime($d)),
            'cost'  => $usage[$d]['cost_usd'] ?? 0,
            'calls' => $usage[$d]['calls'] ?? 0,
        ];
    }

    $data['budget'] = $cfg['daily_budget_usd'];
    $data['credit'] = $cfg['total_credit_usd'];
    $data['total_spent'] = 0;
    foreach ($usage as $d) { if (isset($d['cost_usd'])) $data['total_spent'] += $d['cost_usd']; }
    $data['credit_left'] = max(0, $data['credit'] - $data['total_spent']);

    // Rechazados
    $rej_dir = __DIR__ . '/rechazados';
    $data['rechazados'] = is_dir($rej_dir) ? count(glob("$rej_dir/*.json")) : 0;

    // Logs
    $log_dir = __DIR__ . '/logs';
    $data['last_log'] = '';
    if (is_dir($log_dir)) {
        $logs = glob("$log_dir/*.log");
        if ($logs) {
            rsort($logs);
            $data['last_log'] = basename($logs[0]);
            $data['last_log_tail'] = implode("\n", array_slice(file($logs[0]) ?: [], -20));
        }
    }

    // RSS status (fuentes configuradas por ámbito)
    $data['n_fuentes'] = 0;
    $data['n_cuadrantes'] = 0;
    $data['n_ambitos'] = count($cfg['fuentes']);
    foreach ($cfg['fuentes'] as $amb => $cuadrantes) {
        $data['n_cuadrantes'] += count($cuadrantes);
        foreach ($cuadrantes as $medios) { $data['n_fuentes'] += count($medios); }
    }
}

// ── Helpers ──────────────────────────────────────────────────────────

function fmt_tokens(int $n): string {
    if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000) return number_format($n / 1_000, 1) . 'K';
    return (string)$n;
}

function pct_color(float $pct): string {
    if ($pct < 50) return '#4ade80';
    if ($pct < 80) return '#f2f24a';
    return '#ff4d6d';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prisma — Panel de mando</title>
  <meta name="robots" content="noindex, nofollow">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
      font-size: 14px; line-height: 1.5; color: #c8c8d4; background: #08080f;
    }
    .wrap { max-width: 960px; margin: 0 auto; padding: 1.5rem; }
    h1 { font-size: 1.3rem; font-weight: 700; color: #fff; margin: 0; }
    h2 { font-size: 0.95rem; font-weight: 700; color: #fff; margin: 2rem 0 0.8rem 0;
         padding-bottom: 0.4rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
    p { margin: 0 0 0.8rem 0; }
    a { color: #4dc3ff; text-decoration: none; }
    a:hover { color: #fff; }

    /* Nav */
    .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px;
              margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
    .topbar-links { display: flex; gap: 16px; font-size: 0.82rem; }

    /* Stats grid */
    .grid { display: grid; gap: 10px; }
    .grid-4 { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
    .grid-3 { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
    .grid-2 { grid-template-columns: 1fr 1fr; }
    @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }

    .card {
      padding: 1rem 1.2rem; border: 1px solid rgba(255,255,255,0.06);
      border-radius: 6px; background: rgba(255,255,255,0.02);
    }
    .card-lg { padding: 1.2rem 1.5rem; }
    .stat-val { font-size: 1.6rem; font-weight: 800; color: #fff; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .stat-sub { font-size: 0.72rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #6a6a7a; margin-top: 0.2rem; }

    /* Bar chart mini */
    .bars { display: flex; align-items: flex-end; gap: 4px; height: 60px; margin-top: 0.5rem; }
    .bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; height: 100%; justify-content: flex-end; }
    .bar { width: 100%; border-radius: 2px 2px 0 0; min-height: 2px; transition: height 0.3s; }
    .bar-label { font-size: 0.6rem; color: #5a5a6a; white-space: nowrap; }

    /* Progress bar */
    .prog { height: 6px; border-radius: 3px; background: rgba(255,255,255,0.06); margin-top: 0.6rem; overflow: hidden; }
    .prog-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }

    /* Table */
    table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    th { text-align: left; font-weight: 600; color: #6a6a7a; font-size: 0.72rem; letter-spacing: 0.08em;
         text-transform: uppercase; padding: 0.5rem 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
    td { padding: 0.6rem 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.03); color: #b8b8c4; }
    tr:hover td { background: rgba(255,255,255,0.02); }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 0.68rem; font-weight: 700; }
    .badge-ok { background: rgba(74,222,128,0.12); color: #4ade80; }
    .badge-warn { background: rgba(242,242,74,0.12); color: #f2f24a; }
    .badge-err { background: rgba(255,77,109,0.12); color: #ff4d6d; }

    /* Forms */
    label { display: block; font-size: 0.78rem; font-weight: 600; color: #9a9aaa; margin-bottom: 0.25rem; }
    input, select, textarea {
      width: 100%; padding: 8px 10px; border: 1px solid rgba(255,255,255,0.1);
      border-radius: 4px; background: rgba(255,255,255,0.04); color: #e8e8ec;
      font-family: inherit; font-size: 0.88rem;
    }
    input:focus, select:focus, textarea:focus { outline: 2px solid #f2f24a; border-color: transparent; }
    textarea { resize: vertical; min-height: 50px; }
    .row { display: flex; gap: 10px; align-items: flex-end; }
    .row > * { flex: 1; }
    .mb { margin-bottom: 0.8rem; }

    .btn {
      display: inline-flex; align-items: center; gap: 5px; padding: 8px 16px;
      border: none; border-radius: 4px; font: inherit; font-size: 0.82rem;
      font-weight: 700; cursor: pointer; transition: 0.15s;
    }
    .btn-y { background: #f2f24a; color: #0a0a12; }
    .btn-y:hover { background: #fff; }
    .btn-g { background: #4ade80; color: #0a0a12; }
    .btn-g:hover { background: #6bf09a; }
    .btn-o { background: transparent; border: 1px solid rgba(255,255,255,0.15); color: #b8b8c4; }
    .btn-o:hover { border-color: #fff; color: #fff; }

    /* Output */
    .out {
      margin-top: 1rem; padding: 0.8rem 1rem; border-radius: 4px; background: #04040a;
      border: 1px solid rgba(255,255,255,0.05); font-family: 'Menlo','Consolas',monospace;
      font-size: 0.72rem; color: #7a7a8a; white-space: pre-wrap; word-break: break-all;
      max-height: 400px; overflow-y: auto;
    }
    .out .g { color: #4ade80; }
    .out .r { color: #ff4d6d; }

    /* Login */
    .login { max-width: 320px; margin: 6rem auto; }
    .err { color: #ff4d6d; font-size: 0.85rem; margin-bottom: 0.8rem; }
    .banned { color: #ff4d6d; text-align: center; margin-top: 6rem; }
  </style>
</head>
<body>
<div class="wrap">

<?php if ($is_banned): ?>
  <div class="banned">
    <h1>Acceso bloqueado</h1>
    <p>Demasiados intentos fallidos. Espera 15 minutos.</p>
  </div>

<?php elseif (!$authed): ?>
  <div class="login">
    <h1>Prisma</h1>
    <p style="color:#6a6a7a">Panel de mando</p>
    <?php if ($auth_error): ?><p class="err"><?= htmlspecialchars($auth_error) ?></p><?php endif; ?>
    <form method="post">
      <div class="mb">
        <label>Contraseña</label>
        <input type="password" name="pass" autofocus>
      </div>
      <button class="btn btn-y">Entrar</button>
    </form>
  </div>

<?php else: ?>

  <!-- Topbar -->
  <div class="topbar">
    <h1>Prisma — Panel de mando</h1>
    <div class="topbar-links">
      <a href="<?= $B ?>">Web</a>
      <a href="<?= $B ?>panel.php?logout=1">Salir</a>
    </div>
  </div>

  <!-- ════════ CONSUMO ════════ -->
  <h2>Consumo de API</h2>

  <div class="grid grid-4">
    <!-- Gasto hoy -->
    <div class="card">
      <?php $pct_day = $data['budget'] > 0 ? min(100, $data['day']['cost_usd'] / $data['budget'] * 100) : 0; ?>
      <div class="stat-val" style="color:<?= pct_color($pct_day) ?>">$<?= number_format($data['day']['cost_usd'], 2) ?></div>
      <div class="stat-sub">Hoy / $<?= number_format($data['budget'], 2) ?></div>
      <div class="prog"><div class="prog-fill" style="width:<?= $pct_day ?>%;background:<?= pct_color($pct_day) ?>"></div></div>
    </div>

    <!-- Gasto mes -->
    <div class="card">
      <div class="stat-val">$<?= number_format($data['month']['cost_usd'], 2) ?></div>
      <div class="stat-sub"><?= date('F Y') ?> (<?= $data['month']['days'] ?>d)</div>
    </div>

    <!-- Crédito restante -->
    <div class="card">
      <?php
        $total_spent = 0;
        foreach ($usage as $d) { if (isset($d['cost_usd'])) $total_spent += $d['cost_usd']; }
        $credit_left = max(0, $data['credit'] - $total_spent);
        $pct_used = $data['credit'] > 0 ? min(100, $total_spent / $data['credit'] * 100) : 0;
      ?>
      <div class="stat-val" style="color:<?= pct_color($pct_used) ?>">$<?= number_format($credit_left, 2) ?></div>
      <div class="stat-sub">Restante ($<?= number_format($total_spent, 2) ?> de $<?= number_format($data['credit'], 2) ?> usado)</div>
      <div class="prog"><div class="prog-fill" style="width:<?= $pct_used ?>%;background:<?= pct_color($pct_used) ?>"></div></div>
    </div>

    <!-- Llamadas hoy -->
    <div class="card">
      <div class="stat-val"><?= $data['day']['calls'] ?></div>
      <div class="stat-sub">Llamadas API hoy</div>
    </div>
  </div>

  <!-- Tokens hoy/mes -->
  <div class="grid grid-2" style="margin-top:10px">
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.4rem">Tokens hoy</div>
      <span style="color:#fff;font-weight:700"><?= fmt_tokens($data['day']['input_tokens'] ?? 0) ?></span> in ·
      <span style="color:#fff;font-weight:700"><?= fmt_tokens($data['day']['output_tokens'] ?? 0) ?></span> out
    </div>
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.4rem">Tokens <?= date('M Y') ?></div>
      <span style="color:#fff;font-weight:700"><?= fmt_tokens($data['month']['input_tokens']) ?></span> in ·
      <span style="color:#fff;font-weight:700"><?= fmt_tokens($data['month']['output_tokens']) ?></span> out ·
      <span style="color:#6a6a7a"><?= $data['month']['calls'] ?> calls</span>
    </div>
  </div>

  <!-- Mini chart 7 días -->
  <?php $max_cost = max(0.01, max(array_column($data['week'], 'cost'))); ?>
  <div class="card" style="margin-top:10px">
    <div class="stat-sub">Gasto últimos 7 días</div>
    <div class="bars">
      <?php foreach ($data['week'] as $d): ?>
        <?php $h = max(2, ($d['cost'] / $max_cost) * 50); ?>
        <div class="bar-col">
          <div class="bar" style="height:<?= $h ?>px;background:<?= $d['date'] === date('Y-m-d') ? '#f2f24a' : '#4dc3ff' ?>"></div>
          <div class="bar-label"><?= $d['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ════════ ESTADO ════════ -->
  <h2>Estado del sistema</h2>

  <div class="grid grid-4">
    <div class="card">
      <div class="stat-val"><?= $data['total'] ?></div>
      <div class="stat-sub">Artículos total</div>
    </div>
    <div class="card">
      <div class="stat-val"><?= $data['hoy'] ?></div>
      <div class="stat-sub">Publicados hoy</div>
    </div>
    <div class="card">
      <div class="stat-val"><?= $data['tasa_apto'] ?>%</div>
      <div class="stat-sub">Tasa APTO</div>
    </div>
    <div class="card">
      <div class="stat-val"><?= $data['rechazados'] ?></div>
      <div class="stat-sub">Rechazados</div>
    </div>
  </div>

  <div class="grid grid-2" style="margin-top:10px">
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.3rem">Fuentes RSS</div>
      <span style="color:#fff;font-weight:700"><?= $data['n_fuentes'] ?></span> medios ·
      <span style="color:#fff;font-weight:700"><?= $data['n_ambitos'] ?></span> ámbitos ·
      <span style="color:#fff;font-weight:700"><?= $data['n_cuadrantes'] ?></span> cuadrantes
    </div>
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.3rem">Última publicación</div>
      <span style="color:#fff"><?= htmlspecialchars($data['ultimo']) ?></span>
    </div>
  </div>

  <!-- Últimos artículos -->
  <?php if ($data['ultimos']): ?>
    <h2>Últimos artículos</h2>
    <div class="card card-lg" style="padding:0;overflow:hidden">
      <table>
        <thead><tr><th>ID</th><th>Titular</th><th>Auditoría</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($data['ultimos'] as $row): ?>
          <tr>
            <td><a href="<?= $B ?>articulo.php?id=<?= urlencode($row['id']) ?>"><?= htmlspecialchars($row['id']) ?></a></td>
            <td style="max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars(mb_substr($row['titular_neutral'], 0, 80)) ?></td>
            <td><?php
              $v = $row['veredicto'] ?? '';
              $cls = $v === 'APTO' ? 'ok' : ($v === 'REVISIÓN' ? 'warn' : 'err');
              echo "<span class='badge badge-$cls'>$v</span>";
            ?></td>
            <td style="white-space:nowrap"><?= htmlspecialchars(substr($row['fecha_publicacion'], 0, 16)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- ════════ ACCIONES ════════ -->
  <h2>Acciones</h2>

  <div class="grid grid-2">
    <!-- Dry run -->
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.6rem">Probar RSS + curación</div>
      <p style="font-size:0.82rem;color:#7a7a8a">Lee RSS, agrupa, selecciona temas. Sin gastar tokens.</p>
      <form method="post">
        <input type="hidden" name="action" value="dry-run">
        <div class="mb">
          <label>Ámbito</label>
          <select name="ambito">
            <option value="españa" selected>España</option>
            <option value="europa">Europa</option>
            <option value="global">Global</option>
          </select>
        </div>
        <button class="btn btn-o">Dry-run</button>
      </form>
    </div>

    <!-- Pipeline completo -->
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.6rem">Pipeline completo</div>
      <p style="font-size:0.82rem;color:#7a7a8a">RSS + curación + síntesis + auditoría + publicación.</p>
      <form method="post">
        <input type="hidden" name="action" value="pipeline">
        <div class="row" style="margin-bottom:0.6rem">
          <div>
            <label>Ámbito</label>
            <select name="ambito">
              <option value="españa" selected>España</option>
              <option value="europa">Europa</option>
              <option value="global">Global</option>
            </select>
          </div>
          <div>
            <label>Temas</label>
            <select name="temas">
              <option value="1">1 (prueba)</option>
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="5" selected>5</option>
            </select>
          </div>
        </div>
        <button class="btn btn-g">Lanzar pipeline</button>
      </form>
    </div>
  </div>

  <!-- Manual -->
  <div class="card" style="margin-top:10px">
    <div class="stat-sub" style="margin-bottom:0.6rem">Procesar tema manual</div>
    <form method="post">
      <input type="hidden" name="action" value="manual">
      <div class="mb">
        <label>Tema o noticia</label>
        <textarea name="tema" placeholder="Ej: Manifestación por la educación pública en Madrid"></textarea>
      </div>
      <div class="row">
        <div>
          <label>Ámbito</label>
          <select name="ambito">
            <option value="españa">España</option>
            <option value="europa">Europa</option>
            <option value="global">Global</option>
          </select>
        </div>
        <div style="flex:0">
          <label>&nbsp;</label>
          <button class="btn btn-y">Procesar</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Ajustes -->
  <h2>Ajustes</h2>
  <div class="card">
    <div class="stat-sub" style="margin-bottom:0.6rem">Crédito API cargado</div>
    <p style="font-size:0.82rem;color:#7a7a8a">Anthropic no permite consultar el saldo por API. Actualiza aquí cuando recargues en <a href="https://console.anthropic.com/settings/billing" target="_blank">console.anthropic.com</a>.</p>
    <form method="post">
      <input type="hidden" name="action" value="update-credit">
      <div class="row">
        <div>
          <label>Crédito total cargado (USD)</label>
          <input type="number" name="credit" step="0.01" min="0" value="<?= number_format($data['credit'], 2, '.', '') ?>">
        </div>
        <div style="flex:0">
          <label>&nbsp;</label>
          <button class="btn btn-o">Actualizar</button>
        </div>
      </div>
    </form>
    <p style="font-size:0.75rem;color:#5a5a6a;margin-top:0.6rem;margin-bottom:0">Gasto total trackeado: $<?= number_format($data['total_spent'] ?? 0, 4) ?> · Restante calculado: $<?= number_format($data['credit_left'] ?? 0, 2) ?></p>
  </div>

  <!-- Output de la última acción -->
  <?php if ($action_output): ?>
    <h2>Resultado</h2>
    <div class="out"><?php
      foreach (explode("\n", htmlspecialchars($action_output)) as $line) {
        if (preg_match('/✓|APTO|PUBLICADO|COMPLETADO/', $line))
          echo "<span class='g'>$line</span>\n";
        elseif (preg_match('/✗|ERROR|RECHAZO|DESCARTADO|Abortando/', $line))
          echo "<span class='r'>$line</span>\n";
        else
          echo "$line\n";
      }
    ?></div>

    <?php
    // Extract topics from dry-run output for quick-process buttons
    $detected_topics = [];
    $detected_ambito = 'españa';
    if (preg_match('/Ámbito:\s*(\S+)/', $action_output, $m_amb)) {
        $detected_ambito = $m_amb[1];
    }
    // Match: Tema N/M: <title>
    if (preg_match_all('/Tema \d+\/\d+:\s*(.+)/', $action_output, $matches)) {
        $detected_topics = $matches[1];
    }
    ?>
    <?php if (!empty($detected_topics) && isset($_POST['action']) && $_POST['action'] === 'dry-run'): ?>
      <h2>Procesar temas detectados</h2>
      <p style="font-size:0.82rem;color:#7a7a8a;margin-bottom:1rem">Lanza el pipeline completo para cualquiera de los temas del dry-run:</p>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($detected_topics as $topic): ?>
          <form method="post" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="action" value="manual">
            <input type="hidden" name="ambito" value="<?= htmlspecialchars($detected_ambito) ?>">
            <input type="hidden" name="tema" value="<?= htmlspecialchars(trim($topic)) ?>">
            <button class="btn btn-y" style="flex-shrink:0;padding:6px 14px;font-size:0.78rem">Procesar</button>
            <span style="font-size:0.85rem;color:var(--text-muted)"><?= htmlspecialchars(mb_substr(trim($topic), 0, 100)) ?></span>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
