<?php
header("Content-Type: application/json"); // Establecer el tipo de contenido como JSON

include "../../conexion/configv2.php";

// Obtener los datos enviados por el frontend
$data = json_decode(file_get_contents("php://input"), true);

// Validar que los datos necesarios estén presentes
if (
    !isset($data["numero_factura"]) ||
    !isset($data["id_proveedor"]) ||
    !isset($data["fecha_emision"]) ||
    !isset($data["iva_5"]) ||
    !isset($data["iva_10"]) ||
    !isset($data["descuento"]) ||
    !isset($data["total"]) ||
    !isset($data["detalles"])
) {
    echo json_encode(["error" => "Datos incompletos"]);
    exit;
}

try {
    // Iniciar una transacción
    pg_query($conn, "BEGIN");

    // Insertar en la tabla facturas_cabecera_T
    $query_cabecera = "
        INSERT INTO facturas_cabecera_T (
            numero_factura, id_proveedor, fecha_emision,
            iva_5, iva_10, descuento, total, estado_pago, id_usuario_creacion
        ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
        RETURNING id_factura;
    ";

    $result_cabecera = pg_query_params($conn, $query_cabecera, [
        $data["numero_factura"],
        $data["id_proveedor"],
        $data["fecha_emision"],
        $data["iva_5"],
        $data["iva_10"],
        $data["descuento"],
        $data["total"],
        $data["estado_pago"] ?? "Pendiente", // Estado por defecto: Pendiente
        $data["id_usuario_creacion"] ?? null // Usuario opcional
    ]);

    if (!$result_cabecera) {
        throw new Exception("Error al insertar en facturas_cabecera_T");
    }

    // Obtener el ID de la factura recién insertada
    $row = pg_fetch_assoc($result_cabecera);
    $id_factura = $row["id_factura"];

    // Insertar en la tabla facturas_detalle_T
    foreach ($data["detalles"] as $detalle) {
        $query_detalle = "
            INSERT INTO facturas_detalle_T (
                id_factura, descripcion, cantidad, precio_unitario
            ) VALUES ($1, $2, $3, $4);
        ";

        $result_detalle = pg_query_params($conn, $query_detalle, [
            $id_factura,
            $detalle["descripcion"],
            $detalle["cantidad"],
            $detalle["precio_unitario"]
        ]);

        if (!$result_detalle) {
            throw new Exception("Error al insertar en facturas_detalle_T");
        }
    }

    // Confirmar la transacción
    pg_query($conn, "COMMIT");

    // Devolver una respuesta exitosa
    echo json_encode(["success" => true, "id_factura" => $id_factura]);
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    pg_query($conn, "ROLLBACK");
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    // Cerrar la conexión
    pg_close($conn);
}
?>