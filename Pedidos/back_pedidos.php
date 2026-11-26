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
    if ($siguiente === 'enviado') {
        // Fragansys no hace el envío: se entrega a una paquetería y se guarda su número de guía.
        $paqueteria = trim($_POST['paqueteria'] ?? '');
        $guia       = strtoupper(preg_replace('/\s+/', '', $_POST['numero_guia'] ?? ''));
        if ($paqueteria === '' || mb_strlen($paqueteria) > 60) {
            responder(false, 'Escribe la paquetería con la que se envió el pedido (máximo 60 caracteres).');
        }
        if (!preg_match('/^[A-Z0-9\-]{6,40}$/', $guia)) {
            responder(false, 'El número de guía no es válido: deben ser de 6 a 40 letras o números, sin espacios.');
        }
        $stmt = $conn->prepare("UPDATE pedidos SET estado_pedido = ?, paqueteria = ?, numero_guia = ?, fecha_envio = NOW()
                                WHERE id = ? AND estado_pedido = ?");
        $stmt->bind_param("sssis", $siguiente, $paqueteria, $guia, $id, $actual);
    } else {
        $stmt = $conn->prepare("UPDATE pedidos SET estado_pedido = ? WHERE id = ? AND estado_pedido = ?");
        $stmt->bind_param("sis", $siguiente, $id, $actual);
    }
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        responder(false, 'El pedido cambió mientras lo veías (otra persona lo actualizó o el cliente lo canceló). Vuelve a abrirlo.');
    }

    registrar_movimiento($conn, 'Pedidos', 'Cambiar estado',
        "Pedido #$id: " . ETIQUETAS_ESTADO[$actual] . " → " . ETIQUETAS_ESTADO[$siguiente]
        . ($siguiente === 'enviado' ? " ($paqueteria, guía $guia)" : ''));

    // Aviso al cliente cuando su pedido va en camino o ya se entregó. Si el correo falla, el estado ya cambió igual.
    // El correo tarda varios segundos, así que primero se contesta y luego se envía.
    if ($siguiente === 'enviado' || $siguiente === 'entregado') {
        responder_y_continuar(json_encode(['ok' => true]));
        require_once __DIR__ . '/../correos/correos.php';
        if ($siguiente === 'enviado') {
            correo_pedido_enviado($conn, $id);
        } else {
            correo_pedido_entregado($conn, $id);
        }
        exit;
    }
    responder(true);
}

// Devuelve en PayPal el dinero de un pedido que el cliente canceló después de pagarlo.
// El stock ya se regresó al cancelar; aquí solo se mueve el dinero y se marca el pago como reembolsado.
if ($accion == "reembolsar") {
    if (($_SESSION['rol'] ?? '') !== 'admin') {
        responder(false, 'Solo un administrador puede hacer reembolsos.');
    }
    $id = (int)($_POST['id'] ?? 0);
    require_once __DIR__ . '/../pagos/paypal.php';

    // Se bloquea el pedido mientras PayPal contesta: si dos personas dan clic a la vez, la segunda espera
    // y luego ve que ya está reembolsado (y PayPal tampoco lo duplicaría, por el Request-Id).
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT id, total, estado_pedido, estado_pago, paypal_order_id FROM pedidos WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();

        $problema = null;
        if (!$pedido)                                     $problema = 'Pedido no encontrado.';
        elseif ($pedido['estado_pago'] === 'reembolsado') $problema = 'Este pedido ya estaba reembolsado.';
        elseif ($pedido['estado_pedido'] !== 'cancelado') $problema = 'Solo se reembolsan pedidos cancelados.';
        elseif ($pedido['estado_pago'] !== 'pagado')      $problema = 'Este pedido no se pagó, no hay nada que reembolsar.';
        elseif (empty($pedido['paypal_order_id']))        $problema = 'Este pedido no tiene una orden de PayPal registrada.';
        if ($problema) {
            $conn->rollback();
            responder(false, $problema);
        }

        [$resultado, $datos] = paypal_reembolsar_orden($pedido['paypal_order_id'], $pedido['total']);
        if ($resultado === 'error') {
            $conn->rollback();
            responder(false, $datos['mensaje']);
        }

        $stmt = $conn->prepare("UPDATE pedidos SET estado_pago = 'reembolsado', paypal_reembolso_id = ?, fecha_reembolso = NOW() WHERE id = ?");
        $stmt->bind_param("si", $datos['reembolso_id'], $id);
        $stmt->execute();
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log("Reembolso del pedido $id: " . $e->getMessage());
        // Si PayPal ya había devuelto el dinero, al reintentar se detecta y se registra sin reembolsar otra vez.
        responder(false, 'Ocurrió un error al registrar el reembolso. Vuelve a intentarlo: no se devolverá el dinero dos veces.');
    }
    mysqli_report(MYSQLI_REPORT_OFF);

    registrar_movimiento($conn, 'Pedidos', 'Reembolso',
        "Pedido #$id reembolsado en PayPal por $" . number_format($pedido['total'], 2) . " (reembolso {$datos['reembolso_id']})");

    // Primero se contesta al administrador y después se avisa al cliente por correo (tarda unos segundos).
    // Si el correo falla, el reembolso ya quedó hecho igual.
    responder_y_continuar(json_encode(['ok' => true, 'mensaje' => $resultado === 'pendiente'
        ? 'PayPal aceptó el reembolso y lo está procesando; el cliente lo recibirá en unos minutos. Se le avisará por correo.'
        : 'Se devolvieron $' . number_format($pedido['total'], 2) . ' al cliente en PayPal. Se le avisará por correo.']));
    require_once __DIR__ . '/../correos/correos.php';
    correo_pedido_reembolsado($conn, $id, $resultado === 'pendiente');
    exit;
}

responder(false, 'Acción no válida.');
