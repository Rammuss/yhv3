<?php
// api/cuentas_bancarias.php

// Establece el Content-Type de la respuesta a JSON
header('Content-Type: application/json');

// Incluye el archivo de configuración que contiene la conexión a la BD
require "../../conexion/config_pdo.php";

try {
    // Consulta para obtener las cuentas bancarias excluyendo las de tipo 'Interno'
    // Se retorna el id y un nombre formado por el nombre del banco y el número de cuenta
    $query = "
        SELECT 
            id_cuenta_bancaria AS id, 
            CONCAT(nombre_banco, ' - ', numero_cuenta) AS nombre 
        FROM public.cuentas_bancarias 
        WHERE tipo_cuenta != 'Interno'
        ORDER BY nombre_banco ASC
    ";

    // Preparar y ejecutar la consulta
    $stmt = $pdo->prepare($query);
    $stmt->execute();

    // Recuperar los resultados como un arreglo asociativo
    $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retornar el arreglo en formato JSON
    echo json_encode($cuentas);
} catch (PDOException $e) {
    // En caso de error, se retorna un mensaje de error en JSON
    echo json_encode(['error' => $e->getMessage()]);
}
?>
