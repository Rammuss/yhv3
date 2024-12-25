// Función para obtener los parámetros de consulta de la URL
function getQueryParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

// Mostrar la ventana emergente si el parámetro 'status' es 'success'
document.addEventListener('DOMContentLoaded', function () {
    const status = getQueryParameter('status');
    if (status === 'success') {
        showPopup('Registro exitoso');
    }
});

// Función para mostrar la ventana emergente y ocultarla después de un tiempo
function showPopup(message) {
    const popup = document.getElementById('popup');
    const popupMessage = document.getElementById('popup-message');
    const popupClose = document.getElementById('popup-close');

    popupMessage.textContent = message;
    popup.style.display = 'block';

    // Ocultar la ventana emergente después de 3 segundos (3000 ms)
    setTimeout(function() {
        popup.classList.add('popup-hide');
        // Opcionalmente, ocultar el mensaje completamente después de la transición
        setTimeout(function() {
            popup.style.display = 'none';
            popup.classList.remove('popup-hide');
        }, 500); // 500 ms es el tiempo de la transición
    }, 3000); // 3000 ms es el tiempo antes de ocultar el mensaje

    // Manejar el clic en el botón de cerrar
    popupClose.addEventListener('click', function() {
        popup.classList.add('popup-hide');
        setTimeout(function() {
            popup.style.display = 'none';
            popup.classList.remove('popup-hide');
        }, 500);
    });
}
