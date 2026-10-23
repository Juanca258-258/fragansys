<?php
require_once("../sesion.php");
require_once("../conexion.php");

// La vista ya calcula cuántas unidades alcanzan con el inventario actual.
$res = mysqli_query($conn,
    "SELECT presentacion_id AS id, nombre, marca, presentacion, precio, unidades_disponibles AS disponibles
     FROM vw_catalogo_presentaciones
     WHERE disponible = 1
     ORDER BY marca, nombre, tipo = 'frasco', ml");
$opciones = [];
while ($p = mysqli_fetch_assoc($res)) {
    $p['etiqueta'] = $p['nombre'] . ' — ' . $p['presentacion'];
    $opciones[] = $p;
}
?>
<div class="modal-header">
    <h5 class="modal-title">Nueva venta</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
    <div class="row g-2 align-items-end mb-3">
        <div class="col-md-8">
            <label>Producto</label>
            <select id="sel_producto_venta" class="form-select">
                <option value="">Busca un perfume...</option>
                <?php foreach ($opciones as $o): ?>
                <option value="<?= $o['id'] ?>"
                        data-nombre="<?= htmlspecialchars($o['etiqueta']) ?>"
                        data-precio="<?= $o['precio'] ?>"
                        data-disponibles="<?= (int)$o['disponibles'] ?>">
                    <?= htmlspecialchars($o['marca'] . ' · ' . $o['etiqueta']) ?> · $<?= number_format($o['precio'], 2) ?> (disponibles: <?= (int)$o['disponibles'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label>Cantidad</label>
            <input type="number" id="cant_producto_venta" class="form-control" min="1" step="1" value="1">
        </div>
        <div class="col-md-2">
            <button type="button" id="btn_agregar_venta" class="btn-dorado w-100" onclick="agregar_a_venta()"><i class="fa-solid fa-plus"></i> Agregar</button>
        </div>
    </div>
    <?php if (empty($opciones)): ?>
    <div class="form-text text-danger mb-2">No hay productos con inventario disponible.</div>
    <?php endif; ?>

    <table class="tabla-simple mb-3">
        <thead>
        <tr><th>Producto</th><th class="text-end" style="width:90px">Cant.</th><th class="text-end">P. unit.</th><th class="text-end">Importe</th><th></th></tr>
        </thead>
        <tbody id="items_venta">
            <tr><td colspan="5" class="vacio">Agrega productos a la venta.</td></tr>
        </tbody>
    </table>

    <div class="text-end mb-3">
        <span class="text-muted">Total:</span>
        <strong class="fs-5" id="total_venta">$0.00</strong>
    </div>

    <form id="form_venta" onsubmit="return false;">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Cliente (opcional)</label>
                <input type="text" name="cliente_nombre" class="form-control" maxlength="120">
            </div>
            <div class="col-md-6 mb-3">
                <label>Teléfono (opcional)</label>
                <input type="text" name="cliente_telefono" class="form-control" maxlength="20">
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label>Método de pago</label>
                <select name="metodo_pago" id="metodo_pago_venta" class="form-select" onchange="actualizar_cambio()">
                    <option value="efectivo">Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="tarjeta">Tarjeta</option>
                </select>
            </div>
            <div class="col-md-4 mb-3 grupo-efectivo">
                <label>Efectivo recibido</label>
                <input type="number" name="monto_recibido" id="recibido_venta" class="form-control" min="0" step="0.01" oninput="actualizar_cambio()">
            </div>
            <div class="col-md-4 mb-3 grupo-efectivo d-flex align-items-end">
                <div>
                    <span class="text-muted small">Cambio:</span>
                    <strong id="cambio_venta">$0.00</strong>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal-footer">
    <button class="btn-dorado" id="btn_cobrar" onclick="cobrar_venta()"><i class="fa-solid fa-cash-register"></i> Cobrar e imprimir ticket</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
var items_venta = [];

$('#sel_producto_venta').select2({
    theme: 'bootstrap-5',
    dropdownParent: $('#modal_general'),
    width: '100%'
});

// Enter sostenido repetía el clic: el segundo encontraba el buscador ya vacío y mostraba la alerta.
$('#btn_agregar_venta').on('keydown', function(e){
    if(e.key !== 'Enter') return;
    e.preventDefault();
    if(!e.originalEvent.repeat) agregar_a_venta();
});

function dinero(n){
    return '$' + Number(n).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function total_venta(){
    return items_venta.reduce(function(s, it){ return s + it.precio * it.cantidad; }, 0);
}

function agregar_a_venta(){
    var opt = $('#sel_producto_venta option:selected');
    var id = $('#sel_producto_venta').val();
    var cant = parseInt($('#cant_producto_venta').val()) || 0;
    if(!id){ Swal.fire({title:"Selecciona un producto",icon:"warning",confirmButtonColor:"#d33"}); return; }
    if(cant <= 0){ Swal.fire({title:"La cantidad debe ser al menos 1",icon:"warning",confirmButtonColor:"#d33"}); return; }

    var disponibles = parseInt(opt.data('disponibles'));
    var existente = items_venta.find(function(it){ return it.id === id; });
    var nueva = (existente ? existente.cantidad : 0) + cant;
    if(nueva > disponibles){
        Swal.fire({title:"Sin inventario suficiente",text:"Solo hay " + disponibles + " disponibles de este producto.",icon:"warning",confirmButtonColor:"#d33"});
        return;
    }
    if(existente){
        existente.cantidad = nueva;
    } else {
        items_venta.push({ id: id, nombre: opt.data('nombre'), precio: parseFloat(opt.data('precio')), disponibles: disponibles, cantidad: cant });
    }
    $('#sel_producto_venta').val('').trigger('change');
    $('#cant_producto_venta').val(1);
    pintar_items();
}

function cambiar_cantidad(i, valor){
    var cant = parseInt(valor) || 0;
    if(cant <= 0){ quitar_item(i); return; }
    if(cant > items_venta[i].disponibles){
        Swal.fire({title:"Sin inventario suficiente",text:"Solo hay " + items_venta[i].disponibles + " disponibles.",icon:"warning",confirmButtonColor:"#d33"});
        cant = items_venta[i].disponibles;
    }
    items_venta[i].cantidad = cant;
    pintar_items();
}

function quitar_item(i){
    items_venta.splice(i, 1);
    pintar_items();
}

function pintar_items(){
    var tbody = $('#items_venta').empty();
    if(items_venta.length === 0){
        tbody.append('<tr><td colspan="5" class="vacio">Agrega productos a la venta.</td></tr>');
    }
    items_venta.forEach(function(it, i){
        var fila = $('<tr>');
        fila.append($('<td>').text(it.nombre));
        fila.append($('<td class="text-end">').append(
            $('<input type="number" min="1" step="1" class="form-control form-control-sm text-end">')
                .val(it.cantidad).on('change', function(){ cambiar_cantidad(i, this.value); })
        ));
        fila.append($('<td class="text-end">').text(dinero(it.precio)));
        fila.append($('<td class="text-end fw-semibold">').text(dinero(it.precio * it.cantidad)));
        fila.append($('<td class="text-end">').append(
            $('<button type="button" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-xmark"></i></button>')
                .on('click', function(){ quitar_item(i); })
        ));
        tbody.append(fila);
    });
    $('#total_venta').text(dinero(total_venta()));
    actualizar_cambio();
}

function actualizar_cambio(){
    var efectivo = $('#metodo_pago_venta').val() === 'efectivo';
    $('.grupo-efectivo').toggle(efectivo);
    var cambio = (parseFloat($('#recibido_venta').val()) || 0) - total_venta();
    $('#cambio_venta').text(dinero(Math.max(cambio, 0)));
}

function cobrar_venta(){
    if(items_venta.length === 0){
        Swal.fire({title:"La venta está vacía",text:"Agrega al menos un producto.",icon:"warning",confirmButtonColor:"#d33"});
        return;
    }
    if($('#metodo_pago_venta').val() === 'efectivo' && (parseFloat($('#recibido_venta').val()) || 0) < total_venta()){
        Swal.fire({title:"Efectivo insuficiente",text:"El efectivo recibido no cubre el total.",icon:"warning",confirmButtonColor:"#d33"});
        return;
    }

    var datos = $('#form_venta').serializeArray();
    datos.push({ name: 'accion', value: 'guardar' });
    items_venta.forEach(function(it){
        datos.push({ name: 'presentacion_id[]', value: it.id });
        datos.push({ name: 'cantidad[]', value: it.cantidad });
    });

    // La ventana se abre aquí, durante el clic, para que el navegador no la bloquee como ventana emergente.
    var ventana_ticket = window.open('', '_blank');
    var btn = $('#btn_cobrar').prop('disabled', true);

    $.post("Ventas/back_ventas.php", $.param(datos), function(resp){
        var r = JSON.parse(resp);
        if(r.ok){
            if(ventana_ticket){ ventana_ticket.location = 'Ventas/ticket.php?id=' + r.venta_id + '&imprimir=1'; }
            $("#modal_general").modal('hide');
            Swal.fire({title:"Venta registrada",text:"Folio V-" + String(r.venta_id).padStart(6, '0'),icon:"success",timer:1800,showConfirmButton:false});
            cargar_ventas();
        } else {
            if(ventana_ticket){ ventana_ticket.close(); }
            Swal.fire({title:"No se pudo cobrar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        }
    }).fail(function(){
        if(ventana_ticket){ ventana_ticket.close(); }
        Swal.fire({title:"Error del servidor",text:"No se pudo registrar la venta.",icon:"error",confirmButtonColor:"#d33"});
    }).always(function(){ btn.prop('disabled', false); });
}

actualizar_cambio();
</script>
