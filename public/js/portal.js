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

/* Editar Datos de Contacto Inline */
function toggleContactEditPortal() {
    const section = document.getElementById('contactEditSection');
    const icon = document.getElementById('iconToggleContact');
    if (section) {
        if (section.style.display === 'none' || section.style.display === '') {
            section.style.display = 'block';
            if (icon) icon.className = 'ri-arrow-up-s-line';
            if (typeof section.animate === 'function') {
                section.animate([
                    { opacity: 0, transform: 'translateY(-10px)' },
                    { opacity: 1, transform: 'translateY(0)' }
                ], { duration: 300, easing: 'ease-out' });
            }
        } else {
            section.style.display = 'none';
            if (icon) icon.className = 'ri-arrow-down-s-line';
        }
    }
}

async function submitContactEditPortal() {
    const email = document.getElementById('portal_contact_email').value.trim();
    const phone = document.getElementById('portal_contact_phone').value.trim();

    if (!email || !phone) {
        Swal.fire({
            icon: 'error',
            title: 'Campos requeridos',
            text: 'Por favor complete todos los campos.',
            confirmButtonColor: '#5560FF'
        });
        return;
    }

    const btn = document.getElementById('btnSubmitContactEdit');
    const btnText = btn ? btn.querySelector('span') : null;
    
    if (btn && btnText) {
        btn.disabled = true;
        btnText.textContent = 'Actualizando...';
    }

    try {
        const formData = new FormData();
        formData.append('email', email);
        formData.append('telefono', phone);

        const res = await (await fetch('index.php?action=portal_actualizar_datos_contacto_ajax', {
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
                text: res.message || 'Datos de contacto actualizados correctamente.',
                confirmButtonColor: '#5560FF'
            });
            
            const emailSpan = document.getElementById('profileEmailVal');
            const phoneSpan = document.getElementById('profilePhoneVal');
            if (emailSpan) emailSpan.textContent = res.email;
            if (phoneSpan) phoneSpan.textContent = res.telefono;

            toggleContactEditPortal();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: res.message || 'No se pudieron actualizar los datos.',
                confirmButtonColor: '#5560FF'
            });
        }
    } catch (e) {
        console.error(e);
        Swal.fire({
            icon: 'error',
            title: 'Error de Red',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#5560FF'
        });
    } finally {
        if (btn && btnText) {
            btn.disabled = false;
            btnText.textContent = 'Guardar Cambios';
        }
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
    const currentEl = document.getElementById('portal_current_password');
    const current = currentEl ? currentEl.value : '';
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
    // Desactivar todas las pantallas del portal
    document.querySelectorAll('.app-screen').forEach(screen => {
        screen.classList.remove('active');
    });
    // Activar la pantalla de detalle de mascota
    const detailScreen = document.getElementById('screen-pet-detail');
    if (detailScreen) {
        detailScreen.classList.add('active');
    }
    // Desactivar los botones del nav inferior para indicar subnivel
    document.querySelectorAll('.mobile-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cerrarDrawer() {
    // Regresar a la pantalla de Inicio
    switchTab('home');
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
        window.activePetData = m;
        
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

/* --- Centralización de Modales con Principios SOLID (SRP) --- */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.style.display = 'flex';
    // Forzar reflow para que la transición CSS se ejecute correctamente
    modal.offsetHeight; 
    modal.classList.add('is-open');
    const drawer = modal.querySelector('.portal-drawer');
    if (drawer) drawer.classList.add('is-open');
    document.body.style.overflow = 'hidden';
}
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    const drawer = modal.querySelector('.portal-drawer');
    if (drawer) drawer.classList.remove('is-open');
    modal.classList.remove('is-open');
    setTimeout(() => {
        modal.style.display = 'none';
        if (!document.querySelector('.portal-drawer-overlay.is-open')) {
            document.body.style.overflow = '';
        }
    }, 300);
}

// Función para cambiar de pestaña en el panel "Mi Agenda de Salud" del Home
function switchAgendaTab(tabId, btn) {
    // Ocultar todos los contenidos
    document.querySelectorAll('.agenda-tab-content').forEach(el => {
        el.style.display = 'none';
    });
    // Mostrar el seleccionado
    const target = document.getElementById(tabId);
    if (target) target.style.display = 'block';

    // Desactivar todos los botones
    document.querySelectorAll('.agenda-tab-btn').forEach(b => {
        b.style.background = 'transparent';
        b.style.color = 'var(--z-text-muted)';
        b.style.boxShadow = 'none';
    });

    // Activar el botón seleccionado
    if (btn) {
        btn.style.background = '#ffffff';
        btn.style.color = 'var(--z-primary)';
        btn.style.boxShadow = '0 2px 6px rgba(0,0,0,0.05)';
    }
}

// Función para mostrar modales tipo Bottom Sheet minimalistas (Estilo Tik Tok) en el Portal
function showTikTokModal({ title, message, isConfirm, onConfirm, onCancel }) {
    const existing = document.getElementById('tiktok-micro-modal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'tiktok-micro-modal';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: flex;
        align-items: flex-end;
        justify-content: center;
        z-index: 100000;
        opacity: 0;
        transition: opacity 0.25s ease;
    `;

    const container = document.createElement('div');
    container.style.cssText = `
        background: #ffffff;
        width: 100%;
        max-width: 480px;
        border-radius: 28px 28px 0 0;
        padding: 1.25rem 1.5rem 2.25rem;
        box-sizing: border-box;
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.34, 1.3, 0.64, 1);
        text-align: center;
        box-shadow: 0 -10px 25px rgba(0, 0, 0, 0.1);
    `;

    // Barra superior decorativa del Drawer
    const handle = document.createElement('div');
    handle.style.cssText = `
        width: 36px;
        height: 4px;
        background: #e2e8f0;
        border-radius: 2px;
        margin: 0 auto 1.25rem;
    `;
    container.appendChild(handle);

    if (title) {
        const titleEl = document.createElement('h4');
        titleEl.textContent = title;
        titleEl.style.cssText = `
            margin: 0 0 0.5rem;
            font-size: 1.1rem;
            font-weight: 800;
            color: #0f172a;
            font-family: inherit;
        `;
        container.appendChild(titleEl);
    }

    if (message) {
        const msgEl = document.createElement('p');
        msgEl.textContent = message;
        msgEl.style.cssText = `
            margin: 0 0 1.5rem;
            font-size: 0.88rem;
            color: #64748b;
            line-height: 1.45;
            font-family: inherit;
            white-space: pre-line;
        `;
        container.appendChild(msgEl);
    }

    const btnWrapper = document.createElement('div');
    btnWrapper.style.cssText = `
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    `;

    const mainBtn = document.createElement('button');
    mainBtn.textContent = isConfirm ? 'Sí, continuar' : 'Entendido';
    mainBtn.style.cssText = `
        width: 100%;
        background: linear-gradient(135deg, var(--z-primary) 0%, var(--z-primary-dark) 100%);
        color: #ffffff;
        border: none;
        border-radius: 14px;
        padding: 0.85rem;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        outline: none;
        box-shadow: 0 4px 12px rgba(0, 82, 255, 0.15);
        font-family: inherit;
    `;
    mainBtn.onclick = () => {
        close();
        if (onConfirm) onConfirm();
    };
    btnWrapper.appendChild(mainBtn);

    if (isConfirm) {
        const cancelBtn = document.createElement('button');
        cancelBtn.textContent = 'No, cancelar';
        cancelBtn.style.cssText = `
            width: 100%;
            background: #f1f5f9;
            color: #475569;
            border: none;
            border-radius: 14px;
            padding: 0.85rem;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            outline: none;
            font-family: inherit;
        `;
        cancelBtn.onclick = () => {
            close();
            if (onCancel) onCancel();
        };
        btnWrapper.appendChild(cancelBtn);
    }

    container.appendChild(btnWrapper);
    overlay.appendChild(container);
    document.body.appendChild(overlay);

    // Animar entrada
    setTimeout(() => {
        overlay.style.opacity = '1';
        container.style.transform = 'translateY(0)';
    }, 15);

    const close = () => {
        overlay.style.opacity = '0';
        container.style.transform = 'translateY(100%)';
        setTimeout(() => overlay.remove(), 250);
    };
}

/* Lógica de Agendamiento desde el Portal (HU-26) */
document.addEventListener('DOMContentLoaded', () => {
    // Registración de navegación de Agenda
    const navAgenda = document.getElementById('nav-agenda');
    if (navAgenda) {
        navAgenda.addEventListener('click', () => {
            switchTab('agenda');
        });
    }

    const btnAgendaCitas = document.getElementById('btn-agenda-citas');
    const btnAgendaSalud = document.getElementById('btn-agenda-salud');
    if (btnAgendaCitas && btnAgendaSalud) {
        btnAgendaCitas.addEventListener('click', () => {
            switchAgendaTab('agenda-citas', btnAgendaCitas);
        });
        btnAgendaSalud.addEventListener('click', () => {
            switchAgendaTab('agenda-salud', btnAgendaSalud);
        });
    }

    // --- LÓGICA COMPLETA DE CALENDARIO, FILTROS Y TIMELINE (V1.6.0) ---
    let selectedPetId = 'all';
    let selectedCalendarDate = null; // Formato YYYY-MM-DD
    const today = new Date();
    let currentCalMonth = today.getMonth(); // 0-11
    let currentCalYear = today.getFullYear();

    const chips = document.querySelectorAll('.pet-chip');
    const printBtn = document.getElementById('btnImprimirHistorial');

    // Nombres de meses en español
    const monthNames = [
        "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
    ];

    // Cargar listas filtradas de salud (Vacunas y Controles)
    const renderSaludLists = () => {
        const proximasList = document.getElementById('saludProximasList');
        const historialList = document.getElementById('saludHistorialList');
        const proximasContainer = document.getElementById('agenda-salud-proximas');
        if (!proximasList || !historialList) return;

        const eventos = window.portalAgendaEventos || [];
        
        // Filtrar por mascota y por fecha si aplica
        let filtrados = eventos.filter(e => {
            const matchPet = (selectedPetId === 'all' || e.id_mascota == selectedPetId);
            let matchDate = true;
            if (selectedCalendarDate) {
                matchDate = (e.fecha === selectedCalendarDate || e.proxima === selectedCalendarDate);
            }
            return matchPet && matchDate;
        });

        let htmlProximas = '';
        let htmlHistorial = '';

        filtrados.forEach(e => {
            if (e.tipo === 'cita') {
                const statusStyle = e.estado === 'completada' ? 'background:var(--z-success-soft);color:var(--z-success);' : (e.estado === 'programada' ? 'background:var(--z-primary-soft);color:var(--z-primary);' : '');
                htmlHistorial += `
                <div class="agenda-list-item" style="cursor: pointer; border-left: 3px solid var(--z-primary);" onclick="mostrarDetalleCita(${e.id_cita})">
                    <div class="agenda-icon-wrap" style="background:var(--z-primary-soft); color:var(--z-primary); width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="ri-calendar-event-line"></i>
                    </div>
                    <div class="agenda-item-info" style="margin-left:0.75rem;">
                        <h4>${escapeHtml(e.nombre_mascota)}</h4>
                        <p class="agenda-item-type">Cita: <strong>${escapeHtml(e.titulo)}</strong> · ${escapeHtml(e.detalle)}</p>
                        <span class="agenda-item-date">Fecha: ${formatFecha(e.fecha)} · Hora: ${escapeHtml(e.hora)}</span>
                    </div>
                    <div class="agenda-item-actions" style="margin-left: auto; display: flex; align-items: center;">
                        <span class="status-badge" style="${statusStyle}">${escapeHtml(e.estado)}</span>
                    </div>
                </div>`;
                return;
            }

            const isVacuna = e.tipo === 'vacuna';
            const iconClass = isVacuna ? 'ri-syringe-line' : 'ri-capsule-line';
            const bgClass = isVacuna ? 'bg-vacuna' : 'bg-control';
            
            // Si tiene fecha de próxima dosis y es en el futuro/hoy, va a próximas dosis
            if (e.proxima) {
                const diffTime = new Date(e.proxima + 'T12:00:00') - new Date(today.getFullYear(), today.getMonth(), today.getDate());
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                let badgeText = '';
                let badgeStyle = 'background:#f1f5f9; color:#475569;';
                if (diffDays > 0) {
                    badgeText = `Faltan ${diffDays} días`;
                    badgeStyle = 'background:var(--z-primary-soft); color:var(--z-primary);';
                } else if (diffDays === 0) {
                    badgeText = '¡Hoy!';
                    badgeStyle = 'background:var(--z-success-soft); color:var(--z-success);';
                } else {
                    badgeText = `Vencido hace ${Math.abs(diffDays)} días`;
                    badgeStyle = 'background:var(--z-danger-soft); color:var(--z-danger);';
                }

                htmlProximas += `
                <div class="agenda-list-item">
                    <div class="agenda-icon-wrap ${bgClass}">
                        <i class="${iconClass}"></i>
                    </div>
                    <div class="agenda-item-info">
                        <h4>${escapeHtml(e.nombre_mascota)}</h4>
                        <p class="agenda-item-type">Siguiente dosis: <strong>${escapeHtml(e.titulo)}</strong> · ${escapeHtml(e.detalle)}</p>
                        <span class="agenda-item-date" style="color:var(--z-text-muted);">Última aplicación: ${formatFecha(e.fecha)}</span>
                    </div>
                    <div class="agenda-item-next" style="text-align:right;">
                        <span class="status-badge" style="${badgeStyle} font-weight:700;">${badgeText}</span>
                        <span class="next-date" style="display:block; font-size:0.7rem; margin-top:4px;">${formatFecha(e.proxima)}</span>
                    </div>
                </div>`;
            }

            // Historial (aplicación pasada)
            htmlHistorial += `
            <div class="agenda-list-item">
                <div class="agenda-icon-wrap ${bgClass}">
                    <i class="${iconClass}"></i>
                </div>
                <div class="agenda-item-info">
                    <h4>${escapeHtml(e.nombre_mascota)}</h4>
                    <p class="agenda-item-type">${escapeHtml(e.titulo)} · ${escapeHtml(e.detalle)}</p>
                    <span class="agenda-item-date">${isVacuna ? 'Vacuna aplicada' : 'Control realizado'}: ${formatFecha(e.fecha)}</span>
                </div>
            </div>`;
        });

        // Cargar listas
        proximasContainer.style.display = htmlProximas ? 'block' : 'none';
        proximasList.innerHTML = htmlProximas;
        historialList.innerHTML = htmlHistorial || '<div class="agenda-empty-state"><p>No hay historial registrado.</p></div>';
    };

    // --- NAVEGADOR DE MES/AÑO PERSONALIZADO ESTILO WIN11 (V1.6.0) ---
    const calWin11Nav = document.getElementById('calWin11Nav');
    const calMonthTitle = document.getElementById('calMonthTitle');
    const calWin11Title = document.getElementById('calWin11Title');
    const calWin11MonthsGrid = document.getElementById('calWin11MonthsGrid');
    const calWin11YearsGrid = document.getElementById('calWin11YearsGrid');

    let calWin11View = 'months'; // 'months' or 'years'
    let calWin11ViewYear = currentCalYear;

    const shortMonthNames = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

    const renderCalWin11Months = () => {
        if (!calWin11MonthsGrid) return;
        calWin11View = 'months';
        calWin11MonthsGrid.style.display = 'grid';
        calWin11YearsGrid.style.display = 'none';
        calWin11Title.textContent = calWin11ViewYear;

        calWin11MonthsGrid.innerHTML = '';
        shortMonthNames.forEach((name, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'win11-btn';
            if (currentCalYear === calWin11ViewYear && currentCalMonth === idx) {
                btn.classList.add('active');
            }
            btn.textContent = name;
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                currentCalMonth = idx;
                currentCalYear = calWin11ViewYear;
                calWin11Nav.style.display = 'none';
                renderMiniCalendar();
            });
            calWin11MonthsGrid.appendChild(btn);
        });
    };

    const renderCalWin11Years = () => {
        if (!calWin11YearsGrid) return;
        calWin11View = 'years';
        calWin11MonthsGrid.style.display = 'none';
        calWin11YearsGrid.style.display = 'grid';
        
        const startYear = 2020;
        const thisYear = new Date().getFullYear();
        calWin11Title.textContent = `${startYear} - ${thisYear}`;

        calWin11YearsGrid.innerHTML = '';
        for (let y = startYear; y <= thisYear; y++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'win11-btn';
            if (currentCalYear === y) {
                btn.classList.add('active');
            }
            btn.textContent = y;
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                calWin11ViewYear = y;
                renderCalWin11Months();
            });
            calWin11YearsGrid.appendChild(btn);
        }
    };

    if (calMonthTitle && calWin11Nav) {
        calMonthTitle.addEventListener('click', (e) => {
            e.stopPropagation();
            calWin11ViewYear = currentCalYear;
            calWin11Nav.style.display = 'flex';
            renderCalWin11Months();
        });

        calWin11Title.addEventListener('click', (e) => {
            e.stopPropagation();
            if (calWin11View === 'months') {
                renderCalWin11Years();
            } else {
                renderCalWin11Months();
            }
        });
        
        // Clic fuera del nav para cerrarlo
        document.addEventListener('click', (e) => {
            if (calWin11Nav.style.display === 'flex' && !calWin11Nav.contains(e.target)) {
                calWin11Nav.style.display = 'none';
            }
        });
    }

    // Renderizar Cuadrícula del Calendario
    const renderMiniCalendar = () => {
        const grid = document.getElementById('miniCalendarGrid');
        const title = document.getElementById('calMonthTitle');
        if (!grid) return;

        if (title) {
            title.textContent = `${monthNames[currentCalMonth]} ${currentCalYear}`;
        }

        // Obtener datos del mes
        const firstDayIndex = new Date(currentCalYear, currentCalMonth, 1).getDay();
        const totalDays = new Date(currentCalYear, currentCalMonth + 1, 0).getDate();

        let html = '';

        // Rellenar días en blanco del mes anterior
        for (let i = 0; i < firstDayIndex; i++) {
            html += `<div style="height:36px;"></div>`;
        }

        // Eventos para verificar
        const eventos = window.portalAgendaEventos || [];

        // Rellenar días del mes actual
        for (let day = 1; day <= totalDays; day++) {
            const dateStr = `${currentCalYear}-${String(currentCalMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            // Buscar eventos en este día
            const eventosDia = eventos.filter(e => {
                const matchPet = (selectedPetId === 'all' || e.id_mascota == selectedPetId);
                const matchDate = (e.fecha === dateStr || e.proxima === dateStr);
                return matchPet && matchDate;
            });

            // Verificar tipos de eventos para dibujar puntos
            const tieneCita = eventosDia.some(e => e.tipo === 'cita');
            const tieneVacuna = eventosDia.some(e => e.tipo === 'vacuna');
            const tieneControl = eventosDia.some(e => e.tipo === 'control');

            let dotsHtml = '';
            if (tieneCita) dotsHtml += `<span style="width:4px; height:4px; border-radius:50%; background:var(--z-primary);"></span>`;
            if (tieneVacuna) dotsHtml += `<span style="width:4px; height:4px; border-radius:50%; background:var(--z-success);"></span>`;
            if (tieneControl) dotsHtml += `<span style="width:4px; height:4px; border-radius:50%; background:var(--z-warning);"></span>`;

            const isSelected = selectedCalendarDate === dateStr ? 'background:var(--z-primary-soft); color:var(--z-primary); font-weight:800; border:1.5px solid var(--z-primary);' : '';
            const cursorStyle = eventosDia.length > 0 ? 'cursor:pointer;' : 'opacity:0.85;';

            html += `
            <div class="cal-day-cell" data-date="${dateStr}" style="height:36px; border-radius:10px; display:flex; flex-direction:column; align-items:center; justify-content:center; font-size:0.75rem; color:#0f172a; position:relative; font-weight:600; border:1px solid #f1f5f9; ${isSelected} ${cursorStyle}">
                <span>${day}</span>
                <div style="display:flex; gap:2px; position:absolute; bottom:3px;">${dotsHtml}</div>
            </div>`;
        }

        grid.innerHTML = html;

        // Agregar clics a celdas con eventos
        grid.querySelectorAll('.cal-day-cell').forEach(cell => {
            cell.addEventListener('click', () => {
                const date = cell.dataset.date;
                
                // Si ya está seleccionada, resetear filtro de fecha
                if (selectedCalendarDate === date) {
                    resetCalendarDateFilter();
                } else {
                    selectedCalendarDate = date;
                    document.getElementById('calendarFilterAlert').style.display = 'flex';
                    renderMiniCalendar();
                    renderSaludLists();
                }
            });
        });
    };

    // Resetear filtro de fecha del calendario
    window.resetCalendarDateFilter = () => {
        selectedCalendarDate = null;
        document.getElementById('calendarFilterAlert').style.display = 'none';
        renderMiniCalendar();
        renderSaludLists();
    };

    const resetBtn = document.getElementById('btnResetDateFilter');
    if (resetBtn) {
        resetBtn.addEventListener('click', resetCalendarDateFilter);
    }

    // Navegar meses
    const prevMonthBtn = document.getElementById('btnPrevMonth');
    const nextMonthBtn = document.getElementById('btnNextMonth');
    if (prevMonthBtn && nextMonthBtn) {
        prevMonthBtn.addEventListener('click', () => {
            currentCalMonth--;
            if (currentCalMonth < 0) {
                currentCalMonth = 11;
                currentCalYear--;
            }
            renderMiniCalendar();
        });
        nextMonthBtn.addEventListener('click', () => {
            const thisYear = new Date().getFullYear();
            currentCalMonth++;
            if (currentCalMonth > 11) {
                if (currentCalYear < thisYear) {
                    currentCalMonth = 0;
                    currentCalYear++;
                } else {
                    currentCalMonth = 11; // Quedarse en diciembre de este año
                }
            }
            renderMiniCalendar();
        });
    }

    // Filtrar Citas por Mascota
    const filterCitas = () => {
        const citasContainer = document.getElementById('citasListContainer');
        if (!citasContainer) return;

        let visibleCount = 0;
        citasContainer.querySelectorAll('.agenda-list-item').forEach(item => {
            if (selectedPetId === 'all' || item.dataset.petId == selectedPetId) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        // Mostrar estado vacío si no hay citas de esta mascota
        const emptyState = citasContainer.parentElement.querySelector('.agenda-empty-state');
        if (visibleCount === 0) {
            if (!emptyState) {
                const div = document.createElement('div');
                div.className = 'agenda-empty-state temp-empty';
                div.innerHTML = `<i class="ri-calendar-line"></i><p>No hay citas de esta mascota.</p>`;
                citasContainer.style.display = 'none';
                citasContainer.parentElement.appendChild(div);
            }
        } else {
            citasContainer.style.display = 'flex';
            const temp = citasContainer.parentElement.querySelector('.temp-empty');
            if (temp) temp.remove();
        }
    };

    // Registrar Clics en Chips de Mascota
    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            chips.forEach(c => {
                c.classList.remove('active');
                c.style.background = '#f1f5f9';
                c.style.color = '#64748b';
            });
            chip.classList.add('active');
            chip.style.background = 'var(--z-primary)';
            chip.style.color = '#fff';

            selectedPetId = chip.dataset.petId;

            // Mostrar/Ocultar botón de Imprimir Historial
            if (printBtn) {
                if (selectedPetId === 'all') {
                    printBtn.style.display = 'none';
                } else {
                    printBtn.style.display = 'flex';
                    printBtn.href = `index.php?action=portal_imprimir_historial&id_mascota=${selectedPetId}`;
                }
            }

            // Filtrar ambos módulos
            filterCitas();
            resetCalendarDateFilter();
        });
    });

    // Cargar datos por primera vez al abrir
    if (document.getElementById('agenda-salud')) {
        renderMiniCalendar();
        renderSaludLists();
    }

    // Attach cancellation event listeners to all cancel buttons in the Agenda
    document.querySelectorAll('.btn-cancel-agenda').forEach(btn => {
        btn.addEventListener('click', () => {
            cancelarCitaPortal(btn.dataset.id);
        });
    });

    // Al iniciar, cargar el contador de notificaciones de la campana
    loadPortalAlerts();

    // Inicializar Flatpickr personalizado (estilo Win11) para fecha de nacimiento de mascotas y agendamiento
    if (typeof flatpickr !== 'undefined') {
        try {
            let currentLocale = "es";
            if (flatpickr.l10ns && flatpickr.l10ns.es) {
                currentLocale = flatpickr.l10ns.es;
                if (currentLocale.weekdays) {
                    currentLocale.weekdays.shorthand = ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"];
                }
            }

            // Función para renderizar el selector estilo Win11 en el calendario
            const makeWin11Calendar = function(instance) {
                const header = instance.monthNav.querySelector('.flatpickr-current-month');
                if (header) {
                    header.title = 'Seleccionar Mes/Año';
                    header.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const nav = instance.calendarContainer.querySelector('.fp-win11-nav');
                        if (nav) {
                            nav.classList.toggle('active');
                            const isNavActive = nav.classList.contains('active');
                            instance.calendarContainer.querySelector('.flatpickr-prev-month').style.visibility = isNavActive ? 'hidden' : 'visible';
                            instance.calendarContainer.querySelector('.flatpickr-next-month').style.visibility = isNavActive ? 'hidden' : 'visible';
                            if (isNavActive) nav._renderMonthsView();
                        }
                    });
                }

                const nav = document.createElement('div');
                nav.className = 'fp-win11-nav';
                
                const navHeader = document.createElement('div');
                navHeader.className = 'win11-header';
                const navTitle = document.createElement('button');
                navTitle.className = 'win11-title';
                navTitle.type = 'button';
                navHeader.appendChild(navTitle);
                nav.appendChild(navHeader);

                const navContent = document.createElement('div');
                navContent.className = 'win11-content';
                
                const monthsGrid = document.createElement('div');
                monthsGrid.className = 'win11-grid';
                
                const yearsGrid = document.createElement('div');
                yearsGrid.className = 'win11-grid win11-years';
                yearsGrid.style.display = 'none';

                navContent.appendChild(monthsGrid);
                navContent.appendChild(yearsGrid);
                nav.appendChild(navContent);
                
                instance.calendarContainer.appendChild(nav);

                let currentView = 'months'; 
                let viewYear = instance.currentYear;
                const monthNames = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

                function renderMonthsView() {
                    currentView = 'months';
                    monthsGrid.style.display = 'grid';
                    yearsGrid.style.display = 'none';
                    navTitle.innerText = viewYear;
                    
                    monthsGrid.innerHTML = '';
                    const today = new Date();
                    const currentY = today.getFullYear();
                    const currentM = today.getMonth();
                    
                    monthNames.forEach((m, i) => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'win11-btn';
                        
                        if (instance.currentYear === viewYear && instance.currentMonth === i) btn.classList.add('active');
                        
                        // Validar límites del minDate y maxDate configurados
                        const minD = instance.config.minDate;
                        const maxD = instance.config.maxDate;
                        
                        let isAllowed = true;
                        if (maxD && (viewYear > maxD.getFullYear() || (viewYear === maxD.getFullYear() && i > maxD.getMonth()))) {
                            isAllowed = false;
                        }
                        if (minD && (viewYear < minD.getFullYear() || (viewYear === minD.getFullYear() && i < minD.getMonth()))) {
                            isAllowed = false;
                        }

                        if (!isAllowed) {
                            btn.classList.add('disabled');
                            btn.disabled = true;
                        }
                        
                        btn.innerText = m;
                        
                        if (!btn.disabled) {
                            btn.onclick = (e) => {
                                e.stopPropagation();
                                instance.changeYear(viewYear);
                                instance.changeMonth(i, false);
                                nav.classList.remove('active');
                                instance.calendarContainer.querySelector('.flatpickr-prev-month').style.visibility = 'visible';
                                instance.calendarContainer.querySelector('.flatpickr-next-month').style.visibility = 'visible';
                            };
                        }
                        monthsGrid.appendChild(btn);
                    });
                }

                function renderYearsView() {
                    currentView = 'years';
                    monthsGrid.style.display = 'none';
                    yearsGrid.style.display = 'grid';
                    
                    yearsGrid.innerHTML = '';
                    const today = new Date();
                    const currentY = today.getFullYear();
                    
                    const minD = instance.config.minDate;
                    const maxD = instance.config.maxDate;
                    
                    const startY = minD ? minD.getFullYear() : currentY - 100;
                    const endY = maxD ? maxD.getFullYear() : currentY + 10;
                    
                    for (let y = endY; y >= startY; y--) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'win11-btn';
                        if (y === viewYear) btn.classList.add('active');
                        btn.innerText = y;
                        btn.onclick = (e) => {
                            e.stopPropagation();
                            viewYear = y;
                            renderMonthsView();
                        };
                        yearsGrid.appendChild(btn);
                    }
                    
                    setTimeout(() => {
                        const activeBtn = yearsGrid.querySelector('.active');
                        if (activeBtn) activeBtn.scrollIntoView({ block: 'center' });
                    }, 10);
                }

                nav._renderMonthsView = renderMonthsView;
                navTitle.onclick = (e) => {
                    e.stopPropagation();
                    if (currentView === 'months') {
                        renderYearsView();
                    } else {
                        renderMonthsView();
                    }
                };
            };

            // 1. Inicializar para campos de fecha de nacimiento (históricos)
            flatpickr("input[name='fecha_nacimiento'].flatpickr-date", {
                locale: currentLocale,
                dateFormat: "Y-m-d",
                maxDate: "today",
                disableMobile: true,
                altInput: true,
                altFormat: "Y-m-d",
                monthSelectorType: "static",
                onReady: function(selectedDates, dateStr, instance) {
                    makeWin11Calendar(instance);
                }
            });

            // 2. Inicializar para campo de agendamiento de citas (futuro)
            flatpickr("#booking_fecha", {
                locale: currentLocale,
                dateFormat: "Y-m-d",
                minDate: "today",
                disableMobile: true,
                altInput: true,
                altFormat: "Y-m-d",
                monthSelectorType: "static",
                onChange: function(selectedDates, dateStr, instance) {
                    // Disparar carga de horas disponibles al cambiar fecha
                    if (typeof cargarHoras === "function") {
                        cargarHoras();
                    }
                },
                onReady: function(selectedDates, dateStr, instance) {
                    makeWin11Calendar(instance);
                }
            });

        } catch (e) {
            console.error("Error inicializando Flatpickr:", e);
        }
    }

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
        openModal('portalBookingModal');
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

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            closeModal('portalBookingModal');
        });
    }

    bookingModal.addEventListener('click', (e) => {
        if (e.target === bookingModal) {
            closeModal('portalBookingModal');
        }
    });

    // Cargar horas disponibles al elegir veterinario o fecha (Intersección con sugerencias libres de veterinario)
    const cargarHoras = async () => {
        const vet = vetSelect.value;
        const fecha = dateInput.value;
        const tipoCita = tipoCitaSelect.value;

        if (!vet || !fecha || !tipoCita) return;

        const duracion = tipoCitaSelect.options[tipoCitaSelect.selectedIndex].dataset.duracion || 30;

        horaSelect.innerHTML = '<option value="">Cargando horas...</option>';

        try {
            // Petición 1: Horas laborables de la clínica
            const resClinica = await fetch(`index.php?action=get_horas_disponibles_ajax&fecha=${fecha}&intervalo=${duracion}`);
            const dataClinica = await resClinica.json();

            // Petición 2: Horarios libres del veterinario (sugerencias sin colisiones)
            const resVet = await fetch(`index.php?action=get_sugerencias_horario_ajax&doc_veterinario=${vet}&fecha=${fecha}&duracion_minutos=${duracion}`);
            const dataVet = await resVet.json();

            if (dataClinica.success && dataVet.success) {
                const horasLaborales = dataClinica.horas || [];
                const sugerenciasVet = dataVet.sugerencias || [];

                // Intersectar ambas listas
                const horasDisponibles = horasLaborales.filter(h => sugerenciasVet.includes(h));

                if (horasDisponibles.length > 0) {
                    horaSelect.innerHTML = '<option value="">Selecciona hora...</option>';
                    horasDisponibles.forEach(h => {
                        horaSelect.innerHTML += `<option value="${h}">${h}</option>`;
                    });
                } else {
                    horaSelect.innerHTML = '<option value="">Sin horarios disponibles</option>';
                }
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

    // Procesar formulario
    const enviarFormulario = async (ignoreWarning = false) => {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.querySelector('span').textContent = 'Agendando...';

        try {
            const fd = new FormData(form);
            if (ignoreWarning) {
                fd.append('ignore_warning', '1');
            }
            const res = await (await fetch('index.php?action=portal_agendar_cita_ajax', {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })).json();

            if (res.success) {
                closeModal('portalBookingModal');
                if (res.id_cita) {
                    const mailFd = new FormData();
                    mailFd.append('id_cita', res.id_cita);
                    mailFd.append('tipo', 'confirmacion_nueva');
                    fetch('index.php?action=enviar_email_ajax', {
                        method: 'POST',
                        body: mailFd
                    }).catch(console.error);
                }

                showTikTokModal({
                    title: '¡Cita Reservada!',
                    message: res.message || 'Tu cita ha sido agendada y confirmada con éxito.',
                    isConfirm: false,
                    onConfirm: () => {
                        location.reload();
                    }
                });
            } else if (res.has_warning) {
                showTikTokModal({
                    title: 'Cita Duplicada',
                    message: res.message,
                    isConfirm: true,
                    onConfirm: () => {
                        enviarFormulario(true); // Re-submit ignoring warning
                    },
                    onCancel: () => {
                        submitBtn.disabled = false;
                        submitBtn.querySelector('span').textContent = 'Confirmar Cita';
                    }
                });
            } else {
                showTikTokModal({
                    title: 'No se pudo agendar',
                    message: res.message || 'Inténtalo nuevamente.',
                    isConfirm: false,
                    onConfirm: () => {
                        submitBtn.disabled = false;
                        submitBtn.querySelector('span').textContent = 'Confirmar Cita';
                    }
                });
            }
        } catch (error) {
            console.error('Error al agendar cita:', error);
            showTikTokModal({
                title: 'Error de Red',
                message: 'No pudimos conectarnos con el servidor. Inténtalo más tarde.',
                isConfirm: false,
                onConfirm: () => {
                    submitBtn.disabled = false;
                    submitBtn.querySelector('span').textContent = 'Confirmar Cita';
                }
            });
        }
    };

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        enviarFormulario(false);
    });
});

/* Función para cancelar cita desde el portal (Propietario) */
function cancelarCitaPortal(idCita) {
    showTikTokModal({
        title: '¿Estás seguro?',
        message: 'Esta acción cancelará tu cita programada de forma permanente.',
        isConfirm: true,
        onConfirm: async () => {
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
                    showTikTokModal({
                        title: 'Cita cancelada',
                        message: res.message || 'La cita ha sido cancelada.',
                        isConfirm: false,
                        onConfirm: () => {
                            location.reload();
                        }
                    });
                } else {
                    showTikTokModal({
                        title: 'Error',
                        message: res.message || 'No se pudo cancelar la cita.',
                        isConfirm: false
                    });
                }
            } catch (e) {
                console.error(e);
                showTikTokModal({
                    title: 'Error de Red',
                    message: 'No pudimos conectarnos con el servidor.',
                    isConfirm: false
                });
            }
        }
    });
}

/* --- Gestión de Mascotas desde el Portal (Registro y Edición) --- */
document.addEventListener('DOMContentLoaded', () => {
    const addPetModal = document.getElementById('portalAddPetModal');
    const editPetModal = document.getElementById('portalEditPetModal');
    const openAddBtn = document.getElementById('btnOpenAddPetModal');
    const closeAddBtn = document.getElementById('btnCloseAddPetModal');
    const closeEditBtn = document.getElementById('btnCloseEditPetModal');
    const editProfileBtn = document.getElementById('btnEditPetProfile');

    const addForm = document.getElementById('portalAddPetForm');
    const editForm = document.getElementById('portalEditPetForm');

    const addEspecieSelect = document.getElementById('add_pet_especie');
    const editEspecieSelect = document.getElementById('edit_pet_especie');
    const addRazaSelect = document.getElementById('add_pet_raza');
    const editRazaSelect = document.getElementById('edit_pet_raza');

    let especiesCache = [];
    let coloresCache = [];

    // Cargar Catálogos Iniciales (Especies y Colores)
    const cargarCatalogos = async () => {
        try {
            if (especiesCache.length === 0) {
                especiesCache = await (await fetch('index.php?action=listar_especies_ajax')).json();
            }
            if (coloresCache.length === 0) {
                coloresCache = await (await fetch('index.php?action=listar_colores_ajax')).json();
            }

            // Llenar selectores de especie
            [addEspecieSelect, editEspecieSelect].forEach(sel => {
                if (sel && sel.options.length <= 1) {
                    sel.innerHTML = '<option value="">Selecciona...</option>';
                    especiesCache.forEach(esp => {
                        sel.innerHTML += `<option value="${esp.id_especie}">${escapeHtml(esp.nombre_especie)}</option>`;
                    });
                }
            });

            // Llenar contenedores de colores
            ['add_colores_container', 'edit_colores_container'].forEach(cid => {
                const container = document.getElementById(cid);
                if (container && container.children.length === 0) {
                    container.innerHTML = '';
                    coloresCache.forEach(col => {
                        container.innerHTML += `
                        <label class="color-pill-checkbox" style="display:inline-flex; align-items:center; background:#f1f5f9; padding:0.35rem 0.65rem; border-radius:12px; font-size:0.75rem; color:#475569; cursor:pointer; font-weight:600; border:1px solid #e2e8f0; user-select:none; margin: 2px;">
                            <input type="checkbox" name="colores[]" value="${col.id_color}" style="margin-right:0.35rem;">
                            ${escapeHtml(col.nombre_color)}
                        </label>`;
                    });
                }
            });
        } catch (e) {
            console.error('Error al cargar catálogos:', e);
        }
    };

    // Filtrar razas dinámicamente
    const cargarRazas = async (idEspecie, targetSelect, selectedId = null) => {
        if (!idEspecie) {
            targetSelect.innerHTML = '<option value="">Seleccione especie...</option>';
            return;
        }
        try {
            const razas = await (await fetch(`index.php?action=listar_razas_ajax&id_especie=${idEspecie}`)).json();
            targetSelect.innerHTML = '<option value="">Selecciona raza...</option>';
            razas.forEach(r => {
                const sel = (selectedId && selectedId == r.id_raza) ? 'selected' : '';
                targetSelect.innerHTML += `<option value="${r.id_raza}" ${sel}>${escapeHtml(r.nombre_raza)}</option>`;
            });
            targetSelect.innerHTML += `<option value="Otra">Otra / No listada</option>`;
        } catch (e) {
            console.error(e);
        }
    };

    // Eventos Especie -> Razas
    if (addEspecieSelect) {
        addEspecieSelect.addEventListener('change', (e) => {
            cargarRazas(e.target.value, addRazaSelect);
            document.getElementById('add_nueva_raza_wrapper').style.display = 'none';
        });
    }
    if (editEspecieSelect) {
        editEspecieSelect.addEventListener('change', (e) => {
            cargarRazas(e.target.value, editRazaSelect);
            document.getElementById('edit_nueva_raza_wrapper').style.display = 'none';
        });
    }

    // Eventos Raza -> Mostrar input nueva raza si elige 'Otra'
    if (addRazaSelect) {
        addRazaSelect.addEventListener('change', (e) => {
            document.getElementById('add_nueva_raza_wrapper').style.display = e.target.value === 'Otra' ? 'block' : 'none';
        });
    }
    if (editRazaSelect) {
        editRazaSelect.addEventListener('change', (e) => {
            document.getElementById('edit_nueva_raza_wrapper').style.display = e.target.value === 'Otra' ? 'block' : 'none';
        });
    }

    // Abrir Modal Registrar
    if (openAddBtn) {
        openAddBtn.addEventListener('click', async () => {
            openModal('portalAddPetModal');
            addForm.reset();
            document.getElementById('add_nueva_raza_wrapper').style.display = 'none';
            await cargarCatalogos();
        });
    }

    // Abrir Modal Editar
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', async () => {
            const pet = window.activePetData;
            if (!pet) return;

            openModal('portalEditPetModal');
            editForm.reset();
            document.getElementById('edit_nueva_raza_wrapper').style.display = 'none';
            
            await cargarCatalogos();

            // Rellenar datos
            document.getElementById('edit_pet_id').value = pet.id_mascota;
            document.getElementById('edit_pet_nombre').value = pet.nombre;
            document.getElementById('edit_pet_sexo').value = pet.sexo;
            document.getElementById('edit_pet_peso').value = pet.peso;
            document.getElementById('edit_pet_nacimiento').value = pet.fecha_nacimiento || '';

            // Seleccionar especie y cargar sus razas
            editEspecieSelect.value = pet.id_especie;
            await cargarRazas(pet.id_especie, editRazaSelect, pet.id_raza);

            // Rellenar colores seleccionados
            const petColoresIds = pet.colores_ids ? pet.colores_ids.split(',') : [];
            const colorChecks = editForm.querySelectorAll('input[name="colores[]"]');
            colorChecks.forEach(chk => {
                chk.checked = petColoresIds.includes(chk.value);
            });
        });
    }

    // Cerrar Modales
    if (closeAddBtn) closeAddBtn.addEventListener('click', () => closeModal('portalAddPetModal'));
    if (closeEditBtn) closeEditBtn.addEventListener('click', () => closeModal('portalEditPetModal'));
    
    const closeDetalleCitaBtn = document.getElementById('btnCloseCitaDetalleModal');
    if (closeDetalleCitaBtn) {
        closeDetalleCitaBtn.addEventListener('click', () => closeModal('portalCitaDetalleModal'));
    }

    if (addPetModal) {
        addPetModal.addEventListener('click', (e) => {
            if (e.target === addPetModal) closeModal('portalAddPetModal');
        });
    }
    if (editPetModal) {
        editPetModal.addEventListener('click', (e) => {
            if (e.target === editPetModal) closeModal('portalEditPetModal');
        });
    }
    const citaDetalleModal = document.getElementById('portalCitaDetalleModal');
    if (citaDetalleModal) {
        citaDetalleModal.addEventListener('click', (e) => {
            if (e.target === citaDetalleModal) closeModal('portalCitaDetalleModal');
        });
    }

    // Procesar Registro de Mascota
    if (addForm) {
        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = addForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.querySelector('span').textContent = 'Registrando...';

            try {
                const fd = new FormData(addForm);
                const res = await (await fetch('index.php?action=portal_registrar_mascota_ajax', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })).json();

                if (res.success) {
                    closeModal('portalAddPetModal');
                    Swal.fire({
                        icon: 'success',
                        title: '¡Mascota Registrada!',
                        text: 'Tu nuevo compañero ha sido registrado exitosamente.',
                        confirmButtonColor: '#5560FF'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'No se pudo completar el registro.',
                        confirmButtonColor: '#5560FF'
                    });
                    submitBtn.disabled = false;
                    submitBtn.querySelector('span').textContent = 'Registrar Mascota';
                }
            } catch (err) {
                console.error(err);
                submitBtn.disabled = false;
                submitBtn.querySelector('span').textContent = 'Registrar Mascota';
            }
        });
    }

    // Procesar Edición de Mascota
    if (editForm) {
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = editForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.querySelector('span').textContent = 'Guardando...';

            try {
                const fd = new FormData(editForm);
                const res = await (await fetch('index.php?action=portal_actualizar_mascota_ajax', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })).json();

                if (res.success) {
                    closeModal('portalEditPetModal');
                    Swal.fire({
                        icon: 'success',
                        title: '¡Cambios Guardados!',
                        text: 'La información de tu mascota ha sido actualizada.',
                        confirmButtonColor: '#5560FF'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'No se pudo actualizar la mascota.',
                        confirmButtonColor: '#5560FF'
                    });
                    submitBtn.disabled = false;
                    submitBtn.querySelector('span').textContent = 'Guardar Cambios';
                }
            } catch (err) {
                console.error(err);
                submitBtn.disabled = false;
                submitBtn.querySelector('span').textContent = 'Guardar Cambios';
            }
        });
    }
});

/* Mostrar detalles de una cita */
async function mostrarDetalleCita(idCita) {
    try {
        const res = await (await fetch(`index.php?action=portal_get_detalle_cita_clinica_ajax&id_cita=${idCita}`)).json();
        if (!res.success) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: res.message || 'No se pudo cargar la información de la cita.',
                confirmButtonColor: '#5560FF'
            });
            return;
        }

        const cita = res.cita;
        const consulta = res.consulta;
        const tratamientos = res.tratamientos;

        // Rellenar información general
        document.getElementById('detCitaTipo').textContent = cita.nombre_tipo.toUpperCase();
        document.getElementById('detCitaMascota').textContent = cita.nombre_mascota;
        document.getElementById('detCitaVet').textContent = `Dr(a). ${cita.veterinario}`;
        document.getElementById('detCitaFechaHora').textContent = `${formatFecha(cita.fecha)} · ${cita.hora.substring(0, 5)}`;

        // Rellenar Badge de Estado
        const badge = document.getElementById('detCitaEstado');
        badge.className = 'status-badge';
        if (cita.estado === 'programada') {
            badge.style.backgroundColor = 'var(--z-primary-soft)';
            badge.style.color = 'var(--z-primary)';
            badge.textContent = 'Activa';
        } else if (cita.estado === 'completada') {
            badge.style.backgroundColor = 'var(--z-success-soft)';
            badge.style.color = 'var(--z-success)';
            badge.textContent = 'Completada';
        } else {
            badge.style.backgroundColor = 'var(--z-danger-soft)';
            badge.style.color = 'var(--z-danger)';
            badge.textContent = cita.estado.charAt(0).toUpperCase() + cita.estado.slice(1);
        }

        // Mostrar u ocultar sección clínica
        const clinicaArea = document.getElementById('detCitaClinicaArea');
        const sinClinicaArea = document.getElementById('detCitaSinClinica');

        if (cita.estado === 'completada' && consulta) {
            clinicaArea.style.display = 'block';
            sinClinicaArea.style.display = 'none';

            // Cargar datos de la consulta
            document.getElementById('detClinicaPeso').textContent = `${consulta.peso || '—'} kg`;
            document.getElementById('detClinicaTemp').textContent = `${consulta.temperatura || '—'} °C`;
            document.getElementById('detClinicaFc').textContent = `${consulta.frecuencia_cardiaca || '—'} lpm`;
            document.getElementById('detClinicaMotivo').textContent = consulta.motivo_consulta || '—';
            document.getElementById('detClinicaAnamnesis').textContent = consulta.anamnesis || '—';
            document.getElementById('detClinicaDiagnostico').textContent = consulta.diagnostico || '—';
            document.getElementById('detClinicaPlan').textContent = consulta.plan_tratamiento || '—';

            // Cargar tratamientos
            const tratamientosList = document.getElementById('detClinicaTratamientosList');
            tratamientosList.innerHTML = '';
            if (tratamientos && tratamientos.length > 0) {
                tratamientos.forEach(t => {
                    tratamientosList.innerHTML += `
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.85rem; margin-top: 0.25rem;">
                        <div style="font-weight: 800; color: var(--z-primary); font-size: 0.85rem;">💊 ${escapeHtml(t.medicamento)}</div>
                        <div style="font-size: 0.78rem; color: #475569; margin-top: 0.25rem;">
                            <strong>Dosis:</strong> ${escapeHtml(t.dosis)} · <strong>Vía:</strong> ${escapeHtml(t.via_administracion)}
                        </div>
                        <div style="font-size: 0.78rem; color: #475569; margin-top: 0.15rem;">
                            <strong>Duración:</strong> ${escapeHtml(t.duracion)}
                        </div>
                        ${t.observaciones ? `<div style="font-size: 0.75rem; color: #64748b; margin-top: 0.35rem; font-style: italic; border-top: 1px dotted #cbd5e1; padding-top: 0.35rem;">Nota: ${escapeHtml(t.observaciones)}</div>` : ''}
                    </div>`;
                });
            } else {
                tratamientosList.innerHTML = '<p style="margin:0; font-size:0.8rem; color:#64748b; font-style:italic;">No se recetaron medicamentos.</p>';
            }
        } else {
            clinicaArea.style.display = 'none';
            sinClinicaArea.style.display = 'block';

            const titleEl = document.getElementById('detSinClinicaTitle');
            const descEl = document.getElementById('detSinClinicaDesc');
            const iconEl = document.getElementById('detSinClinicaIcon');

            if (titleEl && descEl && iconEl) {
                if (cita.estado === 'completada') {
                    iconEl.className = 'ri-checkbox-circle-line';
                    iconEl.style.color = 'var(--z-success)';
                    titleEl.textContent = 'Cita completada sin ficha.';
                    descEl.textContent = 'Esta cita se realizó con éxito, pero no se registraron observaciones clínicas adicionales ni recetas de medicamentos.';
                } else if (cita.estado === 'cancelada') {
                    iconEl.className = 'ri-close-circle-line';
                    iconEl.style.color = 'var(--z-danger)';
                    titleEl.textContent = 'Cita cancelada.';
                    descEl.textContent = 'Esta cita fue cancelada y no generó historial clínico.';
                } else {
                    iconEl.className = 'ri-health-book-line';
                    iconEl.style.color = 'var(--z-primary)';
                    titleEl.textContent = 'Esta cita aún no ha sido atendida.';
                    descEl.textContent = 'Cuando el veterinario finalice la consulta, aquí podrás visualizar la historia clínica, diagnóstico y medicamentos recetados.';
                }
            }
        }

        // Abrir Drawer de Detalles
        openModal('portalCitaDetalleModal');
    } catch (e) {
        console.error(e);
        Swal.fire({
            icon: 'error',
            title: 'Error de Red',
            text: 'No se pudo conectar con el servidor para traer los detalles.',
            confirmButtonColor: '#5560FF'
        });
    }
}


