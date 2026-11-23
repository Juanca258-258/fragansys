// App Android del catálogo público de Fragansys.
// Misma organización que el proyecto PHP (catalogo-publico/):
//
//   lib/conexion/                      ≈ conexion.php   (a dónde se conecta la app)
//   lib/helpers/                       ≈ helpers.php    (formato, colores, widgets comunes)
//   lib/catalogo_publico/              ≈ catalogo-publico/   index, back_, modal_, modalg_
//   lib/catalogo_publico/carrito/      ≈ catalogo-publico/carrito/
//   lib/catalogo_publico/pago/         ≈ catalogo-publico/pago/
//   lib/catalogo_publico/sesion_cliente/ ≈ catalogo-publico/sesion-cliente/
//
// Los datos vienen de la base de datos "fragansys" (MySQL) a través de la API en PHP de fragansys/api/.

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import 'catalogo_publico/carrito/back_carrito_pub.dart';
import 'catalogo_publico/index_catalogo.dart';
import 'catalogo_publico/sesion_cliente/back_login_cliente.dart';
import 'conexion/conexion_api.dart';
import 'helpers/helpers.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await ConexionApi.cargar();
  final sesion = SesionCliente();
  final carrito = CarritoCompras();
  await Future.wait([sesion.cargar(), carrito.cargar()]);

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: sesion),
        ChangeNotifierProvider.value(value: carrito),
      ],
      child: const FragansysApp(),
    ),
  );
}

class FragansysApp extends StatelessWidget {
  const FragansysApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Fragansys',
      debugShowCheckedModeBanner: false,
      theme: temaFragansys(),
      home: const IndexCatalogo(),
    );
  }
}
