<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../conexion.php';
require_once __DIR__ . '/../../helpers.php';

// Productos que incluye un combo, para mostrarlos desplegables en el carrito
function obtenerProductosCombo($db, $comboId) {
    if (!$db) {
        return array();
    }
    $stmt = $db->prepare(
        "SELECT cp.cantidad, pr.tipo, TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.ml)) AS ml,
                pf.nombre, pf.marca, pf.imagen
         FROM combo_presentaciones cp
         INNER JOIN presentaciones pr ON cp.presentacion_id = pr.id
         INNER JOIN perfumes pf ON pr.perfume_id = pf.id
         WHERE cp.combo_id = ?
         ORDER BY pf.nombre, pr.ml"
    );
    $stmt->bind_param("i", $comboId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

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
        $tipo = isset($item['tipo']) ? $item['tipo'] : 'presentacion';

        $resumen['items'][] = array(
            // Clave del carrito: id de la presentación, o "c<id>" para un combo
            'clave'        => (string)$productoId,
            'tipo'         => $tipo,
            'productos'    => $tipo === 'combo' ? obtenerProductosCombo($db, (int)$item['combo_id']) : array(),
            'piezas'       => isset($item['piezas']) ? (int)$item['piezas'] : 0,
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

// Envío según Configuración (misma regla que al registrar el pedido en act_carrito_pub.php)
$configTienda = $dbConn ? obtener_config($dbConn) : CONFIG_DEFECTO;
$datosCarrito['envio'] = calcular_envio($configTienda, $datosCarrito['subtotal']);
$datosCarrito['total'] = $datosCarrito['subtotal'] + $datosCarrito['envio'];
// Cuánto le falta al cliente para el envío gratis (0 si ya lo tiene o si nunca es gratis)
$gratisDesde = $configTienda['envio_gratis_desde'] !== '' ? (float)$configTienda['envio_gratis_desde'] : 0;
$datosCarrito['falta_envio_gratis'] = ($gratisDesde > 0 && $datosCarrito['envio'] > 0)
    ? $gratisDesde - $datosCarrito['subtotal'] : 0;

// Cliente con sesión iniciada: el checkout usa los datos de su cuenta sin pedir correo ni contraseña
function obtenerClienteSesion($db) {
    if (!$db || !isset($_SESSION['cliente_id'])) {
        return null;
    }
    $clienteId = (int)$_SESSION['cliente_id'];
    $stmt = $db->prepare("SELECT id, nombre, correo, telefono, direccion FROM clientes WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $clienteId);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    return $cliente ? $cliente : null;
}

$clienteSesion = obtenerClienteSesion($dbConn);