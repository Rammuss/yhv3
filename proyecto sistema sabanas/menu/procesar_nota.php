<?php

// Incluir el archivo de configuración
include("../conexion/config.php");

// Conexión a la base de datos PostgreSQL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error al conectar a la base de datos: " . pg_last_error());
}

// Recibir y validar datos del formulario
$tipo_nota = isset($_POST['tipo_nota']) ? pg_escape_string($conn, $_POST['tipo_nota']) : '';
$numero_nota = isset($_POST['numero_nota']) ? pg_escape_string($conn, $_POST['numero_nota']) : '';
$fecha_nota = isset($_POST['fecha_nota']) ? pg_escape_string($conn, $_POST['fecha_nota']) : '';
$id_proveedor = isset($_POST['id_proveedor']) ? pg_escape_string($conn, $_POST['id_proveedor']) : '';
$id_compra = isset($_POST['id_compra']) ? pg_escape_string($conn, $_POST['id_compra']) : '';
$monto = isset($_POST['monto']) ? pg_escape_string($conn, $_POST['monto']) : '';
$descripcion = isset($_POST['descripcion']) ? pg_escape_string($conn, $_POST['descripcion']) : '';
$estado = isset($_POST['estado']) ? pg_escape_string($conn, $_POST['estado']) : 'Activo'; // Valor por defecto

// Iniciar la transacción
pg_query($conn, "BEGIN");

try {
    // Insertar la nota en la tabla `notas`
    $query = "INSERT INTO notas (tipo_nota, numero_nota, fecha_nota, id_proveedor, id_compra, monto, descripcion, estado)
              VALUES ($1, $2, $3, $4, $5, $6, $7, $8)";
    $result = pg_query_params($conn, $query, [$tipo_nota, $numero_nota, $fecha_nota, $id_proveedor, $id_compra, $monto, $descripcion, $estado]);

    if (!$result) {
        throw new Exception("Error al insertar la nota: " . pg_last_error($conn));
    }

    // Confirmar la transacción
    pg_query($conn, "COMMIT");
    echo "Nota registrada exitosamente.";
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    pg_query($conn, "ROLLBACK");
    echo "Error al registrar la nota: " . $e->getMessage();
}

// Cerrar la conexión
pg_close($conn);
