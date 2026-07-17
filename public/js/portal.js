/* Portal del propietario — Zooki (Rediseño Estilo App Móvil) */

function escapeHtml(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

function formatFecha(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr.includes('T') ? dateStr : dateStr + 'T12:00:00');
    return d.toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatFechaHora(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-CO', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function badgeEstado(estado) {
    const e = (estado || 'pendiente').toLowerCase();
    return `<span class="portal-badge portal-badge--${escapeHtml(e)}">${escapeHtml(estado || 'pendiente')}</span>`;
}

/* Navegación Inferior (Tabs/Pantallas) */
function switchTab(tabId) {
    // Desactivar todas las pantallas
    document.querySelectorAll('.app-screen').forEach(screen => {
        screen.classList.remove('active');
    });

    // Activar la seleccionada
    const targetScreen = document.getElementById(`screen-${tabId}`);
    if (targetScreen) {
        targetScreen.classList.add('active');
    }

    // Actualizar estados del navbar inferior
    document.querySelectorAll('.mobile-nav-item').forEach(item => {
        item.classList.remove('active');
    });

    const activeNavItem = document.getElementById(`nav-${tabId}`);
    if (activeNavItem) {
        activeNavItem.classList.add('active');
    }

    // Si entramos a Notificaciones/Alertas o Explorar, refrescar datos dinámicamente
    if (tabId === 'notifications') {
        loadPortalAlerts();
    } else if (tabId === 'explore') {
        loadVetsExplore();
    }
}

/* Buscar/Filtrar Servicios en la pestaña Explorar */
function filtrarServicios(query) {
    const cleanQuery = query.toLowerCase().trim();
    document.querySelectorAll('.service-row-item').forEach(item => {
        const name = item.dataset.name || '';
        if (name.includes(cleanQuery)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

/* Cargar Veterinarios Activos en pestaña Explorar */
async function loadVetsExplore() {
    const listEl = document.getElementById('exploreVetsList');
    if (!listEl) return;

    try {
        const res = await (await fetch('index.php?action=portal_get_vets_ajax')).json();
        if (!res || res.length === 0) {
            listEl.innerHTML = '<div class="notification-card-empty"><p>No hay veterinarios activos hoy.</p></div>';
            return;
        }

        let html = '';
        res.forEach(v => {
            const iniciales = escapeHtml(v.nombre_completo.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase());
            html += `
            <div class="vet-card-item">
                <div class="vet-photo-circle" style="display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--z-primary); font-size: 1.25rem;">
                    ${iniciales}
                </div>
                <h4>Dr(a). ${escapeHtml(v.nombre_completo.split(' ')[0])}</h4>
                <p>Médico Veterinario</p>
            </div>`;
        });
        listEl.innerHTML = html;
    } catch (e) {
        console.error(e);
        listEl.innerHTML = '<div class="notification-card-empty"><p>Error al cargar veterinarios.</p></div>';
    }
}

/* Cargar Alertas y Notificaciones */
async function loadPortalAlerts() {
    const listEl = document.getElementById('portalAlertsList');
    const bellBadge = document.getElementById('bellBadgeAlert');
    if (!listEl) return;

    try {
        const res = await (await fetch('index.php?action=get_notificaciones_ajax')).json();
        if (!res.success || !res.notificaciones || res.notificaciones.length === 0) {
            listEl.innerHTML = `
                <div class="notification-card-empty">
                    <i class="ri-notification-off-line"></i>
                    <p>No tienes alertas médicas o recordatorios programados en este momento.</p>
                </div>`;
            if (bellBadge) bellBadge.style.display = 'none';
            return;
        }

        if (bellBadge) {
            bellBadge.style.display = res.no_leidas > 0 ? 'block' : 'none';
        }

        let html = '';
        res.notificaciones.forEach(n => {
            let typeClass = 'cita';
            let iconClass = 'ri-calendar-todo-line';
            if (n.tipo === 'NUEVA_VACUNA' || n.tipo === 'VACUNA_PENDIENTE') {
                typeClass = 'vacuna';
                iconClass = 'ri-syringe-line';
            } else if (n.tipo === 'DESPARASITACION') {
                typeClass = 'desparasitacion';
                iconClass = 'ri-capsule-line';
            }

            html += `
            <div class="notification-card">
                <div class="notification-icon ${typeClass}"><i class="${iconClass}"></i></div>
                <div class="notification-content">
                    <h4>${escapeHtml(n.titulo)}</h4>
                    <p>${escapeHtml(n.mensaje)}</p>
                    <span class="notification-date">${escapeHtml(n.fecha_creacion)}</span>
                </div>
            </div>`;
        });
        listEl.innerHTML = html;
    } catch (e) {
        console.error(e);
        listEl.innerHTML = `
            <div class="notification-card-empty">
                <i class="ri-error-warning-line"></i>
                <p>Error al conectar con el servidor.</p>
            </div>`;
    }
}

/* Cambiar Contraseña del Propietario mediante Swal */
/* Cambio de Contraseña Inline */
function togglePasswordChangePortal() {
    const section = document.getElementById('passwordChangeSection');
    const icon = document.getElementById('iconTogglePassword');
    if (section.style.display === 'none' || section.style.display === '') {
        section.style.display = 'block';
        icon.className = 'ri-arrow-up-s-line';
        if (typeof section.animate === 'function') {
            section.animate([
                { opacity: 0, transform: 'translateY(-10px)' },
                { opacity: 1, transform: 'translateY(0)' }
            ], { duration: 300, easing: 'ease-out' });
        }
    } else {
        section.style.display = 'none';
        icon.className = 'ri-arrow-down-s-line';
    }
}

function validarFuerzaPasswordPortal() {
    const pwd = document.getElementById('portal_new_password').value;
    const confirmPwd = document.getElementById('portal_confirm_password').value;
    const bar = document.getElementById('portal_pwd_strength_bar');
    const text = document.getElementById('portal_pwd_strength_text');
    const matchText = document.getElementById('portal_pwd_match_text');
    const btn = document.getElementById('portal_btn_change_pwd');
    
    let strength = 0;
    
    if (pwd.length >= 8) strength += 25;
    if (pwd.match(/[A-Z]/)) strength += 25;
    if (pwd.match(/[0-9]/)) strength += 25;
    if (pwd.match(/[^A-Za-z0-9]/)) strength += 25;

    bar.style.width = strength + '%';
    if (strength <= 25) {
        bar.style.background = 'var(--z-danger)';
        text.textContent = 'Débil: Mínimo 8 caracteres, una mayúscula y un número.';
        text.style.color = 'var(--z-danger)';
    } else if (strength <= 75) {
        bar.style.background = 'var(--z-warning)';
        text.textContent = 'Media: Agrega símbolos para mayor seguridad.';
        text.style.color = 'var(--z-warning)';
    } else {
        bar.style.background = 'var(--z-success)';
        text.textContent = 'Fuerte: Contraseña segura.';
        text.style.color = 'var(--z-success)';
    }
    
    let match = true;
    if (confirmPwd.length > 0) {
        if (pwd !== confirmPwd) {
            matchText.style.display = 'block';
            match = false;
        } else {
            matchText.style.display = 'none';
            match = true;
        }
    } else {
        matchText.style.display = 'none';
        match = false;
    }
    
    if (strength >= 75 && match && pwd.length >= 8) {
        btn.disabled = false;
        btn.style.opacity = '1';
    } else {
        btn.disabled = true;
        btn.style.opacity = '0.5';
    }
}

async function submitChangePasswordPortal() {
    const current = document.getElementById('portal_current_password').value;
    const pwd = document.getElementById('portal_new_password').value;
    const confirmPwd = document.getElementById('portal_confirm_password').value;

    if (pwd !== confirmPwd) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Las contraseñas no coinciden.',
            confirmButtonColor: '#5560FF'
        });
        return;
    }

    const btn = document.getElementById('portal_btn_change_pwd');
    btn.disabled = true;
    btn.innerHTML = 'Actualizando...';

    try {
        const formData = new FormData();
        formData.append('password_actual', current);
        formData.append('password_nueva', pwd);

        const res = await (await fetch('index.php?action=cambiar_password_ajax', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })).json();

        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: res.message || 'Contraseña actualizada correctamente.',
                confirmButtonColor: '#5560FF'
            });
            document.getElementById('portalChangePasswordForm').reset();
            togglePasswordChangePortal();
            validarFuerzaPasswordPortal();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: res.message || 'No se pudo actualizar la contraseña.',
                confirmButtonColor: '#5560FF'
            });
        }
    } catch (e) {
        console.error(e);
        Swal.fire({
            icon: 'error',
            title: 'Error de Red',
            text: 'No pudimos conectarnos con el servidor.',
            confirmButtonColor: '#5560FF'
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Actualizar Contraseña';
    }
}

function openDrawer() {
    document.getElementById('portalDrawerOverlay').classList.add('is-open');
    document.getElementById('portalDrawer').classList.add('is-open');
    document.getElementById('portalDrawerOverlay').setAttribute('aria-hidden', 'false');
    document.getElementById('portalDrawer').setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function cerrarDrawer() {
    document.getElementById('portalDrawerOverlay').classList.remove('is-open');
    document.getElementById('portalDrawer').classList.remove('is-open');
    document.getElementById('portalDrawerOverlay').setAttribute('aria-hidden', 'true');
    document.getElementById('portalDrawer').setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function showTab(tabId, btn) {
    document.querySelectorAll('.portal-tab').forEach(t => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
    });
    document.querySelectorAll('.portal-tab-panel').forEach(p => p.classList.remove('active'));

    const tab = btn || document.querySelector(`.portal-tab[data-tab="${tabId}"]`);
    if (tab) {
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');
    }
    const panel = document.getElementById(`tab-${tabId}`);
    if (panel) panel.classList.add('active');
}

document.querySelectorAll('.portal-tab').forEach(btn => {
    btn.addEventListener('click', () => showTab(btn.dataset.tab, btn));
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') cerrarDrawer();
});

function calcularEdad(fechaNacimiento) {
    if (!fechaNacimiento) return '—';
    const hoy = new Date();
    const cumpleanos = new Date(fechaNacimiento);
    let edadAnios = hoy.getFullYear() - cumpleanos.getFullYear();
    let edadMeses = hoy.getMonth() - cumpleanos.getMonth();
    
    if (edadMeses < 0 || (edadMeses === 0 && hoy.getDate() < cumpleanos.getDate())) {
        edadAnios--;
        edadMeses += 12;
    }
    
    if (edadAnios > 0) {
        return `${edadAnios} año${edadAnios > 1 ? 's' : ''}${edadMeses > 0 ? `, ${edadMeses} mes${edadMeses > 1 ? 'es' : ''}` : ''}`;
    }
    return `${edadMeses} mes${edadMeses !== 1 ? 'es' : ''}`;
}

function renderSummary(mascota) {
    const edad = calcularEdad(mascota.fecha_nacimiento);
    const hc = mascota.numero_historia_clinica || '—';
    const sexo = mascota.sexo || '—';
    const peso = mascota.peso ? mascota.peso + ' kg' : '—';
    
    document.getElementById('drawerPetSummary').innerHTML = `
        <div class="portal-summary-grid">
            <div class="portal-summary-item">
                <span>H. Clínica</span>
                <strong>${escapeHtml(hc)}</strong>
            </div>
            <div class="portal-summary-item">
                <span>Edad</span>
                <strong>${escapeHtml(edad)}</strong>
            </div>
            <div class="portal-summary-item">
                <span>Peso</span>
                <strong>${escapeHtml(peso)}</strong>
            </div>
            <div class="portal-summary-item">
                <span>Sexo</span>
                <strong>${escapeHtml(sexo)}</strong>
            </div>
        </div>
    `;
}

function toggleAccordion(card) {
    const body = card.querySelector('.accordion-body');
    const icon = card.querySelector('.accordion-icon');
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
        card.classList.add('active');
    } else {
        body.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
        card.classList.remove('active');
    }
}

async function verDetalle(id) {
    openDrawer();
    showTab('historial', document.querySelector('.portal-tab[data-tab="historial"]'));

    document.getElementById('drawerPetTitle').innerHTML = 'Cargando…';
    document.getElementById('drawerPetSubtitle').textContent = '';
    document.getElementById('drawerPetSummary').innerHTML = '';
    document.getElementById('historialContent').innerHTML = '<div class="portal-loading">Cargando historial…</div>';
    document.getElementById('citasContent').innerHTML = '';
    document.getElementById('vacunasContent').innerHTML = '';
    document.getElementById('desparasitacionesContent').innerHTML = '';

    try {
        const res = await (await fetch(`index.php?action=ver_detalle_mascota_propietario_ajax&id_mascota=${id}`)).json();
        if (!res.success) {
            alert(res.message || 'Error al obtener detalles');
            cerrarDrawer();
            return;
        }

        const m = res.mascota;
        
        const photoUrl = m.url_foto ? 'uploads/mascotas/' + m.url_foto : null;
        let avatarHtml = '';
        if (photoUrl) {
            avatarHtml = `<img src="${photoUrl}" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--z-primary-soft); margin-right: 0.5rem; vertical-align: middle;">`;
        } else {
            avatarHtml = `<div style="width: 38px; height: 38px; border-radius: 50%; background: var(--z-primary-soft); color: var(--z-primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 700; margin-right: 0.5rem; vertical-align: middle;"><i class="ri-baidu-line"></i></div>`;
        }
        
        document.getElementById('drawerPetTitle').innerHTML = `${avatarHtml}<span style="vertical-align: middle;">${escapeHtml(m.nombre)}</span>`;
        document.getElementById('drawerPetSubtitle').textContent = `${escapeHtml(m.especie)} · ${escapeHtml(m.raza)}`;
        
        renderSummary(m);

        // Historial
        if (!res.historial.length) {
            document.getElementById('historialContent').innerHTML = `
                <div class="portal-empty-inline">
                     <i class="ri-heart-pulse-line" style="font-size: 2rem; opacity: 0.35; display: block; margin-bottom: 0.5rem;"></i>
                     <p>Aún no hay consultas registradas para ${escapeHtml(m.nombre)}.</p>
                </div>`;
        } else {
            let html = '<div class="portal-timeline">';
            res.historial.forEach(h => {
                let filesHtml = '';
                if (h.archivos && h.archivos.length > 0) {
                    filesHtml += `<div class="portal-timeline-files" style="margin-top: 0.75rem; border-top: 1px dashed rgba(0,0,0,0.1); padding-top: 0.5rem;">`;
                    filesHtml += `<strong style="font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 0.25rem;">Archivos adjuntos:</strong>`;
                    h.archivos.forEach(file => {
                        filesHtml += `
                        <a href="${escapeHtml(file.ruta_archivo)}" target="_blank" class="portal-file-link" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; color: var(--z-primary); text-decoration: none; margin-right: 1rem; background: rgba(0,82,255,0.05); padding: 0.25rem 0.5rem; border-radius: 6px; transition: background 0.2s;">
                            <i class="ri-file-pdf-line"></i> ${escapeHtml(file.nombre_original)}
                        </a>`;
                    });
                    filesHtml += `</div>`;
                }

                html += `
                <div class="portal-timeline-item">
                    <div class="portal-timeline-date">${formatFechaHora(h.fecha_hora)}</div>
                    <div class="portal-timeline-card accordion-card" onclick="toggleAccordion(this)" style="cursor: pointer;">
                        <div class="accordion-header" style="display: flex; align-items: center; justify-content: space-between;">
                            <h4 style="margin: 0; font-size: 0.9rem; font-weight: 800; color: var(--z-text);">${escapeHtml(h.motivo_consulta)}</h4>
                            <i class="ri-arrow-down-s-line accordion-icon" style="font-size: 1.25rem; transition: transform 0.2s; color: var(--z-text-muted);"></i>
                        </div>
                        <div class="accordion-body" style="display: none; margin-top: 0.75rem; border-top: 1px solid var(--z-border); padding-top: 0.75rem;">
                            <p style="font-size: 0.8rem; color: var(--z-text-muted); margin-bottom: 0.35rem;"><strong>Diagnóstico:</strong> ${escapeHtml(h.diagnostico)}</p>
                            <p style="font-size: 0.8rem; color: var(--z-text-muted); margin-bottom: 0.35rem;"><strong>Tratamiento:</strong> ${escapeHtml(h.plan_tratamiento)}</p>
                            <p style="font-size: 0.8rem; color: var(--z-text-muted); margin-bottom: 0.35rem;"><strong>Veterinario:</strong> ${escapeHtml(h.veterinario)}</p>
                            ${filesHtml}
                        </div>
                    </div>
                </div>`;
            });
            html += '</div>';
            document.getElementById('historialContent').innerHTML = html;
        }

        // Citas
        if (!res.citas.length) {
            document.getElementById('citasContent').innerHTML = `
                <div class="portal-empty-inline">
                    <i class="ri-calendar-line" style="font-size: 2rem; opacity: 0.35; display: block; margin-bottom: 0.5rem;"></i>
                    <p>No hay citas programadas.</p>
                </div>`;
        } else {
            let html = '';
            res.citas.forEach(c => {
                let cancelBtn = '';
                if (c.estado === 'pendiente' || c.estado === 'confirmada' || c.estado === 'programada') {
                    cancelBtn = `<button type="button" class="portal-btn-cancel" onclick="cancelarCitaPortal(${c.id_cita})" style="margin-top: 0.5rem; background: none; border: 1px solid #ef4444; color: #ef4444; padding: 0.25rem 0.75rem; border-radius: 8px; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 500; transition: all 0.2s;"><i class="ri-close-circle-line"></i> Cancelar Cita</button>`;
                }

                html += `
                <div class="portal-list-item">
                    <div class="portal-list-icon portal-list-icon--cita"><i class="ri-calendar-check-line"></i></div>
                    <div class="portal-list-body">
                        <strong>${formatFecha(c.fecha)} · ${escapeHtml((c.hora || '').substring(0, 5))}</strong>
                        <p>${escapeHtml(c.motivo)}</p>
                        ${c.veterinario_nombre ? `<p>Veterinario: ${escapeHtml(c.veterinario_nombre)}</p>` : ''}
                        ${badgeEstado(c.estado)}
                        ${cancelBtn}
                    </div>
                </div>`;
            });
            document.getElementById('citasContent').innerHTML = html;
        }

        // Vacunas
        if (!res.vacunas.length) {
            document.getElementById('vacunasContent').innerHTML = `
                <div class="portal-empty-inline">
                    <i class="ri-syringe-line" style="font-size: 2rem; opacity: 0.35; display: block; margin-bottom: 0.5rem;"></i>
                    <p>No hay vacunas registradas.</p>
                </div>`;
        } else {
            let html = '';
            res.vacunas.forEach(v => {
                html += `
                <div class="portal-list-item">
                    <div class="portal-list-icon portal-list-icon--vacuna"><i class="ri-syringe-line"></i></div>
                    <div class="portal-list-body">
                        <strong>${escapeHtml(v.nombre_vacuna)}</strong>
                        <p>Aplicada: ${formatFecha(v.fecha_aplicacion)}</p>
                        ${v.fecha_proxima_dosis ? `<p style="color:var(--z-primary);font-weight:600;">Próxima dosis: ${formatFecha(v.fecha_proxima_dosis)}</p>` : ''}
                        ${v.laboratorio ? `<p>Laboratorio: ${escapeHtml(v.laboratorio)}</p>` : ''}
                    </div>
                </div>`;
            });
            document.getElementById('vacunasContent').innerHTML = html;
        }

        // Desparasitaciones
        if (!res.desparasitaciones || !res.desparasitaciones.length) {
            document.getElementById('desparasitacionesContent').innerHTML = `
                <div class="portal-empty-inline">
                    <i class="ri-capsule-line" style="font-size: 2rem; opacity: 0.35; display: block; margin-bottom: 0.5rem;"></i>
                    <p>No hay desparasitaciones registradas.</p>
                </div>`;
        } else {
            let html = '';
            res.desparasitaciones.forEach(d => {
                html += `
                <div class="portal-list-item">
                    <div class="portal-list-icon portal-list-icon--desparasitacion"><i class="ri-capsule-line"></i></div>
                    <div class="portal-list-body">
                        <strong>${escapeHtml(d.producto)} (${escapeHtml(d.tipo)})</strong>
                        <p>Aplicada: ${formatFecha(d.fecha_aplicacion)}</p>
                        ${d.fecha_proxima ? `<p style="color:rgb(245, 158, 11);font-weight:600;">Próxima dosis: ${formatFecha(d.fecha_proxima)} (${escapeHtml(d.periodicidad)})</p>` : ''}
                        ${d.observaciones ? `<p>Observaciones: ${escapeHtml(d.observaciones)}</p>` : ''}
                    </div>
                </div>`;
            });
            document.getElementById('desparasitacionesContent').innerHTML = html;
        }

    } catch (e) {
        console.error(e);
        alert('Error al cargar los datos de la mascota');
        cerrarDrawer();
    }
}

/* Lógica de Agendamiento desde el Portal (HU-26) */
document.addEventListener('DOMContentLoaded', () => {
    // Al iniciar, cargar el contador de notificaciones de la campana
    loadPortalAlerts();

    const bookingModal = document.getElementById('portalBookingModal');
    const openBtns = document.querySelectorAll('#btnOpenBookingModal');
    const closeBtn = document.getElementById('btnCloseBookingModal');
    const form = document.getElementById('portalBookingForm');
    const tipoCitaSelect = document.getElementById('booking_tipo_cita');
    const vetSelect = document.getElementById('booking_veterinario');
    const dateInput = document.getElementById('booking_fecha');
    const horaSelect = document.getElementById('booking_hora');

    if (!bookingModal) return;

    // Función para abrir
    const abrirBooking = async () => {
        bookingModal.style.display = 'flex';
        form.reset();
        horaSelect.innerHTML = '<option value="">Elige fecha...</option>';

        try {
            // Cargar tipos de cita
            const resTipos = await (await fetch('index.php?action=portal_get_tipos_cita_ajax')).json();
            if (resTipos.success) {
                tipoCitaSelect.innerHTML = '<option value="">Selecciona...</option>';
                resTipos.tipos.forEach(t => {
                    tipoCitaSelect.innerHTML += `<option value="${t.id_tipo_cita}" data-duracion="${t.duracion_minutos}">${escapeHtml(t.nombre_tipo)} (${t.duracion_minutos} min)</option>`;
                });
            }

            // Cargar veterinarios
            const resVets = await (await fetch('index.php?action=portal_get_vets_ajax')).json();
            vetSelect.innerHTML = '<option value="">Selecciona...</option>';
            resVets.forEach(v => {
                vetSelect.innerHTML += `<option value="${v.documento}">Dr(a). ${escapeHtml(v.nombre_completo)}</option>`;
            });

        } catch (e) {
            console.error('Error al cargar catálogos:', e);
        }
    };

    // Registrar clicks en todos los botones de abrir (incluyendo el central flotante)
    openBtns.forEach(btn => btn.addEventListener('click', abrirBooking));

    closeBtn.addEventListener('click', () => {
        bookingModal.style.display = 'none';
    });

    bookingModal.addEventListener('click', (e) => {
        if (e.target === bookingModal) {
            bookingModal.style.display = 'none';
        }
    });

    // Cargar horas disponibles al elegir veterinario o fecha
    const cargarHoras = async () => {
        const vet = vetSelect.value;
        const fecha = dateInput.value;
        const tipoCita = tipoCitaSelect.value;

        if (!vet || !fecha || !tipoCita) return;

        const duracion = tipoCitaSelect.options[tipoCitaSelect.selectedIndex].dataset.duracion || 30;

        horaSelect.innerHTML = '<option value="">Cargando horas...</option>';

        try {
            const url = `index.php?action=get_horas_disponibles_ajax&doc_veterinario=${vet}&fecha=${fecha}&duracion_minutos=${duracion}`;
            const res = await (await fetch(url)).json();

            if (res.success && res.horas && res.horas.length > 0) {
                horaSelect.innerHTML = '<option value="">Selecciona hora...</option>';
                res.horas.forEach(h => {
                    horaSelect.innerHTML += `<option value="${h}">${h}</option>`;
                });
            } else {
                horaSelect.innerHTML = '<option value="">Sin horarios disponibles</option>';
            }
        } catch (e) {
            console.error(e);
            horaSelect.innerHTML = '<option value="">Error al cargar horas</option>';
        }
    };

    vetSelect.addEventListener('change', cargarHoras);
    dateInput.addEventListener('change', cargarHoras);
    tipoCitaSelect.addEventListener('change', cargarHoras);

    // Procesar formulario
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.querySelector('span').textContent = 'Agendando...';

        try {
            const fd = new FormData(form);
            const res = await (await fetch('index.php?action=portal_agendar_cita_ajax', {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })).json();

            if (res.success) {
                if (res.id_cita) {
                    const mailFd = new FormData();
                    mailFd.append('id_cita', res.id_cita);
                    mailFd.append('tipo', 'confirmacion_nueva');
                    fetch('index.php?action=enviar_email_ajax', {
                        method: 'POST',
                        body: mailFd
                    }).catch(console.error);
                }

                Swal.fire({
                    icon: 'success',
                    title: '¡Cita Reservada!',
                    text: res.message || 'Tu cita ha sido agendada y confirmada con éxito.',
                    confirmButtonColor: '#5560FF'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo agendar',
                    text: res.message || 'Inténtalo nuevamente.',
                    confirmButtonColor: '#5560FF'
                });
                submitBtn.disabled = false;
                submitBtn.querySelector('span').textContent = 'Confirmar Cita';
            }
        } catch (error) {
            console.error('Error al agendar cita:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de Red',
                text: 'No pudimos conectarnos con el servidor. Inténtalo más tarde.',
                confirmButtonColor: '#5560FF'
            });
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = 'Confirmar Cita';
        }
    });
});

/* Función para cancelar cita desde el portal (Propietario) */
async function cancelarCitaPortal(idCita) {
    const result = await Swal.fire({
        title: '¿Estás seguro?',
        text: "Esta acción cancelará tu cita programada.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, cancelar cita',
        cancelButtonText: 'No, mantener'
    });

    if (result.isConfirmed) {
        try {
            const form = new FormData();
            form.append('id_cita', idCita);
            const res = await (await fetch('index.php?action=cancelar_cita_ajax', {
                method: 'POST',
                body: form,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })).json();

            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Cita cancelada',
                    text: res.message || 'La cita ha sido cancelada.',
                    confirmButtonColor: '#5560FF'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.message || 'No se pudo cancelar la cita.',
                    confirmButtonColor: '#5560FF'
                });
            }
        } catch (e) {
            console.error(e);
            Swal.fire({
                icon: 'error',
                title: 'Error de Red',
                text: 'No pudimos conectarnos con el servidor.',
                confirmButtonColor: '#5560FF'
            });
        }
    }
}
