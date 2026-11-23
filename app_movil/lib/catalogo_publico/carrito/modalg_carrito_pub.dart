// Equivalente al modal "Datos de Entrega" del carrito (modal_carrito_pub.php + el JavaScript de indexC.php).
// Pasos, igual que en la web:
//   - Con sesión iniciada: se usan los datos de la cuenta y solo se piden los que falten.
//   - Sin sesión: 1) correo  2a) si ya está registrado, su contraseña  2b) si es nuevo, sus datos y una contraseña.
// Al confirmar se registra el pedido (pago pendiente) y se pasa a la pantalla de pago.

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../conexion/conexion_api.dart';
import '../../helpers/helpers.dart';
import '../pago/index_pago.dart';
import '../sesion_cliente/back_login_cliente.dart';
import 'back_carrito_pub.dart';

enum _Paso { correo, password, nuevo }

class PantallaCheckout extends StatefulWidget {
  const PantallaCheckout({super.key});

  @override
  State<PantallaCheckout> createState() => _PantallaCheckoutState();
}

class _PantallaCheckoutState extends State<PantallaCheckout> {
  final _formulario = GlobalKey<FormState>();
  final _correo = TextEditingController();
  final _nombre = TextEditingController();
  final _telefono = TextEditingController();
  final _direccion = TextEditingController();
  final _password = TextEditingController();
  final _confirmar = TextEditingController();
  final _notas = TextEditingController();

  _Paso _paso = _Paso.correo;
  bool _sinPassword = false; // cliente registrado antes de que se pidiera contraseña
  bool _enviando = false;

  @override
  void dispose() {
    for (final c in [_correo, _nombre, _telefono, _direccion, _password, _confirmar, _notas]) {
      c.dispose();
    }
    super.dispose();
  }

  void _cambiarCorreo() {
    _password.clear();
    _confirmar.clear();
    setState(() => _paso = _Paso.correo);
  }

  Future<void> _ejecutar(Future<void> Function() accion) async {
    if (!(_formulario.currentState?.validate() ?? false)) return;
    FocusScope.of(context).unfocus();
    setState(() => _enviando = true);
    try {
      await accion();
    } on ApiException catch (e) {
      if (!mounted) return;
      // La sesión guardada ya no es válida: se cierra y el formulario vuelve a pedir el correo
      if (e.sinSesion) await context.read<SesionCliente>().cerrar();
      if (mounted) mostrarMensaje(context, e.mensaje, error: true);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  // Paso 1: ¿el correo ya está registrado?
  Future<void> _buscarCliente() async {
    final r = await CarritoCompras.buscarCliente(_correo.text);
    setState(() {
      _sinPassword = r.sinPassword;
      _paso = r.existe ? _Paso.password : _Paso.nuevo;
    });
  }

  // Paso 2a: contraseña del cliente registrado (deja su sesión iniciada)
  Future<void> _verificarCliente() async {
    await CarritoCompras.verificarCliente(
      context.read<SesionCliente>(),
      correo: _correo.text,
      password: _password.text,
      confirmar: _confirmar.text,
    );
    if (mounted) mostrarMensaje(context, 'Sesión iniciada. Revisa tus datos de entrega.');
  }

  Future<void> _realizarPedido() async {
    final sesion = context.read<SesionCliente>();
    final pedidoId = await context.read<CarritoCompras>().realizarPedido(sesion, {
      'cliente_correo': _correo.text.trim(),
      'cliente_nombre': _nombre.text.trim(),
      'telefono_contacto': _telefono.text.trim(),
      'direccion_entrega': _direccion.text.trim(),
      'password': _password.text,
      'password_confirmar': _confirmar.text,
      'notas': _notas.text.trim(),
    });
    if (!mounted) return;
    // El pedido quedó registrado como pendiente: el cliente pasa a pagarlo con PayPal
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => IndexPago(pedidoId: pedidoId, recienCreado: true)),
      (ruta) => ruta.isFirst,
    );
  }

  String? _requerido(String? v) => (v == null || v.trim().isEmpty) ? 'Campo obligatorio' : null;

  @override
  Widget build(BuildContext context) {
    final cliente = context.watch<SesionCliente>().cliente;
    final carrito = context.watch<CarritoCompras>();

    final List<Widget> campos;
    final String textoBoton;
    final Future<void> Function() accion;

    if (cliente != null) {
      // Con sesión: no se piden correo ni contraseña
      final faltaTelefono = cliente.telefono.isEmpty;
      final faltaDireccion = cliente.direccion.isEmpty;
      campos = [
        Card(
          elevation: 0,
          color: ColoresFragansys.crema,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Hola, ${cliente.nombre}',
                    style: const TextStyle(fontWeight: FontWeight.bold, color: ColoresFragansys.cafe)),
                const SizedBox(height: 4),
                Text('Correo: ${cliente.correo}', style: const TextStyle(color: Colors.black54)),
                if (!faltaTelefono)
                  Text('Teléfono: ${cliente.telefono}', style: const TextStyle(color: Colors.black54)),
                if (!faltaDireccion)
                  Text('Dirección: ${cliente.direccion}', style: const TextStyle(color: Colors.black54)),
              ],
            ),
          ),
        ),
        if (faltaTelefono) _campoTelefono(),
        if (faltaDireccion) _campoDireccion(),
        ..._confirmacion(carrito),
      ];
      textoBoton = 'CONTINUAR AL PAGO';
      accion = _realizarPedido;
    } else {
      campos = [
        TextFormField(
          controller: _correo,
          enabled: _paso == _Paso.correo,
          keyboardType: TextInputType.emailAddress,
          autocorrect: false,
          maxLength: 150,
          decoration: InputDecoration(
            labelText: 'Correo electrónico *',
            helperText: _paso == _Paso.correo ? 'Si ya compraste antes, usaremos los datos que registraste.' : null,
            suffixIcon: _paso == _Paso.correo
                ? null
                : TextButton(onPressed: _cambiarCorreo, child: const Text('Cambiar')),
          ),
          validator: (v) =>
              (v == null || !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(v.trim()))
                  ? 'El correo electrónico no es válido.'
                  : null,
        ),
        if (_paso == _Paso.password) ...[
          Text(
            _sinPassword
                ? 'Tu cuenta aún no tiene contraseña. Crea una para usar tus datos guardados.'
                : 'Este correo ya está registrado. Ingresa tu contraseña para usar tus datos.',
            style: const TextStyle(color: Colors.black54),
          ),
          _campoPassword(_sinPassword ? 'Crea una contraseña *' : 'Contraseña *'),
          if (_sinPassword) _campoConfirmar(),
        ],
        if (_paso == _Paso.nuevo) ...[
          const Text('Es tu primera compra: completa tus datos y crea una contraseña para tu cuenta.',
              style: TextStyle(color: Colors.black54)),
          TextFormField(
            controller: _nombre,
            maxLength: 120,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(labelText: 'Nombre completo *'),
            validator: _requerido,
          ),
          _campoTelefono(),
          _campoDireccion(),
          _campoPassword('Crea una contraseña *'),
          _campoConfirmar(),
          ..._confirmacion(carrito),
        ],
      ];
      switch (_paso) {
        case _Paso.correo:
          textoBoton = 'CONTINUAR';
          accion = _buscarCliente;
        case _Paso.password:
          textoBoton = 'CONTINUAR';
          accion = _verificarCliente;
        case _Paso.nuevo:
          textoBoton = 'CONTINUAR AL PAGO';
          accion = _realizarPedido;
      }
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Datos de Entrega', style: TextStyle(fontFamily: fuenteSerif))),
      body: Form(
        key: _formulario,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            for (final c in campos) Padding(padding: const EdgeInsets.only(bottom: 14), child: c),
            const SizedBox(height: 8),
            FilledButton(
              onPressed: _enviando ? null : () => _ejecutar(accion),
              child: _enviando
                  ? const SizedBox(
                      width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(textoBoton),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: _enviando ? null : () => Navigator.pop(context),
              child: const Text('Cancelar'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _campoTelefono() => TextFormField(
        controller: _telefono,
        keyboardType: TextInputType.phone,
        maxLength: 20,
        decoration: const InputDecoration(labelText: 'Teléfono de contacto *'),
        validator: _requerido,
      );

  Widget _campoDireccion() => TextFormField(
        controller: _direccion,
        maxLength: 255,
        maxLines: 2,
        decoration: const InputDecoration(labelText: 'Dirección de entrega *'),
        validator: _requerido,
      );

  Widget _campoPassword(String etiqueta) => TextFormField(
        controller: _password,
        obscureText: true,
        decoration: InputDecoration(labelText: etiqueta),
        validator: (v) {
          if (v == null || v.isEmpty) return 'Campo obligatorio';
          if (_paso != _Paso.password || _sinPassword) {
            if (v.length < 6) return 'La contraseña debe tener al menos 6 caracteres.';
          }
          return null;
        },
      );

  Widget _campoConfirmar() => TextFormField(
        controller: _confirmar,
        obscureText: true,
        decoration: const InputDecoration(labelText: 'Confirma tu contraseña *'),
        validator: (v) => v != _password.text ? 'Las contraseñas no coinciden.' : null,
      );

  // Notas y totales antes de confirmar
  List<Widget> _confirmacion(CarritoCompras carrito) => [
        TextFormField(
          controller: _notas,
          maxLength: 255,
          maxLines: 2,
          decoration: const InputDecoration(labelText: 'Notas para este pedido (opcional)'),
        ),
        Column(
          children: [
            const Divider(),
            _filaTotal('Productos', dinero(carrito.subtotal)),
            _filaTotal('Envío', carrito.envio > 0 ? dinero(carrito.envio) : 'Gratis'),
            const SizedBox(height: 6),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Total a pagar',
                    style: TextStyle(
                        fontFamily: fuenteSerif, fontWeight: FontWeight.bold, color: ColoresFragansys.cafe)),
                Text(dinero(carrito.total),
                    style: const TextStyle(
                        fontSize: 20, fontWeight: FontWeight.bold, color: ColoresFragansys.dorado)),
              ],
            ),
          ],
        ),
      ];

  Widget _filaTotal(String etiqueta, String valor) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(etiqueta, style: const TextStyle(color: Colors.black54)),
            Text(valor, style: const TextStyle(color: Colors.black54)),
          ],
        ),
      );
}
