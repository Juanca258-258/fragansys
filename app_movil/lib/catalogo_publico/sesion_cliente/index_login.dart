// Equivalente a sesion-cliente/indexS.php: inicio de sesión del cliente.

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../conexion/conexion_api.dart';
import '../../helpers/helpers.dart';
import 'back_login_cliente.dart';
import 'index_pedidos.dart';

class IndexLogin extends StatefulWidget {
  const IndexLogin({super.key});

  @override
  State<IndexLogin> createState() => _IndexLoginState();
}

class _IndexLoginState extends State<IndexLogin> {
  final _formulario = GlobalKey<FormState>();
  final _correo = TextEditingController();
  final _password = TextEditingController();
  bool _verPassword = false;
  bool _enviando = false;
  String? _error;

  @override
  void dispose() {
    _correo.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _iniciarSesion() async {
    if (!(_formulario.currentState?.validate() ?? false)) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      await context.read<SesionCliente>().iniciarSesion(_correo.text, _password.text);
      if (!mounted) return;
      mostrarMensaje(context, '¡Bienvenido(a), ${context.read<SesionCliente>().cliente!.primerNombre}!');
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const IndexPedidos()));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.mensaje);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const LogoFragansys()),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formulario,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Icon(Icons.account_circle_outlined, size: 64, color: ColoresFragansys.dorado),
                const SizedBox(height: 8),
                const Text('Iniciar Sesión',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        fontFamily: fuenteSerif,
                        fontSize: 26,
                        fontWeight: FontWeight.bold,
                        color: ColoresFragansys.cafe)),
                const Text('Consulta y da seguimiento a tus pedidos',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        fontFamily: fuenteSerif, fontStyle: FontStyle.italic, color: Colors.black54)),
                const SizedBox(height: 24),
                if (_error != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 16),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                        color: const Color(0xFFF8D7DA), borderRadius: BorderRadius.circular(10)),
                    child: Text(_error!, style: const TextStyle(color: Color(0xFF842029))),
                  ),
                TextFormField(
                  controller: _correo,
                  keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                      labelText: 'Correo electrónico', prefixIcon: Icon(Icons.mail_outline)),
                  validator: (v) =>
                      (v == null || !v.contains('@')) ? 'Ingresa un correo válido.' : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _password,
                  obscureText: !_verPassword,
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => _iniciarSesion(),
                  decoration: InputDecoration(
                    labelText: 'Contraseña',
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                      icon: Icon(_verPassword ? Icons.visibility_off : Icons.visibility),
                      onPressed: () => setState(() => _verPassword = !_verPassword),
                    ),
                  ),
                  validator: (v) => (v == null || v.isEmpty) ? 'Ingresa tu contraseña.' : null,
                ),
                const SizedBox(height: 24),
                FilledButton(
                  style: FilledButton.styleFrom(backgroundColor: ColoresFragansys.cafe),
                  onPressed: _enviando ? null : _iniciarSesion,
                  child: _enviando
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('INICIAR SESIÓN'),
                ),
                const SizedBox(height: 20),
                const Text(
                  '¿Aún no tienes cuenta? Se crea automáticamente al hacer tu primer pedido desde el carrito.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.black54, fontSize: 13),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
