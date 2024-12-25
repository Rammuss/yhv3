<?php
// Configuración de conexión a PostgreSQL
include("../conexion/config.php");

// Conexión a PostgreSQL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");



// Obtener y limpiar los datos del formulario
$numero_pedido = $_POST['numeroPedido'];
$departamento_solicitante = $_POST['departamento'];
$telefono = $_POST['telefono'];
$correo = $_POST['email'];
$fecha_pedido = $_POST['fechaPedido'];
$fecha_entrega_solicitada = $_POST['fechaEntrega'];

try {
    // Iniciar la transacción
    pg_query($conn, "BEGIN");

    // Actualizar la cabecera del pedido
    $query_cabecera = "UPDATE cabecera_pedido_interno 
                       SET departamento_solicitante = $2, telefono = $3, correo = $4, fecha_pedido = $5, fecha_entrega_solicitada = $6
                       WHERE numero_pedido = $1";

    // Ejecutar la consulta preparada
    $result_cabecera = pg_query_params($conn, $query_cabecera, array(
        $numero_pedido,
        $departamento_solicitante,
        $telefono,
        $correo,
        $fecha_pedido,
        $fecha_entrega_solicitada
    ));

    if (!$result_cabecera) {
        throw new Exception("Error al actualizar la tabla cabecera_pedido_interno");
    }

    // Obtener y almacenar los datos de los productos del formulario
    $id_productos = $_POST['id_producto'];
    $nombre_productos = $_POST['nombre_producto'];
    $cantidades = $_POST['cantidad'];

    // Eliminar los detalles antiguos
    $query_eliminar_detalles = "DELETE FROM detalle_pedido_interno WHERE numero_pedido = $1";
    $result_eliminar_detalles = pg_query_params($conn, $query_eliminar_detalles, array($numero_pedido));

    if (!$result_eliminar_detalles) {
        throw new Exception("Error al eliminar los detalles antiguos del pedido");
    }

    // Preparar la consulta para insertar los nuevos detalles en detalle_pedido_interno
    $query_detalle = "INSERT INTO detalle_pedido_interno (numero_pedido, id_producto, nombre_producto, cantidad)
                      VALUES ($1, $2, $3, $4)";

    // Iterar sobre los datos recibidos y ejecutar la consulta preparada
    for ($i = 0; $i < count($id_productos); $i++) {
        $id_producto = $id_productos[$i];
        $nombre_producto = $nombre_productos[$i];
        $cantidad = $cantidades[$i];

        // Ejecutar la consulta preparada para cada detalle del pedido
        $result_detalle = pg_query_params($conn, $query_detalle, array(
            $numero_pedido,
            $id_producto,
            $nombre_producto,
            $cantidad
        ));

        if (!$result_detalle) {
            throw new Exception("Error al insertar en la tabla detalle_pedido_interno para el producto con ID $id_producto");
        }
    }

    // Si llegaste aquí, todo salió bien. Confirma la transacción
    pg_query($conn, "COMMIT");
    header("Location: tabla_pedidos.html?registro=exitoso");

    exit;
} catch (Exception $e) {
    // Si ocurrió un error, deshacer la transacción
    pg_query($conn, "ROLLBACK");
    echo "Ocurrió un error al actualizar el pedido: " . $e->getMessage();
}

// Cerrar la conexión
pg_close($conn);
?>
