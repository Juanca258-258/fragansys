// Equivalente a pago/back_pago.php y pago/act_pago.php: datos del pedido a pagar y llamadas
// para crear la orden de PayPal y cobrarla. Las claves de PayPal NUNCA están en la app: viven en
// paypal_config.php del servidor.

import '../../conexion/conexion_api.dart';

const String archivoPago = 'catalogo-publico/pago/act_pago.php';

class LineaPago {
  LineaPago({required this.nombre, required this.cantidad, required this.subtotal});

  factory LineaPago.desdeJson(Map<String, dynamic> j) => LineaPago(
        nombre: aTexto(j['nombre']),
        cantidad: aInt(j['cantidad']),
        subtotal: aDouble(j['subtotal']),
      );

  final String nombre;
  final int cantidad;
  final double subtotal;
}

class PedidoPago {
  PedidoPago({
    required this.id,
    required this.subtotal,
    required this.costoEnvio,
    required this.total,
    required this.moneda,
    required this.direccionEntrega,
    required this.estadoPago,
    required this.pagable,
    required this.productos,
  });

  factory PedidoPago.desdeJson(Map<String, dynamic> r) {
    final p = r['pedido'] as Map<String, dynamic>;
    return PedidoPago(
      id: aInt(p['id']),
      subtotal: aDouble(p['subtotal']),
      costoEnvio: aDouble(p['costo_envio']),
      total: aDouble(p['total']),
      moneda: aTexto(p['moneda']),
      direccionEntrega: aTexto(p['direccion_entrega']),
      estadoPago: aTexto(p['estado_pago']),
      pagable: p['pagable'] == true,
      productos: (r['productos'] as List? ?? [])
          .map((l) => LineaPago.desdeJson(l as Map<String, dynamic>))
          .toList(),
    );
  }

  final int id;
  final double subtotal;
  final double costoEnvio;
  final double total;
  final String moneda;
  final String direccionEntrega;
  final String estadoPago; // pendiente | pagado | fallido | reembolsado
  final bool pagable;
  final List<LineaPago> productos;
}

/// Orden creada en PayPal: la app abre [urlAprobar] y espera a que PayPal regrese a una de las otras dos.
class OrdenPaypal {
  OrdenPaypal({
    required this.ordenId,
    required this.urlAprobar,
    required this.urlAprobado,
    required this.urlCancelado,
  });

  final String ordenId;
  final String urlAprobar;
  final String urlAprobado;
  final String urlCancelado;
}

/// Resultado de cobrar: status = success | pagado | rechazado | error
class ResultadoPago {
  ResultadoPago(this.status, this.mensaje);

  final String status;
  final String mensaje;

  bool get pagado => status == 'success' || status == 'pagado';
}

class BackPago {
  BackPago._();

  static Future<PedidoPago> detalle(int pedidoId) async {
    final r = await ConexionApi.enviarOk(archivoPago, {'accion': 'detalle', 'pedido_id': '$pedidoId'});
    return PedidoPago.desdeJson(r);
  }

  /// 1) Nuestro servidor crea la orden con el total guardado en la base de datos.
  /// Devuelve null si el pedido ya estaba pagado.
  static Future<OrdenPaypal?> crearOrden(int pedidoId) async {
    final r = await ConexionApi.enviarOk(archivoPago, {'accion': 'crear_orden', 'pedido_id': '$pedidoId'});
    if (r['status'] == 'pagado') return null;
    return OrdenPaypal(
      ordenId: aTexto(r['orden_id']),
      urlAprobar: aTexto(r['url_aprobar']),
      urlAprobado: aTexto(r['url_aprobado']),
      urlCancelado: aTexto(r['url_cancelado']),
    );
  }

  /// Página de pago con tarjeta (tarjeta.php): mismo botón de tarjeta del SDK de PayPal que usa la web,
  /// para pagar con los datos de la tarjeta sin cuenta de PayPal.
  static String get urlPaginaTarjeta => '${ConexionApi.urlBase}/api/catalogo-publico/pago/tarjeta.php';

  static String urlRetorno(String resultado) =>
      '${ConexionApi.urlBase}/api/catalogo-publico/pago/retorno.php?resultado=$resultado';

  /// Datos con los que se abre tarjeta.php (van por POST para que el token no quede en la dirección)
  static Map<String, String> datosPaginaTarjeta(int pedidoId) =>
      {'pedido_id': '$pedidoId', 'token': ConexionApi.token ?? ''};

  /// 2) El cliente aprobó en PayPal: el servidor cobra, verifica el monto y marca el pedido como pagado.
  static Future<ResultadoPago> capturar(int pedidoId, String ordenId) async {
    final r = await ConexionApi.enviar(
        archivoPago, {'accion': 'capturar', 'pedido_id': '$pedidoId', 'orden_id': ordenId});
    return ResultadoPago(aTexto(r['status']), aTexto(r['message']));
  }
}
