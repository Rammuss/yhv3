<?php
// Configuración de conexión a la base de datos
include "../conexion/configv2.php";

// Obtener datos del cuerpo de la solicitud
$input = json_decode(file_get_contents("php://input"), true);

// Validar y asignar parámetros
$numeroNota = isset($input['numeroNota']) ? trim($input['numeroNota']) : null;
$numeroFactura = isset($input['numeroFactura']) ? trim($input['numeroFactura']) : null;
$fechaNota = isset($input['fechaNota']) ? trim($input['fechaNota']) : null;
$ciRucNota = isset($input['ciRucNota']) ? trim($input['ciRucNota']) : null;
$tipo = isset($input['tipo']) ? trim($input['tipo']) : null; // Nuevo parámetro para tipo

try {
    // Construir la consulta SQL dinámicamente
    $query = "SELECT 
                ncd.id AS id_nota,
                ncd.venta_id AS numero_factura,
                CONCAT(c.nombre, ' ', c.apellido) AS cliente_nombre,
                c.ruc_ci AS ci_ruc,
                ncd.tipo,  -- Incluir el campo tipo en la selección
                ncd.monto,
                ncd.fecha,
                ncd.estado
              FROM notas_credito_debito ncd
              LEFT JOIN clientes c ON ncd.cliente_id = c.id_cliente
              WHERE 1 = 1";

    // Array para los parámetros
    $params = [];
    $paramIndex = 1;

    // Agregar condiciones según los criterios
    if (!empty($numeroNota)) {
        $query .= " AND ncd.id = $" . $paramIndex;
        $params[] = $numeroNota;
        $paramIndex++;
    }
    if (!empty($numeroFactura)) {
        $query .= " AND ncd.venta_id = $" . $paramIndex;
        $params[] = $numeroFactura;
        $paramIndex++;
    }
    if (!empty($fechaNota)) {
        $query .= " AND DATE(ncd.fecha) = $" . $paramIndex;
        $params[] = $fechaNota;
        $paramIndex++;
    }
    if (!empty($ciRucNota)) {
        $query .= " AND c.ruc_ci = $" . $paramIndex;
        $params[] = $ciRucNota;
        $paramIndex++;
    }
    if (!empty($tipo)) {
        $query .= " AND ncd.tipo = $" . $paramIndex;
        $params[] = $tipo;
        $paramIndex++;
    }

    // Preparar y ejecutar la consulta
    $result = pg_query_params($conn, $query, $params);

    if (!$result) {
        throw new Exception("Error al ejecutar la consulta.");
    }

    // Obtener resultados
    $resultados = [];
    while ($row = pg_fetch_assoc($result)) {
        $resultados[] = $row;
    }

    // Retornar resultados en formato JSON
    echo json_encode($resultados);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error: " . $e->getMessage()]);
} finally {
    // Cerrar conexión
    pg_close($conn);
}
?>
