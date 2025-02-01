<?php
// Configuración de la base de datos
$host = "localhost";
$port = "5432";
$dbname = "bd_sabanas";
$user = "postgres";
$password = "1996";

try {
    // Realiza la conexión
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $conn = new PDO($dsn, $user, $password);

    // Configura el modo de error de PDO para que lance excepciones
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conexión exitosa a la base de datos.";
} catch (PDOException $e) {
    echo "Error de conexión a la base de datos: " . $e->getMessage();
}
?>
