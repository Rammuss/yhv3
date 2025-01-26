<?php
// Datos de conexión a la base de datos
include "../../conexion/configv2.php";

// Obtener el id_factura de la URL
if (isset($_GET['id_factura'])) {
    $id_factura = $_GET['id_factura'];
} else {
    die(json_encode(["message" => "Error: id_factura no proporcionado."]));
}

try {
    // 1. Obtener los datos de la factura
    $query_factura = "
        SELECT id_factura, iva_5, iva_10
        FROM facturas_cabecera_t
        WHERE id_factura = $1 AND iva_generado = FALSE;
    ";
    $result_factura = pg_query_params($conn, $query_factura, array($id_factura));

    if (pg_num_rows($result_factura) === 0) {
        die(json_encode(["message" => "Factura no encontrada o ya se generó el IVA."]));
    }

    $factura = pg_fetch_assoc($result_factura);

    // 2. Insertar los IVAs en la tabla ivas_generados
    $query_iva = "
        INSERT INTO ivas_generados (
            id_factura, iva_5, iva_10, id_usuario_generacion
        )
        VALUES ($1, $2, $3, $4)
        RETURNING *;
    ";

    $id_usuario_generacion = 1; // Aquí puedes obtener el ID del usuario desde la sesión o parámetro

    $params_iva = array(
        $factura['id_factura'],
        $factura['iva_5'],
        $factura['iva_10'],
        $id_usuario_generacion,
    );

    $result_iva = pg_query_params($conn, $query_iva, $params_iva);

    if (!$result_iva) {
        die(json_encode(["message" => "Error al generar el IVA: " . pg_last_error()]));
    }

    $iva_generado = pg_fetch_assoc($result_iva);

    // 3. Actualizar el campo iva_generado en facturas_cabecera_t
    $query_update_factura = "
        UPDATE facturas_cabecera_t
        SET iva_generado = TRUE
        WHERE id_factura = $1;
    ";
    $result_update_factura = pg_query_params($conn, $query_update_factura, array($id_factura));

    if (!$result_update_factura) {
        die(json_encode(["message" => "Error al actualizar el estado de la factura: " . pg_last_error()]));
    }

    // 4. Responder con el IVA generado
    echo json_encode([
        "message" => "IVA generado exitosamente.",
        "iva_generado" => $iva_generado,
    ]);
} catch (Exception $e) {
    die(json_encode(["message" => "Error interno del servidor: " . $e->getMessage()]));
}

// Cerrar la conexión
pg_close($conn);
?>