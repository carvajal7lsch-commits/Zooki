<?php // HU-T.5: misma estructura de Mi cuenta/Seguridad, sin permisos nuevos. ?>
<p class="ajustes-menu__grupo" id="ajustesGrupoCuenta">Mi cuenta</p>
<div class="ajustes-menu__caja">
    <div class="ajustes-menu__lista" role="tablist" aria-labelledby="ajustesGrupoCuenta" aria-orientation="vertical">
        <button type="button" role="tab" class="ajustes-menu__item" id="perfilTabCuenta" aria-controls="perfilCuenta" aria-selected="<?= !empty($cuentaSeleccionadaPerfil) ? 'true' : 'false' ?>"<?= empty($cuentaSeleccionadaPerfil) ? ' tabindex="-1"' : '' ?> data-config-tab="cuenta"><i class="fas fa-user" aria-hidden="true"></i><span>Mi cuenta</span><i class="fas fa-chevron-right ajustes-menu__flecha" aria-hidden="true"></i></button>
        <button type="button" role="tab" class="ajustes-menu__item" id="perfilTabSeguridad" aria-controls="seguridad" aria-selected="false" tabindex="-1" data-config-tab="seguridad"><i class="fas fa-shield-alt" aria-hidden="true"></i><span>Seguridad</span><i class="fas fa-chevron-right ajustes-menu__flecha" aria-hidden="true"></i></button>
    </div>
</div>
