-- =====================================================================
-- Fragansys: datos de prueba hasta completar 1500 registros por tabla
-- ---------------------------------------------------------------------
-- * NO borra ni modifica registros existentes: solo INSERTA lo que falta.
-- * Cada tabla calcula cuántos le faltan (1500 - registros actuales),
--   así que sirve en cualquier computadora y se puede correr varias veces
--   (si una tabla ya tiene 1500 o más, no se le agrega nada).
-- * NO toca las tablas `configuracion` ni `usuarios`.
-- * Correos de clientes de prueba: @example.com (dominio reservado, nunca
--   llega un correo real).
-- * Los movimientos de stock (ventas/entradas) son solo históricos: no
--   cambian frascos_sellados ni ml_disponibles de los perfumes existentes.
--
-- Uso (MySQL 8):
--   mysql -uroot -p --default-character-set=utf8mb4 fragansys < sql/datos_prueba_1500.sql
-- =====================================================================

SET NAMES utf8mb4;
SET SESSION cte_max_recursion_depth = 5000;

START TRANSACTION;

SET @meta  = 1500;
SET @admin = (SELECT MIN(id) FROM usuarios WHERE rol = 'admin');

-- Secuencia 1..1500
DROP TEMPORARY TABLE IF EXISTS tmp_seq;
CREATE TEMPORARY TABLE tmp_seq (n INT PRIMARY KEY);
INSERT INTO tmp_seq (n)
WITH RECURSIVE s (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM s WHERE n < 1500)
SELECT n FROM s;

-- Listas para generar datos
SET @marcas      = '["Dior","Chanel","Carolina Herrera","Paco Rabanne","Versace","Hugo Boss","Calvin Klein","Lancôme","Yves Saint Laurent","Armani","Givenchy","Dolce & Gabbana","Jean Paul Gaultier","Tom Ford","Montblanc","Lacoste","Bvlgari","Burberry","Azzaro","Moschino","Ariana Grande","Tommy Hilfiger","Kenzo","Guess"]';
SET @palabra1    = '["Noir","Bleu","Rose","Gold","Silver","Velvet","Amber","Oud","Citrus","Aqua","Black","White","Wild","Pure","Secret","Royal","Midnight","Sunset","Ocean","Crystal"]';
SET @palabra2    = '["Intense","Elixir","Essence","Sport","Night","Garden","Legend","Dream","Spirit","Bloom","Code","Desire","Absolute","Classic","Fresh","Mystery","Passion","Wood","Musk","Vanilla"]';
SET @nombres     = '["María","José","Juan","Guadalupe","Luis","Ana","Carlos","Sofía","Miguel","Fernanda","Jorge","Valeria","Pedro","Daniela","Alejandro","Camila","Ricardo","Paola","Francisco","Mariana","Eduardo","Andrea","Roberto","Ximena","Diego","Lucía","Javier","Regina","Andrés","Natalia"]';
SET @apellidos   = '["Hernández","García","Martínez","López","González","Pérez","Rodríguez","Sánchez","Ramírez","Cruz","Flores","Gómez","Morales","Vázquez","Reyes","Jiménez","Torres","Díaz","Gutiérrez","Ruiz","Mendoza","Aguilar","Ortiz","Moreno","Castillo","Romero","Álvarez","Chávez","Ramos","Herrera"]';
SET @calles      = '["Av. Juárez","Hidalgo","Morelos","Independencia","Allende","Madero","Zaragoza","Reforma","Guerrero","Constitución","Niños Héroes","5 de Mayo","16 de Septiembre","López Mateos","Revolución","Santo Toribio Romo","Olegario García","Aldama","Libertad","Pino Suárez"]';
SET @ciudades    = '["Tepatitlán, Jal.","Guadalajara, Jal.","Zapopan, Jal.","Arandas, Jal.","San Miguel el Alto, Jal.","Jalostotitlán, Jal.","Lagos de Moreno, Jal.","Aguascalientes, Ags.","León, Gto.","Tlaquepaque, Jal."]';
SET @notas_ped   = '["Entregar por la tarde","Tocar el timbre","Es para regalo","Dejar con el vecino","Llamar antes de llegar","Envolver para regalo","Casa con portón negro","Entregar en horario de oficina"]';
SET @paqueterias = '["Estafeta","DHL","FedEx","Redpack","Paquetexpress","J&T Express"]';
SET @proveedores = '["Distribuidora Aromas del Bajío","Perfumes Importados GDL","Fragancias del Centro","Esencias Finas SA de CV","Mayorista Perfumería Jalisco"]';
SET @notas_ent   = '["Compra mensual","Reposición de stock","Pedido especial","Promoción del proveedor","Lote nuevo"]';
SET @desc_combos = '["Aroma floral","Ideal para regalo","Fragancias frescas para el día","Aromas intensos para la noche","Selección de temporada","Combo para pareja","Kit de viaje","Notas amaderadas"]';

-- =====================================================================
-- 1) PERFUMES
-- =====================================================================
SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM perfumes));
INSERT INTO perfumes (marca, nombre, genero, imagen, ml_por_frasco, frascos_sellados,
                      ml_disponibles, stock_minimo_frascos, activo, fecha_registro)
SELECT
  JSON_UNQUOTE(JSON_EXTRACT(@marcas,   CONCAT('$[', CRC32(CONCAT('pm', n)) % JSON_LENGTH(@marcas),   ']'))),
  CONCAT(JSON_UNQUOTE(JSON_EXTRACT(@palabra1, CONCAT('$[', CRC32(CONCAT('p1', n)) % JSON_LENGTH(@palabra1), ']'))), ' ',
         JSON_UNQUOTE(JSON_EXTRACT(@palabra2, CONCAT('$[', CRC32(CONCAT('p2', n)) % JSON_LENGTH(@palabra2), ']')))),
  ELT(1 + CRC32(CONCAT('pg', n)) % 3, 'hombre', 'mujer', 'unisex'),
  NULL,
  100.00,
  CRC32(CONCAT('pf', n)) % 21,
  CRC32(CONCAT('pl', n)) % 101,
  1 + CRC32(CONCAT('ps', n)) % 3,
  IF(CRC32(CONCAT('pa', n)) % 20 = 0, 0, 1),
  NOW() - INTERVAL (CRC32(CONCAT('pt', n)) % 525600) MINUTE
FROM tmp_seq WHERE n <= @faltan ORDER BY n;

-- =====================================================================
-- 2) PRESENTACIONES (respeta el UNIQUE perfume + tipo + ml)
--    Primero un frasco de 100 ml por perfume, luego decants de 5, 10 y 1 ml
-- =====================================================================
SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM presentaciones));
INSERT INTO presentaciones (perfume_id, tipo, ml, precio, activo)
SELECT perfume_id, tipo, ml, precio, 1
FROM (
  SELECT p.id AS perfume_id, o.tipo, o.ml,
         IF(o.tipo = 'frasco',
            ROUND(900 + CRC32(CONCAT('xf', p.id)) % 2100, -1),
            ROUND((80 + CRC32(CONCAT('xd', p.id)) % 60) * o.factor, -1)) AS precio,
         ROW_NUMBER() OVER (ORDER BY o.prio, p.id) AS rn
  FROM perfumes p
  CROSS JOIN (SELECT 'frasco' AS tipo, 100.00 AS ml, 1 AS prio, 0   AS factor
              UNION ALL SELECT 'decant',  5.00, 2, 1.8
              UNION ALL SELECT 'decant', 10.00, 3, 2.8
              UNION ALL SELECT 'decant',  1.00, 4, 1.0) o
  WHERE NOT EXISTS (SELECT 1 FROM presentaciones x
                    WHERE x.perfume_id = p.id AND x.tipo = o.tipo AND x.ml = o.ml)
) c
WHERE rn <= @faltan
ORDER BY rn;

-- =====================================================================
-- 3) COMBOS
-- =====================================================================
SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM combos));
SET @base   = (SELECT COALESCE(MAX(id), 0) FROM combos);
INSERT INTO combos (nombre, descripcion, precio, imagen, activo, fecha_registro)
SELECT
  CONCAT('Combo ', JSON_UNQUOTE(JSON_EXTRACT(@palabra1, CONCAT('$[', CRC32(CONCAT('c1', n)) % JSON_LENGTH(@palabra1), ']'))),
         ' ', @base + n),
  JSON_UNQUOTE(JSON_EXTRACT(@desc_combos, CONCAT('$[', CRC32(CONCAT('cd', n)) % JSON_LENGTH(@desc_combos), ']'))),
  ROUND(800 + CRC32(CONCAT('cp', n)) % 3000, -1),
  NULL,
  IF(CRC32(CONCAT('ca', n)) % 20 = 0, 0, 1),
  NOW() - INTERVAL (CRC32(CONCAT('ct', n)) % 525600) MINUTE
FROM tmp_seq WHERE n <= @faltan ORDER BY n;

-- Tablas auxiliares numeradas para elegir registros "al azar"
DROP TEMPORARY TABLE IF EXISTS tmp_pres;
CREATE TEMPORARY TABLE tmp_pres (rn INT PRIMARY KEY, id INT UNSIGNED, precio DECIMAL(10,2));
INSERT INTO tmp_pres SELECT ROW_NUMBER() OVER (ORDER BY id), id, precio FROM presentaciones WHERE activo = 1;
SET @npres = (SELECT COUNT(*) FROM tmp_pres);

DROP TEMPORARY TABLE IF EXISTS tmp_combo;
CREATE TEMPORARY TABLE tmp_combo (rn INT PRIMARY KEY, id INT UNSIGNED, precio DECIMAL(10,2));
INSERT INTO tmp_combo SELECT ROW_NUMBER() OVER (ORDER BY id DESC), id, precio FROM combos;
SET @ncombo = (SELECT COUNT(*) FROM tmp_combo);

DROP TEMPORARY TABLE IF EXISTS tmp_perf;
CREATE TEMPORARY TABLE tmp_perf (rn INT PRIMARY KEY, id INT UNSIGNED);
INSERT INTO tmp_perf SELECT ROW_NUMBER() OVER (ORDER BY id), id FROM perfumes;
SET @nperf = (SELECT COUNT(*) FROM tmp_perf);

-- =====================================================================
-- 4) COMBO_PRESENTACIONES (empieza por los combos más nuevos)
-- =====================================================================
SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM combo_presentaciones));
INSERT INTO combo_presentaciones (combo_id, presentacion_id, cantidad)
SELECT c.id, p.id, 1 + CRC32(CONCAT('kq', s.n)) % 2
FROM tmp_seq s
JOIN tmp_combo c ON c.rn = 1 + (s.n - 1) % @ncombo
JOIN tmp_pres  p ON p.rn = 1 + CRC32(CONCAT('kp', s.n)) % @npres
WHERE s.n <= @faltan
ORDER BY s.n;

-- =====================================================================
-- 5) CLIENTES (password vacío, igual que los clientes sin cuenta)
-- =====================================================================
SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM clientes));
SET @base   = (SELECT COALESCE(MAX(id), 0) FROM clientes);
INSERT INTO clientes (nombre, correo, password, telefono, direccion, activo, fecha_registro)
SELECT
  CONCAT(JSON_UNQUOTE(JSON_EXTRACT(@nombres,   CONCAT('$[', CRC32(CONCAT('cn', n)) % JSON_LENGTH(@nombres),   ']'))), ' ',
         JSON_UNQUOTE(JSON_EXTRACT(@apellidos, CONCAT('$[', CRC32(CONCAT('ca', n)) % JSON_LENGTH(@apellidos), ']'))), ' ',
         JSON_UNQUOTE(JSON_EXTRACT(@apellidos, CONCAT('$[', CRC32(CONCAT('cb', n)) % JSON_LENGTH(@apellidos), ']')))),
  CONCAT('cliente.prueba', @base + n, '@example.com'),
  '',
  CONCAT('348', LPAD(CRC32(CONCAT('ct', n)) % 10000000, 7, '0')),
  CONCAT(JSON_UNQUOTE(JSON_EXTRACT(@calles,   CONCAT('$[', CRC32(CONCAT('cc', n)) % JSON_LENGTH(@calles),   ']'))), ' ',
         1 + CRC32(CONCAT('cx', n)) % 2500, ', ',
         JSON_UNQUOTE(JSON_EXTRACT(@ciudades, CONCAT('$[', CRC32(CONCAT('cy', n)) % JSON_LENGTH(@ciudades), ']')))),
  IF(CRC32(CONCAT('cv', n)) % 25 = 0, 0, 1),
  NOW() - INTERVAL (CRC32(CONCAT('cf', n)) % 525600) MINUTE
FROM tmp_seq WHERE n <= @faltan ORDER BY n;

DROP TEMPORARY TABLE IF EXISTS tmp_cli;
CREATE TEMPORARY TABLE tmp_cli (rn INT PRIMARY KEY, id INT UNSIGNED, nombre VARCHAR(120),
                                correo VARCHAR(150), telefono VARCHAR(20), direccion VARCHAR(255));
INSERT INTO tmp_cli
SELECT ROW_NUMBER() OVER (ORDER BY id), id, nombre, correo,
       COALESCE(NULLIF(telefono, ''), '3480000000'), COALESCE(NULLIF(direccion, ''), 'Sin dirección')
FROM clientes;
SET @ncli = (SELECT COUNT(*) FROM tmp_cli);

-- =====================================================================
-- 6) PEDIDOS + 7) PEDIDO_DETALLE
--    Se arma primero cada pedido con su renglón de detalle para que
--    subtotal, envío y total cuadren.
-- =====================================================================
SET @faltan  = GREATEST(0, @meta - (SELECT COUNT(*) FROM pedidos));
SET @maxped  = (SELECT COALESCE(MAX(id), 0) FROM pedidos);
SET @envio   = (SELECT CAST(valor AS DECIMAL(10,2)) FROM configuracion WHERE clave = 'envio_costo');
SET @gratis  = (SELECT CAST(valor AS DECIMAL(10,2)) FROM configuracion WHERE clave = 'envio_gratis_desde');
SET @envio   = COALESCE(@envio, 100);
SET @gratis  = COALESCE(@gratis, 1500);

DROP TEMPORARY TABLE IF EXISTS tmp_ped;
CREATE TEMPORARY TABLE tmp_ped AS
SELECT b.*,
       IF(tipo = 'combo', precio_combo, precio_pres)            AS precio_unitario,
       IF(tipo = 'combo', precio_combo, precio_pres) * cantidad AS subtotal,
       CASE
         WHEN estado_pedido = 'nuevo'     THEN 'pendiente'
         WHEN estado_pedido = 'cancelado' THEN ELT(1 + CRC32(CONCAT('dz', n)) % 3, 'pendiente', 'fallido', 'reembolsado')
         ELSE 'pagado'
       END AS estado_pago
FROM (
SELECT s.n,
       c.id AS cliente_id, c.nombre, c.correo, c.telefono, c.direccion,
       IF(CRC32(CONCAT('dt', s.n)) % 5 = 0, 'combo', 'presentacion') AS tipo,
       p.id  AS presentacion_id, p.precio  AS precio_pres,
       k.id  AS combo_id,        k.precio  AS precio_combo,
       1 + CRC32(CONCAT('dq', s.n)) % 3 AS cantidad,
       ELT(1 + CRC32(CONCAT('de', s.n)) % 8,
           'nuevo', 'confirmado', 'en_preparacion', 'enviado', 'entregado', 'entregado', 'entregado', 'cancelado') AS estado_pedido,
       NOW() - INTERVAL (1440 + CRC32(CONCAT('df', s.n)) % 524160) MINUTE AS fecha_pedido
FROM tmp_seq s
JOIN tmp_cli   c ON c.rn = 1 + CRC32(CONCAT('dc', s.n)) % @ncli
JOIN tmp_pres  p ON p.rn = 1 + CRC32(CONCAT('dp', s.n)) % @npres
JOIN tmp_combo k ON k.rn = 1 + CRC32(CONCAT('dk', s.n)) % @ncombo
WHERE s.n <= @faltan
) b;

INSERT INTO pedidos (cliente_id, cliente_nombre, cliente_correo, subtotal, costo_envio, total,
                     metodo_pago, estado_pago, paypal_order_id, paypal_payer_email, estado_pedido,
                     direccion_entrega, telefono_contacto, notas, paqueteria, numero_guia,
                     fecha_envio, fecha_pedido, fecha_actualizacion)
SELECT
  cliente_id, nombre, correo, subtotal,
  IF(subtotal >= @gratis, 0, @envio),
  subtotal + IF(subtotal >= @gratis, 0, @envio),
  'paypal',
  estado_pago,
  IF(estado_pago IN ('pagado', 'reembolsado'), CONCAT('PRUEBA', @maxped + n, 'X', CRC32(CONCAT('do', n)) % 100000), NULL),
  IF(estado_pago IN ('pagado', 'reembolsado'), correo, NULL),
  estado_pedido,
  direccion, telefono,
  IF(CRC32(CONCAT('dn', n)) % 3 = 0,
     JSON_UNQUOTE(JSON_EXTRACT(@notas_ped, CONCAT('$[', CRC32(CONCAT('dm', n)) % JSON_LENGTH(@notas_ped), ']'))), NULL),
  IF(estado_pedido IN ('enviado', 'entregado'),
     JSON_UNQUOTE(JSON_EXTRACT(@paqueterias, CONCAT('$[', CRC32(CONCAT('dy', n)) % JSON_LENGTH(@paqueterias), ']'))), NULL),
  IF(estado_pedido IN ('enviado', 'entregado'),
     CONCAT(LPAD(CRC32(CONCAT('dg', n)) % 100000000, 8, '0'), LPAD(CRC32(CONCAT('dh', n)) % 100000000, 8, '0')), NULL),
  IF(estado_pedido IN ('enviado', 'entregado'), fecha_pedido + INTERVAL (1 + CRC32(CONCAT('dv', n)) % 3) DAY, NULL),
  fecha_pedido,
  IF(estado_pedido IN ('enviado', 'entregado'), fecha_pedido + INTERVAL (1 + CRC32(CONCAT('dv', n)) % 3) DAY, fecha_pedido)
FROM tmp_ped ORDER BY n;

-- Relaciona cada pedido nuevo con su renglón generado (mismo orden)
DROP TEMPORARY TABLE IF EXISTS tmp_ped_ids;
CREATE TEMPORARY TABLE tmp_ped_ids AS
SELECT ROW_NUMBER() OVER (ORDER BY id) AS n, id FROM pedidos WHERE id > @maxped;

SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM pedido_detalle));
INSERT INTO pedido_detalle (pedido_id, tipo, presentacion_id, combo_id, cantidad, precio_unitario, subtotal)
SELECT i.id, g.tipo,
       IF(g.tipo = 'presentacion', g.presentacion_id, NULL),
       IF(g.tipo = 'combo', g.combo_id, NULL),
       g.cantidad, g.precio_unitario, g.subtotal
FROM tmp_ped_ids i JOIN tmp_ped g ON g.n = i.n
WHERE i.n <= @faltan
ORDER BY i.n;

-- Si la tabla de detalle ya estaba llena pero se crearon pedidos nuevos,
-- o viceversa, completa el detalle con pedidos que no tengan renglones.
SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM pedido_detalle));
INSERT INTO pedido_detalle (pedido_id, tipo, presentacion_id, combo_id, cantidad, precio_unitario, subtotal)
SELECT x.id, 'presentacion', p.id, NULL, 1, p.precio, p.precio
FROM (SELECT pe.id, ROW_NUMBER() OVER (ORDER BY pe.id) AS rn
      FROM pedidos pe
      WHERE NOT EXISTS (SELECT 1 FROM pedido_detalle d WHERE d.pedido_id = pe.id)) x
JOIN tmp_pres p ON p.rn = 1 + CRC32(CONCAT('dr', x.id)) % @npres
WHERE x.rn <= @faltan;

-- =====================================================================
-- 8) VENTAS + 9) VENTA_DETALLE (ventas en mostrador, usuario admin)
-- =====================================================================
SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM ventas));
SET @maxven = (SELECT COALESCE(MAX(id), 0) FROM ventas);

DROP TEMPORARY TABLE IF EXISTS tmp_ven;
CREATE TEMPORARY TABLE tmp_ven AS
SELECT b.*,
       IF(tipo = 'combo', precio_combo, precio_pres)            AS precio_unitario,
       IF(tipo = 'combo', precio_combo, precio_pres) * cantidad AS subtotal
FROM (
SELECT s.n,
       IF(CRC32(CONCAT('vg', s.n)) % 3 = 0, NULL, c.nombre)   AS cliente_nombre,
       IF(CRC32(CONCAT('vg', s.n)) % 3 = 0, NULL, c.telefono) AS cliente_telefono,
       IF(CRC32(CONCAT('vt', s.n)) % 6 = 0, 'combo', 'presentacion') AS tipo,
       p.id AS presentacion_id, p.precio AS precio_pres,
       k.id AS combo_id,        k.precio AS precio_combo,
       1 + CRC32(CONCAT('vq', s.n)) % 3 AS cantidad,
       ELT(1 + CRC32(CONCAT('vm', s.n)) % 5, 'efectivo', 'efectivo', 'transferencia', 'tarjeta', 'efectivo') AS metodo_pago,
       IF(CRC32(CONCAT('ve', s.n)) % 15 = 0, 'cancelada', 'completada') AS estado,
       NOW() - INTERVAL (CRC32(CONCAT('vf', s.n)) % 525600) MINUTE AS fecha_venta
FROM tmp_seq s
JOIN tmp_cli   c ON c.rn = 1 + CRC32(CONCAT('vc', s.n)) % @ncli
JOIN tmp_pres  p ON p.rn = 1 + CRC32(CONCAT('vp', s.n)) % @npres
JOIN tmp_combo k ON k.rn = 1 + CRC32(CONCAT('vk', s.n)) % @ncombo
WHERE s.n <= @faltan
) b;

INSERT INTO ventas (pedido_id, usuario_id, cliente_nombre, cliente_telefono, total,
                    metodo_pago, monto_recibido, estado, fecha_venta)
SELECT NULL, @admin, cliente_nombre, cliente_telefono, subtotal, metodo_pago,
       IF(metodo_pago = 'efectivo', CEIL(subtotal / 100) * 100 + 100 * (CRC32(CONCAT('vr', n)) % 2), NULL),
       estado, fecha_venta
FROM tmp_ven ORDER BY n;

DROP TEMPORARY TABLE IF EXISTS tmp_ven_ids;
CREATE TEMPORARY TABLE tmp_ven_ids AS
SELECT ROW_NUMBER() OVER (ORDER BY id) AS n, id FROM ventas WHERE id > @maxven;

SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM venta_detalle));
INSERT INTO venta_detalle (venta_id, tipo, presentacion_id, combo_id, cantidad, precio_unitario, subtotal)
SELECT i.id, g.tipo,
       IF(g.tipo = 'presentacion', g.presentacion_id, NULL),
       IF(g.tipo = 'combo', g.combo_id, NULL),
       g.cantidad, g.precio_unitario, g.subtotal
FROM tmp_ven_ids i JOIN tmp_ven g ON g.n = i.n
WHERE i.n <= @faltan
ORDER BY i.n;

SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM venta_detalle));
INSERT INTO venta_detalle (venta_id, tipo, presentacion_id, combo_id, cantidad, precio_unitario, subtotal)
SELECT x.id, 'presentacion', p.id, NULL, 1, p.precio, p.precio
FROM (SELECT v.id, ROW_NUMBER() OVER (ORDER BY v.id) AS rn
      FROM ventas v
      WHERE NOT EXISTS (SELECT 1 FROM venta_detalle d WHERE d.venta_id = v.id)) x
JOIN tmp_pres p ON p.rn = 1 + CRC32(CONCAT('vr', x.id)) % @npres
WHERE x.rn <= @faltan;

-- =====================================================================
-- 10) ENTRADAS_INVENTARIO
-- =====================================================================
SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM entradas_inventario));
INSERT INTO entradas_inventario (perfume_id, usuario_id, fecha_entrada, frascos, costo_unitario,
                                 proveedor, notas, estado, fecha_registro)
SELECT f.id, @admin, DATE(NOW() - INTERVAL (CRC32(CONCAT('et', s.n)) % 525600) MINUTE),
       1 + CRC32(CONCAT('ef', s.n)) % 12,
       ROUND(300 + CRC32(CONCAT('ec', s.n)) % 1900, -1),
       JSON_UNQUOTE(JSON_EXTRACT(@proveedores, CONCAT('$[', CRC32(CONCAT('ep', s.n)) % JSON_LENGTH(@proveedores), ']'))),
       IF(CRC32(CONCAT('en', s.n)) % 2 = 0,
          JSON_UNQUOTE(JSON_EXTRACT(@notas_ent, CONCAT('$[', CRC32(CONCAT('em', s.n)) % JSON_LENGTH(@notas_ent), ']'))), NULL),
       IF(CRC32(CONCAT('ee', s.n)) % 20 = 0, 'anulada', 'registrada'),
       NOW() - INTERVAL (CRC32(CONCAT('et', s.n)) % 525600) MINUTE
FROM tmp_seq s
JOIN tmp_perf f ON f.rn = 1 + CRC32(CONCAT('ei', s.n)) % @nperf
WHERE s.n <= @faltan
ORDER BY s.n;

-- =====================================================================
-- 11) MOVIMIENTOS (bitácora)
-- =====================================================================
SET @movs = '[
 ["Sesión","Iniciar sesión","Inició sesión"],
 ["Sesión","Cerrar sesión","Cerró sesión"],
 ["Perfumes","Agregar","Agregó el perfume #{n}"],
 ["Perfumes","Editar","Editó el perfume #{n}"],
 ["Perfumes","Abrir frasco","Abrió un frasco del perfume #{n}"],
 ["Catálogo","Editar","Editó la presentación #{n}"],
 ["Combos","Agregar","Agregó el combo #{n}"],
 ["Inventario","Entrada","Registró la entrada de inventario #{n}"],
 ["Ventas","Agregar","Registró la venta #{n}"],
 ["Pedidos","Cambiar estado","Cambió el estado del pedido #{n}"],
 ["Pedidos","Pago en línea","Se recibió el pago en línea del pedido #{n}"]
]';
SET @faltan = GREATEST(0, @meta - (SELECT COUNT(*) FROM movimientos));
INSERT INTO movimientos (usuario_id, modulo, accion, descripcion, fecha)
SELECT @admin,
       JSON_UNQUOTE(JSON_EXTRACT(@movs, CONCAT('$[', k, '][0]'))),
       JSON_UNQUOTE(JSON_EXTRACT(@movs, CONCAT('$[', k, '][1]'))),
       REPLACE(JSON_UNQUOTE(JSON_EXTRACT(@movs, CONCAT('$[', k, '][2]'))), '{n}', 1 + CRC32(CONCAT('mn', n)) % 1500),
       NOW() - INTERVAL (CRC32(CONCAT('mf', n)) % 525600) MINUTE
FROM (SELECT n, CRC32(CONCAT('mk', n)) % JSON_LENGTH(@movs) AS k FROM tmp_seq) s
WHERE n <= @faltan
ORDER BY n;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_seq, tmp_pres, tmp_combo, tmp_perf, tmp_cli,
                               tmp_ped, tmp_ped_ids, tmp_ven, tmp_ven_ids;

-- Resumen
SELECT 'perfumes' AS tabla, COUNT(*) AS registros FROM perfumes
UNION ALL SELECT 'presentaciones',       COUNT(*) FROM presentaciones
UNION ALL SELECT 'combos',               COUNT(*) FROM combos
UNION ALL SELECT 'combo_presentaciones', COUNT(*) FROM combo_presentaciones
UNION ALL SELECT 'clientes',             COUNT(*) FROM clientes
UNION ALL SELECT 'pedidos',              COUNT(*) FROM pedidos
UNION ALL SELECT 'pedido_detalle',       COUNT(*) FROM pedido_detalle
UNION ALL SELECT 'ventas',               COUNT(*) FROM ventas
UNION ALL SELECT 'venta_detalle',        COUNT(*) FROM venta_detalle
UNION ALL SELECT 'entradas_inventario',  COUNT(*) FROM entradas_inventario
UNION ALL SELECT 'movimientos',          COUNT(*) FROM movimientos
UNION ALL SELECT 'configuracion',        COUNT(*) FROM configuracion
UNION ALL SELECT 'usuarios',             COUNT(*) FROM usuarios;
