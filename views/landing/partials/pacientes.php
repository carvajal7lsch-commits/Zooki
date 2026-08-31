<?php
/**
 * Galería ilustrada: tres pacientes de ejemplo con su mini ficha clínica.
 * Los datos son ilustrativos y sirven para explicar el modelo de información.
 */
?>
<section class="lp-section lp-section--alt" id="pacientes">
    <div class="lp-container">

        <div class="lp-section-head lp-reveal">
            <span class="lp-eyebrow">Pacientes</span>
            <h2 class="lp-section-title">Así se ve la información de cada mascota</h2>
            <p class="lp-section-desc">
                Especie, raza, próxima dosis y estado del tratamiento, siempre visibles para el
                equipo clínico y para el propietario.
            </p>
        </div>

        <div class="lp-patients">

            <article class="lp-patient lp-reveal">
                <div class="lp-patient__media">
                    <img src="img/golden-retriever.png" alt="Golden retriever adulto"
                         class="lp-patient__img" width="1952" height="2184" loading="lazy" decoding="async">
                </div>
                <div class="lp-patient__card">
                    <div class="lp-patient__name">
                        <h3>Simba</h3>
                        <span class="lp-patient__species">Canino</span>
                    </div>
                    <dl class="lp-patient__rows">
                        <div class="lp-patient__row">
                            <dt>Raza</dt>
                            <dd>Golden retriever</dd>
                        </div>
                        <div class="lp-patient__row">
                            <dt>Próxima vacuna</dt>
                            <dd><span class="lp-patient__dot lp-patient__dot--accent"></span> En 12 días</dd>
                        </div>
                        <div class="lp-patient__row">
                            <dt>Última consulta</dt>
                            <dd>Control anual</dd>
                        </div>
                    </dl>
                </div>
            </article>

            <article class="lp-patient lp-reveal" data-delay="1">
                <div class="lp-patient__media">
                    <img src="img/gato-gris.png" alt="Gato gris de pelo largo"
                         class="lp-patient__img" width="1024" height="882" loading="lazy" decoding="async">
                </div>
                <div class="lp-patient__card">
                    <div class="lp-patient__name">
                        <h3>Nube</h3>
                        <span class="lp-patient__species">Felino</span>
                    </div>
                    <dl class="lp-patient__rows">
                        <div class="lp-patient__row">
                            <dt>Raza</dt>
                            <dd>Siberiano</dd>
                        </div>
                        <div class="lp-patient__row">
                            <dt>Desparasitación</dt>
                            <dd><span class="lp-patient__dot lp-patient__dot--amber"></span> Vence pronto</dd>
                        </div>
                        <div class="lp-patient__row">
                            <dt>Última consulta</dt>
                            <dd>Vacunación triple</dd>
                        </div>
                    </dl>
                </div>
            </article>

            <article class="lp-patient lp-reveal" data-delay="2">
                <div class="lp-patient__media">
                    <img src="img/gato-blanco.png" alt="Gato blanco de ojos claros"
                         class="lp-patient__img" width="1024" height="1024" loading="lazy" decoding="async">
                </div>
                <div class="lp-patient__card">
                    <div class="lp-patient__name">
                        <h3>Luna</h3>
                        <span class="lp-patient__species">Felino</span>
                    </div>
                    <dl class="lp-patient__rows">
                        <div class="lp-patient__row">
                            <dt>Raza</dt>
                            <dd>Mestizo</dd>
                        </div>
                        <div class="lp-patient__row">
                            <dt>Próxima cita</dt>
                            <dd><span class="lp-patient__dot lp-patient__dot--blue"></span> Mañana, 9:00 a.&nbsp;m.</dd>
                        </div>
                        <div class="lp-patient__row">
                            <dt>Última consulta</dt>
                            <dd>Consulta general</dd>
                        </div>
                    </dl>
                </div>
            </article>

        </div>

    </div>
</section>
