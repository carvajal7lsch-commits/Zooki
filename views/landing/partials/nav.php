<?php
/**
 * Barra de navegación superior + menú lateral para móviles.
 * Espera del contexto: $lp_cta_url, $lp_cta_text.
 */
?>
<header class="lp-header" id="landingHeader">
    <div class="lp-container lp-nav">

        <a href="index.php" class="lp-brand" aria-label="Zooki, ir al inicio">
            <img src="img/icon_blue.png" alt="" class="lp-brand__img" width="36" height="37">
            <span class="lp-brand__name">Zooki</span>
        </a>

        <nav class="lp-nav__menu" aria-label="Navegación principal">
            <a href="#modulos" class="lp-nav__link">Módulos</a>
            <a href="#seguridad" class="lp-nav__link">Seguridad</a>
            <a href="#preguntas" class="lp-nav__link">Preguntas</a>
        </nav>

        <div class="lp-nav__actions">
            <a href="docs/index.php" class="lp-nav__docs" target="_blank" rel="noopener">
                <i class="ri-book-2-line" aria-hidden="true"></i> Documentación
            </a>
            <a href="<?php echo $lp_cta_url; ?>" class="lp-btn lp-btn--primary lp-btn--sm">
                <?php echo $lp_cta_text; ?>
            </a>

            <button type="button" class="lp-nav__toggle" id="navToggle"
                    aria-label="Abrir menú de navegación" aria-expanded="false" aria-controls="navDrawer">
                <i class="ri-menu-line" aria-hidden="true"></i>
            </button>
        </div>

    </div>
</header>

<!-- Capa oscura del menú móvil -->
<div class="lp-overlay" id="navOverlay" aria-hidden="true"></div>

<!-- Menú lateral móvil -->
<aside class="lp-drawer" id="navDrawer" aria-label="Menú de navegación" aria-hidden="true">
    <div class="lp-drawer__head">
        <div class="lp-brand">
            <img src="img/icon_blue.png" alt="" class="lp-brand__img" width="36" height="37">
            <span class="lp-brand__name">Zooki</span>
        </div>
        <button type="button" class="lp-drawer__close" id="navClose" aria-label="Cerrar menú">
            <i class="ri-close-line" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="lp-drawer__menu">
        <a href="#modulos" class="lp-drawer__link"><i class="ri-apps-2-line" aria-hidden="true"></i> Módulos</a>
        <a href="#seguridad" class="lp-drawer__link"><i class="ri-shield-check-line" aria-hidden="true"></i> Seguridad</a>
        <a href="#preguntas" class="lp-drawer__link"><i class="ri-question-line" aria-hidden="true"></i> Preguntas</a>
    </nav>

    <div class="lp-drawer__actions">
        <a href="docs/index.php" class="lp-btn lp-btn--ghost lp-btn--block" target="_blank" rel="noopener">
            <i class="ri-book-2-line" aria-hidden="true"></i> Documentación
        </a>
        <a href="<?php echo $lp_cta_url; ?>" class="lp-btn lp-btn--primary lp-btn--block">
            <?php echo $lp_cta_text; ?> <i class="ri-arrow-right-line" aria-hidden="true"></i>
        </a>
        <p class="lp-drawer__foot">Cuidamos a quienes amas</p>
    </div>
</aside>
