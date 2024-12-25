<?php
include( "../../conexion/config.php");


// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");


if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}

