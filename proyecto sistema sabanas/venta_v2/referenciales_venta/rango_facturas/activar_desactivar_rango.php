<?php
include '../../../conexion/configv2.php';

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

// Recibir parámetros
$id = $data['id'];
$accion = $data['accion']; // Puede ser 'activar' o 'desactivar'

try {
    if ($accion === 'activar') {
        // Desactivar todos los rangos activos
        pg_query($conn, "UPDATE rango_facturas SET activo = FALSE WHERE activo = TRUE");
        // Activar el rango seleccionado
        $result = pg_query($conn, "UPDATE rango_facturas SET activo = TRUE WHERE id = $id");
        $message = 'Rango activado correctamente.';
    } elseif ($accion === 'desactivar') {
        // Desactivar solo el rango seleccionado
        $result = pg_query($conn, "UPDATE rango_facturas SET activo = FALSE WHERE id = $id");
        $message = 'Rango desactivado correctamente.';
    } else {
        throw new Exception('Acción no válida.');
    }

    if ($result) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        throw new Exception('Error al realizar la operación.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

pg_close($conn);
?>
