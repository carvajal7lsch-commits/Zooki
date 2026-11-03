<?php
/**
 * Portada. El collage combina el halo de marca, el recorte del paciente
 * y tres fichas flotantes que resumen la promesa del producto.
 * Espera del contexto: $lp_logged_in, $lp_cta_url, $lp_cta_text y $ofertaLanzamiento.
 */
?>
<section class="lp-hero" id="inicio">
    <div class="lp-container">

        <div class="lp-hero__grid">

            <div class="lp-hero__content lp-reveal">
                <h1 class="lp-hero__title">
                    Tu veterinaria con un copiloto que <em>avisa antes del error</em>
                </h1>

                <p class="lp-hero__subtitle">
                    Sugiere diagnósticos, bloquea medicamentos peligrosos para cada especie y raza, y
                    reacomoda la agenda sola cuando algo se retrasa. Tu clínica empieza gratis.
                </p>

                <div class="lp-hero__ctas">
                    <?php if ($lp_logged_in): ?>
                    <a href="<?php echo $lp_cta_url; ?>" class="lp-btn lp-btn--primary">
                        <?php echo $lp_cta_text; ?> <i class="ri-arrow-right-line" aria-hidden="true"></i>
                    </a>
                    <?php else: ?>
                    <a href="index.php?action=registro_clinica" class="lp-btn lp-btn--primary">
                        Registra tu clínica gratis <i class="ri-arrow-right-line" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                    <a href="#planes" class="lp-btn lp-btn--ghost">Ver planes</a>
                </div>

                <ul class="lp-hero__trust">
                    <li><i class="ri-gift-line" aria-hidden="true"></i> Plan gratuito con los dos grafos</li>
                    <?php if ($ofertaLanzamiento): ?>
                    <li><i class="ri-price-tag-3-line" aria-hidden="true"></i> Lanzamiento: paga 1 mes, lleva 2</li>
                    <?php endif; ?>
                    <li><i class="ri-building-line" aria-hidden="true"></i> Datos aislados por clínica</li>
                </ul>
            </div>

            <div class="lp-hero__visual lp-reveal" data-delay="2" aria-hidden="true">
                <div class="lp-hero__ring"></div>
                <div class="lp-hero__halo"></div>

                <img src="img/cachorro-negro.png" alt="" class="lp-hero__pet"
                     width="583" height="428" fetchpriority="high" decoding="async">

                <div class="lp-chip lp-chip--vacuna">
                    <span class="lp-chip__icon lp-chip__icon--danger"><i class="ri-alert-line"></i></span>
                    <span>
                        <span class="lp-chip__label">Prescripción bloqueada</span>
                        <span class="lp-chip__value">Paracetamol · tóxico en gatos</span>
                    </span>
                </div>

                <div class="lp-chip lp-chip--cita">
                    <span class="lp-chip__icon lp-chip__icon--warn"><i class="ri-pulse-line"></i></span>
                    <span>
                        <span class="lp-chip__label">Triage calculado</span>
                        <span class="lp-chip__value">Naranja · urgente</span>
                    </span>
                </div>

                <div class="lp-chip lp-chip--historia">
                    <span class="lp-chip__icon lp-chip__icon--blue"><i class="ri-calendar-check-line"></i></span>
                    <span>
                        <span class="lp-chip__label">Agenda reacomodada</span>
                        <span class="lp-chip__value">3 citas · +15 min</span>
                    </span>
                </div>
            </div>

        </div>

    </div>

    <!-- Señal de continuidad: con el hero a pantalla completa nada asoma por
         debajo, y sin esta pista el usuario puede creer que la página termina. -->
    <a href="#problema" class="lp-hero__scroll">
        <span>Conoce más</span>
        <i class="ri-arrow-down-line" aria-hidden="true"></i>
    </a>
</section>
