// Equivalente a sesion-cliente/modalg_pedidos_cliente.php: ventana con el detalle de un pedido
// (productos, totales, datos de entrega y seguimiento).

import 'package:material_ui/material_ui.dart';

import '../../conexion/conexion_api.dart';
import '../../helpers/helpers.dart';
import 'back_pedidos_cliente.dart';
import 'modal_pedidos_cliente.dart';

Future<void> mostrarDetallePedido(BuildContext context, int pedidoId) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    backgroundColor: Colors.white,
    builder: (_) => DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.85,
      maxChildSize: 0.95,
      builder: (context, scroll) => _DetallePedido(pedidoId: pedidoId, scroll: scroll),
    ),
  );
}

class _DetallePedido extends StatefulWidget {
  const _DetallePedido({required this.pedidoId, required this.scroll});

  final int pedidoId;
  final ScrollController scroll;

  @override
  State<_DetallePedido> createState() => _DetallePedidoState();
}

class _DetallePedidoState extends State<_DetallePedido> {
  late Future<DetallePedido> _detalle = BackPedidosCliente.detalle(widget.pedidoId);

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<DetallePedido>(
      future: _detalle,
      builder: (context, foto) {
        if (foto.hasError) {
          return VistaError(
            mensaje: foto.error is ApiException ? (foto.error as ApiException).mensaje : 'Ocurrió un error.',
            alReintentar: () => setState(() => _detalle = BackPedidosCliente.detalle(widget.pedidoId)),
          );
        }
        final d = foto.data;
        if (d == null) return const Center(child: CircularProgressIndicator());

        return ListView(
          controller: widget.scroll,
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
          children: [
            Row(
              children: [
                Text('Pedido #${d.pedido.id}',
                    style: const TextStyle(
                        fontFamily: fuenteSerif,
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                        color: ColoresFragansys.cafe)),
                const Spacer(),
                InsigniaPago(pedido: d.pedido),
              ],
            ),
            Text(d.pedido.fechaPedido, style: const TextStyle(color: Colors.black54)),
            const SizedBox(height: 16),
            SeguimientoPedido(pedido: d.pedido),
            DatosEnvio(pedido: d.pedido),
            const Divider(height: 32),
            const Text('Productos', style: TextStyle(fontWeight: FontWeight.bold, color: ColoresFragansys.cafe)),
            const SizedBox(height: 8),
            for (final item in d.items)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  children: [
                    Container(
                      width: 52,
                      height: 52,
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                          color: ColoresFragansys.fondo, borderRadius: BorderRadius.circular(8)),
                      child: ImagenProducto(
                        ruta: item.imagen,
                        alto: 44,
                        icono: item.marca == 'Combo' ? Icons.card_giftcard : Icons.water_drop_outlined,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(item.nombre, style: const TextStyle(fontWeight: FontWeight.w600)),
                          Text('${item.marca} · ${item.cantidad} × ${dinero(item.precioUnitario)}',
                              style: const TextStyle(color: Colors.black54, fontSize: 12)),
                        ],
                      ),
                    ),
                    Text(dinero(item.subtotal), style: const TextStyle(fontWeight: FontWeight.bold)),
                  ],
                ),
              ),
            const Divider(height: 24),
            _fila('Subtotal', dinero(d.subtotal)),
            _fila('Envío', d.costoEnvio > 0 ? dinero(d.costoEnvio) : 'Gratis'),
            const SizedBox(height: 4),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Total',
                    style: TextStyle(
                        fontFamily: fuenteSerif, fontWeight: FontWeight.bold, color: ColoresFragansys.cafe)),
                Text(dinero(d.pedido.total),
                    style: const TextStyle(
                        fontSize: 20, fontWeight: FontWeight.bold, color: ColoresFragansys.dorado)),
              ],
            ),
            const Divider(height: 32),
            const Text('Datos de entrega',
                style: TextStyle(fontWeight: FontWeight.bold, color: ColoresFragansys.cafe)),
            const SizedBox(height: 8),
            _dato(Icons.place_outlined, d.direccionEntrega),
            _dato(Icons.phone_outlined, d.telefonoContacto),
            if (d.notas.isNotEmpty) _dato(Icons.sticky_note_2_outlined, d.notas),
          ],
        );
      },
    );
  }

  Widget _fila(String etiqueta, String valor) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(etiqueta, style: const TextStyle(color: Colors.black54)),
            Text(valor, style: const TextStyle(color: Colors.black54)),
          ],
        ),
      );

  Widget _dato(IconData icono, String texto) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icono, size: 18, color: ColoresFragansys.dorado),
            const SizedBox(width: 8),
            Expanded(child: Text(texto)),
          ],
        ),
      );
}
