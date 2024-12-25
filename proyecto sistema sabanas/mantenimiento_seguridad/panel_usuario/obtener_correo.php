<?php
session_start();
include("../../conexion/configv2.php");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['nombre_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$usuario = $_SESSION['nombre_usuario'];
$query = "SELECT email FROM usuarios WHERE nombre_usuario = $1";
$result = pg_query_params($conn, $query, array($usuario));

if ($result) {
    $correo = pg_fetch_result($result, 0, 'email');
    echo json_encode(['success' => true, 'correo' => $correo]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al obtener el correo']);
}
?>
