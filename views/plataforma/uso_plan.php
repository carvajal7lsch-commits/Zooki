<?php // E2/M0.2: solo cantidades del uso del plan; «sin límite» en Profesional, sin barra que llenar. ?>
<ul class="plataforma-uso" aria-label="Uso del plan">
    <?php foreach (PresentacionPlataforma::uso($clinica['uso_plan']) as $fila): ?>
    <li class="plataforma-uso__fila plataforma-uso__fila--<?= $e($fila['nivel']) ?>">
        <span class="plataforma-uso__nombre"><?= $e($fila['nombre']) ?></span>
        <span class="plataforma-uso__valor"><?= $e($fila['texto']) ?></span>
        <?php if ($fila['limite'] !== null): ?>
        <progress class="plataforma-uso__barra" max="<?= (int) max(1, $fila['limite']) ?>" value="<?= (int) min($fila['uso'], max(1, $fila['limite'])) ?>"><?= $e($fila['texto']) ?></progress>
        <?php else: ?>
        <span class="plataforma-uso__barra plataforma-uso__barra--libre" aria-hidden="true"></span>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
