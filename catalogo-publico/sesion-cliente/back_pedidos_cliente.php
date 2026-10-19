<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../conexion.php';
require_once __DIR__ . '/back_login_cliente.php';

// Pasos del seguimiento en el orden en que avanza un pedido
$PASOS_PEDIDO = array(
    'nuevo'          => array('etiqueta' => 'Recibido',       'icono' => 'bi-receipt'),
    'confirmado'     => array('etiqueta' => 'Confirmado',     'icono' => 'bi-check2-circle'),
    'en_preparacion' => array('etiqueta' => 'En preparación', 'icono' => 'bi-box-seam'),
    'enviado'        => array('etiqueta' => 'Enviado',        'icono' => 'bi-truck'),
    'entregado'      => array('etiqueta' => 'Entregado',      'icono' => 'bi-house-check')
);

$ETIQUETAS_PAGO = array(
    'pendiente'   => array('Pago pendiente', 'warning'),
    'pagado'      => array('Pagado', 'success'),
    'fallido'     => array('Pago fallido', 'danger'),
    'reembolsado' => array('Reembolsado', 'secondary')
);

// Estados en los que el cliente todavía puede cancelar su pedido
$ESTADOS_CANCELABLES = array('nuevo', 'confirmado', 'en_preparacion');

function obtenerPedidosCliente($db, $clienteId) {
    $stmt = $db->prepare(
        "SELECT p.id, p.subtotal, p.costo_envio, p.total, p.estado_pago, p.estado_pedido,
                p.fecha_pedido, p.fecha_actualizacion,
                (SELECT COALESCE(SUM(pd.cantidad), 0) FROM pedido_detalle pd WHERE pd.pedido_id = p.id) AS total_items
         FROM pedidos p
         WHERE p.cliente_id = ?
         ORDER BY p.fecha_pedido DESC, p.id DESC"
    );
    $stmt->bind_param("i", $clienteId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Siempre se filtra por cliente_id para que nadie pueda ver pedidos ajenos cambiando el id
function obtenerPedidoCliente($db, $clienteId, $pedidoId) {
    $stmt = $db->prepare(
        "SELECT id, cliente_nombre, cliente_correo, subtotal, costo_envio, total, metodo_pago,
                estado_pago, estado_pedido, direccion_entrega, telefono_contacto, notas,
                fecha_pedido, fecha_actualizacion
         FROM pedidos
         WHERE id = ? AND cliente_id = ?"
    );
    $stmt->bind_param("ii", $pedidoId, $clienteId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function obtenerDetallePedido($db, $pedidoId) {
    $stmt = $db->prepare(
        "SELECT pd.tipo, pd.cantidad, pd.precio_unitario, pd.subtotal,
                CASE WHEN pd.tipo = 'presentacion'
                     THEN CONCAT(pf.nombre, ' — ', IF(pr.tipo = 'frasco', 'Frasco ', 'Decant '),
                                 TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.ml)), ' ml')
                     ELSE c.nombre END AS nombre_item,
                IF(pd.tipo = 'presentacion', pf.marca, 'Combo') AS marca,
                IF(pd.tipo = 'presentacion', pf.imagen, c.imagen) AS imagen
         FROM pedido_detalle pd
         LEFT JOIN presentaciones pr ON pd.presentacion_id = pr.id
         LEFT JOIN perfumes pf ON pr.perfume_id = pf.id
         LEFT JOIN combos c ON pd.combo_id = c.id
         WHERE pd.pedido_id = ?
         ORDER BY pd.id"
    );
    $stmt->bind_param("i", $pedidoId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function pedidoCancelable($estadoPedido) {
    global $ESTADOS_CANCELABLES;
    return in_array($estadoPedido, $ESTADOS_CANCELABLES, true);
}

function cancelarPedidoCliente($db, $clienteId, $pedidoId) {
    global $ESTADOS_CANCELABLES;
    $resultado = array('status' => 'error', 'message' => 'Pedido no encontrado.');

    $pedido = obtenerPedidoCliente($db, $clienteId, $pedidoId);
    if (!$pedido) {
        return $resultado;
    }

    if (!pedidoCancelable($pedido['estado_pedido'])) {
        $resultado['message'] = 'Este pedido ya no se puede cancelar.';
        return $resultado;
    }

    // La condición de estado va en el UPDATE por si el administrador lo cambió en este mismo momento
    $stmt = $db->prepare(
        "UPDATE pedidos SET estado_pedido = 'cancelado'
         WHERE id = ? AND cliente_id = ? AND estado_pedido IN ('" . implode("','", $ESTADOS_CANCELABLES) . "')"
    );
    $stmt->bind_param("ii", $pedidoId, $clienteId);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        $resultado['message'] = 'Este pedido ya no se puede cancelar.';
        return $resultado;
    }

    require_once __DIR__ . '/../../helpers.php';
    registrar_movimiento($db, 'Pedidos', 'Cancelar (cliente)',
        "Pedido #$pedidoId cancelado por el cliente: {$pedido['estado_pedido']} → cancelado", null);

    $resultado['status']  = 'success';
    $resultado['message'] = 'Tu pedido fue cancelado.';
    return $resultado;
}

// Identifica la versión actual de un pedido; si cambia, el seguimiento del cliente se refresca
function firmaPedido($pedido) {
    return $pedido['estado_pedido'] . '|' . $pedido['estado_pago'] . '|' . $pedido['fecha_actualizacion'];
}

function formatoFecha($fecha) {
    $meses = array('ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic');
    $t = strtotime($fecha);
    return date('j', $t) . ' ' . $meses[(int)date('n', $t) - 1] . ' ' . date('Y, H:i', $t);
}

/**
 * HTML del seguimiento de un pedido (línea de tiempo + botón de cancelar).
 * Se usa al cargar la página y también desde act_pedidos_cliente.php cuando el estado cambia.
 */
function htmlSeguimientoPedido($pedido) {
    global $PASOS_PEDIDO, $ETIQUETAS_PAGO;
    $id = (int)$pedido['id'];
    $pago = isset($ETIQUETAS_PAGO[$pedido['estado_pago']]) ? $ETIQUETAS_PAGO[$pedido['estado_pago']] : array($pedido['estado_pago'], 'secondary');

    ob_start();
    ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <span class="badge bg-<?= $pago[1] ?> bg-opacity-75 rounded-pill px-3 py-2"><?= htmlspecialchars($pago[0]) ?></span>
        <span class="small text-muted"><i class="bi bi-clock-history me-1"></i>Última actualización: <?= formatoFecha($pedido['fecha_actualizacion']) ?></span>
    </div>

    <?php if ($pedido['estado_pedido'] === 'cancelado'): ?>
        <div class="alert alert-danger d-flex align-items-center mb-0 rounded-3" role="alert">
            <i class="bi bi-x-circle fs-4 me-3"></i>
            <div>
                <strong>Pedido cancelado.</strong>
                <?php if ($pedido['estado_pago'] === 'pagado'): ?>
                    <div class="small">Nos pondremos en contacto contigo para el reembolso de tu pago.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <?php $pasoActual = array_search($pedido['estado_pedido'], array_keys($PASOS_PEDIDO), true); ?>
        <div class="seguimiento-pasos">
            <?php $i = 0; foreach ($PASOS_PEDIDO as $clave => $paso): ?>
                <?php $clase = $i < $pasoActual ? 'completado' : ($i === $pasoActual ? 'actual' : ''); ?>
                <div class="paso <?= $clase ?>">
                    <div class="paso-icono"><i class="bi <?= $paso['icono'] ?>"></i></div>
                    <div class="paso-texto"><?= $paso['etiqueta'] ?></div>
                </div>
            <?php $i++; endforeach; ?>
        </div>

        <?php if (pedidoCancelable($pedido['estado_pedido'])): ?>
            <div class="text-end mt-3">
                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="abrirCancelarPedido(<?= $id ?>)">
                    <i class="bi bi-x-circle me-1"></i> Cancelar pedido
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

// Datos para la vista: solo con sesión de cliente iniciada
$pedidosCliente = array();
if (clienteSesionActiva()) {
    $pedidosCliente = obtenerPedidosCliente($conn, (int)$_SESSION['cliente_id']);
}
