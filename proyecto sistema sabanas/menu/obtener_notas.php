<?php
// Incluir el archivo de configuración
include("../conexion/config.php");

// Conexión a la base de datos PostgreSQL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error al conectar a la base de datos: " . pg_last_error());
}

// Consultar las notas
$query = "SELECT n.id_nota, n.tipo_nota, n.numero_nota, n.fecha_nota, p.nombre, n.id_compra, n.monto, n.descripcion, n.estado
          FROM notas n
          JOIN proveedores p ON n.id_proveedor = p.id_proveedor";
$result = pg_query($conn, $query);

if (!$result) {
    die("Error al ejecutar la consulta: " . pg_last_error());
}

// Crear un array para almacenar los resultados
$notas = [];
while ($row = pg_fetch_assoc($result)) {
    $notas[] = $row;
}

// Liberar el resultado
pg_free_result($result);

// Cerrar la conexión
pg_close($conn);

// Convertir los resultados a JSON y devolverlos
echo json_encode($notas);
?>
