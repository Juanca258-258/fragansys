// Equivalente a sesion-cliente/back_pedidos_cliente.php y act_pedidos_cliente.php:
// pedidos del cliente, seguimiento, detalle y cancelación.

import '../../conexion/conexion_api.dart';

const String archivoPedidosCliente = 'catalogo-publico/sesion-cliente/act_pedidos_cliente.php';

/// Paso del seguimiento: Recibido, Confirmado, En preparación, Enviado, Entregado
class PasoPedido {
  PasoPedido({required this.clave, required this.etiqueta, required this.estado});

  factory PasoPedido.desdeJson(Map<String, dynamic> j) => PasoPedido(
        clave: aTexto(j['clave']),
        etiqueta: aTexto(j['etiqueta']),
        estado: aTexto(j['estado']),
      );

  final String clave;
  final String etiqueta;
  final String estado; // completado | actual | ''

  bool get completado => estado == 'completado';
  bool get actual => estado == 'actual';
}

class PedidoCliente {
  PedidoCliente({
    required this.id,
    required this.estadoPedido,
    required this.estadoPago,
    required this.etiquetaPago,
    required this.colorPago,
    required this.pasos,
    required this.cancelable,
    required this.porPagar,
    required this.fechaPedido,
    required this.fechaActualizacion,
    required this.total,
    required this.totalItems,
    required this.paqueteria,
    required this.numeroGuia,
    required this.fechaEnvio,
  });

  factory PedidoCliente.desdeJson(Map<String, dynamic> j) => PedidoCliente(
        id: aInt(j['id']),
        estadoPedido: aTexto(j['estado_pedido']),
        estadoPago: aTexto(j['estado_pago']),
        etiquetaPago: aTexto(j['etiqueta_pago']),
        colorPago: aTexto(j['color_pago']),
        pasos: (j['pasos'] as List? ?? [])
            .map((p) => PasoPedido.desdeJson(p as Map<String, dynamic>))
            .toList(),
        cancelable: j['cancelable'] == true,
        porPagar: j['por_pagar'] == true,
        fechaPedido: aTexto(j['fecha_pedido']),
        fechaActualizacion: aTexto(j['fecha_actualizacion']),
        total: aDouble(j['total']),
        totalItems: aInt(j['total_items']),
        paqueteria: aTexto(j['paqueteria']),
        numeroGuia: aTexto(j['numero_guia']),
        fechaEnvio: aTexto(j['fecha_envio']),
      );

  final int id;
  final String estadoPedido;
  final String estadoPago;
  final String etiquetaPago;
  final String colorPago; // success | warning | danger | secondary
  final List<PasoPedido> pasos;
  final bool cancelable;
  final bool porPagar;
  final String fechaPedido;
  final String fechaActualizacion;
  final double total;
  final int totalItems;
  final String paqueteria;
  final String numeroGuia;
  final String fechaEnvio;

  bool get cancelado => estadoPedido == 'cancelado';
}

class ItemPedido {
  ItemPedido({
    required this.nombre,
    required this.marca,
    required this.imagen,
    required this.cantidad,
    required this.precioUnitario,
    required this.subtotal,
  });

  factory ItemPedido.desdeJson(Map<String, dynamic> j) => ItemPedido(
        nombre: aTexto(j['nombre']),
        marca: aTexto(j['marca']),
        imagen: aTexto(j['imagen']),
        cantidad: aInt(j['cantidad']),
        precioUnitario: aDouble(j['precio_unitario']),
        subtotal: aDouble(j['subtotal']),
      );

  final String nombre;
  final String marca;
  final String imagen;
  final int cantidad;
  final double precioUnitario;
  final double subtotal;
}

class DetallePedido {
  DetallePedido({
    required this.pedido,
    required this.direccionEntrega,
    required this.telefonoContacto,
    required this.notas,
    required this.subtotal,
    required this.costoEnvio,
    required this.items,
  });

  final PedidoCliente pedido;
  final String direccionEntrega;
  final String telefonoContacto;
  final String notas;
  final double subtotal;
  final double costoEnvio;
  final List<ItemPedido> items;
}

class BackPedidosCliente {
  BackPedidosCliente._();

  static Future<List<PedidoCliente>> lista() async {
    final r = await ConexionApi.enviarOk(archivoPedidosCliente, {'accion': 'lista'});
    return (r['pedidos'] as List? ?? [])
        .map((p) => PedidoCliente.desdeJson(p as Map<String, dynamic>))
        .toList();
  }

  static Future<DetallePedido> detalle(int pedidoId) async {
    final r = await ConexionApi.enviarOk(
        archivoPedidosCliente, {'accion': 'detalle', 'pedido_id': '$pedidoId'});
    final p = r['pedido'] as Map<String, dynamic>;
    return DetallePedido(
      pedido: PedidoCliente.desdeJson(p),
      direccionEntrega: aTexto(p['direccion_entrega']),
      telefonoContacto: aTexto(p['telefono_contacto']),
      notas: aTexto(p['notas']),
      subtotal: aDouble(p['subtotal']),
      costoEnvio: aDouble(p['costo_envio']),
      items: (r['items'] as List? ?? [])
          .map((i) => ItemPedido.desdeJson(i as Map<String, dynamic>))
          .toList(),
    );
  }

  /// Devuelve el mensaje de confirmación; lanza ApiException si ya no se puede cancelar.
  static Future<String> cancelar(int pedidoId) async {
    final r = await ConexionApi.enviarOk(
        archivoPedidosCliente, {'accion': 'cancelar', 'pedido_id': '$pedidoId'});
    return aTexto(r['message']);
  }
}
