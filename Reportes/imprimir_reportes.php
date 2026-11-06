<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");
require_once(__DIR__ . "/rep_comun.php");

if ($_SESSION['rol'] !== 'admin') {
    echo 'No tienes permiso para ver los reportes.';
    exit;
}
$tipo = $_GET['tipo'] ?? '';
if (!isset(REPORTES[$tipo])) {
    echo 'Reporte no válido.';
    exit;
}
$f   = leer_filtros($_GET);
$cfg = obtener_config($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= rp_e(REPORTES[$tipo][0]) ?> - <?= rp_e($cfg['negocio_nombre']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../css/estilos.css">
<style>
body { background: #fff; font-size: 13px; }
.hoja { max-width: 1050px; margin: 0 auto; padding: 24px; }
.acciones { text-align: center; margin-bottom: 18px; }
.acciones button { padding: 8px 18px; margin: 0 4px; }
.negocio { display: flex; justify-content: space-between; border-bottom: 2px solid #1b1b1b; padding-bottom: 8px; margin-bottom: 14px; }
.negocio strong { font-family: 'Playfair Display', serif; font-size: 1.3rem; }
.negocio div { font-size: .75rem; color: #555; text-align: right; }
@media print {
    @page { size: A4; margin: 12mm; }
    .acciones { display: none; }
    .hoja { padding: 0; max-width: none; }
}
</style>
</head>
<body>
<div class="hoja">
    <div class="acciones">
        <button class="btn btn-dark" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir / Guardar PDF</button>
        <button class="btn btn-outline-secondary" onclick="window.close()">Cerrar</button>
    </div>
    <div class="negocio">
        <strong><?= rp_e($cfg['negocio_nombre']) ?></strong>
        <div>
            <?= rp_e($cfg['negocio_direccion']) ?>
            <?php if ($cfg['negocio_telefono']): ?><br>Tel. <?= rp_e($cfg['negocio_telefono']) ?><?php endif; ?>
        </div>
    </div>
    <?php mostrar_reporte($conn, $tipo, $f); ?>
</div>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 300); });</script>
</body>
</html>
