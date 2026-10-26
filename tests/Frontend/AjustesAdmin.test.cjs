/** Ajustes del administrador: Configuración y Mi perfil en una pantalla con menú blanco (aprobado 2026-10-26). */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const RAIZ = path.join(__dirname, '../..');
const leer = archivo => fs.readFileSync(path.join(RAIZ, archivo), 'utf8');
const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
// SQLite no tiene UTC_TIMESTAMP: la actividad de la cuenta cae a su mensaje de respaldo y el aviso va a stderr.
const vista = () => execFileSync(php, ['tests/Frontend/apoyo/ajustes-vista.php'], { cwd: RAIZ, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '' } });

test('Ajustes agrupa la clínica y la cuenta, sin estilos ni scripts en línea', () => {
    const fuente = leer('views/admin/configuracion.php');
    assert.doesNotMatch(fuente, /\sstyle=|\son[a-z]+=|<script>\s*\S/i);
    const html = vista();
    assert.match(html, /<h1 class="encabezado-pagina__titulo">Ajustes<\/h1>/);
    assert.equal((html.match(/<h1\b/g) || []).length, 1, 'Un solo título por pantalla');
    assert.match(html, /id="ajustesGrupoClinica">La clínica<[\s\S]*data-config-tab="clinica"[\s\S]*data-config-tab="horarios"[\s\S]*data-config-tab="ausencias"[\s\S]*action=admin_suscripcion&amp;desde=configuracion[\s\S]*id="ajustesGrupoCuenta">Mi cuenta<[\s\S]*data-config-tab="cuenta"[\s\S]*data-config-tab="seguridad"/);
    assert.match(html, /class="ajustes-menu__plan">Profesional</, 'La entrada a Suscripción muestra el plan');
    for (const panel of ['clinica', 'horarios', 'ausencias', 'cuenta', 'seguridad']) assert.match(html, new RegExp(`data-config-panel="${panel}"`), panel);
    assert.match(html, /id="perfilContactoForm"[\s\S]*id="perfilPasswordForm"/, 'Los formularios de Mi perfil no cambian');
    assert.match(html, /class="ajustes-salir"[^>]*>[\s\S]*?Cerrar sesión/);
    assert.match(html, /js\/ajustes\.js[\s\S]*js\/perfil\.js/);
});

test('Mi perfil del administrador lleva a Ajustes › Mi cuenta; el veterinario conserva su Mi perfil', () => {
    const controlador = leer('controllers/PerfilController.php');
    assert.match(controlador, /Roles::ADMIN\) \{\s*header\('Location: index\.php\?action=admin_configuracion#cuenta'\);/);
    assert.match(controlador, /require __DIR__ \. '\/\.\.\/views\/vet\/layout\.php';/);
    assert.match(leer('views/perfil/index.php'), /require __DIR__ \. '\/paneles_cuenta\.php';/);
});

test('ajustes.css usa solo las variables de identidad', () => {
    const css = leer('public/css/ajustes.css').replace(/\/\*[\s\S]*?\*\//g, '');
    assert.doesNotMatch(css, /#[a-f\d]{3,8}\b|rgba?\(|hsla?\(/i, 'Solo variables --z-* de identidad.css');
    const identidad = leer('public/css/identidad.css');
    for (const variable of css.matchAll(/var\((--z-[\w-]+)/g)) assert.ok(identidad.includes(variable[1] + ':'), variable[1]);
});

function servidor(html) {
    const tipos = { '.css': 'text/css', '.js': 'application/javascript', '.png': 'image/png', '.svg': 'image/svg+xml' };
    return http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html.replace(/<script[^>]+src="https:[^>]*><\/script>/g, '').replace(/<link[^>]+href="https:[^>]*>/g, ''));
            return;
        }
        const ruta = path.join(RAIZ, 'public', path.normalize(decodeURIComponent(url.pathname)).replace(/^([/\\])+/, ''));
        if (!ruta.startsWith(path.join(RAIZ, 'public')) || !fs.existsSync(ruta)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', tipos[path.extname(ruta)] || 'application/octet-stream');
        res.end(fs.readFileSync(ruta));
    });
}

test('Chrome: menú lateral y sección sin scroll en 1366×768; en el celular lista, sección con «← Ajustes» y barra de cinco', { skip: buscarChrome() ? false : 'Sin Chrome ni Chromium' }, async () => {
    const srv = servidor(vista());
    await new Promise(r => srv.listen(0, '127.0.0.1', r));
    const base = 'http://127.0.0.1:' + srv.address().port + '/';
    const nav = await abrirChrome(buscarChrome());
    const visible = selector => `(() => { const n = document.querySelector('${selector}'); return !!n && getComputedStyle(n).display !== 'none' && n.getBoundingClientRect().height > 0; })()`;
    const abrir = async (ancho, alto, url) => {
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: alto, deviceScaleFactor: 1, mobile: ancho < 768 });
        await nav.enviar('Page.navigate', { url });
        await nav.esperar("document.readyState === 'complete' && !!document.querySelector('[data-ajustes]').dataset.ajustesVista");
    };
    try {
        await abrir(1366, 768, base);
        for (const seccion of ['clinica', 'horarios', 'ausencias', 'cuenta', 'seguridad']) {
            await nav.evaluar(`document.querySelector('[data-config-tab="${seccion}"]').click(); true`);
            const estado = await nav.evaluar(`(() => ({
                menu: ${visible('.ajustes-lateral')},
                panel: [...document.querySelectorAll('[data-config-panel="${seccion}"]')].some(p => !p.hidden && p.getBoundingClientRect().height > 0),
                otros: [...document.querySelectorAll('[data-config-panel]:not([data-config-panel="${seccion}"])')].every(p => p.hidden),
                sinScroll: document.documentElement.scrollHeight <= innerHeight + 1 && document.documentElement.scrollWidth <= innerWidth,
                volver: ${visible('[data-ajustes-volver]')},
                hash: location.hash
            }))()`);
            assert.deepEqual(estado, { menu: true, panel: true, otros: true, sinScroll: true, volver: false, hash: '#' + seccion }, seccion);
        }
        assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.ajustes-lateral')).backgroundColor"), 'rgb(255, 255, 255)', 'Menú blanco, no azul');

        await abrir(390, 844, base);
        assert.equal(await nav.evaluar(visible('.ajustes-lateral')), true, 'Sin sección pedida, el celular abre la lista');
        assert.equal(await nav.evaluar(visible('.ajustes-contenido')), false);
        assert.equal(await nav.evaluar('location.hash'), '', 'La lista no deja una sección en la dirección');
        assert.equal(await nav.evaluar(visible('.ajustes-salir')), true, 'Cerrar sesión va al final de Ajustes');
        const barra = await nav.evaluar(`[...document.querySelectorAll('.pill-sidebar :is(.pill-nav-item, .pill-logout)')].filter(n => getComputedStyle(n).display !== 'none').map(n => n.textContent.trim())`);
        assert.deepEqual(barra, ['Inicio', 'Usuarios', 'Citas', 'Auditoría', 'Ajustes']);
        await nav.evaluar("document.querySelector('[data-config-tab=horarios]').click(); true");
        assert.equal(await nav.evaluar(visible('.ajustes-lateral')), false);
        assert.equal(await nav.evaluar(visible('#config-horarios')), true);
        assert.equal(await nav.evaluar("document.querySelector('[data-ajustes-titulo]').textContent"), 'Horarios');
        assert.equal(await nav.evaluar(visible('.encabezado-pagina')), false, 'La sección tiene un solo título');
        assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, 'Sin desborde a 390');
        await nav.evaluar("document.querySelector('[data-ajustes-volver]').click(); true");
        assert.equal(await nav.evaluar(visible('.ajustes-lateral')), true);
        assert.equal(await nav.evaluar(visible('.ajustes-contenido')), false);
        assert.equal(await nav.evaluar('location.hash'), '');

        // Mi perfil del administrador redirige a #cuenta: la sección abre directa.
        await abrir(390, 844, base + '#cuenta');
        assert.equal(await nav.evaluar(visible('#perfilCuenta')), true);
        assert.equal(await nav.evaluar(visible('.ajustes-lateral')), false);
        assert.equal(await nav.evaluar("document.querySelector('[data-ajustes-titulo]').textContent"), 'Mi cuenta');
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        srv.close();
    }
});
