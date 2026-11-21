<?php
/**
 * API del carrito y del checkout para la app Android. Misma lógica que catalogo-publico/carrito/act_carrito_pub.php,
 * pero el carrito llega desde el teléfono en el campo "items" (JSON):
 *     [{"tipo": "presentacion", "id": 12, "cantidad": 2}, {"tipo": "combo", "id": 3, "cantidad": 1}]
 *
 *   accion=resumen            items                   -> precios y stock actuales, envío y total
 *   accion=buscar_cliente     cliente_correo          -> ¿el correo ya está registrado?
 *   accion=verificar_cliente  cliente_correo, password[, password_confirmar] -> inicia sesión y devuelve sus datos
 *   accion=realizar_pedido    items, datos de entrega [, token] -> registra el pedido (pago pendiente)
 */
require_once __DIR__ . '/../../conexion_api.php';

function buscarClientePorCorreo($conn, $correo) {
    $stmt = $conn->prepare("SELECT id, nombre, correo, password, telefono, direccion, activo FROM clientes WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Productos que incluye un combo (para mostrarlos en el carrito)
function obtenerProductosCombo($conn, $comboId) {
    $stmt = $conn->prepare(
        "SELECT cp.cantidad, pr.tipo, pr.ml, pf.nombre, pf.marca, pf.imagen
         FROM combo_presentaciones cp
         INNER JOIN presentaciones pr ON cp.presentacion_id = pr.id
         INNER JOIN perfumes pf ON pr.perfume_id = pf.id
         WHERE cp.combo_id = ?
         ORDER BY pf.nombre, pr.ml");
    $stmt->bind_param("i", $comboId);
    $stmt->execute();
    $productos = array();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
        $productos[] = array(
            'cantidad' => (int)$p['cantidad'],
            'tipo'     => $p['tipo'],
            'ml'       => api_ml($p['ml']),
            'nombre'   => $p['nombre'],
            'marca'    => $p['marca'],
            'imagen'   => (string)$p['imagen'],
        );
    }
    return $productos;
}

// Lee el carrito enviado por la app. Junta repetidos y descarta lo que no tenga forma válida.
function leerItemsCarrito() {
    $items = json_decode(api_param('items', '[]'), true);
    $carrito = array();
    if (!is_array($items)) return $carrito;

    foreach ($items as $it) {
        $tipo     = isset($it['tipo']) && $it['tipo'] === 'combo' ? 'combo' : 'presentacion';
        $id       = isset($it['id']) ? (int)$it['id'] : 0;
        $cantidad = isset($it['cantidad']) ? (int)$it['cantidad'] : 0;
        if ($id <= 0 || $cantidad <= 0) continue;

        $clave = ($tipo === 'combo' ? 'c' : '') . $id;
        if (isset($carrito[$clave])) {
            $carrito[$clave]['cantidad'] += $cantidad;
        } else {
            $carrito[$clave] = array('clave' => $clave, 'tipo' => $tipo, 'id' => $id, 'cantidad' => $cantidad);
        }
    }
    return $carrito;
}

// Datos actuales (desde la BD) de un producto del carrito; null si ya no está disponible.
function datosActualesItem($conn, $item) {
    if ($item['tipo'] === 'combo') {
        $combo = obtener_combo_venta($conn, $item['id']);
        if (!$combo) return null;
        return array(
            'nombre' => $combo['nombre'], 'marca' => 'Combo', 'presentacion' => '', 'contenido_ml' => '',
            'precio' => (float)$combo['precio'], 'stock' => (int)$combo['stock'],
            'piezas' => (int)$combo['piezas'], 'imagen' => (string)$combo['imagen'],
        );
    }

    $stmt = $conn->prepare(
        "SELECT pr.tipo, pr.ml, pr.precio, pf.nombre, pf.marca, pf.imagen,
                IF(pr.tipo = 'frasco', pf.frascos_sellados, FLOOR(pf.ml_disponibles / pr.ml)) AS stock
         FROM presentaciones pr
         INNER JOIN perfumes pf ON pr.perfume_id = pf.id
         WHERE pr.id = ? AND pr.activo = 1 AND pf.activo = 1");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    if (!$fila) return null;
    return array(
        'nombre' => $fila['nombre'], 'marca' => $fila['marca'], 'presentacion' => $fila['tipo'],
        'contenido_ml' => api_ml($fila['ml']), 'precio' => (float)$fila['precio'], 'stock' => (int)$fila['stock'],
        'piezas' => 0, 'imagen' => (string)$fila['imagen'],
    );
}

// Envío y lo que falta para el envío gratis, igual que back_carrito_pub.php
function totalesCarrito($conn, $subtotal) {
    $config = obtener_config($conn);
    $envio  = calcular_envio($config, $subtotal);
    $gratisDesde = $config['envio_gratis_desde'] !== '' ? (float)$config['envio_gratis_desde'] : 0;
    return array(
        'subtotal'           => round($subtotal, 2),
        'envio'              => $envio,
        'total'              => round($subtotal + $envio, 2),
        'falta_envio_gratis' => ($gratisDesde > 0 && $envio > 0) ? round($gratisDesde - $subtotal, 2) : 0,
    );
}

$accion = api_param('accion');

if ($accion === 'resumen') {
    // Se actualizan precios y stock con la BD. Si algo se agotó se quita, y si ya no alcanza se ajusta la cantidad.
    $lineas    = array();
    $avisos    = array();
    $subtotal  = 0;
    $totalItems = 0;

    foreach (leerItemsCarrito() as $item) {
        $datos = datosActualesItem($conn, $item);
        if (!$datos || $datos['stock'] <= 0) {
            $avisos[] = 'Se quitó "' . ($datos ? $datos['nombre'] : 'un producto') . '" porque ya no está disponible.';
            continue;
        }
        $cantidad = $item['cantidad'];
        if ($cantidad > $datos['stock']) {
            $cantidad = $datos['stock'];
            $avisos[] = 'Solo quedan ' . $datos['stock'] . ' de "' . $datos['nombre'] . '"; ajustamos la cantidad.';
        }

        $linea = array('clave' => $item['clave'], 'tipo' => $item['tipo'], 'id' => $item['id'], 'cantidad' => $cantidad)
               + $datos
               + array('subtotal' => round($cantidad * $datos['precio'], 2),
                       'productos' => $item['tipo'] === 'combo' ? obtenerProductosCombo($conn, $item['id']) : array());
        $lineas[]    = $linea;
        $subtotal   += $linea['subtotal'];
        $totalItems += $cantidad;
    }

    api_responder(array('status' => 'success', 'items' => $lineas, 'avisos' => $avisos, 'total_items' => $totalItems)
                  + totalesCarrito($conn, $subtotal));
}

if ($accion === 'buscar_cliente') {
    // Paso 1 del checkout: saber si el correo ya tiene datos guardados en clientes
    $correo = trim(api_param('cliente_correo'));
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
        api_error('El correo electrónico no es válido.');
    }
    $cliente = buscarClientePorCorreo($conn, $correo);
    if ($cliente && !$cliente['activo']) {
        api_error('Esta cuenta está desactivada. Contáctanos para más información.');
    }
    // Sin contraseña no se revela ningún dato del cliente
    api_responder(array(
        'status'       => 'success',
        'existe'       => (bool)$cliente,
        // Clientes registrados antes de que se pidiera contraseña: deben crear una
        'sin_password' => $cliente && $cliente['password'] === '',
    ));
}

if ($accion === 'verificar_cliente') {
    // Paso 2 (cliente registrado): validar su contraseña. En la app esto deja la sesión iniciada.
    $correo    = trim(api_param('cliente_correo'));
    $password  = api_param('password');
    $confirmar = api_param('password_confirmar');
    $cliente   = filter_var($correo, FILTER_VALIDATE_EMAIL) ? buscarClientePorCorreo($conn, $correo) : null;
    $crearPassword = $cliente && $cliente['password'] === '';

    if (!$cliente || !$cliente['activo']) {
        api_error('Contraseña incorrecta.');
    } elseif ($crearPassword && mb_strlen($password) < 6) {
        api_error('La contraseña debe tener al menos 6 caracteres.');
    } elseif ($crearPassword && $password !== $confirmar) {
        api_error('Las contraseñas no coinciden.');
    } elseif (!$crearPassword && !password_verify($password, $cliente['password'])) {
        api_error('Contraseña incorrecta.');
    }

    if ($crearPassword) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmtPass = $conn->prepare("UPDATE clientes SET password=? WHERE id=?");
        $stmtPass->bind_param("si", $passwordHash, $cliente['id']);
        $stmtPass->execute();
    }

    api_responder(array(
        'status'  => 'success',
        'token'   => api_crear_token($cliente['id']),
        'cliente' => api_datos_cliente($cliente),
    ));
}

if ($accion === 'realizar_pedido') {
    // Se registra el pedido con estado_pago = 'pendiente' y la app pasa a la pantalla de pago.
    // El inventario se descuenta hasta que el pago queda confirmado (ver pagos/inventario_pedido.php).
    $carrito = leerItemsCarrito();
    $notas   = trim(api_param('notas'));
    $notas   = $notas === '' ? null : $notas;

    $postNombre    = trim(api_param('cliente_nombre'));
    $postCorreo    = trim(api_param('cliente_correo'));
    $postTelefono  = trim(api_param('telefono_contacto'));
    $postDireccion = trim(api_param('direccion_entrega'));
    $postPassword  = api_param('password');
    $postConfirmar = api_param('password_confirmar');

    // Con sesión iniciada se usa la cuenta del cliente. Sin sesión se registra un cliente nuevo;
    // un correo que ya existe debe verificarse antes con su contraseña (accion=verificar_cliente).
    $cliente = api_cliente_sesion($conn);
    $esNuevo = !$cliente;

    if ($cliente) {
        $correo    = $cliente['correo'];
        $nombre    = $cliente['nombre'];
        $telefono  = !empty($cliente['telefono']) ? $cliente['telefono'] : $postTelefono;
        $direccion = !empty($cliente['direccion']) ? $cliente['direccion'] : $postDireccion;
        $actualizarCliente = empty($cliente['telefono']) || empty($cliente['direccion']);
    } else {
        $correo    = $postCorreo;
        $nombre    = $postNombre;
        $telefono  = $postTelefono;
        $direccion = $postDireccion;
        $actualizarCliente = false;
    }

    if (empty($carrito)) {
        api_error('Tu carrito está vacío.');
    } elseif (api_param('token') !== '' && !$cliente) {
        api_error('Tu sesión ya no es válida. Inicia sesión de nuevo.', array('sin_sesion' => true));
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        api_error('El correo electrónico no es válido.');
    } elseif ($esNuevo && buscarClientePorCorreo($conn, $correo)) {
        api_error('Este correo ya está registrado. Ingresa tu contraseña para continuar con el pedido.');
    } elseif ($esNuevo && mb_strlen($postPassword) < 6) {
        api_error('La contraseña debe tener al menos 6 caracteres.');
    } elseif ($esNuevo && $postPassword !== $postConfirmar) {
        api_error('Las contraseñas no coinciden.');
    } elseif ($nombre === '' || $telefono === '' || $direccion === '') {
        api_error('Completa nombre, teléfono y dirección de entrega.');
    } elseif (mb_strlen($nombre) > 120 || mb_strlen($correo) > 150 || mb_strlen($telefono) > 20
              || mb_strlen($direccion) > 255 || ($notas !== null && mb_strlen($notas) > 255)) {
        api_error('Alguno de los datos excede la longitud permitida.');
    }

    // Releer precio y stock desde la BD para no confiar en los valores que manda el teléfono
    $lineas   = array();
    $subtotal = 0;
    foreach ($carrito as $item) {
        $datos = datosActualesItem($conn, $item);
        if (!$datos) {
            api_error('Un producto de tu carrito ya no está disponible. Revisa tu carrito.');
        }
        if ($item['cantidad'] > $datos['stock']) {
            api_error('Stock insuficiente para "' . $datos['nombre'] . '". Disponibles: ' . $datos['stock']);
        }
        $lineas[] = array('tipo' => $item['tipo'], 'id' => $item['id'], 'cantidad' => $item['cantidad'],
                          'precio' => $datos['precio'], 'subtotal' => $datos['precio'] * $item['cantidad']);
        $subtotal += $datos['precio'] * $item['cantidad'];
    }

    $costoEnvio = calcular_envio(obtener_config($conn), $subtotal);
    $total      = $subtotal + $costoEnvio;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();
    try {
        if ($cliente) {
            $clienteId = (int)$cliente['id'];
            if ($actualizarCliente) {
                $stmtCli = $conn->prepare("UPDATE clientes SET telefono=?, direccion=? WHERE id=?");
                $stmtCli->bind_param("ssi", $telefono, $direccion, $clienteId);
                $stmtCli->execute();
            }
        } else {
            $passwordHash = password_hash($postPassword, PASSWORD_DEFAULT);
            $stmtCli = $conn->prepare("INSERT INTO clientes (nombre, correo, password, telefono, direccion) VALUES (?,?,?,?,?)");
            $stmtCli->bind_param("sssss", $nombre, $correo, $passwordHash, $telefono, $direccion);
            $stmtCli->execute();
            $clienteId = $conn->insert_id;
        }

        $stmt = $conn->prepare(
            "INSERT INTO pedidos (cliente_id, cliente_nombre, cliente_correo, subtotal, costo_envio, total,
                                  direccion_entrega, telefono_contacto, notas)
             VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issdddsss", $clienteId, $nombre, $correo, $subtotal, $costoEnvio, $total,
                          $direccion, $telefono, $notas);
        $stmt->execute();
        $pedidoId = $conn->insert_id;

        $stmtDet = $conn->prepare(
            "INSERT INTO pedido_detalle (pedido_id, tipo, presentacion_id, combo_id, cantidad, precio_unitario, subtotal)
             VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($lineas as $l) {
            $presentacionId = $l['tipo'] === 'presentacion' ? $l['id'] : null;
            $comboId        = $l['tipo'] === 'combo' ? $l['id'] : null;
            $stmtDet->bind_param("isiiidd", $pedidoId, $l['tipo'], $presentacionId, $comboId,
                                 $l['cantidad'], $l['precio'], $l['subtotal']);
            $stmtDet->execute();
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log('API realizar_pedido: ' . $e->getMessage());
        api_error('No se pudo registrar el pedido. Intenta de nuevo.');
    }
    mysqli_report(MYSQLI_REPORT_OFF);

    // Se devuelve la sesión del cliente: si era nuevo, ya queda con la sesión iniciada en la app
    $stmt = $conn->prepare("SELECT id, nombre, correo, telefono, direccion FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $clienteId);
    $stmt->execute();
    $clienteFinal = $stmt->get_result()->fetch_assoc();

    api_responder(array(
        'status'    => 'success',
        'message'   => 'Tu pedido #' . $pedidoId . ' fue registrado.',
        'pedido_id' => (int)$pedidoId,
        'token'     => $esNuevo ? api_crear_token($clienteId) : api_param('token'),
        'cliente'   => api_datos_cliente($clienteFinal),
    ));
}

api_error('Acción no válida');
