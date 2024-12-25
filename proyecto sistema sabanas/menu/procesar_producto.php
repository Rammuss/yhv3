<?php
// Conexión a la base de datos PostgreSQL
include("../conexion/config.php");


$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}


// Recibir datos del formulario
$id_producto = $_POST["id_producto"];
$nombre = $_POST["nombre"];
$medida = $_POST["medida"];
$tipo_iva = $_POST["tipo_iva"];
$color = $_POST["color"];
$material = $_POST["material"];
$hilos = $_POST["hilos"];
$precio_unitario = $_POST["precio_unitario"];
$precio_compra = $_POST["precio_compra"];
$categoria = $_POST["categoria"];


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action === 'insert') {
        // Lógica para la operación de inserción
        // Crear la consulta SQL para insertar un proveedor

        $sql = "INSERT INTO producto (id_producto, nombre, medida, tipo_iva, color, material, hilos, precio_unitario, precio_compra, categoria) 
            VALUES ($id_producto, '$nombre', '$medida','$tipo_iva', '$color', '$material', $hilos, $precio_unitario, $precio_compra, '$categoria')";



        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: producto.html?respuesta=$respuesta");
    } elseif ($action === 'update') {
        // Lógica para la operación de actualización

        // Crear la consulta SQL para insertar un proveedor

        $sql = "UPDATE producto
        SET nombre = '$nombre', medida = '$medida', tipo_iva = '$tipo_iva' ,color = '$color', material = 
        '$material', hilos = $hilos, precio_unitario = $precio_unitario, precio_compra = $precio_compra, categoria = '$categoria'
        WHERE id_producto = $id_producto";

        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: producto.html?respuesta=$respuesta");
    } elseif ($action === 'delete') {
        // Lógica para la operación de eliminación
        // Crear la consulta SQL para insertar un proveedor
        $sql = "DELETE FROM producto WHERE id_producto = $id_producto";

        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: producto.html?respuesta=$respuesta");
    }
}


// Cerrar conexión
pg_close($conn);
