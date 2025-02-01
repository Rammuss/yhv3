<?php
// Incluir la conexión a la base de datos
include "../../../conexion/configv2.php";

// Obtener el id_pais desde la solicitud GET
$id_pais = isset($_GET['id_pais']) ? (int)$_GET['id_pais'] : 0;

// Verificar que el id_pais es válido
if ($id_pais > 0) {
    // Consultar las ciudades para el país seleccionado
    $query = "SELECT id_ciudad, nombre FROM ciudades WHERE id_pais = $id_pais";
    $result = pg_query($conn, $query);

    // Verificar si hay resultados
    if (pg_num_rows($result) > 0) {
        $ciudades = [];
        while ($row = pg_fetch_assoc($result)) {
            $ciudades[] = $row;
        }
        // Devolver las ciudades en formato JSON
        echo json_encode($ciudades);
    } else {
        // Si no se encuentran ciudades, retornar un arreglo vacío
        echo json_encode([]);
    }
} else {
    // Si el id_pais no es válido, retornar un error
    echo json_encode(["error" => "País no válido"]);
}

// Cerrar la conexión
pg_close($conn);
?>
