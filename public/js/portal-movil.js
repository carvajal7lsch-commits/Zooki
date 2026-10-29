/* HU-5.1 / RNF-16: piezas móviles; el ancho mayor conserva su marcado y formularios. */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const movil = window.matchMedia('(max-width: 767px)');
    const telefono = document.getElementById('portalContactEditForm');
    const guardar = document.getElementById('btnSubmitContactEdit');
    const ayuda = document.getElementById('ayudaHistoriaCompartida');
    const posiciones = [];
    function trasladable(nodo, destino) {
        if (!nodo || !destino) return;
        const marca = document.createComment('Posición original del portal');
        nodo.before(marca);
        posiciones.push({ nodo, destino, marca });
    }
    trasladable(telefono, document.querySelector('[data-portal-telefono-destino]'));
    trasladable(guardar, document.querySelector('[data-portal-telefono-pie]'));
    trasladable(ayuda, document.querySelector('[data-portal-ayuda-destino]'));
    function citas() {
        document.querySelectorAll('.portal-cita').forEach(tarjeta => {
            const estado = tarjeta.querySelector('.portal-cita__estado');
            if (!estado) return;
            if (!estado._posicionPortal) {
                estado._posicionPortal = document.createComment('Estado de cita en escritorio');
                estado.before(estado._posicionPortal);
            }
            if (movil.matches) {
                const fila = tarjeta.querySelector('.portal-cita__cuando');
                if (estado.parentElement !== fila) fila.insertBefore(estado, fila.querySelector('span'));
            } else if (estado.parentElement !== tarjeta) estado._posicionPortal.after(estado);
        });
    }
    function distribuir() {
        posiciones.forEach(({ nodo, destino, marca }) => {
            if (movil.matches) destino.append(nodo);
            else marca.after(nodo);
        });
        if (movil.matches) guardar.setAttribute('form', 'portalContactEditForm');
        else guardar.removeAttribute('form');
        if (!movil.matches) {
            ['portalTelefonoModal', 'portalClinicasAyudaModal'].forEach(id => {
                if (document.getElementById(id).classList.contains('is-open')) closeModal(id);
            });
        }
        citas();
    }
    const hojas = ['portalDocumentoModal', 'portalCorreoModal', 'portalTelefonoModal', 'portalPasswordModal', 'portalClinicasAyudaModal'];
    document.addEventListener('click', evento => {
        const abrir = evento.target.closest('[data-portal-hoja]');
        if (abrir && movil.matches && hojas.includes(abrir.dataset.portalHoja)) openModal(abrir.dataset.portalHoja);
        const cerrar = evento.target.closest('#portalTelefonoCerrar, #portalClinicasAyudaCerrar');
        if (cerrar) closeModal(cerrar.closest('.portal-drawer-overlay').id);
    });
    document.addEventListener('portal:telefono-guardado', evento => {
        document.querySelectorAll('[data-portal-telefono]').forEach(valor => { valor.textContent = evento.detail.telefono; });
        if (movil.matches) closeModal('portalTelefonoModal');
    });
    // Las listas del portal se repintan tras guardar; preparar las mismas piezas nuevas.
    document.addEventListener('portal:actualizado', () => {
        citas();
        const nuevaAyuda = document.getElementById('misClinicas').querySelector('#ayudaHistoriaCompartida');
        if (!nuevaAyuda) return;
        const anterior = posiciones.findIndex(pieza => pieza.nodo.id === 'ayudaHistoriaCompartida');
        if (anterior !== -1) posiciones.splice(anterior, 1)[0].nodo.remove();
        trasladable(nuevaAyuda, document.querySelector('[data-portal-ayuda-destino]'));
        distribuir();
    });
    movil.addEventListener('change', distribuir);
    distribuir();
});
