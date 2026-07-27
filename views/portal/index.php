<?php
$total_mascotas = count($mascotas);

// Encontrar la cita más próxima de cualquiera de las mascotas
$earliest_cita = null;
$cita_pet_name = '';
$cita_pet_foto = '';
foreach ($mascotas as $m) {
    if (!empty($m['proxima_cita'])) {
        $c = $m['proxima_cita'];
        if ($earliest_cita === null || strtotime($c['fecha'] . ' ' . $c['hora']) < strtotime($earliest_cita['fecha'] . ' ' . $earliest_cita['hora'])) {
            $earliest_cita = $c;
            $cita_pet_name = $m['nombre'];
            $cita_pet_foto = $m['url_foto'] ? 'uploads/mascotas/' . htmlspecialchars($m['url_foto']) : null;
        }
    }
}
?>

<!-- ══ SCREEN: INICIO (HOME) ════════════════════════════════════════ -->
<div id="screen-home" class="app-screen active">
    <!-- Header de usuario -->
    <header class="home-header">
        <div class="user-profile-summary">
            <div class="user-avatar">
                <?php 
                    $iniciales = '';
                    $nombres = explode(' ', trim($_SESSION['usuario_nombre'] ?? ''));
                    foreach (array_slice($nombres, 0, 2) as $n) {
                        $iniciales .= strtoupper(substr($n, 0, 1));
                    }
                    echo htmlspecialchars($iniciales ?: 'U');
                ?>
            </div>
            <div class="user-info-text">
                <span>Hola, bienvenido 👋</span>
                <h3><?php echo htmlspecialchars($primer_nombre); ?></h3>
            </div>
        </div>
        <div class="header-actions">
            <button class="bell-btn" id="btnNotificationBell" onclick="switchTab('notifications')" title="Recordatorios">
                <i class="ri-notification-3-line"></i>
                <span class="bell-badge" id="bellBadgeAlert" style="display: none;"></span>
            </button>
        </div>
    </header>

    <!-- Carrusel horizontal de Mascotas (Patient History) -->
    <div class="section-title-row">
        <h2>Mis Compañeros</h2>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <button id="btnOpenAddPetModal" type="button" style="background: var(--z-primary-soft); color: var(--z-primary); border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.15rem; box-shadow: 0 2px 6px rgba(0,0,0,0.05);" title="Registrar Nueva Mascota">
                <i class="ri-add-line"></i>
            </button>
            <span class="see-all"><?php echo $total_mascotas; ?> en total</span>
        </div>
    </div>

    <?php if (empty($mascotas)): ?>
        <div class="appointment-banner-empty">
            <i class="ri-ghost-line"></i>
            <p>No tienes mascotas registradas aún.</p>
        </div>
    <?php else: ?>
        <div class="pet-carousel">
            <?php foreach ($mascotas as $m): ?>
                <?php $foto = $m['url_foto'] ? 'uploads/mascotas/' . htmlspecialchars($m['url_foto']) : null; ?>
                <div class="pet-carousel-item" onclick="verDetalle(<?php echo (int)$m['id_mascota']; ?>)">
                    <div class="pet-avatar-wrapper">
                        <?php if ($foto): ?>
                            <img src="<?php echo $foto; ?>" alt="<?php echo htmlspecialchars($m['nombre']); ?>" class="pet-avatar-img">
                        <?php else: ?>
                            <div class="pet-avatar-placeholder">
                                <i class="fas fa-dog"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="pet-carousel-name"><?php echo htmlspecialchars($m['nombre']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Tarjeta Próxima Cita (Today Patient) -->
    <div class="section-title-row">
        <h2>Próxima Cita</h2>
    </div>

    <?php if ($earliest_cita): ?>
        <article class="appointment-banner">
            <div class="app-banner-info">
                <?php if ($cita_pet_foto): ?>
                    <img src="<?php echo $cita_pet_foto; ?>" alt="Mascota" class="app-banner-avatar">
                <?php else: ?>
                    <div class="app-banner-avatar" style="background: var(--z-primary-soft); color: var(--z-primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;"><i class="fas fa-dog"></i></div>
                <?php endif; ?>
                <div class="app-banner-details">
                    <h4><?php echo htmlspecialchars($cita_pet_name); ?></h4>
                    <p><?php echo htmlspecialchars($earliest_cita['motivo']); ?></p>
                </div>
            </div>
            <div class="app-banner-schedule">
                <span class="schedule-badge">Confirmada</span>
                <span class="schedule-time"><?php echo date('d/m/Y', strtotime($earliest_cita['fecha'])); ?> · <?php echo substr($earliest_cita['hora'], 0, 5); ?></span>
            </div>
        </article>
    <?php else: ?>
        <div class="appointment-banner-empty">
            <i class="ri-calendar-line"></i>
            <p>No tienes citas programadas próximamente.</p>
        </div>
    <?php endif; ?>

    <!-- Accesos rápidos a servicios -->
    <div class="section-title-row">
        <h2>Accesos Rápidos</h2>
    </div>
    <div class="home-services-grid">
        <a href="#" class="home-service-card" onclick="event.preventDefault(); document.getElementById('btnOpenBookingModal').click();">
            <div class="service-card-icon deworming"><i class="ri-calendar-todo-line"></i></div>
            <h3>Agendar Cita</h3>
            <p>Elige tu veterinario</p>
        </a>
        <a href="#" class="home-service-card" onclick="event.preventDefault(); switchTab('explore');">
            <div class="service-card-icon consultation"><i class="ri-search-eye-line"></i></div>
            <h3>Ver Servicios</h3>
            <p>Consultas y más</p>
        </a>
        <a href="#" class="home-service-card" onclick="event.preventDefault(); switchTab('notifications');">
            <div class="service-card-icon vaccines"><i class="ri-notification-badge-line"></i></div>
            <h3>Recordatorios</h3>
            <p>Vacunas y control</p>
        </a>
        <a href="#" class="home-service-card" onclick="event.preventDefault(); switchTab('account');">
            <div class="service-card-icon history"><i class="ri-user-settings-line"></i></div>
            <h3>Mi Cuenta</h3>
            <p>Editar perfil</p>
        </a>
    </div>

    <!-- Banner Informativo -->
    <div class="clinic-ad-banner">
        <div class="clinic-ad-text">
            <h4>Tu tranquilidad es Zooki</h4>
            <p>Médicos calificados las 24 horas del día.</p>
        </div>
        <div class="clinic-ad-icon">
            <i class="ri-shield-cross-line"></i>
        </div>
    </div>
</div>

<!-- ══ SCREEN: EXPLORAR SERVICIOS (EXPLORE) ═════════════════════════ -->
<div id="screen-explore" class="app-screen">
    <div class="section-title-row">
        <h2>Nuestros Servicios</h2>
    </div>
    <div class="search-container">
        <div class="search-input-wrapper">
            <i class="ri-search-line"></i>
            <input type="text" placeholder="Buscar servicios o especialidades..." oninput="filtrarServicios(this.value)">
        </div>
    </div>

    <div class="services-list-vertical" id="servicesVerticalList">
        <div class="service-row-item" data-name="consulta clinica general medica">
            <div class="service-row-meta">
                <div class="service-row-icon"><i class="ri-heart-pulse-line"></i></div>
                <div class="service-row-details">
                    <h4>Consulta Clínica</h4>
                    <p>Revisión médica general</p>
                </div>
            </div>
            <button class="btn-service-action" onclick="document.getElementById('btnOpenBookingModal').click()">Agendar</button>
        </div>
        <div class="service-row-item" data-name="vacunacion inmunizacion dosis">
            <div class="service-row-meta">
                <div class="service-row-icon" style="background-color: var(--z-success-soft); color: var(--z-success);"><i class="ri-syringe-line"></i></div>
                <div class="service-row-details">
                    <h4>Vacunación</h4>
                    <p>Inmunizaciones obligatorias</p>
                </div>
            </div>
            <button class="btn-service-action" onclick="document.getElementById('btnOpenBookingModal').click()">Agendar</button>
        </div>
        <div class="service-row-item" data-name="desparasitacion interna externa">
            <div class="service-row-meta">
                <div class="service-row-icon" style="background-color: var(--z-warning-soft); color: var(--z-warning);"><i class="ri-capsule-line"></i></div>
                <div class="service-row-details">
                    <h4>Desparasitación</h4>
                    <p>Control interno y externo</p>
                </div>
            </div>
            <button class="btn-service-action" onclick="document.getElementById('btnOpenBookingModal').click()">Agendar</button>
        </div>
    </div>

    <!-- Carrusel de Veterinarios -->
    <div class="section-title-row">
        <h2>Veterinarios Activos</h2>
    </div>
    <div class="vets-scroll-row" id="exploreVetsList">
        <div class="notification-card-empty">Cargando veterinarios...</div>
    </div>
</div>

<!-- ══ SCREEN: AGENDA DE SALUD (AGENDA) ═════════════════════════════ -->
<div id="screen-agenda" class="app-screen">
    <div class="section-title-row">
        <h2>Mi Agenda de Salud</h2>
    </div>

    <!-- Selector rápido de mascotas (Pet Chips) -->
    <div class="pet-filter-chips-wrapper" style="margin-bottom: 1.25rem; overflow-x: auto; white-space: nowrap; padding: 4px 4px 8px; -webkit-overflow-scrolling: touch; display: flex; gap: 0.65rem;">
        <button type="button" class="pet-chip active" data-pet-id="all" style="border: none; background: #e2e8f0; color: #475569; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;">
            <span>Todas</span>
        </button>
        <?php foreach ($mascotas as $m): ?>
            <button type="button" class="pet-chip" data-pet-id="<?php echo (int)$m['id_mascota']; ?>" style="border: none; background: #f1f5f9; color: #64748b; padding: 0.45rem 1rem 0.45rem 0.45rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.45rem;">
                <div class="pet-chip-photo" style="width: 24px; height: 24px; border-radius: 50%; overflow: hidden; background: #cbd5e1; display: flex; align-items: center; justify-content: center;">
                    <?php if ($m['url_foto']): ?>
                        <img src="uploads/mascotas/<?php echo htmlspecialchars($m['url_foto']); ?>" alt="<?php echo htmlspecialchars($m['nombre']); ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <i class="fas fa-dog" style="font-size:0.75rem; color:#fff;"></i>
                    <?php endif; ?>
                </div>
                <span><?php echo htmlspecialchars($m['nombre']); ?></span>
            </button>
        <?php endforeach; ?>
    </div>
    
    <div class="agenda-card">
        <!-- Toggles de Agenda -->
        <div class="agenda-tabs">
            <button type="button" class="agenda-tab-btn active" id="btn-agenda-citas">
                <i class="ri-calendar-event-line"></i> Mis Citas
            </button>
            <button type="button" class="agenda-tab-btn" id="btn-agenda-salud">
                <i class="ri-heart-line"></i> Calendario de Salud
            </button>
        </div>

        <!-- Contenedor 1: Mis Citas -->
        <div id="agenda-citas" class="agenda-tab-content active">
            <?php if (empty($todas_citas)): ?>
                <div class="agenda-empty-state">
                    <i class="ri-calendar-line"></i>
                    <p>No tienes citas agendadas.</p>
                </div>
            <?php else: ?>
                <div class="agenda-list scrollable-list" id="citasListContainer">
                    <?php foreach ($todas_citas as $c): ?>
                        <div class="agenda-list-item" data-pet-id="<?php echo (int)$c['id_mascota']; ?>" style="cursor: pointer;" onclick="if(!event.target.classList.contains('btn-cancel-agenda')) mostrarDetalleCita(<?php echo (int)$c['id_cita']; ?>)">
                            <div class="agenda-pet-photo">
                                <?php if ($c['foto_mascota']): ?>
                                    <img src="<?php echo $c['foto_mascota']; ?>" alt="<?php echo htmlspecialchars($c['nombre_mascota']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-dog"></i>
                                <?php endif; ?>
                            </div>
                            <div class="agenda-item-info">
                                <h4><?php echo htmlspecialchars($c['nombre_mascota']); ?></h4>
                                <p class="agenda-item-type"><?php echo htmlspecialchars($c['nombre_tipo'] ?? 'Consulta'); ?> · <?php echo htmlspecialchars($c['nombre_completo'] ?? 'Veterinario'); ?></p>
                                <span class="agenda-item-date"><?php echo date('d/m/Y', strtotime($c['fecha'])); ?> · <?php echo substr($c['hora'], 0, 5); ?></span>
                            </div>
                            <div class="agenda-item-actions">
                                <?php if ($c['estado'] === 'programada'): ?>
                                    <span class="status-badge status-active">Activa</span>
                                    <button type="button" class="btn-cancel-agenda" data-id="<?php echo (int)$c['id_cita']; ?>">Cancelar</button>
                                <?php else: ?>
                                    <span class="status-badge status-inactive" style="<?php echo $c['estado'] === 'completada' ? 'background:var(--z-success-soft);color:var(--z-success);' : ''; ?>"><?php echo htmlspecialchars($c['estado']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Contenedor 2: Calendario de Salud -->
        <div id="agenda-salud" class="agenda-tab-content">
            <!-- Cuadrícula Mensual del Calendario -->
            <div class="mini-calendar-wrapper" style="margin-bottom: 1.25rem; border: 1px solid var(--z-border); padding: 1rem; border-radius: 16px; background: #fff; position: relative; overflow: hidden;">
                <div class="mini-calendar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <button type="button" class="btn-cal-prev" id="btnPrevMonth" style="background: none; border: none; font-size: 1.1rem; color: var(--z-primary); cursor: pointer; padding: 4px;"><i class="ri-arrow-left-s-line"></i></button>
                    <button type="button" id="calMonthTitle" style="border: none; background: transparent; font-size: 0.88rem; font-weight: 800; color: #0f172a; cursor: pointer; padding: 4px; font-family: inherit; border-radius: 8px; transition: background 0.2s;">—</button>
                    <button type="button" class="btn-cal-next" id="btnNextMonth" style="background: none; border: none; font-size: 1.1rem; color: var(--z-primary); cursor: pointer; padding: 4px;"><i class="ri-arrow-right-s-line"></i></button>
                </div>
                <div class="mini-calendar-days" style="display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-size: 0.65rem; font-weight: 700; color: var(--z-text-muted); margin-bottom: 0.35rem;">
                    <span>D</span><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span>
                </div>
                <div id="miniCalendarGrid" class="mini-calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px;">
                    <!-- Se carga dinámicamente -->
                </div>

                <!-- Panel de selección de mes/año estilo Win11 (Overlay) -->
                <div id="calWin11Nav" class="fp-win11-nav" style="display: none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #ffffff; z-index: 10; padding: 1rem; flex-direction: column; border-radius: 16px;">
                    <div class="win11-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-shrink: 0;">
                        <button type="button" id="calWin11Title" class="win11-title" style="border: none; background: transparent; font-size: 0.88rem; font-weight: 800; color: #0f172a; cursor: pointer; font-family: inherit; padding: 4px; border-radius: 8px;">2026</button>
                    </div>
                    <div class="win11-content" style="flex: 1; overflow-y: auto;">
                        <div id="calWin11MonthsGrid" class="win11-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px;">
                            <!-- Meses Ene-Dic -->
                        </div>
                        <div id="calWin11YearsGrid" class="win11-grid win11-years" style="display: none; grid-template-columns: repeat(3, 1fr); gap: 6px;">
                            <!-- Años -->
                        </div>
                    </div>
                </div>

                <!-- Leyenda de colores del Calendario -->
                <div class="mini-calendar-legend" style="display: flex; justify-content: center; gap: 0.85rem; margin-top: 0.75rem; padding-top: 0.65rem; border-top: 1px dashed var(--z-border); font-size: 0.68rem; font-weight: 700; color: var(--z-text-muted);">
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem;"><span style="width: 6px; height: 6px; border-radius: 50%; background: var(--z-primary); display: inline-block;"></span> Citas</span>
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem;"><span style="width: 6px; height: 6px; border-radius: 50%; background: var(--z-success); display: inline-block;"></span> Vacunas</span>
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem;"><span style="width: 6px; height: 6px; border-radius: 50%; background: var(--z-warning); display: inline-block;"></span> Controles</span>
                </div>
                
                <div id="calendarFilterAlert" style="display: none; background: var(--z-primary-soft); padding: 0.5rem 0.75rem; border-radius: 10px; font-size: 0.75rem; color: var(--z-primary); font-weight: 700; margin-top: 0.75rem; align-items: center; justify-content: space-between;">
                    <span>Filtrado por fecha seleccionada</span>
                    <button type="button" id="btnResetDateFilter" style="background: none; border: none; color: var(--z-primary); font-weight: 800; cursor: pointer; text-decoration: underline; padding: 0; font-family: inherit;">Ver todos</button>
                </div>
            </div>

            <!-- Bloque 1: Próximas Dosis / Alertas Preventivas -->
            <div id="agenda-salud-proximas" style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.75rem; font-weight: 800; color: var(--z-primary); text-transform: uppercase; margin: 0 0 0.65rem; letter-spacing: 0.5px;">Próximas Dosis / Recordatorios</h4>
                <div class="agenda-list" id="saludProximasList">
                    <!-- Se renderiza por JS -->
                </div>
            </div>

            <!-- Bloque 2: Historial Clínico (Completados) -->
            <div id="agenda-salud-historial">
                <h4 style="font-size: 0.75rem; font-weight: 800; color: #475569; text-transform: uppercase; margin: 0 0 0.65rem; letter-spacing: 0.5px;">Historial Clínico (Aplicados)</h4>
                <div class="agenda-list" id="saludHistorialList">
                    <!-- Se renderiza por JS -->
                </div>
            </div>

            <!-- Botón Exportar PDF -->
            <a href="#" id="btnImprimirHistorial" class="btn-export-pdf" style="display: none; justify-content: center; align-items: center; gap: 0.5rem; text-decoration: none; padding: 0.85rem; border-radius: 14px; font-size: 0.82rem; font-weight: 700; color: #fff; background: linear-gradient(135deg, var(--z-primary) 0%, var(--z-primary-dark) 100%); margin-top: 1.5rem; text-align: center; box-shadow: 0 4px 12px rgba(85, 96, 255, 0.2);">
                <i class="ri-printer-line" style="font-size:1.1rem; vertical-align: middle;"></i> <span>Exportar Ficha de Salud en PDF</span>
            </a>
        </div>
    </div>
</div>

<script>
    window.portalAgendaEventos = <?php 
        $eventos_js = [];
        if (is_array($todas_citas)) {
            foreach ($todas_citas as $c) {
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
        }
        if (is_array($todas_vacunas)) {
            foreach ($todas_vacunas as $v) {
                $eventos_js[] = [
                    'tipo' => 'vacuna',
                    'id_mascota' => $v['id_mascota'],
                    'nombre_mascota' => $v['nombre_mascota'],
                    'foto_mascota' => $v['foto_mascota'],
                    'titulo' => $v['nombre_vacuna'],
                    'detalle' => 'Dosis: ' . $v['dosis'],
                    'fecha' => $v['fecha_aplicacion'],
                    'hora' => null,
                    'estado' => 'aplicada',
                    'proxima' => ($v['fecha_proxima'] !== '0000-00-00') ? $v['fecha_proxima'] : null
                ];
            }
        }
        if (is_array($todas_desparasitaciones)) {
            foreach ($todas_desparasitaciones as $d) {
                $eventos_js[] = [
                    'tipo' => 'control',
                    'id_mascota' => $d['id_mascota'],
                    'nombre_mascota' => $d['nombre_mascota'],
                    'foto_mascota' => $d['foto_mascota'],
                    'titulo' => $d['producto'],
                    'detalle' => 'Dosis: ' . $d['dosis'],
                    'fecha' => $d['fecha_aplicacion'],
                    'hora' => null,
                    'estado' => 'aplicado',
                    'proxima' => ($d['fecha_proxima'] !== '0000-00-00') ? $d['fecha_proxima'] : null
                ];
            }
        }
        echo json_encode($eventos_js);
    ?>;
</script>

<!-- ══ SCREEN: RECORDATORIOS (NOTIFICATIONS) ════════════════════════ -->
<div id="screen-notifications" class="app-screen">
    <div class="section-title-row">
        <h2>Alertas de tus Mascotas</h2>
    </div>
    <div class="notifications-list" id="portalAlertsList">
        <div class="notification-card-empty">
            <i class="ri-notification-off-line"></i>
            <p>No tienes alertas médicas o recordatorios programados en este momento.</p>
        </div>
    </div>
</div>

<!-- ══ SCREEN: PERFIL / CUENTA (ACCOUNT) ════════════════════════════ -->
<div id="screen-account" class="app-screen">
    <div class="profile-card">
        <div class="profile-avatar-large">
            <?php echo htmlspecialchars($iniciales ?: 'U'); ?>
        </div>
        <h3><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Propietario'); ?></h3>
        <p>Propietario Zooki</p>
    </div>

    <div class="profile-details-list">
        <div class="profile-detail-row">
            <span class="profile-detail-label">Cédula</span>
            <span class="profile-detail-value"><?php echo htmlspecialchars($_SESSION['usuario_doc'] ?? '—'); ?></span>
        </div>
        <div class="profile-detail-row">
            <span class="profile-detail-label">Correo</span>
            <span class="profile-detail-value" id="profileEmailVal"><?php echo htmlspecialchars($usuarioData['email'] ?? 'No registrado'); ?></span>
        </div>
        <div class="profile-detail-row">
            <span class="profile-detail-label">Teléfono</span>
            <span class="profile-detail-value" id="profilePhoneVal"><?php echo htmlspecialchars($usuarioData['telefono'] ?? 'No registrado'); ?></span>
        </div>
        <div class="profile-detail-row">
            <span class="profile-detail-label">Rol</span>
            <span class="profile-detail-value">Cliente</span>
        </div>
    </div>

    <!-- Editar Datos de Contacto -->
    <div class="profile-actions-list" style="margin-bottom: 0.75rem;">
        <button type="button" class="btn-profile-action" onclick="toggleContactEditPortal()">
            <span><i class="ri-user-edit-line" style="margin-right: 0.5rem; vertical-align: middle;"></i> Editar Datos de Contacto</span>
            <i class="ri-arrow-down-s-line" id="iconToggleContact"></i>
        </button>
        
        <div id="contactEditSection" class="password-change-collapse" style="display: none; padding: 1rem; background: var(--z-bg-light); border-radius: 12px; margin-top: -0.5rem; margin-bottom: 1rem;">
            <form id="portalContactEditForm" onsubmit="event.preventDefault(); submitContactEditPortal();">
                <div class="input-group" style="margin-bottom: 0.8rem;">
                    <label style="font-weight: 600; font-size: 0.8rem; color: var(--z-text-main); display:block; margin-bottom:0.4rem;">Correo Electrónico *</label>
                    <div class="search-input-wrapper" style="padding: 0.5rem 0.8rem; border-color: rgba(85,96,255,0.25); display: flex; align-items: center; background: #fff; border-radius: 12px; border: 1px solid var(--z-border);">
                        <i class="ri-mail-line" style="color: #64748b; margin-right: 0.5rem;"></i>
                        <input type="email" name="email" id="portal_contact_email" required style="border:none; background:transparent; width:100%; outline:none; color: var(--z-text-main); font-family: inherit; font-size: 0.85rem;" placeholder="ejemplo@correo.com" value="<?php echo htmlspecialchars($usuarioData['email'] ?? ''); ?>">
                    </div>
                </div>
                <div class="input-group" style="margin-bottom: 1rem;">
                    <label style="font-weight: 600; font-size: 0.8rem; color: var(--z-text-main); display:block; margin-bottom:0.4rem;">Teléfono de Contacto *</label>
                    <div class="search-input-wrapper" style="padding: 0.5rem 0.8rem; border-color: rgba(85,96,255,0.25); display: flex; align-items: center; background: #fff; border-radius: 12px; border: 1px solid var(--z-border);">
                        <i class="ri-phone-line" style="color: #64748b; margin-right: 0.5rem;"></i>
                        <input type="text" name="telefono" id="portal_contact_phone" required style="border:none; background:transparent; width:100%; outline:none; color: var(--z-text-main); font-family: inherit; font-size: 0.85rem;" placeholder="Tu número de teléfono" value="<?php echo htmlspecialchars($usuarioData['telefono'] ?? ''); ?>">
                    </div>
                </div>
                <button type="submit" class="btn-primary" id="btnSubmitContactEdit" style="background: linear-gradient(135deg, var(--z-primary) 0%, var(--z-primary-dark) 100%); color: #ffffff; width: 100%; border: none; border-radius: 12px; padding: 0.65rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.85rem;">
                    <span>Guardar Cambios</span>
                    <i class="ri-check-line"></i>
                </button>
            </form>
        </div>
    </div>

    <div class="profile-actions-list">
        <button type="button" class="btn-profile-action" onclick="togglePasswordChangePortal()">
            <span><i class="ri-lock-password-line" style="margin-right: 0.5rem; vertical-align: middle;"></i> Cambiar Contraseña</span>
            <i class="ri-arrow-down-s-line" id="iconTogglePassword"></i>
        </button>
        
        <div id="passwordChangeSection" class="password-change-collapse" style="display: none; padding: 1rem; background: var(--z-bg-light); border-radius: 12px; margin-top: -0.5rem; margin-bottom: 1rem;">
            <form id="portalChangePasswordForm" onsubmit="event.preventDefault(); submitChangePasswordPortal();">
                <?php if (($_SESSION['login_method'] ?? 'password') !== 'google'): ?>
                <div class="input-group" style="margin-bottom: 0.8rem;">
                    <label style="font-weight: 600; font-size: 0.8rem; color: var(--z-text-main);">Contraseña Actual</label>
                    <div class="search-input-wrapper" style="padding: 0.5rem 0.8rem; border-color: rgba(85,96,255,0.25);">
                        <input type="password" name="current_password" id="portal_current_password" required style="border:none; background:transparent; width:100%; outline:none; color: var(--z-text-main);" placeholder="Tu contraseña actual">
                    </div>
                </div>
                <?php endif; ?>
                <div class="input-group" style="margin-bottom: 0.8rem;">
                    <label style="font-weight: 600; font-size: 0.8rem; color: var(--z-text-main);">Nueva Contraseña</label>
                    <div class="search-input-wrapper" style="padding: 0.5rem 0.8rem; border-color: rgba(85,96,255,0.25);">
                        <input type="password" name="new_password" id="portal_new_password" required style="border:none; background:transparent; width:100%; outline:none; color: var(--z-text-main);" placeholder="Mínimo 8 caracteres" oninput="validarFuerzaPasswordPortal()">
                    </div>
                    <div class="password-strength-meter" style="margin-top: 5px; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
                        <div id="portal_pwd_strength_bar" style="height: 100%; width: 0%; transition: all 0.3s ease;"></div>
                    </div>
                    <small id="portal_pwd_strength_text" style="font-size: 0.7rem; color: #64748b; margin-top: 4px; display: block;">Mínimo 8 caracteres, una mayúscula y un número.</small>
                </div>
                <div class="input-group" style="margin-bottom: 1rem;">
                    <label style="font-weight: 600; font-size: 0.8rem; color: var(--z-text-main);">Confirmar Nueva Contraseña</label>
                    <div class="search-input-wrapper" style="padding: 0.5rem 0.8rem; border-color: rgba(85,96,255,0.25);">
                        <input type="password" name="confirm_password" id="portal_confirm_password" required style="border:none; background:transparent; width:100%; outline:none; color: var(--z-text-main);" placeholder="Repite la nueva contraseña" oninput="validarFuerzaPasswordPortal()">
                    </div>
                    <small id="portal_pwd_match_text" style="font-size: 0.7rem; color: var(--z-danger); margin-top: 4px; display: none;">Las contraseñas no coinciden.</small>
                </div>
                <button type="submit" id="portal_btn_change_pwd" class="btn-primary" disabled style="background: linear-gradient(135deg, var(--z-primary) 0%, var(--z-primary-dark) 100%); color: #fff; width: 100%; border: none; border-radius: 14px; padding: 0.85rem; font-weight: 700; cursor: pointer; opacity: 0.5; box-shadow: 0 4px 12px rgba(0, 82, 255, 0.2);">Actualizar Contraseña</button>
            </form>
        </div>

        <a href="index.php?action=logout" class="btn-profile-action logout-btn" style="text-decoration: none;">
            <span><i class="ri-logout-box-r-line" style="margin-right: 0.5rem; vertical-align: middle;"></i> Cerrar Sesión</span>
            <i class="ri-arrow-right-s-line"></i>
        </a>
    </div>
</div>

<!-- ══ NAVEGACIÓN INFERIOR (BOTTOM NAV BAR) ══════════════════════════ -->
<nav class="mobile-nav">
    <button type="button" class="mobile-nav-item active" id="nav-home" onclick="switchTab('home')">
        <i class="ri-home-5-line"></i>
        <span>Inicio</span>
    </button>
    <button type="button" class="mobile-nav-item" id="nav-explore" onclick="switchTab('explore')">
        <i class="ri-compass-3-line"></i>
        <span>Servicios</span>
    </button>
    
    <!-- Botón Central Flotante -->
    <div class="mobile-nav-item--center">
        <button type="button" class="center-fab-button" id="btnOpenBookingModal" title="Agendar Cita">
            <i class="ri-add-line"></i>
        </button>
        <span class="center-fab-label">Agendar</span>
    </div>

    <button type="button" class="mobile-nav-item" id="nav-agenda" onclick="switchTab('agenda')">
        <i class="ri-calendar-todo-line"></i>
        <span>Agenda</span>
    </button>

    <button type="button" class="mobile-nav-item" id="nav-account" onclick="switchTab('account')">
        <i class="ri-user-3-line"></i>
        <span>Perfil</span>
    </button>
</nav>

<!-- Pantalla de Detalles de Mascota (screen-pet-detail) -->
<div id="screen-pet-detail" class="app-screen" style="padding-bottom: 90px;">
    <div class="portal-drawer-header" style="display: flex; align-items: center; gap: 0.65rem; padding: 1.25rem 0 1rem; border-bottom: 1px solid var(--z-border); flex-shrink: 0;">
        <button type="button" class="portal-drawer-back" onclick="cerrarDrawer()" aria-label="Volver" style="width: 38px; height: 38px; border: none; background: var(--z-bg); border-radius: 50%; color: var(--z-text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="ri-arrow-left-line"></i>
        </button>
        <div class="portal-drawer-title-wrap" style="flex: 1; min-width: 0;">
            <h2 id="drawerPetTitle" style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">—</h2>
            <p id="drawerPetSubtitle" style="margin: 0; font-size: 0.75rem; color: var(--z-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></p>
        </div>
        <button type="button" id="btnEditPetProfile" style="background: var(--z-primary-soft); color: var(--z-primary); border: none; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.1rem; margin-left: auto;" title="Editar Perfil de Mascota">
            <i class="ri-edit-line"></i>
        </button>
    </div>

    <div id="drawerPetSummary" class="portal-drawer-summary" style="margin-top: 1rem;"></div>

    <nav class="portal-drawer-tabs" role="tablist" style="margin-top: 1rem; display: grid; grid-template-columns: repeat(4, 1fr); background: var(--z-bg-light); padding: 4px; border-radius: 14px; gap: 4px;">
        <button type="button" class="portal-tab active" data-tab="historial" role="tab" aria-selected="true" style="padding: 0.65rem 0.25rem; font-size: 0.75rem; display: flex; flex-direction: column; align-items: center; justify-content: center; border: none; background: transparent; color: var(--z-text-muted); border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.2s;">
            <i class="ri-history-line" style="font-size: 1rem; margin-bottom: 2px;"></i> Historial
        </button>
        <button type="button" class="portal-tab" data-tab="citas" role="tab" aria-selected="false" style="padding: 0.65rem 0.25rem; font-size: 0.75rem; display: flex; flex-direction: column; align-items: center; justify-content: center; border: none; background: transparent; color: var(--z-text-muted); border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.2s;">
            <i class="ri-calendar-line" style="font-size: 1rem; margin-bottom: 2px;"></i> Citas
        </button>
        <button type="button" class="portal-tab" data-tab="vacunas" role="tab" aria-selected="false" style="padding: 0.65rem 0.25rem; font-size: 0.75rem; display: flex; flex-direction: column; align-items: center; justify-content: center; border: none; background: transparent; color: var(--z-text-muted); border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.2s;">
            <i class="ri-syringe-line" style="font-size: 1rem; margin-bottom: 2px;"></i> Vacunas
        </button>
        <button type="button" class="portal-tab" data-tab="desparasitaciones" role="tab" aria-selected="false" style="padding: 0.65rem 0.25rem; font-size: 0.75rem; display: flex; flex-direction: column; align-items: center; justify-content: center; border: none; background: transparent; color: var(--z-text-muted); border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.2s;">
            <i class="ri-capsule-line" style="font-size: 1rem; margin-bottom: 2px;"></i> Desparasit.
        </button>
    </nav>

    <div class="portal-drawer-body" style="margin-top: 1rem;">
        <div id="tab-historial" class="portal-tab-panel active" role="tabpanel">
            <div id="historialContent" class="portal-loading">Cargando historial…</div>
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

<!-- Modal para Agendar Cita desde el Portal -->
<div id="portalBookingModal" class="portal-drawer-overlay" style="display: none; z-index: 10000;">
    <div class="portal-drawer">
        <div class="portal-drawer-header" style="display: flex; align-items: center; gap: 0.65rem; padding: 1.25rem 1.25rem 1rem; border-bottom: 1px solid var(--z-border); flex-shrink: 0;">
            <button type="button" id="btnCloseBookingModal" class="portal-drawer-back" aria-label="Volver" style="width: 38px; height: 38px; border: none; background: var(--z-bg); border-radius: 50%; color: var(--z-text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                <i class="ri-arrow-left-line"></i>
            </button>
            <div class="portal-drawer-title-wrap" style="flex: 1; min-width: 0;">
                <h2 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;"><i class="ri-calendar-event-line" style="color: var(--z-primary);"></i> Agendar Nueva Cita</h2>
                <p style="margin: 0; font-size: 0.75rem; color: var(--z-text-muted);">Elige a tu compañero y el horario de tu preferencia.</p>
            </div>
        </div>
        <div class="portal-drawer-body" style="padding-top: 1rem;">
            <form id="portalBookingForm" style="display: flex; flex-direction: column; gap: 0.85rem;">
                <div class="input-group">
                    <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Seleccionar Mascota</label>
                    <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                        <i class="ri-baidu-line" style="color: #64748b;"></i>
                        <select name="id_mascota" id="booking_mascota" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                            <option value="">Selecciona...</option>
                            <?php foreach ($mascotas as $m): ?>
                                <option value="<?php echo (int)$m['id_mascota']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Tipo de Cita</label>
                    <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                        <i class="ri-briefcase-line" style="color: #64748b;"></i>
                        <select name="id_tipo_cita" id="booking_tipo_cita" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                            <option value="">Cargando tipos de cita...</option>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Seleccionar Veterinario</label>
                    <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                        <i class="ri-user-md-line" style="color: #64748b;"></i>
                        <select name="doc_veterinario" id="booking_veterinario" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                            <option value="">Selecciona veterinario...</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Fecha</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25); display: flex; align-items: center;">
                            <i class="ri-calendar-line" style="color: #64748b; margin-right: 0.35rem;"></i>
                            <input type="text" class="flatpickr-date" name="fecha" id="booking_fecha" required placeholder="Seleccione fecha..." style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                        </div>
                    </div>

                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Hora</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <select name="hora" id="booking_hora" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                                <option value="">Elige fecha...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Motivo de la Cita</label>
                    <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                        <textarea name="motivo" id="booking_motivo" required placeholder="Escribe el motivo..." style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; resize: none; height: 50px; font-size: 0.85rem;"></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, var(--z-primary) 0%, var(--z-primary-dark) 100%); color: #ffffff; width: 100%; border: none; border-radius: 14px; padding: 0.85rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.95rem; box-shadow: 0 8px 20px rgba(0, 82, 255, 0.2); margin-top: 0.5rem;">
                    <span>Confirmar Cita</span>
                    <i class="ri-calendar-check-line"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Registrar Nueva Mascota -->
<div id="portalAddPetModal" class="portal-drawer-overlay" style="display: none; z-index: 10000;">
    <div class="portal-drawer">
        <div class="portal-drawer-header" style="display: flex; align-items: center; gap: 0.65rem; padding: 1.25rem 1.25rem 1rem; border-bottom: 1px solid var(--z-border); flex-shrink: 0;">
            <button type="button" id="btnCloseAddPetModal" class="portal-drawer-back" aria-label="Volver" style="width: 38px; height: 38px; border: none; background: var(--z-bg); border-radius: 50%; color: var(--z-text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                <i class="ri-arrow-left-line"></i>
            </button>
            <div class="portal-drawer-title-wrap" style="flex: 1; min-width: 0;">
                <h2 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;"><i class="ri-baidu-line" style="color: var(--z-primary);"></i> Registrar Mascota</h2>
                <p style="margin: 0; font-size: 0.75rem; color: var(--z-text-muted);">Agrega un nuevo compañero a tu cuenta.</p>
            </div>
        </div>
        <div class="portal-drawer-body" style="padding-top: 1rem;">
            <form id="portalAddPetForm" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 0.85rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Nombre de la Mascota *</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <input type="text" name="nombre" required placeholder="Ej: Toby" style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                        </div>
                    </div>

                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Fecha de Nacimiento</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25); display: flex; align-items: center;">
                            <i class="ri-calendar-line" style="color: #64748b; margin-right: 0.35rem;"></i>
                            <input type="text" class="flatpickr-date" name="fecha_nacimiento" placeholder="Seleccione fecha..." style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Especie *</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <select name="especie" id="add_pet_especie" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                                <option value="">Selecciona...</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Raza *</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <select name="raza" id="add_pet_raza" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                                <option value="">Escribe especie...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="add_nueva_raza_wrapper" class="input-group" style="display: none;">
                    <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">¿Cuál Raza? *</label>
                    <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                        <input type="text" name="nueva_raza" id="add_nueva_raza" placeholder="Nombre de la nueva raza" style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Sexo *</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <select name="sexo" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                                <option value="Macho">Macho</option>
                                <option value="Hembra">Hembra</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Peso (kg) *</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <input type="number" step="0.01" name="peso" required placeholder="Ej: 8.5" style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Colores Base</label>
                    <div id="add_colores_container" style="display: flex; flex-wrap: wrap; gap: 0.4rem; max-height: 120px; overflow-y: auto; padding: 0.25rem;">
                        <!-- Se carga dinámicamente -->
                    </div>
                </div>

                <div class="input-group">
                    <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Foto de Perfil</label>
                    <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                        <input type="file" name="foto" accept="image/*" style="font-size: 0.8rem; width: 100%;">
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, var(--z-primary) 0%, var(--z-primary-dark) 100%); color: #ffffff; width: 100%; border: none; border-radius: 14px; padding: 0.85rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.95rem; box-shadow: 0 8px 20px rgba(0, 82, 255, 0.2); margin-top: 0.5rem;">
                    <span>Registrar Mascota</span>
                    <i class="ri-check-line"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Editar Mascota Existente -->
<div id="portalEditPetModal" class="portal-drawer-overlay" style="display: none; z-index: 10000;">
    <div class="portal-drawer">
        <div class="portal-drawer-header" style="display: flex; align-items: center; gap: 0.65rem; padding: 1.25rem 1.25rem 1rem; border-bottom: 1px solid var(--z-border); flex-shrink: 0;">
            <button type="button" id="btnCloseEditPetModal" class="portal-drawer-back" aria-label="Volver" style="width: 38px; height: 38px; border: none; background: var(--z-bg); border-radius: 50%; color: var(--z-text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                <i class="ri-arrow-left-line"></i>
            </button>
            <div class="portal-drawer-title-wrap" style="flex: 1; min-width: 0;">
                <h2 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;"><i class="ri-edit-line" style="color: var(--z-primary);"></i> Editar Mascota</h2>
                <p style="margin: 0; font-size: 0.75rem; color: var(--z-text-muted);">Personaliza y actualiza la información de tu mascota.</p>
            </div>
        </div>
        <div class="portal-drawer-body" style="padding-top: 1rem;">
            <form id="portalEditPetForm" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 0.85rem;">
                <input type="hidden" name="id_mascota" id="edit_pet_id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Nombre de la Mascota *</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <input type="text" name="nombre" id="edit_pet_nombre" required placeholder="Ej: Toby" style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                        </div>
                    </div>

                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Fecha de Nacimiento</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25); display: flex; align-items: center;">
                            <i class="ri-calendar-line" style="color: #64748b; margin-right: 0.35rem;"></i>
                            <input type="text" class="flatpickr-date" name="fecha_nacimiento" id="edit_pet_nacimiento" placeholder="Seleccione fecha..." style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Especie *</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <select name="especie" id="edit_pet_especie" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                                <option value="">Selecciona...</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Raza *</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <select name="raza" id="edit_pet_raza" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                                <option value="">Escribe especie...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="edit_nueva_raza_wrapper" class="input-group" style="display: none;">
                    <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">¿Cuál Raza? *</label>
                    <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                        <input type="text" name="nueva_raza" id="edit_nueva_raza" placeholder="Nombre de la nueva raza" style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Sexo *</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <select name="sexo" id="edit_pet_sexo" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                                <option value="Macho">Macho</option>
                                <option value="Hembra">Hembra</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Peso (kg) *</label>
                        <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                            <input type="number" step="0.01" name="peso" id="edit_pet_peso" required placeholder="Ej: 8.5" style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Colores Base</label>
                    <div id="edit_colores_container" style="display: flex; flex-wrap: wrap; gap: 0.4rem; max-height: 120px; overflow-y: auto; padding: 0.25rem;">
                        <!-- Se carga dinámicamente -->
                    </div>
                </div>

                <div class="input-group">
                    <label style="font-weight: 700; color: #0f172a; margin-bottom: 0.4rem; display: block; font-size: 0.8rem;">Nueva Foto (Opcional)</label>
                    <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                        <input type="file" name="foto" accept="image/*" style="font-size: 0.8rem; width: 100%;">
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, var(--z-primary) 0%, var(--z-primary-dark) 100%); color: #ffffff; width: 100%; border: none; border-radius: 14px; padding: 0.85rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.95rem; box-shadow: 0 8px 20px rgba(0, 82, 255, 0.2); margin-top: 0.5rem;">
                    <span>Guardar Cambios</span>
                    <i class="ri-check-line"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Ver Detalles de Cita y Ficha Clínica -->
<div id="portalCitaDetalleModal" class="portal-drawer-overlay" style="display: none; z-index: 10000;">
    <div class="portal-drawer" style="max-width: 500px;">
        <div class="portal-drawer-header" style="display: flex; align-items: center; gap: 0.65rem; padding: 1.25rem 1.25rem 1rem; border-bottom: 1px solid var(--z-border); flex-shrink: 0;">
            <button type="button" id="btnCloseCitaDetalleModal" class="portal-drawer-back" aria-label="Volver" style="width: 38px; height: 38px; border: none; background: var(--z-bg); border-radius: 50%; color: var(--z-text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                <i class="ri-arrow-left-line"></i>
            </button>
            <div class="portal-drawer-title-wrap" style="flex: 1; min-width: 0;">
                <h2 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;"><i class="ri-heart-pulse-fill" style="color: var(--z-primary);"></i> Detalle de la Cita</h2>
                <p style="margin: 0; font-size: 0.75rem; color: var(--z-text-muted);">Información y resultado clínico de la consulta.</p>
            </div>
        </div>
        <div class="portal-drawer-body" style="padding-top: 1rem; max-height: 75vh; overflow-y: auto;">
            <!-- Información general de la cita -->
            <div class="appointment-general-info" style="background: var(--z-bg-light); border-radius: 16px; padding: 1rem; border: 1px solid var(--z-border); display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--z-text-muted);" id="detCitaTipo">CONSULTA GENERAL</span>
                    <span class="status-badge" id="detCitaEstado" style="font-size: 0.65rem;">Completada</span>
                </div>
                <div style="font-size: 0.9rem; color: #0f172a; font-weight: 800; margin-top: 0.25rem;">
                    Mascota: <span id="detCitaMascota" style="font-weight: 500;">Toby</span>
                </div>
                <div style="font-size: 0.85rem; color: var(--z-text-muted);">
                    Veterinario: <span id="detCitaVet" style="color: #0f172a; font-weight: 600;">Dr(a). Andrés Pérez</span>
                </div>
                <div style="font-size: 0.82rem; color: var(--z-primary); font-weight: 700; margin-top: 0.15rem; display: flex; align-items: center; gap: 0.35rem;">
                    <i class="ri-calendar-line"></i> <span id="detCitaFechaHora">24/07/2026 · 10:30</span>
                </div>
            </div>

            <!-- Ficha clínica (si está completada) -->
            <div id="detCitaClinicaArea" style="display: none;">
                <h3 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 1.5rem 0 0.75rem; border-bottom: 2px solid var(--z-border); padding-bottom: 0.35rem;">
                    <i class="ri-folder-shield-2-line" style="color: var(--z-success); vertical-align: middle; margin-right: 0.35rem;"></i>Resultado Clínico
                </h3>
                
                <!-- Signos vitales y peso -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 1rem;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.65rem; text-align: center;">
                        <span style="display: block; font-size: 0.65rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Peso</span>
                        <strong id="detClinicaPeso" style="font-size: 0.9rem; color: #0f172a;">8.5 kg</strong>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.65rem; text-align: center;">
                        <span style="display: block; font-size: 0.65rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Temp</span>
                        <strong id="detClinicaTemp" style="font-size: 0.9rem; color: #0f172a;">38.5 °C</strong>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.65rem; text-align: center;">
                        <span style="display: block; font-size: 0.65rem; font-weight: 700; color: #64748b; text-transform: uppercase;">F. Cardiaca</span>
                        <strong id="detClinicaFc" style="font-size: 0.9rem; color: #0f172a;">110 lpm</strong>
                    </div>
                </div>

                <!-- Detalles Clínicos -->
                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <div>
                        <label style="font-weight: 800; font-size: 0.75rem; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Motivo de Consulta</label>
                        <p id="detClinicaMotivo" style="margin: 0; font-size: 0.85rem; color: #334155; background: #f8fafc; padding: 0.65rem 0.85rem; border-radius: 12px; border: 1px solid #e2e8f0; line-height: 1.4;"></p>
                    </div>

                    <div>
                        <label style="font-weight: 800; font-size: 0.75rem; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Anamnesis / Observaciones</label>
                        <p id="detClinicaAnamnesis" style="margin: 0; font-size: 0.85rem; color: #334155; background: #f8fafc; padding: 0.65rem 0.85rem; border-radius: 12px; border: 1px solid #e2e8f0; line-height: 1.4;"></p>
                    </div>

                    <div>
                        <label style="font-weight: 800; font-size: 0.75rem; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Diagnóstico</label>
                        <p id="detClinicaDiagnostico" style="margin: 0; font-size: 0.85rem; color: #0f172a; font-weight: 700; background: #fffbeb; padding: 0.75rem 0.85rem; border-radius: 12px; border: 1px solid #fde68a; line-height: 1.4;"></p>
                    </div>

                    <div>
                        <label style="font-weight: 800; font-size: 0.75rem; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Plan de Tratamiento</label>
                        <p id="detClinicaPlan" style="margin: 0; font-size: 0.85rem; color: #334155; background: #f8fafc; padding: 0.65rem 0.85rem; border-radius: 12px; border: 1px solid #e2e8f0; line-height: 1.4;"></p>
                    </div>
                </div>

                <!-- Receta Médica / Medicamentos -->
                <h3 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 1.5rem 0 0.75rem; border-bottom: 2px solid var(--z-border); padding-bottom: 0.35rem;">
                    <i class="ri-capsule-fill" style="color: var(--z-primary); vertical-align: middle; margin-right: 0.35rem;"></i>Receta de Medicamentos
                </h3>
                <div id="detClinicaTratamientosList" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem;">
                    <!-- Se carga dinámicamente -->
                </div>
            </div>

            <!-- Si no está completada -->
            <div id="detCitaSinClinica" style="display: block; text-align: center; padding: 2rem 1rem; color: var(--z-text-muted); background: #f8fafc; border-radius: 16px; border: 1px dotted #cbd5e1; margin-top: 1rem;">
                <i class="ri-health-book-line" id="detSinClinicaIcon" style="font-size: 2.2rem; opacity: 0.4; display: block; margin-bottom: 0.5rem; color: var(--z-primary);"></i>
                <p style="margin: 0; font-size: 0.82rem; font-weight: 600;" id="detSinClinicaTitle">Esta cita aún no ha sido atendida.</p>
                <p style="margin: 4px 0 0; font-size: 0.75rem; opacity: 0.8;" id="detSinClinicaDesc">Cuando el veterinario finalice la consulta, aquí podrás visualizar la historia clínica, diagnóstico y medicamentos recetados.</p>
            </div>
        </div>
    </div>
</div>


