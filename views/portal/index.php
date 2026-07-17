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
        <span class="see-all"><?php echo $total_mascotas; ?> en total</span>
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
        <div class="service-row-item" data-name="grooming baño peluqueria higiene corte">
            <div class="service-row-meta">
                <div class="service-row-icon" style="background-color: var(--z-purple-light); color: var(--z-purple);"><i class="ri-scissors-cut-line"></i></div>
                <div class="service-row-details">
                    <h4>Grooming & Higiene</h4>
                    <p>Baño y corte especializado</p>
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
            <span class="profile-detail-value"><?php echo htmlspecialchars($usuarioData['email'] ?? 'No registrado'); ?></span>
        </div>
        <div class="profile-detail-row">
            <span class="profile-detail-label">Teléfono</span>
            <span class="profile-detail-value"><?php echo htmlspecialchars($usuarioData['telefono'] ?? 'No registrado'); ?></span>
        </div>
        <div class="profile-detail-row">
            <span class="profile-detail-label">Rol</span>
            <span class="profile-detail-value">Cliente</span>
        </div>
    </div>

    <div class="profile-actions-list">
        <button type="button" class="btn-profile-action" onclick="togglePasswordChangePortal()">
            <span><i class="ri-lock-password-line" style="margin-right: 0.5rem; vertical-align: middle;"></i> Cambiar Contraseña</span>
            <i class="ri-arrow-down-s-line" id="iconTogglePassword"></i>
        </button>
        
        <div id="passwordChangeSection" class="password-change-collapse" style="display: none; padding: 1rem; background: var(--z-bg-light); border-radius: 12px; margin-top: -0.5rem; margin-bottom: 1rem;">
            <form id="portalChangePasswordForm" onsubmit="event.preventDefault(); submitChangePasswordPortal();">
                <div class="input-group" style="margin-bottom: 0.8rem;">
                    <label style="font-weight: 600; font-size: 0.8rem; color: var(--z-text-main);">Contraseña Actual</label>
                    <div class="search-input-wrapper" style="padding: 0.5rem 0.8rem; border-color: rgba(85,96,255,0.25);">
                        <input type="password" name="current_password" id="portal_current_password" required style="border:none; background:transparent; width:100%; outline:none; color: var(--z-text-main);" placeholder="Tu contraseña actual">
                    </div>
                </div>
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

    <button type="button" class="mobile-nav-item" id="nav-notifications" onclick="switchTab('notifications')">
        <i class="ri-notification-3-line"></i>
        <span>Alertas</span>
    </button>
    <button type="button" class="mobile-nav-item" id="nav-account" onclick="switchTab('account')">
        <i class="ri-user-3-line"></i>
        <span>Perfil</span>
    </button>
</nav>

<!-- Panel lateral / Drawer de Detalles de Mascota -->
<div id="portalDrawerOverlay" class="portal-drawer-overlay" onclick="cerrarDrawer()" aria-hidden="true"></div>
<aside id="portalDrawer" class="portal-drawer" aria-hidden="true" aria-labelledby="drawerPetTitle">
    <div class="portal-drawer-header">
        <button type="button" class="portal-drawer-back" onclick="cerrarDrawer()" aria-label="Volver">
            <i class="ri-arrow-left-line"></i>
        </button>
        <div class="portal-drawer-title-wrap">
            <h2 id="drawerPetTitle">—</h2>
            <p id="drawerPetSubtitle"></p>
        </div>
        <button type="button" class="portal-drawer-close" onclick="cerrarDrawer()" aria-label="Cerrar">
            <i class="ri-close-line"></i>
        </button>
    </div>

    <div id="drawerPetSummary" class="portal-drawer-summary"></div>

    <nav class="portal-drawer-tabs" role="tablist">
        <button type="button" class="portal-tab active" data-tab="historial" role="tab" aria-selected="true">
            <i class="ri-history-line"></i> Historial
        </button>
        <button type="button" class="portal-tab" data-tab="citas" role="tab" aria-selected="false">
            <i class="ri-calendar-line"></i> Citas
        </button>
        <button type="button" class="portal-tab" data-tab="vacunas" role="tab" aria-selected="false">
            <i class="ri-syringe-line"></i> Vacunas
        </button>
        <button type="button" class="portal-tab" data-tab="desparasitaciones" role="tab" aria-selected="false">
            <i class="ri-capsule-line"></i> Desparasit.
        </button>
    </nav>

    <div class="portal-drawer-body">
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
</aside>

<!-- Modal para Agendar Cita desde el Portal -->
<div id="portalBookingModal" class="portal-drawer-overlay" style="display: none; align-items: center; justify-content: center; padding: 1.5rem;">
    <div class="portal-drawer" style="position: relative; transform: none; height: auto; bottom: auto; left: auto; width: min(420px, 100%); border-radius: 24px; padding: 1.75rem; box-shadow: var(--z-shadow-lg);">
        <button type="button" id="btnCloseBookingModal" style="position: absolute; top: 1.25rem; right: 1.25rem; background: none; border: none; font-size: 1.5rem; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i class="ri-close-line"></i></button>
        <h3 style="margin-top: 0; color: #0f172a; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-weight: 800;"><i class="ri-calendar-event-line" style="color: var(--z-primary);"></i> Agendar Nueva Cita</h3>
        <p style="color: #64748b; margin-bottom: 1.5rem; font-size: 0.8rem;">Elige a tu compañero y el horario de tu preferencia.</p>
        
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
                    <div class="search-input-wrapper" style="padding: 0.65rem 0.85rem; border-color: rgba(85,96,255,0.25);">
                        <input type="date" name="fecha" id="booking_fecha" required style="border: none; background: transparent; width: 100%; outline: none; font-family: inherit; font-size: 0.85rem;">
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
