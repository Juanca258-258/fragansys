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

    // Se guarda una copia reducida (no se mueve el archivo: en Windows, mover conserva los permisos de la
    // carpeta temporal y luego el usuario no puede abrir la imagen; un archivo nuevo toma los de uploads).
    if (!is_uploaded_file($archivo['tmp_name'])
        || (!reducir_imagen($archivo['tmp_name'], $ruta_absoluta) && !copy($archivo['tmp_name'], $ruta_absoluta))) {
        return ['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.'];
    }
    @unlink($archivo['tmp_name']);

    return ['ok' => true, 'archivo' => $carpeta_relativa . '/' . $nombre_final];
}

/**
 * Guarda en $destino una versión de la imagen de máximo $max px por lado, en el mismo formato
 * (JPG, PNG o WEBP; PNG y WEBP conservan la transparencia). Las fotos de celular se enderezan.
 * Devuelve false si no se pudo procesar (quien llama decide si guarda el original).
 */
function reducir_imagen($origen, $destino, $max = 800) {
    $info = @getimagesize($origen);
    if (!$info || !function_exists('imagecreatetruecolor')) return false;
    [$ancho, $alto, $tipo] = $info;
    $giro = $tipo === IMAGETYPE_JPEG ? orientacion_jpeg($origen) : 1;

    // Ya es chica y no hay que girarla: se deja tal cual para no perder calidad.
    if (max($ancho, $alto) <= $max && $giro === 1 && filesize($origen) <= 300 * 1024) {
        return copy($origen, $destino);
    }

    @ini_set('memory_limit', '256M'); // una foto de 12 MP ocupa ~50 MB al abrirla
    switch ($tipo) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($origen); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($origen); break;
        case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($origen) : false; break;
        default: return false;
    }
    if (!$img) return false;

    // EXIF: 3 = de cabeza, 6 = girada a la derecha, 8 = girada a la izquierda.
    if (in_array($giro, [3, 6, 8], true)) {
        $girada = imagerotate($img, [3 => 180, 6 => -90, 8 => 90][$giro], 0);
        imagedestroy($img);
        $img = $girada;
        $ancho = imagesx($img);
        $alto  = imagesy($img);
    }

    $escala = min(1, $max / max($ancho, $alto));
    $nuevo_ancho = max(1, (int)round($ancho * $escala));
    $nuevo_alto  = max(1, (int)round($alto * $escala));
    $lienzo = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    if ($tipo !== IMAGETYPE_JPEG) {
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
    }
    imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho, $alto);
    imagedestroy($img);

    if ($tipo === IMAGETYPE_JPEG)      $ok = imagejpeg($lienzo, $destino, 82);
    elseif ($tipo === IMAGETYPE_PNG)   $ok = imagepng($lienzo, $destino, 6);
    else                               $ok = imagewebp($lienzo, $destino, 82);
    imagedestroy($lienzo);
    return $ok;
}

/**
 * Lee la orientación EXIF de un JPG (1 = normal) sin necesitar la extensión exif de PHP.
 */
function orientacion_jpeg($ruta) {
    $f = @fopen($ruta, 'rb');
    if (!$f) return 1;
    $datos = fread($f, 65536);
    fclose($f);
    $pos = strpos($datos, "Exif\0\0");
    if ($pos === false) return 1;
    $tiff = substr($datos, $pos + 6);
    if (strlen($tiff) < 8) return 1;
    $le = substr($tiff, 0, 2) === 'II';
    $u16 = function ($o) use ($tiff, $le) { return strlen($tiff) >= $o + 2 ? unpack($le ? 'v' : 'n', substr($tiff, $o, 2))[1] : 0; };
    $u32 = function ($o) use ($tiff, $le) { return strlen($tiff) >= $o + 4 ? unpack($le ? 'V' : 'N', substr($tiff, $o, 4))[1] : 0; };
    $ifd = $u32(4);
    $entradas = $u16($ifd);
    for ($i = 0; $i < $entradas; $i++) {
        $e = $ifd + 2 + $i * 12;
        if ($u16($e) === 0x0112) {
            $valor = $u16($e + 8);
            return ($valor >= 1 && $valor <= 8) ? $valor : 1;
        }
    }
    return 1;
}

/**
 * Manda ya la respuesta al navegador y deja que el script siga trabajando (p. ej. enviando correos,
 * que tardan varios segundos) sin que la persona tenga que esperar. Después de llamarla ya no se
 * puede mandar nada más al navegador ni cambiar la sesión.
 */
function responder_y_continuar($texto) {
    ignore_user_abort(true);   // si el navegador ya se fue, el script termina su trabajo igual
    @set_time_limit(120);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close(); // libera la sesión para que las demás pantallas no se queden esperando
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Connection: close');
    header('Content-Length: ' . strlen($texto));
    echo $texto;
    flush();
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
