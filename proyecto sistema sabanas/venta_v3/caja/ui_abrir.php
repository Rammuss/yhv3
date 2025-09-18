<?php
// /caja/abrir.php — UI: Apertura de Caja (elige caja + saldo inicial)
session_start();
if (empty($_SESSION['id_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x, $d=0){ return number_format((float)$x, $d, ',', '.'); }

// Traer cajas + estado de ocupación actual (sesiones abiertas)
$sql = "
  SELECT
    c.id_caja, c.nombre, c.id_sucursal, COALESCE(c.activa, TRUE) AS activa,
    sa.id_caja_sesion, sa.id_usuario AS usuario_abre, sa.fecha_apertura,
    u.nombre_usuario AS nombre_cajero
  FROM public.caja c
  LEFT JOIN public.v_caja_sesiones_abiertas sa ON sa.id_caja = c.id_caja
  LEFT JOIN public.usuarios u ON u.id = sa.id_usuario
  WHERE c.activa = TRUE
  ORDER BY c.id_sucursal NULLS FIRST, c.nombre
";

$rc = pg_query($conn, $sql);
$cajas = [];
if ($rc) {
  while($x = pg_fetch_assoc($rc)) { $cajas[] = $x; }
}

// ¿El usuario tiene ya una sesión abierta?
$miSesion = null;
$rms = pg_query_params($conn,
  "SELECT cs.id_caja_sesion, cs.id_caja, c.nombre AS caja_nombre, cs.fecha_apertura
     FROM public.caja_sesion cs
     JOIN public.caja c ON c.id_caja = cs.id_caja
    WHERE cs.id_usuario = $1 AND cs.estado = 'Abierta'
    LIMIT 1",
  [ (int)$_SESSION['id_usuario'] ]
);
if ($rms && pg_num_rows($rms)>0) { $miSesion = pg_fetch_assoc($rms); }

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Apertura de Caja</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{ --text:#111827; --muted:#6b7280; --em:#2563eb; --danger:#b91c1c; --ok:#166534; --warn:#9a6700; }
  body{ margin:0; color:var(--text); font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto; background:#fff; }
  .container{ max-width:900px; margin:24px auto; padding:0 14px; }
  .head{ display:flex; justify-content:space-between; align-items:center; margin:10px 0 14px; }
  .head h1{ margin:0; font-size:22px; }
  .muted{ color:var(--muted); }
  .card{ border:1px solid #e5e7eb; border-radius:12px; padding:14px; margin-bottom:14px; }
  .grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  label{ display:block; font-weight:600; margin-bottom:6px; }
  select, input[type="number"]{
    width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px;
  }
  .actions{ display:flex; gap:8px; margin-top:12px; }
  .btn{
    display:inline-block; padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; text-decoration:none; color:#111; cursor:pointer;
  }
  .btn.primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
  .btn.ghost{ background:#fff; }
  .badge{ display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid #e5e7eb; }
  .badge.ok{ color:#166534; border-color:#bbf7d0; background:#f0fdf4; }
  .badge.warn{ color:#9a6700; border-color:#fde68a; background:#fef9c3; }
  .badge.danger{ color:#b91c1c; border-color:#fecaca; background:#fef2f2; }
  .list{ border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; }
  .row{ display:flex; justify-content:space-between; align-items:center; padding:10px 12px; border-top:1px solid #f1f5f9; }
  .row:first-child{ border-top:none; }
  .row small{ color:var(--muted); }
  .right{ text-align:right; }
  .help{ font-size:12px; color:var(--muted); margin-top:6px; }
  .error{ color:#b91c1c; margin-top:8px; }
  .success{ color:#166534; margin-top:8px; }
</style>
</head>
<body>
<div id="navbar-container" class="no-print"></div>

<div class="container">
  <div class="head">
    <div>
      <h1>Apertura de Caja</h1>
      <div class="muted">Usuario: <strong><?= e($_SESSION['nombre_usuario'] ?? ('#'.(string)($_SESSION['id_usuario']??''))) ?></strong></div>
    </div>
    <div>
      <?php if ($miSesion): ?>
        <span class="badge warn">Ya tenés una sesión abierta — <?= e($miSesion['caja_nombre']) ?> (desde <?= e($miSesion['fecha_apertura']) ?>)</span>
      <?php else: ?>
        <span class="badge ok">Sin sesión activa</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <form id="formApertura" onsubmit="return false;">
      <div class="grid">
        <div>
          <label for="id_caja">Caja</label>
          <select id="id_caja" name="id_caja" required>
            <option value="">— Seleccionar —</option>
            <?php foreach($cajas as $c): 
              $ocupada = !empty($c['id_caja_sesion']);
              $label = $c['nombre'] . (isset($c['id_sucursal']) ? (" · Suc. ".$c['id_sucursal']) : "");
              $sub = $ocupada ? (" (Ocupada por ".($c['nombre_cajero'] ? $c['nombre_cajero'] : ('#'.$c['usuario_abre'])).")") : " (Libre)";
            ?>
              <option value="<?= (int)$c['id_caja'] ?>" <?= $ocupada ? 'disabled' : '' ?>>
                <?= e($label.$sub) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="help">Las cajas ocupadas aparecen deshabilitadas.</div>
        </div>
        <div>
          <label for="saldo_inicial">Saldo inicial (Gs)</label>
          <input type="number" min="0" step="1" id="saldo_inicial" name="saldo_inicial" placeholder="0" value="0" required>
          <div class="help">Ej.: 100000 para iniciar con Gs 100.000.</div>
        </div>
      </div>

      <div class="actions">
        <button class="btn primary" id="btnAbrir">Abrir sesión de caja</button>
        <a class="btn ghost" href="/index.php">Cancelar</a>
      </div>
      <div id="msg" class="help"></div>
    </form>
  </div>

  <div class="card">
    <h3 style="margin:0 0 8px">Estado de Cajas</h3>
    <div class="list">
      <?php if(count($cajas)===0): ?>
        <div class="row"><span>No hay cajas activas.</span></div>
      <?php else: ?>
        <?php foreach($cajas as $c): $ocupada = !empty($c['id_caja_sesion']); ?>
          <div class="row">
            <div>
              <strong><?= e($c['nombre']) ?></strong>
              <small>
                <?= isset($c['id_sucursal']) ? ' · Suc. '.e($c['id_sucursal']) : '' ?>
                — <?= $ocupada ? ('Ocupada por '.e($c['nombre_cajero'] ?: ('#'.$c['usuario_abre']))) : 'Libre' ?>
              </small>
            </div>
            <div class="right">
              <?php if ($ocupada): ?>
                <span class="badge warn">Ocupada</span>
              <?php else: ?>
                <span class="badge ok">Libre</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($miSesion): ?>
    <div class="card">
      <h3 style="margin:0 0 8px">Tu sesión actual</h3>
      <div class="list">
        <div class="row">
          <div>
            <strong><?= e($miSesion['caja_nombre']) ?></strong>
            <small> · Abierta: <?= e($miSesion['fecha_apertura']) ?></small>
          </div>
          <div class="right">
            <a class="btn" href="/caja/panel.php">Ir al panel</a>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
<script>
(function(){
  const $ = (q)=>document.querySelector(q);
  const btn = $("#btnAbrir");
  const msg = $("#msg");
  const form = $("#formApertura");

  async function abrirSesion(){
    msg.textContent = "Abriendo sesión...";
    msg.className = "help";

    const id_caja = $("#id_caja").value;
    const saldo_inicial = $("#saldo_inicial").value;

    if(!id_caja){ msg.textContent = "Elegí una caja."; msg.className="error"; return; }

    try{
      const res = await fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/caja_sesion_abrir.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id_caja: parseInt(id_caja,10), saldo_inicial: parseFloat(saldo_inicial||0) })
      });
      const json = await res.json();
      if(!json.ok){
        msg.textContent = (json.error || 'No se pudo abrir la sesión.');
        msg.className = "error";
        return;
      }
      msg.textContent = "Sesión abierta con éxito. Redirigiendo al panel...";
      msg.className = "success";
      setTimeout(()=> location.href='/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_panel.php', 800);
    }catch(err){
      console.error(err);
      msg.textContent = "Error de conexión al abrir la sesión.";
      msg.className = "error";
    }
  }

  btn.addEventListener('click', (e)=>{ e.preventDefault(); abrirSesion(); });
})();
</script>
</body>
</html>
