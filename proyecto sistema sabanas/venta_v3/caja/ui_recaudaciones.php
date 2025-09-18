<?php
// /caja/recaudaciones.php — UI: Consolidador de Recaudación (rutas relativas)
session_start();
if (empty($_SESSION['id_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x,$d=0){ return number_format((float)$x,$d,',','.'); }

$idUser = (int)$_SESSION['id_usuario'];

// Filtros (por defecto: hoy)
$hoy    = date('Y-m-d');
$desde  = trim($_GET['desde'] ?? $hoy);
$hasta  = trim($_GET['hasta'] ?? $hoy);
$suc    = trim($_GET['suc']   ?? ''); // opcional si tenés sucursales

$params = [];
$w = ["cs.estado='Cerrada'"];
if ($desde !== '') { $w[]="cs.fecha_cierre >= $".(count($params)+1); $params[] = $desde.' 00:00:00'; }
if ($hasta !== '') { $w[]="cs.fecha_cierre <= $".(count($params)+1); $params[] = $hasta.' 23:59:59'; }
if ($suc   !== '') { $w[]="c.id_sucursal = $".(count($params)+1);     $params[] = $suc; }

$whereSql = implode(' AND ', $w);

// Traer sesiones cerradas + totales por medio desde movimiento_caja
$sql = "
SELECT
  cs.id_caja_sesion,
  cs.id_caja,
  cs.id_usuario,
  cs.fecha_apertura,
  cs.fecha_cierre,
  c.nombre              AS caja_nombre,
  u.nombre_usuario      AS cajero,
  -- totales por medio (Ingreso - Egreso)
  COALESCE(SUM(CASE WHEN m.medio='Efectivo'      AND m.tipo='Ingreso' THEN m.monto
                    WHEN m.medio='Efectivo'      AND m.tipo='Egreso'  THEN -m.monto END),0)::numeric(14,2) AS t_efectivo,
  COALESCE(SUM(CASE WHEN m.medio='Tarjeta'       AND m.tipo='Ingreso' THEN m.monto
                    WHEN m.medio='Tarjeta'       AND m.tipo='Egreso'  THEN -m.monto END),0)::numeric(14,2) AS t_tarjeta,
  COALESCE(SUM(CASE WHEN m.medio='Transferencia' AND m.tipo='Ingreso' THEN m.monto
                    WHEN m.medio='Transferencia' AND m.tipo='Egreso'  THEN -m.monto END),0)::numeric(14,2) AS t_transferencia,
  COALESCE(SUM(CASE WHEN m.medio NOT IN ('Efectivo','Tarjeta','Transferencia') AND m.tipo='Ingreso' THEN m.monto
                    WHEN m.medio NOT IN ('Efectivo','Tarjeta','Transferencia') AND m.tipo='Egreso'  THEN -m.monto END),0)::numeric(14,2) AS t_otros
FROM public.caja_sesion cs
JOIN public.caja     c ON c.id_caja   = cs.id_caja
JOIN public.usuarios u ON u.id        = cs.id_usuario
LEFT JOIN public.movimiento_caja m ON m.id_caja_sesion = cs.id_caja_sesion
WHERE $whereSql
GROUP BY cs.id_caja_sesion, cs.id_caja, cs.id_usuario, cs.fecha_apertura, cs.fecha_cierre, c.nombre, u.nombre_usuario
ORDER BY cs.fecha_cierre DESC NULLS LAST, cs.id_caja_sesion DESC
";
$r = pg_query_params($conn, $sql, $params);
$rows = [];
if ($r) { while($x=pg_fetch_assoc($r)) $rows[] = $x; }

// Helper totales globales de lo listado
$g = ['ef'=>0,'ta'=>0,'tr'=>0,'ot'=>0];
foreach($rows as $x){
  $g['ef'] += (float)$x['t_efectivo'];
  $g['ta'] += (float)$x['t_tarjeta'];
  $g['tr'] += (float)$x['t_transferencia'];
  $g['ot'] += (float)$x['t_otros'];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recaudaciones — Consolidador</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{ --text:#111827; --muted:#6b7280; --em:#2563eb; --danger:#b91c1c; --ok:#166534; --warn:#9a6700; }
  body{ margin:0; color:var(--text); font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto; background:#fff; }
  .container{ max-width:1150px; margin:24px auto; padding:0 14px; }
  .head{ display:flex; justify-content:space-between; align-items:center; margin:10px 0 14px; }
  .head h1{ margin:0; font-size:22px; }
  .muted{ color:var(--muted); }
  .card{ border:1px solid #e5e7eb; border-radius:12px; padding:14px; margin-bottom:12px; }
  .grid{ display:grid; gap:12px; }
  @media(min-width:1000px){ .grid.cols-4{ grid-template-columns: repeat(4,1fr); } .grid.cols-3{ grid-template-columns: repeat(3,1fr); } }
  label{ display:block; font-weight:600; margin-bottom:6px; }
  input, select{ width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
  table{ width:100%; border-collapse:collapse; }
  th,td{ padding:10px; border-bottom:1px solid #f1f5f9; text-align:left; vertical-align:middle; }
  th{ background:#f8fafc; }
  .right{ text-align:right; }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; }
  .btn{ display:inline-block; padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; text-decoration:none; color:#111; cursor:pointer; }
  .btn.primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
  .btn.ghost{ background:#fff; }
  .totbox{ display:grid; grid-template-columns: repeat(4,1fr); gap:10px; }
  .pill{ border:1px solid #e5e7eb; border-radius:999px; padding:6px 10px; font-size:13px; }
</style>
</head>
<body>
<div id="navbar-container" class="no-print"></div>

<div class="container">
  <div class="head">
    <div>
      <h1>Consolidar Recaudación</h1>
      <div class="muted">Seleccioná sesiones <strong>cerradas</strong> para generar una recaudación (quedará <em>Pendiente de depósito</em>).</div>
    </div>
    <div class="actions">
      <a href="ui_panel.php" class="btn">Volver al Panel</a>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card">
    <form method="get" class="grid cols-4" style="align-items:end;">
      <div>
        <label>Desde</label>
        <input type="date" name="desde" value="<?= e($desde) ?>">
      </div>
      <div>
        <label>Hasta</label>
        <input type="date" name="hasta" value="<?= e($hasta) ?>">
      </div>
      <div>
        <label>Sucursal (opcional)</label>
        <input type="text" name="suc" value="<?= e($suc) ?>" placeholder="ID Sucursal">
      </div>
      <div>
        <button class="btn primary" type="submit">Aplicar</button>
        <a class="btn ghost" href="recaudaciones.php">Hoy</a>
      </div>
    </form>
  </div>

  <!-- Totales globales del período listado -->
  <div class="card">
    <div class="totbox">
      <div class="pill"><strong>Efectivo:</strong> Gs <?= n($g['ef'],0) ?></div>
      <div class="pill"><strong>Tarjeta:</strong> Gs <?= n($g['ta'],0) ?></div>
      <div class="pill"><strong>Transferencia:</strong> Gs <?= n($g['tr'],0) ?></div>
      <div class="pill"><strong>Otros:</strong> Gs <?= n($g['ot'],0) ?></div>
    </div>
  </div>

  <!-- Tabla de sesiones cerradas -->
  <div class="card">
    <form id="formRec" onsubmit="return false;">
      <table>
        <thead>
          <tr>
            <th style="width:36px"><input type="checkbox" id="checkAll"></th>
            <th>Sesión</th>
            <th>Caja</th>
            <th>Cajero</th>
            <th style="width:160px">Apertura</th>
            <th style="width:160px">Cierre</th>
            <th class="right" style="width:140px">Efectivo</th>
            <th class="right" style="width:140px">Tarjeta</th>
            <th class="right" style="width:140px">Transferencia</th>
            <th class="right" style="width:140px">Otros</th>
          </tr>
        </thead>
        <tbody>
        <?php if(empty($rows)): ?>
          <tr><td colspan="10" class="muted">No hay sesiones cerradas en el rango.</td></tr>
        <?php else: ?>
          <?php foreach($rows as $x): ?>
            <tr>
              <td><input type="checkbox" class="ck" value="<?= (int)$x['id_caja_sesion'] ?>"></td>
              <td>#<?= (int)$x['id_caja_sesion'] ?></td>
              <td><?= e($x['caja_nombre']) ?></td>
              <td><?= e($x['cajero']) ?></td>
              <td><?= e($x['fecha_apertura']) ?></td>
              <td><?= e($x['fecha_cierre']) ?></td>
              <td class="right"><?= n($x['t_efectivo'],0) ?></td>
              <td class="right"><?= n($x['t_tarjeta'],0) ?></td>
              <td class="right"><?= n($x['t_transferencia'],0) ?></td>
              <td class="right"><?= n($x['t_otros'],0) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>

      <div class="actions" style="margin-top:12px;">
        <div id="resume" class="muted"></div>
        <div style="flex:1"></div>
        <button class="btn primary" id="btnGen" <?= empty($rows)?'disabled':'' ?>>Generar Recaudación</button>
      </div>
      <div id="msg" class="muted" style="margin-top:6px;"></div>
    </form>
  </div>
</div>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
<script>
(function(){
  const $ = (q)=>document.querySelector(q);
  const $$ = (q)=>document.querySelectorAll(q);
  const fmt = (n)=> new Intl.NumberFormat('es-PY').format(n||0);

  const msg = $("#msg");
  const resume = $("#resume");

  const checkAll = $("#checkAll");
  const cks = $$(".ck");

  function resumen(){
    let sel = 0, ef=0, ta=0, tr=0, ot=0;
    cks.forEach(ck=>{
      if(ck.checked){
        sel++;
        const row = ck.closest('tr');
        ef += parseFloat((row.children[6].textContent||'0').replaceAll('.',''));
        ta += parseFloat((row.children[7].textContent||'0').replaceAll('.',''));
        tr += parseFloat((row.children[8].textContent||'0').replaceAll('.',''));
        ot += parseFloat((row.children[9].textContent||'0').replaceAll('.',''));
      }
    });
    if(sel===0){ resume.textContent = "Ninguna sesión seleccionada."; return; }
    resume.textContent = `Seleccionadas: ${sel} · Efectivo Gs ${fmt(ef)} · Tarjeta Gs ${fmt(ta)} · Transferencia Gs ${fmt(tr)} · Otros Gs ${fmt(ot)}`;
  }

  checkAll && checkAll.addEventListener('change', ()=>{
    cks.forEach(ck=> ck.checked = checkAll.checked);
    resumen();
  });
  cks.forEach(ck=> ck.addEventListener('change', resumen));
  resumen();

  async function generar(){
    // armar payload
    const ses = Array.from(cks).filter(x=>x.checked).map(x=>parseInt(x.value,10));
    if(ses.length===0){ msg.textContent="Seleccioná al menos una sesión."; return; }

    msg.textContent = "Generando recaudación...";
    try{
      const res = await fetch('recaudacion_crear.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ sesiones: ses })
      });
      const js = await res.json();
      if(!js.ok){
        msg.textContent = js.error || "No se pudo generar la recaudación.";
        return;
      }
      msg.textContent = "Recaudación creada. Abriendo detalle...";
      // si tu endpoint devuelve id_recaudacion:
      if(js.id_recaudacion){
        setTimeout(()=> location.href = 'recaudacion_detalle.php?id='+js.id_recaudacion, 600);
      }else{
        setTimeout(()=> location.reload(), 600);
      }
    }catch(e){
      console.error(e);
      msg.textContent = "Error de conexión.";
    }
  }

  $("#btnGen")?.addEventListener('click', (ev)=>{ ev.preventDefault(); generar(); });
})();
</script>
</body>
</html>
