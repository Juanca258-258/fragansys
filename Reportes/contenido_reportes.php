<?php
// Espera $r (resultado de obtener_reportes), $desde y $hasta.
function rp_dinero($n) { return ($n < 0 ? '-' : '') . '$' . number_format(abs((float)$n), 2); }

// Lista con barra horizontal proporcional al valor más alto: sirve para rankings y proporciones.
// $filas: [['titulo'=>, 'sub'=>, 'valor'=>número para la barra, 'texto'=>valor mostrado, 'extra'=>texto secundario]]
function rp_barras($filas, $vacio) {
    if (empty($filas)) {
        echo '<div class="vacio">' . $vacio . '</div>';
        return;
    }
    $max = max(array_column($filas, 'valor')) ?: 1;
    echo '<div class="rp-barras">';
    foreach ($filas as $i => $f) {
        $ancho = max(2, round($f['valor'] / $max * 100));
        echo '<div class="rp-barra-fila" title="' . htmlspecialchars(strip_tags($f['titulo'] . ': ' . $f['texto'])) . '">';
        echo   '<div class="rp-barra-cab">';
        echo     '<span class="rp-barra-titulo">' . (isset($f['rank']) ? '<span class="rp-rank">' . $f['rank'] . '</span>' : '')
                 . htmlspecialchars($f['titulo'])
                 . (!empty($f['sub']) ? ' <span class="rp-barra-sub">' . htmlspecialchars($f['sub']) . '</span>' : '') . '</span>';
        echo     '<span class="rp-barra-valor">' . $f['texto']
                 . (!empty($f['extra']) ? ' <span class="rp-barra-extra">' . $f['extra'] . '</span>' : '') . '</span>';
        echo   '</div>';
        echo   '<div class="rp-pista"><div class="rp-relleno" style="width:' . $ancho . '%"></div></div>';
        echo '</div>';
    }
    echo '</div>';
}

function rp_proporcion($filas, $clave_titulo, $titulos = []) {
    $total = array_sum(array_column($filas, 'total')) ?: 1;
    $salida = [];
    foreach ($filas as $f) {
        $salida[] = [
            'titulo' => $titulos[$f[$clave_titulo]] ?? $f[$clave_titulo],
            'valor'  => (float)$f['total'],
            'texto'  => rp_dinero($f['total']),
            'extra'  => number_format($f['total'] / $total * 100, 0) . '% · ' . $f['num'] . ' venta' . ($f['num'] == 1 ? '' : 's'),
        ];
    }
    return $salida;
}

$rp_metodos = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'paypal' => 'PayPal'];
$rp_ganancia = $r['ganancia_tot']['ingresos'] - $r['ganancia_tot']['costo'];
$rp_margen   = $r['ganancia_tot']['ingresos'] > 0 ? $rp_ganancia / $r['ganancia_tot']['ingresos'] * 100 : 0;
$rp_bajos    = count(array_filter($r['inventario'], function ($p) { return $p['bajo']; }));
$rp_frascos  = array_sum(array_column($r['inventario'], 'frascos_sellados'));
$rp_ml       = array_sum(array_column($r['inventario'], 'ml_disponibles'));
?>
<style>
.rp-nav { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:22px; }
.rp-nav a {
    display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:999px;
    border:1px solid var(--borde); background:#fff; color:var(--texto); font-size:.8rem; font-weight:500;
}
.rp-nav a:hover { border-color:var(--dorado); color:var(--dorado-oscuro); }

.rp-seccion { margin-bottom:34px; scroll-margin-top:16px; }
.rp-seccion-cab { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
.rp-seccion-icono {
    width:40px; height:40px; border-radius:10px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:var(--negro); color:var(--dorado); font-size:1rem;
}
.rp-seccion-cab h2 { font-size:1.25rem; font-weight:700; margin:0; }
.rp-seccion-cab p { margin:0; font-size:.8rem; color:var(--texto-suave); }
.rp-seccion-cab .rp-linea { flex:1; height:1px; background:var(--borde); margin-left:8px; }

.rp-kpi {
    background:#fff; border:1px solid var(--borde); border-radius:12px; padding:16px 18px; height:100%;
    display:flex; gap:14px; align-items:flex-start;
}
.rp-kpi-icono {
    width:38px; height:38px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:var(--dorado-claro); color:var(--dorado-oscuro);
}
.rp-kpi-label { font-size:.7rem; font-weight:600; letter-spacing:.5px; text-transform:uppercase; color:var(--texto-suave); }
.rp-kpi-valor { font-family:'Poppins', sans-serif; font-size:1.55rem; font-weight:600; line-height:1.2; color:var(--texto); }
.rp-kpi-nota { font-size:.75rem; color:var(--texto-suave); }
.rp-kpi.destacado { background:var(--negro); border-color:var(--negro); }
.rp-kpi.destacado .rp-kpi-icono { background:rgba(198,154,76,.18); color:var(--dorado); }
.rp-kpi.destacado .rp-kpi-label, .rp-kpi.destacado .rp-kpi-nota { color:#bfb39a; }
.rp-kpi.destacado .rp-kpi-valor { color:#fff; }

.rp-panel-titulo { font-size:.9rem; font-weight:600; margin:0 0 4px; }
.rp-panel-desc { font-size:.75rem; color:var(--texto-suave); margin-bottom:14px; }

.rp-barras { display:flex; flex-direction:column; gap:12px; }
.rp-barra-cab { display:flex; justify-content:space-between; gap:12px; font-size:.84rem; margin-bottom:5px; }
.rp-barra-titulo { color:var(--texto); }
.rp-barra-sub { color:var(--texto-suave); font-size:.75rem; }
.rp-barra-valor { font-weight:600; white-space:nowrap; font-variant-numeric:tabular-nums; }
.rp-barra-extra { font-weight:400; color:var(--texto-suave); font-size:.75rem; margin-left:4px; }
.rp-rank {
    display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; margin-right:8px;
    border-radius:50%; background:var(--crema-2); font-size:.7rem; font-weight:600; color:var(--texto-suave);
}
.rp-barra-fila:first-child .rp-rank { background:var(--negro); color:var(--dorado); }
.rp-pista { height:8px; border-radius:4px; background:var(--crema-2); overflow:hidden; }
.rp-relleno { height:100%; border-radius:4px; background:var(--dorado-oscuro); }

.rp-tabla td, .rp-tabla th { font-variant-numeric:tabular-nums; }
.rp-estado { display:inline-flex; align-items:center; gap:4px; font-size:.72rem; font-weight:600; padding:2px 8px; border-radius:999px; }
.rp-estado.alerta  { background:#fbe7e0; color:#9b3d1e; }
.rp-estado.bien    { background:#e3f1e6; color:#2f6b3b; }
.rp-estado.neutro  { background:var(--crema-2); color:var(--texto-suave); }

@media print {
    .rp-nav { display:none; }
    .rp-seccion { break-inside:avoid-page; }
    .rp-kpi.destacado, .rp-seccion-icono, .rp-relleno, .rp-rank, .rp-estado { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
}
</style>

<div class="small text-muted mb-3">
    Periodo: <strong><?= date('d/m/Y', strtotime($desde)) ?></strong> al <strong><?= date('d/m/Y', strtotime($hasta)) ?></strong>
    · Generado el <?= date('d/m/Y h:i A') ?>
</div>

<nav class="rp-nav">
    <a href="#rp-ventas"><i class="fa-solid fa-sack-dollar"></i> Ventas</a>
    <a href="#rp-productos"><i class="fa-solid fa-spray-can-sparkles"></i> Productos</a>
    <a href="#rp-rentabilidad"><i class="fa-solid fa-coins"></i> Rentabilidad</a>
    <a href="#rp-compras"><i class="fa-solid fa-truck-ramp-box"></i> Compras</a>
    <a href="#rp-inventario"><i class="fa-solid fa-boxes-stacked"></i> Inventario</a>
</nav>

<!-- ============ VENTAS ============ -->
<section class="rp-seccion" id="rp-ventas">
    <div class="rp-seccion-cab">
        <div class="rp-seccion-icono"><i class="fa-solid fa-sack-dollar"></i></div>
        <div><h2>Ventas</h2><p>Mostrador y tienda en línea en el periodo</p></div>
        <div class="rp-linea"></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3"><div class="rp-kpi destacado">
            <div class="rp-kpi-icono"><i class="fa-solid fa-sack-dollar"></i></div>
            <div><div class="rp-kpi-label">Total vendido</div><div class="rp-kpi-valor"><?= rp_dinero($r['resumen']['total']) ?></div></div>
        </div></div>
        <div class="col-md-6 col-xl-3"><div class="rp-kpi">
            <div class="rp-kpi-icono"><i class="fa-solid fa-receipt"></i></div>
            <div><div class="rp-kpi-label">Ventas</div><div class="rp-kpi-valor"><?= $r['resumen']['num'] ?></div></div>
        </div></div>
        <div class="col-md-6 col-xl-3"><div class="rp-kpi">
            <div class="rp-kpi-icono"><i class="fa-solid fa-ticket"></i></div>
            <div><div class="rp-kpi-label">Ticket promedio</div><div class="rp-kpi-valor"><?= rp_dinero($r['resumen']['promedio']) ?></div></div>
        </div></div>
        <div class="col-md-6 col-xl-3"><div class="rp-kpi">
            <div class="rp-kpi-icono"><i class="fa-solid fa-coins"></i></div>
            <div>
                <div class="rp-kpi-label">Ganancia estimada</div>
                <div class="rp-kpi-valor"><?= rp_dinero($rp_ganancia) ?></div>
                <div class="rp-kpi-nota">Margen <?= number_format($rp_margen, 1) ?>%</div>
            </div>
        </div></div>
    </div>

    <div class="panel mb-3">
        <div class="rp-panel-titulo">Ventas por día</div>
        <div class="rp-panel-desc">Total vendido cada día del periodo. Pasa el cursor sobre una barra para ver el monto.</div>
        <div style="position:relative; height:280px"><canvas id="grafica_ventas_dia" aria-label="Ventas por día"></canvas></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="panel">
                <div class="rp-panel-titulo">Por método de pago</div>
                <div class="rp-panel-desc">Cómo pagaron los clientes</div>
                <?php rp_barras(rp_proporcion($r['por_metodo'], 'metodo_pago', $rp_metodos), 'Sin ventas en el periodo.'); ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel">
                <div class="rp-panel-titulo">Mostrador y tienda en línea</div>
                <div class="rp-panel-desc">Dónde se hizo la venta</div>
                <?php rp_barras(rp_proporcion($r['por_origen'], 'origen'), 'Sin ventas en el periodo.'); ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel">
                <div class="rp-panel-titulo">Por vendedor</div>
                <div class="rp-panel-desc">Cuánto vendió cada persona</div>
                <?php rp_barras(rp_proporcion($r['por_vendedor'], 'vendedor'), 'Sin ventas en el periodo.'); ?>
            </div>
        </div>
    </div>
</section>

<!-- ============ PRODUCTOS ============ -->
<section class="rp-seccion" id="rp-productos">
    <div class="rp-seccion-cab">
        <div class="rp-seccion-icono"><i class="fa-solid fa-spray-can-sparkles"></i></div>
        <div><h2>Productos</h2><p>Lo que más se vendió en el periodo</p></div>
        <div class="rp-linea"></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="panel">
                <div class="rp-panel-titulo">Presentaciones más vendidas</div>
                <div class="rp-panel-desc">Top 10 por unidades vendidas</div>
                <?php
                $filas = [];
                foreach ($r['top_presentaciones'] as $i => $p) {
                    $filas[] = ['rank' => $i + 1, 'titulo' => nombre_presentacion($p['nombre'], $p['tipo'], $p['ml']), 'sub' => $p['marca'],
                                'valor' => (float)$p['unidades'], 'texto' => (int)$p['unidades'] . ' u.', 'extra' => rp_dinero($p['importe'])];
                }
                rp_barras($filas, 'Sin ventas en el periodo.');
                ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel">
                <div class="rp-panel-titulo">Perfumes más vendidos</div>
                <div class="rp-panel-desc">Top 10 por ml vendidos (decants y frascos)</div>
                <?php
                $filas = [];
                $n = 0;
                foreach ($r['top_perfumes'] as $p) {
                    $filas[] = ['rank' => ++$n, 'titulo' => $p['nombre'], 'sub' => $p['marca'],
                                'valor' => $p['ml'], 'texto' => number_format($p['ml'], 0) . ' ml', 'extra' => rp_dinero($p['importe'])];
                }
                rp_barras($filas, 'Sin ventas en el periodo.');
                ?>
            </div>
        </div>
    </div>
</section>

<!-- ============ RENTABILIDAD ============ -->
<section class="rp-seccion" id="rp-rentabilidad">
    <div class="rp-seccion-cab">
        <div class="rp-seccion-icono"><i class="fa-solid fa-coins"></i></div>
        <div><h2>Rentabilidad</h2><p>Ganancia estimada: lo vendido menos lo que costó</p></div>
        <div class="rp-linea"></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="rp-kpi">
            <div class="rp-kpi-icono"><i class="fa-solid fa-cash-register"></i></div>
            <div><div class="rp-kpi-label">Vendido (con costo)</div><div class="rp-kpi-valor"><?= rp_dinero($r['ganancia_tot']['ingresos']) ?></div></div>
        </div></div>
        <div class="col-md-4"><div class="rp-kpi">
            <div class="rp-kpi-icono"><i class="fa-solid fa-tags"></i></div>
            <div><div class="rp-kpi-label">Costo de lo vendido</div><div class="rp-kpi-valor"><?= rp_dinero($r['ganancia_tot']['costo']) ?></div></div>
        </div></div>
        <div class="col-md-4"><div class="rp-kpi destacado">
            <div class="rp-kpi-icono"><i class="fa-solid fa-coins"></i></div>
            <div>
                <div class="rp-kpi-label">Ganancia</div>
                <div class="rp-kpi-valor"><?= rp_dinero($rp_ganancia) ?></div>
                <div class="rp-kpi-nota">Margen <?= number_format($rp_margen, 1) ?>%</div>
            </div>
        </div></div>
    </div>

    <div class="panel">
        <div class="rp-panel-titulo">Ganancia por perfume</div>
        <div class="rp-panel-desc">El costo sale del costo promedio por frasco de las entradas de Inventario, dividido entre los ml del frasco.</div>
        <?php if (empty($r['ganancia'])): ?><div class="vacio">Sin ventas en el periodo.</div><?php else: ?>
        <div class="table-responsive"><table class="tabla-simple rp-tabla">
            <thead><tr><th>Perfume</th><th class="text-end">Vendido</th><th class="text-end">Costo</th><th class="text-end">Ganancia</th><th class="text-end">Margen</th></tr></thead>
            <tbody>
            <?php foreach ($r['ganancia'] as $g): ?>
            <tr>
                <td><?= htmlspecialchars($g['nombre']) ?> <span class="small text-muted"><?= htmlspecialchars($g['marca']) ?></span></td>
                <td class="text-end"><?= rp_dinero($g['ingresos']) ?></td>
                <?php if ($g['costo'] === null): ?>
                <td class="text-end" colspan="3"><span class="rp-estado neutro"><i class="fa-solid fa-circle-question"></i> Sin costo registrado</span></td>
                <?php else:
                    $margen = $g['ingresos'] > 0 ? $g['ganancia'] / $g['ingresos'] * 100 : 0; ?>
                <td class="text-end"><?= rp_dinero($g['costo']) ?></td>
                <td class="text-end fw-semibold"><?= rp_dinero($g['ganancia']) ?></td>
                <td class="text-end">
                    <?php if ($g['ganancia'] < 0): ?>
                    <span class="rp-estado alerta"><i class="fa-solid fa-arrow-trend-down"></i> Pérdida <?= number_format($margen, 1) ?>%</span>
                    <?php else: ?>
                    <span class="rp-estado bien"><i class="fa-solid fa-arrow-trend-up"></i> <?= number_format($margen, 1) ?>%</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php if ($r['ganancia_tot']['sin_costo'] > 0): ?>
        <div class="small text-muted mt-2">
            <i class="fa-solid fa-circle-info"></i>
            Hay <?= rp_dinero($r['ganancia_tot']['sin_costo']) ?> vendidos de perfumes sin costo registrado; no entran en el cálculo de la ganancia.
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ============ COMPRAS ============ -->
<section class="rp-seccion" id="rp-compras">
    <div class="rp-seccion-cab">
        <div class="rp-seccion-icono"><i class="fa-solid fa-truck-ramp-box"></i></div>
        <div><h2>Compras</h2><p>Mercancía que llegó en el periodo (entradas de Inventario)</p></div>
        <div class="rp-linea"></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6"><div class="rp-kpi">
            <div class="rp-kpi-icono"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div><div class="rp-kpi-label">Total gastado</div><div class="rp-kpi-valor"><?= rp_dinero($r['compras_total']) ?></div></div>
        </div></div>
        <div class="col-md-6"><div class="rp-kpi">
            <div class="rp-kpi-icono"><i class="fa-solid fa-box"></i></div>
            <div><div class="rp-kpi-label">Frascos recibidos</div><div class="rp-kpi-valor"><?= array_sum(array_column($r['compras_perfume'], 'frascos')) ?></div></div>
        </div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="panel">
                <div class="rp-panel-titulo">Por perfume</div>
                <div class="rp-panel-desc">Costo total de lo que llegó de cada perfume</div>
                <?php
                $filas = [];
                foreach ($r['compras_perfume'] as $c) {
                    $filas[] = ['titulo' => $c['nombre'], 'sub' => $c['marca'], 'valor' => (float)$c['total'],
                                'texto' => rp_dinero($c['total']), 'extra' => (int)$c['frascos'] . ' frasco' . ($c['frascos'] == 1 ? '' : 's')];
                }
                rp_barras($filas, 'Sin entradas de inventario en el periodo.');
                ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel">
                <div class="rp-panel-titulo">Por proveedor</div>
                <div class="rp-panel-desc">A quién se le compró</div>
                <?php
                $filas = [];
                foreach ($r['compras_proveedor'] as $c) {
                    $filas[] = ['titulo' => $c['proveedor'], 'valor' => (float)$c['total'],
                                'texto' => rp_dinero($c['total']), 'extra' => (int)$c['frascos'] . ' frasco' . ($c['frascos'] == 1 ? '' : 's')];
                }
                rp_barras($filas, 'Sin entradas de inventario en el periodo.');
                ?>
            </div>
        </div>
    </div>
</section>

<!-- ============ INVENTARIO ============ -->
<section class="rp-seccion" id="rp-inventario">
    <div class="rp-seccion-cab">
        <div class="rp-seccion-icono"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div><h2>Inventario actual</h2><p>Existencias de hoy; no depende del periodo seleccionado</p></div>
        <div class="rp-linea"></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3"><div class="rp-kpi destacado">
            <div class="rp-kpi-icono"><i class="fa-solid fa-vault"></i></div>
            <div><div class="rp-kpi-label">Invertido en inventario</div><div class="rp-kpi-valor"><?= rp_dinero($r['inventario_total']) ?></div></div>
        </div></div>
        <div class="col-md-6 col-xl-3"><div class="rp-kpi">
            <div class="rp-kpi-icono"><i class="fa-solid fa-box"></i></div>
            <div><div class="rp-kpi-label">Frascos sellados</div><div class="rp-kpi-valor"><?= $rp_frascos ?></div></div>
        </div></div>
        <div class="col-md-6 col-xl-3"><div class="rp-kpi">
            <div class="rp-kpi-icono"><i class="fa-solid fa-flask"></i></div>
            <div><div class="rp-kpi-label">ml para decants</div><div class="rp-kpi-valor"><?= number_format($rp_ml, 0) ?> ml</div></div>
        </div></div>
        <div class="col-md-6 col-xl-3"><div class="rp-kpi">
            <div class="rp-kpi-icono"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div><div class="rp-kpi-label">En stock bajo</div><div class="rp-kpi-valor"><?= $rp_bajos ?></div><div class="rp-kpi-nota">de <?= count($r['inventario']) ?> perfumes</div></div>
        </div></div>
    </div>

    <div class="panel">
        <?php if (empty($r['inventario'])): ?><div class="vacio">No hay perfumes registrados.</div><?php else: ?>
        <div class="table-responsive"><table class="tabla-simple rp-tabla">
            <thead><tr><th>Perfume</th><th>Estado</th><th class="text-end">Frascos sellados</th><th class="text-end">ml para decants</th><th class="text-end">Costo por frasco</th><th class="text-end">Valor</th></tr></thead>
            <tbody>
            <?php foreach ($r['inventario'] as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['nombre']) ?> <span class="small text-muted"><?= htmlspecialchars($p['marca']) ?></span></td>
                <td>
                    <?php if ($p['bajo']): ?>
                    <span class="rp-estado alerta"><i class="fa-solid fa-triangle-exclamation"></i> Stock bajo</span>
                    <?php else: ?>
                    <span class="rp-estado bien"><i class="fa-solid fa-check"></i> Suficiente</span>
                    <?php endif; ?>
                </td>
                <td class="text-end"><?= (int)$p['frascos_sellados'] ?> <span class="small text-muted">(mín. <?= (int)$p['stock_minimo_frascos'] ?>)</span></td>
                <td class="text-end"><?= (float)$p['ml_disponibles'] ?> ml</td>
                <td class="text-end"><?= $p['costo_frasco'] === null ? '<span class="rp-estado neutro">Sin costo</span>' : rp_dinero($p['costo_frasco']) ?></td>
                <td class="text-end fw-semibold"><?= $p['valor'] === null ? '<span class="text-muted">—</span>' : rp_dinero($p['valor']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</section>

<script>
// Chart.js lo carga la página que incluye este archivo (inicio.php o imprimir_reportes.php):
// si se cargara aquí, dentro del panel este código correría antes de que terminara de descargarse.
(function(){
    var lienzo = document.getElementById('grafica_ventas_dia');
    if(!lienzo || typeof Chart === 'undefined') return;
    if(window._grafica_ventas_dia) window._grafica_ventas_dia.destroy();
    var dinero = function(v){ return '$' + Number(v).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
    window._grafica_ventas_dia = new Chart(lienzo, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($r['por_dia'], 'dia')) ?>,
            datasets: [{
                label: 'Ventas',
                data: <?= json_encode(array_column($r['por_dia'], 'total')) ?>,
                backgroundColor: '#a87e34',
                hoverBackgroundColor: '#1b1b1b',
                borderRadius: { topLeft: 4, topRight: 4 },
                borderSkipped: 'bottom',
                maxBarThickness: 28
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1b1b1b', titleColor: '#c69a4c', bodyColor: '#fff', padding: 10, displayColors: false,
                    callbacks: { label: function(ctx){ return dinero(ctx.parsed.y); } }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#8a7f68', font: { size: 11 }, maxRotation: 0, autoSkip: true } },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: '#efe9db' },
                    ticks: { color: '#8a7f68', font: { size: 11 }, callback: function(v){ return '$' + v.toLocaleString('es-MX'); } }
                }
            }
        }
    });
})();
</script>
