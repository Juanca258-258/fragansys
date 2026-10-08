<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");

$accion = $_POST['accion'];

if ($accion == "guardar") {
    $catalogo_id  = (int)$_POST['catalogo_id'];
    $nombre       = trim($_POST['nombre']);
    $descripcion  = trim($_POST['descripcion']);
    $tipo         = $_POST['tipo'];
    $contenido_ml = (float)$_POST['contenido_ml'];
    $precio       = (float)$_POST['precio'];
    $stock        = (int)$_POST['stock'];

    if ($catalogo_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Selecciona una fragancia válida del catálogo.']);
        exit;
    }

    $subida = subir_imagen('imagen', 'uploads/productos');
    if (!$subida['ok']) {
        echo json_encode(['ok' => false, 'error' => $subida['error']]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO productos
        (catalogo_id, nombre, descripcion, tipo, contenido_ml, precio, stock, imagen)
        VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("isssddis", $catalogo_id, $nombre, $descripcion, $tipo, $contenido_ml, $precio, $stock, $subida['archivo']);
    $stmt->execute();

    registrar_movimiento($conn, 'Productos', 'Agregar', "Agregó el producto '$nombre' (stock: $stock, precio: $" . number_format($precio, 2) . ")");

    echo json_encode(['ok' => true]);
    exit;
}

if ($accion == "actualizar") {
    $id           = (int)$_POST['id'];
    $catalogo_id  = (int)$_POST['catalogo_id'];
    $nombre       = trim($_POST['nombre']);
    $descripcion  = trim($_POST['descripcion']);
    $tipo         = $_POST['tipo'];
    $contenido_ml = (float)$_POST['contenido_ml'];
    $precio       = (float)$_POST['precio'];
    $stock        = (int)$_POST['stock'];
    $imagen_actual = $_POST['imagen_actual'];

    if ($catalogo_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Selecciona una fragancia válida del catálogo.']);
        exit;
    }

    $subida = subir_imagen('imagen', 'uploads/productos');
    if (!$subida['ok']) {
        echo json_encode(['ok' => false, 'error' => $subida['error']]);
        exit;
    }
    $imagen_final = $subida['archivo'] ?? $imagen_actual;

    $anterior = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stock, precio FROM productos WHERE id=$id"));

    $stmt = $conn->prepare("UPDATE productos SET
        catalogo_id=?, nombre=?, descripcion=?, tipo=?, contenido_ml=?, precio=?, stock=?, imagen=?
        WHERE id=?");
    $stmt->bind_param("isssddisi", $catalogo_id, $nombre, $descripcion, $tipo, $contenido_ml, $precio, $stock, $imagen_final, $id);
    $stmt->execute();

    $cambios = [];
    if ((int)$anterior['stock'] !== $stock) {
        $cambios[] = "stock {$anterior['stock']} → $stock";
    }
    if ((float)$anterior['precio'] !== $precio) {
        $cambios[] = "precio $" . number_format($anterior['precio'], 2) . " → $" . number_format($precio, 2);
    }
    $detalle = $cambios ? ' (' . implode(', ', $cambios) . ')' : '';
    registrar_movimiento($conn, 'Productos', 'Editar', "Editó el producto '$nombre'$detalle");

    echo json_encode(['ok' => true]);
    exit;
}

if ($accion == "eliminar") {
    $id = (int)$_POST['id'];
    $fila = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nombre FROM productos WHERE id=$id"));
    mysqli_query($conn, "UPDATE productos SET activo=0 WHERE id=$id");

    registrar_movimiento($conn, 'Productos', 'Eliminar', "Eliminó el producto '{$fila['nombre']}'");

    echo json_encode(['ok' => true]);
    exit;
}
