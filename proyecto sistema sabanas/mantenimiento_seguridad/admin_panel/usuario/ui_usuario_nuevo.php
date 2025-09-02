<?php
// Archivo sugerido: /mantenimiento_seguridad/usuarios/ui_registro_usuario.php
session_start();

// Sólo admin
if (!isset($_SESSION['nombre_usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}

require_once __DIR__ . '../../../../conexion/configv2.php'; // ajustá la ruta si tu config está en otro lado

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Config de uploads
$BASE_DIR   = "/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/imagenes_perfil/";
$DEFAULT_AV = "default.png";
$uploadFsDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . trim($BASE_DIR, '/');
if (!is_dir($uploadFsDir)) {
  @mkdir($uploadFsDir, 0775, true);
}

$errors = [];
$okMsg  = '';

function sanitize_text($v)
{
  return trim(filter_var($v, FILTER_UNSAFE_RAW));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF check
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $errors[] = 'Token CSRF inválido. Recargá la página.';
  }

  $nombre_usuario = sanitize_text($_POST['nombre_usuario'] ?? '');
  $email          = sanitize_text($_POST['email'] ?? '');
  $telefono       = sanitize_text($_POST['telefono'] ?? '');
  $rol            = sanitize_text($_POST['rol'] ?? 'compra');
  $pass           = $_POST['contrasena'] ?? '';
  $pass2          = $_POST['contrasena2'] ?? '';
  $imagenNombre   = $DEFAULT_AV; // nombre de archivo (no ruta)

  // Validaciones básicas
  if ($nombre_usuario === '' || strlen($nombre_usuario) < 3) $errors[] = 'El nombre de usuario debe tener al menos 3 caracteres.';
  if ($pass === '' || strlen($pass) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
  if ($pass !== $pass2) $errors[] = 'Las contraseñas no coinciden.';
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido.';
  if (!in_array($rol, ['admin', 'compra', 'venta', 'tesoreria'], true)) $errors[] = 'Rol inválido.';

  // Imagen (opcional)
  if (!empty($_FILES['imagen_perfil']['name'])) {
    $f = $_FILES['imagen_perfil'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
      $finfo   = finfo_open(FILEINFO_MIME_TYPE);
      $mime    = finfo_file($finfo, $f['tmp_name']);
      finfo_close($finfo);
      if (!isset($allowed[$mime])) {
        $errors[] = 'Formato de imagen no permitido (solo JPG, PNG, WEBP).';
      } elseif ($f['size'] > 2 * 1024 * 1024) {
        $errors[] = 'La imagen supera 2MB.';
      } else {
        $imagenNombre = uniqid('u_', true) . $allowed[$mime];
        $dest = $uploadFsDir . $imagenNombre;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
          $errors[] = 'No se pudo guardar la imagen subida.';
          $imagenNombre = $DEFAULT_AV;
        }
      }
    } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
      $errors[] = 'Error al subir la imagen (código ' . (int)$f['error'] . ').';
    }
  }

  // Si todo va bien, insertar
  if (!$errors) {
    // Hash de contraseña
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // Evitar duplicados (opcional, la BD igual tiene UNIQUE)
    $dup = pg_query_params($conn, 'SELECT 1 FROM usuarios WHERE nombre_usuario=$1 OR email=$2 LIMIT 1', [$nombre_usuario, $email]);
    if ($dup && pg_num_rows($dup) > 0) {
      $errors[] = 'El nombre de usuario o email ya existen.';
    } else {
      $sql = 'INSERT INTO usuarios (nombre_usuario, contrasena, rol, email, telefono, imagen_perfil, estado, fecha_creacion, fecha_actualizacion)
              VALUES ($1, $2, $3, $4, $5, $6, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';
      $res = pg_query_params($conn, $sql, [
        $nombre_usuario,
        $hash,
        $rol,
        $email !== '' ? $email : null,
        $telefono !== '' ? $telefono : null,
        $imagenNombre,
      ]);
      if ($res) {
        $okMsg = 'Usuario registrado correctamente.';
        // Reset de sticky values
        $nombre_usuario = $email = $telefono = '';
        $rol = 'compra';
      } else {
        // Capturar error de unique para mensaje más claro
        $err = pg_last_error($conn);
        if (stripos((string)$err, 'usuarios_nombre_usuario_key') !== false) {
          $errors[] = 'El nombre de usuario ya existe.';
        } elseif (stripos((string)$err, 'unique_email') !== false) {
          $errors[] = 'El email ya está registrado.';
        } else {
          $errors[] = 'Error al guardar en la base de datos.';
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Administración — Registro de Usuario</title>
  <style>
    :root {
      --bg: #f6f7fb;
      --surface: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --primary: #2563eb;
      --danger: #ef4444;
      --shadow: 0 10px 24px rgba(0, 0, 0, .08);
      --radius: 14px;
    }

    * {
      box-sizing: border-box;
    }

    /* <- asegura que padding/border no hagan overflow */
    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font: 16px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, sans-serif
    }

    a {
      color: inherit;
      text-decoration: none
    }

    .container {
      width: 100%;
      max-width: 980px;
      margin: 0 auto;
      padding: 0 16px
    }

    /* <- padding lateral */
    .topbar {
      background: var(--surface);
      border-bottom: 1px solid rgba(0, 0, 0, .06)
    }

    .topbar-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 0;
      gap: 12px
    }

    .profile {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap
    }

    /* <- evita saltos feos en pantallas chicas */
    .avatar,
    .img_perfil {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(0, 0, 0, .08)
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: none;
      border-radius: 10px;
      padding: 10px 12px;
      background: var(--primary);
      color: #fff;
      cursor: pointer
    }

    .btn.secondary {
      background: #e5e7eb;
      color: #111
    }

    .btn.danger {
      background: var(--danger)
    }

    .content {
      padding: 22px 0
    }

    h1 {
      margin: 0 0 10px;
      font-size: 1.4rem
    }

    .muted {
      color: var(--muted)
    }

    .card {
      background: var(--surface);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 18px;
      margin-top: 14px;
      overflow: hidden
    }

    /* <- evita desbordes ocultos */
    .grid {
      display: grid;
      gap: 14px
    }

    /* <- AQUÍ la clave: columnas fluidas que no rompen */
    .form-grid {
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    }

    /* Inputs 100% y sin desbordar */
    label {
      display: block;
      font-weight: 600;
      margin: 8px 0 6px
    }

    input,
    select {
      width: 100%;
      max-width: 100%;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, .12);
      background: #fff;
    }

    input:focus,
    select:focus {
      outline: 2px solid rgba(37, 99, 235, .35)
    }

    /* Evita que textos largos (emails/telefonos pegados) rompan el layout */
    input,
    select {
      overflow-wrap: anywhere;
    }

    /* File input suele desbordar: fuerza bloque + ancho */
    #imagen_perfil {
      display: block;
      width: 100%;
      max-width: 100%;
    }

    .help {
      font-size: .85rem;
      color: var(--muted)
    }

    .alert {
      padding: 12px 14px;
      border-radius: 10px;
      margin: 10px 0
    }

    .alert.ok {
      background: #ecfdf5;
      color: #065f46;
      border: 1px solid #10b981
    }

    .alert.err {
      background: #fef2f2;
      color: #7f1d1d;
      border: 1px solid #ef4444
    }

    /* Responsivo del topbar */
    @media (max-width:560px) {
      .topbar-inner {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px
      }
    }
  </style>
</head>

<body>
  <header class="topbar">
    <div class="container topbar-inner">
      <div style="font-weight:800">Administración · Registro de Usuario</div>
      <div class="profile">
        <img class="img_perfil avatar" src="<?= htmlspecialchars($BASE_DIR . $DEFAULT_AV) ?>" alt="Perfil">
        <form method="get" action="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/admin_panel/ui_admin.php">
          <button class="btn secondary" type="submit">← Volver</button>
        </form>
      </div>
    </div>
  </header>

  <main class="container content">
    <?php if ($okMsg): ?>
      <div class="alert ok">✅ <?= htmlspecialchars($okMsg) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert err">
        <strong>Revisá estos puntos:</strong>
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="grid form-grid">
          <div>
            <label for="nombre_usuario">Nombre de usuario *</label>
            <input type="text" id="nombre_usuario" name="nombre_usuario" minlength="3" required value="<?= htmlspecialchars($nombre_usuario ?? '') ?>">
            <p class="help">Debe ser único. Mínimo 3 caracteres.</p>
          </div>

          <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="opcional@correo.com">
          </div>

          <div>
            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono" value="<?= htmlspecialchars($telefono ?? '') ?>" placeholder="(0991) 000 000">
          </div>

          <div>
            <label for="rol">Rol *</label>
            <select id="rol" name="rol" required>
              <?php $roles = ['compra' => 'Compra', 'venta' => 'Venta', 'tesoreria' => 'Tesorería', 'admin' => 'Admin'];
              $selRol = $rol ?? 'compra';
              foreach ($roles as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($selRol === $val ? 'selected' : '') ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label for="contrasena">Contraseña *</label>
            <input type="password" id="contrasena" name="contrasena" minlength="6" required>
            <p class="help">Mínimo 6 caracteres.</p>
          </div>

          <div>
            <label for="contrasena2">Repetir contraseña *</label>
            <input type="password" id="contrasena2" name="contrasena2" minlength="6" required>
          </div>

          <div>
            <label for="imagen_perfil">Imagen de perfil (JPG/PNG/WEBP, máx 2MB)</label>
            <input type="file" id="imagen_perfil" name="imagen_perfil" accept="image/jpeg,image/png,image/webp">
            <p class="help">Si no subís una, se usará la imagen por defecto.</p>
          </div>
        </div>

        <div style="margin-top:14px; display:flex; gap:10px;">
          <button class="btn" type="submit">Guardar usuario</button>
          <button class="btn danger" type="reset">Limpiar</button>
        </div>
      </form>
    </div>
  </main>

  <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/cargar_imagen_perfil.js"></script>
</body>

</html>