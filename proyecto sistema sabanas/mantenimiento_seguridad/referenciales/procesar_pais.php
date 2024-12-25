<?php
include( "../../conexion/config.php");


// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");


if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}




// Recibir datos del formulario
$id = $_POST["id_pais"];
$nombre = $_POST["nombre"];
$gentilicio = $_POST["gentilicio"];

        

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action === 'insert') {
        // Lógica para la operación de inserción
// Crear la consulta SQL para insertar un proveedor
        $sql = "INSERT INTO paises (id_pais, nombre, gentilicio) VALUES ('$id','$nombre', '$gentilicio')";

        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: pais.html?respuesta=$respuesta");

    } elseif ($action === 'update') {
        // Lógica para la operación de actualización

        // Crear la consulta SQL para insertar un proveedor
        $sql = "UPDATE Paises SET nombre = '$nombre', gentilicio = '$gentilicio' WHERE id_pais = $id";

    
        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: pais.html?respuesta=$respuesta");

    } elseif ($action === 'delete') {
        // Lógica para la operación de eliminación
        // Crear la consulta SQL para insertar un proveedor
        $sql = "DELETE FROM Paises WHERE id_pais = $id";
        
        $result = pg_query($conn, $sql);

        if ($result !== false) {
            // La consulta se ejecutó con éxito
            $respuesta = "true";
        } else {
            // Hubo un error en la ejecución de la consulta
            $respuesta = "false";
        }
        header("Location: pais.html?respuesta=$respuesta");

    }
}


// Cerrar conexión
pg_close($conn);
?>