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

// Verificar el tipo de solicitud
if (isset($_GET['id'])) {
    // Solicitud para obtener detalles del proveedor
    $proveedor_id = intval($_GET['id']); // Sanitizar el ID del proveedor
    
    // Consulta para obtener los datos del proveedor
    $query = "SELECT direccion, telefono, email, ruc FROM proveedores WHERE id_proveedor = $1";
    $result = pg_query_params($conn, $query, array($proveedor_id));
    
    if ($result) {
        $proveedor = pg_fetch_assoc($result);
        
        if ($proveedor) {
            echo json_encode($proveedor);
        } else {
            echo json_encode(['error' => 'Proveedor no encontrado']);
        }
    } else {
        echo json_encode(['error' => 'Error en la consulta']);
    }
} else {
    // Solicitud para obtener la lista de proveedores
    $query = "SELECT id_proveedor, nombre FROM proveedores";
    $result = pg_query($conn, $query);

    if ($result) {
        $proveedores = [];
        while ($row = pg_fetch_assoc($result)) {
            $proveedores[] = $row;
        }
        echo json_encode($proveedores);
    } else {
        echo json_encode(['error' => 'Error en la consulta']);
    }
}

// Cerrar la conexión
pg_close($conn);
?>
