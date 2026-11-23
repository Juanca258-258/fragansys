// Equivalente a helpers.php y a los estilos comunes del catálogo: colores, tipografías,
// formato de dinero y pequeños widgets que se usan en varias pantallas.

import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../conexion/conexion_api.dart';

/// Colores del catálogo web (.text-brown, .text-gold, .bg-dark-card, .hero-section ...)
class ColoresFragansys {
  ColoresFragansys._();

  static const Color cafe = Color(0xFF4A3320);
  static const Color dorado = Color(0xFFB58C5A);
  static const Color tarjetaOscura = Color(0xFF111111);
  static const Color crema = Color(0xFFFEFAF6);
  static const Color beige = Color(0xFFF0E6D6);
  static const Color fondo = Color(0xFFF8F9FA);
  static const Color mujer = Color(0xFFE83E8C);
  static const Color hombre = Color(0xFF0D6EFD);
  static const Color exito = Color(0xFF198754);
}

/// La web usa Georgia para títulos; en Android la fuente serif del sistema.
const String fuenteSerif = 'serif';

ThemeData temaFragansys() {
  final esquema = ColorScheme.fromSeed(
    seedColor: ColoresFragansys.cafe,
    primary: ColoresFragansys.cafe,
    secondary: ColoresFragansys.dorado,
    surface: Colors.white,
  );
  return ThemeData(
    colorScheme: esquema,
    scaffoldBackgroundColor: ColoresFragansys.fondo,
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: ColoresFragansys.cafe,
      elevation: 1,
      surfaceTintColor: Colors.white,
    ),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      filled: true,
      fillColor: Colors.white,
      isDense: true,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: ColoresFragansys.dorado,
        foregroundColor: Colors.white,
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        textStyle: const TextStyle(fontWeight: FontWeight.bold, letterSpacing: 1),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: ColoresFragansys.cafe,
        shape: const StadiumBorder(),
        side: const BorderSide(color: ColoresFragansys.cafe),
      ),
    ),
    snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
  );
}

final NumberFormat _formatoDinero = NumberFormat('#,##0.00', 'en_US');

/// Igual que '$' . number_format($valor, 2) en PHP
String dinero(double valor) => '\$${_formatoDinero.format(valor)}';

void mostrarMensaje(BuildContext context, String texto, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(texto),
      backgroundColor: error ? Colors.red.shade700 : ColoresFragansys.cafe,
    ));
}

/// Logo de la barra superior: círculo café con la "F" y el nombre
class LogoFragansys extends StatelessWidget {
  const LogoFragansys({super.key});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 34,
          height: 34,
          alignment: Alignment.center,
          decoration: const BoxDecoration(color: ColoresFragansys.cafe, shape: BoxShape.circle),
          child: const Text('F',
              style: TextStyle(color: Colors.white, fontFamily: fuenteSerif, fontSize: 18)),
        ),
        const SizedBox(width: 8),
        const Text('Fragansys',
            style: TextStyle(color: ColoresFragansys.cafe, fontFamily: fuenteSerif, fontSize: 22)),
      ],
    );
  }
}

/// Título de sección con el rombo dorado y la línea separadora (como en modal_catalogo_pub.php)
class TituloSeccion extends StatelessWidget {
  const TituloSeccion({super.key, required this.titulo, this.subtitulo});

  final String titulo;
  final String? subtitulo;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Text('◆', style: TextStyle(color: ColoresFragansys.dorado, fontSize: 18)),
            const SizedBox(width: 8),
            Flexible(
              child: Text(titulo,
                  style: const TextStyle(
                      fontFamily: fuenteSerif, fontSize: 22, color: ColoresFragansys.cafe)),
            ),
            const SizedBox(width: 12),
            const Expanded(child: Divider(color: Color(0xFFD8CACA))),
          ],
        ),
        if (subtitulo != null && subtitulo!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(subtitulo!,
                style: const TextStyle(
                    fontFamily: fuenteSerif, fontStyle: FontStyle.italic, color: Colors.black54)),
          ),
      ],
    );
  }
}

/// Imagen de un producto guardada en uploads/. Si no hay imagen o falla, muestra un ícono.
class ImagenProducto extends StatelessWidget {
  const ImagenProducto({
    super.key,
    required this.ruta,
    this.alto,
    this.icono = Icons.water_drop_outlined,
    this.colorIcono = ColoresFragansys.dorado,
  });

  final String ruta;
  final double? alto;
  final IconData icono;
  final Color colorIcono;

  @override
  Widget build(BuildContext context) {
    final respaldo = Icon(icono, color: colorIcono, size: (alto ?? 40) * 0.55);
    if (ruta.isEmpty) {
      return SizedBox(height: alto, child: Center(child: respaldo));
    }
    return Image.network(
      ConexionApi.urlImagen(ruta),
      height: alto,
      fit: BoxFit.contain,
      errorBuilder: (_, _, _) => SizedBox(height: alto, child: Center(child: respaldo)),
      loadingBuilder: (context, child, progreso) => progreso == null
          ? child
          : SizedBox(
              height: alto,
              child: const Center(
                  child: SizedBox(
                      width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)))),
    );
  }
}

/// Mensaje de error con botón de reintentar
class VistaError extends StatelessWidget {
  const VistaError({super.key, required this.mensaje, required this.alReintentar});

  final String mensaje;
  final VoidCallback alReintentar;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off, size: 56, color: ColoresFragansys.dorado),
            const SizedBox(height: 12),
            Text(mensaje, textAlign: TextAlign.center, style: const TextStyle(color: Colors.black54)),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: alReintentar,
              icon: const Icon(Icons.refresh),
              label: const Text('Reintentar'),
            ),
          ],
        ),
      ),
    );
  }
}
