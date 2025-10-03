<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}

$nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$rol     = $_SESSION['rol'] ?? 'invitado';

if ($rol !== 'admin') {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Acceso denegado</title></head><body style="font-family:system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, sans-serif; padding:40px;">'
        . '<h1 style="margin:0 0 10px;">403 — Acceso denegado</h1>'
        . '<p>No tenés permisos para ver esta página.</p>'
        . '<p><a href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/index.php">Volver al Dashboard</a></p>'
        . '</body></html>';
    exit;
}

$avatarUrl = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/imagenes_perfil/default.png';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Beauty Creations | Centro de Administración</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: linear-gradient(135deg, #ffe5f4 0%, #f6f0ff 45%, #fef9ff 100%);
            --bg-dark: linear-gradient(135deg, #1f1024 0%, #2a1231 40%, #231027 100%);
            --surface: rgba(255, 255, 255, 0.88);
            --surface-dark: rgba(32, 19, 42, 0.82);
            --text: #411f31;
            --text-dark: #f7ecf8;
            --muted: #9d6f8b;
            --muted-dark: #c7abc4;
            --primary: #d63384;
            --primary-dark: #f5a6d0;
            --accent: #7f5dff;
            --shadow: 0 28px 54px rgba(188, 70, 137, 0.22);
            --shadow-card: 0 18px 40px rgba(64, 21, 53, 0.16);
            --radius: 20px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: var(--bg-dark);
                --surface: var(--surface-dark);
                --text: var(--text-dark);
                --muted: var(--muted-dark);
                --primary: var(--primary-dark);
                --shadow: 0 24px 48px rgba(0, 0, 0, 0.45);
                --shadow-card: 0 20px 44px rgba(0, 0, 0, 0.48);
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 16px/1.55 "Poppins", system-ui, -apple-system, Segoe UI, sans-serif;
            min-height: 100vh;
            position: relative;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            filter: blur(140px);
            z-index: 0;
            opacity: 0.55;
        }

        body::before {
            top: -160px;
            left: -160px;
            background: rgba(214, 51, 132, 0.32);
        }

        body::after {
            bottom: -200px;
            right: -140px;
            background: rgba(126, 90, 255, 0.28);
        }

        .app {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            width: min(1200px, 100%);
            margin: 0 auto;
            padding: 0 28px;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(214, 51, 132, 0.12);
            box-shadow: 0 16px 32px rgba(64, 21, 53, 0.12);
        }

        .topbar__inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 20px 0;
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .brand__badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.78rem;
            letter-spacing: 3.4px;
            text-transform: uppercase;
            color: var(--primary);
            font-weight: 600;
        }

        .brand__badge::before,
        .brand__badge::after {
            content: "";
            width: 18px;
            height: 1px;
            background: currentColor;
            opacity: 0.65;
        }

        .brand__title {
            margin: 0;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2.25rem;
            letter-spacing: 0.6px;
        }

        .brand__subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 8px 18px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 999px;
            box-shadow: 0 12px 26px rgba(64, 21, 53, 0.14);
        }

        .avatar,
        .img_perfil {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(214, 51, 132, 0.36);
            display: block;
        }

        .badge {
            font-size: 0.72rem;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            background: rgba(214, 51, 132, 0.16);
            color: #a3175b;
            padding: 4px 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge::before {
            content: "•";
            font-size: 0.9rem;
        }

        .btn,
        .btn-outline {
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 600;
            letter-spacing: 0.4px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            border: none;
        }

        .btn {
            background: linear-gradient(135deg, #d63384, #f072c1);
            color: #fff;
            box-shadow: 0 14px 28px rgba(214, 51, 132, 0.28);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 36px rgba(214, 51, 132, 0.32);
        }

        .btn-outline {
            background: transparent;
            color: var(--text);
            border: 1px solid rgba(214, 51, 132, 0.22);
            box-shadow: 0 10px 20px rgba(64, 21, 53, 0.1);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(64, 21, 53, 0.16);
        }

        .greeting {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .greeting strong {
            font-weight: 600;
            font-size: 1rem;
        }

        main {
            flex: 1;
            padding: 42px 0 64px;
        }

        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 0.92rem;
            margin-bottom: 18px;
        }

        .breadcrumbs a {
            color: var(--muted);
        }

        h1.page-title {
            margin: 0;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2.1rem;
            letter-spacing: 0.6px;
        }

        p.lead {
            margin: 12px 0 28px;
            color: var(--muted);
            max-width: 640px;
            line-height: 1.6;
        }

        .grid {
            display: grid;
            gap: 22px;
        }

        .modules {
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }

        .card {
            position: relative;
            background: rgba(255, 255, 255, 0.92);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(214, 51, 132, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
        }

        .card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(214, 51, 132, 0.18), transparent 55%);
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 22px 46px rgba(64, 21, 53, 0.18);
        }

        .card:hover::after {
            opacity: 1;
        }

        .module-title {
            font-weight: 600;
            font-size: 1.16rem;
            margin: 12px 0 8px;
        }

        .muted {
            color: var(--muted);
            font-size: 0.96rem;
            line-height: 1.56;
        }

        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: rgba(214, 51, 132, 0.14);
            color: var(--primary);
            box-shadow: inset 0 0 0 1px rgba(214, 51, 132, 0.12);
        }

        .icon i {
            display: inline-block;
            width: 22px;
            height: 22px;
            background: currentColor;
            mask-size: cover;
            -webkit-mask-size: cover;
        }

        .i-user-add {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-6 2c-2.67 0-8 1.34-8 4v2h12v-2c0-2.66-5.33-4-8-4zm11-1v-2h-2V9h-2v2h-2v2h2v2h2v-2h2z"/></svg>');
        }

        .i-users {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05C16.69 13.77 18 14.68 18 16.5V20h6v-3.5c0-2.33-4.67-3.5-8-3.5z"/></svg>');
        }

        .i-shield {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>');
        }

        .i-params {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M3 17v2h6v-2H3zm0-7v2h12V10H3zm0-7v2h18V3H3z"/></svg>');
        }

        .i-audit {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M3 3h18v4H3zM3 9h18v12H3zM7 12h2v6H7zm4 0h2v6h-2zm4 0h2v6h-2z"/></svg>');
        }

        .i-backup {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M12 4a8 8 0 1 0 8 8h-2a6 6 0 1 1-6-6V4l4 3-4 3V7z"/></svg>');
        }

        footer {
            color: var(--muted);
            text-align: center;
            padding: 40px 0 32px;
            font-size: 0.92rem;
        }

        @media (max-width: 960px) {
            .topbar__inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .profile {
                align-self: stretch;
                justify-content: space-between;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 640px) {
            .container {
                padding: 0 20px;
            }

            .brand__title {
                font-size: 2rem;
            }

            h1.page-title {
                font-size: 1.85rem;
            }

            .modules {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="app">
        <header class="topbar">
            <div class="container topbar__inner">
                <div class="brand">
                    <span class="brand__badge">Beauty Creations</span>
                    <h1 class="brand__title">Centro de Administración</h1>
                    <p class="brand__subtitle">Gestioná usuarios, roles y parámetros clave de tu salón.</p>
                </div>
                <div class="profile">
                    <img class="img_perfil avatar" src="<?= htmlspecialchars($avatarUrl) ?>" alt="Perfil">
                    <div class="greeting">
                        <strong>Hola, <?= htmlspecialchars($nombre) ?> ✨</strong>
                        <span class="badge">Rol <?= htmlspecialchars($rol) ?></span>
                    </div>
                    <a class="btn-outline" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/dashboard/dashboardv1.php">Volver al Dashboard</a>
                    <form method="post" action="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/logout.php">
                        <button class="btn" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="container">
            <nav class="breadcrumbs">
                <a href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/dashboard/dashboardv1.php">Inicio</a>
                <span>/</span>
                <span>Administración</span>
            </nav>

            <h1 class="page-title">Panel maestro de control</h1>
            <p class="lead">Coordiná equipos, roles y configuraciones generales del sistema Beauty Creations desde un espacio pensado para administrar cada detalle con estilo.</p>

            <section class="grid modules">
                <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/admin_panel/usuario/ui_usuario_nuevo.php">
                    <span class="icon"><i class="ico i-user-add"></i></span>
                    <div class="module-title">Registro de Usuario</div>
                    <p class="muted"></p>
                </a>

                <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/admin_panel/usuario/ui_usuario_gestionar.php">
                    <span class="icon" style="background: rgba(127, 93, 255, 0.16); color: var(--accent);"><i class="ico i-users"></i></span>
                    <div class="module-title">Gestión de Usuarios</div>
                    <p class="muted"></p>
                </a>

                <!-- <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/roles/ui_roles.php">
                    <span class="icon" style="background: rgba(214, 51, 132, 0.18); color: var(--primary);"><i class="ico i-shield"></i></span>
                    <div class="module-title">Roles</div>
                    <p class="muted"></p>
                </a>

                <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/permisos/ui_permisos.php">
                    <span class="icon" style="background: rgba(253, 197, 123, 0.2); color: #b6720c;"><i class="ico i-shield"></i></span>
                    <div class="module-title">Permisos</div>
                    <p class="muted"></p>
                </a>

                <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento/parametros/ui_parametros.php">
                    <span class="icon" style="background: rgba(120, 97, 255, 0.16); color: #3f2de4;"><i class="ico i-params"></i></span>
                    <div class="module-title">Parámetros del Sistema</div>
                    <p class="muted"></p>
                </a>

                <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/auditoria/ui_auditoria.php">
                    <span class="icon" style="background: rgba(134, 197, 143, 0.18); color: #2f7d45;"><i class="ico i-audit"></i></span>
                    <div class="module-title">Auditoría</div>
                    <p class="muted"></p>
                </a>

                <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento/respaldos/ui_respaldos.php">
                    <span class="icon" style="background: rgba(214, 51, 132, 0.16); color: var(--primary);"><i class="ico i-backup"></i></span>
                    <div class="module-title">Respaldos</div>
                    <p class="muted"></p>
                </a> -->
            </section>
        </main>

        <footer class="container">© <?= date('Y') ?> Beauty Creations — Administración Profesional</footer>
    </div>

    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/cargar_imagen_perfil.js"></script>
</body>

</html>
