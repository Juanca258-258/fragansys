<?php
// Catálogo de reportes, filtros y utilidades compartidas. [título, ¿usa fechas?, ícono]
const REPORTES = [
    'ventas'     => ['Ventas', true, 'fa-receipt'],
    'inventario' => ['Inventario', false, 'fa-boxes-stacked'],
    'compras'    => ['Compras y ganancia', true, 'fa-coins'],
];

const METODOS_PAGO = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'paypal' => 'PayPal'];

function rp_dinero($n) { return ($n < 0 ? '-' : '') . '$' . number_format(abs((float)$n), 2); }
function rp_e($s) { return htmlspecialchars((string)$s); }

function fecha_valida($f) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$f);
    return $d && $d->format('Y-m-d') === $f;
}

function leer_filtros($req) {
    $f = [
        'desde' => fecha_valida($req['desde'] ?? '') ? $req['desde'] : date('Y-m-01'),
        'hasta' => fecha_valida($req['hasta'] ?? '') ? $req['hasta'] : date('Y-m-d'),
    ];
    if ($f['desde'] > $f['hasta']) {
        [$f['desde'], $f['hasta']] = [$f['hasta'], $f['desde']];
    }
    return $f;
}

function texto_periodo($f) {
    $d = date('d/m/Y', strtotime($f['desde']));
    $h = date('d/m/Y', strtotime($f['hasta']));
    return $d === $h ? "Día $d" : "Del $d al $h";
}

// Productos vendidos en el periodo (mostrador completadas y pedidos pagados no cancelados), un renglón por presentación.
// Los combos se reparten en sus piezas; el precio del combo se divide en proporción al precio por separado de cada pieza.
function sql_items_vendidos($desde, $hasta) {
    $ini = "$desde 00:00:00";
    $fin = "$hasta 23:59:59";
    $lista = "(SELECT cp2.combo_id, SUM(pr2.precio * cp2.cantidad) AS lista
               FROM combo_presentaciones cp2 INNER JOIN presentaciones pr2 ON pr2.id = cp2.presentacion_id
               GROUP BY cp2.combo_id)";
    return "
        SELECT vd.presentacion_id, vd.cantidad, vd.subtotal
        FROM venta_detalle vd INNER JOIN ventas v ON v.id = vd.venta_id
        WHERE vd.tipo = 'presentacion' AND v.estado = 'completada' AND v.fecha_venta BETWEEN '$ini' AND '$fin'
        UNION ALL
        SELECT pd.presentacion_id, pd.cantidad, pd.subtotal
        FROM pedido_detalle pd INNER JOIN pedidos p ON p.id = pd.pedido_id
        WHERE pd.tipo = 'presentacion' AND p.estado_pago = 'pagado' AND p.estado_pedido <> 'cancelado'
              AND p.fecha_pedido BETWEEN '$ini' AND '$fin'
        UNION ALL
        SELECT cp.presentacion_id, vd.cantidad * cp.cantidad, vd.subtotal * (pr.precio * cp.cantidad) / lc.lista
        FROM venta_detalle vd
        INNER JOIN ventas v ON v.id = vd.venta_id
        INNER JOIN combo_presentaciones cp ON cp.combo_id = vd.combo_id
        INNER JOIN presentaciones pr ON pr.id = cp.presentacion_id
        INNER JOIN $lista lc ON lc.combo_id = vd.combo_id
        WHERE vd.tipo = 'combo' AND v.estado = 'completada' AND v.fecha_venta BETWEEN '$ini' AND '$fin'
        UNION ALL
        SELECT cp.presentacion_id, pd.cantidad * cp.cantidad, pd.subtotal * (pr.precio * cp.cantidad) / lc.lista
        FROM pedido_detalle pd
        INNER JOIN pedidos p ON p.id = pd.pedido_id
        INNER JOIN combo_presentaciones cp ON cp.combo_id = pd.combo_id
        INNER JOIN presentaciones pr ON pr.id = cp.presentacion_id
        INNER JOIN $lista lc ON lc.combo_id = pd.combo_id
        WHERE pd.tipo = 'combo' AND p.estado_pago = 'pagado' AND p.estado_pedido <> 'cancelado'
              AND p.fecha_pedido BETWEEN '$ini' AND '$fin'";
}

// Costo promedio por frasco de cada perfume, según las entradas de inventario con costo.
function costos_por_frasco($conn) {
    $costos = [];
    $res = mysqli_query($conn,
        "SELECT perfume_id, SUM(frascos * costo_unitario) / SUM(frascos) AS costo
         FROM entradas_inventario WHERE estado = 'registrada' AND costo_unitario > 0 GROUP BY perfume_id");
    while ($f = mysqli_fetch_assoc($res)) {
        $costos[$f['perfume_id']] = (float)$f['costo'];
    }
    return $costos;
}


// Productos de varias ventas o pedidos, agrupados por id: [id => [[cantidad, nombre, subtotal], ...]].
function detalle_productos($conn, $tabla_detalle, $columna_padre, $ids) {
    $detalle = [];
    if (!$ids) {
        return $detalle;
    }
    $lista = implode(',', array_map('intval', $ids));
    $res = mysqli_query($conn,
        "SELECT d.$columna_padre AS padre, d.cantidad, d.subtotal,
                IF(d.tipo = 'combo', CONCAT('Combo ', c.nombre),
                   CONCAT(pf.nombre, ' · ', IF(pr.tipo = 'frasco', 'Frasco ', 'Decant '), TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.ml)), ' ml')) AS nombre
         FROM $tabla_detalle d
         LEFT JOIN presentaciones pr ON pr.id = d.presentacion_id
         LEFT JOIN perfumes pf ON pf.id = pr.perfume_id
         LEFT JOIN combos c ON c.id = d.combo_id
         WHERE d.$columna_padre IN ($lista) ORDER BY d.id");
    while ($d = mysqli_fetch_assoc($res)) {
        $detalle[$d['padre']][] = $d;
    }
    return $detalle;
}

// Tarjeta de resumen (mismo estilo que el Dashboard).
function rp_kpi($icono, $etiqueta, $valor, $nota = '', $clase = '') {
    ?>
    <div class="kpi <?= $clase ?>">
        <div class="kpi-icono"><i class="fa-solid <?= $icono ?>"></i></div>
        <div class="flex-grow-1">
            <div class="kpi-label"><?= $etiqueta ?></div>
            <div class="kpi-valor"><?= $valor ?></div>
            <?php if ($nota !== ''): ?><div class="kpi-nota"><?= $nota ?></div><?php endif; ?>
        </div>
    </div>
    <?php
}

// Encabezado que solo aparece en papel (en pantalla ya están las pestañas y el periodo) y cuerpo del reporte.
function mostrar_reporte($conn, $tipo, $f) {
    [$titulo, $usa_fechas] = REPORTES[$tipo];
    ?>
    <div class="rep-cab-impresion">
        <h2><?= rp_e($titulo) ?></h2>
        <span><?= $usa_fechas ? texto_periodo($f) . ' · ' : '' ?>Generado el <?= date('d/m/Y h:i A') ?></span>
    </div>
    <?php
    include __DIR__ . "/rep/$tipo.php";
}
