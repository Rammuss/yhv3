<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    // Corrección: header con Location
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}

$nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$rol     = $_SESSION['rol'] ?? 'invitado';

// Ruta fija para la imagen de perfil
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
    <title>Dashboard Global</title>
    <style>
        .card img {
            max-width: 100%;
            height: auto;
        }

        :root {
            --bg: #f6f7fb;
            --surface: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #2563eb;
            --success: #10b981;
            --warn: #f59e0b;
            --danger: #ef4444;
            --shadow: 0 10px 24px rgba(0, 0, 0, .08);
            --radius: 14px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1020;
                --surface: #121931;
                --text: #e5e7eb;
                --muted: #a3a9b9;
                --shadow: 0 14px 36px rgba(0, 0, 0, .45);
            }
        }

        * {
            box-sizing: border-box
        }

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

        /* Layout base */
        .app {
            min-height: 100vh;
            display: flex;
            flex-direction: column
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px
        }

        /* Topbar */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 5;
            background: var(--surface);
            border-bottom: 1px solid rgba(0, 0, 0, .06)
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0
        }

        .title {
            font-weight: 800;
            letter-spacing: .2px
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 12px
        }

        /* Avatar / imágenes de perfil (tamaño fijo y recorte) */
        .avatar,
        .img_perfil {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            /* recorta centrado si es grande */
            border: 2px solid rgba(0, 0, 0, .08);
            display: block;
            /* evita espacios raros en inline */
            flex-shrink: 0;
            /* no se achica en el flex */
        }

        .badge {
            font-size: .72rem;
            letter-spacing: .4px;
            text-transform: uppercase;
            background: rgba(0, 0, 0, .06);
            color: var(--muted);
            padding: 4px 8px;
            border-radius: 999px;
            display: inline-block
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            border-radius: 10px;
            padding: 10px 12px;
            background: var(--danger);
            color: #fff;
            cursor: pointer
        }

        /* Content */
        .content {
            padding: 24px 0
        }

        .hello {
            margin: 0 0 6px;
            font-weight: 800;
            font-size: 1.6rem
        }

        .sub {
            margin: 0 0 20px;
            color: var(--muted)
        }

        /* Cards de módulos */
        .grid {
            display: grid;
            gap: 16px
        }

        .modules {
            grid-template-columns: repeat(3, minmax(0, 1fr))
        }

        .card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 18px;
            transition: transform .15s ease, box-shadow .15s ease
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, .12)
        }

        .module-title {
            font-weight: 700;
            font-size: 1.05rem;
            margin-top: 8px
        }

        .muted {
            color: var(--muted);
            font-size: .92rem
        }

        .pill {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: .78rem;
            margin-top: 10px
        }

        .pill.buy {
            background: rgba(37, 99, 235, .15);
            color: #1e40af
        }

        .pill.sale {
            background: rgba(16, 185, 129, .18);
            color: #065f46
        }

        .pill.cash {
            background: rgba(245, 158, 11, .18);
            color: #7c3a03
        }

        /* Iconos via mask (sin libs) */
        .ico {
            display: inline-block;
            width: 18px;
            height: 18px;
            background: currentColor;
            mask-size: cover;
            -webkit-mask-size: cover
        }

        .i-users {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05C16.69 13.77 18 14.68 18 16.5V20h6v-3.5c0-2.33-4.67-3.5-8-3.5z"/></svg>')
        }

        .i-bag {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M16 6V4a4 4 0 0 0-8 0v2H3v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6h-5zm-6-2a2 2 0 1 1 4 0v2h-4V4z"/></svg>')
        }

        .i-sale {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M17.5 17.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0zm-6-11A2.5 2.5 0 1 1 9 4a2.5 2.5 0 0 1 2.5 2.5zM6 14l12-4" stroke="%23000" stroke-width="2" fill="none"/></svg>')
        }

        .i-cash {
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23000" d="M3 6h18v12H3zM7 10h2v4H7zm8 0h2v4h-2z"/></svg>')
        }

        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            color: var(--primary)
        }

        /* Footer y responsive */
        footer {
            color: var(--muted);
            text-align: center;
            padding: 30px
        }

        @media (max-width:1100px) {
            .modules {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width:560px) {
            .modules {
                grid-template-columns: 1fr;
            }

            .topbar-inner {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px
            }
        }
    </style>
</head>

<body>
    <div class="app">
        <!-- TOPBAR -->
        <header class="topbar">
            <div class="container topbar-inner">
                <div class="title">Panel Administración</div>
                <div class="profile">
                    <img class="img_perfil avatar" src="<?= htmlspecialchars($avatarUrl) ?>" alt="Perfil">

                    <div>
                        <div style="font-weight:700">Hola, <?= htmlspecialchars($nombre) ?> 👋</div>
                        <span class="badge">Rol: <?= htmlspecialchars($rol) ?></span>
                    </div>
                    <form method="post" action="logout.php">
                        <button class="btn" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </header>

        <!-- CONTENT -->
        <main class="container content">
            <h1 class="hello">¿Listo para gestionar tu sistema?</h1>
            <div id="frase-dia" style="margin-top:20px; font-style:italic; color:#6b7280;">
                Cargando frase motivacional...
            </div>


            <h2 id="modulos" style="margin:22px 0 8px;font-size:1.2rem">Módulos</h2>
            <div class="grid modules">
                <!-- Panel de Usuario (todos) -->
                <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/ui_panel_usuario.php">
                    <div class="icon"><i class="ico i-users"></i></div>
                    <div class="module-title">Panel de Usuario</div>
                    <div class="muted">Gestioná tu perfil y preferencias</div>
                </a>

                <!-- Administración (solo admin) -->
                <?php if (canAccess(['admin'], $rol)): ?>
                    <a class="card" href="../admin_panel/ui_admin.php">
                        <div class="icon"><i class="ico i-settings" style="color:var(--warn)"></i></div>
                        <div class="module-title">Administración</div>
                        <div class="muted">Usuarios, roles, permisos, parámetros</div>
                        <span class="pill" style="background:rgba(234,179,8,.18);color:#713f12">Admin</span>
                    </a>
                <?php endif; ?>

                <!-- Compra (admin o compra) -->
                <?php if (canAccess(['admin', 'compra'], $rol)): ?>
                    <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/menu/tabla_pedidos.html">
                        <div class="icon"><i class="ico i-bag"></i></div>
                        <div class="module-title">Módulo de Compra</div>
                        <div class="muted">Presupuestos, OCs, facturas</div>
                        <span class="pill buy">Compra</span>
                    </a>
                <?php endif; ?>

                <!-- Ventas (admin o venta) -->
                <?php if (canAccess(['admin', 'venta'], $rol)): ?>
                    <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/pedido/ui_pedido_nuevo.php">
                        <div class="icon"><i class="ico i-bag"></i></div>
                        <div class="module-title">Módulo de Ventas</div>
                        <div class="muted">Pedidos, facturas, caja</div>
                        <span class="pill sale">Venta</span>
                    </a>
                <?php endif; ?>

                <!-- Tesorería (admin o tesoreria) -->
                <?php if (canAccess(['admin', 'tesoreria'], $rol)): ?>
                    <a class="card" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/vista/ui_cargar_factura.php">
                        <div class="icon"><i class="ico i-cash"></i></div>
                        <div class="module-title">Módulo de Tesorería</div>
                        <div class="muted">Movimientos, conciliación, depósitos</div>
                        <span class="pill cash">Tesorería</span>
                    </a>
                <?php endif; ?>
            </div>
        </main>

        <footer class="container">© <?= date('Y') ?> Ofertas del Container — Panel v1.0</footer>
    </div>

    <script>
        // Atajos (g + u / v / c)
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
    </script>
    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/cargar_imagen_perfil.js"></script>
    <!-- Frase motivacional -->
    <script>
        const frases = [
            "El éxito es la suma de pequeños esfuerzos repetidos día tras día.",
            "No cuentes los días, haz que los días cuenten.",
            "La disciplina tarde o temprano vencerá a la inteligencia.",
            "Haz hoy lo que otros no quieren, haz mañana lo que otros no pueden.",
            "Tu actitud, no tu aptitud, determinará tu altitud.",
            "La motivación es lo que te pone en marcha, el hábito es lo que hace que sigas.",
            "Nunca es demasiado tarde para ser lo que podrías haber sido.",
            "El único modo de hacer un gran trabajo es amar lo que haces.",
            "El fracaso derrota a los perdedores e inspira a los ganadores.",
            "Cree en ti y todo será posible."
        ];

        function fraseAleatoria() {
            const index = Math.floor(Math.random() * frases.length);
            document.getElementById("frase-dia").innerText = frases[index];
        }

        document.addEventListener("DOMContentLoaded", fraseAleatoria);
    </script>
</body>

</html>