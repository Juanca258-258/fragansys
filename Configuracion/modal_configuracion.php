<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");

if ($_SESSION['rol'] !== 'admin') {
    echo '<div class="panel vacio">No tienes permiso para ver la configuración.</div>';
    exit;
}

$c = obtener_config($conn);
$e = function ($clave) use ($c) { return htmlspecialchars($c[$clave]); };
?>
<style>
.config-titulo { display:flex; align-items:center; gap:10px; margin-bottom:4px; }
.config-titulo i { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;
                   background:var(--dorado-claro); color:var(--dorado-oscuro); font-size:.85rem; }
.config-titulo h5 { font-size:.95rem; font-weight:600; margin:0; }
.config-desc { font-size:.78rem; color:var(--texto-suave); margin:0 0 16px 42px; }
</style>

<div class="modulo-cab compacto">
    <div class="modulo-icono"><i class="fa-solid fa-gear"></i></div>
    <div>
        <h1>Configuración</h1>
        <div class="sub">Datos del negocio, ticket, envíos e inventario</div>
    </div>
    <div class="acciones-cab">
        <button class="btn-dorado" id="btn_guardar_config" onclick="guardar_configuracion()"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
    </div>
</div>

<form id="form_configuracion" onsubmit="guardar_configuracion(); return false;">
<div class="row g-3">
    <div class="col-lg-7">
        <div class="panel h-100">
            <div class="config-titulo"><i class="fa-solid fa-store"></i><h5>Datos del negocio</h5></div>
            <p class="config-desc">Aparecen en la parte de arriba del ticket de venta.</p>
            <div class="mb-3">
                <label>Nombre del negocio</label>
                <input type="text" name="negocio_nombre" class="form-control" maxlength="100" value="<?= $e('negocio_nombre') ?>" required>
            </div>
            <div class="mb-3">
                <label>Dirección <span class="text-muted">(opcional)</span></label>
                <input type="text" name="negocio_direccion" class="form-control" maxlength="200" value="<?= $e('negocio_direccion') ?>">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Teléfono <span class="text-muted">(opcional)</span></label>
                    <input type="text" name="negocio_telefono" class="form-control" maxlength="20" value="<?= $e('negocio_telefono') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Correo <span class="text-muted">(opcional)</span></label>
                    <input type="email" name="negocio_correo" class="form-control" maxlength="150" value="<?= $e('negocio_correo') ?>">
                </div>
            </div>
            <div class="mb-1">
                <label>RFC <span class="text-muted">(opcional)</span></label>
                <input type="text" name="negocio_rfc" class="form-control" maxlength="13" value="<?= $e('negocio_rfc') ?>" style="text-transform:uppercase; max-width:260px">
            </div>
        </div>
    </div>

    <div class="col-lg-5 d-flex flex-column gap-3">
        <div class="panel">
            <div class="config-titulo"><i class="fa-solid fa-receipt"></i><h5>Ticket</h5></div>
            <p class="config-desc">Texto que aparece al final del ticket.</p>
            <label>Mensaje al pie</label>
            <input type="text" name="ticket_mensaje" class="form-control" maxlength="150" value="<?= $e('ticket_mensaje') ?>"
                   placeholder="Ej. ¡Gracias por su compra! No hay cambios en decants.">
        </div>

        <div class="panel">
            <div class="config-titulo"><i class="fa-solid fa-truck-fast"></i><h5>Envíos</h5></div>
            <p class="config-desc">Para los pedidos de la tienda en línea.</p>
            <div class="row">
                <div class="col-sm-6 mb-2">
                    <label>Costo de envío</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="envio_costo" class="form-control" min="0" step="0.01" value="<?= $e('envio_costo') ?>" required>
                    </div>
                </div>
                <div class="col-sm-6 mb-2">
                    <label>Envío gratis desde</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="envio_gratis_desde" class="form-control" min="0" step="0.01" value="<?= $e('envio_gratis_desde') ?>" placeholder="Nunca">
                    </div>
                </div>
            </div>
            <div class="small text-muted">Deja "Envío gratis desde" vacío si siempre se cobra el envío.</div>
        </div>

        <div class="panel">
            <div class="config-titulo"><i class="fa-solid fa-boxes-stacked"></i><h5>Inventario</h5></div>
            <p class="config-desc">Valor que se propone al dar de alta un perfume nuevo.</p>
            <label>Stock mínimo de frascos por defecto</label>
            <input type="number" name="stock_minimo_defecto" class="form-control" min="0" step="1" value="<?= $e('stock_minimo_defecto') ?>" required style="max-width:160px">
        </div>
    </div>
</div>
</form>

<script>
function guardar_configuracion(){
    var form = document.getElementById('form_configuracion');
    if(!form.reportValidity()) return;
    var btn = $('#btn_guardar_config').prop('disabled', true);
    $.post("Configuracion/back_configuracion.php", $(form).serialize() + "&accion=guardar", function(resp){
        var r = JSON.parse(resp);
        if(r.ok){
            Swal.fire({title:"Configuración guardada",icon:"success",timer:1400,showConfirmButton:false});
        } else {
            Swal.fire({title:"No se pudo guardar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        }
    }).always(function(){ btn.prop('disabled', false); });
}
</script>
