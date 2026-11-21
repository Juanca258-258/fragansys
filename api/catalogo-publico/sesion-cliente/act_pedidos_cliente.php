<?php
/**
 * API de "Mis pedidos" para la app Android. Usa las funciones de back_pedidos_cliente.php.
 *   accion=lista     token             -> pedidos del cliente con su seguimiento
 *   accion=detalle   token, pedido_id  -> datos de entrega y productos del pedido
 *   accion=cancelar  token, pedido_id  -> cancela el pedido si todavía se puede
 */
require_once __DIR__ . '/../../conexion_api.php';
require_once __DIR__ . '/../../../catalogo-publico/sesion-cliente/back_pedidos_cliente.php';

// Seguimiento del pedido en datos (la web arma el HTML en htmlSeguimientoPedido; la app dibuja lo mismo)
function seguimientoPedido($pedido) {
    global $PASOS_PEDIDO, $ETIQUETAS_PAGO;

    $pago = isset($ETIQUETAS_PAGO[$pedido['estado_pago']])
        ? $ETIQUETAS_PAGO[$pedido['estado_pago']] : array($pedido['estado_pago'], 'secondary');
    $claves = array_keys($PASOS_PEDIDO);
    $actual = array_search($pedido['estado_pedido'], $claves, true);

    $pasos = array();
    foreach ($claves as $i => $clave) {
        $pasos[] = array(
            'clave'    => $clave,
            'etiqueta' => $PASOS_PEDIDO[$clave]['etiqueta'],
            'estado'   => $actual === false ? '' : ($i < $actual ? 'completado' : ($i === $actual ? 'actual' : '')),
        );
    }

    return array(
        'id'                  => (int)$pedido['id'],
        'estado_pedido'       => $pedido['estado_pedido'],
        'estado_pago'         => $pedido['estado_pago'],
        'etiqueta_pago'       => $pago[0],
        'color_pago'          => $pago[1], // success, warning, danger, secondary
        'pasos'               => $pasos,
        'cancelable'          => pedidoCancelable($pedido['estado_pedido']),
        'por_pagar'           => $pedido['estado_pedido'] !== 'cancelado'
                                 && in_array($pedido['estado_pago'], array('pendiente', 'fallido'), true),
        'fecha_pedido'        => formatoFecha($pedido['fecha_pedido']),
        'fecha_actualizacion' => formatoFecha($pedido['fecha_actualizacion']),
        'total'               => (float)$pedido['total'],
    ) + datosEnvioPedido($pedido);
}

$cliente   = api_requiere_cliente($conn);
$clienteId = (int)$cliente['id'];
$accion    = api_param('accion');

if ($accion === 'lista') {
    $lista = array();
    foreach (obtenerPedidosCliente($conn, $clienteId) as $pedido) {
        $lista[] = seguimientoPedido($pedido) + array('total_items' => (int)$pedido['total_items']);
    }
    api_responder(array('status' => 'success', 'pedidos' => $lista));
}

if ($accion === 'detalle') {
    $pedidoId = (int)api_param('pedido_id', '0');
    $pedido   = obtenerPedidoCliente($conn, $clienteId, $pedidoId);
    if (!$pedido) {
        api_error('Pedido no encontrado.');
    }

    $items = array();
    foreach (obtenerDetallePedido($conn, $pedidoId) as $d) {
        $items[] = array(
            'nombre'          => $d['nombre_item'],
            'marca'           => $d['marca'],
            'imagen'          => (string)$d['imagen'],
            'cantidad'        => (int)$d['cantidad'],
            'precio_unitario' => (float)$d['precio_unitario'],
            'subtotal'        => (float)$d['subtotal'],
        );
    }

    api_responder(array(
        'status' => 'success',
        'pedido' => seguimientoPedido($pedido) + array(
            'direccion_entrega' => $pedido['direccion_entrega'],
            'telefono_contacto' => $pedido['telefono_contacto'],
            'notas'             => (string)$pedido['notas'],
            'metodo_pago'       => $pedido['metodo_pago'],
            'subtotal'          => (float)$pedido['subtotal'],
            'costo_envio'       => (float)$pedido['costo_envio'],
        ),
        'items' => $items,
    ));
}

if ($accion === 'cancelar') {
    $pedidoId  = (int)api_param('pedido_id', '0');
    $resultado = cancelarPedidoCliente($conn, $clienteId, $pedidoId);
    api_responder($resultado);
}

api_error('Acción no válida');
