<?php
/**
 * C9.1: vínculo del cliente con la clínica, con la misma acción que usa el
 * veterinario en Pacientes (actualizar_propietario_ajax).
 */
?>
<label class="toggle-switch" title="<?= $activo ? 'Vínculo activo (clic para desactivar)' : 'Vínculo inactivo (clic para activar)' ?>">
    <input type="checkbox" data-accion="estado-cliente" data-id="<?= (int) $p['id_usuario'] ?>" data-nombre="<?= $e($p['nombre_completo']) ?>" aria-label="Vínculo activo: <?= $e($p['nombre_completo']) ?>" <?= $activo ? 'checked' : '' ?>>
    <span class="toggle-slider"></span>
</label>
