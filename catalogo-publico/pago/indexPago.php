<?php
require_once __DIR__ . '/back_pago.php';

$pedidoId = isset($_GET['pedido']) ? (int)$_GET['pedido'] : 0;
$pedido   = $pedidoId ? obtenerPedidoPago($conn, $pedidoId) : null;
$visible  = $pedido && puedePagarPedido($pedido);
$detalle  = $visible ? obtenerDetallePago($conn, $pedidoId) : array();
$conSesion = isset($_SESSION['cliente_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar pedido - Fragansys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .font-serif { font-family: 'Georgia', 'Times New Roman', serif; }
        .text-brown { color: #4a3320 !important; }
        .text-gold { color: #b58c5a !important; }
        .tarjeta-pago { max-width: 560px; margin: 0 auto; }
        .linea-producto { border-bottom: 1px dashed #e6ddd2; }
        .linea-producto:last-child { border-bottom: 0; }
        #botones-paypal { min-height: 150px; }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-light bg-white shadow-sm py-3 mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <span class="rounded-circle text-white d-flex justify-content-center align-items-center" style="width: 35px; height: 35px; background-color: #4a3320; font-family: Georgia, serif;">F</span>
                <span class="ms-2 fs-4 font-serif text-brown">Fragansys</span>
            </a>
        </div>
    </nav>

    <div class="container py-3">
        <div class="tarjeta-pago">
        <?php if (!$visible): ?>
            <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                <i class="bi bi-search text-gold" style="font-size: 3.5rem;"></i>
                <h4 class="font-serif text-brown fw-bold mt-3">No encontramos este pedido</h4>
                <p class="text-muted px-4">Inicia sesión para ver y pagar tus pedidos.</p>
                <a href="../sesion-cliente/indexS.php" class="btn btn-dark rounded-pill px-4" style="background-color: #4a3320; border: none;">Iniciar sesión</a>
            </div>
        <?php else: ?>
            <div class="text-center mb-4">
                <h2 class="font-serif text-brown fw-bold mb-1">Pagar pedido #<?= (int)$pedido['id'] ?></h2>
                <p class="text-muted mb-0" style="font-style: italic; font-family: Georgia, serif;">Pago seguro con PayPal o tarjeta</p>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-body p-4">
                    <div class="small text-muted text-uppercase mb-2" style="letter-spacing: 1px;">Tu pedido</div>
                    <?php foreach ($detalle as $d): ?>
                        <div class="d-flex justify-content-between py-2 linea-producto">
                            <span><span class="text-muted me-2"><?= (int)$d['cantidad'] ?> ×</span><?= htmlspecialchars($d['nombre']) ?></span>
                            <span class="fw-semibold text-nowrap ms-3">$<?= number_format($d['subtotal'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between pt-2 small text-muted">
                        <span>Envío</span><span><?= (float)$pedido['costo_envio'] > 0 ? '$' . number_format($pedido['costo_envio'], 2) : 'Gratis' ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top mt-2 pt-3">
                        <span class="font-serif fw-bold text-brown fs-5">Total</span>
                        <span class="fs-3 fw-bold text-gold">$<?= number_format($pedido['total'], 2) ?> <small class="fs-6 text-muted">MXN</small></span>
                    </div>
                    <div class="small text-muted mt-2"><i class="bi bi-geo-alt me-1"></i>Entrega: <?= htmlspecialchars($pedido['direccion_entrega']) ?></div>
                </div>
            </div>

            <div id="mensaje-pago" class="d-none"></div>

            <?php if ($pedido['estado_pago'] === 'pagado'): ?>
                <div class="alert alert-success rounded-4 text-center"><i class="bi bi-check-circle me-2"></i>Este pedido ya está pagado. ¡Gracias por tu compra!</div>
            <?php elseif (!pedidoPagable($pedido)): ?>
                <div class="alert alert-secondary rounded-4 text-center">Este pedido ya no se puede pagar.</div>
            <?php else: ?>
                <div id="zona-pago" class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div id="botones-paypal">
                            <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Cargando PayPal…</div>
                        </div>
                        <p class="small text-muted text-center mb-0 mt-2">
                            <i class="bi bi-shield-lock me-1"></i>Tus datos de pago los maneja PayPal; nosotros no los vemos ni los guardamos.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="<?= $conSesion ? '../sesion-cliente/indexP.php' : '../index.php' ?>" class="btn btn-outline-dark rounded-pill px-4" id="btn-salir">
                    <?= $conSesion ? 'Ir a mis pedidos' : 'Volver a la tienda' ?>
                </a>
                <?php if (pedidoPagable($pedido)): ?>
                    <div class="small text-muted mt-2" id="nota-despues">Si prefieres pagar después, tu pedido queda guardado como pendiente<?= $conSesion ? ' en "Mis pedidos"' : '; inicia sesión con tu correo para pagarlo' ?>.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>

<?php if ($visible && pedidoPagable($pedido)): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode(PAYPAL_CLIENT_ID) ?>&currency=<?= PAYPAL_MONEDA ?>&intent=capture&locale=es_MX"
            onerror="mostrarMensaje('danger', 'No se pudo cargar PayPal. Revisa tu conexión y recarga la página.')"></script>
    <script>
        const PEDIDO_ID = <?= (int)$pedido['id'] ?>;

        function mostrarMensaje(tipo, texto) {
            const caja = document.getElementById('mensaje-pago');
            caja.className = 'alert alert-' + tipo + ' rounded-4 text-center';
            caja.textContent = texto;
            caja.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function llamarPago(accion, extra) {
            const datos = new FormData();
            datos.append('accion', accion);
            datos.append('pedido_id', PEDIDO_ID);
            Object.keys(extra || {}).forEach(k => datos.append(k, extra[k]));
            return fetch('act_pago.php', { method: 'POST', body: datos }).then(r => r.json());
        }

        function pagoTerminado(texto) {
            mostrarMensaje('success', texto);
            document.getElementById('zona-pago').remove();
            const nota = document.getElementById('nota-despues');
            if (nota) nota.remove();
        }

        if (window.paypal) {
            document.getElementById('botones-paypal').innerHTML = '';
            paypal.Buttons({
                style: { layout: 'vertical', shape: 'pill', label: 'pay' },

                // 1) Clic en pagar: nuestro servidor crea la orden con el total guardado en la base de datos.
                createOrder: function () {
                    return llamarPago('crear_orden').then(function (r) {
                        if (r.status === 'pagado') { pagoTerminado(r.message); throw new Error('ya pagado'); }
                        if (r.status !== 'success') { mostrarMensaje('warning', r.message); throw new Error(r.message); }
                        return r.orden_id;
                    });
                },

                // 2) El cliente aprobó en PayPal: nuestro servidor cobra, verifica el monto y marca el pedido como pagado.
                onApprove: function (data, actions) {
                    mostrarMensaje('info', 'Confirmando tu pago…');
                    return llamarPago('capturar', { orden_id: data.orderID }).then(function (r) {
                        if (r.status === 'success' || r.status === 'pagado') return pagoTerminado(r.message);
                        if (r.status === 'rechazado') { mostrarMensaje('warning', r.message); return actions.restart(); }
                        mostrarMensaje('danger', r.message);
                    }).catch(function () {
                        mostrarMensaje('danger', 'Se perdió la conexión al confirmar el pago. Recarga la página para ver si quedó pagado antes de intentar otra vez.');
                    });
                },

                onCancel: function () {
                    mostrarMensaje('secondary', 'Cancelaste el pago. Tu pedido sigue pendiente; puedes intentarlo de nuevo cuando quieras.');
                },

                onError: function (err) {
                    console.error(err);
                    if (!document.getElementById('mensaje-pago').classList.contains('d-none')) return; // ya hay un mensaje
                    mostrarMensaje('danger', 'Ocurrió un problema con PayPal. Intenta de nuevo.');
                }
            }).render('#botones-paypal');
        }
    </script>
<?php endif; ?>
</body>
</html>
