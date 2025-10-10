<?php
require_once __DIR__ . '/conciliacion_common.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $idConc = require_int($_GET['id_conciliacion'] ?? null, 'Conciliación');
  $conc = require_conciliacion($conn, $idConc, false);

  $sql = "
    SELECT *
      FROM public.conciliacion_bancaria_extracto
     WHERE id_conciliacion = $1
     ORDER BY fecha, id_extracto
  ";
  $st = pg_query_params($conn, $sql, [$idConc]);
  if (!$st) bad('No se pudo obtener el extracto', 500);
  $rows = [];
  while ($r = pg_fetch_assoc($st)) {
    $rows[] = $r;
  }
  ok(['extracto' => $rows, 'conciliacion' => $conc]);
}

if ($method === 'POST') {
  if (isset($_FILES['archivo'])) {
    $idConc = require_int($_POST['id_conciliacion'] ?? null, 'Conciliación');
    require_conciliacion($conn, $idConc, false);

    $file = $_FILES['archivo'];
    if ($file['error'] !== UPLOAD_ERR_OK) bad('Error al subir archivo');

    $rows = [];
    if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
      $row = 0;
      while (($data = fgetcsv($handle, 1000, ';')) !== false) {
        $row++;
        if ($row === 1 && isset($_POST['skip_header']) && $_POST['skip_header'] === '1') continue;
        if (count($data) < 3) continue;
        $fecha = trim($data[0]);
        $descripcion = trim($data[1]);
        $monto = (float)str_replace([',', '$'], ['.', ''], trim($data[2]));
        $signo = $monto >= 0 ? 1 : -1;
        $rows[] = [
          'fecha' => $fecha,
          'descripcion' => $descripcion,
          'monto' => abs($monto),
          'signo' => $signo,
          'referencia' => trim($data[3] ?? '')
        ];
      }
      fclose($handle);
    } else {
      bad('No se pudo leer el archivo');
    }

    if (!$rows) bad('El archivo no contiene datos válidos', 422);

    pg_query($conn,'BEGIN');
    $sqlIns = "
      INSERT INTO public.conciliacion_bancaria_extracto
        (id_conciliacion, fecha, descripcion, referencia, signo, monto, created_at)
      VALUES ($1, $2::date, $3, NULLIF($4,''), $5, $6, now())
    ";
    foreach ($rows as $r) {
      $ok = pg_query_params($conn, $sqlIns, [
        $idConc, $r['fecha'], $r['descripcion'], $r['referencia'], $r['signo'], $r['monto']
      ]);
      if (!$ok) { pg_query($conn,'ROLLBACK'); bad('Error al insertar extracto', 500); }
    }
    pg_query($conn,'COMMIT');
    ok(['insertados' => count($rows)]);
  }

  $in = read_json();
  $accion = $in['accion'] ?? '';
  if ($accion === 'agregar') {
    $idConc = require_int($in['id_conciliacion'] ?? null, 'Conciliación');
    require_conciliacion($conn, $idConc, false);
    $fecha = $in['fecha'] ?? date('Y-m-d');
    $descripcion = trim($in['descripcion'] ?? '');
    $monto = (float)($in['monto'] ?? 0);
    $signo = (int)($in['signo'] ?? 1);
    if (!in_array($signo, [1,-1], true)) bad('Signo inválido');
    if ($monto <= 0) bad('Monto inválido');
    $ref = trim($in['referencia'] ?? '');

    $ok = pg_query_params(
      $conn,
      "INSERT INTO public.conciliacion_bancaria_extracto
        (id_conciliacion, fecha, descripcion, referencia, signo, monto, created_at)
       VALUES ($1, $2::date, $3, NULLIF($4,''), $5, $6, now())
       RETURNING id_extracto",
      [$idConc, $fecha, $descripcion, $ref, $signo, $monto]
    );
    if (!$ok) bad('No se pudo insertar', 500);
    $idExt = (int)pg_fetch_result($ok, 0, 0);
    ok(['id_extracto' => $idExt]);
  }

  bad('Acción no soportada', 405);
}

if ($method === 'DELETE') {
  parse_str(file_get_contents('php://input'), $in);
  $id = require_int($in['id_extracto'] ?? null, 'Id extracto');

  $st = pg_query_params(
    $conn,
    "DELETE FROM public.conciliacion_bancaria_extracto
      WHERE id_extracto = $1",
    [$id]
  );
  if (!$st) bad('No se pudo eliminar', 500);
  ok(['eliminado'=>true]);
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
