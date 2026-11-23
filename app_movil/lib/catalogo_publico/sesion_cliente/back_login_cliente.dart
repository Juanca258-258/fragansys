// Equivalente a sesion-cliente/back_login_cliente.php y act_login_cliente.php.
// En la web la sesión vive en $_SESSION; en la app se guarda en el teléfono un token firmado por la API.

import 'dart:convert';

import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../conexion/conexion_api.dart';

const String archivoLoginCliente = 'catalogo-publico/sesion-cliente/act_login_cliente.php';

class Cliente {
  Cliente({
    required this.id,
    required this.nombre,
    required this.correo,
    required this.telefono,
    required this.direccion,
  });

  factory Cliente.desdeJson(Map<String, dynamic> j) => Cliente(
        id: aInt(j['id']),
        nombre: aTexto(j['nombre']),
        correo: aTexto(j['correo']),
        telefono: aTexto(j['telefono']),
        direccion: aTexto(j['direccion']),
      );

  final int id;
  final String nombre;
  final String correo;
  final String telefono;
  final String direccion;

  /// En la barra solo va el primer nombre (igual que en index.php)
  String get primerNombre => nombre.trim().split(' ').first;

  Map<String, dynamic> aJson() => {
        'id': id,
        'nombre': nombre,
        'correo': correo,
        'telefono': telefono,
        'direccion': direccion,
      };
}

/// Estado de la sesión del cliente, compartido en toda la app (Provider).
class SesionCliente extends ChangeNotifier {
  static const _claveToken = 'cliente_token';
  static const _claveCliente = 'cliente_datos';

  Cliente? _cliente;

  Cliente? get cliente => _cliente;
  bool get activa => _cliente != null && ConexionApi.token != null;

  /// Recupera la sesión guardada en el teléfono y, si hay conexión, confirma con el servidor que siga válida.
  Future<void> cargar() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_claveToken);
    final datos = prefs.getString(_claveCliente);
    if (token == null || datos == null) {
      return;
    }
    ConexionApi.token = token;
    _cliente = Cliente.desdeJson(jsonDecode(datos) as Map<String, dynamic>);
    notifyListeners();
    refrescarPerfil();
  }

  Future<void> refrescarPerfil() async {
    if (!activa) {
      return;
    }
    try {
      final r = await ConexionApi.enviarOk(archivoLoginCliente, {'accion': 'perfil'});
      await establecer(ConexionApi.token!, Cliente.desdeJson(r['cliente'] as Map<String, dynamic>));
    } on ApiException catch (e) {
      if (e.sinSesion) {
        await cerrar();
      }
      // Sin conexión: se conserva la sesión guardada
    }
  }

  /// accion=iniciar_sesion. Lanza ApiException con el mensaje si el correo o la contraseña no son correctos.
  Future<void> iniciarSesion(String correo, String password) async {
    final r = await ConexionApi.enviarOk(archivoLoginCliente, {
      'accion': 'iniciar_sesion',
      'correo': correo.trim(),
      'password': password,
    });
    await establecer(aTexto(r['token']), Cliente.desdeJson(r['cliente'] as Map<String, dynamic>));
  }

  /// Deja la sesión iniciada con un token que entregó la API (login, checkout o registro).
  Future<void> establecer(String token, Cliente cliente) async {
    ConexionApi.token = token;
    _cliente = cliente;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_claveToken, token);
    await prefs.setString(_claveCliente, jsonEncode(cliente.aJson()));
    notifyListeners();
  }

  /// Cierra la sesión. Igual que en la web, quien llama también debe vaciar el carrito.
  Future<void> cerrar() async {
    ConexionApi.token = null;
    _cliente = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_claveToken);
    await prefs.remove(_claveCliente);
    notifyListeners();
  }
}
