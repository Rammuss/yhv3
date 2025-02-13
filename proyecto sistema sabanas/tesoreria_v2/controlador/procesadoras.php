<?php
// api/procesadoras.php

// Indicar que la respuesta será en formato JSON
header('Content-Type: application/json');

// Incluir el archivo de configuración para la conexión a la base de datos
require_once '../../conexion/config_pdo.php';

try {
    // Consulta para obtener todas las procesadoras ordenadas por nombre
    $sql = "SELECT id, nombre, descripcion FROM procesadoras ORDER BY nombre ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    // Recuperar los resultados como arreglo asociativo
    $procesadoras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retornar la lista en formato JSON
    echo json_encode($procesadoras);
} catch (PDOException $e) {
    // En caso de error, se retorna un JSON con el mensaje de error y se establece el código HTTP 500
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
