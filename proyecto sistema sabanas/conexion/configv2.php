    <?php
    // Configuración de la base de datos


    $host = "localhost";
    $port = "5433";
    $dbname = "bd_sabanas";
    $user = "postgres";
    $password = "1996";

    // Realiza la conexión
    $conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

    if (!$conn) {
        die("Error de conexión a la base de datos.");
    }

    ?>
