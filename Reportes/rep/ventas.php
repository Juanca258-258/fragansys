<?php
// Ventas de mostrador completadas y pedidos en línea pagados (no cancelados). Con "Hoy" funciona como corte de caja.
$ini = "{$f['desde']} 00:00:00";
$fin = "{$f['hasta']} 23:59:59";

$filas = mysqli_fetch_all(mysqli_query($conn,
    "SELECT 'v' AS origen, v.id, v.fecha_venta AS fecha, COALESCE(v.cliente_nombre, 'Público general') AS cliente, v.metodo_pago, v.total
     FROM ventas v WHERE v.estado = 'completada' AND v.fecha_venta BETWEEN '$ini' AND '$fin'
     UNION ALL
     SELECT 'p', p.id, p.fecha_pedido, p.cliente_nombre, p.metodo_pago, p.total
     FROM pedidos p WHERE p.estado_pago = 'pagado' AND p.estado_pedido <> 'cancelado' AND p.fecha_pedido BETWEEN '$ini' AND '$fin'
     ORDER BY fecha"), MYSQLI_ASSOC);

$ids_v = $ids_p = [];
foreach ($filas as $v) {
    if ($v['origen'] === 'v') { $ids_v[] = $v['id']; } else { $ids_p[] = $v['id']; }
}
$detalle = [
    'v' => detalle_productos($conn, 'venta_detalle', 'venta_id', $ids_v),
    'p' => detalle_productos($conn, 'pedido_detalle', 'pedido_id', $ids_p),
];

$metodos = [
    'efectivo'      => ['Efectivo', 'fa-solid fa-money-bill-wave', 'var(--dorado)'],
    'transferencia' => ['Transferencia', 'fa-solid fa-building-columns', '#6b8fb8'],
    'tarjeta'       => ['Tarjeta', 'fa-solid fa-credit-card', '#8a7f68'],
    'paypal'        => ['PayPal', 'fa-brands fa-paypal', '#2f6b3b'],
];
$por_metodo = ['efectivo' => 0, 'transferencia' => 0, 'tarjeta' => 0, 'paypal' => 0];
$piezas = 0;
foreach ($filas as $v) {
    $por_metodo[$v['metodo_pago']] = ($por_metodo[$v['metodo_pago']] ?? 0) + $v['total'];
    $piezas += array_sum(array_column($detalle[$v['origen']][$v['id']] ?? [], 'cantidad'));
}
if ($por_metodo['paypal'] == 0) {
    unset($por_metodo['paypal']);
}
$total = array_sum($por_metodo);
$num   = count($filas);
$canceladas = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS t FROM ventas
     WHERE estado = 'cancelada' AND fecha_venta BETWEEN '$ini' AND '$fin'"));
?>
<div class="row g-3 mb-3 rep-kpis">
    <div class="col-6 col-lg-3"><?php rp_kpi('fa-sack-dollar', 'Total vendido', rp_dinero($total), texto_periodo($f), 'destacado'); ?></div>
    <div class="col-6 col-lg-3"><?php rp_kpi('fa-receipt', 'Ventas', $num, $piezas . ' artículo' . ($piezas == 1 ? '' : 's')); ?></div>
    <div class="col-6 col-lg-3"><?php rp_kpi('fa-scale-balanced', 'Venta promedio', rp_dinero($num ? $total / $num : 0), 'por ticket'); ?></div>
    <div class="col-6 col-lg-3">
        <div class="kpi">
            <div class="kpi-icono"><i class="fa-solid fa-wallet"></i></div>
            <div class="flex-grow-1">
                <div class="kpi-label">Cómo pagaron</div>
                <div class="pago-barra">
                    <?php foreach ($por_metodo as $m => $t): if ($t <= 0) continue; ?>
                    <span style="width:<?= $t / $total * 100 ?>%;background:<?= $metodos[$m][2] ?>"></span>
                    <?php endforeach; ?>
                </div>
                <div class="pago-leyenda">
                    <?php foreach ($por_metodo as $m => $t): ?>
                    <span><i class="fa-solid fa-circle" style="color:<?= $metodos[$m][2] ?>"></i> <?= $metodos[$m][0] ?> <?= rp_dinero($t) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="panel panel-modulo">
    <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
        <h5 class="rep-subt"><i class="fa-solid fa-list"></i> Detalle de ventas</h5>
        <?php if ($filas): ?><span class="kpi-nota solo-pantalla">Haz clic en una venta para ver sus productos</span><?php endif; ?>
    </div>
    <div class="table-responsive">
    <table class="table tabla-modulo align-middle mb-0">
        <thead><tr><th style="width:28px"></th><th>Folio</th><th>Fecha</th><th>Cliente</th><th>Artículos</th><th>Pago</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php if (!$filas): ?>
        <tr><td colspan="7" class="rep-vacio"><i class="fa-regular fa-folder-open"></i> No hay ventas en este periodo.</td></tr>
        <?php endif; ?>
        <?php foreach ($filas as $v):
            $items = $detalle[$v['origen']][$v['id']] ?? [];
            [$m_txt, $m_ico] = $metodos[$v['metodo_pago']] ?? [$v['metodo_pago'], 'fa-solid fa-money-bill'];
            $folio = $v['origen'] === 'v' ? 'V-' . str_pad($v['id'], 6, '0', STR_PAD_LEFT) : 'P-' . $v['id']; ?>
        <tr class="fila-venta" onclick="$(this).toggleClass('abierta').next().toggle()">
            <td><i class="fa-solid fa-chevron-right"></i></td>
            <td class="fw-semibold"><?= $folio ?></td>
            <td class="text-nowrap"><?= date('d/m/Y', strtotime($v['fecha'])) ?><div class="small text-muted"><?= date('h:i A', strtotime($v['fecha'])) ?></div></td>
            <td><?= rp_e($v['cliente']) ?><?php if ($v['origen'] === 'p'): ?> <span class="estado info"><i class="fa-solid fa-globe"></i> En línea</span><?php endif; ?></td>
            <td><span class="chip"><strong><?= array_sum(array_column($items, 'cantidad')) ?></strong> piezas</span></td>
            <td><span class="estado neutro"><i class="<?= $m_ico ?>"></i> <?= $m_txt ?></span></td>
            <td class="text-end fw-semibold"><?= rp_dinero($v['total']) ?></td>
        </tr>
        <tr class="detalle-venta"><td colspan="7"><table>
            <?php foreach ($items as $it): ?>
            <tr><td class="text-end"><?= (int)$it['cantidad'] ?></td><td><?= rp_e($it['nombre']) ?></td><td class="text-end"><?= rp_dinero($it['subtotal']) ?></td></tr>
            <?php endforeach; ?>
        </table></td></tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($filas): ?>
        <tfoot><tr><td></td><td colspan="5" class="fw-semibold pt-3"><?= $num ?> venta<?= $num == 1 ? '' : 's' ?></td><td class="text-end fw-bold pt-3"><?= rp_dinero($total) ?></td></tr></tfoot>
        <?php endif; ?>
    </table>
    </div>
    <?php if ($canceladas['n'] > 0): ?>
    <div class="kpi-nota mt-3"><i class="fa-solid fa-ban"></i> <?= $canceladas['n'] ?> venta<?= $canceladas['n'] == 1 ? '' : 's' ?> cancelada<?= $canceladas['n'] == 1 ? '' : 's' ?> por <?= rp_dinero($canceladas['t']) ?> no se cuentan.</div>
    <?php endif; ?>
</div>
