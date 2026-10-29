<?php // HU-T.5, R C: misma identidad de Ajustes para ambos roles, sin repetir datos de acceso. ?>
<div class="ajustes-identidad">
    <span class="ajustes-identidad__avatar" aria-hidden="true"><?= $e($iniciales) ?></span>
    <div class="ajustes-identidad__texto">
        <p class="ajustes-identidad__nombre"><?= $e($perfil['nombre_completo']) ?></p>
        <p class="ajustes-identidad__rol"><?= $e($rolNombre) ?> · <?= $e($nombreClinicaPerfil) ?></p>
    </div>
</div>
