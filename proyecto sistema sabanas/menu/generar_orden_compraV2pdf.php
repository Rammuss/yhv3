<?php
// Incluir la librería FPDF
require('../fpdf/fpdf.php');

// Conexión a la base de datos
include("../conexion/config.php");

// Obtener el ID de la orden de compra desde la URL
$id_orden_compra = $_GET['id'];

// Conectar a la base de datos
$conn = pg_connect("host=$host dbname=$dbname user=$user password=$password");
if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}

// Consulta para obtener los detalles de la orden de compra
$query = "
    SELECT o.*, p.nombre AS nombre_proveedor
    FROM ordenes_compra o
    JOIN proveedores p ON o.id_proveedor = p.id_proveedor
    WHERE o.id_orden_compra = $1
";
$result = pg_query_params($conn, $query, array($id_orden_compra));

if ($result) {
    $row = pg_fetch_assoc($result);

    // Crear el PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Orden de Compra', 0, 1, 'C');

    // Agregar detalles de la orden al PDF
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(40, 10, 'ID Orden:', 0, 0);
    $pdf->Cell(40, 10, $row['id_orden_compra'], 0, 1);

    $pdf->Cell(40, 10, 'Fecha Emision:', 0, 0);
    $pdf->Cell(40, 10, $row['fecha_emision'], 0, 1);

    $pdf->Cell(40, 10, 'Fecha Entrega:', 0, 0);
    $pdf->Cell(40, 10, $row['fecha_entrega'], 0, 1);

    $pdf->Cell(40, 10, 'Proveedor:', 0, 0);
    $pdf->Cell(40, 10, $row['nombre_proveedor'], 0, 1);

    $pdf->Cell(40, 10, 'Metodo de Pago:', 0, 0);
    $pdf->Cell(40, 10, $row['metodo_pago'], 0, 1);

    if ($row['cuotas']) {
        $pdf->Cell(40, 10, 'Cuotas:', 0, 0);
        $pdf->Cell(40, 10, $row['cuotas'], 0, 1);
    }

    $pdf->Cell(40, 10, 'Condiciones Entrega:', 0, 0);
    $pdf->MultiCell(0, 10, $row['condiciones_entrega']);

    $pdf->Cell(0, 10, '', 0, 1); // Espacio entre secciones
    
    // Consultar detalles de la orden de compra
    $query_detalles = "
        SELECT d.*, p.nombre AS nombre_producto
        FROM detalle_orden_compra d
        JOIN producto p ON d.id_producto = p.id_producto
        WHERE d.id_orden_compra = $1
    ";
    $result_detalles = pg_query_params($conn, $query_detalles, array($id_orden_compra));
    
    if ($result_detalles) { 
        // Agregar tabla de detalles al PDF
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 10, 'ID Producto', 1);
        $pdf->Cell(80, 10, 'Descripcion', 1);
        $pdf->Cell(30, 10, 'Cantidad', 1);
        $pdf->Cell(30, 10, 'Precio Unitario', 1);
        $pdf->Ln();
        
        $pdf->SetFont('Arial', '', 12);
        while ($detalle = pg_fetch_assoc($result_detalles)) {
            $pdf->Cell(40, 10, $detalle['id_producto'], 1);
            $pdf->Cell(80, 10, $detalle['descripcion'], 1);
            $pdf->Cell(30, 10, $detalle['cantidad'], 1);
            $pdf->Cell(30, 10, number_format($detalle['precio_unitario'], 2), 1);
            $pdf->Ln();
        }
    } else {
        $pdf->Cell(0, 10, 'No se encontraron detalles para esta orden.', 0, 1);
    }
    
    // Output PDF
    $pdf->Output('I', 'orden_compra_' . $row['id_orden_compra'] . '.pdf');
} else {
    echo "Error al obtener los datos de la orden.";
}

// Liberar el resultado y cerrar la conexión
pg_free_result($result);
pg_free_result($result_detalles);
pg_close($conn);
?>