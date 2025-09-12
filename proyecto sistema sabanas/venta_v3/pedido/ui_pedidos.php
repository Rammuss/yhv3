<?php
// ui_pedidos.php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

// ---- Helpers ----
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$ESTADOS = ['pendiente','facturado','anulado'];

// ---- Filtros (GET) ----
$id_pedido = isset($_GET['id_pedido']) ? (int)$_GET['id_pedido'] : 0;
$estado    = isset($_GET['estado']) ? strtolower(trim($_GET['estado'])) : '';
$desde     = trim($_GET['desde'] ?? '');
$hasta     = trim($_GET['hasta'] ?? '');
$q         = trim($_GET['q'] ?? ''); // nombre/ci
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 15;
$offset    = ($page - 1) * $per_page;

// ---- WHERE dinámico ----
$where  = [];
$params = [];

if ($id_pedido > 0) {
  $where[]  = "pc.id_pedido = $".(count($params)+1);
  $params[] = $id_pedido;
}
if ($estado !== '' && in_array($estado, $ESTADOS, true)) {
  $where[]  = "LOWER(pc.estado) = $".(count($params)+1);
  $params[] = $estado;
}
if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$desde)) {
  $where[]  = "pc.fecha_pedido >= $".(count($params)+1)."::date";
  $params[] = $desde;
}
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$hasta)) {
  $where[]  = "pc.fecha_pedido <= $".(count($params)+1)."::date";
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

// ---- Total para paginación ----
$sql_count = "
  SELECT COUNT(*) 
  FROM public.pedido_cab pc
  JOIN public.clientes c ON c.id_cliente = pc.id_cliente
  $where_sql
";
$res_count = pg_query_params($conn, $sql_count, $params);
$total_rows = $res_count ? (int)pg_fetch_result($res_count, 0, 0) : 0;
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ---- Query principal ----
$sql = "
  SELECT
    pc.id_pedido,
    pc.fecha_pedido,
    pc.estado,
    pc.total_neto,
    pc.total_bruto,
    pc.total_iva,
    COALESCE(pc.observacion,'') AS observacion,
    c.id_cliente,
    (c.nombre||' '||c.apellido) AS cliente,
    c.ruc_ci
  FROM public.pedido_cab pc
  JOIN public.clientes c ON c.id_cliente = pc.id_cliente
  $where_sql
  ORDER BY pc.fecha_pedido DESC, pc.id_pedido DESC
  LIMIT $per_page OFFSET $offset
";
$res = pg_query_params($conn, $sql, $params);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Listado de pedidos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{--bg:#f6f7fb;--surface:#fff;--text:#1f2937;--muted:#6b7280;--primary:#2563eb;--danger:#ef4444;--radius:14px;--shadow:0 10px 24px rgba(0,0,0,.08);}
  body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto}
  .wrap{max-width:1150px;margin:24px auto;padding:0 16px}
  .card{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;margin-bottom:16px}
  h1{margin:0 0 10px}
  .row{display:flex;gap:12px;flex-wrap:wrap}
  label{display:block;font-size:.9rem;margin-bottom:6px;color:#111827}
  input,select{padding:8px;border:1px solid #e5e7eb;border-radius:10px}
  .btn{background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer}
  .btn-danger{background:var(--danger)}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #eef2f7;padding:10px;text-align:left}
  th{background:#f8fafc}
  .muted{color:var(--muted)}
  .badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:.75rem}
  .b-pend{background:#eef2ff;color:#1e40af}
  .b-fact{background:#ecfdf5;color:#065f46}
  .b-anul{background:#fef2f2;color:#991b1b}
  .right{display:flex;justify-content:flex-end;gap:8px}
  .pager{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:12px}
  .pager a{padding:6px 10px;border-radius:8px;background:#fff;border:1px solid #e5e7eb;text-decoration:none;color:inherit}
  .pager .current{background:#111827;color:#fff;border-color:#111827}
  #toast{position:fixed;right:16px;top:16px;background:#16a34a;color:#fff;padding:12px 14px;border-radius:10px;display:none;z-index:9999}
</style>
</head>
<body>
  <div id="navbar-container"></div>
<div class="wrap">

  <div class="card">
    <h1>Listado de pedidos</h1>
    <form class="row" method="get" autocomplete="off">
      <div>
        <label>N° pedido</label>
        <input type="number" name="id_pedido" value="<?= e($id_pedido ?: '') ?>" placeholder="ID">
      </div>
      <div>
        <label>Estado</label>
        <select name="estado">
          <option value="">Todos</option>
          <?php foreach ($ESTADOS as $st): ?>
            <option value="<?= e($st) ?>" <?= $estado===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Desde</label>
        <input type="date" name="desde" value="<?= e($desde) ?>">
      </div>
      <div>
        <label>Hasta</label>
        <input type="date" name="hasta" value="<?= e($hasta) ?>">
      </div>
      <div style="flex:1;min-width:240px">
        <label>Cliente / CI-RUC</label>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nombre, apellido o CI/RUC">
      </div>
      <div style="align-self:end">
        <button class="btn" type="submit">Filtrar</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="muted" style="margin-bottom:8px">
      Resultados: <?= e(number_format($total_rows,0,',','.')) ?> pedidos
      — Página <?= e($page) ?>/<?= e($total_pages) ?>
    </div>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha</th>
          <th>Cliente</th>
          <th>CI/RUC</th>
          <th>Estado</th>
          <th>Total Neto</th>
          <th>Obs.</th>
          <th class="right">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res && pg_num_rows($res)>0): ?>
          <?php while($r = pg_fetch_assoc($res)): ?>
            <?php
              $badge = 'b-pend';
              if (strtolower($r['estado'])==='facturado') $badge='b-fact';
              if (strtolower($r['estado'])==='anulado')   $badge='b-anul';
            ?>
            <tr>
              <td>#<?= e($r['id_pedido']) ?></td>
              <td><?= e($r['fecha_pedido']) ?></td>
              <td><?= e($r['cliente']) ?></td>
              <td><?= e($r['ruc_ci']) ?></td>
              <td><span class="badge <?= $badge ?>"><?= e(ucfirst($r['estado'])) ?></span></td>
              <td><?= number_format((float)$r['total_neto'], 2, ',', '.') ?></td>
              <td class="muted"><?= e(mb_strimwidth($r['observacion'],0,40,'…')) ?></td>
              <td>
                <div class="right">
                  <a class="btn" href="pedido_ver.php?id=<?= e($r['id_pedido']) ?>" target="_blank">Ver</a>
                  <?php if (strtolower($r['estado'])==='pendiente'): ?>
                    <button class="btn btn-danger" type="button" onclick="anular(<?= (int)$r['id_pedido'] ?>)">Anular</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="8">No se encontraron pedidos con esos filtros.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Paginación -->
    <div class="pager">
      <?php
        $qs = $_GET; unset($qs['page']);
        $base = '?'.http_build_query($qs);
      ?>
      <?php if ($page>1): ?>
        <a href="<?= $base.'&page=1' ?>">&laquo; Primero</a>
        <a href="<?= $base.'&page='.($page-1) ?>">&lsaquo; Anterior</a>
      <?php endif; ?>

      <span class="current">Página <?= e($page) ?></span>

      <?php if ($page<$total_pages): ?>
        <a href="<?= $base.'&page='.($page+1) ?>">Siguiente &rsaquo;</a>
        <a href="<?= $base.'&page='.$total_pages ?>">Último &raquo;</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div id="toast"></div>
<script>
function toast(msg, ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg;
  t.style.background = ok ? '#16a34a' : '#ef4444';
  t.style.display='block';
  clearTimeout(window.__t); window.__t=setTimeout(()=>t.style.display='none', 2800);
}
async function anular(id){
  const motivo = prompt('Motivo de anulación (opcional):','');
  if (motivo===null) return;
  if (!confirm('¿Seguro que querés anular el pedido #'+id+'?')) return;

  try{
    const fd = new FormData();
    fd.append('id_pedido', id);
    fd.append('motivo', motivo);
    const res = await fetch('pedido_anular.php', { method:'POST', body: fd });
    const js  = await res.json();
    if (js.success){
      toast('Pedido anulado correctamente');
      setTimeout(()=>location.reload(), 800);
    }else{
      toast(js.error || 'No se pudo anular', false);
    }
  }catch(e){
    toast('Error de red/servidor', false);
  }
}
</script>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>
</body>
</html>
