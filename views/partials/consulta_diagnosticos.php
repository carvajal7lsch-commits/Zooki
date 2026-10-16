<?php
// M2 C5a (HU-2.8, RE-2.8.1 a RE-2.8.4): diagnósticos sugeridos antes del campo
// de diagnóstico; lo maneja js/consulta-diagnosticos.js. Lo incluyen el modal
// de consulta y Atención con su clase de etiqueta ($claseEtiqueta).
// Los datos opcionales no tienen name: ayudan a calcular y no se guardan en la consulta.
?>
<section class="consulta-sugerencias" data-diagnosticos aria-live="polite" aria-label="Diagnósticos sugeridos">
    <div class="consulta-sugerencias__cabecera">
        <span class="<?= htmlspecialchars($claseEtiqueta ?? '', ENT_QUOTES, 'UTF-8') ?> consulta-sugerencias__titulo">Diagnósticos sugeridos</span>
        <span class="consulta-sugerencias__apoyo">Sugerencia de apoyo · pendiente de validación veterinaria</span>
    </div>
    <p class="consulta-sugerencias__estado" data-diagnosticos-estado></p>
    <ol class="consulta-sugerencias__lista" data-diagnosticos-lista hidden></ol>
    <div class="consulta-sugerencias__datos" data-diagnosticos-datos hidden>
        <p class="consulta-sugerencias__datos-titulo">Datos que ayudarían a afinar las sugerencias (opcionales, no se guardan):</p>
        <ul class="consulta-sugerencias__ficha" data-diagnosticos-ficha hidden></ul>
        <div class="consulta-sugerencias__campos">
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
    </div>
</section>
