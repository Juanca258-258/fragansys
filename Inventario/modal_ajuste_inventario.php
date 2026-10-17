<?php
require_once("../sesion.php");
require_once("../conexion.php");
if ($_SESSION['rol'] !== 'admin') exit;

$perfumes = mysqli_query($conn,
    "SELECT id, marca, nombre, ml_disponibles FROM perfumes WHERE activo = 1 ORDER BY marca, nombre");
?>
<div class="modal-header">
    <h5 class="modal-title">Ajuste de ml para decants</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="form_ajuste_ml" onsubmit="return false;">
    <div class="mb-3">
        <label>Perfume</label>
        <select name="perfume_id" id="sel_perfume_ajuste" class="form-select" required>
            <option value="">Selecciona un perfume...</option>
            <?php while ($p = mysqli_fetch_assoc($perfumes)) { ?>
            <option value="<?= $p['id'] ?>" data-ml="<?= (float)$p['ml_disponibles'] ?>">
                <?= htmlspecialchars($p['marca'] . ' — ' . $p['nombre']) ?> (registrados: <?= (float)$p['ml_disponibles'] ?> ml)
            </option>
            <?php } ?>
        </select>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>ml reales en el frasco abierto</label>
            <input type="number" name="ml_nuevo" id="ajuste_ml_nuevo" class="form-control" min="0" step="0.01" required oninput="mostrar_diferencia_ajuste()">
        </div>
        <div class="col-md-6 mb-3 d-flex align-items-end">
            <div class="small" id="ajuste_diferencia"></div>
        </div>
    </div>

    <div class="mb-3">
        <label>Motivo</label>
        <select name="motivo" class="form-select" required>
            <option value="">Selecciona el motivo...</option>
            <option value="inventario_inicial">Inventario inicial</option>
            <option value="conteo_fisico">Conteo físico</option>
            <option value="derrame">Derrame</option>
            <option value="evaporacion">Evaporación</option>
            <option value="otro">Otro</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Notas (obligatorias si el motivo es "Otro")</label>
        <input type="text" name="notas" class="form-control" maxlength="120">
    </div>
</form>
</div>

<div class="modal-footer">
    <button class="btn-dorado" id="btn_guardar_ajuste" onclick="guardar_ajuste_ml()">Guardar ajuste</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$('#sel_perfume_ajuste').select2({
    theme: 'bootstrap-5',
    dropdownParent: $('#modal_general'),
    width: '100%'
}).on('change', mostrar_diferencia_ajuste);

function mostrar_diferencia_ajuste(){
    var actual = parseFloat($('#sel_perfume_ajuste option:selected').data('ml'));
    var nuevo = parseFloat($('#ajuste_ml_nuevo').val());
    if(isNaN(actual) || isNaN(nuevo)){ $('#ajuste_diferencia').text(''); return; }
    var dif = Math.round((nuevo - actual) * 100) / 100;
    $('#ajuste_diferencia').html(actual + ' ml → ' + nuevo + ' ml <strong>(' + (dif > 0 ? '+' : '') + dif + ' ml)</strong>');
}

function guardar_ajuste_ml(){
    var form = document.getElementById('form_ajuste_ml');
    if(!$('#sel_perfume_ajuste').val()){
        Swal.fire({title:"Campo requerido",text:"Selecciona el perfume.",icon:"warning",confirmButtonColor:"#d33"});
        return;
    }
    if(!form.reportValidity()) return;

    var btn = $('#btn_guardar_ajuste').prop('disabled', true);
    $.post("Inventario/back_inventario.php", $(form).serialize() + "&accion=ajustar_ml", function(resp){
        var r = JSON.parse(resp);
        if(r.ok){
            $("#modal_general").modal('hide');
            Swal.fire({title:"Ajuste guardado",text:"Quedó registrado en el Historial.",icon:"success",timer:1500,showConfirmButton:false});
            cargar_inventario();
        } else {
            Swal.fire({title:"No se pudo guardar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        }
    }).always(function(){ btn.prop('disabled', false); });
}
</script>
