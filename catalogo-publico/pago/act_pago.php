<?php
// Llamadas de los botones de PayPal: 'crear_orden' (al dar clic en pagar) y 'capturar' (cuando el cliente ya aprobó).
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/back_pago.php';

function responderPago($datos) {
    echo json_encode($datos);
    exit;
}

$accion   = isset($_POST['accion']) ? $_POST['accion'] : '';
$pedidoId = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
$pedido   = $pedidoId ? obtenerPedidoPago($conn, $pedidoId) : null;

if (!$pedido || !puedePagarPedido($pedido)) {
    responderPago(array('status' => 'error', 'message' => 'Pedido no encontrado.'));
}
if ($pedido['estado_pago'] === 'pagado') {
    responderPago(array('status' => 'pagado', 'message' => 'Este pedido ya está pagado.'));
}
if (!pedidoPagable($pedido)) {
    responderPago(array('status' => 'error', 'message' => 'Este pedido ya no se puede pagar.'));
}

if ($accion === 'crear_orden') {
    // Se revisa antes de abrir PayPal para no hacer pasar al cliente por el pago si algo se agotó.
    $faltante = revisar_existencias($conn, necesidades_pedido($conn, $pedidoId));
    if ($faltante) {
        responderPago(array('status' => 'error', 'message' => $faltante));
    }

    $config  = obtener_config($conn);
    $ordenId = paypal_crear_orden($pedido, $config['negocio_nombre']);
    if (!$ordenId) {
        responderPago(array('status' => 'error', 'message' => 'No pudimos conectar con PayPal. Intenta de nuevo en un momento.'));
    }

    // Se guarda la orden para que al cobrar solo se acepte la que se creó para este pedido.
    $stmt = $conn->prepare("UPDATE pedidos SET paypal_order_id = ? WHERE id = ? AND estado_pago IN ('pendiente', 'fallido')");
    $stmt->bind_param("si", $ordenId, $pedidoId);
    $stmt->execute();
    responderPago(array('status' => 'success', 'orden_id' => $ordenId));
}

if ($accion === 'capturar') {
    $ordenId = isset($_POST['orden_id']) ? trim($_POST['orden_id']) : '';

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();
    try {
        // Se bloquea el pedido y los perfumes: así un doble clic, o dos clientes pagando lo último que queda,
        // no pueden cobrar ni descontar dos veces.
        $pedido = obtenerPedidoPago($conn, $pedidoId, true);
        if ($pedido['estado_pago'] === 'pagado') {
            $conn->rollback();
            responderPago(array('status' => 'pagado', 'message' => 'Este pedido ya está pagado.'));
        }
        // Se le pregunta a PayPal de qué pedido es la orden (la referencia que le pusimos al crearla) y por cuánto.
        // PayPal puede crear más de una orden para el mismo pedido (p. ej. si el cliente cambia de cuenta PayPal
        // a tarjeta), así que vale cualquiera de este pedido, pero nunca una de otro pedido o con otro monto.
        $orden = preg_match('/^[A-Z0-9]{10,30}$/', $ordenId) ? paypal_consultar_orden($ordenId) : null;
        if (!$orden || $orden['referencia'] !== paypal_referencia($pedidoId)
            || $orden['monto'] !== paypal_monto($pedido['total']) || $orden['moneda'] !== PAYPAL_MONEDA) {
            $conn->rollback();
            error_log("Pedido $pedidoId: la orden $ordenId no corresponde (" . json_encode($orden) . ')');
            responderPago(array('status' => 'error', 'message' => 'El pago no corresponde a este pedido.'));
        }

        $necesidades = necesidades_pedido($conn, $pedidoId);
        $faltante    = revisar_existencias($conn, $necesidades, true);
        if ($faltante) {
            $conn->rollback(); // No se cobra: la orden de PayPal queda aprobada pero sin cobrar y caduca sola.
            responderPago(array('status' => 'error', 'message' => $faltante));
        }

        [$resultado, $datos] = paypal_capturar_orden($ordenId);

        if ($resultado === 'rechazado') {
            $conn->rollback();
            responderPago(array('status' => 'rechazado', 'message' => 'PayPal rechazó esa forma de pago. Elige otra.'));
        }
        if ($resultado === 'pendiente') {
            $conn->rollback();
            error_log("Pago del pedido $pedidoId en revisión por PayPal (orden $ordenId)");
            responderPago(array('status' => 'error', 'message' => 'PayPal dejó tu pago en revisión. Te avisaremos cuando se confirme.'));
        }
        if ($resultado !== 'cobrado') {
            $conn->rollback();
            responderPago(array('status' => 'error', 'message' => 'No se pudo completar el pago. No se hizo ningún cobro; intenta de nuevo.'));
        }

        // Ya hay dinero cobrado: se confirma que sea exactamente el total del pedido y en la moneda correcta.
        if ($datos['moneda'] !== PAYPAL_MONEDA || $datos['monto'] !== paypal_monto($pedido['total'])) {
            $conn->rollback();
            error_log("Pedido $pedidoId: PayPal cobró {$datos['monto']} {$datos['moneda']} y el total es {$pedido['total']} (orden $ordenId)");
            responderPago(array('status' => 'error', 'message' => 'El monto cobrado no coincide con tu pedido. Contáctanos para revisarlo.'));
        }

        $stmt = $conn->prepare("UPDATE pedidos SET estado_pago = 'pagado', metodo_pago = 'paypal', paypal_order_id = ?, paypal_payer_email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $ordenId, $datos['correo'], $pedidoId);
        $stmt->execute();
        descontar_inventario_pedido($conn, $necesidades);
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log('Pago del pedido ' . $pedidoId . ': ' . $e->getMessage());
        responderPago(array('status' => 'error', 'message' => 'Ocurrió un error al registrar tu pago. Contáctanos con tu número de pedido.'));
    }
    mysqli_report(MYSQLI_REPORT_OFF);

    registrar_movimiento($conn, 'Pedidos', 'Pago en línea',
        "Pedido #$pedidoId pagado con PayPal por $" . number_format($pedido['total'], 2) . " (captura {$datos['captura_id']})", null);

    // Ya no hace falta recordarlo como "por pagar" en este navegador.
    if (isset($_SESSION['pedidos_por_pagar'])) {
        $_SESSION['pedidos_por_pagar'] = array_values(array_diff($_SESSION['pedidos_por_pagar'], array($pedidoId)));
    }

    // Primero se le confirma al cliente y después se envían los correos (tardan varios segundos cada uno).
    // Si un correo falla, el pago ya quedó registrado igual.
    responder_y_continuar(json_encode(array('status' => 'success', 'message' => '¡Pago recibido! Tu pedido #' . $pedidoId . ' ya está pagado.')));
    require_once __DIR__ . '/../../correos/correos.php';
    correo_pedido_pagado($conn, $pedidoId);
    correo_admin_nuevo_pedido($conn, $pedidoId);
    exit;
}

responderPago(array('status' => 'error', 'message' => 'Acción no válida.'));
