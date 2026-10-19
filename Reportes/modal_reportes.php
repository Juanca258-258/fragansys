<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once(__DIR__ . "/datos_reportes.php");

if ($_SESSION['rol'] !== 'admin') {
    echo '<div class="panel vacio">No tienes permiso para ver los reportes.</div>';
    exit;
}

[$periodo, $desde, $hasta] = periodo_desde_peticion($_GET);
$r = obtener_reportes($conn, $desde, $hasta);
?>
<div class="modulo-cab compacto">
    <div class="modulo-icono"><i class="fa-solid fa-chart-line"></i></div>
    <div>
        <h1>Reportes</h1>
        <div class="sub">Ventas, productos, compras, ganancia e inventario</div>
    </div>
    <div class="acciones-cab">
        <button class="btn-dorado" onclick="imprimir_reportes()"><i class="fa-solid fa-print"></i> Imprimir / PDF</button>
    </div>
</div>

<div class="panel mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label>Periodo</label>
            <select id="rep_periodo" class="form-select" onchange="cambiar_periodo_reporte()">
                <option value="hoy" <?= $periodo == 'hoy' ? 'selected' : '' ?>>Hoy</option>
                <option value="semana" <?= $periodo == 'semana' ? 'selected' : '' ?>>Esta semana</option>
                <option value="mes" <?= $periodo == 'mes' ? 'selected' : '' ?>>Este mes</option>
                <option value="rango" <?= $periodo == 'rango' ? 'selected' : '' ?>>Rango de fechas</option>
            </select>
        </div>
        <div class="col-md-3 rep-rango">
            <label>Desde</label>
            <input type="date" id="rep_desde" class="form-control" value="<?= $desde ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-3 rep-rango">
            <label>Hasta</label>
            <input type="date" id="rep_hasta" class="form-control" value="<?= $hasta ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-3 rep-rango">
            <button class="btn-dorado-outline w-100" onclick="cargar_reportes(parametros_reporte())">Consultar</button>
        </div>
    </div>
</div>

<?php include(__DIR__ . "/contenido_reportes.php"); ?>

<script>
$('.rep-rango').toggle($('#rep_periodo').val() === 'rango');

function parametros_reporte(){
    var p = { periodo: $('#rep_periodo').val() };
    if(p.periodo === 'rango'){
        p.desde = $('#rep_desde').val();
        p.hasta = $('#rep_hasta').val();
    }
    return p;
}

function cambiar_periodo_reporte(){
    var rango = $('#rep_periodo').val() === 'rango';
    $('.rep-rango').toggle(rango);
    if(!rango) cargar_reportes(parametros_reporte());
}

function imprimir_reportes(){
    window.open('Reportes/imprimir_reportes.php?' + $.param(parametros_reporte()), '_blank');
}
</script>
