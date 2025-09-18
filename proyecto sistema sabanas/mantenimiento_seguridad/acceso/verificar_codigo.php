<?php
// verificar_2fa.php
session_start();
include("../../conexion/configv2.php");
header('Content-Type: application/json');

$response = ['success' => false, 'mensaje' => ''];

$userId = $_SESSION['pending_2fa_user_id'] ?? null;
$code   = trim($_POST['codigo'] ?? '');

if (!$userId) {
    $response['mensaje'] = "Sesión no válida. Inicia sesión de nuevo.";
    echo json_encode($response);
    exit;
}

if (!preg_match('/^\d{6}$/', $code)) {
    $response['mensaje'] = "Código inválido.";
    echo json_encode($response);
    exit;
}

$hash = hash('sha256', $code);

// Traer el último código activo, no usado y vigente
$sql = "SELECT id, code_hash, expires_at, attempts
        FROM two_factor_codes
        WHERE user_id = $1 AND used = false AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1";
$res = pg_query_params($conn, $sql, [(int)$userId]);

if (!$res || pg_num_rows($res) === 0) {
    $response['mensaje'] = "Código expirado o no encontrado. Pedí uno nuevo.";
    echo json_encode($response);
    exit;
}

$row = pg_fetch_assoc($res);

// Demasiados intentos
if ((int)$row['attempts'] >= 5) {
    $response['mensaje'] = "Demasiados intentos. Pedí un código nuevo.";
    echo json_encode($response);
    exit;
}

// Comparación segura del hash
if (hash_equals($row['code_hash'], $hash)) {
    // Marcar como usado
    pg_query_params($conn, "UPDATE two_factor_codes SET used = true WHERE id = $1", [$row['id']]);

    // Regenerar ID de sesión (mitiga session fixation)
    session_regenerate_id(true);

    // Traer datos del usuario (tu PK es 'id', la alias como 'id_usuario')
    $ru = pg_query_params(
        $conn,
        "SELECT id AS id_usuario, nombre_usuario
           FROM public.usuarios
          WHERE id = $1
          LIMIT 1",
        [(int)$userId]
    );

    if ($ru && pg_num_rows($ru) > 0) {
        $U = pg_fetch_assoc($ru);

        // Setear claves que esperan tus páginas protegidas
        $_SESSION['id_usuario']     = (int)$U['id_usuario'];
        $_SESSION['nombre_usuario'] = $U['nombre_usuario'] ?? '';
        $_SESSION['auth']           = true;

        // Limpiar el pending del 2FA
        unset($_SESSION['pending_2fa_user_id']);

        $response['success']  = true;
        $response['mensaje']  = "Código verificado. ¡Bienvenido!";
        $response['redirect'] = "/caja/abrir.php";
        echo json_encode($response);
        exit;
    } else {
        $response['mensaje'] = "Usuario no encontrado.";
        echo json_encode($response);
        exit;
    }

} else {
    // Incrementar intentos
    pg_query_params($conn, "UPDATE two_factor_codes SET attempts = attempts + 1 WHERE id = $1", [$row['id']]);

    $response['mensaje'] = "Código incorrecto. Intentá de nuevo.";
    echo json_encode($response);
    exit;
}
