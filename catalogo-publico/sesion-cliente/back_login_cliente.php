<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../conexion.php';

function obtenerClienteLogin($db, $correo) {
    $stmt = $db->prepare("SELECT id, nombre, correo, password, activo FROM clientes WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Valida correo y contraseña contra la tabla clientes.
 * Devuelve array('status', 'message') y, si es correcto, deja la sesión del cliente iniciada.
 */
function validarLoginCliente($db, $correo, $password) {
    $resultado = array('status' => 'error', 'message' => 'Correo o contraseña incorrectos.');

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $password === '') {
        $resultado['message'] = 'Ingresa un correo válido y tu contraseña.';
        return $resultado;
    }

    $cliente = obtenerClienteLogin($db, $correo);

    if (!$cliente) {
        return $resultado;
    }

    if (!$cliente['activo']) {
        $resultado['message'] = 'Esta cuenta está desactivada. Contáctanos para más información.';
        return $resultado;
    }

    // Clientes registrados antes de que se pidiera contraseña
    if ($cliente['password'] === '') {
        $resultado['message'] = 'Tu cuenta aún no tiene contraseña. Créala al realizar tu próximo pedido desde el carrito.';
        return $resultado;
    }

    if (!password_verify($password, $cliente['password'])) {
        return $resultado;
    }

    session_regenerate_id(true);
    $_SESSION['cliente_id']     = (int)$cliente['id'];
    $_SESSION['cliente_nombre'] = $cliente['nombre'];
    $_SESSION['cliente_correo'] = $cliente['correo'];

    $resultado['status']  = 'success';
    $resultado['message'] = '';
    return $resultado;
}

// Al cerrar sesión también se vacía el carrito: lo que se agregó con la cuenta no queda para el siguiente visitante
function cerrarSesionCliente() {
    unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre'], $_SESSION['cliente_correo'],
          $_SESSION['checkout_cliente_id']);
    $_SESSION['carrito'] = array();
    session_regenerate_id(true);
}

function clienteSesionActiva() {
    return isset($_SESSION['cliente_id']);
}

/* ===================== Recuperar contraseña =====================
 * Se envía un código de 6 dígitos al correo del cliente. El código (cifrado), su vigencia y los
 * intentos se guardan en la sesión de este navegador, así que no se necesita ninguna tabla nueva.
 */
const RECUPERAR_MINUTOS_VIGENCIA = 15;
const RECUPERAR_MAX_INTENTOS     = 5;
const RECUPERAR_ESPERA_REENVIO   = 60; // segundos entre un código y el siguiente

function solicitarCodigoRecuperacion($db, $correo) {
    $resultado = array('status' => 'error', 'message' => '');

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $resultado['message'] = 'Ingresa un correo válido.';
        return $resultado;
    }

    $anterior = isset($_SESSION['recuperar_password']) ? $_SESSION['recuperar_password'] : null;
    if ($anterior && time() - $anterior['enviado'] < RECUPERAR_ESPERA_REENVIO) {
        $faltan = RECUPERAR_ESPERA_REENVIO - (time() - $anterior['enviado']);
        $resultado['message'] = "Espera $faltan segundos antes de pedir otro código.";
        return $resultado;
    }

    $cliente = obtenerClienteLogin($db, $correo);
    if (!$cliente) {
        $resultado['message'] = 'No encontramos una cuenta con ese correo.';
        return $resultado;
    }
    if (!$cliente['activo']) {
        $resultado['message'] = 'Esta cuenta está desactivada. Contáctanos para más información.';
        return $resultado;
    }

    $codigo = (string)random_int(100000, 999999);

    require_once __DIR__ . '/../../correos/correos.php';
    $negocio   = obtener_config($db)['negocio_nombre'];
    $contenido = '<p>Hola ' . correo_e($cliente['nombre']) . ',</p>'
        . '<p>Recibimos una solicitud para cambiar la contraseña de tu cuenta. Tu código es:</p>'
        . '<p style="text-align:center;font-size:32px;letter-spacing:8px;font-weight:bold;color:#4a3320;margin:24px 0;">' . $codigo . '</p>'
        . '<p>El código vence en ' . RECUPERAR_MINUTOS_VIGENCIA . ' minutos. Si tú no lo pediste, ignora este correo: tu contraseña no cambiará.</p>';
    $html = correo_plantilla($negocio, 'Cambiar contraseña', $contenido);

    if (!correo_enviar($cliente['correo'], $cliente['nombre'], 'Código para cambiar tu contraseña', $html)) {
        $resultado['message'] = 'No pudimos enviar el correo. Intenta de nuevo más tarde.';
        return $resultado;
    }

    $_SESSION['recuperar_password'] = array(
        'cliente_id' => (int)$cliente['id'],
        'correo'     => $cliente['correo'],
        'codigo'     => password_hash($codigo, PASSWORD_DEFAULT),
        'expira'     => time() + RECUPERAR_MINUTOS_VIGENCIA * 60,
        'enviado'    => time(),
        'intentos'   => 0,
    );

    $resultado['status']  = 'success';
    $resultado['message'] = 'Te enviamos un código a ' . $cliente['correo'] . '. Revisa también tu carpeta de spam.';
    return $resultado;
}

function cambiarPasswordConCodigo($db, $codigo, $nueva, $confirmar) {
    $resultado = array('status' => 'error', 'message' => '');
    $datos = isset($_SESSION['recuperar_password']) ? $_SESSION['recuperar_password'] : null;

    if (!$datos) {
        $resultado['message'] = 'Primero solicita un código con tu correo.';
        return $resultado;
    }
    if (time() > $datos['expira'] || $datos['intentos'] >= RECUPERAR_MAX_INTENTOS) {
        unset($_SESSION['recuperar_password']);
        $resultado['message'] = 'El código ya no es válido. Solicita uno nuevo.';
        $resultado['reiniciar'] = true;
        return $resultado;
    }
    if (!password_verify(trim($codigo), $datos['codigo'])) {
        $_SESSION['recuperar_password']['intentos']++;
        $restantes = RECUPERAR_MAX_INTENTOS - $_SESSION['recuperar_password']['intentos'];
        $resultado['message'] = $restantes > 0
            ? "Código incorrecto. Te quedan $restantes intentos."
            : 'Código incorrecto. Solicita uno nuevo.';
        return $resultado;
    }
    if (mb_strlen($nueva) < 6) {
        $resultado['message'] = 'La contraseña debe tener al menos 6 caracteres.';
        return $resultado;
    }
    if ($nueva !== $confirmar) {
        $resultado['message'] = 'Las contraseñas no coinciden.';
        return $resultado;
    }

    $hash = password_hash($nueva, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE clientes SET password = ? WHERE id = ? AND correo = ?");
    $stmt->bind_param("sis", $hash, $datos['cliente_id'], $datos['correo']);
    $stmt->execute();

    unset($_SESSION['recuperar_password']);

    $resultado['status']  = 'success';
    $resultado['message'] = 'Tu contraseña se cambió correctamente. Ya puedes iniciar sesión con la nueva.';
    $resultado['correo']  = $datos['correo'];
    return $resultado;
}
