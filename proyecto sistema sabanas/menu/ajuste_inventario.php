<?php
// Conectar a la base de datos

// Incluir el archivo de configuración
include("../conexion/config.php");

// Conexión a la base de datos PostgreSQL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}

// Recibir y validar datos del formulario
$id_producto = isset($_POST['id_producto']) ? pg_escape_string($conn, $_POST['id_producto']) : '';
$cantidad_ajustada = isset($_POST['cantidad_ajustada']) ? pg_escape_string($conn, $_POST['cantidad_ajustada']) : '';
$fecha_ajuste = isset($_POST['fecha_ajuste']) ? pg_escape_string($conn, $_POST['fecha_ajuste']) : '';
$motivo_ajuste = isset($_POST['motivo_ajuste']) ? pg_escape_string($conn, $_POST['motivo_ajuste']) : '';

// Verificar que todos los campos necesarios están presentes
if ($id_producto == '' || $cantidad_ajustada == '' || $fecha_ajuste == '') {
    die("Faltan datos necesarios para el ajuste.");
}

// Iniciar la transacción
pg_query($conn, "BEGIN");

try {
    // Insertar el ajuste en la tabla de ajustes de inventario
    $query = "INSERT INTO ajustes_inventario (id_producto, cantidad_ajustada, fecha_ajuste, motivo_ajuste)
              VALUES ($1, $2, $3, $4)";
    $result = pg_query_params($conn, $query, [$id_producto, $cantidad_ajustada, $fecha_ajuste, $motivo_ajuste]);

    if (!$result) {
        throw new Exception("Error al insertar el ajuste: " . pg_last_error($conn));
    }

    // Actualizar el inventario
    $query_update = "UPDATE inventario
                     SET cantidad = cantidad + $1
                     WHERE id_producto = $2";
    $result_update = pg_query_params($conn, $query_update, [$cantidad_ajustada, $id_producto]);

    if (!$result_update) {
        throw new Exception("Error al actualizar el inventario: " . pg_last_error($conn));
    }

    // Confirmar la transacción
    pg_query($conn, "COMMIT");

    echo "Ajuste registrado y inventario actualizado con éxito.";
    header('Location: ajuste_inventario.html?status=success');


} catch (Exception $e) {
    // Deshacer la transacción en caso de error
    pg_query($conn, "ROLLBACK");
    echo "Error: " . $e->getMessage();
}

// Cerrar la conexión
pg_close($conn);
?>
