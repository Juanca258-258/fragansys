<?php
/**
 * API del catálogo público para la app Android.
 *   accion=catalogo (por defecto) &seccion=inicio|completo|decants|perfumes|combos|mujer|hombre
 *   accion=agregar        producto_id, cantidad, cantidad_en_carrito  -> valida stock y devuelve el producto para el carrito
 *   accion=agregar_combo  combo_id,    cantidad, cantidad_en_carrito  -> igual, para un combo
 *
 * La app guarda el carrito en el teléfono (en la web vive en $_SESSION), por eso manda cuántas
 * unidades ya tiene de ese producto: así se valida el stock igual que act_catalogo_pub.php.
 */
require_once __DIR__ . '/../conexion_api.php';

$accion = api_param('accion', 'catalogo');

if ($accion === 'catalogo') {
    // back_catalogo_pub.php lee $_GET['seccion'] y deja listos $seccionActual, $datosSeccion, $listaPerfumes y $listaCombos
    require_once __DIR__ . '/../../catalogo-publico/back_catalogo_pub.php';

    $secciones = array();
    foreach ($SECCIONES_CATALOGO as $clave => $s) {
        $secciones[] = array(
            'clave'     => $clave,
            'menu'      => trim(str_replace('•', '', $s['menu'])),
            'titulo'    => $s['titulo'],
            'subtitulo' => $s['subtitulo'],
            // En "mujer"/"hombre" ya va filtrado el género, así que la app no muestra ese filtro
            'filtra_genero' => !isset($s['generos']) || $s['generos'] === null,
        );
    }

    $perfumes = array();
    foreach ($listaPerfumes as $p) {
        $variantes = array();
        foreach ($p['variantes'] as $v) {
            $variantes[] = array(
                'id'       => (int)$v['id'],
                'tipo'     => $v['tipo'],
                'ml'       => api_ml($v['ml']),
                'precio'   => (float)$v['precio'],
                'etiqueta' => ($v['tipo'] === 'frasco' ? 'Frasco completo ' : '') . api_ml($v['ml']) . ' ml',
            );
        }
        $perfumes[] = array(
            'id'        => (int)$p['id'],
            'nombre'    => $p['nombre'],
            'marca'     => $p['marca'],
            'genero'    => $p['genero'],
            'imagen'    => (string)$p['imagen'],
            'variantes' => $variantes,
        );
    }

    $combos = array();
    foreach ($listaCombos as $c) {
        $combos[] = array(
            'id'          => (int)$c['id'],
            'nombre'      => $c['nombre'],
            'descripcion' => (string)$c['descripcion'],
            'precio'      => (float)$c['precio'],
            'imagen'      => (string)$c['imagen'],
            'disponible'  => (bool)$c['disponible'],
            'items'       => $c['items'],
        );
    }

    api_responder(array(
        'status'    => 'success',
        'seccion'   => $seccionActual,
        'secciones' => $secciones,
        'perfumes'  => $perfumes,
        'combos'    => $combos,
    ));
}

$cantidad         = (int)api_param('cantidad', '1');
$cantidadCarrito  = max(0, (int)api_param('cantidad_en_carrito', '0'));

if ($accion === 'agregar') {
    $productoId = (int)api_param('producto_id', '0');
    if ($productoId <= 0 || $cantidad <= 0) {
        api_error('Datos de producto o cantidad inválidos.');
    }

    // Misma consulta que act_catalogo_pub.php: "stock" son las unidades que alcanzan
    $stmt = $conn->prepare(
        "SELECT pr.id AS producto_id, pr.tipo, pr.ml, pr.precio,
                IF(pr.tipo = 'frasco', pf.frascos_sellados, FLOOR(pf.ml_disponibles / pr.ml)) AS stock,
                pf.id AS catalogo_id, pf.nombre, pf.marca, pf.imagen
         FROM presentaciones pr
         INNER JOIN perfumes pf ON pr.perfume_id = pf.id
         WHERE pr.id = ? AND pr.activo = 1 AND pf.activo = 1");
    $stmt->bind_param("i", $productoId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();

    if (!$item) {
        api_error('El producto no está disponible.');
    }
    $stock = (int)$item['stock'];
    if ($cantidadCarrito + $cantidad > $stock) {
        api_error($cantidadCarrito > 0
            ? 'No puedes agregar más unidades de las disponibles en stock (' . $stock . ').'
            : 'Stock insuficiente. Disponibles: ' . $stock);
    }

    api_responder(array(
        'status'  => 'success',
        'message' => 'Producto agregado al carrito con éxito.',
        'item'    => array(
            'clave'        => (string)$item['producto_id'],
            'tipo'         => 'presentacion',
            'id'           => (int)$item['producto_id'],
            'nombre'       => $item['nombre'],
            'marca'        => $item['marca'],
            'presentacion' => $item['tipo'],
            'contenido_ml' => api_ml($item['ml']),
            'precio'       => (float)$item['precio'],
            'stock'        => $stock,
            'piezas'       => 0,
            'imagen'       => (string)$item['imagen'],
        ),
    ));
}

if ($accion === 'agregar_combo') {
    $comboId = (int)api_param('combo_id', '0');
    $combo   = $comboId > 0 ? obtener_combo_venta($conn, $comboId) : null;

    if ($cantidad <= 0) {
        api_error('Cantidad inválida.');
    }
    if (!$combo) {
        api_error('El combo no está disponible.');
    }
    if ($cantidadCarrito + $cantidad > $combo['stock']) {
        api_error($combo['stock'] > 0
            ? 'No puedes agregar más combos de los disponibles en stock (' . $combo['stock'] . ').'
            : 'El combo está agotado.');
    }

    api_responder(array(
        'status'  => 'success',
        'message' => 'Combo agregado al carrito con éxito.',
        'item'    => array(
            // En el carrito los combos usan la clave "c<id>" para no chocar con los ids de presentaciones
            'clave'        => 'c' . $comboId,
            'tipo'         => 'combo',
            'id'           => $comboId,
            'nombre'       => $combo['nombre'],
            'marca'        => 'Combo',
            'presentacion' => '',
            'contenido_ml' => '',
            'precio'       => (float)$combo['precio'],
            'stock'        => (int)$combo['stock'],
            'piezas'       => (int)$combo['piezas'],
            'imagen'       => (string)$combo['imagen'],
        ),
    ));
}

api_error('Acción no válida');
