<?php
// Inventario de los pedidos en línea. Regla: un pedido descuenta inventario en el momento en que queda PAGADO,
// y lo devuelve si un pedido pagado se cancela. Así "estado_pago = pagado" significa "ya se descontó".

// Lo que necesita el pedido por perfume: [perfume_id => ['nombre', 'frascos', 'ml']]. Incluye las piezas de los combos.
function necesidades_pedido($conn, $pedido_id) {
    $pedido_id = (int)$pedido_id;
    $res = $conn->query(
        "SELECT pf.id, pf.nombre, SUM(x.frascos) AS frascos, SUM(x.ml) AS ml FROM (
             SELECT pr.perfume_id,
                    IF(pr.tipo = 'frasco', pd.cantidad, 0)         AS frascos,
                    IF(pr.tipo = 'decant', pr.ml * pd.cantidad, 0) AS ml
             FROM pedido_detalle pd INNER JOIN presentaciones pr ON pr.id = pd.presentacion_id
             WHERE pd.pedido_id = $pedido_id AND pd.tipo = 'presentacion'
             UNION ALL
             SELECT pr.perfume_id,
                    IF(pr.tipo = 'frasco', pd.cantidad * cp.cantidad, 0),
                    IF(pr.tipo = 'decant', pr.ml * pd.cantidad * cp.cantidad, 0)
             FROM pedido_detalle pd
             INNER JOIN combo_presentaciones cp ON cp.combo_id = pd.combo_id
             INNER JOIN presentaciones pr ON pr.id = cp.presentacion_id
             WHERE pd.pedido_id = $pedido_id AND pd.tipo = 'combo'
         ) x INNER JOIN perfumes pf ON pf.id = x.perfume_id
         GROUP BY pf.id");
    $necesidades = [];
    while ($n = $res->fetch_assoc()) {
        $necesidades[(int)$n['id']] = ['nombre' => $n['nombre'], 'frascos' => (int)$n['frascos'], 'ml' => (float)$n['ml']];
    }
    return $necesidades;
}

// Revisa si hay existencias. Con $bloquear = true usa FOR UPDATE: llamarla dentro de una transacción.
// Devuelve null si alcanza, o el mensaje de lo que falta.
function revisar_existencias($conn, $necesidades, $bloquear = false) {
    if (!$necesidades) {
        return 'El pedido no tiene productos.';
    }
    $ids = implode(',', array_keys($necesidades));
    $res = $conn->query("SELECT id, nombre, frascos_sellados, ml_disponibles FROM perfumes WHERE id IN ($ids)"
                        . ($bloquear ? ' FOR UPDATE' : ''));
    while ($pf = $res->fetch_assoc()) {
        $n = $necesidades[(int)$pf['id']];
        if ($n['frascos'] > (int)$pf['frascos_sellados'] || $n['ml'] > (float)$pf['ml_disponibles'] + 0.0001) {
            return 'Lo sentimos, ya no hay suficiente "' . $pf['nombre'] . '" para tu pedido. No se hizo ningún cobro.';
        }
    }
    return null;
}

function mover_inventario_pedido($conn, $necesidades, $signo) {
    $stmt = $conn->prepare("UPDATE perfumes SET frascos_sellados = frascos_sellados + ?, ml_disponibles = ml_disponibles + ? WHERE id = ?");
    foreach ($necesidades as $pid => $n) {
        $frascos = $signo * $n['frascos'];
        $ml      = $signo * $n['ml'];
        $stmt->bind_param("idi", $frascos, $ml, $pid);
        $stmt->execute();
    }
}

function descontar_inventario_pedido($conn, $necesidades) {
    mover_inventario_pedido($conn, $necesidades, -1);
}

function devolver_inventario_pedido($conn, $pedido_id) {
    mover_inventario_pedido($conn, necesidades_pedido($conn, $pedido_id), 1);
}
