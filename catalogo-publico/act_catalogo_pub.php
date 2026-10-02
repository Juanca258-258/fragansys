<?php
// Reporte de errores en desarrollo
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Iniciar sesión para gestionar el carrito temporal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../conexion.php';

// Detectar conexión disponible
$db = null;
if (isset($conexion) && is_object($conexion)) {
    $db = $conexion;
} elseif (isset($conn) && is_object($conn)) {
    $db = $conn;
}

$respuesta = array(
    'status' => 'error',
    'message' => 'Acción no válida'
);

// Verificar la acción solicitada
$accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

if ($accion === 'agregar') {
    $productoId = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;

    if ($productoId <= 0 || $cantidad <= 0) {
        $respuesta['message'] = 'Datos de producto o cantidad inválidos.';
        echo json_encode($respuesta);
        exit;
    }

    if (!$db || !is_object($db) || $db->connect_error) {
        $respuesta['message'] = 'Error de conexión con la base de datos.';
        echo json_encode($respuesta);
        exit;
    }

    // Consultar el producto/variante junto con los datos de su catálogo de la BD
    $sql = "SELECT p.id AS producto_id, p.nombre AS producto_nombre, p.contenido_ml, p.precio, p.stock, p.imagen AS producto_imagen,
                   c.id AS catalogo_id, c.nombre AS perfume_nombre, c.marca, c.imagen AS catalogo_imagen
            FROM productos p
            INNER JOIN catalogo c ON p.catalogo_id = c.id
            WHERE p.id = ? AND p.activo = 1 AND c.activo = 1";

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $productoId);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $item = $res->fetch_assoc();

            // Validar stock disponible
            if ($item['stock'] < $cantidad) {
                $respuesta['message'] = 'Stock insuficiente. Disponibles: ' . $item['stock'];
                echo json_encode($respuesta);
                exit;
            }

            // Inicializar carrito en sesión si no existe
            if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
                $_SESSION['carrito'] = array();
            }

            // Determinar imagen del producto
            $imagen = !empty($item['producto_imagen']) 
                ? '../uploads/productos/' . $item['producto_imagen']
                : (!empty($item['catalogo_imagen']) ? '../uploads/catalogo/' . $item['catalogo_imagen'] : '../uploads/catalogo/default.png');

            // Si el producto ya está en el carrito, sumar la cantidad
            if (isset($_SESSION['carrito'][$productoId])) {
                $nuevaCantidad = $_SESSION['carrito'][$productoId]['cantidad'] + $cantidad;

                if ($nuevaCantidad > $item['stock']) {
                    $respuesta['message'] = 'No puedes agregar más unidades de las disponibles en stock (' . $item['stock'] . ').';
                    echo json_encode($respuesta);
                    exit;
                }

                $_SESSION['carrito'][$productoId]['cantidad'] = $nuevaCantidad;
                $_SESSION['carrito'][$productoId]['subtotal'] = $nuevaCantidad * $item['precio'];
            } else {
                // Si es nuevo, insertarlo
                $_SESSION['carrito'][$productoId] = array(
                    'producto_id'     => $item['producto_id'],
                    'catalogo_id'     => $item['catalogo_id'],
                    'nombre'          => $item['perfume_nombre'],
                    'marca'           => $item['marca'],
                    'contenido_ml'    => $item['contenido_ml'],
                    'precio'          => (float)$item['precio'],
                    'cantidad'        => $cantidad,
                    'stock'           => (int)$item['stock'],
                    'subtotal'        => $cantidad * (float)$item['precio'],
                    'imagen'          => $imagen
                );
            }

            // Contar total de ítems en carrito
            $totalItems = 0;
            foreach ($_SESSION['carrito'] as $prod) {
                $totalItems += $prod['cantidad'];
            }

            $respuesta['status'] = 'success';
            $respuesta['message'] = 'Producto agregado al carrito con éxito.';
            $respuesta['total_items'] = $totalItems;
        } else {
            $respuesta['message'] = 'El producto no está disponible.';
        }
        $stmt->close();
    } else {
        $respuesta['message'] = 'Error en la consulta del producto.';
    }
}

echo json_encode($respuesta);
exit;