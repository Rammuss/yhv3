<?php
// anular_orden_pago.php

// Establecer conexión con la base de datos PostgreSQL
include "../../conexion/configv2.php";

// Verificar que se reciba el ID de la orden a anular
if (isset($_GET['id_orden'])) {
    $id_orden = $_GET['id_orden'];

    // Validar que el ID de la orden es un número entero
    if (!is_numeric($id_orden)) {
        echo json_encode(['success' => false, 'message' => 'ID de orden no válido']);
        exit;
    }

    // Actualizar el estado de la orden a 'Anulada'
    $query = "UPDATE ordenes_pago SET estado = 'Anulada' WHERE id = $1"; // Usar parámetros para evitar inyección SQL
    $result = pg_query_params($conn, $query, array($id_orden));

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Orden de pago anulada exitosamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al anular la orden de pago.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID de orden no recibido.']);
}

// Cerrar la conexión
pg_close($conn);
?>
