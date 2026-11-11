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

    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE
        || $archivo['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'La imagen pesa más de 2 MB. Usa una imagen más ligera.'];
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Ocurrió un error al subir la imagen.'];
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

// Valores por defecto mientras no se hayan guardado en Configuración.
const CONFIG_DEFECTO = [
    'negocio_nombre'       => 'Fragansys',
    'negocio_direccion'    => '',
    'negocio_telefono'     => '',
    'negocio_correo'       => '',
    'negocio_rfc'          => '',
    'ticket_mensaje'       => '¡Gracias por su compra!',
    'envio_costo'          => '0',
    'envio_gratis_desde'   => '',
    'stock_minimo_defecto' => '1',
];

function obtener_config($conn) {
    $config = CONFIG_DEFECTO;
    $res = mysqli_query($conn, "SELECT clave, valor FROM configuracion");
    while ($fila = mysqli_fetch_assoc($res)) {
        if (array_key_exists($fila['clave'], $config)) {
            $config[$fila['clave']] = $fila['valor'];
        }
    }
    return $config;
}

// Costo de envío de un pedido en línea según Configuración: el costo fijo, o 0 si el subtotal
// llega al monto de "envío gratis desde" (vacío = el envío nunca es gratis).
function calcular_envio($config, $subtotal) {
    $gratis_desde = $config['envio_gratis_desde'];
    if ($gratis_desde !== '' && (float)$gratis_desde > 0 && round((float)$subtotal, 2) >= (float)$gratis_desde) {
        return 0.00;
    }
    return max(0, round((float)$config['envio_costo'], 2));
}

function registrar_movimiento($conn, $modulo, $accion, $descripcion, $usuario_id = null) {
    $usuario_id = $usuario_id ?? ($_SESSION['id_usuario'] ?? null);
    $stmt = $conn->prepare("INSERT INTO movimientos (usuario_id, modulo, accion, descripcion) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $usuario_id, $modulo, $accion, $descripcion);
    $stmt->execute();
}

/**
 * Datos de un combo activo para venderlo: id, nombre, precio, imagen, piezas y stock.
 * El stock son los combos completos que alcanzan: se suma lo que el combo pide de cada perfume
 * (ml para los decants, frascos sellados para los frascos) y se compara con lo disponible.
 * Devuelve null si el combo no existe, está inactivo o alguna de sus presentaciones ya no está activa.
 */
function obtener_combo_venta($conn, $combo_id) {
    $stmt = $conn->prepare("SELECT id, nombre, precio, imagen FROM combos WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $combo_id);
    $stmt->execute();
    $combo = $stmt->get_result()->fetch_assoc();
    if (!$combo) return null;

    $stmt = $conn->prepare(
        "SELECT cp.cantidad, pr.tipo, pr.ml, pr.activo AND pf.activo AS activo,
                pf.id AS perfume_id, pf.frascos_sellados, pf.ml_disponibles
         FROM combo_presentaciones cp
         INNER JOIN presentaciones pr ON cp.presentacion_id = pr.id
         INNER JOIN perfumes pf ON pr.perfume_id = pf.id
         WHERE cp.combo_id = ?");
    $stmt->bind_param("i", $combo_id);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if (empty($filas)) return null;

    $por_perfume = [];
    $piezas = 0;
    foreach ($filas as $f) {
        if (!$f['activo']) return null;
        $pid = (int)$f['perfume_id'];
        if (!isset($por_perfume[$pid])) {
            $por_perfume[$pid] = ['ml' => 0, 'frascos' => 0,
                                  'ml_disp' => (float)$f['ml_disponibles'], 'frascos_disp' => (int)$f['frascos_sellados']];
        }
        if ($f['tipo'] === 'frasco') {
            $por_perfume[$pid]['frascos'] += (int)$f['cantidad'];
        } else {
            $por_perfume[$pid]['ml'] += (float)$f['ml'] * (int)$f['cantidad'];
        }
        $piezas += (int)$f['cantidad'];
    }

    $stock = PHP_INT_MAX;
    foreach ($por_perfume as $p) {
        if ($p['ml'] > 0)      $stock = min($stock, (int)floor(round($p['ml_disp'] / $p['ml'], 6)));
        if ($p['frascos'] > 0) $stock = min($stock, intdiv($p['frascos_disp'], $p['frascos']));
    }

    $combo['precio'] = (float)$combo['precio'];
    $combo['piezas'] = $piezas;
    $combo['stock']  = max(0, $stock);
    return $combo;
}
