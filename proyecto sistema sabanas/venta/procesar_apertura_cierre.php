<?php
include("../conexion/config.php");

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}


//recibo los datos del form apertura_caja.html

$numero_caja = $_POST['numero_caja'];

$nombre_usuario = $_POST['nombre_usuario'];

$fecha_apertura = $_POST['fecha_apertura'];

$hora_apertura = $_POST['hora_apertura'];

$monto_inicial = $_POST['monto_inicial'];

$sql = "INSERT INTO aperturas_de_caja 
(numero_caja, 
nombre_usuario, 
estado,fecha_apertura, 
hora_apertura, 
monto_inicial) values ('$numero_caja','$nombre_usuario','Abierto','$fecha_apertura','$hora_apertura','$monto_inicial')";


$result = pg_query($conn , $sql);
if ($result) {
    echo "Realizado!";
} else {
    echo "Error " . pg_last_error($conn);
}

pg_close($conn);