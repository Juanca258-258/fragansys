// Equivalente a conexion.php: aquí se configura a dónde se conecta la app.
//
// La app NO se conecta directo a MySQL (eso obligaría a guardar el usuario y la contraseña de la
// base de datos dentro del APK). Se conecta por HTTP a la API en PHP (carpeta fragansys/api), y esa
// API usa conexion.php para hablar con la base de datos "fragansys".

import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Error que se le muestra al cliente tal cual (los mensajes vienen en español desde la API).
class ApiException implements Exception {
  ApiException(this.mensaje, {this.sinSesion = false});

  final String mensaje;

  /// true cuando la API indica que la sesión del cliente expiró.
  final bool sinSesion;

  @override
  String toString() => mensaje;
}

class ConexionApi {
  ConexionApi._();

  /// Dirección de la carpeta "fragansys" en el servidor (Apache de AppServ).
  ///  - Emulador de Android: http://10.0.2.2/fragansys  (10.0.2.2 es la PC donde corre el emulador)
  ///  - Celular físico: `http://IP-DE-LA-PC/fragansys`, p. ej. http://192.168.1.70/fragansys
  /// Se puede fijar al compilar con --dart-define=API_URL=... o cambiar dentro de la app (menú > Servidor).
  static const String urlPorDefecto =
      String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2/fragansys');

  static const String _clavePreferencias = 'url_servidor';
  static const Duration _tiempoEspera = Duration(seconds: 30);

  static String urlBase = urlPorDefecto;

  /// Token de la sesión del cliente; se envía en cada petición (lo administra SesionCliente).
  static String? token;

  static Future<void> cargar() async {
    final prefs = await SharedPreferences.getInstance();
    urlBase = prefs.getString(_clavePreferencias) ?? urlPorDefecto;
  }

  static Future<void> cambiarServidor(String url) async {
    var limpia = url.trim();
    while (limpia.endsWith('/')) {
      limpia = limpia.substring(0, limpia.length - 1);
    }
    if (!limpia.startsWith('http://') && !limpia.startsWith('https://')) {
      limpia = 'http://$limpia';
    }
    urlBase = limpia;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_clavePreferencias, limpia);
  }

  /// Las imágenes se guardan en la BD como "uploads/perfumes/archivo.png", relativas a la raíz del proyecto.
  static String urlImagen(String ruta) => ruta.isEmpty ? '' : '$urlBase/$ruta';

  /// Llama a un archivo de la API (ruta relativa a fragansys/api) y devuelve el JSON.
  /// No revisa "status": quien llama decide qué hacer con cada respuesta.
  static Future<Map<String, dynamic>> enviar(String archivo, Map<String, String> datos) async {
    final cuerpo = <String, String>{...datos};
    if (token != null && !cuerpo.containsKey('token')) {
      cuerpo['token'] = token!;
    }

    final http.Response respuesta;
    try {
      respuesta = await http
          .post(Uri.parse('$urlBase/api/$archivo'), body: cuerpo)
          .timeout(_tiempoEspera);
    } on TimeoutException {
      throw ApiException('El servidor tardó demasiado en responder. Intenta de nuevo.');
    } catch (_) {
      throw ApiException('No se pudo conectar con el servidor ($urlBase). Revisa tu conexión.');
    }

    try {
      final json = jsonDecode(utf8.decode(respuesta.bodyBytes));
      if (json is Map<String, dynamic>) {
        return json;
      }
    } catch (_) {
      // Respuesta que no es JSON: normalmente un error de PHP o una dirección equivocada
    }
    throw ApiException('Respuesta inesperada del servidor (HTTP ${respuesta.statusCode}).');
  }

  /// Igual que [enviar], pero si la API responde status = "error" lanza [ApiException] con su mensaje.
  static Future<Map<String, dynamic>> enviarOk(String archivo, Map<String, String> datos) async {
    final json = await enviar(archivo, datos);
    if (json['status'] == 'error') {
      throw ApiException(
        (json['message'] ?? 'Ocurrió un error.').toString(),
        sinSesion: json['sin_sesion'] == true,
      );
    }
    return json;
  }
}

// Lectura segura de los valores del JSON (PHP a veces manda números como texto)
double aDouble(dynamic valor) => valor is num ? valor.toDouble() : double.tryParse('$valor') ?? 0;
int aInt(dynamic valor) => valor is num ? valor.toInt() : int.tryParse('$valor') ?? 0;
String aTexto(dynamic valor) => valor == null ? '' : '$valor';
