<?php
session_start();
include("../../conexion/configv2.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// autoload (ajusta niveles si cambia la ubicación del archivo)
$projectRoot = realpath(__DIR__ . '/../../..');
require $projectRoot . '/vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');

    // (1) Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Correo inválido.";
        exit;
    }

    // (2) Buscar usuario
    $sql = "SELECT id FROM usuarios WHERE email = $1 LIMIT 1";
    $res = pg_query_params($conn, $sql, [$email]);
    if (!$res) {
        http_response_code(500);
        echo "Error consultando usuario.";
        exit;
    }

    if (pg_num_rows($res) > 0) {
        // (3) Token y expiración
        $token       = bin2hex(random_bytes(16));
        $expiry      = date('Y-m-d H:i:s', time() + 3600);

        // (4) Guardar token (tu tabla actual)
        $ins = "INSERT INTO recuperacion_contrasena (email, token, expiry) VALUES ($1, $2, $3)";
        $ok  = pg_query_params($conn, $ins, [$email, $token, $expiry]);
        if (!$ok) {
            http_response_code(500);
            echo "Error guardando token. Intenta de nuevo.";
            exit;
        }

        // (5) Link de restablecimiento
        $basePath  = "http://localhost/TALLER%20DE%20ANALISIS%20Y%20PROGRAMACI%C3%93N%20I/proyecto%20sistema%20sabanas/mantenimiento_seguridad/acceso/ui_restablecer_contrasena.php";
        $resetLink = $basePath . "?token=" . urlencode($token);

        // (6) Envío de correo con PHPMailer (PROD: Brevo)
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();

            // === DEV (MailHog) ===
            $mail->Host     = '127.0.0.1';
            $mail->Port     = 1025;
            $mail->SMTPAuth = false;
            $mail->SMTPDebug = 2;              // opcional
            $mail->Debugoutput = 'error_log';  // opcional

            

            // ⚠️ From debe ser un remitente VERIFICADO en Brevo
            $mail->setFrom('sistema@tusistema.com', 'Sistema');
            // opcional: Reply-To si querés recibir respuestas en tu Gmail
            // $mail->addReplyTo('mmarcoscaceres@gmail.com', 'Marcos');

            $mail->addAddress($email);

            $mail->Subject = 'Restablecer tu contraseña';
            $bodyText = "Usá este enlace para restablecer tu contraseña (válido por 1 hora):\n$resetLink\n\nSi no solicitaste esto, ignorá este correo.";
            $bodyHtml = "<p>Usá este enlace para restablecer tu contraseña (válido por 1 hora):</p>
                         <p><a href=\"$resetLink\">Restablecer contraseña</a></p>
                         <p>Si no solicitaste esto, ignorá este correo.</p>";

            $mail->isHTML(true);
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $bodyText;

            $mail->SMTPDebug = 2;              // o 3 para más detalle
            $mail->Debugoutput = 'error_log';  // manda el trace al error_log de PHP

            $mail->send();
            echo "Se ha enviado un enlace para restablecer la contraseña a tu correo.";
        } catch (Exception $e) {
            error_log('Error PHPMailer: ' . $mail->ErrorInfo);
            http_response_code(500);
            echo "No se pudo enviar el correo. Revisá los logs.";
        }
    } else {
        echo "No se encontró ningún usuario con ese correo.";
    }
}
