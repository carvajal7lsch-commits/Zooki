<?php
// C4: captura compartida; los episodios y el nivel se validan en el servidor.
$hAgenda = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<section data-triage-agenda data-permite-revision="<?= !empty($puedeRevisarAgenda) ? '1' : '0' ?>" aria-label="Síntomas y triage">
    <p>Confirma las marcas antes de consultar horarios. La nota no calcula el nivel.</p>
    <?php foreach (['sintomas' => 'Síntomas', 'exposiciones' => 'Sustancias o fármacos a los que estuvo expuesta'] as $grupoAgenda => $tituloAgenda): ?>
    <div data-triage-grupo="<?= $grupoAgenda ?>" data-sintomas data-max="30" class="consulta-sintomas">
        <label class="zk-label" for="<?= $hAgenda($prefijoAgenda . '_' . $grupoAgenda) ?>"><?= $tituloAgenda ?></label>
        <div data-sintomas-chips class="consulta-sintomas__chips" hidden></div>
        <div class="consulta-sintomas__buscador">
            <input class="zk-input" id="<?= $hAgenda($prefijoAgenda . '_' . $grupoAgenda) ?>" data-sintomas-buscar maxlength="100" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?= $hAgenda($prefijoAgenda . '_' . $grupoAgenda . '_opciones') ?>" placeholder="Buscar en el catálogo">
            <ul id="<?= $hAgenda($prefijoAgenda . '_' . $grupoAgenda . '_opciones') ?>" data-sintomas-sugerencias class="consulta-sintomas__sugerencias" role="listbox" hidden></ul>
        </div>
        <small data-campo-aviso="sintomas" class="cuenta-validacion"></small>
    </div>
    <?php endforeach; ?>
    <button type="button" class="btn-secondary" data-triage-otro>Otro / no sé</button>
    <div data-triage-tiempos></div>
    <label class="zk-label">Vacunación declarada
        <select class="zk-input" data-triage-vacunacion><option value="">Sin dato</option><option value="completa">Completa</option><option value="incompleta">Incompleta</option><option value="desconocida">Desconocida</option></select>
    </label>
    <label class="zk-label">Días desde el celo, si se conocen
        <input class="zk-input" data-triage-celo type="text" inputmode="decimal" data-caracteres="0-9." data-formato="decimal" data-decimales="3" maxlength="12">
    </label>
    <small class="cuenta-validacion" data-triage-validacion aria-live="polite"></small>
    <button type="button" class="btn-secondary" data-triage-calcular>Calcular triage</button>
    <button type="button" class="btn-secondary" data-triage-comprobar hidden>Comprobar revisión</button>
    <p data-triage-resultado role="status" aria-live="polite"></p>
    <?php if (!empty($puedeRevisarAgenda)): ?>
    <div data-triage-revision hidden>
        <label class="zk-label">Nivel aprobado <select class="zk-input" data-triage-nivel><option value="amarillo">Amarillo</option><option value="naranja">Naranja</option><option value="verde">Verde</option><option value="rojo">Rojo</option></select></label>
        <label class="zk-label">Motivo de revisión <input class="zk-input" data-triage-motivo maxlength="255" data-caracteres="\p{L}\p{M}\p{N}\p{P}\p{S} " data-espacios="normalizar"></label>
        <button type="button" class="btn-secondary" data-triage-aprobar>Confirmar revisión</button>
    </div>
    <?php endif; ?>
</section>
