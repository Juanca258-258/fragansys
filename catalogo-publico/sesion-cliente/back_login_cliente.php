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

function cerrarSesionCliente() {
    unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre'], $_SESSION['cliente_correo']);
}

function clienteSesionActiva() {
    return isset($_SESSION['cliente_id']);
}
