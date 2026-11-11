<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");
require_once(__DIR__ . "/flujo_pedidos.php");

function responder($ok, $error = null) {
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

$accion = $_POST['accion'] ?? '';

if ($accion == "avanzar_estado") {
    $id     = (int)($_POST['id'] ?? 0);
    $actual = $_POST['estado_actual'] ?? '';

    $siguiente = siguiente_estado($actual);
    if ($siguiente === null) {
        responder(false, 'Este pedido ya no puede avanzar de estado.');
    }

    // La condición sobre el estado actual evita que un doble clic, o dos personas a la vez, lo avancen dos pasos.
    $stmt = $conn->prepare("UPDATE pedidos SET estado_pedido = ? WHERE id = ? AND estado_pedido = ?");
    $stmt->bind_param("sis", $siguiente, $id, $actual);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        responder(false, 'El pedido cambió mientras lo veías (otra persona lo actualizó o el cliente lo canceló). Vuelve a abrirlo.');
    }

    registrar_movimiento($conn, 'Pedidos', 'Cambiar estado',
        "Pedido #$id: " . ETIQUETAS_ESTADO[$actual] . " → " . ETIQUETAS_ESTADO[$siguiente]);

    // Aviso al cliente cuando su pedido va en camino o ya se entregó. Si el correo falla, el estado ya cambió igual.
    if ($siguiente === 'enviado' || $siguiente === 'entregado') {
        require_once __DIR__ . '/../correos/correos.php';
        if ($siguiente === 'enviado') {
            correo_pedido_enviado($conn, $id);
        } else {
            correo_pedido_entregado($conn, $id);
        }
    }
    responder(true);
}

responder(false, 'Acción no válida.');
