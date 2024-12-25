


<?php
session_start();  // Inicia la sesión
session_unset();  // Libera todas las variables de sesión
session_destroy();  // Destruye la sesión

// Redirige al usuario a la página de login o inicio (ajusta la ruta según la ubicación de tu archivo)
header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
exit();
?>
