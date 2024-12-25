// Función para mostrar el mensaje de notificación


  function mostrarNotificacion(mensaje) {
    console.log("Mostrando notificación: " + mensaje);
    const notificacion = document.getElementById('notificacion');
    notificacion.textContent = mensaje;
    notificacion.classList.add('mostrar');

    // Ocultar la notificación después de 3 segundos
    setTimeout(() => {
        notificacion.classList.remove('mostrar');
    }, 3000);
}
