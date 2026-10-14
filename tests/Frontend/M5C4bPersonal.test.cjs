const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { nodo, todos, botones, modulo, codigo } = require('./apoyo/propietario-mascota.cjs');

// M5 C4b — Lado de la clínica: tarjetas de Propietarios como las de Usuarios,
// un solo bloque para el propietario elegido, búsqueda por nombre entre los
// clientes de la clínica, sin recargas, sin colores y el modal de Google que
// activa una cuenta pendiente.

/** medical-module.js con un DOM mínimo. */
function pacientes() {
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

const ROSA = { id_usuario: 9, nombre_completo: 'Rosa Nueva', documento: '1000000040', telefono: '3001234567', email: 'rosa@zooki.test', total_mascotas: 1, estado: 1, pendiente_activacion: 1 };

test('Propietarios usa la tarjeta mini de Usuarios, con «Aún no activa su cuenta» como etiqueta y botones de icono', () => {
    const p = pacientes();
    const tarjeta = p.tarjetaDueno(ROSA);

    assert.ok(tarjeta.classList.contains('person-card'));
    for (const parte of ['card-header-mini', 'card-body-mini', 'card-tags-mini', 'card-contact-mini', 'card-footer-mini']) {
        assert.ok(todos(tarjeta).some(h => h.classList.contains(parte)), parte);
    }
    const etiquetas = todos(tarjeta).filter(h => h.classList.contains('tag-mini'));
    assert.ok(etiquetas.some(e => e.classList.contains('tag-mini--aviso') && /Aún no activa su cuenta/.test(e.texto)));
    const acciones = botones(tarjeta);
    assert.ok(acciones.every(b => b.classList.contains('action-btn-mini')));
    assert.deepEqual(acciones.map(b => b.title), ['Editar', 'Reenviar enlace de activación', 'Ver detalle y mascotas']);
    assert.ok(acciones.every(b => b.textContent === ''), 'Iconos, sin texto suelto que se parta (captura 06).');
    assert.equal(acciones[1].dataset.reenviarActivacion, '9', 'propietario-mascota.js atiende el reenvío.');

    const desvinculado = p.tarjetaDueno({ ...ROSA, estado: 0, pendiente_activacion: 0, telefono: null, email: null, documento: null });
    assert.match(desvinculado.texto, /Desvinculado/);
    assert.deepEqual(botones(desvinculado).map(b => b.title), ['Ver detalle y mascotas'], 'RN-115: desvinculado ya no se edita.');
    assert.equal(p.estadoDueno(ROSA), 'Aún no activa su cuenta');

    const fila = p.filaDueno(ROSA);
    assert.equal(fila.tagName, 'TR');
    assert.deepEqual(botones(fila).map(b => b.title), ['Editar', 'Reenviar enlace de activación', 'Ver detalle y mascotas']);
});

test('el propietario se busca también por nombre, solo con tres letras o más', async () => {
    const m = modulo({ respuestas: [{ success: true, clientes: [{ id_usuario: 6, nombre_completo: 'Fabio Dueño', documento: '1000000006', identificador: '1000000006', pendiente: false }] }] });

    assert.equal(m.api.tipoDeBusqueda('Fa'), null);
    assert.equal(m.api.tipoDeBusqueda('Fab'), 'nombre');
    assert.equal(m.api.tipoDeBusqueda('María José'), 'nombre');
    assert.equal(m.api.tipoDeBusqueda('1000000006'), 'exacta');
    assert.equal(m.api.tipoDeBusqueda('fabio@zooki.test'), 'exacta');
    assert.equal(m.api.tipoDeBusqueda('fabio@'), null, 'Un correo a medias no es un nombre.');

    m.escribir('Fa');
    await m.correr();
    assert.equal(m.pedidos.length, 0);
    m.escribir('Fab');
    await m.correr();
    assert.equal(m.pedidos.length, 1);
    assert.match(m.pedidos[0].url, /buscar_propietario_nombre_ajax&nombre=Fab$/);
    const [resultado] = botones(m.ids.mascotasExistentes);
    assert.ok(resultado.classList.contains('propietario-resultado'));
    assert.match(resultado.texto, /Fabio Dueño/);
    assert.equal(resultado.dataset.identificadorCliente, '1000000006');
});

test('al elegir un resultado por nombre se trae su ficha exacta y queda un solo bloque', async () => {
    const fabio = { vinculado: true, pendiente: false, id_usuario: 6, nombre_completo: 'Fabio Dueño', documento: '1000000006', mascotas: [] };
    const m = modulo({ respuestas: [
        { success: true, clientes: [{ id_usuario: 6, nombre_completo: 'Fabio Dueño', documento: '1000000006', identificador: '1000000006', pendiente: false }] },
        { success: true, propietario: fabio },
    ] });
    m.escribir('fabio');
    await m.correr();

    await m.clic(botones(m.ids.mascotasExistentes)[0]);

    assert.match(m.pedidos[1].url, /buscar_propietario_exacto_ajax&identificador=1000000006$/);
    assert.equal(m.ids.petOwnerDoc.value, 6);
    assert.equal(m.ids.mascotasExistentes.hijos.length, 0, 'Sin la tarjeta repetida en la búsqueda (captura 07).');
    assert.equal(m.ids.selectedOwnerInfo.hidden, false);
    assert.equal(todos(m.ids.selectedOwnerInfo).filter(h => h.tagName === 'ARTICLE').length, 1);
    assert.deepEqual(botones(m.ids.selectedOwnerInfo).map(b => b.getAttribute('aria-label')), ['Cambiar de propietario']);
});

test('sin coincidencias por nombre lo dice en la línea de ayuda y no ofrece nada', async () => {
    const m = modulo({ respuestas: [{ success: true, clientes: [] }] });
    m.escribir('Zacarías');
    await m.correr();
    assert.match(m.ids.ownerSearchAyuda.textContent, /Ningún cliente de esta clínica con ese nombre/);
    assert.equal(botones(m.ids.mascotasExistentes).length, 0);
});

test('el campo de búsqueda admite nombres con espacios y sigue restringiendo lo que no corresponde', () => {
    const vista = codigo('views/vet/pacientes.php');
    const linea = vista.split('\n').find(l => l.includes('id="ownerSearchInput"'));
    assert.match(linea, /placeholder="Nombre, documento o correo\.\.\."/);
    assert.match(linea, /data-caracteres="\\p\{L\}\\p\{M\}\\p\{N\} @\._\+'’\\-"/);
    assert.match(linea, /maxlength="<\?= ValidadorCuenta::CORREO_MAX \?>"/);
});

test('registrar o editar una mascota y desactivar un cliente ya no recargan la página', () => {
    const medical = codigo('public/js/medical-module.js');
    const guardar = medical.slice(medical.indexOf('async function savePet'), medical.indexOf('async function savePet') + 900);
    const editar = medical.slice(medical.indexOf('async function updatePet'), medical.indexOf('async function updatePet') + 900);
    for (const cuerpo of [guardar, editar]) {
        assert.doesNotMatch(cuerpo, /zookiRecargarConAviso|location\.reload/);
        assert.match(cuerpo, /ponerAlDiaPacientes\(/);
        assert.match(cuerpo, /zookiToast\(/);
    }
    assert.match(medical, /async function ponerAlDiaPacientes[\s\S]*refrescarPacientes\(\)[\s\S]*loadOwners\(\)[\s\S]*openOwnerDossier\(currentDossierId/);

    const usuarios = codigo('public/js/usuarios.js');
    const desactivar = usuarios.slice(usuarios.indexOf('async function cambiarEstadoCliente'), usuarios.indexOf("modulo.addEventListener('change'"));
    assert.doesNotMatch(desactivar, /zookiRecargarConAviso\('Vínculo desactivado/);
    assert.match(desactivar, /refrescarClientes\(\)/);
    assert.match(usuarios, /querySelector\('\[data-panel="clientes"\]'\)[\s\S]*cambiarVista\(modulo\.dataset\.vista\)[\s\S]*filtrar\('clientes'\)/);
});

test('el panel de Clientes se repinta en su sitio con la vista y el filtro elegidos', async () => {
    const panelActual = { innerHTML: 'viejo' };
    const llamadas = [];
    const modulo = {
        dataset: { vista: 'tabla' },
        addEventListener() {},
        querySelector: (selector) => (selector === '[data-panel="clientes"]' ? panelActual : null),
        querySelectorAll: () => [],
    };
    const modal = { addEventListener() {}, querySelector: () => ({}), querySelectorAll: () => [] };
    const form = { elements: {}, addEventListener() {}, querySelector: () => null };
    const contexto = {
        console, FormData, setTimeout,
        localStorage: { getItem: () => null, setItem() {} },
        window: { location: { href: 'index.php?action=admin_usuarios', reload: () => llamadas.push('recarga') } },
        document: { getElementById: (id) => ({ usuariosModulo: modulo, usuarioModal: modal, usuarioForm: form })[id] || null, addEventListener() {}, body: {} },
        fetch: async () => ({ text: async () => '<section data-panel="clientes">nuevo</section>' }),
        DOMParser: class { parseFromString() { return { querySelector: () => ({ innerHTML: 'nuevo' }) }; } },
        zookiToast() {},
    };
    vm.createContext(contexto);
    const fuente = codigo('public/js/usuarios.js').replace("    async function cambiarEstadoCliente(interruptor) {", "    window.refrescarClientesParaPrueba = refrescarClientes;\n    async function cambiarEstadoCliente(interruptor) {");
    vm.runInContext(fuente, contexto);

    await contexto.window.refrescarClientesParaPrueba();

    assert.equal(panelActual.innerHTML, 'nuevo');
    assert.deepEqual(llamadas, [], 'Sin recargar la página.');
});

test('el modal de Google activa la cuenta pendiente sin pedir clínica (RN-G21)', () => {
    const vista = codigo('views/auth/login.php');
    assert.match(vista, /<h3 data-google-modo="activar" hidden>Activa tu cuenta con Google<\/h3>/);
    assert.match(vista, /<div class="input-group" data-google-modo="registro">\s*<label for="google_id_clinica">/);
    assert.match(vista, /<strong data-google-clinica><\/strong> te registró como cliente/);

    const login = codigo('public/js/login.js');
    assert.match(login, /extra\.action === 'activar_cuenta'[\s\S]*abrirGoogleModal\(extra\.email, null, extra\.clinica\)/);
    assert.match(login, /googleClinica\.disabled = modo === 'activar'/, 'La clínica no se exige ni se envía al activar.');
    assert.match(codigo('public/css/styles.css'), /\.modal-card \[hidden\] \{\s*display: none !important;/);
});

test('el registro de mascota del personal ya no pide colores', () => {
    const vista = codigo('views/vet/pacientes.php');
    assert.doesNotMatch(vista, /SelectedColoresInput|colores\[\]|dashPetColores/);
    const medical = codigo('public/js/medical-module.js');
    assert.doesNotMatch(medical, /initColores|listar_colores_ajax|colores_nombres|SelectedColoresInput/);
});
