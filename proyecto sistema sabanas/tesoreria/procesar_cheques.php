<?php
include("../conexion/config.php");

// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error en la conexión a la base de datos: " . pg_last_error());
}



// Datos del formulario (asegúrate de validar y limpiar los datos antes de usarlos en una consulta)
$id_proveedor = intval($_POST['id_proveedor']);
$monto = $_POST['monto'];
$fecha_entrega = $_POST['fecha_entrega'];
$numero_cheque = $_POST['numero_cheque'];
$descripcion = $_POST['descripcion'];

// Consulta SQL para insertar un nuevo registro en la tabla entrega_cheques
$query = "INSERT INTO entrega_cheques (id_proveedor, monto, fecha_entrega, numero_cheque, descripcion) 
VALUES ('$id_proveedor', '$monto', '$fecha_entrega', '$numero_cheque', '$descripcion')";

$result = pg_query($conn, $query);

if ($result) {
    // Redireccionar a una página de éxito o mostrar un mensaje de éxito
    header("Location: registro_exitoso.php");
} else {
    // Manejar errores de la base de datos
    echo "Error en la base de datos: " . pg_last_error($conn);
}

// Cerrar la conexión a la base de datos
pg_close($conn);
