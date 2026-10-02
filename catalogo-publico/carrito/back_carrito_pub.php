<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../conexion.php';

function obtenerCarritoPublico($db) {
    $resumen = array(
        'items' => array(),
        'subtotal' => 0.00,
        'total_items' => 0
    );

    if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])) {
        return $resumen;
    }

    foreach ($_SESSION['carrito'] as $productoId => $item) {
        $cant = (int)$item['cantidad'];
        $precio = (float)$item['precio'];
        $subtotal = $cant * $precio;

        $resumen['items'][] = array(
            'producto_id'  => $item['producto_id'],
            'catalogo_id'  => $item['catalogo_id'],
            'nombre'       => $item['nombre'],
            'marca'        => $item['marca'],
            'contenido_ml' => $item['contenido_ml'],
            'precio'       => $precio,
            'cantidad'     => $cant,
            'stock'        => $item['stock'],
            'subtotal'     => $subtotal,
            'imagen'       => $item['imagen']
        );

        $resumen['subtotal'] += $subtotal;
        $resumen['total_items'] += $cant;
    }

    return $resumen;
}

// Detectar conexión de forma segura
$dbConn = null;
if (isset($conexion) && is_object($conexion)) {
    $dbConn = $conexion;
} elseif (isset($conn) && is_object($conn)) {
    $dbConn = $conn;
}

$datosCarrito = obtenerCarritoPublico($dbConn);