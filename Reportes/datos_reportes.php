<?php
// Cálculo de todos los reportes para un periodo. Lo usan la vista del panel y la versión para imprimir.

function periodo_desde_peticion($req) {
    $hoy = date('Y-m-d');
    $tipo = $req['periodo'] ?? 'mes';
    switch ($tipo) {
        case 'hoy':
            return ['hoy', $hoy, $hoy];
        case 'semana':
            return ['semana', date('Y-m-d', strtotime('monday this week')), $hoy];
        case 'rango':
            $desde = $req['desde'] ?? '';
            $hasta = $req['hasta'] ?? '';
            $valida = function ($f) {
                $d = DateTime::createFromFormat('Y-m-d', $f);
                return $d && $d->format('Y-m-d') === $f;
            };
            if ($valida($desde) && $valida($hasta)) {
                if ($desde > $hasta) {
                    [$desde, $hasta] = [$hasta, $desde];
                }
                return ['rango', $desde, $hasta];
            }
            return ['mes', date('Y-m-01'), $hoy];
        default:
            return ['mes', date('Y-m-01'), $hoy];
    }
}

function nombre_presentacion($nombre, $tipo, $ml) {
    return $nombre . ' — ' . ($tipo === 'frasco' ? 'Frasco ' : 'Decant ') . (float)$ml . ' ml';
}

function obtener_reportes($conn, $desde, $hasta) {
    // $desde y $hasta ya vienen validados como YYYY-MM-DD.
    $ini = "$desde 00:00:00";
    $fin = "$hasta 23:59:59";
    $r = [];

    $ventas_sql = "
        SELECT 'Mostrador' AS origen, v.total, v.metodo_pago, DATE(v.fecha_venta) AS dia, v.usuario_id
        FROM ventas v
        WHERE v.estado = 'completada' AND v.fecha_venta BETWEEN '$ini' AND '$fin'
        UNION ALL
        SELECT 'En línea', p.total, 'paypal', DATE(p.fecha_pedido), NULL
        FROM pedidos p
        WHERE p.estado_pago = 'pagado' AND p.estado_pedido <> 'cancelado' AND p.fecha_pedido BETWEEN '$ini' AND '$fin'";

    $items_sql = "
        SELECT vd.presentacion_id, vd.cantidad, vd.subtotal
        FROM venta_detalle vd INNER JOIN ventas v ON v.id = vd.venta_id
        WHERE vd.tipo = 'presentacion' AND v.estado = 'completada' AND v.fecha_venta BETWEEN '$ini' AND '$fin'
        UNION ALL
        SELECT pd.presentacion_id, pd.cantidad, pd.subtotal
        FROM pedido_detalle pd INNER JOIN pedidos p ON p.id = pd.pedido_id
        WHERE pd.tipo = 'presentacion' AND p.estado_pago = 'pagado' AND p.estado_pedido <> 'cancelado'
              AND p.fecha_pedido BETWEEN '$ini' AND '$fin'";

    // 1. Resumen
    $res = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS num, COALESCE(SUM(total), 0) AS total FROM ($ventas_sql) t"));
    $r['resumen'] = [
        'num'      => (int)$res['num'],
        'total'    => (float)$res['total'],
        'promedio' => $res['num'] > 0 ? $res['total'] / $res['num'] : 0,
    ];
    $r['por_metodo'] = mysqli_fetch_all(mysqli_query($conn,
        "SELECT metodo_pago, COUNT(*) AS num, SUM(total) AS total FROM ($ventas_sql) t GROUP BY metodo_pago ORDER BY total DESC"), MYSQLI_ASSOC);
    $r['por_origen'] = mysqli_fetch_all(mysqli_query($conn,
        "SELECT origen, COUNT(*) AS num, SUM(total) AS total FROM ($ventas_sql) t GROUP BY origen ORDER BY total DESC"), MYSQLI_ASSOC);

    // 2. Ventas por día (incluye los días sin ventas para que la gráfica no se salte fechas)
    $por_dia = [];
    $res = mysqli_query($conn, "SELECT dia, SUM(total) AS total FROM ($ventas_sql) t GROUP BY dia");
    while ($f = mysqli_fetch_assoc($res)) {
        $por_dia[$f['dia']] = (float)$f['total'];
    }
    $r['por_dia'] = [];
    for ($d = strtotime($desde); $d <= strtotime($hasta); $d = strtotime('+1 day', $d)) {
        $dia = date('Y-m-d', $d);
        $r['por_dia'][] = ['dia' => date('d/m', $d), 'total' => $por_dia[$dia] ?? 0];
    }

    // 3. Más vendidos
    $vendidos = mysqli_fetch_all(mysqli_query($conn,
        "SELECT pr.id, pr.tipo, pr.ml, pf.id AS perfume_id, pf.nombre, pf.marca, pf.ml_por_frasco,
                SUM(i.cantidad) AS unidades, SUM(i.subtotal) AS importe, SUM(i.cantidad * pr.ml) AS ml_vendidos
         FROM ($items_sql) i
         INNER JOIN presentaciones pr ON pr.id = i.presentacion_id
         INNER JOIN perfumes pf ON pf.id = pr.perfume_id
         GROUP BY pr.id
         ORDER BY unidades DESC, importe DESC"), MYSQLI_ASSOC);
    $r['top_presentaciones'] = array_slice($vendidos, 0, 10);

    $por_perfume = [];
    foreach ($vendidos as $v) {
        $pid = $v['perfume_id'];
        if (!isset($por_perfume[$pid])) {
            $por_perfume[$pid] = ['nombre' => $v['nombre'], 'marca' => $v['marca'], 'ml_por_frasco' => (float)$v['ml_por_frasco'],
                                  'ml' => 0, 'importe' => 0];
        }
        $por_perfume[$pid]['ml']      += (float)$v['ml_vendidos'];
        $por_perfume[$pid]['importe'] += (float)$v['importe'];
    }
    uasort($por_perfume, function ($a, $b) { return $b['ml'] <=> $a['ml']; });
    $r['top_perfumes'] = array_slice($por_perfume, 0, 10, true);

    // 4. Por vendedor (los pedidos en línea no tienen vendedor)
    $r['por_vendedor'] = mysqli_fetch_all(mysqli_query($conn,
        "SELECT COALESCE(CONCAT(u.nombre, ' ', u.apellidos), IF(t.origen = 'En línea', 'Tienda en línea', 'Usuario eliminado')) AS vendedor,
                COUNT(*) AS num, SUM(t.total) AS total
         FROM ($ventas_sql) t LEFT JOIN usuarios u ON u.id = t.usuario_id
         GROUP BY vendedor ORDER BY total DESC"), MYSQLI_ASSOC);

    // Costo promedio por frasco según las entradas registradas (solo las que tienen costo)
    $costos = [];
    $res = mysqli_query($conn,
        "SELECT perfume_id, SUM(frascos * costo_unitario) / SUM(frascos) AS costo_frasco
         FROM entradas_inventario WHERE estado = 'registrada' AND costo_unitario > 0 GROUP BY perfume_id");
    while ($f = mysqli_fetch_assoc($res)) {
        $costos[$f['perfume_id']] = (float)$f['costo_frasco'];
    }

    // 5. Inventario actual (no depende del periodo)
    $inventario = mysqli_fetch_all(mysqli_query($conn,
        "SELECT id, nombre, marca, ml_por_frasco, frascos_sellados, ml_disponibles, stock_minimo_frascos
         FROM perfumes WHERE activo = 1 ORDER BY marca, nombre"), MYSQLI_ASSOC);
    $r['inventario'] = [];
    $r['inventario_total'] = 0;
    foreach ($inventario as $p) {
        $costo = $costos[$p['id']] ?? null;
        $valor = $costo === null ? null
            : $p['frascos_sellados'] * $costo + $p['ml_disponibles'] * ($costo / $p['ml_por_frasco']);
        $p['costo_frasco'] = $costo;
        $p['valor']        = $valor;
        $p['bajo']         = $p['frascos_sellados'] <= $p['stock_minimo_frascos'];
        $r['inventario'][] = $p;
        $r['inventario_total'] += $valor ?? 0;
    }

    // 6. Compras del periodo
    $compras_sql = "FROM entradas_inventario e INNER JOIN perfumes p ON p.id = e.perfume_id
                    WHERE e.estado = 'registrada' AND e.fecha_entrada BETWEEN '$desde' AND '$hasta'";
    $r['compras_perfume'] = mysqli_fetch_all(mysqli_query($conn,
        "SELECT p.nombre, p.marca, SUM(e.frascos) AS frascos, SUM(e.frascos * e.costo_unitario) AS total
         $compras_sql GROUP BY p.id ORDER BY total DESC"), MYSQLI_ASSOC);
    $r['compras_proveedor'] = mysqli_fetch_all(mysqli_query($conn,
        "SELECT COALESCE(NULLIF(e.proveedor, ''), 'Sin proveedor') AS proveedor, SUM(e.frascos) AS frascos,
                SUM(e.frascos * e.costo_unitario) AS total
         $compras_sql GROUP BY proveedor ORDER BY total DESC"), MYSQLI_ASSOC);
    $r['compras_total'] = array_sum(array_column($r['compras_perfume'], 'total'));

    // 7. Ganancia estimada: costo por ml = costo promedio del frasco / ml del frasco
    $r['ganancia'] = [];
    $r['ganancia_tot'] = ['ingresos' => 0, 'costo' => 0, 'sin_costo' => 0];
    foreach ($por_perfume as $pid => $p) {
        $costo_frasco = $costos[$pid] ?? null;
        $fila = ['nombre' => $p['nombre'], 'marca' => $p['marca'], 'ingresos' => $p['importe'], 'costo' => null, 'ganancia' => null];
        if ($costo_frasco !== null) {
            $fila['costo']    = $p['ml'] * ($costo_frasco / $p['ml_por_frasco']);
            $fila['ganancia'] = $fila['ingresos'] - $fila['costo'];
            $r['ganancia_tot']['ingresos'] += $fila['ingresos'];
            $r['ganancia_tot']['costo']    += $fila['costo'];
        } else {
            $r['ganancia_tot']['sin_costo'] += $fila['ingresos'];
        }
        $r['ganancia'][] = $fila;
    }

    return $r;
}
