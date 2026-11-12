<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");

function responder($ok, $error = null) {
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if ($_SESSION['rol'] !== 'admin') {
    responder(false, 'No tienes permiso para cambiar la configuración.');
}

// Etiqueta que se muestra en el Historial y descripción que se guarda en la tabla.
const CAMPOS_CONFIG = [
    'negocio_nombre'       => ['Nombre del negocio', 'Nombre que aparece en el ticket'],
    'negocio_direccion'    => ['Dirección', 'Dirección del negocio para el ticket'],
    'negocio_telefono'     => ['Teléfono', 'Teléfono del negocio para el ticket'],
    'negocio_correo'       => ['Correo', 'Correo de contacto del negocio'],
    'negocio_rfc'          => ['RFC', 'RFC del negocio (opcional)'],
    'ticket_mensaje'       => ['Mensaje del ticket', 'Texto al pie del ticket'],
    'envio_costo'          => ['Costo de envío', 'Costo fijo de envío para pedidos en línea'],
    'envio_gratis_desde'   => ['Envío gratis desde', 'Monto a partir del cual el envío es gratis (vacío = nunca)'],
    'stock_minimo_defecto' => ['Stock mínimo por defecto', 'Mínimo de frascos sellados propuesto al dar de alta un perfume'],
];

if (($_POST['accion'] ?? '') !== 'guardar') {
    responder(false, 'Acción no válida.');
}

$v = [];
foreach (array_keys(CAMPOS_CONFIG) as $clave) {
    $v[$clave] = trim($_POST[$clave] ?? '');
}

if ($v['negocio_nombre'] === '') responder(false, 'El nombre del negocio es obligatorio.');
if (strlen($v['negocio_nombre']) > 100) responder(false, 'El nombre del negocio es demasiado largo (máximo 100 caracteres).');
if (strlen($v['negocio_direccion']) > 200) responder(false, 'La dirección es demasiado larga (máximo 200 caracteres).');
// Teléfono: se ignoran espacios, guiones y paréntesis; deben quedar exactamente 10 dígitos.
$v['negocio_telefono'] = preg_replace('/[\s\-().]/', '', $v['negocio_telefono']);
if ($v['negocio_telefono'] !== '' && !preg_match('/^\d{10}$/', $v['negocio_telefono'])) responder(false, 'El teléfono debe tener 10 dígitos (solo números).');
if ($v['negocio_correo'] !== '' && !filter_var($v['negocio_correo'], FILTER_VALIDATE_EMAIL)) responder(false, 'El correo no es válido.');
// RFC: 3 letras (empresa) o 4 letras (persona física) + fecha AAMMDD + 3 de homoclave. Se ignoran espacios y guiones.
$v['negocio_rfc'] = mb_strtoupper(preg_replace('/[\s\-]/', '', $v['negocio_rfc']), 'UTF-8');
if ($v['negocio_rfc'] !== '') {
    $rfc_ok = preg_match('/^[A-ZÑ&]{3,4}(\d{2})(\d{2})(\d{2})[A-Z0-9]{3}$/u', $v['negocio_rfc'], $m)
              && checkdate((int)$m[2], (int)$m[3], 2000 + (int)$m[1]);
    if (!$rfc_ok) {
        responder(false, 'El RFC no es válido. Lleva letras, la fecha y la homoclave: 4 letras + AAMMDD + 3 caracteres '
                       . '(persona física, ej. GODE561231GR8) o 3 letras + AAMMDD + 3 caracteres (empresa, ej. ABC010101AB1).');
    }
}
if (strlen($v['ticket_mensaje']) > 150) responder(false, 'El mensaje del ticket es demasiado largo (máximo 150 caracteres).');

if (!is_numeric($v['envio_costo']) || (float)$v['envio_costo'] < 0) responder(false, 'El costo de envío debe ser 0 o más.');
$v['envio_costo'] = number_format((float)$v['envio_costo'], 2, '.', '');
if ($v['envio_gratis_desde'] !== '') {
    if (!is_numeric($v['envio_gratis_desde']) || (float)$v['envio_gratis_desde'] <= 0) responder(false, 'El monto para envío gratis debe ser mayor a 0, o déjalo vacío.');
    $v['envio_gratis_desde'] = number_format((float)$v['envio_gratis_desde'], 2, '.', '');
}

if (!ctype_digit($v['stock_minimo_defecto'])) responder(false, 'El stock mínimo por defecto debe ser un número entero de 0 o más.');

$anterior = obtener_config($conn);
$cambiados = [];

$stmt = $conn->prepare("INSERT INTO configuracion (clave, valor, descripcion) VALUES (?,?,?)
                        ON DUPLICATE KEY UPDATE valor = VALUES(valor), descripcion = VALUES(descripcion)");
foreach (CAMPOS_CONFIG as $clave => [$etiqueta, $descripcion]) {
    $stmt->bind_param("sss", $clave, $v[$clave], $descripcion);
    $stmt->execute();
    $antes = (string)$anterior[$clave];
    $igual = (is_numeric($antes) && is_numeric($v[$clave])) ? (float)$antes == (float)$v[$clave] : $antes === $v[$clave];
    if (!$igual) {
        $cambiados[] = $etiqueta;
    }
}

if ($cambiados) {
    registrar_movimiento($conn, 'Configuración', 'Editar', 'Actualizó: ' . implode(', ', $cambiados));
}
responder(true);
