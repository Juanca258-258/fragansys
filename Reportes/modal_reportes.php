<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once(__DIR__ . "/rep_comun.php");

if ($_SESSION['rol'] !== 'admin') {
    echo '<div class="panel vacio">No tienes permiso para ver los reportes.</div>';
    exit;
}
$hoy = date('Y-m-d');
$periodos = [
    'hoy'    => ['Hoy', $hoy],
    'semana' => ['Esta semana', date('Y-m-d', strtotime('monday this week'))],
    'mes'    => ['Este mes', date('Y-m-01')],
];
?>
<div class="modulo-cab compacto">
    <div class="modulo-icono"><i class="fa-solid fa-chart-column"></i></div>
    <div>
        <h1>Reportes</h1>
        <div class="sub">Consulta e imprime la información del negocio</div>
    </div>
    <div class="acciones-cab">
        <button class="btn-dorado-outline" onclick="imprimir_reporte()"><i class="fa-solid fa-print"></i> Imprimir / PDF</button>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
    <div class="rep-tabs">
        <?php foreach (REPORTES as $clave => [$titulo, , $icono]): ?>
        <button type="button" class="rep-tab" data-tipo="<?= $clave ?>" onclick="elegir_reporte('<?= $clave ?>')"><i class="fa-solid <?= $icono ?>"></i> <?= rp_e($titulo) ?></button>
        <?php endforeach; ?>
    </div>
    <div class="rep-periodo" id="rep_periodo">
        <?php foreach ($periodos as $clave => [$texto, $desde]): ?>
        <button type="button" class="rep-per" data-per="<?= $clave ?>" onclick="elegir_periodo('<?= $clave ?>', '<?= $desde ?>', '<?= $hoy ?>')"><?= $texto ?></button>
        <?php endforeach; ?>
        <button type="button" class="rep-per" data-per="rango" onclick="elegir_periodo('rango')"><i class="fa-regular fa-calendar"></i> Otro rango</button>
        <div class="rep-rango" id="rep_rango">
            <input type="date" id="rep_desde" class="form-control" max="<?= $hoy ?>" onchange="generar_reporte()">
            a
            <input type="date" id="rep_hasta" class="form-control" max="<?= $hoy ?>" onchange="generar_reporte()">
        </div>
    </div>
</div>

<div id="rep_resultado"></div>

<script>
var REP_USA_FECHAS = <?= json_encode(array_map(function ($r) { return $r[1]; }, REPORTES)) ?>;
var rep_tipo = 'ventas';

function elegir_reporte(tipo){
    rep_tipo = tipo;
    $('.rep-tab').removeClass('activa').filter('[data-tipo="' + tipo + '"]').addClass('activa');
    $('#rep_periodo').toggle(REP_USA_FECHAS[tipo]);
    generar_reporte();
}

function elegir_periodo(per, desde, hasta){
    $('.rep-per').removeClass('activa').filter('[data-per="' + per + '"]').addClass('activa');
    $('#rep_rango').css('display', per === 'rango' ? 'flex' : 'none');
    if(per !== 'rango'){
        $('#rep_desde').val(desde);
        $('#rep_hasta').val(hasta);
    }
    generar_reporte();
}

function parametros_reporte(){
    return { tipo: rep_tipo, desde: $('#rep_desde').val(), hasta: $('#rep_hasta').val() };
}

function generar_reporte(){
    $('#rep_resultado').css('opacity', .5).load('Reportes/generar.php?' + $.param(parametros_reporte()), function(){
        $(this).css('opacity', 1);
    });
}

function imprimir_reporte(){
    // 'noopener' la abre como pestaña independiente: su cuadro de imprimir ya no congela esta página.
    window.open('Reportes/imprimir_reportes.php?' + $.param(parametros_reporte()), '_blank', 'noopener');
}

// Al entrar: Ventas de hoy (sirve como corte de caja).
$('.rep-tab[data-tipo="ventas"]').addClass('activa');
elegir_periodo('hoy', '<?= $hoy ?>', '<?= $hoy ?>');
</script>
