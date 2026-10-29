<?php
// Mockup del dueño: trazo en reposo, relleno activo. Plataforma conserva sus iconos.
$iconosNavegacion = [
    'fas fa-th-large' => ['bi-grid', 'bi-grid-fill'],
    'fas fa-home' => ['bi-house', 'bi-house-fill'],
    'fas fa-users' => ['bi-people', 'bi-people-fill'],
    'fas fa-calendar-alt' => ['bi-calendar3', 'bi-calendar3-fill'],
    'fas fa-shield-alt' => ['bi-shield-check', 'bi-shield-fill-check'],
    'fas fa-cog' => ['bi-gear', 'bi-gear-fill'],
    'fas fa-user-circle' => ['bi-person-circle', 'bi-person-fill'],
];
$esBarraClinica = in_array(Contexto::rolActivo(), [Roles::ADMIN, Roles::VETERINARIO], true);
$iconoNavegacion = $esBarraClinica && isset($iconosNavegacion[$__item['icono']]) ? 'bi ' . $iconosNavegacion[$__item['icono']][empty($__item['activo']) ? 0 : 1] : $__item['icono'];
?>
<?php if ($esBarraClinica && $__item['icono'] === 'fas fa-paw' && empty($__item['activo'])): ?>
<svg class="pill-icono-huella" viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="5" cy="8" rx="2" ry="3"></ellipse><ellipse cx="10" cy="5" rx="2" ry="3"></ellipse><ellipse cx="16" cy="5" rx="2" ry="3"></ellipse><ellipse cx="21" cy="9" rx="2" ry="3"></ellipse><path d="M7 14c2-1 2-4 5-4s4 3 6 4c4 4 1 7-2 6-3-1-3-1-6 0-4 1-7-2-3-6Z"></path></svg>
<?php else: ?>
<i class="<?= $__bh($iconoNavegacion) ?>" aria-hidden="true"></i>
<?php endif; ?>
