<!-- Título de Sección -->
<div class="d-flex align-items-center mb-1">
    <span class="text-gold fs-5">&#9670;</span>
    <h3 class="ms-2 mb-0 font-serif text-brown"><?= htmlspecialchars($datosSeccion['titulo']) ?></h3>
    <div class="separator-line"></div>
</div>
<p class="text-muted mb-4" style="font-style: italic; font-family: Georgia, serif;"><?= htmlspecialchars($datosSeccion['subtitulo']) ?></p>

<?php if ($seccionActual === 'combos'): ?>
    <!-- Grid de Combos -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php if (empty($listaCombos)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-gift text-gold" style="font-size: 3.5rem;"></i>
                <p class="text-muted fs-5 mt-3 mb-0">Muy pronto tendremos combos disponibles.</p>
            </div>
        <?php else: ?>
            <?php foreach ($listaCombos as $combo): ?>
                <div class="col">
                    <div class="card h-100 bg-dark-card text-white border-0" style="border-radius: 12px;">
                        <div class="position-absolute w-100 p-3 d-flex justify-content-end top-0 z-1">
                            <span class="badge <?= $combo['disponible'] ? 'bg-success' : 'bg-secondary' ?> rounded-pill px-3 py-2" style="font-size: 0.65rem; letter-spacing: 1px;">
                                <?= $combo['disponible'] ? 'DISPONIBLE' : 'AGOTADO' ?>
                            </span>
                        </div>

                        <div class="text-center pt-5 pb-2">
                            <?php if ($combo['imagen_url']): ?>
                                <img src="<?= htmlspecialchars($combo['imagen_url']) ?>" class="img-fluid" alt="<?= htmlspecialchars($combo['nombre']) ?>" style="max-height: 200px; object-fit: contain;">
                            <?php else: ?>
                                <i class="bi bi-gift text-gold" style="font-size: 5rem;"></i>
                            <?php endif; ?>
                        </div>

                        <div class="card-body d-flex flex-column text-center px-4 pb-4">
                            <small class="text-gold text-uppercase fw-bold mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">Combo</small>
                            <h5 class="card-title font-serif mb-2" style="font-size: 1.2rem;"><?= htmlspecialchars($combo['nombre']) ?></h5>
                            <?php if (!empty($combo['descripcion'])): ?>
                                <p class="small text-white-50 mb-3"><?= htmlspecialchars($combo['descripcion']) ?></p>
                            <?php endif; ?>

                            <ul class="list-unstyled small text-start mb-4 mx-auto">
                                <?php foreach ($combo['items'] as $item): ?>
                                    <li class="mb-1"><span class="text-gold">&#9670;</span> <?= $item['cantidad'] ?> × <?= htmlspecialchars($item['etiqueta']) ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="mt-auto">
                                <h4 class="fw-bold mb-3">$<?= number_format((float)$combo['precio'], 2) ?></h4>
                                <?php if ($combo['disponible']): ?>
                                    <button type="button" class="btn btn-outline-light w-100 rounded-pill text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;"
                                            onclick="agregarComboAlCarrito(this, <?= (int)$combo['id'] ?>)">
                                        Agregar al Carrito
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary w-100 rounded-pill text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;" disabled>
                                        Agotado
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php else: ?>

<?php if ($seccionActual !== 'inicio' && !empty($listaPerfumes)): ?>
    <!-- Búsqueda y filtro de género -->
    <div class="row g-2 mb-4">
        <div class="<?= $datosSeccion['generos'] === null ? 'col-md-8' : 'col-12' ?>">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-gold"></i></span>
                <input type="text" id="buscarPerfume" class="form-control border-start-0" placeholder="Buscar por nombre o marca...">
            </div>
        </div>
        <?php if ($datosSeccion['generos'] === null): ?>
            <div class="col-md-4">
                <select id="filtroGenero" class="form-select">
                    <option value="">Todos los géneros</option>
                    <option value="mujer">Mujer</option>
                    <option value="hombre">Hombre</option>
                    <option value="unisex">Unisex</option>
                </select>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Grid de Perfumes -->
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4" id="contenedorPerfumes">
    <?php if (empty($listaPerfumes)): ?>
        <div class="col-12 text-center py-5">
            <p class="text-muted fs-5">No hay perfumes disponibles en esta sección por el momento.</p>
        </div>
    <?php else: ?>
        <?php foreach ($listaPerfumes as $perfume): ?>
            <div class="col tarjeta-perfume"
                 data-nombre="<?= htmlspecialchars(mb_strtolower($perfume['nombre'])) ?>"
                 data-marca="<?= htmlspecialchars(mb_strtolower($perfume['marca'])) ?>"
                 data-genero="<?= htmlspecialchars($perfume['genero']) ?>">
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
                                        <?= htmlspecialchars($variante['etiqueta']) ?>
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

<!-- Sin resultados en la búsqueda -->
<p id="sinResultados" class="text-center text-muted fs-5 py-5 d-none">No encontramos fragancias con esa búsqueda.</p>

<?php if ($seccionActual === 'inicio' && !empty($listaPerfumes)): ?>
    <div class="text-center mt-5">
        <a href="index.php?seccion=completo" class="btn btn-outline-dark rounded-pill px-5 py-2 text-uppercase font-serif" style="font-size: 0.8rem; letter-spacing: 1px;">
            Ver Catálogo Completo <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
<?php endif; ?>

<?php endif; ?>
