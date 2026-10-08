<?php
// Incluir el archivo de conexión del sistema
require_once __DIR__ . '/../conexion.php';

/**
 * Función para obtener perfumes y variantes sin depender de sesiones
 */
function obtenerCatalogoPublico($db) {$catalogo = array();

    // Validar que la conexión exista y sea un objeto válido
    if (!$db || !is_object($db) || $db->connect_error) {
        return $catalogo;
    }

    // 1. Consultar perfumes activos en la tabla `perfumes`
    $sqlCatalogo = "SELECT id, nombre, marca, genero, imagen, frascos_sellados, ml_disponibles
                    FROM perfumes
                    WHERE activo = 1
                    ORDER BY nombre ASC";

    $resultadoCatalogo = $db->query($sqlCatalogo);

    if ($resultadoCatalogo &&$resultadoCatalogo->num_rows > 0) {
        while ($perfume =$resultadoCatalogo->fetch_assoc()) {
            $catalogoId = (int)$perfume['id'];

            // En la BD se guarda "uploads/perfumes/archivo", relativo a la raíz del proyecto
            $perfume['imagen_url'] = '../' . $perfume['imagen'];

            // 2. Consultar las presentaciones (decants y frasco) del perfume
            $sqlProductos = "SELECT id, tipo, ml, precio
                             FROM presentaciones
                             WHERE perfume_id = ? AND activo = 1
                             ORDER BY tipo = 'frasco', ml ASC";

            $stmt = $db->prepare($sqlProductos);
            if ($stmt) {$stmt->bind_param("i", $catalogoId);$stmt->execute();
                $resProductos =$stmt->get_result();

                $variantes = array();
                while ($prod =$resProductos->fetch_assoc()) {
                    // Un decant sale de los ml del frasco abierto; un frasco, de los sellados
                    $hayStock = $prod['tipo'] === 'frasco'
                        ? $perfume['frascos_sellados'] >= 1
                        : $perfume['ml_disponibles'] >= $prod['ml'];
                    if (!$hayStock) {
                        continue;
                    }
                    $prod['contenido_ml'] = (float)$prod['ml'];
                    $prod['etiqueta'] = ($prod['tipo'] === 'frasco' ? 'Frasco completo ' : '') . $prod['contenido_ml'] . ' ml';
                    $variantes[] =$prod;
                }
                $stmt->close();

                $perfume['variantes'] =$variantes;

                // Solo incluir si tiene al menos una variante con stock
                if (count($variantes) > 0) {
                    $catalogo[] =$perfume;
                }
            }
        }
    }

    return $catalogo;
}

// Detectar automáticamente el nombre de la variable de conexión
$dbConnection = null;
if (isset($conexion) && is_object($conexion)) {
    $dbConnection =$conexion;
} elseif (isset($conn) && is_object($conn)) {
    $dbConnection =$conn;
} elseif (isset($link) && is_object($link)) {
    $dbConnection =$link;
}

// Ejecutar la consulta con la conexión encontrada
$listaPerfumes = obtenerCatalogoPublico($dbConnection);