<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/back_pedidos_cliente.php';

$respuesta = array('status' => 'error', 'message' => 'Acción no válida');
$accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

if (!clienteSesionActiva()) {
    echo json_encode(array('status' => 'error', 'message' => 'Tu sesión expiró. Inicia sesión de nuevo.', 'sin_sesion' => true));
    exit;
}

$clienteId = (int)$_SESSION['cliente_id'];

if ($accion === 'estados') {
    // Consulta periódica: devuelve el seguimiento de cada pedido para marcar los cambios de estado
    $lista = array();
    foreach (obtenerPedidosCliente($conn, $clienteId) as $pedido) {
        $lista[] = array(
            'id'    => (int)$pedido['id'],
            'firma' => firmaPedido($pedido),
            'html'  => htmlSeguimientoPedido($pedido)
        );
    }
    $respuesta = array('status' => 'success', 'pedidos' => $lista);
} elseif ($accion === 'detalle') {
    $pedidoId = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
    $pedido = obtenerPedidoCliente($conn, $clienteId, $pedidoId);

    if (!$pedido) {
        $respuesta['message'] = 'Pedido no encontrado.';
    } else {
        $items = array();
        foreach (obtenerDetallePedido($conn, $pedidoId) as $d) {
            $items[] = array(
                'nombre'          => $d['nombre_item'],
                'marca'           => $d['marca'],
                'imagen'          => $d['imagen'],
                'cantidad'        => (int)$d['cantidad'],
                'precio_unitario' => (float)$d['precio_unitario'],
                'subtotal'        => (float)$d['subtotal']
            );
        }

        $respuesta = array(
            'status' => 'success',
            'pedido' => array(
                'id'                => (int)$pedido['id'],
                'fecha_pedido'      => formatoFecha($pedido['fecha_pedido']),
                'direccion_entrega' => $pedido['direccion_entrega'],
                'telefono_contacto' => $pedido['telefono_contacto'],
                'notas'             => (string)$pedido['notas'],
                'metodo_pago'       => $pedido['metodo_pago'],
                'subtotal'          => (float)$pedido['subtotal'],
                'costo_envio'       => (float)$pedido['costo_envio'],
                'total'             => (float)$pedido['total'],
                'seguimiento_html'  => htmlSeguimientoPedido($pedido)
            ),
            'items' => $items
        );
    }
} elseif ($accion === 'cancelar') {
    $pedidoId = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
    $respuesta = cancelarPedidoCliente($conn, $clienteId, $pedidoId);
}

echo json_encode($respuesta);
