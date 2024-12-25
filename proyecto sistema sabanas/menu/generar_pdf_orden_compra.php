<?php
// Conexión a la base de datos PostgreSQL
include("../conexion/config.php");


$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}


if (isset($_GET['id'])) {
    $id_orden_compra = $_GET['id'];


    $id_orden_compra = $_GET['id'];

    $query = "SELECT
        oc.id_orden_compra,
        p.nombre AS nombre_proveedor,
        oc.fecha,
        pr.nombre AS nombre_producto,
        ocd.cantidad,
        ocd.precio_unitario,
        ocd.precio_total
    FROM
        orden_compra oc
        INNER JOIN proveedores p ON oc.id_proveedor = p.id_proveedor
        INNER JOIN orden_compra_detalle ocd ON oc.id_orden_compra = ocd.id_orden_compra
        INNER JOIN producto pr ON ocd.id_producto = pr.id_producto
    WHERE
        oc.id_orden_compra = $id_orden_compra";

    $result = pg_query($conn, $query);

    if (!$result) {
        die('Error en la consulta a la base de datos');
    }

    require('C:/xampp/htdocs/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/fpdf/fpdf.php');
    // Asegúrate de ajustar la ruta al archivo fpdf.php según la ubicación de tu carpeta FPDF.

    // Crear una instancia de FPDF
    $pdf = new FPDF();
    $pdf->AddPage();

    // Establecer la fuente y el tamaño del texto
    $pdf->SetFont('Arial', 'B', 16);
    $firstRow = true;  // Para identificar la primera fila de datos 
    // Agregar contenido al PDF
    $total = 0;
    while ($row = pg_fetch_assoc($result)) {
        if ($firstRow) {
            // Generar líneas de encabezado solo en la primera fila de datos

            $pdf->Cell(0, 10, 'Orden de Compra', 1, 1, 'C');
            $pdf->Cell(70, 10, utf8_decode('Número de Orden:') . $row['id_orden_compra'], 0);
            $pdf->Cell(50, 10, 'Proveedor: ' . $row['nombre_proveedor'], 0);
            $pdf->Cell(50, 10, 'Fecha: ' . $row['fecha'], 0);
            $pdf->Ln();
            $pdf->Cell(50, 10, "Producto", 1);
            $pdf->Cell(50, 10, "Cantidad", 1);
            $pdf->Cell(50, 10, "Prrecio Unitario", 1);
            $pdf->Cell(40, 10, "Sub Total", 1);
            $pdf->Ln();



            $firstRow = false;  // Marcar que se generaron las líneas de encabezado
        }

        // Generar el resto de los detalles de la orden de compra
        $pdf->Cell(50, 10, $row['nombre_producto'], 1);
        $pdf->Cell(50, 10, $row['cantidad'], 1);
        $pdf->Cell(50, 10, $row['precio_unitario'], 1);
        $pdf->Cell(40, 10, $row['precio_total'], 1);
        $pdf->Ln();


        $total += $row['precio_total'];
    }
    $pdf->Cell(50, 10, 'Total:', 1);
    $pdf->Cell(140, 10, $total, 1,0,'R');
    $pdf->Ln();

    // Generar el PDF y mostrarlo al usuario o guardarlo como archivo
    $pdf->Output('orden_compra.pdf', 'I');
} else {
    echo 'ID de orden de compra no especificado.';
}
