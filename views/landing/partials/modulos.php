<?php
/**
 * Rejilla bento con los módulos funcionales del sistema.
 * La primera tarjeta se destaca por color, no por tamaño: la rejilla es
 * pareja de 3x2 para que ninguna fila quede incompleta.
 */
?>
<section class="lp-section" id="modulos">
    <div class="lp-container">

        <div class="lp-section-head lp-reveal">
            <span class="lp-eyebrow">Módulos</span>
            <h2 class="lp-section-title">Todo lo que ocurre en tu clínica, en un solo lugar</h2>
            <p class="lp-section-desc">
                Seis módulos conectados entre sí: lo que registra el veterinario aparece al
                instante en la agenda y en el portal del propietario.
            </p>
        </div>

        <div class="lp-bento">

            <article class="lp-bento__card lp-bento__card--featured lp-reveal">
                <span class="lp-bento__icon"><i class="ri-heart-pulse-line" aria-hidden="true"></i></span>
                <h3 class="lp-bento__title">Historia clínica digital</h3>
                <p class="lp-bento__text">
                    Cada consulta queda registrada con signos vitales, diagnóstico, plan de tratamiento
                    y receta. Admite radiografías y exámenes adjuntos.
                </p>
                <ul class="lp-bento__tags">
                    <li>Anamnesis</li>
                    <li>Signos vitales</li>
                    <li>Diagnóstico</li>
                    <li>Tratamientos</li>
                    <li>Archivos adjuntos</li>
                </ul>
            </article>

            <article class="lp-bento__card lp-reveal" data-delay="1">
                <span class="lp-bento__icon lp-bento__icon--accent"><i class="ri-calendar-check-line" aria-hidden="true"></i></span>
                <h3 class="lp-bento__title">Agenda sin cruces</h3>
                <p class="lp-bento__text">
                    Se validan contra el horario de la clínica y la disponibilidad real del veterinario.
                    Si el espacio está ocupado, se bloquea antes de guardar.
                </p>
            </article>

            <article class="lp-bento__card lp-reveal" data-delay="1">
                <span class="lp-bento__icon lp-bento__icon--amber"><i class="ri-syringe-line" aria-hidden="true"></i></span>
                <h3 class="lp-bento__title">Vacunas y desparasitación</h3>
                <p class="lp-bento__text">
                    Calendario preventivo por especie. El sistema avisa cuándo falta poco y marca
                    lo que ya está vencido.
                </p>
            </article>

            <article class="lp-bento__card lp-reveal" data-delay="2">
                <span class="lp-bento__icon"><i class="ri-smartphone-line" aria-hidden="true"></i></span>
                <h3 class="lp-bento__title">Portal del propietario</h3>
                <p class="lp-bento__text">
                    Cada dueño registra sus mascotas, agenda citas y consulta el calendario de salud
                    desde el celular.
                </p>
            </article>

            <article class="lp-bento__card lp-reveal" data-delay="2">
                <span class="lp-bento__icon lp-bento__icon--accent"><i class="ri-file-pdf-2-line" aria-hidden="true"></i></span>
                <h3 class="lp-bento__title">Reportes y exportación</h3>
                <p class="lp-bento__text">
                    Cartilla de salud oficial en PDF, con el membrete de la clínica y el historial
                    de vacunación completo.
                </p>
            </article>

            <article class="lp-bento__card lp-reveal" data-delay="3">
                <span class="lp-bento__icon lp-bento__icon--amber"><i class="ri-history-line" aria-hidden="true"></i></span>
                <h3 class="lp-bento__title">Auditoría y trazabilidad</h3>
                <p class="lp-bento__text">
                    Cada inicio de sesión, alta, edición o borrado queda registrado con su autor
                    y el detalle del cambio.
                </p>
            </article>

        </div>

    </div>
</section>
