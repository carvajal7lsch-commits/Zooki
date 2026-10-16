<?php
// M2 C5a (RN-210, A.1): síntomas del catálogo de la consulta; lo maneja js/consulta-sintomas.js.
// Lo incluyen el modal de consulta y Atención, cada uno con su clase de campo
// ($claseCampo, $claseEtiqueta) para conservar su diseño, y un $prefijoConsulta para los id.
require_once __DIR__ . '/../../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../../helpers/BuscadorSintomas.php';
$idBuscarSintoma = htmlspecialchars(($prefijoConsulta ?? 'consulta') . 'BuscarSintoma', ENT_QUOTES, 'UTF-8');
$idSugerenciasSintoma = htmlspecialchars(($prefijoConsulta ?? 'consulta') . 'SugerenciasSintoma', ENT_QUOTES, 'UTF-8');
?>
<div class="<?= htmlspecialchars($claseCampo ?? '', ENT_QUOTES, 'UTF-8') ?> consulta-sintomas" data-sintomas data-max="<?= ValidadorClinico::SINTOMAS_MAX ?>">
    <label for="<?= $idBuscarSintoma ?>" class="<?= htmlspecialchars($claseEtiqueta ?? '', ENT_QUOTES, 'UTF-8') ?>">Síntomas observados</label>
    <div class="consulta-sintomas__chips" data-sintomas-chips hidden></div>
    <div class="consulta-sintomas__buscador">
        <input type="text" id="<?= $idBuscarSintoma ?>" data-sintomas-buscar
               maxlength="<?= BuscadorSintomas::CONSULTA_MAX ?>" data-caracteres="\p{L}\p{M}\p{N} ,.()/-" data-espacios="normalizar"
               autocomplete="off" enterkeyhint="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?= $idSugerenciasSintoma ?>"
               placeholder="Busca y marca: vómito, tos, no come…">
        <ul class="consulta-sintomas__sugerencias" id="<?= $idSugerenciasSintoma ?>" data-sintomas-sugerencias role="listbox" hidden></ul>
    </div>
    <small class="cuenta-validacion" data-campo-aviso="sintomas"></small>
    <small class="consulta-ayuda consulta-sintomas__ayuda">Opcional. Lo que no esté en el catálogo, escríbelo en la anamnesis.</small>
</div>
