<?php
/**
 * API de pago con PayPal para la app Android. Misma lógica que catalogo-publico/pago/act_pago.php.
 * Requiere la sesión del cliente (token): solo el dueño del pedido lo puede ver y pagar.
 *
 *   accion=detalle      pedido_id            -> pedido, productos y si todavía se puede pagar
 *   accion=crear_orden  pedido_id            -> crea la orden en PayPal y devuelve la liga para aprobarla
 *                       (con modo=sdk solo devuelve el id; lo usa el botón de tarjeta de tarjeta.php)
 *   accion=capturar     pedido_id, orden_id  -> cobra la orden aprobada y marca el pedido como pagado
 *
 * En la app el cliente aprueba el pago dentro de un WebView con la página de PayPal. Al terminar, PayPal
 * redirige a retorno.php (?resultado=aprobado|cancelado); la app detecta esa dirección y llama a "capturar".
 */
require_once __DIR__ . '/../../conexion_api.php';
require_once __DIR__ . '/../../../catalogo-publico/pago/back_pago.php';

// Dirección de retorno que PayPal abre al terminar (la app la intercepta en el WebView)
function urlRetornoPago($resultado) {
    $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $carpeta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $carpeta . '/retorno.php?resultado=' . $resultado;
}

// Igual que paypal_crear_orden() pero con las direcciones de retorno para el flujo con redirección de la app.
// Devuelve [id de la orden, liga para aprobar] o null.
function crearOrdenPaypalApp($pedido, $negocio) {
    list($codigo, $r) = paypal_api('POST', '/v2/checkout/orders', array(
        'intent' => 'CAPTURE',
        'purchase_units' => array(array(
            'reference_id' => paypal_referencia($pedido['id']),
            'custom_id'    => paypal_referencia($pedido['id']),
            'description'  => 'Pedido #' . $pedido['id'] . ' - ' . $negocio,
            'amount'       => array('currency_code' => PAYPAL_MONEDA, 'value' => paypal_monto($pedido['total'])),
        )),
        'application_context' => array(
            'brand_name'          => mb_substr($negocio, 0, 127),
            'locale'              => 'es-MX',
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'         => 'PAY_NOW',
            'return_url'          => urlRetornoPago('aprobado'),
            'cancel_url'          => urlRetornoPago('cancelado'),
        ),
    ));
    if (($codigo !== 200 && $codigo !== 201) || empty($r['id'])) {
        error_log('PayPal (app) no creó la orden del pedido ' . $pedido['id'] . ' (HTTP ' . $codigo . '): ' . json_encode($r));
        return null;
    }
    foreach (isset($r['links']) ? $r['links'] : array() as $liga) {
        if ($liga['rel'] === 'approve' || $liga['rel'] === 'payer-action') {
            return array($r['id'], $liga['href']);
        }
    }
    return null;
}

$cliente  = api_requiere_cliente($conn);
$accion   = api_param('accion');
$pedidoId = (int)api_param('pedido_id', '0');
$pedido   = $pedidoId ? obtenerPedidoPago($conn, $pedidoId) : null;

// Siempre se compara con el cliente de la sesión para que nadie vea ni pague pedidos ajenos
if (!$pedido || (int)$pedido['cliente_id'] !== (int)$cliente['id']) {
    api_error('Pedido no encontrado.');
}

if ($accion === 'detalle') {
    $productos = array();
    foreach (obtenerDetallePago($conn, $pedidoId) as $d) {
        $productos[] = array('nombre' => $d['nombre'], 'cantidad' => (int)$d['cantidad'], 'subtotal' => (float)$d['subtotal']);
    }
    api_responder(array(
        'status' => 'success',
        'pedido' => array(
            'id'                => (int)$pedido['id'],
            'subtotal'          => (float)$pedido['subtotal'],
            'costo_envio'       => (float)$pedido['costo_envio'],
            'total'             => (float)$pedido['total'],
            'moneda'            => PAYPAL_MONEDA,
            'direccion_entrega' => $pedido['direccion_entrega'],
            'estado_pago'       => $pedido['estado_pago'],
            'estado_pedido'     => $pedido['estado_pedido'],
            'pagable'           => pedidoPagable($pedido),
        ),
        'productos' => $productos,
    ));
}

if ($pedido['estado_pago'] === 'pagado') {
    api_responder(array('status' => 'pagado', 'message' => 'Este pedido ya está pagado.'));
}
if (!pedidoPagable($pedido)) {
    api_error('Este pedido ya no se puede pagar.');
}

if ($accion === 'crear_orden') {
    // Se revisa antes de abrir PayPal para no hacer pasar al cliente por el pago si algo se agotó.
    $faltante = revisar_existencias($conn, necesidades_pedido($conn, $pedidoId));
    if ($faltante) {
        api_error($faltante);
    }

    $config = obtener_config($conn);

    // modo=sdk: la orden la pide el botón de tarjeta de tarjeta.php (SDK de PayPal, igual que la web),
    // que no necesita direcciones de retorno.
    if (api_param('modo') === 'sdk') {
        $ordenId = paypal_crear_orden($pedido, $config['negocio_nombre']);
        if (!$ordenId) {
            api_error('No pudimos conectar con PayPal. Intenta de nuevo en un momento.');
        }
        $stmt = $conn->prepare("UPDATE pedidos SET paypal_order_id = ? WHERE id = ? AND estado_pago IN ('pendiente', 'fallido')");
        $stmt->bind_param("si", $ordenId, $pedidoId);
        $stmt->execute();
        api_responder(array('status' => 'success', 'orden_id' => $ordenId));
    }

    $orden  = crearOrdenPaypalApp($pedido, $config['negocio_nombre']);
    if (!$orden) {
        api_error('No pudimos conectar con PayPal. Intenta de nuevo en un momento.');
    }
    list($ordenId, $urlAprobar) = $orden;

    // Se guarda la orden para que al cobrar solo se acepte la que se creó para este pedido.
    $stmt = $conn->prepare("UPDATE pedidos SET paypal_order_id = ? WHERE id = ? AND estado_pago IN ('pendiente', 'fallido')");
    $stmt->bind_param("si", $ordenId, $pedidoId);
    $stmt->execute();

    api_responder(array(
        'status'        => 'success',
        'orden_id'      => $ordenId,
        'url_aprobar'   => $urlAprobar,
        'url_aprobado'  => urlRetornoPago('aprobado'),
        'url_cancelado' => urlRetornoPago('cancelado'),
    ));
}

if ($accion === 'capturar') {
    $ordenId = trim(api_param('orden_id'));

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();
    try {
        // Se bloquea el pedido y los perfumes: un doble toque, o dos clientes pagando lo último que queda,
        // no pueden cobrar ni descontar dos veces.
        $pedido = obtenerPedidoPago($conn, $pedidoId, true);
        if ($pedido['estado_pago'] === 'pagado') {
            $conn->rollback();
            api_responder(array('status' => 'pagado', 'message' => 'Este pedido ya está pagado.'));
        }
        // La orden debe ser de este pedido y por este monto
        $orden = preg_match('/^[A-Z0-9]{10,30}$/', $ordenId) ? paypal_consultar_orden($ordenId) : null;
        if (!$orden || $orden['referencia'] !== paypal_referencia($pedidoId)
            || $orden['monto'] !== paypal_monto($pedido['total']) || $orden['moneda'] !== PAYPAL_MONEDA) {
            $conn->rollback();
            error_log("Pedido $pedidoId (app): la orden $ordenId no corresponde (" . json_encode($orden) . ')');
            api_error('El pago no corresponde a este pedido.');
        }

        $necesidades = necesidades_pedido($conn, $pedidoId);
        $faltante    = revisar_existencias($conn, $necesidades, true);
        if ($faltante) {
            $conn->rollback(); // No se cobra: la orden de PayPal queda aprobada pero sin cobrar y caduca sola.
            api_error($faltante);
        }

        list($resultado, $datos) = paypal_capturar_orden($ordenId);

        if ($resultado === 'rechazado') {
            $conn->rollback();
            api_responder(array('status' => 'rechazado', 'message' => 'PayPal rechazó esa forma de pago. Intenta con otra.'));
        }
        if ($resultado === 'pendiente') {
            $conn->rollback();
            error_log("Pago del pedido $pedidoId en revisión por PayPal (orden $ordenId)");
            api_error('PayPal dejó tu pago en revisión. Te avisaremos cuando se confirme.');
        }
        if ($resultado !== 'cobrado') {
            $conn->rollback();
            api_error('No se pudo completar el pago. No se hizo ningún cobro; intenta de nuevo.');
        }
        if ($datos['moneda'] !== PAYPAL_MONEDA || $datos['monto'] !== paypal_monto($pedido['total'])) {
            $conn->rollback();
            error_log("Pedido $pedidoId: PayPal cobró {$datos['monto']} {$datos['moneda']} y el total es {$pedido['total']} (orden $ordenId)");
            api_error('El monto cobrado no coincide con tu pedido. Contáctanos para revisarlo.');
        }

        $stmt = $conn->prepare("UPDATE pedidos SET estado_pago = 'pagado', metodo_pago = 'paypal', paypal_order_id = ?, paypal_payer_email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $ordenId, $datos['correo'], $pedidoId);
        $stmt->execute();
        descontar_inventario_pedido($conn, $necesidades);
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log('Pago del pedido ' . $pedidoId . ' (app): ' . $e->getMessage());
        api_error('Ocurrió un error al registrar tu pago. Contáctanos con tu número de pedido.');
    }
    mysqli_report(MYSQLI_REPORT_OFF);

    registrar_movimiento($conn, 'Pedidos', 'Pago en línea',
        "Pedido #$pedidoId pagado con PayPal desde la app por $" . number_format($pedido['total'], 2) . " (captura {$datos['captura_id']})", null);

    // Primero se le confirma al cliente y después se envían los correos (tardan varios segundos cada uno).
    responder_y_continuar(json_encode(array('status' => 'success', 'message' => '¡Pago recibido! Tu pedido #' . $pedidoId . ' ya está pagado.'), JSON_UNESCAPED_UNICODE));
    require_once __DIR__ . '/../../../correos/correos.php';
    correo_pedido_pagado($conn, $pedidoId);
    correo_admin_nuevo_pedido($conn, $pedidoId);
    exit;
}

api_error('Acción no válida.');
