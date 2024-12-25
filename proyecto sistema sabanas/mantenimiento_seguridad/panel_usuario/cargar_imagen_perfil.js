
// <img id="imagen-perfil" src="imagenes_perfil/default.png" alt="Imagen de Perfil" width="150" height="150">
async function cargarImagenPerfil() {
    try {
        const response = await fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/obtener_imagen_perfil.php');
        const data = await response.json();

        if (data.success) {
            // Actualiza el src de la imagen con la ruta obtenida
            const imagenesPerfil = document.querySelectorAll('.img_perfil');
            console.log('Imágenes seleccionadas:', imagenesPerfil);

            imagenesPerfil.forEach(imagen => { imagen.src = data.imagen_perfil; });
        } else {
            console.error('Error al obtener la imagen de perfil:', data.message);
        }
    } catch (error) {
        console.error('Error de red al obtener la imagen de perfil:', error);
    }
}

// Llama a la función cuando la página esté lista
document.addEventListener('DOMContentLoaded', cargarImagenPerfil);
