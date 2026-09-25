<?php
require_once("../sesion.php");
require_once("../conexion.php");

$productos_totales = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total, SUM(stock > 0) AS disponibles FROM productos WHERE activo = 1"));

$combos_activos = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM combos WHERE activo = 1"));

$ventas_registradas = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS ingresos
     FROM ventas WHERE estado = 'completada'"));

$stock_total = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(contenido_ml * stock), 0) AS total_ml
     FROM productos WHERE activo = 1"));

$stock_bajo = mysqli_query($conn,
    "SELECT p.nombre, c.marca, (p.contenido_ml * p.stock) AS ml_restantes
     FROM productos p
     LEFT JOIN catalogo c ON p.catalogo_id = c.id
     WHERE p.activo = 1 AND (p.contenido_ml * p.stock) <= 15
     ORDER BY ml_restantes ASC
     LIMIT 5");
$filas_stock_bajo = mysqli_fetch_all($stock_bajo, MYSQLI_ASSOC);

$ultimas_ventas = mysqli_query($conn,
    "SELECT id, cliente_nombre, total, fecha_venta
     FROM ventas
     ORDER BY fecha_venta DESC
     LIMIT 5");
$filas_ultimas_ventas = mysqli_fetch_all($ultimas_ventas, MYSQLI_ASSOC);
?>
<div class="page-header">
    <h1>Dashboard</h1>
    <div class="sub">Resumen general de Fragansys</div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="label">Productos totales</div>
            <div class="valor"><?= (int)$productos_totales['total'] ?></div>
            <div class="extra"><?= (int)$productos_totales['disponibles'] ?> disponibles</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="label">Combos activos</div>
            <div class="valor"><?= (int)$combos_activos['total'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="label">Ventas registradas</div>
            <div class="valor"><?= (int)$ventas_registradas['total'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card oscura">
            <div class="label">Ingresos estimados</div>
            <div class="valor">$<?= number_format($ventas_registradas['ingresos'], 2) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="label">Stock total (ml)</div>
            <div class="valor"><?= number_format($stock_total['total_ml'], 0) ?> ml</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="panel">
            <div class="panel-header">
                <h5><i class="fa-solid fa-triangle-exclamation text-warning"></i> Stock bajo</h5>
                <a href="#" class="btn-dorado-outline" onclick="ir_a('productos'); return false;">Ver todos</a>
            </div>
            <?php if (empty($filas_stock_bajo)): ?>
                <div class="vacio"><i class="fa-solid fa-circle-check me-1"></i>No hay productos con stock bajo.</div>
            <?php else: ?>
            <table class="tabla-simple">
                <thead>
                <tr><th>Producto</th><th>Marca</th><th class="text-end">ML restantes</th></tr>
                </thead>
                <tbody>
                <?php foreach ($filas_stock_bajo as $p): ?>
                <tr>
                    <td><a href="#" onclick="ir_a('productos'); return false;"><?= htmlspecialchars($p['nombre']) ?></a></td>
                    <td><?= htmlspecialchars($p['marca'] ?? '—') ?></td>
                    <td class="text-end dato-alerta"><?= number_format($p['ml_restantes'], 0) ?> ml</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="panel">
            <div class="panel-header">
                <h5><i class="fa-solid fa-clock-rotate-left text-warning"></i> Últimas ventas</h5>
                <a href="#" class="btn-dorado-outline" onclick="ir_a('ventas'); return false;">Ver todas</a>
            </div>
            <?php if (empty($filas_ultimas_ventas)): ?>
                <div class="vacio">Aún no hay ventas registradas.</div>
            <?php else: ?>
            <table class="tabla-simple">
                <thead>
                <tr><th>Cliente</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                <?php foreach ($filas_ultimas_ventas as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['cliente_nombre'] ?: 'Cliente sin nombre') ?></td>
                    <td class="text-end fw-bold">$<?= number_format($v['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
