<?php
include( "../../conexion/config.php");


// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");


if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}




// Recibir datos del formulario
$id = $_POST["id"];
$nombre = $_POST["nombre"];

        

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action === 'insert') {
        // Lógica para la operación de inserción
// Crear la consulta SQL para insertar un proveedor
        $sql = "INSERT INTO ciudades (id_ciudad, nombre) VALUES ('$id','$nombre')";

        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: ciudad.html?respuesta=$respuesta");

    } elseif ($action === 'update') {
        // Lógica para la operación de actualización

        // Crear la consulta SQL para insertar un proveedor
        $sql = "UPDATE ciudades SET nombre = '$nombre' WHERE id_ciudad = $id";

    
        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: ciudad.html?respuesta=$respuesta");

    } elseif ($action === 'delete') {
        // Lógica para la operación de eliminación
        // Crear la consulta SQL para insertar un proveedor
        $sql = "DELETE FROM ciudades WHERE id_ciudad = $id";
        
        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: ciudad.html?respuesta=$respuesta");

    }
}


// Cerrar conexión
pg_close($conn);
?>