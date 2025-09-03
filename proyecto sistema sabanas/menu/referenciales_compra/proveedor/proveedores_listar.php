<?php
// proveedores_listar.php
header('Content-Type: application/json; charset=utf-8');

try {
  // OJO con la ruta; dejé un ejemplo típico:
  require __DIR__ . '../../../../conexion/configv2.php'; // Debe exponer $conn

  if (!$conn) { echo json_encode(["ok"=>false,"error"=>"Sin conexión"]); exit; }

  $tipo   = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
  $q      = isset($_GET['q'])    ? trim($_GET['q'])    : '';
  $inclEl = isset($_GET['incluir_eliminados']) && $_GET['incluir_eliminados']=='1';

  $where = [];
  $params = [];
  $i = 1;

  // Excluir eliminados por defecto
  if (!$inclEl) {
    $where[] = "p.deleted_at IS NULL";
  }

  if ($tipo !== '') { $where[] = "p.tipo = $" . ($i++); $params[] = $tipo; }

  if ($q !== '') {
    // Si no tenés la extensión unaccent, cambiá unaccent(lower(...)) por lower(...)
    $where[] = "(unaccent(lower(p.nombre)) LIKE $" . ($i++) .
               " OR lower(p.ruc) LIKE $" . ($i++) .
               " OR lower(p.email) LIKE $" . ($i++) . ")";
    $like = '%'.mb_strtolower($q).'%';
    $params[] = $like; $params[] = $like; $params[] = $like;
  }

  $sql = "
    SELECT p.id_proveedor, p.nombre, p.direccion, p.telefono, p.email, p.ruc,
           p.id_ciudad, c.nombre AS ciudad,
           p.id_pais,   pa.nombre AS pais,
           p.tipo,
           p.estado,
           p.deleted_at
      FROM public.proveedores p
      LEFT JOIN public.ciudades c ON c.id_ciudad = p.id_ciudad
      LEFT JOIN public.paises   pa ON pa.id_pais = p.id_pais
     " . (count($where) ? "WHERE ".implode(' AND ', $where) : "") . "
     ORDER BY p.deleted_at IS NOT NULL, p.nombre ASC
     LIMIT 1000
  ";

  $res = pg_query_params($conn, $sql, $params);
  if (!$res) { echo json_encode(["ok"=>false,"error"=>pg_last_error($conn)]); exit; }

  $out = [];
  while ($row = pg_fetch_assoc($res)) {
    $out[] = [
      "id_proveedor" => (int)$row["id_proveedor"],
      "nombre"       => $row["nombre"],
      "direccion"    => $row["direccion"],
      "telefono"     => $row["telefono"],
      "email"        => $row["email"],
      "ruc"          => $row["ruc"],
      "id_ciudad"    => isset($row["id_ciudad"]) ? (int)$row["id_ciudad"] : null,
      "ciudad"       => $row["ciudad"],
      "id_pais"      => isset($row["id_pais"]) ? (int)$row["id_pais"] : null,
      "pais"         => $row["pais"],
      "tipo"         => $row["tipo"] ?? 'PROVEEDOR',
      "estado"       => $row["estado"] ?? 'Activo',
      "deleted_at"   => $row["deleted_at"], // null si activo, timestamp si eliminado
    ];
  }

  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}
