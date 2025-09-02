<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}

$nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$rol     = $_SESSION['rol'] ?? 'invitado';

// ✔️ Sólo ADMIN puede entrar a este módulo
if ($rol !== 'admin') {
  http_response_code(403);
  echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Acceso denegado</title></head><body style="font-family:system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, sans-serif; padding:40px;">'
     .'<h1 style="margin:0 0 10px;">403 — Acceso denegado</h1>'
     .'<p>No tenés permisos para ver esta página.</p>'
     .'<p><a href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/index.php">Volver al Dashboard</a></p>'
     .'</body></html>';
  exit;
}

// Ruta base para imagen por defecto (tu JS luego la reemplaza si corresponde)
$avatarUrl = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/imagenes_perfil/default.png';

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Administración — Home</title>
  <style>
    :root{
      --bg:#f6f7fb; --surface:#ffffff; --text:#1f2937; --muted:#6b7280;
      --primary:#2563eb; --success:#10b981; --warn:#f59e0b; --danger:#ef4444;
      --shadow:0 10px 24px rgba(0,0,0,.08); --radius:14px;
    }
    @media (prefers-color-scheme: dark){
      :root{ --bg:#0b1020; --surface:#121931; --text:#e5e7eb; --muted:#a3a9b9; --shadow:0 14px 36px rgba(0,0,0,.45); }
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif}
    a{color:inherit;text-decoration:none}

    .container{width:100%;max-width:1200px;margin:0 auto;padding:0 16px}

    /* Topbar */
    .topbar{position:sticky;top:0;z-index:5;background:var(--surface);border-bottom:1px solid rgba(0,0,0,.06)}
    .topbar-inner{display:flex;align-items:center;justify-content:space-between;padding:12px 0}
    .title{font-weight:800;letter-spacing:.2px}
    .profile{display:flex;align-items:center;gap:12px}
    .avatar, .img_perfil{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,0,0,.08);display:block;flex-shrink:0}
    .badge{font-size:.72rem;letter-spacing:.4px;text-transform:uppercase;background:rgba(0,0,0,.06);color:var(--muted);padding:4px 8px;border-radius:999px;display:inline-block}
    .btn{display:inline-flex;align-items:center;gap:8px;border:none;border-radius:10px;padding:10px 12px;background:var(--danger);color:#fff;cursor:pointer}
    .btn-outline{background:transparent;color:var(--text);border:1px solid rgba(0,0,0,.12)}

    /* Breadcrumbs */
    .breadcrumbs{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:.92rem;padding:12px 0}
    .breadcrumbs a{color:var(--muted)}

    /* Grid de secciones */
    .grid{display:grid;gap:16px}
    .modules{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:8px}
    .card{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;transition:transform .15s ease, box-shadow .15s ease}
    .card:hover{transform:translateY(-2px);box-shadow:0 16px 36px rgba(0,0,0,.12)}
    .module-title{font-weight:700;font-size:1.05rem;margin-top:8px}
    .muted{color:var(--muted);font-size:.92rem}

    /* Iconos via mask */
    .ico{display:inline-block;width:18px;height:18px;background:currentColor;mask-size:cover;-webkit-mask-size:cover}
    .i-user-add{mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-6 2c-2.67 0-8 1.34-8 4v2h12v-2c0-2.66-5.33-4-8-4zm11-1v-2h-2V9h-2v2h-2v2h2v2h2v-2h2z"/></svg>')}
    .i-users{mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05C16.69 13.77 18 14.68 18 16.5V20h6v-3.5c0-2.33-4.67-3.5-8-3.5z"/></svg>')}
    .i-shield{mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>')}
    .i-params{mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M3 17v2h6v-2H3zm0-7v2h12V10H3zm0-7v2h18V3H3z"/></svg>')}
    .i-audit{mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M3 3h18v4H3zM3 9h18v12H3zM7 12h2v6H7zm4 0h2v6h-2zm4 0h2v6h-2z"/></svg>')}
    .i-backup{mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M12 4a8 8 0 1 0 8 8h-2a6 6 0 1 1-6-6V4l4 3-4 3V7z"/></svg>')}

    .icon{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;color:var(--primary)}

    /* Responsive */
    @media (max-width:1100px){ .modules{grid-template-columns:repeat(2,minmax(0,1fr));} }
    @media (max-width:560px){ .modules{grid-template-columns:1fr;} .topbar-inner{flex-direction:column;align-items:flex-start;gap:10px} }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="container topbar-inner">
      <div class="title">Administración</div>
      <div class="profile">
        <img class="img_perfil avatar" src="<?= htmlspecialchars($avatarUrl) ?>" alt="Perfil">
        <div>
          <div style="font-weight:700">Hola, <?= htmlspecialchars($nombre) ?> 👋</div>
          <span class="badge">Rol: <?= htmlspecialchars($rol) ?></span>
        </div>
        <a class="btn btn-outline" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/dashboard/dashboardv1.php">Volver al Dashboard</a>
        <form method="post" action="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/logout.php">
          <button class="btn" type="submit">Cerrar sesión</button>
        </form>
      </div>
    </div>
  </header>

  <main class="container" style="padding:18px 0 28px;">
    <nav class="breadcrumbs">
      <a href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/dashboard/dashboardv1.php">Inicio</a>
      <span>/</span>
      <span>Administración</span>
    </nav>

    <h1 style="margin:0 0 8px;font-size:1.4rem;font-weight:800">Centro de Administración</h1>
    <p class="muted" style="margin:0 0 16px">Elegí una sección para gestionar tu sistema.</p>

    <section class="grid modules">
      <!-- Registro de Usuario -->
      <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/admin_panel/usuario/ui_usuario_nuevo.php">
        <div class="icon"><i class="ico i-user-add"></i></div>
        <div class="module-title">Registro de Usuario</div>
        <div class="muted">Crear nuevas cuentas y definir datos básicos</div>
      </a>

      <!-- Gestión de Usuarios (lista/editar/bloquear) -->
      <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/admin_panel/usuario/ui_usuario_gestionar.php">
        <div class="icon"><i class="ico i-users"></i></div>
        <div class="module-title">Gestión de Usuarios</div>
        <div class="muted">Listar, editar, activar/desactivar usuarios</div>
      </a>

      <!-- Roles -->
      <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/roles/ui_roles.php">
        <div class="icon"><i class="ico i-shield"></i></div>
        <div class="module-title">Roles</div>
        <div class="muted">Crear y administrar roles del sistema</div>
      </a>

      <!-- Permisos -->
      <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/permisos/ui_permisos.php">
        <div class="icon"><i class="ico i-shield"></i></div>
        <div class="module-title">Permisos</div>
        <div class="muted">Asignar permisos a roles y usuarios</div>
      </a>

      <!-- Parámetros del Sistema -->
      <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento/parametros/ui_parametros.php">
        <div class="icon"><i class="ico i-params"></i></div>
        <div class="module-title">Parámetros del Sistema</div>
        <div class="muted">Valores generales: empresa, cajas, impuestos</div>
      </a>

      <!-- Auditoría -->
      <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/auditoria/ui_auditoria.php">
        <div class="icon"><i class="ico i-audit"></i></div>
        <div class="module-title">Auditoría</div>
        <div class="muted">Registro de acciones y accesos</div>
      </a>

      <!-- Respaldos -->
      <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento/respaldos/ui_respaldos.php">
        <div class="icon"><i class="ico i-backup"></i></div>
        <div class="module-title">Respaldos</div>
        <div class="muted">Exportar/Importar copias de seguridad</div>
      </a>
    </section>
  </main>

  <footer class="container" style="color:var(--muted);padding:28px 0;">© <?= date('Y') ?> Ofertas del Container — Administración v1.0</footer>

  <!-- Tu JS actual de imagen de perfil (opcional) -->
  <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/cargar_imagen_perfil.js"></script>
</body>
</html>
