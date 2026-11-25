<?php
/**
 * Genera imágenes de prueba para los primeros 100 perfumes del catálogo
 * (mismo orden que el catálogo público: activos, por nombre A-Z) que NO
 * tengan imagen. Dibuja un frasco con la marca y el nombre del perfume,
 * lo guarda en uploads/perfumes/prueba_<id>.webp y llena perfumes.imagen.
 *
 * - No toca perfumes que ya tienen imagen.
 * - Se puede correr varias veces: los que ya tienen imagen se saltan.
 * - Como uploads/ no se sube a git, en la otra computadora hay que correrlo
 *   también (genera exactamente las mismas imágenes).
 *
 * Uso (desde la carpeta del proyecto):
 *   C:\AppServ\php7\php.exe sql\imagenes_prueba.php
 *   C:\AppServ\php7\php.exe sql\imagenes_prueba.php 150     (otra cantidad)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se puede ejecutar desde la terminal.');
}
if (!function_exists('imagewebp')) {
    exit("PHP no tiene GD con soporte WebP.\n");
}

require __DIR__ . '/../conexion.php';

$cantidad    = isset($argv[1]) ? max(1, (int)$argv[1]) : 100;
$carpeta_rel = 'uploads/perfumes';
$carpeta_abs = __DIR__ . '/../' . $carpeta_rel;
if (!is_dir($carpeta_abs)) mkdir($carpeta_abs, 0777, true);

$fuente_normal = 'C:/Windows/Fonts/arial.ttf';
$fuente_negrita = 'C:/Windows/Fonts/arialbd.ttf';
$fuente_titulo = file_exists('C:/Windows/Fonts/georgiab.ttf') ? 'C:/Windows/Fonts/georgiab.ttf' : $fuente_negrita;
$hay_fuentes = file_exists($fuente_normal) && file_exists($fuente_negrita);

// Primeros N del catálogo (mismo orden que catalogo-publico/back_catalogo_pub.php)
$res = $conn->query("SELECT id, marca, nombre, genero, imagen FROM perfumes WHERE activo = 1 ORDER BY nombre ASC, id ASC LIMIT " . (int)$cantidad);

$hechos = 0; $saltados = 0;
$upd = $conn->prepare("UPDATE perfumes SET imagen = ? WHERE id = ? AND (imagen IS NULL OR imagen = '')");

while ($p = $res->fetch_assoc()) {
    if (!empty($p['imagen'])) { $saltados++; continue; }

    $archivo = 'prueba_' . $p['id'] . '.webp';
    dibujar_frasco($carpeta_abs . '/' . $archivo, $p);

    $ruta = $carpeta_rel . '/' . $archivo;
    $id = (int)$p['id'];
    $upd->bind_param('si', $ruta, $id);
    $upd->execute();
    $hechos++;
}

echo "Imágenes generadas: $hechos\n";
echo "Ya tenían imagen (sin cambios): $saltados\n";


// ---------------------------------------------------------------------

function dibujar_frasco($destino, $p) {
    global $fuente_normal, $fuente_negrita, $fuente_titulo, $hay_fuentes;

    // Se dibuja al doble de tamaño y luego se reduce para que los bordes salgan suaves
    $W = 1200; $H = 1600; $F = 2;
    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, true);

    $h = crc32($p['marca'] . '|' . $p['nombre'] . '|' . $p['id']);

    // Paletas por género: [fondo arriba, fondo abajo, vidrio, tapa]
    $paletas = [
        'mujer'  => [[[253,236,240],[244,196,210],[232,120,160],[198,160,90]],
                     [[248,236,252],[220,196,240],[170,110,210],[210,210,215]],
                     [[255,242,232],[246,206,190],[230,140,120],[200,165,95]]],
        'hombre' => [[[230,238,248],[176,196,224],[60,100,160],[40,40,48]],
                     [[232,242,238],[180,210,198],[50,120,110],[30,30,36]],
                     [[238,238,242],[190,192,204],[90,90,110],[170,140,80]]],
        'unisex' => [[[250,244,230],[230,210,170],[200,150,60],[60,50,40]],
                     [[236,246,246],[188,222,224],[70,150,160],[205,205,210]],
                     [[244,240,236],[214,204,192],[150,120,100],[40,40,40]]],
    ];
    $grupo = $paletas[$p['genero']] ?? $paletas['unisex'];
    [$c_arriba, $c_abajo, $c_vidrio, $c_tapa] = $grupo[$h % count($grupo)];

    // Fondo degradado
    for ($y = 0; $y < $H; $y++) {
        $t = $y / ($H - 1);
        $col = imagecolorallocate($im,
            (int)($c_arriba[0] + ($c_abajo[0] - $c_arriba[0]) * $t),
            (int)($c_arriba[1] + ($c_abajo[1] - $c_arriba[1]) * $t),
            (int)($c_arriba[2] + ($c_abajo[2] - $c_arriba[2]) * $t));
        imageline($im, 0, $y, $W, $y, $col);
    }

    // Forma del frasco (3 estilos)
    $estilo = intdiv($h, 7) % 3;
    $cx = $W / 2;
    if ($estilo === 0)      { $bw = 560; $bh = 700; $radio = 70;  }  // cuadrado
    elseif ($estilo === 1)  { $bw = 440; $bh = 800; $radio = 120; }  // alto y delgado
    else                    { $bw = 620; $bh = 640; $radio = 300; }  // redondo
    $base  = 1380;
    $top   = $base - $bh;
    $x1 = $cx - $bw / 2; $x2 = $cx + $bw / 2;

    // Sombra en el piso
    $sombra = imagecolorallocatealpha($im, 0, 0, 0, 100);
    imagefilledellipse($im, $cx, $base + 20, $bw + 160, 70, $sombra);

    // Cuello y tapa
    $cuello = imagecolorallocatealpha($im, $c_vidrio[0], $c_vidrio[1], $c_vidrio[2], 40);
    imagefilledrectangle($im, $cx - 70, $top - 70, $cx + 70, $top + 10, $cuello);
    $tapa = imagecolorallocate($im, $c_tapa[0], $c_tapa[1], $c_tapa[2]);
    $tapa_alto = $estilo === 1 ? 240 : 190;
    $tapa_ancho = $estilo === 2 ? 200 : 250;
    rect_redondeado($im, $cx - $tapa_ancho / 2, $top - 70 - $tapa_alto, $cx + $tapa_ancho / 2, $top - 60, 28, $tapa);
    $brillo_tapa = imagecolorallocatealpha($im, 255, 255, 255, 95);
    imagefilledrectangle($im, $cx - $tapa_ancho / 2 + 30, $top - 50 - $tapa_alto, $cx - $tapa_ancho / 2 + 60, $top - 80, $brillo_tapa);

    // Cuerpo de vidrio (dos capas para dar profundidad)
    $vidrio = imagecolorallocatealpha($im, $c_vidrio[0], $c_vidrio[1], $c_vidrio[2], 30);
    rect_redondeado($im, $x1, $top, $x2, $base, $radio, $vidrio);
    $liquido = imagecolorallocatealpha($im,
        max(0, $c_vidrio[0] - 30), max(0, $c_vidrio[1] - 30), max(0, $c_vidrio[2] - 30), 50);
    rect_redondeado($im, $x1 + 25, $top + $bh * 0.18, $x2 - 25, $base - 25, max(10, $radio - 25), $liquido);

    // Reflejo
    $reflejo = imagecolorallocatealpha($im, 255, 255, 255, 80);
    if ($estilo === 2) rect_redondeado($im, $x1 + 95, $top + 150, $x1 + 145, $base - 170, 25, $reflejo);
    else               rect_redondeado($im, $x1 + 45, $top + 60,  $x1 + 95,  $base - 90,  25, $reflejo);

    // Etiqueta
    $lw = min($bw - 120, 420); $lh = 300;
    $ly1 = $top + ($bh - $lh) / 2 + 20;
    $blanco = imagecolorallocatealpha($im, 255, 255, 255, 12);
    rect_redondeado($im, $cx - $lw / 2, $ly1, $cx + $lw / 2, $ly1 + $lh, 18, $blanco);
    $tinta = imagecolorallocate($im, 45, 40, 45);
    $tinta_suave = imagecolorallocate($im, 110, 105, 110);
    $linea = imagecolorallocate($im, $c_tapa[0], $c_tapa[1], $c_tapa[2]);
    imagefilledrectangle($im, $cx - 60, $ly1 + 92, $cx + 60, $ly1 + 96, $linea);

    if ($hay_fuentes) {
        texto_centrado($im, mb_strtoupper($p['marca']), $fuente_negrita, 30, $cx, $ly1 + 70, $tinta_suave, $lw - 40);
        $lineas = partir_texto($p['nombre'], $fuente_titulo, 46, $lw - 50);
        $y = $ly1 + 160 + (count($lineas) === 1 ? 30 : 0);
        foreach (array_slice($lineas, 0, 2) as $l) {
            texto_centrado($im, $l, $fuente_titulo, 46, $cx, $y, $tinta, $lw - 40);
            $y += 62;
        }
        texto_centrado($im, 'EAU DE PARFUM · 100 ML', $fuente_normal, 18, $cx, $ly1 + $lh - 25, $tinta_suave, $lw - 40);

        // Nombre abajo de la imagen
        texto_centrado($im, $p['marca'] . ' — ' . $p['nombre'], $fuente_negrita, 40, $cx, 1520, $tinta, $W - 120);
    }

    // Reducir a 600 x 800 y guardar como WebP (igual que la app)
    $final = imagecreatetruecolor($W / $F, $H / $F);
    imagecopyresampled($final, $im, 0, 0, 0, 0, $W / $F, $H / $F, $W, $H);
    imagewebp($final, $destino, 82);
    imagedestroy($im);
    imagedestroy($final);
}

function rect_redondeado($im, $x1, $y1, $x2, $y2, $r, $color) {
    $x1 = (int)$x1; $y1 = (int)$y1; $x2 = (int)$x2; $y2 = (int)$y2;
    $r = (int)min($r, ($x2 - $x1) / 2, ($y2 - $y1) / 2);
    // Se pinta sin mezclar para que el color semitransparente no se "doble" donde se enciman piezas
    $capa = imagecreatetruecolor($x2 - $x1 + 1, $y2 - $y1 + 1);
    imagesavealpha($capa, true);
    imagealphablending($capa, false);
    $transp = imagecolorallocatealpha($capa, 0, 0, 0, 127);
    imagefill($capa, 0, 0, $transp);
    $rgba = imagecolorsforindex($im, $color);
    $c = imagecolorallocatealpha($capa, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']);
    $w = $x2 - $x1; $h = $y2 - $y1;
    imagefilledrectangle($capa, $r, 0, $w - $r, $h, $c);
    imagefilledrectangle($capa, 0, $r, $w, $h - $r, $c);
    imagefilledellipse($capa, $r, $r, $r * 2, $r * 2, $c);
    imagefilledellipse($capa, $w - $r, $r, $r * 2, $r * 2, $c);
    imagefilledellipse($capa, $r, $h - $r, $r * 2, $r * 2, $c);
    imagefilledellipse($capa, $w - $r, $h - $r, $r * 2, $r * 2, $c);
    imagecopy($im, $capa, $x1, $y1, 0, 0, $w + 1, $h + 1);
    imagedestroy($capa);
}

function texto_centrado($im, $texto, $fuente, $tam, $cx, $y, $color, $max_ancho) {
    // Reduce el tamaño si no cabe
    do {
        $caja = imagettfbbox($tam, 0, $fuente, $texto);
        $ancho = $caja[2] - $caja[0];
        if ($ancho <= $max_ancho || $tam <= 12) break;
        $tam -= 2;
    } while (true);
    imagettftext($im, $tam, 0, (int)($cx - $ancho / 2 - $caja[0]), (int)$y, $color, $fuente, $texto);
}

function partir_texto($texto, $fuente, $tam, $max_ancho) {
    $palabras = preg_split('/\s+/', trim($texto));
    $lineas = []; $actual = '';
    foreach ($palabras as $pal) {
        $prueba = $actual === '' ? $pal : "$actual $pal";
        $caja = imagettfbbox($tam, 0, $fuente, $prueba);
        if ($caja[2] - $caja[0] > $max_ancho && $actual !== '') {
            $lineas[] = $actual;
            $actual = $pal;
        } else {
            $actual = $prueba;
        }
    }
    if ($actual !== '') $lineas[] = $actual;
    return $lineas;
}
