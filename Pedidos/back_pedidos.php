<?php
require_once("../sesion.php");
require_once("../conexion.php");

$accion = $_POST['accion'];

if ($accion == "actualizar_estado") {
    $id            = (int)$_POST['id'];
    $estado_pedido = $_POST['estado_pedido'];

    $validos = ['nuevo', 'confirmado', 'en_preparacion', 'enviado', 'entregado', 'cancelado'];
    if (!in_array($estado_pedido, $validos, true)) {
        echo json_encode(['ok' => false, 'error' => 'Estado no válido.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE pedidos SET estado_pedido=? WHERE id=?");
    $stmt->bind_param("si", $estado_pedido, $id);
    $stmt->execute();

    echo json_encode(['ok' => true]);
    exit;
}
