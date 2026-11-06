<?php
// Lo que se compró (entradas de inventario) y la ganancia estimada de lo vendido en el mismo periodo.
$entradas = mysqli_fetch_all(mysqli_query($conn,
    "SELECT e.fecha_entrada, e.frascos, e.costo_unitario, e.proveedor, pf.nombre
     FROM entradas_inventario e INNER JOIN perfumes pf ON pf.id = e.perfume_id
     WHERE e.estado = 'registrada' AND e.fecha_entrada BETWEEN '{$f['desde']}' AND '{$f['hasta']}'
     ORDER BY e.fecha_entrada, e.id"), MYSQLI_ASSOC);
$total_compras = 0;
foreach ($entradas as $e) {
    $total_compras += $e['frascos'] * $e['costo_unitario'];
}

$costos = costos_por_frasco($conn);
$ventas = mysqli_fetch_all(mysqli_query($conn,
    "SELECT pf.id, pf.nombre, pf.ml_por_frasco, SUM(i.cantidad * pr.ml) AS ml_vendidos, SUM(i.subtotal) AS vendido
     FROM (" . sql_items_vendidos($f['desde'], $f['hasta']) . ") i
     INNER JOIN presentaciones pr ON pr.id = i.presentacion_id
     INNER JOIN perfumes pf ON pf.id = pr.perfume_id
     GROUP BY pf.id ORDER BY vendido DESC"), MYSQLI_ASSOC);

$vendido_total = 0;
$tot = ['vendido' => 0, 'costo' => 0];
foreach ($ventas as &$v) {
    $vendido_total += $v['vendido'];
    $v['costo'] = isset($costos[$v['id']]) ? $v['ml_vendidos'] * $costos[$v['id']] / $v['ml_por_frasco'] : null;
    if ($v['costo'] !== null) {
        $tot['vendido'] += $v['vendido'];
        $tot['costo']   += $v['costo'];
    }
}
unset($v);
$ganancia = $tot['vendido'] - $tot['costo'];
$margen   = $tot['vendido'] > 0 ? number_format($ganancia / $tot['vendido'] * 100, 1) . '%' : '—';
?>
<div class="row g-3 mb-3 rep-kpis">
    <div class="col-6 col-lg-3"><?php rp_kpi('fa-truck-ramp-box', 'Comprado', rp_dinero($total_compras), array_sum(array_column($entradas, 'frascos')) . ' frascos'); ?></div>
    <div class="col-6 col-lg-3"><?php rp_kpi('fa-sack-dollar', 'Vendido', rp_dinero($vendido_total), texto_periodo($f)); ?></div>
    <div class="col-6 col-lg-3"><?php rp_kpi('fa-tags', 'Costo de lo vendido', rp_dinero($tot['costo']), 'de perfumes con costo'); ?></div>
    <div class="col-6 col-lg-3"><?php rp_kpi('fa-arrow-trend-up', 'Ganancia', rp_dinero($ganancia), 'margen ' . $margen, 'destacado'); ?></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="panel panel-modulo">
            <h5 class="rep-subt mb-2"><i class="fa-solid fa-truck-ramp-box"></i> Compras</h5>
            <div class="table-responsive">
            <table class="table tabla-modulo align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Perfume</th><th class="text-end">Frascos</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                <?php if (!$entradas): ?>
                <tr><td colspan="4" class="rep-vacio"><i class="fa-regular fa-folder-open"></i> No hubo compras en este periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($entradas as $e): ?>
                <tr>
                    <td class="text-nowrap"><?= date('d/m/Y', strtotime($e['fecha_entrada'])) ?></td>
                    <td class="fw-semibold"><?= rp_e($e['nombre']) ?>
                        <div class="small text-muted"><?= rp_dinero($e['costo_unitario']) ?> c/u<?= $e['proveedor'] ? ' · ' . rp_e($e['proveedor']) : '' ?></div></td>
                    <td class="text-end"><?= (int)$e['frascos'] ?></td>
                    <td class="text-end fw-semibold"><?= rp_dinero($e['frascos'] * $e['costo_unitario']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel panel-modulo">
            <h5 class="rep-subt mb-2"><i class="fa-solid fa-arrow-trend-up"></i> Ganancia por perfume</h5>
            <div class="table-responsive">
            <table class="table tabla-modulo align-middle mb-0">
                <thead><tr><th>Perfume</th><th class="text-end">Vendido</th><th class="text-end">Ganancia</th></tr></thead>
                <tbody>
                <?php if (!$ventas): ?>
                <tr><td colspan="3" class="rep-vacio"><i class="fa-regular fa-folder-open"></i> No hubo ventas en este periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($ventas as $v): ?>
                <tr>
                    <td class="fw-semibold"><?= rp_e($v['nombre']) ?><div class="small text-muted"><?= number_format($v['ml_vendidos'], 0) ?> ml vendidos</div></td>
                    <td class="text-end"><?= rp_dinero($v['vendido']) ?></td>
                    <td class="text-end">
                        <?php if ($v['costo'] === null): ?>
                        <span class="estado neutro" title="Registra el costo en Inventario para calcularla">Sin costo</span>
                        <?php else: $g = $v['vendido'] - $v['costo']; ?>
                        <span class="estado <?= $g >= 0 ? 'bien' : 'alerta' ?>"><?= rp_dinero($g) ?> · <?= $v['vendido'] > 0 ? round($g / $v['vendido'] * 100) : 0 ?>%</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
