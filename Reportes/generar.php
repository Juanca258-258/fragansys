<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once(__DIR__ . "/rep_comun.php");

if ($_SESSION['rol'] !== 'admin') {
    echo '<div class="rep-vacio">No tienes permiso para ver los reportes.</div>';
    exit;
}
$tipo = $_GET['tipo'] ?? '';
if (!isset(REPORTES[$tipo])) {
    echo '<div class="rep-vacio">Reporte no válido.</div>';
    exit;
}
mostrar_reporte($conn, $tipo, leer_filtros($_GET));
