/**
 * M5 C4a — Un DOM mínimo para probar el modal de confirmación de avisos.js
 * (zookiConfirmarCorto) sin navegador: nodos con hijos, atributos, clases,
 * eventos, foco y teclado. No es un DOM completo; solo lo que usa el modal.
 */
function crearDocumento() {
    const escuchas = [];

    function nodo(etiqueta) {
        return {
            tagName: String(etiqueta).toUpperCase(),
            className: '',
            id: '',
            type: '',
            textContent: '',
            hijos: [],
            padre: null,
            atributos: {},
            eventos: {},
            appendChild(hijo) {
                hijo.padre = this;
                this.hijos.push(hijo);
                return hijo;
            },
            remove() {
                if (!this.padre) return;
                this.padre.hijos = this.padre.hijos.filter(h => h !== this);
                this.padre = null;
            },
            setAttribute(nombre, valor) { this.atributos[nombre] = String(valor); },
            getAttribute(nombre) { return nombre in this.atributos ? this.atributos[nombre] : null; },
            addEventListener(tipo, callback) { (this.eventos[tipo] = this.eventos[tipo] || []).push(callback); },
            focus() { documento.activeElement = this; },
            click() { (this.eventos.click || []).forEach(callback => callback({ target: this })); },
            /** Todos los descendientes, en orden. */
            todos() { return this.hijos.flatMap(h => [h, ...h.todos()]); },
            buscar(clase) { return this.todos().find(n => n.className.split(' ').includes(clase)) || null; },
            porEtiqueta(etiqueta) { return this.todos().filter(n => n.tagName === etiqueta.toUpperCase()); },
        };
    }

    const documento = {
        activeElement: null,
        body: nodo('body'),
        createElement: nodo,
        addEventListener(tipo, callback, captura) { escuchas.push({ tipo, callback, captura }); },
        removeEventListener(tipo, callback) {
            const i = escuchas.findIndex(e => e.tipo === tipo && e.callback === callback);
            if (i >= 0) escuchas.splice(i, 1);
        },
        /** Una tecla en el elemento con foco; devuelve el evento para ver si se detuvo. */
        tecla(key) {
            const evento = { key, detenido: false, prevenido: false, stopPropagation() { this.detenido = true; }, preventDefault() { this.prevenido = true; } };
            escuchas.filter(e => e.tipo === 'keydown').forEach(e => e.callback(evento));
            return evento;
        },
        escuchas,
    };
    return documento;
}

module.exports = { crearDocumento };
