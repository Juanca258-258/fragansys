<?php
/**
 * Pago con tarjeta para la app Android. La app abre esta página en un WebView.
 * Usa el mismo botón "Tarjeta de débito o crédito" del SDK de PayPal que catalogo-publico/pago/indexPago.php,
 * así el cliente llena el formulario de la tarjeta sin entrar a su cuenta de PayPal ni crear una.
 *
 * Recibe pedido_id y token (la sesión del cliente). Cuando el cliente aprueba el pago, la página se va a
 *   retorno.php?resultado=aprobado&orden=<id de la orden>
 * y la app intercepta esa dirección para cobrar (act_pago.php, accion=capturar).
 */
ini_set('display_errors', 0);
require_once __DIR__ . '/../../conexion_api.php';
require_once __DIR__ . '/../../../paypal_config.php';
header('Content-Type: text/html; charset=utf-8');

$cliente  = api_cliente_sesion($conn);
$pedidoId = (int)api_param('pedido_id', '0');
$token    = api_param('token');

$pedido = null;
if ($cliente && $pedidoId) {
    $stmt = $conn->prepare("SELECT id, total, estado_pago, estado_pedido FROM pedidos WHERE id = ? AND cliente_id = ?");
    $stmt->bind_param("ii", $pedidoId, $cliente['id']);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
}
$pagable = $pedido && $pedido['estado_pedido'] !== 'cancelado' && in_array($pedido['estado_pago'], array('pendiente', 'fallido'), true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar con tarjeta - Fragansys</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #f8f9fa; color: #4a3320; margin: 0; padding: 20px 16px; }
        h2 { font-family: Georgia, serif; text-align: center; margin: 0 0 4px; }
        .sub { text-align: center; color: #777; font-style: italic; font-family: Georgia, serif; margin: 0 0 18px; }
        .total { text-align: center; font-size: 1.6rem; font-weight: bold; color: #b58c5a; margin-bottom: 18px; }
        .caja { background: #fff; border-radius: 16px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
        #mensaje { display: none; padding: 12px; border-radius: 12px; margin-bottom: 14px; text-align: center; }
        .error { background: #f8d7da; color: #842029; } .info { background: #cff4fc; color: #055160; }
        .nota { font-size: .8rem; color: #777; text-align: center; margin-top: 12px; }
        #cargando { text-align: center; color: #777; padding: 24px 0; }
    </style>
</head>
<body>
<?php if (!$pedido): ?>
    <div id="mensaje" class="error" style="display:block">No encontramos este pedido. Vuelve a la app e inicia sesión.</div>
<?php elseif (!$pagable): ?>
    <div id="mensaje" class="info" style="display:block">Este pedido ya no se puede pagar.</div>
<?php else: ?>
    <h2>Pagar pedido #<?= (int)$pedido['id'] ?></h2>
    <p class="sub">Tarjeta de débito o crédito</p>
    <div class="total">$<?= number_format((float)$pedido['total'], 2) ?> <small style="font-size:.9rem;color:#777"><?= PAYPAL_MONEDA ?></small></div>
    <div id="mensaje"></div>
    <div class="caja">
        <div id="cargando">Cargando formulario de pago…</div>
        <div id="boton-tarjeta"></div>
        <p class="nota">Tus datos de pago los maneja PayPal; nosotros no los vemos ni los guardamos.</p>
    </div>

    <script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode(PAYPAL_CLIENT_ID) ?>&currency=<?= PAYPAL_MONEDA ?>&intent=capture&locale=es_MX&components=buttons&enable-funding=card"
            onerror="mostrar('error', 'No se pudo cargar PayPal. Revisa tu conexión e intenta de nuevo.')"></script>
    <script>
        const PEDIDO_ID = <?= (int)$pedido['id'] ?>;
        const TOKEN = <?= json_encode($token) ?>;

        function mostrar(tipo, texto) {
            const caja = document.getElementById('mensaje');
            caja.className = tipo;
            caja.style.display = 'block';
            caja.textContent = texto;
            document.getElementById('cargando').style.display = 'none';
        }

        // Al aprobar se va a la dirección de retorno; la app la intercepta y hace el cobro
        function regresarApp(resultado, orden) {
            location.href = 'retorno.php?resultado=' + resultado + (orden ? '&orden=' + encodeURIComponent(orden) : '');
        }

        if (window.paypal) {
            const boton = paypal.Buttons({
                fundingSource: paypal.FUNDING.CARD,
                style: { layout: 'vertical', shape: 'pill' },
                // 1) El servidor crea la orden con el total guardado en la base de datos (igual que la web)
                createOrder: function () {
                    const datos = new FormData();
                    datos.append('accion', 'crear_orden');
                    datos.append('modo', 'sdk');
                    datos.append('pedido_id', PEDIDO_ID);
                    datos.append('token', TOKEN);
                    return fetch('act_pago.php', { method: 'POST', body: datos })
                        .then(r => r.json())
                        .then(function (r) {
                            if (r.status === 'pagado') { regresarApp('aprobado', ''); throw new Error('ya pagado'); }
                            if (r.status !== 'success') { mostrar('error', r.message); throw new Error(r.message); }
                            return r.orden_id;
                        });
                },
                // 2) El cliente llenó y aprobó la tarjeta: la app cobra y confirma
                onApprove: function (data) {
                    mostrar('info', 'Confirmando tu pago…');
                    regresarApp('aprobado', data.orderID);
                },
                onCancel: function () {
                    mostrar('info', 'Cancelaste el pago. Tu pedido sigue pendiente.');
                },
                onError: function (err) {
                    console.error(err);
                    if (document.getElementById('mensaje').style.display === 'block') return;
                    mostrar('error', 'Ocurrió un problema con el pago. Intenta de nuevo.');
                }
            });

            if (boton.isEligible()) {
                document.getElementById('cargando').style.display = 'none';
                boton.render('#boton-tarjeta');
            } else {
                mostrar('error', 'El pago con tarjeta no está disponible en este momento. Usa la opción de PayPal.');
            }
        }
    </script>
<?php endif; ?>
</body>
</html>
