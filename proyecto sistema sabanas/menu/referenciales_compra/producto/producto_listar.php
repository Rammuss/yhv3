<?php
header('Content-Type: application/json; charset=utf-8');

try {
  require __DIR__ . '../../../../conexion/configv2.php'; // $conn
  if (!$conn) { echo json_encode(["ok"=>false,"error"=>"Sin conexión"]); exit; }

  $q         = isset($_GET['q']) ? trim($_GET['q']) : '';
  $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
  $iva       = isset($_GET['iva']) ? trim($_GET['iva']) : '';
  $estado    = isset($_GET['estado']) ? trim($_GET['estado']) : '';

  $where = [];
  $params = [];
  $i = 1;

  if ($q !== '') {
    $where[] = "(lower(p.nombre) LIKE $" . ($i++) . " OR lower(p.categoria) LIKE $" . ($i++) . ")";
    $like = '%'.mb_strtolower($q).'%';
    array_push($params, $like, $like);
  }
  if ($categoria !== '') { $where[] = "p.categoria = $" . ($i++); $params[] = $categoria; }
  if ($iva !== '')       { $where[] = "p.tipo_iva = $" . ($i++);   $params[] = $iva; }
  if ($estado !== '')    { $where[] = "p.estado = $" . ($i++);     $params[] = $estado; }

  $sql = "
    SELECT p.id_producto, p.nombre, p.precio_unitario, p.precio_compra,
           p.estado, p.tipo_iva, p.categoria
      FROM public.producto p
     " . (count($where) ? "WHERE ".implode(' AND ',$where) : "") . "
     ORDER BY (p.estado<>'Activo'), lower(p.nombre) ASC
     LIMIT 1000
  ";

  $res = pg_query_params($conn, $sql, $params);
  if (!$res) { echo json_encode(["ok"=>false,"error"=>pg_last_error($conn)]); exit; }

  $out = [];
  while ($row = pg_fetch_assoc($res)) {
    $out[] = [
      "id_producto"     => (int)$row["id_producto"],
      "nombre"          => $row["nombre"],
      "precio_unitario" => (float)$row["precio_unitario"],
      "precio_compra"   => (float)$row["precio_compra"],
      "estado"          => $row["estado"],
      "tipo_iva"        => $row["tipo_iva"],
      "categoria"       => $row["categoria"],
    ];
  }

  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}
