<?php
session_start();
include("../../conexion/configv2.php"); // Asegúrate de incluir la conexión

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'];
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash de la nueva contraseña

    // Verifica el token antes de actualizar
    $query = "SELECT email FROM recuperacion_contrasena WHERE token = $1";
    $result = pg_query_params($conn, $query, array($token));

    if (pg_num_rows($result) > 0) {
        $email = pg_fetch_result($result, 0, 'email');

        // Iniciar una transacción para asegurar consistencia
        pg_query($conn, "BEGIN");

        // Actualizar la contraseña y los campos de intentos fallidos y bloqueado
        $query = "UPDATE usuarios SET contrasena = $1, intentos_fallidos = 0, bloqueado = false WHERE email = $2";
        $update_result = pg_query_params($conn, $query, array($new_password, $email));

        if ($update_result && pg_affected_rows($update_result) > 0) {
            // Eliminar el token usado
            $delete_query = "DELETE FROM recuperacion_contrasena WHERE token = $1";
            $delete_result = pg_query_params($conn, $delete_query, array($token));

            if ($delete_result && pg_affected_rows($delete_result) > 0) {
                pg_query($conn, "COMMIT"); // Confirmar la transacción
                echo "Contraseña actualizada exitosamente.";
            } else {
                pg_query($conn, "ROLLBACK"); // Revertir en caso de error
                echo "Error al eliminar el token: " . pg_last_error($conn);
            }
        } else {
            pg_query($conn, "ROLLBACK"); // Revertir en caso de error
            echo "Error al actualizar la contraseña: " . pg_last_error($conn);
        }
    } else {
        echo "Token inválido.";
    }
}
?>
<script>
    setTimeout(() => {
        window.location.href = "/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html"; // Cambia a la página deseada
    }, 2000);
</script>
