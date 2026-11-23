// Pruebas de la lógica del carrito y del formato de dinero (no necesitan el servidor).

import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:prueba/catalogo_publico/carrito/back_carrito_pub.dart';
import 'package:prueba/helpers/helpers.dart';
import 'package:shared_preferences/shared_preferences.dart';

ItemCarrito _item({String clave = '13', int cantidad = 1, int stock = 3, double precio = 150}) =>
    ItemCarrito.desdeJson({
      'clave': clave,
      'tipo': 'presentacion',
      'id': int.parse(clave),
      'nombre': 'Perfume',
      'marca': 'Marca',
      'presentacion': 'decant',
      'contenido_ml': '5',
      'precio': precio,
      'cantidad': cantidad,
      'stock': stock,
      'piezas': 0,
      'imagen': '',
    });

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  test('dinero() da el mismo formato que number_format de PHP', () {
    expect(dinero(1500), '\$1,500.00');
    expect(dinero(99.5), '\$99.50');
  });

  test('El carrito guardado se recupera con sus cantidades y subtotal', () async {
    final item = _item(cantidad: 2);
    SharedPreferences.setMockInitialValues({'carrito': jsonEncode([item.aJson()])});
    final carrito = CarritoCompras();
    await carrito.cargar();

    expect(carrito.totalItems, 2);
    expect(carrito.subtotal, 300);
    expect(carrito.cantidadDe('13'), 2);
  });

  test('No se puede pasar del stock y cantidad 0 elimina el producto', () async {
    SharedPreferences.setMockInitialValues({'carrito': jsonEncode([_item(stock: 3).aJson()])});
    final carrito = CarritoCompras();
    await carrito.cargar();

    expect(carrito.actualizarCantidad('13', 5), 'Stock máximo disponible: 3');
    expect(carrito.cantidadDe('13'), 1);

    expect(carrito.actualizarCantidad('13', 3), isNull);
    expect(carrito.cantidadDe('13'), 3);

    expect(carrito.actualizarCantidad('13', 0), isNull);
    expect(carrito.vacio, isTrue);
  });
}
