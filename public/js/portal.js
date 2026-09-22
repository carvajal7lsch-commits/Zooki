/* Portal del propietario — Zooki (Rediseño Estilo App Móvil) */

// escapeHtml() vive en avisos.js, que este layout carga antes (DRY).

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

// Estados de la cita como los ve el propietario; los mismos de views/portal/index.php.
// Para él, "sin cerrar" sigue siendo una atención en curso.
const ESTADOS_CITA_PROPIETARIO = {
    pendiente: 'Activa', confirmada: 'Activa', en_curso: 'En atención', sin_cerrar: 'En atención',
    completada: 'Completada', cancelada: 'Cancelada', no_asistio: 'No asistió', cerrada_sin_consulta: 'Cerrada'
};

function etiquetaEstadoCita(estado) {
    if (ESTADOS_CITA_PROPIETARIO[estado]) return ESTADOS_CITA_PROPIETARIO[estado];
    return estado ? (estado.charAt(0).toUpperCase() + estado.slice(1)).replace(/_/g, ' ') : '—';
}

function claseEstadoCita(estado) {
    if (['pendiente', 'confirmada', 'en_curso', 'sin_cerrar'].includes(estado)) return 'status-badge--abierta';
    if (estado === 'completada') return 'status-badge--completada';
    if (['no_asistio', 'cerrada_sin_consulta'].includes(estado)) return 'status-badge--no-asistio';
    return 'status-badge--cancelada';
}

function badgeEstado(estado) {
    const e = (estado || 'pendiente').toLowerCase();
    return `<span class="portal-badge portal-badge--${escapeHtml(e)}">${escapeHtml(estado || 'pendiente')}</span>`;
}

/* ── Navegación entre secciones ──
   Cada sección tiene su dirección (#agenda, #perfil…) para que el botón
   «atrás» del navegador y la recarga de la página respeten dónde estaba. */
const SECCIONES_URL = { home: 'inicio', explore: 'servicios', agenda: 'agenda', notifications: 'recordatorios', account: 'perfil' };

function seccionDesdeHash(hash) {
    const limpio = (hash || '').replace(/^#/, '');
    const mascota = limpio.match(/^mascota-(\d+)$/);
    if (mascota) return { mascota: Number(mascota[1]) };
    const tab = Object.keys(SECCIONES_URL).find(k => SECCIONES_URL[k] === limpio);
    return { tab: tab || 'home' };
}

function marcarNavegacion(tabId) {
    // El menú lateral y la barra inferior comparten data-nav.
    document.querySelectorAll('.mobile-nav-item[data-nav], .portal-rail__item[data-nav]').forEach(item => {
        const activo = item.dataset.nav === tabId;
        item.classList.toggle('active', activo);
        if (activo) item.setAttribute('aria-current', 'page');
        else item.removeAttribute('aria-current');
    });
}

function mostrarPantalla(id) {
    document.querySelectorAll('.app-screen').forEach(screen => screen.classList.remove('active'));
    const pantalla = document.getElementById(id);
    if (pantalla) pantalla.classList.add('active');
    window.scrollTo({ top: 0 });
}

function switchTab(tabId, opciones = {}) {
    if (!document.getElementById(`screen-${tabId}`)) tabId = 'home';
    mostrarPantalla(`screen-${tabId}`);
    marcarNavegacion(tabId);

    if (opciones.historial !== false) {
        const url = '#' + SECCIONES_URL[tabId];
        if (opciones.reemplazar) history.replaceState({ zkTab: tabId }, '', url);
        else if (location.hash !== url) history.pushState({ zkTab: tabId }, '', url);
    }

    // Si entramos a Notificaciones/Alertas o Explorar, refrescar datos dinámicamente
    if (tabId === 'notifications') {
        loadPortalAlerts();
    } else if (tabId === 'explore') {
        loadVetsExplore();
    }
}

// Botones de navegación (menú lateral, barra inferior, campana, accesos rápidos).
document.addEventListener('click', (e) => {
    const destino = e.target.closest('[data-nav]');
    if (destino) {
        e.preventDefault();
        switchTab(destino.dataset.nav);
    }
});

// Las tarjetas con role="button" responden a Enter y Espacio como un botón.
document.addEventListener('keydown', (e) => {
    const tarjeta = e.target.closest('[role="button"][tabindex="0"]');
    if (tarjeta && tarjeta === e.target && (e.key === 'Enter' || e.key === ' ')) {
        e.preventDefault();
        tarjeta.click();
    }
});

window.addEventListener('popstate', (e) => {
    // Con una ventana abierta, «atrás» solo la cierra.
    const abierta = document.querySelector('.portal-drawer-overlay.is-open');
    if (abierta && !(e.state && e.state.zkModal === abierta.id)) {
        cerrarVentana(abierta.id);
        return;
    }
    const destino = seccionDesdeHash(location.hash);
    if (destino.mascota) verDetalle(destino.mascota, { historial: false });
    else switchTab(destino.tab, { historial: false });
});

document.addEventListener('DOMContentLoaded', () => {
    const destino = seccionDesdeHash(location.hash);
    if (destino.mascota) verDetalle(destino.mascota, { historial: false });
    else if (destino.tab !== 'home') switchTab(destino.tab, { historial: false });
    history.replaceState(destino.mascota ? { zkMascota: destino.mascota } : { zkTab: destino.tab }, '', location.hash || '#inicio');
});

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
                <div class="vet-photo-circle" aria-hidden="true">${iniciales}</div>
                <h3>Dr(a). ${escapeHtml(v.nombre_completo.split(' ')[0])}</h3>
                <p>Médico Veterinario</p>
            </div>`;
        });
        listEl.innerHTML = html;
    } catch (e) {
        console.error(e);
        listEl.innerHTML = '<div class="notification-card-empty"><p>Error al cargar veterinarios.</p></div>';
    }
}

/** La campana (móvil y tablet) y el punto del menú lateral (escritorio) se encienden juntos. */
function mostrarAvisoAlertas(hayNuevas) {
    const campana = document.getElementById('bellBadgeAlert');
    const puntoMenu = document.querySelector('[data-badge-alertas]');
    if (campana) campana.style.display = hayNuevas ? 'block' : 'none';
    if (puntoMenu) puntoMenu.hidden = !hayNuevas;
}

/* Cargar Alertas y Notificaciones */
async function loadPortalAlerts() {
    const listEl = document.getElementById('portalAlertsList');
    if (!listEl) return;

    try {
        const res = await (await fetch('index.php?action=get_notificaciones_ajax')).json();
        if (!res.success || !res.notificaciones || res.notificaciones.length === 0) {
            listEl.innerHTML = `
                <div class="notification-card-empty">
                    <i class="ri-notification-off-line"></i>
                    <p>No tienes alertas médicas o recordatorios programados en este momento.</p>
                </div>`;
            mostrarAvisoAlertas(false);
            return;
        }

        mostrarAvisoAlertas(res.no_leidas > 0);

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
                    <h3>${escapeHtml(n.titulo)}</h3>
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
    
    // La politica compartida decide primero (RN-G10): no tiene sentido pintar
    // la barra de verde si el servidor va a rechazar la contrasena.
    const motivo = window.motivoPasswordInvalida
        ? window.motivoPasswordInvalida(pwd)
        : (pwd.length >= 8 ? null : 'Mínimo 8 caracteres');
    const cumple = motivo === null;

    let strength = 0;
    if (cumple) {
        strength = 75;
        if (pwd.match(/[^A-Za-z0-9]/)) strength += 25;   // simbolos
    } else if (pwd.length > 0) {
        strength = 25;
    }

    bar.style.width = strength + '%';
    if (!cumple) {
        bar.style.background = 'var(--z-danger)';
        text.textContent = pwd.length === 0
            ? 'Mínimo 8 caracteres, con mayúscula, minúscula y número.'
            : motivo + '.';
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
    
    btn.disabled = !(cumple && match);
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
        btn.innerHTML = 'Actualizar contraseña';
    }
}

function openDrawer() {
    mostrarPantalla('screen-pet-detail');
    // La ficha es un subnivel de Inicio.
    marcarNavegacion('home');
}

function cerrarDrawer() {
    // Si se llegó desde el portal, «volver» es lo mismo que el botón atrás.
    if (history.state && history.state.zkDentro) history.back();
    else switchTab('home', { reemplazar: true });
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

// Esc cierra la ventana abierta; si no hay ninguna, sale de la ficha de la mascota.
// Antes volvía a Inicio desde cualquier pantalla, aunque hubiera una ventana abierta.
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || document.getElementById('tiktok-micro-modal')) return;
    const abierta = document.querySelector('.portal-drawer-overlay.is-open');
    if (abierta) closeModal(abierta.id);
    else if (document.getElementById('screen-pet-detail')?.classList.contains('active')) cerrarDrawer();
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
    const abierta = card.classList.toggle('active');
    card.setAttribute('aria-expanded', abierta ? 'true' : 'false');
}

async function verDetalle(id, opciones = {}) {
    openDrawer();
    if (opciones.historial !== false) history.pushState({ zkMascota: id, zkDentro: true }, '', `#mascota-${id}`);
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
            zookiAviso(res.message || 'No se pudieron obtener los detalles.');
            switchTab('home', { reemplazar: true });
            return;
        }

        const m = res.mascota;
        window.activePetData = m;
        
        const avatarHtml = m.url_foto
            ? `<img src="uploads/mascotas/${escapeHtml(m.url_foto)}" alt="" class="pet-detail__avatar">`
            : `<span class="pet-detail__avatar pet-detail__avatar--vacio" aria-hidden="true"><i class="ri-baidu-line"></i></span>`;

        document.getElementById('drawerPetTitle').innerHTML = `${avatarHtml}<span>${escapeHtml(m.nombre)}</span>`;
        // textContent ya escapa: con escapeHtml encima, «&» salía como «&amp;».
        document.getElementById('drawerPetSubtitle').textContent = `${m.especie} · ${m.raza}`;

        renderSummary(m);

        // Historial
        if (!res.historial.length) {
            document.getElementById('historialContent').innerHTML = `
                <div class="portal-empty-inline">
                     <i class="ri-heart-pulse-line" aria-hidden="true"></i>
                     <p>Aún no hay consultas registradas para ${escapeHtml(m.nombre)}.</p>
                </div>`;
        } else {
            let html = '<div class="portal-timeline">';
            res.historial.forEach(h => {
                let filesHtml = '';
                if (h.archivos && h.archivos.length > 0) {
                    filesHtml += `<div class="portal-timeline-files"><strong>Archivos adjuntos:</strong>`;
                    h.archivos.forEach(file => {
                        filesHtml += `
                        <!-- M2-05: enlazaba ruta_archivo directo (uploads/clinicos/...),
                             saltandose el control de acceso. Solo lo frenaba el .htaccess,
                             que cubre Apache con AllowOverride y nada mas. Ahora pasa por
                             ver_archivo.php, que verifica que la mascota sea del dueno. -->
                        <a href="ver_archivo.php?id=${encodeURIComponent(file.id_archivo)}" target="_blank" rel="noopener" class="portal-file-link" onclick="event.stopPropagation()">
                            <i class="ri-file-pdf-line" aria-hidden="true"></i> ${escapeHtml(file.nombre_original)}
                        </a>`;
                    });
                    filesHtml += `</div>`;
                }

                html += `
                <div class="portal-timeline-item">
                    <div class="portal-timeline-date">${formatFechaHora(h.fecha_hora)}</div>
                    <div class="portal-timeline-card accordion-card" role="button" tabindex="0" aria-expanded="false" onclick="toggleAccordion(this)">
                        <div class="accordion-header">
                            <h3>${escapeHtml(h.motivo_consulta)}</h3>
                            <i class="ri-arrow-down-s-line accordion-icon" aria-hidden="true"></i>
                        </div>
                        <div class="accordion-body">
                            <p><strong>Diagnóstico:</strong> ${escapeHtml(h.diagnostico)}</p>
                            <p><strong>Tratamiento:</strong> ${escapeHtml(h.plan_tratamiento)}</p>
                            <p><strong>Veterinario:</strong> ${escapeHtml(h.veterinario)}</p>
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
                    <i class="ri-calendar-line" aria-hidden="true"></i>
                    <p>No hay citas programadas.</p>
                </div>`;
        } else {
            let html = '';
            res.citas.forEach(c => {
                let cancelBtn = '';
                if (c.estado === 'pendiente' || c.estado === 'confirmada' || c.estado === 'programada') {
                    cancelBtn = `<button type="button" class="portal-btn-cancel" onclick="cancelarCitaPortal(${c.id_cita})"><i class="ri-close-circle-line" aria-hidden="true"></i> Cancelar cita</button>`;
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
                    <i class="ri-syringe-line" aria-hidden="true"></i>
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
                        ${v.fecha_proxima_dosis ? `<p class="portal-proxima">Próxima dosis: ${formatFecha(v.fecha_proxima_dosis)}</p>` : ''}
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
                    <i class="ri-capsule-line" aria-hidden="true"></i>
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
                        ${d.fecha_proxima ? `<p class="portal-proxima portal-proxima--control">Próxima dosis: ${formatFecha(d.fecha_proxima)} (${escapeHtml(d.periodicidad)})</p>` : ''}
                        ${d.observaciones ? `<p>Observaciones: ${escapeHtml(d.observaciones)}</p>` : ''}
                    </div>
                </div>`;
            });
            document.getElementById('desparasitacionesContent').innerHTML = html;
        }

    } catch (e) {
        console.error(e);
        zookiAviso('No se pudieron cargar los datos de la mascota.');
        switchTab('home', { reemplazar: true });
    }
}

/* --- Centralización de Modales con Principios SOLID (SRP) --- */
// Quién abrió cada ventana, para devolverle el foco al cerrarla.
const focoPrevio = {};

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    focoPrevio[modalId] = document.activeElement;
    modal.style.display = 'flex';
    // Forzar reflow para que la transición CSS se ejecute correctamente
    modal.offsetHeight;
    modal.classList.add('is-open');
    const drawer = modal.querySelector('.portal-drawer');
    if (drawer) {
        drawer.classList.add('is-open');
        // El foco va a la ventana y no al primer campo: en el celular eso abriría el teclado.
        drawer.setAttribute('tabindex', '-1');
        drawer.focus({ preventScroll: true });
    }
    document.body.style.overflow = 'hidden';
    // Una entrada en el historial: «atrás» en el celular cierra la ventana.
    if (!(history.state && history.state.zkModal === modalId)) {
        history.pushState({ ...(history.state || {}), zkModal: modalId }, '', location.hash);
    }
}

/** Cierra la ventana sin tocar el historial (lo usa el botón atrás). */
function cerrarVentana(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal || !modal.classList.contains('is-open')) return;
    const drawer = modal.querySelector('.portal-drawer');
    if (drawer) drawer.classList.remove('is-open');
    modal.classList.remove('is-open');
    const previo = focoPrevio[modalId];
    if (previo && document.contains(previo)) previo.focus({ preventScroll: true });
    setTimeout(() => {
        modal.style.display = 'none';
        if (!document.querySelector('.portal-drawer-overlay.is-open')) {
            document.body.style.overflow = '';
        }
    }, 300);
}

function closeModal(modalId) {
    if (history.state && history.state.zkModal === modalId) {
        // Deshace la entrada que abrió la ventana; popstate la cierra.
        history.back();
    } else {
        cerrarVentana(modalId);
    }
}

// Tab no se sale de la ventana abierta.
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return;
    const drawer = document.querySelector('.portal-drawer-overlay.is-open .portal-drawer');
    if (!drawer) return;
    const enfocables = [...drawer.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter(el => !el.disabled && el.offsetParent !== null);
    if (!enfocables.length) return;
    const primero = enfocables[0];
    const ultimo = enfocables[enfocables.length - 1];
    if (e.shiftKey && (document.activeElement === primero || document.activeElement === drawer)) {
        e.preventDefault();
        ultimo.focus();
    } else if (!e.shiftKey && document.activeElement === ultimo) {
        e.preventDefault();
        primero.focus();
    }
});

// Pestañas de «Mi agenda de salud». En escritorio las dos columnas se ven a la vez (agenda.css).
function switchAgendaTab(tabId, btn) {
    document.querySelectorAll('.agenda-tab-content').forEach(el => {
        el.classList.toggle('active', el.id === tabId);
    });
    document.querySelectorAll('.agenda-tab-btn').forEach(b => {
        const activo = b === btn;
        b.classList.toggle('active', activo);
        b.setAttribute('aria-selected', activo ? 'true' : 'false');
    });
}

// Aviso breve del portal (confirmar, cancelar, errores). Hoja que sube desde
// abajo en el celular y ventana centrada desde tablet; estilos en ventanas.css.
function showTikTokModal({ title, message, isConfirm, onConfirm, onCancel }) {
    const existing = document.getElementById('tiktok-micro-modal');
    if (existing) existing.remove();
    const focoAnterior = document.activeElement;

    const overlay = document.createElement('div');
    overlay.id = 'tiktok-micro-modal';
    overlay.className = 'zk-sheet';

    const panel = document.createElement('div');
    panel.className = 'zk-sheet__panel';
    panel.setAttribute('role', isConfirm ? 'alertdialog' : 'dialog');
    panel.setAttribute('aria-modal', 'true');

    const asa = document.createElement('div');
    asa.className = 'zk-sheet__asa';
    panel.appendChild(asa);

    if (title) {
        const titleEl = document.createElement('h2');
        titleEl.className = 'zk-sheet__titulo';
        titleEl.id = 'zkSheetTitulo';
        titleEl.textContent = title;
        panel.appendChild(titleEl);
        panel.setAttribute('aria-labelledby', titleEl.id);
    }

    if (message) {
        const msgEl = document.createElement('p');
        msgEl.className = 'zk-sheet__mensaje';
        msgEl.textContent = message;
        panel.appendChild(msgEl);
    }

    const acciones = document.createElement('div');
    acciones.className = 'zk-sheet__acciones';

    const close = () => {
        document.removeEventListener('keydown', alTeclear);
        overlay.classList.remove('is-open');
        setTimeout(() => overlay.remove(), 250);
        if (focoAnterior && document.contains(focoAnterior)) focoAnterior.focus({ preventScroll: true });
    };

    const mainBtn = document.createElement('button');
    mainBtn.type = 'button';
    mainBtn.className = 'zk-sheet__btn zk-sheet__btn--principal';
    mainBtn.textContent = isConfirm ? 'Sí, continuar' : 'Entendido';
    mainBtn.onclick = () => {
        close();
        if (onConfirm) onConfirm();
    };
    acciones.appendChild(mainBtn);

    let cancelBtn = null;
    if (isConfirm) {
        cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'zk-sheet__btn zk-sheet__btn--secundario';
        cancelBtn.textContent = 'No, cancelar';
        cancelBtn.onclick = () => {
            close();
            if (onCancel) onCancel();
        };
        acciones.appendChild(cancelBtn);
    }

    // Esc equivale a «No» en una confirmación y a «Entendido» en un aviso.
    const alTeclear = (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            (cancelBtn || mainBtn).click();
        }
    };
    document.addEventListener('keydown', alTeclear);

    panel.appendChild(acciones);
    overlay.appendChild(panel);
    document.body.appendChild(overlay);

    // Animar entrada
    setTimeout(() => {
        overlay.classList.add('is-open');
        mainBtn.focus({ preventScroll: true });
    }, 15);
}

/* Lógica de Agendamiento desde el Portal (HU-26) */
document.addEventListener('DOMContentLoaded', () => {
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
                htmlHistorial += `
                <div class="agenda-list-item agenda-list-item--accion agenda-list-item--cita" role="button" tabindex="0" onclick="mostrarDetalleCita(${Number(e.id_cita)})">
                    <div class="agenda-icon-wrap"><i class="ri-calendar-event-line" aria-hidden="true"></i></div>
                    <div class="agenda-item-info">
                        <h3>${escapeHtml(e.nombre_mascota)}</h3>
                        <p class="agenda-item-type">Cita: <strong>${escapeHtml(e.titulo)}</strong> · ${escapeHtml(e.detalle)}</p>
                        <span class="agenda-item-date">Fecha: ${formatFecha(e.fecha)} · Hora: ${escapeHtml(e.hora)}</span>
                    </div>
                    <div class="agenda-item-actions">
                        <span class="status-badge ${claseEstadoCita(e.estado)}">${escapeHtml(etiquetaEstadoCita(e.estado))}</span>
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
                let badgeClase = 'status-badge--pendiente-dosis';
                if (diffDays > 0) {
                    badgeText = `Faltan ${diffDays} días`;
                    badgeClase = 'status-badge--abierta';
                } else if (diffDays === 0) {
                    badgeText = '¡Hoy!';
                    badgeClase = 'status-badge--completada';
                } else {
                    badgeText = `Vencido hace ${Math.abs(diffDays)} días`;
                    badgeClase = 'status-badge--cancelada';
                }

                htmlProximas += `
                <div class="agenda-list-item">
                    <div class="agenda-icon-wrap ${bgClass}">
                        <i class="${iconClass}" aria-hidden="true"></i>
                    </div>
                    <div class="agenda-item-info">
                        <h3>${escapeHtml(e.nombre_mascota)}</h3>
                        <p class="agenda-item-type">Siguiente dosis: <strong>${escapeHtml(e.titulo)}</strong> · ${escapeHtml(e.detalle)}</p>
                        <span class="agenda-item-date agenda-item-date--suave">Última aplicación: ${formatFecha(e.fecha)}</span>
                    </div>
                    <div class="agenda-item-next">
                        <span class="status-badge ${badgeClase}">${badgeText}</span>
                        <span class="next-date">${formatFecha(e.proxima)}</span>
                    </div>
                </div>`;
            }

            // Historial (aplicación pasada)
            htmlHistorial += `
            <div class="agenda-list-item">
                <div class="agenda-icon-wrap ${bgClass}">
                    <i class="${iconClass}" aria-hidden="true"></i>
                </div>
                <div class="agenda-item-info">
                    <h3>${escapeHtml(e.nombre_mascota)}</h3>
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
            html += `<div class="cal-day-cell cal-day-cell--vacia" aria-hidden="true"></div>`;
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

            // Un punto por cada tipo de evento del día
            let dotsHtml = '';
            if (eventosDia.some(e => e.tipo === 'cita')) dotsHtml += '<span class="cal-dot"></span>';
            if (eventosDia.some(e => e.tipo === 'vacuna')) dotsHtml += '<span class="cal-dot cal-dot--vacuna"></span>';
            if (eventosDia.some(e => e.tipo === 'control')) dotsHtml += '<span class="cal-dot cal-dot--control"></span>';

            const clases = ['cal-day-cell'];
            if (eventosDia.length > 0) clases.push('tiene-eventos');
            if (selectedCalendarDate === dateStr) clases.push('is-selected');
            const accesible = eventosDia.length > 0
                ? ` role="button" tabindex="0" aria-pressed="${selectedCalendarDate === dateStr}" aria-label="${day} de ${monthNames[currentCalMonth]}: ${eventosDia.length} evento${eventosDia.length > 1 ? 's' : ''}"`
                : '';

            html += `
            <div class="${clases.join(' ')}" data-date="${dateStr}"${accesible}>
                <span>${day}</span>
                <div class="cal-day-dots">${dotsHtml}</div>
            </div>`;
        }

        grid.innerHTML = html;

        // Agregar clics a celdas con eventos
        grid.querySelectorAll('.cal-day-cell[data-date]').forEach(cell => {
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
                c.classList.toggle('active', c === chip);
                c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
            });

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

            // 1. Fecha de nacimiento: hasta hoy y no más de 40 años atrás (ValidadorMascota).
            const hace40 = new Date();
            hace40.setFullYear(hace40.getFullYear() - 40);
            flatpickr("input[name='fecha_nacimiento'].flatpickr-date", {
                locale: currentLocale,
                dateFormat: "Y-m-d",
                minDate: hace40,
                maxDate: "today",
                disableMobile: true,
                altInput: true,
                altFormat: "j M Y",
                monthSelectorType: "static",
                onReady: function(selectedDates, dateStr, instance) {
                    makeWin11Calendar(instance);
                }
            });

            // 2. El calendario de agendar lo crea su propio bloque, más abajo.
            window.zkLocale = currentLocale;
            window.zkMesesAnios = makeWin11Calendar;

        } catch (e) {
            console.error("Error inicializando Flatpickr:", e);
        }
    }

    // ── Agendar cita (HU-26): el mismo flujo del panel del personal ──
    // Datos a la izquierda; día y horarios libres a la derecha. Los horarios
    // aparecen cuando ya hay tipo, veterinario y día, y mientras tanto el
    // panel dice qué falta (antes quedaba «Elige fecha…» sin explicar nada).
    const bookingModal = document.getElementById('portalBookingModal');
    const form = document.getElementById('portalBookingForm');
    if (!bookingModal || !form) return;

    const closeBtn = document.getElementById('btnCloseBookingModal');
    const mascotaSelect = document.getElementById('booking_mascota');
    const tipoCitaSelect = document.getElementById('booking_tipo_cita');
    const vetSelect = document.getElementById('booking_veterinario');
    const dateInput = document.getElementById('booking_fecha');
    const horaInput = document.getElementById('booking_hora');
    const slots = document.getElementById('booking_slots');
    const resumen = document.getElementById('booking_resumen');
    const confirmar = document.getElementById('booking_confirmar');
    // Días sin atención en toda la jornada (1 = lunes … 7 = domingo), desde Configuración de horarios.
    const diasCerrados = JSON.parse(form.dataset.diasCerrados || '[]');
    let turno = 0; // si el usuario cambia algo mientras cargan los horarios, se ignora la respuesta vieja
    let catalogosListos = false;

    const minutosDe = h => { const [hh, mm] = h.split(':').map(Number); return hh * 60 + mm; };
    const hora12 = h => { const [hh, mm] = h.split(':').map(Number); return `${hh % 12 || 12}:${String(mm).padStart(2, '0')} ${hh < 12 ? 'a. m.' : 'p. m.'}`; };
    const hoyIso = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
    const enLista = partes => partes.length > 1 ? `${partes.slice(0, -1).join(', ')} y ${partes[partes.length - 1]}` : partes[0];
    const textoOpcion = sel => (sel.selectedOptions[0] ? sel.selectedOptions[0].textContent.trim() : '');

    const calendario = typeof flatpickr !== 'undefined' ? flatpickr(dateInput, {
        locale: window.zkLocale || 'es',
        dateFormat: 'Y-m-d',
        minDate: 'today',
        inline: true,
        disableMobile: true,
        monthSelectorType: 'static',
        disable: [d => diasCerrados.includes(d.getDay() === 0 ? 7 : d.getDay())],
        onChange: () => cargarHoras(),
        onReady: (fechas, texto, instancia) => { if (window.zkMesesAnios) window.zkMesesAnios(instancia); }
    }) : null;

    function estadoSlots(tipo, texto = '') {
        if (tipo === 'cargando') {
            slots.innerHTML = `<div class="slot-grid">${'<span class="slot-skeleton"></span>'.repeat(8)}</div>`;
            return;
        }
        const icono = tipo === 'guia' ? 'ri-time-line' : 'ri-calendar-close-line';
        slots.innerHTML = `<div class="slot-state"><i class="${icono}" aria-hidden="true"></i><p>${escapeHtml(texto)}</p></div>`;
    }

    function textoGuia() {
        const faltan = [];
        if (!tipoCitaSelect.value) faltan.push('el tipo de cita');
        if (!vetSelect.value) faltan.push('el veterinario');
        if (!dateInput.value) faltan.push('el día');
        return faltan.length ? `Elige ${enLista(faltan)} para ver los horarios libres.` : '';
    }

    /** Horarios repartidos en Mañana y Tarde, como en el panel del personal. */
    function renderSlots(horas) {
        const grupos = [
            ['manana', 'Mañana', horas.filter(h => minutosDe(h) < 720)],
            ['tarde', 'Tarde', horas.filter(h => minutosDe(h) >= 720)]
        ].filter(([, , hs]) => hs.length);

        const cabecera = grupos.length > 1
            ? `<div class="slot-tabs" role="tablist">${grupos.map(([clave, nombre, hs], i) =>
                `<button type="button" role="tab" class="slot-tab${i ? '' : ' is-active'}" data-franja="${clave}" aria-selected="${!i}">${nombre} <span>${hs.length}</span></button>`
              ).join('')}</div>`
            : `<p class="slot-caption">${grupos[0][1]} · ${grupos[0][2].length} ${grupos[0][2].length === 1 ? 'horario libre' : 'horarios libres'}</p>`;

        slots.innerHTML = cabecera + grupos.map(([clave, , hs], i) =>
            `<div class="slot-grid" data-franja="${clave}"${i ? ' hidden' : ''}>${hs.map(h =>
                `<button type="button" class="slot-chip" data-hora="${escapeHtml(h)}" aria-pressed="false">${escapeHtml(hora12(h))}</button>`
            ).join('')}</div>`
        ).join('');
    }

    slots.addEventListener('click', (e) => {
        const tab = e.target.closest('.slot-tab');
        if (tab) {
            slots.querySelectorAll('.slot-tab').forEach(t => {
                t.classList.toggle('is-active', t === tab);
                t.setAttribute('aria-selected', String(t === tab));
            });
            slots.querySelectorAll('.slot-grid').forEach(g => { g.hidden = g.dataset.franja !== tab.dataset.franja; });
            return;
        }
        const chip = e.target.closest('.slot-chip');
        if (chip) {
            slots.querySelectorAll('.slot-chip').forEach(c => {
                c.classList.toggle('is-selected', c === chip);
                c.setAttribute('aria-pressed', String(c === chip));
            });
            horaInput.value = chip.dataset.hora;
            actualizarResumen();
        }
    });

    async function cargarHoras() {
        horaInput.value = '';
        actualizarResumen();

        const guia = textoGuia();
        if (guia) {
            estadoSlots('guia', guia);
            return;
        }

        const fecha = dateInput.value;
        const vet = vetSelect.value;
        const duracion = (tipoCitaSelect.selectedOptions[0] && tipoCitaSelect.selectedOptions[0].dataset.duracion) || 30;
        const miTurno = ++turno;
        estadoSlots('cargando');

        try {
            // Horas de la clínica ese día ∩ huecos libres del veterinario.
            const [clinica, agendaVet] = await Promise.all([
                fetch(`index.php?action=get_horas_disponibles_ajax&fecha=${encodeURIComponent(fecha)}&intervalo=${encodeURIComponent(duracion)}`).then(r => r.json()),
                fetch(`index.php?action=get_sugerencias_horario_ajax&doc_veterinario=${encodeURIComponent(vet)}&fecha=${encodeURIComponent(fecha)}&duracion_minutos=${encodeURIComponent(duracion)}`).then(r => r.json())
            ]);
            if (miTurno !== turno) return;

            if (!clinica.success || !agendaVet.success) {
                estadoSlots('vacio', 'No se pudieron cargar los horarios. Intenta de nuevo.');
                return;
            }
            const horasClinica = clinica.horas || [];
            if (!horasClinica.length) {
                estadoSlots('vacio', 'La clínica no atiende ese día. Elige otro día.');
                return;
            }
            const libresVet = agendaVet.sugerencias || [];
            let horas = horasClinica.filter(h => libresVet.includes(h));
            // Hoy no se ofrecen horas que ya pasaron.
            if (fecha === hoyIso()) {
                const ahora = new Date();
                const minutosAhora = ahora.getHours() * 60 + ahora.getMinutes();
                horas = horas.filter(h => minutosDe(h) > minutosAhora);
            }
            if (!horas.length) {
                estadoSlots('vacio', 'Ya no quedan horarios libres ese día con este veterinario. Prueba otro día u otro veterinario.');
                return;
            }
            renderSlots(horas);
        } catch (e) {
            console.error(e);
            if (miTurno === turno) estadoSlots('vacio', 'No se pudieron cargar los horarios. Revisa tu conexión e intenta de nuevo.');
        }
    }

    function actualizarResumen() {
        const listo = mascotaSelect.value && tipoCitaSelect.value && vetSelect.value && dateInput.value && horaInput.value;
        confirmar.disabled = !listo;
        if (!listo) {
            resumen.textContent = '';
            return;
        }
        const dia = calendario && calendario.selectedDates[0]
            ? calendario.selectedDates[0].toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' })
            : dateInput.value;
        resumen.innerHTML = `<i class="ri-checkbox-circle-line" aria-hidden="true"></i>${escapeHtml(textoOpcion(mascotaSelect))} · ${escapeHtml(textoOpcion(tipoCitaSelect))} con ${escapeHtml(textoOpcion(vetSelect))} · ${escapeHtml(dia)}, ${escapeHtml(hora12(horaInput.value))}`;
    }

    async function cargarCatalogos() {
        try {
            const [resTipos, resVets] = await Promise.all([
                fetch('index.php?action=portal_get_tipos_cita_ajax').then(r => r.json()),
                fetch('index.php?action=portal_get_vets_ajax').then(r => r.json())
            ]);
            tipoCitaSelect.innerHTML = '<option value="">Selecciona…</option>' + (resTipos.success ? resTipos.tipos : []).map(t =>
                `<option value="${Number(t.id_tipo_cita)}" data-duracion="${Number(t.duracion_minutos)}">${escapeHtml(t.nombre_tipo)} (${Number(t.duracion_minutos)} min)</option>`
            ).join('');
            vetSelect.innerHTML = '<option value="">Selecciona…</option>' + (Array.isArray(resVets) ? resVets : []).map(v =>
                `<option value="${escapeHtml(v.documento)}">Dr(a). ${escapeHtml(v.nombre_completo)}</option>`
            ).join('');
            catalogosListos = true;
        } catch (e) {
            console.error('Error al cargar catálogos:', e);
            tipoCitaSelect.innerHTML = vetSelect.innerHTML = '<option value="">No se pudo cargar. Cierra y vuelve a abrir.</option>';
        }
    }

    const abrirBooking = async () => {
        form.reset();
        horaInput.value = '';
        if (calendario) calendario.clear();
        // Con una sola mascota no hay nada que elegir.
        if (mascotaSelect.options.length === 2) mascotaSelect.selectedIndex = 1;
        openModal('portalBookingModal');

        if (mascotaSelect.options.length === 1) {
            estadoSlots('vacio', 'Primero registra una mascota desde Inicio para poder agendarle una cita.');
            return;
        }
        if (!catalogosListos) await cargarCatalogos();
        cargarHoras();
    };

    // Todos los botones de agendar: menú lateral, barra inferior, accesos y servicios.
    document.querySelectorAll('[data-agendar]').forEach(btn => btn.addEventListener('click', abrirBooking));
    if (closeBtn) closeBtn.addEventListener('click', () => closeModal('portalBookingModal'));
    bookingModal.addEventListener('click', (e) => {
        if (e.target === bookingModal) closeModal('portalBookingModal');
    });

    mascotaSelect.addEventListener('change', actualizarResumen);
    tipoCitaSelect.addEventListener('change', cargarHoras);
    vetSelect.addEventListener('change', cargarHoras);

    const enviarFormulario = async (ignoreWarning = false) => {
        const submitBtn = confirmar;
        const reactivar = () => {
            submitBtn.querySelector('span').textContent = 'Confirmar cita';
            actualizarResumen();
        };
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
                    onCancel: reactivar
                });
            } else {
                showTikTokModal({
                    title: 'No se pudo agendar',
                    message: res.message || 'Inténtalo nuevamente.',
                    isConfirm: false,
                    onConfirm: () => {
                        reactivar();
                        // El horario pudo ocuparse mientras tanto: se vuelven a pedir.
                        cargarHoras();
                    }
                });
            }
        } catch (error) {
            console.error('Error al agendar cita:', error);
            showTikTokModal({
                title: 'Error de Red',
                message: 'No pudimos conectarnos con el servidor. Inténtalo más tarde.',
                isConfirm: false,
                onConfirm: reactivar
            });
        }
    };

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (confirmar.disabled) return;
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

/* --- Gestión de Mascotas desde el Portal (Registro y Edición) ---
   Las especies vienen en la página (views/portal/index.php); las razas se
   piden por especie. El propietario ya no crea razas: si la suya no está,
   elige la criolla o «Sin raza definida». El color lo registra la clínica. */
document.addEventListener('DOMContentLoaded', () => {
    const addPetModal = document.getElementById('portalAddPetModal');
    const editPetModal = document.getElementById('portalEditPetModal');
    const addForm = document.getElementById('portalAddPetForm');
    const editForm = document.getElementById('portalEditPetForm');
    if (!addForm || !editForm) return;

    const MAX_FOTO = 5 * 1024 * 1024; // FotoMascota::MAX_BYTES
    const TIPOS_FOTO = ['image/jpeg', 'image/png'];

    const campo = (form, nombre) => form.querySelector(`[name="${nombre}"]`);

    // ── Razas de la especie elegida ──
    // Si la raza no está, hay tres salidas claras (al comienzo de la lista):
    //   · «Criollo» (perros y gatos): es una mezcla.
    //   · «No sé la raza»: se guarda como «Sin raza definida».
    //   · «Mi raza no está en la lista…»: la escribe y la clínica la revisa (RE-15.9).
    const RAZA_OTRA = 'otra'; // ValidadorMascota::RAZA_OTRA
    const esSinDefinir = nombre => nombre === 'Sin raza definida';
    const esMestiza = nombre => /criollo|mestizo/i.test(nombre);

    /** Muestra u oculta «¿Cuál es la raza?»; oculto va deshabilitado, así no se exige ni se envía. */
    const mostrarRazaOtra = (form, visible, valor = '') => {
        const grupo = form.querySelector('.raza-otra');
        const input = campo(form, 'raza_indicada');
        grupo.hidden = !visible;
        input.disabled = !visible;
        input.required = visible;
        input.value = visible ? valor : '';
    };

    const cargarRazas = async (idEspecie, select, seleccionada = null) => {
        const ayuda = document.getElementById(`${select.id}_ayuda`);
        select.disabled = true;
        if (ayuda) ayuda.hidden = true;
        if (!idEspecie) {
            select.innerHTML = '<option value="">Primero elige la especie</option>';
            return;
        }
        select.innerHTML = '<option value="">Cargando razas…</option>';
        try {
            const razas = await (await fetch(`index.php?action=listar_razas_ajax&id_especie=${encodeURIComponent(idEspecie)}`)).json();
            const marcada = valor => String(seleccionada) === String(valor) ? ' selected' : '';
            const opcion = (valor, texto) => `<option value="${escapeHtml(String(valor))}"${marcada(valor)}>${escapeHtml(texto)}</option>`;

            const mestizas = razas.filter(r => esMestiza(r.nombre_raza));
            const sinDefinir = razas.find(r => esSinDefinir(r.nombre_raza));
            const conocidas = razas.filter(r => !esMestiza(r.nombre_raza) && !esSinDefinir(r.nombre_raza));

            // «Mi raza no está» se guarda como «Sin raza definida»: sin ella (falta la migración 13) no se ofrece.
            const salidas = [
                ...mestizas.map(r => opcion(r.id_raza, `${r.nombre_raza} · es una mezcla`)),
                ...(sinDefinir ? [opcion(sinDefinir.id_raza, 'No sé la raza'), opcion(RAZA_OTRA, 'Mi raza no está en la lista…')] : [])
            ];

            select.innerHTML = '<option value="">Selecciona…</option>'
                + (salidas.length ? `<optgroup label="Si no encuentras la raza">${salidas.join('')}</optgroup>` : '')
                + (conocidas.length ? `<optgroup label="Razas">${conocidas.map(r => opcion(r.id_raza, r.nombre_raza)).join('')}</optgroup>` : '');
            select.disabled = false;

            if (ayuda && salidas.length) {
                ayuda.textContent = sinDefinir
                    ? '¿No la encuentras? Al comienzo de la lista puedes indicar que es mestiza, que no sabes la raza o escribir la tuya.'
                    : '¿No la encuentras? Al comienzo de la lista puedes indicar que es mestiza.';
                ayuda.hidden = false;
            }
        } catch (e) {
            console.error(e);
            select.innerHTML = '<option value="">No se pudieron cargar las razas</option>';
        }
    };

    // ── Foto: la actual y la elegida ──
    const pintarFoto = (form, url) => {
        form.querySelector('.foto-campo__vista').innerHTML = url
            ? `<img src="${escapeHtml(url)}" alt="">`
            : '<i class="ri-image-line"></i>';
    };

    const prepararFoto = (form, urlActual) => {
        if (form._urlVista) URL.revokeObjectURL(form._urlVista);
        form._urlVista = null;
        form.dataset.fotoActual = urlActual || '';
        campo(form, 'foto').value = '';
        form.querySelector('.foto-campo__quitar').hidden = true;
        const ayuda = form.querySelector('.foto-campo__ayuda');
        ayuda.textContent = urlActual ? 'Esta es la foto actual. JPG o PNG, hasta 5 MB.' : 'JPG o PNG, hasta 5 MB.';
        ayuda.classList.remove('is-error');
        pintarFoto(form, urlActual);
    };

    [addForm, editForm].forEach(form => {
        form.addEventListener('change', (e) => {
            const input = e.target;

            if (input.name === 'especie') {
                mostrarRazaOtra(form, false);
                cargarRazas(input.value, campo(form, 'raza'));
            }

            if (input.name === 'raza') {
                const otra = input.value === RAZA_OTRA;
                mostrarRazaOtra(form, otra);
                if (otra) campo(form, 'raza_indicada').focus();
            }

            if (input.name === 'foto') {
                const archivo = input.files[0];
                const ayuda = form.querySelector('.foto-campo__ayuda');
                if (!archivo) return;
                // Se revisa aquí para avisar de una vez; el servidor lo vuelve a comprobar.
                const problema = !TIPOS_FOTO.includes(archivo.type) ? 'Esa foto no es JPG ni PNG. Elige otra.'
                    : archivo.size > MAX_FOTO ? 'Esa foto pesa más de 5 MB. Elige una más liviana.'
                    : null;
                if (problema) {
                    prepararFoto(form, form.dataset.fotoActual);
                    ayuda.textContent = problema;
                    ayuda.classList.add('is-error');
                    return;
                }
                if (form._urlVista) URL.revokeObjectURL(form._urlVista);
                form._urlVista = URL.createObjectURL(archivo);
                pintarFoto(form, form._urlVista);
                ayuda.textContent = `${archivo.name} · se guarda al confirmar.`;
                ayuda.classList.remove('is-error');
                form.querySelector('.foto-campo__quitar').hidden = false;
            }
        });

        form.querySelector('.foto-campo__quitar').addEventListener('click', () => prepararFoto(form, form.dataset.fotoActual));
    });

    const limpiarFormulario = (form) => {
        form.reset();
        form.classList.remove('intentado');
        const nacimiento = campo(form, 'fecha_nacimiento');
        if (nacimiento && nacimiento._flatpickr) nacimiento._flatpickr.clear();
    };

    // ── Abrir: registrar ──
    const openAddBtn = document.getElementById('btnOpenAddPetModal');
    if (openAddBtn) {
        openAddBtn.addEventListener('click', () => {
            limpiarFormulario(addForm);
            cargarRazas('', campo(addForm, 'raza'));
            mostrarRazaOtra(addForm, false);
            prepararFoto(addForm, null);
            openModal('portalAddPetModal');
        });
    }

    // ── Abrir: editar, con los datos actuales ──
    const editProfileBtn = document.getElementById('btnEditPetProfile');
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', async () => {
            const pet = window.activePetData;
            if (!pet) return;

            limpiarFormulario(editForm);
            document.getElementById('edit_pet_id').value = pet.id_mascota;
            document.getElementById('edit_pet_nombre').value = pet.nombre || '';
            // Dos botones Macho / Hembra; un «Desconocido» antiguo queda sin marcar.
            const sexo = editForm.querySelector(`input[name="sexo"][value="${pet.sexo}"]`);
            if (sexo) sexo.checked = true;
            document.getElementById('edit_pet_peso').value = pet.peso || '';
            const nacimiento = document.getElementById('edit_pet_nacimiento');
            if (nacimiento._flatpickr) nacimiento._flatpickr.setDate(pet.fecha_nacimiento || null, false);
            else nacimiento.value = pet.fecha_nacimiento || '';

            prepararFoto(editForm, pet.url_foto ? 'uploads/mascotas/' + pet.url_foto : null);

            document.getElementById('edit_pet_especie').value = pet.id_especie;
            openModal('portalEditPetModal');
            const conIndicada = Boolean(pet.raza_indicada);
            mostrarRazaOtra(editForm, conIndicada, pet.raza_indicada || '');
            await cargarRazas(pet.id_especie, document.getElementById('edit_pet_raza'), conIndicada ? RAZA_OTRA : pet.id_raza);
        });
    }

    // ── Cerrar ──
    document.getElementById('btnCloseAddPetModal')?.addEventListener('click', () => closeModal('portalAddPetModal'));
    document.getElementById('btnCloseEditPetModal')?.addEventListener('click', () => closeModal('portalEditPetModal'));
    document.getElementById('btnCloseCitaDetalleModal')?.addEventListener('click', () => closeModal('portalCitaDetalleModal'));

    [addPetModal, editPetModal, document.getElementById('portalCitaDetalleModal')].forEach(modal => {
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal(modal.id);
            });
        }
    });

    // ── Enviar: primero la validación del navegador, luego el servidor ──
    const enviarMascota = (form, { accion, modalId, textoBoton, textoEnviando, exito }) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            form.classList.add('intentado');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.querySelector('span').textContent = textoEnviando;

            try {
                const res = await (await fetch(`index.php?action=${accion}`, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })).json();

                if (res.success) {
                    closeModal(modalId);
                    Swal.fire({ icon: 'success', title: exito[0], text: exito[1], confirmButtonColor: '#0052FF' })
                        .then(() => location.reload());
                    return;
                }
                Swal.fire({ icon: 'error', title: 'Revisa los datos', text: res.message || 'No se pudo guardar.', confirmButtonColor: '#0052FF' });
            } catch (err) {
                console.error(err);
                Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#0052FF' });
            }
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = textoBoton;
        });
    };

    enviarMascota(addForm, {
        accion: 'portal_registrar_mascota_ajax', modalId: 'portalAddPetModal',
        textoBoton: 'Registrar mascota', textoEnviando: 'Registrando...',
        exito: ['¡Mascota registrada!', 'Tu nuevo compañero ha sido registrado exitosamente.']
    });
    enviarMascota(editForm, {
        accion: 'portal_actualizar_mascota_ajax', modalId: 'portalEditPetModal',
        textoBoton: 'Guardar cambios', textoEnviando: 'Guardando...',
        exito: ['¡Cambios guardados!', 'La información de tu mascota ha sido actualizada.']
    });
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
        badge.className = `status-badge ${claseEstadoCita(cita.estado)}`;
        badge.textContent = etiquetaEstadoCita(cita.estado);

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
                    <div class="cita-tratamiento">
                        <div class="cita-tratamiento__nombre">💊 ${escapeHtml(t.medicamento)}</div>
                        <div class="cita-tratamiento__dato"><strong>Dosis:</strong> ${escapeHtml(t.dosis)} · <strong>Vía:</strong> ${escapeHtml(t.via_administracion)}</div>
                        <div class="cita-tratamiento__dato"><strong>Duración:</strong> ${escapeHtml(t.duracion)}</div>
                        ${t.observaciones ? `<div class="cita-tratamiento__nota">Nota: ${escapeHtml(t.observaciones)}</div>` : ''}
                    </div>`;
                });
            } else {
                tratamientosList.innerHTML = '<p class="cita-tratamientos__vacio">No se recetaron medicamentos.</p>';
            }
        } else {
            clinicaArea.style.display = 'none';
            sinClinicaArea.style.display = 'block';

            const titleEl = document.getElementById('detSinClinicaTitle');
            const descEl = document.getElementById('detSinClinicaDesc');
            const iconEl = document.getElementById('detSinClinicaIcon');

            if (titleEl && descEl && iconEl) {
                if (cita.estado === 'completada') {
                    iconEl.className = 'ri-checkbox-circle-line is-ok';
                    titleEl.textContent = 'Cita completada sin ficha.';
                    descEl.textContent = 'Esta cita se realizó con éxito, pero no se registraron observaciones clínicas adicionales ni recetas de medicamentos.';
                } else if (cita.estado === 'cancelada') {
                    iconEl.className = 'ri-close-circle-line is-cancelada';
                    titleEl.textContent = 'Cita cancelada.';
                    descEl.textContent = 'Esta cita fue cancelada y no generó historial clínico.';
                } else {
                    iconEl.className = 'ri-health-book-line';
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


