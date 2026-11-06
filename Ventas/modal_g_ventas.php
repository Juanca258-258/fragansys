<?php
require_once("../sesion.php");
require_once("../conexion.php");

// Todas las presentaciones activas (también las agotadas, que se muestran apagadas), agrupadas por perfume.
$res = mysqli_query($conn,
    "SELECT v.presentacion_id, v.perfume_id, v.marca, v.nombre, v.tipo, v.ml, v.presentacion, v.precio,
            pf.imagen, pf.frascos_sellados, pf.ml_disponibles
     FROM vw_catalogo_presentaciones v
     INNER JOIN perfumes pf ON pf.id = v.perfume_id
     ORDER BY v.marca, v.nombre, v.tipo = 'frasco', v.ml");
$perfumes = [];
while ($f = mysqli_fetch_assoc($res)) {
    $pid = $f['perfume_id'];
    if (!isset($perfumes[$pid])) {
        $perfumes[$pid] = [
            'id' => (int)$pid, 'nombre' => $f['nombre'], 'marca' => $f['marca'], 'imagen' => $f['imagen'],
            'frascos' => (int)$f['frascos_sellados'], 'ml' => (float)$f['ml_disponibles'], 'presentaciones' => [],
        ];
    }
    $perfumes[$pid]['presentaciones'][] = [
        'id' => (int)$f['presentacion_id'], 'tipo' => $f['tipo'], 'ml' => (float)$f['ml'],
        'precio' => (float)$f['precio'], 'nombre' => $f['presentacion'],
    ];
}

// Combos activos con sus piezas; se omiten los que incluyen algún producto que ya no está a la venta.
$res = mysqli_query($conn,
    "SELECT c.id, c.nombre, c.descripcion, c.precio, c.imagen, cp.cantidad, pr.tipo, pr.ml, pr.precio AS precio_pieza,
            pr.perfume_id, pf.nombre AS perfume, (pr.activo = 1 AND pf.activo = 1) AS activa
     FROM combos c
     INNER JOIN combo_presentaciones cp ON cp.combo_id = c.id
     INNER JOIN presentaciones pr ON pr.id = cp.presentacion_id
     INNER JOIN perfumes pf ON pf.id = pr.perfume_id
     WHERE c.activo = 1
     ORDER BY c.nombre, cp.id");
$combos = [];
$combos_invalidos = [];
while ($f = mysqli_fetch_assoc($res)) {
    $cid = $f['id'];
    if (!$f['activa']) $combos_invalidos[$cid] = true;
    if (!isset($combos[$cid])) {
        $combos[$cid] = ['id' => (int)$cid, 'nombre' => $f['nombre'], 'descripcion' => $f['descripcion'],
                         'precio' => (float)$f['precio'], 'imagen' => $f['imagen'], 'separado' => 0, 'piezas' => []];
    }
    $combos[$cid]['separado'] += $f['precio_pieza'] * $f['cantidad'];
    $combos[$cid]['piezas'][] = [
        'perfume_id' => (int)$f['perfume_id'], 'tipo' => $f['tipo'], 'ml' => (float)$f['ml'], 'cantidad' => (int)$f['cantidad'],
        'texto' => $f['cantidad'] . ' x ' . $f['perfume'] . ' ' . ($f['tipo'] === 'frasco' ? 'Frasco ' : 'Decant ') . (float)$f['ml'] . ' ml',
    ];
}
$combos = array_diff_key($combos, $combos_invalidos);
?>
<style>
.pos-productos { max-height: 62vh; overflow-y: auto; padding-right: 4px; }
.pos-card {
    background:#fff; border:1px solid var(--borde); border-radius:12px; padding:12px;
    height:100%; display:flex; flex-direction:column; gap:8px;
}
.pos-card-cab { display:flex; align-items:center; gap:10px; }
.pos-foto { width:48px; height:48px; border-radius:10px; object-fit:cover; border:1px solid var(--borde); flex-shrink:0; }
.pos-foto-vacia { display:flex; align-items:center; justify-content:center; background:var(--crema-2); color:var(--dorado-oscuro); }
.pos-nombre { font-weight:600; font-size:.9rem; line-height:1.2; }
.pos-marca { font-size:.72rem; color:var(--texto-suave); text-transform:uppercase; letter-spacing:.4px; }
.pos-opciones { display:flex; flex-wrap:wrap; gap:6px; }
.pos-btn {
    border:1px solid var(--borde); background:var(--crema); border-radius:8px; padding:6px 10px;
    font-size:.78rem; text-align:left; line-height:1.2; transition:border-color .12s, background .12s;
}
.pos-btn strong { display:block; font-size:.82rem; }
.pos-btn:hover:not(:disabled) { border-color:var(--dorado); background:var(--dorado-claro); }
.pos-btn:disabled { opacity:.4; cursor:not-allowed; }
.pos-btn .pos-en-venta { color:var(--dorado-oscuro); font-weight:700; }
.pos-seccion { font-size:.72rem; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:var(--texto-suave); margin:4px 0 8px; display:flex; align-items:center; gap:6px; }
.pos-card.combo { border-color:var(--dorado); background:linear-gradient(180deg, #fffaf0 0%, #fff 60%); }
.pos-piezas { font-size:.74rem; color:var(--texto-suave); line-height:1.35; }
.pos-btn-combo { width:100%; text-align:center; }

.pos-ticket { background:#fff; border:1px solid var(--borde); border-radius:12px; padding:14px; position:sticky; top:0; }
.pos-ticket h6 { font-weight:700; margin:0 0 10px; display:flex; align-items:center; gap:8px; }
.pos-items { max-height:30vh; overflow-y:auto; margin-bottom:10px; }
.pos-item { display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid #f4f0e6; font-size:.84rem; }
.pos-item-nombre { flex:1; min-width:0; }
.pos-item-nombre small { display:block; color:var(--texto-suave); }
.pos-cant { display:flex; align-items:center; gap:4px; }
.pos-cant button {
    width:26px; height:26px; border-radius:7px; border:1px solid var(--borde); background:#fff;
    font-weight:700; line-height:1; padding:0;
}
.pos-cant button:hover:not(:disabled) { border-color:var(--dorado); }
.pos-cant span { min-width:20px; text-align:center; font-weight:600; }
.pos-importe { width:78px; text-align:right; font-weight:600; }
.pos-total { display:flex; justify-content:space-between; align-items:baseline; padding:8px 0; border-top:2px solid var(--negro); margin-top:4px; }
.pos-total strong { font-size:1.5rem; }
.pos-metodos { display:flex; gap:6px; }
.pos-metodos label {
    flex:1; text-align:center; border:1px solid var(--borde); border-radius:8px; padding:7px 4px;
    font-size:.76rem; font-weight:600; cursor:pointer; margin:0;
}
.pos-metodos input { display:none; }
.pos-metodos input:checked + label { background:var(--negro); border-color:var(--negro); color:var(--dorado); }
.pos-cobrar { width:100%; padding:12px; font-size:1rem; }
</style>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa-solid fa-cash-register me-2" style="color:var(--dorado-oscuro)"></i>Nueva venta</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<div class="row g-3">
    <!-- Productos -->
    <div class="col-lg-8">
        <div class="input-group input-busqueda mb-3" style="max-width:none">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
            <input type="text" id="pos_buscar" class="form-control" placeholder="Buscar perfume o marca..." autocomplete="off">
        </div>
        <div class="pos-productos">
            <?php if (empty($perfumes) && empty($combos)): ?>
                <div class="vacio">No hay perfumes ni combos a la venta.</div>
            <?php endif; ?>

            <?php if ($combos): ?>
            <div class="pos-seccion pos-titulo-seccion"><i class="fa-solid fa-gift"></i> Combos</div>
            <div class="row g-2 mb-3">
                <?php foreach ($combos as $c): $ahorro = $c['separado'] - $c['precio']; ?>
                <div class="col-sm-6 col-xl-4 pos-tarjeta" data-buscar="<?= htmlspecialchars(mb_strtolower($c['nombre'] . ' combo ' . implode(' ', array_column($c['piezas'], 'texto')))) ?>">
                    <div class="pos-card combo">
                        <div class="pos-card-cab">
                            <?php if ($c['imagen']): ?>
                                <img src="<?= htmlspecialchars($c['imagen']) ?>" alt="" class="pos-foto">
                            <?php else: ?>
                                <div class="pos-foto pos-foto-vacia"><i class="fa-solid fa-gift"></i></div>
                            <?php endif; ?>
                            <div>
                                <div class="pos-nombre"><?= htmlspecialchars($c['nombre']) ?></div>
                                <?php if ($ahorro > 0): ?>
                                <span class="estado bien" style="font-size:.66rem"><i class="fa-solid fa-tag"></i> Ahorra $<?= number_format($ahorro, 2) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pos-piezas">
                            <?php foreach ($c['piezas'] as $pz): ?><div><?= htmlspecialchars($pz['texto']) ?></div><?php endforeach; ?>
                        </div>
                        <button type="button" class="pos-btn pos-btn-combo mt-auto" data-combo="<?= $c['id'] ?>" onclick="agregar_a_venta('c', <?= $c['id'] ?>)">
                            Agregar combo <strong>$<?= number_format($c['precio'], 2) ?></strong>
                            <span class="pos-en-venta"></span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($perfumes): ?>
            <div class="pos-seccion pos-titulo-seccion"><i class="fa-solid fa-spray-can-sparkles"></i> Perfumes</div>
            <?php endif; ?>
            <div class="row g-2" id="pos_tarjetas">
                <?php foreach ($perfumes as $p): ?>
                <div class="col-sm-6 col-xl-4 pos-tarjeta" data-buscar="<?= htmlspecialchars(mb_strtolower($p['nombre'] . ' ' . $p['marca'])) ?>">
                    <div class="pos-card">
                        <div class="pos-card-cab">
                            <?php if ($p['imagen']): ?>
                                <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="" class="pos-foto">
                            <?php else: ?>
                                <div class="pos-foto pos-foto-vacia"><i class="fa-solid fa-spray-can-sparkles"></i></div>
                            <?php endif; ?>
                            <div>
                                <div class="pos-nombre"><?= htmlspecialchars($p['nombre']) ?></div>
                                <div class="pos-marca"><?= htmlspecialchars($p['marca']) ?></div>
                            </div>
                        </div>
                        <div class="pos-opciones">
                            <?php foreach ($p['presentaciones'] as $pr): ?>
                            <button type="button" class="pos-btn" data-presentacion="<?= $pr['id'] ?>"
                                    onclick="agregar_a_venta('p', <?= $pr['id'] ?>)">
                                <?= htmlspecialchars($pr['nombre']) ?>
                                <strong>$<?= number_format($pr['precio'], 2) ?></strong>
                                <span class="pos-en-venta"></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="vacio" id="pos_sin_resultados" style="display:none">Ningún perfume coincide con la búsqueda.</div>
        </div>
    </div>

    <!-- Ticket -->
    <div class="col-lg-4">
        <div class="pos-ticket">
            <h6><i class="fa-solid fa-receipt" style="color:var(--dorado-oscuro)"></i> Venta</h6>
            <div class="pos-items" id="items_venta"></div>

            <div class="pos-total">
                <span class="text-muted">Total</span>
                <strong id="total_venta">$0.00</strong>
            </div>

            <form id="form_venta" onsubmit="return false;">
                <div class="small text-muted mb-1">Datos del cliente (opcional)</div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><input type="text" name="cliente_nombre" class="form-control form-control-sm" maxlength="120" placeholder="Cliente"></div>
                    <div class="col-6"><input type="text" name="cliente_telefono" class="form-control form-control-sm" maxlength="20" placeholder="Teléfono"></div>
                </div>

                <div class="pos-metodos mb-2">
                    <input type="radio" name="metodo_pago" id="mp_efectivo" value="efectivo" checked onchange="actualizar_cambio()">
                    <label for="mp_efectivo"><i class="fa-solid fa-money-bill-wave"></i><br>Efectivo</label>
                    <input type="radio" name="metodo_pago" id="mp_transferencia" value="transferencia" onchange="actualizar_cambio()">
                    <label for="mp_transferencia"><i class="fa-solid fa-building-columns"></i><br>Transferencia</label>
                    <input type="radio" name="metodo_pago" id="mp_tarjeta" value="tarjeta" onchange="actualizar_cambio()">
                    <label for="mp_tarjeta"><i class="fa-solid fa-credit-card"></i><br>Tarjeta</label>
                </div>

                <div class="row g-2 mb-3 grupo-efectivo align-items-center">
                    <div class="col-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Recibido $</span>
                            <input type="number" name="monto_recibido" id="recibido_venta" class="form-control" min="0" step="0.01" oninput="actualizar_cambio()">
                        </div>
                    </div>
                    <div class="col-5 text-end small">Cambio: <strong id="cambio_venta">$0.00</strong></div>
                </div>
            </form>

            <button class="btn-dorado pos-cobrar" id="btn_cobrar" onclick="cobrar_venta()">
                <i class="fa-solid fa-cash-register"></i> Cobrar <span id="cobrar_monto"></span>
            </button>
        </div>
    </div>
</div>
</div>

<script>
var POS_PERFUMES = <?= json_encode(array_values($perfumes)) ?>;
var POS_COMBOS = <?= json_encode(array_values($combos)) ?>;
var items_venta = [];   // { tipo: 'p' | 'c', id, nombre, detalle, precio, cantidad }

var POS_STOCK = {};     // perfume_id -> { frascos, ml }
var POS_PRES = {};      // presentacion_id -> { pres, perfume }
var POS_COMBO = {};     // combo_id -> combo
POS_PERFUMES.forEach(function(pf){
    POS_STOCK[pf.id] = { frascos: pf.frascos, ml: pf.ml };
    pf.presentaciones.forEach(function(pr){ POS_PRES[pr.id] = { pres: pr, perfume: pf }; });
});
POS_COMBOS.forEach(function(c){ POS_COMBO[c.id] = c; });

function dinero(n){
    return '$' + Number(n).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function total_venta(){
    return items_venta.reduce(function(s, it){ return s + it.precio * it.cantidad; }, 0);
}

// Lo que ocupa UNA unidad de un producto, por perfume: los decants usan ml del frasco abierto
// y los frascos completos usan frascos sellados. Un combo ocupa lo de todas sus piezas.
function ocupa(tipo, id){
    var piezas = tipo === 'p'
        ? [{ perfume_id: POS_PRES[id].perfume.id, tipo: POS_PRES[id].pres.tipo, ml: POS_PRES[id].pres.ml, cantidad: 1 }]
        : POS_COMBO[id].piezas;
    var r = {};
    piezas.forEach(function(pz){
        r[pz.perfume_id] = r[pz.perfume_id] || { frascos: 0, ml: 0 };
        if(pz.tipo === 'frasco') r[pz.perfume_id].frascos += pz.cantidad;
        else r[pz.perfume_id].ml += pz.ml * pz.cantidad;
    });
    return r;
}

function cabe_uno_mas(tipo, id){
    var usado = {};
    items_venta.forEach(function(it){
        var o = ocupa(it.tipo, it.id);
        Object.keys(o).forEach(function(pid){
            usado[pid] = usado[pid] || { frascos: 0, ml: 0 };
            usado[pid].frascos += o[pid].frascos * it.cantidad;
            usado[pid].ml += o[pid].ml * it.cantidad;
        });
    });
    var nuevo = ocupa(tipo, id);
    return Object.keys(nuevo).every(function(pid){
        var u = usado[pid] || { frascos: 0, ml: 0 };
        var s = POS_STOCK[pid] || { frascos: 0, ml: 0 };
        return u.frascos + nuevo[pid].frascos <= s.frascos && u.ml + nuevo[pid].ml <= s.ml + 1e-9;
    });
}

function agregar_a_venta(tipo, id){
    if(!cabe_uno_mas(tipo, id)) return;
    var existente = items_venta.find(function(it){ return it.tipo === tipo && it.id === id; });
    if(existente){
        existente.cantidad++;
    } else if(tipo === 'p'){
        var info = POS_PRES[id];
        items_venta.push({ tipo: 'p', id: id, nombre: info.perfume.nombre, detalle: info.pres.nombre, precio: info.pres.precio, cantidad: 1 });
    } else {
        var c = POS_COMBO[id];
        items_venta.push({ tipo: 'c', id: id, nombre: c.nombre, detalle: 'Combo', precio: c.precio, cantidad: 1 });
    }
    pintar_items();
}

function cambiar_cantidad(i, delta){
    if(delta > 0 && !cabe_uno_mas(items_venta[i].tipo, items_venta[i].id)) return;
    items_venta[i].cantidad += delta;
    if(items_venta[i].cantidad <= 0) items_venta.splice(i, 1);
    pintar_items();
}

function pintar_items(){
    var cont = $('#items_venta').empty();
    if(items_venta.length === 0){
        cont.append('<div class="vacio py-3">Toca una presentación para agregarla.</div>');
    }
    items_venta.forEach(function(it, i){
        var fila = $('<div class="pos-item">');
        var nombre = $('<div class="pos-item-nombre">').text(it.nombre);
        if(it.tipo === 'c') nombre.prepend('<i class="fa-solid fa-gift me-1" style="color:var(--dorado-oscuro)"></i>');
        fila.append(nombre.append($('<small>').text(it.detalle + ' · ' + dinero(it.precio))));
        var mas = $('<button type="button" title="Uno más">+</button>').on('click', function(){ cambiar_cantidad(i, 1); });
        if(!cabe_uno_mas(it.tipo, it.id)) mas.prop('disabled', true).attr('title', 'No hay más inventario');
        fila.append($('<div class="pos-cant">')
            .append($('<button type="button" title="Uno menos">−</button>').on('click', function(){ cambiar_cantidad(i, -1); }))
            .append($('<span>').text(it.cantidad))
            .append(mas));
        fila.append($('<div class="pos-importe">').text(dinero(it.precio * it.cantidad)));
        cont.append(fila);
    });

    // Botones de presentación: apagados si ya no cabe otra; marcan cuántas van en la venta.
    $('.pos-btn').each(function(){
        var tipo = $(this).data('combo') ? 'c' : 'p';
        var id = parseInt(tipo === 'c' ? $(this).data('combo') : $(this).data('presentacion'));
        var en_venta = items_venta.find(function(it){ return it.tipo === tipo && it.id === id; });
        var cabe = cabe_uno_mas(tipo, id);
        $(this).prop('disabled', !cabe)
               .attr('title', cabe ? 'Agregar a la venta' : 'Sin inventario suficiente')
               .find('.pos-en-venta').text(en_venta ? '× ' + en_venta.cantidad + ' en la venta' : '');
    });

    var total = total_venta();
    $('#total_venta').text(dinero(total));
    $('#cobrar_monto').text(total > 0 ? dinero(total) : '');
    actualizar_cambio();
}

function actualizar_cambio(){
    var efectivo = $('input[name=metodo_pago]:checked').val() === 'efectivo';
    $('.grupo-efectivo').toggle(efectivo);
    var cambio = (parseFloat($('#recibido_venta').val()) || 0) - total_venta();
    $('#cambio_venta').text(dinero(Math.max(cambio, 0)));
}

$('#pos_buscar').on('input', function(){
    var texto = this.value.toLowerCase().trim();
    var visibles = 0;
    $('.pos-tarjeta').each(function(){
        var coincide = !texto || $(this).data('buscar').indexOf(texto) !== -1;
        $(this).toggle(coincide);
        if(coincide) visibles++;
    });
    $('#pos_sin_resultados').toggle(visibles === 0 && (POS_PERFUMES.length + POS_COMBOS.length) > 0);
    $('.pos-titulo-seccion').toggle(!texto);
});

function cobrar_venta(){
    if(items_venta.length === 0){
        Swal.fire({title:"La venta está vacía",text:"Agrega al menos un producto.",icon:"warning",confirmButtonColor:"#d33"});
        return;
    }
    if($('input[name=metodo_pago]:checked').val() === 'efectivo' && (parseFloat($('#recibido_venta').val()) || 0) < total_venta()){
        Swal.fire({title:"Efectivo insuficiente",text:"El efectivo recibido no cubre el total.",icon:"warning",confirmButtonColor:"#d33"});
        return;
    }

    var datos = $('#form_venta').serializeArray();
    datos.push({ name: 'accion', value: 'guardar' });
    items_venta.forEach(function(it){
        if(it.tipo === 'c'){
            datos.push({ name: 'combo_id[]', value: it.id });
            datos.push({ name: 'cantidad_combo[]', value: it.cantidad });
        } else {
            datos.push({ name: 'presentacion_id[]', value: it.id });
            datos.push({ name: 'cantidad[]', value: it.cantidad });
        }
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

pintar_items();
setTimeout(function(){ $('#pos_buscar').trigger('focus'); }, 300);
</script>
