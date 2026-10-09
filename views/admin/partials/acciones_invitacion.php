<?php
/**
 * D2.2 (RE-T.7.5): una invitación pendiente solo se reenvía o se cancela,
 * sea de una cuenta nueva o de una existente. No se edita ni se restablece
 * hasta que la persona acepta: la clínica no sabe cuál de las dos es.
 */
?>
<button type="button" class="<?= $claseAccion ?>" data-accion="reenviar-invitacion" data-id="<?= (int) $i['id_invitacion'] ?>" data-nombre="<?= $e($i['nombre_completo']) ?>" title="Reenviar invitación" aria-label="Reenviar la invitación de <?= $e($i['nombre_completo']) ?>"><i class="bi bi-send" aria-hidden="true"></i></button>
<button type="button" class="<?= $claseAccion ?>" data-accion="cancelar-invitacion" data-id="<?= (int) $i['id_invitacion'] ?>" data-nombre="<?= $e($i['nombre_completo']) ?>" title="Cancelar invitación" aria-label="Cancelar la invitación de <?= $e($i['nombre_completo']) ?>"><i class="bi bi-x-circle" aria-hidden="true"></i></button>
