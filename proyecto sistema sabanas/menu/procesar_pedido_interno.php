<?php


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Conexión a la base de datos PostgreSQL
    include("../conexion/config.php");
    $conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

    if (!$conn) {
        die("Error de conexión: " . pg_last_error());
    }

    

    // Obtener y limpiar los datos del formulario
    $numero_pedido = $_POST['numeroPedido'];
    $departamento_solicitante = $_POST['departameto'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['email'];
    $fecha_pedido = $_POST['fechaPedido'];
    $fecha_entrega_solicitada = $_POST['fechaEntrega'];

    try {
        // Iniciar la transacción
        pg_query($conn, "BEGIN");

    $query_cabecera = "INSERT INTO cabecera_pedido_interno (numero_pedido, departamento_solicitante, telefono, correo, fecha_pedido, fecha_entrega_solicitada)
     VALUES ($1, $2, $3, $4, $5, $6)";

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
        die("Error al insertar en la tabla cabecera_pedido_interno");
    }

    // Obtener el número de filas afectadas (debería ser 1)
    $rows_affected = pg_affected_rows($result_cabecera);

     // Verificar si se insertó correctamente
     if ($rows_affected != 1) {
        die("Error: no se insertó correctamente en la tabla cabecera_pedido_interno");
    }

    // Obtener y almacenar los datos de los productos del formulario
    $id_productos = $_POST['id_producto'];
    $nombres = $_POST['nombre_producto'];
    $cantidades = $_POST['cantidad'];

    // Preparar la consulta para insertar en detalle_pedido_interno
    $query_detalle = "INSERT INTO detalle_pedido_interno (numero_pedido, id_producto, nombre_producto, cantidad)
                      VALUES ($1, $2, $3, $4)";

    // Iterar sobre los datos recibidos y ejecutar la consulta preparada
    for ($i = 0; $i < count($id_productos); $i++) {
        $id_producto = $id_productos[$i];
        $nombre_producto = $nombres[$i];
        $cantidad = $cantidades[$i];

        // Ejecutar la consulta preparada para cada detalle del pedido
        $result_detalle = pg_query_params($conn, $query_detalle, array(
            $numero_pedido,
            $id_producto,
            $nombre_producto,
            $cantidad
        ));

        if (!$result_detalle) {
            die("Error al insertar en la tabla detalle_pedido_interno para el producto con ID $id_producto");
        }
    }

    pg_query($conn, "COMMIT");
    //echo "Datos insertados correctamente en ambas tablas";
    
    header("Location: registrar_pedidos.html?status=success");
    exit;
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    pg_query($conn, "ROLLBACK");
    echo "Error: " . $e->getMessage();
}
}
?>

