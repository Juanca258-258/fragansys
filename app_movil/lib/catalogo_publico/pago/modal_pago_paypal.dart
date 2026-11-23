// Ventana de pago dentro de la app (en la web son los botones del SDK de PayPal en indexPago.php).
// Se usa de dos formas:
//   - PayPal: abre la página de PayPal para que el cliente entre a su cuenta.
//   - Tarjeta: abre tarjeta.php del servidor, con el mismo botón de tarjeta del SDK que la web.
// Cuando la página llega a la dirección de retorno, la ventana se cierra y devuelve el resultado.

import 'dart:convert';

import 'package:material_ui/material_ui.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../helpers/helpers.dart';

/// Resultado de la ventana: aprobado (con el id de la orden si la página lo mandó) o cancelado.
class ResultadoVentanaPago {
  ResultadoVentanaPago({required this.aprobado, this.ordenId = ''});

  final bool aprobado;
  final String ordenId;
}

class VentanaPaypal extends StatefulWidget {
  const VentanaPaypal({
    super.key,
    required this.url,
    required this.urlAprobado,
    required this.urlCancelado,
    this.datosPost,
    this.titulo = 'Pago seguro con PayPal',
  });

  final String url;

  /// Si se indica, la página se abre con POST (así el token no viaja en la dirección).
  final Map<String, String>? datosPost;
  final String urlAprobado;
  final String urlCancelado;
  final String titulo;

  @override
  State<VentanaPaypal> createState() => _VentanaPaypalState();
}

class _VentanaPaypalState extends State<VentanaPaypal> {
  late final WebViewController _control;
  int _progreso = 0;
  bool _cerrada = false;

  @override
  void initState() {
    super.initState();
    _control = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(NavigationDelegate(
        onProgress: (p) {
          if (mounted) setState(() => _progreso = p);
        },
        onNavigationRequest: (peticion) {
          final url = peticion.url;
          if (url.startsWith(widget.urlAprobado)) {
            final orden = Uri.parse(url).queryParameters['orden'] ?? '';
            _cerrar(ResultadoVentanaPago(aprobado: true, ordenId: orden));
            return NavigationDecision.prevent;
          }
          if (url.startsWith(widget.urlCancelado)) {
            _cerrar(ResultadoVentanaPago(aprobado: false));
            return NavigationDecision.prevent;
          }
          return NavigationDecision.navigate;
        },
      ));

    final post = widget.datosPost;
    if (post == null) {
      _control.loadRequest(Uri.parse(widget.url));
    } else {
      _control.loadRequest(
        Uri.parse(widget.url),
        method: LoadRequestMethod.post,
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: utf8.encode(Uri(queryParameters: post).query),
      );
    }
  }

  void _cerrar(ResultadoVentanaPago resultado) {
    if (_cerrada || !mounted) return;
    _cerrada = true;
    Navigator.pop(context, resultado);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.titulo, style: const TextStyle(fontFamily: fuenteSerif)),
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => _cerrar(ResultadoVentanaPago(aprobado: false)),
        ),
        bottom: _progreso < 100
            ? PreferredSize(
                preferredSize: const Size.fromHeight(3),
                child: LinearProgressIndicator(value: _progreso / 100, color: ColoresFragansys.dorado),
              )
            : null,
      ),
      body: WebViewWidget(controller: _control),
    );
  }
}
