<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");

function responder($ok, $error = null) {
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if ($_SESSION['rol'] !== 'admin') {
    responder(false, 'No tienes permiso para registrar inventario.');
}

$accion = $_POST['accion'] ?? '';

if ($accion == "guardar") {
    $perfume_id = (int)($_POST['perfume_id'] ?? 0);
    $fecha      = $_POST['fecha_entrada'] ?? '';
    $frascos    = (int)($_POST['frascos'] ?? 0);
    $costo      = (float)($_POST['costo_unitario'] ?? -1);
    $proveedor  = trim($_POST['proveedor'] ?? '');
    $notas      = trim($_POST['notas'] ?? '');

    $f = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$f || $f->format('Y-m-d') !== $fecha) responder(false, 'La fecha no es válida.');
    if ($fecha > date('Y-m-d')) responder(false, 'La fecha de llegada no puede ser futura.');
    if ($frascos <= 0) responder(false, 'Los frascos que llegaron deben ser al menos 1.');
    if ($costo < 0) responder(false, 'El costo por frasco no puede ser negativo.');

    $perfume = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT nombre, marca, frascos_sellados FROM perfumes WHERE id=$perfume_id AND activo=1"));
    if (!$perfume) responder(false, 'Selecciona un perfume válido.');

    $usuario_id = (int)$_SESSION['id_usuario'];
    $proveedor  = $proveedor !== '' ? $proveedor : null;
    $notas      = $notas !== '' ? $notas : null;

    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO entradas_inventario
        (perfume_id, usuario_id, fecha_entrada, frascos, costo_unitario, proveedor, notas)
        VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("iisidss", $perfume_id, $usuario_id, $fecha, $frascos, $costo, $proveedor, $notas);
    $ok = $stmt->execute();
    $ok = $ok && mysqli_query($conn, "UPDATE perfumes SET frascos_sellados = frascos_sellados + $frascos WHERE id=$perfume_id");
    if (!$ok) {
        $conn->rollback();
        responder(false, 'No se pudo registrar la entrada.');
    }
    $conn->commit();

    $despues = $perfume['frascos_sellados'] + $frascos;
    registrar_movimiento($conn, 'Inventario', 'Entrada',
        "Entrada de $frascos frasco(s) de '{$perfume['nombre']}' de {$perfume['marca']} a $" . number_format($costo, 2)
        . " c/u ({$perfume['frascos_sellados']} → $despues)");
    responder(true);
}

if ($accion == "anular") {
    $id = (int)($_POST['id'] ?? 0);

    $conn->begin_transaction();
    $entrada = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT perfume_id, frascos, estado FROM entradas_inventario WHERE id=$id FOR UPDATE"));
    if (!$entrada || $entrada['estado'] !== 'registrada') {
        $conn->rollback();
        responder(false, 'La entrada no existe o ya estaba anulada.');
    }

    $perfume = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT nombre, marca, frascos_sellados FROM perfumes WHERE id={$entrada['perfume_id']} FOR UPDATE"));
    if ($perfume['frascos_sellados'] < $entrada['frascos']) {
        $conn->rollback();
        responder(false, "No se puede anular: solo quedan {$perfume['frascos_sellados']} frasco(s) sellados de '{$perfume['nombre']}' "
            . "y la entrada sumó {$entrada['frascos']}. Ya se vendieron o se abrieron.");
    }

    mysqli_query($conn, "UPDATE perfumes SET frascos_sellados = frascos_sellados - {$entrada['frascos']} WHERE id={$entrada['perfume_id']}");
    mysqli_query($conn, "UPDATE entradas_inventario SET estado='anulada' WHERE id=$id");
    $conn->commit();

    $despues = $perfume['frascos_sellados'] - $entrada['frascos'];
    registrar_movimiento($conn, 'Inventario', 'Anular',
        "Anuló la entrada #$id de {$entrada['frascos']} frasco(s) de '{$perfume['nombre']}' ({$perfume['frascos_sellados']} → $despues)");
    responder(true);
}

if ($accion == "ajustar_ml") {
    $perfume_id = (int)($_POST['perfume_id'] ?? 0);
    $ml_texto   = trim($_POST['ml_nuevo'] ?? '');
    $ml_nuevo   = round((float)$ml_texto, 2);
    $motivo     = $_POST['motivo'] ?? '';
    $notas      = trim($_POST['notas'] ?? '');

    $motivos = [
        'inventario_inicial' => 'Inventario inicial',
        'derrame'            => 'Derrame',
        'evaporacion'        => 'Evaporación',
        'conteo_fisico'      => 'Conteo físico',
        'otro'               => 'Otro',
    ];
    if ($ml_texto === '' || $ml_nuevo < 0) responder(false, 'Escribe los ml reales (0 o más).');
    if (!isset($motivos[$motivo])) responder(false, 'Selecciona el motivo del ajuste.');
    if ($motivo === 'otro' && $notas === '') responder(false, 'Si el motivo es "Otro", explica en las notas qué pasó.');
    // La descripción del Historial admite 255 caracteres; las notas van dentro de ella.
    if (strlen($notas) > 120) responder(false, 'Las notas deben ser de máximo 120 caracteres.');

    $conn->begin_transaction();
    $perfume = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT nombre, marca, ml_disponibles FROM perfumes WHERE id=$perfume_id AND activo=1 FOR UPDATE"));
    if (!$perfume) {
        $conn->rollback();
        responder(false, 'Selecciona un perfume válido.');
    }
    $ml_antes = round((float)$perfume['ml_disponibles'], 2);
    if ($ml_antes == $ml_nuevo) {
        $conn->rollback();
        responder(false, 'Los ml reales son iguales a los registrados; no hay nada que ajustar.');
    }

    $stmt = $conn->prepare("UPDATE perfumes SET ml_disponibles = ? WHERE id = ?");
    $stmt->bind_param("di", $ml_nuevo, $perfume_id);
    $stmt->execute();
    $conn->commit();

    $diferencia = $ml_nuevo - $ml_antes;
    $signo      = $diferencia > 0 ? '+' : '';
    $detalle_notas = $notas !== '' ? ". Notas: $notas" : '';
    registrar_movimiento($conn, 'Inventario', 'Ajuste de ml',
        "Ajuste de ml de '{$perfume['nombre']}' de {$perfume['marca']}: " . (float)$ml_antes . " → " . (float)$ml_nuevo
        . " ml ($signo" . (float)$diferencia . " ml). Motivo: {$motivos[$motivo]}$detalle_notas");
    responder(true);
}

responder(false, 'Acción no válida.');
