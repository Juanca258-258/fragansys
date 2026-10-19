<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once(__DIR__ . "/datos_reportes.php");

if ($_SESSION['rol'] !== 'admin') {
    echo 'No tienes permiso para ver los reportes.';
    exit;
}

[$periodo, $desde, $hasta] = periodo_desde_peticion($_GET);
$r = obtener_reportes($conn, $desde, $hasta);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reportes Fragansys <?= date('d-m-Y', strtotime($desde)) ?> al <?= date('d-m-Y', strtotime($hasta)) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../css/estilos.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
body { background: #fff; }
.hoja { max-width: 1000px; margin: 0 auto; padding: 24px; }
.acciones { text-align: center; margin-bottom: 16px; }
.acciones button { padding: 8px 18px; margin: 0 4px; }
@media print {
    @page { size: A4; margin: 12mm; }
    .acciones { display: none; }
    .hoja { padding: 0; max-width: none; }
    .panel, .rp-kpi { break-inside: avoid; }
}
</style>
</head>
<body>
<div class="hoja">
    <div class="acciones">
        <button class="btn btn-dark" onclick="window.print()">Imprimir / Guardar PDF</button>
        <button class="btn btn-outline-secondary" onclick="window.close()">Cerrar</button>
    </div>
    <div class="page-header">
        <h1>Reportes Fragansys</h1>
    </div>
    <?php include(__DIR__ . "/contenido_reportes.php"); ?>
</div>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 300); });</script>
</body>
</html>
