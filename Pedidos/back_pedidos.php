<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");

$accion = $_POST['accion'];

if ($accion == "actualizar_estado") {
    $id            = (int)$_POST['id'];
    $estado_pedido = $_POST['estado_pedido'];

    $validos = ['nuevo', 'confirmado', 'en_preparacion', 'enviado', 'entregado', 'cancelado'];
    if (!in_array($estado_pedido, $validos, true)) {
        echo json_encode(['ok' => false, 'error' => 'Estado no válido.']);
        exit;
    }

    $anterior = mysqli_fetch_assoc(mysqli_query($conn, "SELECT estado_pedido FROM pedidos WHERE id=$id"));

    $stmt = $conn->prepare("UPDATE pedidos SET estado_pedido=? WHERE id=?");
    $stmt->bind_param("si", $estado_pedido, $id);
    $stmt->execute();

    if ($anterior['estado_pedido'] !== $estado_pedido) {
        registrar_movimiento($conn, 'Pedidos', 'Cambiar estado', "Pedido #$id: {$anterior['estado_pedido']} → $estado_pedido");
    }

    echo json_encode(['ok' => true]);
    exit;
}
