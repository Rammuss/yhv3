<?php
// ui_facturas.php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

// Helpers
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$ESTADOS    = ['Emitida','Anulada'];     // ajustá si tenés más
$CONDICION  = ['Contado','Credito'];     // idem

// Filtros
$numero_doc = trim($_GET['numero'] ?? '');                 // N° documento exacto o parcial
$estado     = trim($_GET['estado'] ?? '');                 // Emitida/Anulada
$condicion  = trim($_GET['condicion'] ?? '');              // Contado/Credito
$desde      = trim($_GET['desde'] ?? '');                  // YYYY-MM-DD
$hasta      = trim($_GET['hasta'] ?? '');                  // YYYY-MM-DD
$q          = trim($_GET['q'] ?? '');                      // nombre/apellido/CI-RUC
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 15;
$offset     = ($page - 1) * $per_page;

// WHERE dinámico
$where  = [];
$params = [];

if ($numero_doc !== '') {
  $where[]  = "LOWER(f.numero_documento) LIKE LOWER($".(count($params)+1).")";
  $params[] = "%$numero_doc%";
}
if ($estado !== '' && in_array($estado, $ESTADOS, true)) {
  $where[]  = "f.estado = $".(count($params)+1);
  $params[] = $estado;
}
if ($condicion !== '' && in_array($condicion, $CONDICION, true)) {
  $where[]  = "f.condicion_venta = $".(count($params)+1);
  $params[] = $condicion;
}
if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$desde)) {
  $where[]  = "f.fecha_emision >= $".(count($params)+1)."::date";
  $params[] = $desde;
}
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$hasta)) {
  $where[]  = "f.fecha_emision <= $".(count($params)+1)."::date";
  $params[] = $hasta;
}
if ($q !== '') {
  $where[]  = "(LOWER(c.nombre) LIKE LOWER($".(count($params)+1).")
                OR LOWER(c.apellido) LIKE LOWER($".(count($params)+2).")
                OR LOWER(c.ruc_ci) LIKE LOWER($".(count($params)+3)."))";
  $like = "%$q%";
  array_push($params, $like, $like, $like);
}
$where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

// Total para paginación
$sql_count = "
  SELECT COUNT(*)
  FROM public.factura_venta_cab f
  JOIN public.clientes c ON c.id_cliente = f.id_cliente
  $where_sql
";
$res_count = pg_query_params($conn, $sql_count, $params);
$total_rows = $res_count ? (int)pg_fetch_result($res_count, 0, 0) : 0;
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Query principal
$sql = "
  SELECT
    f.id_factura,
    f.numero_documento,
    f.fecha_emision,
    f.estado,
    f.condicion_venta,
    f.total_bruto,
    f.total_descuento,
    f.total_neto,
    COALESCE(f.id_pedido, NULL) AS id_pedido,
    COALESCE(f.observacion,'') AS observacion,
    c.id_cliente,
    (c.nombre||' '||c.apellido) AS cliente,
    c.ruc_ci
  FROM public.factura_venta_cab f
  JOIN public.clientes c ON c.id_cliente = f.id_cliente
  $where_sql
  ORDER BY f.fecha_emision DESC, f.id_factura DESC
  LIMIT $per_page OFFSET $offset
";
$res = pg_query_params($conn, $sql, $params);

// Helper UI
function badge_estado($estado){
  $st = strtolower($estado);
  if ($st==='emitida') return 'b-fact';
  if ($st==='anulada') return 'b-anul';
  return 'b-pend';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Listado de Facturas</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
:root{
  --bg:#f6f7fb; --surface:#fff; --text:#1f2937; --muted:#6b7280; --primary:#2563eb;
  --danger:#ef4444; --radius:14px; --shadow:0 10px 24px rgba(0,0,0,.08);
}
body{ margin:0; background:var(--bg); color:var(--text); font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto; }
.wrap{max-width:1150px;margin:24px auto;padding:0 16px;}
.card{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;margin-bottom:16px;}
h1{margin:0 0 10px;}
.row{display:flex;gap:12px;flex-wrap:wrap;}
label{display:block;font-size:.9rem;margin-bottom:6px;color:#111827;}
input,select{padding:8px;border:1px solid #e5e7eb;border-radius:10px;width:100%;}
.btn{background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer;text-decoration:none;display:inline-block;}
.btn-danger{background:var(--danger);}
table{width:100%;border-collapse:collapse;margin-top:12px;}
th,td{border-bottom:1px solid #eef2f7;padding:10px;text-align:left;}
th{background:#f8fafc;}
.muted{color:var(--muted);}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:.75rem;}
.b-pend{background:#eef2ff;color:#1e40af;}
.b-fact{background:#ecfdf5;color:#065f46;}
.b-anul{background:#fef2f2;color:#991b1b;}
.right{display:flex;justify-content:flex-end;gap:8px;}
.pager{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:12px;}
.pager a{padding:6px 10px;border-radius:8px;background:#fff;border:1px solid #e5e7eb;text-decoration:none;color:inherit;}
.pager .current{background:#111827;color:#fff;border-color:#111827;}
#toast{position:fixed;right:16px;top:16px;background:#16a34a;color:#fff;padding:12px 14px;border-radius:10px;display:none;z-index:9999;}
</style>
</head>
<body>
<div id="navbar-container"></div>

<div class="wrap">
  <!-- FILTROS -->
  <div class="card">
    <h1>Listado de facturas</h1>
    <form class="row" method="get" autocomplete="off">
      <div style="flex:0 0 180px;">
        <label>N° Documento</label>
        <input type="text" name="numero" value="<?= e($numero_doc) ?>" placeholder="001-001-0000123">
      </div>
      <div style="flex:0 0 150px;">
        <label>Estado</label>
        <select name="estado">
          <option value="">Todos</option>
          <?php foreach ($ESTADOS as $st): ?>
            <option value="<?= e($st) ?>" <?= $estado===$st?'selected':'' ?>><?= e($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:0 0 150px;">
        <label>Condición</label>
        <select name="condicion">
          <option value="">Todas</option>
          <?php foreach ($CONDICION as $cv): ?>
            <option value="<?= e($cv) ?>" <?= $condicion===$cv?'selected':'' ?>><?= e($cv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:0 0 150px;"><label>Desde</label><input type="date" name="desde" value="<?= e($desde) ?>"></div>
      <div style="flex:0 0 150px;"><label>Hasta</label><input type="date" name="hasta" value="<?= e($hasta) ?>"></div>
      <div style="flex:1;min-width:220px;">
        <label>Cliente / CI-RUC</label>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nombre, apellido o CI/RUC">
      </div>
      <div style="align-self:end;"><button class="btn" type="submit">Filtrar</button></div>
    </form>
  </div>

  <!-- RESULTADOS -->
  <div class="card">
    <div class="muted" style="margin-bottom:8px">
      Resultados: <?= e(number_format($total_rows,0,',','.')) ?> facturas — Página <?= e($page) ?>/<?= e($total_pages) ?>
    </div>

    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>N° Documento</th>
            <th>Cliente</th>
            <th>CI/RUC</th>
            <th>Condición</th>
            <th>Estado</th>
            <th>Total Neto</th>
            <th class="right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($res && pg_num_rows($res)>0): ?>
            <?php while($r=pg_fetch_assoc($res)):
              $badge = badge_estado($r['estado']);
            ?>
              <tr>
                <td>#<?= e($r['id_factura']) ?></td>
                <td><?= e($r['fecha_emision']) ?></td>
                <td><?= e($r['numero_documento']) ?></td>
                <td><?= e($r['cliente']) ?></td>
                <td><?= e($r['ruc_ci']) ?></td>
                <td><?= e($r['condicion_venta']) ?></td>
                <td><span class="badge <?= e($badge) ?>"><?= e($r['estado']) ?></span></td>
                <td><?= number_format((float)$r['total_neto'],2,',','.') ?></td>
                <td>
                  <div class="right">
                    <a class="btn" href="factura_ver.php?id=<?= e($r['id_factura']) ?>" target="_blank">Ver</a>
                    <?php if (strtolower($r['estado'])==='emitida'): ?>
                      <button class="btn btn-danger" type="button" onclick="anularFactura(<?= (int)$r['id_factura'] ?>)">Anular</button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="9">No se encontraron facturas con esos filtros.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINACIÓN -->
    <div class="pager">
      <?php if($page>1): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">&laquo; Anterior</a>
      <?php endif; ?>
      <span class="current"><?= $page ?></span>
      <?php if($page<$total_pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">Siguiente &raquo;</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
function showToast(msg, ok=true){
  const t=document.getElementById('toast');
  t.textContent = msg;
  t.style.background = ok ? '#16a34a' : '#ef4444';
  t.style.display = 'block';
  clearTimeout(window.__t);
  window.__t = setTimeout(()=>t.style.display='none', 3000);
}

async function anularFactura(id){
  const motivo = prompt('Motivo de anulación (requerido):','');
  if (motivo === null) return;
  if (!motivo.trim()) { alert('Debes indicar un motivo.'); return; }
  if (!confirm("¿Seguro que deseas anular la factura #"+id+"?")) return;

  try{
    const body = new URLSearchParams();
    body.append('id_factura', id);
    body.append('motivo', motivo);
    body.append('anulado_por', <?= json_encode($_SESSION['nombre_usuario'] ?? '') ?>);

    const res = await fetch('anular_factura.php', {
      method: 'POST',
      headers: { 'Content-Type':'application/x-www-form-urlencoded' },
      body
    });
    const d = await res.json();
    if (!res.ok || !d.success) {
      showToast(d.error || ('HTTP '+res.status), false);
      return;
    }
    showToast('Factura anulada correctamente');
    setTimeout(()=>location.reload(), 900);
  }catch(e){
    showToast('Error de red/servidor', false);
  }
}
</script>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>
</body>
</html>
