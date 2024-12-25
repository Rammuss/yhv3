<?php
include("../conexion/config.php");

// Conectar a la base de datos
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$conn) {
    echo 'Error: No se pudo conectar a la base de datos.';
    exit;
}

// Recibir datos del formulario
$numero_remision = $_POST['numero_remision'];
$fecha_remision = $_POST['fecha_remision'];
$id_proveedor = $_POST['id_proveedor'];
$id_compra = $_POST['id_compra'];
$estado = $_POST['estado'] ?? 'Activo';

// Comenzar transacción
pg_query($conn, "BEGIN");

try {
    // Insertar en la tabla nota_remision
    $query = "INSERT INTO public.nota_remision (numero_remision, fecha_remision, id_proveedor, id_compra, estado)
              VALUES ('$numero_remision', '$fecha_remision', $id_proveedor, $id_compra, '$estado') RETURNING id_nota_remision";
    $result = pg_query($conn, $query);
    if (!$result) {
        throw new Exception('Error al insertar en nota_remision: ' . pg_last_error($conn));
    }

    $row = pg_fetch_assoc($result);
    $id_nota_remision = $row['id_nota_remision'];

    // Insertar en la tabla nota_remision_detalle
    $detalles = $_POST['detalles']; // Detalles recibidos como array de productos

    foreach ($detalles as $detalle) {
        $id_producto = $detalle['id_producto'];
        $nombre_producto = $detalle['nombre_producto'];
        $cantidad = $detalle['cantidad'];
        $precio_unitario = $detalle['precio_unitario'];

        $query = "INSERT INTO public.nota_remision_detalle (id_nota_remision, id_producto, nombre_producto, cantidad, precio_unitario)
                  VALUES ($id_nota_remision, $id_producto, '$nombre_producto', $cantidad, $precio_unitario)";
        $result = pg_query($conn, $query);
        if (!$result) {
            throw new Exception('Error al insertar en nota_remision_detalle: ' . pg_last_error($conn));
        }
    }

    // Confirmar transacción
    pg_query($conn, "COMMIT");
    echo 'Nota de remisión registrada exitosamente.';
    header('Location: tabla_nota_remision.html?status=success');

} catch (Exception $e) {
    // Revertir transacción en caso de error
    pg_query($conn, "ROLLBACK");
    echo 'Error al registrar la nota de remisión: ' . $e->getMessage();
}

// Cerrar conexión
pg_close($conn);
?>
