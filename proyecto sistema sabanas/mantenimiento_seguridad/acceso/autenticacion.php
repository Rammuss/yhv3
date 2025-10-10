<?php
session_start();
include("../../conexion/configv2.php");

header('Content-Type: application/json');
$response = ['success' => false, 'mensaje' => ''];

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Autoloader de Composer (ajusta si tu ruta cambia)
$projectRoot = realpath(__DIR__ . '/../../..');
require $projectRoot . '/vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $response['mensaje'] = "Por favor, complete todos los campos.";
        echo json_encode($response); exit();
    }

    $query = "SELECT * FROM usuarios WHERE nombre_usuario = $1 LIMIT 1";
    $result = pg_query_params($conn, $query, [$username]);

    if ($result && pg_num_rows($result) > 0) {
        $user = pg_fetch_assoc($result);

        if ($user['bloqueado'] === 't') {
            $response['mensaje'] = "Tu cuenta está bloqueada. Por favor, contacta al administrador.";
            echo json_encode($response); exit();
        }

        if (password_verify($password, $user['contrasena'])) {
            // Reinicia intentos fallidos
            pg_query_params($conn, "UPDATE usuarios SET intentos_fallidos = 0 WHERE id = $1", [$user['id']]);

            // (Opcional) Rate-limit: evita reenviar si se envió hace < 60s
            $rl = pg_query_params($conn,
                "SELECT created_at FROM two_factor_codes WHERE user_id=$1 AND used=false
                 ORDER BY created_at DESC LIMIT 1", [$user['id']]);
            if ($rl && pg_num_rows($rl) > 0) {
                $last = pg_fetch_assoc($rl);
                if (time() - strtotime($last['created_at']) < 60) {
                    $response['success'] = true;
                    $response['mensaje'] = "Ya enviamos un código recientemente. Revisá tu correo.";
                    $response['redirect'] = "ui_verificar_codigo.php";
                    // Guarda sesión para la siguiente etapa
                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                    $_SESSION['nombre_usuario'] = $user['nombre_usuario'];
                    $_SESSION['rol'] = $user['rol'];
                    echo json_encode($response); exit();
                }
            }

            // === Genera y guarda código 2FA ===
            $codigo_verificacion = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); // 000000-999999
            $codeHash = hash('sha256', $codigo_verificacion);
            $expiresAt = date('Y-m-d H:i:s', time() + 5 * 60); // 5 minutos

            $ins = pg_query_params($conn,
                "INSERT INTO two_factor_codes (user_id, code_hash, expires_at) VALUES ($1, $2, $3)",
                [$user['id'], $codeHash, $expiresAt]
            );
            if (!$ins) {
                $response['mensaje'] = "No se pudo generar el código. Intenta de nuevo.";
                echo json_encode($response); exit();
            }

            // Guarda lo mínimo en sesión
            $_SESSION['pending_2fa_user_id'] = $user['id'];
            $_SESSION['nombre_usuario'] = $user['nombre_usuario'];
            $_SESSION['rol'] = $user['rol'];

            // === Enviar el código por correo con PHPMailer ===
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();

                // --- DEV con MailHog (para pruebas locales) ---
                $mail->Host     = '127.0.0.1';
                $mail->Port     = 1025;
                $mail->SMTPAuth = false;
                $mail->CharSet  = 'UTF-8';
                $mail->SMTPDebug = 2; $mail->Debugoutput = 'error_log';

                

                // Remitente verificado en Brevo
                $mail->setFrom('mmarcoscaceres@gmail.com', 'Sistema compra,venta,tesoreria');

                // Destinatario: correo del usuario (de la BD)
                $mail->addAddress($user['email']);

                $mail->Subject = 'Tu código de verificación (2FA)';
                $plain = "Tu código de verificación es: {$codigo_verificacion}\n"
                       . "Vence en 5 minutos.\nSi no solicitaste este código, ignora este mensaje.";
                $html  = "<p>Tu código de verificación es: <strong style=\"font-size:18px;\">{$codigo_verificacion}</strong></p>"
                       . "<p>Vence en <strong>5 minutos</strong>.</p>"
                       . "<p>Si no solicitaste este código, ignora este mensaje.</p>";

                $mail->isHTML(true);
                $mail->Body    = $html;
                $mail->AltBody = $plain;

                $mail->send();

                $response['success'] = true;
                $response['mensaje'] = "Código de verificación enviado al correo.";
                $response['redirect'] = "ui_verificar_codigo.php";
                echo json_encode($response); exit();

            } catch (Exception $e) {
                error_log('2FA mail error: ' . $mail->ErrorInfo);
                $response['mensaje'] = "Hubo un error al enviar el correo de verificación.";
                echo json_encode($response); exit();
            }

        } else {
            // Password incorrecto: suma intentos y bloquea si corresponde
            pg_query_params($conn, "UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = $1", [$user['id']]);
            $newCount = $user['intentos_fallidos'] + 1;
            if ($newCount >= 3) {
                pg_query_params($conn, "UPDATE usuarios SET bloqueado = TRUE WHERE id = $1", [$user['id']]);
                $response['mensaje'] = "Tu cuenta ha sido bloqueada por demasiados intentos fallidos.";
            } else {
                $response['mensaje'] = "Usuario o contraseña incorrectos. Te quedan " . (3 - $newCount) . " intentos.";
            }
            echo json_encode($response); exit();
        }
    } else {
        $response['mensaje'] = "Usuario o contraseña incorrectos.";
        echo json_encode($response); exit();
    }
}
session_write_close();