/** RE-4.19.1/3: presencia y tolerancia, sin mezclar otra fecha, veterinario o rol. */
const test = require('node:test');
const assert = require('node:assert/strict');
const { accionesLlegada } = require('../../public/js/llegadas-cita.js');

test('Citas: llegada, ausencia y ajuste respetan presencia y límite inclusivo de tolerancia', () => {
    const cita = { id_veterinario: 2, fecha: '2030-01-07', hora: '09:00:00', estado: 'confirmada', tolerancia_llegada_min: 10, hora_llegada: null };
    const contexto = { esVeterinario: true, idVeterinario: 2, hoy: cita.fecha, ahora: new Date('2030-01-07T09:10:00') };
    assert.deepEqual(accionesLlegada(cita, contexto), ['registrar']);
    contexto.ahora = new Date('2030-01-07T09:10:01');
    assert.deepEqual(accionesLlegada(cita, contexto), ['registrar', 'no_asistio']);
    cita.hora_llegada = '2030-01-07 09:10:00';
    assert.deepEqual(accionesLlegada(cita, contexto), ['ajustar']);
    cita.hora_llegada = '2030-01-07 09:10:01';
    assert.deepEqual(accionesLlegada(cita, contexto), ['resolver', 'ajustar']);
    for (const cambio of [{ esVeterinario: false }, { idVeterinario: 3 }, { hoy: '2030-01-08' }]) {
        assert.deepEqual(accionesLlegada(cita, { ...contexto, ...cambio }), []);
    }
    for (const estado of ['completada', 'sin_cerrar', 'en_curso', 'cancelada', 'no_asistio']) {
        assert.deepEqual(accionesLlegada({ ...cita, estado }, contexto), []);
    }
});
