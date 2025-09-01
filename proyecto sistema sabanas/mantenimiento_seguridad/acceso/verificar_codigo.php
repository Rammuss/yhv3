<?php
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

// Trae el último código activo, no usado y vigente
$sql = "SELECT id, code_hash, expires_at, attempts
        FROM two_factor_codes
        WHERE user_id = $1 AND used = false AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1";
$res = pg_query_params($conn, $sql, [$userId]);

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

// Comparación segura
if (hash_equals($row['code_hash'], $hash)) {
    // Marca usado
    pg_query_params($conn, "UPDATE two_factor_codes SET used = true WHERE id = $1", [$row['id']]);

    // Autentica definitivamente al usuario
    $_SESSION['auth'] = true;

    $response['success']  = true;
    $response['mensaje']  = "Código verificado. ¡Bienvenido!";
    $response['redirect'] = "dashboard.php";
    echo json_encode($response);
    exit;
} else {
    // Suma intento
    pg_query_params($conn, "UPDATE two_factor_codes SET attempts = attempts + 1 WHERE id = $1", [$row['id']]);

    $response['mensaje'] = "Código incorrecto. Intentá de nuevo.";
    echo json_encode($response);
    exit;
}
