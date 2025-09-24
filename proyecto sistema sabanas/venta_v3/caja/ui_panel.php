<?php
// /caja/ui_panel.php — UI: Panel del turno de Caja (rutas relativas)
session_start();
if (empty($_SESSION['id_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x,$d=0){ return number_format((float)$x,$d,',','.'); }

$idUser = (int)$_SESSION['id_usuario'];

// 1) Traer mi sesión abierta
$sqlSesion = "
  SELECT cs.id_caja_sesion, cs.id_caja, cs.fecha_apertura, c.nombre AS caja_nombre
  FROM public.caja_sesion cs
  JOIN public.caja c ON c.id_caja = cs.id_caja
  WHERE cs.id_usuario = $1 AND cs.estado = 'Abierta'
  LIMIT 1
";
$rs = pg_query_params($conn, $sqlSesion, [$idUser]);
$ses = $rs && pg_num_rows($rs)>0 ? pg_fetch_assoc($rs) : null;

if (!$ses) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_abrir.php');
  exit;
}

$idSesion = (int)$ses['id_caja_sesion'];

// 2) Totales teóricos
$sqlTot = "
  SELECT COALESCE(efectivo,0) AS efectivo,
         COALESCE(tarjeta,0) AS tarjeta,
         COALESCE(transferencia,0) AS transferencia,
         COALESCE(otros,0) AS otros
  FROM public.v_caja_saldos_teoricos
  WHERE id_caja_sesion = $1
";
$rt = pg_query_params($conn, $sqlTot, [$idSesion]);
$tot = ['efectivo'=>0,'tarjeta'=>0,'transferencia'=>0,'otros'=>0];
if ($rt && pg_num_rows($rt)>0) $tot = pg_fetch_assoc($rt);

// 3) Últimos movimientos
$sqlMovs = "
  SELECT id_movimiento, fecha, tipo, origen, medio, monto, descripcion
  FROM public.movimiento_caja
  WHERE id_caja_sesion = $1
  ORDER BY fecha DESC
  LIMIT 10
";
$rm = pg_query_params($conn, $sqlMovs, [$idSesion]);
$movs = [];
if ($rm) while($x=pg_fetch_assoc($rm)) $movs[]=$x;

// 4) Contador de movimientos
$sqlCount = "SELECT COUNT(*) FROM public.movimiento_caja WHERE id_caja_sesion = $1";
$rc = pg_query_params($conn, $sqlCount, [$idSesion]);
$totalMovs = $rc ? (int)pg_fetch_result($rc,0,0) : 0;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Panel de Caja — <?= e($ses['caja_nombre']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{ --text:#111827; --muted:#6b7280; --em:#2563eb; --danger:#b91c1c; --ok:#166534; --warn:#9a6700; }
  body{ margin:0; color:var(--text); font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto; background:#fff; }
  .container{ max-width:1100px; margin:24px auto; padding:0 14px; }
  .head{ display:flex; justify-content:space-between; align-items:center; margin:10px 0 14px; }
  .head h1{ margin:0; font-size:22px; }
  .muted{ color:var(--muted); }
  .grid{ display:grid; gap:12px; }
  @media(min-width:900px){ .grid.cols-3{ grid-template-columns: 1fr 1fr 1fr; } .grid.cols-2{ grid-template-columns: 1fr 1fr; } }
  .card{ border:1px solid #e5e7eb; border-radius:12px; padding:14px; }
  .kpi{ display:flex; justify-content:space-between; align-items:flex-end; }
  .kpi h3{ margin:0 0 6px; font-size:13px; color:var(--muted); }
  .kpi strong{ font-size:20px; }
  .list{ border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; }
  .row{ display:flex; gap:12px; align-items:center; padding:10px 12px; border-top:1px solid #f1f5f9; }
  .row:first-child{ border-top:none; }
  .row .grow{ flex:1; }
  .badge{ display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid #e5e7eb; }
  .badge.ing{ color:#166534; background:#f0fdf4; border-color:#bbf7d0; }
  .badge.egr{ color:#9a6700; background:#fef9c3; border-color:#fde68a; }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; }
  .btn{ display:inline-block; padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; text-decoration:none; color:#111; cursor:pointer; }
  .btn.primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
  .btn.danger{ background:#b91c1c; color:#fff; border-color:#b91c1c; }
  label{ display:block; font-weight:600; margin-bottom:6px; }
  input, select{ width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
  .help{ font-size:12px; color:var(--muted); margin-top:6px; }
  .success{ color:#166534; }
  .error{ color:#b91c1c; }
  .right{ text-align:right; }
</style>
</head>
<body>
<div id="navbar-container" class="no-print"></div>

<div class="container">
  <div class="head">
    <div>
      <h1>Panel de Caja — <?= e($ses['caja_nombre']) ?></h1>
      <div class="muted">Apertura: <strong><?= e($ses['fecha_apertura']) ?></strong> · Usuario: <strong><?= e($_SESSION['nombre_usuario'] ?? ('#'.$idUser)) ?></strong></div>
    </div>
    <div class="actions">
      <a href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_cierre.php" class="btn danger">Arqueo & Cerrar</a>
      <a href="abrir.php" class="btn">Cambiar Caja</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="grid cols-4" style="display:grid; grid-template-columns: repeat(4,1fr); gap:12px;">
    <div class="card kpi"><div><h3>Efectivo</h3><strong>Gs <?= n($tot['efectivo'],0) ?></strong></div></div>
    <div class="card kpi"><div><h3>Tarjeta</h3><strong>Gs <?= n($tot['tarjeta'],0) ?></strong></div></div>
    <div class="card kpi"><div><h3>Transferencia</h3><strong>Gs <?= n($tot['transferencia'],0) ?></strong></div></div>
    <div class="card kpi"><div><h3>Otros</h3><strong>Gs <?= n($tot['otros'],0) ?></strong></div></div>
  </div>

  <div class="grid cols-2" style="margin-top:12px;">
    <!-- Movimiento rápido -->
    <div class="card">
      <h3 style="margin:0 0 10px;">Movimiento rápido</h3>
      <form id="formMov" onsubmit="return false;">
        <div class="grid cols-2">
          <div>
            <label for="tipo">Tipo</label>
            <select id="tipo" required>
              <option value="Ingreso">Ingreso</option>
              <option value="Egreso">Egreso</option>
            </select>
          </div>
          <div>
            <label for="origen">Origen</label>
            <select id="origen" required>
              <option value="Venta">Venta</option>
              <option value="Retiro">Retiro</option>
              <option value="Gasto">Gasto</option>
              <option value="Ajuste">Ajuste</option>
              <option value="Deposito">Deposito</option>
            </select>
          </div>
        </div>

        <div class="grid cols-2" style="margin-top:10px;">
          <div>
            <label for="medio">Medio de pago</label>
            <select id="medio" required>
              <option value="Efectivo">Efectivo</option>
              <option value="Tarjeta">Tarjeta</option>
              <option value="Transferencia">Transferencia</option>
              <option value="Cheque">Cheque</option>
              <option value="Credito">Crédito</option>
              <option value="Otros">Otros</option>
            </select>
          </div>
          <div>
            <label for="monto">Monto (Gs)</label>
            <input type="number" id="monto" min="0" step="1" placeholder="0" required>
          </div>
        </div>

        <div style="margin-top:10px;">
          <label for="descripcion">Descripción</label>
          <input type="text" id="descripcion" maxlength="140" placeholder="Detalle / referencia (opcional)">
        </div>

        <div class="actions" style="margin-top:12px;">
          <button class="btn primary" id="btnMov">Registrar movimiento</button>
          <span id="msgMov" class="help"></span>
        </div>
      </form>
    </div>

    <!-- Últimos movimientos -->
    <div class="card">
      <h3 style="margin:0 0 10px;">Últimos movimientos (<?= (int)$totalMovs ?>)</h3>
      <div class="list">
        <?php if(empty($movs)): ?>
          <div class="row"><div class="grow muted">Sin movimientos todavía.</div></div>
        <?php else: foreach($movs as $m): ?>
          <div class="row">
            <div class="grow">
              <div><strong><?= e($m['origen']) ?></strong> · <small class="muted"><?= e($m['medio']) ?></small></div>
              <div class="muted"><?= e($m['descripcion'] ?? '') ?></div>
              <div class="muted" style="font-size:12px;"><?= e($m['fecha']) ?></div>
            </div>
            <div class="right">
              <span class="badge <?= $m['tipo']==='Ingreso'?'ing':'egr' ?>"><?= e($m['tipo']) ?></span><br>
              <strong>Gs <?= n($m['monto'],0) ?></strong>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <div class="actions" style="margin-top:10px;">
        <a class="btn" href="movimientos.php">Ver todos</a>
      </div>
    </div>
  </div>

  <!-- NC pendientes -->
  <div class="card" style="margin-top:16px;">
    <h3 style="margin:0 0 10px;">Notas de Crédito Pendientes</h3>
    <div id="ncPendientes" class="list"></div>
    <div style="margin-top:10px; display:flex; gap:8px;">
      <input type="text" id="buscarNC" placeholder="Buscar por cliente o número..." style="flex:1; padding:8px;">
      <button class="btn" id="btnBuscarNC">Buscar</button>
    </div>
  </div>
</div>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
<script>
(async function(){
  const $ = q => document.querySelector(q);

  async function enviarMovimiento(){
    const msg = $("#msgMov");
    msg.textContent = "Guardando...";
    msg.className = "help";
    const payload = {
      id_caja_sesion: <?= (int)$idSesion ?>,
      tipo: $("#tipo").value,
      origen: $("#origen").value,
      medio: $("#medio").value,
      monto: parseFloat($("#monto").value||0),
      descripcion: $("#descripcion").value||""
    };
    try{
      const res = await fetch('movimiento_caja_crear.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      const js = await res.json();
      if(!js.ok){
        msg.textContent = js.error || "No se pudo registrar.";
        msg.className = "error";
        return;
      }
      msg.textContent = "Movimiento registrado.";
      msg.className = "success";
      setTimeout(()=> location.reload(), 600);
    }catch(e){
      console.error(e);
      msg.textContent = "Error de conexión.";
      msg.className = "error";
    }
  }
  document.getElementById('btnMov').addEventListener('click', e=>{ e.preventDefault(); enviarMovimiento(); });

  async function cargarNCPendientes(filtro=""){
    const res = await fetch('../../venta_v3/notas/notas_pendientes.php?filtro='+encodeURIComponent(filtro));
    const js = await res.json();
    const cont = document.getElementById('ncPendientes');
    cont.innerHTML = "";
    if(!js.ok || !js.data.length){
      cont.innerHTML = '<div class="row"><div class="grow muted">Sin NC pendientes.</div></div>';
      return;
    }
    js.data.forEach(n=>{
      const div = document.createElement('div');
      div.className="row";
      div.innerHTML = `
        <div class="grow">
          <strong>${n.numero_documento}</strong> — ${n.cliente}<br>
          <small class="muted">Total: Gs ${new Intl.NumberFormat('es-PY').format(n.total_neto)}</small><br>
          <select class="medio-nc">
            <option value="Efectivo">Efectivo</option>
            <option value="Transferencia">Transferencia</option>
            <option value="Cheque">Cheque</option>
          </select>
        </div>
        <div>
          <button class="btn primary" onclick="generarEgreso(${n.id_nc},${n.total_neto},this)">Egreso</button>
        </div>
      `;
      cont.appendChild(div);
    });
  }

  window.generarEgreso = async function(id_nc, importe, btn){
    const medio = btn.parentElement.parentElement.querySelector('.medio-nc').value;
    if(!confirm("¿Generar egreso de caja por esta NC?")) return;
    const res = await fetch('../../venta_v3/notas/nota_cobrar_egreso.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({id_nc:id_nc, medio:medio, importe:importe})
    });
    const js = await res.json();
    if(js.ok){
      alert("Egreso generado correctamente.");
      cargarNCPendientes();
    }else{
      alert("Error: "+js.error);
    }
  }

  document.getElementById('btnBuscarNC').addEventListener('click',()=> {
    cargarNCPendientes(document.getElementById('buscarNC').value);
  });

  cargarNCPendientes();
})();
</script>
</body>
</html>
