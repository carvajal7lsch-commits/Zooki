/* 
    Zooki Medical Module - Comprehensive External JS 
    Logic for Pets, Owners, and Consultations.
*/

// --- GLOBAL EVENT LISTENERS (ZOOKI_REGLAS COMPLIANCE) ---
document.addEventListener('DOMContentLoaded', () => {
    // 1. Modales - Cierre con botón X y Cancelar
    document.querySelectorAll('.close-modal-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            closeModal(e.currentTarget.dataset.modal);
        });
    });

    // 2. Modales - Cierre al hacer click en el fondo (backdrop)
    document.querySelectorAll('.close-modal-backdrop').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal.dataset.modal);
            }
        });
    });

    // 3. Tabs en modales. Solo los botones con data-target-tab: los del modal
    // de consulta usan onclick propio, y este listener les llegaba con el
    // destino undefined, apagaba todas las pestañas y lanzaba un error, así
    // que Signos, Plan y Archivos quedaban en blanco.
    document.querySelectorAll('.modal-tab-btn[data-target-tab]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if(typeof switchModalTab === 'function') {
                switchModalTab(e, e.currentTarget.dataset.targetTab);
            }
        });
    });

    // 4. Toggle Switches (Estado Activo/Inactivo)
    document.querySelectorAll('.status-toggle-input').forEach(toggle => {
        toggle.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            const targetInput = document.getElementById(e.target.dataset.target);
            const textTarget = document.getElementById(e.target.dataset.textTarget);
            
            if (targetInput) targetInput.value = isChecked ? 1 : 0;
            if (textTarget) {
                textTarget.textContent = isChecked ? e.target.dataset.textActive : e.target.dataset.textInactive;
                textTarget.className = isChecked ? 'text-success' : 'text-danger';
            }
        });
    });

    // 5. Image Upload - Previews y Clear buttons
    document.querySelectorAll('.image-upload-input').forEach(input => {
        input.addEventListener('change', (e) => {
            if(typeof previewImg === 'function') {
                previewImg(e.target, e.target.dataset.preview, e.target.dataset.clearBtn);
            }
        });
    });
    document.querySelectorAll('.clear-preview-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if(typeof clearPreview === 'function') {
                clearPreview(e, e.currentTarget.dataset.input, e.currentTarget.dataset.preview, e.currentTarget.id);
            }
        });
    });

    // 6. Validaciones en tiempo real
    const validations = [
        { selector: '.validate-select', func: 'validarSelect' },
        { selector: '.validate-doc', func: 'validarDocumento', event: 'input' },
        { selector: '.validate-name', func: 'validarNombre', event: 'input' },
        { selector: '.validate-email', func: 'validarEmail', event: 'input' }
    ];
    validations.forEach(val => {
        document.querySelectorAll(val.selector).forEach(el => {
            const ev = val.event || 'change';
            el.addEventListener(ev, (e) => {
                if(typeof window[val.func] === 'function') {
                    window[val.func](e.target);
                }
            });
        });
    });

    // 7. Eventos específicos de edición (Razas, etc)
    const loadBreedsSelects = document.querySelectorAll('.load-breeds-select');
    loadBreedsSelects.forEach(select => {
        select.addEventListener('change', (e) => {
            if(typeof loadBreeds === 'function') {
                loadBreeds(e.target.value, e.target.dataset.target);
            }
        });
    });

    const checkOtherBreedSelects = document.querySelectorAll('.check-other-breed');
    checkOtherBreedSelects.forEach(select => {
        select.addEventListener('change', (e) => {
            if(typeof checkOtherBreed === 'function') {
                checkOtherBreed(e.target, e.target.dataset.target);
            }
        });
    });

    // 8. Búsqueda de propietario
    const ownerSearchInputs = document.querySelectorAll('.owner-search-input');
    ownerSearchInputs.forEach(input => {
        input.addEventListener('keyup', (e) => {
            if(typeof searchOwnersForEdit === 'function') {
                searchOwnersForEdit(e.target.value);
            }
        });
    });

    const closeOwnerSelections = document.querySelectorAll('.close-selected-owner');
    closeOwnerSelections.forEach(btn => {
        btn.addEventListener('click', () => {
            if(typeof clearEditOwnerSelection === 'function') {
                clearEditOwnerSelection();
            }
        });
    });

    // 9. Forms de Edición (Submits)
    const formEditMascota = document.getElementById('formEditMascota');
    if(formEditMascota) {
        formEditMascota.addEventListener('submit', (e) => {
            if(typeof updatePet === 'function') {
                updatePet(e);
            }
        });
    }

    const formEditPropietario = document.getElementById('formEditPropietario');
    if(formEditPropietario) {
        formEditPropietario.addEventListener('submit', (e) => {
            if(typeof updateOwner === 'function') {
                updateOwner(e);
            }
        });
    }
});

// M2 C5a (hallazgo de A.1): una consulta guardada pone al día, sin recargar,
// los pacientes y el expediente (número de historia, ficha) y el historial abierto.
document.addEventListener('zooki:consulta-registrada', (evento) => {
    const idMascota = String(evento.detail.idMascota);
    if (document.getElementById('dossierView')) ponerAlDiaPacientes(idMascota);
    if (historialAbierto && String(historialAbierto.id) === idMascota && historialEstaAbierto()) viewMedicalHistory(historialAbierto.id, historialAbierto.nombre);
});

/** Pacientes cierra su cajón también con closeDrawer: se mira el cajón, no solo la variable. */
function historialEstaAbierto() {
    const enPacientes = document.getElementById('drawerHistorial');
    if (enPacientes) return enPacientes.classList.contains('is-open');
    const enConsultas = document.getElementById('historialDrawer');
    return Boolean(enConsultas) && enConsultas.style.transform === 'translateX(0)';
}

// --- UTILITIES ---
function openModal(id) {
    const m = document.getElementById(id);
    if (m) {
        m.classList.remove('d-none');
        m.style.display = 'flex';
    }
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) {
        m.classList.add('d-none');
        m.style.display = 'none';
    }
}

function abrirModalRegistro(tipo) {
    const m = document.getElementById('modalNuevoRegistro');
    if (m) {
        openModal('modalNuevoRegistro');
        const tabProp = document.querySelector('#modalNuevoRegistro .modal-tab-btn[data-target-tab="tabNuevoPropietario"]');
        const tabMasc = document.querySelector('#modalNuevoRegistro .modal-tab-btn[data-target-tab="tabNuevaMascota"]');
        if (tipo === 'propietario' && tabProp) {
            tabProp.click();
        } else if (tipo === 'mascota' && tabMasc) {
            tabMasc.click();
        }
    } else {
        // Fallback para vistas antiguas que no tienen modalNuevoRegistro
        if (tipo === 'propietario') openModal('modalPropietario');
        else openModal('modalMascota');
    }
}

function previewImg(input, previewId, btnId = null) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => { 
            const img = document.getElementById(previewId);
            img.src = e.target.result;
            img.style.display = 'block';
            if (btnId) {
                const btn = document.getElementById(btnId);
                if (btn) btn.style.display = 'flex';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearPreview(e, inputId, previewId, btnId) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    document.getElementById(inputId).value = '';
    const img = document.getElementById(previewId);
    img.src = '';
    img.style.display = 'none';
    const btn = document.getElementById(btnId);
    if (btn) btn.style.display = 'none';
}

function viewImage(src) {
    const lb = document.getElementById('lightboxVisor');
    if (lb) { lb.querySelector('img').src = src; lb.style.display = 'flex'; }
}

function closeLightbox() {
    const lb = document.getElementById('lightboxVisor');
    if (lb) lb.style.display = 'none';
}

function switchModalTab(event, tabId) {
    const modal = event.target.closest('.modal, .users-modal, .modal-content');
    if (!modal) return;
    modal.querySelectorAll('.modal-tab-btn').forEach(b => b.classList.remove('active'));
    modal.querySelectorAll('.modal-tab-content').forEach(c => c.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById(tabId).classList.add('active');
    if (modal.classList.contains('consulta-modal') && window.matchMedia && window.matchMedia('(max-width: 767px)').matches) {
        event.currentTarget.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }
}

// recargar: false al volver a una pestaña que ya está cargada (al cerrar el
// expediente), para no repintar el directorio completo.
function switchModule(module, { recargar = true } = {}) {
    const globalHeader = document.querySelector('.section-header');
    if (globalHeader) globalHeader.style.display = 'flex';

    const dView = document.getElementById('dossierView');
    if (dView) { dView.style.display = 'none'; dView.classList.add('d-none'); }

    const directory = document.getElementById('ownersDirectory');
    if (directory) directory.style.display = 'block';

    const toggleHeader = document.querySelector('.head-view-toggle');
    if (toggleHeader) toggleHeader.style.display = '';

    const petsSearch = document.getElementById('searchRowPets');
    const ownersSearch = document.getElementById('searchRowOwners');
    if (petsSearch && ownersSearch) {
        petsSearch.classList.toggle('is-active', module === 'pets');
        ownersSearch.classList.toggle('is-active', module === 'owners');
        petsSearch.style.display = module === 'pets' ? '' : 'none';
        ownersSearch.style.display = module === 'owners' ? '' : 'none';
    }

    const petViewToggle = document.getElementById('petViewToggle');
    const ownerViewToggle = document.getElementById('ownerViewToggle');
    if (petViewToggle && ownerViewToggle) {
        petViewToggle.style.display = module === 'pets' ? 'flex' : 'none';
        ownerViewToggle.style.display = module === 'owners' ? 'flex' : 'none';
    }

    document.getElementById('tabPets').classList.toggle('active', module === 'pets');
    document.getElementById('tabOwners').classList.toggle('active', module === 'owners');

    document.getElementById('modulePets').classList.toggle('active', module === 'pets');
    document.getElementById('moduleOwners').classList.toggle('active', module === 'owners');

    if (module === 'owners' && recargar) {
        loadOwners();
    }
}


// --- PROPIETARIOS ---
/** Un nodo con su clase y su texto (sin armar HTML: los datos van con textContent). */
function nodoDueno(etiqueta, clase, texto) {
    const nodo = document.createElement(etiqueta);
    if (clase) nodo.className = clase;
    if (texto !== undefined) nodo.textContent = texto;
    return nodo;
}

/** Dos letras para el avatar, como las tarjetas de Usuarios. */
function inicialesDueno(nombre) {
    const partes = String(nombre || '').trim().split(/\s+/).filter(Boolean);
    return partes.slice(0, 2).map(parte => parte.charAt(0).toUpperCase()).join('') || '?';
}

/** Una etiqueta mini con su icono de Bootstrap Icons. */
function etiquetaDueno(texto, icono, clase = 'tag-mini') {
    const etiqueta = nodoDueno('span', clase);
    if (icono) {
        const i = nodoDueno('i', 'bi ' + icono);
        i.setAttribute('aria-hidden', 'true');
        etiqueta.append(i, ' ');
    }
    etiqueta.append(texto);
    return etiqueta;
}

/**
 * M5 C4b (captura 06): las acciones de Usuarios › Clientes, como botones de
 * icono: editar (si sigue vinculado, RN-115), reenviar el enlace (si aún no
 * activa su cuenta, M5 C3) y ver su ficha con las mascotas.
 */
function accionesDueno(o) {
    const activo = Number(o.estado) === 1;
    const acciones = [];
    const boton = (icono, titulo, etiqueta) => {
        const b = nodoDueno('button', 'action-btn-mini');
        b.type = 'button';
        b.title = titulo;
        b.setAttribute('aria-label', etiqueta);
        const i = nodoDueno('i', 'bi ' + icono);
        i.setAttribute('aria-hidden', 'true');
        b.append(i);
        return b;
    };
    if (activo) {
        const editar = boton('bi-pencil-fill', 'Editar', 'Editar a ' + o.nombre_completo);
        editar.addEventListener('click', () => editOwner(o.id_usuario));
        acciones.push(editar);
    }
    if (activo && Number(o.pendiente_activacion) === 1) {
        // propietario-mascota.js atiende data-reenviar-activacion.
        const reenviar = boton('bi-envelope-arrow-up-fill', 'Reenviar enlace de activación', 'Reenviar a ' + o.nombre_completo + ' el enlace para activar su cuenta');
        reenviar.dataset.reenviarActivacion = String(o.id_usuario);
        acciones.push(reenviar);
    }
    const ver = boton('bi-eye-fill', 'Ver detalle y mascotas', 'Ver a ' + o.nombre_completo + ' y sus mascotas');
    ver.addEventListener('click', () => openOwnerDossier(o.id_usuario));
    acciones.push(ver);
    return acciones;
}

/** Estado del cliente en palabras: desvinculado, aún sin activar o activo. */
function estadoDueno(o) {
    if (Number(o.estado) !== 1) return 'Desvinculado';
    return Number(o.pendiente_activacion) === 1 ? 'Aún no activa su cuenta' : 'Activo';
}

/** M5 C4b (captura 06): la tarjeta mini de Usuarios › Clientes. */
function tarjetaDueno(o) {
    const activo = Number(o.estado) === 1;
    const tarjeta = nodoDueno('article', 'client-card person-card');
    tarjeta.dataset.estado = String(o.estado);
    const cabecera = nodoDueno('div', 'card-header-mini');
    const avatar = nodoDueno('span', 'avatar-mini avatar-iniciales avatar-iniciales--cliente', inicialesDueno(o.nombre_completo));
    avatar.setAttribute('aria-hidden', 'true');
    cabecera.append(avatar);
    // La marca va en la cabecera, junto al avatar: así el contenido de todas las tarjetas queda a la misma altura.
    if (activo && Number(o.pendiente_activacion) === 1) cabecera.append(etiquetaDueno('Aún no activa su cuenta', 'bi-hourglass-split', 'tag-mini tag-mini--aviso'));
    // RN-115: desvinculado, solo el nombre y la marca (el servidor no manda el contacto).
    if (!activo) cabecera.append(etiquetaDueno('Desvinculado', 'bi-link-45deg'));
    const cuerpo = nodoDueno('div', 'card-body-mini');
    cuerpo.append(nodoDueno('h3', 'card-title-mini', o.nombre_completo));
    const etiquetas = nodoDueno('div', 'card-tags-mini');
    etiquetas.append(etiquetaDueno(`${Number(o.total_mascotas)} mascota(s)`, 'bi-heart'));
    if (o.telefono) etiquetas.append(etiquetaDueno(o.telefono, 'bi-phone'));
    cuerpo.append(etiquetas);
    const datosContacto = [];
    if (o.documento) datosContacto.push(etiquetaDueno(o.documento, 'bi-person-badge', 'contact-text-mini'));
    if (o.email) datosContacto.push(etiquetaDueno(o.email, 'bi-envelope', 'contact-text-mini'));
    if (datosContacto.length) {
        const contacto = nodoDueno('div', 'card-contact-mini');
        contacto.append(...datosContacto);
        cuerpo.append(contacto);
    }
    const pie = nodoDueno('div', 'card-footer-mini');
    pie.append(...accionesDueno(o));
    tarjeta.append(cabecera, cuerpo, pie);
    abrirDetalleConLaTarjeta(tarjeta, o);
    return tarjeta;
}

/**
 * M5 C4b (usuario): tocar la tarjeta abre el detalle del propietario con sus
 * mascotas, como el ojito. Sus botones (editar, reenviar) siguen haciendo lo
 * suyo, y con el teclado se abre con Enter o Espacio.
 */
function abrirDetalleConLaTarjeta(tarjeta, o) {
    tarjeta.setAttribute('role', 'button');
    tarjeta.setAttribute('tabindex', '0');
    tarjeta.setAttribute('aria-label', 'Ver a ' + o.nombre_completo + ' y sus mascotas');
    tarjeta.classList.add('client-card--abre');
    tarjeta.addEventListener('click', (evento) => {
        if (evento.target.closest && evento.target.closest('button')) return;
        openOwnerDossier(o.id_usuario);
    });
    tarjeta.addEventListener('keydown', (evento) => {
        if (evento.target !== tarjeta || (evento.key !== 'Enter' && evento.key !== ' ')) return;
        evento.preventDefault();
        openOwnerDossier(o.id_usuario);
    });
}

/** La misma información en una fila de la tabla. */
function filaDueno(o) {
    const fila = document.createElement('tr');
    fila.dataset.estado = String(o.estado);
    [o.documento, o.nombre_completo, o.telefono, o.email, o.total_mascotas, estadoDueno(o)].forEach(valor => {
        fila.append(nodoDueno('td', '', valor ?? ''));
    });
    const celda = nodoDueno('td', 'acciones-mini');
    celda.append(...accionesDueno(o));
    fila.append(celda);
    return fila;
}

async function loadOwners() {
    const tbody = document.getElementById('ownersTableBody');
    const grid = document.getElementById('ownersGrid');
    if (!tbody && !grid) return;
    try {
        const owners = await pedirJson('index.php?action=listar_propietarios_ajax');
        if (tbody) tbody.replaceChildren(...owners.map(filaDueno));
        if (grid) grid.replaceChildren(...owners.map(tarjetaDueno));
        filterOwners();
    } catch (error) { zookiAviso('No se pudieron cargar los propietarios de esta clínica.'); }
}

function togglePetStatus(id, newStatus) {
    const actionWord = newStatus === 1 ? 'activada' : 'desactivada';
    
    fetch('index.php?action=cambiar_estado_mascota_ajax', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id_mascota=${id}&estado=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            
            Toast.fire({
                icon: 'success',
                title: `Mascota ${actionWord}`
            });
            
            const gridCard = document.querySelector(`.pet-card[data-id="${id}"]`);
            if (gridCard) gridCard.setAttribute('data-estado', newStatus);
            const tableRow = document.querySelector(`#petsTable tr[data-id="${id}"]`);
            if (tableRow) tableRow.setAttribute('data-estado', newStatus);
        } else {
            const toggle = document.querySelector(`.pet-card[data-id="${id}"] input[type="checkbox"]`);
            if (toggle) {
                toggle.checked = !toggle.checked;
            }
            Swal.fire('Error', data.message || 'No se pudo actualizar el estado', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const toggle = document.querySelector(`.pet-card[data-id="${id}"] input[type="checkbox"]`);
        if (toggle) {
            toggle.checked = !toggle.checked;
        }
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    });
}

let currentDossierId = '';
// Pestaña desde la que se abrió el expediente, para volver a ella al cerrarlo.
let dossierOrigen = 'owners';
// Cada apertura invalida la anterior: con dos clics seguidos solo se pinta la
// última ficha pedida, no la que responda más tarde.
let dossierPeticion = 0;

const FOTO_MASCOTA_DEFECTO = 'img/default-pet.svg';

function fotoMascotaUrl(urlFoto) {
    return urlFoto ? 'uploads/mascotas/' + encodeURIComponent(urlFoto) : FOTO_MASCOTA_DEFECTO;
}

// Si el archivo no está en el servidor se muestra el marcador local. Se anula
// onerror antes de cambiar src para no entrar en bucle.
function usarFotoPorDefecto(img) {
    img.onerror = null;
    img.src = FOTO_MASCOTA_DEFECTO;
}

/** HU-15: la raza, más la que escribió el propietario si no estaba en la lista. */
function razaConIndicada(nombre, indicada, porDefecto = '---') {
    const base = nombre || porDefecto;
    return indicada ? `${indicada} (por confirmar)` : base;
}

function escaparTexto(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

async function pedirJson(url) {
    const res = await fetch(url);
    return res.json();
}

/**
 * Abre directamente la ficha de una mascota (pestaña Mascotas).
 *
 * Antes llamaba a switchModule('owners'), que recargaba y repintaba todo el
 * directorio; luego mostraba el listado del propietario y 100 ms después la
 * ficha: tres pantallas seguidas. Ahora los datos se piden a la vez y la
 * vista cambia una sola vez, con la ficha ya pintada.
 */
// Enlace directo a la ficha desde los paneles de inicio:
// index.php?action=vet_pacientes&propietario=DOC&mascota=ID
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const doc = params.get('propietario');
    if (!doc || !document.getElementById('dossierView')) return;
    viewPetInDossier(doc, params.get('mascota'));
});

function viewPetInDossier(doc, petId) {
    return openOwnerDossier(doc, { petId });
}

async function openOwnerDossier(doc, { petId = null } = {}) {
    const peticion = ++dossierPeticion;

    // Si el expediente ya está abierto (se está refrescando) se conserva el origen.
    const dView = document.getElementById('dossierView');
    if (!dView || dView.classList.contains('d-none')) {
        dossierOrigen = document.getElementById('tabPets')?.classList.contains('active') ? 'pets' : 'owners';
    }
    document.body.style.cursor = 'progress';

    try {
        const [owner, pets, historial] = await Promise.all([
            pedirJson(`index.php?action=get_propietario_ajax&id_usuario=${encodeURIComponent(doc)}`),
            pedirJson(`index.php?action=listar_mascotas_propietario_ajax&id_usuario=${encodeURIComponent(doc)}`),
            petId ? pedirJson(`index.php?action=listar_historial_ajax&id_mascota=${encodeURIComponent(petId)}`) : null,
        ]);
        if (peticion !== dossierPeticion) return;

        // M2-09: el endpoint del historial responde success:false si algo falla.
        if (petId && (!historial || historial.success === false)) {
            zookiAviso((historial && historial.message) || 'No se pudo abrir la ficha de la mascota.');
            return;
        }

        currentDossierId = doc;
        renderOwnerDossier(owner, Array.isArray(pets) ? pets : []);
        if (petId) renderPetDashboard(historial, petId);
        mostrarDossier(petId ? 'ficha' : 'listado');
    } catch (e) {
        console.error('Error al abrir dossier:', e);
        zookiAviso('No se pudo abrir el expediente.');
    } finally {
        if (peticion === dossierPeticion) document.body.style.cursor = '';
    }
}

/**
 * Carrusel de mascotas del expediente. Con muchas mascotas las tarjetas se
 * salían del contenedor y las flechas del pie no hacían nada. Ahora las
 * flechas van sobre las tarjetas, cada clic avanza una página y cada flecha
 * solo aparece cuando hay más mascotas hacia su lado.
 */
function moverCarruselMascotas(direccion) {
    const pista = document.getElementById('dossierPetsScroll');
    if (pista) pista.scrollBy({ left: direccion * pista.clientWidth, behavior: 'smooth' });
}

function actualizarCarruselMascotas() {
    const pista = document.getElementById('dossierPetsScroll');
    const prev = document.getElementById('dossierPetsPrev');
    const next = document.getElementById('dossierPetsNext');
    if (!pista || !prev || !next) return;

    const maximo = pista.scrollWidth - pista.clientWidth;
    prev.hidden = pista.scrollLeft <= 2;
    next.hidden = pista.scrollLeft >= maximo - 2;
}

document.addEventListener('DOMContentLoaded', () => {
    const pista = document.getElementById('dossierPetsScroll');
    if (!pista) return;
    pista.addEventListener('scroll', actualizarCarruselMascotas, { passive: true });
    window.addEventListener('resize', actualizarCarruselMascotas);
    // Cubre el cambio de propietario (tarjetas nuevas: vuelve al inicio) y el
    // filtro por nombre o especie (tarjetas que se ocultan o se muestran).
    new MutationObserver(cambios => {
        if (cambios.some(c => c.type === 'childList' && c.target === pista)) pista.scrollLeft = 0;
        requestAnimationFrame(actualizarCarruselMascotas);
    }).observe(pista, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
});

function renderOwnerDossier(owner, pets) {
    const ponerTexto = (id, valor) => {
        const el = document.getElementById(id);
        if (el) el.innerText = valor;
    };
    const ponerEstado = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.className = `status-badge ${owner.estado == 1 ? 'active' : 'inactive'}`;
        el.innerText = owner.estado == 1 ? 'Activo' : 'Inactivo';
    };

    // Tarjeta del propietario y su versión compacta del dashboard (sufijo Dash).
    ['', 'Dash'].forEach(sufijo => {
        ponerTexto('dossierOwnerName' + sufijo, owner.nombre_completo);
        ponerTexto('dossierOwnerDoc' + sufijo, owner.documento);
        ponerTexto('dossierOwnerPhone' + sufijo, owner.telefono || 'Sin teléfono');
        ponerTexto('dossierOwnerEmail' + sufijo, owner.email || 'Sin email');
        ponerEstado('dossierOwnerEstado' + sufijo);
    });

    const petsScroll = document.getElementById('dossierPetsScroll');
    const petsCount = document.getElementById('dossierPetsCount');
    if (petsCount) petsCount.innerText = `Mostrando ${pets.length} pacientes asociados`;
    petsScroll.innerHTML = '';

    if (pets.length === 0) {
        petsScroll.innerHTML = '<div class="dossier-sin-mascotas"><i class="fas fa-paw"></i>Este cliente aún no tiene mascotas registradas.</div>';
        return;
    }

    const SEXOS = { M: 'MACHO', Macho: 'MACHO', H: 'HEMBRA', Hembra: 'HEMBRA' };

    pets.forEach(m => {
        const card = document.createElement('div');
        card.className = 'pet-dossier-card';
        card.style.cursor = 'pointer';
        card.addEventListener('click', () => loadPetDashboard(m.id_mascota));

        // Solo se muestra lo que se sabe. Antes la etiqueta "VACUNA PENDIENTE"
        // salía de Math.random(): era un dato clínico inventado.
        const conHistoria = Boolean(m.numero_historia_clinica);

        card.innerHTML = `
            <div class="pet-dossier-card-top">
                <img src="${escaparTexto(fotoMascotaUrl(m.url_foto))}" class="pet-dossier-card-photo" alt="">
                <div class="pet-dossier-card-info">
                    <h4 class="pet-dossier-card-name">${escaparTexto(m.nombre)}</h4>
                    <p class="pet-dossier-card-species">${escaparTexto(m.especie)} • ${escaparTexto(razaConIndicada(m.raza, m.raza_indicada, 'Mestizo'))}</p>
                </div>
            </div>
            <div class="pet-dossier-card-grid">
                <div class="data-group">
                    <label>GÉNERO</label>
                    <span>${SEXOS[m.sexo] || 'DESCONOCIDO'}</span>
                </div>
                <div class="data-group right-align">
                    <label>HISTORIA</label>
                    <span class="pet-dossier-card-hc pet-dossier-card-hc--numero">${escaparTexto(m.numero_historia_clinica || 'SIN REGISTRO')}</span>
                </div>
            </div>
            <div class="pet-dossier-status">
                <span class="status-pill ${conHistoria ? 'al-dia' : 'desconocido'}">${conHistoria ? 'CON HISTORIA' : 'SIN HISTORIA'}</span>
            </div>
            <div class="pet-dossier-card-actions">
                <button type="button" class="btn-ficha">Ver Ficha</button>
            </div>
        `;
        const foto = card.querySelector('.pet-dossier-card-photo');
        foto.addEventListener('error', () => usarFotoPorDefecto(foto));
        petsScroll.appendChild(card);
    });
}

/**
 * Muestra el expediente ya pintado. Se activa el módulo de propietarios sin
 * pasar por switchModule, que recargaría el directorio y lo haría parpadear;
 * la pestaña resaltada sigue siendo la de origen.
 */
function mostrarDossier(seccion) {
    document.getElementById('modulePets').classList.remove('active');
    document.getElementById('moduleOwners').classList.add('active');

    ['searchRowPets', 'searchRowOwners'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    document.getElementById('ownersDirectory').style.display = 'none';
    const toggleHeader = document.querySelector('.head-view-toggle');
    if (toggleHeader) toggleHeader.style.display = 'none';

    const searchInput = document.getElementById('dossierPetSearch');
    if (searchInput) searchInput.value = '';
    const speciesFilter = document.getElementById('dossierPetSpeciesFilter');
    if (speciesFilter) speciesFilter.value = '';

    mostrarSeccionDossier(seccion);

    const dView = document.getElementById('dossierView');
    if (dView) { dView.style.display = 'block'; dView.classList.remove('d-none'); }

    window.scrollTo({ top: 0 });
}

// 'listado' = pacientes del propietario, 'ficha' = ficha de una mascota.
function mostrarSeccionDossier(seccion) {
    const listSection = document.getElementById('dossierListSection');
    if (listSection) listSection.style.display = seccion === 'listado' ? 'block' : 'none';
    const dashSection = document.getElementById('dossierPetDashboardSection');
    if (dashSection) dashSection.style.display = seccion === 'ficha' ? 'block' : 'none';
}

function hideDossier() {
    switchModule(dossierOrigen, { recargar: false });
}

function addNewPetFromDossier() {
    abrirModalRegistro('mascota');
    const documento = document.getElementById('dossierOwnerDoc').innerText;
    // M5 C3: la búsqueda del propietario vive en propietario-mascota.js.
    ZookiPropietarioMascota.buscarAhora(documento);
}

function editOwnerFromDossier() {
    editOwner(currentDossierId);
}

/**
 * HU-1.2 y RE-T.19.1 (M5 C3): la clínica registra al propietario y él activa
 * su cuenta desde el correo. Si el documento o el correo ya tienen cuenta, el
 * aviso y «Pedir confirmación» salen en el formulario (RN-109), sin ventana.
 */
async function saveOwner(e) {
    e.preventDefault();
    if (!e.target.reportValidity()) return;
    const fd = new FormData(e.target);
    if (itiNewOwnerPhone && itiNewOwnerPhone.isValidNumber()) fd.set('telefono', itiNewOwnerPhone.getNumber());
    const existente = document.getElementById('propietarioExistente');
    ZookiPropietarioMascota.limpiarExistente(existente);
    try {
        const res = await (await fetch('index.php?action=guardar_propietario_ajax', { method: 'POST', body: fd })).json();
        if (res.existe) {
            ZookiPropietarioMascota.mostrarExistente(existente, res);
            return;
        }
        if (!res.success) {
            ZookiPropietarioMascota.mostrarError(existente, res.message || 'No se pudo registrar al propietario.');
            return;
        }
        zookiToast(res.message);
        e.target.reset();
        await loadOwners();
        const pestana = document.querySelector('[data-target-tab="tabNuevaMascota"]');
        if (pestana) pestana.click();
        // Queda elegido para su primera mascota: es cliente de esta clínica y no tiene mascotas.
        ZookiPropietarioMascota.buscarAhora(fd.get('email'));
    } catch (error) {
        ZookiPropietarioMascota.mostrarError(existente, 'No se pudo registrar al propietario. Revisa la conexión.');
    }
}

async function editOwner(doc) {
    try {
        const o = await (await fetch(`index.php?action=get_propietario_ajax&id_usuario=${doc}`)).json();
        if (o && o.id_usuario) {
            if (document.getElementById('edit_owner_tipo_doc') && o.tipo_documento) {
                document.getElementById('edit_owner_tipo_doc').value = o.tipo_documento;
            }
            document.getElementById('edit_owner_doc').value = o.documento;
            document.getElementById('edit_owner_doc_orig').value = o.id_usuario;
            document.getElementById('edit_owner_nombre').value = o.nombre_completo;
            document.getElementById('edit_owner_tel').value = o.telefono;
            document.getElementById('edit_owner_email').value = o.email;
            document.getElementById('edit_owner_estado').value = o.estado;
            
            // Sincronizar el toggle visual
            const toggle = document.getElementById('toggle_edit_owner_estado');
            if (toggle) {
                toggle.checked = (o.estado == 1);
                // D1 (RN-109): la clínica desactiva el vínculo, pero no lo
                // reactiva; se reactiva cuando el propietario lo confirma por correo.
                toggle.disabled = o.estado != 1;
                toggle.dispatchEvent(new Event('change'));
                const texto = document.getElementById('text_edit_owner_estado');
                if (texto && o.estado != 1) texto.textContent = 'Inactivo: se reactiva cuando el propietario confirma por correo';
            }

            openModal('modalEditarPropietario');
        }
    } catch (e) { console.error(e); }
}

async function updateOwner(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const res = await (await fetch('index.php?action=actualizar_propietario_ajax', { method: 'POST', body: fd })).json();
        if (res.success) { 
            loadOwners(); 
            closeModal('modalEditarPropietario'); 
            Swal.fire({
                toast: true, position: 'top-end', showConfirmButton: false, timer: 2000,
                icon: 'success', title: 'Propietario actualizado con éxito'
            });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) { 
        console.error(e); 
        Swal.fire('Error', 'No se pudo actualizar', 'error');
    }
}

// --- MASCOTAS ---
function switchView(view) {
    const listBtn = document.getElementById('btnViewList');
    if (!listBtn) return;
    listBtn.classList.toggle('active', view === 'list');
    document.getElementById('btnViewGrid').classList.toggle('active', view === 'grid');
    document.getElementById('listView').classList.toggle('active', view === 'list');
    document.getElementById('gridView').classList.toggle('active', view === 'grid');
    localStorage.setItem('petViewPreference', view);
}

function switchOwnerView(view) {
    const listBtn = document.getElementById('btnOwnerViewList');
    if (!listBtn) return;
    listBtn.classList.toggle('active', view === 'list');
    document.getElementById('btnOwnerViewGrid').classList.toggle('active', view === 'grid');
    document.getElementById('ownerListView').classList.toggle('active', view === 'list');
    document.getElementById('ownerGridView').classList.toggle('active', view === 'grid');
    localStorage.setItem('ownerViewPreference', view);
}

async function initSpecies() {
    try {
        const especies = await (await fetch('index.php?action=listar_especies_ajax')).json();
        const sels = [document.getElementById('new_especie'), document.getElementById('edit_especie')];
        sels.forEach(s => {
            if (!s) return;
            s.innerHTML = '<option value="">Seleccione...</option>';
            especies.forEach(e => s.innerHTML += `<option value="${e.id_especie}">${escaparTexto(e.nombre_especie)}</option>`);
        });
    } catch (e) { console.error(e); }
}

async function loadBreeds(idEspecie, targetId, selectedRaza = null) {
    const sel = document.getElementById(targetId);
    if (!sel) return;
    if (!idEspecie) { sel.innerHTML = '<option value="">Seleccione especie...</option>'; return; }
    try {
        const razas = await (await fetch(`index.php?action=listar_razas_ajax&id_especie=${idEspecie}`)).json();
        sel.innerHTML = '<option value="">Seleccione raza...</option>';
        razas.forEach(r => {
            const s = (selectedRaza && (selectedRaza == r.id_raza || selectedRaza == r.nombre_raza)) ? 'selected' : '';
            sel.innerHTML += `<option value="${r.id_raza}" ${s}>${escaparTexto(r.nombre_raza)}</option>`;
        });
        sel.innerHTML += `<option value="Otra">Otra / No listada</option>`;
    } catch (e) { console.error(e); }
}

/**
 * M5 C3: los formularios de mascota del personal validan mientras se escribe
 * con las reglas de ValidadorMascota (validacion-mascota.js, como en C2b) y
 * el rechazo del servidor va bajo su campo, nunca en una ventana.
 */
function mascotaValida(form) {
    if (!window.ZookiValidacionMascota.validar(form)) return false;
    const propietario = form.querySelector('[name="id_propietario"]');
    if (propietario && propietario.required && !propietario.value) {
        const ayuda = document.getElementById('ownerSearchAyuda');
        if (ayuda) {
            ayuda.textContent = 'Busca y elige al propietario.';
            ayuda.classList.add('cuenta-validacion--error');
        }
        document.getElementById('ownerSearchInput')?.focus();
        return false;
    }
    return true;
}

async function enviarMascota(form, accion) {
    const respuesta = await fetch(`index.php?action=${accion}`, { method: 'POST', body: new FormData(form) });
    try {
        return await respuesta.json();
    } catch (error) {
        return { success: false, message: 'No se pudo guardar. Intenta nuevamente.' };
    }
}

/**
 * M5 C4b (revisión de C3): después de registrar o editar una mascota se ponen
 * al día, en su sitio, la lista de pacientes, la de propietarios (su conteo de
 * mascotas) y el expediente que esté abierto.
 */
async function ponerAlDiaPacientes(idMascota = null) {
    if (window.ZookiPropietarioMascota) await window.ZookiPropietarioMascota.refrescarPacientes();
    loadOwners();
    const expediente = document.getElementById('dossierView');
    if (!currentDossierId || !expediente || expediente.classList.contains('d-none')) return;
    const enFicha = document.getElementById('dossierPetDashboardSection')?.style.display === 'block';
    openOwnerDossier(currentDossierId, { petId: enFicha ? idMascota : null });
}

/** El formulario de registro queda listo para la siguiente mascota. */
function limpiarRegistroMascota(form) {
    form.reset();
    form.classList.remove('intentado');
    if (window.ZookiValidacionMascota && window.ZookiValidacionMascota.limpiar) window.ZookiValidacionMascota.limpiar(form);
    if (typeof clearPreview === 'function') clearPreview(null, 'newFoto', 'newPreview', 'btnClearNewImg');
    if (window.ZookiPropietarioMascota) window.ZookiPropietarioMascota.quitarPropietario(false);
}

async function savePet(e) {
    e.preventDefault();
    const form = e.target;
    if (!mascotaValida(form)) return;
    try {
        const res = await enviarMascota(form, 'guardar_mascota_ajax');
        if (res.success) {
            // M5 C4b (revisión de C3): sin recargar; el modal se cierra y las listas se ponen al día.
            closeModal('modalNuevoRegistro');
            limpiarRegistroMascota(form);
            zookiToast(res.message || 'Mascota registrada.');
            ponerAlDiaPacientes();
            return;
        }
        window.ZookiValidacionMascota.mostrarServidor(form, res);
    } catch (err) {
        console.error(err);
        window.ZookiValidacionMascota.mostrarServidor(form, { message: 'No se pudo procesar el registro. Revisa la conexión.' });
    }
}

async function editPet(id) {
    try {
        const m = await (await fetch(`index.php?action=get_mascota_ajax&id=${id}`)).json();
        if (m && m.id_mascota) {
            document.getElementById('edit_id_mascota').value = m.id_mascota;
            document.getElementById('edit_nombre').value = m.nombre;
            document.getElementById('edit_especie').value = m.id_especie;
            await loadBreeds(m.id_especie, 'edit_raza', m.id_raza);
            document.getElementById('edit_peso').value = m.peso;
            const nacimiento = document.getElementById('edit_fecha_nac');
            if (nacimiento._flatpickr) nacimiento._flatpickr.setDate(m.fecha_nacimiento, false);
            else nacimiento.value = m.fecha_nacimiento;
            document.getElementById('edit_sexo').value = m.sexo;
            document.getElementById('edit_estado').value = m.estado;
            ['edit_especie','edit_raza','edit_sexo','edit_fecha_nac','edit_nueva_raza'].forEach(id => {
                const campo = document.getElementById(id);
                if (campo) {
                    campo.disabled = !m.identidad_editable;
                    if (campo._flatpickr?.altInput) campo._flatpickr.altInput.disabled = !m.identidad_editable;
                }
            });
            const indicada = document.getElementById('edit_nueva_raza');
            if (indicada) indicada.value = m.raza_indicada || '';
            if (m.raza_indicada) document.getElementById('edit_raza').value = 'Otra';
            checkOtherBreed(document.getElementById('edit_raza'),'editOtherBreedGroup');
            const esterilizado = document.getElementById('edit_esterilizado');
            if (esterilizado) esterilizado.value = m.esterilizado ?? '';

            
            // Sincronizar el toggle visual de mascota
            const toggle = document.getElementById('toggle_edit_pet_estado');
            if (toggle) {
                toggle.checked = (m.estado == 1);
                toggle.dispatchEvent(new Event('change'));
            }

            selectEditOwner({ id_usuario: m.id_propietario, nombre_completo: m.propietario_nombre });
            
            const p = document.getElementById('editPreview');
            if (p) {
                p.onerror = () => usarFotoPorDefecto(p);
                p.src = fotoMascotaUrl(m.url_foto);
                p.classList.remove('d-none');
                const btn = document.getElementById('btnClearEditImg');
                if (btn) btn.classList.remove('d-none');
            }
            openModal('modalEditarMascota');
        }
    } catch (e) { console.error(e); }
}

async function updatePet(e) {
    e.preventDefault();
    const form = e.target;
    if (!mascotaValida(form)) return;
    try {
        const res = await enviarMascota(form, 'actualizar_mascota_ajax');
        if (res.success) {
            // M5 C4b (revisión de C3): sin recargar, como el registro.
            closeModal('modalEditarMascota');
            zookiToast(res.message || 'Mascota actualizada.');
            ponerAlDiaPacientes(form.elements.id_mascota ? form.elements.id_mascota.value : null);
            return;
        }
        window.ZookiValidacionMascota.mostrarServidor(form, res);
    } catch (err) {
        console.error(err);
        window.ZookiValidacionMascota.mostrarServidor(form, { message: 'No se pudo guardar. Revisa la conexión.' });
    }
}

// --- BUSCADOR DE PROPIETARIOS ---
let allOwnersList = [];

async function searchOwnersForEdit(term) {
    if (allOwnersList.length === 0) {
        const res = await fetch('index.php?action=listar_propietarios_ajax');
        allOwnersList = await res.json();
    }
    const suggestions = document.getElementById('editOwnerSuggestions');
    if (term.length < 2) { suggestions.style.display = 'none'; return; }
    const matches = allOwnersList.filter(o => o.nombre_completo.toLowerCase().includes(term.toLowerCase()) || o.documento.includes(term)).slice(0, 5);
    if (matches.length > 0) {
        suggestions.innerHTML = '';
        matches.forEach(m => {
            const div = document.createElement('div');
            div.className = 'suggestion-item';
            div.innerHTML = `<span class="name">${m.nombre_completo}</span><br><small>${m.documento}</small>`;
            div.onclick = () => selectEditOwner(m);
            suggestions.appendChild(div);
        });
        suggestions.style.display = 'block';
    } else suggestions.style.display = 'none';
}

function selectEditOwner(owner) {
    document.getElementById('edit_petOwnerDoc').value = owner.id_usuario;
    const input = document.getElementById('editOwnerSearchInput');
    if(input) {
        input.style.display = 'none';
        const wrapper = input.closest('.input-wrapper');
        if (wrapper) wrapper.style.display = 'none';
    }
    document.getElementById('editOwnerSuggestions').style.display = 'none';
    document.getElementById('selectedEditOwnerName').innerText = owner.nombre_completo;
    document.getElementById('selectedEditOwnerInfo').classList.remove('d-none');
    document.getElementById('selectedEditOwnerInfo').style.display = 'flex'; // Fix inline style if present
}

function clearEditOwnerSelection() {
    document.getElementById('edit_petOwnerDoc').value = '';
    const input = document.getElementById('editOwnerSearchInput');
    if(input) {
        input.value = ''; 
        input.style.display = 'block';
        const wrapper = input.closest('.input-wrapper');
        if (wrapper) wrapper.style.display = 'flex';
    }
    document.getElementById('selectedEditOwnerInfo').classList.add('d-none');
    document.getElementById('selectedEditOwnerInfo').style.display = '';
}

function filterTableBySpecies(species) {
    filterTable();
}

function filterTable() {
    const term = document.getElementById('tableSearch') ? document.getElementById('tableSearch').value.toLowerCase() : '';
    const statusRadio = document.querySelector('input[name="estado_mascotas"]:checked');
    const statusVal = statusRadio ? statusRadio.value : '';
    const speciesSelect = document.querySelector('.command-center-filter-select');
    const speciesVal = speciesSelect ? speciesSelect.value.toLowerCase() : '';

    document.querySelectorAll('#petsTable tbody tr').forEach(r => {
        if (r.classList.contains('empty-table')) return;
        const txt = r.innerText.toLowerCase();
        const rStatus = r.getAttribute('data-estado');
        const rSpeciesCell = r.cells[2] ? r.cells[2].innerText.toLowerCase() : '';

        const matchesSearch = txt.includes(term);
        const matchesStatus = (statusVal === '' || rStatus === statusVal);
        const matchesSpecies = (speciesVal === '' || rSpeciesCell.includes(speciesVal));

        r.style.display = (matchesSearch && matchesStatus && matchesSpecies) ? '' : 'none';
    });

    document.querySelectorAll('.pet-card').forEach(c => {
        const txt = c.innerText.toLowerCase();
        const cStatus = c.getAttribute('data-estado');
        const cSpecies = c.getAttribute('data-species') ? c.getAttribute('data-species').toLowerCase() : '';

        const matchesSearch = txt.includes(term);
        const matchesStatus = (statusVal === '' || cStatus === statusVal);
        const matchesSpecies = (speciesVal === '' || cSpecies === speciesVal);

        c.style.display = (matchesSearch && matchesStatus && matchesSpecies) ? '' : 'none';
    });
}

// --- CONSULTAS ---
// M2 C5a y C5b: el formulario de consulta (openConsultationModal,
// saveConsultation, addTreatmentRow, updateFileList y avisarAdjuntosRechazados)
// vive en consulta-formulario.js y consulta-tratamientos.js, compartidos con
// Atención; aquí queda el historial.

/**
 * M2 C5b y C7 (revisión de C5b): una consulta sin peso dice «Sin peso
 * registrado», que es cierto tanto si no se pudo pesar como en las consultas
 * anteriores; el peso NULL nunca se muestra ni se cuenta como 0.
 */
function textoPeso(peso) {
    return peso === null || peso === undefined || peso === '' ? 'Sin peso registrado' : `${escaparTexto(peso)} Kg`;
}

/** M2 C7 (HU-2.2): una consulta sin cita (urgencia o imprevisto) se distingue con «Sin cita». */
function etiquetaSinCita(consulta) {
    if (!consulta || consulta.id_cita) return '';
    return '<span class="consultation-sin-cita-badge" title="Registrada sin cita (urgencia o imprevisto)"><i class="fas fa-ambulance"></i> Sin cita</span>';
}

/**
 * RE-2.5.4 / RE-2.10.1 y RE-2.10.4: un registro de otra clínica lleva a la
 * vista el nombre de la clínica que lo hizo y que es de solo lectura (antes
 * «solo lectura» solo salía al pasar el ratón). Lo propio no lleva marca.
 */
function etiquetaOrigen(registro) {
    if (!registro || registro.es_propia !== false) return '';
    return `<span class="origen-clinica"><i class="fas fa-hospital" aria-hidden="true"></i> ${escaparTexto(registro.clinica_nombre)} · solo lectura</span>`;
}

/** Hueco 6 (M5 C3; RN-115): la mascota se desvinculó de esta clínica. */
const AVISO_SOLO_LECTURA = 'El propietario se desvinculó: solo lectura';

/**
 * Pone o quita, al principio de `contenedor`, el aviso de solo lectura.
 * Devuelve true si la mascota es de solo lectura.
 */
function avisoSoloLectura(contenedor, mascota) {
    const previo = contenedor.querySelector(':scope > .aviso-solo-lectura');
    if (previo) previo.remove();
    if (!mascota || !mascota.solo_lectura) return false;
    const aviso = document.createElement('p');
    aviso.className = 'aviso-solo-lectura';
    aviso.setAttribute('role', 'status');
    aviso.textContent = AVISO_SOLO_LECTURA;
    contenedor.prepend(aviso);
    return true;
}

/** M2 C5a (RN-210): síntomas marcados de una consulta visible, ya escapados. */
function textoSintomas(consulta) {
    const sintomas = Array.isArray(consulta.sintomas) ? consulta.sintomas : [];
    return sintomas.map(s => escaparTexto(s.etiqueta)).join(', ');
}

function seccionSintomas(consulta) {
    const texto = textoSintomas(consulta);
    if (texto === '') return '';
    return `
        <div class="clinical-section">
            <label>Síntomas marcados</label>
            <p>${texto}</p>
        </div>`;
}

function tablaTratamientos(tratamientos) {
    if (!Array.isArray(tratamientos) || tratamientos.length === 0) return '';
    const filas = tratamientos.map(t => `
        <tr>
            <td data-etiqueta="Medicamento">${escaparTexto(t.medicamento)}</td>
            <td data-etiqueta="Dosis">${escaparTexto(t.dosis)}</td>
            <td data-etiqueta="Vía">${escaparTexto(t.via_administracion)}</td>
            <td data-etiqueta="Duración">${escaparTexto(t.duracion)}</td>
            <td data-etiqueta="Inicio">${t.fecha_inicio ? escaparTexto(fechaHistorial(t.fecha_inicio)) : '---'}</td>
        </tr>`).join('');
    return `
        <div class="clinical-section">
            <label><i class="fas fa-pills"></i> Tratamiento Prescrito</label>
            <table class="history-treatment-table">
                <thead><tr><th>Medicamento</th><th>Dosis</th><th>Vía</th><th>Duración</th><th>Inicio</th></tr></thead>
                <tbody>${filas}</tbody>
            </table>
        </div>`;
}

/** La descarga siempre pasa por ver_archivo.php, que aplica la misma regla de visibilidad (RE-2.3.3). */
function listaAdjuntos(archivos) {
    if (!Array.isArray(archivos) || archivos.length === 0) return '';
    const enlaces = archivos.map(archivo => {
        const esImagen = ['jpg', 'jpeg', 'png'].includes(archivo.extension);
        return `
            <a href="ver_archivo.php?id=${encodeURIComponent(archivo.id_archivo)}" target="_blank" rel="noopener" class="history-file-link">
                <i class="fas ${esImagen ? 'fa-image' : 'fa-file-pdf'}"></i> ${escaparTexto(archivo.nombre_original)}
            </a>`;
    }).join('');
    return `
        <div class="clinical-section">
            <label><i class="fas fa-paperclip"></i> Archivos Adjuntos</label>
            <div class="history-adjuntos">${enlaces}</div>
        </div>`;
}

/** Orden común de consultas, vacunas y desparasitaciones (RE-2.5.1). */
function eventosHistorialCompacto(datos) {
    const eventos = [
        ...(datos.consultas || []).map(registro => ({ registro, tipo: 'consulta', fecha: registro.fecha_hora })),
        ...(datos.vacunas || []).map(registro => ({ registro, tipo: 'vacuna', fecha: registro.fecha_aplicacion })),
        ...(datos.desparasitaciones || []).map(registro => ({ registro, tipo: 'desparasitacion', fecha: registro.fecha_aplicacion })),
    ];
    const fechaLocal = fecha => new Date(fecha && fecha.length === 10 ? fecha + 'T00:00:00' : String(fecha || '').replace(' ', 'T'));
    return eventos.sort((a, b) => fechaLocal(b.fecha) - fechaLocal(a.fecha));
}

/**
 * M2 C8.2: una sola fecha dd/mm/aaaa para todos los historiales. Se lee la cadena
 * del servidor sin pasar por Date para que una fecha sin hora no retroceda un día por UTC.
 */
function fechaHistorial(fecha) {
    const partes = String(fecha || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!partes) return 'Sin fecha';
    return `${partes[3]}/${partes[2]}/${partes[1]}`;
}

/**
 * M2 C8.1 (H11) y C8.2: mismo resumen en Atención y en los dos cajones de historial.
 * Dos grupos que el CSS deja en una línea si caben y en dos si no: arriba fecha y
 * diagnóstico (o el motivo si no hay diagnóstico); abajo clínica y chips. Ningún texto
 * se trunca: si no cabe, baja de línea por palabras completas.
 */
function filaHistorialCompacto(registro, tipo) {
    const consulta = tipo === 'consulta';
    const fecha = registro.fecha_hora || registro.fecha_aplicacion;
    const fechaTexto = fechaHistorial(fecha);
    const titulo = consulta ? registro.motivo_consulta : (registro.nombre_vacuna || registro.producto);
    const diagnostico = consulta ? registro.diagnostico : (registro.laboratorio || registro.tipo || '');
    const principal = consulta ? (diagnostico || titulo || 'Sin diagnóstico') : (titulo || 'Sin registro');
    const tratamientos = Array.isArray(registro.tratamientos) ? registro.tratamientos : [];
    const chipDetalle = diagnostico ? `<span class="historia-fila__chip">${escaparTexto(diagnostico)}</span>` : '';
    const chips = consulta ? `${etiquetaSinCita(registro)}<span class="historia-fila__chip">${textoPeso(registro.peso)}</span><span class="historia-fila__chip">${tratamientos.length} tratamientos</span>` : chipDetalle;
    const detalle = consulta ? `
        <p><strong>Veterinario:</strong> ${escaparTexto(registro.veterinario || 'Sin dato')}</p>
        ${etiquetaOrigen(registro)}
        <div class="clinical-data-grid">
            <div class="clinical-field"><label>Peso</label><span>${textoPeso(registro.peso)}</span></div>
            <div class="clinical-field"><label>Temp.</label><span>${escaparTexto(registro.temperatura || '—')} °C</span></div>
            <div class="clinical-field"><label>F.C.</label><span>${escaparTexto(registro.frecuencia_cardiaca || '—')} lpm</span></div>
            <div class="clinical-field"><label>F.R.</label><span>${escaparTexto(registro.frecuencia_respiratoria || '—')} rpm</span></div>
        </div>
        ${seccionSintomas(registro)}
        <p><strong>Motivo:</strong> ${escaparTexto(titulo)}</p>
        <p><strong>Anamnesis:</strong> ${escaparTexto(registro.anamnesis || 'Sin registro')}</p>
        <p><strong>Diagnóstico:</strong> ${escaparTexto(diagnostico)}</p>
        <p><strong>Plan:</strong> ${escaparTexto(registro.plan_tratamiento || 'Sin registro')}</p>
        ${tablaTratamientos(tratamientos)}${listaAdjuntos(registro.archivos)}` : `
        ${etiquetaOrigen(registro)}
        <p><strong>${tipo === 'vacuna' ? 'Vacuna' : 'Producto'}:</strong> ${escaparTexto(titulo)}</p>
        <p><strong>Detalle:</strong> ${escaparTexto(diagnostico)}</p>
        <p><strong>Lote:</strong> ${escaparTexto(registro.lote || 'Sin registro')}</p>
        <p><strong>Próxima aplicación:</strong> ${fechaProximaHistorial(registro.fecha_proxima_dosis || registro.fecha_proxima)}</p>
        <p>${escaparTexto(registro.observaciones || '')}</p>`;
    return `<details class="historia-fila">
        <summary class="historia-fila__resumen">
            <span class="historia-fila__lineas">
                <span class="historia-fila__linea historia-fila__linea--principal">
                    <time class="historia-fila__fecha">${escaparTexto(fechaTexto)}</time>
                    <strong class="historia-fila__titulo">${escaparTexto(principal)}</strong>
                </span>
                <span class="historia-fila__linea historia-fila__linea--contexto">
                    <span class="historia-fila__clinica">${escaparTexto(registro.clinica_nombre || 'Clínica actual')}</span>
                    <span class="historia-fila__chips">${chips}</span>
                </span>
            </span>
            <i class="fas fa-chevron-down historia-fila__flecha" aria-hidden="true"></i>
        </summary>
        <div class="historia-fila__detalle"><p><strong>Clínica:</strong> ${escaparTexto(registro.clinica_nombre || 'Clínica actual')}</p>${detalle}</div>
    </details>`;
}

function fechaProximaHistorial(fecha) {
    return fecha ? escaparTexto(fechaHistorial(fecha)) : 'Sin programar';
}

// M2 C5a: el historial abierto se pone al día cuando se guarda una consulta de esa mascota.
let historialAbierto = null;

async function viewMedicalHistory(id, nombre) {
    historialAbierto = { id, nombre };
    document.getElementById('historyPetName').innerText = nombre;
    const timeline = document.getElementById('historyTimeline');
    const vaccineList = document.getElementById('vaccineList');
    const hcNumber = document.getElementById('historyHCNumber');
    const petSpecie = document.getElementById('historyPetSpecie');
    const petAge = document.getElementById('historyPetAge');

    timeline.innerHTML = '<div class="empty-table"><i class="fas fa-spinner fa-spin"></i><p>Cargando historial...</p></div>';
    vaccineList.innerHTML = '';
    // M2 C1: no se muestran las alertas de la mascota anterior mientras carga la nueva.
    const alertasHistorial = document.querySelector('[data-alertas-historial]');
    if (alertasHistorial) alertasHistorial.hidden = true;
    if (alertasHistorial && window.ZookiPacienteCompacto) window.ZookiPacienteCompacto.preparar(alertasHistorial.closest('[data-paciente-compacto]'));
    hcNumber.innerText = '---';
    petSpecie.innerText = '---';
    petAge.innerText = '---';

    openHistorialDrawer();

    try {
        const data = await pedirJson(`index.php?action=listar_historial_ajax&id_mascota=${encodeURIComponent(id)}`);

        // M2-09: el endpoint ya responde siempre con JSON; si no hay historial
        // se dice en el propio panel en vez de dejarlo cargando para siempre.
        if (data.success === false) {
            timeline.innerHTML = `<div class="empty-table"><i class="fas fa-exclamation-triangle"></i><p>${escaparTexto(data.message || 'No se pudo cargar el historial.')}</p></div>`;
            return;
        }

        // 1. Datos de Mascota
        const m = data.mascota;
        // Hueco 6: desvinculada, la clínica solo lee lo suyo y no se ofrece registrar nada.
        const soloLectura = avisoSoloLectura(document.querySelector('#drawerHistorial .drawer-body') || document.querySelector('#historialDrawer .drawer-body'), m);
        // M2 C1 (RE-2.10.1): alergias y alertas de todas las clínicas, arriba de la historia.
        if (window.ZookiAlertas) window.ZookiAlertas.pintar(alertasHistorial, data.alertas, { idMascota: id, nombreMascota: m.nombre });
        hcNumber.innerText = m.numero_historia_clinica || 'Sin asignar';
        petSpecie.innerText = m.nombre_especie || '---';
        petAge.innerText = m.fecha_nacimiento ? calculateAge(m.fecha_nacimiento) : 'Edad desconocida';

        // M2 C8.1 (H11): las tres clases de registro comparten filas plegables.
        vaccineList.innerHTML = eventosHistorialCompacto(data)
            .filter(evento => evento.tipo !== 'consulta')
            .map(evento => filaHistorialCompacto(evento.registro, evento.tipo)).join('') || '<p class="sub-text">No hay vacunas ni desparasitaciones registradas.</p>';
        timeline.innerHTML = '';
        if (data.consultas && data.consultas.length > 0) {
            timeline.innerHTML = data.consultas.map(c => filaHistorialCompacto(c, 'consulta')).join('');
        } else if (m.numero_historia_clinica) {
            timeline.innerHTML = '<div class="empty-table"><i class="fas fa-folder-open"></i><p>No hay consultas registradas para esta mascota.</p></div>';
        } else {
            mostrarSinHistoria(timeline, id, m.nombre, closeHistorialDrawer, soloLectura);
        }
    } catch (e) {
        console.error(e);
        timeline.innerHTML = '<div class="empty-table empty-table--error"><i class="fas fa-exclamation-triangle"></i><p>Error al cargar el historial clínico.</p></div>';
    }
}

/**
 * RN-102: el número de historia clínica se asigna con la primera consulta.
 * Una mascota sin consultas todavía no tiene historia; se explica en el panel
 * en lugar de dejarlo vacío y, si la vista tiene el formulario de consulta,
 * se ofrece abrirlo desde ahí.
 */
function mostrarSinHistoria(contenedor, idMascota, nombre, alAbrirConsulta = null, soloLectura = false) {
    const puedeConsultar = !soloLectura && Boolean(document.getElementById('modalConsulta'));
    contenedor.innerHTML = `
        <div class="empty-table">
            <i class="fas fa-folder-plus"></i>
            <p><strong>${escaparTexto(nombre)}</strong> aún no tiene historia clínica. El número se asigna con su primera consulta; no hace falta una cita (HU-2.2).</p>
            ${puedeConsultar ? '<button type="button" class="btn-modal-primary btn-primera-consulta"><i class="fas fa-stethoscope"></i> Registrar consulta</button>' : ''}
        </div>`;

    const boton = contenedor.querySelector('.btn-primera-consulta');
    if (boton) {
        boton.addEventListener('click', () => {
            if (alAbrirConsulta) alAbrirConsulta();
            openConsultationModal(idMascota, nombre);
        });
    }
}

// El historial está en dos vistas con marcado distinto: pacientes.php usa
// #drawerHistorial (se abre con la clase .is-open) y consultas.php usa
// #historialDrawer. Antes solo se buscaba el segundo, así que en Pacientes el
// botón de ficha clínica no abría nada.
function openHistorialDrawer() {
    if (document.getElementById('drawerHistorial')) {
        openDrawer('drawerHistorial');
        return;
    }
    const overlay = document.getElementById('historialDrawerOverlay');
    const drawer = document.getElementById('historialDrawer');
    if (overlay) overlay.style.display = 'block';
    if (drawer) setTimeout(() => { drawer.style.transform = 'translateX(0)'; }, 10);
}

function closeHistorialDrawer() {
    historialAbierto = null;
    if (document.getElementById('drawerHistorial')) {
        closeDrawer('drawerHistorial');
        return;
    }
    const drawer = document.getElementById('historialDrawer');
    const overlay = document.getElementById('historialDrawerOverlay');
    if (drawer) drawer.style.transform = 'translateX(100%)';
    setTimeout(() => { if (overlay) overlay.style.display = 'none'; }, 400);
}

function calculateAge(fecha) {
    const born = new Date(fecha);
    const now = new Date();
    let years = now.getFullYear() - born.getFullYear();
    let months = now.getMonth() - born.getMonth();
    if (months < 0 || (months === 0 && now.getDate() < born.getDate())) {
        years--;
        months = 12 + months;
    }
    if (years === 0) return `${months} meses`;
    return `${years} años, ${months} meses`;
}

// --- DRAWER FUNCTIONS ---
function openDrawer(drawerId) {
    const drawer = document.getElementById(drawerId);
    const overlay = document.getElementById(drawerId + 'Overlay');
    if (drawer && overlay) {
        drawer.classList.add('is-open');
        overlay.classList.add('is-open');
    }
}

function closeDrawer(drawerId) {
    const drawer = document.getElementById(drawerId);
    const overlay = document.getElementById(drawerId + 'Overlay');
    if (drawer && overlay) {
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
    }
}

// --- VACUNAS ---
async function openVaccineModal(id, nombre) {
    document.getElementById('vacuna_id_mascota').value = id;
    document.getElementById('vacunaPetName').innerText = nombre;
    document.getElementById('formVacuna').reset();
    document.getElementById('formVacuna').elements['fecha_aplicacion'].value = new Date().toISOString().split('T')[0];
    
    // Ocultar campos de nueva vacuna y laboratorio
    document.getElementById('nuevaVacunaContainer').style.display = 'none';
    document.getElementById('nuevaVacunaInput').required = false;
    document.getElementById('nuevoLaboratorioContainer').style.display = 'none';
    document.getElementById('nuevoLaboratorioInput').required = false;
    
    // Cargar vacunas según la especie de la mascota
    try {
        const res = await fetch(`index.php?action=get_vacunas_por_especie_ajax&id_mascota=${id}`);
        const data = await res.json();
        
        const vacunaSelect = document.querySelector('select[name="nombre_vacuna"]');
        if (vacunaSelect && data.success) {
            vacunaSelect.innerHTML = '<option value="">Seleccione una vacuna...</option>';
            data.vacunas.forEach(v => {
                vacunaSelect.innerHTML += `<option value="${escaparTexto(v.nombre_vacuna)}">${escaparTexto(v.nombre_vacuna)}</option>`;
            });
            // Agregar opción "Otra" al final
            vacunaSelect.innerHTML += `<option value="Otra">Otra (no está en la lista)</option>`;
        }
    } catch (e) {
        console.error('Error al cargar vacunas:', e);
    }
    
    // Cargar laboratorios
    try {
        const res = await fetch('index.php?action=get_laboratorios_ajax');
        const data = await res.json();
        
        const laboratorioSelect = document.querySelector('select[name="laboratorio"]');
        if (laboratorioSelect && data.success) {
            laboratorioSelect.innerHTML = '<option value="">Seleccione laboratorio...</option>';
            data.laboratorios.forEach(l => {
                laboratorioSelect.innerHTML += `<option value="${escaparTexto(l.nombre_laboratorio)}">${escaparTexto(l.nombre_laboratorio)}</option>`;
            });
            // Agregar opción "Otro" al final
            laboratorioSelect.innerHTML += `<option value="Otro">Otro (no está en la lista)</option>`;
        }
    } catch (e) {
        console.error('Error al cargar laboratorios:', e);
    }
    
    openDrawer('drawerVacuna');
}

function toggleNuevaVacuna(select) {
    const nuevaVacunaContainer = document.getElementById('nuevaVacunaContainer');
    const nuevaVacunaInput = document.getElementById('nuevaVacunaInput');
    
    if (select.value === 'Otra') {
        nuevaVacunaContainer.style.display = 'block';
        nuevaVacunaInput.required = true;
    } else {
        nuevaVacunaContainer.style.display = 'none';
        nuevaVacunaInput.required = false;
        nuevaVacunaInput.value = '';
    }
}

function toggleNuevoLaboratorio(select) {
    const nuevoLaboratorioContainer = document.getElementById('nuevoLaboratorioContainer');
    const nuevoLaboratorioInput = document.getElementById('nuevoLaboratorioInput');
    
    if (select.value === 'Otro') {
        nuevoLaboratorioContainer.style.display = 'block';
        nuevoLaboratorioInput.required = true;
    } else {
        nuevoLaboratorioContainer.style.display = 'none';
        nuevoLaboratorioInput.required = false;
        nuevoLaboratorioInput.value = '';
    }
}

async function saveVaccine(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const vacunaSelect = document.querySelector('select[name="nombre_vacuna"]');
    const nuevaVacunaInput = document.getElementById('nuevaVacunaInput');
    const laboratorioSelect = document.querySelector('select[name="laboratorio"]');
    const nuevoLaboratorioInput = document.getElementById('nuevoLaboratorioInput');
    
    try {
        // Si se seleccionó "Otra" y hay una nueva vacuna, primero registrarla
        if (vacunaSelect.value === 'Otra' && nuevaVacunaInput.value.trim()) {
            const nuevaVacunaFd = new FormData();
            nuevaVacunaFd.append('nombre_vacuna', nuevaVacunaInput.value.trim());
            nuevaVacunaFd.append('id_mascota', fd.get('id_mascota'));
            
            const resNueva = await (await fetch('index.php?action=registrar_nueva_vacuna_ajax', { method: 'POST', body: nuevaVacunaFd })).json();
            
            if (resNueva.success) {
                // Actualizar el select con la nueva vacuna y seleccionarla
                fd.set('nombre_vacuna', resNueva.nombre_vacuna);
            } else {
                zookiAviso(resNueva.message);
                return;
            }
        }
        
        // Si se seleccionó "Otro" y hay un nuevo laboratorio, primero registrarlo
        if (laboratorioSelect.value === 'Otro' && nuevoLaboratorioInput.value.trim()) {
            const nuevoLaboratorioFd = new FormData();
            nuevoLaboratorioFd.append('nombre_laboratorio', nuevoLaboratorioInput.value.trim());
            
            const resLab = await (await fetch('index.php?action=registrar_nuevo_laboratorio_ajax', { method: 'POST', body: nuevoLaboratorioFd })).json();
            
            if (resLab.success) {
                // Actualizar el select con el nuevo laboratorio y seleccionarlo
                fd.set('laboratorio', resLab.nombre_laboratorio);
            } else {
                zookiAviso(resLab.message);
                return;
            }
        }
        
        // Registrar la aplicación de la vacuna
        const res = await (await fetch('index.php?action=registrar_vacuna_ajax', { method: 'POST', body: fd })).json();
        if (res.success) { 
            closeDrawer('drawerVacuna');
            zookiRecargarConAviso(res.message);
        } else {
            zookiAviso(res.message);
        }
    } catch (e) { 
        console.error(e); 
        zookiAviso('No se pudo registrar la vacuna.');
    }
}

// --- DESPARASITACIÓN ---
async function openDewormingModal(id, nombre) {
    document.getElementById('desp_id_mascota').value = id;
    document.getElementById('despPetName').innerText = nombre;
    document.getElementById('formDesparasitacion').reset();
    document.getElementById('formDesparasitacion').elements['fecha_aplicacion'].value = new Date().toISOString().split('T')[0];
    
    // Ocultar campo de nuevo producto
    document.getElementById('nuevoProductoContainer').style.display = 'none';
    document.getElementById('nuevoProductoInput').required = false;
    
    // Cargar productos
    try {
        const res = await fetch('index.php?action=get_productos_desparasitacion_ajax');
        const data = await res.json();
        
        const productoSelect = document.querySelector('select[name="producto"]');
        if (productoSelect && data.success) {
            productoSelect.innerHTML = '<option value="">Seleccione producto...</option>';
            data.productos.forEach(p => {
                productoSelect.innerHTML += `<option value="${escaparTexto(p.nombre_producto)}">${escaparTexto(p.nombre_producto)} (${escaparTexto(p.tipo)})</option>`;
            });
            // Agregar opción "Otro" al final
            productoSelect.innerHTML += `<option value="Otro">Otro (no está en la lista)</option>`;
        }
    } catch (e) {
        console.error('Error al cargar productos:', e);
    }
    
    openDrawer('drawerDesparasitacion');
}

function toggleNuevoProducto(select) {
    const nuevoProductoContainer = document.getElementById('nuevoProductoContainer');
    const nuevoProductoInput = document.getElementById('nuevoProductoInput');
    
    if (select.value === 'Otro') {
        nuevoProductoContainer.style.display = 'block';
        nuevoProductoInput.required = true;
    } else {
        nuevoProductoContainer.style.display = 'none';
        nuevoProductoInput.required = false;
        nuevoProductoInput.value = '';
    }
}

async function saveDeworming(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const productoSelect = document.querySelector('select[name="producto"]');
    const nuevoProductoInput = document.getElementById('nuevoProductoInput');
    const tipoSelect = document.querySelector('select[name="tipo"]');
    
    try {
        // Si se seleccionó "Otro" y hay un nuevo producto, primero registrarlo
        if (productoSelect.value === 'Otro' && nuevoProductoInput.value.trim()) {
            const nuevoProductoFd = new FormData();
            nuevoProductoFd.append('nombre_producto', nuevoProductoInput.value.trim());
            nuevoProductoFd.append('tipo', tipoSelect.value);
            
            const resNuevo = await (await fetch('index.php?action=registrar_nuevo_producto_desparasitacion_ajax', { method: 'POST', body: nuevoProductoFd })).json();
            
            if (resNuevo.success) {
                // Actualizar el select con el nuevo producto y seleccionarlo
                fd.set('producto', resNuevo.nombre_producto);
            } else {
                zookiAviso(resNuevo.message);
                return;
            }
        }
        
        // Registrar la desparasitación
        const res = await (await fetch('index.php?action=registrar_desparasitacion_ajax', { method: 'POST', body: fd })).json();
        if (res.success) { 
            closeDrawer('drawerDesparasitacion');
            zookiRecargarConAviso(res.message);
        } else {
            zookiAviso(res.message);
        }
    } catch (e) { 
        console.error(e); 
        zookiAviso('No se pudo registrar la desparasitación.');
    }
}

// --- CITAS / AGENDAMIENTO ---
let vetsLoaded = false;

async function loadVets() {
    if (vetsLoaded) return;
    try {
        const res = await (await fetch('index.php?action=listar_veterinarios_ajax')).json();
        const select = document.getElementById('cita_veterinario');
        select.innerHTML = '<option value="">Seleccione un veterinario...</option>';
        res.forEach(v => {
            select.innerHTML += `<option value="${escaparTexto(v.id_usuario)}">${escaparTexto(v.nombre_completo)}</option>`;
        });
        vetsLoaded = true;
    } catch (e) {
        console.error('Error loading vets', e);
        document.getElementById('cita_veterinario').innerHTML = '<option value="">Error al cargar</option>';
    }
}

async function loadVetsForAppointment() {
    const select = document.getElementById('cita_veterinario');
    if (!select) return;

    try {
        const res = await (await fetch('index.php?action=listar_veterinarios_ajax')).json();
        if (res && res.length > 0) {
            select.innerHTML = res.map(v => `<option value="${escaparTexto(v.id_usuario)}">${escaparTexto(v.nombre_completo)}</option>`).join('');
        } else {
            select.innerHTML = '<option value="">No hay veterinarios disponibles</option>';
        }
    } catch(e) { 
        console.error('Error loading vets', e); 
        select.innerHTML = '<option value="">Error al cargar veterinarios</option>';
    }
}

function openAppointmentModal(id, nombre) {
    document.getElementById('cita_id_mascota').value = id;
    document.getElementById('citaPetName').innerText = nombre;
    document.getElementById('formCita').reset();
    document.getElementById('cita_fecha').value = new Date().toISOString().split('T')[0];
    loadVetsForAppointment();
    openModal('modalCita');
}

async function saveAppointment(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agendando...';

    const fd = new FormData(e.target);
    try {
        const res = await (await fetch('index.php?action=registrar_cita_ajax', { method: 'POST', body: fd })).json();
        if (res.success) { 
            closeModal('modalCita');
            zookiRecargarConAviso(res.message);
        } else {
            zookiAviso(res.message);
        }
    } catch (e) { 
        console.error(e); 
        zookiAviso('No se pudo confirmar el agendado. Es posible que la cita se haya guardado pero fallara el correo: revisa la agenda antes de reintentar.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Confirmar Cita';
    }
}

// --- INTERACTIVE PET DASHBOARD (ESTILO OKVET) ---
// Abre la ficha de una mascota del expediente que ya está en pantalla.
async function loadPetDashboard(id) {
    const peticion = ++dossierPeticion;
    try {
        const data = await pedirJson(`index.php?action=listar_historial_ajax&id_mascota=${encodeURIComponent(id)}`);
        if (peticion !== dossierPeticion) return;

        // M2-09: el endpoint ya responde siempre con JSON; si no hay historial
        // se avisa en vez de reventar al leer data.mascota.
        if (data.success === false) {
            zookiAviso(data.message || 'No se pudo cargar el historial.');
            return;
        }

        renderPetDashboard(data, id);
        mostrarSeccionDossier('ficha');
    } catch (e) {
        console.error('Error loading pet dashboard:', e);
        zookiAviso('No se pudo abrir el panel de la mascota.');
    }
}

function renderPetDashboard(data, id) {
    const m = data.mascota;

    // 1. Dashboard Header & Tab bindings
    document.getElementById('dashPetName').innerText = m.nombre;

    const editBtn = document.getElementById('dashEditPetBtn');
    if (editBtn) {
        editBtn.setAttribute('data-id', id);
        editBtn.onclick = () => editPet(id);
        // Hueco 6: con la mascota desvinculada no se edita ni se registra nada.
        editBtn.hidden = Boolean(m.solo_lectura);
    }
    const soloLectura = avisoSoloLectura(document.getElementById('dossierPetDashboardSection'), m);
    // M2 C1 (RE-2.10.1): alergias y alertas de todas las clínicas, arriba de la ficha.
    if (window.ZookiAlertas) window.ZookiAlertas.pintar(document.querySelector('[data-alertas-ficha]'), data.alertas, { idMascota: id, nombreMascota: m.nombre });

    // 2. Pet Info Card
    const photo = document.getElementById('dashPetPhoto');
    if (photo) {
        photo.onerror = () => usarFotoPorDefecto(photo);
        photo.src = fotoMascotaUrl(m.url_foto);
    }

    const statusDot = document.getElementById('dashPetStatus');
    if (statusDot) {
        statusDot.style.backgroundColor = m.estado == 1 ? 'var(--success)' : 'var(--danger)';
        statusDot.style.boxShadow = m.estado == 1 ? '0 0 10px var(--success)' : '0 0 10px var(--danger)';
    }

    document.getElementById('dashPetEspecie').innerText = m.nombre_especie || '---';
    document.getElementById('dashPetRaza').innerText = razaConIndicada(m.nombre_raza, m.raza_indicada);
    document.getElementById('dashPetSexo').innerText = m.sexo || '---';
    document.getElementById('dashPetPeso').innerText = m.peso ? m.peso + ' Kg' : '---';
    document.getElementById('dashPetHC').innerText = m.numero_historia_clinica || 'Sin asignar';
    document.getElementById('dashPetFechaNac').innerText = m.fecha_nacimiento
        ? new Date(m.fecha_nacimiento).toLocaleDateString() + ' (' + calculateAge(m.fecha_nacimiento) + ')'
        : '---';

    // 3. Render Unified Chronological Timeline
    const timelineDiv = document.getElementById('dashHistorialTimeline');
    if (timelineDiv) {
        const eventos = eventosHistorialCompacto(data);
        if (eventos.length > 0) {
            timelineDiv.innerHTML = eventos.map(evento => filaHistorialCompacto(evento.registro, evento.tipo)).join('');
        } else if (m.numero_historia_clinica) {
            timelineDiv.innerHTML = '<div class="empty-table"><i class="fas fa-history"></i><p>No hay eventos registrados en el expediente clínico.</p></div>';
        } else {
            mostrarSinHistoria(timelineDiv, id, m.nombre, null, soloLectura);
        }
    }

    // 4. Cada ficha abre en "Información General"
    document.querySelectorAll('.dossier-dashboard-tab').forEach((b, i) => b.classList.toggle('active', i === 0));
    mostrarPestanaFicha('dashGeneral');
}

// RE-7.4.4: cada acto conserva su marca; la cabecera del historial sigue siendo Zooki.
function marcaParaImprimir(registro) {
    const logo = String(registro.clinica_logo || '');
    const valido = !/[\s<>"'\\]/.test(logo) && !logo.startsWith('//')
        && (/^https?:\/\//i.test(logo) || !logo.includes(':'));
    const imagen = logo && valido ? `<img width="96" class="marca-clinica-logo" src="${escaparTexto(logo)}" alt="Logo de ${escaparTexto(registro.clinica_nombre)}">` : '';
    const contacto = ['clinica_direccion', 'clinica_telefono', 'clinica_email'].filter(c => registro[c]).map(c => `<span>${escaparTexto(registro[c])}</span>`).join('');
    return `<div class="marca-clinica">${imagen}<strong>${escaparTexto(registro.clinica_nombre)}</strong>${contacto}</div>`;
}

async function printMedicalHistory(id, nombre) {
    try {
        const response = await fetch(`index.php?action=listar_historial_ajax&id_mascota=${id}`);
        const data = await response.json();

        // M2-09: el endpoint ya responde siempre con JSON; si no hay historial
        // se avisa en vez de reventar al leer data.mascota.
        if (data.success === false) {
            zookiAviso(data.message || 'No se pudo cargar el historial.');
            return;
        }
        
        // Crear un iframe oculto para imprimir sin salir de la página
        let printIframe = document.getElementById('printIframe');
        if (!printIframe) {
            printIframe = document.createElement('iframe');
            printIframe.id = 'printIframe';
            printIframe.style.visibility = 'hidden';
            printIframe.style.position = 'absolute';
            printIframe.style.right = '0';
            printIframe.style.bottom = '0';
            document.body.appendChild(printIframe);
        }
        
        let html = `
            <html>
            <head>
                <title>Historia Clínica - ${escaparTexto(nombre)}</title>
                <link rel="stylesheet" href="css/marca-clinica.css">
                <link rel="stylesheet" href="css/imprimir-historia-personal.css">
            </head>
            <body>
                <h1>Zooki · Historia Clínica: ${escaparTexto(nombre)}</h1>
                <div class="meta">
                    <strong>HC N°:</strong> ${escaparTexto(data.mascota.numero_historia_clinica || '---')} <br>
                    <strong>Especie/Raza:</strong> ${escaparTexto(data.mascota.nombre_especie)} - ${escaparTexto(razaConIndicada(data.mascota.nombre_raza, data.mascota.raza_indicada))} <br>
                    <strong>Propietario:</strong> ${escaparTexto(data.mascota.propietario_nombre)}
                </div>
        `;

        if (data.consultas && data.consultas.length > 0) {
            html += `<div class="section"><h2>Consultas Clínicas</h2>`;
            data.consultas.forEach(c => {
                html += `
                    <h3>Fecha: ${new Date(c.fecha_hora).toLocaleDateString()} - Dr. ${escaparTexto(c.veterinario)}</h3>
                    ${marcaParaImprimir(c)}
                    <p><strong>Motivo:</strong> ${escaparTexto(c.motivo_consulta)}</p>
                    ${textoSintomas(c) ? `<p><strong>Síntomas:</strong> ${textoSintomas(c)}</p>` : ''}
                    <p><strong>Diagnóstico:</strong> ${escaparTexto(c.diagnostico)}</p>
                    <p><strong>Peso:</strong> ${textoPeso(c.peso)} | <strong>Temp:</strong> ${escaparTexto(c.temperatura || '--')} °C</p>
                `;
                if (c.tratamientos && c.tratamientos.length > 0) {
                    html += `<ul>`;
                    c.tratamientos.forEach(t => {
                        html += `<li>${escaparTexto(t.medicamento)} - ${escaparTexto(t.dosis)} (${escaparTexto(t.via_administracion)}, ${escaparTexto(t.duracion)}, desde ${escaparTexto(t.fecha_inicio)})</li>`;
                    });
                    html += `</ul>`;
                }
            });
            html += `</div>`;
        }

        if (data.vacunas && data.vacunas.length > 0) {
            html += `<div class="section"><h2>Vacunación</h2><table><tr><th>Fecha</th><th>Vacuna</th><th>Clínica</th><th>Próxima Dosis</th></tr>`;
            data.vacunas.forEach(v => {
                html += `<tr><td>${new Date(v.fecha_aplicacion).toLocaleDateString()}</td><td>${escaparTexto(v.nombre_vacuna)}</td><td>${marcaParaImprimir(v)}</td><td>${v.fecha_proxima_dosis ? new Date(v.fecha_proxima_dosis).toLocaleDateString() : '---'}</td></tr>`;
            });
            html += `</table></div>`;
        }

        if (data.desparasitaciones && data.desparasitaciones.length > 0) {
            html += `<div class="section"><h2>Desparasitaciones</h2><table><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Clínica</th><th>Próxima Dosis</th></tr>`;
            data.desparasitaciones.forEach(d => {
                html += `<tr><td>${new Date(d.fecha_aplicacion).toLocaleDateString()}</td><td>${escaparTexto(d.producto)}</td><td>${escaparTexto(d.tipo)}</td><td>${marcaParaImprimir(d)}</td><td>${d.fecha_proxima ? new Date(d.fecha_proxima).toLocaleDateString() : '---'}</td></tr>`;
            });
            html += `</table></div>`;
        }

        html += `
                <div class="pie">
                    Documento generado el ${new Date().toLocaleString()} por Zooki.
                </div>
            </body>
            </html>
        `;

        const printDocument = printIframe.contentWindow.document;
        printDocument.open();
        printDocument.write(html);
        printDocument.close();
        
        // Esperar a que renderice y llamar print
        setTimeout(() => {
            printIframe.contentWindow.focus();
            printIframe.contentWindow.print();
        }, 500);

    } catch (e) {
        console.error('Error generando PDF:', e);
        zookiAviso('No se pudo generar el documento para imprimir.');
    }
}

function switchPetDashTab(event, tabId) {
    document.querySelectorAll('.dossier-dashboard-tab').forEach(b => b.classList.remove('active'));
    event.currentTarget.classList.add('active');
    mostrarPestanaFicha(tabId);
}

// Las pestañas llevan las clases d-none/d-block, que son !important: cambiar
// solo style.display (como se hacía) no las mostraba, y la pestaña
// "Historia Clínica" nunca llegaba a abrirse.
function mostrarPestanaFicha(tabId) {
    document.querySelectorAll('.dossier-dashboard-content .dash-tab-content').forEach(c => {
        const visible = c.id === tabId;
        c.classList.toggle('active', visible);
        c.classList.toggle('d-block', visible);
        c.classList.toggle('d-none', !visible);
        c.style.display = '';
    });
}

function showPetsListFromDossier() {
    mostrarSeccionDossier('listado');
}

function toggleDashConsultaDetails(index) {
    const detailRow = document.getElementById(`consultaDetails-${index}`);
    const icon = document.getElementById(`toggleIcon-${index}`);
    if (detailRow) {
        if (detailRow.style.display === 'none') {
            detailRow.style.display = 'table-row';
            if (icon) icon.className = 'fas fa-eye-slash';
        } else {
            detailRow.style.display = 'none';
            if (icon) icon.className = 'fas fa-eye';
        }
    }
}

// Bind to window to ensure absolute global availability
window.loadPetDashboard = loadPetDashboard;
window.switchPetDashTab = switchPetDashTab;
window.showPetsListFromDossier = showPetsListFromDossier;
window.toggleDashConsultaDetails = toggleDashConsultaDetails;

// --- INITIALIZATION ---
window.onclick = (e) => {
    if (e.target.classList.contains('modal')) closeModal(e.target.id);
    if (e.target.classList.contains('lightbox')) closeLightbox();
};

function setupPreviewBox(box, input, previewId, btnId = null) {
    if (!box || !input) return;
    
    // Handler para hacer clic
    box.addEventListener('click', (e) => {
        // Evitar bucle si se hace click en elementos internos
        if (e.target !== input && !e.target.closest('.btn-clear-img')) {
            input.click();
        }
    });
    
    // Handlers para arrastrar
    ['dragenter', 'dragover'].forEach(eventName => {
        box.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            box.classList.add('dragging');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        box.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            box.classList.remove('dragging');
        }, false);
    });
    
    // Handler para soltar
    box.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            input.files = files;
            // Actualizar vista previa
            previewImg(input, previewId, btnId);
        }
    }, false);
}

// promptAddNewColor removida para evitar data basura

function initPreviewBoxes() {
    const regBox = document.querySelector('#tabNuevaMascota .preview-box');
    const regInput = document.getElementById('newFoto');
    const editBox = document.querySelector('#modalEditarMascota .preview-box');
    const editInput = document.getElementById('editFoto');
    
    setupPreviewBox(regBox, regInput, 'newPreview', 'btnClearNewImg');
    setupPreviewBox(editBox, editInput, 'editPreview', 'btnClearEditImg');
}

document.addEventListener('DOMContentLoaded', () => {
    // Mover todos los modales al nivel del body para evitar que el sidebar los cubra (z-index fix)
    document.querySelectorAll('.modal, .users-modal').forEach(m => document.body.appendChild(m));

    if (document.getElementById('petsTable')) {
        switchView(localStorage.getItem('petViewPreference') || 'grid');
        switchOwnerView(localStorage.getItem('ownerViewPreference') || 'grid');
        initSpecies(); loadOwners();
        initPreviewBoxes();
        initDatePickers();
    }

    if (document.getElementById("petsTable")) filterTable();
});

function initDatePickers() {
    if (typeof flatpickr === 'undefined') return;
    try {
        let currentLocale = "es";
        if (flatpickr.l10ns && flatpickr.l10ns.es) {
            currentLocale = flatpickr.l10ns.es;
            if (currentLocale.weekdays) {
                currentLocale.weekdays.shorthand = ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"];
            }
        }
        const fpConfig = {
            locale: currentLocale,
            dateFormat: "Y-m-d",
            maxDate: "today",
            disableMobile: true,
            altInput: true,
            altFormat: "Y-m-d",
            monthSelectorType: "static",
            onReady: function(selectedDates, dateStr, instance) {
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
                        
                        // Bloquear meses futuros
                        if (viewYear > currentY || (viewYear === currentY && i > currentM)) {
                            btn.classList.add('disabled');
                            btn.disabled = true;
                        }
                        
                        btn.innerText = m;
                        
                        if (!btn.disabled) {
                            btn.onclick = (e) => {
                                e.stopPropagation();
                                instance.changeYear(viewYear);
                                instance.changeMonth(i);
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
                    const currentY = new Date().getFullYear();
                    const startY = currentY - 100;
                    navTitle.innerText = `${startY} - ${currentY}`;
                    
                    for (let y = currentY; y >= startY; y--) {
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
                    
                    // Mover scroll al año activo
                    setTimeout(() => {
                        const activeBtn = yearsGrid.querySelector('.active');
                        if (activeBtn) activeBtn.scrollIntoView({ block: 'center' });
                    }, 10);
                }

                nav._renderMonthsView = renderMonthsView; // bind for use in header click
                navTitle.onclick = (e) => {
                    e.stopPropagation();
                    if (currentView === 'months') {
                        renderYearsView();
                    } else {
                        renderMonthsView();
                    }
                };
            }
        };
        flatpickr(".flatpickr-date", fpConfig);
    } catch(e) {
        console.error("Error inicializando Flatpickr:", e);
    }
}


function filterDossierPets() {
    const termInput = document.getElementById('dossierPetSearch');
    const speciesInput = document.getElementById('dossierPetSpeciesFilter');
    if (!termInput || !speciesInput) return;

    const term = termInput.value.toLowerCase();
    const speciesSel = speciesInput.value.toLowerCase();

    document.querySelectorAll('#dossierPetsScroll .pet-dossier-card').forEach(card => {
        const nameEl = card.querySelector('.pet-dossier-card-name');
        const petName = nameEl ? nameEl.innerText.toLowerCase() : '';

        const speciesEl = card.querySelector('.pet-dossier-card-species');
        const speciesText = speciesEl ? speciesEl.innerText.toLowerCase() : '';

        const hcEl = card.querySelector('.pet-dossier-card-hc');
        const hcText = hcEl ? hcEl.innerText.toLowerCase() : '';

        const matchesSearch = petName.includes(term) || speciesText.includes(term) || hcText.includes(term);
        const matchesSpecies = speciesSel === '' || speciesText.includes(speciesSel);

        card.style.display = (matchesSearch && matchesSpecies) ? '' : 'none';
    });
}

window.filterDossierPets = filterDossierPets;

/** Un aviso del buscador de «Atención sin cita», con las clases de la vista, sin estilos en línea. */
function avisoBusquedaPaciente(texto, clase = '', icono = false) {
    const aviso = document.createElement('div');
    aviso.className = `empty-state-text ${clase}`.trim();
    if (icono) {
        const i = document.createElement('i');
        i.className = 'fas fa-search icon-large';
        aviso.append(i);
    }
    aviso.append(texto);
    return aviso;
}

function openNewConsultationFlow() {
    openModal('modalSelectPetConsulta');
    const searchInput = document.getElementById('consultaPetSearch');
    if (searchInput) searchInput.value = '';
    const suggestions = document.getElementById('consultaPetSuggestions');
    if (suggestions) suggestions.replaceChildren(avisoBusquedaPaciente('Escribe para buscar un paciente...', '', true));
}

/** Un paciente encontrado: al elegirlo se abre su consulta (sin onclick ni onerror en línea). */
function resultadoPaciente(item) {
    const fila = document.createElement('div');
    fila.className = 'suggestion-item paciente-resultado';
    fila.innerHTML = `
        <img src="${escaparTexto(fotoMascotaUrl(item.url_foto))}" class="paciente-resultado__foto" alt="">
        <div>
            <div class="paciente-resultado__nombre">${escaparTexto(item.nombre)}</div>
            <!-- M1-21: HU-03 pide mostrar la especie y no aparecia. -->
            <div class="paciente-resultado__dato">${escaparTexto(item.nombre_especie || 'Sin especie')}${item.nombre_raza ? ' · ' + escaparTexto(item.nombre_raza) : ''}</div>
            <div class="paciente-resultado__dato">Propietario: ${escaparTexto(item.propietario_nombre || 'Sin asignar')}</div>
        </div>`;
    const foto = fila.querySelector('img');
    foto.addEventListener('error', () => usarFotoPorDefecto(foto), { once: true });
    fila.addEventListener('click', () => selectPetForConsultation(item.id_mascota, item.nombre));
    return fila;
}

async function searchPetForConsultation(term) {
    const suggestions = document.getElementById('consultaPetSuggestions');
    // M1-15: HU-03 pide un minimo de 3 caracteres; el front pedia 2 y el
    // backend tambien, asi que la busqueda salia con menos de lo acordado.
    if (term.length < 3) {
        suggestions.replaceChildren(avisoBusquedaPaciente('Escribe al menos 3 caracteres para buscar un paciente...', '', true));
        return;
    }

    try {
        const res = await (await fetch(`index.php?action=buscar_mascotas&query=${encodeURIComponent(term)}`)).json();
        if (res.length) {
            suggestions.replaceChildren(...res.map(resultadoPaciente));
        } else {
            suggestions.replaceChildren(avisoBusquedaPaciente('No se encontraron pacientes.', 'empty-state-text--compacto'));
        }
    } catch (e) {
        console.error('Search error', e);
        suggestions.replaceChildren(avisoBusquedaPaciente('Error en la búsqueda.', 'empty-state-text--compacto empty-state-text--error'));
    }
}

function selectPetForConsultation(id, nombre) {
    closeModal('modalSelectPetConsulta');
    openConsultationModal(id, nombre);
}

async function verFichaBasica(id) {
    try {
        const mascota=await pedirJson(`index.php?action=get_mascota_ajax&id=${encodeURIComponent(id)}`);
        if (!mascota.id_mascota) throw new Error('Ficha no disponible');
        await Swal.fire({title:mascota.nombre,html:`<p>${escaparTexto(mascota.nombre_especie)} · ${escaparTexto(razaConIndicada(mascota.nombre_raza,mascota.raza_indicada))}</p>
            <p>Propietario: ${escaparTexto(mascota.propietario_nombre)}</p><p>Peso: ${escaparTexto(mascota.peso)} kg · ${escaparTexto(mascota.sexo)}</p>
            <p>Historia clínica: ${escaparTexto(mascota.numero_historia_clinica || 'Se asigna en la primera consulta')}</p>`,confirmButtonText:'Cerrar'});
    } catch (error) { zookiAviso('No se pudo abrir la ficha de esta clínica.'); }
}
// Eventos de pacientes externos a la vista (AGENTS.md).
const eventosPacientes = {
    evento0: (event, element) => { switchModule('owners'); },
    evento1: (event, element) => { switchModule('pets'); },
    evento2: (event, element) => { abrirModalRegistro('propietario'); },
    evento3: (event, element) => { filterOwners(); },
    evento4: (event, element) => { filterOwners(); },
    evento5: (event, element) => { filterOwners(); },
    evento6: (event, element) => { filterOwners(); },
    evento7: (event, element) => { filterTable(); },
    evento8: (event, element) => { filterTable(); },
    evento9: (event, element) => { filterTable(); },
    evento10: (event, element) => { filterTable(); },
    evento11: (event, element) => { filterTable(); },
    evento12: (event, element) => { switchOwnerView('list'); },
    evento13: (event, element) => { switchOwnerView('grid'); },
    evento14: (event, element) => { switchView('list'); },
    evento15: (event, element) => { switchView('grid'); },
    evento16: (event, element) => { editOwnerFromDossier(); },
    evento17: (event, element) => { hideDossier(); },
    evento18: (event, element) => { window.location.href='tel:'+document.getElementById('dossierOwnerPhone').innerText; },
    evento19: (event, element) => { window.location.href='mailto:'+document.getElementById('dossierOwnerEmail').innerText; },
    evento20: (event, element) => { window.location.href='index.php?action=vet_agenda'; },
    evento21: (event, element) => { addNewPetFromDossier(); },
    evento22: (event, element) => { filterDossierPets(); },
    evento23: (event, element) => { filterDossierPets(); },
    evento24: (event, element) => { moverCarruselMascotas(-1); },
    evento25: (event, element) => { moverCarruselMascotas(1); },
    evento26: (event, element) => { showPetsListFromDossier(); },
    evento27: (event, element) => { switchPetDashTab(event, 'dashGeneral'); },
    evento28: (event, element) => { switchPetDashTab(event, 'dashHistorial'); },
    evento29: (event, element) => { printMedicalHistory(document.getElementById('dashEditPetBtn').getAttribute('data-id'), document.getElementById('dashPetName').innerText); },
    evento30: (event, element) => { viewImage(element.src); },
    evento31: (event, element) => { element.onerror=null;element.src='img/default-pet.svg'; },
    evento32: (event, element) => { viewMedicalHistory(JSON.parse(element.dataset.c3Arg0), JSON.parse(element.dataset.c3Arg1)); },
    evento33: (event, element) => { editPet(JSON.parse(element.dataset.c3Arg0)); },
    evento34: (event, element) => { verFichaBasica(JSON.parse(element.dataset.c3Arg0)); },
    evento35: (event, element) => { element.onerror=null;element.src='img/default-pet.svg'; },
    evento36: (event, element) => { togglePetStatus(JSON.parse(element.dataset.c3Arg0), element.checked ? 1 : 0); },
    evento37: (event, element) => { verFichaBasica(JSON.parse(element.dataset.c3Arg0)); },
    evento38: (event, element) => { editPet(JSON.parse(element.dataset.c3Arg0)); },
    evento39: (event, element) => { viewMedicalHistory(JSON.parse(element.dataset.c3Arg0), JSON.parse(element.dataset.c3Arg1)); },
    evento40: (event, element) => { verFichaBasica(JSON.parse(element.dataset.c3Arg0)); },
    evento41: (event, element) => { saveOwner(event); },
    evento42: (event, element) => { savePet(event); },
    evento43: (event, element) => { loadBreeds(element.value, 'new_raza'); },
    evento44: (event, element) => { checkOtherBreed(element, 'newOtherBreedGroup'); },
    evento46: (event, element) => { closeDrawer('drawerHistorial'); },
    evento47: (event, element) => { closeDrawer('drawerHistorial'); },
    evento48: (event, element) => { closeDrawer('drawerVacuna'); },
    evento49: (event, element) => { closeDrawer('drawerVacuna'); },
    evento50: (event, element) => { saveVaccine(event); },
    evento51: (event, element) => { toggleNuevaVacuna(element); },
    evento52: (event, element) => { toggleNuevoLaboratorio(element); },
    evento53: (event, element) => { closeDrawer('drawerDesparasitacion'); },
    evento54: (event, element) => { closeDrawer('drawerDesparasitacion'); },
    evento55: (event, element) => { saveDeworming(event); },
    evento56: (event, element) => { toggleNuevoProducto(element); },
    evento57: (event, element) => { closeModal('modalCita'); },
    evento58: (event, element) => { saveAppointment(event); },
    evento59: (event, element) => { closeLightbox(); },
};
/**
 * Enlaza los eventos data-c3-* dentro de `raiz`. M5 C3: se vuelve a llamar
 * con la parte que se repinta en su sitio (propietario-mascota.js).
 */
function enlazarEventosPacientes(raiz) {
    raiz.querySelectorAll('[data-c3-change]').forEach(element => element.addEventListener('change', event => eventosPacientes[element.dataset.c3Change](event, element)));
    raiz.querySelectorAll('[data-c3-click]').forEach(element => element.addEventListener('click', event => eventosPacientes[element.dataset.c3Click](event, element)));
    raiz.querySelectorAll('[data-c3-error]').forEach(element => element.addEventListener('error', event => eventosPacientes[element.dataset.c3Error](event, element)));
    raiz.querySelectorAll('[data-c3-keyup]').forEach(element => element.addEventListener('keyup', event => eventosPacientes[element.dataset.c3Keyup](event, element)));
    raiz.querySelectorAll('[data-c3-submit]').forEach(element => element.addEventListener('submit', event => eventosPacientes[element.dataset.c3Submit](event, element)));
}
document.addEventListener('DOMContentLoaded', () => enlazarEventosPacientes(document));

let itiNewOwnerPhone;
let itiEditOwnerPhone;

// M5 C4a: la configuración es la de js/telefono-internacional.js, una sola para todo el sistema.
document.addEventListener('DOMContentLoaded', function() {
    if (!window.zookiTelefono) return;
    itiNewOwnerPhone = window.zookiTelefono(document.querySelector("#new_owner_tel"));
    itiEditOwnerPhone = window.zookiTelefono(document.querySelector("#edit_owner_tel"));
});

function checkOtherBreed(select, targetGroupId) {
    const group = document.getElementById(targetGroupId);
    if (!group) return;
    const input = group.querySelector('input');
    if (select.value === 'Otra' || select.value === 'other') {
        group.classList.remove('d-none');
        group.style.display = 'block';
        if (input) input.required = true;
    } else {
        group.classList.add('d-none');
        group.style.display = 'none';
        if (input) {
            input.required = false;
            input.value = '';
        }
    }
}

function validarSelect(select) {
    const errorSpan = select.closest('.input-group') ? select.closest('.input-group').querySelector('.error-message') : null;
    if (!errorSpan) return;

    if (select.value === '') {
        errorSpan.textContent = 'Debe seleccionar una opción';
        errorSpan.style.display = 'block';
        select.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        select.classList.remove('error');
    }

    if (select.name === 'tipo_documento') {
        const docInput = select.closest('form') ? select.closest('form').querySelector('input[name="documento"]') : null;
        if (docInput && docInput.value.length > 0) {
            validarDocumento(docInput);
        }
    }
}

async function verificarDocumentoExiste(input) {
    const value = input.value.replace(/[^0-9]/g, '');
    input.value = value;

    const errorSpan = input.closest('.input-group') ? input.closest('.input-group').querySelector('.error-message') : null;
    if (!errorSpan) return;

    if (value.length < 5) return;

    const originalDoc = document.getElementById('edit_owner_doc_orig') ? document.getElementById('edit_owner_doc_orig').value : '';
    const url = `index.php?action=verificar_documento_ajax&documento=${value}&exclude_doc=${originalDoc}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (data.exists) {
            errorSpan.textContent = 'El documento ya está registrado en el sistema';
            errorSpan.style.display = 'block';
            input.classList.add('error');
        } else {
            errorSpan.style.display = 'none';
            input.classList.remove('error');
        }
    } catch (e) {
        console.error('Error al verificar documento:', e);
    }
}

async function verificarEmailExiste(input) {
    const value = input.value;

    const errorSpan = input.closest('.input-group') ? input.closest('.input-group').querySelector('.error-message') : null;
    if (!errorSpan) return;

    if (!value || !value.includes('@')) return;

    const originalDoc = document.getElementById('edit_owner_doc_orig') ? document.getElementById('edit_owner_doc_orig').value : '';
    const url = `index.php?action=verificar_email_ajax&email=${encodeURIComponent(value)}&exclude_doc=${originalDoc}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (data.exists) {
            errorSpan.textContent = 'El correo electrónico ya está registrado en el sistema';
            errorSpan.style.display = 'block';
            input.classList.add('error');
        } else {
            errorSpan.style.display = 'none';
            input.classList.remove('error');
        }
    } catch (e) {
        console.error('Error al verificar email:', e);
    }
}

function validarDocumento(input) {
    const form = input.closest('form');
    let tipoDoc = '';
    if (form) {
        const select = form.querySelector('select[name="tipo_documento"]');
        if (select) tipoDoc = select.value;
    }

    let value = input.value;
    const errorSpan = input.closest('.input-group') ? input.closest('.input-group').querySelector('.error-message') : null;
    let errorMessage = '';

    if (tipoDoc === 'PP') {
        value = value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        value = value.substring(0, 15);
        if (value.length > 0 && value.length < 6) errorMessage = 'El pasaporte debe tener al menos 6 caracteres';
    } else {
        value = value.replace(/[^0-9]/g, '');
        if (tipoDoc === 'CC') {
            value = value.substring(0, 10);
            if (value.length > 0 && value.length < 5) errorMessage = 'Cédula inválida (muy corta)';
            else if (value.length === 9) errorMessage = 'Las cédulas en Colombia no tienen 9 dígitos';
        } else if (tipoDoc === 'TI') {
            value = value.substring(0, 11);
            if (value.length > 0 && value.length < 10) errorMessage = 'La TI debe tener 10 u 11 dígitos';
        } else if (tipoDoc === 'CE') {
            value = value.substring(0, 7);
            if (value.length > 0 && value.length < 6) errorMessage = 'La CE debe tener al menos 6 dígitos';
        } else {
            value = value.substring(0, 20);
            if (value.length > 0 && value.length < 5) errorMessage = 'El documento debe tener al menos 5 dígitos';
        }
    }

    input.value = value;

    if (!errorSpan) return;

    if (errorMessage) {
        errorSpan.textContent = errorMessage;
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        input.classList.remove('error');
    }

    if (value.length >= 5) {
        verificarDocumentoExiste(input);
    }
}

function validarNombre(input) {
    const value = input.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
    input.value = value;

    const errorSpan = input.closest('.input-group') ? input.closest('.input-group').querySelector('.error-message') : null;
    if (!errorSpan) return;

    if (value.length > 0 && value.length < 3) {
        errorSpan.textContent = 'El nombre debe tener al menos 3 caracteres';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else if (value.length > 100) {
        errorSpan.textContent = 'El nombre no puede tener más de 100 caracteres';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        input.classList.remove('error');
    }
}

function validarEmail(input) {
    const value = input.value;

    const errorSpan = input.closest('.input-group') ? input.closest('.input-group').querySelector('.error-message') : null;
    if (!errorSpan) return;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,4}$/;

    if (value.length > 0 && !emailRegex.test(value)) {
        errorSpan.textContent = 'Ingrese un correo electrónico válido (ej. usuario@dominio.com)';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else if (value.length > 100) {
        errorSpan.textContent = 'El correo no puede tener más de 100 caracteres';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        input.classList.remove('error');
    }

    if (emailRegex.test(value) && value.length > 0) {
        verificarEmailExiste(input);
    }
}

// Search owners directory locally using real-time search
function filterOwners() {
    const term = document.getElementById('ownerSearch').value.toLowerCase();

    // Filter grid cards
    const cards = document.querySelectorAll('#ownerGridView .client-card');
    cards.forEach(card => {
        const txt = card.innerText.toLowerCase();
        card.style.display = txt.includes(term) ? '' : 'none';
    });

    // Filter table rows
    const rows = document.querySelectorAll('#ownersTable tbody tr');
    rows.forEach(row => {
        const txt = row.innerText.toLowerCase();
        row.style.display = txt.includes(term) ? '' : 'none';
    });
}
