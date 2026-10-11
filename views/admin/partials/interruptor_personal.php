<?php
/** C9.1: estado del vínculo en la clínica (RN-G08); nadie se desactiva a sí mismo. */
?>
<label class="toggle-switch <?= $esYo ? 'disabled' : '' ?>" title="<?= $esYo ? 'No puedes desactivarte a ti mismo' : ($activo ? 'Activo en la clínica (clic para desactivar)' : 'Inactivo en la clínica (clic para activar)') ?>">
    <input type="checkbox" data-accion="estado" data-id="<?= (int) $p['id_usuario'] ?>" data-nombre="<?= $e($p['nombre_completo']) ?>" aria-label="Activo en la clínica: <?= $e($p['nombre_completo']) ?>" <?= $activo ? 'checked' : '' ?> <?= $esYo ? 'disabled' : '' ?>>
    <span class="toggle-slider"></span>
</label>
