<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['nombre_usuario'])) {
    // Si no hay un usuario autenticado, redirige al usuario a la página de inicio de sesión
    header("Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Usuario</title>
    <link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/menu/styles.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
            display: grid;
            justify-items: center;
        }

        .cerrar-sesion {
            border: 1px solid #ddd;
            margin: 5px;
            display: inline-block;
            /* Asegura que se muestre como un bloque en línea */
            padding: 10px 20px;
            /* Espaciado interior para hacer el botón más grande */
            background-color: #fff;
            /* Color de fondo rojo */
            color: green;
            /* Color de texto blanco */
            font-size: 16px;
            /* Tamaño de la fuente */
            font-weight: bold;
            /* Texto en negrita */
            text-decoration: none;
            /* Quita el subrayado del enlace */
            border-radius: 5px;
            /* Bordes redondeados */
            transition: background-color 0.3s ease, transform 0.2s ease;
            /* Efectos de transición */
        }

        .cerrar-sesion:hover {
            background-color: #f4f4f4;
            /* Color más oscuro al pasar el cursor */
            transform: scale(1.05);
            /* Efecto de escala cuando se pasa el mouse */
        }

        .cerrar-sesion:active {
            background-color: #f4f4f4;
            /* Color aún más oscuro cuando se hace clic */
            transform: scale(1);
            /* Vuelve al tamaño original */
        }


        .panel-usuario {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 90%;
            justify-content: center;
            display: block;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .panel-usuario form {
            margin-bottom: 20px;
        }

        .panel-usuario label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .panel-usuario input[type="file"],
        .panel-usuario input[type="email"],
        .panel-usuario input[type="password"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .panel-usuario button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
        }

        .panel-usuario button:hover {
            background-color: #45a049;
        }

        .panel-usuario button:focus,
        .panel-usuario input:focus {
            outline: none;
            border-color: #4CAF50;
        }
    </style>
</head>

<body>


    <div class="panel-usuario">

        <div>
            <a class="cerrar-sesion" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I//proyecto sistema sabanas//mantenimiento_seguridad//dashboard//logout.php">
                <img title="Cerrar Sesion" src="/TALLER DE ANALISIS Y PROGRAMACIÓN I//proyecto sistema sabanas//mantenimiento_seguridad//panel_usuario//iconosPerfil//cerrar-sesion.png" alt="">
            </a>
            <a class="cerrar-sesion" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/dashboard/dashboard.php">Dash Board</a>
            <a class="cerrar-sesion" href="javascript:history.back()">
                <img title="Volver Atras" src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas//mantenimiento_seguridad//panel_usuario/iconosPerfil/hacia-atras.png" alt="Volver atrás" />
            </a>
        </div>

        <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></h2> <!-- Muestra el nombre de usuario -->
        <!-- Formulario para actualizar la imagen de perfil -->
        <div id="perfil-contenedor">
            <img class="img_perfil" id="imagen-perfil" src="imagenes_perfil/default.png" alt="Imagen de Perfil" width="150" height="150">
        </div>

        <form id="form-imagen-perfil" method="POST" enctype="multipart/form-data">
            <label for="imagen_perfil">Selecciona una nueva imagen de perfil:</label>
            <input type="file" name="imagen_perfil" accept="image/*" required>
            <button type="submit">Actualizar Imagen</button>
        </form>

        <div id="resultado"></div> <!-- Para mostrar mensajes de éxito o error -->


        <div style="border-top: 1px solid #ccc; margin: 20px 0;"></div>
        <!-- Formulario para cambiar el correo -->
        <div id="correo-actual">
            <p><strong>Correo actual:</strong> <span id="correo-usuario">Cargando...</span></p>
        </div>

        <form id="form-cambiar-email">
            <label for="nuevo_email">Nuevo correo:</label>
            <input type="email" id="nuevo_email" name="nuevo_email" placeholder="Introduce el correo a ser asginado a su usuario" required>
            <button type="submit">Actualizar Correo</button>
        </form>
        <div id="mensaje-respuesta-email"></div>

        <div style="border-top: 1px solid #ccc; margin: 20px 0;"></div>
        <!-- Formulario para cambiar la contraseña -->
        <form id="form-cambiar-contrasena">
            <label for="contraseña_actual">Contraseña actual:</label>
            <input type="password" id="contraseña_actual" name="contraseña_actual" placeholder="Introduce tu contraseña actual" required>
            <label for="nueva_contraseña">Nueva contraseña:</label>
            <input type="password" id="nueva_contraseña" name="nueva_contraseña" placeholder="Introduce tu nueva contraseña" required>
            <button type="submit">Cambiar Contraseña</button>
        </form>

        <div id="mensaje-respuesta"></div>

    </div>
</body>

<script>
    document.getElementById('form-imagen-perfil').addEventListener('submit', function(event) {
        event.preventDefault(); // Evitar el envío tradicional del formulario

        const formData = new FormData(this);

        fetch('cambio_imagen.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                const resultadoDiv = document.getElementById('resultado');
                if (data.success) {
                    resultadoDiv.innerHTML = "<p>Imagen de perfil actualizada correctamente.</p>";
                    resultadoDiv.style.color = 'green';
                    // Actualizar la imagen en el panel
                    const imagenesPerfil = document.querySelectorAll('.img_perfil');
                    const timestamp = new Date().getTime(); // Añadir un timestamp para evitar el cacheo

                    imagenesPerfil.forEach(imagen => {
                        imagen.src = `/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/imagenes_perfil/${data.imageName}?t=${timestamp}`;
                    });

                } else {
                    resultadoDiv.innerHTML = `<p>Error: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error al actualizar la imagen:', error);
                document.getElementById('resultado').innerHTML = "<p>Error al enviar la imagen. Intenta de nuevo.</p>";
            });
    });
</script>

<script>
    // Hacer la solicitud asíncrona para obtener el correo
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

    // Llamar a la función al cargar la página
    obtenerCorreoActual();
</script>

<script>
    document.getElementById('form-cambiar-email').addEventListener('submit', async function(event) {
        event.preventDefault(); // Previene el envío por defecto del formulario

        // Obtener el valor del campo de correo electrónico
        const nuevoEmail = document.getElementById('nuevo_email').value;

        try {
            // Enviar la solicitud asíncrona al servidor usando fetch
            const response = await fetch('cambio_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    nuevo_email: nuevoEmail
                })
            });

            // Procesar la respuesta del servidor
            const data = await response.json();

            // Mostrar mensaje al usuario
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
    document.getElementById('form-cambiar-contrasena').addEventListener('submit', async function(event) {
        event.preventDefault(); // Evita que el formulario se envíe de manera predeterminada

        // Obtener los valores de los campos
        const contrasenaActual = document.getElementById('contraseña_actual').value;
        const nuevaContrasena = document.getElementById('nueva_contraseña').value;

        try {
            // Enviar la solicitud asíncrona al servidor usando fetch
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

            // Procesar la respuesta del servidor
            const data = await response.json();

            // Mostrar mensaje al usuario
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


</html>