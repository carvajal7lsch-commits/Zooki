<?php
/**
 * Pestañas por rol del sistema (los cuatro perfiles definidos en la ERS).
 * La interacción se resuelve en landing.js siguiendo el patrón ARIA de tabs.
 *
 * Cada panel acompaña el texto con una mini interfaz construida en CSS que
 * representa lo que ese perfil ve dentro del sistema. Son ilustrativas y van
 * marcadas como decorativas para que un lector de pantalla no las anuncie
 * como datos clínicos reales.
 */
?>
<section class="lp-section lp-section--alt" id="roles">
    <div class="lp-container">

        <div class="lp-section-head lp-reveal">
            <span class="lp-eyebrow">Para tu equipo</span>
            <h2 class="lp-section-title">Cada persona ve exactamente lo que necesita</h2>
            <p class="lp-section-desc">
                Zooki maneja cuatro perfiles con permisos independientes. Nadie accede a información
                que no le corresponde, y cada quien encuentra su trabajo sin dar vueltas.
            </p>
        </div>

        <div class="lp-roles lp-reveal" data-delay="1">

            <div class="lp-roles__tabs" role="tablist" aria-label="Perfiles de usuario de Zooki">
                <button type="button" class="lp-roles__tab is-active" data-role="vet"
                        role="tab" aria-selected="true" aria-controls="rol-vet" id="tab-vet" tabindex="0">
                    <i class="ri-stethoscope-line" aria-hidden="true"></i> Veterinario
                </button>
                <button type="button" class="lp-roles__tab" data-role="recepcion"
                        role="tab" aria-selected="false" aria-controls="rol-recepcion" id="tab-recepcion" tabindex="-1">
                    <i class="ri-customer-service-2-line" aria-hidden="true"></i> Recepción
                </button>
                <button type="button" class="lp-roles__tab" data-role="propietario"
                        role="tab" aria-selected="false" aria-controls="rol-propietario" id="tab-propietario" tabindex="-1">
                    <i class="ri-user-heart-line" aria-hidden="true"></i> Propietario
                </button>
                <button type="button" class="lp-roles__tab" data-role="admin"
                        role="tab" aria-selected="false" aria-controls="rol-admin" id="tab-admin" tabindex="-1">
                    <i class="ri-shield-user-line" aria-hidden="true"></i> Administrador
                </button>
            </div>

            <!-- Veterinario -->
            <div class="lp-roles__panel is-active" id="rol-vet" role="tabpanel" aria-labelledby="tab-vet">
                <div class="lp-roles__body">
                    <p class="lp-roles__label"><i class="ri-stethoscope-line" aria-hidden="true"></i> Veterinario</p>
                    <h3 class="lp-roles__title">Atiende sin perder tiempo buscando papeles</h3>
                    <p class="lp-roles__text">
                        Abre la ficha del paciente y tiene delante todo su historial: consultas anteriores,
                        vacunas aplicadas, tratamientos en curso y exámenes adjuntos.
                    </p>
                    <ul class="lp-roles__list">
                        <li><i class="ri-check-line" aria-hidden="true"></i> Registra consultas con signos vitales, diagnóstico y plan de tratamiento.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Receta medicamentos con dosis y frecuencia asociadas a la consulta.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Aplica vacunas y desparasitaciones programando la siguiente dosis.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Consulta su agenda del día y gestiona el estado de cada cita.</li>
                    </ul>
                </div>
                <div class="lp-roles__figure">
                    <div class="lp-ui" aria-hidden="true">
                        <div class="lp-ui__bar">
                            <span class="lp-ui__dots"><span></span><span></span><span></span></span>
                            Historia clínica
                        </div>
                        <div class="lp-ui__body">
                            <div class="lp-ui__head">
                                <span class="lp-ui__name">Simba · Canino</span>
                                <span class="lp-ui__chip lp-ui__chip--blue">En consulta</span>
                            </div>
                            <div class="lp-ui__rows">
                                <div class="lp-ui__row"><span class="lp-ui__k">Peso</span><span class="lp-ui__v">28,4 kg</span></div>
                                <div class="lp-ui__row"><span class="lp-ui__k">Temperatura</span><span class="lp-ui__v">38,5 °C</span></div>
                                <div class="lp-ui__row"><span class="lp-ui__k">Frecuencia cardíaca</span><span class="lp-ui__v">92 lpm</span></div>
                            </div>
                            <div>
                                <p class="lp-ui__label">Diagnóstico</p>
                                <p class="lp-ui__v">Gastritis leve</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recepción -->
            <div class="lp-roles__panel" id="rol-recepcion" role="tabpanel" aria-labelledby="tab-recepcion">
                <div class="lp-roles__body">
                    <p class="lp-roles__label"><i class="ri-customer-service-2-line" aria-hidden="true"></i> Recepcionista</p>
                    <h3 class="lp-roles__title">Agenda con la certeza de que no habrá cruces</h3>
                    <p class="lp-roles__text">
                        El calendario muestra la disponibilidad real de cada veterinario. Al agendar,
                        el sistema solo ofrece los horarios que de verdad están libres.
                    </p>
                    <ul class="lp-roles__list">
                        <li><i class="ri-check-line" aria-hidden="true"></i> Registra nuevos propietarios y sus mascotas en minutos.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Programa, reprograma y cancela citas con notificación automática.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Ve la agenda completa de la clínica por día, semana o veterinario.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Busca cualquier paciente o propietario en tiempo real.</li>
                    </ul>
                </div>
                <div class="lp-roles__figure">
                    <div class="lp-ui" aria-hidden="true">
                        <div class="lp-ui__bar">
                            <span class="lp-ui__dots"><span></span><span></span><span></span></span>
                            Agenda · Martes 12
                        </div>
                        <div class="lp-ui__body">
                            <div class="lp-ui__slots">
                                <div class="lp-ui__slot"><span class="lp-ui__time">08:00</span> Nube · Vacunación</div>
                                <div class="lp-ui__slot"><span class="lp-ui__time">09:00</span> Simba · Control</div>
                                <div class="lp-ui__slot lp-ui__slot--free"><span class="lp-ui__time">10:00</span> Disponible</div>
                                <div class="lp-ui__slot lp-ui__slot--blocked"><span class="lp-ui__time">11:00</span> Ocupado · se bloquea</div>
                            </div>
                            <div class="lp-ui__head">
                                <span class="lp-ui__label">Dr. Ramírez</span>
                                <span class="lp-ui__chip lp-ui__chip--accent">Sin cruces</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Propietario -->
            <div class="lp-roles__panel" id="rol-propietario" role="tabpanel" aria-labelledby="tab-propietario">
                <div class="lp-roles__body">
                    <p class="lp-roles__label"><i class="ri-user-heart-line" aria-hidden="true"></i> Propietario</p>
                    <h3 class="lp-roles__title">Su mascota, en el bolsillo</h3>
                    <p class="lp-roles__text">
                        Un portal pensado para el celular, donde el dueño hace seguimiento sin tener que
                        llamar a la clínica para preguntar cuándo toca el refuerzo.
                    </p>
                    <ul class="lp-roles__list">
                        <li><i class="ri-check-line" aria-hidden="true"></i> Registra y edita el perfil de sus mascotas, incluida la foto.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Agenda citas eligiendo entre los horarios realmente disponibles.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Consulta el calendario de salud con vacunas y controles pendientes.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Descarga la cartilla de salud oficial en PDF cuando la necesite.</li>
                    </ul>
                </div>
                <div class="lp-roles__figure">
                    <div class="lp-ui lp-ui--phone" aria-hidden="true">
                        <div class="lp-ui__bar">
                            <span class="lp-ui__dots"><span></span></span>
                            Mis mascotas
                        </div>
                        <div class="lp-ui__body">
                            <div class="lp-ui__head">
                                <span class="lp-ui__name">Luna</span>
                                <span class="lp-ui__chip lp-ui__chip--muted">Felino</span>
                            </div>
                            <div class="lp-ui__rows">
                                <div class="lp-ui__row"><span class="lp-ui__k">Vacuna</span><span class="lp-ui__v">12 días</span></div>
                                <div class="lp-ui__row"><span class="lp-ui__k">Control</span><span class="lp-ui__v">Al día</span></div>
                            </div>
                            <div class="lp-ui__cta">Agendar cita</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Administrador -->
            <div class="lp-roles__panel" id="rol-admin" role="tabpanel" aria-labelledby="tab-admin">
                <div class="lp-roles__body">
                    <p class="lp-roles__label"><i class="ri-shield-user-line" aria-hidden="true"></i> Administrador</p>
                    <h3 class="lp-roles__title">Control total, con rastro de todo</h3>
                    <p class="lp-roles__text">
                        Gestiona el equipo, define los parámetros de la clínica y supervisa la operación
                        con un registro de auditoría que no deja huecos.
                    </p>
                    <ul class="lp-roles__list">
                        <li><i class="ri-check-line" aria-hidden="true"></i> Crea usuarios, asigna roles y activa o desactiva cuentas.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Configura horarios de atención, tipos de cita y catálogos clínicos.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Revisa el log de auditoría con el valor anterior y el nuevo de cada cambio.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Consulta indicadores de la operación desde el panel principal.</li>
                    </ul>
                </div>
                <div class="lp-roles__figure">
                    <div class="lp-ui" aria-hidden="true">
                        <div class="lp-ui__bar">
                            <span class="lp-ui__dots"><span></span><span></span><span></span></span>
                            Usuarios y auditoría
                        </div>
                        <div class="lp-ui__body">
                            <div class="lp-ui__people">
                                <div class="lp-ui__person">
                                    <span class="lp-ui__avatar">MG</span>
                                    <span class="lp-ui__person-name">María G.</span>
                                    <span class="lp-ui__chip lp-ui__chip--blue">Veterinario</span>
                                </div>
                                <div class="lp-ui__person">
                                    <span class="lp-ui__avatar">CR</span>
                                    <span class="lp-ui__person-name">Carlos R.</span>
                                    <span class="lp-ui__chip lp-ui__chip--accent">Recepción</span>
                                </div>
                                <div class="lp-ui__person">
                                    <span class="lp-ui__avatar">AP</span>
                                    <span class="lp-ui__person-name">Ana P.</span>
                                    <span class="lp-ui__chip lp-ui__chip--muted">Inactivo</span>
                                </div>
                            </div>
                            <div class="lp-ui__rows">
                                <div class="lp-ui__row"><span class="lp-ui__k">LOGIN · María G.</span><span class="lp-ui__v">hace 2 min</span></div>
                                <div class="lp-ui__row"><span class="lp-ui__k">UPDATE · mascota #14</span><span class="lp-ui__v">hace 1 h</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</section>
