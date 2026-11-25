// Equivalente a catalogo-publico/index.php: barra superior con sesión y carrito, menú de secciones
// (Destacados, Catálogo completo, Decants, Perfumes, Combos, Mujer, Hombre) y búsqueda.

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../conexion/conexion_api.dart';
import '../helpers/helpers.dart';
import 'back_catalogo_pub.dart';
import 'carrito/back_carrito_pub.dart';
import 'carrito/index_carrito.dart';
import 'modal_catalogo_pub.dart';
import 'sesion_cliente/back_login_cliente.dart';
import 'sesion_cliente/index_login.dart';
import 'sesion_cliente/index_pedidos.dart';

class IndexCatalogo extends StatefulWidget {
  const IndexCatalogo({super.key});

  @override
  State<IndexCatalogo> createState() => _IndexCatalogoState();
}

class _IndexCatalogoState extends State<IndexCatalogo> {
  List<Seccion>? _secciones;
  DatosSeccion? _datosInicio;
  String? _error;
  int _recarga = 0; // cambia al cambiar de servidor para volver a cargar todas las secciones

  @override
  void initState() {
    super.initState();
    _cargarSecciones();
  }

  Future<void> _cargarSecciones() async {
    setState(() => _error = null);
    try {
      final datos = await BackCatalogoPub.obtenerSeccion('inicio');
      if (!mounted) return;
      setState(() {
        _secciones = datos.secciones;
        _datosInicio = datos;
        _recarga++;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.mensaje);
    }
  }

  @override
  Widget build(BuildContext context) {
    final secciones = _secciones;
    if (secciones == null) {
      return Scaffold(
        appBar: AppBar(title: const LogoFragansys(), actions: [_MenuCliente(alCambiarServidor: _cargarSecciones)]),
        body: _error == null
            ? const Center(child: CircularProgressIndicator())
            : VistaError(mensaje: _error!, alReintentar: _cargarSecciones),
      );
    }

    return DefaultTabController(
      key: ValueKey(_recarga),
      length: secciones.length,
      child: Builder(builder: (context) {
        return Scaffold(
          appBar: AppBar(
            title: const LogoFragansys(),
            actions: [const _BotonCarrito(), _MenuCliente(alCambiarServidor: _cargarSecciones)],
            bottom: TabBar(
              isScrollable: true,
              tabAlignment: TabAlignment.start,
              indicatorColor: ColoresFragansys.dorado,
              labelColor: ColoresFragansys.cafe,
              unselectedLabelColor: Colors.black54,
              labelStyle: const TextStyle(fontWeight: FontWeight.w600, letterSpacing: 1, fontSize: 12),
              tabs: [
                for (final s in secciones)
                  Tab(
                    child: Text(
                      s.menu.toUpperCase(),
                      style: TextStyle(
                        color: switch (s.clave) {
                          'mujer' => Colors.red.shade700,
                          'hombre' => Colors.black87,
                          _ => null,
                        },
                      ),
                    ),
                  ),
              ],
            ),
          ),
          body: TabBarView(
            children: [
              for (final s in secciones)
                VistaSeccion(
                  seccion: s,
                  datosIniciales: s.clave == 'inicio' ? _datosInicio : null,
                  alVerCatalogoCompleto: () {
                    final indice = secciones.indexWhere((x) => x.clave == 'completo');
                    if (indice >= 0) DefaultTabController.of(context).animateTo(indice);
                  },
                ),
            ],
          ),
        );
      }),
    );
  }
}

/// Ícono del carrito con el contador (badgeCarritoCount)
class _BotonCarrito extends StatelessWidget {
  const _BotonCarrito();

  @override
  Widget build(BuildContext context) {
    final total = context.watch<CarritoCompras>().totalItems;
    return IconButton(
      tooltip: 'Carrito',
      onPressed: () =>
          Navigator.push(context, MaterialPageRoute(builder: (_) => const IndexCarrito())),
      icon: Badge(
        label: Text('$total'),
        backgroundColor: ColoresFragansys.dorado,
        isLabelVisible: total > 0,
        child: const Icon(Icons.shopping_cart_outlined),
      ),
    );
  }
}

/// Menú del cliente: Iniciar sesión, o su nombre con "Mis pedidos" y "Cerrar sesión"
class _MenuCliente extends StatelessWidget {
  const _MenuCliente({required this.alCambiarServidor});

  final VoidCallback alCambiarServidor;

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionCliente>();
    final cliente = sesion.cliente;

    return PopupMenuButton<String>(
      tooltip: cliente == null ? 'Iniciar sesión' : cliente.nombre,
      icon: Icon(cliente == null ? Icons.person_outline : Icons.account_circle),
      onSelected: (opcion) async {
        switch (opcion) {
          case 'login':
            Navigator.push(context, MaterialPageRoute(builder: (_) => const IndexLogin()));
          case 'pedidos':
            Navigator.push(context, MaterialPageRoute(builder: (_) => const IndexPedidos()));
          case 'salir':
            // Al cerrar sesión también se vacía el carrito (igual que cerrarSesionCliente() en PHP)
            context.read<CarritoCompras>().vaciar();
            await sesion.cerrar();
            if (context.mounted) mostrarMensaje(context, 'Sesión cerrada.');
          case 'servidor':
            if (await dialogoServidor(context)) alCambiarServidor();
        }
      },
      itemBuilder: (_) => [
        if (cliente == null)
          const PopupMenuItem(
            value: 'login',
            child: ListTile(leading: Icon(Icons.login), title: Text('Iniciar sesión')),
          )
        else ...[
          PopupMenuItem(
            enabled: false,
            child: Text(cliente.nombre,
                style: const TextStyle(fontFamily: fuenteSerif, color: ColoresFragansys.cafe)),
          ),
          const PopupMenuItem(
            value: 'pedidos',
            child: ListTile(leading: Icon(Icons.shopping_bag_outlined), title: Text('Mis pedidos')),
          ),
          const PopupMenuItem(
            value: 'salir',
            child: ListTile(leading: Icon(Icons.logout), title: Text('Cerrar sesión')),
          ),
        ],
        const PopupMenuDivider(),
        const PopupMenuItem(
          value: 'servidor',
          child: ListTile(leading: Icon(Icons.dns_outlined), title: Text('Servidor')),
        ),
      ],
    );
  }
}

/// Permite cambiar la dirección del servidor (útil al probar en un celular físico). Devuelve true si cambió.
Future<bool> dialogoServidor(BuildContext context) async {
  final control = TextEditingController(text: ConexionApi.urlBase);
  final cambiar = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: const Text('Servidor'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Dirección de la carpeta fragansys en la PC con AppServ.\n'
            'Emulador: http://10.0.2.2/fragansys\n'
            'Celular: http://IP-DE-TU-PC/fragansys',
            style: TextStyle(fontSize: 13, color: Colors.black54),
          ),
          const SizedBox(height: 12),
          TextField(controller: control, keyboardType: TextInputType.url, autocorrect: false),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => control.text = ConexionApi.urlPorDefecto,
          child: const Text('Por defecto'),
        ),
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Guardar')),
      ],
    ),
  );
  if (cambiar != true) return false;
  await ConexionApi.cambiarServidor(control.text);
  return true;
}

/// Contenido de una sección del menú (lo que muestra index.php?seccion=...)
class VistaSeccion extends StatefulWidget {
  const VistaSeccion({
    super.key,
    required this.seccion,
    required this.alVerCatalogoCompleto,
    this.datosIniciales,
  });

  final Seccion seccion;
  final DatosSeccion? datosIniciales;
  final VoidCallback alVerCatalogoCompleto;

  @override
  State<VistaSeccion> createState() => _VistaSeccionState();
}

class _VistaSeccionState extends State<VistaSeccion> with AutomaticKeepAliveClientMixin {
  DatosSeccion? _datos;
  String? _error;
  String _busqueda = '';

  @override
  bool get wantKeepAlive => true;

  bool get _esInicio => widget.seccion.clave == 'inicio';
  bool get _esCombos => widget.seccion.clave == 'combos';

  @override
  void initState() {
    super.initState();
    _datos = widget.datosIniciales;
    if (_datos == null) _cargar();
  }

  Future<void> _cargar() async {
    setState(() => _error = null);
    try {
      final datos = await BackCatalogoPub.obtenerSeccion(widget.seccion.clave);
      if (mounted) setState(() => _datos = datos);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.mensaje);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final datos = _datos;
    if (datos == null) {
      return _error == null
          ? const Center(child: CircularProgressIndicator())
          : VistaError(mensaje: _error!, alReintentar: _cargar);
    }

    final perfumes = datos.perfumes
        .where((p) => p.coincide(_busqueda))
        .toList();

    return RefreshIndicator(
      onRefresh: _cargar,
      child: CustomScrollView(
        slivers: [
          if (_esInicio) const SliverToBoxAdapter(child: _BannerHero()),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 12),
            sliver: SliverToBoxAdapter(
              child: TituloSeccion(titulo: widget.seccion.titulo, subtitulo: widget.seccion.subtitulo),
            ),
          ),
          if (_esCombos)
            ..._slivesCombos(datos.combos)
          else ...[
            if (!_esInicio && datos.perfumes.isNotEmpty)
              SliverToBoxAdapter(child: _filtros()),
            if (perfumes.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(32),
                    child: Text(
                      datos.perfumes.isEmpty
                          ? 'No hay perfumes disponibles en esta sección por el momento.'
                          : 'No encontramos fragancias con esa búsqueda.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.black54, fontSize: 16),
                    ),
                  ),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: 260,
                    mainAxisExtent: 410,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                  ),
                  delegate: SliverChildBuilderDelegate(
                    (_, i) => TarjetaPerfume(key: ValueKey(perfumes[i].id), perfume: perfumes[i]),
                    childCount: perfumes.length,
                  ),
                ),
              ),
            if (_esInicio && datos.perfumes.isNotEmpty)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.only(top: 24),
                  child: Center(
                    child: OutlinedButton.icon(
                      onPressed: widget.alVerCatalogoCompleto,
                      icon: const Icon(Icons.arrow_forward),
                      label: const Text('VER CATÁLOGO COMPLETO'),
                    ),
                  ),
                ),
              ),
          ],
          const SliverToBoxAdapter(child: SizedBox(height: 32)),
        ],
      ),
    );
  }

  List<Widget> _slivesCombos(List<Combo> combos) {
    if (combos.isEmpty) {
      return const [
        SliverFillRemaining(
          hasScrollBody: false,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.card_giftcard, size: 56, color: ColoresFragansys.dorado),
              SizedBox(height: 12),
              Text('Muy pronto tendremos combos disponibles.',
                  style: TextStyle(color: Colors.black54, fontSize: 16)),
            ],
          ),
        ),
      ];
    }
    return [
      SliverPadding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        sliver: SliverList.separated(
          itemCount: combos.length,
          separatorBuilder: (_, _) => const SizedBox(height: 16),
          itemBuilder: (_, i) => TarjetaCombo(combo: combos[i]),
        ),
      ),
    ];
  }

  // Búsqueda (el género se elige con las pestañas Mujer / Hombre del menú superior)
  Widget _filtros() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Row(
        children: [
          Expanded(
            flex: 3,
            child: TextField(
              decoration: const InputDecoration(
                hintText: 'Buscar por nombre o marca...',
                prefixIcon: Icon(Icons.search, color: ColoresFragansys.dorado),
              ),
              onChanged: (t) => setState(() => _busqueda = t),
            ),
          ),
        ],
      ),
    );
  }
}

/// Banner "Fragancias que cuentan historias"
class _BannerHero extends StatelessWidget {
  const _BannerHero();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 16),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [ColoresFragansys.crema, ColoresFragansys.beige],
        ),
      ),
      child: Column(
        children: [
          const Text('FRAGANCIAS ORIGINALES',
              style: TextStyle(
                  color: Colors.black54, letterSpacing: 2, fontSize: 12, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          RichText(
            textAlign: TextAlign.center,
            text: const TextSpan(
              style: TextStyle(fontFamily: fuenteSerif, fontSize: 30, color: ColoresFragansys.cafe),
              children: [
                TextSpan(text: 'Fragancias que '),
                TextSpan(
                  text: 'cuentan historias',
                  style: TextStyle(color: ColoresFragansys.dorado, fontStyle: FontStyle.italic),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          const Text('Descubre tu aroma sin invertir en un frasco completo.',
              textAlign: TextAlign.center, style: TextStyle(color: ColoresFragansys.cafe)),
        ],
      ),
    );
  }
}
