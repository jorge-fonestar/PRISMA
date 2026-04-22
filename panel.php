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
$cfg = prisma_cfg();

// ── Fail2ban básico ──────────────────────────────────────────────────

$ban_file = __DIR__ . '/data/panel_bans.json';
$bans = file_exists($ban_file) ? (json_decode(file_get_contents($ban_file), true) ?: array()) : array();
$client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$ban_window = 900;
$max_attempts = 5;

if (isset($bans[$client_ip])) {
    $bans[$client_ip] = array_filter($bans[$client_ip], function($ts) use ($ban_window) { return $ts > time() - $ban_window; });
    if (empty($bans[$client_ip])) unset($bans[$client_ip]);
}

$is_banned = count(isset($bans[$client_ip]) ? $bans[$client_ip] : array()) >= $max_attempts;

// ── Auth ─────────────────────────────────────────────────────────────

session_start();
$authed = (isset($_SESSION['prisma_panel']) ? $_SESSION['prisma_panel'] : '') === 'ok';
$auth_error = '';

if (!$authed && isset($_POST['pass']) && !$is_banned) {
    if ($_POST['pass'] === $cfg['panel_pass']) {
        $_SESSION['prisma_panel'] = 'ok';
        $authed = true;
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

// ── Helper ───────────────────────────────────────────────────────────

function ph($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function fmt_tokens($n) {
    $n = (int)$n;
    if ($n >= 1000000) return number_format($n / 1000000, 1) . 'M';
    if ($n >= 1000) return number_format($n / 1000, 1) . 'K';
    return (string)$n;
}

function pct_color($pct) {
    if ($pct < 50) return '#4ade80';
    if ($pct < 80) return '#f2f24a';
    return '#ff4d6d';
}

// ── Acciones ─────────────────────────────────────────────────────────

$action_output = '';
$action_result = null;


if ($authed && isset($_POST['action'])) {
    set_time_limit(300);
    ob_start();

    $action = $_POST['action'];
    $php = PHP_BINARY ?: 'php';
    $cd = 'cd ' . escapeshellarg(__DIR__);

    if ($action === 'escanear') {
        // Phase 1: Scan RSS + calculate tension + insert radar (free)
        $safe_ambito = escapeshellarg(isset($_POST['ambito']) ? $_POST['ambito'] : 'todos');
        passthru("$cd && $php escanear.php --ambito $safe_ambito 2>&1", $rc);
        $action_result = $rc === 0 ? 'ok' : 'error';

    } elseif ($action === 'analizar-pendientes') {
        // Phase 2: Analyze top N pending topics (costs tokens)
        $n = max(1, min(10, (int)(isset($_POST['temas']) ? $_POST['temas'] : $cfg['articulos_dia'])));
        passthru("$cd && $php analizar.php --temas $n 2>&1", $rc);
        $action_result = $rc === 0 ? 'ok' : 'error';

    } elseif ($action === 'process-radar') {
        // Phase 2: Analyze a specific radar topic by ID (costs tokens)
        $radar_id = (int)(isset($_POST['radar_id']) ? $_POST['radar_id'] : 0);
        if ($radar_id > 0) {
            echo "Lanzando análisis para tema radar #$radar_id...\n\n";
            $safe_id = escapeshellarg($radar_id);
            passthru("$cd && $php analizar.php --id $safe_id 2>&1", $rc);
            $action_result = $rc === 0 ? 'ok' : 'error';
        }

    } elseif ($action === 'analizar-manual') {
        // Phase 2: Analyze a free-text topic (costs tokens)
        $tema = trim(isset($_POST['tema']) ? $_POST['tema'] : '');
        $ambito = isset($_POST['ambito']) ? $_POST['ambito'] : 'españa';
        if ($tema) {
            // Write job file for analizar.php
            $job = array('tema_libre' => $tema, 'ambito' => $ambito);
            $job_path = __DIR__ . '/data/manual_job.json';
            file_put_contents($job_path, json_encode($job, JSON_UNESCAPED_UNICODE));
            echo "Tema manual: $tema\nÁmbito: $ambito\n\n";
            passthru("$cd && $php analizar.php 2>&1", $rc);
            @unlink($job_path);
            $action_result = $rc === 0 ? 'ok' : 'error';
        } else {
            echo "Error: escribe un tema a analizar.\n";
            $action_result = 'error';
        }

    } elseif ($action === 'etiquetar') {
        $radar_id = (int)(isset($_POST['radar_id']) ? $_POST['radar_id'] : 0);
        $etiqueta = (int)(isset($_POST['etiqueta']) ? $_POST['etiqueta'] : 0);
        if ($radar_id > 0) {
            $db = prisma_db();
            $stmt = $db->prepare('INSERT OR REPLACE INTO etiquetas_calibracion (radar_id, etiqueta, operador) VALUES (:rid, :et, :op)');
            $stmt->execute(array(':rid' => $radar_id, ':et' => $etiqueta, ':op' => 'panel'));
            echo "Etiqueta guardada para radar #$radar_id: " . ($etiqueta ? 'relevante' : 'no relevante') . "\n";
            $action_result = 'ok';
        }

    } elseif ($action === 'reset-db') {
        $db = prisma_db();
        $db->exec('DELETE FROM radar');
        $db->exec('DELETE FROM articulos');
        echo "Base de datos limpia. Todas las entradas de radar y articulos eliminadas.\n";
        $action_result = 'ok';
    }

    $action_output = ob_get_clean();
}

// ── Datos del dashboard ──────────────────────────────────────────────

$data = array();
if ($authed) {
    $db = prisma_db();

    // Radar stats
    $data['radar_total'] = (int)$db->query("SELECT COUNT(*) FROM radar")->fetchColumn();
    $data['radar_hoy'] = (int)$db->query("SELECT COUNT(*) FROM radar WHERE fecha = date('now')")->fetchColumn();
    $data['radar_analizados'] = (int)$db->query("SELECT COUNT(*) FROM radar WHERE analizado = 1")->fetchColumn();

    // Articles stats
    $data['articulos_total'] = (int)$db->query("SELECT COUNT(*) FROM articulos")->fetchColumn();

    // Uso API
    $usage_file = __DIR__ . '/data/usage.json';
    $usage = file_exists($usage_file) ? (json_decode(file_get_contents($usage_file), true) ?: array()) : array();

    $today = date('Y-m-d');
    $month_prefix = date('Y-m');
    $data['day'] = isset($usage[$today]) ? $usage[$today] : array('cost_usd' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0);

    $data['month'] = array('cost_usd' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0, 'days' => 0);
    foreach ($usage as $day => $d) {
        if (strpos($day, $month_prefix) === 0 && isset($d['cost_usd'])) {
            $data['month']['cost_usd']      += $d['cost_usd'];
            $data['month']['input_tokens']  += isset($d['input_tokens']) ? $d['input_tokens'] : 0;
            $data['month']['output_tokens'] += isset($d['output_tokens']) ? $d['output_tokens'] : 0;
            $data['month']['calls']         += isset($d['calls']) ? $d['calls'] : 0;
            $data['month']['days']++;
        }
    }

    // Histórico últimos 7 días para mini-gráfico
    $data['week'] = array();
    $dias_es = array('dom','lun','mar','mié','jue','vie','sáb');
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $ts = strtotime($d);
        $data['week'][] = array(
            'date'  => $d,
            'label' => $dias_es[date('w', $ts)] . ' ' . date('j', $ts),
            'cost'  => isset($usage[$d]['cost_usd']) ? $usage[$d]['cost_usd'] : 0,
            'calls' => isset($usage[$d]['calls']) ? $usage[$d]['calls'] : 0,
        );
    }

    $data['budget'] = $cfg['daily_budget_usd'];
    $data['total_spent'] = 0;
    foreach ($usage as $d) { if (isset($d['cost_usd'])) $data['total_spent'] += $d['cost_usd']; }

    // Total calls all-time
    $data['total_calls'] = 0;
    foreach ($usage as $d) { if (isset($d['calls'])) $data['total_calls'] += $d['calls']; }
}

// Ambito options for forms
$ambito_labels = array('españa' => 'España', 'europa' => 'Europa', 'global' => 'Global', 'todos' => 'Todos');
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

    .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px;
              margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
    .topbar-links { display: flex; gap: 16px; font-size: 0.82rem; }

    .grid { display: grid; gap: 10px; }
    .grid-4 { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
    .grid-3 { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
    .grid-2 { grid-template-columns: 1fr 1fr; }
    @media (max-width: 600px) { .grid-2, .grid-3 { grid-template-columns: 1fr; } }

    .card {
      padding: 1rem 1.2rem; border: 1px solid rgba(255,255,255,0.06);
      border-radius: 6px; background: rgba(255,255,255,0.02);
    }
    .stat-val { font-size: 1.6rem; font-weight: 800; color: #fff; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .stat-sub { font-size: 0.72rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #6a6a7a; margin-top: 0.2rem; }

    .bars { display: flex; align-items: flex-end; gap: 4px; height: 60px; margin-top: 0.5rem; }
    .bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; height: 100%; justify-content: flex-end; }
    .bar { width: 100%; border-radius: 2px 2px 0 0; min-height: 2px; transition: height 0.3s; }
    .bar-label { font-size: 0.6rem; color: #5a5a6a; white-space: nowrap; }

    .prog { height: 6px; border-radius: 3px; background: rgba(255,255,255,0.06); margin-top: 0.6rem; overflow: hidden; }
    .prog-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }

    table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    th { text-align: left; font-weight: 600; color: #6a6a7a; font-size: 0.72rem; letter-spacing: 0.08em;
         text-transform: uppercase; padding: 0.5rem 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
    td { padding: 0.5rem 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.03); color: #b8b8c4; }
    tr:hover td { background: rgba(255,255,255,0.02); }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 0.68rem; font-weight: 700; }
    .badge-ok { background: rgba(74,222,128,0.12); color: #4ade80; }
    .badge-warn { background: rgba(242,242,74,0.12); color: #f2f24a; }
    .badge-err { background: rgba(255,77,109,0.12); color: #ff4d6d; }

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
    .btn-sm { padding: 4px 12px; font-size: 0.75rem; }
    .btn-y { background: #f2f24a; color: #0a0a12; }
    .btn-y:hover { background: #fff; }
    .btn-g { background: #4ade80; color: #0a0a12; }
    .btn-g:hover { background: #6bf09a; }
    .btn-o { background: transparent; border: 1px solid rgba(255,255,255,0.15); color: #b8b8c4; }
    .btn-o:hover { border-color: #fff; color: #fff; }
    .btn-r { background: rgba(255,77,109,0.15); color: #ff4d6d; border: 1px solid rgba(255,77,109,0.3); }
    .btn-r:hover { background: rgba(255,77,109,0.25); }

    .out {
      margin-top: 1rem; padding: 0.8rem 1rem; border-radius: 4px; background: #04040a;
      border: 1px solid rgba(255,255,255,0.05); font-family: 'Menlo','Consolas',monospace;
      font-size: 0.72rem; color: #7a7a8a; white-space: pre-wrap; word-break: break-all;
      max-height: 400px; overflow-y: auto;
    }
    .out .g { color: #4ade80; }
    .out .r { color: #ff4d6d; }

    .login { max-width: 320px; margin: 6rem auto; }
    .err { color: #ff4d6d; font-size: 0.85rem; margin-bottom: 0.8rem; }
    .banned { color: #ff4d6d; text-align: center; margin-top: 6rem; }

    .h-bar { display: inline-block; height: 6px; border-radius: 3px; vertical-align: middle; }
    .amb-tag { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 0.65rem; font-weight: 700;
               letter-spacing: 0.04em; text-transform: uppercase; background: rgba(255,255,255,0.05); color: #8a8a9a; }

    .sr-row { display: flex; gap: 8px; align-items: flex-start; padding: 0.6rem 0;
              border-bottom: 1px solid rgba(255,255,255,0.03); }
    .sr-row:last-child { border-bottom: none; }
    .sr-info { flex: 1; min-width: 0; }
    .sr-title { font-size: 0.85rem; color: #e8e8ec; font-weight: 600; }
    .sr-meta { font-size: 0.7rem; color: #6a6a7a; margin-top: 0.15rem; }

    .detail-row { display: none; }
    .detail-row.open { display: table-row; }
    .detail-cell { padding: 0.8rem 1rem; background: rgba(255,255,255,0.015); border-bottom: 1px solid rgba(255,255,255,0.06); }
    .detail-grid { display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.8rem; }
    .detail-grid .d-item { flex: 1; min-width: 120px; }
    .detail-grid .d-label { font-size: 0.68rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #6a6a7a; }
    .detail-grid .d-val { color: #e8e8ec; font-weight: 600; margin-top: 0.15rem; }
    .detail-resumen { color: #9a9aaa; font-size: 0.82rem; line-height: 1.5; margin-top: 0.6rem; }
    tr.row-analyzed { cursor: pointer; }
    tr.row-analyzed:hover td { background: rgba(74,222,128,0.04); }
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
    <?php if ($auth_error): ?><p class="err"><?= ph($auth_error) ?></p><?php endif; ?>
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

  <!-- ════════ ESTADO DEL SISTEMA ════════ -->
  <h2>Estado del sistema</h2>

  <div class="grid grid-4">
    <div class="card">
      <div class="stat-val"><?= $data['radar_total'] ?></div>
      <div class="stat-sub">Temas en radar</div>
    </div>
    <div class="card">
      <div class="stat-val"><?= $data['radar_hoy'] ?></div>
      <div class="stat-sub">Temas hoy</div>
    </div>
    <div class="card">
      <div class="stat-val"><?= $data['radar_analizados'] ?></div>
      <div class="stat-sub">Analizados</div>
    </div>
    <div class="card">
      <div class="stat-val"><?= $data['articulos_total'] ?></div>
      <div class="stat-sub">Artículos publicados</div>
    </div>
  </div>

  <!-- ════════ CONSUMO ════════ -->
  <h2>Consumo de API</h2>

  <div class="grid grid-4">
    <div class="card">
      <?php $pct_day = $data['budget'] > 0 ? min(100, $data['day']['cost_usd'] / $data['budget'] * 100) : 0; ?>
      <div class="stat-val" style="color:<?= pct_color($pct_day) ?>">$<?= number_format($data['day']['cost_usd'], 2) ?></div>
      <div class="stat-sub">Hoy / $<?= number_format($data['budget'], 2) ?></div>
      <div class="prog"><div class="prog-fill" style="width:<?= $pct_day ?>%;background:<?= pct_color($pct_day) ?>"></div></div>
    </div>
    <div class="card">
      <div class="stat-val">$<?= number_format($data['month']['cost_usd'], 2) ?></div>
      <div class="stat-sub">Este mes (<?= $data['month']['days'] ?> días)</div>
    </div>
    <div class="card">
      <div class="stat-val">$<?= number_format($data['total_spent'], 2) ?></div>
      <div class="stat-sub">Total absoluto</div>
    </div>
    <div class="card">
      <div class="stat-val"><?= $data['day']['calls'] ?></div>
      <div class="stat-sub">Llamadas hoy</div>
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

  <!-- ════════ ACCIONES ════════ -->
  <h2>Acciones</h2>

  <div class="grid grid-2">
    <!-- Phase 1: Scan -->
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.6rem">Fase 1 · Escanear fuentes</div>
      <p style="font-size:0.82rem;color:#7a7a8a">Lee RSS, agrupa temas, filtra por listas negativas, clasifica relevancia y framing con Haiku, y calcula el H-score v2. <strong style="color:#4ade80">Coste: ~$0.02/escaneo</strong></p>
      <form method="post">
        <input type="hidden" name="action" value="escanear">
        <div class="mb">
          <label>Ámbito</label>
          <select name="ambito">
            <option value="todos" selected>Todos</option>
            <?php foreach (array_keys($cfg['fuentes']) as $amb): ?>
              <option value="<?= ph($amb) ?>"><?= ph(isset($ambito_labels[$amb]) ? $ambito_labels[$amb] : ucfirst($amb)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-o">Escanear</button>
      </form>
    </div>

    <!-- Phase 2: Analyze top N -->
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.6rem">Fase 2 · Analizar pendientes</div>
      <p style="font-size:0.82rem;color:#7a7a8a">Triage Haiku + síntesis Sonnet + auditoría Moral Core de los temas con más polarización. <strong style="color:#ff4d6d">Gasta tokens</strong></p>
      <form method="post" onsubmit="return confirm('Esto gastará tokens de API. ¿Continuar?')">
        <input type="hidden" name="action" value="analizar-pendientes">
        <div class="mb">
          <label>Máximo de temas</label>
          <select name="temas">
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3" selected>3</option>
            <option value="5">5</option>
          </select>
        </div>
        <button class="btn btn-g">Analizar</button>
      </form>
    </div>
  </div>

  <!-- Output de la última acción -->
  <?php if ($action_output): ?>
    <h2>Resultado</h2>
    <div class="out"><?php
      foreach (explode("\n", ph($action_output)) as $line) {
        if (preg_match('/APTO|PUBLICADO|COMPLETADO|insertados|ok/', $line))
          echo "<span class='g'>$line</span>\n";
        elseif (preg_match('/ERROR|RECHAZO|DESCARTADO|Abortando|error/', $line))
          echo "<span class='r'>$line</span>\n";
        else
          echo "$line\n";
      }
    ?></div>
  <?php endif; ?>

  <!-- ════════ CALIBRACIÓN (justo después de acciones) ════════ -->
  <h2>Calibración — Etiquetado manual</h2>
  <?php
    $cal_total_radar = (int)$db->query("SELECT COUNT(*) FROM radar")->fetchColumn();
    $cal_total_etiquetado = (int)$db->query("SELECT COUNT(*) FROM etiquetas_calibracion")->fetchColumn();
    $cal_next = $db->query("SELECT r.* FROM radar r LEFT JOIN etiquetas_calibracion e ON r.id = e.radar_id WHERE e.id IS NULL ORDER BY r.h_score DESC LIMIT 1")->fetch();
  ?>
  <div class="card">
    <div class="stat-sub" style="margin-bottom:0.8rem"><?= $cal_total_etiquetado ?>/<?= $cal_total_radar ?> etiquetados (<?= $cal_total_radar > 0 ? round($cal_total_etiquetado/$cal_total_radar*100) : 0 ?>%)</div>

    <?php if ($cal_next): ?>
    <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:1.2em;margin-top:0.8em">
      <h4 style="margin:0 0 0.5em 0"><?= ph($cal_next['titulo_tema']) ?></h4>
      <p style="font-size:0.82em;color:#7a7a8a;margin:0 0 0.8em 0">
        Ámbito: <?= $cal_next['ambito'] ?> |
        H-score: <?= round($cal_next['h_score']*100) ?>%
        <?php if ($cal_next['relevancia']): ?> | Rel: <?= $cal_next['relevancia'] ?><?php endif; ?>
        <?php if ($cal_next['dominio_tematico']): ?> | Dominio: <?= $cal_next['dominio_tematico'] ?><?php endif; ?>
      </p>

      <?php
      $cal_fuentes = json_decode($cal_next['fuentes_json'], true);
      if ($cal_fuentes):
          $cal_por_cuadrante = array();
          foreach ($cal_fuentes as $f) $cal_por_cuadrante[$f['cuadrante']][] = $f;
      ?>
      <div style="margin-bottom:0.8em;font-size:0.80em">
        <?php foreach ($cal_por_cuadrante as $cuad => $arts): ?>
        <div style="margin-bottom:0.4em">
          <strong style="color:#7a7a8a"><?= ph($cuad) ?>:</strong>
          <?php foreach ($arts as $a): ?>
          <div style="margin-left:1em">[<?= ph($a['medio']) ?>] <?= ph($a['titulo']) ?></div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" style="display:flex;gap:0.8em">
        <input type="hidden" name="action" value="etiquetar">
        <input type="hidden" name="radar_id" value="<?= $cal_next['id'] ?>">
        <button type="submit" name="etiqueta" value="1"
            style="padding:0.5em 1.2em;background:#22c55e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:0.85em">
          Relevante polarizado
        </button>
        <button type="submit" name="etiqueta" value="0"
            style="padding:0.5em 1.2em;background:#ef4444;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:0.85em">
          No relevante
        </button>
      </form>
    </div>
    <?php else: ?>
    <p style="color:#7a7a8a;font-size:0.85em">Todos los temas etiquetados.</p>
    <?php endif; ?>
  </div>

  <!-- ════════ BUSCADOR DE RADAR ════════ -->
  <h2>Radar</h2>
  <?php
    $radar_query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $radar_results = array();
    if ($radar_query !== '') {
        $sq = $db->prepare("SELECT r.id, r.fecha, r.titulo_tema, r.ambito, r.h_score, r.analizado, r.articulo_id, r.relevancia, r.dominio_tematico,
               a.veredicto, a.puntuacion
               FROM radar r LEFT JOIN articulos a ON r.articulo_id = a.id
               WHERE r.titulo_tema LIKE :q ORDER BY r.fecha DESC LIMIT 50");
        $sq->execute(array(':q' => '%' . $radar_query . '%'));
        $radar_results = $sq->fetchAll();
    }
  ?>
  <div class="card">
    <form method="get" action="panel.php" style="margin-bottom:0.8rem">
      <div class="row">
        <div style="flex:3">
          <label>Buscar en radar</label>
          <input type="text" name="q" placeholder="Ej: Ayuso, Hungría, homeopatía..." value="<?= ph($radar_query) ?>">
        </div>
        <div style="flex:0">
          <label>&nbsp;</label>
          <button class="btn btn-o">Buscar</button>
        </div>
      </div>
    </form>

    <?php if ($radar_query !== ''): ?>
      <div class="stat-sub" style="margin-bottom:0.6rem"><?= count($radar_results) ?> resultados para &laquo;<?= ph($radar_query) ?>&raquo;</div>
      <?php if (!empty($radar_results)): ?>
        <table>
          <thead><tr><th>Fecha</th><th style="width:45%">Tema</th><th>Ámbito</th><th>H</th><th>Estado</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($radar_results as $rr):
            $rr_pct = round($rr['h_score'] * 100);
            $rr_color = $rr_pct >= 75 ? '#ff4d6d' : ($rr_pct >= 50 ? '#f2f24a' : '#4dc3ff');
            $rr_analyzed = (bool)$rr['analizado'];
          ?>
            <tr>
              <td style="white-space:nowrap;font-size:0.78rem"><?= ph($rr['fecha']) ?></td>
              <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?php if ($rr_analyzed): ?><span style="color:#4ade80;margin-right:4px" title="Analizado">&#10003;</span><?php endif; ?>
                <?= ph(mb_substr($rr['titulo_tema'], 0, 70, 'UTF-8')) ?>
              </td>
              <td><span class="amb-tag"><?= ph(isset($ambito_labels[$rr['ambito']]) ? $ambito_labels[$rr['ambito']] : $rr['ambito']) ?></span></td>
              <td style="white-space:nowrap">
                <span class="h-bar" style="width:<?= max(8, $rr_pct * 0.6) ?>px;background:<?= $rr_color ?>"></span>
                <span style="font-size:0.78rem;font-weight:700;color:<?= $rr_color ?>"><?= $rr_pct ?>%</span>
              </td>
              <td>
                <?php if ($rr_analyzed):
                  $rv = isset($rr['veredicto']) ? $rr['veredicto'] : '';
                  $rcls = $rv === 'APTO' ? 'ok' : ($rv === 'REVISIÓN' ? 'warn' : 'err');
                ?>
                  <span class="badge badge-<?= $rcls ?>"><?= ph($rv) ?></span>
                <?php elseif ($rr['relevancia']): ?>
                  <span style="font-size:0.72rem;color:#7a7a8a"><?= ph($rr['relevancia']) ?></span>
                <?php else: ?>
                  <span class="badge badge-warn">Pendiente</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($rr_analyzed && $rr['articulo_id']): ?>
                  <a href="<?= $B ?>articulo.php?id=<?= urlencode($rr['articulo_id']) ?>" class="btn btn-o btn-sm">Ver</a>
                <?php elseif (!$rr_analyzed): ?>
                  <form method="post" style="margin:0" onsubmit="if(!confirm('Gastará tokens. ¿Continuar?'))return false;this.querySelector('button').disabled=true;this.querySelector('button').textContent='...'">
                    <input type="hidden" name="action" value="process-radar">
                    <input type="hidden" name="radar_id" value="<?= $rr['id'] ?>">
                    <button class="btn btn-g btn-sm">Analizar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:#6a6a7a;margin:0;font-size:0.85rem">Sin resultados.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Scoring v2: Anomalías -->
  <h2>Anomalías de scoring</h2>
  <p style="font-size:0.82rem;color:#7a7a8a;margin-bottom:0.8rem">Casos donde el motor detecta inconsistencias en la clasificación automática: actores políticos clasificados como irrelevantes, violaciones de caps de framing, o overrides automáticos.</p>
  <?php
    $anomalies_count = (int)$db->query("SELECT COUNT(*) FROM scoring_anomalies WHERE fecha >= date('now', '-7 days')")->fetchColumn();
    $anomalies = $db->query("SELECT * FROM scoring_anomalies ORDER BY created_at DESC LIMIT 50")->fetchAll();
  ?>
  <div class="card">
    <div class="stat-sub" style="margin-bottom:0.8rem">Últimos 7 días: <?= $anomalies_count ?> anomalías</div>
    <?php if (empty($anomalies)): ?>
      <p style="color:#7a7a8a;font-size:0.85em">Sin anomalías registradas.</p>
    <?php else: ?>
      <table style="width:100%;border-collapse:collapse;font-size:0.82em">
        <tr style="border-bottom:1px solid rgba(255,255,255,0.1)">
          <th style="text-align:left;padding:6px">Fecha</th>
          <th style="text-align:left;padding:6px">Tipo</th>
          <th style="text-align:left;padding:6px">Detalle</th>
          <th style="text-align:left;padding:6px">Radar</th>
        </tr>
        <?php foreach ($anomalies as $a):
            $sev_color = '#7a7a8a';
            if (strpos($a['tipo'], 'POLITICAL_LOW') !== false) $sev_color = '#ff9e4d';
            if (strpos($a['tipo'], 'CAP_VIOLATION') !== false) $sev_color = '#ff4d6d';
            if (strpos($a['tipo'], 'FRAMING_OVERRIDE') !== false) $sev_color = '#f2f24a';
        ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.05)">
          <td style="padding:6px"><?= ph($a['fecha']) ?></td>
          <td style="padding:6px;color:<?= $sev_color ?>"><?= ph($a['tipo']) ?></td>
          <td style="padding:6px"><?= ph($a['detalle']) ?></td>
          <td style="padding:6px"><?= $a['radar_id'] ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>

  <!-- Reset DB -->
  <h2>Mantenimiento</h2>
  <div class="card">
    <div class="stat-sub" style="margin-bottom:0.4rem">Resetear base de datos</div>
    <p style="font-size:0.82rem;color:#7a7a8a">Elimina todos los registros de radar y artículos. Irreversible.</p>
    <form method="post" onsubmit="return confirm('¿Seguro? Se borrarán TODOS los datos de radar y artículos.')">
      <input type="hidden" name="action" value="reset-db">
      <button class="btn btn-r">Resetear DB</button>
    </form>
  </div>

<?php endif; ?>

</div>
</body>
</html>
