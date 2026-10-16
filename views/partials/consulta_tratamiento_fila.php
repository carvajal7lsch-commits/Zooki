<?php
// M2 C5b (HU-2.4, HU-2.9): plantilla de una fila de tratamiento, la misma en el
// modal de consulta y en Atención; la clona js/consulta-tratamientos.js.
// Los máximos, las vías y la conciliación salen de los helpers del servidor.
// M2 C8 (H9): los campos van en una cuadrícula fija (css/consulta-clinica.css)
// que no se tuerce: medicamento, dosis y vía; duración, inicio y fin; observaciones.
require_once __DIR__ . '/../../helpers/ValidadorTratamiento.php';
require_once __DIR__ . '/../../helpers/ValidadorPrescripcion.php';
$eFila = fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$caracteresFila = $eFila(ValidadorTratamiento::CARACTERES);
$obligatorio = '<span class="consulta-obligatorio" aria-hidden="true">*</span>';
?>
<template data-tratamiento-plantilla>
    <div class="consulta-tratamiento" data-tratamiento>
        <div class="consulta-tratamiento__campos">
            <div class="consulta-campo consulta-tratamiento__medicamento">
                <label class="consulta-campo__etiqueta">Medicamento <?= $obligatorio ?></label>
                <div class="consulta-tratamiento__buscador">
                    <input type="text" name="med_nombre[]" required maxlength="<?= ValidadorTratamiento::MEDICAMENTO_MAX ?>" data-tratamiento-campo="medicamento"
                           data-caracteres="<?= $caracteresFila ?>" data-espacios="normalizar" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false"
                           placeholder="Busca en el catálogo o escribe el nombre">
                    <ul class="consulta-sintomas__sugerencias" data-farmaco-sugerencias role="listbox" hidden></ul>
                </div>
                <input type="hidden" name="med_farmaco[]" data-tratamiento-campo="farmaco">
                <small class="consulta-ayuda consulta-tratamiento__cruce" data-farmaco-estado></small>
                <small class="cuenta-validacion" data-tratamiento-aviso="medicamento"></small>
            </div>
            <div class="consulta-campo consulta-tratamiento__dosis">
                <label class="consulta-campo__etiqueta">Dosis <?= $obligatorio ?></label>
                <input type="text" name="med_dosis[]" required maxlength="<?= ValidadorTratamiento::DOSIS_MAX ?>" data-tratamiento-campo="dosis"
                       data-caracteres="<?= $caracteresFila ?>" data-espacios="normalizar" placeholder="1 tableta cada 12 h">
                <small class="cuenta-validacion" data-tratamiento-aviso="dosis"></small>
            </div>
            <div class="consulta-campo consulta-tratamiento__via">
                <label class="consulta-campo__etiqueta">Vía <?= $obligatorio ?></label>
                <select name="med_via[]" required data-tratamiento-campo="via_administracion">
                    <option value="">Elige la vía</option>
                    <?php foreach (ValidadorTratamiento::VIAS as $via): ?>
                        <option value="<?= $eFila($via) ?>"><?= $eFila($via) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="cuenta-validacion" data-tratamiento-aviso="via_administracion"></small>
            </div>
            <button type="button" class="consulta-tratamiento__quitar" data-quitar-tratamiento title="Quitar medicamento" aria-label="Quitar medicamento"><i class="fas fa-trash" aria-hidden="true"></i></button>
            <div class="consulta-campo consulta-tratamiento__duracion">
                <label class="consulta-campo__etiqueta">Duración <?= $obligatorio ?></label>
                <input type="text" name="med_duracion[]" required maxlength="<?= ValidadorTratamiento::DURACION_MAX ?>" data-tratamiento-campo="duracion"
                       data-caracteres="<?= $caracteresFila ?>" data-espacios="normalizar" placeholder="7 días, 2 semanas…">
                <small class="cuenta-validacion" data-tratamiento-aviso="duracion"></small>
            </div>
            <div class="consulta-campo consulta-tratamiento__inicio">
                <label class="consulta-campo__etiqueta">Inicio <?= $obligatorio ?></label>
                <input type="date" name="med_inicio[]" required data-tratamiento-campo="fecha_inicio">
                <small class="cuenta-validacion" data-tratamiento-aviso="fecha_inicio"></small>
            </div>
            <div class="consulta-campo consulta-tratamiento__fin">
                <label class="consulta-campo__etiqueta">Fin</label>
                <input type="date" name="med_fin[]" data-tratamiento-campo="fecha_fin">
                <small class="consulta-ayuda consulta-tratamiento__ayuda" data-fin-ayuda></small>
                <small class="cuenta-validacion" data-tratamiento-aviso="fecha_fin"></small>
            </div>
            <div class="consulta-campo consulta-tratamiento__observaciones">
                <label class="consulta-campo__etiqueta">Observaciones</label>
                <input type="text" name="med_obs[]" maxlength="<?= ValidadorTratamiento::OBSERVACIONES_MAX ?>" data-tratamiento-campo="observaciones"
                       data-caracteres="<?= $caracteresFila ?>" data-espacios="normalizar" placeholder="Con comida, cuidados, controles…">
            </div>
        </div>
        <div class="consulta-tratamiento__prescripcion" data-prescripcion aria-live="polite">
            <p class="consulta-tratamiento__estado" data-prescripcion-estado></p>
            <ul class="consulta-tratamiento__alertas" data-prescripcion-lista hidden></ul>
            <label class="consulta-tratamiento__confirmar" data-prescripcion-confirmar hidden>
                <input type="checkbox" data-prescripcion-casilla> Confirmo la prescripción
            </label>
            <small class="cuenta-validacion" data-tratamiento-aviso="decision"></small>
            <div class="consulta-campo consulta-tratamiento__conciliacion" data-prescripcion-conciliacion hidden>
                <label class="consulta-campo__etiqueta">Conciliación con el propietario <?= $obligatorio ?></label>
                <textarea name="med_conciliacion[]" rows="2" maxlength="<?= ValidadorPrescripcion::CONCILIACION_MAX ?>" data-tratamiento-campo="conciliacion"
                          data-caracteres="<?= $caracteresFila ?>" data-espacios="normalizar"
                          placeholder="Qué medicación confirmó el propietario o qué autorización pediste"></textarea>
                <small class="consulta-ayuda consulta-tratamiento__contador" data-conciliacion-contador>0/<?= ValidadorPrescripcion::CONCILIACION_MAX ?></small>
                <small class="cuenta-validacion" data-tratamiento-aviso="conciliacion"></small>
            </div>
            <input type="hidden" name="med_decision[]" data-tratamiento-campo="decision">
            <input type="hidden" name="med_huella[]" data-tratamiento-campo="huella">
            <small class="cuenta-validacion" data-tratamiento-aviso="fila"></small>
        </div>
    </div>
</template>
