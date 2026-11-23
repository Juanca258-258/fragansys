// Equivalente a sesion-cliente/modal_pedidos_cliente.php: tarjeta de cada pedido con su
// línea de tiempo de seguimiento, datos de envío y botones Cancelar / Pagar ahora.

import 'package:material_ui/material_ui.dart';

import '../../helpers/helpers.dart';
import 'back_pedidos_cliente.dart';

const Map<String, IconData> iconosPasos = {
  'nuevo': Icons.receipt_long_outlined,
  'confirmado': Icons.check_circle_outline,
  'en_preparacion': Icons.inventory_2_outlined,
  'enviado': Icons.local_shipping_outlined,
  'entregado': Icons.home_outlined,
};

(Color, Color) coloresPago(String color) => switch (color) {
      'success' => (const Color(0xFFD1E7DD), const Color(0xFF0F5132)),
      'warning' => (const Color(0xFFFFF3CD), const Color(0xFF664D03)),
      'danger' => (const Color(0xFFF8D7DA), const Color(0xFF842029)),
      _ => (const Color(0xFFE2E3E5), const Color(0xFF41464B)),
    };

class InsigniaPago extends StatelessWidget {
  const InsigniaPago({super.key, required this.pedido});

  final PedidoCliente pedido;

  @override
  Widget build(BuildContext context) {
    final (fondo, texto) = coloresPago(pedido.colorPago);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: fondo, borderRadius: BorderRadius.circular(20)),
      child: Text(pedido.etiquetaPago,
          style: TextStyle(color: texto, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}

/// Línea de tiempo: Recibido → Confirmado → En preparación → Enviado → Entregado
class SeguimientoPedido extends StatelessWidget {
  const SeguimientoPedido({super.key, required this.pedido});

  final PedidoCliente pedido;

  @override
  Widget build(BuildContext context) {
    if (pedido.cancelado) {
      return Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: const Color(0xFFF8D7DA), borderRadius: BorderRadius.circular(10)),
        child: Row(
          children: [
            const Icon(Icons.cancel_outlined, color: Color(0xFF842029)),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Pedido cancelado.',
                      style: TextStyle(color: Color(0xFF842029), fontWeight: FontWeight.bold)),
                  if (pedido.estadoPago == 'pagado')
                    const Text('Nos pondremos en contacto contigo para el reembolso de tu pago.',
                        style: TextStyle(color: Color(0xFF842029), fontSize: 12)),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < pedido.pasos.length; i++) ...[
          if (i > 0)
            Expanded(
              child: Container(
                margin: const EdgeInsets.only(top: 17),
                height: 2,
                color: pedido.pasos[i].completado || pedido.pasos[i].actual
                    ? ColoresFragansys.dorado
                    : Colors.grey.shade300,
              ),
            ),
          _Paso(paso: pedido.pasos[i]),
        ],
      ],
    );
  }
}

class _Paso extends StatelessWidget {
  const _Paso({required this.paso});

  final PasoPedido paso;

  @override
  Widget build(BuildContext context) {
    final activo = paso.completado || paso.actual;
    return SizedBox(
      width: 52,
      child: Column(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: paso.actual ? ColoresFragansys.dorado : (activo ? ColoresFragansys.crema : Colors.white),
              border: Border.all(color: activo ? ColoresFragansys.dorado : Colors.grey.shade300, width: 2),
            ),
            child: Icon(iconosPasos[paso.clave] ?? Icons.circle_outlined,
                size: 18,
                color: paso.actual ? Colors.white : (activo ? ColoresFragansys.dorado : Colors.grey)),
          ),
          const SizedBox(height: 4),
          Text(paso.etiqueta,
              textAlign: TextAlign.center,
              style: TextStyle(
                  fontSize: 10,
                  color: activo ? ColoresFragansys.cafe : Colors.grey,
                  fontWeight: paso.actual ? FontWeight.bold : FontWeight.normal)),
        ],
      ),
    );
  }
}

/// Datos de la paquetería cuando el pedido ya se envió
class DatosEnvio extends StatelessWidget {
  const DatosEnvio({super.key, required this.pedido});

  final PedidoCliente pedido;

  @override
  Widget build(BuildContext context) {
    if (pedido.numeroGuia.isEmpty && pedido.paqueteria.isEmpty) return const SizedBox.shrink();
    return Container(
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: ColoresFragansys.crema,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE6DDD2)),
      ),
      child: Row(
        children: [
          const Icon(Icons.local_shipping_outlined, color: ColoresFragansys.dorado),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (pedido.paqueteria.isNotEmpty)
                  Text('Paquetería: ${pedido.paqueteria}', style: const TextStyle(fontWeight: FontWeight.w600)),
                if (pedido.numeroGuia.isNotEmpty) SelectableText('Guía: ${pedido.numeroGuia}'),
                if (pedido.fechaEnvio.isNotEmpty)
                  Text('Enviado el ${pedido.fechaEnvio}',
                      style: const TextStyle(color: Colors.black54, fontSize: 12)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class TarjetaPedido extends StatelessWidget {
  const TarjetaPedido({
    super.key,
    required this.pedido,
    required this.alVerDetalle,
    required this.alCancelar,
    required this.alPagar,
    this.resaltado = false,
  });

  final PedidoCliente pedido;
  final VoidCallback alVerDetalle;
  final VoidCallback alCancelar;
  final VoidCallback alPagar;

  /// true cuando el estado cambió desde la última consulta
  final bool resaltado;

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 600),
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: resaltado ? ColoresFragansys.dorado : Colors.grey.shade200, width: resaltado ? 2 : 1),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: alVerDetalle,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text('Pedido #${pedido.id}',
                      style: const TextStyle(
                          fontFamily: fuenteSerif,
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: ColoresFragansys.cafe)),
                  const Spacer(),
                  InsigniaPago(pedido: pedido),
                ],
              ),
              const SizedBox(height: 4),
              Text('${pedido.fechaPedido} · ${pedido.totalItems} producto(s) · ${dinero(pedido.total)}',
                  style: const TextStyle(color: Colors.black54, fontSize: 13)),
              const SizedBox(height: 16),
              SeguimientoPedido(pedido: pedido),
              DatosEnvio(pedido: pedido),
              const SizedBox(height: 8),
              Text('Última actualización: ${pedido.fechaActualizacion}',
                  style: const TextStyle(color: Colors.black45, fontSize: 11)),
              if (!pedido.cancelado && (pedido.cancelable || pedido.porPagar))
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Wrap(
                    alignment: WrapAlignment.end,
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      if (pedido.cancelable)
                        OutlinedButton.icon(
                          style: OutlinedButton.styleFrom(
                              foregroundColor: Colors.red, side: const BorderSide(color: Colors.red)),
                          onPressed: alCancelar,
                          icon: const Icon(Icons.cancel_outlined, size: 18),
                          label: const Text('Cancelar pedido'),
                        ),
                      if (pedido.porPagar)
                        FilledButton.icon(
                          style: FilledButton.styleFrom(
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10)),
                          onPressed: alPagar,
                          icon: const Icon(Icons.credit_card, size: 18),
                          label: const Text('Pagar ahora'),
                        ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
