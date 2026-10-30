/* Portal del propietario — Zooki (Rediseño Estilo App Móvil) */

// escapeHtml() vive en avisos.js, que este layout carga antes (DRY).

/** Eventos de la agenda de salud: llegan en data-eventos de #portalAgendaDatos (sin JS en línea). */
function eventosDeLaAgenda() {
    const datos = document.getElementById('portalAgendaDatos');
    if (!datos) return [];
    try {
        return JSON.parse(datos.dataset.eventos || '[]');
    } catch (e) {
        console.error('Agenda: datos ilegibles', e);
        return [];
    }
}

/* ── Calendario de salud (M5 C4a, punto 7) ──
   Funciones puras sobre los eventos de #portalAgendaDatos: se prueban sin DOM. */

/** Las dosis próximas pueden caer en los años siguientes: el calendario llega hasta dos años adelante. */
const ANIOS_ADELANTE_CALENDARIO = 2;

/** Lo de un día: las citas y aplicaciones de esa fecha y las dosis que tocan ese día. */
function eventosDelDia(eventos, fecha, idMascota = 'all') {
    return eventos.filter(e => {
        const deLaMascota = idMascota === 'all' || String(e.id_mascota) === String(idMascota);
        return deLaMascota && (e.fecha === fecha || e.proxima === fecha);
    });
}

/** «martes, 14 de octubre», para el título del día elegido. */
function etiquetaDelDia(fecha) {
    const dia = new Date(fecha + 'T12:00:00');
    const texto = dia.toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' });
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** Días que faltan para una fecha, contando desde hoy (negativo si ya pasó). */
function diasHasta(fecha, hoy = new Date()) {
    const inicio = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
    return Math.ceil((new Date(fecha + 'T12:00:00') - inicio) / 86400000);
}

/**
 * Una fila de la agenda de salud. La cita abre su detalle; una vacuna o un
 * control se ven como «próxima dosis» en la fecha que toca y como aplicados
 * en la fecha en que se pusieron.
 */
function filaEventoDeSalud(e, fecha) {
    if (e.tipo === 'cita') {
        return `
            <div class="agenda-list-item agenda-list-item--accion agenda-list-item--cita" role="button" tabindex="0" data-ui-accion="detalle-cita-portal" data-ui-id="${Number(e.id_cita)}">
                <div class="agenda-icon-wrap"><i class="ri-calendar-event-line" aria-hidden="true"></i></div>
                <div class="agenda-item-info">
                    <h3>${escapeHtml(e.nombre_mascota)}</h3>
                    <p class="agenda-item-type">Cita: <strong>${escapeHtml(e.titulo)}</strong> · ${escapeHtml(e.detalle)}</p>
                    <span class="agenda-item-date">${formatFecha(e.fecha)} · ${escapeHtml(e.hora)}</span>
                </div>
                <div class="agenda-item-actions">${badgeEstado(e.estado)}</div>
            </div>`;
    }
    const esVacuna = e.tipo === 'vacuna';
    const icono = esVacuna ? 'ri-syringe-line' : 'ri-capsule-line';
    const fondo = esVacuna ? 'bg-vacuna' : 'bg-control';
    if (e.proxima && e.proxima === fecha) {
        const faltan = diasHasta(e.proxima);
        let aviso = `Faltan ${faltan} días`;
        if (faltan === 1) aviso = 'Mañana';
        if (faltan === 0) aviso = 'Hoy';
        if (faltan < 0) aviso = `Vencida hace ${Math.abs(faltan)} días`;
        return `
            <div class="agenda-list-item">
                <div class="agenda-icon-wrap ${fondo}"><i class="${icono}" aria-hidden="true"></i></div>
                <div class="agenda-item-info">
                    <h3>${escapeHtml(e.nombre_mascota)}</h3>
                    <p class="agenda-item-type">Próxima dosis: <strong>${escapeHtml(e.titulo)}</strong> · ${escapeHtml(e.detalle)}</p>
                    <span class="agenda-item-date agenda-item-date--suave">Última aplicación: ${formatFecha(e.fecha)}</span>
                </div>
                <div class="agenda-item-next">
                    <span class="tag-mini${faltan >= 0 ? ' tag-mini--azul' : ''}">${escapeHtml(aviso)}</span>
                    <span class="next-date">${formatFecha(e.proxima)}</span>
                </div>
            </div>`;
    }
    return `
        <div class="agenda-list-item">
            <div class="agenda-icon-wrap ${fondo}"><i class="${icono}" aria-hidden="true"></i></div>
            <div class="agenda-item-info">
                <h3>${escapeHtml(e.nombre_mascota)}</h3>
                <p class="agenda-item-type">${escapeHtml(e.titulo)} · ${escapeHtml(e.detalle)}</p>
                <span class="agenda-item-date">${esVacuna ? 'Vacuna aplicada' : 'Control realizado'}: ${formatFecha(e.fecha)}</span>
            </div>
        </div>`;
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

// Estados de la cita como los ve el propietario; los mismos de views/portal/index.php.
// Para él, "sin cerrar" sigue siendo una atención en curso.
const ESTADOS_CITA_PROPIETARIO = {
    pendiente: 'Activa', confirmada: 'Activa', en_curso: 'En atención', sin_cerrar: 'En atención',
    completada: 'Completada', cancelada: 'Cancelada', no_asistio: 'No asistió'
};

function etiquetaEstadoCita(estado) {
    if (ESTADOS_CITA_PROPIETARIO[estado]) return ESTADOS_CITA_PROPIETARIO[estado];
    return estado ? (estado.charAt(0).toUpperCase() + estado.slice(1)).replace(/_/g, ' ') : '—';
}

function claseEstadoCita(estado) {
    if (['pendiente', 'confirmada', 'en_curso', 'sin_cerrar'].includes(estado)) return 'status-badge--abierta';
    if (estado === 'completada') return 'status-badge--completada';
    if (estado === 'no_asistio') return 'status-badge--no-asistio';
    return 'status-badge--cancelada';
}

function badgeEstado(estado) {
    const abierta = ['pendiente', 'confirmada', 'en_curso', 'sin_cerrar'].includes(estado);
    return `<span class="tag-mini${abierta ? ' tag-mini--azul' : ''}">${escapeHtml(etiquetaEstadoCita(estado))}</span>`;
}

/** Dos letras para el avatar, como las tarjetas de Usuarios (PresentacionPlataforma::iniciales). */
function inicialesDe(nombre) {
    const palabras = String(nombre || '?').trim().split(/\s+/).slice(0, 2);
    return palabras.map(p => p.charAt(0)).join('').toUpperCase();
}

/** RN-114: la clínica de cada registro, como etiqueta. */
function etiquetaClinica(nombre) {
    return `<span class="tag-mini"><i class="ri-hospital-line" aria-hidden="true"></i> ${escapeHtml(nombre)}</span>`;
}

/** M2 C7 (RN-210, RN-G02): los síntomas marcados en una consulta de su mascota, si los hay. */
function lineaSintomas(sintomas) {
    if (!Array.isArray(sintomas) || sintomas.length === 0) return '';
    return `<p><strong>Síntomas:</strong> ${sintomas.map(s => escapeHtml(s.etiqueta)).join(', ')}</p>`;
}

/** M2 C7: un peso NULL dice que no hay peso registrado, nunca « kg» ni 0. */
function textoPesoConsulta(peso) {
    return peso === null || peso === undefined || peso === '' ? 'Sin peso registrado' : `${peso} kg`;
}

/** Cabecera de la ficha: especie y raza, y las clínicas donde se atiende. */
function etiquetasDeLaMascota(m, clinicas) {
    let html = `<span class="tag-mini pet-detail__especie">${escapeHtml([m.especie, m.raza].filter(Boolean).join(' · '))}</span><span class="pet-detail__clinicas">`;
    clinicas.forEach(c => {
        html += `<span class="tag-mini tag-mini--azul"><i class="ri-hospital-line" aria-hidden="true"></i> ${escapeHtml(c.nombre)}</span>`;
    });
    return html + '</span>';
}

/** Clínicas distintas entre todos los registros de la ficha, en orden de aparición. */
function clinicasDeLosRegistros(res) {
    const vistas = new Map();
    [res.historial, res.vacunas, res.desparasitaciones, res.citas].forEach(lista => {
        (lista || []).forEach(r => {
            if (!vistas.has(Number(r.id_clinica))) vistas.set(Number(r.id_clinica), r.clinica_nombre);
        });
    });
    return [...vistas].map(([id, nombre]) => ({ id, nombre }));
}

/** Con registros de varias clínicas, chips para ver solo los de una (RN-114). */
function prepararFiltroClinica(clinicas) {
    const filtro = document.getElementById('filtroClinicaMascota');
    if (!filtro) return;
    filtro.hidden = clinicas.length < 2;
    if (filtro.hidden) return;
    let html = '<button type="button" class="pet-chip active" data-filtro-clinica="all" aria-pressed="true"><span>Todas</span></button>';
    clinicas.forEach(c => {
        html += `<button type="button" class="pet-chip" data-filtro-clinica="${c.id}" aria-pressed="false"><span>${escapeHtml(c.nombre)}</span></button>`;
    });
    filtro.innerHTML = html;
}

function filtrarFichaPorClinica(idClinica) {
    const mascota = window.activePetData;
    if (mascota) renderSummary(mascota, window.activePetClinicas || [], idClinica);
    document.querySelectorAll('#screen-pet-detail [data-filtro-clinica]').forEach(chip => {
        const activo = chip.dataset.filtroClinica === idClinica;
        chip.classList.toggle('active', activo);
        chip.setAttribute('aria-pressed', activo ? 'true' : 'false');
    });
    document.querySelectorAll('#screen-pet-detail [data-id-clinica]').forEach(registro => {
        registro.hidden = idClinica !== 'all' && registro.dataset.idClinica !== idClinica;
    });
}

document.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-filtro-clinica]');
    if (chip) filtrarFichaPorClinica(chip.dataset.filtroClinica);
});

/* ── Actualización en su sitio (M5 C2b, pendiente «pantalla 3–4 s tarde») ──
   Tras una acción, la pantalla se actualiza con lo que dice el servidor sin
   recargar la página: se pide de nuevo el portal y se cambian solo las partes
   que dependen de los datos. La sección, la pestaña de Citas y las ventanas
   abiertas se conservan, porque sus elementos no se reemplazan. */
const PARTES_DEL_PORTAL = [
    'screen-home', 'filtroMascotasCitas', 'contadorProximas', 'citas-proximas', 'citas-pasadas',
    'misClinicas', 'bookingEleccionClinica', 'bookingEleccionMascota'
];
const ATRIBUTOS_DEL_PORTAL = [['portalAgendaDatos', 'data-eventos'], ['portalBookingForm', 'data-dias-cerrados']];

/** Copia del portal nuevo las partes y los atributos que cambian, y avisa a quien los pinta. */
function aplicarPartes(nuevo) {
    PARTES_DEL_PORTAL.forEach(id => {
        const actual = document.getElementById(id);
        const reciente = nuevo.getElementById(id);
        if (actual && reciente) actual.innerHTML = reciente.innerHTML;
    });
    ATRIBUTOS_DEL_PORTAL.forEach(([id, atributo]) => {
        const actual = document.getElementById(id);
        const reciente = nuevo.getElementById(id);
        if (actual && reciente) actual.setAttribute(atributo, reciente.getAttribute(atributo) || '');
    });
    document.dispatchEvent(new CustomEvent('portal:actualizado'));
}

async function refrescarPortal() {
    try {
        const respuesta = await fetch('index.php?action=portal_propietario', { credentials: 'same-origin' });
        const nuevo = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
        // Sin el portal en la respuesta (por ejemplo, venció la sesión y llegó el login), se recarga entera.
        if (!respuesta.ok || !nuevo.getElementById('screen-home')) {
            window.location.reload();
            return;
        }
        aplicarPartes(nuevo);
        // La ficha abierta también se pone al día, en la misma pestaña.
        const ficha = document.getElementById('screen-pet-detail');
        if (ficha && ficha.classList.contains('active') && window.activePetData) {
            verDetalle(window.activePetData.id_mascota, { historial: false, conservarPestana: true });
        }
    } catch (error) {
        console.error('Portal: no se pudo actualizar', error);
        window.location.reload();
    }
}

/* ── Navegación entre secciones (M5 C2: Inicio · Citas · Clínicas · Perfil) ──
   Cada sección tiene su dirección (#citas, #perfil…) para que el botón
   «atrás» del navegador y la recarga de la página respeten dónde estaba
   (RE-5.1.6). */
const SECCIONES_URL = { home: 'inicio', citas: 'citas', clinicas: 'clinicas', account: 'perfil' };

/** Direcciones anteriores a C2: un enlace guardado sigue llevando a algún lado. */
const SECCIONES_ANTERIORES = { agenda: 'citas', servicios: 'home', recordatorios: 'home' };

function seccionDesdeHash(hash) {
    const limpio = (hash || '').replace(/^#/, '');
    const mascota = limpio.match(/^mascota-(\d+)$/);
    if (mascota) return { mascota: Number(mascota[1]) };
    const salud = limpio.match(/^salud-(\d+)$/);
    if (salud) return { tab:'citas', salud:Number(salud[1]) };
    const tab = Object.keys(SECCIONES_URL).find(k => SECCIONES_URL[k] === limpio);
    return { tab: tab || SECCIONES_ANTERIORES[limpio] || 'home' };
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
}

// Botones de navegación (menú lateral, barra inferior y enlaces «Ver todas»).
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
    const retornoCarnet = document.getElementById('portalRetornoCarnet');
    if (retornoCarnet) {
        const id = Number(retornoCarnet.dataset.mascota);
        verDetalle(id, { historial: false });
        history.replaceState({ zkMascota: id }, '', '#inicio');
        return;
    }
    const destino = seccionDesdeHash(location.hash);
    if (destino.mascota) verDetalle(destino.mascota, { historial: false });
    else if (destino.tab !== 'home') switchTab(destino.tab, { historial: false });
    const direccion = destino.mascota || destino.salud ? location.hash : '#' + SECCIONES_URL[destino.tab];
    history.replaceState(destino.mascota ? { zkMascota: destino.mascota } : { zkTab: destino.tab }, '', direccion);
});

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('portalContactEditForm');
    if (!form) return;
    form.addEventListener('submit', (evento) => {
        evento.preventDefault();
        submitContactEditPortal();
    });
});

/**
 * Decisión C6: el propietario cambia su teléfono aquí mismo; el correo y el
 * documento, con verificación, en sus modales (M5 C4a, Modelos §13.2).
 * El número sale con su prefijo (js/telefono-internacional.js).
 */
async function submitContactEditPortal() {
    const campo = document.getElementById('portal_contact_phone');
    const resultado = document.querySelector('#portalContactEditForm [data-password-resultado]');

    // C9.1: la misma regla del servidor (ValidadorTelefono), avisada en el campo.
    if (!campo.checkValidity()) {
        campo.reportValidity();
        return;
    }

    const btn = document.getElementById('btnSubmitContactEdit');
    const btnText = btn ? btn.querySelector('span') : null;
    if (btn && btnText) {
        btn.disabled = true;
        btnText.textContent = 'Guardando…';
    }

    try {
        const formData = new FormData();
        formData.append('telefono', window.zookiNumeroTelefono ? window.zookiNumeroTelefono(campo) : campo.value.trim());

        const res = await (await fetch('index.php?action=portal_actualizar_datos_contacto_ajax', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })).json();

        if (res.success) {
            zookiToast(res.message || 'Datos de contacto actualizados.', 'success');
            if (resultado) resultado.textContent = '';
            document.getElementById('portalContactEditForm').dispatchEvent(new CustomEvent('portal:telefono-guardado', { bubbles: true, detail: { telefono: campo.value } }));
        } else if (resultado) {
            // Un error del campo va junto a él, no en una ventana (M0.1).
            resultado.textContent = res.message || 'No se pudo actualizar el teléfono.';
            resultado.className = 'cuenta-validacion cuenta-validacion--error';
        }
    } catch (e) {
        console.error(e);
        zookiToast('No se pudo conectar con el servidor.', 'error');
    } finally {
        if (btn && btnText) {
            btn.disabled = false;
            btnText.textContent = 'Guardar';
        }
    }
}

/* ── Modales del Perfil (M5 C4a, puntos 9 y 10) ──
   Correo, documento y contraseña se cambian en los modales de Zooki. Los
   formularios los atienden validacion-cuenta.js y cuenta-password.js; al
   guardar avisan con «cuenta:guardada» y aquí se cierra su ventana. */
const MODALES_DEL_PERFIL = ['portalCorreoModal', 'portalDocumentoModal', 'portalPasswordModal'];

document.addEventListener('click', (e) => {
    const abrir = e.target.closest('[data-abrir-modal]');
    if (abrir && MODALES_DEL_PERFIL.includes(abrir.dataset.abrirModal)) {
        openModal(abrir.dataset.abrirModal);
        return;
    }
    const cerrar = e.target.closest('#btnCloseCorreoModal, #btnCloseDocumentoModal, #btnClosePasswordModal');
    if (cerrar) closeModal(cerrar.closest('.portal-drawer-overlay').id);
});

document.addEventListener('cuenta:guardada', (e) => {
    const ventana = e.target.closest ? e.target.closest('.portal-drawer-overlay') : null;
    if (!ventana || !MODALES_DEL_PERFIL.includes(ventana.id)) return;
    closeModal(ventana.id);
    const mensaje = e.detail && e.detail.message;
    if (mensaje) zookiToast(mensaje, 'success');
    const resultado = e.target.querySelector('[data-identidad-resultado], [data-password-resultado]');
    if (resultado) resultado.textContent = '';
});

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

/**
 * Pestañas del portal (Citas y Mascota), con los componentes de Usuarios
 * (tabs-wrapper y tab-btn). Cada botón nombra su panel en data-pestana.
 */
function activarPestana(grupo, idPanel) {
    if (!grupo) return;
    grupo.querySelectorAll('[data-pestana]').forEach(boton => {
        const activa = boton.dataset.pestana === idPanel;
        boton.classList.toggle('active', activa);
        boton.setAttribute('aria-selected', activa ? 'true' : 'false');
        const panel = document.getElementById(boton.dataset.pestana);
        if (panel) panel.hidden = !activa;
    });
}

document.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-pestana]');
    if (!boton) return;
    activarPestana(boton.closest('[data-pestanas]'), boton.dataset.pestana);
});

// Esc cierra la ventana abierta; si no hay ninguna, sale de la ficha de la mascota.
// Antes volvía a Inicio desde cualquier pantalla, aunque hubiera una ventana abierta.
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || document.querySelector('.zooki-confirmacion')) return;
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

function renderSummary(mascota, clinicas = [], idClinica = 'all') {
    const edad = calcularEdad(mascota.fecha_nacimiento);
    const clinica = idClinica === 'all' ? clinicas[0] : clinicas.find(c => String(c.id_clinica) === String(idClinica));
    const hc = clinica?.numero_historia_clinica || mascota.numero_historia_clinica || '—';
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
        <a class="portal-boton-secundario pet-detail__pdf" href="index.php?action=portal_imprimir_historial&amp;id_mascota=${encodeURIComponent(mascota.id_mascota)}&amp;origen=mascota"><i class="ri-file-pdf-line" aria-hidden="true"></i> Historia clínica (PDF)</a>
    `;
}

function toggleAccordion(card) {
    const abierta = card.classList.toggle('active');
    card.setAttribute('aria-expanded', abierta ? 'true' : 'false');
}

async function verDetalle(id, opciones = {}) {
    openDrawer();
    if (opciones.historial !== false) history.pushState({ zkMascota: id, zkDentro: true }, '', `#mascota-${id}`);
    if (!opciones.conservarPestana) activarPestana(document.querySelector('[data-pestanas="mascota"]'), 'tab-historial');

    const imprimir = document.getElementById('btnImprimirFichaMascota');
    if (imprimir) imprimir.hidden = true;
    if (!opciones.conservarPestana) {
        document.getElementById('drawerPetTitle').innerHTML = 'Cargando…';
        document.getElementById('drawerPetTags').replaceChildren();
        document.getElementById('filtroClinicaMascota').hidden = true;
    }
    document.getElementById('drawerPetSummary').innerHTML = '';
    document.getElementById('historialContent').innerHTML = '<div class="portal-loading">Cargando historial…</div>';
    if (window.ZookiCarnet) window.ZookiCarnet.cargar(id);
    document.getElementById('citasContent').innerHTML = '';
    document.getElementById('vacunasContent').innerHTML = '';
    document.getElementById('desparasitacionesContent').innerHTML = '';
    // M2 C1: no se muestran las alertas de otra mascota mientras carga esta.
    const alertasMascota = document.getElementById('alertasMascota');
    if (alertasMascota) alertasMascota.hidden = true;

    try {
        const res = await (await fetch(`index.php?action=ver_detalle_mascota_propietario_ajax&id_mascota=${id}`)).json();
        if (!res.success) {
            zookiAviso(res.message || 'No se pudieron obtener los detalles.');
            switchTab('home', { reemplazar: true });
            return;
        }

        const m = res.mascota;
        window.activePetData = m;
        window.activePetClinicas = res.clinicas || [];
        // C9.1 (RE-5.11): el historial para imprimir de esta mascota.
        if (imprimir) {
            imprimir.href = `index.php?action=portal_imprimir_historial&id_mascota=${encodeURIComponent(m.id_mascota)}&origen=mascota`;
            imprimir.hidden = false;
        }
        
        const avatarHtml = m.url_foto
            ? `<span class="avatar-mini"><img src="uploads/mascotas/${escapeHtml(m.url_foto)}" alt=""></span>`
            : `<span class="avatar-mini avatar-iniciales" aria-hidden="true">${escapeHtml(inicialesDe(m.nombre))}</span>`;

        document.getElementById('drawerPetTitle').innerHTML = `${avatarHtml}<span class="pet-detail__nombre">${escapeHtml(m.nombre)}<small class="pet-detail__raza-movil">${escapeHtml([m.especie, m.raza].filter(Boolean).join(' · '))}</small></span>`;
        document.getElementById('drawerPetTags').innerHTML = etiquetasDeLaMascota(m, res.clinicas || []);

        renderSummary(m, res.clinicas || []);
        // M2 C1 (RE-2.10.1, RN-114): de solo lectura, con la clínica que registró cada una.
        if (alertasMascota && window.ZookiAlertas) {
            window.ZookiAlertas.pintar(alertasMascota, { activas: res.alertas || [], historial: [] }, { idMascota: m.id_mascota, lectura: true, iconos: 'ri' });
        }

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
                        <a href="ver_archivo.php?id=${encodeURIComponent(file.id_archivo)}" target="_blank" rel="noopener" class="portal-file-link" data-ui-detener>
                            <i class="ri-file-pdf-line" aria-hidden="true"></i> ${escapeHtml(file.nombre_original)}
                        </a>`;
                    });
                    filesHtml += `</div>`;
                }

                html += `
                <div class="portal-timeline-item" data-id-clinica="${Number(h.id_clinica)}">
                    <div class="portal-timeline-date">${escapeHtml(fechaHistorial(h.fecha_hora))} · ${escapeHtml(String(h.fecha_hora || '').slice(11, 16))}</div>
                    <div class="portal-timeline-card accordion-card" role="button" tabindex="0" aria-expanded="false" data-ui-accion="acordeon-historia">
                        <div class="accordion-header">
                            <h3>${escapeHtml(h.motivo_consulta)}</h3>
                            <i class="ri-arrow-down-s-line accordion-icon" aria-hidden="true"></i>
                        </div>
                        <div class="accordion-body">
                            ${etiquetaClinica(h.clinica_nombre)}
                            ${lineaSintomas(h.sintomas)}
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
                // Solo se cancela en una clínica con vínculo activo (lo dice el servidor en `cancelable`).
                let cancelBtn = '';
                if (c.cancelable) {
                    cancelBtn = `<button type="button" class="portal-btn-cancel" data-ui-accion="cancelar-cita-portal" data-ui-id="${Number(c.id_cita)}"><i class="ri-close-circle-line" aria-hidden="true"></i> Cancelar cita</button>`;
                }

                if (c.cancelable) cancelBtn += `<button type="button" class="portal-btn-cancel" data-ui-detener data-reprogramar-cita="${Number(c.id_cita)}">Reprogramar</button>`;
                html += `
                <div class="portal-list-item" data-id-clinica="${Number(c.id_clinica)}">
                    <div class="portal-list-icon portal-list-icon--cita"><i class="ri-calendar-check-line"></i></div>
                    <div class="portal-list-body">
                        <strong>${formatFecha(c.fecha)} · ${escapeHtml((c.hora || '').substring(0, 5))}</strong>
                        <p>${escapeHtml(c.motivo)}</p>
                        ${c.inicio_estimado ? `<p>Inicio estimado: ${escapeHtml(c.inicio_estimado)}</p>` : ''}
                        ${c.estado_reajuste === 'pendiente_reprogramacion' ? '<p>Contacta a la clínica o elige Reprogramar para consultar alternativas.</p>' : ''}
                        ${c.veterinario_nombre ? `<p>Veterinario: ${escapeHtml(c.veterinario_nombre)}</p>` : ''}
                        <div class="card-tags-mini">${etiquetaClinica(c.clinica_nombre)}${badgeEstado(c.estado)}</div>
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
                <div class="portal-list-item" data-id-clinica="${Number(v.id_clinica)}">
                    <div class="portal-list-icon portal-list-icon--vacuna"><i class="ri-syringe-line"></i></div>
                    <div class="portal-list-body">
                        <strong>${escapeHtml(v.nombre_vacuna)}</strong>
                        <p>Aplicada: ${formatFecha(v.fecha_aplicacion)}</p>
                        ${v.fecha_proxima_dosis ? `<p class="portal-proxima">Próxima dosis: ${formatFecha(v.fecha_proxima_dosis)}</p>` : ''}
                        ${v.laboratorio ? `<p>Laboratorio: ${escapeHtml(v.laboratorio)}</p>` : ''}
                        <div class="card-tags-mini">${etiquetaClinica(v.clinica_nombre)}</div>
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
                <div class="portal-list-item" data-id-clinica="${Number(d.id_clinica)}">
                    <div class="portal-list-icon portal-list-icon--desparasitacion"><i class="ri-capsule-line"></i></div>
                    <div class="portal-list-body">
                        <strong>${escapeHtml(d.producto)} (${escapeHtml(d.tipo)})</strong>
                        <p>Aplicada: ${formatFecha(d.fecha_aplicacion)}</p>
                        ${d.fecha_proxima ? `<p class="portal-proxima portal-proxima--control">Próxima dosis: ${formatFecha(d.fecha_proxima)} (${escapeHtml(d.periodicidad)})</p>` : ''}
                        ${d.observaciones ? `<p>Observaciones: ${escapeHtml(d.observaciones)}</p>` : ''}
                        <div class="card-tags-mini">${etiquetaClinica(d.clinica_nombre)}</div>
                    </div>
                </div>`;
            });
            document.getElementById('desparasitacionesContent').innerHTML = html;
        }

        const clinicasFiltro = new Map((res.clinicas || []).map(c => [Number(c.id_clinica), { id:Number(c.id_clinica), nombre:c.nombre }]));
        clinicasDeLosRegistros(res).forEach(c => { if (!clinicasFiltro.has(c.id)) clinicasFiltro.set(c.id, c); });
        prepararFiltroClinica([...clinicasFiltro.values()]);
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

/* Lógica de Agendamiento desde el Portal (HU-26) */
document.addEventListener('DOMContentLoaded', () => {
    // --- LÓGICA COMPLETA DE CALENDARIO, FILTROS Y TIMELINE (V1.6.0) ---
    let selectedPetId = 'all';
    let selectedCalendarDate = null; // Formato YYYY-MM-DD
    const today = new Date();
    let currentCalMonth = today.getMonth(); // 0-11
    let currentCalYear = today.getFullYear();

    const printBtn = document.getElementById('btnImprimirHistorial');

    // Nombres de meses en español
    const monthNames = [
        "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
    ];

    // «Vacunas y controles»: próximas dosis y aplicadas de la mascota elegida.
    // M5 C4a: las citas tienen sus pestañas y el día elegido su propio panel.
    const renderSaludLists = () => {
        const proximasList = document.getElementById('saludProximasList');
        const historialList = document.getElementById('saludHistorialList');
        const proximasContainer = document.getElementById('agenda-salud-proximas');
        if (!proximasList || !historialList) return;

        const filtrados = eventosDeLaAgenda().filter(e => e.tipo !== 'cita' && (selectedPetId === 'all' || String(e.id_mascota) === String(selectedPetId)));
        const proximas = filtrados.filter(e => e.proxima).sort((x, y) => String(x.proxima).localeCompare(String(y.proxima)));

        proximasContainer.hidden = proximas.length === 0;
        proximasList.innerHTML = proximas.map(e => filaEventoDeSalud(e, e.proxima)).join('');
        historialList.innerHTML = filtrados.map(e => filaEventoDeSalud(e, e.fecha)).join('')
            || '<div class="agenda-empty-state"><p>No hay vacunas ni controles registrados.</p></div>';
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
        const thisYear = new Date().getFullYear() + ANIOS_ADELANTE_CALENDARIO;
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
        const eventos = eventosDeLaAgenda();

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

        // Un día marcado muestra lo de ese día; tocarlo otra vez vuelve a la lista.
        grid.querySelectorAll('.cal-day-cell.tiene-eventos[data-date]').forEach(cell => {
            cell.addEventListener('click', () => {
                const date = cell.dataset.date;
                if (selectedCalendarDate === date) {
                    resetCalendarDateFilter();
                    return;
                }
                selectedCalendarDate = date;
                renderDiaElegido();
                renderMiniCalendar();
            });
        });
    };

    // M5 C4a (punto 7): lo del día elegido ocupa el lugar de las pestañas, en la misma columna.
    const renderDiaElegido = () => {
        const vista = document.querySelector('[data-citas-vista]');
        const panel = document.getElementById('citasDiaElegido');
        if (!vista || !panel) return;
        const hayDia = selectedCalendarDate !== null;
        panel.hidden = !hayDia;
        vista.classList.toggle('citas-vista--dia', hayDia);
        if (!hayDia) return;
        document.getElementById('citasDiaTitulo').textContent = etiquetaDelDia(selectedCalendarDate);
        const delDia = eventosDelDia(eventosDeLaAgenda(), selectedCalendarDate, selectedPetId);
        document.getElementById('citasDiaLista').innerHTML = delDia.map(e => filaEventoDeSalud(e, selectedCalendarDate)).join('')
            || '<div class="agenda-empty-state"><p>Nada este día para esta mascota.</p></div>';
    };

    // Resetear filtro de fecha del calendario
    window.resetCalendarDateFilter = () => {
        selectedCalendarDate = null;
        renderDiaElegido();
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
            const thisYear = new Date().getFullYear() + ANIOS_ADELANTE_CALENDARIO;
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

    // Filtrar Próximas y Pasadas por mascota; cada panel dice si quedó vacío.
    const filterCitas = () => {
        document.querySelectorAll('[data-panel-citas]').forEach(panel => {
            let visibles = 0;
            panel.querySelectorAll('.portal-cita[data-pet-id]').forEach(tarjeta => {
                const mostrar = selectedPetId === 'all' || tarjeta.dataset.petId === selectedPetId;
                tarjeta.hidden = !mostrar;
                if (mostrar) visibles++;
            });
            const aviso = panel.querySelector('.portal-filtro-vacio');
            const hayTarjetas = panel.querySelector('.portal-cita') !== null;
            if (hayTarjetas && visibles === 0 && !aviso) {
                const p = document.createElement('p');
                p.className = 'portal-filtro-vacio';
                p.textContent = 'No hay citas de esta mascota aquí.';
                panel.append(p);
            }
            if (visibles > 0 && aviso) aviso.remove();
        });
    };

    // Chips de mascota: delegados, porque se repintan al actualizar el portal.
    const elegirMascota = (chip) => {
        document.querySelectorAll('.pet-chip[data-pet-id]').forEach(c => {
            c.classList.toggle('active', c === chip);
            c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
        });
        selectedPetId = chip.dataset.petId;

        // Imprimir la ficha de salud solo con una mascota elegida.
        if (printBtn) {
            printBtn.hidden = selectedPetId === 'all';
            printBtn.href = `index.php?action=portal_imprimir_historial&id_mascota=${encodeURIComponent(selectedPetId)}&origen=salud`;
        }

        // Filtrar ambos módulos
        filterCitas();
        resetCalendarDateFilter();
    };

    document.addEventListener('click', (e) => {
        const chip = e.target.closest('.pet-chip[data-pet-id]');
        if (chip) elegirMascota(chip);
    });

    // Tras una acción: el calendario y las listas con los datos nuevos, con el mismo filtro.
    document.addEventListener('portal:actualizado', () => {
        const chip = document.querySelector(`.pet-chip[data-pet-id="${selectedPetId}"]`);
        if (chip) elegirMascota(chip);
        else selectedPetId = 'all';
        filterCitas();
        if (document.getElementById('agenda-salud')) {
            renderMiniCalendar();
            renderSaludLists();
            renderDiaElegido();
        }
    });

    // Cargar datos por primera vez al abrir
    if (document.getElementById('agenda-salud')) {
        renderMiniCalendar();
        renderSaludLists();
    }

    // RE-5.5.1: el regreso del PDF recupera Vacunas y su mascota sin guardar datos personales.
    const retornoSalud = location.hash.match(/^#salud-(\d+)$/);
    if (retornoSalud) {
        switchTab('citas', { historial:false });
        activarPestana(document.querySelector('[data-pestanas="citas"]'), 'agenda-salud');
        const chip = document.querySelector(`.pet-chip[data-pet-id="${Number(retornoSalud[1])}"]`);
        if (chip) elegirMascota(chip);
        history.replaceState({ zkTab:'citas' }, '', '#citas');
    }

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

    // ── Agendar cita (M5 C3, decisión del usuario): un solo formulario con la
    // clínica, la mascota, el tipo, el veterinario, el día y la hora a la
    // vista. Los horarios salen cuando hay tipo, veterinario y día; una línea
    // dice qué falta y «Agendar» abre una confirmación corta.
    const bookingModal = document.getElementById('portalBookingModal');
    const form = document.getElementById('portalBookingForm');
    if (!bookingModal || !form) return;

    const closeBtn = document.getElementById('btnCloseBookingModal');
    // C6 / RE-5.9.3: primero la clínica, entre las vinculadas; catálogos y horarios son de esa clínica.
    const clinicaSelect = eleccion(form, 'id_clinica');
    const mascotaSelect = eleccion(form, 'id_mascota');
    const tipoCitaSelect = document.getElementById('booking_tipo_cita');
    const vetSelect = document.getElementById('booking_veterinario');
    const dateInput = document.getElementById('booking_fecha');
    const horaInput = document.getElementById('booking_hora');
    const slots = document.getElementById('booking_slots');
    const guia = document.getElementById('booking_guia');
    const confirmar = document.getElementById('booking_confirmar');
    const triageC4 = ZookiTriageAgenda.crear(form.querySelector('[data-triage-agenda]'), {
        seleccion: () => ({ id_clinica: clinicaSelect.value, id_mascota: mascotaSelect.value, id_veterinario: vetSelect.value, id_tipo_cita: tipoCitaSelect.value, fecha: dateInput.value }),
        // Pulido de interfaz: el celo solo se pregunta a hembras sin esterilizar.
        mascota: () => { const r = form.querySelector('[name="id_mascota"]:checked'); return r ? { sexo: r.dataset.sexo || '', esterilizado: r.dataset.esterilizado ?? '' } : null; },
        alCambiar: () => cargarHoras()
    });
    // Días sin atención en toda la jornada de cada clínica (1 = lunes … 7 = domingo); se relee tras actualizar.
    const diasCerrados = () => (JSON.parse(form.dataset.diasCerrados || '{}')[clinicaSelect.value]) || [];
    let turno = 0; // si el usuario cambia algo mientras cargan los horarios, se ignora la respuesta vieja
    const catalogosPorClinica = {};

    const minutosDe = h => { const [hh, mm] = h.split(':').map(Number); return hh * 60 + mm; };
    const hora12 = h => zookiHoraAgenda(h, 'a. m.', 'p. m.');
    const hoyIso = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };

    const calendario = typeof flatpickr !== 'undefined' ? flatpickr(dateInput, {
        locale: window.zkLocale || 'es',
        dateFormat: 'Y-m-d',
        minDate: 'today',
        inline: true,
        disableMobile: true,
        monthSelectorType: 'static',
        disable: [d => diasCerrados().includes(d.getDay() === 0 ? 7 : d.getDay())],
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

    /** Lo que falta para agendar, en el orden del formulario (faltanParaAgendar, más abajo). */
    const faltan = () => faltanParaAgendar({
        clinica: clinicaSelect.value,
        mascota: mascotaSelect.value,
        tipo: tipoCitaSelect.value,
        veterinario: vetSelect.value,
        dia: dateInput.value,
        hora: horaInput.value,
    });

    function actualizarGuia() {
        const pendientes = faltan();
        guia.textContent = guiaParaAgendar(pendientes);
        confirmar.disabled = pendientes.length > 0 || !triageC4.consulta();
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
            actualizarGuia();
        }
    });

    async function cargarHoras() {
        const miTurno = ++turno;
        horaInput.value = '';
        actualizarGuia();

        if (!clinicaSelect.value || !tipoCitaSelect.value || !vetSelect.value || !mascotaSelect.value) {
            estadoSlots('guia', 'Elige primero el tipo de cita y el veterinario.');
            return;
        }
        if (!dateInput.value) {
            estadoSlots('guia', 'Elige el día para ver los horarios libres.');
            return;
        }

        const solicitud = triageC4.consulta();
        if (!solicitud) {
            estadoSlots('guia', 'Calcula el triage para consultar horarios. Si el nivel es rojo, contacta a urgencias.');
            return;
        }
        const fecha = dateInput.value;
        const vet = vetSelect.value;
        const clinica = clinicaSelect.value;
        const tipo = tipoCitaSelect.value;
        const mascota = mascotaSelect.value;
        estadoSlots('cargando');

        try {
            const parametros = new URLSearchParams({ action: 'portal_get_sugerencias_ajax', id_clinica: clinica, id_veterinario: vet, id_mascota: mascota, id_tipo_cita: tipo, fecha, id_solicitud: solicitud });
            const agenda = await fetch(`index.php?${parametros}`).then(r => r.json());
            if (miTurno !== turno) return;
            if (!agenda.success) {
                estadoSlots('vacio', agenda.message || 'No se pudieron cargar los horarios. Intenta de nuevo.');
                return;
            }
            const horas = Array.isArray(agenda.sugerencias) ? agenda.sugerencias : [];
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

    /** Clínica, mascota, día y hora: lo que dice la confirmación corta. */
    function filasDeLaCita() {
        const dia = calendario && calendario.selectedDates[0]
            ? calendario.selectedDates[0].toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' })
            : dateInput.value;
        return [
            ['Clínica', clinicaSelect.texto()],
            ['Mascota', mascotaSelect.texto()],
            ['Día', dia],
            ['Hora', horaInput.value ? hora12(horaInput.value) : ''],
        ];
    }

    /** Tipos de cita y veterinarios de la clínica elegida (una vez por clínica). */
    async function cargarCatalogos() {
        const clinica = clinicaSelect.value;
        if (!clinica) {
            tipoCitaSelect.innerHTML = vetSelect.innerHTML = '<option value="">Elige primero la clínica</option>';
            return;
        }
        try {
            if (!catalogosPorClinica[clinica]) {
                const [resTipos, resVets] = await Promise.all([
                    fetch(`index.php?action=portal_get_tipos_cita_ajax&id_clinica=${encodeURIComponent(clinica)}`).then(r => r.json()),
                    fetch(`index.php?action=portal_get_vets_ajax&id_clinica=${encodeURIComponent(clinica)}`).then(r => r.json())
                ]);
                catalogosPorClinica[clinica] = {
                    tipos: resTipos.success ? resTipos.tipos : [],
                    veterinarios: Array.isArray(resVets) ? resVets : []
                };
            }
            const { tipos, veterinarios } = catalogosPorClinica[clinica];
            tipoCitaSelect.innerHTML = '<option value="">Selecciona…</option>' + tipos.map(t =>
                `<option value="${Number(t.id_tipo_cita)}" data-duracion="${Number(t.duracion_minutos)}">${escapeHtml(t.nombre_tipo)} (${Number(t.duracion_minutos)} min)</option>`
            ).join('');
            vetSelect.innerHTML = '<option value="">Selecciona…</option>' + veterinarios.map(v =>
                `<option value="${Number(v.id_usuario)}">Dr(a). ${escapeHtml(v.nombre_completo)}</option>`
            ).join('');
        } catch (e) {
            console.error('Error al cargar catálogos:', e);
            tipoCitaSelect.innerHTML = vetSelect.innerHTML = '<option value="">No se pudo cargar. Cierra y vuelve a abrir.</option>';
        }
        actualizarGuia();
    }

    const comandoC3 = zookiCrearComandoAgenda();
    let enviandoC3 = false;
    const abrirBooking = async () => {
        comandoC3.reiniciar();
        form.reset();
        horaInput.value = '';
        if (calendario) calendario.clear();
        // Con una sola mascota no hay nada que elegir.
        mascotaSelect.elegirUnica();
        openModal('portalBookingModal');
        if (calendario) calendario.redraw();
        await cargarCatalogos();
        cargarHoras();
    };

    // Todos los botones de agendar (menú lateral, barra inferior y avisos), delegados: se repintan.
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-agendar]')) abrirBooking();
        if (e.target.closest('[data-cerrar-agendar]')) closeModal('portalBookingModal');
    });
    if (closeBtn) closeBtn.addEventListener('click', () => closeModal('portalBookingModal'));
    bookingModal.addEventListener('click', (e) => {
        if (e.target === bookingModal) closeModal('portalBookingModal');
    });

    // Otra clínica: otros tipos, veterinarios y días de atención.
    clinicaSelect.alCambiar(async () => {
        if (calendario) {
            calendario.clear();
            calendario.redraw();
        }
        await cargarCatalogos();
        cargarHoras();
    });
    mascotaSelect.alCambiar(cargarHoras);
    tipoCitaSelect.addEventListener('change', cargarHoras);
    vetSelect.addEventListener('change', cargarHoras);

    const enviarFormulario = async (ignoreWarning = false) => {
        if (enviandoC3) return;
        enviandoC3 = true;
        let repetirAviso = false;
        const submitBtn = confirmar;
        const reactivar = () => {
            submitBtn.querySelector('span').textContent = 'Agendar';
            actualizarGuia();
        };
        submitBtn.disabled = true;
        submitBtn.querySelector('span').textContent = 'Agendando...';

        try {
            const fd = new FormData(form);
            fd.set('id_solicitud', triageC4.consulta() || '');
            fd.set('clave_operacion', comandoC3.clave(fd));
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
                reactivar();
                if (res.id_cita) {
                    const mailFd = new FormData();
                    mailFd.append('id_cita', res.id_cita);
                    mailFd.append('tipo', 'confirmacion_nueva');
                    fetch('index.php?action=enviar_email_ajax', {
                        method: 'POST',
                        body: mailFd,
                        keepalive: true
                    }).catch(console.error);
                }
                // M5 C2b: el aviso sale ya y la pantalla se pone al día sin recargar.
                zookiToast(res.message || 'Tu cita quedó agendada.', 'success');
                refrescarPortal();
            } else if (res.has_warning) {
                // C9: una cita adicional del mismo día pide una decisión explícita (M5 C4a: modal de Zooki).
                const otraIgual = await zookiConfirmarCorto(res.message || '¿Agendar otra cita el mismo día?', 'Agendar igual', [], 'Volver');
                if (otraIgual) {
                    repetirAviso = true;
                }
                else reactivar();
            } else {
                // El horario pudo ocuparse mientras tanto: se vuelven a pedir los horarios libres.
                zookiToast(res.message || 'No se pudo agendar. Inténtalo nuevamente.', 'error');
                reactivar();
                cargarHoras();
            }
        } catch (error) {
            console.error('Error al agendar cita:', error);
            zookiToast('No pudimos conectarnos con el servidor. Inténtalo más tarde.', 'error');
            reactivar();
        } finally {
            enviandoC3 = false;
            if (repetirAviso) enviarFormulario(true);
        }
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (faltan().length > 0) {
            actualizarGuia();
            return;
        }
        const confirmada = await zookiConfirmarCorto('¿Agendar esta cita?', 'Agendar', filasDeLaCita());
        if (confirmada) enviarFormulario(false);
    });
});

/**
 * M5 C3: lo que falta para agendar, en el orden del formulario. Recibe los
 * valores elegidos y devuelve sus nombres; vacío si se puede agendar.
 */
function faltanParaAgendar(valores) {
    const nombres = [
        ['clinica', 'la clínica'],
        ['mascota', 'la mascota'],
        ['tipo', 'el tipo de cita'],
        ['veterinario', 'el veterinario'],
        ['dia', 'el día'],
        ['hora', 'la hora'],
    ];
    return nombres.filter(([clave]) => !valores[clave]).map(([, nombre]) => nombre);
}

/** La línea bajo el formulario: «Elige la mascota y la hora.», o nada si está completo. */
function guiaParaAgendar(faltan) {
    if (!faltan.length) return '';
    const lista = faltan.length > 1 ? `${faltan.slice(0, -1).join(', ')} y ${faltan[faltan.length - 1]}` : faltan[0];
    return `Elige ${lista}.`;
}

/**
 * Tarjetas de elección (radios) como un campo: valor, texto, cambio y la
 * opción única. Con una sola clínica el valor llega en un campo oculto
 * (RE-5.9.3, hueco 8). Se leen los campos cada vez: se repintan al actualizar.
 */
function eleccion(form, nombre) {
    const campos = () => [...form.querySelectorAll(`[name="${nombre}"]`)];
    const elegido = () => campos().find(c => c.type === 'hidden' || c.checked) || null;
    return {
        get value() { return elegido() ? elegido().value : ''; },
        texto() { return elegido() ? (elegido().dataset.nombre || '') : ''; },
        elegirUnica() {
            const opciones = campos();
            if (opciones.length === 1 && opciones[0].type === 'radio') opciones[0].checked = true;
        },
        alCambiar(accion) {
            form.addEventListener('change', (e) => { if (e.target.name === nombre) accion(); });
        },
    };
}

/**
 * Mascota y día de la cita, leídos de su fila en pantalla, para la
 * confirmación de cancelar. Sin la fila (por ejemplo, desde la ficha), nada.
 */
function filasDeCitaEnPantalla(idCita) {
    const fila = document.querySelector(`.portal-cita[data-ui-id="${Number(idCita)}"]`);
    if (!fila) return [];
    const mascota = fila.querySelector('.portal-cita__cuando strong');
    const cuando = fila.querySelector('.portal-cita__cuando span');
    return [['Mascota', mascota ? mascota.textContent : ''], ['Día y hora', cuando ? cuando.textContent : '']];
}

/* Cancelar una cita desde el portal (RN-405): es lo único irreversible del portal y se confirma. */
const cancelacionesC4 = new Map();
const cancelacionesEnCursoC4 = new Set();
async function cancelarCitaPortal(idCita) {
    if (cancelacionesEnCursoC4.has(idCita)) return;
    const confirmada = await zookiConfirmarCorto('¿Cancelar esta cita?', 'Cancelar cita', filasDeCitaEnPantalla(idCita), 'Volver');
    if (!confirmada) return;
    if (cancelacionesEnCursoC4.has(idCita)) return;
    cancelacionesEnCursoC4.add(idCita);
    if (!cancelacionesC4.has(idCita)) cancelacionesC4.set(idCita, zookiCrearComandoAgenda());
    try {
        const form = new FormData();
        form.append('id_cita', idCita);
        form.append('clave_operacion', cancelacionesC4.get(idCita).clave(form));
        const res = await (await fetch('index.php?action=cancelar_cita_ajax', {
            method: 'POST',
            body: form,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })).json();

        if (res.success) {
            zookiToast(res.message || 'La cita quedó cancelada.', 'success');
            refrescarPortal();
        } else {
            zookiToast(res.message || 'No se pudo cancelar la cita.', 'error');
        }
    } catch (e) {
        console.error(e);
        zookiToast('No pudimos conectarnos con el servidor.', 'error');
    } finally {
        cancelacionesEnCursoC4.delete(idCita);
    }
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
        const zona = form.querySelector('.foto-campo');
        for (const nombre of ['dragenter', 'dragover']) zona.addEventListener(nombre, evento => {
            evento.preventDefault();
            zona.dataset.arrastrando = 'true';
        });
        zona.addEventListener('dragleave', evento => {
            if (!zona.contains(evento.relatedTarget)) delete zona.dataset.arrastrando;
        });
        zona.addEventListener('drop', evento => {
            evento.preventDefault();
            delete zona.dataset.arrastrando;
            const archivos = evento.dataTransfer?.files;
            if (!archivos?.length) return;
            const entrada = campo(form, 'foto');
            if (archivos.length > 1) {
                prepararFoto(form, form.dataset.fotoActual);
                const ayuda = form.querySelector('.foto-campo__ayuda');
                ayuda.textContent = 'Elige una sola foto.';
                ayuda.classList.add('is-error');
                return;
            }
            entrada.files = archivos;
            entrada.dispatchEvent(new Event('change', { bubbles:true }));
        });
        // Soltar un archivo fuera de la zona no navega fuera del formulario abierto.
        for (const nombre of ['dragover', 'drop']) document.addEventListener(nombre, evento => {
            if (form.getClientRects().length) evento.preventDefault();
        });
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
                    : !archivo.size || archivo.size > MAX_FOTO ? 'Esa foto pesa más de 5 MB o está vacía. Elige una más liviana.'
                    : null;
                if (problema) {
                    prepararFoto(form, form.dataset.fotoActual);
                    ayuda.textContent = problema;
                    ayuda.classList.add('is-error');
                    return;
                }
                if (window.ZookiValidacionMascota) window.ZookiValidacionMascota.marcar(input, '');
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
        if (window.ZookiValidacionMascota) window.ZookiValidacionMascota.limpiar(form);
        const nacimiento = campo(form, 'fecha_nacimiento');
        if (nacimiento && nacimiento._flatpickr) nacimiento._flatpickr.clear();
    };

    // ── Abrir: registrar (delegado: el botón está en Inicio, que se repinta) ──
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-agregar-mascota]')) return;
        limpiarFormulario(addForm);
        cargarRazas('', campo(addForm, 'raza'));
        mostrarRazaOtra(addForm, false);
        prepararFoto(addForm, null);
        openModal('portalAddPetModal');
    });

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

    // ── Enviar: primero la validación en el navegador (avisos de una línea), luego el servidor ──
    const enviarMascota = (form, { accion, modalId, textoBoton, textoEnviando, exito }) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            form.classList.add('intentado');
            if (!window.ZookiValidacionMascota.validar(form)) return;

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
                    // M5 C2b: el aviso sale ya y la pantalla (y la ficha abierta) se ponen al día sin recargar.
                    zookiToast(exito[1] || exito[0], 'success');
                    refrescarPortal();
                } else {
                    // El error del servidor va bajo su campo y en el resumen, no en una ventana (M0.1).
                    window.ZookiValidacionMascota.mostrarServidor(form, res);
                }
            } catch (err) {
                console.error(err);
                zookiToast('No se pudo conectar con el servidor.', 'error');
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
            zookiToast(res.message || 'No se pudo cargar la información de la cita.', 'error');
            return;
        }

        const cita = res.cita;
        const consulta = res.consulta;
        const tratamientos = res.tratamientos;

        // Rellenar información general
        document.getElementById('detCitaTipo').textContent = cita.nombre_tipo.toUpperCase();
        document.getElementById('detCitaMascota').textContent = cita.nombre_mascota;
        document.getElementById('detCitaVet').textContent = `Dr(a). ${cita.veterinario} · ${cita.clinica}`;
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
            document.getElementById('detClinicaPeso').textContent = textoPesoConsulta(consulta.peso);
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
        zookiToast('No se pudo conectar con el servidor para traer los detalles.', 'error');
    }
}

/* ── HU-5.12 y HU-5.13: mis clínicas ───────────────────────────────────── */
async function postPortal(accion, datos) {
    const cuerpo = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => cuerpo.append(clave, valor));
    const res = await fetch(`index.php?action=${accion}`, {
        method: 'POST',
        body: cuerpo,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return res.json();
}

function avisoPortal(icono, titulo, texto) {
    return zookiToast(texto || titulo, icono);
}

document.addEventListener('DOMContentLoaded', () => {
    const seccion = document.getElementById('misClinicas');
    if (!seccion) return;

    // RE-5.12.1/2: el interruptor aplica de inmediato; si el servidor rechaza, vuelve a su estado.
    seccion.addEventListener('change', async (e) => {
        const interruptor = e.target.closest('[data-autorizar-clinica]');
        if (!interruptor) return;
        interruptor.disabled = true;
        try {
            const res = await postPortal('portal_autorizar_historia_ajax', {
                id_clinica: interruptor.dataset.autorizarClinica,
                autoriza: interruptor.checked ? '1' : '0'
            });
            if (!res.success) {
                interruptor.checked = !interruptor.checked;
                avisoPortal('error', 'No se pudo cambiar', res.message || 'Intenta de nuevo.');
                return;
            }
            avisoPortal('success', 'Listo', res.message);
        } catch (err) {
            console.error(err);
            interruptor.checked = !interruptor.checked;
            avisoPortal('error', 'Error de red', 'No se pudo conectar con el servidor.');
        } finally {
            interruptor.disabled = false;
        }
    });

    seccion.addEventListener('click', async (e) => {
        const vincular = e.target.closest('[data-vincular-clinica]');
        const desvincular = e.target.closest('[data-desvincular-clinica]');
        if (!vincular && !desvincular) return;

        const boton = vincular || desvincular;
        // RE-5.13.1: vínculo reversible con un clic; el servidor valida RN-115.
        const aviso = boton.closest('.portal-clinica')?.querySelector('[data-aviso-clinica]');
        if (aviso) aviso.textContent = '';

        boton.disabled = true;
        try {
            const res = vincular
                ? await postPortal('portal_vincular_clinica_ajax', { id_clinica: vincular.dataset.vincularClinica })
                : await postPortal('portal_desvincular_clinica_ajax', { id_clinica: desvincular.dataset.desvincularClinica });
            if (!res.success) {
                // RE-5.13.3: el rechazo (citas sin resolver) queda en la misma tarjeta, en una línea.
                avisoEnTarjeta(boton, res.message || 'No se pudo completar. Intenta de nuevo.');
                boton.disabled = false;
                return;
            }
            avisoPortal('success', 'Listo', res.message || 'Listo');
            refrescarPortal();
        } catch (err) {
            console.error(err);
            boton.disabled = false;
            avisoEnTarjeta(boton, 'No se pudo conectar con el servidor.');
        }
    });

    // «Otras clínicas en Zooki»: el buscador filtra mientras se escribe.
    seccion.addEventListener('input', (e) => {
        if (!e.target.matches('[data-buscar-clinica]')) return;
        filtrarClinicas(seccion, e.target.value);
    });
});

/** Un aviso de una línea en la tarjeta de la clínica; el texto completo, al pasar el cursor. */
function avisoEnTarjeta(boton, mensaje) {
    const aviso = boton.closest('.portal-clinica')?.querySelector('[data-aviso-clinica]');
    if (!aviso) {
        avisoPortal('error', 'No se pudo completar', mensaje);
        return;
    }
    aviso.textContent = mensaje;
    aviso.title = mensaje;
    aviso.classList.add('cuenta-validacion--error');
}

/** Sin tildes ni mayúsculas, para buscar «clinica» y encontrar «Clínica». */
function sinTildes(texto) {
    return String(texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function filtrarClinicas(seccion, consulta) {
    const buscado = sinTildes(consulta);
    let visibles = 0;
    seccion.querySelectorAll('[data-otras-clinicas] [data-buscar]').forEach(tarjeta => {
        const coincide = buscado === '' || sinTildes(tarjeta.dataset.buscar).includes(buscado);
        tarjeta.hidden = !coincide;
        if (coincide) visibles++;
    });
    const vacio = seccion.querySelector('[data-sin-clinicas]');
    if (vacio) vacio.hidden = visibles > 0;
}
