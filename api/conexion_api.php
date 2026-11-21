<?php
/**
 * Base de la API que usa la app Android (Flutter).
 * La app nunca se conecta directo a MySQL: llama a estos archivos PHP, que usan la misma conexión
 * (conexion.php) y las mismas funciones del sistema (helpers.php, pagos/...).
 *
 * Todas las respuestas son JSON con la forma {"status": "success"|"error"|..., "message": "...", ...datos}.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
date_default_timezone_set('America/Mexico_City');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/api_config.php';

function api_responder($datos) {
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error($mensaje, $extra = array()) {
    api_responder(array('status' => 'error', 'message' => $mensaje) + $extra);
}

// Parámetro enviado por POST (formulario) o por GET
function api_param($nombre, $defecto = '') {
    if (isset($_POST[$nombre])) return is_string($_POST[$nombre]) ? $_POST[$nombre] : $defecto;
    if (isset($_GET[$nombre]))  return is_string($_GET[$nombre]) ? $_GET[$nombre] : $defecto;
    return $defecto;
}

/* ---------------------------------------------------------------------------------------------
 * Sesión del cliente en la app.
 * En el navegador la sesión vive en $_SESSION; la app en cambio guarda un "token" firmado con
 * API_CLAVE_SECRETA: "<id>.<vence>.<firma>". Nadie puede fabricar uno sin conocer la clave.
 * ------------------------------------------------------------------------------------------- */
function api_firma($texto) {
    return hash_hmac('sha256', $texto, API_CLAVE_SECRETA);
}

function api_crear_token($clienteId) {
    $datos = (int)$clienteId . '.' . (time() + API_DIAS_SESION * 86400);
    return $datos . '.' . api_firma($datos);
}

// Devuelve el id del cliente si el token es válido y no ha vencido; 0 si no.
function api_cliente_del_token($token) {
    $partes = explode('.', (string)$token);
    if (count($partes) !== 3) return 0;
    list($id, $vence, $firma) = $partes;
    if (!ctype_digit($id) || !ctype_digit($vence) || (int)$vence < time()) return 0;
    if (!hash_equals(api_firma($id . '.' . $vence), $firma)) return 0;
    return (int)$id;
}

// Cliente de la sesión actual (token enviado como campo "token" o cabecera X-Token). null si no hay sesión válida.
function api_cliente_sesion($conn) {
    $token = api_param('token');
    if ($token === '' && isset($_SERVER['HTTP_X_TOKEN'])) {
        $token = $_SERVER['HTTP_X_TOKEN'];
    }
    $id = api_cliente_del_token($token);
    if (!$id) return null;

    $stmt = $conn->prepare("SELECT id, nombre, correo, telefono, direccion, activo FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    return ($cliente && $cliente['activo']) ? $cliente : null;
}

// Datos públicos del cliente que se le mandan a la app
function api_datos_cliente($cliente) {
    return array(
        'id'        => (int)$cliente['id'],
        'nombre'    => $cliente['nombre'],
        'correo'    => $cliente['correo'],
        'telefono'  => (string)$cliente['telefono'],
        'direccion' => (string)$cliente['direccion'],
    );
}

// Igual que la sesión pero obligatoria: si no hay sesión válida se responde el error y se termina.
function api_requiere_cliente($conn) {
    $cliente = api_cliente_sesion($conn);
    if (!$cliente) {
        api_error('Tu sesión expiró. Inicia sesión de nuevo.', array('sin_sesion' => true));
    }
    return $cliente;
}

// Quita los ceros sobrantes de los ml: 5.00 -> "5", 2.50 -> "2.5"
function api_ml($ml) {
    return rtrim(rtrim(number_format((float)$ml, 2, '.', ''), '0'), '.');
}
