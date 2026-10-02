<!-- Título de Sección -->
<div class="d-flex align-items-center mb-1">
    <span class="text-gold fs-5">&#9670;</span>
    <h3 class="ms-2 mb-0 font-serif text-brown">Selección Destacada</h3>
    <div class="separator-line"></div>
</div>
<p class="text-muted mb-4" style="font-style: italic; font-family: Georgia, serif;">Los favoritos de la casa</p>

<!-- Grid de Perfumes -->
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4" id="contenedorPerfumes">
    <?php if (empty($listaPerfumes)): ?>
        <div class="col-12 text-center py-5">
            <p class="text-muted fs-5">No hay perfumes disponibles en este momento.</p>
        </div>
    <?php else: ?>
        <?php foreach ($listaPerfumes as $perfume): ?>
            <div class="col tarjeta-perfume">
                <div class="card h-100 bg-dark-card text-white border-0" style="border-radius: 12px;">
                    
                    <!-- Insignias Superiores -->
                    <div class="position-absolute w-100 p-3 d-flex justify-content-between top-0 z-1">
                        <!-- Icono de Género -->
                        <?php 
                            $genero = strtolower($perfume['genero']);
                            $colorGenero = '#888';
                            $icono = 'bi-gender-ambiguous';
                            
                            if (strpos($genero, 'dama') !== false || strpos($genero, 'mujer') !== false) {
                                $colorGenero = '#e83e8c'; // Rosa
                                $icono = 'bi-gender-female';
                            } elseif (strpos($genero, 'caballero') !== false || strpos($genero, 'hombre') !== false) {
                                $colorGenero = '#0d6efd'; // Azul
                                $icono = 'bi-gender-male';
                            }
                        ?>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 28px; height: 28px; border: 1px solid <?= $colorGenero ?>; color: <?= $colorGenero ?>;">
                            <i class="bi <?= $icono ?>" style="font-size: 0.9rem;"></i>
                        </div>
                        
                        <!-- Etiqueta Disponible -->
                        <span class="badge bg-success rounded-pill px-3 py-2 d-flex align-items-center" 
                              style="font-size: 0.65rem; letter-spacing: 1px;">
                            DISPONIBLE
                        </span>
                    </div>

                    <!-- Imagen -->
                    <div class="text-center pt-5 pb-2">
                        <img src="<?= htmlspecialchars($perfume['imagen_url']) ?>" 
                             class="img-fluid" 
                             alt="<?= htmlspecialchars($perfume['nombre']) ?>" 
                             style="max-height: 200px; object-fit: contain;">
                    </div>

                    <!-- Detalles del Producto -->
                    <div class="card-body d-flex flex-column text-center px-4 pb-4">
                        <small class="text-gold text-uppercase fw-bold mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">
                            <?= htmlspecialchars($perfume['marca']) ?>
                        </small>
                        <h5 class="card-title font-serif mb-4" style="font-size: 1.2rem;">
                            <?= htmlspecialchars($perfume['nombre']) ?>
                        </h5>

                        <div class="mt-auto">
                            <!-- Selector oscuro -->
                            <select class="form-select form-select-sm mb-3 bg-dark text-white border-secondary text-center selector-variante" 
                                    onchange="actualizarPrecio(this)">
                                <?php foreach ($perfume['variantes'] as $index => $variante): ?>
                                    <option value="<?= $variante['id'] ?>" 
                                            data-precio="<?= $variante['precio'] ?>" 
                                            <?= $index === 0 ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($variante['contenido_ml']) ?> ml
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <h4 class="fw-bold mb-3 precio-display" style="color: #fff;">
                                $<?= number_format($perfume['variantes'][0]['precio'], 2) ?>
                            </h4>

                            <button type="button" 
                                    class="btn btn-outline-light w-100 rounded-pill text-uppercase" 
                                    style="font-size: 0.8rem; letter-spacing: 1px;"
                                    onclick="agregarAlCarrito(this)">
                                Agregar al Carrito
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>