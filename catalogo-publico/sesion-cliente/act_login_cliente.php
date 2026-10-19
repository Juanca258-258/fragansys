<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/back_login_cliente.php';

$respuesta = array('status' => 'error', 'message' => 'Acción no válida');
$accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

if ($accion === 'iniciar_sesion') {
    $correo   = isset($_POST['correo']) ? trim($_POST['correo']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $respuesta = validarLoginCliente($conn, $correo, $password);
} elseif ($accion === 'cerrar_sesion') {
    cerrarSesionCliente();
    $respuesta['status']  = 'success';
    $respuesta['message'] = 'Sesión cerrada.';
}

echo json_encode($respuesta);
