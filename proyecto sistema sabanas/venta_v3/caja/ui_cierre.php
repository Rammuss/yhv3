<?php
// /caja/cierre.php — UI: Arqueo & Cierre de Caja (rutas relativas)
session_start();
if (empty($_SESSION['id_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x,$d=0){ return number_format((float)$x,$d,',','.'); }

$idUser = (int)($_SESSION['id_usuario'] ?? 0);

// 1) Determinar la sesión de caja a cerrar
$idSesion = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idSesion <= 0) {
  // Si no viene por GET, intento tomar mi sesión abierta
  $rs = pg_query_params($conn, "
    SELECT cs.id_caja_sesion
      FROM public.caja_sesion cs
     WHERE cs.id_usuario = $1 AND cs.estado='Abierta'
     LIMIT 1
  ", [$idUser]);
  if ($rs && pg_num_rows($rs)>0) {
    $idSesion = (int)pg_fetch_result($rs,0,0);
  }
}

// Si sigue sin sesión, mandar a abrir
if ($idSesion <= 0) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_abrir.php');
  exit;
}

// 2) Traer datos de la sesión + caja
$ri = pg_query_params($conn, "
  SELECT cs.id_caja_sesion, cs.id_caja, cs.fecha_apertura, c.nombre AS caja_nombre
    FROM public.caja_sesion cs
    JOIN public.caja c ON c.id_caja = cs.id_caja
   WHERE cs.id_caja_sesion = $1
   LIMIT 1
", [$idSesion]);
$ses = $ri && pg_num_rows($ri)>0 ? pg_fetch_assoc($ri) : null;
if (!$ses) { header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_abrir.php'); exit; }

// 3) Totales teóricos por medio
$rt = pg_query_params($conn, "
  SELECT COALESCE(efectivo,0) AS efectivo,
         COALESCE(tarjeta,0) AS tarjeta,
         COALESCE(transferencia,0) AS transferencia,
         COALESCE(otros,0) AS otros
    FROM public.v_caja_saldos_teoricos
   WHERE id_caja_sesion = $1
", [$idSesion]);
$tot = ['efectivo'=>0,'tarjeta'=>0,'transferencia'=>0,'otros'=>0];
if ($rt && pg_num_rows($rt)>0) { $tot = pg_fetch_assoc($rt); }

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Cierre de Caja — <?= e($ses['caja_nombre']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{ --text:#111827; --muted:#6b7280; --em:#2563eb; --danger:#b91c1c; --ok:#166534; --warn:#9a6700; }
  body{ margin:0; color:var(--text); font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto; background:#fff; }
  .container{ max-width:900px; margin:24px auto; padding:0 14px; }
  .head{ display:flex; justify-content:space-between; align-items:center; margin:10px 0 14px; }
  .head h1{ margin:0; font-size:22px; }
  .muted{ color:var(--muted); }
  .grid{ display:grid; gap:12px; }
  @media(min-width:900px){ .grid.cols-2{ grid-template-columns: 1fr 1fr; } }
  .card{ border:1px solid #e5e7eb; border-radius:12px; padding:14px; }
  table{ width:100%; border-collapse:collapse; }
  th,td{ padding:10px; border-bottom:1px solid #f1f5f9; text-align:left; }
  th{ background:#f8fafc; }
  .right{ text-align:right; }
  input[type="number"], textarea{
    width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px;
  }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
  .btn{ display:inline-block; padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; text-decoration:none; color:#111; cursor:pointer; }
  .btn.primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
  .btn.ghost{ background:#fff; }
  .badge{ display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid #e5e7eb; }
  .ok{ color:#166534; }
  .err{ color:#b91c1c; }
  .muted-small{ color:var(--muted); font-size:12px; }
</style>
</head>
<body>
<div id="navbar-container" class="no-print"></div>

<div class="container">
  <div class="head">
    <div>
      <h1>Cierre de Caja — <?= e($ses['caja_nombre']) ?></h1>
      <div class="muted">Apertura: <strong><?= e($ses['fecha_apertura']) ?></strong> · Usuario: <strong><?= e($_SESSION['nombre_usuario'] ?? ('#'.$idUser)) ?></strong></div>
    </div>
    <div class="actions">
      <a href="ui_panel.php" class="btn ghost">Volver al Panel</a>
    </div>
  </div>

  <div class="card">
    <h3 style="margin:0 0 8px;">Arqueo</h3>
    <p class="muted-small">Ingresá los montos contados por medio de pago. Abajo verás la diferencia vs. total teórico.</p>

    <table>
      <thead>
        <tr>
          <th>Medio</th>
          <th class="right">Teórico (Gs)</th>
          <th class="right">Contado (Gs)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Efectivo</td>
          <td class="right" id="tEf"><?= n($tot['efectivo'] ?? 0, 0) ?></td>
          <td class="right"><input type="number" id="cEf" min="0" step="1" value="0"></td>
        </tr>
        <tr>
          <td>Tarjeta</td>
          <td class="right" id="tTa"><?= n($tot['tarjeta'] ?? 0, 0) ?></td>
          <td class="right"><input type="number" id="cTa" min="0" step="1" value="0"></td>
        </tr>
        <tr>
          <td>Transferencia</td>
          <td class="right" id="tTr"><?= n($tot['transferencia'] ?? 0, 0) ?></td>
          <td class="right"><input type="number" id="cTr" min="0" step="1" value="0"></td>
        </tr>
        <tr>
          <td>Otros</td>
          <td class="right" id="tOt"><?= n($tot['otros'] ?? 0, 0) ?></td>
          <td class="right"><input type="number" id="cOt" min="0" step="1" value="0"></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td class="right"><strong>Total</strong></td>
          <td class="right"><strong id="tTot">0</strong></td>
          <td class="right"><strong id="cTot">0</strong></td>
        </tr>
        <tr>
          <td class="right"><strong>Diferencia (Contado − Teórico)</strong></td>
          <td class="right" colspan="2">
            <strong id="diff">0</strong> <span id="diffFlag" class="muted-small"></span>
          </td>
        </tr>
      </tfoot>
    </table>

    <div style="margin-top:12px;">
      <label for="obs" style="display:block; font-weight:600; margin-bottom:6px;">Observación</label>
      <textarea id="obs" rows="3" placeholder="Notas del cierre (opcional)"></textarea>
    </div>

    <div class="actions">
      <button class="btn primary" id="btnCerrar">Confirmar Cierre</button>
      <a class="btn ghost" href="ui_panel.php">Cancelar</a>
      <span id="msg" class="muted-small"></span>
    </div>
  </div>
</div>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
<script>
(function(){
  const $ = (q)=>document.querySelector(q);
  const fmt = (n)=> new Intl.NumberFormat('es-PY').format(n||0);

  const tEf = <?= (float)($tot['efectivo'] ?? 0) ?>;
  const tTa = <?= (float)($tot['tarjeta'] ?? 0) ?>;
  const tTr = <?= (float)($tot['transferencia'] ?? 0) ?>;
  const tOt = <?= (float)($tot['otros'] ?? 0) ?>;
  const idSesion = <?= (int)$idSesion ?>;

  const $cEf = $("#cEf"), $cTa=$("#cTa"), $cTr=$("#cTr"), $cOt=$("#cOt");
  const $tTot=$("#tTot"), $cTot=$("#cTot"), $diff=$("#diff"), $flag=$("#diffFlag");
  const $msg = $("#msg"), $btn = $("#btnCerrar");

  function recompute(){
    const cEf = parseFloat($cEf.value||0), cTa=parseFloat($cTa.value||0), cTr=parseFloat($cTr.value||0), cOt=parseFloat($cOt.value||0);
    const tTotal = tEf + tTa + tTr + tOt;
    const cTotal = cEf + cTa + cTr + cOt;
    const diff = cTotal - tTotal;

    $tTot.textContent = fmt(tTotal);
    $cTot.textContent = fmt(cTotal);
    $diff.textContent = fmt(diff);

    $flag.textContent = diff===0 ? ' (Cuadra)' : (diff>0 ? ' (Sobrante)' : ' (Faltante)');
    $flag.className = diff===0 ? 'ok' : (diff>0 ? 'ok' : 'err');
  }

  ["input","change"].forEach(ev=>{
    $cEf.addEventListener(ev,recompute);
    $cTa.addEventListener(ev,recompute);
    $cTr.addEventListener(ev,recompute);
    $cOt.addEventListener(ev,recompute);
  });
  recompute();

  async function cerrar(){
    $msg.textContent = "Cerrando sesión...";
    $msg.className = "muted-small";

    const payload = {
      conteo_efectivo: parseFloat($cEf.value||0),
      conteo_tarjeta: parseFloat($cTa.value||0),
      conteo_transferencia: parseFloat($cTr.value||0),
      conteo_otros: parseFloat($cOt.value||0),
      observacion: ($("#obs").value||"").trim()
    };

    try{
      // Llama a tu endpoint de cierre (espera ?id= y body JSON)
      const res = await fetch('caja_sesion_cerrar.php?id='+idSesion, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      const js = await res.json();
      if(!js.ok){
        $msg.textContent = js.error || "No se pudo cerrar la caja.";
        $msg.className = "err";
        return;
      }
      $msg.textContent = "Caja cerrada correctamente.";
      $msg.className = "ok";
      // Redirige a abrir (para empezar una nueva) o a listado
      setTimeout(()=> location.href = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_abrir.php', 800);
    }catch(e){
      console.error(e);
      $msg.textContent = "Error de conexión.";
      $msg.className = "err";
    }
  }

  $("#btnCerrar").addEventListener('click', (ev)=>{ ev.preventDefault(); cerrar(); });
})();
</script>
</body>
</html>
