<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");

function responder($ok, $error = null) {
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

function leer_formulario($conn) {
    $d = [
        'nombre'      => trim($_POST['nombre'] ?? ''),
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'precio'      => (float)($_POST['precio'] ?? 0),
        'productos'   => [],
    ];
    if ($d['nombre'] === '') responder(false, 'El nombre del combo es obligatorio.');
    if (mb_strlen($d['nombre']) > 150) responder(false, 'El nombre es demasiado largo (máximo 150 caracteres).');
    if ($d['precio'] <= 0) responder(false, 'El precio del combo debe ser mayor a 0.');

    // Si la misma presentación viene en dos filas, se suman sus cantidades.
    foreach ($_POST['presentacion_id'] ?? [] as $i => $pid) {
        $pid  = (int)$pid;
        $cant = (int)($_POST['cantidad'][$i] ?? 0);
        if ($pid === 0 && $cant === 0) continue;
        if ($pid <= 0) responder(false, 'Selecciona el producto en todas las filas.');
        if ($cant <= 0) responder(false, 'Las cantidades deben ser de al menos 1.');
        $d['productos'][$pid] = ($d['productos'][$pid] ?? 0) + $cant;
    }
    if (array_sum($d['productos']) < 2) {
        responder(false, 'Un combo debe incluir al menos 2 piezas.');
    }

    $ids = implode(',', array_keys($d['productos']));
    $validos = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS n FROM presentaciones pr INNER JOIN perfumes pf ON pf.id = pr.perfume_id
         WHERE pr.id IN ($ids) AND pr.activo = 1 AND pf.activo = 1"))['n'];
    if ((int)$validos !== count($d['productos'])) {
        responder(false, 'Uno de los productos ya no está disponible. Vuelve a abrir el formulario.');
    }
    return $d;
}

function guardar_productos($conn, $combo_id, $productos) {
    $conn->query("DELETE FROM combo_presentaciones WHERE combo_id = $combo_id");
    $stmt = $conn->prepare("INSERT INTO combo_presentaciones (combo_id, presentacion_id, cantidad) VALUES (?,?,?)");
    foreach ($productos as $pid => $cant) {
        $stmt->bind_param("iii", $combo_id, $pid, $cant);
        $stmt->execute();
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_exception_handler(function ($e) {
    error_log($e);
    responder(false, 'Error en la base de datos: ' . $e->getMessage());
});

$accion = $_POST['accion'] ?? '';

if ($accion == "guardar") {
    $d = leer_formulario($conn);
    $subida = subir_imagen('imagen', 'uploads/combos');
    if (!$subida['ok']) responder(false, $subida['error']);

    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO combos (nombre, descripcion, precio, imagen) VALUES (?,?,?,?)");
        $stmt->bind_param("ssds", $d['nombre'], $d['descripcion'], $d['precio'], $subida['archivo']);
        $stmt->execute();
        guardar_productos($conn, $conn->insert_id, $d['productos']);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        if ($subida['archivo']) @unlink(__DIR__ . '/../' . $subida['archivo']);
        throw $e;
    }

    registrar_movimiento($conn, 'Combos', 'Agregar',
        "Agregó el combo '{$d['nombre']}' ($" . number_format($d['precio'], 2) . ", " . array_sum($d['productos']) . " piezas)");
    responder(true);
}

if ($accion == "actualizar") {
    $id = (int)$_POST['id'];
    $d  = leer_formulario($conn);

    $anterior = mysqli_fetch_assoc(mysqli_query($conn, "SELECT imagen, precio FROM combos WHERE id=$id AND activo=1"));
    if (!$anterior) responder(false, 'El combo no existe.');

    $subida = subir_imagen('imagen', 'uploads/combos');
    if (!$subida['ok']) responder(false, $subida['error']);
    $imagen = $subida['archivo'] ?? $anterior['imagen'];
    if (!empty($_POST['quitar_imagen']) && !$subida['archivo']) {
        $imagen = null;
    }

    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE combos SET nombre=?, descripcion=?, precio=?, imagen=? WHERE id=?");
        $stmt->bind_param("ssdsi", $d['nombre'], $d['descripcion'], $d['precio'], $imagen, $id);
        $stmt->execute();
        guardar_productos($conn, $id, $d['productos']);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        if ($subida['archivo']) @unlink(__DIR__ . '/../' . $subida['archivo']);
        throw $e;
    }

    $detalle = (float)$anterior['precio'] != $d['precio']
        ? " (precio $" . number_format($anterior['precio'], 2) . " → $" . number_format($d['precio'], 2) . ")" : '';
    registrar_movimiento($conn, 'Combos', 'Editar', "Editó el combo '{$d['nombre']}'$detalle");
    responder(true);
}

if ($accion == "eliminar") {
    $id = (int)$_POST['id'];
    $combo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nombre FROM combos WHERE id=$id AND activo=1"));
    if (!$combo) responder(false, 'El combo no existe.');
    mysqli_query($conn, "UPDATE combos SET activo=0 WHERE id=$id");
    registrar_movimiento($conn, 'Combos', 'Eliminar', "Eliminó el combo '{$combo['nombre']}'");
    responder(true);
}

responder(false, 'Acción no válida.');
