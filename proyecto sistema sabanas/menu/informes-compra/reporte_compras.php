<?php
// menu/informes-compra/reporte_compras.php
session_start();

if (!isset($_SESSION['nombre_usuario'])) {
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}

require_once __DIR__ . '/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x,$d=0){ return number_format((float)$x, $d, ',', '.'); }

/**
 * Devuelve la primera columna existente en una tabla dada una lista de candidatos
 * Ej: proveedores: ['razon_social','nombre','nombre_fantasia','denominacion']
 */
function firstExistingColumn($conn, $schema, $table, array $candidates){
    $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema=$1 AND table_name=$2";
    $r = pg_query_params($conn, $sql, [$schema, $table]);
    if (!$r) return null;
    $cols = [];
    while($row = pg_fetch_assoc($r)){
        $cols[strtolower($row['column_name'])] = true;
    }
    foreach($candidates as $c){
        if (isset($cols[strtolower($c)])) return $c;
    }
    return null;
}

/* ===== Detectar columnas “nombre” ===== */
$provNameCol = firstExistingColumn($conn, 'public', 'proveedores', ['razon_social','nombre','nombre_fantasia','denominacion']);
if (!$provNameCol) $provNameCol = 'id_proveedor'; // fallback

$sucNameCol = firstExistingColumn($conn, 'public', 'sucursales', ['descripcion','nombre','sucursal','denominacion','alias']);
if (!$sucNameCol) $sucNameCol = 'id_sucursal'; // fallback

/* ===== Cargar combos ===== */
$proveedores = [];
$rp = pg_query($conn, "SELECT id_proveedor, $provNameCol AS proveedor FROM public.proveedores ORDER BY 2");
if ($rp) {
    while($row = pg_fetch_assoc($rp)) $proveedores[] = $row;
}

$sucursales = [];
$rs = pg_query($conn, "SELECT id_sucursal, $sucNameCol AS sucursal FROM public.sucursales ORDER BY 2");
if ($rs) {
    while($row = pg_fetch_assoc($rs)) $sucursales[] = $row;
}

/* ===== Filtros (GET) ===== */
$fd     = $_GET['fecha_desde'] ?? date('Y-m-01');
$fh     = $_GET['fecha_hasta'] ?? date('Y-m-d');
$idProv = isset($_GET['id_proveedor']) ? (int)$_GET['id_proveedor'] : 0;
$idSuc  = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : 0;
$estado = trim($_GET['estado'] ?? '');          // 'Registrada', 'Anulada', etc
$cond   = trim($_GET['condicion'] ?? '');       // 'CONTADO' | 'CREDITO'
$numdoc = trim($_GET['numero_documento'] ?? ''); // búsqueda textual

/* ===== Query principal ===== */
$params = [];
$wheres = [];

$params[] = $fd;  $wheres[] = "f.fecha_emision >= $".count($params);
$params[] = $fh;  $wheres[] = "f.fecha_emision <= $".count($params);

if ($idProv > 0) { $params[] = $idProv; $wheres[] = "f.id_proveedor = $".count($params); }
if ($idSuc  > 0) { $params[] = $idSuc;  $wheres[] = "f.id_sucursal = $".count($params); }
if ($estado !== '') {
    $params[] = $estado; $wheres[] = "LOWER(f.estado)=LOWER($".count($params).")";
}
if ($cond !== '') {
    $params[] = $cond;   $wheres[] = "LOWER(f.condicion)=LOWER($".count($params).")";
}
if ($numdoc !== '') {
    $params[] = '%'.$numdoc.'%'; $wheres[] = "f.numero_documento ILIKE $".count($params);
}

$whereSql = $wheres ? ('WHERE '.implode(' AND ',$wheres)) : '';

$sql = "
SELECT
  f.id_factura,
  f.fecha_emision,
  f.numero_documento,
  f.condicion,
  f.estado,
  COALESCE(f.total_factura,0) AS total_factura,
  p.$provNameCol AS proveedor,
  s.$sucNameCol  AS sucursal
FROM public.factura_compra_cab f
JOIN public.proveedores p ON p.id_proveedor = f.id_proveedor
LEFT JOIN public.sucursales s ON s.id_sucursal = f.id_sucursal
$whereSql
ORDER BY f.fecha_emision DESC, f.id_factura DESC
LIMIT 500
";
$res = pg_query_params($conn, $sql, $params);

/* Totales */
$total_importe = 0.0; $cantidad = 0;
$rows = [];
if ($res) {
    while($r = pg_fetch_assoc($res)){
        $rows[] = $r;
        $cantidad++;
        $total_importe += (float)$r['total_factura'];
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Informe — (Compras por período)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
  --bg: #f6f7fb;
  --surface: #fff;
  --text: #1f2937;
  --muted:#6b7280;
  --primary:#0d6efd;
  --ok:#166534;
  --warn:#9a6700;
  --danger:#b91c1c;
  --border:#e5e7eb;
  --radius: 14px;
}
*{ box-sizing: border-box; }
body{
  margin:0; padding:22px 16px 40px;
  background: var(--bg);
  font: 14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;
  color: var(--text);
}
.container{
  width: min(1200px, 100%);
  margin: 0 auto;
}
header.head{
  display:flex; justify-content:space-between; align-items:center; gap:16px;
  margin-bottom: 16px;
}
header.head h1{
  margin:0; font-size: 1.4rem; letter-spacing:.2px;
}
.actions{
  display:flex; gap:8px; flex-wrap:wrap;
}
.btn{
  appearance:none; border:1px solid var(--border); background:#fff; color:var(--text);
  padding:8px 12px; border-radius: 10px; cursor:pointer; font-weight:600;
  box-shadow: 0 1px 2px rgba(0,0,0,.04);
  transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
}
.btn:hover{ transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,.08); }
.btn.primary{ background: var(--primary); color:#fff; border-color: var(--primary); }
.btn.secondary{ background:#fff; }
.card{
  background: var(--surface);
  border:1px solid var(--border);
  border-radius: var(--radius);
  padding:14px;
  box-shadow: 0 8px 24px rgba(0,0,0,.05);
}

/* Filtros */
.filters{
  display:grid; gap:12px; grid-template-columns: repeat(6, minmax(0,1fr));
  align-items:end; margin-bottom: 14px;
}
.filters label{ display:block; font-size:.85rem; color:var(--muted); margin:0 0 4px; }
.filters input, .filters select{
  width:100%; padding:9px 10px; border-radius:10px; border:1px solid var(--border); background:#fff;
  font-size:.95rem;
}
.filters .col-span-2{ grid-column: span 2 / span 2; }
.filters .col-span-3{ grid-column: span 3 / span 3; }

/* Tabla */
.table-wrap{ margin-top: 10px; overflow:auto; }
table{ width:100%; border-collapse: collapse; }
th, td{ padding:10px 12px; border-bottom:1px solid var(--border); text-align:left; background:#fff; }
th{ font-weight:700; font-size:.86rem; color:#334155; background:#f8fafc; }
tr:hover td{ background:#fafafa; }
.right{ text-align:right; }
.muted{ color:var(--muted); }

/* Resumen */
.summary{
  display:flex; gap:16px; flex-wrap:wrap; margin-top: 14px;
}
.badge{
  display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px;
  background:#f8fafc; border:1px solid var(--border); font-weight:600; color:#111827; font-size:.92rem;
}
.badge .dot{ width:8px; height:8px; border-radius:50%; background: var(--primary); display:inline-block; }

/* Print */
@media print{
  body{ background:#fff; padding:0; }
  .no-print{ display:none !important; }
  .card{ border:none; box-shadow:none; padding:0; }
  header.head{ margin-bottom: 6px; }
  @page{ size: A4 landscape; margin: 10mm; }
}

/* Responsive */
@media (max-width: 980px){
  .filters{ grid-template-columns: repeat(3, 1fr); }
  .filters .col-span-2{ grid-column: span 1 / span 1; }
  .filters .col-span-3{ grid-column: span 1 / span 1; }
}
@media (max-width: 560px){
  .filters{ grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="container">

  <header class="head">
    <h1>Informe de Compras por Período</h1>
    <div class="actions no-print">
      <!-- Botón Volver (solo JS del navegador) -->
      <button class="btn secondary" type="button" onclick="history.back()">Volver</button>
      <button class="btn secondary" onclick="location.href=location.pathname">Limpiar</button>
      <button class="btn primary" onclick="window.print()">Imprimir</button>
    </div>
  </header>

  <section class="card no-print">
    <form class="filters" method="get">
      <div>
        <label>Fecha desde</label>
        <input type="date" name="fecha_desde" value="<?= e($fd) ?>">
      </div>
      <div>
        <label>Fecha hasta</label>
        <input type="date" name="fecha_hasta" value="<?= e($fh) ?>">
      </div>
      <div class="col-span-2">
        <label>Proveedor</label>
        <select name="id_proveedor">
          <option value="0">— Todos —</option>
          <?php foreach($proveedores as $p): ?>
            <option value="<?= (int)$p['id_proveedor'] ?>" <?= $idProv===(int)$p['id_proveedor']?'selected':'' ?>>
              <?= e($p['proveedor']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Sucursal</label>
        <select name="id_sucursal">
          <option value="0">— Todas —</option>
          <?php foreach($sucursales as $s): ?>
            <option value="<?= (int)$s['id_sucursal'] ?>" <?= $idSuc===(int)$s['id_sucursal']?'selected':'' ?>>
              <?= e($s['sucursal']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Condición</label>
        <select name="condicion">
          <option value="">— Todas —</option>
          <option value="CONTADO" <?= $cond==='CONTADO'?'selected':'' ?>>CONTADO</option>
          <option value="CREDITO" <?= $cond==='CREDITO'?'selected':'' ?>>CRÉDITO</option>
        </select>
      </div>
      <div>
        <label>Estado</label>
        <select name="estado">
          <option value="">— Todos —</option>
          <option value="Registrada" <?= $estado==='Registrada'?'selected':'' ?>>Registrada</option>
          <option value="Anulada" <?= $estado==='Anulada'?'selected':'' ?>>Anulada</option>
        </select>
      </div>
      <div class="col-span-3">
        <label>N° Documento (contiene)</label>
        <input type="text" name="numero_documento" value="<?= e($numdoc) ?>" placeholder="Ej.: 001-002-0001234">
      </div>
      <div>
        <label>&nbsp;</label>
        <button class="btn primary" type="submit">Buscar</button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>N° Documento</th>
            <th>Proveedor</th>
            <th>Sucursal</th>
            <th>Condición</th>
            <th>Estado</th>
            <th class="right">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="muted">Sin resultados para los filtros aplicados.</td></tr>
          <?php else: ?>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?= e($r['fecha_emision']) ?></td>
                <td><?= e($r['numero_documento']) ?></td>
                <td><?= e($r['proveedor'] ?? '') ?></td>
                <td><?= e($r['sucursal'] ?? '') ?></td>
                <td><?= e($r['condicion'] ?? '') ?></td>
                <td><?= e($r['estado'] ?? '') ?></td>
                <td class="right"><?= n($r['total_factura'], 0) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
          <tr>
            <th colspan="6" class="right">TOTAL</th>
            <th class="right"><?= n($total_importe, 0) ?></th>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>

    <div class="summary">
      <span class="badge"><span class="dot"></span> Registros: <?= (int)$cantidad ?></span>
      <span class="badge"><span class="dot" style="background:var(--ok)"></span> Importe total: <?= n($total_importe,0) ?></span>
      <span class="badge"><span class="dot" style="background:var(--warn)"></span> Período: <?= e($fd) ?> → <?= e($fh) ?></span>
    </div>
  </section>

  <p class="muted" style="margin-top:10px">* Generado por <?= e($_SESSION['nombre_usuario'] ?? 'usuario') ?> · <?= date('Y-m-d H:i') ?></p>
</div>
</body>
</html>
