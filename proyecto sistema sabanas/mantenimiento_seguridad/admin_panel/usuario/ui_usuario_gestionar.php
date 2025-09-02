<?php
// Archivo sugerido: /mantenimiento_seguridad/usuarios/ui_lista_usuarios.php
session_start();

// Solo admin
if (!isset($_SESSION['nombre_usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}

require_once __DIR__ . '/../../../conexion/configv2.php'; // ajusta si tu árbol cambia

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helpers
function s($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function t($v){ return trim(filter_var($v, FILTER_UNSAFE_RAW)); }

// Parámetros de filtro y paginación
$q       = t($_GET['q'] ?? '');             // búsqueda texto
$rolF    = t($_GET['rol'] ?? '');           // filtro de rol
$estadoF = t($_GET['estado'] ?? '');        // true/false
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;                               // filas por página
$offset  = ($page - 1) * $perPage;

// Orden
$validSort = ['id','nombre_usuario','email','rol','estado','fecha_creacion'];

// Tomamos parámetro si viene, si no usamos default
$sortParam = $_GET['sort'] ?? 'fecha_creacion';
// Validamos con whitelist
$sort = in_array($sortParam, $validSort, true) ? $sortParam : 'fecha_creacion';

// Dirección
$dirParam = $_GET['dir'] ?? 'desc';
$dir = ($dirParam === 'asc') ? 'asc' : 'desc';

// Acciones POST (bloquear, activar, reset intentos, reset pass)
$flash_ok = '';
$flash_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $flash_err = 'Token CSRF inválido.';
  } else {
    $action = t($_POST['action'] ?? '');
    $uid    = (int)($_POST['id'] ?? 0);

    if ($uid <= 0) {
      $flash_err = 'ID inválido.';
    } else {
      if ($action === 'toggle_estado') {
        // Cambiar estado true/false
        $res = pg_query_params($conn, 'UPDATE usuarios SET estado = NOT estado, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = $1', [$uid]);
        $flash_ok = $res ? 'Estado actualizado.' : 'No se pudo actualizar el estado.';
      }
      elseif ($action === 'toggle_bloqueo') {
        $res = pg_query_params($conn, 'UPDATE usuarios SET bloqueado = NOT bloqueado, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = $1', [$uid]);
        $flash_ok = $res ? 'Bloqueo actualizado.' : 'No se pudo actualizar el bloqueo.';
      }
      elseif ($action === 'reset_intentos') {
        $res = pg_query_params($conn, 'UPDATE usuarios SET intentos_acceso = 0, intentos_fallidos = 0, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = $1', [$uid]);
        $flash_ok = $res ? 'Intentos reiniciados.' : 'No se pudieron reiniciar los intentos.';
      }
      elseif ($action === 'reset_pass') {
        // Generar pass temporal y actualizar
        $tmp = substr(bin2hex(random_bytes(8)), 0, 12); // 12 chars
        $hash = password_hash($tmp, PASSWORD_DEFAULT);
        $res = pg_query_params($conn, 'UPDATE usuarios SET contrasena = $1, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = $2', [$hash, $uid]);
        if ($res) {
          $flash_ok = 'Contraseña temporal generada: ' . s($tmp);
        } else {
          $flash_err = 'No se pudo resetear la contraseña.';
        }
      }
      // (Opcional) eliminar usuario, con protecciones
      elseif ($action === 'delete') {
        // Evitar borrarse a uno mismo
        $yo = $_SESSION['nombre_usuario'];
        $qyo = pg_query_params($conn, 'SELECT id FROM usuarios WHERE nombre_usuario = $1 LIMIT 1', [$yo]);
        $myid = ($qyo && pg_num_rows($qyo)===1) ? (int)pg_fetch_result($qyo, 0, 0) : 0;
        if ($uid === $myid) {
          $flash_err = 'No podés eliminar tu propia cuenta.';
        } else {
          $res = pg_query_params($conn, 'DELETE FROM usuarios WHERE id = $1', [$uid]);
          $flash_ok = $res ? 'Usuario eliminado.' : 'No se pudo eliminar.';
        }
      }
    }
  }
}

// Construir WHERE dinámico
$where = [];
$args  = [];
$pi    = 1;

// q: búsqueda
if ($q !== '') {
  $where[] = "(nombre_usuario ILIKE $" . $pi . " OR email ILIKE $" . $pi . ")";
  $args[]  = '%' . $q . '%';
  $pi++;
}

// rol: filtro
if ($rolF !== '' && in_array($rolF, ['admin','compra','venta','tesoreria'], true)) {
  $where[] = "rol = $" . $pi;
  $args[]  = $rolF;
  $pi++;
}

// estado: 'true' o 'false' como STRINGS, no boolean PHP
if ($estadoF === 'true' || $estadoF === 'false') {
  $where[] = "estado = $" . $pi;
  // Podés usar 'true'/'false' o 't'/'f'. Ambas funcionan.
  $args[]  = ($estadoF === 'true') ? 'true' : 'false';
  $pi++;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';



// Total para paginación
$countSql = "SELECT COUNT(*) FROM usuarios $whereSql";
$countRes = pg_query_params($conn, $countSql, $args);
$total = ($countRes && pg_num_rows($countRes)===1) ? (int)pg_fetch_result($countRes, 0, 0) : 0;
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) { $page = $pages; $offset = ($page-1)*$perPage; }

// Listado
$listSql = "SELECT id, nombre_usuario, email, telefono, rol, estado, bloqueado, fecha_creacion
            FROM usuarios
            $whereSql
            ORDER BY $sort $dir
            LIMIT $perPage OFFSET $offset";
$listRes = pg_query_params($conn, $listSql, $args);
$rows = $listRes ? pg_fetch_all($listRes) : [];
if (!$rows) $rows = [];

// Utilidad para armar URLs de orden conservando filtros
function urlWith($params){
  $base = $_GET; foreach($params as $k=>$v){ $base[$k] = $v; } return '?' . http_build_query($base);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Administración — Gestión de Usuarios</title>
  <style>
    :root{ --bg:#f6f7fb; --surface:#ffffff; --text:#1f2937; --muted:#6b7280; --primary:#2563eb; --danger:#ef4444; --shadow:0 10px 24px rgba(0,0,0,.08); --radius:14px; }
    *{ box-sizing: border-box; }
    body{ margin:0; background:var(--bg); color:var(--text); font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif }
    a{ color:inherit; text-decoration:none }
    .container{ width:100%; max-width:1100px; margin:0 auto; padding:0 16px }

    .topbar{ background:var(--surface); border-bottom:1px solid rgba(0,0,0,.06) }
    .topbar-inner{ display:flex; align-items:center; justify-content:space-between; padding:12px 0; gap:12px }
    .profile{ display:flex; align-items:center; gap:12px; flex-wrap:wrap }
    .avatar,.img_perfil{ width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid rgba(0,0,0,.08) }
    .btn{ display:inline-flex; align-items:center; gap:8px; border:none; border-radius:8px; padding:8px 12px; background:var(--primary); color:#fff; cursor:pointer }
    .btn.secondary{ background:#e5e7eb; color:#111 }
    .btn.danger{ background:var(--danger) }
    .btn.small{ padding:6px 8px; font-size:.9rem }

    .content{ padding:22px 0 }
    h1{ margin:0 0 10px; font-size:1.4rem }
    .muted{ color:var(--muted) }

    .card{ background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow); padding:18px; margin-top:14px; overflow:hidden }

    /* Filtros */
    .filters{ display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:10px; margin:8px 0 4px }
    .filters input, .filters select{ width:100%; padding:10px 12px; border-radius:10px; border:1px solid rgba(0,0,0,.12); background:#fff }

    /* Tabla */
    .table-wrap{ width:100%; overflow:auto; border-radius:12px; border:1px solid rgba(0,0,0,.06) }
    table{ width:100%; border-collapse:collapse; min-width:800px; background:#fff }
    th, td{ padding:10px 12px; text-align:left; border-bottom:1px solid rgba(0,0,0,.06) }
    th a{ color:inherit }
    th{ background:#f3f4f6; font-weight:700 }
    tr:hover td{ background:#f9fafb }
    .tag{ display:inline-block; padding:4px 8px; border-radius:999px; font-size:.8rem }
    .tag.on{ background:#ecfdf5; color:#065f46; border:1px solid #10b981 }
    .tag.off{ background:#fef2f2; color:#7f1d1d; border:1px solid #ef4444 }
    .actions{ display:flex; flex-wrap:wrap; gap:6px }

    /* Paginación */
    .pagination{ display:flex; gap:8px; align-items:center; justify-content:flex-end; margin-top:10px }
    .pagination a, .pagination span{ padding:6px 10px; border-radius:8px; border:1px solid rgba(0,0,0,.12); background:#fff }
    .pagination .current{ background:#111; color:#fff; border-color:#111 }

    @media (max-width:560px){ .topbar-inner{ flex-direction:column; align-items:flex-start } }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="container topbar-inner">
      <div style="font-weight:800">Administración · Gestión de Usuarios</div>
      <div class="profile">
        <img class="img_perfil avatar" src="<?= s('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/imagenes_perfil/default.png') ?>" alt="Perfil">
        <form method="get" action="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/admin_panel/ui_admin.php">
          <button class="btn secondary" type="submit">← Volver</button>
        </form>
      </div>
    </div>
  </header>

  <main class="container content">
    <?php if ($flash_ok): ?>
      <div class="card" style="border-left:4px solid #10b981;">✅ <?= s($flash_ok) ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
      <div class="card" style="border-left:4px solid #ef4444;">⚠️ <?= s($flash_err) ?></div>
    <?php endif; ?>

    <div class="card">
      <form class="filters" method="get">
        <input type="text" name="q" placeholder="Buscar nombre o email…" value="<?= s($q) ?>">
        <select name="rol">
          <option value="">Rol (todos)</option>
          <?php foreach(['compra'=>'Compra','venta'=>'Venta','tesoreria'=>'Tesorería','admin'=>'Admin'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $rolF===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
        <select name="estado">
          <option value="">Estado (todos)</option>
          <option value="true"  <?= $estadoF==='true'?'selected':'' ?>>Activo</option>
          <option value="false" <?= $estadoF==='false'?'selected':'' ?>>Inactivo</option>
        </select>
        <button class="btn" type="submit">Filtrar</button>
      </form>

      <div class="table-wrap" style="margin-top:10px;">
        <table>
          <thead>
            <tr>
              <th><a href="<?= s(urlWith(['sort'=>'id','dir'=>$sort==='id' && $dir==='asc'?'desc':'asc'])) ?>">ID</a></th>
              <th><a href="<?= s(urlWith(['sort'=>'nombre_usuario','dir'=>$sort==='nombre_usuario' && $dir==='asc'?'desc':'asc'])) ?>">Usuario</a></th>
              <th><a href="<?= s(urlWith(['sort'=>'email','dir'=>$sort==='email' && $dir==='asc'?'desc':'asc'])) ?>">Email</a></th>
              <th>Teléfono</th>
              <th><a href="<?= s(urlWith(['sort'=>'rol','dir'=>$sort==='rol' && $dir==='asc'?'desc':'asc'])) ?>">Rol</a></th>
              <th>Estado</th>
              <th>Bloqueo</th>
              <th><a href="<?= s(urlWith(['sort'=>'fecha_creacion','dir'=>$sort==='fecha_creacion' && $dir==='asc'?'desc':'asc'])) ?>">Creado</a></th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="9" style="text-align:center; padding:18px; color:var(--muted)">No hay resultados</td></tr>
          <?php else: foreach($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= s($r['nombre_usuario']) ?></td>
              <td><?= s($r['email']) ?></td>
              <td><?= s($r['telefono']) ?></td>
              <td><?= s(ucfirst($r['rol'])) ?></td>
              <td><?= ($r['estado']==='t' || $r['estado']===true) ? '<span class="tag on">Activo</span>' : '<span class="tag off">Inactivo</span>' ?></td>
              <td><?= ($r['bloqueado']==='t' || $r['bloqueado']===true) ? '<span class="tag off">Bloqueado</span>' : '<span class="tag on">OK</span>' ?></td>
              <td><?= s(date('Y-m-d H:i', strtotime($r['fecha_creacion']))) ?></td>
              <td>
                <div class="actions">
                  <form method="post" onsubmit="return confirm('¿Cambiar estado (activo/inactivo)?');">
                    <input type="hidden" name="csrf_token" value="<?= s($csrf_token) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="toggle_estado">
                    <button class="btn small" type="submit">Estado</button>
                  </form>
                  <form method="post" onsubmit="return confirm('¿Bloquear / Desbloquear usuario?');">
                    <input type="hidden" name="csrf_token" value="<?= s($csrf_token) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="toggle_bloqueo">
                    <button class="btn small" type="submit">Bloqueo</button>
                  </form>
                  <form method="post" onsubmit="return confirm('¿Reiniciar intentos?');">
                    <input type="hidden" name="csrf_token" value="<?= s($csrf_token) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="reset_intentos">
                    <button class="btn small" type="submit">Intentos</button>
                  </form>
                  <form method="post" onsubmit="return confirm('¿Generar contraseña temporal? Se mostrará una vez.');">
                    <input type="hidden" name="csrf_token" value="<?= s($csrf_token) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="reset_pass">
                    <button class="btn small" type="submit">Reset Pass</button>
                  </form>
                  <!-- Enlace a editar (opcional, crear ui_editar_usuario.php) -->
                  <!-- <a class="btn small secondary" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/usuarios/ui_editar_usuario.php?id=<?= (int)$r['id'] ?>">Editar</a> -->
                  <!-- <form method="post" onsubmit="return confirm('¿Eliminar usuario? Esta acción no se puede deshacer.');">
                    <input type="hidden" name="csrf_token" value="<?= s($csrf_token) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="btn small danger" type="submit">Eliminar</button>
                  </form> -->
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <?php if($page>1): ?><a href="<?= s(urlWith(['page'=>1])) ?>">« Primero</a><?php endif; ?>
        <?php if($page>1): ?><a href="<?= s(urlWith(['page'=>$page-1])) ?>">‹ Anterior</a><?php endif; ?>
        <span class="current">Página <?= (int)$page ?> / <?= (int)$pages ?></span>
        <?php if($page<$pages): ?><a href="<?= s(urlWith(['page'=>$page+1])) ?>">Siguiente ›</a><?php endif; ?>
        <?php if($page<$pages): ?><a href="<?= s(urlWith(['page'=>$pages])) ?>">Última »</a><?php endif; ?>
      </div>
    </div>
  </main>

  <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/cargar_imagen_perfil.js"></script>
</body>
</html>
