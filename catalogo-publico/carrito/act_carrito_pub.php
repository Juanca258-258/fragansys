<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$respuesta = array('status' => 'error', 'message' => 'Acción no válida');
$accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = array();
}

if ($accion === 'obtener_conteo') {
    $totalItems = 0;
    if (isset($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
        foreach ($_SESSION['carrito'] as $prod) {
            $totalItems += (int)$prod['cantidad'];
        }
    }
    echo json_encode(array(
        'status' => 'success',
        'total_items' => $totalItems
    ));
    exit;
}

if ($accion === 'actualizar') {
    $productoId = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;

    if (isset($_SESSION['carrito'][$productoId])) {
        if ($cantidad > $_SESSION['carrito'][$productoId]['stock']) {
            $respuesta['message'] = 'Stock máximo disponible: ' . $_SESSION['carrito'][$productoId]['stock'];
        } elseif ($cantidad <= 0) {
            unset($_SESSION['carrito'][$productoId]);
            $respuesta['status'] = 'success';
            $respuesta['message'] = 'Producto eliminado del carrito.';
        } else {
            $_SESSION['carrito'][$productoId]['cantidad'] = $cantidad;
            $_SESSION['carrito'][$productoId]['subtotal'] = $cantidad * $_SESSION['carrito'][$productoId]['precio'];
            $respuesta['status'] = 'success';
            $respuesta['message'] = 'Cantidad actualizada.';
        }
    }
} elseif ($accion === 'eliminar') {
    $productoId = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    if (isset($_SESSION['carrito'][$productoId])) {
        unset($_SESSION['carrito'][$productoId]);
        $respuesta['status'] = 'success';
        $respuesta['message'] = 'Producto eliminado.';
    }
} elseif ($accion === 'vaciar') {
    $_SESSION['carrito'] = array();
    $respuesta['status'] = 'success';
    $respuesta['message'] = 'Carrito vaciado.';
}

// Recalcular métricas
$subtotalTotal = 0;
$totalItems = 0;
foreach ($_SESSION['carrito'] as $prod) {
    $subtotalTotal += $prod['cantidad'] * $prod['precio'];
    $totalItems += $prod['cantidad'];
}

$respuesta['subtotal'] = number_format($subtotalTotal, 2);
$respuesta['total_items'] = $totalItems;

echo json_encode($respuesta);
exit;