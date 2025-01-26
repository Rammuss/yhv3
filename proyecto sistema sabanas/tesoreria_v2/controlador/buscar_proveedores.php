<?php
header("Content-Type: application/json"); // Establecer el tipo de contenido como JSON

include "../../conexion/configv2.php" ;

// Obtener el parámetro de búsqueda
$q = $_GET["q"] ?? ""; // Obtener el valor del parámetro 'q' (RUC o nombre)

// Consulta SQL para buscar proveedores
$query = "
    SELECT id_proveedor, nombre, ruc
    FROM proveedores
    WHERE ruc ILIKE $1 OR nombre ILIKE $1
    LIMIT 10;
";

// Preparar y ejecutar la consulta
$result = pg_query_params($conn, $query, ["%$q%"]);

if (!$result) {
    echo json_encode(["error" => "Error al ejecutar la consulta"]);
    exit;
}

// Obtener los resultados
$proveedores = [];
while ($row = pg_fetch_assoc($result)) {
    $proveedores[] = [
        "id_proveedor" => $row["id_proveedor"],
        "nombre" => $row["nombre"],
        "ruc" => $row["ruc"],
    ];
}

// Devolver los resultados en formato JSON
echo json_encode($proveedores);

// Cerrar la conexión
pg_close($conn);
?>