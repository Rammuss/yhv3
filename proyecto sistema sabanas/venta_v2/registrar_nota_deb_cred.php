<?php
// Configuración de la conexión a la base de datos
include "../conexion/configv2.php"; 

// Obtener datos JSON del frontend
$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true);

// Validar datos básicos
if (empty($data['cliente_id']) || empty($data['tipo']) || empty($data['monto']) || !isset($data['motivo']) || empty($data['id_venta'])) {
    http_response_code(400);
    echo json_encode(["error" => "Datos incompletos"]);
    exit;
}

// Validar detalles solo si el tipo es 'credito'
if ($data['tipo'] == 'credito' && empty($data['detalles'])) {
    http_response_code(400);
    echo json_encode(["error" => "Detalles incompletos para nota de crédito"]);
    exit;
}

try {
    // Iniciar transacción
    pg_query($conn, "BEGIN");

    // Insertar cabecera
    $queryCabecera = "
        INSERT INTO notas_credito_debito (cliente_id, tipo, monto, venta_id, motivo)
        VALUES ($1, $2, $3, $4, $5)
        RETURNING id
    ";
    $resultCabecera = pg_query_params($conn, $queryCabecera, [
        $data['cliente_id'],
        $data['tipo'],
        $data['monto'],
        $data['id_venta'], // Aquí se utiliza id_venta del frontend
        $data['motivo']
    ]);

    if (!$resultCabecera) {
        throw new Exception("Error al insertar la cabecera");
    }

    $notaId = pg_fetch_result($resultCabecera, 0, "id");

    // Insertar detalles solo si existen
    if (!empty($data['detalles'])) {
        $queryDetalle = "
            INSERT INTO detalle_notas_credito_debito (nota_id, producto_id, cantidad, precio_unitario)
            VALUES ($1, $2, $3, $4)
        ";

        foreach ($data['detalles'] as $detalle) {
            $resultDetalle = pg_query_params($conn, $queryDetalle, [
                $notaId,
                $detalle['producto_id'],
                $detalle['cantidad'],
                $detalle['precio_unitario']
            ]);

            if (!$resultDetalle) {
                throw new Exception("Error al insertar el detalle del producto ID " . $detalle['producto_id']);
            }
        }
    }

    // Confirmar transacción
    pg_query($conn, "COMMIT");

    // Responder con el ID de la nota creada
    echo json_encode(["success" => true, "nota_id" => $notaId]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    pg_query($conn, "ROLLBACK");
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} finally {
    // Cerrar conexión
    pg_close($conn);
}
?>
