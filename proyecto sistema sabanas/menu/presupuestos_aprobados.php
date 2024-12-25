<?php
// Configuración de conexión a la base de datos
include("../conexion/config.php");

// Establece la conexión con la base de datos
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

// Verificar si la conexión fue exitosa
if (!$conn) {
    echo json_encode(['error' => 'Error al conectar con la base de datos']);
    exit;
}

// Consulta para obtener los presupuestos aprobados
$query = "SELECT id_presupuesto FROM presupuestos WHERE estado = 'Aprobado'";
$result = pg_query($conn, $query);

if ($result) {
    $presupuestos = [];
    while ($row = pg_fetch_assoc($result)) {
        $presupuestos[] = $row;
    }
    echo json_encode($presupuestos);
} else {
    echo json_encode(['error' => 'Error en la consulta']);
}

// Cerrar la conexión
pg_close($conn);
?>
