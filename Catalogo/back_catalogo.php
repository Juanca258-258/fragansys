<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");

$accion = $_POST['accion'];

if ($accion == "guardar") {
    $nombre = trim($_POST['nombre']);
    $marca  = trim($_POST['marca']);
    $genero = $_POST['genero'];

    $dup = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM catalogo WHERE nombre='" . mysqli_real_escape_string($conn, $nombre) . "'
         AND marca='" . mysqli_real_escape_string($conn, $marca) . "' AND activo=1 LIMIT 1"));
    if ($dup) {
        echo json_encode(['ok' => false, 'error' => "Ya existe '$nombre' de la marca '$marca' en el catálogo."]);
        exit;
    }

    $subida = subir_imagen('imagen', 'uploads/catalogo');
    if (!$subida['ok']) {
        echo json_encode(['ok' => false, 'error' => $subida['error']]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO catalogo (nombre, marca, genero, imagen) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $nombre, $marca, $genero, $subida['archivo']);
    $stmt->execute();

    echo json_encode(['ok' => true]);
    exit;
}

if ($accion == "actualizar") {
    $id            = (int)$_POST['id'];
    $nombre        = trim($_POST['nombre']);
    $marca         = trim($_POST['marca']);
    $genero        = $_POST['genero'];
    $imagen_actual = $_POST['imagen_actual'];

    $dup = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM catalogo WHERE nombre='" . mysqli_real_escape_string($conn, $nombre) . "'
         AND marca='" . mysqli_real_escape_string($conn, $marca) . "' AND activo=1 AND id!=$id LIMIT 1"));
    if ($dup) {
        echo json_encode(['ok' => false, 'error' => "Ya existe otra fragancia '$nombre' de la marca '$marca'."]);
        exit;
    }

    $subida = subir_imagen('imagen', 'uploads/catalogo');
    if (!$subida['ok']) {
        echo json_encode(['ok' => false, 'error' => $subida['error']]);
        exit;
    }
    $imagen_final = $subida['archivo'] ?? $imagen_actual;

    $stmt = $conn->prepare("UPDATE catalogo SET nombre=?, marca=?, genero=?, imagen=? WHERE id=?");
    $stmt->bind_param("ssssi", $nombre, $marca, $genero, $imagen_final, $id);
    $stmt->execute();

    echo json_encode(['ok' => true]);
    exit;
}

if ($accion == "eliminar") {
    $id = (int)$_POST['id'];
    mysqli_query($conn, "UPDATE catalogo SET activo=0 WHERE id=$id");
    echo json_encode(['ok' => true]);
    exit;
}
