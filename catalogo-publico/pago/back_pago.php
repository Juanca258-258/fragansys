<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../conexion.php';
require_once __DIR__ . '/../../helpers.php';
require_once __DIR__ . '/../../pagos/paypal.php';
require_once __DIR__ . '/../../pagos/inventario_pedido.php';

// Un pedido lo puede pagar el cliente dueño (con sesión iniciada) o quien lo acaba de crear en este navegador.
function puedePagarPedido($pedido) {
    $porPagar = isset($_SESSION['pedidos_por_pagar']) ? $_SESSION['pedidos_por_pagar'] : array();
    return (isset($_SESSION['cliente_id']) && (int)$_SESSION['cliente_id'] === (int)$pedido['cliente_id'])
        || in_array((int)$pedido['id'], $porPagar, true);
}

// Pedido que todavía se puede pagar: no cancelado y con pago pendiente o fallido.
function pedidoPagable($pedido) {
    return $pedido['estado_pedido'] !== 'cancelado' && in_array($pedido['estado_pago'], array('pendiente', 'fallido'), true);
}

function obtenerPedidoPago($db, $pedidoId, $bloquear = false) {
    $stmt = $db->prepare("SELECT * FROM pedidos WHERE id = ?" . ($bloquear ? ' FOR UPDATE' : ''));
    $stmt->bind_param("i", $pedidoId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function obtenerDetallePago($db, $pedidoId) {
    $stmt = $db->prepare(
        "SELECT pd.cantidad, pd.subtotal,
                IF(pd.tipo = 'combo', CONCAT('Combo ', c.nombre),
                   CONCAT(pf.nombre, ' · ', IF(pr.tipo = 'frasco', 'Frasco ', 'Decant '),
                          TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.ml)), ' ml')) AS nombre
         FROM pedido_detalle pd
         LEFT JOIN presentaciones pr ON pr.id = pd.presentacion_id
         LEFT JOIN perfumes pf ON pf.id = pr.perfume_id
         LEFT JOIN combos c ON c.id = pd.combo_id
         WHERE pd.pedido_id = ? ORDER BY pd.id");
    $stmt->bind_param("i", $pedidoId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
