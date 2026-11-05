<?php
// Incluir el archivo de conexión del sistema
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../helpers.php';

/**
 * Secciones del menú superior del catálogo.
 *   generos      -> géneros que se muestran (null = todos)
 *   presentacion -> tipo de presentación que se ofrece (null = decants y frascos)
 */
$SECCIONES_CATALOGO = array(
    'inicio'   => array('menu' => 'Destacados',        'titulo' => 'Selección Destacada',  'subtitulo' => 'Los favoritos de la casa',
                        'generos' => null, 'presentacion' => null),
    'completo' => array('menu' => 'Catálogo Completo', 'titulo' => 'Catálogo Completo',    'subtitulo' => 'Todas nuestras fragancias disponibles',
                        'generos' => null, 'presentacion' => null),
    'decants'  => array('menu' => 'Decants',           'titulo' => 'Decants',              'subtitulo' => 'Prueba tu fragancia en tamaño muestra',
                        'generos' => null, 'presentacion' => 'decant'),
    'perfumes' => array('menu' => 'Perfumes',          'titulo' => 'Perfumes',             'subtitulo' => 'Frascos completos y sellados',
                        'generos' => null, 'presentacion' => 'frasco'),
    'combos'   => array('menu' => 'Combos',            'titulo' => 'Combos',               'subtitulo' => 'Paquetes especiales a un mejor precio'),
    'mujer'    => array('menu' => '• Mujer',           'titulo' => 'Fragancias para Mujer', 'subtitulo' => 'Aromas femeninos y unisex',
                        'generos' => array('mujer', 'unisex'), 'presentacion' => null),
    'hombre'   => array('menu' => '• Hombre',          'titulo' => 'Fragancias para Hombre', 'subtitulo' => 'Aromas masculinos y unisex',
                        'generos' => array('hombre', 'unisex'), 'presentacion' => null)
);

// En "Destacados" (inicio) solo se muestran los más vendidos
define('LIMITE_DESTACADOS', 8);

/**
 * Función para obtener perfumes y variantes sin depender de sesiones.
 * $generos: array de géneros a incluir (null = todos)
 * $tipoPresentacion: 'decant', 'frasco' o null para ambas
 * $soloDestacados: ordena por unidades vendidas y limita a LIMITE_DESTACADOS
 */
function obtenerCatalogoPublico($db, $generos = null, $tipoPresentacion = null, $soloDestacados = false) {$catalogo = array();

    // Validar que la conexión exista y sea un objeto válido
    if (!$db || !is_object($db) || $db->connect_error) {
        return $catalogo;
    }

    // 1. Consultar perfumes activos en la tabla `perfumes`
    //    "vendidos" suma lo vendido en mostrador y lo pedido en línea (sin cancelados) para ordenar los destacados
    $sqlCatalogo = "SELECT pf.id, pf.nombre, pf.marca, pf.genero, pf.imagen, pf.frascos_sellados, pf.ml_disponibles,
                           (SELECT COALESCE(SUM(vd.cantidad), 0)
                              FROM venta_detalle vd
                              INNER JOIN ventas v ON vd.venta_id = v.id AND v.estado = 'completada'
                              INNER JOIN presentaciones p1 ON vd.presentacion_id = p1.id
                             WHERE p1.perfume_id = pf.id)
                         + (SELECT COALESCE(SUM(pd.cantidad), 0)
                              FROM pedido_detalle pd
                              INNER JOIN pedidos pe ON pd.pedido_id = pe.id AND pe.estado_pedido <> 'cancelado'
                              INNER JOIN presentaciones p2 ON pd.presentacion_id = p2.id
                             WHERE p2.perfume_id = pf.id) AS vendidos
                    FROM perfumes pf
                    WHERE pf.activo = 1
                    ORDER BY " . ($soloDestacados ? "vendidos DESC, " : "") . "pf.nombre ASC";

    $resultadoCatalogo = $db->query($sqlCatalogo);

    if ($resultadoCatalogo &&$resultadoCatalogo->num_rows > 0) {
        while ($perfume =$resultadoCatalogo->fetch_assoc()) {
            if ($generos !== null && !in_array($perfume['genero'], $generos, true)) {
                continue;
            }

            $catalogoId = (int)$perfume['id'];

            // En la BD se guarda "uploads/perfumes/archivo", relativo a la raíz del proyecto
            $perfume['imagen_url'] = '../' . $perfume['imagen'];

            // 2. Consultar las presentaciones (decants y frasco) del perfume
            $sqlProductos = "SELECT id, tipo, ml, precio
                             FROM presentaciones
                             WHERE perfume_id = ? AND activo = 1
                             ORDER BY tipo = 'frasco', ml ASC";

            $stmt = $db->prepare($sqlProductos);
            if ($stmt) {$stmt->bind_param("i", $catalogoId);$stmt->execute();
                $resProductos =$stmt->get_result();

                $variantes = array();
                while ($prod =$resProductos->fetch_assoc()) {
                    if ($tipoPresentacion !== null && $prod['tipo'] !== $tipoPresentacion) {
                        continue;
                    }
                    // Un decant sale de los ml del frasco abierto; un frasco, de los sellados
                    $hayStock = $prod['tipo'] === 'frasco'
                        ? $perfume['frascos_sellados'] >= 1
                        : $perfume['ml_disponibles'] >= $prod['ml'];
                    if (!$hayStock) {
                        continue;
                    }
                    $prod['contenido_ml'] = (float)$prod['ml'];
                    $prod['etiqueta'] = ($prod['tipo'] === 'frasco' ? 'Frasco completo ' : '') . $prod['contenido_ml'] . ' ml';
                    $variantes[] =$prod;
                }
                $stmt->close();

                $perfume['variantes'] =$variantes;

                // Solo incluir si tiene al menos una variante con stock
                if (count($variantes) > 0) {
                    $catalogo[] =$perfume;
                }
            }

            if ($soloDestacados && count($catalogo) >= LIMITE_DESTACADOS) {
                break;
            }
        }
    }

    return $catalogo;
}

/**
 * Combos activos con las presentaciones que incluyen.
 * "disponible" indica si alcanza el stock para armar al menos un combo completo.
 */
function obtenerCombosPublicos($db) {
    $combos = array();

    if (!$db || !is_object($db) || $db->connect_error) {
        return $combos;
    }

    $resCombos = $db->query("SELECT id, nombre, descripcion, precio, imagen FROM combos WHERE activo = 1 ORDER BY nombre ASC");
    if (!$resCombos) {
        return $combos;
    }

    $stmt = $db->prepare(
        "SELECT cp.cantidad, pr.tipo, pr.ml, pf.nombre, pf.marca
         FROM combo_presentaciones cp
         INNER JOIN presentaciones pr ON cp.presentacion_id = pr.id
         INNER JOIN perfumes pf ON pr.perfume_id = pf.id
         WHERE cp.combo_id = ?
         ORDER BY pf.nombre"
    );

    while ($combo = $resCombos->fetch_assoc()) {
        $comboId = (int)$combo['id'];
        $stmt->bind_param("i", $comboId);
        $stmt->execute();
        $resItems = $stmt->get_result();

        $combo['items'] = array();
        while ($item = $resItems->fetch_assoc()) {
            $ml = (float)$item['ml'];
            $combo['items'][] = array(
                'cantidad' => (int)$item['cantidad'],
                'etiqueta' => $item['nombre'] . ' — ' . ($item['tipo'] === 'frasco' ? 'Frasco ' : 'Decant ') . $ml . ' ml',
                'marca'    => $item['marca']
            );
        }

        // Un combo sin presentaciones no se muestra
        if (empty($combo['items'])) {
            continue;
        }

        // El stock se calcula por perfume: dos decants del mismo perfume salen del mismo frasco abierto
        $venta = obtener_combo_venta($db, $comboId);
        $combo['disponible'] = $venta !== null && $venta['stock'] >= 1;

        $combo['imagen_url'] = $combo['imagen'] ? '../' . $combo['imagen'] : '';
        $combos[] = $combo;
    }
    $stmt->close();

    return $combos;
}

// Detectar automáticamente el nombre de la variable de conexión
$dbConnection = null;
if (isset($conexion) && is_object($conexion)) {
    $dbConnection =$conexion;
} elseif (isset($conn) && is_object($conn)) {
    $dbConnection =$conn;
} elseif (isset($link) && is_object($link)) {
    $dbConnection =$link;
}

// Sección elegida en el menú superior (?seccion=...); por defecto el inicio del catálogo
$seccionActual = isset($_GET['seccion']) && isset($SECCIONES_CATALOGO[$_GET['seccion']]) ? $_GET['seccion'] : 'inicio';
$datosSeccion  = $SECCIONES_CATALOGO[$seccionActual];

// Ejecutar la consulta con la conexión encontrada
$listaPerfumes = array();
$listaCombos   = array();
if ($seccionActual === 'combos') {
    $listaCombos = obtenerCombosPublicos($dbConnection);
} else {
    $listaPerfumes = obtenerCatalogoPublico($dbConnection, $datosSeccion['generos'], $datosSeccion['presentacion'],
                                            $seccionActual === 'inicio');
}
