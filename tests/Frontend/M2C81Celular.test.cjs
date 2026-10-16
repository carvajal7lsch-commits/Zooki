/** C8.1: controles reales de la cabecera y contenido seguro de las filas plegables. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { crearDocumento, colgar } = require('./apoyo/dom-datos.cjs');
const leer = archivo => fs.readFileSync(path.join(__dirname, '../..', archivo), 'utf8');

function cabecera() {
    const documento = crearDocumento();
    const raiz = colgar(documento.raiz, 'aside', { pacienteCompacto: '' });
    const alertas = colgar(raiz, 'button', { pacienteAlertas: '' });
    const ficha = colgar(raiz, 'button', { pacienteFicha: '' });
    alertas.setAttribute('aria-expanded', 'false');
    ficha.setAttribute('aria-expanded', 'false');
    const lista = colgar(raiz, 'section', { alertasMedicas: '' });
    const contexto = { window: { document: documento }, document: documento };
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/paciente-compacto.js'), contexto);
    documento.disparar('DOMContentLoaded');
    return { raiz, alertas, ficha, lista, contexto, api: contexto.window.ZookiPacienteCompacto };
}

test('H10: el número cuenta todas las alertas y el rojo se reserva para una alergia', () => {
    const c = cabecera();
    c.api.actualizarAlertas(c.lista, [{ tipo: 'alergia' }, { tipo: 'condicion_cronica' }, { tipo: 'medicacion_continua' }]);
    assert.equal(c.alertas.textContent, '3 alertas');
    assert.ok(c.alertas.classList.contains('paciente-compacto__alertas--alergia'));
    c.api.actualizarAlertas(c.lista, [{ tipo: 'condicion_cronica' }]);
    assert.equal(c.alertas.textContent, '1 alerta');
    assert.equal(c.alertas.classList.contains('paciente-compacto__alertas--alergia'), false);
    c.api.actualizarAlertas(c.lista, []);
    assert.equal(c.alertas.textContent, '0 alertas');
    c.api.actualizarAlertas(c.lista, null);
    assert.equal(c.alertas.textContent, 'Alertas sin cargar', 'Un fallo de carga no equivale a cero alertas');
});

test('H10: abrir y cerrar alertas y Ver ficha son independientes; reabrir el modal los pliega', () => {
    const c = cabecera();
    c.alertas.disparar('click');
    assert.equal(c.alertas.getAttribute('aria-expanded'), 'true');
    assert.ok(c.raiz.classList.contains('is-alertas-abierta'));
    c.ficha.disparar('click');
    assert.ok(c.raiz.classList.contains('is-ficha-abierta'));
    c.alertas.disparar('click');
    assert.equal(c.raiz.classList.contains('is-alertas-abierta'), false);
    assert.ok(c.raiz.classList.contains('is-ficha-abierta'));
    c.api.plegar(c.raiz);
    assert.equal(c.ficha.getAttribute('aria-expanded'), 'false');
    assert.equal(c.raiz.classList.contains('is-ficha-abierta'), false);
    c.api.actualizarAlertas(c.lista, [{ tipo: 'alergia' }]);
    c.api.preparar(c.raiz);
    assert.equal(c.alertas.textContent, 'Cargando alertas', 'Otra mascota no conserva el conteo anterior');
    assert.equal(c.alertas.getAttribute('aria-label'), 'Cargando alertas');
    assert.equal(c.alertas.classList.contains('paciente-compacto__alertas--alergia'), false);
});

test('H10: pintar la lista real actualiza también el indicador de la cabecera', () => {
    const c = cabecera();
    vm.runInContext(leer('public/js/alertas-medicas.js'), c.contexto);
    c.contexto.window.ZookiAlertas.pintar(c.lista, { activas: [{ tipo: 'alergia', descripcion: 'Reacción registrada' }], historial: [] }, { idMascota: 7, lectura: true });
    assert.equal(c.alertas.textContent, '1 alerta');
    assert.ok(c.alertas.classList.contains('paciente-compacto__alertas--alergia'));
    c.contexto.window.ZookiAlertas.pintar(c.lista, { activas: [], historial: [] }, { idMascota: 7, lectura: true });
    assert.equal(c.alertas.textContent, '0 alertas');
    assert.equal(c.alertas.classList.contains('paciente-compacto__alertas--alergia'), false);
});

test('H11: fila plegada con clínica, motivo, diagnóstico y chips; detalle conserva signos, síntomas, tratamiento y adjunto', () => {
    const documento = crearDocumento();
    const contexto = { document: documento, window: {}, console };
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/medical-module.js'), contexto);
    const consulta = { fecha_hora: '2026-10-01 10:00:00', clinica_nombre: '<Sur>', motivo_consulta: 'Control', diagnostico: 'Gastritis', peso: null, id_cita: null, frecuencia_respiratoria: 24, sintomas: [{ etiqueta: '<Vómito>' }], tratamientos: [{ medicamento: 'Medicamento de prueba', dosis: 'Registrada', duracion: 'Registrada', via_administracion: 'Oral' }], archivos: [{ id_archivo: 3, nombre_original: '<informe>.pdf', extension: 'pdf' }] };
    const html = contexto.filaHistorialCompacto(consulta, 'consulta');
    assert.match(html, /^<details class="historia-fila">/);
    assert.doesNotMatch(html, /<details[^>]* open/);
    const resumen = html.split('</summary>')[0];
    assert.match(resumen, /&lt;Sur&gt;[\s\S]*Control[\s\S]*Gastritis[\s\S]*Sin cita[\s\S]*Sin peso registrado[\s\S]*1 tratamientos/);
    assert.match(html, /F.R.[\s\S]*24 rpm/);
    assert.match(html, /&lt;Vómito&gt;/);
    assert.match(html, /Medicamento de prueba/);
    assert.match(html, /ver_archivo.php\?id=3/);
    assert.doesNotMatch(html, /<informe>|<Sur>|<Vómito>/);
    for (const tipo of ['vacuna', 'desparasitacion']) {
        const fila = contexto.filaHistorialCompacto({ nombre_vacuna: 'Rabia', producto: 'Producto', fecha_aplicacion: '2026-10-01', lote: '<lote>' }, tipo);
        assert.match(fila, /<details class="historia-fila">/);
        assert.match(fila, /&lt;lote&gt;/);
        assert.match(fila, /1\/10\/2026/, 'La fecha sin hora no retrocede un día por UTC');
    }
    const orden = contexto.eventosHistorialCompacto({
        consultas: [{ fecha_hora: '2026-10-01 10:00:00' }],
        vacunas: [{ fecha_aplicacion: '2026-09-01' }],
        desparasitaciones: [{ fecha_aplicacion: '2026-10-02' }],
    });
    assert.equal(orden.map(evento => evento.tipo).join(','), 'desparasitacion,consulta,vacuna', 'Se ordenan todos los tipos del más reciente al más antiguo');
});

test('H10: pie sobre la barra inferior, pasos sin encoger y estilos nuevos sin colores sueltos', () => {
    const css = leer('public/css/atencion.css');
    const nuevo = css.slice(css.indexOf('/* H10:'));
    assert.match(nuevo, /position: fixed/);
    assert.match(nuevo, /bottom: calc\(84px \+ env\(safe-area-inset-bottom\)\)/);
    assert.match(nuevo, /\.atencion__pasos > li \{ flex: 0 0 auto/);
    for (const archivo of ['public/css/atencion.css', 'public/css/consulta-clinica.css']) {
        const contenido = leer(archivo).replace(/\/\*[\s\S]*?\*\//g, '');
        assert.doesNotMatch(contenido, /#[a-f\d]{3,8}\b|rgba?\(|hsla?\(/i);
        const identidad = leer('public/css/identidad.css');
        for (const variable of contenido.matchAll(/var\((--z-[\w-]+)/g)) assert.ok(identidad.includes(variable[1] + ':'), variable[1]);
    }
    for (const archivo of ['views/vet/atencion.php', 'views/vet/modal_consulta.php', 'public/js/paciente-compacto.js']) assert.doesNotMatch(leer(archivo), /\sstyle=|\sonclick=/);
});
