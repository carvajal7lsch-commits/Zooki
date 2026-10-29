/**
 * Inicio del administrador (HU-6.2, RE-6.2.8, RE-6.2.9 y RE-6.2.13).
 * - Tocar un día pide panel_admin_dia_ajax y pinta la agenda por veterinario;
 *   una respuesta vieja no pisa a la nueva; las flechas cambian de mes.
 * - En Chrome real: con cuatro veterinarios la agenda se desplaza a los lados
 *   dentro de su tarjeta (decisión del dueño, 2026-10-24) y la página no se
 *   desplaza en 1366×768; en celular se elige un veterinario sin carrusel lateral.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { crearDocumento, colgar } = require('./apoyo/dom-datos.cjs');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');

const RAIZ = path.join(__dirname, '../..');
const leer = archivo => fs.readFileSync(path.join(RAIZ, archivo), 'utf8').replace(/\r\n/g, '\n');
const esperar = async () => { for (let i = 0; i < 4; i++) await new Promise(resolver => setImmediate(resolver)); };
/** Clic que sube por los ancestros, como en el navegador: el Inicio delega sus eventos. */
const tocar = nodo => {
    for (let actual = nodo; actual; actual = actual.padre) (actual.escuchas.click || []).forEach(fn => fn({ target: nodo, preventDefault() {} }));
};

function inicio(responder) {
    const documento = crearDocumento();
    const raiz = colgar(documento.raiz, 'div', { inicio: '', inicioAccion: 'panel_admin_dia_ajax' });
    const calendario = colgar(raiz, 'section', { inicioCalendario: '', elegido: '2026-10-23' });
    const contenidoCalendario = colgar(calendario, 'div', { inicioCalendarioContenido: '' });
    const dias = ['2026-10-23', '2026-10-26', '2026-10-27'].map(fecha => colgar(contenidoCalendario, 'button', { inicioFecha: fecha }));
    const flecha = colgar(contenidoCalendario, 'button', { inicioMes: '2026-11' });
    const dia = colgar(raiz, 'section', { inicioDia: '' });
    const contenidoDia = colgar(dia, 'div', { inicioDiaContenido: '' }, { innerHTML: '<p>hoy</p>' });
    const llamadas = { fetch: [], avisos: [] };
    const contexto = {
        console, URLSearchParams, document: documento, module: { exports: {} }, location: { reload() {} },
        zookiAviso: texto => llamadas.avisos.push(texto),
        fetch: url => { llamadas.fetch.push(url); return responder(url); },
    };
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/inicio-comun.js'), contexto);
    vm.runInContext(leer('public/js/inicio-admin.js'), contexto);
    contexto.module.exports.iniciar(raiz);
    return { dias, flecha, dia, contenidoDia, contenidoCalendario, llamadas };
}

test('tocar un día pide la agenda por veterinario a panel_admin_dia_ajax', async () => {
    const i = inicio(url => Promise.resolve({ json: async () => ({ success: true, html: '<p>' + new URL(url, 'https://zooki.test').searchParams.get('fecha') + '</p>' }) }));
    tocar(i.dias[1]);
    assert.equal(i.dia.getAttribute('aria-busy'), 'true');
    await esperar();
    assert.equal(i.llamadas.fetch[0], 'index.php?action=panel_admin_dia_ajax&fecha=2026-10-26');
    assert.equal(i.contenidoDia.innerHTML, '<p>2026-10-26</p>');
    assert.equal(i.dia.getAttribute('aria-busy'), 'false');
    assert.ok(i.dias[1].classList.contains('is-elegido'));
    assert.equal(i.dias[0].getAttribute('aria-pressed'), 'false');
});

test('una respuesta vieja no pisa la agenda del día que se tocó después', async () => {
    const pendientes = [];
    const i = inicio(url => new Promise(resolver => pendientes.push(() => resolver({ json: async () => ({ success: true, html: url }) }))));
    tocar(i.dias[1]);
    tocar(i.dias[2]);
    pendientes[1]();
    await esperar();
    pendientes[0]();
    await esperar();
    assert.match(i.contenidoDia.innerHTML, /fecha=2026-10-27$/);
});

test('las flechas cambian de mes conservando el día elegido; un error avisa sin borrar la agenda', async () => {
    let respuesta = { success: true, html: '<p>Noviembre</p>' };
    const i = inicio(() => Promise.resolve({ json: async () => respuesta }));
    tocar(i.flecha);
    await esperar();
    assert.equal(i.llamadas.fetch[0], 'index.php?action=panel_admin_dia_ajax&mes=2026-11&elegido=2026-10-23');
    assert.equal(i.contenidoCalendario.innerHTML, '<p>Noviembre</p>');

    respuesta = { success: false, message: 'La fecha no es válida.' };
    tocar(i.dias[0]);
    await esperar();
    assert.equal(i.contenidoDia.innerHTML, '<p>hoy</p>');
    assert.deepEqual(i.llamadas.avisos, ['No se pudo cargar ese día. Intenta nuevamente.']);
});

test('el Inicio del administrador no usa estilos ni scripts en línea y deja sin uso panel.css y panel-admin.js', () => {
    for (const vista of ['views/admin/panel.php', 'views/admin/partials/inicio_agenda.php', 'views/partials/inicio_calendario.php']) {
        assert.doesNotMatch(leer(vista), /\sstyle=|\son[a-z]+=|<script/i, vista);
    }
    const layout = leer('views/admin/layout.php');
    assert.match(layout, /css\/inicio-comun\.css[^\n]*\n    <link rel="stylesheet" href="css\/inicio-admin\.css/);
    assert.match(layout, /js\/inicio-comun\.js[^\n]*\n    <script src="js\/inicio-admin\.js/);
    assert.doesNotMatch(layout, /panel\.css|panel-admin\.js/);
    assert.ok(!fs.existsSync(path.join(RAIZ, 'public/css/panel.css')));
    assert.ok(!fs.existsSync(path.join(RAIZ, 'public/js/panel-admin.js')));
    assert.doesNotMatch(leer('views/admin/panel.php'), /Agendar/, 'RN-401: el administrador no crea citas');
    const css = leer('public/css/inicio-admin.css');
    assert.match(css, /\.inicio-agenda--muchas \{[^}]*grid-auto-flow: column;[^}]*overflow-x: auto;/);
});

function vista(escenario) {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' && fs.existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
    return execFileSync(php, ['tests/Frontend/apoyo/inicio-admin-vista.php', escenario], { cwd: RAIZ, encoding: 'utf8', env: { ...process.env, TRUSTED_PROXIES: '127.0.0.2', ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' } });
}

/** La hoja con sus @import locales resueltos, como los sirve el navegador; los externos se omiten. */
function cssLocal(archivo) {
    return fs.readFileSync(archivo, 'utf8').replace(/@import\s+url\(['"]?([^'"\)]+)['"]?\);/g, (_, ruta) => {
        if (/^https?:/.test(ruta)) return '';
        return cssLocal(path.join(path.dirname(archivo), ruta.split('?')[0]));
    });
}

function pagina(html) {
    const estilos = [...html.matchAll(/<link[^>]+href="(css\/[^"?]+)[^"]*"[^>]*>/g)]
        .map(m => '<style>' + cssLocal(path.join(RAIZ, 'public', m[1])) + '</style>').join('\n');
    return html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '')
        .replace(/<img\b[^>]*>/gi, '')
        .replace('</head>', `${estilos}<style>#global-loader{display:none}*{animation:none!important;transition:none!important}</style></head>`);
}

test('Chrome: con cuatro veterinarios la agenda se desplaza dentro de su tarjeta y la página cabe', { skip: !buscarChrome() }, async () => {
    const paginas = { dos: pagina(vista('dos')), cuatro: pagina(vista('cuatro')) };
    assert.doesNotMatch(paginas.dos + paginas.cuatro, /Fatal error|Warning:|Notice:/);
    const nav = await abrirChrome(buscarChrome());
    try {
        for (const [escenario, ancho, alto] of [['dos', 1366, 768], ['cuatro', 1366, 768], ['cuatro', 1024, 768], ['dos', 390, 844], ['cuatro', 390, 844]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: alto, deviceScaleFactor: 1, mobile: ancho < 768 });
            const arbol = await nav.enviar('Page.getFrameTree');
            await nav.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html: paginas[escenario] });
            if (ancho < 768) {
                await nav.evaluar(leer('public/js/inicio-comun.js'));
                await nav.evaluar(leer('public/js/inicio-admin.js'));
            }
            const estado = await nav.evaluar(`(() => {
                const agenda = document.querySelector('.inicio-agenda');
                return { horizontal: document.documentElement.scrollWidth <= innerWidth, vertical: document.documentElement.scrollHeight <= innerHeight,
                    columnas: agenda.children.length, visibles: [...agenda.children].filter(n => !n.hidden).length, desplazaLados: agenda.scrollWidth > agenda.clientWidth + 1 };
            })()`);
            const detalle = JSON.stringify({ escenario, ancho, estado });
            assert.equal(estado.horizontal, true, detalle);
            assert.equal(estado.columnas, escenario === 'cuatro' ? 4 : 2, detalle);
            if (ancho >= 1024) {
                assert.equal(estado.vertical, true, 'RE-6.2.13: cabe sin desplazar la página ' + detalle);
                assert.equal(estado.desplazaLados, escenario === 'cuatro', 'Más de tres veterinarios se desplazan a los lados ' + detalle);
            } else {
                assert.equal(estado.desplazaLados, false, 'En el celular se elige un veterinario sin desplazamiento lateral ' + detalle);
                assert.equal(estado.visibles, 1, 'Solo una agenda visible ' + detalle);
            }
        }
    } finally {
        await nav.cerrar();
    }
});
