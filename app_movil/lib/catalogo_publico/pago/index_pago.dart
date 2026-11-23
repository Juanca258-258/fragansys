// Equivalente a pago/indexPago.php: "Pagar pedido #N" con el resumen del pedido y el pago con PayPal.

import 'package:material_ui/material_ui.dart';

import '../../conexion/conexion_api.dart';
import '../../helpers/helpers.dart';
import '../sesion_cliente/index_pedidos.dart';
import 'back_pago.dart';
import 'modal_pago_paypal.dart';

class IndexPago extends StatefulWidget {
  const IndexPago({super.key, required this.pedidoId, this.recienCreado = false});

  final int pedidoId;

  /// true cuando se llega justo después de registrar el pedido en el carrito
  final bool recienCreado;

  @override
  State<IndexPago> createState() => _IndexPagoState();
}

enum _TipoMensaje { exito, info, aviso, error }

class _IndexPagoState extends State<IndexPago> {
  PedidoPago? _pedido;
  String? _errorCarga;
  bool _procesando = false;
  bool _pagado = false;
  (String, _TipoMensaje)? _mensaje;

  @override
  void initState() {
    super.initState();
    if (widget.recienCreado) {
      _mensaje = ('Tu pedido #${widget.pedidoId} fue registrado. Ahora realiza el pago.', _TipoMensaje.info);
    }
    _cargar();
  }

  Future<void> _cargar() async {
    setState(() => _errorCarga = null);
    try {
      final pedido = await BackPago.detalle(widget.pedidoId);
      if (mounted) {
        setState(() {
          _pedido = pedido;
          _pagado = pedido.estadoPago == 'pagado';
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _errorCarga = e.mensaje);
    }
  }

  void _avisar(String texto, _TipoMensaje tipo) => setState(() => _mensaje = (texto, tipo));

  /// Pago con cuenta de PayPal: se abre la página de PayPal para iniciar sesión
  Future<void> _pagarConPaypal() => _procesar(() async {
        // 1) Crear la orden en el servidor
        final orden = await BackPago.crearOrden(widget.pedidoId);
        if (orden == null) return '';
        if (!mounted) return null;
        // 2) El cliente aprueba en la página de PayPal
        final resultado = await Navigator.push<ResultadoVentanaPago>(
          context,
          MaterialPageRoute(
            builder: (_) => VentanaPaypal(
              url: orden.urlAprobar,
              urlAprobado: orden.urlAprobado,
              urlCancelado: orden.urlCancelado,
            ),
          ),
        );
        return resultado != null && resultado.aprobado ? orden.ordenId : null;
      });

  /// Pago con tarjeta: mismo formulario de tarjeta del SDK de PayPal que la web, sin cuenta de PayPal
  Future<void> _pagarConTarjeta() => _procesar(() async {
        final resultado = await Navigator.push<ResultadoVentanaPago>(
          context,
          MaterialPageRoute(
            builder: (_) => VentanaPaypal(
              titulo: 'Pagar con tarjeta',
              url: BackPago.urlPaginaTarjeta,
              datosPost: BackPago.datosPaginaTarjeta(widget.pedidoId),
              urlAprobado: BackPago.urlRetorno('aprobado'),
              urlCancelado: BackPago.urlRetorno('cancelado'),
            ),
          ),
        );
        if (resultado == null || !resultado.aprobado) return null;
        // Sin id de orden la página avisó que el pedido ya estaba pagado
        return resultado.ordenId;
      });

  /// Hace el paso de aprobación indicado y después el cobro.
  /// [aprobar] devuelve el id de la orden aprobada, '' si el pedido ya estaba pagado o null si se canceló.
  Future<void> _procesar(Future<String?> Function() aprobar) async {
    setState(() => _procesando = true);
    try {
      final ordenId = await aprobar();
      if (!mounted) return;
      if (ordenId == null) {
        _avisar('Cancelaste el pago. Tu pedido sigue pendiente; puedes intentarlo de nuevo cuando quieras.',
            _TipoMensaje.aviso);
        return;
      }
      if (ordenId.isEmpty) {
        setState(() => _pagado = true);
        _avisar('Este pedido ya está pagado.', _TipoMensaje.exito);
        return;
      }

      // El servidor cobra y marca el pedido como pagado
      _avisar('Confirmando tu pago…', _TipoMensaje.info);
      final cobro = await BackPago.capturar(widget.pedidoId, ordenId);
      if (cobro.pagado) {
        setState(() => _pagado = true);
        _avisar(cobro.mensaje, _TipoMensaje.exito);
      } else {
        _avisar(cobro.mensaje, cobro.status == 'rechazado' ? _TipoMensaje.aviso : _TipoMensaje.error);
      }
    } on ApiException catch (e) {
      _avisar(e.mensaje, _TipoMensaje.error);
    } finally {
      if (mounted) setState(() => _procesando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const LogoFragansys()),
      body: _contenido(),
    );
  }

  Widget _contenido() {
    final pedido = _pedido;
    if (pedido == null) {
      return _errorCarga == null
          ? const Center(child: CircularProgressIndicator())
          : VistaError(mensaje: _errorCarga!, alReintentar: _cargar);
    }

    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Text('Pagar pedido #${pedido.id}',
            textAlign: TextAlign.center,
            style: const TextStyle(
                fontFamily: fuenteSerif, fontSize: 24, fontWeight: FontWeight.bold, color: ColoresFragansys.cafe)),
        const Text('Pago seguro con PayPal o tarjeta',
            textAlign: TextAlign.center,
            style: TextStyle(fontFamily: fuenteSerif, fontStyle: FontStyle.italic, color: Colors.black54)),
        const SizedBox(height: 20),
        Card(
          elevation: 0,
          color: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('TU PEDIDO',
                    style: TextStyle(color: Colors.black54, letterSpacing: 1, fontSize: 12)),
                const SizedBox(height: 8),
                for (final l in pedido.productos)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('${l.cantidad} ×  ', style: const TextStyle(color: Colors.black54)),
                        Expanded(child: Text(l.nombre)),
                        const SizedBox(width: 12),
                        Text(dinero(l.subtotal), style: const TextStyle(fontWeight: FontWeight.w600)),
                      ],
                    ),
                  ),
                const SizedBox(height: 4),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Envío', style: TextStyle(color: Colors.black54)),
                    Text(pedido.costoEnvio > 0 ? dinero(pedido.costoEnvio) : 'Gratis',
                        style: const TextStyle(color: Colors.black54)),
                  ],
                ),
                const Divider(height: 24),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total',
                        style: TextStyle(
                            fontFamily: fuenteSerif,
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: ColoresFragansys.cafe)),
                    Text('${dinero(pedido.total)} ${pedido.moneda}',
                        style: const TextStyle(
                            fontSize: 22, fontWeight: FontWeight.bold, color: ColoresFragansys.dorado)),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.place_outlined, size: 16, color: Colors.black54),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text('Entrega: ${pedido.direccionEntrega}',
                          style: const TextStyle(color: Colors.black54, fontSize: 13)),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        if (_mensaje != null) _CajaMensaje(texto: _mensaje!.$1, tipo: _mensaje!.$2),
        if (_pagado) ...[
          // Si se acaba de pagar ya se muestra la confirmación del servidor
          if (_mensaje?.$2 != _TipoMensaje.exito)
            const _CajaMensaje(
                texto: 'Este pedido ya está pagado. ¡Gracias por tu compra!', tipo: _TipoMensaje.exito),
        ] else if (!pedido.pagable)
          const _CajaMensaje(texto: 'Este pedido ya no se puede pagar.', tipo: _TipoMensaje.aviso)
        else ...[
          if (_procesando)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Center(child: CircularProgressIndicator()),
            )
          else ...[
            FilledButton.icon(
              style: FilledButton.styleFrom(backgroundColor: const Color(0xFF2C2E2F)),
              onPressed: _pagarConTarjeta,
              icon: const Icon(Icons.credit_card),
              label: const Text('TARJETA DE DÉBITO O CRÉDITO'),
            ),
            const SizedBox(height: 10),
            FilledButton.icon(
              style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFFFC439), foregroundColor: const Color(0xFF003087)),
              onPressed: _pagarConPaypal,
              icon: const Icon(Icons.account_balance_wallet_outlined),
              label: const Text('PAGAR CON PAYPAL'),
            ),
          ],
          const SizedBox(height: 8),
          const Text(
            'Tus datos de pago los maneja PayPal; nosotros no los vemos ni los guardamos.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.black54, fontSize: 12),
          ),
        ],
        const SizedBox(height: 24),
        OutlinedButton(
          onPressed: () => Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(builder: (_) => const IndexPedidos()),
            (ruta) => ruta.isFirst,
          ),
          child: const Text('Ir a mis pedidos'),
        ),
        TextButton(
          onPressed: () => Navigator.of(context).popUntil((ruta) => ruta.isFirst),
          child: const Text('Volver a la tienda'),
        ),
        if (!_pagado && pedido.pagable)
          const Text(
            'Si prefieres pagar después, tu pedido queda guardado como pendiente en "Mis pedidos".',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.black54, fontSize: 12),
          ),
      ],
    );
  }
}

class _CajaMensaje extends StatelessWidget {
  const _CajaMensaje({required this.texto, required this.tipo});

  final String texto;
  final _TipoMensaje tipo;

  @override
  Widget build(BuildContext context) {
    final (fondo, color, icono) = switch (tipo) {
      _TipoMensaje.exito => (const Color(0xFFD1E7DD), const Color(0xFF0F5132), Icons.check_circle_outline),
      _TipoMensaje.info => (const Color(0xFFCFF4FC), const Color(0xFF055160), Icons.info_outline),
      _TipoMensaje.aviso => (const Color(0xFFFFF3CD), const Color(0xFF664D03), Icons.warning_amber),
      _TipoMensaje.error => (const Color(0xFFF8D7DA), const Color(0xFF842029), Icons.error_outline),
    };
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: fondo, borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: [
          Icon(icono, color: color),
          const SizedBox(width: 10),
          Expanded(child: Text(texto, style: TextStyle(color: color))),
        ],
      ),
    );
  }
}
