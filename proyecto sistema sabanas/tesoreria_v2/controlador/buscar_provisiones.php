<?php
// backend/buscar_provisiones.php

// Configuración de conexión
include "../../conexion/configv2.php";

// Recibir datos del cuerpo de la solicitud
$data = json_decode(file_get_contents('php://input'), true);

// Construir la consulta SQL base
$sql = "SELECT * FROM provisiones_cuentas_pagar WHERE 1=1";
$conditions = [];
$params = [];

// Añadir condiciones según los parámetros recibidos
$index = 1;

if (!empty($data['fecha'])) {
    $conditions[] = "DATE(fecha_creacion) = $" . $index;
    $params[] = $data['fecha'];
    $index++;
}

if (!empty($data['ruc'])) {
    $conditions[] = "id_proveedor IN (SELECT id_proveedor FROM proveedores WHERE ruc = $" . $index . ")";
    $params[] = $data['ruc'];
    $index++;
}

if (!empty($data['estado'])) {
    $conditions[] = "estado_provision = $" . $index;
    $params[] = $data['estado'];
    $index++;
}

// Combinar condiciones con la consulta
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Preparar y ejecutar la consulta
$result = pg_query_params($conn, $sql, $params);

if (!$result) {
    die("Error al ejecutar la consulta: " . pg_last_error($conn));
}

// Obtener los resultados en un arreglo asociativo
$provisiones = [];
while ($row = pg_fetch_assoc($result)) {
    $provisiones[] = $row;
}

// Liberar resultados y cerrar conexión
pg_free_result($result);
pg_close($conn);

// Devolver resultados como JSON
header('Content-Type: application/json');
echo json_encode($provisiones);
?>
