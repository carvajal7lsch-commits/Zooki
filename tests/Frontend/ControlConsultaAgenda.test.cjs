const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const fuente = fs.readFileSync(require('node:path').join(__dirname, '../../public/js/calendario.js'), 'utf8');

// RN-431: la excepción abre el formulario; jamás reserva ni inventa disponibilidad.
test('Agendar control sin horario abre prellenado; Nueva cita ordinaria conserva la guarda', async () => {
    const valores = {};
    const abiertos = [];
    const avisos = [];
    const elementos = new Map();
    const doc = { getElementById(id) { if (!elementos.has(id)) elementos.set(id, { hidden: false, textContent: '', focus() {} }); return elementos.get(id); } };
    const entorno = { document: doc, URLSearchParams, Date, Promise, setTimeout() {},
        window: { location: { search: '?action=vet_agenda&control_mascota=7&control_veterinario=2&control_fecha=2030-01-08', pathname: '/index.php', hash: '' }, history: { replaceState() {} } },
        contextoDeAgenda: () => ({ franjas: [] }), mostrarToast: m => avisos.push(m), isUsuarioVeterinario: () => true,
        triageModalC4: { reiniciar() {}, ponerControl() { valores.preventivo = true; } }, comandosC3: { registrar_cita_ajax: { reiniciar() {} } },
        diaNoReservable: () => true, inicioDelDia: d => d, toLocalDateStr: () => '2030-01-08', getUsuarioId: () => '2',
        val(id,v) { if (v !== undefined) valores[id] = v; return valores[id]; }, capitalizar: x => x, formatFecha: () => '',
        limpiarMascotaSeleccionada() {}, ocultarErrorModal() {}, setSlotsEstado() {}, textoGuiaSlots: () => '', mostrarPasoCita() {},
        abrirOverlay: id => abiertos.push(id), obtenerVeterinarios: async () => [], llenarSelect() {}, FilterManager: { getVeterinario: () => '' },
        obtenerTipos: async () => [{ value: '1', data: { nombre: 'Consulta general' } }, { value: '9', data: { nombre: 'Control' } }],
        obtenerMascotas: async () => [{ id: 7, nombre: 'Luna', propietario: 'Dueño' }],
        seleccionarMascota: id => { valores.mascota = id; }, actualizarResumenModal() {}, mostrarErrorModal: m => avisos.push(m),
    };
    vm.createContext(entorno);
    const abrir = fuente.slice(fuente.indexOf('function abrirCitaModal('), fuente.indexOf('function closeCitaModal('));
    const enlace = fuente.slice(fuente.indexOf('async function abrirAgendarDesdeEnlace('), fuente.indexOf('function marcarHoraPreferida('));
    vm.runInContext(abrir + '\nlet _horaPreferidaCita = null;\n' + enlace, entorno);
    entorno.abrirCitaModal(new Date('2030-01-08'));
    assert.equal(abiertos.length, 0);
    await entorno.abrirAgendarDesdeEnlace();
    assert.deepEqual(abiertos, ['citaModalOverlay']);
    assert.equal(valores.mascota, 7);
    assert.equal(valores.modal_tipo_cita, '9');
    assert.equal(valores.preventivo, true);
    assert.match(avisos.at(-1), /reserva necesita disponibilidad/);
    entorno.window.location.search = '?control_mascota=7&control_veterinario=4&control_fecha=2030-01-08';
    await entorno.abrirAgendarDesdeEnlace();
    assert.equal(abiertos.length, 1, 'El enlace no asigna otro veterinario');
});
