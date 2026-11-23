// Equivalente a carrito/indexC.php: pantalla "Tu Carrito de Compras".

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../conexion/conexion_api.dart';
import '../../helpers/helpers.dart';
import 'back_carrito_pub.dart';
import 'modal_carrito_pub.dart';
import 'modalg_carrito_pub.dart';

class IndexCarrito extends StatefulWidget {
  const IndexCarrito({super.key});

  @override
  State<IndexCarrito> createState() => _IndexCarritoState();
}

class _IndexCarritoState extends State<IndexCarrito> {
  CarritoCompras? _carrito;
  String _firmaResumen = '';
  String? _error;

  @override
  void initState() {
    super.initState();
    _carrito = context.read<CarritoCompras>();
    _carrito!.addListener(_alCambiarCarrito);
    _refrescar();
  }

  @override
  void dispose() {
    _carrito?.removeListener(_alCambiarCarrito);
    super.dispose();
  }

  // Cada vez que cambia una cantidad se vuelve a pedir el resumen (el envío depende del subtotal)
  void _alCambiarCarrito() {
    final carrito = _carrito!;
    if (!carrito.resumenListo && !carrito.vacio && _firma(carrito) != _firmaResumen) {
      _refrescar();
    }
  }

  String _firma(CarritoCompras c) => c.items.map((i) => '${i.clave}:${i.cantidad}').join(',');

  Future<void> _refrescar() async {
    final carrito = _carrito!;
    if (carrito.vacio) return;
    _firmaResumen = _firma(carrito);
    try {
      final avisos = await carrito.refrescarResumen();
      if (!mounted) return;
      setState(() => _error = null);
      if (avisos.isNotEmpty) mostrarMensaje(context, avisos.join('\n'), error: true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.mensaje);
    }
  }

  @override
  Widget build(BuildContext context) {
    final carrito = context.watch<CarritoCompras>();

    return Scaffold(
      appBar: AppBar(title: const Text('Tu Carrito', style: TextStyle(fontFamily: fuenteSerif))),
      body: carrito.vacio
          ? const _CarritoVacio()
          : RefreshIndicator(
              onRefresh: _refrescar,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const _Encabezado(),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      OutlinedButton.icon(
                        onPressed: () => Navigator.pop(context),
                        icon: const Icon(Icons.arrow_back, size: 18),
                        label: const Text('SEGUIR COMPRANDO', style: TextStyle(fontSize: 12)),
                      ),
                      Text('Items: ${carrito.totalItems}',
                          style: const TextStyle(fontFamily: fuenteSerif, color: Colors.black54)),
                    ],
                  ),
                  const SizedBox(height: 16),
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(_error!, style: TextStyle(color: Colors.red.shade700)),
                    ),
                  for (final item in carrito.items) FilaCarrito(key: ValueKey(item.clave), item: item),
                  const SizedBox(height: 8),
                  ResumenPedido(
                    alProceder: () => Navigator.push(
                        context, MaterialPageRoute(builder: (_) => const PantallaCheckout())),
                  ),
                  const SizedBox(height: 8),
                  TextButton.icon(
                    onPressed: () async {
                      final ok = await showDialog<bool>(
                        context: context,
                        builder: (c) => AlertDialog(
                          title: const Text('Vaciar carrito'),
                          content: const Text('¿Quitar todos los productos del carrito?'),
                          actions: [
                            TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('No')),
                            FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Vaciar')),
                          ],
                        ),
                      );
                      if (ok == true) carrito.vaciar();
                    },
                    icon: const Icon(Icons.remove_shopping_cart_outlined, color: Colors.black54),
                    label: const Text('Vaciar carrito', style: TextStyle(color: Colors.black54)),
                  ),
                ],
              ),
            ),
    );
  }
}

class _Encabezado extends StatelessWidget {
  const _Encabezado();

  @override
  Widget build(BuildContext context) {
    return const Column(
      children: [
        Text('◆  Tu Carrito de Compras  ◆',
            textAlign: TextAlign.center,
            style: TextStyle(
                fontFamily: fuenteSerif,
                fontSize: 22,
                fontWeight: FontWeight.bold,
                color: ColoresFragansys.cafe)),
        SizedBox(height: 4),
        Text('Procede con tu pedido cuando estés listo.',
            style: TextStyle(fontFamily: fuenteSerif, fontStyle: FontStyle.italic, color: Colors.black54)),
      ],
    );
  }
}

class _CarritoVacio extends StatelessWidget {
  const _CarritoVacio();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.shopping_bag_outlined, size: 72, color: ColoresFragansys.dorado),
            const SizedBox(height: 16),
            const Text('Tu carrito está actualmente vacío',
                textAlign: TextAlign.center,
                style: TextStyle(
                    fontFamily: fuenteSerif,
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: ColoresFragansys.cafe)),
            const SizedBox(height: 8),
            const Text(
              'Parece que aún no has agregado ninguna fragancia a tu colección. '
              'Explora nuestro catálogo y descubre tu próximo aroma.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.black54),
            ),
            const SizedBox(height: 24),
            FilledButton.icon(
              style: FilledButton.styleFrom(backgroundColor: ColoresFragansys.cafe),
              onPressed: () => Navigator.pop(context),
              icon: const Icon(Icons.arrow_back),
              label: const Text('EXPLORAR EL CATÁLOGO'),
            ),
          ],
        ),
      ),
    );
  }
}
