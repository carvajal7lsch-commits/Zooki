<?php
// El motivo sigue siendo texto: las opciones son ayudas de entrada, no estados ni reglas nuevas.
require_once __DIR__ . '/../../helpers/ValidadorAgendaClinica.php';
$idMotivo = $motivoOpcional ? 'ausencia-motivo' : 'cierre-motivo';
$eMotivo = static fn (string $texto): string => htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
?>
<label class="coord-campo coord-campo--ancho" data-motivo-agenda>
    <span>Motivo<?php if ($motivoOpcional): ?> <span class="coord-opcional">(opcional)</span><?php endif; ?></span>
    <select data-motivo-opcion aria-describedby="<?= $idMotivo ?>-error">
        <option value=""><?= $motivoOpcional ? 'Sin especificar' : 'Selecciona un motivo' ?></option>
        <?php foreach ($opcionesMotivo as $opcionMotivo): ?>
            <option value="<?= $eMotivo($opcionMotivo) ?>"><?= $eMotivo($opcionMotivo) ?></option>
        <?php endforeach; ?>
        <option value="otro">Otro motivo</option>
    </select>
    <input name="motivo" maxlength="<?= ValidadorAgendaClinica::MOTIVO_MAX ?>" data-espacios="normalizar" placeholder="Escribe el motivo" aria-describedby="<?= $idMotivo ?>-error" aria-label="Otro motivo" <?= $motivoOpcional ? '' : 'required' ?> hidden>
    <span id="<?= $idMotivo ?>-error" data-error-campo="motivo" class="coordinacion-error" aria-live="polite"></span>
</label>
