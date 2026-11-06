<?php
// Existencias de cada perfume; los que llegaron a su mínimo van primero. Al imprimir se agrega la columna de conteo.
$perfumes = mysqli_fetch_all(mysqli_query($conn,
    "SELECT nombre, marca, ml_por_frasco, frascos_sellados, stock_minimo_frascos, ml_disponibles,
            frascos_sellados <= stock_minimo_frascos AS comprar
     FROM perfumes WHERE activo = 1 ORDER BY comprar DESC, nombre"), MYSQLI_ASSOC);
$frascos     = array_sum(array_column($perfumes, 'frascos_sellados'));
$ml          = array_sum(array_column($perfumes, 'ml_disponibles'));
$por_comprar = array_sum(array_column($perfumes, 'comprar'));
?>
<div class="row g-3 mb-3 rep-kpis">
    <div class="col-md-4"><?php rp_kpi('fa-spray-can-sparkles', 'Perfumes', count($perfumes), 'activos en el catálogo', 'destacado'); ?></div>
    <div class="col-md-4"><?php rp_kpi('fa-box', 'Frascos sellados', $frascos, number_format($ml, 0) . ' ml abiertos para decants'); ?></div>
    <div class="col-md-4"><?php rp_kpi('fa-cart-flatbed', 'Por comprar', $por_comprar, 'en su stock mínimo', $por_comprar ? 'alerta' : ''); ?></div>
</div>

<div class="panel panel-modulo">
    <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
        <h5 class="rep-subt"><i class="fa-solid fa-boxes-stacked"></i> Existencias</h5>
        <span class="kpi-nota solo-pantalla">Al imprimir se agrega una columna para anotar el conteo físico</span>
    </div>
    <div class="table-responsive">
    <table class="table tabla-modulo align-middle mb-0">
        <thead><tr><th>Perfume</th><th class="text-end">Frascos</th><th class="text-end">Mínimo</th><th class="text-end">ml abiertos</th><th>Estado</th><th class="solo-impresion">Conteo</th></tr></thead>
        <tbody>
        <?php if (!$perfumes): ?>
        <tr><td colspan="5" class="rep-vacio"><i class="fa-regular fa-folder-open"></i> No hay perfumes registrados.</td></tr>
        <?php endif; ?>
        <?php foreach ($perfumes as $p): ?>
        <tr>
            <td class="fw-semibold"><?= rp_e($p['nombre']) ?><div class="small text-muted"><?= rp_e($p['marca']) ?> · <?= (float)$p['ml_por_frasco'] ?> ml</div></td>
            <td class="text-end"><?= (int)$p['frascos_sellados'] ?></td>
            <td class="text-end"><?= (int)$p['stock_minimo_frascos'] ?></td>
            <td class="text-end"><?= (float)$p['ml_disponibles'] ?></td>
            <td><?= $p['comprar']
                ? '<span class="estado alerta"><i class="fa-solid fa-cart-flatbed"></i> Comprar</span>'
                : '<span class="estado bien"><i class="fa-solid fa-check"></i> Suficiente</span>' ?></td>
            <td class="solo-impresion casilla"></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
