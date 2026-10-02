<!-- Modal para Ver Detalles y Notas Olfativas del Perfume -->
<div class="modal fade" id="modalDetallesPerfume" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalDetallesLabel">Detalles del Perfume</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalPerfumeImg" src="" class="img-fluid rounded mb-3" style="max-height: 200px; object-fit: contain;">
                <h4 id="modalPerfumeNombre" class="fw-bold mb-1"></h4>
                <p id="modalPerfumeMarca" class="text-muted text-uppercase mb-3"></p>
                
                <hr>

                <div class="text-start">
                    <h6 class="fw-bold text-primary mb-2">Pirámide Olfativa</h6>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Familia:</strong> <span id="modalPerfumeFamilia"></span>
                        </li>
                        <li class="list-group-item">
                            <strong>Notas de Salida:</strong> <span id="modalPerfumeSalida"></span>
                        </li>
                        <li class="list-group-item">
                            <strong>Notas de Corazón:</strong> <span id="modalPerfumeCorazon"></span>
                        </li>
                        <li class="list-group-item">
                            <strong>Notas de Fondo:</strong> <span id="modalPerfumeFondo"></span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>