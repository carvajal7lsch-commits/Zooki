/**
 * M2 — La misma fila que views/partials/consulta_tratamiento_fila.php, con sus
 * data-*, para el DOM mínimo de dom-datos.cjs. Salió de M2C5bTratamientos para
 * usarla también en M2C8Rediseno (navegación por teclado del buscador de fármacos).
 */
const { crearNodo, colgar } = require('./dom-datos.cjs');

function plantillaFila() {
    const fila = crearNodo('div');
    fila.dataset.tratamiento = '';
    const campo = (nombre, etiqueta, extra = {}) => {
        const nodo = colgar(fila, etiqueta, { tratamientoCampo: nombre }, extra);
        colgar(fila, 'small', { tratamientoAviso: nombre });
        return nodo;
    };
    campo('medicamento', 'input', { name: 'med_nombre[]', type: 'text' });
    colgar(fila, 'ul', { farmacoSugerencias: '' }, { hidden: true });
    colgar(fila, 'input', { tratamientoCampo: 'farmaco' }, { name: 'med_farmaco[]', type: 'hidden' });
    colgar(fila, 'small', { farmacoEstado: '' });
    campo('dosis', 'input', { name: 'med_dosis[]', type: 'text' });
    campo('via_administracion', 'select', { name: 'med_via[]' });
    campo('duracion', 'input', { name: 'med_duracion[]', type: 'text' });
    campo('fecha_inicio', 'input', { name: 'med_inicio[]', type: 'date' });
    campo('fecha_fin', 'input', { name: 'med_fin[]', type: 'date' });
    colgar(fila, 'small', { finAyuda: '' });
    colgar(fila, 'input', { tratamientoCampo: 'observaciones' }, { name: 'med_obs[]', type: 'text' });
    colgar(fila, 'button', { quitarTratamiento: '' }, { type: 'button' });
    colgar(fila, 'p', { prescripcionEstado: '' });
    colgar(fila, 'ul', { prescripcionLista: '' }, { hidden: true });
    const confirmar = colgar(fila, 'label', { prescripcionConfirmar: '' }, { hidden: true });
    colgar(confirmar, 'input', { prescripcionCasilla: '' }, { type: 'checkbox' });
    colgar(fila, 'small', { tratamientoAviso: 'decision' });
    const conciliacion = colgar(fila, 'div', { prescripcionConciliacion: '' }, { hidden: true });
    const texto = colgar(conciliacion, 'textarea', { tratamientoCampo: 'conciliacion' }, { name: 'med_conciliacion[]' });
    texto.setAttribute('maxlength', '500');
    colgar(conciliacion, 'small', { conciliacionContador: '' });
    colgar(conciliacion, 'small', { tratamientoAviso: 'conciliacion' });
    colgar(fila, 'input', { tratamientoCampo: 'decision' }, { name: 'med_decision[]', type: 'hidden' });
    colgar(fila, 'input', { tratamientoCampo: 'huella' }, { name: 'med_huella[]', type: 'hidden' });
    colgar(fila, 'small', { tratamientoAviso: 'fila' });
    return fila;
}

module.exports = { plantillaFila };
