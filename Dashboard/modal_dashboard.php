<?php
require_once("../sesion.php");
require_once("../conexion.php");

$productos_totales = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total, SUM(frascos_sellados > 0 OR ml_disponibles > 0) AS disponibles
     FROM perfumes WHERE activo = 1"));

$combos_activos = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM combos WHERE activo = 1"));

// Un pedido en línea cuenta como venta solo cuando está pagado; los pendientes se muestran aparte.
$ventas_sql = "
    SELECT cliente_nombre, total, fecha_venta AS fecha, 'Mostrador' AS origen FROM ventas WHERE estado = 'completada'
    UNION ALL
    SELECT cliente_nombre, total, fecha_pedido, 'En línea' FROM pedidos
    WHERE estado_pago = 'pagado' AND estado_pedido <> 'cancelado'";

$ventas_registradas = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS ingresos FROM ($ventas_sql) t"));

$pendientes_pago = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS num, COALESCE(SUM(total), 0) AS total FROM pedidos
     WHERE estado_pago = 'pendiente' AND estado_pedido <> 'cancelado'"));

$stock_total = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(ml_disponibles + frascos_sellados * ml_por_frasco), 0) AS total_ml
     FROM perfumes WHERE activo = 1"));

$stock_bajo = mysqli_query($conn,
    "SELECT nombre, marca, frascos_sellados, stock_minimo_frascos, ml_disponibles AS ml_restantes
     FROM perfumes
     WHERE activo = 1 AND frascos_sellados <= stock_minimo_frascos
     ORDER BY frascos_sellados ASC, ml_disponibles ASC
     LIMIT 5");
$filas_stock_bajo = mysqli_fetch_all($stock_bajo, MYSQLI_ASSOC);

$ultimas_ventas = mysqli_query($conn, "SELECT * FROM ($ventas_sql) t ORDER BY fecha DESC LIMIT 5");
$filas_ultimas_ventas = mysqli_fetch_all($ultimas_ventas, MYSQLI_ASSOC);
?>
<div class="modulo-cab">
    <div class="modulo-icono"><i class="fa-solid fa-gauge-high"></i></div>
    <div>
        <h1>Dashboard</h1>
        <div class="sub">Resumen general de Fragansys</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl">
        <div class="kpi destacado">
            <div class="kpi-icono"><i class="fa-solid fa-sack-dollar"></i></div>
            <div>
                <div class="kpi-label">Ingresos</div>
                <div class="kpi-valor">$<?= number_format($ventas_registradas['ingresos'], 2) ?></div>
                <?php if ($pendientes_pago['num'] > 0): ?>
                <div class="kpi-nota">
                    <i class="fa-solid fa-hourglass-half"></i>
                    + $<?= number_format($pendientes_pago['total'], 2) ?> pendientes de pago
                    (<?= $pendientes_pago['num'] ?> pedido<?= $pendientes_pago['num'] == 1 ? '' : 's' ?>)
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl">
        <div class="kpi">
            <div class="kpi-icono"><i class="fa-solid fa-receipt"></i></div>
            <div>
                <div class="kpi-label">Ventas registradas</div>
                <div class="kpi-valor"><?= (int)$ventas_registradas['total'] ?></div>
                <div class="kpi-nota">Mostrador y pedidos pagados</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl">
        <div class="kpi">
            <div class="kpi-icono"><i class="fa-solid fa-spray-can-sparkles"></i></div>
            <div>
                <div class="kpi-label">Perfumes</div>
                <div class="kpi-valor"><?= (int)$productos_totales['total'] ?></div>
                <div class="kpi-nota"><?= (int)$productos_totales['disponibles'] ?> disponibles</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl">
        <div class="kpi">
            <div class="kpi-icono"><i class="fa-solid fa-flask"></i></div>
            <div>
                <div class="kpi-label">Stock total</div>
                <div class="kpi-valor"><?= number_format($stock_total['total_ml'], 0) ?> ml</div>
                <div class="kpi-nota">Frascos sellados y abiertos</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl">
        <div class="kpi">
            <div class="kpi-icono"><i class="fa-solid fa-gift"></i></div>
            <div>
                <div class="kpi-label">Combos activos</div>
                <div class="kpi-valor"><?= (int)$combos_activos['total'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="panel">
            <div class="panel-header">
                <h5><i class="fa-solid fa-triangle-exclamation" style="color:#9b3d1e"></i> Stock bajo</h5>
                <a href="#" class="btn-accion" onclick="ir_a('perfumes'); return false;">Ver perfumes <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <?php if (empty($filas_stock_bajo)): ?>
                <div class="vacio"><span class="estado bien"><i class="fa-solid fa-circle-check"></i> No hay perfumes con stock bajo</span></div>
            <?php else: ?>
            <div class="table-responsive">
            <table class="tabla-simple">
                <thead>
                <tr><th>Perfume</th><th class="text-end">Frascos sellados</th><th class="text-end">Para decants</th></tr>
                </thead>
                <tbody>
                <?php foreach ($filas_stock_bajo as $p): ?>
                <tr>
                    <td>
                        <a href="#" onclick="ir_a('perfumes'); return false;" class="fw-semibold" style="color:var(--texto)"><?= htmlspecialchars($p['nombre']) ?></a>
                        <div class="small text-muted"><?= htmlspecialchars($p['marca']) ?></div>
                    </td>
                    <td class="text-end">
                        <span class="estado alerta"><i class="fa-solid fa-triangle-exclamation"></i> <?= (int)$p['frascos_sellados'] ?></span>
                        <div class="small text-muted">mín. <?= (int)$p['stock_minimo_frascos'] ?></div>
                    </td>
                    <td class="text-end"><?= number_format($p['ml_restantes'], 0) ?> ml</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="panel">
            <div class="panel-header">
                <h5><i class="fa-solid fa-clock-rotate-left" style="color:var(--dorado-oscuro)"></i> Últimas ventas</h5>
                <a href="#" class="btn-accion" onclick="ir_a('ventas'); return false;">Ver ventas <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <?php if (empty($filas_ultimas_ventas)): ?>
                <div class="vacio">Aún no hay ventas registradas.</div>
            <?php else: ?>
            <table class="tabla-simple">
                <tbody>
                <?php foreach ($filas_ultimas_ventas as $v): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($v['cliente_nombre'] ?: 'Público general') ?></div>
                        <div class="small text-muted"><?= date('d/m/Y h:i A', strtotime($v['fecha'])) ?></div>
                    </td>
                    <td>
                        <?php if ($v['origen'] === 'En línea'): ?>
                        <span class="estado info"><i class="fa-solid fa-globe"></i> En línea</span>
                        <?php else: ?>
                        <span class="estado neutro"><i class="fa-solid fa-store"></i> Mostrador</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end fw-semibold">$<?= number_format($v['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
