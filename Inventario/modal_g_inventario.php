<?php
require_once("../sesion.php");
require_once("../conexion.php");
if ($_SESSION['rol'] !== 'admin') exit;

$perfumes = mysqli_query($conn,
    "SELECT id, marca, nombre, frascos_sellados FROM perfumes WHERE activo = 1 ORDER BY marca, nombre");
?>
<div class="modal-header">
    <h5 class="modal-title">Registrar entrada de mercancía</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="form_g_inventario">
    <div class="mb-3">
        <label>Perfume</label>
        <select name="perfume_id" id="sel_perfume_inv" class="form-select" required>
            <option value="">Selecciona un perfume...</option>
            <?php while ($p = mysqli_fetch_assoc($perfumes)) { ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['marca'] . ' — ' . $p['nombre']) ?> (hay <?= (int)$p['frascos_sellados'] ?> sellados)</option>
            <?php } ?>
        </select>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label>Fecha de llegada</label>
            <input type="date" name="fecha_entrada" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-4 mb-3">
            <label>Frascos que llegaron</label>
            <input type="number" name="frascos" id="inv_frascos" class="form-control" step="1" min="1" required oninput="calcular_total_entrada()">
        </div>
        <div class="col-md-4 mb-3">
            <label>Costo por frasco</label>
            <input type="number" name="costo_unitario" id="inv_costo" class="form-control" step="0.01" min="0" required oninput="calcular_total_entrada()">
        </div>
    </div>

    <div class="mb-3 text-end">
        <span class="text-muted small">Costo total:</span>
        <strong id="inv_total">$0.00</strong>
    </div>

    <div class="mb-3">
        <label>Proveedor (opcional)</label>
        <input type="text" name="proveedor" class="form-control" maxlength="120">
    </div>
    <div class="mb-3">
        <label>Notas (opcional)</label>
        <input type="text" name="notas" class="form-control" maxlength="255">
    </div>
</form>
</div>

<div class="modal-footer">
    <button class="btn-dorado" id="btn_guardar_entrada" onclick="guardar_entrada()">Registrar</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$('#sel_perfume_inv').select2({
    theme: 'bootstrap-5',
    dropdownParent: $('#modal_general'),
    width: '100%'
});

function calcular_total_entrada(){
    var total = (parseInt($('#inv_frascos').val()) || 0) * (parseFloat($('#inv_costo').val()) || 0);
    $('#inv_total').text('$' + total.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
}

function guardar_entrada(){
    var form = document.getElementById('form_g_inventario');
    if(!$('#sel_perfume_inv').val()){
        Swal.fire({title:"Campo requerido",text:"Selecciona el perfume.",icon:"warning",confirmButtonColor:"#d33"});
        return;
    }
    if(!form.reportValidity()) return;

    var btn = $('#btn_guardar_entrada');
    btn.prop('disabled', true);
    $.post("Inventario/back_inventario.php", $(form).serialize() + "&accion=guardar", function(resp){
        var r = JSON.parse(resp);
        if(r.ok){
            $("#modal_general").modal('hide');
            Swal.fire({title:"Entrada registrada",text:"Los frascos se sumaron al inventario.",icon:"success",timer:1500,showConfirmButton:false});
            cargar_inventario();
        } else {
            Swal.fire({title:"No se pudo registrar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        }
    }).always(function(){ btn.prop('disabled', false); });
}
</script>
