<?php
header('Content-Type: application/json; charset=utf-8');

// Configuración de conexión a PostgreSQL
include("../conexion/config.php");

// Conexión a PostgreSQL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$conn) { echo json_encode(["error"=>"Error de conexión a PostgreSQL."]); exit; }

// Parámetros opcionales de filtro
$estado = $_GET['estado'] ?? '';          // ej: "Abierto", "Anulado", etc.
$q      = trim($_GET['q'] ?? '');         // búsqueda por nro o departamento

$sql = "SELECT 
          numero_pedido,
          departamento_solicitante,
          telefono,
          correo,
          fecha_pedido,
          fecha_entrega_solicitada,
          estado
        FROM cabecera_pedido_interno
        WHERE 1=1";

$params = [];
$i = 1;

if ($estado !== '') {
  $sql .= " AND estado = $" . $i++;
  $params[] = $estado;
}

if ($q !== '') {
  $sql .= " AND (CAST(numero_pedido AS TEXT) ILIKE $" . $i . " 
               OR departamento_solicitante ILIKE $" . $i . ")";
  $params[] = "%$q%";
  $i++;
}

$sql .= " ORDER BY numero_pedido DESC LIMIT 500";

$res = pg_query_params($conn, $sql, $params);
if (!$res) { echo json_encode(["error"=>"Error al ejecutar la consulta."]); exit; }

$rows = [];
while ($row = pg_fetch_assoc($res)) { $rows[] = $row; }

pg_free_result($res);
pg_close($conn);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
