<?php
/**
 * Seguridad técnica implementada. Las afirmaciones corresponden a los
 * requisitos RNF-03 a RNF-06 de la ERS. El marco normativo no vive aquí:
 * su lugar es la política de tratamiento de datos, donde se explica cómo
 * se cumple en lugar de limitarse a enunciarlo.
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

        <div class="lp-security__grid">

            <article class="lp-security__card lp-reveal">
                <span class="lp-security__icon"><i class="ri-lock-password-line" aria-hidden="true"></i></span>
                <h3 class="lp-security__title">Contraseñas cifradas</h3>
                <p class="lp-security__text">
                    Hashing bcrypt con salt. Ni siquiera un administrador puede leer la contraseña
                    de otro usuario.
                </p>
            </article>

            <article class="lp-security__card lp-reveal" data-delay="1">
                <span class="lp-security__icon"><i class="ri-shield-keyhole-line" aria-hidden="true"></i></span>
                <h3 class="lp-security__title">Control de acceso por roles</h3>
                <p class="lp-security__text">
                    Cada perfil accede solo a sus rutas. Un propietario jamás alcanza la
                    administración del sistema.
                </p>
            </article>

            <article class="lp-security__card lp-reveal" data-delay="2">
                <span class="lp-security__icon"><i class="ri-file-shield-2-line" aria-hidden="true"></i></span>
                <h3 class="lp-security__title">Archivos clínicos protegidos</h3>
                <p class="lp-security__text">
                    Radiografías y exámenes no son accesibles por URL directa: exigen sesión
                    y autorización sobre ese paciente.
                </p>
            </article>

            <article class="lp-security__card lp-reveal">
                <span class="lp-security__icon"><i class="ri-spam-2-line" aria-hidden="true"></i></span>
                <h3 class="lp-security__title">Protección CSRF y rate limiting</h3>
                <p class="lp-security__text">
                    Un middleware global valida tokens en cada escritura y limita los intentos
                    contra el inicio de sesión.
                </p>
            </article>

            <article class="lp-security__card lp-reveal" data-delay="1">
                <span class="lp-security__icon"><i class="ri-history-line" aria-hidden="true"></i></span>
                <h3 class="lp-security__title">Auditoría forense</h3>
                <p class="lp-security__text">
                    Altas, ediciones y borrados quedan registrados con su autor y el estado
                    anterior del dato.
                </p>
            </article>

            <article class="lp-security__card lp-reveal" data-delay="2">
                <span class="lp-security__icon"><i class="ri-google-fill" aria-hidden="true"></i></span>
                <h3 class="lp-security__title">Autenticación con Google</h3>
                <p class="lp-security__text">
                    Integra Google Identity Services para quienes prefieren no gestionar otra
                    contraseña.
                </p>
            </article>

        </div>

    </div>
</section>
