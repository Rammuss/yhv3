<?php
session_start();
include '../../../conexion/configv2.php';
// Configurar el encabezado para JSON
header('Content-Type: application/json');

// Recibir datos del formulario
$timbrado = $_POST['timbrado'];
$rango_inicio = $_POST['rango_inicio'];
$rango_fin = $_POST['rango_fin'];
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin = $_POST['fecha_fin'];

try {
    // Preparar la consulta SQL
    $query = "INSERT INTO rango_facturas (timbrado, rango_inicio, rango_fin, actual, fecha_inicio, fecha_fin, activo) 
              VALUES ('$timbrado', $rango_inicio, $rango_fin, " . ($rango_inicio - 1) . ", '$fecha_inicio', '$fecha_fin', FALSE)";
    
    // Ejecutar la consulta
    $result = pg_query($conn, $query);

    if ($result) {
        // Responder con éxito
        echo json_encode(['success' => true, 'message' => 'Rango de facturas cargado exitosamente.']);
    } else {
        // Responder con error
        echo json_encode(['success' => false, 'message' => 'Error al cargar el rango: ' . pg_last_error($conn)]);
    }

    // Cerrar la conexión
    pg_close($conn);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al cargar el rango: ' . $e->getMessage()]);
}
?>
