<?php
session_start();
include("../../conexion/configv2.php"); // Asegúrate de incluir la conexión

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    // Verificar si el correo está registrado en la base de datos
    $query = "SELECT id FROM usuarios WHERE email = $1";
    $result = pg_query_params($conn, $query, array($email));

    if (pg_num_rows($result) > 0) {
        $token = bin2hex(random_bytes(16)); // Generar un token aleatorio
        $expiry_time = time() + 3600; // Expira en 1 hora
        $expiry = date('Y-m-d H:i:s', $expiry_time); // Convertir a formato de fecha


        // Almacenar el token y su expiración en la base de datos
        $query = "INSERT INTO recuperacion_contrasena (email, token, expiry) VALUES ($1, $2, $3)";
        pg_query_params($conn, $query, array($email, $token, $expiry));

        // Enviar el correo con el enlace para restablecer la contraseña
        $reset_link = "http://localhost/TALLER%20DE%20ANALISIS%20Y%20PROGRAMACI%C3%93N%20I/proyecto%20sistema%20sabanas/mantenimiento_seguridad/acceso/ui_restablecer_contrasena.php?token=$token";
        $subject = "Restablecer tu Contraseña";

        $message = "Haz clic en el siguiente enlace para restablecer tu contraseña: $reset_link";
        mail($email, $subject, $message); // Asegúrate de que la función mail esté configurada correctamente

        echo "Se ha enviado un enlace para restablecer la contraseña a tu correo.";
    } else {
        echo "No se encontró ningún usuario con ese correo.";
    }
}
