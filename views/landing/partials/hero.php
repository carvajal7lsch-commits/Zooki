<?php
/**
 * Portada. El collage combina el halo de marca, el recorte del paciente
 * y tres fichas flotantes que resumen la promesa del producto.
 * Espera del contexto: $lp_cta_url, $lp_cta_text.
 */
?>
<section class="lp-hero" id="inicio">
    <div class="lp-container">

        <div class="lp-hero__grid">

            <div class="lp-hero__content lp-reveal">
                <h1 class="lp-hero__title">
                    La historia clínica de cada mascota, <em>sin una sola ficha de cartón</em>
                </h1>

                <p class="lp-hero__subtitle">
                    Historia clínica digital, agenda sin cruces y recordatorios automáticos
                    de vacunación para tu clínica veterinaria.
                </p>

                <div class="lp-hero__ctas">
                    <a href="<?php echo $lp_cta_url; ?>" class="lp-btn lp-btn--primary">
                        <?php echo $lp_cta_text; ?> <i class="ri-arrow-right-line" aria-hidden="true"></i>
                    </a>
                    <a href="#modulos" class="lp-btn lp-btn--ghost">
                        <i class="ri-compass-3-line" aria-hidden="true"></i> Ver qué incluye
                    </a>
                </div>

                <ul class="lp-hero__trust">
                    <li><i class="ri-shield-check-line" aria-hidden="true"></i> Acceso por roles</li>
                    <li><i class="ri-lock-2-line" aria-hidden="true"></i> Contraseñas cifradas</li>
                    <li><i class="ri-file-list-3-line" aria-hidden="true"></i> Auditoría completa</li>
                </ul>
            </div>

            <div class="lp-hero__visual lp-reveal" data-delay="2" aria-hidden="true">
                <div class="lp-hero__ring"></div>
                <div class="lp-hero__halo"></div>

                <img src="img/cachorro-negro.png" alt="" class="lp-hero__pet"
                     width="583" height="428" fetchpriority="high" decoding="async">

                <div class="lp-chip lp-chip--vacuna">
                    <span class="lp-chip__icon lp-chip__icon--accent"><i class="ri-syringe-line"></i></span>
                    <span>
                        <span class="lp-chip__label">Próxima vacuna</span>
                        <span class="lp-chip__value">En 12 días</span>
                    </span>
                </div>

                <div class="lp-chip lp-chip--cita">
                    <span class="lp-chip__icon lp-chip__icon--blue"><i class="ri-calendar-check-line"></i></span>
                    <span>
                        <span class="lp-chip__label">Cita confirmada</span>
                        <span class="lp-chip__value">Hoy, 10:30 a.&nbsp;m.</span>
                    </span>
                </div>

                <div class="lp-chip lp-chip--historia">
                    <span class="lp-chip__icon lp-chip__icon--blue"><i class="ri-file-text-line"></i></span>
                    <span>
                        <span class="lp-chip__label">Historia clínica</span>
                        <span class="lp-chip__value">14 registros</span>
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
