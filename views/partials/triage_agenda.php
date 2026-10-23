<?php
// C4 + pulido de interfaz (2026-10-21): captura compartida (agenda del vet y portal).
// Todo se elige del catálogo; el nivel y la revisión los decide el servidor.
$hAgenda = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$grupoAgenda = static function (string $grupo, string $titulo, string $ayuda, bool $requerido = false) use ($hAgenda, $prefijoAgenda): void {
    $id = $hAgenda($prefijoAgenda . '_' . $grupo);
    ?>
    <div data-triage-grupo="<?= $grupo ?>" data-sintomas data-max="30" class="consulta-sintomas triage-captura__grupo">
        <label class="triage-captura__etiqueta" for="<?= $id ?>"><?= $titulo ?><?php if ($requerido): ?> <span class="zk-requerido" aria-hidden="true">*</span><?php endif; ?></label>
        <div data-sintomas-chips class="consulta-sintomas__chips" hidden></div>
        <div class="consulta-sintomas__buscador triage-captura__buscador">
            <i class="fas fa-search ri-search-line" aria-hidden="true"></i>
            <input class="zk-input triage-captura__input" id="<?= $id ?>" data-sintomas-buscar maxlength="100" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?= $id ?>_opciones" placeholder="<?= $hAgenda($ayuda) ?>">
            <ul id="<?= $id ?>_opciones" data-sintomas-sugerencias class="consulta-sintomas__sugerencias" role="listbox" hidden></ul>
        </div>
        <small data-campo-aviso="sintomas" class="cuenta-validacion triage-captura__aviso"></small>
    </div>
    <?php
};
?>
<section class="triage-captura" data-triage-agenda data-permite-revision="<?= !empty($puedeRevisarAgenda) ? '1' : '0' ?>" aria-label="Síntomas y nivel de urgencia">
    <div class="triage-captura__cabecera">
        <span class="triage-captura__titulo">Síntomas y urgencia</span>
        <?php if (empty($ocultarNotaAgenda)): ?>
        <span class="triage-captura__nota">Elige del catálogo; el nivel lo calcula Zooki.</span>
        <?php endif; ?>
    </div>
    <?php $grupoAgenda('sintomas', '¿Qué le pasa?', 'Escribe y elige un síntoma (ej. vómito)', true); ?>
    <button type="button" class="triage-captura__enlace" data-triage-otro>¿No aparece? Marcar «Otro / no sé»</button>
    <div class="triage-captura__tiempos" data-triage-tiempos></div>
    <details class="triage-captura__extra">
        <summary>Exposición a sustancias y otros datos <span>(opcional)</span></summary>
        <div class="triage-captura__extra-cuerpo">
            <?php $grupoAgenda('exposiciones', '¿Comió o estuvo en contacto con algo?', 'Escribe y elige la sustancia o el fármaco'); ?>
            <div class="triage-captura__fila">
                <label class="triage-captura__campo">
                    <span class="triage-captura__etiqueta">Vacunas</span>
                    <select class="zk-input" data-triage-vacunacion>
                        <option value="">Sin dato</option>
                        <option value="completa">Al día</option>
                        <option value="incompleta">Incompletas</option>
                        <option value="desconocida">No se sabe</option>
                    </select>
                </label>
                <label class="triage-captura__campo" data-triage-celo-campo>
                    <span class="triage-captura__etiqueta">Días desde el último celo</span>
                    <input class="zk-input" data-triage-celo type="text" inputmode="decimal" data-caracteres="0-9." data-formato="decimal" data-decimales="3" maxlength="12" placeholder="Si se sabe">
                </label>
            </div>
        </div>
    </details>
    <small class="cuenta-validacion triage-captura__aviso" data-triage-validacion aria-live="polite"></small>
    <button type="button" class="triage-captura__calcular" data-triage-calcular><i class="fas fa-heartbeat ri-heart-pulse-line" aria-hidden="true"></i> Calcular nivel de urgencia</button>
    <div class="triage-captura__resultado" data-triage-resultado role="status" aria-live="polite" hidden></div>
    <button type="button" class="triage-captura__enlace" data-triage-comprobar hidden>Comprobar si ya fue revisado</button>
    <?php if (!empty($puedeRevisarAgenda)): ?>
    <div class="triage-captura__revision" data-triage-revision hidden>
        <span class="triage-captura__etiqueta">Tu revisión como veterinario: confirma el nivel o cámbialo</span>
        <div class="triage-captura__fila">
            <label class="triage-captura__campo">
                <span class="triage-captura__etiqueta">Nivel</span>
                <select class="zk-input" data-triage-nivel><option value="amarillo">Amarillo · prioritario</option><option value="naranja">Naranja · urgente</option><option value="verde">Verde · no urgente</option><option value="rojo">Rojo · crítico</option></select>
            </label>
            <label class="triage-captura__campo triage-captura__campo--ancho">
                <span class="triage-captura__etiqueta">Motivo <span class="zk-optional">(solo si cambias el nivel)</span></span>
                <input class="zk-input" data-triage-motivo maxlength="255" data-caracteres="\p{L}\p{M}\p{N}\p{P}\p{S} " data-espacios="normalizar" placeholder="Motivo (opcional)">
            </label>
        </div>
        <small class="cuenta-validacion triage-captura__aviso" data-triage-revision-aviso aria-live="polite"></small>
        <button type="button" class="triage-captura__confirmar" data-triage-aprobar>Confirmar nivel</button>
    </div>
    <?php endif; ?>
</section>
