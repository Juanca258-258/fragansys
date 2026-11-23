// Equivalente a catalogo-publico/modal_catalogo_pub.php: tarjetas oscuras de perfumes y combos.

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../conexion/conexion_api.dart';
import '../helpers/helpers.dart';
import 'back_catalogo_pub.dart';
import 'carrito/back_carrito_pub.dart';
import 'modalg_catalogo_pub.dart';

/// Ícono del género en la esquina de la tarjeta (rosa mujer, azul hombre, gris unisex)
class IconoGenero extends StatelessWidget {
  const IconoGenero({super.key, required this.genero});

  final String genero;

  @override
  Widget build(BuildContext context) {
    final (color, icono) = switch (genero) {
      'mujer' => (ColoresFragansys.mujer, Icons.female),
      'hombre' => (ColoresFragansys.hombre, Icons.male),
      _ => (const Color(0xFF888888), Icons.transgender),
    };
    return Container(
      width: 28,
      height: 28,
      decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: color)),
      child: Icon(icono, color: color, size: 16),
    );
  }
}

class EtiquetaDisponible extends StatelessWidget {
  const EtiquetaDisponible({super.key, this.disponible = true});

  final bool disponible;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: disponible ? ColoresFragansys.exito : Colors.grey.shade700,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(disponible ? 'DISPONIBLE' : 'AGOTADO',
          style: const TextStyle(
              color: Colors.white, fontSize: 9, letterSpacing: 1, fontWeight: FontWeight.bold)),
    );
  }
}

/// Botón "AGREGAR AL CARRITO" con los estados Agregando... / ¡AGREGADO! de la web
class BotonAgregarCarrito extends StatefulWidget {
  const BotonAgregarCarrito({super.key, required this.alAgregar, this.oscuro = true});

  /// Debe lanzar ApiException con el mensaje si no se pudo agregar
  final Future<void> Function() alAgregar;
  final bool oscuro;

  @override
  State<BotonAgregarCarrito> createState() => _BotonAgregarCarritoState();
}

class _BotonAgregarCarritoState extends State<BotonAgregarCarrito> {
  bool _cargando = false;
  bool _agregado = false;

  Future<void> _agregar() async {
    setState(() => _cargando = true);
    try {
      await widget.alAgregar();
      if (!mounted) return;
      setState(() => _agregado = true);
      Future.delayed(const Duration(seconds: 2), () {
        if (mounted) setState(() => _agregado = false);
      });
    } on ApiException catch (e) {
      if (mounted) mostrarMensaje(context, 'Atención: ${e.mensaje}', error: true);
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorBase = widget.oscuro ? Colors.white : ColoresFragansys.cafe;
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton(
        onPressed: _cargando || _agregado ? null : _agregar,
        style: OutlinedButton.styleFrom(
          foregroundColor: colorBase,
          disabledForegroundColor: Colors.white,
          backgroundColor: _agregado ? ColoresFragansys.exito : null,
          side: BorderSide(color: _agregado ? ColoresFragansys.exito : colorBase),
          padding: const EdgeInsets.symmetric(vertical: 10),
          textStyle: const TextStyle(fontSize: 11, letterSpacing: 1, fontWeight: FontWeight.w600),
        ),
        child: _cargando
            ? SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2, color: colorBase))
            : Text(_agregado ? '¡AGREGADO!' : 'AGREGAR AL CARRITO'),
      ),
    );
  }
}

class TarjetaPerfume extends StatefulWidget {
  const TarjetaPerfume({super.key, required this.perfume});

  final Perfume perfume;

  @override
  State<TarjetaPerfume> createState() => _TarjetaPerfumeState();
}

class _TarjetaPerfumeState extends State<TarjetaPerfume> {
  late Variante _variante = widget.perfume.variantes.first;

  @override
  Widget build(BuildContext context) {
    final perfume = widget.perfume;
    return Material(
      color: ColoresFragansys.tarjetaOscura,
      borderRadius: BorderRadius.circular(12),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => mostrarDetallePerfume(context, perfume),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [IconoGenero(genero: perfume.genero), const EtiquetaDisponible()],
              ),
              const SizedBox(height: 8),
              Expanded(
                child: Hero(
                  tag: 'perfume-${perfume.id}',
                  child: ImagenProducto(ruta: perfume.imagen, alto: 150),
                ),
              ),
              const SizedBox(height: 8),
              Text(perfume.marca.toUpperCase(),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      color: ColoresFragansys.dorado,
                      fontSize: 11,
                      letterSpacing: 1,
                      fontWeight: FontWeight.bold)),
              const SizedBox(height: 2),
              Text(perfume.nombre,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white, fontFamily: fuenteSerif, fontSize: 15)),
              const SizedBox(height: 8),
              // Selector oscuro de decant / frasco
              Container(
                height: 34,
                padding: const EdgeInsets.symmetric(horizontal: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFF212529),
                  border: Border.all(color: Colors.grey.shade700),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<Variante>(
                    value: _variante,
                    isExpanded: true,
                    dropdownColor: const Color(0xFF212529),
                    iconEnabledColor: Colors.white,
                    style: const TextStyle(color: Colors.white, fontSize: 12),
                    items: [
                      for (final v in perfume.variantes)
                        DropdownMenuItem(
                          value: v,
                          child: Text(v.etiqueta, overflow: TextOverflow.ellipsis),
                        ),
                    ],
                    onChanged: (v) => setState(() => _variante = v ?? _variante),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Text(dinero(_variante.precio),
                  style: const TextStyle(
                      color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              BotonAgregarCarrito(
                alAgregar: () => context.read<CarritoCompras>().agregarProducto(_variante),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class TarjetaCombo extends StatelessWidget {
  const TarjetaCombo({super.key, required this.combo});

  final Combo combo;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
          color: ColoresFragansys.tarjetaOscura, borderRadius: BorderRadius.circular(12)),
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Align(alignment: Alignment.centerRight, child: EtiquetaDisponible(disponible: combo.disponible)),
          ImagenProducto(ruta: combo.imagen, alto: 160, icono: Icons.card_giftcard),
          const SizedBox(height: 8),
          const Text('COMBO',
              style: TextStyle(
                  color: ColoresFragansys.dorado,
                  fontSize: 11,
                  letterSpacing: 1,
                  fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          Text(combo.nombre,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontFamily: fuenteSerif, fontSize: 18)),
          if (combo.descripcion.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(combo.descripcion,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white54, fontSize: 13)),
          ],
          const SizedBox(height: 12),
          for (final item in combo.items)
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('◆ ', style: TextStyle(color: ColoresFragansys.dorado, fontSize: 12)),
                  Expanded(
                    child: Text('${item.cantidad} × ${item.etiqueta}',
                        style: const TextStyle(color: Colors.white, fontSize: 13)),
                  ),
                ],
              ),
            ),
          const SizedBox(height: 12),
          Text(dinero(combo.precio),
              style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          if (combo.disponible)
            BotonAgregarCarrito(alAgregar: () => context.read<CarritoCompras>().agregarCombo(combo))
          else
            const SizedBox(
              width: double.infinity,
              child: OutlinedButton(onPressed: null, child: Text('AGOTADO')),
            ),
        ],
      ),
    );
  }
}
