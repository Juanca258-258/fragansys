// Equivalente a carrito/modal_carrito_pub.php: filas del carrito (con +/- y eliminar),
// detalle desplegable de los combos y la tarjeta oscura "Resumen del Pedido".

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../helpers/helpers.dart';
import 'back_carrito_pub.dart';

class FilaCarrito extends StatelessWidget {
  const FilaCarrito({super.key, required this.item});

  final ItemCarrito item;

  void _cambiar(BuildContext context, int cantidad) {
    final error = context.read<CarritoCompras>().actualizarCantidad(item.clave, cantidad);
    if (error != null) mostrarMensaje(context, error, error: true);
  }

  @override
  Widget build(BuildContext context) {
    final contenido = Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 64,
            height: 64,
            padding: const EdgeInsets.all(4),
            decoration: BoxDecoration(
                color: ColoresFragansys.fondo, borderRadius: BorderRadius.circular(10)),
            child: ImagenProducto(
              ruta: item.imagen,
              alto: 56,
              icono: item.esCombo ? Icons.card_giftcard : Icons.water_drop_outlined,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.nombre,
                    style: const TextStyle(
                        fontFamily: fuenteSerif,
                        fontWeight: FontWeight.bold,
                        color: ColoresFragansys.cafe,
                        fontSize: 15)),
                const SizedBox(height: 4),
                _Insignia(
                  texto: item.esCombo
                      ? 'Combo • ${item.piezas} piezas'
                      : '${item.marca} • ${item.presentacion == 'frasco' ? 'Frasco ' : ''}${item.contenidoMl} ml',
                ),
                const SizedBox(height: 6),
                Text('${dinero(item.precio)} c/u',
                    style: const TextStyle(color: Colors.black54, fontSize: 13)),
                const SizedBox(height: 8),
                Row(
                  children: [
                    _SelectorCantidad(
                      cantidad: item.cantidad,
                      maximo: item.stock,
                      alCambiar: (c) => _cambiar(context, c),
                    ),
                    const Spacer(),
                    Text(dinero(item.subtotal),
                        style: const TextStyle(
                            fontWeight: FontWeight.bold, color: ColoresFragansys.cafe, fontSize: 16)),
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Eliminar producto',
            icon: const Icon(Icons.delete_outline, color: Colors.red),
            onPressed: () async {
              final carrito = context.read<CarritoCompras>();
              final ok = await showDialog<bool>(
                context: context,
                builder: (c) => AlertDialog(
                  title: const Text('Eliminar producto'),
                  content: const Text('Este producto se eliminará del carrito. ¿Está seguro?'),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Cancelar')),
                    FilledButton(
                      style: FilledButton.styleFrom(backgroundColor: Colors.red),
                      onPressed: () => Navigator.pop(c, true),
                      child: const Text('Eliminar'),
                    ),
                  ],
                ),
              );
              if (ok == true) carrito.eliminar(item.clave);
            },
          ),
        ],
      ),
    );

    return Card(
      elevation: 0,
      color: Colors.white,
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14), side: BorderSide(color: Colors.grey.shade200)),
      child: Column(
        children: [
          contenido,
          if (item.esCombo && item.productos.isNotEmpty) _DetalleCombo(item: item),
        ],
      ),
    );
  }
}

class _Insignia extends StatelessWidget {
  const _Insignia({required this.texto});

  final String texto;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: ColoresFragansys.fondo,
        border: Border.all(color: Colors.grey.shade300),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(texto, style: const TextStyle(fontSize: 12)),
    );
  }
}

class _SelectorCantidad extends StatelessWidget {
  const _SelectorCantidad({required this.cantidad, required this.maximo, required this.alCambiar});

  final int cantidad;
  final int maximo;
  final ValueChanged<int> alCambiar;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade300),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            visualDensity: VisualDensity.compact,
            icon: const Icon(Icons.remove, size: 18),
            onPressed: cantidad > 1 ? () => alCambiar(cantidad - 1) : null,
          ),
          Text('$cantidad',
              style: const TextStyle(fontWeight: FontWeight.bold, color: ColoresFragansys.cafe)),
          IconButton(
            visualDensity: VisualDensity.compact,
            icon: const Icon(Icons.add, size: 18),
            onPressed: cantidad < maximo ? () => alCambiar(cantidad + 1) : null,
          ),
        ],
      ),
    );
  }
}

/// Productos incluidos en el combo (oculto hasta que se despliega)
class _DetalleCombo extends StatelessWidget {
  const _DetalleCombo({required this.item});

  final ItemCarrito item;

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        dense: true,
        title: const Text('Incluye en cada combo',
            style: TextStyle(fontSize: 12, letterSpacing: 1, color: Colors.black54)),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        children: [
          for (final p in item.productos)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  SizedBox(width: 40, height: 40, child: ImagenProducto(ruta: p.imagen, alto: 36)),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(p.nombre,
                            style: const TextStyle(
                                fontWeight: FontWeight.w600, color: ColoresFragansys.cafe, fontSize: 13)),
                        Text('${p.marca} • ${p.tipo == 'frasco' ? 'Frasco' : 'Decant'} ${p.ml} ml',
                            style: const TextStyle(color: Colors.black54, fontSize: 12)),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                        color: ColoresFragansys.dorado, borderRadius: BorderRadius.circular(20)),
                    child: Text('× ${p.cantidad}',
                        style: const TextStyle(color: Colors.white, fontSize: 12)),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Tarjeta "Resumen del Pedido": subtotal, envío, cuánto falta para envío gratis y total
class ResumenPedido extends StatelessWidget {
  const ResumenPedido({super.key, required this.alProceder});

  final VoidCallback alProceder;

  @override
  Widget build(BuildContext context) {
    final carrito = context.watch<CarritoCompras>();
    const etiqueta = TextStyle(color: Colors.white60, fontFamily: fuenteSerif);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
          color: ColoresFragansys.tarjetaOscura, borderRadius: BorderRadius.circular(16)),
      child: Column(
        children: [
          const Text('Resumen del Pedido',
              style: TextStyle(
                  color: ColoresFragansys.dorado,
                  fontFamily: fuenteSerif,
                  fontSize: 20,
                  fontWeight: FontWeight.bold)),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Subtotal', style: etiqueta),
              Text(dinero(carrito.subtotal),
                  style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Envío', style: etiqueta),
              if (!carrito.resumenListo)
                const SizedBox(
                    width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2))
              else if (carrito.envio > 0)
                Text(dinero(carrito.envio),
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold))
              else
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                  decoration: BoxDecoration(
                      color: const Color(0xFFD1E7DD), borderRadius: BorderRadius.circular(20)),
                  child: const Text('Gratis',
                      style: TextStyle(
                          color: ColoresFragansys.exito, fontWeight: FontWeight.bold, fontSize: 12)),
                ),
            ],
          ),
          if (carrito.resumenListo && carrito.faltaEnvioGratis > 0)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text.rich(
                TextSpan(children: [
                  const WidgetSpan(
                      child: Icon(Icons.local_shipping_outlined, size: 16, color: Color(0xFFD9C3A5))),
                  const TextSpan(text: ' Agrega '),
                  TextSpan(
                      text: dinero(carrito.faltaEnvioGratis),
                      style: const TextStyle(fontWeight: FontWeight.bold)),
                  const TextSpan(text: ' más y tu envío es gratis.'),
                ]),
                textAlign: TextAlign.center,
                style: const TextStyle(color: Color(0xFFD9C3A5), fontSize: 13),
              ),
            ),
          const Divider(color: Colors.white24, height: 28),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Total Final',
                  style: TextStyle(
                      color: Colors.white, fontFamily: fuenteSerif, fontSize: 18, fontWeight: FontWeight.bold)),
              Text(carrito.resumenListo ? dinero(carrito.total) : '—',
                  style: const TextStyle(
                      color: ColoresFragansys.dorado, fontSize: 26, fontWeight: FontWeight.bold)),
            ],
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: carrito.resumenListo ? alProceder : null,
              icon: const Icon(Icons.credit_card),
              label: const Text('PROCEDER AL PAGO'),
            ),
          ),
        ],
      ),
    );
  }
}
