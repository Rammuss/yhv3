<?php
session_start();
include("../../conexion/configv2.php");

header('Content-Type: application/json');
$response = ['success' => false, 'mensaje' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $response['mensaje'] = "Por favor, complete todos los campos.";
        echo json_encode($response);
        exit();
    }

    $query = "SELECT * FROM usuarios WHERE nombre_usuario = $1";
    $result = pg_query_params($conn, $query, array($username));

    if (pg_num_rows($result) > 0) {
        $user = pg_fetch_assoc($result);

        if ($user['bloqueado'] === 't') {
            $response['mensaje'] = "Tu cuenta está bloqueada. Por favor, contacta al administrador.";
            echo json_encode($response);
            exit();
        }

        if (password_verify($password, $user['contrasena'])) {
            $updateQuery = "UPDATE usuarios SET intentos_fallidos = 0 WHERE id = $1";
            pg_query_params($conn, $updateQuery, array($user['id']));

            // Genera y envía el código de verificación
            $codigo_verificacion = rand(100000, 999999); // Genera un código de 6 dígitos
            $_SESSION['codigo_verificacion'] = $codigo_verificacion; // Guarda el código en la sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nombre_usuario'] = $user['nombre_usuario'];
            $_SESSION['rol'] = $user['rol']; 


            // Enviar el código de verificación por correo
            $to = $user['email']; // Supongamos que tienes el correo del usuario en la base de datos
            $subject = 'Tu Código de Verificación';
            $message = "Tu código de verificación es: $codigo_verificacion";
            $headers = 'From: noreply@tuapp.com';

            if (mail($to, $subject, $message, $headers)) {
                $response['success'] = true;
                $response['mensaje'] = "Código de verificación enviado al correo.";
                $response['redirect'] = "ui_verificar_codigo.php"; // Página de verificación
            } else {
                $response['mensaje'] = "Hubo un error al enviar el correo de verificación.";
            }

            echo json_encode($response);
            exit();
        } else {
            $updateQuery = "UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = $1";
            pg_query_params($conn, $updateQuery, array($user['id']));

            if ($user['intentos_fallidos'] + 1 >= 3) {
                $blockQuery = "UPDATE usuarios SET bloqueado = TRUE WHERE id = $1";
                pg_query_params($conn, $blockQuery, array($user['id']));
                $response['mensaje'] = "Tu cuenta ha sido bloqueada por demasiados intentos fallidos.";
            } else {
                $response['mensaje'] = "Usuario o contraseña incorrectos. Tienes " . (3 - ($user['intentos_fallidos'] + 1)) . " intentos restantes.";
            }
            echo json_encode($response);
            exit();
        }
    } else {
        $response['mensaje'] = "Usuario o contraseña incorrectos.";
        echo json_encode($response);
        exit();
    }
}
?>
