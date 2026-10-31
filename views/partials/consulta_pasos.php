<?php
// RE-2.1.6: un solo formulario para Atención y modal; campos y límites del servidor.
require_once __DIR__ . '/../../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../../helpers/ValidadorMascota.php';
require_once __DIR__ . '/../../helpers/ValidadorTratamiento.php';
$escConsulta = fn($valor): string => htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
$prefijoCampos = !empty($consultaEnAtencion) ? 'atencion' : 'consulta';
$prefijoConsulta = $prefijoCampos;
$claseCampo = 'consulta-campo';
$claseEtiqueta = 'consulta-campo__etiqueta';
?>
<div class="consulta-tres" data-consulta-tres>
    <nav class="consulta-progreso" data-consulta-navegacion aria-label="Pasos de la consulta">
        <?php foreach (['Evaluación', 'Diagnóstico y tratamiento', 'Cierre'] as $numero => $nombrePaso): ?>
            <button type="button" class="atencion__paso-btn<?= $numero === 0 ? ' is-active' : '' ?>" data-paso="<?= $numero ?>" data-consulta-ir="<?= $numero ?>" aria-current="<?= $numero === 0 ? 'step' : 'false' ?>"><b><?= $numero + 1 ?></b><span><?= $escConsulta($nombrePaso) ?></span></button>
        <?php endforeach; ?>
    </nav>
    <div class="consulta-contexto-aviso" data-contexto-aviso role="status" hidden><span data-contexto-mensaje></span><button type="button" class="consulta-boton consulta-boton--suave" data-contexto-reintentar hidden>Reintentar</button></div>
    <div class="consulta-borrador-aviso" data-borrador-aviso role="status" hidden><span>Recuperamos tu borrador</span><button type="button" data-descartar-borrador>Descartar</button></div>
    <div class="consulta-tres__contenido">
        <div class="atencion__paso is-active consulta-hoja" data-consulta-paso="0" id="<?= !empty($consultaEnAtencion) ? 'atencionEvaluacion' : 'tabMotivo' ?>">
            <div class="consulta-campo">
                <label for="<?= $prefijoCampos ?>Motivo" class="consulta-campo__etiqueta">Motivo de la consulta <span class="consulta-obligatorio">*</span></label>
                <input type="text" id="<?= $prefijoCampos ?>Motivo" name="motivo" required maxlength="<?= ValidadorClinico::TEXTO_MAX ?>" data-consulta-campo="motivo" data-aviso-requerido="<?= $escConsulta(ValidadorClinico::AVISOS_CONSULTA['motivo']) ?>" value="<?= $escConsulta(!empty($consultaEnAtencion) ? ($cita['motivo'] ?? '') : '') ?>" placeholder="Por qué viene hoy">
                <small class="cuenta-validacion" data-campo-aviso="motivo"></small>
            </div>
            <?php include __DIR__ . '/consulta_sintomas.php'; ?>
            <div class="consulta-vitales" aria-label="Signos vitales">
                <?php foreach (['peso' => 'Peso', 'temperatura' => 'Temperatura', 'frecuencia_cardiaca' => 'Frec. cardiaca', 'frecuencia_respiratoria' => 'Frec. respiratoria'] as $campoVital => $etiquetaVital): ?>
                    <?php $idVital = $prefijoCampos . ['peso' => 'Peso', 'temperatura' => 'Temperatura', 'frecuencia_cardiaca' => 'FrecuenciaCardiaca', 'frecuencia_respiratoria' => 'FrecuenciaRespiratoria'][$campoVital]; ?>
                    <div class="consulta-vital" data-vital="<?= $campoVital ?>">
                        <label for="<?= $idVital ?>"><i class="fas fa-<?= ['peso' => 'weight', 'temperatura' => 'thermometer-half', 'frecuencia_cardiaca' => 'heartbeat', 'frecuencia_respiratoria' => 'lungs'][$campoVital] ?>" aria-hidden="true"></i> <?= $escConsulta($etiquetaVital) ?><?= $campoVital === 'peso' ? ' *' : '' ?></label>
                        <div class="consulta-vital__valor">
                            <input id="<?= $idVital ?>" name="<?= $campoVital ?>" <?= ValidadorClinico::atributosSigno($campoVital) ?> <?= $campoVital === 'peso' ? 'required' : '' ?> data-consulta-campo="<?= $campoVital ?>" data-aviso-rango="<?= $escConsulta(ValidadorClinico::AVISOS_CONSULTA[$campoVital]) ?>" data-aviso-requerido="<?= $campoVital === 'peso' ? $escConsulta(ValidadorClinico::AVISO_PESO_REQUERIDO) : '' ?>" placeholder="—">
                            <span><?= $escConsulta(ValidadorClinico::SIGNOS[$campoVital]['unidad']) ?></span>
                        </div>
                        <small data-vital-rango></small>
                        <small data-vital-referencia hidden></small>
                        <small class="cuenta-validacion" data-campo-aviso="<?= $campoVital ?>"></small>
                        <?php if ($campoVital === 'peso'): ?>
                            <label class="consulta-sin-peso"><input type="checkbox" name="peso_desconocido" value="1" data-sin-peso="peso" id="<?= $prefijoCampos ?>SinPeso"> No se pudo pesar</label>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="consulta-campo">
                <label for="<?= $prefijoCampos ?>Anamnesis" class="consulta-campo__etiqueta">Anamnesis</label>
                <textarea id="<?= $prefijoCampos ?>Anamnesis" name="anamnesis" data-consulta-campo="anamnesis" rows="4" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>" placeholder="Historia relatada por el propietario, desde cuándo…"></textarea>
            </div>
            <div data-panel-movil="0"></div>
        </div>
        <div class="atencion__paso consulta-hoja" data-consulta-paso="1" id="<?= !empty($consultaEnAtencion) ? 'atencionResolucion' : 'tabResolucion' ?>" hidden>
            <div data-panel-movil="1"></div>
            <div data-sugerencia-aviso></div>
            <div class="consulta-campo">
                <label for="<?= $prefijoCampos ?>Diagnostico" class="consulta-campo__etiqueta">Diagnóstico <span class="consulta-obligatorio">*</span></label>
                <textarea id="<?= $prefijoCampos ?>Diagnostico" name="diagnostico" rows="1" class="consulta-diagnostico-linea" required maxlength="<?= ValidadorClinico::TEXTO_MAX ?>" data-consulta-campo="diagnostico" data-aviso-requerido="<?= $escConsulta(ValidadorClinico::AVISOS_CONSULTA['diagnostico']) ?>"></textarea>
                <small class="consulta-marca-sugerido" data-diagnostico-marca hidden>Sugerido por Zooki</small><small class="cuenta-validacion" data-campo-aviso="diagnostico"></small>
            </div>
            <div class="consulta-plan-elegible" data-plan-elegible hidden>
                <div class="consulta-campo__etiqueta">Exámenes sugeridos</div><div class="consulta-examenes" data-examenes-elegibles></div>
                <div class="consulta-campo__etiqueta">Recomendaciones</div><ul class="consulta-recomendaciones" data-recomendaciones-elegibles data-maximo="<?= ValidadorClinico::TEXTO_MAX ?>"></ul>
            </div>
            <div class="consulta-campo">
                <label for="<?= $prefijoCampos ?>Plan" class="consulta-campo__etiqueta">Otras indicaciones</label>
                <textarea id="<?= $prefijoCampos ?>Plan" data-otras-indicaciones data-consulta-campo="otras_indicaciones" rows="3" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"></textarea>
                <input type="hidden" name="plan_tratamiento" data-consulta-campo="plan_tratamiento" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"><small class="cuenta-validacion" data-campo-aviso="plan_tratamiento"></small>
            </div>
            <section class="consulta-tratamientos" data-tratamientos data-avisos="<?= $escConsulta(json_encode(ValidadorTratamiento::AVISOS, JSON_UNESCAPED_UNICODE)) ?>">
                <div class="consulta-seccion__cabecera"><h3>Tratamientos</h3><button type="button" class="consulta-boton consulta-boton--suave" data-agregar-tratamiento id="<?= !empty($consultaEnAtencion) ? 'btnAgregarTratamiento' : 'consultaAgregarTratamiento' ?>">+ Agregar medicamento</button></div>
                <p data-tratamientos-vacio>Sin medicamentos. Agrega uno si hace falta.</p>
                <div data-tratamientos-lista id="<?= !empty($consultaEnAtencion) ? 'atencionTratamientos' : 'treatmentsList' ?>"></div>
                <?php include __DIR__ . '/consulta_tratamiento_fila.php'; ?>
            </section>
        </div>
        <div class="atencion__paso consulta-hoja" data-consulta-paso="2" id="<?= !empty($consultaEnAtencion) ? 'atencionCierre' : 'tabArchivos' ?>" hidden>
            <label class="consulta-adjuntos file-upload-zone" data-soltar-adjuntos>
                <input type="file" name="archivos[]" multiple accept=".jpg,.jpeg,.png,.pdf" data-consulta-archivos data-adjunto-max="<?= ValidadorClinico::ADJUNTO_BYTES_MAX ?>" data-adjunto-tipos="<?= $escConsulta(json_encode(array_values(ValidadorClinico::TIPOS_ADJUNTO))) ?>" id="<?= !empty($consultaEnAtencion) ? 'archivosConsulta' : 'consultaArchivos' ?>">
                <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i><strong>Arrastra o elige radiografías, exámenes o fotos</strong>
                <small class="consulta-ayuda">JPG, PNG o PDF · máximo 10 MB cada uno</small><div class="file-list" id="<?= !empty($consultaEnAtencion) ? 'atencionFileList' : 'fileList' ?>"></div>
                <small class="cuenta-validacion cuenta-validacion--error" data-adjuntos-aviso aria-live="polite"></small>
            </label>
            <div class="consulta-campo"><label for="<?= $prefijoCampos ?>Observaciones" class="consulta-campo__etiqueta">Observaciones</label><textarea id="<?= $prefijoCampos ?>Observaciones" name="observaciones" rows="3" data-consulta-campo="observaciones" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"></textarea></div>
            <section class="consulta-control" data-proximo-control>
                <label class="consulta-control__cabecera"><strong>Próximo control</strong><input type="checkbox" name="control_activo" value="1" data-control-activo role="switch"></label>
                <p data-control-descripcion></p>
                <div class="consulta-control__fecha" data-control-contenido hidden><label for="<?= $prefijoCampos ?>FechaControl">Fecha del control</label><input id="<?= $prefijoCampos ?>FechaControl" type="text" readonly name="control_fecha" data-control-fecha><small data-control-aviso role="status"></small></div>
            </section>
            <section class="consulta-resumen-cierre" data-panel-resumen aria-label="Resumen de la atención"><div class="consulta-collage"><strong>Todo listo para revisar</strong><img src="img/portal-bienvenida.png" alt="" loading="lazy"></div><dl data-resumen-cierre></dl></section>
        </div>
    </div>
    <div class="consulta-hoja-pie" data-hoja-pie></div>
    <aside class="consulta-panel" data-panel-escritorio>
        <?php include __DIR__ . '/consulta_diagnosticos.php'; ?>
    </aside>
</div>
