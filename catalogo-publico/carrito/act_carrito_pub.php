<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function buscarClientePorCorreo($conn, $correo) {
    $stmt = $conn->prepare("SELECT id, nombre, password, telefono, direccion, activo FROM clientes WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function buscarClientePorId($conn, $id) {
    $stmt = $conn->prepare("SELECT id, nombre, correo, password, telefono, direccion, activo FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$respuesta = array('status' => 'error', 'message' => 'Acción no válida');
$accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = array();
}

if ($accion === 'obtener_conteo') {
    $totalItems = 0;
    if (isset($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
        foreach ($_SESSION['carrito'] as $prod) {
            $totalItems += (int)$prod['cantidad'];
        }
    }
    echo json_encode(array(
        'status' => 'success',
        'total_items' => $totalItems
    ));
    exit;
}

if ($accion === 'actualizar') {
    $productoId = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;

    if (isset($_SESSION['carrito'][$productoId])) {
        if ($cantidad > $_SESSION['carrito'][$productoId]['stock']) {
            $respuesta['message'] = 'Stock máximo disponible: ' . $_SESSION['carrito'][$productoId]['stock'];
        } elseif ($cantidad <= 0) {
            unset($_SESSION['carrito'][$productoId]);
            $respuesta['status'] = 'success';
            $respuesta['message'] = 'Producto eliminado del carrito.';
        } else {
            $_SESSION['carrito'][$productoId]['cantidad'] = $cantidad;
            $_SESSION['carrito'][$productoId]['subtotal'] = $cantidad * $_SESSION['carrito'][$productoId]['precio'];
            $respuesta['status'] = 'success';
            $respuesta['message'] = 'Cantidad actualizada.';
        }
    }
} elseif ($accion === 'eliminar') {
    $productoId = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    if (isset($_SESSION['carrito'][$productoId])) {
        unset($_SESSION['carrito'][$productoId]);
        $respuesta['status'] = 'success';
        $respuesta['message'] = 'Producto eliminado.';
    }
} elseif ($accion === 'vaciar') {
    $_SESSION['carrito'] = array();
    $respuesta['status'] = 'success';
    $respuesta['message'] = 'Carrito vaciado.';
} elseif ($accion === 'buscar_cliente') {
    // Paso 1 del checkout: saber si el correo ya tiene datos guardados en clientes
    require_once __DIR__ . '/../../conexion.php';

    $correo = isset($_POST['cliente_correo']) ? trim($_POST['cliente_correo']) : '';
    unset($_SESSION['checkout_cliente_id']); // Un correo nuevo invalida la verificación anterior

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
        $respuesta['message'] = 'El correo electrónico no es válido.';
    } else {
        $cliente = buscarClientePorCorreo($conn, $correo);

        if ($cliente && !$cliente['activo']) {
            $respuesta['message'] = 'Esta cuenta está desactivada. Contáctanos para más información.';
        } else {
            // Sin contraseña no se revela ningún dato del cliente
            $respuesta['status']  = 'success';
            $respuesta['message'] = '';
            $respuesta['existe']  = (bool)$cliente;
            // Clientes registrados antes de que se pidiera contraseña: deben crear una
            $respuesta['sin_password'] = $cliente && $cliente['password'] === '';
        }
    }
} elseif ($accion === 'verificar_cliente') {
    // Paso 2 (cliente registrado): validar su contraseña y devolver sus datos guardados
    require_once __DIR__ . '/../../conexion.php';

    $correo   = isset($_POST['cliente_correo']) ? trim($_POST['cliente_correo']) : '';
    $password  = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmar = isset($_POST['password_confirmar']) ? $_POST['password_confirmar'] : '';
    $cliente   = filter_var($correo, FILTER_VALIDATE_EMAIL) ? buscarClientePorCorreo($conn, $correo) : null;
    $crearPassword = $cliente && $cliente['password'] === '';

    if (!$cliente || !$cliente['activo']) {
        $respuesta['message'] = 'Contraseña incorrecta.';
    } elseif ($crearPassword && mb_strlen($password) < 6) {
        $respuesta['message'] = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($crearPassword && $password !== $confirmar) {
        $respuesta['message'] = 'Las contraseñas no coinciden.';
    } elseif (!$crearPassword && !password_verify($password, $cliente['password'])) {
        $respuesta['message'] = 'Contraseña incorrecta.';
    } else {
        if ($crearPassword) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmtPass = $conn->prepare("UPDATE clientes SET password=? WHERE id=?");
            $stmtPass->bind_param("si", $passwordHash, $cliente['id']);
            $stmtPass->execute();
        }

        $_SESSION['checkout_cliente_id'] = (int)$cliente['id'];

        $respuesta['status']          = 'success';
        $respuesta['message']         = '';
        $respuesta['nombre']          = $cliente['nombre'];
        $respuesta['telefono']        = (string)$cliente['telefono'];
        $respuesta['direccion']       = (string)$cliente['direccion'];
        $respuesta['falta_telefono']  = empty($cliente['telefono']);
        $respuesta['falta_direccion'] = empty($cliente['direccion']);
    }
} elseif ($accion === 'realizar_pedido') {
    // Por ahora solo se registra el pedido (estado_pago = 'pendiente'); aún no se redirige a PayPal.
    require_once __DIR__ . '/../../conexion.php';

    $correo = isset($_POST['cliente_correo']) ? trim($_POST['cliente_correo']) : '';
    $notas  = isset($_POST['notas']) ? trim($_POST['notas']) : '';
    $notas  = $notas === '' ? null : $notas;

    // Con sesión de cliente iniciada se usa su cuenta; el correo del formulario se ignora
    $clienteSesionId = isset($_SESSION['cliente_id']) ? (int)$_SESSION['cliente_id'] : 0;
    if ($clienteSesionId) {
        $cliente = buscarClientePorId($conn, $clienteSesionId);
        $correo  = $cliente ? $cliente['correo'] : '';
    } else {
        $cliente = filter_var($correo, FILTER_VALIDATE_EMAIL) ? buscarClientePorCorreo($conn, $correo) : null;
    }

    // Cliente registrado: se usan sus datos guardados y solo se completan los que le falten.
    // Cliente nuevo: se toman todos los datos del formulario y se registra en clientes.
    $postNombre    = isset($_POST['cliente_nombre']) ? trim($_POST['cliente_nombre']) : '';
    $postTelefono  = isset($_POST['telefono_contacto']) ? trim($_POST['telefono_contacto']) : '';
    $postDireccion = isset($_POST['direccion_entrega']) ? trim($_POST['direccion_entrega']) : '';
    $postPassword  = isset($_POST['password']) ? $_POST['password'] : '';
    $postConfirmar = isset($_POST['password_confirmar']) ? $_POST['password_confirmar'] : '';

    // Un cliente registrado solo puede comprar si inició sesión o verificó su contraseña en este checkout
    $clienteVerificado = $cliente && (
                             $clienteSesionId === (int)$cliente['id']
                             || (isset($_SESSION['checkout_cliente_id'])
                                 && $_SESSION['checkout_cliente_id'] === (int)$cliente['id'])
                         );

    if ($cliente) {
        $nombre    = $cliente['nombre'];
        $telefono  = !empty($cliente['telefono']) ? $cliente['telefono'] : $postTelefono;
        $direccion = !empty($cliente['direccion']) ? $cliente['direccion'] : $postDireccion;
        $actualizarCliente = empty($cliente['telefono']) || empty($cliente['direccion']);
    } else {
        $nombre    = $postNombre;
        $telefono  = $postTelefono;
        $direccion = $postDireccion;
        $actualizarCliente = false;
    }

    if (empty($_SESSION['carrito'])) {
        $respuesta['message'] = 'Tu carrito está vacío.';
    } elseif ($clienteSesionId && !$cliente) {
        $respuesta['message'] = 'Tu sesión ya no es válida. Inicia sesión de nuevo.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $respuesta['message'] = 'El correo electrónico no es válido.';
    } elseif ($cliente && !$cliente['activo']) {
        $respuesta['message'] = 'Esta cuenta está desactivada. Contáctanos para más información.';
    } elseif ($cliente && !$clienteVerificado) {
        $respuesta['message'] = 'Ingresa tu contraseña para continuar con el pedido.';
    } elseif (!$cliente && mb_strlen($postPassword) < 6) {
        $respuesta['message'] = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (!$cliente && $postPassword !== $postConfirmar) {
        $respuesta['message'] = 'Las contraseñas no coinciden.';
    } elseif ($nombre === '' || $telefono === '' || $direccion === '') {
        $respuesta['message'] = 'Completa nombre, teléfono y dirección de entrega.';
    } elseif (mb_strlen($nombre) > 120 || mb_strlen($correo) > 150 || mb_strlen($telefono) > 20
              || mb_strlen($direccion) > 255 || ($notas !== null && mb_strlen($notas) > 255)) {
        $respuesta['message'] = 'Alguno de los datos excede la longitud permitida.';
    } else {
        // Releer precio y stock desde la BD para no confiar en los valores guardados en sesión
        $stmtPres = $conn->prepare(
            "SELECT pr.precio, pf.nombre,
                    IF(pr.tipo = 'frasco', pf.frascos_sellados, FLOOR(pf.ml_disponibles / pr.ml)) AS stock
             FROM presentaciones pr
             INNER JOIN perfumes pf ON pr.perfume_id = pf.id
             WHERE pr.id = ? AND pr.activo = 1 AND pf.activo = 1"
        );

        $lineas   = array();
        $subtotal = 0;
        $error    = '';

        foreach ($_SESSION['carrito'] as $productoId => $prod) {
            $productoId = (int)$productoId;
            $cantidad   = (int)$prod['cantidad'];

            $stmtPres->bind_param("i", $productoId);
            $stmtPres->execute();
            $fila = $stmtPres->get_result()->fetch_assoc();

            if (!$fila) {
                $error = 'El producto "' . $prod['nombre'] . '" ya no está disponible.';
                break;
            }
            if ($cantidad > (int)$fila['stock']) {
                $error = 'Stock insuficiente para "' . $fila['nombre'] . '". Disponibles: ' . (int)$fila['stock'];
                break;
            }

            $precio   = (float)$fila['precio'];
            $lineas[] = array('presentacion_id' => $productoId, 'cantidad' => $cantidad,
                              'precio' => $precio, 'subtotal' => $precio * $cantidad);
            $subtotal += $precio * $cantidad;
        }

        if ($error !== '') {
            $respuesta['message'] = $error;
        } else {
            $costoEnvio = 0.00; // Pendiente de definir la tarifa de envío
            $total      = $subtotal + $costoEnvio;

            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Errores de BD como excepciones (PHP 7)
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
                    $stmtCli = $conn->prepare(
                        "INSERT INTO clientes (nombre, correo, password, telefono, direccion) VALUES (?,?,?,?,?)"
                    );
                    $stmtCli->bind_param("sssss", $nombre, $correo, $passwordHash, $telefono, $direccion);
                    $stmtCli->execute();
                    $clienteId = $conn->insert_id;
                }

                $stmt = $conn->prepare(
                    "INSERT INTO pedidos (cliente_id, cliente_nombre, cliente_correo, subtotal, costo_envio, total,
                                          direccion_entrega, telefono_contacto, notas)
                     VALUES (?,?,?,?,?,?,?,?,?)"
                );
                $stmt->bind_param("issdddsss", $clienteId, $nombre, $correo, $subtotal, $costoEnvio, $total,
                                  $direccion, $telefono, $notas);
                $stmt->execute();
                $pedidoId = $conn->insert_id;

                $stmtDet = $conn->prepare(
                    "INSERT INTO pedido_detalle (pedido_id, tipo, presentacion_id, cantidad, precio_unitario, subtotal)
                     VALUES (?, 'presentacion', ?, ?, ?, ?)"
                );
                foreach ($lineas as $l) {
                    $stmtDet->bind_param("iiidd", $pedidoId, $l['presentacion_id'], $l['cantidad'],
                                         $l['precio'], $l['subtotal']);
                    $stmtDet->execute();
                }

                $conn->commit();

                $_SESSION['carrito'] = array();
                unset($_SESSION['checkout_cliente_id']);
                $respuesta['status']    = 'success';
                $respuesta['message']   = 'Tu pedido #' . $pedidoId . ' fue registrado. Te contactaremos para confirmar el pago.';
                $respuesta['pedido_id'] = $pedidoId;
            } catch (Exception $e) {
                $conn->rollback();
                $respuesta['message'] = 'No se pudo registrar el pedido. Intenta de nuevo.';
            }
        }
    }
}

// Recalcular métricas
$subtotalTotal = 0;
$totalItems = 0;
foreach ($_SESSION['carrito'] as $prod) {
    $subtotalTotal += $prod['cantidad'] * $prod['precio'];
    $totalItems += $prod['cantidad'];
}

$respuesta['subtotal'] = number_format($subtotalTotal, 2);
$respuesta['total_items'] = $totalItems;

echo json_encode($respuesta);
exit;