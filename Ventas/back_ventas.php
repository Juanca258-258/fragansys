<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");

function responder($datos) {
    echo json_encode($datos);
    exit;
}

function fallar($error) {
    responder(['ok' => false, 'error' => $error]);
}

$accion = $_POST['accion'] ?? '';

if ($accion == "guardar") {
    $metodo   = $_POST['metodo_pago'] ?? '';
    $cliente  = trim($_POST['cliente_nombre'] ?? '');
    $telefono = trim($_POST['cliente_telefono'] ?? '');
    $recibido = trim($_POST['monto_recibido'] ?? '');

    if (!in_array($metodo, ['efectivo', 'transferencia', 'tarjeta'], true)) fallar('Selecciona el método de pago.');

    $cantidades = [];
    foreach ($_POST['presentacion_id'] ?? [] as $i => $pid) {
        $pid  = (int)$pid;
        $cant = (int)($_POST['cantidad'][$i] ?? 0);
        if ($pid <= 0 || $cant <= 0) fallar('Revisa las cantidades de los productos.');
        $cantidades[$pid] = ($cantidades[$pid] ?? 0) + $cant;
    }
    if (empty($cantidades)) fallar('Agrega al menos un producto a la venta.');

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn->begin_transaction();

        $ids = implode(',', array_keys($cantidades));
        $presentaciones = [];
        $res = $conn->query("SELECT pr.id, pr.tipo, pr.ml, pr.precio, pr.perfume_id
                             FROM presentaciones pr
                             INNER JOIN perfumes pf ON pr.perfume_id = pf.id
                             WHERE pr.id IN ($ids) AND pr.activo = 1 AND pf.activo = 1");
        while ($pr = $res->fetch_assoc()) {
            $presentaciones[$pr['id']] = $pr;
        }
        if (count($presentaciones) !== count($cantidades)) {
            $conn->rollback();
            fallar('Uno de los productos ya no está disponible. Vuelve a abrir la venta.');
        }

        $necesario = [];
        $total = 0;
        foreach ($cantidades as $id => $cant) {
            $pr  = $presentaciones[$id];
            $pid = $pr['perfume_id'];
            $necesario[$pid] = $necesario[$pid] ?? ['frascos' => 0, 'ml' => 0];
            if ($pr['tipo'] === 'frasco') {
                $necesario[$pid]['frascos'] += $cant;
            } else {
                $necesario[$pid]['ml'] += $pr['ml'] * $cant;
            }
            $total += $pr['precio'] * $cant;
        }
        $total = round($total, 2);

        // Los decants de un mismo perfume comparten el frasco abierto, por eso se valida la suma por perfume.
        // FOR UPDATE evita que dos ventas simultáneas vendan el mismo líquido.
        $pids = implode(',', array_keys($necesario));
        $res = $conn->query("SELECT id, nombre, frascos_sellados, ml_disponibles FROM perfumes WHERE id IN ($pids) FOR UPDATE");
        while ($pf = $res->fetch_assoc()) {
            $n = $necesario[$pf['id']];
            if ($n['frascos'] > $pf['frascos_sellados']) {
                $conn->rollback();
                fallar("No hay suficientes frascos sellados de {$pf['nombre']} (hay {$pf['frascos_sellados']}).");
            }
            if ($n['ml'] > $pf['ml_disponibles']) {
                $conn->rollback();
                fallar("No hay suficientes ml para decants de {$pf['nombre']} (hay " . (float)$pf['ml_disponibles'] . " ml). Abre un frasco si tienes sellados.");
            }
        }

        $monto_recibido = null;
        if ($metodo === 'efectivo') {
            $monto_recibido = round((float)$recibido, 2);
            if ($recibido === '' || $monto_recibido < $total) {
                $conn->rollback();
                fallar('El efectivo recibido no cubre el total de $' . number_format($total, 2) . '.');
            }
        }

        $usuario_id = (int)$_SESSION['id_usuario'];
        $cliente    = $cliente !== '' ? $cliente : null;
        $telefono   = $telefono !== '' ? $telefono : null;

        $stmt = $conn->prepare("INSERT INTO ventas (usuario_id, cliente_nombre, cliente_telefono, total, metodo_pago, monto_recibido)
                                VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("issdsd", $usuario_id, $cliente, $telefono, $total, $metodo, $monto_recibido);
        $stmt->execute();
        $venta_id = $conn->insert_id;

        $det = $conn->prepare("INSERT INTO venta_detalle (venta_id, tipo, presentacion_id, cantidad, precio_unitario, subtotal)
                               VALUES (?, 'presentacion', ?, ?, ?, ?)");
        foreach ($cantidades as $id => $cant) {
            $precio = (float)$presentaciones[$id]['precio'];
            $linea  = round($precio * $cant, 2);
            $det->bind_param("iiidd", $venta_id, $id, $cant, $precio, $linea);
            $det->execute();
        }

        $inv = $conn->prepare("UPDATE perfumes SET frascos_sellados = frascos_sellados - ?, ml_disponibles = ml_disponibles - ? WHERE id = ?");
        foreach ($necesario as $pid => $n) {
            $inv->bind_param("idi", $n['frascos'], $n['ml'], $pid);
            $inv->execute();
        }

        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log($e);
        fallar('No se pudo registrar la venta: ' . $e->getMessage());
    }
    mysqli_report(MYSQLI_REPORT_OFF);

    registrar_movimiento($conn, 'Ventas', 'Agregar', "Venta #$venta_id por $" . number_format($total, 2) . " ($metodo)");
    responder(['ok' => true, 'venta_id' => $venta_id]);
}

if ($accion == "cancelar") {
    if ($_SESSION['rol'] !== 'admin') fallar('Solo un administrador puede cancelar ventas.');
    $id = (int)($_POST['id'] ?? 0);

    $conn->begin_transaction();
    $venta = mysqli_fetch_assoc(mysqli_query($conn, "SELECT estado, total FROM ventas WHERE id=$id FOR UPDATE"));
    if (!$venta || $venta['estado'] !== 'completada') {
        $conn->rollback();
        fallar('La venta no existe o ya estaba cancelada.');
    }

    mysqli_query($conn, "UPDATE perfumes pf
                         INNER JOIN (
                             SELECT pr.perfume_id,
                                    SUM(IF(pr.tipo = 'frasco', vd.cantidad, 0))         AS frascos,
                                    SUM(IF(pr.tipo = 'decant', pr.ml * vd.cantidad, 0)) AS ml
                             FROM venta_detalle vd
                             INNER JOIN presentaciones pr ON vd.presentacion_id = pr.id
                             WHERE vd.venta_id = $id AND vd.tipo = 'presentacion'
                             GROUP BY pr.perfume_id
                         ) d ON d.perfume_id = pf.id
                         SET pf.frascos_sellados = pf.frascos_sellados + d.frascos,
                             pf.ml_disponibles   = pf.ml_disponibles + d.ml");
    mysqli_query($conn, "UPDATE ventas SET estado='cancelada' WHERE id=$id");
    $conn->commit();

    registrar_movimiento($conn, 'Ventas', 'Cancelar', "Canceló la venta #$id por $" . number_format($venta['total'], 2) . " (inventario devuelto)");
    responder(['ok' => true]);
}

fallar('Acción no válida.');
