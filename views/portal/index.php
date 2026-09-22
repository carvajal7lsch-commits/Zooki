<?php
require_once __DIR__ . '/../../helpers/ResumenPanel.php';
require_once __DIR__ . '/../../helpers/ReglaAtencion.php';

$total_mascotas = count($mascotas);
$hoy = new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA));

// La cita más próxima de cualquiera de las mascotas.
$earliest_cita = null;
$cita_mascota = null;
foreach ($mascotas as $m) {
    $c = $m['proxima_cita'] ?? null;
    if ($c && ($earliest_cita === null || strtotime($c['fecha'] . ' ' . $c['hora']) < strtotime($earliest_cita['fecha'] . ' ' . $earliest_cita['hora']))) {
        $earliest_cita = $c;
        $cita_mascota = $m;
    }
}

$fotoMascota = fn (array $m): ?string => !empty($m['url_foto']) ? 'uploads/mascotas/' . htmlspecialchars($m['url_foto']) : null;

// Estados vistos por el propietario: "sin cerrar" sigue siendo una atención en curso para él.
$etiquetasCita = ['en_curso' => 'En atención', 'completada' => 'Completada', 'cancelada' => 'Cancelada', 'no_asistio' => 'No asistió', 'sin_cerrar' => 'En atención', 'cerrada_sin_consulta' => 'Cerrada'];
?>

<!-- ══ INICIO ═══════════════════════════════════════════════════════ -->
<section id="screen-home" class="app-screen active" aria-labelledby="homeSaludo">
    <header class="home-header">
        <div class="user-profile-summary">
            <div class="user-avatar" aria-hidden="true"><?= htmlspecialchars($iniciales) ?></div>
            <div class="user-info-text">
                <span>Hola, bienvenido 👋</span>
                <h1 id="homeSaludo"><?= htmlspecialchars($primer_nombre) ?></h1>
            </div>
        </div>
        <div class="header-actions">
            <button type="button" class="bell-btn" id="btnNotificationBell" data-nav="notifications" aria-label="Recordatorios">
                <i class="ri-notification-3-line" aria-hidden="true"></i>
                <span class="bell-badge" id="bellBadgeAlert"></span>
            </button>
        </div>
    </header>

    <div class="home-grid">
        <div class="home-grid__principal">
            <section aria-labelledby="tituloMascotas">
                <div class="section-title-row">
                    <h2 id="tituloMascotas">Mis compañeros</h2>
                    <div class="section-title-row__acciones">
                        <span class="see-all"><?= $total_mascotas ?> en total</span>
                        <button id="btnOpenAddPetModal" type="button" class="btn-icono btn-icono--suave" aria-label="Registrar nueva mascota" title="Registrar nueva mascota">
                            <i class="ri-add-line" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <?php if (empty($mascotas)): ?>
                    <div class="appointment-banner-empty">
                        <i class="ri-ghost-line" aria-hidden="true"></i>
                        <p>No tienes mascotas registradas aún.</p>
                    </div>
                <?php else: ?>
                    <div class="pet-carousel">
                        <?php foreach ($mascotas as $m): $foto = $fotoMascota($m); ?>
                            <button type="button" class="pet-carousel-item" onclick="verDetalle(<?= (int) $m['id_mascota'] ?>)">
                                <span class="pet-avatar-wrapper">
                                    <?php if ($foto): ?>
                                        <img src="<?= $foto ?>" alt="" class="pet-avatar-img" loading="lazy">
                                    <?php else: ?>
                                        <span class="pet-avatar-placeholder"><i class="fas fa-dog" aria-hidden="true"></i></span>
                                    <?php endif; ?>
                                </span>
                                <span class="pet-carousel-name"><?= htmlspecialchars($m['nombre']) ?></span>
                                <span class="pet-carousel-raza"><?= htmlspecialchars(trim(($m['especie'] ?? '') . ' · ' . (!empty($m['raza_indicada']) ? $m['raza_indicada'] : ($m['raza'] ?? '')), ' ·')) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section aria-labelledby="tituloProxima">
                <div class="section-title-row">
                    <h2 id="tituloProxima">Próxima cita</h2>
                </div>

                <?php if ($earliest_cita): $foto = $fotoMascota($cita_mascota); ?>
                    <article class="appointment-banner" role="button" tabindex="0" onclick="mostrarDetalleCita(<?= (int) $earliest_cita['id_cita'] ?>)">
                        <div class="app-banner-info">
                            <?php if ($foto): ?>
                                <img src="<?= $foto ?>" alt="" class="app-banner-avatar">
                            <?php else: ?>
                                <span class="app-banner-avatar app-banner-avatar--vacio"><i class="fas fa-dog" aria-hidden="true"></i></span>
                            <?php endif; ?>
                            <div class="app-banner-details">
                                <h3><?= htmlspecialchars($cita_mascota['nombre']) ?></h3>
                                <p><?= htmlspecialchars($earliest_cita['motivo']) ?></p>
                                <?php if (!empty($earliest_cita['veterinario_nombre'])): ?>
                                    <p class="app-banner-vet"><i class="ri-stethoscope-line" aria-hidden="true"></i> Dr(a). <?= htmlspecialchars($earliest_cita['veterinario_nombre']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="app-banner-schedule">
                            <span class="schedule-badge schedule-badge--<?= htmlspecialchars($earliest_cita['estado']) ?>"><?= htmlspecialchars(ResumenPanel::etiquetaEstado($earliest_cita['estado'])) ?></span>
                            <span class="schedule-day"><?= htmlspecialchars(ResumenPanel::etiquetaDia($earliest_cita['fecha'], $hoy)) ?></span>
                            <span class="schedule-time"><?= htmlspecialchars(ResumenPanel::hora($earliest_cita['hora'])) ?></span>
                        </div>
                    </article>
                <?php else: ?>
                    <div class="appointment-banner-empty">
                        <i class="ri-calendar-line" aria-hidden="true"></i>
                        <p>No tienes citas programadas próximamente.</p>
                        <button type="button" class="btn-enlace" data-agendar>Agendar una cita</button>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="home-grid__lateral">
            <section aria-labelledby="tituloAccesos">
                <div class="section-title-row">
                    <h2 id="tituloAccesos">Accesos rápidos</h2>
                </div>
                <div class="home-services-grid">
                    <button type="button" class="home-service-card" data-agendar>
                        <span class="service-card-icon deworming"><i class="ri-calendar-todo-line" aria-hidden="true"></i></span>
                        <h3>Agendar cita</h3>
                        <p>Elige tu veterinario</p>
                    </button>
                    <button type="button" class="home-service-card" data-nav="explore">
                        <span class="service-card-icon consultation"><i class="ri-search-eye-line" aria-hidden="true"></i></span>
                        <h3>Ver servicios</h3>
                        <p>Consultas y más</p>
                    </button>
                    <button type="button" class="home-service-card" data-nav="notifications">
                        <span class="service-card-icon vaccines"><i class="ri-notification-badge-line" aria-hidden="true"></i></span>
                        <h3>Recordatorios</h3>
                        <p>Vacunas y control</p>
                    </button>
                    <button type="button" class="home-service-card" data-nav="account">
                        <span class="service-card-icon history"><i class="ri-user-settings-line" aria-hidden="true"></i></span>
                        <h3>Mi cuenta</h3>
                        <p>Editar perfil</p>
                    </button>
                </div>
            </section>

            <!-- HU-43: horario real de la clínica (antes, un banner de «24 horas» que no era cierto). -->
            <section class="horario-card" aria-labelledby="tituloHorario" data-abierta="<?= $horario_ahora['abierta'] ? '1' : '0' ?>">
                <div class="horario-card__estado">
                    <span class="horario-card__icono" aria-hidden="true"><i class="ri-time-line"></i></span>
                    <div>
                        <h2 id="tituloHorario"><?= htmlspecialchars($horario_ahora['texto']) ?></h2>
                        <p><?= htmlspecialchars($horario_ahora['detalle']) ?></p>
                    </div>
                </div>
                <details class="horario-card__semana">
                    <summary>Horario de la semana <i class="ri-arrow-down-s-line" aria-hidden="true"></i></summary>
                    <dl>
                        <?php foreach ($horario_semana as $n => $dia): ?>
                            <div class="horario-card__dia<?= $n === $horario_ahora['hoy'] ? ' is-hoy' : '' ?><?= !$dia['franjas'] ? ' is-cerrado' : '' ?>">
                                <dt><?= htmlspecialchars($dia['dia']) ?></dt>
                                <dd><?= htmlspecialchars(HorarioAtencion::texto($dia['franjas'])) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </details>
            </section>
        </div>
    </div>
</section>

<!-- ══ SERVICIOS ════════════════════════════════════════════════════ -->
<section id="screen-explore" class="app-screen" aria-labelledby="tituloServicios">
    <div class="section-title-row">
        <h1 id="tituloServicios">Nuestros servicios</h1>
    </div>
    <div class="search-container">
        <label class="search-input-wrapper">
            <i class="ri-search-line" aria-hidden="true"></i>
            <input type="search" placeholder="Buscar servicios o especialidades..." aria-label="Buscar servicios" oninput="filtrarServicios(this.value)">
        </label>
    </div>

    <div class="services-list-vertical" id="servicesVerticalList">
        <?php foreach ([
            ['consulta clinica general medica', 'ri-heart-pulse-line', '', 'Consulta clínica', 'Revisión médica general'],
            ['vacunacion inmunizacion dosis', 'ri-syringe-line', 'service-row-icon--vacuna', 'Vacunación', 'Inmunizaciones obligatorias'],
            ['desparasitacion interna externa', 'ri-capsule-line', 'service-row-icon--control', 'Desparasitación', 'Control interno y externo'],
        ] as [$clave, $icono, $variante, $titulo, $detalle]): ?>
            <div class="service-row-item" data-name="<?= $clave ?>">
                <div class="service-row-meta">
                    <span class="service-row-icon <?= $variante ?>"><i class="<?= $icono ?>" aria-hidden="true"></i></span>
                    <div class="service-row-details">
                        <h2><?= $titulo ?></h2>
                        <p><?= $detalle ?></p>
                    </div>
                </div>
                <button type="button" class="btn-service-action" data-agendar>Agendar</button>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section-title-row">
        <h2>Veterinarios activos</h2>
    </div>
    <div class="vets-scroll-row" id="exploreVetsList">
        <div class="notification-card-empty">Cargando veterinarios...</div>
    </div>
</section>

<!-- ══ AGENDA DE SALUD ══════════════════════════════════════════════ -->
<section id="screen-agenda" class="app-screen" aria-labelledby="tituloAgenda">
    <div class="section-title-row">
        <h1 id="tituloAgenda">Mi agenda de salud</h1>
    </div>

    <div class="pet-filter-chips-wrapper" role="group" aria-label="Filtrar por mascota">
        <button type="button" class="pet-chip active" data-pet-id="all" aria-pressed="true"><span>Todas</span></button>
        <?php foreach ($mascotas as $m): $foto = $fotoMascota($m); ?>
            <button type="button" class="pet-chip pet-chip--foto" data-pet-id="<?= (int) $m['id_mascota'] ?>" aria-pressed="false">
                <span class="pet-chip-photo">
                    <?php if ($foto): ?>
                        <img src="<?= $foto ?>" alt="">
                    <?php else: ?>
                        <i class="fas fa-dog" aria-hidden="true"></i>
                    <?php endif; ?>
                </span>
                <span><?= htmlspecialchars($m['nombre']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="agenda-card">
        <!-- En móvil y tablet son pestañas; en escritorio las dos columnas se ven a la vez. -->
        <div class="agenda-tabs" role="tablist">
            <button type="button" class="agenda-tab-btn active" id="btn-agenda-citas" role="tab" aria-selected="true" aria-controls="agenda-citas">
                <i class="ri-calendar-event-line" aria-hidden="true"></i> Mis citas
            </button>
            <button type="button" class="agenda-tab-btn" id="btn-agenda-salud" role="tab" aria-selected="false" aria-controls="agenda-salud">
                <i class="ri-heart-line" aria-hidden="true"></i> Calendario de salud
            </button>
        </div>

        <div id="agenda-citas" class="agenda-tab-content active" role="tabpanel" aria-labelledby="btn-agenda-citas">
            <h2 class="agenda-col-title"><i class="ri-calendar-event-line" aria-hidden="true"></i> Mis citas</h2>
            <?php if (empty($todas_citas)): ?>
                <div class="agenda-empty-state">
                    <i class="ri-calendar-line" aria-hidden="true"></i>
                    <p>No tienes citas agendadas.</p>
                </div>
            <?php else: ?>
                <div class="agenda-list scrollable-list" id="citasListContainer">
                    <?php foreach ($todas_citas as $c): $abierta = in_array($c['estado'], ['pendiente', 'confirmada'], true); ?>
                        <div class="agenda-list-item agenda-list-item--accion" data-pet-id="<?= (int) $c['id_mascota'] ?>" role="button" tabindex="0" onclick="if(!event.target.classList.contains('btn-cancel-agenda')) mostrarDetalleCita(<?= (int) $c['id_cita'] ?>)">
                            <div class="agenda-pet-photo">
                                <?php if ($c['foto_mascota']): ?>
                                    <img src="<?= $c['foto_mascota'] ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-dog" aria-hidden="true"></i>
                                <?php endif; ?>
                            </div>
                            <div class="agenda-item-info">
                                <h3><?= htmlspecialchars($c['nombre_mascota']) ?></h3>
                                <p class="agenda-item-type"><?= htmlspecialchars($c['nombre_tipo'] ?? 'Consulta') ?> · <?= htmlspecialchars($c['nombre_completo'] ?? 'Veterinario') ?></p>
                                <span class="agenda-item-date"><?= date('d/m/Y', strtotime($c['fecha'])) ?> · <?= htmlspecialchars(ResumenPanel::hora($c['hora'])) ?></span>
                            </div>
                            <div class="agenda-item-actions">
                                <?php if ($abierta): ?>
                                    <span class="status-badge status-active">Activa</span>
                                    <button type="button" class="btn-cancel-agenda" data-id="<?= (int) $c['id_cita'] ?>">Cancelar</button>
                                <?php else: ?>
                                    <span class="status-badge status-inactive<?= $c['estado'] === 'completada' ? ' status-done' : '' ?>"><?= htmlspecialchars($etiquetasCita[$c['estado']] ?? ucfirst($c['estado'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="agenda-salud" class="agenda-tab-content" role="tabpanel" aria-labelledby="btn-agenda-salud">
            <h2 class="agenda-col-title"><i class="ri-heart-line" aria-hidden="true"></i> Calendario de salud</h2>
            <div class="mini-calendar-wrapper">
                <div class="mini-calendar-header">
                    <button type="button" class="btn-cal-prev" id="btnPrevMonth" aria-label="Mes anterior"><i class="ri-arrow-left-s-line" aria-hidden="true"></i></button>
                    <button type="button" id="calMonthTitle" class="mini-calendar-title" aria-label="Elegir mes y año">—</button>
                    <button type="button" class="btn-cal-next" id="btnNextMonth" aria-label="Mes siguiente"><i class="ri-arrow-right-s-line" aria-hidden="true"></i></button>
                </div>
                <div class="mini-calendar-days" aria-hidden="true">
                    <span>D</span><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span>
                </div>
                <div id="miniCalendarGrid" class="mini-calendar-grid"></div>

                <!-- Selector de mes y año -->
                <div id="calWin11Nav" class="fp-win11-nav mini-calendar-nav">
                    <div class="win11-header">
                        <button type="button" id="calWin11Title" class="win11-title mini-calendar-title">—</button>
                    </div>
                    <div class="win11-content">
                        <div id="calWin11MonthsGrid" class="win11-grid mini-calendar-nav__grid"></div>
                        <div id="calWin11YearsGrid" class="win11-grid win11-years mini-calendar-nav__grid"></div>
                    </div>
                </div>

                <div class="mini-calendar-legend">
                    <span><span class="cal-dot cal-dot--cita"></span> Citas</span>
                    <span><span class="cal-dot cal-dot--vacuna"></span> Vacunas</span>
                    <span><span class="cal-dot cal-dot--control"></span> Controles</span>
                </div>

                <div id="calendarFilterAlert" class="calendar-filter-alert">
                    <span>Filtrado por fecha seleccionada</span>
                    <button type="button" id="btnResetDateFilter" class="btn-enlace">Ver todos</button>
                </div>
            </div>

            <div id="agenda-salud-proximas" class="agenda-salud-bloque">
                <h3 class="agenda-salud-titulo agenda-salud-titulo--proximas">Próximas dosis / recordatorios</h3>
                <div class="agenda-list" id="saludProximasList"></div>
            </div>

            <div id="agenda-salud-historial" class="agenda-salud-bloque">
                <h3 class="agenda-salud-titulo">Historial clínico (aplicados)</h3>
                <div class="agenda-list" id="saludHistorialList"></div>
            </div>

            <a href="#" id="btnImprimirHistorial" class="btn-export-pdf">
                <i class="ri-printer-line" aria-hidden="true"></i> <span>Exportar ficha de salud en PDF</span>
            </a>
        </div>
    </div>
</section>

<script>
    window.portalAgendaEventos = <?php
        $eventos_js = [];
        foreach ((array) $todas_citas as $c) {
            $eventos_js[] = [
                'tipo' => 'cita',
                'id_cita' => $c['id_cita'],
                'id_mascota' => $c['id_mascota'],
                'nombre_mascota' => $c['nombre_mascota'],
                'foto_mascota' => $c['foto_mascota'],
                'titulo' => $c['nombre_tipo'] ?? 'Consulta',
                'detalle' => $c['nombre_completo'] ?? 'Veterinario',
                'fecha' => $c['fecha'],
                'hora' => substr($c['hora'], 0, 5),
                'estado' => $c['estado'],
                'proxima' => null
            ];
        }
        foreach ((array) $todas_vacunas as $vac) {
            $eventos_js[] = [
                'tipo' => 'vacuna',
                'id_mascota' => $vac['id_mascota'],
                'nombre_mascota' => $vac['nombre_mascota'],
                'foto_mascota' => $vac['foto_mascota'],
                'titulo' => $vac['nombre_vacuna'],
                // La tabla vacunas no tiene «dosis» ni «fecha_proxima» (esas son de
                // desparasitaciones): con esos nombres las vacunas nunca salían en
                // «Próximas dosis» y cada carga dejaba avisos de PHP en el log.
                'detalle' => !empty($vac['laboratorio']) ? 'Laboratorio: ' . $vac['laboratorio'] : 'Sin laboratorio registrado',
                'fecha' => $vac['fecha_aplicacion'],
                'hora' => null,
                'estado' => 'aplicada',
                'proxima' => (!empty($vac['fecha_proxima_dosis']) && $vac['fecha_proxima_dosis'] !== '0000-00-00') ? $vac['fecha_proxima_dosis'] : null
            ];
        }
        foreach ((array) $todas_desparasitaciones as $d) {
            $eventos_js[] = [
                'tipo' => 'control',
                'id_mascota' => $d['id_mascota'],
                'nombre_mascota' => $d['nombre_mascota'],
                'foto_mascota' => $d['foto_mascota'],
                'titulo' => $d['producto'],
                // Tampoco hay «dosis» en desparasitaciones: se muestran el tipo y la periodicidad.
                'detalle' => ucfirst((string) ($d['tipo'] ?? '')) . ' · ' . ($d['periodicidad'] ?? ''),
                'fecha' => $d['fecha_aplicacion'],
                'hora' => null,
                'estado' => 'aplicado',
                'proxima' => ($d['fecha_proxima'] !== '0000-00-00') ? $d['fecha_proxima'] : null
            ];
        }
        echo json_encode($eventos_js);
    ?>;
</script>

<!-- ══ RECORDATORIOS ════════════════════════════════════════════════ -->
<section id="screen-notifications" class="app-screen" aria-labelledby="tituloAlertas">
    <div class="section-title-row">
        <h1 id="tituloAlertas">Alertas de tus mascotas</h1>
    </div>
    <div class="notifications-list" id="portalAlertsList">
        <div class="notification-card-empty">
            <i class="ri-notification-off-line" aria-hidden="true"></i>
            <p>No tienes alertas médicas o recordatorios programados en este momento.</p>
        </div>
    </div>
</section>

<!-- ══ PERFIL ═══════════════════════════════════════════════════════ -->
<section id="screen-account" class="app-screen" aria-labelledby="tituloCuenta">
    <h1 class="sr-only" id="tituloCuenta">Mi perfil</h1>
    <div class="account-grid">
        <div class="account-grid__datos">
            <div class="profile-card">
                <div class="profile-avatar-large" aria-hidden="true"><?= htmlspecialchars($iniciales) ?></div>
                <h2><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Propietario') ?></h2>
                <p>Propietario Zooki</p>
            </div>

            <dl class="profile-details-list">
                <div class="profile-detail-row">
                    <dt class="profile-detail-label">Cédula</dt>
                    <dd class="profile-detail-value"><?= htmlspecialchars($_SESSION['usuario_doc'] ?? '—') ?></dd>
                </div>
                <div class="profile-detail-row">
                    <dt class="profile-detail-label">Correo</dt>
                    <dd class="profile-detail-value" id="profileEmailVal"><?= htmlspecialchars($usuarioData['email'] ?? 'No registrado') ?></dd>
                </div>
                <div class="profile-detail-row">
                    <dt class="profile-detail-label">Teléfono</dt>
                    <dd class="profile-detail-value" id="profilePhoneVal"><?= htmlspecialchars($usuarioData['telefono'] ?? 'No registrado') ?></dd>
                </div>
                <div class="profile-detail-row">
                    <dt class="profile-detail-label">Rol</dt>
                    <dd class="profile-detail-value">Cliente</dd>
                </div>
            </dl>
        </div>

        <div class="account-grid__acciones">
            <div class="profile-actions-list">
                <button type="button" class="btn-profile-action" onclick="toggleContactEditPortal()" aria-controls="contactEditSection">
                    <span><i class="ri-contacts-line" aria-hidden="true"></i> Editar datos de contacto</span>
                    <i class="ri-arrow-down-s-line" id="iconToggleContact" aria-hidden="true"></i>
                </button>

                <div id="contactEditSection" class="password-change-collapse">
                    <form id="portalContactEditForm" class="portal-form" onsubmit="event.preventDefault(); submitContactEditPortal();">
                        <div class="input-group">
                            <label class="portal-label" for="portal_contact_email">Correo electrónico *</label>
                            <div class="search-input-wrapper campo">
                                <i class="ri-mail-line" aria-hidden="true"></i>
                                <input type="email" name="email" id="portal_contact_email" required autocomplete="email" placeholder="ejemplo@correo.com" value="<?= htmlspecialchars($usuarioData['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="portal-label" for="portal_contact_phone">Teléfono de contacto *</label>
                            <div class="search-input-wrapper campo">
                                <i class="ri-phone-line" aria-hidden="true"></i>
                                <input type="tel" name="telefono" id="portal_contact_phone" required autocomplete="tel" placeholder="Tu número de teléfono" value="<?= htmlspecialchars($usuarioData['telefono'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary btn-primary--compacto" id="btnSubmitContactEdit">
                            <span>Guardar cambios</span>
                            <i class="ri-check-line" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="profile-actions-list">
                <button type="button" class="btn-profile-action" onclick="togglePasswordChangePortal()" aria-controls="passwordChangeSection">
                    <span><i class="ri-lock-password-line" aria-hidden="true"></i> Cambiar contraseña</span>
                    <i class="ri-arrow-down-s-line" id="iconTogglePassword" aria-hidden="true"></i>
                </button>

                <div id="passwordChangeSection" class="password-change-collapse">
                    <form id="portalChangePasswordForm" class="portal-form" onsubmit="event.preventDefault(); submitChangePasswordPortal();">
                        <?php // HU-39: se pide la actual si la cuenta tiene una contraseña conocida ?>
                        <?php if ((int) ($usuarioData['password_definida'] ?? 1) === 1): ?>
                        <div class="input-group">
                            <label class="portal-label" for="portal_current_password">Contraseña actual</label>
                            <div class="search-input-wrapper campo">
                                <input type="password" name="current_password" id="portal_current_password" required autocomplete="current-password" placeholder="Tu contraseña actual">
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="input-group">
                            <label class="portal-label" for="portal_new_password">Nueva contraseña</label>
                            <div class="search-input-wrapper campo">
                                <input type="password" name="new_password" id="portal_new_password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres" oninput="validarFuerzaPasswordPortal()" aria-describedby="portal_pwd_strength_text">
                            </div>
                            <div class="password-strength-meter" aria-hidden="true">
                                <div id="portal_pwd_strength_bar"></div>
                            </div>
                            <small id="portal_pwd_strength_text" class="password-strength-text">Mínimo 8 caracteres, una mayúscula y un número.</small>
                        </div>
                        <div class="input-group">
                            <label class="portal-label" for="portal_confirm_password">Confirmar nueva contraseña</label>
                            <div class="search-input-wrapper campo">
                                <input type="password" name="confirm_password" id="portal_confirm_password" required autocomplete="new-password" placeholder="Repite la nueva contraseña" oninput="validarFuerzaPasswordPortal()">
                            </div>
                            <small id="portal_pwd_match_text" class="password-match-text" role="alert">Las contraseñas no coinciden.</small>
                        </div>
                        <button type="submit" id="portal_btn_change_pwd" class="btn-primary" disabled>Actualizar contraseña</button>
                    </form>
                </div>

                <a href="index.php?action=logout" class="btn-profile-action logout-btn">
                    <span><i class="ri-logout-box-r-line" aria-hidden="true"></i> Cerrar sesión</span>
                    <i class="ri-arrow-right-s-line" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ══ DETALLE DE MASCOTA ═══════════════════════════════════════════ -->
<section id="screen-pet-detail" class="app-screen pet-detail" aria-labelledby="drawerPetTitle">
    <header class="pet-detail__cabecera">
        <button type="button" class="portal-drawer-back" onclick="cerrarDrawer()" aria-label="Volver">
            <i class="ri-arrow-left-line" aria-hidden="true"></i>
        </button>
        <div class="portal-drawer-title-wrap">
            <h1 id="drawerPetTitle" class="pet-detail__titulo">—</h1>
            <p id="drawerPetSubtitle" class="pet-detail__subtitulo"></p>
        </div>
        <button type="button" id="btnEditPetProfile" class="btn-icono btn-icono--suave" aria-label="Editar perfil de la mascota" title="Editar perfil de la mascota">
            <i class="ri-edit-line" aria-hidden="true"></i>
        </button>
    </header>

    <div class="pet-detail__layout">
        <aside id="drawerPetSummary" class="portal-drawer-summary pet-detail__ficha"></aside>

        <div class="pet-detail__contenido">
            <nav class="portal-drawer-tabs" role="tablist" aria-label="Información de la mascota">
                <button type="button" class="portal-tab active" data-tab="historial" role="tab" aria-selected="true" aria-controls="tab-historial">
                    <i class="ri-history-line" aria-hidden="true"></i> Historial
                </button>
                <button type="button" class="portal-tab" data-tab="citas" role="tab" aria-selected="false" aria-controls="tab-citas">
                    <i class="ri-calendar-line" aria-hidden="true"></i> Citas
                </button>
                <button type="button" class="portal-tab" data-tab="vacunas" role="tab" aria-selected="false" aria-controls="tab-vacunas">
                    <i class="ri-syringe-line" aria-hidden="true"></i> Vacunas
                </button>
                <button type="button" class="portal-tab" data-tab="desparasitaciones" role="tab" aria-selected="false" aria-controls="tab-desparasitaciones">
                    <i class="ri-capsule-line" aria-hidden="true"></i> <span class="texto-largo">Desparasitaciones</span><span class="texto-corto" aria-hidden="true">Desparasit.</span>
                </button>
            </nav>

            <div class="pet-detail__paneles">
                <div id="tab-historial" class="portal-tab-panel active" role="tabpanel">
                    <div id="historialContent"><div class="portal-loading">Cargando historial…</div></div>
                </div>
                <div id="tab-citas" class="portal-tab-panel" role="tabpanel">
                    <div id="citasContent"></div>
                </div>
                <div id="tab-vacunas" class="portal-tab-panel" role="tabpanel">
                    <div id="vacunasContent"></div>
                </div>
                <div id="tab-desparasitaciones" class="portal-tab-panel" role="tabpanel">
                    <div id="desparasitacionesContent"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
/**
 * Encabezado común de las ventanas: botón volver, ícono, título y ayuda.
 * En móvil son hojas que suben desde abajo; desde tablet, ventanas centradas.
 */
$cabeceraVentana = function (string $idCerrar, string $icono, string $titulo, string $ayuda, string $idTitulo): void { ?>
        <div class="portal-drawer-header">
            <button type="button" id="<?= $idCerrar ?>" class="portal-drawer-back" aria-label="Cerrar">
                <i class="ri-arrow-left-line" aria-hidden="true"></i>
            </button>
            <div class="portal-drawer-title-wrap">
                <h2 id="<?= $idTitulo ?>"><i class="<?= $icono ?>" aria-hidden="true"></i> <?= $titulo ?></h2>
                <p><?= $ayuda ?></p>
            </div>
        </div>
<?php };

/**
 * Campo con el borde y el ícono de los formularios del portal.
 * Los obligatorios llevan * y los demás «(opcional)», como en el panel del personal.
 */
$campo = function (string $etiqueta, string $control, string $icono = '', string $for = '', bool $obligatorio = true, string $ayuda = ''): void { ?>
                <div class="input-group">
                    <label class="portal-label"<?= $for ? " for=\"$for\"" : '' ?>><?= $etiqueta ?><?= $obligatorio ? ' <span class="portal-requerido" aria-hidden="true">*</span>' : ' <span class="portal-opcional">(opcional)</span>' ?></label>
                    <div class="search-input-wrapper campo">
                        <?php if ($icono): ?><i class="<?= $icono ?>" aria-hidden="true"></i><?php endif; ?>
                        <?= $control ?>
                    </div>
                    <?php if ($ayuda): ?><p class="portal-ayuda"<?= $for ? " id=\"{$for}_ayuda\"" : '' ?>><?= $ayuda ?></p><?php endif; ?>
                </div>
<?php };

$leyenda = '<p class="portal-form__leyenda"><span class="portal-requerido" aria-hidden="true">*</span> Obligatorio. Lo demás es opcional.</p>';

// Días en que la clínica no atiende en toda la jornada (1 = lunes … 7 = domingo).
$diasCerrados = array_keys(array_filter($horario_semana, fn ($d) => !$d['franjas']));
?>

<!-- Ventana: agendar cita. Mismo flujo que el panel del personal:
     datos a la izquierda; día y horarios libres a la derecha. -->
<div id="portalBookingModal" class="portal-drawer-overlay">
    <div class="portal-drawer portal-drawer--ancho" role="dialog" aria-modal="true" aria-labelledby="bookingTitulo">
        <?php $cabeceraVentana('btnCloseBookingModal', 'ri-calendar-event-line', 'Agendar nueva cita', 'Elige a tu compañero, el día y uno de los horarios libres.', 'bookingTitulo'); ?>
        <div class="portal-drawer-body">
            <form id="portalBookingForm" class="portal-form booking" novalidate data-dias-cerrados="<?= htmlspecialchars(json_encode(array_values($diasCerrados))) ?>">
                <?= $leyenda ?>
                <div class="booking__columnas">
                    <div class="booking__datos">
                        <?php
                        $opciones = '<option value="">Selecciona…</option>';
                        foreach ($mascotas as $m) {
                            $opciones .= '<option value="' . (int) $m['id_mascota'] . '">' . htmlspecialchars($m['nombre']) . '</option>';
                        }
                        $campo('Mascota', '<select name="id_mascota" id="booking_mascota" required>' . $opciones . '</select>', 'ri-baidu-line', 'booking_mascota');
                        $campo('Tipo de cita', '<select name="id_tipo_cita" id="booking_tipo_cita" required><option value="">Cargando…</option></select>', 'ri-briefcase-line', 'booking_tipo_cita');
                        $campo('Veterinario', '<select name="doc_veterinario" id="booking_veterinario" required><option value="">Cargando…</option></select>', 'ri-stethoscope-line', 'booking_veterinario');
                        $campo('Motivo', '<textarea name="motivo" id="booking_motivo" maxlength="255" placeholder="Ej: control de peso, vómito desde ayer…" rows="3"></textarea>', '', 'booking_motivo', false);
                        ?>
                    </div>

                    <div class="booking__agenda">
                        <div class="input-group">
                            <span class="portal-label" id="booking_dia_etq">Día <span class="portal-requerido" aria-hidden="true">*</span></span>
                            <input type="text" name="fecha" id="booking_fecha" class="booking__fecha" aria-labelledby="booking_dia_etq" readonly tabindex="-1">
                            <?php if ($diasCerrados): ?>
                                <p class="portal-ayuda">Los días en gris no se pueden elegir: ya pasaron o la clínica no atiende.</p>
                            <?php endif; ?>
                        </div>

                        <div class="input-group">
                            <span class="portal-label" id="booking_hora_etq">Horario disponible <span class="portal-requerido" aria-hidden="true">*</span></span>
                            <input type="hidden" name="hora" id="booking_hora">
                            <div class="slot-panel" id="booking_slots" role="group" aria-labelledby="booking_hora_etq" aria-live="polite"></div>
                        </div>
                    </div>
                </div>

                <div class="booking__pie">
                    <p class="booking__resumen" id="booking_resumen" aria-live="polite"></p>
                    <button type="submit" class="btn-primary" id="booking_confirmar" disabled>
                        <span>Confirmar cita</span>
                        <i class="ri-calendar-check-line" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
/** Formulario de mascota: el mismo para registrar y para editar. */
$formularioMascota = function (string $p, bool $editar) use ($campo, $leyenda, $catalogo_especies): void {
    // Los ids edit_pet_* los usa portal.js para rellenar la edición.
    // El color no se pide: lo registra la clínica en la consulta, viendo a la mascota.
    $especies = '<option value="">Selecciona…</option>';
    foreach ($catalogo_especies as $e) {
        $especies .= '<option value="' . (int) $e['id_especie'] . '">' . htmlspecialchars($e['nombre_especie']) . '</option>';
    }
    echo $leyenda;
    ?>
                <div class="portal-form__fila">
                    <?php
                    $campo('Nombre', "<input type=\"text\" name=\"nombre\" id=\"{$p}_pet_nombre\" required maxlength=\"50\" pattern=\"[\\p{L}\\p{N}][\\p{L}\\p{N} .'\\-]*\" title=\"Letras, números, espacios, puntos, guiones y apóstrofos.\" placeholder=\"Ej: Toby\" autocomplete=\"off\">", '', "{$p}_pet_nombre");
                    $campo('Fecha de nacimiento', "<input type=\"text\" class=\"flatpickr-date\" name=\"fecha_nacimiento\" id=\"{$p}_pet_nacimiento\" placeholder=\"Selecciona la fecha\">", 'ri-calendar-line', "{$p}_pet_nacimiento", false);
                    ?>
                </div>
                <div class="portal-form__fila">
                    <?php
                    $campo('Especie', "<select name=\"especie\" id=\"{$p}_pet_especie\" required>$especies</select>", '', "{$p}_pet_especie");
                    $campo('Raza', "<select name=\"raza\" id=\"{$p}_pet_raza\" required aria-describedby=\"{$p}_pet_raza_ayuda\" disabled><option value=\"\">Primero elige la especie</option></select>", '', "{$p}_pet_raza");
                    ?>
                </div>
                <?php // portal.js la muestra cuando carga las razas de la especie. ?>
                <p class="portal-ayuda portal-ayuda--fila" id="<?= $p ?>_pet_raza_ayuda" hidden></p>
                <?php // «Mi raza no está en la lista»: no crea la raza, la deja para que la clínica la revise (RE-15.9). ?>
                <div class="input-group raza-otra" id="<?= $p ?>_raza_otra" hidden>
                    <label class="portal-label" for="<?= $p ?>_pet_raza_indicada">¿Cuál es la raza? <span class="portal-requerido" aria-hidden="true">*</span></label>
                    <div class="search-input-wrapper campo">
                        <input type="text" name="raza_indicada" id="<?= $p ?>_pet_raza_indicada" maxlength="50" pattern="[\p{L}][\p{L}\p{N} .'\(\)\-]*" title="Letras, números, espacios, puntos, guiones, apóstrofos y paréntesis." placeholder="Ej: Pomerania" autocomplete="off" disabled>
                    </div>
                    <p class="portal-ayuda">La clínica la revisa y la agrega a la lista. Mientras tanto se ve como «por confirmar».</p>
                </div>
                <div class="portal-form__fila">
                    <fieldset class="input-group segmento-campo">
                        <legend class="portal-label">Sexo <span class="portal-requerido" aria-hidden="true">*</span></legend>
                        <div class="segmento">
                            <label class="segmento__opcion">
                                <input type="radio" name="sexo" value="Macho" id="<?= $p ?>_pet_sexo" required>
                                <span><i class="ri-men-line" aria-hidden="true"></i> Macho</span>
                            </label>
                            <label class="segmento__opcion">
                                <input type="radio" name="sexo" value="Hembra" required>
                                <span><i class="ri-women-line" aria-hidden="true"></i> Hembra</span>
                            </label>
                        </div>
                    </fieldset>
                    <?php $campo('Peso (kg)', "<input type=\"text\" inputmode=\"decimal\" name=\"peso\" id=\"{$p}_pet_peso\" required maxlength=\"6\" pattern=\"\\d{1,3}([.,]\\d{1,2})?\" title=\"Un número mayor que 0, por ejemplo 8,5.\" placeholder=\"Ej: 8,5\">", '', "{$p}_pet_peso"); ?>
                </div>

                <div class="input-group">
                    <span class="portal-label" id="<?= $p ?>_foto_etq">Foto de perfil <span class="portal-opcional">(opcional)</span></span>
                    <div class="foto-campo" data-foto="<?= $p ?>">
                        <div class="foto-campo__vista" aria-hidden="true"><i class="ri-image-line"></i></div>
                        <div class="foto-campo__acciones">
                            <input type="file" name="foto" id="<?= $p ?>_pet_foto" accept="image/jpeg,image/png" class="foto-campo__input" aria-labelledby="<?= $p ?>_foto_etq" aria-describedby="<?= $p ?>_foto_ayuda">
                            <label class="foto-campo__boton" for="<?= $p ?>_pet_foto"><i class="ri-upload-2-line" aria-hidden="true"></i> <span><?= $editar ? 'Cambiar foto' : 'Elegir foto' ?></span></label>
                            <button type="button" class="btn-enlace foto-campo__quitar" hidden>Quitar la foto elegida</button>
                            <p class="portal-ayuda foto-campo__ayuda" id="<?= $p ?>_foto_ayuda">JPG o PNG, hasta 5 MB.</p>
                        </div>
                    </div>
                </div>
<?php }; ?>

<!-- Ventana: registrar mascota -->
<div id="portalAddPetModal" class="portal-drawer-overlay">
    <div class="portal-drawer" role="dialog" aria-modal="true" aria-labelledby="addPetTitulo">
        <?php $cabeceraVentana('btnCloseAddPetModal', 'ri-baidu-line', 'Registrar mascota', 'Agrega un nuevo compañero a tu cuenta.', 'addPetTitulo'); ?>
        <div class="portal-drawer-body">
            <form id="portalAddPetForm" class="portal-form" enctype="multipart/form-data">
                <?php $formularioMascota('add', false); ?>
                <button type="submit" class="btn-primary">
                    <span>Registrar mascota</span>
                    <i class="ri-check-line" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Ventana: editar mascota -->
<div id="portalEditPetModal" class="portal-drawer-overlay">
    <div class="portal-drawer" role="dialog" aria-modal="true" aria-labelledby="editPetTitulo">
        <?php $cabeceraVentana('btnCloseEditPetModal', 'ri-edit-line', 'Editar mascota', 'Personaliza y actualiza la información de tu mascota.', 'editPetTitulo'); ?>
        <div class="portal-drawer-body">
            <form id="portalEditPetForm" class="portal-form" enctype="multipart/form-data">
                <input type="hidden" name="id_mascota" id="edit_pet_id">
                <?php $formularioMascota('edit', true); ?>
                <button type="submit" class="btn-primary">
                    <span>Guardar cambios</span>
                    <i class="ri-check-line" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Ventana: detalle de la cita y ficha clínica -->
<div id="portalCitaDetalleModal" class="portal-drawer-overlay">
    <div class="portal-drawer" role="dialog" aria-modal="true" aria-labelledby="citaDetalleTitulo">
        <?php $cabeceraVentana('btnCloseCitaDetalleModal', 'ri-heart-pulse-fill', 'Detalle de la cita', 'Información y resultado clínico de la consulta.', 'citaDetalleTitulo'); ?>
        <div class="portal-drawer-body">
            <div class="cita-info">
                <div class="cita-info__fila">
                    <span class="cita-info__tipo" id="detCitaTipo">—</span>
                    <span class="status-badge" id="detCitaEstado">—</span>
                </div>
                <div class="cita-info__mascota">Mascota: <span id="detCitaMascota">—</span></div>
                <div class="cita-info__vet">Veterinario: <span id="detCitaVet">—</span></div>
                <div class="cita-info__fecha"><i class="ri-calendar-line" aria-hidden="true"></i> <span id="detCitaFechaHora">—</span></div>
            </div>

            <div id="detCitaClinicaArea" class="cita-clinica">
                <h3 class="cita-clinica__titulo"><i class="ri-folder-shield-2-line cita-clinica__icono--ok" aria-hidden="true"></i> Resultado clínico</h3>

                <div class="cita-signos">
                    <div class="cita-signo"><span>Peso</span><strong id="detClinicaPeso">—</strong></div>
                    <div class="cita-signo"><span>Temp</span><strong id="detClinicaTemp">—</strong></div>
                    <div class="cita-signo"><span>F. cardiaca</span><strong id="detClinicaFc">—</strong></div>
                </div>

                <div class="cita-campos">
                    <div><span class="cita-campo__etiqueta">Motivo de consulta</span><p id="detClinicaMotivo" class="cita-campo"></p></div>
                    <div><span class="cita-campo__etiqueta">Anamnesis / observaciones</span><p id="detClinicaAnamnesis" class="cita-campo"></p></div>
                    <div><span class="cita-campo__etiqueta">Diagnóstico</span><p id="detClinicaDiagnostico" class="cita-campo cita-campo--diagnostico"></p></div>
                    <div><span class="cita-campo__etiqueta">Plan de tratamiento</span><p id="detClinicaPlan" class="cita-campo"></p></div>
                </div>

                <h3 class="cita-clinica__titulo"><i class="ri-capsule-fill" aria-hidden="true"></i> Receta de medicamentos</h3>
                <div id="detClinicaTratamientosList" class="cita-tratamientos"></div>
            </div>

            <div id="detCitaSinClinica" class="cita-sin-clinica">
                <i class="ri-health-book-line" id="detSinClinicaIcon" aria-hidden="true"></i>
                <p class="cita-sin-clinica__titulo" id="detSinClinicaTitle">Esta cita aún no ha sido atendida.</p>
                <p class="cita-sin-clinica__texto" id="detSinClinicaDesc">Cuando el veterinario finalice la consulta, aquí podrás visualizar la historia clínica, diagnóstico y medicamentos recetados.</p>
            </div>
        </div>
    </div>
</div>
