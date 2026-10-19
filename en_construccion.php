<div class="modulo-cab compacto">
    <div class="modulo-icono"><i class="fa-solid <?= htmlspecialchars($icono) ?>"></i></div>
    <div>
        <h1><?= htmlspecialchars($titulo) ?></h1>
        <div class="sub">Módulo en construcción</div>
    </div>
    <div class="acciones-cab">
        <span class="estado aviso"><i class="fa-solid fa-person-digging"></i> En construcción</span>
    </div>
</div>
<div class="panel text-center py-5">
    <div class="modulo-icono mx-auto mb-3" style="width:64px; height:64px; border-radius:16px; font-size:1.6rem;">
        <i class="fa-solid <?= htmlspecialchars($icono) ?>"></i>
    </div>
    <h5 class="fw-bold mb-2">Próximamente</h5>
    <p class="text-muted mb-0 mx-auto" style="max-width:420px;"><?= htmlspecialchars($texto) ?></p>
</div>
