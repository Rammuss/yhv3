<?php
// /caja/movimientos.php — Listado con filtros + paginación (rutas relativas)
session_start();
if (empty($_SESSION['id_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x,$d=0){ return number_format((float)$x,$d,',','.'); }

$idUser = (int)$_SESSION['id_usuario'];

// 1) Determinar sesión a consultar
$idSesion = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idSesion <= 0) {
  $rs = pg_query_params($conn, "
    SELECT cs.id_caja_sesion
      FROM public.caja_sesion cs
     WHERE cs.id_usuario = $1 AND cs.estado='Abierta'
     LIMIT 1
  ", [$idUser]);
  if ($rs && pg_num_rows($rs)>0) { $idSesion = (int)pg_fetch_result($rs,0,0); }
}

// Si no hay sesión (ni abierta ni pasada por id), enviar a abrir
if ($idSesion <= 0) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_abrir.php');
  exit;
}

// 2) Traer info de la sesión
$ri = pg_query_params($conn, "
  SELECT cs.id_caja_sesion, cs.fecha_apertura, cs.estado, c.nombre AS caja_nombre
    FROM public.caja_sesion cs
    JOIN public.caja c ON c.id_caja = cs.id_caja
   WHERE cs.id_caja_sesion = $1
   LIMIT 1
", [$idSesion]);
$ses = $ri && pg_num_rows($ri)>0 ? pg_fetch_assoc($ri) : null;
if (!$ses) { header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_abrir.php'); exit; }

// 3) Filtros
$tipo    = trim($_GET['tipo']    ?? '');  // Ingreso|Egreso
$medio   = trim($_GET['medio']   ?? '');  // Efectivo|Tarjeta|...
$origen  = trim($_GET['origen']  ?? '');  // Venta|Retiro|...
$q       = trim($_GET['q']       ?? '');  // texto en descripción
$desde   = trim($_GET['desde']   ?? '');  // YYYY-MM-DD
$hasta   = trim($_GET['hasta']   ?? '');  // YYYY-MM-DD
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 20;
$offset  = ($page-1) * $limit;

$where = ["id_caja_sesion = $1"];
$params = [$idSesion];
$pi = 2;

if ($tipo !== '')  { $where[] = "tipo = $".$pi;   $params[] = $tipo;   $pi++; }
if ($medio !== '') { $where[] = "medio = $".$pi;  $params[] = $medio;  $pi++; }
if ($origen !== ''){ $where[] = "origen = $".$pi; $params[] = $origen; $pi++; }
if ($q !== '')     { $where[] = "unaccent(lower(coalesce(descripcion,''))) LIKE unaccent(lower($".$pi."))"; $params[]='%'.$q.'%'; $pi++; }
if ($desde !== '') { $where[] = "fecha >= $".$pi; $params[] = $desde.' 00:00:00'; $pi++; }
if ($hasta !== '') { $where[] = "fecha <= $".$pi; $params[] = $hasta.' 23:59:59'; $pi++; }

$wSql = implode(' AND ', $where);

// 4) Totales y conteo
$sqlAgg = "
  SELECT
    COUNT(*)::int                                                AS total_rows,
    COALESCE(SUM(CASE WHEN tipo='Ingreso' THEN monto ELSE 0 END),0) AS total_ingresos,
    COALESCE(SUM(CASE WHEN tipo='Egreso'  THEN monto ELSE 0 END),0) AS total_egresos
  FROM public.movimiento_caja
  WHERE $wSql
";
$ra = pg_query_params($conn, $sqlAgg, $params);
$agg = $ra ? pg_fetch_assoc($ra) : ['total_rows'=>0,'total_ingresos'=>0,'total_egresos'=>0];
$totalRows = (int)($agg['total_rows'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $limit));

// 5) Listado paginado
$sqlList = "
  SELECT id_movimiento, fecha, tipo, origen, medio, monto, descripcion
    FROM public.movimiento_caja
   WHERE $wSql
   ORDER BY fecha DESC, id_movimiento DESC
   LIMIT $limit OFFSET $offset
";
$rl = pg_query_params($conn, $sqlList, $params);
$rows = [];
if ($rl) { while($x=pg_fetch_assoc($rl)) $rows[]=$x; }

// Helpers para select
$tipos   = [''=>'(Todos)','Ingreso'=>'Ingreso','Egreso'=>'Egreso'];
$medios  = [''=>'(Todos)','Efectivo'=>'Efectivo','Tarjeta'=>'Tarjeta','Transferencia'=>'Transferencia','Cheque'=>'Cheque','Credito'=>'Crédito','Otros'=>'Otros'];
$origenes= [''=>'(Todos)','Venta'=>'Venta','Retiro'=>'Retiro','Gasto'=>'Gasto','Ajuste'=>'Ajuste','Deposito'=>'Deposito'];

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Movimientos — <?= e($ses['caja_nombre']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{ --text:#111827; --muted:#6b7280; --em:#2563eb; --danger:#b91c1c; --ok:#166534; --warn:#9a6700; }
  body{ margin:0; color:var(--text); font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto; background:#fff; }
  .container{ max-width:1100px; margin:24px auto; padding:0 14px; }
  .head{ display:flex; justify-content:space-between; align-items:center; margin:10px 0 14px; }
  .head h1{ margin:0; font-size:22px; }
  .muted{ color:var(--muted); }
  .card{ border:1px solid #e5e7eb; border-radius:12px; padding:14px; margin-bottom:12px; }
  .grid{ display:grid; gap:12px; }
  @media(min-width:900px){ .grid.cols-4{ grid-template-columns: repeat(4,1fr); } .grid.cols-3{ grid-template-columns: repeat(3,1fr); } }
  label{ display:block; font-weight:600; margin-bottom:6px; }
  input, select{ width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
  table{ width:100%; border-collapse:collapse; }
  th,td{ padding:10px; border-bottom:1px solid #f1f5f9; text-align:left; }
  th{ background:#f8fafc; }
  .right{ text-align:right; }
  .badge{ display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid #e5e7eb; }
  .badge.ing{ color:#166534; background:#f0fdf4; border-color:#bbf7d0; }
  .badge.egr{ color:#9a6700; background:#fef9c3; border-color:#fde68a; }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; }
  .btn{ display:inline-block; padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; text-decoration:none; color:#111; cursor:pointer; }
  .btn.primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
  .btn.ghost{ background:#fff; }
  .pager{ display:flex; gap:6px; align-items:center; justify-content:flex-end; margin-top:8px; }
</style>
</head>
<body>
<div id="navbar-container" class="no-print"></div>

<div class="container">
  <div class="head">
    <div>
      <h1>Movimientos — <?= e($ses['caja_nombre']) ?></h1>
      <div class="muted">Sesión #<?= (int)$ses['id_caja_sesion'] ?> · Apertura: <strong><?= e($ses['fecha_apertura']) ?></strong> · Estado: <strong><?= e($ses['estado']) ?></strong></div>
    </div>
    <div class="actions">
      <a href="ui_panel.php" class="btn">Volver al Panel</a>
      <?php if (strcasecmp($ses['estado'],'Abierta')===0): ?>
        <a href="cierre.php?id=<?= (int)$ses['id_caja_sesion'] ?>" class="btn primary">Arqueo & Cerrar</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Totales -->
  <div class="card">
    <div class="grid cols-3">
      <div><strong>Total Ingresos:</strong> Gs <?= n($agg['total_ingresos'] ?? 0, 0) ?></div>
      <div><strong>Total Egresos:</strong> Gs <?= n($agg['total_egresos'] ?? 0, 0) ?></div>
      <div><strong>Movimientos:</strong> <?= n($totalRows, 0) ?></div>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card">
    <form method="get" class="grid cols-4" style="align-items:end;">
      <input type="hidden" name="id" value="<?= (int)$idSesion ?>">
      <div>
        <label>Tipo</label>
        <select name="tipo">
          <?php foreach($tipos as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= $tipo===$k?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Medio</label>
        <select name="medio">
          <?php foreach($medios as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= $medio===$k?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Origen</label>
        <select name="origen">
          <?php foreach($origenes as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= $origen===$k?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Búsqueda</label>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Descripción...">
      </div>
      <div>
        <label>Desde</label>
        <input type="date" name="desde" value="<?= e($desde) ?>">
      </div>
      <div>
        <label>Hasta</label>
        <input type="date" name="hasta" value="<?= e($hasta) ?>">
      </div>
      <div>
        <button class="btn primary" type="submit">Filtrar</button>
      </div>
      <div>
        <a class="btn ghost" href="movimientos.php?id=<?= (int)$idSesion ?>">Limpiar</a>
      </div>
    </form>
  </div>

  <!-- Tabla -->
  <div class="card">
    <table>
      <thead>
        <tr>
          <th style="width:150px">Fecha</th>
          <th style="width:110px">Tipo</th>
          <th style="width:130px">Medio</th>
          <th style="width:130px">Origen</th>
          <th class="right" style="width:140px">Monto (Gs)</th>
          <th>Descripción</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($rows)): ?>
          <tr><td colspan="6" class="muted">Sin resultados.</td></tr>
        <?php else: ?>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= e($r['fecha']) ?></td>
              <td><span class="badge <?= $r['tipo']==='Ingreso'?'ing':'egr' ?>"><?= e($r['tipo']) ?></span></td>
              <td><?= e($r['medio']) ?></td>
              <td><?= e($r['origen']) ?></td>
              <td class="right"><?= n($r['monto'],0) ?></td>
              <td><?= e($r['descripcion'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Paginación -->
    <div class="pager">
      <?php if($page>1): ?>
        <a class="btn" href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">« Anterior</a>
      <?php endif; ?>
      <span class="muted">Página <?= (int)$page ?> de <?= (int)$totalPages ?></span>
      <?php if($page<$totalPages): ?>
        <a class="btn" href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">Siguiente »</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
</body>
</html>
