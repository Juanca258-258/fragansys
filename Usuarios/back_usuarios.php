<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");

function responder($ok, $error = null) {
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if ($_SESSION['rol'] !== 'admin') {
    responder(false, 'No tienes permiso para administrar usuarios.');
}

function leer_datos() {
    $d = [
        'nombre'    => trim($_POST['nombre'] ?? ''),
        'apellidos' => trim($_POST['apellidos'] ?? ''),
        'usuario'   => trim($_POST['usuario'] ?? ''),
        'rol'       => $_POST['rol'] ?? '',
        'password'  => $_POST['password'] ?? '',
        'confirmar' => $_POST['password_confirm'] ?? '',
    ];
    if ($d['nombre'] === '' || $d['apellidos'] === '' || $d['usuario'] === '') {
        responder(false, 'Nombre, apellidos y usuario son obligatorios.');
    }
    if (preg_match('/\s/', $d['usuario'])) {
        responder(false, 'El usuario no puede llevar espacios.');
    }
    if (!in_array($d['rol'], ['admin', 'vendedor'], true)) {
        responder(false, 'Rol no válido.');
    }
    if ($d['password'] !== $d['confirmar']) {
        responder(false, 'Las contraseñas no coinciden.');
    }
    return $d;
}

function buscar_por_usuario($conn, $usuario, $excluir_id = 0) {
    $stmt = $conn->prepare("SELECT id, activo FROM usuarios WHERE usuario = ? AND id <> ?");
    $stmt->bind_param("si", $usuario, $excluir_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$accion = $_POST['accion'] ?? '';

if ($accion == "guardar") {
    $d = leer_datos();
    if ($d['password'] === '') {
        responder(false, 'La contraseña es obligatoria.');
    }
    $hash = password_hash($d['password'], PASSWORD_DEFAULT);

    $existe = buscar_por_usuario($conn, $d['usuario']);
    if ($existe && $existe['activo']) {
        responder(false, "El usuario '{$d['usuario']}' ya existe.");
    }

    if ($existe) {
        // El usuario fue eliminado antes: se reactiva con los datos nuevos.
        $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellidos=?, rol=?, password=?, activo=1 WHERE id=?");
        $stmt->bind_param("ssssi", $d['nombre'], $d['apellidos'], $d['rol'], $hash, $existe['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO usuarios (usuario, password, nombre, apellidos, rol) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $d['usuario'], $hash, $d['nombre'], $d['apellidos'], $d['rol']);
    }
    $stmt->execute();

    registrar_movimiento($conn, 'Usuarios', 'Agregar', "Agregó al usuario '{$d['usuario']}' ({$d['nombre']} {$d['apellidos']}, {$d['rol']})");
    responder(true);
}

if ($accion == "actualizar") {
    $id = (int)$_POST['id'];
    $d  = leer_datos();

    $anterior = mysqli_fetch_assoc(mysqli_query($conn, "SELECT usuario, rol FROM usuarios WHERE id=$id AND activo=1"));
    if (!$anterior) {
        responder(false, 'El usuario no existe.');
    }
    if ($id === (int)$_SESSION['id_usuario'] && $d['rol'] !== $anterior['rol']) {
        responder(false, 'No puedes cambiar tu propio rol.');
    }

    $existe = buscar_por_usuario($conn, $d['usuario'], $id);
    if ($existe) {
        responder(false, $existe['activo']
            ? "El usuario '{$d['usuario']}' ya está en uso."
            : "El usuario '{$d['usuario']}' pertenece a un usuario eliminado. Elige otro.");
    }

    if ($d['password'] !== '') {
        $hash = password_hash($d['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET usuario=?, nombre=?, apellidos=?, rol=?, password=? WHERE id=?");
        $stmt->bind_param("sssssi", $d['usuario'], $d['nombre'], $d['apellidos'], $d['rol'], $hash, $id);
    } else {
        $stmt = $conn->prepare("UPDATE usuarios SET usuario=?, nombre=?, apellidos=?, rol=? WHERE id=?");
        $stmt->bind_param("ssssi", $d['usuario'], $d['nombre'], $d['apellidos'], $d['rol'], $id);
    }
    $stmt->execute();

    if ($id === (int)$_SESSION['id_usuario']) {
        $_SESSION['usuario']         = $d['usuario'];
        $_SESSION['nombre_completo'] = $d['nombre'] . ' ' . $d['apellidos'];
    }

    $detalle = $d['password'] !== '' ? ' (cambió la contraseña)' : '';
    registrar_movimiento($conn, 'Usuarios', 'Editar', "Editó al usuario '{$d['usuario']}'$detalle");
    responder(true);
}

if ($accion == "eliminar") {
    $id = (int)$_POST['id'];
    if ($id === 1) {
        responder(false, 'El administrador principal del sistema no puede eliminarse.');
    }
    if ($id === (int)$_SESSION['id_usuario']) {
        responder(false, 'No puedes eliminar tu propio usuario.');
    }

    $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT usuario FROM usuarios WHERE id=$id AND activo=1"));
    if (!$u) {
        responder(false, 'El usuario no existe.');
    }
    mysqli_query($conn, "UPDATE usuarios SET activo=0 WHERE id=$id");

    registrar_movimiento($conn, 'Usuarios', 'Eliminar', "Eliminó al usuario '{$u['usuario']}'");
    responder(true);
}

responder(false, 'Acción no válida.');
