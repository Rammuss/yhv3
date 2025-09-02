<!DOCTYPE html>
<html lang="es">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Registro de Usuario</title>
    <link rel="stylesheet" href="notificacion.css">
    <style>
        /* Estilos para pantallas pequeñas */
        /* Estilos para pantallas pequeñas */
        @media (max-width: 600px) {
            body {
                padding: 0 10px;
            }

            h2 {
                font-size: 1.5em;
                /* Ajusta el tamaño de la fuente */
            }

            form {
                width: 100%;
                /* Ancho completo en pantallas pequeñas */
                padding: 10px;
                margin: 0;
                /* Quita margen horizontal */
            }

            input[type="text"],
            input[type="password"],
            input[type="tel"],
            input[type="email"],
            select,
            input[type="submit"],
            input[type="button"] {
                padding: 8px;
                /* Reducir padding para ahorrar espacio */
                font-size: 1em;
                /* Ajustar el tamaño de fuente */
            }

            table,
            thead,
            tbody,
            th,
            td,
            tr {
                display: block;
                /* Convierte la tabla en un diseño de bloque en pantallas pequeñas */
            }

            th,
            td {
                font-size: 14px;
                /* Reducir el tamaño de la fuente */
                padding: 6px;
                /* Reducir el padding para celdas */
                text-align: left;
            }

            thead tr {
                display: none;
                /* Oculta el encabezado de la tabla */
            }

            tr {
                margin-bottom: 10px;
                /* Añadir espacio entre las filas */
                border: 1px solid #ddd;
                /* Opcional: bordes para mejorar la legibilidad */
            }

            td::before {
                content: attr(data-label);
                /* Usa un atributo para mostrar el nombre de la columna */
                font-weight: bold;
                display: block;
            }

            .btn-eliminar,
            .btn-modificar {
                padding: 6px 8px;
                font-size: 0.9em;
            }

            #form_delete {
                width: 90%;
                /* Ancho mayor del formulario modal */
                padding: 15px;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
        }

        h2 {
            text-align: center;
        }

        form {
            margin: 0 auto;
            width: 300px;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="password"],
        input[type="tel"],
        input[type="email"],
        select {
            width: 100%;
            padding: 5px;
            margin-bottom: 10px;
        }

        select {
            height: 30px;
        }

        input[type="submit"],
        input[type="button"] {
            background-color: #007BFF;
            color: #fff;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
        }

        input[type="submit"]:hover,
        input[type="button"]:hover {
            background-color: #0056b3;
        }

        table {
            border-collapse: collapse;
            width: 80%;
            margin: 20px auto;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
        }

        .btn-eliminar {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            margin: 5px;
        }

        .btn-modificar {
            background-color: #007BFF;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            margin: 5px;
        }

        .btn-eliminar:hover {
            background-color: darkred;
        }

        .btn-modificar:hover {
            background-color: darkblue;
        }


        #overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            /* Fondo oscuro semi-transparente */
            display: none;
            /* Inicialmente oculto */
            z-index: 1000;
            /* Asegúrate de que esté por encima de otros elementos */
        }

        #form_delete {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            /* Centra el formulario */
            background-color: white;
            /* Fondo blanco */
            border-radius: 8px;
            /* Bordes redondeados */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            /* Sombra para un efecto de elevación */
            padding: 20px;
            /* Espaciado interno */
            z-index: 1001;
            /* Asegúrate de que esté por encima del overlay */
            width: 300px;
            /* Ancho fijo, ajusta según tu diseño */
        }

        #form_delete label {
            display: block;
            /* Cada etiqueta en una nueva línea */
            margin-bottom: 5px;
            /* Espacio entre la etiqueta y el campo */
        }

        #form_delete input,
        #form_delete select {
            width: 100%;
            /* Campos llenan el ancho del formulario */
            padding: 8px;
            /* Espaciado interno en los campos */
            margin-bottom: 15px;
            /* Espacio entre campos */
            border: 1px solid #ccc;
            /* Borde claro */
            border-radius: 4px;
            /* Bordes redondeados en los campos */
        }


        #form_delete select {
            width: 100%;
            /* Campos llenan el ancho del formulario */
            padding: 0px;
            /* Espaciado interno en los campos */
            margin-bottom: 15px;
            /* Espacio entre campos */
            border: 1px solid #ccc;
            /* Borde claro */
            border-radius: 4px;
            /* Bordes redondeados en los campos */
        }

        #form_delete button {
            padding: 10px;
            /* Espaciado interno en botones */
            border: none;
            /* Sin borde */
            border-radius: 4px;
            /* Bordes redondeados */
            cursor: pointer;
            /* Cambia el cursor al pasar por encima */
        }

        #form_delete button[type="submit"] {
            background-color: #4CAF50;
            /* Color de fondo para guardar cambios */
            color: white;
            /* Color del texto */
        }

        #form_delete button[type="button"] {
            background-color: #f44336;
            /* Color de fondo para cerrar */
            color: white;
            /* Color del texto */
        }
    </style>
</head>

<body>
    <h2>Registro de Usuario</h2>
    <form id="myForm" action="registrar.php" method="post" onsubmit="return validarFormulario();">
        <label for="usuario">Usuario:</label>
        <input type="text" id="usuario" name="usuario" required>

        <label for="contrasena">Contraseña:</label>
        <input type="password" id="contrasena" name="contrasena" required>

        <label for="confirmar_contrasena">Confirmar Contraseña:</label>
        <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="telefono">Número de Teléfono:</label>
        <input type="tel" id="telefono" name="telefono" required>


        <label for="rol">Rol:</label>
        <select id="rol" name="rol">
            <option value="compra">compra</option>
            <option value="venta">venta</option>
            <option value="tesoreria">tesoreria</option>
            <option value="admin">admin</option>
        </select>

        <input type="submit" value="Registrarse">
        <input type="button" value="Limpiar" onclick="limpiarFormulario()">

    </form>

    <div id="notificacion" class="notificacion"></div>


    <table id="tablaUsuarios">
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Telefono</th>
                <th>Email</th>
                <th>Accion</th>
            </tr>
        </thead>

        <tbody>

        </tbody>
    </table>
    <!-- Resto de tu código JavaScript -->
</body>
<script src="notificacion.js"></script>

</html>

<script>
    function validarFormulario() {
        // Verificar si las contraseñas coinciden
        var contrasena = document.getElementById("contrasena").value;
        var confirmarContrasena = document.getElementById("confirmar_contrasena").value;

        if (contrasena !== confirmarContrasena) {
            mostrarNotificacion("Error: Las contraseñas no coinciden. Por favor, inténtalo de nuevo.");
            return false; // Evitar el envío del formulario
        }

        // Llamar a la función de validación de contraseña
        const resultadoValidacion = validarContrasena(contrasena);
        if (resultadoValidacion !== true) {
            mostrarNotificacion(resultadoValidacion); // Mostrar mensaje de error
            return false; // Evitar el envío del formulario
        }

        // Si todo está bien, enviar el formulario
        return true;
    }

    function validarContrasena(password) {
        const longitudMinima = 8;

        // Verifica la longitud de la contraseña
        if (password.length < longitudMinima) {
            return `La contraseña debe tener al menos ${longitudMinima} caracteres.`;
        }

        // Verifica que tenga al menos una letra mayúscula
        if (!/[A-Z]/.test(password)) {
            return "La contraseña debe tener al menos una letra mayúscula.";
        }

        // Verifica que tenga al menos una letra minúscula
        if (!/[a-z]/.test(password)) {
            return "La contraseña debe tener al menos una letra minúscula.";
        }

        // Verifica que tenga al menos un número
        if (!/[0-9]/.test(password)) {
            return "La contraseña debe tener al menos un número.";
        }

        // Verifica que tenga al menos un carácter especial
        if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            return "La contraseña debe tener al menos un carácter especial.";
        }

        return true; // La contraseña es segura
    }

    document.getElementById("myForm").addEventListener("submit", function(event) {
        // Evitar el envío del formulario si hay errores
        if (!validarFormulario()) {
            event.preventDefault(); // Evita el envío del formulario
        } else {
            // Crear un objeto FormData para enviar los datos del formulario
            const formData = new FormData(event.target);

            // Realizar una solicitud fetch al servidor
            fetch("registrar.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    mostrarNotificacion(data); // Usar la función de notificación
                    cargarUsuarios();
                    // Limpiar el formulario solo si la respuesta indica éxito
                    if (data.includes('Usuario registrado exitosamente')) { // Asegúrate de que este mensaje coincida con el que envías desde el servidor
                        limpiarFormulario();
                    }




                })
                .catch(error => {
                    console.error("Error:", error);
                    mostrarNotificacion("Ha ocurrido un error"); // Usar la función de notificación
                });

            // Evitar que el formulario se envíe de forma tradicional
            event.preventDefault();
        }
    });
</script>

<script>
    // Función para limpiar el formulario
    function limpiarFormulario() {
        document.getElementById("myForm").reset(); // Limpia todos los campos del formulario
    }
</script>




<script>
    function cargarUsuarios() {
        const tbody = document.getElementById("tablaUsuarios").querySelector("tbody");
        tbody.innerHTML = ""; // Vaciar contenido actual de la tabla

        fetch("obtener_usuarios.php")
            .then(response => response.json())
            .then(data => {
                data.forEach(usuario => {
                    const row = document.createElement("tr");

                    // Crear celdas para ID, nombre de usuario, rol y estado
                    const idCell = document.createElement("td");
                    idCell.textContent = usuario.id;
                    idCell.setAttribute("data-label", "ID"); // Agregar data-label

                    const nombreUsuarioCell = document.createElement("td");
                    nombreUsuarioCell.textContent = usuario.nombre_usuario;
                    nombreUsuarioCell.setAttribute("data-label", "Usuario"); // Agregar data-label

                    const rolCell = document.createElement("td");
                    rolCell.textContent = usuario.rol;
                    rolCell.setAttribute("data-label", "Rol"); // Agregar data-label

                    const estadoCell = document.createElement("td");
                    estadoCell.textContent = usuario.estado;
                    estadoCell.setAttribute("data-label", "Estado"); // Agregar data-label

                    const telefonoCell = document.createElement("td");
                    telefonoCell.textContent = usuario.telefono;
                    telefonoCell.setAttribute("data-label", "Teléfono"); // Agregar data-label

                    const emailCell = document.createElement("td");
                    emailCell.textContent = usuario.email;
                    emailCell.setAttribute("data-label", "Email"); // Agregar data-label

                    const accionCell = document.createElement("td");
                    accionCell.setAttribute("data-label", "Acción"); // Agregar data-label

                    // Crear celda para el botón de modificar
                    const modificarBoton = document.createElement("button");
                    modificarBoton.textContent = "Modificar";
                    modificarBoton.classList.add("btn-modificar");
                    modificarBoton.onclick = () => modificarUsuario(usuario.id); // Llama a la función de modificar

                    // Crear celda para el botón de eliminar
                    const eliminarBoton = document.createElement("button");
                    eliminarBoton.textContent = "Eliminar";
                    eliminarBoton.classList.add("btn-eliminar");
                    eliminarBoton.onclick = () => eliminarUsuario(usuario.id); // Llama a la función de eliminar

                    accionCell.appendChild(modificarBoton);
                    accionCell.appendChild(eliminarBoton);

                    // Agregar celdas a la fila
                    row.appendChild(idCell);
                    row.appendChild(nombreUsuarioCell);
                    row.appendChild(rolCell);
                    row.appendChild(estadoCell);
                    row.appendChild(telefonoCell);
                    row.appendChild(emailCell);
                    row.appendChild(accionCell);

                    // Agregar la fila al tbody de la tabla
                    tbody.appendChild(row);
                });
            })
            .catch(error => console.error("Error al cargar usuarios:", error));
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Cargar la tabla de usuarios al cargar la página
        cargarUsuarios();
    });
</script>


<script>
    function eliminarUsuario(id) {
        if (confirm("¿Estás seguro de que deseas eliminar este usuario?")) {
            fetch(`eliminar_usuario.php?id=${id}`, {
                    method: 'PATCH' // Usamos PATCH para actualizar el estado del usuario
                })
                .then(response => {
                    if (response.ok) {
                        // Actualización exitosa
                        cargarUsuarios(); // Recargar la tabla después de "eliminar"
                        mostrarNotificacion('Usuario eliminado exitosamente');
                    } else {
                        console.error("Error al eliminar usuario:", response.statusText);
                        mostrarNotificacion('No se pudo eliminar');

                    }
                })
                .catch(error => console.error("Error al eliminar usuario:", error));
        }
    }
</script>

<script>
    function modificarUsuario(id) {
        // Aquí puedes obtener el usuario por ID y cargar sus datos en un formulario para editarlos.
        // Por simplicidad, aquí solo voy a mostrar un prompt. Debes sustituirlo con un formulario adecuado.

        fetch(`obtener_usuarios.php?id=${id}`)
            .then(response => response.json())
            .then(usuario => {
                // Suponiendo que tienes un formulario con campos para modificar el usuario
                document.getElementById("nombre_usuarioDL").value = usuario.nombre_usuario;
                document.getElementById("rolDL").value = usuario.rol;
                document.getElementById("telefonoDL").value = usuario.telefono;
                document.getElementById("emailDL").value = usuario.email;
                // También debes almacenar el ID en un campo oculto o una variable para saber qué usuario estás modificando
                document.getElementById("usuario_id").value = usuario.id;

                // Mostrar el formulario y el overlay
                mostrarFormularioModificar();
            })
            .catch(error => console.error("Error al obtener el usuario:", error));
    }
</script>
<div id="overlay"></div>
<form id="form_delete" style="display:none;">
    <input type="hidden" id="usuario_id" />
    <label for="nombre_usuarioDL">Nombre de Usuario:</label>
    <input type="text" id="nombre_usuarioDL" required />

    <label for="rolDL">Rol:</label>
    <select id="rolDL" name="rolDL">
        <option value="compra">compra</option>
        <option value="venta">venta</option>
        <option value="tesoreria">tesoreria</option>
        <option value="admin">admin</option>
    </select>

    <label for="telefonoDL">Teléfono:</label>
    <input type="text" id="telefonoDL" required />

    <label for="emailDL">Email:</label>
    <input type="emailDL" id="emailDL" required />

    <button type="submit">Guardar Cambios</button>
    <button type="button" onclick="cerrarFormulario()">Cerrar</button>
</form>

<script>
    function mostrarFormularioModificar() {
        document.getElementById("form_delete").style.display = 'block';
        document.getElementById("overlay").style.display = 'block'; // Mostrar overlay
    }

    function cerrarFormulario() {
        document.getElementById("form_delete").style.display = 'none';
        document.getElementById("overlay").style.display = 'none'; // Ocultar overlay
    }
</script>

<script>
    document.getElementById("form_delete").addEventListener("submit", function(e) {
        e.preventDefault(); // Prevenir el envío normal del formulario

        const id = document.getElementById("usuario_id").value;
        const nombre = document.getElementById("nombre_usuarioDL").value;
        const rol = document.getElementById("rolDL").value;
        const telefono = document.getElementById("telefonoDL").value;
        const email = document.getElementById("emailDL").value;

        // Realiza la actualización con AJAX o fetch
        fetch("actualizar_usuario.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    id,
                    nombre_usuario: nombre,
                    rol,
                    telefono,
                    email
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Cargar de nuevo los usuarios
                    cargarUsuarios();
                    mostrarNotificacion("Usuario Actualizado")
                    cerrarFormulario()
                    // Limpiar el formulario y ocultarlo
                    document.getElementById("form_delete").reset();
                    document.getElementById("form_delete").style.display = 'none';
                } else {
                    alert("Error al actualizar el usuario: " + data.message);
                }
            })
            .catch(error => console.error("Error al actualizar el usuario:", error));
    });
</script>

</body>

</html>