<?php


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Conexión a la base de datos PostgreSQL
    include("../conexion/config.php");
    $conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

    if (!$conn) {
        die("Error de conexión: " . pg_last_error());
    }

    // Verifica si los datos generales están presentes
    if (isset($_POST['id_orden_compra'], $_POST['proveedor'], $_POST['fecha_pedido'])) {
        // Recoge los datos generales de la orden de compra
        $id_orden_compra = $_POST['id_orden_compra'];
        $id_proveedor = $_POST['proveedor'];
        $fecha_pedido = $_POST['fecha_pedido'];

        // Procesa los datos generales de la orden de compra
        $sql = "INSERT INTO orden_compra (id_orden_compra, id_proveedor, fecha) VALUES ($1, $2, $3)";
        $result = pg_query_params($conn, $sql, array($id_orden_compra, $id_proveedor, $fecha_pedido));

        if ($result) {
            
            $productos = json_decode($_POST['productos'], true);

             if (!empty($productos) && is_array($productos)) {
               

                 echo '<pre>';
                 var_dump($productos);
                 echo '</pre>';

                foreach ($productos as $producto) {
                    $id_producto = intval($producto['producto_id']);
                    $cantidad = intval($producto['cantidad']);
                    $precioUnitario = floatval($producto['precioUnitario']);

                    // Procesa los datos de los productos (inserción en la base de datos o cualquier otro procesamiento)
                    $sql = "INSERT INTO orden_compra_detalle ( id_orden_compra, id_producto, cantidad, precio_unitario, precio_total) 
                    VALUES ( $id_orden_compra, $id_producto, $cantidad, $precioUnitario, $cantidad * $precioUnitario)";


                    $result = pg_query($conn, $sql);
                    if (!$result) {
                        // Manejar errores en la inserción de detalles
                        echo "Error al insertar detalles: " . pg_last_error($conn);
                    }
                }
            

            // Redirige a una página de éxito o muestra un mensaje de éxito al usuario
            //header('Location: orden_compra.html');
            exit;
        } else {
            // Si hubo un error en la inserción de los datos generales
            echo "Error al insertar datos generales: " . pg_last_error($conn);
        }
    } else {
        // Manejar el caso en el que faltan datos
        echo "Faltan datos requeridos.";
    }

    // Cierra la conexión a la base de datos
    pg_close($conn);
}
}
