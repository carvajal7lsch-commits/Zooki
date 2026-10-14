const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

// M5 C2a — Portal: Inicio · Citas · Clínicas · Perfil, pestañas comunes y
// filtro por clínica en la ficha de la mascota (RN-114).

function elemento(datos = {}) {
    const clases = new Set(datos.clases || []);
    return {
        id: datos.id || '',
        hidden: Boolean(datos.hidden),
        dataset: { ...(datos.dataset || {}) },
        atributos: {},
        hijos: datos.hijos || [],
        innerHTML: '',
        classList: {
            add: (c) => clases.add(c),
            remove: (c) => clases.delete(c),
            toggle: (c, si) => (si ? clases.add(c) : clases.delete(c)),
            contains: (c) => clases.has(c),
        },
        setAttribute(clave, valor) { this.atributos[clave] = String(valor); },
        removeAttribute(clave) { delete this.atributos[clave]; },
        querySelectorAll(selector) { return (datos.consultas || {})[selector] || []; },
        tieneClase: (c) => clases.has(c),
    };
}

/** Carga portal.js con un DOM mínimo: ids, consultas por selector, historial y dirección. */
function portal({ ids = {}, consultas = {}, hash = '' } = {}) {
    const historial = [];
    const document = {
        addEventListener() {},
        getElementById: (id) => ids[id] || null,
        querySelectorAll: (selector) => consultas[selector] || [],
        querySelector: (selector) => (consultas[selector] || [])[0] || null,
        // Lo justo para escapeHtml(): textContent entra y sale escapado por innerHTML.
        createElement: () => {
            let texto = '';
            return {
                set textContent(valor) { texto = valor; },
                get innerHTML() { return texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); },
            };
        },
    };
    const contexto = {
        document,
        window: { addEventListener() {}, scrollTo() {} },
        location: { hash },
        history: {
            state: null,
            pushState: (estado, titulo, url) => historial.push(['push', url]),
            replaceState: (estado, titulo, url) => historial.push(['replace', url]),
        },
        console,
    };
    contexto.window.scrollTo = () => {};
    vm.createContext(contexto);
    // escapeHtml() vive en avisos.js, que el layout carga antes que portal.js.
    vm.runInContext(codigo('public/js/avisos.js'), contexto);
    vm.runInContext(codigo('public/js/portal.js'), contexto);
    return { contexto, historial };
}

test('cada sección tiene su dirección y las anteriores llevan a la nueva', () => {
    const { contexto } = portal();
    const destino = (hash) => JSON.parse(JSON.stringify(contexto.seccionDesdeHash(hash)));

    assert.deepEqual(destino('#inicio'), { tab: 'home' });
    assert.deepEqual(destino('#citas'), { tab: 'citas' });
    assert.deepEqual(destino('#clinicas'), { tab: 'clinicas' });
    assert.deepEqual(destino('#perfil'), { tab: 'account' });
    assert.deepEqual(destino('#mascota-7'), { mascota: 7 });
    // Antes de C2: «Agenda» es ahora «Citas»; «Servicios» y «Recordatorios» ya no existen.
    assert.deepEqual(destino('#agenda'), { tab: 'citas' });
    assert.deepEqual(destino('#servicios'), { tab: 'home' });
    assert.deepEqual(destino('#recordatorios'), { tab: 'home' });
    assert.deepEqual(destino('#otra-cosa'), { tab: 'home' });
});

test('cambiar de sección muestra su pantalla, marca el menú y guarda la dirección', () => {
    const inicio = elemento({ id: 'screen-home', clases: ['app-screen', 'active'] });
    const citas = elemento({ id: 'screen-citas', clases: ['app-screen'] });
    const menuInicio = elemento({ dataset: { nav: 'home' }, clases: ['active'] });
    const menuCitas = elemento({ dataset: { nav: 'citas' } });
    const { contexto, historial } = portal({
        ids: { 'screen-home': inicio, 'screen-citas': citas },
        consultas: {
            '.app-screen': [inicio, citas],
            '.mobile-nav-item[data-nav], .portal-rail__item[data-nav]': [menuInicio, menuCitas],
        },
    });

    contexto.switchTab('citas');

    assert.equal(citas.tieneClase('active'), true);
    assert.equal(inicio.tieneClase('active'), false);
    assert.equal(menuCitas.atributos['aria-current'], 'page');
    assert.equal(menuInicio.atributos['aria-current'], undefined);
    assert.deepEqual(historial.at(-1), ['push', '#citas']);
});

test('una sección que no existe vuelve a Inicio', () => {
    const inicio = elemento({ id: 'screen-home', clases: ['app-screen'] });
    const { contexto } = portal({ ids: { 'screen-home': inicio }, consultas: { '.app-screen': [inicio] } });

    contexto.switchTab('explore');

    assert.equal(inicio.tieneClase('active'), true);
});

test('las pestañas marcan la elegida y ocultan los demás paneles', () => {
    const panelProximas = elemento({ id: 'citas-proximas' });
    const panelPasadas = elemento({ id: 'citas-pasadas', hidden: true });
    const proximas = elemento({ dataset: { pestana: 'citas-proximas' }, clases: ['active'] });
    const pasadas = elemento({ dataset: { pestana: 'citas-pasadas' } });
    const grupo = elemento({ consultas: { '[data-pestana]': [proximas, pasadas] } });
    const { contexto } = portal({ ids: { 'citas-proximas': panelProximas, 'citas-pasadas': panelPasadas } });

    contexto.activarPestana(grupo, 'citas-pasadas');

    assert.equal(panelPasadas.hidden, false);
    assert.equal(panelProximas.hidden, true);
    assert.equal(pasadas.atributos['aria-selected'], 'true');
    assert.equal(proximas.atributos['aria-selected'], 'false');
    assert.equal(pasadas.tieneClase('active'), true);
});

test('la ficha filtra por clínica y el filtro solo aparece con dos o más', () => {
    const filtro = elemento({ id: 'filtroClinicaMascota', hidden: true });
    const deNorte = elemento({ dataset: { idClinica: '1' } });
    const deSur = elemento({ dataset: { idClinica: '2' } });
    const chipSur = elemento({ dataset: { filtroClinica: '2' } });
    const { contexto } = portal({
        ids: { filtroClinicaMascota: filtro },
        consultas: {
            '#screen-pet-detail [data-id-clinica]': [deNorte, deSur],
            '#screen-pet-detail [data-filtro-clinica]': [chipSur],
        },
    });

    contexto.prepararFiltroClinica([{ id: 1, nombre: 'Norte' }]);
    assert.equal(filtro.hidden, true, 'Con una sola clínica no hay filtro.');

    contexto.prepararFiltroClinica([{ id: 1, nombre: 'Norte' }, { id: 2, nombre: 'Sur <b>' }]);
    assert.equal(filtro.hidden, false);
    assert.match(filtro.innerHTML, /data-filtro-clinica="2"/);
    assert.doesNotMatch(filtro.innerHTML, /<b>/, 'El nombre de la clínica se escapa.');

    contexto.filtrarFichaPorClinica('2');
    assert.equal(deNorte.hidden, true);
    assert.equal(deSur.hidden, false);
    assert.equal(chipSur.atributos['aria-pressed'], 'true');

    contexto.filtrarFichaPorClinica('all');
    assert.equal(deNorte.hidden, false);
});

test('las clínicas de los registros salen sin repetir, en orden de aparición', () => {
    const { contexto } = portal();
    const clinicas = contexto.clinicasDeLosRegistros({
        historial: [{ id_clinica: 2, clinica_nombre: 'Sur' }],
        vacunas: [{ id_clinica: 1, clinica_nombre: 'Norte' }, { id_clinica: 2, clinica_nombre: 'Sur' }],
        desparasitaciones: [],
        citas: [{ id_clinica: 1, clinica_nombre: 'Norte' }],
    });
    assert.deepEqual(JSON.parse(JSON.stringify(clinicas)), [{ id: 2, nombre: 'Sur' }, { id: 1, nombre: 'Norte' }]);
});

test('el portal no tiene JS ni CSS en línea, ni Servicios, Alertas o Font Awesome', () => {
    for (const archivo of ['views/portal/index.php', 'views/portal/layout.php', 'views/portal/imprimir_historial.php']) {
        const vista = codigo(archivo);
        assert.doesNotMatch(vista, /\sstyle="|\son[a-z]+="|<script>\s*\S/, archivo);
        assert.doesNotMatch(vista, /font-awesome|class="fa[s ]?\s|fa-/, archivo);
    }
    const vista = codigo('views/portal/index.php');
    assert.doesNotMatch(vista, /screen-explore|screen-notifications|filtrarServicios/);
    assert.doesNotMatch(codigo('public/js/portal.js'), /loadPortalAlerts|loadVetsExplore|filtrarServicios/);
    assert.doesNotMatch(codigo('views/portal/layout.php'), /\?v=<\?= \$v \?>-/, 'Sin sufijos de versión escritos a mano.');
});
