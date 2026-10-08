<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");

function responder($ok, $error = null) {
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

function ml_texto($ml) {
    return (float)$ml . ' ml';
}

function leer_formulario() {
    $d = [
        'marca'            => trim($_POST['marca'] ?? ''),
        'nombre'           => trim($_POST['nombre'] ?? ''),
        'genero'           => $_POST['genero'] ?? 'unisex',
        'ml_por_frasco'    => (float)($_POST['ml_por_frasco'] ?? 0),
        'frascos_sellados' => (int)($_POST['frascos_sellados'] ?? 0),
        'ml_disponibles'   => (float)($_POST['ml_disponibles'] ?? 0),
        'precio_frasco'    => trim($_POST['precio_frasco'] ?? ''),
        'decants'          => [],
    ];

    if ($d['marca'] === '' || $d['nombre'] === '') responder(false, 'Marca y nombre son obligatorios.');
    if (!in_array($d['genero'], ['hombre', 'mujer', 'unisex'], true)) responder(false, 'Género no válido.');
    if ($d['ml_por_frasco'] <= 0) responder(false, 'Los ml por frasco deben ser mayores a 0.');
    if ($d['frascos_sellados'] < 0 || $d['ml_disponibles'] < 0) responder(false, 'El inventario no puede ser negativo.');

    if ($d['precio_frasco'] !== '') {
        $d['precio_frasco'] = (float)$d['precio_frasco'];
        if ($d['precio_frasco'] <= 0) responder(false, 'El precio del frasco completo debe ser mayor a 0.');
    } else {
        $d['precio_frasco'] = null;
    }

    $mls     = $_POST['decant_ml'] ?? [];
    $precios = $_POST['decant_precio'] ?? [];
    $vistos  = [];
    foreach ($mls as $i => $ml) {
        $ml     = trim($ml);
        $precio = trim($precios[$i] ?? '');
        if ($ml === '' && $precio === '') continue;
        if ($ml === '' || $precio === '') responder(false, 'Cada decant necesita ml y precio.');
        $ml = (float)$ml;
        $precio = (float)$precio;
        if ($ml <= 0 || $precio <= 0) responder(false, 'Los ml y el precio de los decants deben ser mayores a 0.');
        if ($ml >= $d['ml_por_frasco']) responder(false, 'Un decant no puede ser igual o mayor al frasco completo.');
        $clave = number_format($ml, 2);
        if (isset($vistos[$clave])) responder(false, 'Hay dos decants con los mismos ml (' . ml_texto($ml) . ').');
        $vistos[$clave] = true;
        $d['decants'][] = ['ml' => $ml, 'precio' => $precio];
    }

    if ($d['precio_frasco'] === null && empty($d['decants'])) {
        responder(false, 'Agrega al menos una presentación a la venta: frasco completo o un decant.');
    }

    return $d;
}

// Desactiva todas las presentaciones del perfume y reactiva/crea solo las enviadas.
// No se borran para no romper pedidos y ventas que ya las referencian.
function sincronizar_presentaciones($conn, $perfume_id, $d) {
    $conn->query("UPDATE presentaciones SET activo=0 WHERE perfume_id=$perfume_id");
    $stmt = $conn->prepare("INSERT INTO presentaciones (perfume_id, tipo, ml, precio, activo) VALUES (?,?,?,?,1)
                            ON DUPLICATE KEY UPDATE precio=VALUES(precio), activo=1");

    if ($d['precio_frasco'] !== null) {
        $tipo = 'frasco';
        $stmt->bind_param("isdd", $perfume_id, $tipo, $d['ml_por_frasco'], $d['precio_frasco']);
        $stmt->execute();
    }
    foreach ($d['decants'] as $dec) {
        $tipo = 'decant';
        $stmt->bind_param("isdd", $perfume_id, $tipo, $dec['ml'], $dec['precio']);
        $stmt->execute();
    }
}

$accion = $_POST['accion'] ?? '';

if ($accion == "guardar") {
    $d = leer_formulario();

    if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] === UPLOAD_ERR_NO_FILE) {
        responder(false, 'La foto del perfume es obligatoria.');
    }
    $subida = subir_imagen('imagen', 'uploads/perfumes');
    if (!$subida['ok']) responder(false, $subida['error']);

    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO perfumes (marca, nombre, genero, imagen, ml_por_frasco, frascos_sellados, ml_disponibles)
                            VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssdid", $d['marca'], $d['nombre'], $d['genero'], $subida['archivo'],
                      $d['ml_por_frasco'], $d['frascos_sellados'], $d['ml_disponibles']);
    $stmt->execute();
    sincronizar_presentaciones($conn, $conn->insert_id, $d);
    $conn->commit();

    registrar_movimiento($conn, 'Perfumes', 'Agregar',
        "Agregó '{$d['nombre']}' de {$d['marca']} ({$d['frascos_sellados']} frascos, " . ml_texto($d['ml_disponibles']) . " para decants)");
    responder(true);
}

if ($accion == "actualizar") {
    $id = (int)$_POST['id'];
    $d  = leer_formulario();

    $anterior = mysqli_fetch_assoc(mysqli_query($conn, "SELECT imagen, frascos_sellados, ml_disponibles FROM perfumes WHERE id=$id"));
    if (!$anterior) responder(false, 'El perfume no existe.');

    $subida = subir_imagen('imagen', 'uploads/perfumes');
    if (!$subida['ok']) responder(false, $subida['error']);
    $imagen_final = $subida['archivo'] ?? $anterior['imagen'];

    $conn->begin_transaction();
    $stmt = $conn->prepare("UPDATE perfumes SET marca=?, nombre=?, genero=?, imagen=?, ml_por_frasco=?, frascos_sellados=?, ml_disponibles=?
                            WHERE id=?");
    $stmt->bind_param("ssssdidi", $d['marca'], $d['nombre'], $d['genero'], $imagen_final,
                      $d['ml_por_frasco'], $d['frascos_sellados'], $d['ml_disponibles'], $id);
    $stmt->execute();
    sincronizar_presentaciones($conn, $id, $d);
    $conn->commit();

    $cambios = [];
    if ((int)$anterior['frascos_sellados'] !== $d['frascos_sellados']) {
        $cambios[] = "frascos {$anterior['frascos_sellados']} → {$d['frascos_sellados']}";
    }
    if ((float)$anterior['ml_disponibles'] !== $d['ml_disponibles']) {
        $cambios[] = "decants " . ml_texto($anterior['ml_disponibles']) . " → " . ml_texto($d['ml_disponibles']);
    }
    $detalle = $cambios ? ' (' . implode(', ', $cambios) . ')' : '';
    registrar_movimiento($conn, 'Perfumes', 'Editar', "Editó '{$d['nombre']}' de {$d['marca']}$detalle");
    responder(true);
}

if ($accion == "abrir_frasco") {
    $id = (int)$_POST['id'];
    mysqli_query($conn, "UPDATE perfumes
                         SET frascos_sellados = frascos_sellados - 1, ml_disponibles = ml_disponibles + ml_por_frasco
                         WHERE id=$id AND frascos_sellados > 0");
    if (mysqli_affected_rows($conn) === 0) responder(false, 'No hay frascos sellados para abrir.');

    $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nombre, marca, ml_por_frasco, ml_disponibles FROM perfumes WHERE id=$id"));
    registrar_movimiento($conn, 'Perfumes', 'Abrir frasco',
        "Abrió un frasco de '{$p['nombre']}' de {$p['marca']} (+" . ml_texto($p['ml_por_frasco']) . ", ahora " . ml_texto($p['ml_disponibles']) . " para decants)");
    responder(true);
}

if ($accion == "eliminar") {
    $id = (int)$_POST['id'];
    $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nombre, marca FROM perfumes WHERE id=$id"));
    mysqli_query($conn, "UPDATE perfumes SET activo=0 WHERE id=$id");
    registrar_movimiento($conn, 'Perfumes', 'Eliminar', "Eliminó '{$p['nombre']}' de {$p['marca']}");
    responder(true);
}

responder(false, 'Acción no válida.');
