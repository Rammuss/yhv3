<?php
// backend/buscar_ivas.php

// Configuración de conexión
include "../../conexion/configv2.php";

// Recibir datos del cuerpo de la solicitud
$data = json_decode(file_get_contents('php://input'), true);

$sql = "
    SELECT 
        ivg.id_iva, 
        fc.numero_factura, 
        ivg.iva_5, 
        ivg.iva_10, 
        ivg.fecha_generacion
    FROM ivas_generados ivg
    INNER JOIN facturas_cabecera_t fc ON ivg.id_factura = fc.id_factura
    WHERE 1=1
";
$params = [];
$index = 1;

// Filtrar por número de factura
if (!empty($data['numero_factura'])) {
    $sql .= " AND fc.numero_factura = $" . $index;
    $params[] = $data['numero_factura'];
    $index++;
}

// Filtrar por fecha
if (!empty($data['fecha'])) {
    $sql .= " AND DATE(ivg.fecha_generacion) = $" . $index;
    $params[] = $data['fecha'];
    $index++;
}

$result = pg_query_params($conn, $sql, $params);

if (!$result) {
    die("Error al ejecutar la consulta: " . pg_last_error($conn));
}

$ivas = [];
while ($row = pg_fetch_assoc($result)) {
    $ivas[] = $row;
}

pg_free_result($result);
pg_close($conn);

header('Content-Type: application/json');
echo json_encode($ivas);
?>
