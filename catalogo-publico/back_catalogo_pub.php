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

    // 1. Consultar perfumes activos en la tabla `catalogo`
    $sqlCatalogo = "SELECT id, nombre, marca, genero, familia_olfativa, 
                           notas_salida, notas_corazon, notas_fondo, imagen 
                    FROM catalogo 
                    WHERE activo = 1 
                    ORDER BY nombre ASC";

    $resultadoCatalogo = $db->query($sqlCatalogo);

    if ($resultadoCatalogo &&$resultadoCatalogo->num_rows > 0) {
        while ($perfume =$resultadoCatalogo->fetch_assoc()) {
            $catalogoId = (int)$perfume['id'];

            // Ruta de la imagen del catálogo
            $perfume['imagen_url'] = !empty($perfume['imagen']) 
                ? '../uploads/catalogo/' . $perfume['imagen'] 
                : '../uploads/catalogo/default.png';

            // 2. Consultar productos/variantes de la tabla `productos`
            $sqlProductos = "SELECT id, nombre, descripcion, tipo, contenido_ml, precio, stock, imagen 
                             FROM productos 
                             WHERE catalogo_id = ? AND activo = 1 AND stock > 0
                             ORDER BY contenido_ml ASC";

            $stmt = $db->prepare($sqlProductos);
            if ($stmt) {$stmt->bind_param("i", $catalogoId);$stmt->execute();
                $resProductos =$stmt->get_result();

                $variantes = array();
                while ($prod =$resProductos->fetch_assoc()) {
                    $prod['imagen_url'] = !empty($prod['imagen']) 
                        ? '../uploads/productos/' . $prod['imagen'] 
                        : $perfume['imagen_url'];
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