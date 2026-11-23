// Equivalente a catalogo-publico/modalg_catalogo_pub.php: ventana emergente con los detalles del perfume.
// Muestra todas sus presentaciones con su precio y un botón para agregar cada una.

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../helpers/helpers.dart';
import 'back_catalogo_pub.dart';
import 'carrito/back_carrito_pub.dart';
import 'modal_catalogo_pub.dart';

Future<void> mostrarDetallePerfume(BuildContext context, Perfume perfume) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    backgroundColor: Colors.white,
    builder: (_) => DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.75,
      maxChildSize: 0.95,
      builder: (context, scroll) => ListView(
        controller: scroll,
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
        children: [
          Hero(tag: 'perfume-${perfume.id}', child: ImagenProducto(ruta: perfume.imagen, alto: 220)),
          const SizedBox(height: 16),
          Text(perfume.marca.toUpperCase(),
              textAlign: TextAlign.center,
              style: const TextStyle(
                  color: ColoresFragansys.dorado, letterSpacing: 1.5, fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          Text(perfume.nombre,
              textAlign: TextAlign.center,
              style: const TextStyle(
                  fontFamily: fuenteSerif, fontSize: 24, color: ColoresFragansys.cafe)),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconoGenero(genero: perfume.genero),
              const SizedBox(width: 8),
              Text(
                switch (perfume.genero) {
                  'mujer' => 'Para mujer',
                  'hombre' => 'Para hombre',
                  _ => 'Unisex',
                },
                style: const TextStyle(color: Colors.black54),
              ),
            ],
          ),
          const Divider(height: 32),
          const Text('Presentaciones disponibles',
              style: TextStyle(fontWeight: FontWeight.bold, color: ColoresFragansys.cafe)),
          const SizedBox(height: 8),
          for (final v in perfume.variantes)
            Card(
              elevation: 0,
              color: ColoresFragansys.crema,
              margin: const EdgeInsets.only(bottom: 10),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  children: [
                    Icon(v.tipo == 'frasco' ? Icons.local_drink_outlined : Icons.water_drop_outlined,
                        color: ColoresFragansys.dorado),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(v.tipo == 'frasco' ? 'Frasco completo' : 'Decant',
                              style: const TextStyle(fontWeight: FontWeight.w600)),
                          Text('${v.ml} ml · ${dinero(v.precio)}',
                              style: const TextStyle(color: Colors.black54)),
                        ],
                      ),
                    ),
                    SizedBox(
                      width: 150,
                      child: BotonAgregarCarrito(
                        oscuro: false,
                        alAgregar: () => context.read<CarritoCompras>().agregarProducto(v),
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    ),
  );
}
