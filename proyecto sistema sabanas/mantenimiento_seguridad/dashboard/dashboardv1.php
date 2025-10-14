<?php
session_start();
//echo "<pre>"; print_r($_SESSION); echo "</pre>";

if (!isset($_SESSION['nombre_usuario'])) {
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}

$nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$rol     = $_SESSION['rol'] ?? 'invitado';

$avatarUrl = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/imagenes_perfil/default.png';

function canAccess($roles, $current)
{
    return in_array($current, (array)$roles, true);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Beauty Creations | Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: linear-gradient(135deg, #ffe5f4 0%, #f6f0ff 45%, #fef9ff 100%);
            --bg-dark: linear-gradient(135deg, #1f1024 0%, #2a1231 40%, #231027 100%);
            --surface: rgba(255, 255, 255, 0.84);
            --surface-dark: rgba(32, 19, 42, 0.78);
            --text: #411f31;
            --text-dark: #f7ecf8;
            --muted: #a57895;
            --muted-dark: #c5a7c3;
            --primary: #d63384;
            --primary-dark: #f5a6d0;
            --pill-bg: rgba(214, 51, 132, 0.16);
            --pill-color: #a3175b;
            --shadow: 0 28px 50px rgba(188, 70, 137, 0.22);
            --shadow-card: 0 16px 32px rgba(64, 21, 53, 0.16);
            --radius: 18px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: var(--bg-dark);
                --surface: var(--surface-dark);
                --text: var(--text-dark);
                --muted: var(--muted-dark);
                --primary: var(--primary-dark);
                --pill-bg: rgba(245, 166, 208, 0.18);
                --pill-color: #fed6ec;
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
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .app {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 0;
            opacity: 0.55;
        }

        body::before {
            top: -120px;
            left: -120px;
            background: rgba(214, 51, 132, 0.35);
        }

        body::after {
            bottom: -160px;
            right: -100px;
            background: rgba(126, 90, 255, 0.32);
        }

        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 5;
            background: var(--surface);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(214, 51, 132, 0.12);
            box-shadow: 0 10px 36px rgba(64, 21, 53, 0.08);
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 0;
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .brand__badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.78rem;
            letter-spacing: 3.6px;
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
            opacity: 0.7;
        }

        .title {
            font-family: "Playfair Display", "Poppins", serif;
            font-weight: 600;
            font-size: 2rem;
            letter-spacing: 0.6px;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(255, 255, 255, 0.55);
            padding: 8px 14px;
            border-radius: 999px;
            box-shadow: 0 10px 24px rgba(64, 21, 53, 0.12);
        }

        .avatar,
        .img_perfil {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(214, 51, 132, 0.36);
            display: block;
            flex-shrink: 0;
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

        .badge {
            font-size: 0.72rem;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            background: rgba(214, 51, 132, 0.16);
            color: var(--pill-color);
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

        .btn {
            border: none;
            border-radius: 999px;
            padding: 10px 16px;
            background: linear-gradient(135deg, #d63384, #f072c1);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 12px 22px rgba(214, 51, 132, 0.26);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(214, 51, 132, 0.32);
        }

        .content {
            padding: 40px 0 60px;
        }

        .hello {
            margin: 0;
            font-family: "Playfair Display", "Poppins", serif;
            font-weight: 600;
            font-size: 2.4rem;
            letter-spacing: 0.4px;
        }

        .sub {
            margin: 8px 0 26px;
            color: var(--muted);
            max-width: 580px;
            line-height: 1.6;
        }

        #frase-dia {
            margin: 26px 0 36px;
            font-style: italic;
            color: var(--primary);
            background: rgba(214, 51, 132, 0.08);
            padding: 14px 18px;
            border-radius: 14px;
            box-shadow: 0 10px 28px rgba(64, 21, 53, 0.12);
        }

        h2 {
            margin: 0 0 14px;
            font-size: 1.28rem;
            font-weight: 600;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: var(--muted);
        }

        .grid {
            display: grid;
            gap: 20px;
        }

        .modules {
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }

        .card {
            position: relative;
            background: rgba(255, 255, 255, 0.92);
            border-radius: var(--radius);
            box-shadow: var(--shadow-card);
            padding: 24px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
            border: 1px solid rgba(214, 51, 132, 0.08);
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
            box-shadow: 0 22px 44px rgba(64, 21, 53, 0.16);
        }

        .card:hover::after {
            opacity: 1;
        }

        .module-title {
            font-weight: 600;
            font-size: 1.12rem;
            margin: 12px 0 8px;
        }

        .muted {
            color: var(--muted);
            font-size: 0.96rem;
            line-height: 1.56;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.82rem;
            margin-top: 16px;
            background: var(--pill-bg);
            color: var(--pill-color);
            font-weight: 500;
        }

        .pill::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.6;
        }

        .pill.buy {
            background: rgba(120, 97, 255, 0.15);
            color: #4c3ae5;
        }

        .pill.sale {
            background: rgba(134, 197, 143, 0.18);
            color: #2f7d45;
        }

        .pill.cash {
            background: rgba(255, 214, 126, 0.2);
            color: #9a6209;
        }

        .pill.service {
            background: rgba(214, 51, 132, 0.2);
            color: #a3175b;
        }

        /* Iconos via mask (sin libs) */
        .ico {
            display: inline-block;
            width: 18px;
            height: 18px;
            background: currentColor;
            mask-size: cover;
            -webkit-mask-size: cover;
        }

        .i-users {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05C16.69 13.77 18 14.68 18 16.5V20h6v-3.5c0-2.33-4.67-3.5-8-3.5z"/></svg>');
        }

        .i-bag {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M16 6V4a4 4 0 0 0-8 0v2H3v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6h-5zm-6-2a2 2 0 1 1 4 0v2h-4V4z"/></svg>');
        }

        .i-sale {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M17.5 17.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0zm-6-11A2.5 2.5 0 1 1 9 4a2.5 2.5 0 0 1 2.5 2.5zM6 14l12-4" stroke="%23000" stroke-width="2" fill="none"/></svg>');
        }

        .i-cash {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M3 6h18v12H3zM7 10h2v4H7zm8 0h2v4h-2z"/></svg>');
        }

        .i-service {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M21 7l-1.41 1.41-3-3L18 4l3 3zm-4.24-.59l-9.9 9.9a2 2 0 0 0-.52.93l-.79 3.16a.5.5 0 0 0 .61.61l3.16-.79a2 2 0 0 0 .93-.52l9.9-9.9-3.39-3.39zM5 8a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0 2a5 5 0 0 1 5 5v1H0v-1a5 5 0 0 1 5-5z"/></svg>');
        }

        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: rgba(214, 51, 132, 0.12);
            color: var(--primary);
            box-shadow: inset 0 0 0 1px rgba(214, 51, 132, 0.12);
        }

        .icon i {
            display: inline-block;
            width: 21px;
            height: 21px;
            background: currentColor;
            mask-size: cover;
            -webkit-mask-size: cover;
        }

        .i-users {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill=\"%23000\" d=\"M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05C16.69 13.77 18 14.68 18 16.5V20h6v-3.5c0-2.33-4.67-3.5-8-3.5z\"/></svg>');
        }

        .i-settings {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox=\"0 0 24 24\"><path fill=\"%23000\" d=\"M19.14,12.94c0.04-0.31,0.06-0.63,0.06-0.94s-0.02-0.63-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61l-1.92-3.32c-0.12-0.21-0.37-0.3-0.6-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.96,2.5C14.93,2.22,14.71,2,14.43,2h-3.86C10.29,2,10.07,2.22,10.04,2.5L9.7,4.35C9.11,4.59,8.58,4.91,8.09,5.29L5.7,4.33c-0.23-0.09-0.49,0-0.6,0.22L3.17,7.87C3.06,8.08,3.11,8.34,3.29,8.48l2.03,1.58C5.28,10.37,5.25,10.69,5.25,11s0.02,0.63,0.07,0.94L3.29,13.52c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.21,0.37,0.3,0.6,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.34,1.85c0.03,0.28,0.25,0.5,0.53,0.5h3.86c0.28,0,0.5-0.22,0.53-0.5l0.34-1.85c0.59-0.24,1.12-0.56,1.62-0.94l2.39,0.96c0.23,0.09,0.49,0,0.6-0.22l1.92-3.32c0.12-0.21,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.5c-1.93,0-3.5-1.57-3.5-3.5S10.07,8.5,12,8.5s3.5,1.57,3.5,3.5S13.93,15.5,12,15.5z\"/></svg>');
        }

        .i-bag {
            mask: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"%23000\" d=\"M16 6V4a4 4 0 0 0-8 0v2H3v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6h-5zm-6-2a2 2 0 1 1 4 0v2h-4V4z\"/></svg>');
        }

        .i-sale {
            mask: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"%23000\" d=\"M17.5 17.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0zm-6-11A2.5 2.5 0 1 1 9 4a2.5 2.5 0 0 1 2.5 2.5zM6 14l12-4\" stroke=\"%23000\" stroke-width=\"2\" fill=\"none\"/></svg>');
        }

        .i-cash {
            mask: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"%23000\" d=\"M3 6h18v12H3zM7 10h2v4H7zm8 0h2v4h-2z\"/></svg>');
        }

        .i-service {
            mask: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"%23000\" d=\"M21 7l-1.41 1.41-3-3L18 4l3 3zm-4.24-.59l-9.9 9.9a2 2 0 0 0-.52.93l-.79 3.16a.5.5 0 0 0 .61.61l3.16-.79a2 2 0 0 0 .93-.52l9.9-9.9-3.39-3.39zM5 8a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0 2a5 5 0 0 1 5 5v1H0v-1a5 5 0 0 1 5-5z\"/></svg>');
        }

        footer {
            color: var(--muted);
            text-align: center;
            padding: 40px 0 32px;
            font-size: 0.92rem;
        }

        @media (max-width: 960px) {
            .topbar-inner {
                gap: 16px;
            }

            .profile {
                padding: 8px 12px;
            }
        }

        @media (max-width: 720px) {
            .topbar-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .profile {
                align-self: stretch;
                justify-content: space-between;
            }

            .hello {
                font-size: 2rem;
            }
        }

        @media (max-width: 560px) {
            .container {
                padding: 0 18px;
            }

            .modules {
                grid-template-columns: 1fr;
            }

            .hello {
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>
    <div class="app">
        <header class="topbar">
            <div class="container topbar-inner">
                <div class="brand">
                    <span class="brand__badge">Beauty Creations</span>
                    <div class="title">Panel de Gestión</div>
                </div>
                <div class="profile">
                    <img class="img_perfil avatar" src="<?= htmlspecialchars($avatarUrl) ?>" alt="Perfil">
                    <div class="greeting">
                        <strong>Hola, <?= htmlspecialchars($nombre) ?> ✨</strong>
                        <span class="badge">Rol <?= htmlspecialchars($rol) ?></span>
                    </div>
                    <form method="post" action="logout.php">
                        <button class="btn" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="container content">
            <h1 class="hello">Bienvenida a tu universo Beauty Creations</h1>
            <p class="sub">Gestioná turnos, servicios y todo lo que hace brillar a tu salón desde un único lugar pensado para vos.</p>

            <div id="frase-dia">Cargando frase motivacional...</div>

            <h2 id="modulos" style="margin:22px 0 8px;font-size:1.2rem">Módulos disponibles</h2>
            <div class="grid modules">
                <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/ui_panel_usuario.php">
                    <span class="icon"><i class="ico i-users"></i></span>
                    <div class="module-title">Panel de Usuario</div>
                    <div class="muted">Actualizá tu perfil y mantené tu información siempre al día.</div>
                </a>

                <?php if (canAccess(['admin'], $rol)): ?>
                    <a class="card" href="../admin_panel/ui_admin.php">
                        <span class="icon" style="background: rgba(247, 179, 24, 0.18); color: #c37b09;"><i class="ico i-settings"></i></span>
                        <div class="module-title">Administración</div>
                        <div class="muted">Usuarios, roles, permisos y parámetros generales del salón.</div>
                        <span class="pill" style="background: rgba(247, 179, 24, 0.18); color: #a26203;">Admin</span>
                    </a>
                <?php endif; ?>

                <?php if (canAccess(['admin', 'compra'], $rol)): ?>
                    <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/menu/tabla_pedidos.html">
                        <span class="icon" style="background: rgba(120, 97, 255, 0.16); color: #3f2de4;"><i class="ico i-bag"></i></span>
                        <div class="module-title">Módulo de Compras</div>
                        <div class="muted">Stock de productos, pedidos y facturación de insumos.</div>
                        <span class="pill buy">Compras</span>
                    </a>
                <?php endif; ?>

                <?php if (canAccess(['admin', 'venta'], $rol)): ?>
                    <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/pedido/ui_pedido_nuevo.php">
                        <span class="icon" style="background: rgba(134, 197, 143, 0.18); color: #2f7d45;"><i class="ico i-sale"></i></span>
                        <div class="module-title">Módulo de Ventas</div>
                        <div class="muted">Pedidos, facturación y caja diaria de tu salón.</div>
                        <span class="pill sale">Ventas</span>
                    </a>
                <?php endif; ?>

                <!-- <?php if (canAccess(['admin', 'tesoreria'], $rol)): ?>
                    <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/vista/ui_cargar_factura.php">
                        <span class="icon" style="background: rgba(255, 214, 126, 0.2); color: #b6720c;"><i class="ico i-cash"></i></span>
                        <div class="module-title">Módulo de Tesorería</div>
                        <div class="muted">Movimientos, conciliaciones y seguimiento financiero.</div>
                        <span class="pill cash">Tesorería</span>
                    </a>
                <?php endif; ?> -->

                <?php if (canAccess(['admin', 'servicios'], $rol)): ?>
                    <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/orden_reserva/ui_reserva.php">
                        <span class="icon" style="background: rgba(214, 51, 132, 0.18); color: #d63384;"><i class="ico i-service"></i></span>
                        <div class="module-title">Módulo de Servicios</div>
                        <div class="muted">Agenda, estilistas, reservas y seguimiento de cada clienta.</div>
                        <span class="pill service">Servicios</span>
                    </a>
                <?php endif; ?>

                <!-- Servicios (admin o servicios) -->
                <!-- <?php if (canAccess(['admin', 'servicios'], $rol)): ?>
                    <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/orden_reserva/ui_reserva.php">
                        <div class="icon"><i class="ico i-service"></i></div>
                        <div class="module-title">Módulo de Servicios</div>
                        <div class="muted">Agenda, órdenes y seguimiento de tareas</div>
                        <span class="pill service">Servicios</span>
                    </a>
                <?php endif; ?> -->
            </div>
        </main>

        <footer class="container">© <?= date('Y') ?> Beauty Creations — Dashboard Profesional</footer>
    </div>

    <script>
        let gPressed = false;
        document.addEventListener('keydown', (e) => {
            if (e.key === 'g') {
                gPressed = true;
                return;
            }
            if (gPressed) {
                if (e.key === 'u') window.location.href = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/ui_panel_usuario.php';
                if (e.key === 'v') window.location.href = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v2/ui_apertura_cierre_caja.php';
                if (e.key === 'c') window.location.href = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/menu/tabla_prespuestos.html';
                gPressed = false;
            }
        });
        document.addEventListener('keyup', (e) => {
            if (e.key === 'g') gPressed = false;
        });

        const frases = [
            "Cada detalle que cuidás brilla en tus clientas: seguí creando belleza.",
            "Recordá: tu talento transforma looks y eleva la autoestima.",
            "La elegancia nace de la pasión y el compromiso con cada servicio.",
            "Un salón organizado es el mejor aliado de tu creatividad.",
            "Beauty Creations: donde cada día es una oportunidad de brillar más."
        ];

        function fraseAleatoria() {
            const index = Math.floor(Math.random() * frases.length);
            document.getElementById("frase-dia").innerText = frases[index];
        }

        document.addEventListener("DOMContentLoaded", fraseAleatoria);
    </script>
    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/cargar_imagen_perfil.js"></script>
</body>

</html>
