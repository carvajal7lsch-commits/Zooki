/** Rediseño de Suscripción, entrada desde Configuración y barra con nombres (aprobado 2026-10-26). */
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
const vista = (escenario, desde = '') => execFileSync(php, ['tests/Frontend/apoyo/suscripcion-vista.php', escenario, desde], { cwd: RAIZ, encoding: 'utf8', env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '' } });

test('la vista conserva los ganchos de pago y no lleva estilos ni scripts en línea', () => {
    const fuente = leer('views/admin/suscripcion.php');
    assert.doesNotMatch(fuente, /\sstyle=|\son[a-z]+=|<script/i);
    for (const gancho of ['data-suscripcion-pantalla', 'data-suscripcion-estado', 'data-suscripcion-fin', 'data-suscripcion-plan', 'suscripcion__pagar', 'suscripcion__consultar', 'suscripcionMensaje',
        'data-fuente-pantalla', 'data-fuente-preparar', 'data-fuente-guardar', 'data-automatico-autorizar', 'data-automatico-retirar', 'data-fuente-quitar', 'data-nequi-comprobar',
        'data-acepta-politica', 'data-acepta-datos', 'data-acepta-mensual', 'data-fuente-mensaje', 'data-fuente-validacion', 'data-fuente-condiciones', 'data-contrato-politica',
        'data-contrato-datos', 'data-fuente-resumen', 'data-fuente-estado', 'data-automatico-estado', 'data-importe-mensual', 'data-mandato-importe']) {
        assert.ok(fuente.includes(gancho), gancho);
    }
    const css = leer('public/css/suscripcion.css').replace(/\/\*[\s\S]*?\*\//g, '');
    assert.doesNotMatch(css, /#[a-f\d]{3,8}\b|rgba?\(|hsla?\(/i, 'Solo variables --z-* de identidad.css');
    const identidad = leer('public/css/identidad.css');
    for (const variable of css.matchAll(/var\((--z-[\w-]+)/g)) assert.ok(identidad.includes(variable[1] + ':'), variable[1]);
});

test('volver dice a dónde vuelve: Inicio por defecto y Configuración si se entró desde allí; la paginación conserva el origen', () => {
    const desdeInicio = vista('historial');
    assert.match(desdeInicio, /class="encabezado-pagina__volver" href="index\.php\?action=admin_panel"[\s\S]*?>Inicio</);
    const desdeConfiguracion = vista('historial', 'configuracion');
    assert.match(desdeConfiguracion, /class="encabezado-pagina__volver" href="index\.php\?action=admin_configuracion"[\s\S]*?>Configuración</);
    assert.match(desdeConfiguracion, /<h1 class="encabezado-pagina__titulo">Suscripción<\/h1>/);
    assert.equal((desdeConfiguracion.match(/<h1\b/g) || []).length, 1, 'Un solo título por pantalla');
    assert.match(vista('historial', '<script>'), /class="encabezado-pagina__volver" href="index\.php\?action=admin_panel"/, 'Un origen desconocido vuelve al Inicio');
    assert.match(leer('views/admin/suscripcion.php'), /'desde' => \$origenSuscripcion/);
    assert.match(leer('views/admin/configuracion.php'), /class="config-tabs__enlace" href="index\.php\?action=admin_suscripcion&amp;desde=configuracion"/);
});

test('barra: el administrador lleva Auditoría y Mi perfil a «Más»; el veterinario cabe sin «Más» y conserva Salir', () => {
    const admin = vista('historial');
    assert.match(admin, /class="pill-sidebar pill-sidebar--etiquetas pill-sidebar--con-mas"/);
    assert.match(admin, /pill-nav-item--secundario" href="index\.php\?action=admin_auditoria"/);
    assert.match(admin, /pill-nav-item--secundario" href="index\.php\?action=mi_perfil"/);
    assert.match(admin, /class="pill-nav-item active" href="index\.php\?action=admin_configuracion"[^>]*aria-current="page"/, 'Suscripción vive en Configuración');
    assert.match(admin, /<dialog class="zk-dialogo barra-mas" id="barraNavegacionMas"[\s\S]*admin_auditoria[\s\S]*mi_perfil[\s\S]*Cerrar sesión/);
    const vet = execFileSync(php, ['tests/Frontend/apoyo/c81-vistas.php', 'vet_pacientes'], { cwd: RAIZ, encoding: 'utf8' });
    assert.doesNotMatch(vet, /pill-sidebar--con-mas|barraNavegacionMas/);
    assert.match(vet, /class="pill-logout"[\s\S]*?>Salir</);
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

test('Chrome: celular con pestañas y hoja «Pagar o renovar»; total según el ciclo; barra con nombres sin desborde', { skip: buscarChrome() ? false : 'Sin Chrome ni Chromium' }, async () => {
    const srv = servidor(vista('historial'));
    await new Promise(r => srv.listen(0, '127.0.0.1', r));
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        await nav.enviar('Page.navigate', { url: 'http://127.0.0.1:' + srv.address().port + '/' });
        await nav.esperar("document.readyState === 'complete' && document.querySelector('[data-suscripcion-pantalla]').dataset.pestana === 'plan'");
        const visible = selector => `(() => { const n = document.querySelector('${selector}'); return !!n && getComputedStyle(n).display !== 'none' && n.getBoundingClientRect().height > 0; })()`;
        assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, 'Sin desborde a 390');
        assert.equal(await nav.evaluar(visible('.suscripcion__plan')), true);
        assert.equal(await nav.evaluar(visible('.suscripcion__historial')), false, 'El historial va en su pestaña');
        assert.equal(await nav.evaluar("document.querySelector('.suscripcion__renovar').getBoundingClientRect().top >= innerHeight - 1"), true, 'La hoja empieza cerrada');
        await nav.evaluar("document.querySelector('[data-suscripcion-abrir]').click(); true");
        await nav.esperar("document.querySelector('.suscripcion__renovar').getBoundingClientRect().bottom <= innerHeight + 1");
        await nav.evaluar("document.querySelector('input[value=anual]').click(); true");
        assert.match(await nav.evaluar("document.querySelector('[data-suscripcion-total]').textContent"), /899\.900/);
        assert.match(await nav.evaluar("document.querySelector('[data-suscripcion-unidad]').textContent"), /año/);
        await nav.evaluar("document.querySelector('.suscripcion__renovar').dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true})); true");
        await nav.esperar("!document.querySelector('[data-suscripcion-pantalla]').classList.contains('is-renovar-abierta')");
        await nav.evaluar("document.querySelector('[data-suscripcion-pestana=historial]').click(); true");
        assert.equal(await nav.evaluar(visible('.suscripcion__historial')), true);
        assert.equal(await nav.evaluar(visible('.suscripcion__plan')), false);
        assert.equal(await nav.evaluar("document.querySelector('[data-suscripcion-pestana=historial]').getAttribute('aria-selected')"), 'true');
        const barra = await nav.evaluar(`(() => {
            const barra = document.querySelector('.pill-sidebar').getBoundingClientRect();
            const visibles = [...document.querySelectorAll('.pill-sidebar :is(.pill-nav-item, .pill-logout)')].filter(n => getComputedStyle(n).display !== 'none');
            return { textos: visibles.map(n => n.textContent.trim()), dentro: visibles.every(n => { const r = n.getBoundingClientRect(); return r.left >= barra.left - 1 && r.right <= barra.right + 1; }) };
        })()`);
        assert.deepEqual(barra.textos, ['Inicio', 'Usuarios', 'Citas', 'Ajustes', 'Más']);
        assert.equal(barra.dentro, true);
        await nav.evaluar("document.querySelector('.pill-nav-mas').click(); true");
        await nav.esperar("document.getElementById('barraNavegacionMas').open");
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
        await nav.enviar('Page.reload');
        await nav.esperar("document.readyState === 'complete' && document.querySelector('[data-suscripcion-pantalla]')");
        assert.equal(await nav.evaluar(visible('.suscripcion__historial')), true, 'En escritorio se ve todo junto');
        assert.equal(await nav.evaluar(visible('.suscripcion-pestanas')), false);
        assert.equal(await nav.evaluar(visible('.pill-nav-mas')), false, 'La barra lateral de escritorio no cambia');
        assert.equal(await nav.evaluar(visible('.pill-nav-item--secundario')), true);
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        srv.close();
    }
});
