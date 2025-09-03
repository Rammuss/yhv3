<?php
// ui_ajuste_inventario.php
session_start();
include("../../conexion/configv2.php");

// Filtros simples para la grilla (opcional)
$busca = $_GET['q'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

// Traer productos
$prodSql = "SELECT id_producto, nombre FROM public.producto /*WHERE estado='ACTIVO'*/ ORDER BY nombre LIMIT 500";
$prodRes = pg_query($conn, $prodSql);

// Traer últimos movimientos con filtros
$params = [];
$w = [];
if ($busca !== '') {
  $w[] = "(LOWER(p.nombre) LIKE LOWER($".(count($params)+1)."))";
  $params[] = "%$busca%";
}
if ($desde !== '') {
  $w[] = "m.fecha >= $".(count($params)+1);
  $params[] = $desde." 00:00:00";
}
if ($hasta !== '') {
  $w[] = "m.fecha <= $".(count($params)+1);
  $params[] = $hasta." 23:59:59";
}
$where = $w ? "WHERE ".implode(" AND ", $w) : "";

$listSql = "
  SELECT m.id, m.id_producto, p.nombre AS producto, m.tipo_movimiento, m.cantidad, m.fecha
  FROM public.movimiento_stock m
  JOIN public.producto p ON p.id_producto = m.id_producto
  $where
  ORDER BY m.fecha DESC
  LIMIT 100
";
$listRes = pg_query_params($conn, $listSql, $params);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Ajuste de Inventario</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../../menu/styles.css" />
  <style>
    body{font-family:Arial, sans-serif; margin:20px; background:#f7f7f7;}
    .card{background:#fff; padding:16px; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.08); margin-bottom:16px;}
    h2{margin:0 0 12px;}
    label{display:block; font-size:12px; margin-bottom:6px; color:#333;}
    input,select,button{width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; box-sizing:border-box;}
    .row{display:flex; gap:12px;}
    .row>div{flex:1}
    table{width:100%; border-collapse:collapse; background:#fff}
    th,td{padding:10px; border-bottom:1px solid #eee; font-size:14px; text-align:left;}
    th{background:#fafafa}
    .actions{display:flex; gap:8px;}
    .ok{color:#137333; font-weight:bold;}
    .err{color:#b00020; font-weight:bold;}

    /* label + badge */
    .label-row{display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px;}
    .stock-badge{display:inline-flex; align-items:center; gap:6px; padding:2px 8px; border-radius:999px; font-size:12px; line-height:1.6; border:1px solid #e5e7eb; background:#f9fafb; color:#111827; white-space:nowrap;}
    .stock-dot{width:8px; height:8px; border-radius:999px; background:#6b7280;}
    .stock--ok .stock-dot{background:#16a34a;}
    .stock--warn .stock-dot{background:#ca8a04;}
    .stock--zero .stock-dot{background:#ef4444;}
  </style>
</head>
<body>
  <div id="navbar-container"></div>

  <div class="card">
    <h2>Nuevo ajuste de inventario</h2>
    <form id="form-ajuste" autocomplete="off">
      <div class="row">
        <div>
          <div class="label-row">
            <label for="sel-producto">Producto</label>
            <span class="stock-badge" id="stock-badge">
              <span class="stock-dot"></span>
              <span>Stock: <b id="stock-actual">—</b></span>
            </span>
          </div>
          <select id="sel-producto" name="id_producto" required>
            <option value="">-- Seleccione --</option>
            <?php while ($p = pg_fetch_assoc($prodRes)): ?>
              <option value="<?= htmlspecialchars($p['id_producto']) ?>">
                <?= htmlspecialchars($p['nombre']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div>
          <label for="tipo-mov">Tipo de ajuste</label>
          <select id="tipo-mov" name="tipo_movimiento" required>
            <option value="">-- Seleccione --</option>
            <option value="AJUSTE_POS">Incrementar stock (AJUSTE_POS)</option>
            <option value="AJUSTE_NEG">Disminuir stock (AJUSTE_NEG)</option>
          </select>
        </div>

        <div>
          <label for="cant">Cantidad</label>
          <input id="cant" type="number" name="cantidad" min="1" step="1" required />
        </div>
      </div>

      <div class="row" style="margin-top:12px;">
        <div>
          <label for="obs">Observación (opcional)</label>
          <input id="obs" type="text" name="observacion" maxlength="255" placeholder="Motivo del ajuste, lote, conteo, etc." />
        </div>
        <div style="align-self:end">
          <button type="submit">Guardar ajuste</button>
        </div>
      </div>
      <div id="msg" style="margin-top:10px;"></div>
    </form>
  </div>

  <div class="card">
    <h2>Movimientos recientes</h2>
    <form method="get" class="row" style="margin-bottom:12px;">
      <div><label>Buscar producto</label><input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Nombre contiene..." /></div>
      <div><label>Desde</label><input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" /></div>
      <div><label>Hasta</label><input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" /></div>
      <div style="align-self:end"><button type="submit">Filtrar</button></div>
    </form>
    <table>
      <thead>
        <tr>
          <th>ID</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Fecha</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($m = pg_fetch_assoc($listRes)): ?>
          <tr>
            <td><?= htmlspecialchars($m['id']) ?></td>
            <td><?= htmlspecialchars($m['producto']) ?></td>
            <td><?= htmlspecialchars($m['tipo_movimiento']) ?></td>
            <td><?= htmlspecialchars($m['cantidad']) ?></td>
            <td><?= htmlspecialchars($m['fecha']) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <script>
    // Guardado del ajuste
    const form = document.getElementById('form-ajuste');
    const msg  = document.getElementById('msg');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      msg.textContent = 'Guardando...';
      msg.className = '';
      const data = new FormData(form);
      try {
        const res  = await fetch('stock_guardar.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
          msg.textContent = json.mensaje || 'Ajuste guardado.';
          msg.className   = 'ok';
          form.reset();
          setTimeout(()=> location.reload(), 600);
        } else {
          msg.textContent = json.mensaje || 'No se pudo guardar.';
          msg.className   = 'err';
        }
      } catch (err) {
        msg.textContent = 'Error de red o servidor.';
        msg.className   = 'err';
      }
    });
  </script>

  <script>
    // Stock actual + badge (versión única, sin duplicados)
    const selProd  = document.getElementById('sel-producto');
    const lblStock = document.getElementById('stock-actual');
    const badge    = document.getElementById('stock-badge');
    const fmtPY    = new Intl.NumberFormat('es-PY');

    function setBadgeTone(value) {
      badge.classList.remove('stock--ok','stock--warn','stock--zero');
      if (value === 0)        badge.classList.add('stock--zero');
      else if (value <= 5)    badge.classList.add('stock--warn'); // umbral ejemplo
      else                    badge.classList.add('stock--ok');
    }

    async function cargarStock() {
      const id = selProd.value;
      if (!id) {
        lblStock.textContent = '—';
        badge.classList.remove('stock--ok','stock--warn','stock--zero');
        return;
      }
      try {
        const res  = await fetch('api_stock_producto.php?id_producto=' + encodeURIComponent(id));
        const json = await res.json();
        const val  = Number(json?.stock_actual ?? 0);
        lblStock.textContent = Number.isFinite(val) ? fmtPY.format(val) : '—';
        setBadgeTone(Number.isFinite(val) ? val : 0);
      } catch {
        lblStock.textContent = '—';
        badge.classList.remove('stock--ok','stock--warn','stock--zero');
      }
    }

    selProd.addEventListener('change', cargarStock);
    window.addEventListener('DOMContentLoaded', cargarStock);
  </script>

  <script src="../../menu/navbar.js"></script>
</body>
</html>
