<?php
/**
 * API de sesión del cliente para la app Android.
 *   accion=iniciar_sesion  correo, password -> token de sesión + datos del cliente
 *   accion=perfil          token            -> datos actuales del cliente (para saber si la sesión sigue válida)
 *
 * La validación es la misma del catálogo web (validarLoginCliente de back_login_cliente.php).
 * Cerrar sesión no necesita llamar al servidor: la app solo borra su token.
 */
require_once __DIR__ . '/../../conexion_api.php';
require_once __DIR__ . '/../../../catalogo-publico/sesion-cliente/back_login_cliente.php';

$accion = api_param('accion');

if ($accion === 'iniciar_sesion') {
    $correo   = trim(api_param('correo'));
    $password = api_param('password');

    $resultado = validarLoginCliente($conn, $correo, $password);
    if ($resultado['status'] !== 'success') {
        api_error($resultado['message']);
    }

    // validarLoginCliente dejó al cliente en $_SESSION; la app no usa la sesión de PHP sino un token
    $clienteId = (int)$_SESSION['cliente_id'];
    session_destroy();

    $stmt = $conn->prepare("SELECT id, nombre, correo, telefono, direccion FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $clienteId);
    $stmt->execute();

    api_responder(array(
        'status'  => 'success',
        'token'   => api_crear_token($clienteId),
        'cliente' => api_datos_cliente($stmt->get_result()->fetch_assoc()),
    ));
}

if ($accion === 'perfil') {
    $cliente = api_requiere_cliente($conn);
    api_responder(array('status' => 'success', 'cliente' => api_datos_cliente($cliente)));
}

api_error('Acción no válida');
