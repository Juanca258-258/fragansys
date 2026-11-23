// Equivalente a carrito/back_carrito_pub.php y carrito/act_carrito_pub.php.
// En la web el carrito vive en $_SESSION['carrito']; en la app se guarda en el teléfono y el servidor
// lo vuelve a validar (precios y stock de la BD) al ver el resumen y al registrar el pedido.

import 'dart:convert';

import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../conexion/conexion_api.dart';
import '../back_catalogo_pub.dart';
import '../sesion_cliente/back_login_cliente.dart';

const String archivoCarrito = 'catalogo-publico/carrito/act_carrito_pub.php';

/// Producto que incluye un combo (se muestra desplegable en el carrito)
class ProductoCombo {
  ProductoCombo({
    required this.cantidad,
    required this.tipo,
    required this.ml,
    required this.nombre,
    required this.marca,
    required this.imagen,
  });

  factory ProductoCombo.desdeJson(Map<String, dynamic> j) => ProductoCombo(
        cantidad: aInt(j['cantidad']),
        tipo: aTexto(j['tipo']),
        ml: aTexto(j['ml']),
        nombre: aTexto(j['nombre']),
        marca: aTexto(j['marca']),
        imagen: aTexto(j['imagen']),
      );

  final int cantidad;
  final String tipo;
  final String ml;
  final String nombre;
  final String marca;
  final String imagen;

  Map<String, dynamic> aJson() => {
        'cantidad': cantidad,
        'tipo': tipo,
        'ml': ml,
        'nombre': nombre,
        'marca': marca,
        'imagen': imagen,
      };
}

class ItemCarrito {
  ItemCarrito({
    required this.clave,
    required this.tipo,
    required this.id,
    required this.nombre,
    required this.marca,
    required this.presentacion,
    required this.contenidoMl,
    required this.precio,
    required this.cantidad,
    required this.stock,
    required this.piezas,
    required this.imagen,
    this.productos = const [],
  });

  factory ItemCarrito.desdeJson(Map<String, dynamic> j) => ItemCarrito(
        clave: aTexto(j['clave']),
        tipo: aTexto(j['tipo']),
        id: aInt(j['id']),
        nombre: aTexto(j['nombre']),
        marca: aTexto(j['marca']),
        presentacion: aTexto(j['presentacion']),
        contenidoMl: aTexto(j['contenido_ml']),
        precio: aDouble(j['precio']),
        cantidad: j['cantidad'] == null ? 1 : aInt(j['cantidad']),
        stock: aInt(j['stock']),
        piezas: aInt(j['piezas']),
        imagen: aTexto(j['imagen']),
        productos: (j['productos'] as List? ?? [])
            .map((p) => ProductoCombo.desdeJson(p as Map<String, dynamic>))
            .toList(),
      );

  /// Id de la presentación ("12") o `c` + id para un combo ("c3")
  final String clave;
  final String tipo; // presentacion | combo
  final int id;
  final String nombre;
  final String marca;
  final String presentacion; // decant | frasco ('' en combos)
  final String contenidoMl;
  final double precio;
  int cantidad;
  final int stock;
  final int piezas;
  final String imagen;
  final List<ProductoCombo> productos;

  bool get esCombo => tipo == 'combo';
  double get subtotal => precio * cantidad;

  Map<String, dynamic> aJson() => {
        'clave': clave,
        'tipo': tipo,
        'id': id,
        'nombre': nombre,
        'marca': marca,
        'presentacion': presentacion,
        'contenido_ml': contenidoMl,
        'precio': precio,
        'cantidad': cantidad,
        'stock': stock,
        'piezas': piezas,
        'imagen': imagen,
        'productos': productos.map((p) => p.aJson()).toList(),
      };
}

/// Carrito compartido en toda la app (Provider)
class CarritoCompras extends ChangeNotifier {
  static const _clavePreferencias = 'carrito';

  final List<ItemCarrito> _items = [];

  // Envío y total según Configuración; los calcula el servidor en accion=resumen
  double envio = 0;
  double total = 0;
  double faltaEnvioGratis = 0;
  bool resumenListo = false;

  List<ItemCarrito> get items => List.unmodifiable(_items);
  bool get vacio => _items.isEmpty;
  int get totalItems => _items.fold(0, (suma, i) => suma + i.cantidad);
  double get subtotal => _items.fold(0.0, (suma, i) => suma + i.subtotal);

  int cantidadDe(String clave) {
    for (final i in _items) {
      if (i.clave == clave) return i.cantidad;
    }
    return 0;
  }

  Future<void> cargar() async {
    final prefs = await SharedPreferences.getInstance();
    final guardado = prefs.getString(_clavePreferencias);
    if (guardado == null) return;
    try {
      _items
        ..clear()
        ..addAll((jsonDecode(guardado) as List)
            .map((i) => ItemCarrito.desdeJson(i as Map<String, dynamic>)));
    } catch (_) {
      _items.clear();
    }
    notifyListeners();
  }

  Future<void> _guardar() async {
    resumenListo = false; // el envío se tiene que recalcular
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_clavePreferencias, jsonEncode(_items.map((i) => i.aJson()).toList()));
  }

  /// accion=agregar de act_catalogo_pub.php. Lanza ApiException si no hay stock suficiente.
  Future<void> agregarProducto(Variante variante, {int cantidad = 1}) => _agregar({
        'accion': 'agregar',
        'producto_id': '${variante.id}',
        'cantidad': '$cantidad',
        'cantidad_en_carrito': '${cantidadDe('${variante.id}')}',
      }, cantidad);

  /// accion=agregar_combo
  Future<void> agregarCombo(Combo combo, {int cantidad = 1}) => _agregar({
        'accion': 'agregar_combo',
        'combo_id': '${combo.id}',
        'cantidad': '$cantidad',
        'cantidad_en_carrito': '${cantidadDe('c${combo.id}')}',
      }, cantidad);

  Future<void> _agregar(Map<String, String> datos, int cantidad) async {
    final r = await ConexionApi.enviarOk(archivoCatalogo, datos);
    final nuevo = ItemCarrito.desdeJson(r['item'] as Map<String, dynamic>);
    final indice = _items.indexWhere((i) => i.clave == nuevo.clave);
    if (indice >= 0) {
      // Se reemplaza con los datos recientes (precio y stock) sumando la cantidad
      nuevo.cantidad = _items[indice].cantidad + cantidad;
      _items[indice] = nuevo;
    } else {
      nuevo.cantidad = cantidad;
      _items.add(nuevo);
    }
    await _guardar();
  }

  /// accion=actualizar. Devuelve un mensaje si la cantidad no es válida, o null si se actualizó.
  String? actualizarCantidad(String clave, int cantidad) {
    final indice = _items.indexWhere((i) => i.clave == clave);
    if (indice < 0) return null;
    if (cantidad > _items[indice].stock) {
      return 'Stock máximo disponible: ${_items[indice].stock}';
    }
    if (cantidad <= 0) {
      _items.removeAt(indice);
    } else {
      _items[indice].cantidad = cantidad;
    }
    _guardar();
    return null;
  }

  void eliminar(String clave) {
    _items.removeWhere((i) => i.clave == clave);
    _guardar();
  }

  void vaciar() {
    _items.clear();
    _guardar();
  }

  /// accion=resumen: actualiza precios y stock con la BD y calcula envío y total.
  /// Devuelve los avisos (productos agotados o cantidades ajustadas).
  Future<List<String>> refrescarResumen() async {
    final enviado = _itemsParaApi();
    final r = await ConexionApi.enviarOk(archivoCarrito, {'accion': 'resumen', 'items': enviado});
    // Si el carrito cambió mientras se esperaba la respuesta, esta ya es vieja: se descarta
    // (el cambio dispara otro resumen).
    if (_itemsParaApi() != enviado) return const [];
    _items
      ..clear()
      ..addAll((r['items'] as List? ?? [])
          .map((i) => ItemCarrito.desdeJson(i as Map<String, dynamic>)));
    await _guardar();
    envio = aDouble(r['envio']);
    total = aDouble(r['total']);
    faltaEnvioGratis = aDouble(r['falta_envio_gratis']);
    resumenListo = true;
    notifyListeners();
    return (r['avisos'] as List? ?? []).map((a) => '$a').toList();
  }

  String _itemsParaApi() => jsonEncode(
      _items.map((i) => {'tipo': i.tipo, 'id': i.id, 'cantidad': i.cantidad}).toList());

  // ---------------------------------------------------------------- Checkout

  /// Paso 1: ¿el correo ya está registrado? Devuelve (existe, sinPassword).
  static Future<({bool existe, bool sinPassword})> buscarCliente(String correo) async {
    final r = await ConexionApi.enviarOk(
        archivoCarrito, {'accion': 'buscar_cliente', 'cliente_correo': correo.trim()});
    return (existe: r['existe'] == true, sinPassword: r['sin_password'] == true);
  }

  /// Paso 2 (cliente registrado): valida su contraseña y deja la sesión iniciada.
  static Future<void> verificarCliente(
    SesionCliente sesion, {
    required String correo,
    required String password,
    String confirmar = '',
  }) async {
    final r = await ConexionApi.enviarOk(archivoCarrito, {
      'accion': 'verificar_cliente',
      'cliente_correo': correo.trim(),
      'password': password,
      'password_confirmar': confirmar,
    });
    await sesion.establecer(aTexto(r['token']), Cliente.desdeJson(r['cliente'] as Map<String, dynamic>));
  }

  /// accion=realizar_pedido. Registra el pedido (pago pendiente), vacía el carrito y devuelve el id del pedido.
  /// Si el cliente es nuevo, se registra y queda con la sesión iniciada.
  Future<int> realizarPedido(SesionCliente sesion, Map<String, String> datosEntrega) async {
    final r = await ConexionApi.enviarOk(archivoCarrito, {
      ...datosEntrega,
      'accion': 'realizar_pedido',
      'items': _itemsParaApi(),
    });
    await sesion.establecer(aTexto(r['token']), Cliente.desdeJson(r['cliente'] as Map<String, dynamic>));
    vaciar();
    return aInt(r['pedido_id']);
  }
}
