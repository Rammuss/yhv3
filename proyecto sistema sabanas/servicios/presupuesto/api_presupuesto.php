<?php
// servicios/presupuesto/api_presupuesto.php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

function s($v)
{
    return is_string($v) ? trim($v) : null;
}
function arr($v)
{
    return is_array($v) ? $v : [];
}

function normalize_estado($estado)
{
    $estado = ucfirst(strtolower($estado));
    $validos = ['Borrador', 'Enviado', 'Aprobado', 'Rechazado', 'Vencido'];
    return in_array($estado, $validos, true) ? $estado : 'Borrador';
}
function normalize_tipo_item($tipo)
{
    $tipo = strtoupper(substr(trim((string)$tipo), 0, 1));
    return in_array($tipo, ['S', 'P', 'D'], true) ? $tipo : 'S';
}
function normalize_tipo_iva($tipo)
{
    $tipo = strtoupper(trim((string)$tipo));
    if (in_array($tipo, ['IVA10', '10', '10%', 'IVA 10', '10.0'], true)) return 'IVA10';
    if (in_array($tipo, ['IVA5', '5', '5%', 'IVA 5', '5.0'], true)) return 'IVA5';
    return 'EXE';
}
function calc_line_totals($cantidad, $precio, $descuento, $tipoIva)
{
    $importeBruto = $cantidad * $precio;
    $descuento = max(0.0, min($descuento, $importeBruto));
    $importeFinal = $importeBruto - $descuento;
    $tipoIva = normalize_tipo_iva($tipoIva);
    $rate = $tipoIva === 'IVA10' ? 0.10 : ($tipoIva === 'IVA5' ? 0.05 : 0.0);
    if ($rate > 0) {
        $base = round($importeFinal / (1 + $rate), 2);
        $iva  = round($importeFinal - $base, 2);
    } else {
        $base = round($importeFinal, 2);
        $iva  = 0.0;
    }
    return [$importeFinal, $iva];
}
function calc_totals($items)
{
    $grav10 = $iva10 = $grav5 = $iva5 = $exentas = $total = $desc = 0.0;
    foreach ($items as $it) {
        $cantidad = (float)($it['cantidad'] ?? 0);
        $precio   = (float)($it['precio_unitario'] ?? 0);
        $descuento = (float)($it['descuento'] ?? 0);
        $tipoIva  = normalize_tipo_iva($it['tipo_iva'] ?? 'EXE');
        list($importeFinal, $iva) = calc_line_totals($cantidad, $precio, $descuento, $tipoIva);
        $total += $importeFinal;
        $desc  += $descuento;
        if ($tipoIva === 'IVA10') {
            $grav10 += $importeFinal - $iva;
            $iva10  += $iva;
        } elseif ($tipoIva === 'IVA5') {
            $grav5  += $importeFinal - $iva;
            $iva5   += $iva;
        } else {
            $exentas += $importeFinal;
        }
    }
    return [
        'grav10' => round($grav10, 2),
        'iva10' => round($iva10, 2),
        'grav5' => round($grav5, 2),
        'iva5' => round($iva5, 2),
        'exentas' => round($exentas, 2),
        'total' => round($total, 2),
        'descuento' => round($desc, 2),
        'total_bruto' => round($grav10 + $grav5 + $exentas, 2),
        'total_iva' => round($iva10 + $iva5, 2),
        'total_neto' => round($total, 2)
    ];
}
function json_error($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}
function json_ok($data = [])
{
    echo json_encode(['success' => true] + $data);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$op = strtolower(s($in['op'] ?? ''));
if ($op === '') json_error('Parámetro op requerido');

try {
    switch ($op) {

        case 'list': {
                $estado = s($in['estado'] ?? '');
                $idCliente = (int)($in['id_cliente'] ?? 0);
                $desde = s($in['fecha_desde'] ?? '');
                $hasta = s($in['fecha_hasta'] ?? '');
                $where = [];
                $params = [];
                if ($estado !== '' && $estado !== 'Todos') {
                    $where[] = 'p.estado = $' . (count($params) + 1);
                    $params[] = ucfirst(strtolower($estado));
                }
                if ($idCliente > 0) {
                    $where[] = 'p.id_cliente = $' . (count($params) + 1);
                    $params[] = $idCliente;
                }
                if ($desde !== '' && $hasta !== '') {
                    $where[] = 'p.fecha_presupuesto BETWEEN $' . (count($params) + 1) . ' AND $' . (count($params) + 2);
                    $params[] = $desde;
                    $params[] = $hasta;
                } elseif ($desde !== '') {
                    $where[] = 'p.fecha_presupuesto >= $' . (count($params) + 1);
                    $params[] = $desde;
                } elseif ($hasta !== '') {
                    $where[] = 'p.fecha_presupuesto <= $' . (count($params) + 1);
                    $params[] = $hasta;
                }
                $sql = "
        SELECT p.id_presupuesto,
               p.fecha_presupuesto,
               p.estado,
               p.total_neto,
               p.total_bruto,
               p.total_iva,
               c.id_cliente,
               c.nombre,
               c.apellido,
               p.id_reserva,
               p.validez_hasta,
               p.creado_por,
               p.creado_en
          FROM public.serv_presupuesto_cab p
          JOIN public.clientes c ON c.id_cliente = p.id_cliente
        ";
                if ($where) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }
                $sql .= ' ORDER BY p.fecha_presupuesto DESC, p.id_presupuesto DESC LIMIT 200';
                $res = $params ? pg_query_params($conn, $sql, $params) : pg_query($conn, $sql);
                if (!$res) json_error('No se pudieron listar presupuestos');
                $rows = [];
                while ($row = pg_fetch_assoc($res)) {
                    $row['id_presupuesto'] = (int)$row['id_presupuesto'];
                    $row['id_cliente'] = (int)$row['id_cliente'];
                    $row['total_neto'] = (float)$row['total_neto'];
                    $row['total_bruto'] = (float)$row['total_bruto'];
                    $row['total_iva'] = (float)$row['total_iva'];
                    $row['id_reserva'] = $row['id_reserva'] !== null ? (int)$row['id_reserva'] : null;
                    $rows[] = $row;
                }
                json_ok(['rows' => $rows]);
            }

        case 'list_reservas': {
                $idCliente = (int)($in['id_cliente'] ?? 0);
                $estado = s($in['estado'] ?? '');
                $desde = s($in['fecha_desde'] ?? '');
                $hasta = s($in['fecha_hasta'] ?? '');
                $where = [];
                $params = [];
                if ($idCliente > 0) {
                    $where[] = 'r.id_cliente = $' . (count($params) + 1);
                    $params[] = $idCliente;
                }
                if ($estado !== '' && $estado !== 'Todos') {
                    $where[] = 'r.estado = $' . (count($params) + 1);
                    $params[] = ucfirst(strtolower($estado));
                } else {
                    $where[] = "r.estado IN ('Confirmada','Pendiente')";
                }
                if ($desde !== '' && $hasta !== '') {
                    $where[] = 'r.fecha_reserva BETWEEN $' . (count($params) + 1) . ' AND $' . (count($params) + 2);
                    $params[] = $desde;
                    $params[] = $hasta;
                } elseif ($desde !== '') {
                    $where[] = 'r.fecha_reserva >= $' . (count($params) + 1);
                    $params[] = $desde;
                } elseif ($hasta !== '') {
                    $where[] = 'r.fecha_reserva <= $' . (count($params) + 1);
                    $params[] = $hasta;
                }
                $sql = "
        SELECT r.id_reserva,
               r.fecha_reserva,
               r.inicio_ts,
               r.fin_ts,
               r.estado,
               r.id_cliente,
               c.nombre AS cliente_nombre,
               c.apellido AS cliente_apellido,
               r.id_profesional,
               pr.nombre AS profesional_nombre,
               (SELECT COUNT(*) FROM public.serv_presupuesto_cab pc WHERE pc.id_reserva = r.id_reserva) AS presupuestos_count,
               COALESCE((SELECT json_agg(json_build_object(
         'id_producto', rd.id_producto,
         'descripcion', rd.descripcion,
         'cantidad', rd.cantidad,
         'precio_unitario', rd.precio_unitario,
         'tipo_item', COALESCE(p.tipo_item,'S'),
         'tipo_iva', COALESCE(rd.tipo_iva,'EXE')
       ) ORDER BY rd.descripcion)
 FROM public.reserva_det rd
 LEFT JOIN public.producto p ON p.id_producto = rd.id_producto
 WHERE rd.id_reserva = r.id_reserva)
, '[]') AS servicios
          FROM public.reserva_cab r
          JOIN public.clientes c ON c.id_cliente = r.id_cliente
          LEFT JOIN public.profesional pr ON pr.id_profesional = r.id_profesional
      ";
                if ($where) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }
                $sql .= ' ORDER BY r.fecha_reserva DESC, r.inicio_ts DESC LIMIT 200';
                $res = $params ? pg_query_params($conn, $sql, $params) : pg_query($conn, $sql);
                if (!$res) json_error('No se pudieron listar reservas');
                $rows = [];
                while ($row = pg_fetch_assoc($res)) {
                    $row['id_reserva'] = (int)$row['id_reserva'];
                    $row['id_cliente'] = (int)$row['id_cliente'];
                    $row['id_profesional'] = $row['id_profesional'] !== null ? (int)$row['id_profesional'] : null;
                    $row['presupuestos_count'] = (int)$row['presupuestos_count'];
                    $row['servicios'] = json_decode($row['servicios'], true) ?: [];
                    $rows[] = $row;
                }
                json_ok(['rows' => $rows]);
            }

        case 'get': {
                $id = (int)($in['id_presupuesto'] ?? 0);
                if ($id <= 0) json_error('id_presupuesto requerido');
                $cab = pg_query_params($conn, "
        SELECT p.*, c.nombre, c.apellido, c.ruc_ci
          FROM public.serv_presupuesto_cab p
          JOIN public.clientes c ON c.id_cliente = p.id_cliente
         WHERE p.id_presupuesto = $1
        ", [$id]);
                if (!$cab || pg_num_rows($cab) === 0) json_error('Presupuesto no encontrado', 404);
                $cabRow = pg_fetch_assoc($cab);
                $cabRow['id_presupuesto'] = (int)$cabRow['id_presupuesto'];
                $cabRow['id_cliente'] = (int)$cabRow['id_cliente'];
                $cabRow['id_reserva'] = $cabRow['id_reserva'] !== null ? (int)$cabRow['id_reserva'] : null;
                $cabRow['total_bruto'] = (float)$cabRow['total_bruto'];
                $cabRow['total_descuento'] = (float)$cabRow['total_descuento'];
                $cabRow['total_iva'] = (float)$cabRow['total_iva'];
                $cabRow['total_neto'] = (float)$cabRow['total_neto'];

                $det = pg_query_params($conn, "
        SELECT id_presupuesto_det,id_producto,descripcion,tipo_item,cantidad,precio_unitario,descuento,tipo_iva,iva_monto,subtotal_neto,comentario
          FROM public.serv_presupuesto_det
         WHERE id_presupuesto = $1
         ORDER BY id_presupuesto_det
      ", [$id]);
                $items = [];
                while ($row = pg_fetch_assoc($det)) {
                    $row['id_presupuesto_det'] = (int)$row['id_presupuesto_det'];
                    $row['id_producto'] = $row['id_producto'] !== null ? (int)$row['id_producto'] : null;
                    $row['cantidad'] = (float)$row['cantidad'];
                    $row['precio_unitario'] = (float)$row['precio_unitario'];
                    $row['descuento'] = (float)$row['descuento'];
                    $row['iva_monto'] = (float)$row['iva_monto'];
                    $row['subtotal_neto'] = (float)$row['subtotal_neto'];
                    $items[] = $row;
                }
                json_ok(['cab' => $cabRow, 'items' => $items]);
            }

        case 'create_from_reserva': {
                $id_reserva = (int)($in['id_reserva'] ?? 0);
                if ($id_reserva <= 0) json_error('id_reserva requerido');
                $rRes = pg_query_params($conn, "
        SELECT r.*, c.id_cliente
          FROM public.reserva_cab r
          JOIN public.clientes c ON c.id_cliente = r.id_cliente
         WHERE r.id_reserva = $1
         LIMIT 1
      ", [$id_reserva]);
                if (!$rRes || pg_num_rows($rRes) === 0) json_error('Reserva no encontrada', 404);
                $reserva = pg_fetch_assoc($rRes);
                $id_cliente = (int)$reserva['id_cliente'];
                $validez = s($in['validez_hasta'] ?? null);

                $det = pg_query_params($conn, "
  SELECT rd.id_producto,
         rd.descripcion,
         COALESCE(p.tipo_item,'S') AS tipo_item,
         COALESCE(rd.tipo_iva,'EXE') AS tipo_iva,
         COALESCE(rd.cantidad,1) AS cantidad,
         COALESCE(rd.precio_unitario,0) AS precio_unitario
    FROM public.reserva_det rd
    LEFT JOIN public.producto p ON p.id_producto = rd.id_producto
   WHERE rd.id_reserva = $1
   ORDER BY rd.descripcion
", [$id_reserva]);


                $items = [];
                while ($row = pg_fetch_assoc($det)) {
                    $items[] = [
                        'id_producto' => $row['id_producto'] !== null ? (int)$row['id_producto'] : null,
                        'descripcion' => $row['descripcion'],
                        'tipo_item'   => normalize_tipo_item($row['tipo_item']),
                        'cantidad'    => (float)$row['cantidad'],
                        'precio_unitario' => (float)$row['precio_unitario'],
                        'descuento'   => 0.0,
                        'tipo_iva'    => normalize_tipo_iva($row['tipo_iva'])
                    ];
                }

                if (empty($items)) json_error('La reserva no tiene servicios asociados');

                $tot = calc_totals($items);

                if (!pg_query($conn, 'BEGIN')) json_error('No se pudo iniciar transacción');

                $sqlCab = "
        INSERT INTO public.serv_presupuesto_cab
          (id_reserva,id_cliente,fecha_presupuesto,validez_hasta,estado,total_bruto,total_descuento,total_iva,total_neto,notas,creado_por,creado_en)
        VALUES ($1,$2,current_date,$3,'Borrador',$4,$5,$6,$7,$8,$9,now())
        RETURNING id_presupuesto
      ";
                $creado_por = $_SESSION['nombre_usuario'] ?? null;
                $notas = s($in['notas'] ?? null);
                $rCab = pg_query_params($conn, $sqlCab, [
                    $id_reserva,
                    $id_cliente,
                    $validez !== '' ? $validez : null,
                    $tot['total_bruto'],
                    $tot['descuento'],
                    $tot['total_iva'],
                    $tot['total_neto'],
                    $notas,
                    $creado_por
                ]);
                if (!$rCab) {
                    pg_query($conn, 'ROLLBACK');
                    json_error('No se pudo crear presupuesto');
                }
                $idPres = (int)pg_fetch_result($rCab, 0, 0);

                $sqlDet = "
        INSERT INTO public.serv_presupuesto_det
          (id_presupuesto,id_producto,descripcion,tipo_item,cantidad,precio_unitario,descuento,tipo_iva,iva_monto,subtotal_neto)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
      ";
                foreach ($items as $it) {
                    list($importe, $iva) = calc_line_totals($it['cantidad'], $it['precio_unitario'], $it['descuento'], $it['tipo_iva']);
                    $ok = pg_query_params($conn, $sqlDet, [
                        $idPres,
                        $it['id_producto'],
                        $it['descripcion'],
                        $it['tipo_item'],
                        $it['cantidad'],
                        $it['precio_unitario'],
                        $it['descuento'],
                        normalize_tipo_iva($it['tipo_iva']),
                        $iva,
                        $importe
                    ]);
                    if (!$ok) {
                        pg_query($conn, 'ROLLBACK');
                        json_error('No se pudo copiar detalle de la reserva');
                    }
                }

                pg_query($conn, 'COMMIT');
                json_ok(['id_presupuesto' => $idPres]);
            }

        case 'create_manual': {
                $id_cliente = (int)($in['id_cliente'] ?? 0);
                if ($id_cliente <= 0) json_error('id_cliente requerido');
                $items = arr($in['items'] ?? []);
                if (empty($items)) json_error('Debe incluir al menos un ítem');
                $validez = s($in['validez_hasta'] ?? null);
                $notas = s($in['notas'] ?? null);

                $normalizedItems = [];
                foreach ($items as $it) {
                    $normalizedItems[] = [
                        'id_producto' => isset($it['id_producto']) && $it['id_producto'] !== '' ? (int)$it['id_producto'] : null,
                        'descripcion' => s($it['descripcion'] ?? ''),
                        'tipo_item'   => normalize_tipo_item($it['tipo_item'] ?? 'S'),
                        'cantidad'    => (float)($it['cantidad'] ?? 1),
                        'precio_unitario' => (float)($it['precio_unitario'] ?? 0),
                        'descuento'   => (float)($it['descuento'] ?? 0),
                        'tipo_iva'    => normalize_tipo_iva($it['tipo_iva'] ?? 'EXE')
                    ];
                    if ($normalizedItems[count($normalizedItems) - 1]['descripcion'] === '') {
                        json_error('Descripción requerida en cada ítem');
                    }
                }

                $tot = calc_totals($normalizedItems);

                if (!pg_query($conn, 'BEGIN')) json_error('No se pudo iniciar transacción');
                $sqlCab = "
        INSERT INTO public.serv_presupuesto_cab
          (id_cliente,fecha_presupuesto,validez_hasta,estado,total_bruto,total_descuento,total_iva,total_neto,notas,creado_por,creado_en)
        VALUES ($1,current_date,$2,'Borrador',$3,$4,$5,$6,$7,$8,now())
        RETURNING id_presupuesto
      ";
                $creado_por = $_SESSION['nombre_usuario'] ?? null;
                $rCab = pg_query_params($conn, $sqlCab, [
                    $id_cliente,
                    $validez !== '' ? $validez : null,
                    $tot['total_bruto'],
                    $tot['descuento'],
                    $tot['total_iva'],
                    $tot['total_neto'],
                    $notas,
                    $creado_por
                ]);
                if (!$rCab) {
                    pg_query($conn, 'ROLLBACK');
                    json_error('No se pudo crear presupuesto');
                }
                $idPres = (int)pg_fetch_result($rCab, 0, 0);

                $sqlDet = "
        INSERT INTO public.serv_presupuesto_det
          (id_presupuesto,id_producto,descripcion,tipo_item,cantidad,precio_unitario,descuento,tipo_iva,iva_monto,subtotal_neto)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
      ";
                foreach ($normalizedItems as $it) {
                    list($importe, $iva) = calc_line_totals($it['cantidad'], $it['precio_unitario'], $it['descuento'], $it['tipo_iva']);
                    $ok = pg_query_params($conn, $sqlDet, [
                        $idPres,
                        $it['id_producto'],
                        $it['descripcion'],
                        $it['tipo_item'],
                        $it['cantidad'],
                        $it['precio_unitario'],
                        $it['descuento'],
                        normalize_tipo_iva($it['tipo_iva']),
                        $iva,
                        $importe
                    ]);
                    if (!$ok) {
                        pg_query($conn, 'ROLLBACK');
                        json_error('No se pudo insertar el detalle');
                    }
                }
                pg_query($conn, 'COMMIT');
                json_ok(['id_presupuesto' => $idPres]);
            }

        case 'update': {
                $id_presupuesto = (int)($in['id_presupuesto'] ?? 0);
                if ($id_presupuesto <= 0) json_error('id_presupuesto requerido');
                $cab = pg_query_params($conn, "
        SELECT estado FROM public.serv_presupuesto_cab WHERE id_presupuesto=$1 LIMIT 1
      ", [$id_presupuesto]);
                if (!$cab || pg_num_rows($cab) === 0) json_error('Presupuesto no encontrado', 404);
                $estadoActual = pg_fetch_result($cab, 0, 0);
                if ($estadoActual !== 'Borrador') json_error('Sólo se pueden editar presupuestos en estado Borrador');

                $items = arr($in['items'] ?? []);
                if (empty($items)) json_error('Debe incluir al menos un ítem');
                $validez = s($in['validez_hasta'] ?? null);
                $notas = s($in['notas'] ?? null);

                $normalizedItems = [];
                foreach ($items as $it) {
                    $descripcion = s($it['descripcion'] ?? '');
                    if ($descripcion === '') json_error('Descripción requerida en cada ítem');
                    $normalizedItems[] = [
                        'id_producto' => isset($it['id_producto']) && $it['id_producto'] !== '' ? (int)$it['id_producto'] : null,
                        'descripcion' => $descripcion,
                        'tipo_item'   => normalize_tipo_item($it['tipo_item'] ?? 'S'),
                        'cantidad'    => (float)($it['cantidad'] ?? 1),
                        'precio_unitario' => (float)($it['precio_unitario'] ?? 0),
                        'descuento'   => (float)($it['descuento'] ?? 0),
                        'tipo_iva'    => normalize_tipo_iva($it['tipo_iva'] ?? 'EXE')
                    ];
                }
                $tot = calc_totals($normalizedItems);

                if (!pg_query($conn, 'BEGIN')) json_error('No se pudo iniciar transacción');
                $updCab = pg_query_params($conn, "
        UPDATE public.serv_presupuesto_cab
           SET validez_hasta=$2,
               notas=$3,
               total_bruto=$4,
               total_descuento=$5,
               total_iva=$6,
               total_neto=$7,
               actualizado_en=now()
         WHERE id_presupuesto=$1
      ", [
                    $id_presupuesto,
                    $validez !== '' ? $validez : null,
                    $notas,
                    $tot['total_bruto'],
                    $tot['descuento'],
                    $tot['total_iva'],
                    $tot['total_neto']
                ]);
                if (!$updCab) {
                    pg_query($conn, 'ROLLBACK');
                    json_error('No se pudo actualizar encabezado');
                }

                $delDet = pg_query_params($conn, "DELETE FROM public.serv_presupuesto_det WHERE id_presupuesto=$1", [$id_presupuesto]);
                if (!$delDet) {
                    pg_query($conn, 'ROLLBACK');
                    json_error('No se pudo limpiar detalle');
                }

                $sqlDet = "
        INSERT INTO public.serv_presupuesto_det
          (id_presupuesto,id_producto,descripcion,tipo_item,cantidad,precio_unitario,descuento,tipo_iva,iva_monto,subtotal_neto)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
      ";
                foreach ($normalizedItems as $it) {
                    list($importe, $iva) = calc_line_totals($it['cantidad'], $it['precio_unitario'], $it['descuento'], $it['tipo_iva']);
                    $ok = pg_query_params($conn, $sqlDet, [
                        $id_presupuesto,
                        $it['id_producto'],
                        $it['descripcion'],
                        $it['tipo_item'],
                        $it['cantidad'],
                        $it['precio_unitario'],
                        $it['descuento'],
                        normalize_tipo_iva($it['tipo_iva']),
                        $iva,
                        $importe
                    ]);
                    if (!$ok) {
                        pg_query($conn, 'ROLLBACK');
                        json_error('No se pudo insertar detalle');
                    }
                }
                pg_query($conn, 'COMMIT');
                json_ok();
            }

        case 'change_state': {
                $id_presupuesto = (int)($in['id_presupuesto'] ?? 0);
                if ($id_presupuesto <= 0) json_error('id_presupuesto requerido');
                $nuevoEstado = normalize_estado($in['estado'] ?? '');
                $valido = ['Borrador', 'Enviado', 'Aprobado', 'Rechazado', 'Vencido'];
                if (!in_array($nuevoEstado, $valido, true)) json_error('Estado no válido');

                $upd = pg_query_params($conn, "
        UPDATE public.serv_presupuesto_cab
           SET estado=$2,
               actualizado_en=now()
         WHERE id_presupuesto=$1
        RETURNING estado
      ", [$id_presupuesto, $nuevoEstado]);
                if (!$upd || pg_num_rows($upd) === 0) json_error('No se pudo actualizar estado');
                json_ok(['estado' => pg_fetch_result($upd, 0, 0)]);
            }

        case 'delete': {
                $id_presupuesto = (int)($in['id_presupuesto'] ?? 0);
                if ($id_presupuesto <= 0) json_error('id_presupuesto requerido');
                $estado = pg_query_params($conn, "
        SELECT estado FROM public.serv_presupuesto_cab WHERE id_presupuesto=$1
      ", [$id_presupuesto]);
                if (!$estado || pg_num_rows($estado) === 0) json_error('Presupuesto no encontrado', 404);
                if (pg_fetch_result($estado, 0, 0) !== 'Borrador') json_error('Sólo se pueden eliminar presupuestos en Borrador');
                $del = pg_query_params($conn, "DELETE FROM public.serv_presupuesto_cab WHERE id_presupuesto=$1", [$id_presupuesto]);
                if (!$del) json_error('No se pudo eliminar');
                json_ok();
            }

        default:
            json_error('op no reconocido');
    }
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'constraint') !== false) {
        $msg = 'Error de integridad de datos.';
    }
    json_error($msg);
}
