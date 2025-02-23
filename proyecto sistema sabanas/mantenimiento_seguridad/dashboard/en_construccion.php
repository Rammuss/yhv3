<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prototipo de Página con Formularios</title>
    <style>
        /* Aplica el desenfoque a toda la pantalla */
        body {
            margin: 0;
            height: 100vh;
            overflow: hidden;
            position: relative;
            background-image: url('https://www.w3schools.com/w3images/nature.jpg');
            background-size: cover;
            background-position: center;
        }

        /* Capa de desenfoque */
        .blur-background {
            position: fixed;
            /* Fija la capa para que cubra toda la pantalla */
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(10px);
            /* Aplica el desenfoque */
            -webkit-backdrop-filter: blur(10px);
            /* Soporte para Safari */
            background-color: rgba(255, 255, 255, 0.5);
            /* Fondo semitransparente */
            z-index: 1;
            /* Coloca la capa detrás del contenido */
        }

        /* Estilo para el encabezado */
        .h1 {
            position: absolute;
            top: 20%;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 3rem;
            z-index: 2;
        }
    </style>
</head>

<body>

    <!-- Capa de desenfoque -->
    <div class="blur-background"></div>

    <!-- Encabezado encima del desenfoque -->
    <h1 class="h1">en construccion</h1>

    <!-- Barra de Navegación (Navbar) -->
    <nav>
        <ul>
            <li><a href="#">Inicio</a></li>
            <li><a href="#">Servicios</a></li>
            <li><a href="#">Contacto</a></li>
            <li><a href="#">Acerca de</a></li>
        </ul>
    </nav>

    <!-- Sección principal con Formulario de Registro -->
    <section>
        <h1>Formulario de Registro</h1>
        <form action="#" method="POST">
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" required>
            <br>

            <label for="email">Correo Electrónico:</label>
            <input type="email" id="email" name="email" required>
            <br>

            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>
            <br>

            <button type="submit">Registrarse</button>
        </form>
    </section>

    <!-- Formulario de inicio de sesión -->
    <section>
        <h1>Iniciar Sesión</h1>
        <form action="#" method="POST">
            <label for="email-login">Correo Electrónico:</label>
            <input type="email" id="email-login" name="email-login" required>
            <br>

            <label for="password-login">Contraseña:</label>
            <input type="password" id="password-login" name="password-login" required>
            <br>

            <button type="submit">Iniciar Sesión</button>
        </form>
    </section>

    <!-- Formulario de contacto -->
    <section>
        <h1>Formulario de Contacto</h1>
        <form action="#" method="POST">
            <label for="nombre-contacto">Nombre:</label>
            <input type="text" id="nombre-contacto" name="nombre-contacto" required>
            <br>

            <label for="email-contacto">Correo Electrónico:</label>
            <input type="email" id="email-contacto" name="email-contacto" required>
            <br>

            <label for="mensaje">Mensaje:</label>
            <textarea id="mensaje" name="mensaje" rows="4" required></textarea>
            <br>

            <button type="submit">Enviar</button>
        </form>
    </section>

</body>

</html>
