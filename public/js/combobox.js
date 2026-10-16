/**
 * M2 C8 (H6) — Navegación por teclado de los buscadores con lista: síntomas y
 * fármacos de la consulta, y nodos del conocimiento en «Registrar alergia».
 *
 * Patrón combobox de ARIA: el campo conserva el foco y señala la opción activa
 * con aria-activedescendant. Flecha abajo o arriba recorre las opciones
 * (role="option") de la lista abierta; Enter elige la activa con su mismo clic,
 * y nunca envía el formulario; Esc cierra la lista sin cerrar la ventana que la
 * contiene. El clic sigue funcionando igual.
 */
(function (global) {
    'use strict';

    let contador = 0;

    function nuevoId(prefijo) {
        contador += 1;
        return `${prefijo}-${contador}`;
    }

    /** Las opciones que se pueden elegir de la lista, en su orden. */
    function opcionesDe(lista) {
        return [...lista.querySelectorAll('[role="option"]')].filter(opcion => !opcion.disabled);
    }

    function activaDe(lista) {
        return opcionesDe(lista).find(opcion => opcion.getAttribute('aria-selected') === 'true') || null;
    }

    /** Marca una opción como activa (o ninguna, con null) y la deja a la vista. */
    function marcar(campo, lista, opcion) {
        opcionesDe(lista).forEach(actual => {
            const esta = actual === opcion;
            actual.setAttribute('aria-selected', esta ? 'true' : 'false');
            actual.classList.toggle('is-activa', esta);
        });
        if (!opcion) {
            campo.setAttribute('aria-activedescendant', '');
            return;
        }
        if (!opcion.id) opcion.id = nuevoId('zooki-opcion');
        campo.setAttribute('aria-activedescendant', opcion.id);
        if (typeof opcion.scrollIntoView === 'function') opcion.scrollIntoView({ block: 'nearest' });
    }

    /** Al repintar o cerrar la lista, ninguna opción queda activa. */
    function reiniciar(campo) {
        campo.setAttribute('aria-activedescendant', '');
    }

    /**
     * Enlaza un campo con su lista de sugerencias. opciones.cerrar() cierra la
     * lista (la de cada buscador, que también limpia sus opciones).
     */
    function enlazar(campo, lista, opciones = {}) {
        const cerrar = opciones.cerrar || (() => { lista.hidden = true; });
        if (!lista.id) lista.id = nuevoId('zooki-lista');
        campo.setAttribute('role', 'combobox');
        campo.setAttribute('aria-autocomplete', 'list');
        campo.setAttribute('aria-controls', lista.id);
        if (campo.getAttribute('aria-expanded') === null) campo.setAttribute('aria-expanded', 'false');
        lista.setAttribute('role', 'listbox');

        campo.addEventListener('keydown', evento => {
            const abierta = !lista.hidden;
            if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
                const disponibles = abierta ? opcionesDe(lista) : [];
                if (disponibles.length === 0) return;
                evento.preventDefault();
                const indice = disponibles.indexOf(activaDe(lista));
                const paso = evento.key === 'ArrowDown' ? 1 : -1;
                const inicio = paso === 1 ? 0 : disponibles.length - 1;
                const siguiente = indice === -1 ? inicio : (indice + paso + disponibles.length) % disponibles.length;
                marcar(campo, lista, disponibles[siguiente]);
                return;
            }
            if (evento.key === 'Enter') {
                // Enter nunca envía la consulta ni el formulario: elige la opción activa, si la hay.
                evento.preventDefault();
                const activa = abierta ? activaDe(lista) : null;
                if (activa) activa.click();
                return;
            }
            if (evento.key === 'Escape' && abierta) {
                // Solo cierra la lista: la ventana que la contiene sigue abierta.
                evento.preventDefault();
                if (typeof evento.stopPropagation === 'function') evento.stopPropagation();
                cerrar();
                reiniciar(campo);
            }
        });
    }

    global.ZookiCombobox = { enlazar, marcar, reiniciar, opcionesDe };
})(typeof window !== 'undefined' ? window : globalThis);
