<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beauty Creations | Verificar Código</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="notificacion.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-overlay: rgba(255, 255, 255, 0.55);
            --card-bg: rgba(255, 255, 255, 0.72);
            --accent: #d63384;
            --accent-dark: #b0276b;
            --text-main: #351421;
            --text-muted: #9f607d;
            --shadow-lg: 0 24px 48px rgba(53, 20, 33, 0.18);
            --radius-card: 22px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            font-family: "Poppins", system-ui, -apple-system, Segoe UI, sans-serif;
            color: var(--text-main);
            background-image:
                linear-gradient(120deg, rgba(241, 154, 200, 0.32), rgba(255, 235, 248, 0.55)),
                url("../panel_usuario/iconosPerfil/trincaje-de-contenedores.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: var(--bg-overlay);
            backdrop-filter: blur(6px);
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            width: min(420px, 100%);
            background: var(--card-bg);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-lg);
            padding: 42px 38px 36px;
            border: 1px solid rgba(214, 51, 132, 0.12);
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--accent);
            font-weight: 600;
            letter-spacing: 3px;
            text-transform: uppercase;
            font-size: 0.78rem;
        }

        .brand-badge::before,
        .brand-badge::after {
            content: "";
            width: 16px;
            height: 1px;
            background: currentColor;
            opacity: 0.6;
        }

        h2 {
            margin: 4px 0 0;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 1.95rem;
            font-weight: 600;
            letter-spacing: 0.8px;
        }

        p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 16px;
        }

        label {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-main);
        }

        input {
            border: 1px solid rgba(214, 51, 132, 0.2);
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.9);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            color: var(--text-main);
            letter-spacing: 2px;
            text-align: center;
            font-weight: 600;
        }

        input:focus {
            outline: none;
            border-color: rgba(214, 51, 132, 0.55);
            box-shadow: 0 0 0 4px rgba(214, 51, 132, 0.18);
        }

        button {
            margin-top: 4px;
            border: none;
            border-radius: 16px;
            padding: 13px 16px;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.4px;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, #d63384, #f072c1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 14px 24px rgba(214, 51, 132, 0.28);
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 30px rgba(214, 51, 132, 0.3);
        }

        button:active {
            transform: translateY(0);
            box-shadow: 0 10px 20px rgba(214, 51, 132, 0.28);
        }

        #notificacion {
            margin-top: 12px;
        }

        @media (max-width: 480px) {
            body {
                padding: 20px 12px;
            }

            .container {
                padding: 32px 26px 28px;
            }

            h2 {
                font-size: 1.7rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <span class="brand-badge">Beauty Creations</span>
        <h2>Confirmar Identidad</h2>
        <p>Ingresá el código que enviamos a tu correo o teléfono para continuar.</p>

        <form id="verificarForm">
            <label for="codigo">Código de verificación</label>
            <input type="text" id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" required>
            <button type="submit">Verificar</button>
        </form>

        <div id="notificacion" class="notificacion"></div>
    </div>

    <script>
        document.getElementById('verificarForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('verificar_codigo.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Error en la conexión");
                    }
                    return response.json();
                })
                .then(data => {
                    mostrarNotificacion(data.mensaje);
                    if (data.success) {
                        setTimeout(() => {
                            window.location.href = "/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/dashboard/dashboardv1.php";
                        }, 2000);
                    }
                })
                .catch(error => {
                    mostrarNotificacion(error.message);
                });
        });
    </script>
    <script src="notificacion.js"></script>
</body>

</html>
