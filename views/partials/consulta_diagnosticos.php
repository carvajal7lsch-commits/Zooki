<?php
// M2 C5a (HU-2.8, RE-2.8.1 a RE-2.8.4): diagnósticos sugeridos antes del campo
// de diagnóstico; lo maneja js/consulta-diagnosticos.js. Lo incluyen el modal
// de consulta y Atención con su clase de etiqueta ($claseEtiqueta).
// Los datos opcionales no tienen name: ayudan a calcular y no se guardan en la consulta.
// M2 C8 (H7): cada sugerencia es una fila compacta que se expande, y los datos
// que ayudarían van en una sola línea.
?>
<section class="consulta-sugerencias" data-diagnosticos aria-live="polite" aria-label="Diagnósticos sugeridos">
    <div class="consulta-sugerencias__cabecera">
        <div class="consulta-collage"><div><strong>Zooki sugiere</strong><small>Se actualiza mientras marcas síntomas</small></div><img src="img/portal-bienvenida.png" alt=""></div>
    </div>
    <section class="consulta-ficha-mini" data-ficha-mini data-edad-maxima="<?= ValidadorMascota::EDAD_MAX_ANIOS ?>" hidden aria-label="Completar ficha">
        <strong>Ayudaría a saber</strong><p>Completa la ficha para afinar las sugerencias.</p>
        <div class="consulta-ficha-mini__campos">
            <?php if (!empty($consultaEnAtencion)): ?>
                <fieldset class="consulta-ficha-mini__fila" data-ficha-fila="sexo" hidden>
                    <legend>Sexo</legend>
                    <div class="consulta-ficha-segmentado">
                        <?php foreach (['Macho', 'Hembra'] as $sexoFicha): ?>
                            <label><input type="radio" name="ficha_sexo" value="<?= $escConsulta($sexoFicha) ?>" data-ficha-campo="sexo"><span><?= $escConsulta($sexoFicha) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <small data-ficha-error="sexo"></small>
                </fieldset>
                <fieldset class="consulta-ficha-mini__fila" data-ficha-fila="esterilizado" hidden>
                    <legend>Esterilizado</legend>
                    <div class="consulta-ficha-segmentado">
                        <label><input type="radio" name="ficha_esterilizado" value="1" data-ficha-campo="esterilizado"><span>Sí</span></label>
                        <label><input type="radio" name="ficha_esterilizado" value="0" data-ficha-campo="esterilizado"><span>No</span></label>
                    </div>
                    <small data-ficha-error="esterilizado"></small>
                </fieldset>
            <?php else: ?>
                <label data-ficha-fila="sexo" hidden>Sexo<select data-ficha-campo="sexo"><option value="">Elige</option><?php foreach (ValidadorMascota::SEXOS as $sexoFicha): ?><option value="<?= $escConsulta($sexoFicha) ?>"><?= $escConsulta($sexoFicha) ?></option><?php endforeach; ?></select><small data-ficha-error="sexo"></small></label>
                <label data-ficha-fila="esterilizado" hidden>Esterilizado<select data-ficha-campo="esterilizado"><option value="">Elige</option><option value="1">Sí</option><option value="0">No</option></select><small data-ficha-error="esterilizado"></small></label>
            <?php endif; ?>
            <label data-ficha-fila="fecha_nacimiento" hidden>Nacimiento<input type="date" data-ficha-campo="fecha_nacimiento" min="<?= date('Y-m-d', strtotime('-' . ValidadorMascota::EDAD_MAX_ANIOS . ' years')) ?>" max="<?= date('Y-m-d') ?>"><small data-ficha-error="fecha_nacimiento"></small></label>
        </div>
        <button type="button" class="consulta-boton consulta-boton--suave" data-guardar-ficha>Guardar en la ficha</button><small data-ficha-aviso role="status"></small>
    </section>
    <div class="consulta-panel__sintomas" data-sintomas-panel></div>
    <p class="consulta-sugerencias__estado" data-diagnosticos-estado></p>
    <?php if (!empty($consultaEnAtencion)): ?>
    <ol class="consulta-vacio-guia" data-diagnosticos-guia hidden>
        <li>Marca los síntomas que observas.</li>
        <li>Aquí aparecen los diagnósticos posibles, del más al menos probable.</li>
    </ol>
    <?php else: ?>
    <i class="fas fa-stethoscope consulta-vacio-icono" aria-hidden="true" data-diagnosticos-vacio></i>
    <?php endif; ?>
    <ol class="consulta-sugerencias__lista" data-diagnosticos-lista hidden></ol>

    <div class="consulta-sugerencias__datos" data-diagnosticos-datos hidden>
        <span class="consulta-sugerencias__datos-titulo">Datos de esta evaluación (no se guardan en la ficha):</span>
        <ul class="consulta-sugerencias__ficha" data-diagnosticos-ficha hidden></ul>
        <label class="consulta-sugerencias__dato" data-dato="vacunacion" hidden>
            <span>Estado de vacunación</span>
            <select data-dato-campo="vacunacion">
                <option value="">Sin indicar</option>
                <option value="completa">Completa</option>
                <option value="incompleta">Incompleta</option>
                <option value="desconocida">Desconocida</option>
            </select>
        </label>
        <label class="consulta-sugerencias__dato" data-dato="dias_desde_celo" hidden>
            <span>Días desde el último celo</span>
            <input type="text" inputmode="numeric" maxlength="4" data-caracteres="0-9" data-dato-campo="dias_desde_celo" placeholder="Ej. 20">
        </label>
    </div>
    <small class="consulta-sugerencias__apoyo">Apoyo a la decisión · pendiente de validación veterinaria</small>
</section>
