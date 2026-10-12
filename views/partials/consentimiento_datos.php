<?php
/**
 * M0.1 (RN-G19, RE-0.1.10, RE-5.8.x): la misma casilla de la política de
 * tratamiento de datos en todos los registros. No se marca por defecto: la
 * autorización es un acto expreso. Variable opcional: $consentimientoId.
 */
$consentimientoId = $consentimientoId ?? 'aceptaDatos';
?>
<label class="consent-check" for="<?= htmlspecialchars($consentimientoId, ENT_QUOTES, 'UTF-8') ?>">
    <input type="checkbox" name="acepta_datos" id="<?= htmlspecialchars($consentimientoId, ENT_QUOTES, 'UTF-8') ?>" value="1" required>
    <span>
        Autorizo el tratamiento de mis datos personales conforme a la
        <a href="index.php?action=privacidad" target="_blank" rel="noopener">Política de Tratamiento de Datos</a>.
    </span>
</label>
