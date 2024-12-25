<?php
include '../conexion/configv2.php';

header('Content-Type: application/json');

try {
    $searchTerm = $_GET['search'] ?? '';
    $query = "SELECT id_cliente, nombre, apellido, ruc_ci 
              FROM clientes 
              WHERE nombre ILIKE '%$searchTerm%' 
                 OR apellido ILIKE '%$searchTerm%' 
                 OR ruc_ci ILIKE '%$searchTerm%'
              ORDER BY nombre ASC LIMIT 10";

    $result = pg_query($conn, $query);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener los clientes.']);
        exit;
    }

    $clientes = [];
    while ($row = pg_fetch_assoc($result)) {
        $clientes[] = $row;
    }

    echo json_encode(['success' => true, 'clientes' => $clientes]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

pg_close($conn);
?>
