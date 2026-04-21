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
$search_results = array();

if ($authed && isset($_POST['action'])) {
    set_time_limit(300);
    ob_start();

    $action = $_POST['action'];
    $php = PHP_BINARY ?: 'php';
    $cd = 'cd ' . escapeshellarg(__DIR__);

    if ($action === 'dry-run') {
        $safe_ambito = escapeshellarg(isset($_POST['ambito']) ? $_POST['ambito'] : 'todos');
        passthru("$cd && $php pipeline.php --ambito $safe_ambito --dry-run 2>&1", $rc);
        $action_result = $rc === 0 ? 'ok' : 'error';

    } elseif ($action === 'process-radar') {
        // Process a specific radar topic by ID
        $radar_id = (int)(isset($_POST['radar_id']) ? $_POST['radar_id'] : 0);
        if ($radar_id > 0) {
            $db = prisma_db();
            $stmt = $db->prepare('SELECT * FROM radar WHERE id = :id');
            $stmt->execute(array(':id' => $radar_id));
            $radar_topic = $stmt->fetch();

            if ($radar_topic) {
                // Write job file for procesar.php
                $job = array('tema' => $radar_topic['titulo_tema'], 'ambito' => $radar_topic['ambito']);
                $job_path = __DIR__ . '/data/manual_job.json';
                file_put_contents($job_path, json_encode($job, JSON_UNESCAPED_UNICODE));
                echo "Procesando: " . $radar_topic['titulo_tema'] . "\n";
                echo "Ambito: " . $radar_topic['ambito'] . " | H-score: " . round($radar_topic['h_score'] * 100) . "%\n\n";
                passthru("$cd && $php procesar.php 2>&1", $rc);
                @unlink($job_path);

                if ($rc === 0) {
                    // Link the radar entry to the article
                    $article_id = $db->query("SELECT id FROM articulos ORDER BY created_at DESC LIMIT 1")->fetchColumn();
                    if ($article_id) {
                        $upd = $db->prepare('UPDATE radar SET analizado = 1, articulo_id = :aid WHERE id = :rid');
                        $upd->execute(array(':aid' => $article_id, ':rid' => $radar_id));
                    }
                }
                $action_result = $rc === 0 ? 'ok' : 'error';
            } else {
                echo "Error: tema radar #$radar_id no encontrado.\n";
                $action_result = 'error';
            }
        }

    } elseif ($action === 'search-topic') {
        // Search RSS feeds for articles matching a user query
        $query = trim(isset($_POST['query']) ? $_POST['query'] : '');
        if ($query) {
            require_once __DIR__ . '/lib/rss.php';
            echo "Buscando en fuentes RSS: \"$query\"\n\n";

            $all_articles = rss_fetch_all('');
            $query_lower = mb_strtolower($query, 'UTF-8');
            $keywords = preg_split('/\s+/', $query_lower);

            foreach ($all_articles as $art) {
                $haystack = mb_strtolower($art['titulo'] . ' ' . (isset($art['descripcion']) ? $art['descripcion'] : ''), 'UTF-8');
                $hits = 0;
                foreach ($keywords as $kw) {
                    if (mb_strpos($haystack, $kw) !== false) $hits++;
                }
                if ($hits >= max(1, count($keywords) - 1)) {
                    $search_results[] = $art;
                }
            }

            echo count($search_results) . " artículos encontrados de " . count($all_articles) . " totales.\n";
            $action_result = 'ok';
        } else {
            echo "Error: escribe un tema a buscar.\n";
            $action_result = 'error';
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
    $data['articulos_hoy'] = (int)$db->query("SELECT COUNT(*) FROM articulos WHERE date(fecha_publicacion) = date('now')")->fetchColumn();

    // Tasa APTO
    $total_audited = (int)$db->query("SELECT COUNT(*) FROM articulos WHERE veredicto IS NOT NULL")->fetchColumn();
    $aptos = (int)$db->query("SELECT COUNT(*) FROM articulos WHERE veredicto = 'APTO'")->fetchColumn();
    $data['tasa_apto'] = $total_audited > 0 ? round($aptos / $total_audited * 100) : 0;

    // Radar + articulos joined: one unified view
    $data['radar_temas'] = $db->query("
        SELECT r.id, r.fecha, r.titulo_tema, r.ambito, r.h_score, r.analizado, r.articulo_id,
               a.veredicto, a.puntuacion, a.resumen, a.fuentes_total
        FROM radar r
        LEFT JOIN articulos a ON r.articulo_id = a.id
        ORDER BY r.fecha DESC, r.h_score DESC
        LIMIT 100
    ")->fetchAll();

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
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $data['week'][] = array(
            'date'  => $d,
            'label' => date('D j', strtotime($d)),
            'cost'  => isset($usage[$d]['cost_usd']) ? $usage[$d]['cost_usd'] : 0,
            'calls' => isset($usage[$d]['calls']) ? $usage[$d]['calls'] : 0,
        );
    }

    $data['budget'] = $cfg['daily_budget_usd'];
    $data['credit'] = $cfg['total_credit_usd'];
    $data['total_spent'] = 0;
    foreach ($usage as $d) { if (isset($d['cost_usd'])) $data['total_spent'] += $d['cost_usd']; }
    $data['credit_left'] = max(0, $data['credit'] - $data['total_spent']);

    // Rechazados
    $rej_dir = __DIR__ . '/rechazados';
    $data['rechazados'] = is_dir($rej_dir) ? count(glob("$rej_dir/*.json")) : 0;

    // RSS sources count
    $data['n_fuentes'] = 0;
    $data['n_cuadrantes'] = 0;
    $data['n_ambitos'] = count($cfg['fuentes']);
    foreach ($cfg['fuentes'] as $amb => $cuadrantes) {
        $data['n_cuadrantes'] += count($cuadrantes);
        foreach ($cuadrantes as $medios) { $data['n_fuentes'] += count($medios); }
    }
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
      <div class="stat-val"><?= $data['radar_analizados'] ?></div>
      <div class="stat-sub">Analizados</div>
    </div>
    <div class="card">
      <div class="stat-val"><?= $data['articulos_total'] ?></div>
      <div class="stat-sub">Artículos publicados</div>
    </div>
    <div class="card">
      <div class="stat-val"><?= $data['tasa_apto'] ?>%</div>
      <div class="stat-sub">Tasa APTO</div>
    </div>
  </div>

  <div class="grid grid-3" style="margin-top:10px">
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.3rem">Fuentes RSS</div>
      <span style="color:#fff;font-weight:700"><?= $data['n_fuentes'] ?></span> medios ·
      <span style="color:#fff;font-weight:700"><?= $data['n_ambitos'] ?></span> ámbitos ·
      <span style="color:#fff;font-weight:700"><?= $data['n_cuadrantes'] ?></span> cuadrantes
    </div>
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.3rem">Hoy</div>
      <span style="color:#fff;font-weight:700"><?= $data['radar_hoy'] ?></span> temas ·
      <span style="color:#fff;font-weight:700"><?= $data['articulos_hoy'] ?></span> artículos
    </div>
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.3rem">Rechazados</div>
      <span style="color:#fff;font-weight:700"><?= $data['rechazados'] ?></span> en archivo
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
      <div class="stat-sub"><?= date('M Y') ?> (<?= $data['month']['days'] ?>d)</div>
    </div>
    <div class="card">
      <?php $pct_used = $data['credit'] > 0 ? min(100, $data['total_spent'] / $data['credit'] * 100) : 0; ?>
      <div class="stat-val" style="color:<?= pct_color($pct_used) ?>">$<?= number_format($data['credit_left'], 2) ?></div>
      <div class="stat-sub">Restante de $<?= number_format($data['credit'], 2) ?></div>
      <div class="prog"><div class="prog-fill" style="width:<?= $pct_used ?>%;background:<?= pct_color($pct_used) ?>"></div></div>
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
    <!-- Dry Run -->
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.6rem">Dry-Run (solo log)</div>
      <p style="font-size:0.82rem;color:#7a7a8a">Lee RSS, agrupa temas, calcula tensión. No gasta tokens ni publica.</p>
      <form method="post">
        <input type="hidden" name="action" value="dry-run">
        <div class="mb">
          <label>Ámbito</label>
          <select name="ambito">
            <option value="todos" selected>Todos</option>
            <?php foreach (array_keys($cfg['fuentes']) as $amb): ?>
              <option value="<?= ph($amb) ?>"><?= ph(isset($ambito_labels[$amb]) ? $ambito_labels[$amb] : ucfirst($amb)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-o">Lanzar dry-run</button>
      </form>
    </div>

    <!-- Buscar tema -->
    <div class="card">
      <div class="stat-sub" style="margin-bottom:0.6rem">Buscar tema en fuentes</div>
      <p style="font-size:0.82rem;color:#7a7a8a">Busca en todas las fuentes RSS artículos relacionados con un tema.</p>
      <form method="post">
        <input type="hidden" name="action" value="search-topic">
        <div class="mb">
          <label>Tema a buscar</label>
          <input type="text" name="query" placeholder="Ej: regulación inteligencia artificial" value="<?= ph(isset($_POST['action']) && $_POST['action'] === 'search-topic' ? (isset($_POST['query']) ? $_POST['query'] : '') : '') ?>">
        </div>
        <button class="btn btn-y">Buscar en fuentes</button>
      </form>
    </div>
  </div>

  <!-- Search results -->
  <?php if (!empty($search_results)): ?>
    <h2>Resultados de búsqueda (<?= count($search_results) ?>)</h2>
    <div class="card" style="padding:0.6rem 1rem">
      <?php foreach ($search_results as $sr): ?>
        <div class="sr-row">
          <div class="sr-info">
            <div class="sr-title"><?= ph($sr['titulo']) ?></div>
            <div class="sr-meta">
              <span class="amb-tag"><?= ph($sr['cuadrante']) ?></span>
              <?= ph($sr['medio']) ?>
              <?php if (isset($sr['url'])): ?> · <a href="<?= ph($sr['url']) ?>" target="_blank" rel="noopener">ver</a><?php endif; ?>
            </div>
          </div>
          <form method="post" style="flex-shrink:0">
            <input type="hidden" name="action" value="search-topic">
            <input type="hidden" name="query" value="<?= ph(isset($_POST['query']) ? $_POST['query'] : '') ?>">
            <input type="hidden" name="add_to_radar" value="1">
            <input type="hidden" name="sr_titulo" value="<?= ph($sr['titulo']) ?>">
            <input type="hidden" name="sr_medio" value="<?= ph($sr['medio']) ?>">
            <input type="hidden" name="sr_url" value="<?= ph(isset($sr['url']) ? $sr['url'] : '') ?>">
            <input type="hidden" name="sr_cuadrante" value="<?= ph($sr['cuadrante']) ?>">
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

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

  <!-- ════════ REVISIÓN DE RADAR ════════ -->
  <h2>Revisión de radar</h2>
  <p style="font-size:0.82rem;color:#7a7a8a;margin-bottom:1rem">Todos los temas detectados. Puedes lanzar análisis completo de cualquiera.</p>

  <?php if (empty($data['radar_temas'])): ?>
    <div class="card"><p style="color:#6a6a7a;margin:0">No hay temas en el radar. Ejecuta un dry-run primero.</p></div>
  <?php else: ?>
    <?php
      // Group by date
      $by_date = array();
      foreach ($data['radar_temas'] as $rt) {
          $by_date[$rt['fecha']][] = $rt;
      }
    ?>
    <?php foreach ($by_date as $fecha => $temas_dia): ?>
      <div style="font-size:0.72rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#6a6a7a;margin:1rem 0 0.4rem 0">
        <?= ph($fecha) ?> (<?= count($temas_dia) ?> temas)
      </div>
      <div class="card" style="padding:0;overflow:hidden">
        <table>
          <thead><tr><th style="width:50%">Tema</th><th>Ámbito</th><th>H-score</th><th>Estado</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($temas_dia as $rt): ?>
            <?php
              $score_pct = round($rt['h_score'] * 100);
              $bar_color = $score_pct >= 75 ? '#ff4d6d' : ($score_pct >= 50 ? '#f2f24a' : '#4dc3ff');
            ?>
            <tr>
              <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?php if ($rt['articulo_id']): ?>
                  <a href="<?= $B ?>articulo.php?id=<?= urlencode($rt['articulo_id']) ?>"><?= ph(mb_substr($rt['titulo_tema'], 0, 70, 'UTF-8')) ?></a>
                <?php else: ?>
                  <?= ph(mb_substr($rt['titulo_tema'], 0, 70, 'UTF-8')) ?>
                <?php endif; ?>
              </td>
              <td><span class="amb-tag"><?= ph(isset($ambito_labels[$rt['ambito']]) ? $ambito_labels[$rt['ambito']] : $rt['ambito']) ?></span></td>
              <td style="white-space:nowrap">
                <span class="h-bar" style="width:<?= max(8, $score_pct * 0.6) ?>px;background:<?= $bar_color ?>"></span>
                <span style="font-size:0.78rem;font-weight:700;color:<?= $bar_color ?>"><?= $score_pct ?>%</span>
              </td>
              <td>
                <?php if ($rt['analizado']): ?>
                  <span class="badge badge-ok">Analizado</span>
                <?php else: ?>
                  <span class="badge badge-warn">Pendiente</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$rt['analizado']): ?>
                  <form method="post" style="margin:0" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='...'">
                    <input type="hidden" name="action" value="process-radar">
                    <input type="hidden" name="radar_id" value="<?= $rt['id'] ?>">
                    <button class="btn btn-g btn-sm">Analizar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Últimos artículos publicados -->
  <?php if (!empty($data['ultimos'])): ?>
    <h2>Últimos artículos publicados</h2>
    <div class="card" style="padding:0;overflow:hidden">
      <table>
        <thead><tr><th>Titular</th><th>Auditoría</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($data['ultimos'] as $row): ?>
          <tr>
            <td style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <a href="<?= $B ?>articulo.php?id=<?= urlencode($row['id']) ?>"><?= ph(mb_substr($row['titular_neutral'], 0, 80, 'UTF-8')) ?></a>
            </td>
            <td><?php
              $v = isset($row['veredicto']) ? $row['veredicto'] : '';
              $cls = $v === 'APTO' ? 'ok' : ($v === 'REVISIÓN' ? 'warn' : 'err');
              echo "<span class='badge badge-$cls'>" . ph($v) . "</span>";
            ?></td>
            <td style="white-space:nowrap"><?= ph(substr($row['fecha_publicacion'], 0, 16)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

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
