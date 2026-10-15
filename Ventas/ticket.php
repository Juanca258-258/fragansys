<?php
require_once("../sesion.php");
require_once("../conexion.php");

$id = (int)($_GET['id'] ?? 0);
$venta = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT v.*, CONCAT(u.nombre, ' ', u.apellidos) AS vendedor
     FROM ventas v LEFT JOIN usuarios u ON v.usuario_id = u.id
     WHERE v.id = $id"));
if (!$venta) {
    echo 'La venta no existe.';
    exit;
}

$detalle = mysqli_query($conn,
    "SELECT vd.cantidad, vd.precio_unitario, vd.subtotal,
            CONCAT(pf.nombre, ' ', IF(pr.tipo = 'frasco', 'Frasco ', 'Decant '),
                   TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.ml)), ' ml') AS producto
     FROM venta_detalle vd
     LEFT JOIN presentaciones pr ON vd.presentacion_id = pr.id
     LEFT JOIN perfumes pf ON pr.perfume_id = pf.id
     WHERE vd.venta_id = $id");

$folio     = 'V-' . str_pad($venta['id'], 6, '0', STR_PAD_LEFT);
$metodos   = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'paypal' => 'PayPal'];
$cancelada = $venta['estado'] === 'cancelada';
function dinero($n) { return '$' . number_format($n, 2); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket <?= $folio ?></title>
<style>
@page { size: 80mm auto; margin: 0; }
* { box-sizing: border-box; }
body {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: #000;
    background: #f1f1f1;
    margin: 0;
}
.ticket {
    width: 80mm;
    margin: 12px auto;
    padding: 6mm 4mm;
    background: #fff;
}
.centro { text-align: center; }
.negocio { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
.linea { border-top: 1px dashed #000; margin: 6px 0; }
table { width: 100%; border-collapse: collapse; }
td { vertical-align: top; padding: 1px 0; }
.der { text-align: right; white-space: nowrap; }
.producto { padding-top: 3px; }
.total td { font-size: 15px; font-weight: bold; padding-top: 4px; }
.cancelada { border: 2px solid #000; font-weight: bold; font-size: 14px; padding: 4px; margin: 6px 0; }
.acciones { text-align: center; margin: 10px; font-family: sans-serif; }
.acciones button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
@media print {
    body { background: #fff; }
    .ticket { margin: 0; }
    .acciones { display: none; }
}
</style>
</head>
<body>

<div class="acciones">
    <button onclick="window.print()">Imprimir</button>
    <button onclick="window.close()">Cerrar</button>
</div>

<div class="ticket">
    <div class="centro negocio">FRAGANSYS</div>
    <div class="centro">Fragancias originales</div>
    <div class="linea"></div>

    <?php if ($cancelada): ?>
    <div class="centro cancelada">VENTA CANCELADA</div>
    <?php endif; ?>

    <table>
        <tr><td>Folio:</td><td class="der"><?= $folio ?></td></tr>
        <tr><td>Fecha:</td><td class="der"><?= date('d/m/Y h:i A', strtotime($venta['fecha_venta'])) ?></td></tr>
        <tr><td>Atendió:</td><td class="der"><?= htmlspecialchars($venta['vendedor'] ?? '—') ?></td></tr>
        <?php if ($venta['cliente_nombre']): ?>
        <tr><td>Cliente:</td><td class="der"><?= htmlspecialchars($venta['cliente_nombre']) ?></td></tr>
        <?php endif; ?>
        <?php if ($venta['cliente_telefono']): ?>
        <tr><td>Teléfono:</td><td class="der"><?= htmlspecialchars($venta['cliente_telefono']) ?></td></tr>
        <?php endif; ?>
    </table>

    <div class="linea"></div>
    <table>
        <tr><td><strong>Cant</strong></td><td class="der"><strong>P. unit.</strong></td><td class="der"><strong>Importe</strong></td></tr>
        <?php while ($d = mysqli_fetch_assoc($detalle)): ?>
        <tr><td colspan="3" class="producto"><?= htmlspecialchars($d['producto'] ?? 'Producto') ?></td></tr>
        <tr>
            <td><?= (int)$d['cantidad'] ?></td>
            <td class="der"><?= dinero($d['precio_unitario']) ?></td>
            <td class="der"><?= dinero($d['subtotal']) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

    <div class="linea"></div>
    <table>
        <tr class="total"><td>TOTAL</td><td class="der"><?= dinero($venta['total']) ?></td></tr>
        <tr><td>Pago:</td><td class="der"><?= $metodos[$venta['metodo_pago']] ?? $venta['metodo_pago'] ?></td></tr>
        <?php if ($venta['metodo_pago'] === 'efectivo' && $venta['monto_recibido'] !== null): ?>
        <tr><td>Recibido:</td><td class="der"><?= dinero($venta['monto_recibido']) ?></td></tr>
        <tr><td>Cambio:</td><td class="der"><?= dinero($venta['monto_recibido'] - $venta['total']) ?></td></tr>
        <?php endif; ?>
    </table>

    <div class="linea"></div>
    <div class="centro">¡Gracias por su compra!</div>
</div>

<?php if (!empty($_GET['imprimir'])): ?>
<script>window.addEventListener('load', function(){ window.print(); });</script>
<?php endif; ?>
</body>
</html>
