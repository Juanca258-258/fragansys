<?php
// Conexión con la API de PayPal (Orders v2). Las claves viven en paypal_config.php, que no se sube a git.
require_once __DIR__ . '/../paypal_config.php';

function paypal_url_api() {
    return PAYPAL_MODO === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

// Llamada HTTP a PayPal. Devuelve [código HTTP, respuesta decodificada]; código 0 si no hubo conexión.
function paypal_http($metodo, $ruta, $cuerpo = null, $cabeceras = []) {
    $ch = curl_init(paypal_url_api() . $ruta);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $cabeceras,
        CURLOPT_TIMEOUT        => 30,
        // Lista oficial de certificados: el PHP de AppServ no trae una y sin ella no se puede verificar a PayPal.
        CURLOPT_CAINFO         => __DIR__ . '/cacert.pem',
    ]);
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
    }
    $respuesta = curl_exec($ch);
    $codigo    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($respuesta === false) {
        error_log('PayPal sin conexión: ' . curl_error($ch));
    }
    curl_close($ch);
    return [$codigo, $respuesta === false ? null : json_decode($respuesta, true)];
}

// Token de acceso (dura varias horas); se guarda en la sesión de PHP para no pedirlo en cada llamada.
function paypal_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $guardado = $_SESSION['paypal_token'] ?? null;
    if ($guardado && $guardado['expira'] > time() + 60 && $guardado['modo'] === PAYPAL_MODO) {
        return $guardado['token'];
    }
    [$codigo, $r] = paypal_http('POST', '/v1/oauth2/token', 'grant_type=client_credentials', [
        'Authorization: Basic ' . base64_encode(PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET),
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    if ($codigo !== 200 || empty($r['access_token'])) {
        error_log('PayPal no entregó token (HTTP ' . $codigo . ')');
        return null;
    }
    $_SESSION['paypal_token'] = ['token' => $r['access_token'], 'expira' => time() + (int)$r['expires_in'], 'modo' => PAYPAL_MODO];
    return $r['access_token'];
}

// Llamada autenticada con JSON. $id_peticion hace la operación idempotente: si se repite, PayPal no la duplica.
function paypal_api($metodo, $ruta, $datos = null, $id_peticion = null) {
    $token = paypal_token();
    if (!$token) {
        return [0, null];
    }
    $cabeceras = ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Prefer: return=representation'];
    if ($id_peticion) {
        $cabeceras[] = 'PayPal-Request-Id: ' . $id_peticion;
    }
    $cuerpo = $metodo === 'GET' ? null : ($datos === null ? '{}' : json_encode($datos));
    return paypal_http($metodo, $ruta, $cuerpo, $cabeceras);
}

// Referencia del pedido en PayPal. Lleva el prefijo de la PC porque cada PC tiene su propia base de datos.
function paypal_referencia($pedido_id) {
    return PAYPAL_PREFIJO . '-' . (int)$pedido_id;
}

function paypal_monto($total) {
    return number_format((float)$total, 2, '.', '');
}

// Crea la orden en PayPal por el total guardado en la base de datos. Devuelve el id de la orden o null.
function paypal_crear_orden($pedido, $negocio) {
    [$codigo, $r] = paypal_api('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => paypal_referencia($pedido['id']),
            'custom_id'    => paypal_referencia($pedido['id']),
            'description'  => 'Pedido #' . $pedido['id'] . ' - ' . $negocio,
            'amount'       => ['currency_code' => PAYPAL_MONEDA, 'value' => paypal_monto($pedido['total'])],
        ]],
        // No se fija la forma de pago (payment_source): así la misma orden sirve para cuenta PayPal y para tarjeta.
        'application_context' => [
            'brand_name'          => mb_substr($negocio, 0, 127),
            'locale'              => 'es-MX',
            'shipping_preference' => 'NO_SHIPPING', // La dirección de entrega ya la capturó la tienda
            'user_action'         => 'PAY_NOW',
        ],
    ]);
    if (($codigo !== 200 && $codigo !== 201) || empty($r['id'])) {
        error_log('PayPal no creó la orden del pedido ' . $pedido['id'] . ' (HTTP ' . $codigo . '): ' . json_encode($r));
        return null;
    }
    return $r['id'];
}

// Consulta a PayPal a qué pedido pertenece una orden y por cuánto es: ['referencia', 'monto', 'moneda'] o null.
function paypal_consultar_orden($orden_id) {
    [$codigo, $r] = paypal_api('GET', '/v2/checkout/orders/' . rawurlencode($orden_id));
    $unidad = $r['purchase_units'][0] ?? null;
    if ($codigo !== 200 || !$unidad) {
        return null;
    }
    return [
        'referencia' => $unidad['custom_id'] ?? '',
        'monto'      => $unidad['amount']['value'] ?? '',
        'moneda'     => $unidad['amount']['currency_code'] ?? '',
    ];
}

// Cobra una orden ya aprobada por el cliente. Devuelve [estado, datos]:
//   'cobrado'    -> el dinero quedó cobrado; datos = ['monto', 'moneda', 'correo', 'captura_id']
//   'rechazado'  -> PayPal rechazó la forma de pago; el cliente puede intentar con otra
//   'pendiente'  -> PayPal dejó el cobro en revisión
//   'error'      -> cualquier otro problema (no se cobró)
function paypal_capturar_orden($orden_id) {
    [$codigo, $r] = paypal_api('POST', '/v2/checkout/orders/' . rawurlencode($orden_id) . '/capture', null, 'captura-' . $orden_id);

    if ($codigo === 422 && isset($r['details'][0]['issue'])) {
        $problema = $r['details'][0]['issue'];
        if ($problema === 'INSTRUMENT_DECLINED') {
            return ['rechazado', null];
        }
        if ($problema === 'ORDER_ALREADY_CAPTURED') {
            // Ya se había cobrado (p. ej. se cortó la conexión en el primer intento): se consulta la orden.
            [$codigo, $r] = paypal_api('GET', '/v2/checkout/orders/' . rawurlencode($orden_id));
        }
    }
    $captura = $r['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (($codigo !== 200 && $codigo !== 201) || !$captura) {
        error_log('PayPal no cobró la orden ' . $orden_id . ' (HTTP ' . $codigo . '): ' . json_encode($r));
        return ['error', null];
    }
    $datos = [
        'monto'      => $captura['amount']['value'] ?? '',
        'moneda'     => $captura['amount']['currency_code'] ?? '',
        'correo'     => $r['payer']['email_address'] ?? null,
        'captura_id' => $captura['id'] ?? null,
    ];
    if ($captura['status'] === 'COMPLETED') {
        return ['cobrado', $datos];
    }
    if ($captura['status'] === 'PENDING') {
        return ['pendiente', $datos];
    }
    return ['error', $datos];
}
