// cargar_nombre_usuario.js

function cargarNombreUsuario() {
    fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/obtener_nombre_usuario.php')
        .then(response => response.json())
        .then(data => {
            const nombreUsuario = data.nombre_usuario || 'Invitado'; // Valor predeterminado si no hay nombre
            const usuarioSpan = document.getElementById('nombre-usuario');
            if (usuarioSpan) {
                usuarioSpan.textContent = nombreUsuario;
            }
        })
        .catch(error => console.error('Error al obtener el nombre de usuario:', error));
}
