<?php
/**
 * Procesa la subida de una imagen desde $_FILES.
 * Devuelve ['ok'=>true,'archivo'=>'uploads/x/nombre.jpg'|null] o ['ok'=>false,'error'=>'...'].
 * 'archivo' es null cuando no se envió ningún archivo (para conservar la imagen actual en updates).
 */
function subir_imagen($campo, $carpeta_relativa) {
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'archivo' => null];
    }

    $archivo = $_FILES[$campo];

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Ocurrió un error al subir la imagen.'];
    }

    if ($archivo['size'] > 3 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'La imagen no debe superar 3 MB.'];
    }

    $permitidas = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!array_key_exists($ext, $permitidas)) {
        return ['ok' => false, 'error' => 'Formato no permitido. Usa JPG, PNG o WEBP.'];
    }

    if (getimagesize($archivo['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'El archivo no es una imagen válida.'];
    }

    $nombre_final  = uniqid('img_', true) . '.' . $ext;
    $ruta_absoluta = __DIR__ . '/' . $carpeta_relativa . '/' . $nombre_final;

    if (!move_uploaded_file($archivo['tmp_name'], $ruta_absoluta)) {
        return ['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.'];
    }

    return ['ok' => true, 'archivo' => $carpeta_relativa . '/' . $nombre_final];
}
