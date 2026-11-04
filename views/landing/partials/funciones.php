<?php
/**
 * Así trabaja Zooki: una sola sección con pestañas que reúne los dos grafos,
 * los perfiles y el carnet, para no repetir la misma idea en varias secciones.
 * Usa el componente de pestañas de Roles (landing.js, patrón ARIA de tabs).
 *
 * Las mini interfaces son ilustrativas y decorativas (`aria-hidden`).
 * RN-209: el texto deja claro que el grafo sugiere y el veterinario decide.
 */
?>
<section class="lp-section lp-funciones" id="funciones">
    <div class="lp-container">

        <div class="lp-section-head lp-reveal">
            <span class="lp-eyebrow">Así trabaja Zooki</span>
            <h2 class="lp-section-title">Dos grafos trabajando mientras tú atiendes</h2>
            <p class="lp-section-desc">
                Sugieren y avisan; el veterinario siempre decide. Y todo lo demás de la clínica,
                conectado en el mismo sistema.
            </p>
        </div>

        <div class="lp-roles lp-reveal" data-delay="1">

            <div class="lp-roles__tabs" role="tablist" aria-label="Funciones de Zooki">
                <button type="button" class="lp-roles__tab is-active"
                        role="tab" aria-selected="true" aria-controls="fn-copiloto" id="tab-fn-copiloto" tabindex="0">
                    <i class="ri-share-line" aria-hidden="true"></i> Copiloto clínico
                </button>
                <button type="button" class="lp-roles__tab"
                        role="tab" aria-selected="false" aria-controls="fn-agenda" id="tab-fn-agenda" tabindex="-1">
                    <i class="ri-calendar-todo-line" aria-hidden="true"></i> Agenda inteligente
                </button>
                <button type="button" class="lp-roles__tab"
                        role="tab" aria-selected="false" aria-controls="fn-propietario" id="tab-fn-propietario" tabindex="-1">
                    <i class="ri-user-heart-line" aria-hidden="true"></i> Propietario
                </button>
                <button type="button" class="lp-roles__tab"
                        role="tab" aria-selected="false" aria-controls="fn-carnet" id="tab-fn-carnet" tabindex="-1">
                    <i class="ri-qr-code-line" aria-hidden="true"></i> Carnet QR
                </button>
                <button type="button" class="lp-roles__tab"
                        role="tab" aria-selected="false" aria-controls="fn-admin" id="tab-fn-admin" tabindex="-1">
                    <i class="ri-shield-user-line" aria-hidden="true"></i> Administración
                </button>
            </div>

            <!-- Grafo I · Copiloto clínico -->
            <div class="lp-roles__panel is-active" id="fn-copiloto" role="tabpanel" aria-labelledby="tab-fn-copiloto">
                <div class="lp-roles__body">
                    <p class="lp-roles__label"><i class="ri-share-line" aria-hidden="true"></i> Grafo I · Copiloto clínico</p>
                    <h3 class="lp-roles__title">De los síntomas al tratamiento, sin pasar por alto un riesgo</h3>
                    <ul class="lp-roles__list">
                        <li><i class="ri-check-line" aria-hidden="true"></i> Ordena los diagnósticos probables según los síntomas.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Bloquea medicamentos tóxicos por especie y raza (p. ej. MDR1).</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Calcula el triage en 4 colores sin intervención.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Historia clínica con signos vitales, tratamiento y adjuntos.</li>
                    </ul>
                </div>
                <div class="lp-roles__figure">
                    <div class="lp-ui" aria-hidden="true">
                        <div class="lp-ui__bar">
                            <span class="lp-ui__dots"><span></span><span></span><span></span></span>
                            Atención · Nube
                        </div>
                        <div class="lp-ui__body">
                            <div class="lp-ui__head">
                                <span class="lp-ui__name">Nube · Felino</span>
                                <span class="lp-ui__chip lp-ui__chip--warn">Triage naranja</span>
                            </div>
                            <div>
                                <p class="lp-ui__label">Diagnósticos probables</p>
                                <div class="lp-ui__rows">
                                    <div class="lp-ui__row"><span class="lp-ui__k">Intoxicación</span><span class="lp-ui__v">Alta</span></div>
                                    <div class="lp-ui__row"><span class="lp-ui__k">Gastritis</span><span class="lp-ui__v">Media</span></div>
                                    <div class="lp-ui__row"><span class="lp-ui__k">Pancreatitis</span><span class="lp-ui__v">Baja</span></div>
                                </div>
                            </div>
                            <div class="lp-ui__slot lp-ui__slot--blocked">Paracetamol bloqueado · tóxico en felinos</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grafo II · Agenda inteligente -->
            <div class="lp-roles__panel" id="fn-agenda" role="tabpanel" aria-labelledby="tab-fn-agenda">
                <div class="lp-roles__body">
                    <p class="lp-roles__label"><i class="ri-calendar-todo-line" aria-hidden="true"></i> Grafo II · Agenda inteligente</p>
                    <h3 class="lp-roles__title">Una consulta se alarga y la agenda se reacomoda sola</h3>
                    <ul class="lp-roles__list">
                        <li><i class="ri-check-line" aria-hidden="true"></i> Propaga los retrasos a las citas siguientes.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Una urgencia roja nunca espera turno.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Sobrecupo naranja con aviso al veterinario.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Llegadas tarde: servicio reducido, reprogramar u otro veterinario.</li>
                    </ul>
                </div>
                <div class="lp-roles__figure">
                    <div class="lp-ui" aria-hidden="true">
                        <div class="lp-ui__bar">
                            <span class="lp-ui__dots"><span></span><span></span><span></span></span>
                            Agenda de hoy
                        </div>
                        <div class="lp-ui__body">
                            <div class="lp-ui__slots">
                                <div class="lp-ui__slot lp-ui__slot--blocked"><span class="lp-ui__time">Ahora</span> Rocky · urgencia roja</div>
                                <div class="lp-ui__slot"><span class="lp-ui__time">10:00</span> Simba · en curso +15 min</div>
                                <div class="lp-ui__slot"><span class="lp-ui__time">10:45</span> Nube · reacomodada</div>
                                <div class="lp-ui__slot lp-ui__slot--free"><span class="lp-ui__time">11:30</span> Libre</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Portal del propietario -->
            <div class="lp-roles__panel" id="fn-propietario" role="tabpanel" aria-labelledby="tab-fn-propietario">
                <div class="lp-roles__body">
                    <p class="lp-roles__label"><i class="ri-user-heart-line" aria-hidden="true"></i> Portal del propietario</p>
                    <h3 class="lp-roles__title">Su mascota, en el bolsillo</h3>
                    <ul class="lp-roles__list">
                        <li><i class="ri-check-line" aria-hidden="true"></i> Agenda citas eligiendo entre los horarios realmente disponibles.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Consulta el calendario de salud con vacunas y controles pendientes.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Recibe recordatorios automáticos antes de cada vacuna.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Se vincula a varias clínicas con la misma cuenta.</li>
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

            <!-- Carnet digital con QR -->
            <div class="lp-roles__panel" id="fn-carnet" role="tabpanel" aria-labelledby="tab-fn-carnet">
                <div class="lp-roles__body">
                    <p class="lp-roles__label"><i class="ri-qr-code-line" aria-hidden="true"></i> Carnet digital</p>
                    <h3 class="lp-roles__title">Si se pierde o tiene una emergencia, su carnet habla por él</h3>
                    <ul class="lp-roles__list">
                        <li><i class="ri-check-line" aria-hidden="true"></i> Quien escanee el QR ve lo necesario para ayudar y contactar al dueño.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> El propietario decide qué se muestra.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Un solo carnet en todas las clínicas donde se atiende.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Cada escaneo queda registrado para el propietario.</li>
                    </ul>
                </div>
                <div class="lp-roles__figure">
                    <div class="lp-ui lp-ui--phone lp-ui--carnet" aria-hidden="true">
                        <div class="lp-ui__bar">
                            <span class="lp-ui__dots"><span></span></span>
                            Carnet de Nube
                        </div>
                        <div class="lp-ui__body">
                            <div class="lp-ui__head">
                                <span class="lp-ui__name">Nube · Felino</span>
                                <span class="lp-ui__chip lp-ui__chip--blue">Al día</span>
                            </div>
                            <div class="lp-ui__qr"><i class="ri-qr-code-line"></i></div>
                            <div class="lp-ui__rows">
                                <div class="lp-ui__row"><span class="lp-ui__k">Alerta médica</span><span class="lp-ui__v">Alérgica a la penicilina</span></div>
                                <div class="lp-ui__row"><span class="lp-ui__k">Vacunas</span><span class="lp-ui__v">Al día</span></div>
                            </div>
                            <div class="lp-ui__cta">Contactar al propietario</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Administración -->
            <div class="lp-roles__panel" id="fn-admin" role="tabpanel" aria-labelledby="tab-fn-admin">
                <div class="lp-roles__body">
                    <p class="lp-roles__label"><i class="ri-shield-user-line" aria-hidden="true"></i> Administrador</p>
                    <h3 class="lp-roles__title">Control total, con rastro de todo</h3>
                    <ul class="lp-roles__list">
                        <li><i class="ri-check-line" aria-hidden="true"></i> Crea las cuentas del personal y activa o desactiva accesos.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Configura horarios de atención y tipos de cita.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Revisa la auditoría con el valor anterior y el nuevo de cada cambio.</li>
                        <li><i class="ri-check-line" aria-hidden="true"></i> Gestiona el plan de la clínica y paga la suscripción en línea.</li>
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
                                    <span class="lp-ui__chip lp-ui__chip--blue">Veterinario</span>
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
