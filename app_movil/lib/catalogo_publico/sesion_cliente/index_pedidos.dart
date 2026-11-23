// Equivalente a sesion-cliente/indexP.php: "Mis Pedidos" con el seguimiento de cada pedido.
// Igual que la web, revisa cada cierto tiempo si algún pedido cambió de estado y lo resalta.

import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../conexion/conexion_api.dart';
import '../../helpers/helpers.dart';
import '../carrito/back_carrito_pub.dart';
import '../pago/index_pago.dart';
import 'back_login_cliente.dart';
import 'back_pedidos_cliente.dart';
import 'index_login.dart';
import 'modal_pedidos_cliente.dart';
import 'modalg_pedidos_cliente.dart';

class IndexPedidos extends StatefulWidget {
  const IndexPedidos({super.key});

  @override
  State<IndexPedidos> createState() => _IndexPedidosState();
}

class _IndexPedidosState extends State<IndexPedidos> {
  static const _intervaloSeguimiento = Duration(seconds: 30);

  List<PedidoCliente>? _pedidos;
  String? _error;
  Timer? _temporizador;
  final Map<int, String> _firmas = {};
  final Set<int> _cambiados = {};

  @override
  void initState() {
    super.initState();
    _cargar();
    _temporizador = Timer.periodic(_intervaloSeguimiento, (_) => _cargar(silencioso: true));
  }

  @override
  void dispose() {
    _temporizador?.cancel();
    super.dispose();
  }

  // Identifica la versión de un pedido; si cambia, se resalta (como firmaPedido() en PHP)
  String _firma(PedidoCliente p) =>
      '${p.estadoPedido}|${p.estadoPago}|${p.fechaActualizacion}|${p.paqueteria}|${p.numeroGuia}';

  Future<void> _cargar({bool silencioso = false}) async {
    if (!context.read<SesionCliente>().activa) return;
    try {
      final pedidos = await BackPedidosCliente.lista();
      if (!mounted) return;
      final cambiados = <int>{};
      for (final p in pedidos) {
        final anterior = _firmas[p.id];
        if (anterior != null && anterior != _firma(p)) cambiados.add(p.id);
        _firmas[p.id] = _firma(p);
      }
      setState(() {
        _pedidos = pedidos;
        _error = null;
        _cambiados.addAll(cambiados);
      });
      if (cambiados.isNotEmpty) {
        mostrarMensaje(context, cambiados.length == 1
            ? 'Tu pedido #${cambiados.first} se actualizó.'
            : 'Algunos de tus pedidos se actualizaron.');
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.sinSesion) {
        context.read<CarritoCompras>().vaciar();
        await context.read<SesionCliente>().cerrar();
      }
      if (!silencioso && mounted) setState(() => _error = e.mensaje);
    }
  }

  Future<void> _cancelar(PedidoCliente pedido) async {
    final confirmar = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text('Cancelar pedido #${pedido.id}'),
        content: Text(pedido.estadoPago == 'pagado'
            ? '¿Seguro que quieres cancelar este pedido? Nos pondremos en contacto contigo para el reembolso.'
            : '¿Seguro que quieres cancelar este pedido?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('No, conservarlo')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Sí, cancelar'),
          ),
        ],
      ),
    );
    if (confirmar != true || !mounted) return;
    try {
      final mensaje = await BackPedidosCliente.cancelar(pedido.id);
      if (mounted) mostrarMensaje(context, mensaje);
    } on ApiException catch (e) {
      if (mounted) mostrarMensaje(context, e.mensaje, error: true);
    }
    _cargar();
  }

  Future<void> _pagar(PedidoCliente pedido) async {
    await Navigator.push(context, MaterialPageRoute(builder: (_) => IndexPago(pedidoId: pedido.id)));
    _cargar();
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionCliente>();

    return Scaffold(
      appBar: AppBar(title: const Text('Mis Pedidos', style: TextStyle(fontFamily: fuenteSerif))),
      body: !sesion.activa ? _sinSesion() : _contenido(sesion.cliente!),
    );
  }

  Widget _sinSesion() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.lock_outline, size: 56, color: ColoresFragansys.dorado),
            const SizedBox(height: 12),
            Text(_error ?? 'Inicia sesión para ver tus pedidos.',
                textAlign: TextAlign.center, style: const TextStyle(color: Colors.black54)),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () =>
                  Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const IndexLogin())),
              child: const Text('INICIAR SESIÓN'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _contenido(Cliente cliente) {
    final pedidos = _pedidos;
    if (pedidos == null) {
      return _error == null
          ? const Center(child: CircularProgressIndicator())
          : VistaError(mensaje: _error!, alReintentar: _cargar);
    }

    return RefreshIndicator(
      onRefresh: _cargar,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TituloSeccion(titulo: 'Hola, ${cliente.primerNombre}', subtitulo: 'Aquí puedes seguir tus pedidos'),
          const SizedBox(height: 16),
          if (pedidos.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 48),
              child: Column(
                children: [
                  const Icon(Icons.shopping_bag_outlined, size: 64, color: ColoresFragansys.dorado),
                  const SizedBox(height: 12),
                  const Text('Aún no tienes pedidos.', style: TextStyle(color: Colors.black54, fontSize: 16)),
                  const SizedBox(height: 16),
                  OutlinedButton(
                    onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
                    child: const Text('EXPLORAR EL CATÁLOGO'),
                  ),
                ],
              ),
            )
          else
            for (final p in pedidos)
              TarjetaPedido(
                key: ValueKey(p.id),
                pedido: p,
                resaltado: _cambiados.contains(p.id),
                alVerDetalle: () {
                  setState(() => _cambiados.remove(p.id));
                  mostrarDetallePedido(context, p.id);
                },
                alCancelar: () => _cancelar(p),
                alPagar: () => _pagar(p),
              ),
        ],
      ),
    );
  }
}
