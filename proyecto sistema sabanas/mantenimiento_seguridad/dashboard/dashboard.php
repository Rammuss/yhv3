<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    header('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}

$rol = $_SESSION['rol']; // Acceder al rol del usuario
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <title>Dashboard Global</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.3/css/bulma.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Estilos personalizados -->
    <style>
        /* Colores personalizados para los módulos */
        .card-compra {
            border-left: 5px solid #3498db; /* Azul para Compra */
        }

        .card-venta {
            border-left: 5px solid #2ecc71; /* Verde para Ventas */
        }

        .card-tesoreria {
            border-left: 5px solid #e67e22; /* Naranja para Tesorería */
        }

        .card-compra .button {
            background-color: #3498db; /* Azul para el botón de Compra */
            border-color: #3498db;
        }

        .card-venta .button {
            background-color: #2ecc71; /* Verde para el botón de Ventas */
            border-color: #2ecc71;
        }

        .card-tesoreria .button {
            background-color: #e67e22; /* Naranja para el botón de Tesorería */
            border-color: #e67e22;
        }

        .card-compra .button:hover,
        .card-venta .button:hover,
        .card-tesoreria .button:hover {
            opacity: 0.9; /* Efecto hover para los botones */
        }

        .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-5px); /* Efecto hover para las cards */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <!-- Hero Section: Bienvenida al usuario -->
    <section class="hero is-primary is-bold">
        <div class="hero-body">
            <div class="container">
                <h1 class="title">
                    Bienvenido, <?php echo $_SESSION['nombre_usuario']; ?>
                </h1>
                <h2 class="subtitle">
                    ¿Listo para gestionar tu sistema?
                </h2>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">

            <!-- Botón de Cerrar Sesión -->
            <div class="columns">
                <div class="column is-12 has-text-right">
                    <a class="button is-danger is-rounded" href="logout.php">
                        <img title="Cerrar Sesion" src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/iconosPerfil/cerrar-sesion.png" alt="Cerrar sesión" class="mr-2"/>
                        Cerrar Sesión
                    </a>
                </div>
            </div>

            <!-- Dashboard Cards -->
            <div class="columns is-multiline">
                <!-- Card Panel de Usuario -->
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="card">
                        <div class="card-content has-text-centered">
                            <a href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/ui_panel_usuario.php">
                                <img src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/imagenes_perfil/default.png"
                                     alt="Imagen de Perfil" class="image is-128x128 is-rounded img_perfil mb-4">
                                <h3 class="title is-5">Panel de Usuario</h3>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Card Administración (Solo para admin) -->
                <?php if ($rol == 'admin'): ?>
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="card">
                        <div class="card-content">
                            <h3 class="title is-5">Administración</h3>
                            <a class="button is-link is-fullwidth" href="../dashboard/en_construccion.php">Ir al módulo de Administración</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Card Módulo de Compra (admin o compra) -->
                <?php if ($rol == 'admin' || $rol == 'compra'): ?>
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="card card-compra">
                        <div class="card-content">
                            <h3 class="title is-5">Módulo de Compra</h3>
                            <a class="button is-fullwidth" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/menu/tabla_prespuestos.html">Ir al módulo de Compra</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Card Módulo de Ventas (admin o venta) -->
                <?php if ($rol == 'admin' || $rol == 'venta'): ?>
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="card card-venta">
                        <div class="card-content">
                            <h3 class="title is-5">Módulo de Ventas</h3>
                            <a class="button is-fullwidth" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v2/ui_apertura_cierre_caja.php">Ir al módulo de Ventas</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Card Módulo de Tesorería (admin o tesoreria) -->
                <?php if ($rol == 'admin' || $rol == 'tesoreria'): ?>
                <div class="column is-12-mobile is-6-tablet is-4-desktop">
                    <div class="card card-tesoreria">
                        <div class="card-content">
                            <h3 class="title is-5">Módulo de Tesorería</h3>
                            <a class="button is-fullwidth" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/vista/ui_cargar_factura.php">Ir al módulo de Tesorería</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

</body>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/cargar_imagen_perfil.js"></script>

</html>