<?php
/**
 * Seguridad técnica implementada, en una franja compacta: ícono y título.
 * Las afirmaciones corresponden a los requisitos RNF-05 a RNF-08 y RNF-11.
 * El marco normativo no vive aquí: su lugar es la política de tratamiento
 * de datos, donde se explica cómo se cumple en lugar de enunciarlo.
 */
?>
<section class="lp-section lp-section--dark lp-security" id="seguridad">
    <div class="lp-container">

        <div class="lp-section-head lp-reveal">
            <span class="lp-eyebrow">Seguridad</span>
            <h2 class="lp-section-title">Una historia clínica es información sensible</h2>
            <p class="lp-section-desc">
                Detrás de cada ficha hay datos personales y el historial médico de un paciente.
                Zooki los protege desde la arquitectura.
            </p>
        </div>

        <ul class="lp-security__grid lp-security__grid--compacta lp-reveal" data-delay="1">
            <li class="lp-security__card">
                <span class="lp-security__icon"><i class="ri-lock-password-line" aria-hidden="true"></i></span>
                <span class="lp-security__title">Contraseñas cifradas con bcrypt</span>
            </li>
            <li class="lp-security__card">
                <span class="lp-security__icon"><i class="ri-shield-keyhole-line" aria-hidden="true"></i></span>
                <span class="lp-security__title">Acceso por roles</span>
            </li>
            <li class="lp-security__card">
                <span class="lp-security__icon"><i class="ri-building-line" aria-hidden="true"></i></span>
                <span class="lp-security__title">Datos aislados por clínica</span>
            </li>
            <li class="lp-security__card">
                <span class="lp-security__icon"><i class="ri-file-shield-2-line" aria-hidden="true"></i></span>
                <span class="lp-security__title">Archivos clínicos solo con sesión</span>
            </li>
            <li class="lp-security__card">
                <span class="lp-security__icon"><i class="ri-spam-2-line" aria-hidden="true"></i></span>
                <span class="lp-security__title">CSRF, límite de intentos y CAPTCHA</span>
            </li>
            <li class="lp-security__card">
                <span class="lp-security__icon"><i class="ri-history-line" aria-hidden="true"></i></span>
                <span class="lp-security__title">Auditoría de cada cambio</span>
            </li>
        </ul>

    </div>
</section>
