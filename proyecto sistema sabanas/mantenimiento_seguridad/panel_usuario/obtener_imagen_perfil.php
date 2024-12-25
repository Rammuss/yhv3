<?php
session_start();
include '../../conexion/configv2.php'; // Incluye tu conexión a la base de datos

if (!isset($_SESSION['nombre_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$nombre_usuario = $_SESSION['nombre_usuario'];

// Consulta la imagen de perfil
$query = "SELECT imagen_perfil FROM usuarios WHERE nombre_usuario = $1";
$result = pg_query_params($conn, $query, array($nombre_usuario));

$imagen_perfil = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/imagenes_perfil/default.png'; // Imagen predeterminada

if ($result && pg_num_rows($result) > 0) {
    $row = pg_fetch_assoc($result);
    if (!empty($row['imagen_perfil'])) {
        $imagen_perfil = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/panel_usuario/imagenes_perfil/' . $row['imagen_perfil'];
    }
}

echo json_encode(['success' => true, 'imagen_perfil' => $imagen_perfil]);
?>
