<?php
// Envío de correos con PHPMailer. Los datos de la cuenta viven en mail_config.php, que no se sube a git.
// Ninguna función de este archivo lanza errores: si un correo falla se anota en el log de PHP y el
// pedido, el pago o el cambio de estado siguen adelante como si nada.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../helpers.php';
if (file_exists(__DIR__ . '/../mail_config.php')) {
    require_once __DIR__ . '/../mail_config.php';
}

use PHPMailer\PHPMailer\PHPMailer;

// Envía un correo HTML. Devuelve true si el servidor de correo lo aceptó.
function correo_enviar($para, $nombre_para, $asunto, $html) {
    if (!defined('MAIL_HABILITADO') || !MAIL_HABILITADO) {
        return false; // Sin mail_config.php, o con los correos apagados
    }
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        error_log("Correo no enviado ('$asunto'): dirección inválida '$para'");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PUERTO;
        $mail->SMTPSecure = MAIL_SEGURIDAD === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USUARIO;
        $mail->Password   = MAIL_PASSWORD;
        $mail->Timeout    = 15; // Que un servidor de correo lento no deje esperando al cliente
        $mail->CharSet    = PHPMailer::CHARSET_UTF8;
        // El PHP de AppServ no trae lista de certificados; se usa la misma que para PayPal.
        $mail->SMTPOptions = ['ssl' => ['cafile' => __DIR__ . '/../pagos/cacert.pem']];

        $mail->setFrom(MAIL_REMITENTE, MAIL_NOMBRE);
        $mail->addAddress($para, $nombre_para);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $html;
        $mail->AltBody = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/tr|\/h\d)>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log("Correo no enviado a $para ('$asunto'): " . $mail->ErrorInfo . ' ' . $e->getMessage());
        return false;
    }
}

// Dirección de la tienda (http://equipo/fragansys) para los enlaces del correo; vacía si no se sabe.
function correo_url_base() {
    if (empty($_SERVER['HTTP_HOST']) || empty($_SERVER['DOCUMENT_ROOT'])) {
        return '';
    }
    $raiz   = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
    $carpeta = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $ruta   = stripos($carpeta, $raiz) === 0 ? substr($carpeta, strlen($raiz)) : '';
    $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $ruta;
}

function correo_dinero($valor) {
    return '$' . number_format((float)$valor, 2);
}

function correo_e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

// Pedido con sus productos, o null si no existe.
function correo_datos_pedido($conn, $pedido_id) {
    $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->bind_param("i", $pedido_id);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    if (!$pedido) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT pd.cantidad, pd.precio_unitario, pd.subtotal,
                IF(pd.tipo = 'combo', CONCAT('Combo ', c.nombre),
                   CONCAT(pf.nombre, ' · ', IF(pr.tipo = 'frasco', 'Frasco ', 'Decant '),
                          TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.ml)), ' ml')) AS nombre
         FROM pedido_detalle pd
         LEFT JOIN presentaciones pr ON pr.id = pd.presentacion_id
         LEFT JOIN perfumes pf ON pf.id = pr.perfume_id
         LEFT JOIN combos c ON c.id = pd.combo_id
         WHERE pd.pedido_id = ? ORDER BY pd.id");
    $stmt->bind_param("i", $pedido_id);
    $stmt->execute();
    $pedido['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return $pedido;
}

// Tabla de productos con subtotal, envío y total.
function correo_tabla_productos($pedido) {
    $filas = '';
    foreach ($pedido['items'] as $item) {
        $filas .= '<tr>'
            . '<td style="padding:8px 0;border-bottom:1px solid #eee5da;">' . correo_e($item['nombre']) . '</td>'
            . '<td style="padding:8px 0;border-bottom:1px solid #eee5da;text-align:center;">' . (int)$item['cantidad'] . '</td>'
            . '<td style="padding:8px 0;border-bottom:1px solid #eee5da;text-align:right;">' . correo_dinero($item['subtotal']) . '</td>'
            . '</tr>';
    }
    $envio = (float)$pedido['costo_envio'] > 0 ? correo_dinero($pedido['costo_envio']) : 'Gratis';

    return '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#333;margin:16px 0;">'
        . '<tr style="color:#9a8f84;font-size:12px;text-transform:uppercase;">'
        . '<th align="left" style="padding-bottom:6px;">Producto</th><th style="padding-bottom:6px;">Cant.</th><th align="right" style="padding-bottom:6px;">Importe</th></tr>'
        . $filas
        . '<tr><td colspan="2" style="padding-top:10px;color:#777;">Subtotal</td><td align="right" style="padding-top:10px;">' . correo_dinero($pedido['subtotal']) . '</td></tr>'
        . '<tr><td colspan="2" style="color:#777;">Envío</td><td align="right">' . $envio . '</td></tr>'
        . '<tr><td colspan="2" style="padding-top:6px;font-weight:bold;color:#4a3320;font-size:16px;">Total</td>'
        . '<td align="right" style="padding-top:6px;font-weight:bold;color:#4a3320;font-size:16px;">' . correo_dinero($pedido['total']) . '</td></tr>'
        . '</table>';
}

// Marco común de todos los correos, con los colores de la tienda.
function correo_plantilla($negocio, $titulo, $contenido, $boton_texto = '', $boton_url = '') {
    $boton = ($boton_texto && $boton_url)
        ? '<p style="text-align:center;margin:28px 0 8px;"><a href="' . correo_e($boton_url) . '" '
          . 'style="background:#4a3320;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:30px;font-size:14px;display:inline-block;">'
          . correo_e($boton_texto) . '</a></p>'
        : '';

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:0;background:#f5f0ea;font-family:Helvetica,Arial,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0ea;padding:24px 12px;"><tr><td align="center">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:#111111;padding:20px;text-align:center;">'
        . '<span style="font-family:Georgia,serif;font-size:24px;color:#b58c5a;">' . correo_e($negocio) . '</span></td></tr>'
        . '<tr><td style="padding:28px 28px 20px;color:#333;font-size:15px;line-height:1.5;">'
        . '<h1 style="font-family:Georgia,serif;color:#4a3320;font-size:22px;margin:0 0 16px;">' . correo_e($titulo) . '</h1>'
        . $contenido . $boton
        . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#fbf8f4;color:#9a8f84;font-size:12px;text-align:center;">'
        . 'Este correo se envió automáticamente desde ' . correo_e($negocio) . '.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

// Al cliente: su pago quedó confirmado.
function correo_pedido_pagado($conn, $pedido_id) {
    try {
        $pedido = correo_datos_pedido($conn, $pedido_id);
        if (!$pedido) {
            return false;
        }
        $negocio = obtener_config($conn)['negocio_nombre'];
        $base    = correo_url_base();

        $contenido = '<p>Hola ' . correo_e($pedido['cliente_nombre']) . ',</p>'
            . '<p>Recibimos el pago de tu <strong>pedido #' . (int)$pedido['id'] . '</strong>. Ya lo estamos preparando y te avisaremos cuando vaya en camino.</p>'
            . correo_tabla_productos($pedido)
            . '<p style="font-size:14px;color:#555;"><strong>Se entregará en:</strong><br>' . correo_e($pedido['direccion_entrega']) . '</p>';

        $html = correo_plantilla($negocio, '¡Recibimos tu pago!', $contenido,
                                 'Ver mi pedido', $base ? $base . '/catalogo-publico/sesion-cliente/indexP.php' : '');
        return correo_enviar($pedido['cliente_correo'], $pedido['cliente_nombre'],
                             "Pago confirmado - Pedido #{$pedido['id']}", $html);
    } catch (Throwable $e) {
        error_log("Correo de pago del pedido $pedido_id: " . $e->getMessage());
        return false;
    }
}

// Al cliente: su pedido ya salió.
function correo_pedido_enviado($conn, $pedido_id) {
    try {
        $pedido = correo_datos_pedido($conn, $pedido_id);
        if (!$pedido) {
            return false;
        }
        $negocio = obtener_config($conn)['negocio_nombre'];
        $base    = correo_url_base();

        $contenido = '<p>Hola ' . correo_e($pedido['cliente_nombre']) . ',</p>'
            . '<p>¡Buenas noticias! Tu <strong>pedido #' . (int)$pedido['id'] . '</strong> ya fue enviado y va en camino a:</p>'
            . '<p style="background:#fbf8f4;border-left:3px solid #b58c5a;padding:10px 14px;font-size:14px;">' . correo_e($pedido['direccion_entrega']) . '</p>'
            . correo_tabla_productos($pedido)
            . '<p style="font-size:14px;color:#555;">Puedes seguir el estado de tu pedido desde tu cuenta.</p>';

        $html = correo_plantilla($negocio, 'Tu pedido va en camino', $contenido,
                                 'Seguir mi pedido', $base ? $base . '/catalogo-publico/sesion-cliente/indexP.php' : '');
        return correo_enviar($pedido['cliente_correo'], $pedido['cliente_nombre'],
                             "Tu pedido #{$pedido['id']} fue enviado", $html);
    } catch (Throwable $e) {
        error_log("Correo de envío del pedido $pedido_id: " . $e->getMessage());
        return false;
    }
}

// Al cliente: su pedido ya se entregó.
function correo_pedido_entregado($conn, $pedido_id) {
    try {
        $pedido = correo_datos_pedido($conn, $pedido_id);
        if (!$pedido) {
            return false;
        }
        $negocio = obtener_config($conn)['negocio_nombre'];
        $base    = correo_url_base();

        $contenido = '<p>Hola ' . correo_e($pedido['cliente_nombre']) . ',</p>'
            . '<p>¡Su <strong>pedido #' . (int)$pedido['id'] . '</strong> ha sido entregado en su domicilio con éxito!</p>'
            . '<p style="background:#fbf8f4;border-left:3px solid #b58c5a;padding:10px 14px;font-size:14px;">'
            . '<strong>Entregado en:</strong><br>' . correo_e($pedido['direccion_entrega']) . '</p>'
            . '<p style="font-size:14px;color:#9a8f84;text-transform:uppercase;letter-spacing:1px;margin:20px 0 0;">Detalles del pedido</p>'
            . correo_tabla_productos($pedido)
            . '<p style="font-size:14px;color:#555;">Gracias por su compra. Esperamos que disfrute sus fragancias.</p>';

        $html = correo_plantilla($negocio, '¡Pedido entregado!', $contenido,
                                 'Ver mis pedidos', $base ? $base . '/catalogo-publico/sesion-cliente/indexP.php' : '');
        return correo_enviar($pedido['cliente_correo'], $pedido['cliente_nombre'],
                             "Tu pedido #{$pedido['id']} fue entregado", $html);
    } catch (Throwable $e) {
        error_log("Correo de entrega del pedido $pedido_id: " . $e->getMessage());
        return false;
    }
}

// Al administrador: un pedido del catálogo ya quedó pagado y hay que prepararlo.
function correo_admin_nuevo_pedido($conn, $pedido_id) {
    try {
        if (!defined('MAIL_ADMIN')) {
            return false;
        }
        $pedido = correo_datos_pedido($conn, $pedido_id);
        if (!$pedido) {
            return false;
        }
        $negocio = obtener_config($conn)['negocio_nombre'];
        $base    = correo_url_base();
        $notas   = $pedido['notas'] !== null && $pedido['notas'] !== ''
            ? '<br><strong>Notas:</strong> ' . correo_e($pedido['notas']) : '';

        $contenido = '<p>El <strong>pedido #' . (int)$pedido['id'] . '</strong> del catálogo en línea ya fue pagado con PayPal y está listo para prepararse.</p>'
            . '<p style="font-size:14px;background:#fbf8f4;padding:12px 14px;border-radius:8px;">'
            . '<strong>Cliente:</strong> ' . correo_e($pedido['cliente_nombre']) . '<br>'
            . '<strong>Correo:</strong> ' . correo_e($pedido['cliente_correo']) . '<br>'
            . '<strong>Teléfono:</strong> ' . correo_e($pedido['telefono_contacto']) . '<br>'
            . '<strong>Entrega:</strong> ' . correo_e($pedido['direccion_entrega'])
            . $notas . '</p>'
            . correo_tabla_productos($pedido)
            . '<p style="font-size:14px;color:#555;">El inventario de este pedido ya se descontó.</p>';

        $html = correo_plantilla($negocio, 'Nuevo pedido pagado #' . (int)$pedido['id'], $contenido,
                                 'Abrir el panel', $base ? $base . '/login.php' : '');
        return correo_enviar(MAIL_ADMIN, $negocio,
                             "Nuevo pedido pagado #{$pedido['id']} - " . correo_dinero($pedido['total']), $html);
    } catch (Throwable $e) {
        error_log("Correo al administrador del pedido $pedido_id: " . $e->getMessage());
        return false;
    }
}
