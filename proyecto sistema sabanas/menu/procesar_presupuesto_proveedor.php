<?php
include("../conexion/config.php");

// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error en la conexión a la base de datos: " . pg_last_error());
}
// Recibir datos del formulario
$proveedor = $_POST["proveedor"];
$fecha_registro = $_POST["fecha_registro"];
$fecha_vencimiento = $_POST["fecha_vencimiento"];
$estado = $_POST["state"];

// Procesar el archivo subido
// $archivo_documento = $_FILES["archivo_documento"];


// Prepara la consulta SQL para insertar el documento y otros detalles del presupuesto
$sql = "INSERT INTO presupuestos (id_proveedor, fecharegistro, fechavencimiento, estado)
    VALUES ('$proveedor', '$fecha_registro', '$fecha_vencimiento', '$estado') RETURNING id_presupuesto";

// Ejecuta la consulta SQL
$result = pg_query($conn, $sql);

if ($result) {

    $productos = json_decode($_POST['productos'], true);

    $row = pg_fetch_assoc($result);
    $id_presupuesto = intval($row['id_presupuesto']);

    if (!empty($productos) && is_array($productos)) {


        foreach ($productos as $producto) {

            $id_producto = intval($producto['producto_id']);
            $cantidad = intval($producto['cantidad']);
            $precioUnitario = floatval($producto['precioUnitario']);

            // Procesa los datos de los productos (inserción en la base de datos o cualquier otro procesamiento)
            $sql = "INSERT INTO presupuesto_detalle ( id_presupuesto, id_producto, cantidad, precio_unitario, precio_total) 
            VALUES ( $id_presupuesto, $id_producto, $cantidad, $precioUnitario, $cantidad * $precioUnitario)";


            $result = pg_query($conn, $sql);
            if (!$result) {
                // Manejar errores en la inserción de detalles
                echo "Error al insertar detalles: " . pg_last_error($conn);
            }
        }


        // Redirige a una página de éxito o muestra un mensaje de éxito al usuario
        header('Location: registrar_presupuesto_proveedor.html?status=success');
exit;
    } else {
        // Si hubo un error en la inserción de los datos generales
        echo "Error al insertar datos generales: " . pg_last_error($conn);
    }
}
// Cierra la conexión a la base de datos
pg_close($conn);
