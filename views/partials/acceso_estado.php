<?php
/**
 * M0.2 — Encabezado de estado de las páginas de acceso: el mismo componente
 * para el formulario por completar, el éxito, el error y el enlace no
 * disponible; cambian el color y el icono.
 *
 * Variable $estado: ['tipo' => 'info'|'exito'|'error'|'aviso', 'icono' =>
 * clase de Remix Icon, 'titulo' => texto, 'texto' => texto] y, en lugar de
 * 'texto', 'html' con HTML ya escapado por la vista (para resaltar un nombre).
 */
$h = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tipoEstado = in_array($estado['tipo'] ?? 'info', ['info', 'exito', 'error', 'aviso'], true) ? $estado['tipo'] : 'info';
$iconoEstado = $estado['icono'] ?? ['info' => 'ri-information-line', 'exito' => 'ri-checkbox-circle-line', 'error' => 'ri-error-warning-line', 'aviso' => 'ri-time-line'][$tipoEstado];
?>
<div class="acceso-estado acceso-estado--<?= $h($tipoEstado) ?>" <?= $tipoEstado === 'info' ? '' : 'role="status"' ?>>
    <span class="acceso-estado__icono" aria-hidden="true"><i class="<?= $h($iconoEstado) ?>"></i></span>
    <h1><?= $h($estado['titulo']) ?></h1>
    <?php if (isset($estado['html'])): ?>
        <p class="acceso-estado__texto"><?= $estado['html'] ?></p>
    <?php elseif (($estado['texto'] ?? '') !== ''): ?>
        <p class="acceso-estado__texto"><?= $h($estado['texto']) ?></p>
    <?php endif; ?>
</div>
