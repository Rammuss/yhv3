<?php
include("../conexion/config.php");

// Conectar a la base de datos
$conn = pg_connect("host=$host dbname=$dbname user=$user password=$password");

if (!$conn) {
    echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
    exit;
}

// Recibir el ID de la nota de remisión a anular
$id_nota_remision = $_POST['id_nota_remision'];

if (!$id_nota_remision) {
    echo json_encode(['error' => 'ID de nota de remisión no proporcionado.']);
    exit;
}

// Actualizar el estado a 'Anulado'
$query = "UPDATE public.nota_remision SET estado = 'Anulado' WHERE id_nota_remision = $id_nota_remision";
$result = pg_query($conn, $query);

if (!$result) {
    echo json_encode(['error' => 'Error al actualizar el estado: ' . pg_last_error($conn)]);
    exit;
}

pg_close($conn);

echo json_encode(['success' => true, 'message' => 'Nota de remisión anulada exitosamente.']);
?>
