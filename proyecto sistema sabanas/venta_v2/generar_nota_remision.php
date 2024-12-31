<?php
// Conexión a la base de datos
include '../conexion/configv2.php';

// Recibir datos del frontend
$data = json_decode(file_get_contents('php://input'), true);
$numero_factura = $data['numero_factura'];

try {
    // Iniciar una transacción
    pg_query($conn, "BEGIN");

    // Consultar datos de la cabecera de venta
    $queryCabecera = "SELECT * FROM ventas WHERE numero_factura = $1";
    $resultCabecera = pg_query_params($conn, $queryCabecera, [$numero_factura]);

    if (!$resultCabecera || pg_num_rows($resultCabecera) === 0) {
        throw new Exception("Factura no encontrada");
    }

    $venta = pg_fetch_assoc($resultCabecera);

    // Insertar en nota_remision_cabecera
    $queryInsertCabecera = "
        INSERT INTO nota_remision_venta_cabecera (cliente_id, fecha, estado, numero_factura)
        VALUES ($1, NOW(), 'pendiente', $2)
        RETURNING id_remision;
    ";
    $resultInsertCabecera = pg_query_params($conn, $queryInsertCabecera, [
        $venta['cliente_id'],
        $numero_factura
    ]);

    if (!$resultInsertCabecera) {
        throw new Exception("Error al insertar en la cabecera de la nota de remisión");
    }

    $id_remision = pg_fetch_result($resultInsertCabecera, 0, 'id_remision');

    // Consultar detalles de la venta
    $queryDetalle = "SELECT * FROM detalle_venta WHERE venta_id = $1";
    $resultDetalle = pg_query_params($conn, $queryDetalle, [$venta['id']]);

    if (!$resultDetalle) {
        throw new Exception("Error al consultar los detalles de la venta");
    }

    // Insertar en nota_remision_detalle
    $queryInsertDetalle = "
        INSERT INTO nota_remision_venta_detalle (remision_id, producto_id, cantidad, precio_unitario)
        VALUES ($1, $2, $3, $4);
    ";

    while ($detalle = pg_fetch_assoc($resultDetalle)) {
        $resultInsertDetalle = pg_query_params($conn, $queryInsertDetalle, [
            $id_remision,
            $detalle['producto_id'],
            $detalle['cantidad'],
            $detalle['precio_unitario']
        ]);

        if (!$resultInsertDetalle) {
            throw new Exception("Error al insertar en los detalles de la nota de remisión");
        }
    }

    // Confirmar la transacción
    pg_query($conn, "COMMIT");

    echo json_encode(['success' => true, 'message' => 'Nota de remisión generada exitosamente']);
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    pg_query($conn, "ROLLBACK");
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    // Cerrar la conexión
    pg_close($conn);
}
