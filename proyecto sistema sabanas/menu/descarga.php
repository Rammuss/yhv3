<?php
// Conexión a la base de datos PostgreSQL
include("../conexion/config.php");


$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}

// Obtiene el ID del presupuesto desde la URL
$idPresupuesto = $_GET['id'];

// Realiza una consulta para obtener el documento del presupuesto
$query = "SELECT documento, hash_documento FROM public.presupuestos WHERE id_presupuesto = $idPresupuesto";
$result = pg_query($conn, $query);

if ($row = pg_fetch_assoc($result)) {

    $datosDocumento = $row['documento'];
    $hashAlmacenado = $row['hash_documento'];

    // Calcula el hash del documento descargado
    $hashCalculado = md5($datosDocumento);
    // Verifica la integridad del archivo
    //if ($hashCalculado === $hashAlmacenado) {
        echo "coinciden";
    // Proporciona el tipo de contenido adecuado (en este caso, un PDF)
    header('Content-Type: application/pdf');
    echo $datosDocumento;
    } else {
        echo "El archivo ha sido modificado. Los hashes no coinciden.";
    }

    // Muestra los datos binarios en el navegador
//} else {
    echo "Presupuesto no encontrado.";
//}
?>
