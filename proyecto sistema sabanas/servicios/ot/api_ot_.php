<?php
// api_ot.php — API (POST) para Orden de Trabajo

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

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
function s($x)
{
    return is_string($x) ? trim($x) : null;
}
function arr($x)
{
    return is_array($x) ? $x : [];
}
function normalizar_bool($valor)
{
    return $valor === true || $valor === 't' || $valor === '1' || $valor === 1;
}
function normalizar_estado($estado)
{
    $estado = ucfirst(strtolower($estado));
    $validos = ['Programada', 'En ejecución', 'Completada', 'Cancelada'];
    return in_array($estado, $validos, true) ? $estado : 'Programada';
}

function normalizar_tipo_iva_codigo($tipo)
{
    $tipo = strtoupper(trim((string)$tipo));
    if ($tipo === 'EXE' || $tipo === 'EX' || $tipo === '0' || $tipo === '') return 'EXE';
    if ($tipo === 'IVA10' || $tipo === '10' || $tipo === '10%' || strpos($tipo, '10') !== false) return 'IVA10';
    if ($tipo === 'IVA5' || $tipo === '5' || $tipo === '5%' || strpos($tipo, '5') !== false) return 'IVA5';
    return 'EXE';
}
function tasa_iva($tipo)
{
    $tipo = normalizar_tipo_iva_codigo($tipo);
    if ($tipo === 'IVA10') return 0.10;
    if ($tipo === 'IVA5') return 0.05;
    return 0.0;
}
function round_money($val, $dec = 2)
{
    return round((float)$val, $dec);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método no permitido', 405);
}

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$op = strtolower(s($in['op'] ?? ''));
if ($op === '') json_error('Parámetro op requerido');

try {
    switch ($op) {

        case 'list_profesionales': {
                $r = pg_query($conn, "SELECT id_profesional, nombre FROM public.profesional WHERE estado='Activo' ORDER BY nombre");
                if (!$r) json_error('No se pudo listar profesionales');
                $rows = [];
                while ($x = pg_fetch_assoc($r)) $rows[] = $x;
                json_ok(['rows' => $rows]);
            }

        case 'list_ot': {
                $estado = s($in['estado'] ?? '');
                $fecha  = s($in['fecha'] ?? '');
                $where  = [];
                $params = [];
                if ($estado !== '' && $estado !== 'Todos') {
                    $estado = normalizar_estado($estado);
                    $where[] = 'ot.estado = $' . (count($params) + 1);
                    $params[] = $estado;
                }
                if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    $where[] = 'ot.fecha_programada = $' . (count($params) + 1);
                    $params[] = $fecha;
                }
                $sql = "SELECT ot.id_ot,
                     ot.fecha_programada,
                     ot.hora_programada,
                     ot.estado,
                     c.nombre||' '||COALESCE(c.apellido,'') AS cliente,
                     p.nombre AS profesional,
                     ot.id_reserva,
                     ot.id_pedido
              FROM public.ot_cab ot
              JOIN public.clientes c ON c.id_cliente = ot.id_cliente
              LEFT JOIN public.profesional p ON p.id_profesional = ot.id_profesional";
                if ($where) {
                    $sql .= " WHERE " . implode(' AND ', $where);
                }
                $sql .= " ORDER BY ot.fecha_programada DESC, ot.hora_programada DESC, ot.id_ot DESC LIMIT 200";
                $r = $params ? pg_query_params($conn, $sql, $params) : pg_query($conn, $sql);
                if (!$r) json_error('No se pudieron listar las OT');
                $rows = [];
                while ($x = pg_fetch_assoc($r)) {
                    $x['id_ot'] = (int)$x['id_ot'];
                    $x['id_pedido'] = $x['id_pedido'] !== null ? (int)$x['id_pedido'] : null;
                    $rows[] = $x;
                }
                json_ok(['rows' => $rows]);
            }

        case 'search_reservas': {
                $q      = s($in['q'] ?? '');
                $fecha  = s($in['fecha'] ?? '');
                $estado = s($in['estado'] ?? 'Confirmada');
                $limit  = max(1, min(100, (int)($in['limit'] ?? 30)));

                $params = [];
                $where  = [];

                if ($estado !== '' && $estado !== 'Todos') {
                    $where[] = 'rc.estado = $' . (count($params) + 1);
                    $params[] = ucfirst(strtolower($estado));
                } else {
                    $where[] = "rc.estado IN ('Confirmada','Pendiente')";
                }

                if ($q !== '') {
                    $where[] = "(c.nombre ILIKE $" . (count($params) + 1) . " OR c.apellido ILIKE $" . (count($params) + 1) . " OR CAST(rc.id_reserva AS TEXT) ILIKE $" . (count($params) + 1) . ")";
                    $params[] = '%' . $q . '%';
                }

                if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    $where[] = "(rc.inicio_ts AT TIME ZONE 'America/Asuncion')::date = $" . (count($params) + 1) . "::date";
                    $params[] = $fecha;
                }

                $sql = "SELECT rc.id_reserva,
                     rc.inicio_ts,
                     rc.fin_ts,
                     rc.estado,
                     c.nombre||' '||COALESCE(c.apellido,'') AS cliente,
                     p.nombre AS profesional
              FROM public.reserva_cab rc
              JOIN public.clientes c ON c.id_cliente = rc.id_cliente
              LEFT JOIN public.profesional p ON p.id_profesional = rc.id_profesional";
                if ($where) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }
                $sql .= ' ORDER BY rc.inicio_ts DESC LIMIT ' . $limit;

                $r = $params ? pg_query_params($conn, $sql, $params) : pg_query($conn, $sql);
                if (!$r) json_error('No se pudieron listar reservas');

                $rows = [];
                while ($x = pg_fetch_assoc($r)) {
                    $x['id_reserva'] = (int)$x['id_reserva'];
                    $rows[] = $x;
                }
                json_ok(['rows' => $rows]);
            }

        case 'get_ot': {
                $id_ot = (int)($in['id_ot'] ?? 0);
                if ($id_ot <= 0) json_error('id_ot requerido');
                $sql = "SELECT ot.*, 
                     c.nombre||' '||COALESCE(c.apellido,'') AS cliente_nombre,
                     p.nombre AS profesional_nombre,
                     r.inicio_ts AS reserva_inicio,
                     r.fin_ts    AS reserva_fin
              FROM public.ot_cab ot
              JOIN public.clientes c ON c.id_cliente = ot.id_cliente
              LEFT JOIN public.profesional p ON p.id_profesional = ot.id_profesional
              LEFT JOIN public.reserva_cab r ON r.id_reserva = ot.id_reserva
              WHERE ot.id_ot = $1";
                $r = pg_query_params($conn, $sql, [$id_ot]);
                if (!$r || pg_num_rows($r) === 0) json_error('OT no encontrada', 404);
                $cab = pg_fetch_assoc($r);
                $cab['id_pedido'] = $cab['id_pedido'] !== null ? (int)$cab['id_pedido'] : null;

                $sqlDet = "SELECT item_nro, id_producto, descripcion, tipo_item, cantidad,
                        precio_unitario, tipo_iva, duracion_min, observaciones
               FROM public.ot_det
               WHERE id_ot=$1
               ORDER BY item_nro";
                $det = [];
                $rDet = pg_query_params($conn, $sqlDet, [$id_ot]);
                while ($row = pg_fetch_assoc($rDet)) {
                    $row['item_nro'] = (int)$row['item_nro'];
                    $row['id_producto'] = $row['id_producto'] !== null ? (int)$row['id_producto'] : null;
                    $row['cantidad'] = (float)$row['cantidad'];
                    $row['precio_unitario'] = (float)$row['precio_unitario'];
                    $row['duracion_min'] = (int)$row['duracion_min'];
                    $det[] = $row;
                }

                $sqlIns = "WITH stock AS (
                      SELECT id_producto,
                             COALESCE(SUM(CASE WHEN tipo_movimiento='entrada' THEN cantidad
                                               WHEN tipo_movimiento='salida' THEN -cantidad
                                               ELSE 0 END),0) AS stock_actual
                        FROM public.movimiento_stock
                       GROUP BY id_producto
                    )
                    SELECT i.item_nro,
                           i.id_producto,
                           i.cantidad,
                           i.deposito,
                           i.lote,
                           i.comentario,
                           p.nombre      AS producto_nombre,
                           COALESCE(p.unidad_base,'') AS unidad_base,
                           COALESCE(p.es_fraccion,false) AS es_fraccion,
                           COALESCE(s.stock_actual,0)    AS stock_actual
                      FROM public.ot_insumo i
                      JOIN public.producto p ON p.id_producto = i.id_producto
                      LEFT JOIN stock s      ON s.id_producto = i.id_producto
                     WHERE i.id_ot=$1
                     ORDER BY i.item_nro";
                $ins = [];
                $rIns = pg_query_params($conn, $sqlIns, [$id_ot]);
                while ($row = pg_fetch_assoc($rIns)) {
                    $row['item_nro']      = (int)$row['item_nro'];
                    $row['id_producto']   = (int)$row['id_producto'];
                    $row['cantidad']      = (float)$row['cantidad'];
                    $row['es_fraccion']   = normalizar_bool($row['es_fraccion']);
                    $row['stock_actual']  = (float)$row['stock_actual'];
                    $ins[] = $row;
                }

                json_ok(['cab' => $cab, 'det' => $det, 'insumos' => $ins]);
            }

        case 'list_catalogos': {
                $sqlServ = "SELECT id_producto, nombre, tipo_item, COALESCE(duracion_min,30) AS duracion_min,
                         precio_unitario, COALESCE(tipo_iva,'EXE') AS tipo_iva
                  FROM public.producto
                  WHERE estado='Activo' AND tipo_item IN ('S','D')
                  ORDER BY CASE WHEN tipo_item='S' THEN 0 ELSE 1 END, nombre";

                $sqlIns = "WITH stock AS (
                   SELECT id_producto,
                          COALESCE(SUM(CASE WHEN tipo_movimiento='entrada' THEN cantidad
                                            WHEN tipo_movimiento='salida' THEN -cantidad
                                            ELSE 0 END),0) AS stock_actual
                     FROM public.movimiento_stock
                    GROUP BY id_producto
                 )
                 SELECT p.id_producto,
                        p.nombre,
                        p.precio_unitario,
                        p.tipo_item,
                        COALESCE(p.es_fraccion,false)            AS es_fraccion,
                        p.id_producto_padre,
                        COALESCE(p.factor_equivalencia,0)::float AS factor_equivalencia,
                        COALESCE(p.unidad_base,'')               AS unidad_base,
                        COALESCE(s.stock_actual,0)               AS stock_actual
                   FROM public.producto p
                   LEFT JOIN stock s ON s.id_producto = p.id_producto
                  WHERE p.estado='Activo'
                  ORDER BY p.nombre";

                $serv = pg_query($conn, $sqlServ);
                $ins  = pg_query($conn, $sqlIns);
                if (!$serv || !$ins) json_error('No se pudieron cargar catálogos');

                $servRows = [];
                while ($row = pg_fetch_assoc($serv)) {
                    $row['id_producto'] = (int)$row['id_producto'];
                    $row['duracion_min'] = (int)$row['duracion_min'];
                    $row['precio_unitario'] = (float)$row['precio_unitario'];
                    $row['tipo_iva'] = normalizar_tipo_iva_codigo($row['tipo_iva']);
                    $servRows[] = $row;
                }

                $insRows = [];
                while ($row = pg_fetch_assoc($ins)) {
                    $row['id_producto']         = (int)$row['id_producto'];
                    $row['precio_unitario']     = (float)$row['precio_unitario'];
                    $row['stock_actual']        = (float)$row['stock_actual'];
                    $row['es_fraccion']         = normalizar_bool($row['es_fraccion']);
                    $row['factor_equivalencia'] = (float)$row['factor_equivalencia'];
                    $row['id_producto_padre']   = $row['id_producto_padre'] !== null ? (int)$row['id_producto_padre'] : null;
                    $insRows[] = $row;
                }

                json_ok(['servicios' => $servRows, 'insumos' => $insRows]);
            }

        case 'list_solicitudes_cliente': {
                $id_ot = (int)($in['id_ot'] ?? 0);
                $id_cliente = (int)($in['id_cliente'] ?? 0);

                if ($id_ot > 0) {
                    $rCli = pg_query_params($conn, "SELECT id_cliente FROM public.ot_cab WHERE id_ot=$1", [$id_ot]);
                    if (!$rCli || pg_num_rows($rCli) === 0) json_error('OT no encontrada', 404);
                    $id_cliente = (int)pg_fetch_result($rCli, 0, 0);
                }
                if ($id_cliente <= 0) json_error('id_cliente requerido');

                $sqlSol = "SELECT s.id_solicitud,
                        s.estado,
                        s.notas,
                        s.created_at
                 FROM public.solicitud_cliente s
                 WHERE s.id_cliente=$1
                   AND s.estado IN ('Abierta','Pendiente')
                 ORDER BY s.created_at DESC";
                $rSol = pg_query_params($conn, $sqlSol, [$id_cliente]);
                if (!$rSol) json_error('No se pudieron listar solicitudes');

                $solicitudes = [];
                while ($sol = pg_fetch_assoc($rSol)) {
                    $id_solicitud = (int)$sol['id_solicitud'];
                    $items = [];
                    $sqlItems = "SELECT sci.id_item,
                            sci.id_producto,
                            p.nombre,
                            sci.cantidad,
                            COALESCE(p.precio_unitario,0) AS precio_unitario,
                            COALESCE(p.tipo_iva,'EXE') AS tipo_iva,
                            sci.prioridad,
                            sci.nota
                     FROM public.solicitud_cliente_item sci
                     JOIN public.producto p ON p.id_producto = sci.id_producto
                     WHERE sci.id_solicitud=$1
                     ORDER BY p.nombre";
                    $rItems = pg_query_params($conn, $sqlItems, [$id_solicitud]);
                    if ($rItems) {
                        while ($it = pg_fetch_assoc($rItems)) {
                            $it['id_item'] = (int)$it['id_item'];
                            $it['id_producto'] = (int)$it['id_producto'];
                            $it['cantidad'] = (float)$it['cantidad'];
                            $it['precio_unitario'] = (float)$it['precio_unitario'];
                            $it['tipo_iva'] = normalizar_tipo_iva_codigo($it['tipo_iva']);
                            $items[] = $it;
                        }
                    }
                    $sol['id_solicitud'] = $id_solicitud;
                    $sol['items'] = $items;
                    $solicitudes[] = $sol;
                }

                json_ok(['rows' => $solicitudes]);
            }

        case 'create_from_reserva': {
                $id_reserva = (int)($in['id_reserva'] ?? 0);
                if ($id_reserva <= 0) json_error('id_reserva requerido');

                $sql = "SELECT rc.*, p.id_profesional
          FROM public.reserva_cab rc
          LEFT JOIN public.profesional p ON p.id_profesional = rc.id_profesional
          WHERE rc.id_reserva = $1 LIMIT 1";
                $r = pg_query_params($conn, $sql, [$id_reserva]);
                if (!$r || pg_num_rows($r) === 0) json_error('Reserva no encontrada', 404);

                $row = pg_fetch_assoc($r);
                $estadoReserva = ucfirst(strtolower($row['estado'] ?? ''));
                if (in_array($estadoReserva, ['En ot', 'Usada', 'Procesada'], true)) {
                    json_error('La reserva ya no está disponible para generar OT');
                }

                $id_cliente = (int)$row['id_cliente'];
                $id_prof_original = $row['id_profesional'] !== null ? (int)$row['id_profesional'] : null;
                $fecha_prog = $row['fecha_reserva'];
                $hora_prog  = substr($row['inicio_ts'], 11, 5);

                $ya = pg_query_params($conn, "SELECT id_ot FROM public.ot_cab WHERE id_reserva=$1 LIMIT 1", [$id_reserva]);
                if ($ya && pg_num_rows($ya) > 0) {
                    $existing = (int)pg_fetch_result($ya, 0, 0);
                    json_ok(['id_ot' => $existing, 'mensaje' => 'Ya existía una OT para la reserva']);
                }

                pg_query($conn, 'BEGIN');

                $sqlCab = "INSERT INTO public.ot_cab
               (id_reserva,id_cliente,id_profesional,fecha_programada,hora_programada,
                estado,notas,creado_el)
             VALUES ($1,$2,$3,$4,$5,'Programada',$6,now())
             RETURNING id_ot";
                $rCab = pg_query_params($conn, $sqlCab, [
                    $id_reserva,
                    $id_cliente,
                    $id_prof_original,
                    $fecha_prog,
                    $hora_prog,
                    $row['notas']
                ]);
                if (!$rCab) {
                    pg_query($conn, 'ROLLBACK');
                    json_error('No se pudo crear OT');
                }
                $id_ot = (int)pg_fetch_result($rCab, 0, 0);

                $sqlDet = "SELECT rd.id_producto,
                    rd.descripcion,
                    rd.precio_unitario,
                    rd.tipo_iva,
                    rd.duracion_min,
                    rd.cantidad,
                    pr.tipo_item
             FROM public.reserva_det rd
             LEFT JOIN public.producto pr ON pr.id_producto = rd.id_producto
             WHERE rd.id_reserva=$1
             ORDER BY rd.descripcion";
                $rDet = pg_query_params($conn, $sqlDet, [$id_reserva]);
                while ($det = pg_fetch_assoc($rDet)) {
                    $tipo_item = $det['tipo_item'] ?? 'S';
                    if (!in_array($tipo_item, ['S', 'D'], true)) $tipo_item = 'S';
                    $tipo_iva = normalizar_tipo_iva_codigo($det['tipo_iva'] ?? 'EXE');

                    $sqlInsDet = "INSERT INTO public.ot_det
                   (id_ot, id_producto, descripcion, tipo_item, cantidad,
                    precio_unitario, tipo_iva, duracion_min)
                  VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
                    $ok = pg_query_params($conn, $sqlInsDet, [
                        $id_ot,
                        $det['id_producto'] !== null ? (int)$det['id_producto'] : null,
                        $det['descripcion'],
                        $tipo_item,
                        (float)$det['cantidad'],
                        (float)$det['precio_unitario'],
                        $tipo_iva,
                        (int)$det['duracion_min']
                    ]);
                    if (!$ok) {
                        pg_query($conn, 'ROLLBACK');
                        json_error('Error copiando detalle de reserva');
                    }
                }

                $updRes = pg_query_params(
                    $conn,
                    "UPDATE public.reserva_cab
      SET estado='En OT'
    WHERE id_reserva=$1",
                    [$id_reserva]
                );

                if (!$updRes) {
                    pg_query($conn, 'ROLLBACK');
                    json_error('No se pudo actualizar la reserva');
                }

                pg_query($conn, 'COMMIT');
                json_ok(['id_ot' => $id_ot]);
            }


        case 'save_ot_item': {
                $id_ot    = (int)($in['id_ot'] ?? 0);
                $item_nro = (int)($in['item_nro'] ?? 0);
                $id_prod  = $in['id_producto'] !== null ? (int)$in['id_producto'] : null;
                $desc     = s($in['descripcion'] ?? '');
                $tipo     = strtoupper(s($in['tipo_item'] ?? 'S'));
                $cant     = (float)($in['cantidad'] ?? 1);
                $precio   = (float)($in['precio_unitario'] ?? 0);
                $tipo_iva = normalizar_tipo_iva_codigo($in['tipo_iva'] ?? 'EXE');
                $dur      = (int)($in['duracion_min'] ?? 0);
                $obs      = s($in['observaciones'] ?? null);

                if ($id_ot <= 0) json_error('id_ot requerido');
                if ($desc === '') json_error('Descripción requerida');
                if (!in_array($tipo, ['S', 'D'], true)) json_error('tipo_item inválido');
                if ($cant <= 0) json_error('Cantidad inválida');
                if ($tipo_iva === '') $tipo_iva = 'EXE';

                if ($item_nro <= 0) {
                    $sql = "INSERT INTO public.ot_det
                  (id_ot, id_producto, descripcion, tipo_item, cantidad,
                   precio_unitario, tipo_iva, duracion_min, observaciones)
                VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)
                RETURNING item_nro";
                    $res = pg_query_params($conn, $sql, [
                        $id_ot,
                        $id_prod,
                        $desc,
                        $tipo,
                        $cant,
                        $precio,
                        $tipo_iva,
                        $dur,
                        $obs
                    ]);
                    if (!$res) json_error('No se pudo agregar el ítem');
                    $item_nro = (int)pg_fetch_result($res, 0, 0);
                } else {
                    $sql = "UPDATE public.ot_det
                   SET id_producto=$3,
                       descripcion=$4,
                       tipo_item=$5,
                       cantidad=$6,
                       precio_unitario=$7,
                       tipo_iva=$8,
                       duracion_min=$9,
                       observaciones=$10
                 WHERE id_ot=$1 AND item_nro=$2";
                    $res = pg_query_params($conn, $sql, [
                        $id_ot,
                        $item_nro,
                        $id_prod,
                        $desc,
                        $tipo,
                        $cant,
                        $precio,
                        $tipo_iva,
                        $dur,
                        $obs
                    ]);
                    if (!$res) json_error('No se pudo actualizar el ítem');
                }
                json_ok(['item_nro' => $item_nro]);
            }

        case 'delete_ot_item': {
                $id_ot = (int)($in['id_ot'] ?? 0);
                $item  = (int)($in['item_nro'] ?? 0);
                if ($id_ot <= 0 || $item <= 0) json_error('id_ot y item_nro requeridos');
                $sql = "DELETE FROM public.ot_det WHERE id_ot=$1 AND item_nro=$2";
                $res = pg_query_params($conn, $sql, [$id_ot, $item]);
                if (!$res) json_error('No se pudo eliminar el ítem');
                json_ok();
            }

        case 'save_ot_insumo': {
                $id_ot    = (int)($in['id_ot'] ?? 0);
                $item_nro = (int)($in['item_nro'] ?? 0);
                $id_prod  = (int)($in['id_producto'] ?? 0);
                $cant     = (float)($in['cantidad'] ?? 0);
                $dep      = s($in['deposito'] ?? null);
                $lote     = s($in['lote'] ?? null);
                $coment   = s($in['comentario'] ?? null);

                if ($id_ot <= 0) json_error('id_ot requerido');
                if ($id_prod <= 0) json_error('id_producto requerido');
                if ($cant <= 0) json_error('Cantidad inválida');

                $sqlProd = "SELECT es_fraccion FROM public.producto WHERE id_producto=$1 AND estado='Activo'";
                $rProd = pg_query_params($conn, $sqlProd, [$id_prod]);
                if (!$rProd || pg_num_rows($rProd) === 0) {
                    json_error('El producto seleccionado no existe o está inactivo');
                }
                $esFraccion = normalizar_bool(pg_fetch_result($rProd, 0, 0));
                if (!$esFraccion) {
                    json_error('El producto debe ser un fraccionado (es_fraccion=true)');
                }

                if ($item_nro <= 0) {
                    $sql = "INSERT INTO public.ot_insumo
                  (id_ot, id_producto, cantidad, deposito, lote, comentario)
                VALUES ($1,$2,$3,$4,$5,$6)
                RETURNING item_nro";
                    $res = pg_query_params($conn, $sql, [
                        $id_ot,
                        $id_prod,
                        $cant,
                        $dep,
                        $lote,
                        $coment
                    ]);
                    if (!$res) json_error('No se pudo agregar el insumo');
                    $item_nro = (int)pg_fetch_result($res, 0, 0);
                } else {
                    $sql = "UPDATE public.ot_insumo
                   SET id_producto=$3,
                       cantidad=$4,
                       deposito=$5,
                       lote=$6,
                       comentario=$7
                 WHERE id_ot=$1 AND item_nro=$2";
                    $res = pg_query_params($conn, $sql, [
                        $id_ot,
                        $item_nro,
                        $id_prod,
                        $cant,
                        $dep,
                        $lote,
                        $coment
                    ]);
                    if (!$res) json_error('No se pudo actualizar el insumo');
                }
                json_ok(['item_nro' => $item_nro]);
            }

        case 'delete_ot_insumo': {
                $id_ot = (int)($in['id_ot'] ?? 0);
                $item  = (int)($in['item_nro'] ?? 0);
                if ($id_ot <= 0 || $item <= 0) json_error('id_ot y item_nro requeridos');
                $sql = "DELETE FROM public.ot_insumo WHERE id_ot=$1 AND item_nro=$2";
                $res = pg_query_params($conn, $sql, [$id_ot, $item]);
                if (!$res) json_error('No se pudo eliminar el insumo');
                json_ok();
            }

        case 'update_ot_state': {
                $id_ot  = (int)($in['id_ot'] ?? 0);
                $estado = normalizar_estado($in['estado'] ?? '');
                if ($id_ot <= 0) json_error('id_ot requerido');
                $fields = ['estado' => $estado, 'actualizado_el' => date('c')];
                if ($estado === 'En ejecución') {
                    $fields['inicio_real'] = $in['inicio_real'] ?? date('Y-m-d H:i:s');
                } elseif ($estado === 'Completada') {
                    if (!empty($in['finalizar_con_fecha'])) {
                        $fields['fin_real'] = date('Y-m-d H:i:s');
                    } else {
                        $fields['fin_real'] = $in['fin_real'] ?? date('Y-m-d H:i:s');
                    }
                    if (empty($fields['inicio_real'])) {
                        $fields['inicio_real'] = $in['inicio_real'] ?? date('Y-m-d H:i:s');
                    }
                }
                $set = [];
                $params = [];
                foreach ($fields as $k => $v) {
                    $set[] = "$k=$" . (count($params) + 1);
                    $params[] = $v;
                }
                $params[] = $id_ot;
                $sql = "UPDATE public.ot_cab SET " . implode(',', $set) . " WHERE id_ot=$" . count($params);
                $res = pg_query_params($conn, $sql, $params);
                if (!$res) json_error('No se pudo actualizar el estado');
                json_ok();
            }

        case 'update_ot_notas': {
                $id_ot = (int)($in['id_ot'] ?? 0);
                $notas = s($in['notas'] ?? null);
                if ($id_ot <= 0) json_error('id_ot requerido');
                $sql = "UPDATE public.ot_cab SET notas=$2, actualizado_el=now() WHERE id_ot=$1";
                $res = pg_query_params($conn, $sql, [$id_ot, $notas]);
                if (!$res) json_error('No se pudo actualizar notas');
                json_ok();
            }

        case 'update_ot_prof': {
                $id_ot = (int)($in['id_ot'] ?? 0);
                $id_prof = $in['id_profesional'] !== null ? (int)$in['id_profesional'] : null;
                if ($id_ot <= 0) json_error('id_ot requerido');
                $sql = "UPDATE public.ot_cab SET id_profesional=$2, actualizado_el=now() WHERE id_ot=$1";
                $res = pg_query_params($conn, $sql, [$id_ot, $id_prof]);
                if (!$res) json_error('No se pudo actualizar profesional');
                json_ok();
            }

        case 'create_pedido_from_ot': {
                $id_ot = (int)($in['id_ot'] ?? 0);
                $id_solicitud = isset($in['id_solicitud']) && $in['id_solicitud'] !== '' ? (int)$in['id_solicitud'] : null;
                $observacion = s($in['observacion'] ?? null);

                if ($id_ot <= 0) json_error('id_ot requerido');

                $rOt = pg_query_params($conn, "SELECT id_cliente, id_pedido FROM public.ot_cab WHERE id_ot=$1", [$id_ot]);
                if (!$rOt || pg_num_rows($rOt) === 0) json_error('OT no encontrada', 404);
                $ot = pg_fetch_assoc($rOt);
                $id_cliente = (int)$ot['id_cliente'];
                if ($ot['id_pedido'] !== null) {
                    json_ok(['id_pedido' => (int)$ot['id_pedido'], 'mensaje' => 'La OT ya tiene un pedido asociado.']);
                }

                $lineas = [];
                $otTieneProductos = false;

                $sqlOtItems = "SELECT id_producto, cantidad, precio_unitario, tipo_iva
                     FROM public.ot_det
                     WHERE id_ot=$1 AND id_producto IS NOT NULL";
                $rItemsOt = pg_query_params($conn, $sqlOtItems, [$id_ot]);
                if (!$rItemsOt) json_error('No se pudieron obtener ítems de la OT');

                while ($row = pg_fetch_assoc($rItemsOt)) {
                    $id_prod = (int)$row['id_producto'];
                    $cantidad = (float)$row['cantidad'];
                    if ($id_prod <= 0 || $cantidad <= 0) continue;
                    $otTieneProductos = true;
                    $precio = (float)$row['precio_unitario'];
                    $tipo_iva = normalizar_tipo_iva_codigo($row['tipo_iva'] ?? 'EXE');
                    if (!isset($lineas[$id_prod])) {
                        $lineas[$id_prod] = ['cantidad' => 0.0, 'importe' => 0.0, 'tipo_iva' => $tipo_iva];
                    } else {
                        if ($lineas[$id_prod]['tipo_iva'] === 'EXE' && $tipo_iva !== 'EXE') {
                            $lineas[$id_prod]['tipo_iva'] = $tipo_iva;
                        }
                    }
                    $lineas[$id_prod]['cantidad'] += $cantidad;
                    $lineas[$id_prod]['importe'] += $cantidad * $precio;
                }

                $solicitudInfo = null;
                if ($id_solicitud !== null) {
                    $rSol = pg_query_params($conn, "SELECT id_solicitud,id_cliente,estado FROM public.solicitud_cliente WHERE id_solicitud=$1", [$id_solicitud]);
                    if (!$rSol || pg_num_rows($rSol) === 0) json_error('Solicitud no encontrada', 404);
                    $solicitudInfo = pg_fetch_assoc($rSol);
                    if ((int)$solicitudInfo['id_cliente'] !== $id_cliente) {
                        json_error('La solicitud pertenece a otro cliente');
                    }
                    $estadoSol = ucfirst(strtolower($solicitudInfo['estado'] ?? ''));
                    if (!in_array($estadoSol, ['Abierta', 'Pendiente'], true)) {
                        json_error('La solicitud ya fue procesada');
                    }

                    $sqlSolItems = "SELECT sci.id_producto,
                               sci.cantidad,
                               COALESCE(p.precio_unitario,0) AS precio_unitario,
                               COALESCE(p.tipo_iva,'EXE') AS tipo_iva
                        FROM public.solicitud_cliente_item sci
                        JOIN public.producto p ON p.id_producto = sci.id_producto
                        WHERE sci.id_solicitud=$1";
                    $rSolItems = pg_query_params($conn, $sqlSolItems, [$id_solicitud]);
                    if (!$rSolItems) json_error('No se pudieron obtener ítems de la solicitud');

                    while ($row = pg_fetch_assoc($rSolItems)) {
                        $id_prod = (int)$row['id_producto'];
                        $cantidad = (float)$row['cantidad'];
                        if ($id_prod <= 0 || $cantidad <= 0) continue;
                        $precio = (float)$row['precio_unitario'];
                        $tipo_iva = normalizar_tipo_iva_codigo($row['tipo_iva'] ?? 'EXE');
                        if (!isset($lineas[$id_prod])) {
                            $lineas[$id_prod] = ['cantidad' => 0.0, 'importe' => 0.0, 'tipo_iva' => $tipo_iva];
                        } else {
                            if ($lineas[$id_prod]['tipo_iva'] === 'EXE' && $tipo_iva !== 'EXE') {
                                $lineas[$id_prod]['tipo_iva'] = $tipo_iva;
                            }
                        }
                        $lineas[$id_prod]['cantidad'] += $cantidad;
                        $lineas[$id_prod]['importe'] += $cantidad * $precio;
                    }
                }

                if (!$otTieneProductos && $id_solicitud === null) {
                    json_error('La OT no tiene ítems con producto asignado');
                }
                if (empty($lineas)) {
                    json_error('No hay productos para generar el pedido');
                }

                $detalles = [];
                $total_bruto = 0.0;
                $total_desc = 0.0;
                $total_iva  = 0.0;

                foreach ($lineas as $id_prod => $data) {
                    $cantidad = round_money($data['cantidad'], 2);
                    if ($cantidad <= 0) {
                        continue;
                    }
                    $importe = round_money($data['importe'], 2);
                    $tipo_iva = $data['tipo_iva'] ?? 'EXE';
                    $tasa = tasa_iva($tipo_iva);

                    if ($tasa > 0) {
                        $base = round_money($importe / (1 + $tasa), 2);
                        $iva  = round_money($importe - $base, 2);
                    } else {
                        $base = $importe;
                        $iva  = 0.0;
                    }

                    $precio_unitario = $cantidad != 0 ? round_money($importe / $cantidad, 2) : 0.0;
                    $subtotal_bruto  = $base;
                    $subtotal_neto   = round_money($base + $iva, 2);

                    $detalles[] = [
                        'id_producto' => $id_prod,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio_unitario,
                        'descuento' => 0.0,
                        'tipo_iva' => $tipo_iva,
                        'iva_monto' => $iva,
                        'subtotal_bruto' => $subtotal_bruto,
                        'subtotal_neto' => $subtotal_neto
                    ];
                    $total_bruto += $subtotal_bruto;
                    $total_iva   += $iva;
                }

                if (empty($detalles)) {
                    json_error('No hay líneas válidas para generar el pedido');
                }

                $total_neto = ($total_bruto - $total_desc) + $total_iva;

                if (!pg_query($conn, 'BEGIN')) {
                    json_error('No se pudo iniciar la transacción');
                }

                $obs_final = $observacion !== null ? $observacion : ('Generado desde OT #' . $id_ot);
                $creado_por = $_SESSION['nombre_usuario'] ?? null;

                $sqlCab = "INSERT INTO public.pedido_cab
                   (id_cliente, observacion, estado, total_bruto, total_descuento, total_iva, total_neto, creado_por)
                 VALUES ($1,$2,'Pendiente',$3,$4,$5,$6,$7)
                 RETURNING id_pedido";
                $rCab = pg_query_params($conn, $sqlCab, [
                    $id_cliente,
                    $obs_final,
                    round_money($total_bruto, 2),
                    round_money($total_desc, 2),
                    round_money($total_iva, 2),
                    round_money($total_neto, 2),
                    $creado_por
                ]);
                if (!$rCab) {
                    pg_query($conn, 'ROLLBACK');
                    json_error('No se pudo crear el pedido');
                }
                $id_pedido = (int)pg_fetch_result($rCab, 0, 0);

                $sqlDet = "INSERT INTO public.pedido_det
                   (id_pedido, id_producto, cantidad, precio_unitario, descuento, tipo_iva, iva_monto, subtotal_bruto, subtotal_neto)
                 VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)";
                foreach ($detalles as $det) {
                    $ok = pg_query_params($conn, $sqlDet, [
                        $id_pedido,
                        $det['id_producto'],
                        $det['cantidad'],
                        $det['precio_unitario'],
                        $det['descuento'],
                        $det['tipo_iva'],
                        $det['iva_monto'],
                        $det['subtotal_bruto'],
                        $det['subtotal_neto']
                    ]);
                    if (!$ok) {
                        pg_query($conn, 'ROLLBACK');
                        json_error('No se pudo insertar el detalle del pedido');
                    }
                }

                $updOt = pg_query_params($conn, "UPDATE public.ot_cab SET id_pedido=$2, actualizado_el=now() WHERE id_ot=$1", [$id_ot, $id_pedido]);
                if (!$updOt) {
                    pg_query($conn, 'ROLLBACK');
                    json_error('No se pudo vincular el pedido con la OT');
                }

                if ($id_solicitud !== null) {
                    $updSol = pg_query_params($conn, "UPDATE public.solicitud_cliente SET estado='Procesada', updated_at=now() WHERE id_solicitud=$1", [$id_solicitud]);
                    if (!$updSol) {
                        pg_query($conn, 'ROLLBACK');
                        json_error('No se pudo actualizar la solicitud');
                    }
                }

                // descuenta stock de insumos utilizados
                $sqlInsumosOT = "
                  SELECT id_producto, SUM(cantidad) AS total
                    FROM public.ot_insumo
                   WHERE id_ot = $1
                   GROUP BY id_producto
                ";
                $resInsumos = pg_query_params($conn, $sqlInsumosOT, [$id_ot]);
                if (!$resInsumos) {
                    pg_query($conn, 'ROLLBACK');
                    json_error('No se pudieron obtener los insumos de la OT');
                }

                while ($insumo = pg_fetch_assoc($resInsumos)) {
                    $idProductoInsumo = (int)$insumo['id_producto'];
                    $totalConsumido   = (float)$insumo['total'];

                    if ($idProductoInsumo <= 0 || $totalConsumido <= 0) {
                        continue;
                    }

                    $sqlMov = "
  INSERT INTO public.movimiento_stock
    (id_producto, tipo_movimiento, cantidad, fecha, observacion)
  VALUES ($1, 'salida', $2, NOW(), $3)
";
                    $okMov = pg_query_params($conn, $sqlMov, [
                        $idProductoInsumo,
                        $totalConsumido,
                        "Consumo OT #{$id_ot}"
                    ]);

                    if (!$okMov) {
                        pg_query($conn, 'ROLLBACK');
                        json_error('No se pudo registrar el movimiento de stock de los insumos');
                    }
                }

                pg_query($conn, 'COMMIT');
                json_ok(['id_pedido' => $id_pedido]);
            }

        default:
            json_error('op no reconocido');
    }
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'constraint') !== false) {
        $msg = 'Violación de constraint: revisá los datos.';
    }
    json_error($msg);
}
