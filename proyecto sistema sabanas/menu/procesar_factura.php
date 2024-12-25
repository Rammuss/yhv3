<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Imprimir datos recibidos para depuración
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';
}



// Incluir el archivo de configuración
include("../conexion/config.php");

// Conexión a la base de datos PostgreSQL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}

// Recibir y validar datos del formulario
$numero_factura = isset($_POST['numero_factura']) ? pg_escape_string($conn, $_POST['numero_factura']) : '';
$fecha_factura = isset($_POST['fecha_factura']) ? pg_escape_string($conn, $_POST['fecha_factura']) : '';
$id_proveedor = isset($_POST['id_proveedor']) ? pg_escape_string($conn, $_POST['id_proveedor']) : '';
$id_orden_compra = isset($_POST['id_orden_compra']) ? pg_escape_string($conn, $_POST['id_orden_compra']) : '';
$condicion_pago = isset($_POST['condiciones_pago']) ? pg_escape_string($conn, $_POST['condiciones_pago']) : '';
$cantidad_cuotas = (isset($_POST['cuotas']) && $_POST['cuotas'] !== '') ? pg_escape_string($conn, $_POST['cuotas']) : NULL;
$detalles = isset($_POST['detalles']) ? $_POST['detalles'] : [];

// Iniciar la transacción
pg_query($conn, "BEGIN");

try {
    // Insertar la factura sin el campo `total_compra`
    $query = "INSERT INTO compras (numero_factura, fecha_factura, id_proveedor, id_orden_compra, condicion_pago, cantidad_cuotas)
              VALUES ($1, $2, $3, $4, $5, $6) RETURNING id_compra";
    $result = pg_query_params($conn, $query, [$numero_factura, $fecha_factura, $id_proveedor, $id_orden_compra, $condicion_pago, $cantidad_cuotas]);

    if (!$result) {
        throw new Exception("Error al insertar la factura: " . pg_last_error($conn));
    }

    // Obtener el ID de la factura insertada
    $row = pg_fetch_assoc($result);
    $id_compra = $row['id_compra'];

    // Insertar los detalles de la factura
    foreach ($detalles as $detalle) {
        $id_producto = pg_escape_string($conn, $detalle['id_producto']);
        $descripcion = pg_escape_string($conn, $detalle['descripcion']);
        $cantidad = pg_escape_string($conn, $detalle['cantidad']);
        $precio_unitario = pg_escape_string($conn, $detalle['precio_unitario']);

        $query = "INSERT INTO detalle_compras (id_compra, id_producto, descripcion, cantidad, precio_unitario) 
                  VALUES ($1, $2, $3, $4, $5)";
        $result = pg_query_params($conn, $query, [$id_compra, $id_producto, $descripcion, $cantidad, $precio_unitario]);

        if (!$result) {
            throw new Exception("Error al insertar el detalle de la factura: " . pg_last_error($conn));
        }
    }

    // Confirmar la transacción
    pg_query($conn, "COMMIT");
    echo "Factura registrada con éxito.";
    header('Location: tabla_compras.html?status=success');
} catch (Exception $e) {
    // Revertir cambios en caso de error
    pg_query($conn, "ROLLBACK");
    echo "Error al registrar la factura: " . $e->getMessage();
}

// Cerrar la conexión
pg_close($conn);
?>
