<?php
// Conexión a la base de datos PostgreSQL
$host = "localhost";
$port = "5432";
$dbname = "bd_sabanas";
$user = "postgres";
$password = "1996";

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}


// Recibir datos del formulario
$id = $_POST["id"];
$nombre = $_POST["nombre"];
$direccion = $_POST["direccion"];
$telefono = $_POST["telefono"];
$email = $_POST["email"];
$ruc = $_POST["ruc"];
$ciudad = $_POST["id_ciudad"];
$pais = $_POST["id_pais"];


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action === 'insert') {
        // Lógica para la operación de inserción
// Crear la consulta SQL para insertar un proveedor
        
        $sql = "INSERT INTO proveedores (nombre, direccion, telefono, email, ruc, id_pais, id_ciudad) VALUES ('$nombre', '$direccion', '$telefono', '$email', '$ruc', $ciudad, $pais)";


        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: proveedores.html?respuesta=$respuesta");

    } elseif ($action === 'update') {
        // Lógica para la operación de actualización

        // Crear la consulta SQL para insertar un proveedor

        $sql = "UPDATE proveedores SET nombre = '$nombre', direccion = '$direccion', telefono = '$telefono', email = '$email', ruc = '$ruc', id_pais = $pais, id_ciudad = $ciudad WHERE id_proveedor = $id";

        
        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: proveedores.html?respuesta=$respuesta");

    } elseif ($action === 'delete') {
        // Lógica para la operación de eliminación
        // Crear la consulta SQL para insertar un proveedor
        $sql = "DELETE FROM proveedores WHERE id_proveedor = $id";
        
        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: proveedores.html?respuesta=$respuesta");

    }
}


// Cerrar conexión
pg_close($conn);
