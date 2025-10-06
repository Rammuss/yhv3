<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['nombre_usuario'])) {
    header("Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html");
    exit;
}

$nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beauty Creations | Panel de Usuario</title>
    <link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/menu/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: linear-gradient(130deg, #ffe5f4 0%, #f6f0ff 45%, #fef9ff 100%);
            --bg-dark: linear-gradient(135deg, #1f1024 0%, #2a1231 40%, #231027 100%);
            --surface: rgba(255, 255, 255, 0.86);
            --surface-dark: rgba(32, 19, 42, 0.82);
            --text: #411f31;
            --text-dark: #f7ecf8;
            --muted: #9d6f8b;
            --muted-dark: #c7abc4;
            --primary: #d63384;
            --primary-dark: #f5a6d0;
            --shadow: 0 28px 52px rgba(188, 70, 137, 0.22);
            --shadow-card: 0 18px 40px rgba(64, 21, 53, 0.16);
            --radius: 22px;
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
            min-height: 100vh;
            font-family: "Poppins", system-ui, -apple-system, Segoe UI, sans-serif;
            color: var(--text);
            background: var(--bg);
            position: relative;
            display: flex;
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
            top: -140px;
            left: -140px;
            background: rgba(214, 51, 132, 0.32);
        }

        body::after {
            bottom: -200px;
            right: -120px;
            background: rgba(126, 90, 255, 0.28);
        }

        .app {
            position: relative;
            z-index: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .container {
            width: min(1100px, 100%);
            margin: 0 auto;
            padding: 0 24px;
        }

        .topbar {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(214, 51, 132, 0.12);
            box-shadow: 0 10px 32px rgba(64, 21, 53, 0.12);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar__inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
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
            font-size: 2.1rem;
            letter-spacing: 0.6px;
        }

        .brand__subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(214, 51, 132, 0.18);
            color: var(--text);
            font-weight: 500;
            text-decoration: none;
            box-shadow: 0 10px 24px rgba(64, 21, 53, 0.12);
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 32px rgba(64, 21, 53, 0.18);
            background: rgba(255, 255, 255, 0.9);
        }

        .action-btn img {
            width: 20px;
            height: 20px;
        }

        main {
            flex: 1;
            padding: 42px 0 64px;
        }

        .welcome {
            margin: 0 0 28px;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2.3rem;
            letter-spacing: 0.6px;
        }

        .grid {
            display: grid;
            gap: 24px;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        .card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 28px;
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(214, 51, 132, 0.14);
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .card h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            font-family: "Playfair Display", "Poppins", serif;
        }

        .card p {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .avatar-frame {
            width: 160px;
            height: 160px;
            margin: 0 auto;
            border-radius: 26px;
            padding: 12px;
            background: rgba(214, 51, 132, 0.14);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 0 1px rgba(214, 51, 132, 0.18);
        }

        .avatar-frame img {
            width: 100%;
            height: 100%;
            border-radius: 20px;
            object-fit: cover;
            box-shadow: 0 12px 26px rgba(64, 21, 53, 0.18);
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        label {
            font-weight: 500;
            font-size: 0.95rem;
        }

        input[type="file"],
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(214, 51, 132, 0.24);
            background: rgba(255, 255, 255, 0.92);
            font-size: 0.98rem;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
            color: var(--text);
        }

        input:focus {
            outline: none;
            border-color: rgba(214, 51, 132, 0.55);
            box-shadow: 0 0 0 4px rgba(214, 51, 132, 0.16);
        }

        button[type="submit"],
        .card button {
            align-self: flex-start;
            border: none;
            border-radius: 16px;
            padding: 13px 20px;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.4px;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, #d63384, #f072c1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 14px 28px rgba(214, 51, 132, 0.28);
        }

        button[type="submit"]:hover,
        .card button:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 36px rgba(214, 51, 132, 0.32);
        }

        button[type="submit"]:active,
        .card button:active {
            transform: translateY(0);
            box-shadow: 0 12px 24px rgba(214, 51, 132, 0.28);
        }

        .divider {
            height: 1px;
            background: rgba(214, 51, 132, 0.12);
            margin: 10px 0;
        }

        .feedback {
            font-size: 0.95rem;
            font-weight: 500;
        }

        #resultado,
        #mensaje-respuesta-email,
        #mensaje-respuesta {
            min-height: 18px;
        }

        @media (max-width: 820px) {
            .topbar__inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .actions {
                width: 100%;
                justify-content: flex-start;
            }

            .brand__title {
                font-size: 1.9rem;
            }

            .welcome {
                font-size: 2rem;
            }
        }

        @media (max-width: 560px) {
            .container {
                padding: 0 18px;
            }

            .card {
                padding: 24px;
            }

            .avatar-frame {
                width: 140px;
                height: 140px;
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
                    <h1 class="brand__title">Panel Personal</h1>
                    <p class="brand__subtitle">Gestioná tu imagen, correo y credenciales con estilo.</p>
                </div>
                <nav class="actions">
                    <a class="action-btn" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/dashboard/dashboardv1.php">
                        <img src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/iconosPerfil/home.png" alt="">
                        Dashboard
                    </a>
                    <a class="action-btn" href="javascript:history.back()">
                        <img src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/iconosPerfil/hacia-atras.png" alt="">
                        Volver
                    </a>
                    <a class="action-btn" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/dashboard/logout.php">
                        <img src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/iconosPerfil/cerrar-sesion.png" alt="">
                        Cerrar sesión
                    </a>
                </nav>
            </div>
        </header>

        <main class="container">
            <h2 class="welcome">Hola, <?= htmlspecialchars($nombre) ?> ✨</h2>
            <div class="grid">
                <section class="card profile-card">
                    <h3>Tu imagen de perfil</h3>
                    <p>Mantené tu perfil actualizado para que el equipo siempre te identifique al instante.</p>

                    <div id="perfil-contenedor" class="avatar-frame">
                        <img class="img_perfil" id="imagen-perfil" src="imagenes_perfil/default.png" alt="Imagen de Perfil">
                    </div>

                    <form id="form-imagen-perfil" method="POST" enctype="multipart/form-data">
                        <label for="imagen_perfil">Subí una nueva imagen:</label>
                        <input type="file" id="imagen_perfil" name="imagen_perfil" accept="image/*" required>
                        <button type="submit">Actualizar imagen</button>
                    </form>

                    <div id="resultado" class="feedback"></div>
                </section>

                <section class="card">
                    <h3>Correo electrónico</h3>
                    <p>Actualizá el correo asociado a tu cuenta para recibir notificaciones y recordatorios.</p>

                    <div class="divider"></div>

                    <div id="correo-actual">
                        <p><strong>Correo actual:</strong> <span id="correo-usuario">Cargando...</span></p>
                    </div>

                    <form id="form-cambiar-email">
                        <label for="nuevo_email">Nuevo correo</label>
                        <input type="email" id="nuevo_email" name="nuevo_email" placeholder="ejemplo@beautycreations.com" required>
                        <button type="submit">Actualizar correo</button>
                    </form>

                    <div id="mensaje-respuesta-email" class="feedback"></div>
                </section>

                <section class="card">
                    <h3>Seguridad de tu cuenta</h3>
                    <p>Cambiá tu contraseña regularmente para mantener tu cuenta protegida.</p>

                    <div class="divider"></div>

                    <form id="form-cambiar-contrasena">
                        <label for="contraseña_actual">Contraseña actual</label>
                        <input type="password" id="contraseña_actual" name="contraseña_actual" placeholder="Tu contraseña vigente" required>

                        <label for="nueva_contraseña">Nueva contraseña</label>
                        <input type="password" id="nueva_contraseña" name="nueva_contraseña" placeholder="Nueva contraseña segura" required>

                        <button type="submit">Cambiar contraseña</button>
                    </form>

                    <div id="mensaje-respuesta" class="feedback"></div>
                </section>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('form-imagen-perfil').addEventListener('submit', function (event) {
            event.preventDefault();

            const formData = new FormData(this);

            fetch('cambio_imagen.php', {
                method: 'POST',
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                    const resultadoDiv = document.getElementById('resultado');
                    if (data.success) {
                        resultadoDiv.textContent = "Imagen de perfil actualizada correctamente.";
                        resultadoDiv.style.color = 'green';

                        const imagenesPerfil = document.querySelectorAll('.img_perfil');
                        const timestamp = new Date().getTime();

                        imagenesPerfil.forEach(imagen => {
                            imagen.src = `/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/imagenes_perfil/${data.imageName}?t=${timestamp}`;
                        });

                    } else {
                        resultadoDiv.textContent = `Error: ${data.message}`;
                        resultadoDiv.style.color = 'red';
                    }
                })
                .catch(error => {
                    console.error('Error al actualizar la imagen:', error);
                    const resultadoDiv = document.getElementById('resultado');
                    resultadoDiv.textContent = "Error al enviar la imagen. Intenta de nuevo.";
                    resultadoDiv.style.color = 'red';
                });
        });
    </script>

    <script>
        async function obtenerCorreoActual() {
            try {
                const response = await fetch('obtener_correo.php');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('correo-usuario').textContent = data.correo;
                } else {
                    document.getElementById('correo-usuario').textContent = 'Error al obtener el correo';
                }
            } catch (error) {
                console.error('Error al obtener el correo:', error);
                document.getElementById('correo-usuario').textContent = 'Error de red. Inténtalo más tarde.';
            }
        }

        obtenerCorreoActual();
    </script>

    <script>
        document.getElementById('form-cambiar-email').addEventListener('submit', async function (event) {
            event.preventDefault();

            const nuevoEmail = document.getElementById('nuevo_email').value;

            try {
                const response = await fetch('cambio_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        nuevo_email: nuevoEmail
                    })
                });

                const data = await response.json();

                const mensajeRespuesta = document.getElementById('mensaje-respuesta-email');
                if (data.success) {
                    mensajeRespuesta.textContent = 'Correo actualizado con éxito.';
                    mensajeRespuesta.style.color = 'green';
                    obtenerCorreoActual();
                } else {
                    mensajeRespuesta.textContent = `Error: ${data.message}`;
                    mensajeRespuesta.style.color = 'red';
                }
            } catch (error) {
                console.error('Error al actualizar el correo:', error);
                const mensajeRespuesta = document.getElementById('mensaje-respuesta-email');
                mensajeRespuesta.textContent = 'Error de red. Inténtalo de nuevo.';
                mensajeRespuesta.style.color = 'red';
            }
        });
    </script>

    <script>
        document.getElementById('form-cambiar-contrasena').addEventListener('submit', async function (event) {
            event.preventDefault();

            const contrasenaActual = document.getElementById('contraseña_actual').value;
            const nuevaContrasena = document.getElementById('nueva_contraseña').value;

            try {
                const response = await fetch('cambio_contrasena.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        contraseña_actual: contrasenaActual,
                        nueva_contraseña: nuevaContrasena
                    })
                });

                const data = await response.json();

                const mensajeRespuesta = document.getElementById('mensaje-respuesta');
                if (data.success) {
                    mensajeRespuesta.textContent = 'Contraseña cambiada con éxito.';
                    mensajeRespuesta.style.color = 'green';
                } else {
                    mensajeRespuesta.textContent = `Error: ${data.message}`;
                    mensajeRespuesta.style.color = 'red';
                }
            } catch (error) {
                console.error('Error al cambiar la contraseña:', error);
                const mensajeRespuesta = document.getElementById('mensaje-respuesta');
                mensajeRespuesta.textContent = 'Error de red. Inténtalo de nuevo.';
                mensajeRespuesta.style.color = 'red';
            }
        });
    </script>

    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/cargar_imagen_perfil.js"></script>
    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/cargar_nombre_usuario.js"></script>
</body>

</html>
