'use strict';

/** Un cambio confirmado actualiza calendario y paneles; la sincronización periódica es respaldo. */
function actualizarAgendaTrasCambio(detalle = {}) {
    window.dispatchEvent(new CustomEvent('agenda:actualizada', { detail: detalle }));
}

// HU-4.13/14/15/19: refrescar sin sustituir formularios que el personal está editando.
function panelAgendaEnEdicion(raiz) {
    return Boolean(raiz.querySelector('input:focus, select:focus, textarea:focus, details[open], form[data-agenda-editando]')) || Boolean(raiz.ownerDocument.querySelector('dialog[open]'));
}

function sincronizarPanelAgenda(raiz, cargar, ocupado = () => false) {
    const documento = raiz.ownerDocument;
    const ventana = documento.defaultView;
    let consultando = false;
    async function actualizar() {
        if (documento.hidden || consultando || ocupado() || panelAgendaEnEdicion(raiz)) return;
        consultando = true;
        try {
            await cargar(true);
        } finally {
            consultando = false;
        }
    }
    for (const nombre of ['input', 'change']) {
        raiz.addEventListener(nombre, evento => {
            const form = evento.target.closest('form');
            if (form) form.dataset.agendaEditando = '1';
        });
    }
    raiz.addEventListener('agenda:mostrar', actualizar);
    ventana.addEventListener('agenda:urgencia', actualizar);
    ventana.addEventListener('agenda:actualizada', actualizar);
    documento.addEventListener('visibilitychange', actualizar);
    const temporizador = ventana.setInterval(actualizar, 10000);
    ventana.addEventListener('pagehide', () => ventana.clearInterval(temporizador), { once: true });
    actualizar();
    return actualizar;
}

// D: pestañas accesibles de las herramientas de agenda. Al mostrar un panel se avisa con
// «agenda:mostrar» para que cargue sus datos solo cuando se ve (antes lo hacía el <details>).
function activarPanelAgenda(raiz, clave, enfocar) {
    raiz.querySelectorAll('[data-agenda-tab]').forEach((tab) => {
        const activa = tab.dataset.agendaTab === clave;
        tab.setAttribute('aria-selected', activa ? 'true' : 'false');
        tab.tabIndex = activa ? 0 : -1;
        const panel = raiz.querySelector('#' + tab.getAttribute('aria-controls'));
        if (!panel) return;
        panel.hidden = !activa;
        if (activa) {
            panel.dispatchEvent(new CustomEvent('agenda:mostrar'));
            if (enfocar) tab.focus();
        }
    });
}

function iniciarHerramientasAgenda(documento) {
    documento.querySelectorAll('[data-agenda-herramientas]').forEach((raiz) => {
        const tabs = Array.from(raiz.querySelectorAll('[data-agenda-tab]'));
        tabs.forEach((tab, indice) => {
            tab.addEventListener('click', () => activarPanelAgenda(raiz, tab.dataset.agendaTab, false));
            tab.addEventListener('keydown', (evento) => {
                const paso = evento.key === 'ArrowRight' ? 1 : evento.key === 'ArrowLeft' ? -1 : 0;
                if (!paso) return;
                evento.preventDefault();
                const siguiente = tabs[(indice + paso + tabs.length) % tabs.length];
                activarPanelAgenda(raiz, siguiente.dataset.agendaTab, true);
            });
        });
        // HU-6.1: los avisos enlazan a Pendientes por su hash (#triage, #reasignaciones).
        const pedida = tabs.find((tab) => '#' + tab.dataset.agendaTab === (documento.defaultView?.location?.hash || ''));
        const inicial = pedida || tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') || tabs[0];
        if (inicial) activarPanelAgenda(raiz, inicial.dataset.agendaTab, false);
        if (pedida && typeof raiz.scrollIntoView === 'function') raiz.scrollIntoView({ block: 'start' });
    });
}

if (typeof document !== 'undefined' && document.addEventListener) {
    document.addEventListener('DOMContentLoaded', () => iniciarHerramientasAgenda(document));
}
if (typeof module !== 'undefined') {
    module.exports = { activarPanelAgenda, iniciarHerramientasAgenda, sincronizarPanelAgenda, panelAgendaEnEdicion };
}
