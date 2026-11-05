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

    // Consultar la presentación (decant o frasco) junto con los datos de su perfume.
    // "producto_id" es el id de la presentación; "stock" son las unidades que alcanzan:
    // frascos sellados para un frasco, o ml disponibles entre los ml del decant.
    $sql = "SELECT pr.id AS producto_id, TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.ml)) AS contenido_ml, pr.precio,
                   IF(pr.tipo = 'frasco', pf.frascos_sellados, FLOOR(pf.ml_disponibles / pr.ml)) AS stock,
                   pf.id AS catalogo_id, pf.nombre AS perfume_nombre, pf.marca, pf.imagen AS catalogo_imagen
            FROM presentaciones pr
            INNER JOIN perfumes pf ON pr.perfume_id = pf.id
            WHERE pr.id = ? AND pr.activo = 1 AND pf.activo = 1";

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
            // Ruta relativa a la raíz del proyecto; cada vista le antepone su propio "../"
            $imagen = $item['catalogo_imagen'];

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
} elseif ($accion === 'agregar_combo') {
    require_once __DIR__ . '/../helpers.php';

    $comboId  = isset($_POST['combo_id']) ? (int)$_POST['combo_id'] : 0;
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;
    $combo    = ($comboId > 0 && $db) ? obtener_combo_venta($db, $comboId) : null;

    // En el carrito los combos usan la clave "c<id>" para no chocar con los ids de presentaciones
    $clave = 'c' . $comboId;

    if ($cantidad <= 0) {
        $respuesta['message'] = 'Cantidad inválida.';
    } elseif (!$combo) {
        $respuesta['message'] = 'El combo no está disponible.';
    } else {
        if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
            $_SESSION['carrito'] = array();
        }

        $nuevaCantidad = (isset($_SESSION['carrito'][$clave]) ? $_SESSION['carrito'][$clave]['cantidad'] : 0) + $cantidad;

        if ($nuevaCantidad > $combo['stock']) {
            $respuesta['message'] = $combo['stock'] > 0
                ? 'No puedes agregar más combos de los disponibles en stock (' . $combo['stock'] . ').'
                : 'El combo está agotado.';
        } else {
            $_SESSION['carrito'][$clave] = array(
                'tipo'         => 'combo',
                'producto_id'  => $clave,
                'combo_id'     => $comboId,
                'catalogo_id'  => null,
                'nombre'       => $combo['nombre'],
                'marca'        => 'Combo',
                'contenido_ml' => null,
                'piezas'       => $combo['piezas'],
                'precio'       => $combo['precio'],
                'cantidad'     => $nuevaCantidad,
                'stock'        => $combo['stock'],
                'subtotal'     => $nuevaCantidad * $combo['precio'],
                'imagen'       => $combo['imagen']
            );

            $totalItems = 0;
            foreach ($_SESSION['carrito'] as $prod) {
                $totalItems += $prod['cantidad'];
            }

            $respuesta['status'] = 'success';
            $respuesta['message'] = 'Combo agregado al carrito con éxito.';
            $respuesta['total_items'] = $totalItems;
        }
    }
}

echo json_encode($respuesta);
exit;