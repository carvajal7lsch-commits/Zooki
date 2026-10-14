const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { crearDocumento } = require('./apoyo/dom-minimo.cjs');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

// M5 C3 — Lado del personal: confirmación corta, búsqueda del propietario en
// tiempo real, tarjetas de vincular sin recargar, solo lectura en la historia
// y Agendar del portal en un solo formulario con su confirmación corta.

const { nodo, todos, botones, modulo } = require('./apoyo/propietario-mascota.cjs');

const FABIO = {
    vinculado: true,
    pendiente: false,
    id_usuario: 6,
    nombre_completo: 'Fabio Dueño',
    mascotas: [{ id_mascota: 1, nombre: 'Luna', nombre_especie: 'Canino', nombre_raza: 'Criollo', raza_indicada: null, url_foto: null }],
};

test('la búsqueda espera un documento o correo completo y busca sola tras una espera corta', async () => {
    const m = modulo({ respuestas: [{ success: true, propietario: null }] });

    assert.equal(m.api.identificadorCompleto('1000'), false);
    assert.equal(m.api.identificadorCompleto('1000000006'), true);
    assert.equal(m.api.identificadorCompleto('fabio@zooki'), false);
    assert.equal(m.api.identificadorCompleto('fabio@zooki.test'), true);

    m.escribir('10000');
    m.escribir('100000');
    m.escribir('1000000');
    assert.equal(m.pedidos.length, 0, 'No busca en cada tecla.');
    await m.correr();
    assert.equal(m.pedidos.length, 1, 'Una sola búsqueda, la del último valor.');
    assert.match(m.pedidos[0].url, /buscar_propietario_exacto_ajax&identificador=1000000$/);
    assert.ok(m.temporizadores.length === 0);
    assert.match(m.ids.ownerSearchAyuda.textContent, /No hay una cuenta con ese dato/);

    m.escribir('fabio@');
    await m.correr();
    assert.equal(m.pedidos.length, 1, 'Con un correo incompleto no busca.');
    assert.match(m.ids.ownerSearchAyuda.textContent, /documento o el correo completo/);
});

test('sin vínculo sale el aviso del servidor y «Pedir confirmación», sin nombre ni mascotas', async () => {
    const aviso = 'Hay una cuenta con ese dato. Pide la confirmación por correo.';
    const m = modulo({ respuestas: [{ success: true, propietario: { vinculado: false, mensaje: aviso } }, { success: true, message: 'Se envió la solicitud.' }] });

    m.escribir('1000000006');
    await m.correr();

    assert.equal(m.ids.ownerSearchAyuda.textContent, aviso);
    const panel = m.ids.mascotasExistentes;
    assert.doesNotMatch(panel.texto, /undefined|Vincular|Fabio/);
    const [pedir] = botones(panel);
    assert.equal(pedir.textContent, 'Pedir confirmación por correo');
    await m.clic(pedir);
    assert.match(m.pedidos[1].url, /solicitar_vinculo_propietario_ajax/);
    assert.equal(m.pedidos[1].opciones.body.datos.identificador, '1000000006');
    assert.deepEqual(m.avisos, [['Se envió la solicitud.', 'success']]);
});

test('el límite por IP se avisa en la línea de ayuda, sin ventana', async () => {
    const m = modulo({ respuestas: [{ success: false, message: 'Espera un momento antes de seguir buscando.' }] });
    m.escribir('fabio@zooki.test');
    await m.correr();
    assert.equal(m.ids.ownerSearchAyuda.textContent, 'Espera un momento antes de seguir buscando.');
    assert.ok(m.ids.ownerSearchAyuda.classList.contains('cuenta-validacion--error'));
});

test('con vínculo: el cliente y sus mascotas en las tarjetas mini de Usuarios', async () => {
    const m = modulo({ respuestas: [{ success: true, propietario: FABIO }] });
    m.escribir('1000000006');
    await m.correr();

    const tarjetas = todos(m.ids.mascotasExistentes).filter(h => h.tagName === 'ARTICLE');
    assert.equal(tarjetas.length, 2, 'El cliente y Luna.');
    for (const tarjeta of tarjetas) {
        assert.ok(tarjeta.classList.contains('person-card'));
        assert.ok(todos(tarjeta).some(h => h.classList.contains('card-body-mini')));
    }
    assert.match(m.ids.mascotasExistentes.texto, /Fabio Dueño/);
    assert.match(m.ids.mascotasExistentes.texto, /Luna/);
    assert.match(m.ids.mascotasExistentes.texto, /Canino · Criollo/);
    assert.deepEqual(botones(m.ids.mascotasExistentes).map(b => b.textContent), ['Vincular a la clínica', 'Es otra mascota: registrar nueva']);
});

test('vincular se pone al día en su sitio con un aviso breve, sin recargar', async () => {
    const m = modulo({ respuestas: [{ success: true, propietario: FABIO }, { success: true }] });
    m.escribir('1000000006');
    await m.correr();
    const [vincular] = botones(m.ids.mascotasExistentes);

    await m.clic(vincular);

    assert.match(m.pedidos[1].url, /vincular_mascota_ajax/);
    assert.deepEqual(m.pedidos[1].opciones.body.datos, { id_propietario: 6, id_mascota: '1' });
    assert.deepEqual(m.avisos, [['Luna quedó vinculada a la clínica.', 'success']]);
    assert.match(m.ids.mascotasExistentes.texto, /Vinculada a la clínica/);
    assert.equal(botones(m.ids.mascotasExistentes).length, 1, 'El botón de Luna cambió por la marca.');
    assert.equal(m.recargas(), 0);
    assert.ok(m.pedidos.some(p => p.url === 'index.php?action=vet_pacientes'), 'Pide otra vez la lista de pacientes para ponerla al día.');
});

test('elegir al propietario y quitarlo usan hidden, nunca style.display; ya elegido, un solo bloque (M5 C4b)', async () => {
    const m = modulo({ respuestas: [{ success: true, propietario: { ...FABIO, mascotas: [], pendiente: true } }] });
    m.escribir('fabio@zooki.test');
    await m.correr();

    assert.equal(m.ids.petOwnerDoc.value, 6, 'Sin mascotas queda elegido para registrar la primera.');
    assert.equal(m.ids.ownerSearchWrapper.hidden, true);
    assert.equal(m.ids.selectedOwnerInfo.hidden, false);
    // M5 C4b (captura 07): la tarjeta del elegido es lo único; no queda otra en el panel de la búsqueda.
    assert.equal(m.ids.mascotasExistentes.hijos.length, 0);
    assert.match(m.ids.selectedOwnerInfo.texto, /Fabio Dueño/);
    assert.match(m.ids.selectedOwnerInfo.texto, /Aún no activa su cuenta/);
    const acciones = botones(m.ids.selectedOwnerInfo);
    assert.deepEqual(acciones.map(b => b.getAttribute('aria-label')), ['Reenviar el enlace de activación', 'Cambiar de propietario']);
    assert.equal(acciones[0].dataset.reenviarActivacion, '6');
    assert.equal(acciones[1].dataset.quitarPropietario, '');

    await m.clic(acciones[1]);
    assert.equal(m.ids.petOwnerDoc.value, '');
    assert.equal(m.ids.ownerSearchWrapper.hidden, false);
    assert.equal(m.ids.selectedOwnerInfo.hidden, true);
    assert.equal(m.ids.selectedOwnerInfo.hijos.length, 0);

    const js = codigo('public/js/propietario-mascota.js');
    assert.doesNotMatch(js, /\.style\./);
    assert.doesNotMatch(js, /location\.reload|zookiRecargarConAviso/);
    assert.doesNotMatch(codigo('public/js/medical-module.js'), /searchOwnersForPet|function selectOwner|clearOwnerSelection/);
    assert.match(codigo('views/vet/pacientes.php'), /<div id="selectedOwnerInfo" class="propietario-elegido" aria-live="polite" hidden><\/div>/);
    assert.doesNotMatch(codigo('views/vet/pacientes.php'), /selectedOwnerName/);
});

test('un documento o correo con cuenta, al registrar, muestra el aviso y «Pedir confirmación» en el formulario', () => {
    const m = modulo();
    const contenedor = nodo('div');
    contenedor.hidden = true;

    m.api.mostrarExistente(contenedor, { vinculado: false, identificador: 'fabio@zooki.test', message: 'Hay una cuenta con ese dato. Pide la confirmación por correo.' });
    assert.equal(contenedor.hidden, false);
    assert.match(contenedor.texto, /Hay una cuenta con ese dato/);
    assert.equal(botones(contenedor)[0].dataset.pedirConfirmacion, 'fabio@zooki.test');

    m.api.mostrarExistente(contenedor, { vinculado: true, identificador: '1000000006', message: 'Ya es cliente de esta clínica.' });
    assert.equal(botones(contenedor).length, 0, 'Si ya es cliente, no se pide nada.');
    assert.doesNotMatch(codigo('views/vet/pacientes.php'), /titular_presente|acepta_politica/);
});

/**
 * avisos.js con un DOM mínimo. M5 C4a (decisión del usuario, 2026-10-13): la
 * confirmación corta es el modal de Zooki, sin SweetAlert; si se llamara a
 * Swal.fire, la prueba lo vería en «llamadas».
 */
function avisos() {
    const llamadas = [];
    const document = crearDocumento();
    const contexto = { console, window: {}, document };
    contexto.Swal = { fire: async (opciones) => { llamadas.push(opciones); return { isConfirmed: true }; } };
    contexto.window.Swal = contexto.Swal;
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/avisos.js'), contexto);
    return { contexto, llamadas, document };
}

test('desactivar un cliente pide solo «¿Desactivar a <nombre> como cliente?» con el estilo de Zooki', async () => {
    const { contexto, llamadas, document } = avisos();

    const promesa = contexto.zookiConfirmarCorto('¿Desactivar a Fabio Dueño como cliente?', 'Desactivar');
    const fondo = document.body.hijos[0];
    assert.equal(fondo.className, 'zooki-confirmacion');
    assert.equal(fondo.buscar('zooki-confirmacion__titulo').textContent, '¿Desactivar a Fabio Dueño como cliente?');
    assert.equal(fondo.buscar('zooki-confirmacion__filas'), null, 'Sin filas ni explicación.');
    assert.equal(fondo.buscar('btn-modal-secondary').textContent, 'Cancelar');
    fondo.buscar('btn-modal-primary').click();

    assert.equal(await promesa, true);
    assert.equal(llamadas.length, 0, 'Sin SweetAlert.');
    const usuarios = codigo('public/js/usuarios.js');
    assert.match(usuarios, /zookiConfirmarCorto\('¿Desactivar a ' \+ nombre \+ ' como cliente\?', 'Desactivar'\)/);
    assert.doesNotMatch(usuarios, /sus otras clínicas no cambian/, 'Ya no insinúa que tiene otras clínicas.');
});

test('la confirmación corta de Agendar lleva clínica, mascota, día y hora, como texto', async () => {
    const { contexto, document } = avisos();

    const promesa = contexto.zookiConfirmarCorto('¿Agendar esta cita?', 'Agendar', [['Clínica', 'Norte <b>'], ['Mascota', 'Luna'], ['Día', 'lunes 7 de enero'], ['Hora', '9:00 a. m.']]);
    const fondo = document.body.hijos[0];
    const etiquetas = fondo.porEtiqueta('dt').map(n => n.textContent);
    const valores = fondo.porEtiqueta('dd').map(n => n.textContent);
    fondo.buscar('btn-modal-secondary').click();

    assert.equal(await promesa, false);
    assert.deepEqual(etiquetas, ['Clínica', 'Mascota', 'Día', 'Hora']);
    assert.equal(valores[0], 'Norte <b>', 'Va con textContent: no se interpreta como HTML.');
});

test('Agendar es un solo formulario: una línea dice qué falta y «Agendar» abre la confirmación corta', () => {
    const contexto = { console, document: { addEventListener() {} }, window: { addEventListener() {} } };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/portal.js'), contexto);

    const vacio = { clinica: '', mascota: '', tipo: '', veterinario: '', dia: '', hora: '' };
    assert.deepEqual([...contexto.faltanParaAgendar(vacio)], ['la clínica', 'la mascota', 'el tipo de cita', 'el veterinario', 'el día', 'la hora']);
    const casi = { clinica: '1', mascota: '1', tipo: '1', veterinario: '2', dia: '2030-01-07', hora: '' };
    assert.equal(contexto.guiaParaAgendar(contexto.faltanParaAgendar(casi)), 'Elige la hora.');
    assert.equal(contexto.guiaParaAgendar(contexto.faltanParaAgendar({ ...casi, mascota: '' })), 'Elige la mascota y la hora.');
    assert.equal(contexto.guiaParaAgendar(contexto.faltanParaAgendar({ ...casi, hora: '09:00' })), '');

    const js = codigo('public/js/portal.js');
    assert.doesNotMatch(js, /irAPaso|faltanDelPaso|data-paso/);
    assert.match(js, /zookiConfirmarCorto\('¿Agendar esta cita\?', 'Agendar', filasDeLaCita\(\)\)/);
    const filas = js.slice(js.indexOf('function filasDeLaCita'), js.indexOf('/** Tipos de cita y veterinarios'));
    for (const etiqueta of ['Clínica', 'Mascota', 'Día', 'Hora']) assert.match(filas, new RegExp(`\\['${etiqueta}'`));
});

/** medical-module.js con un DOM mínimo. */
function historia() {
    const contexto = {
        console,
        window: {},
        document: {
            addEventListener() {},
            getElementById: () => null,
            createElement: (etiqueta) => nodo(etiqueta),
        },
    };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/medical-module.js'), contexto);
    return contexto;
}

test('hueco 6: una mascota desvinculada dice «solo lectura» y no ofrece registrar nada', () => {
    const contexto = historia();
    const panel = nodo('div');

    assert.equal(contexto.avisoSoloLectura(panel, { solo_lectura: true }), true);
    assert.equal(panel.hijos[0].textContent, 'El propietario se desvinculó: solo lectura');
    assert.equal(contexto.avisoSoloLectura(panel, { solo_lectura: true }), true);
    assert.equal(panel.hijos.length, 1, 'No se repite al volver a abrirla.');
    assert.equal(contexto.avisoSoloLectura(panel, { solo_lectura: false }), false);
    assert.equal(panel.hijos.length, 0);

    const sinHistoria = { innerHTML: '', querySelector: () => null };
    contexto.document.getElementById = (id) => (id === 'modalConsulta' ? {} : null);
    contexto.mostrarSinHistoria(sinHistoria, 1, 'Luna', null, true);
    assert.doesNotMatch(sinHistoria.innerHTML, /Registrar primera consulta/);
    contexto.mostrarSinHistoria(sinHistoria, 1, 'Luna', null, false);
    assert.match(sinHistoria.innerHTML, /Registrar primera consulta/, 'Con el vínculo activo sí se ofrece.');
});

test('lo registrado por otra clínica lleva su clínica y «solo lectura» a la vista, no solo en el title', () => {
    const contexto = historia();
    const etiqueta = contexto.etiquetaOrigen({ es_propia: false, clinica_nombre: 'Clínica Sur' });

    assert.match(etiqueta, /Clínica Sur · solo lectura<\/span>$/);
    assert.doesNotMatch(etiqueta, /title=/);
    assert.equal(contexto.etiquetaOrigen({ es_propia: true, clinica_nombre: 'Clínica Norte' }), '');
});

test('«Vincularme» deshabilitado al tope se repinta con la sección y el aviso del rechazo sale en la tarjeta', () => {
    const js = codigo('public/js/portal.js');
    assert.match(js, /'misClinicas'/, 'Clínicas se repinta en su sitio, con el botón ya deshabilitado.');
    const vista = codigo('views/portal/index.php');
    assert.match(vista, /data-vincular-clinica="<\?= \(int\) \$clinica\['id_clinica'\] \?>" data-nombre="<\?= \$e\(\$clinica\['nombre'\]\) \?>"<\?= \$al_tope_clinicas \? ' disabled' : '' \?>>/);
    assert.match(vista, /data-tope-clinicas/);
});
