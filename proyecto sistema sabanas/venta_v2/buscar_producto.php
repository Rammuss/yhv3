<?php
include '../conexion/configv2.php';

header('Content-Type: application/json');

// Obtener el término de búsqueda enviado desde el cliente
$searchTerm = isset($_GET['query']) ? $_GET['query'] : '';

// Consulta para buscar productos según el término
$query = "SELECT id_producto, nombre, tipo_iva, precio_unitario FROM producto WHERE nombre ILIKE $1 LIMIT 10";
$result = pg_query_params($conn, $query, ['%' . $searchTerm . '%']);

// Depuración para ver si la consulta devuelve resultados
if ($result) {
    $productos = pg_fetch_all($result);
    // Verifica si los productos están siendo obtenidos correctamente
    if ($productos) {
        echo json_encode($productos);
    } else {
        echo json_encode([]);  // Si no hay productos
    }
} else {
    echo json_encode(['error' => 'Error en la consulta SQL']);  // Error en la consulta
}

pg_close($conn);
