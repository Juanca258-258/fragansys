// Equivalente a catalogo-publico/back_catalogo_pub.php: obtiene del servidor las secciones,
// los perfumes (con sus decants y frascos con stock) y los combos.

import '../conexion/conexion_api.dart';

const String archivoCatalogo = 'catalogo-publico/act_catalogo_pub.php';

class Seccion {
  Seccion({
    required this.clave,
    required this.menu,
    required this.titulo,
    required this.subtitulo,
    required this.filtraGenero,
  });

  factory Seccion.desdeJson(Map<String, dynamic> j) => Seccion(
        clave: aTexto(j['clave']),
        menu: aTexto(j['menu']),
        titulo: aTexto(j['titulo']),
        subtitulo: aTexto(j['subtitulo']),
        filtraGenero: j['filtra_genero'] == true,
      );

  final String clave; // inicio, completo, decants, perfumes, combos, mujer, hombre
  final String menu;
  final String titulo;
  final String subtitulo;
  final bool filtraGenero;
}

/// Una presentación del perfume: decant de X ml o frasco completo
class Variante {
  Variante({
    required this.id,
    required this.tipo,
    required this.ml,
    required this.precio,
    required this.etiqueta,
  });

  factory Variante.desdeJson(Map<String, dynamic> j) => Variante(
        id: aInt(j['id']),
        tipo: aTexto(j['tipo']),
        ml: aTexto(j['ml']),
        precio: aDouble(j['precio']),
        etiqueta: aTexto(j['etiqueta']),
      );

  final int id; // id de la presentación (el "producto_id" del carrito)
  final String tipo; // decant | frasco
  final String ml;
  final double precio;
  final String etiqueta;
}

class Perfume {
  Perfume({
    required this.id,
    required this.nombre,
    required this.marca,
    required this.genero,
    required this.imagen,
    required this.variantes,
  });

  factory Perfume.desdeJson(Map<String, dynamic> j) => Perfume(
        id: aInt(j['id']),
        nombre: aTexto(j['nombre']),
        marca: aTexto(j['marca']),
        genero: aTexto(j['genero']),
        imagen: aTexto(j['imagen']),
        variantes: (j['variantes'] as List? ?? [])
            .map((v) => Variante.desdeJson(v as Map<String, dynamic>))
            .toList(),
      );

  final int id;
  final String nombre;
  final String marca;
  final String genero; // hombre | mujer | unisex
  final String imagen;
  final List<Variante> variantes;

  /// Filtro de búsqueda por nombre o marca (como la función filtrar() de index.php)
  bool coincide(String texto) {
    final t = texto.toLowerCase().trim();
    return t.isEmpty || nombre.toLowerCase().contains(t) || marca.toLowerCase().contains(t);
  }
}

class ItemCombo {
  ItemCombo({required this.cantidad, required this.etiqueta, required this.marca});

  factory ItemCombo.desdeJson(Map<String, dynamic> j) => ItemCombo(
        cantidad: aInt(j['cantidad']),
        etiqueta: aTexto(j['etiqueta']),
        marca: aTexto(j['marca']),
      );

  final int cantidad;
  final String etiqueta;
  final String marca;
}

class Combo {
  Combo({
    required this.id,
    required this.nombre,
    required this.descripcion,
    required this.precio,
    required this.imagen,
    required this.disponible,
    required this.items,
  });

  factory Combo.desdeJson(Map<String, dynamic> j) => Combo(
        id: aInt(j['id']),
        nombre: aTexto(j['nombre']),
        descripcion: aTexto(j['descripcion']),
        precio: aDouble(j['precio']),
        imagen: aTexto(j['imagen']),
        disponible: j['disponible'] == true,
        items: (j['items'] as List? ?? [])
            .map((i) => ItemCombo.desdeJson(i as Map<String, dynamic>))
            .toList(),
      );

  final int id;
  final String nombre;
  final String descripcion;
  final double precio;
  final String imagen;
  final bool disponible;
  final List<ItemCombo> items;
}

class DatosSeccion {
  DatosSeccion({
    required this.seccion,
    required this.secciones,
    required this.perfumes,
    required this.combos,
  });

  final String seccion;
  final List<Seccion> secciones;
  final List<Perfume> perfumes;
  final List<Combo> combos;
}

class BackCatalogoPub {
  BackCatalogoPub._();

  /// Igual que entrar a index.php?seccion=...
  static Future<DatosSeccion> obtenerSeccion(String seccion) async {
    final r = await ConexionApi.enviarOk(archivoCatalogo, {'accion': 'catalogo', 'seccion': seccion});
    return DatosSeccion(
      seccion: aTexto(r['seccion']),
      secciones: (r['secciones'] as List? ?? [])
          .map((s) => Seccion.desdeJson(s as Map<String, dynamic>))
          .toList(),
      perfumes: (r['perfumes'] as List? ?? [])
          .map((p) => Perfume.desdeJson(p as Map<String, dynamic>))
          .toList(),
      combos: (r['combos'] as List? ?? [])
          .map((c) => Combo.desdeJson(c as Map<String, dynamic>))
          .toList(),
    );
  }
}
