<?php
// Configuración de la base de datos
$host = 'localhost';
$port = '5433';
$dbname = 'bd_sabanas';
$user = 'postgres';
$password = '1996';

try {
    // Realiza la conexión con PDO
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    // Configurar atributos de PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>
