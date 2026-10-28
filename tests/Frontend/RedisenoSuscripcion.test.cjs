/** Rediseño de Suscripción, entrada desde Ajustes y barra con nombres (aprobado 2026-10-26). */
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

test('Suscripción comparte Ajustes y solo conserva Inicio al entrar desde el dashboard', () => {
    const desdeInicio = vista('historial');
    assert.match(desdeInicio, /class="encabezado-pagina__volver" href="index\.php\?action=admin_panel"[\s\S]*?>Inicio</);
    const desdeConfiguracion = vista('historial', 'configuracion');
    assert.doesNotMatch(desdeConfiguracion, /class="encabezado-pagina__volver"/);
    assert.match(desdeConfiguracion, /<h1 class="encabezado-pagina__titulo top-header-title">Ajustes<\/h1>/);
    assert.equal((desdeConfiguracion.match(/<h1\b/g) || []).length, 1, 'Un solo título por pantalla');
    assert.doesNotMatch(desdeConfiguracion, /encabezado-pagina__subtitulo/);
    assert.doesNotMatch(vista('historial', '<script>'), /class="encabezado-pagina__volver"/, 'Un origen desconocido no agrega un destino');
    assert.match(leer('views/admin/suscripcion.php'), /'desde' => \$origenSuscripcion/);
    assert.match(desdeConfiguracion, /data-config-panel="suscripcion"[\s\S]*data-suscripcion-pantalla/);
    assert.match(desdeConfiguracion, /data-config-tab="suscripcion"/);
});

test('barra: el administrador tiene cinco destinos sin «Más» y Cerrar sesión en Ajustes; el veterinario cabe sin «Más» y conserva Salir', () => {
    const admin = vista('historial');
    assert.match(admin, /class="pill-sidebar pill-sidebar--etiquetas pill-sidebar--sin-salir"/);
    assert.doesNotMatch(admin, /pill-nav-item--secundario|barraNavegacionMas|action=mi_perfil/);
    assert.match(admin, /href="index\.php\?action=admin_auditoria"/);
    assert.match(admin, /class="pill-nav-item active" href="index\.php\?action=admin_configuracion"[^>]*aria-label="Ajustes" aria-current="page"/, 'Suscripción vive en Ajustes');
    // Desde la cabecera común (R, 2026-10-27) la cuenta del administrador se abre en el menú del avatar.
    assert.match(admin, /href="index\.php\?action=admin_configuracion#cuenta" data-cuenta-destino/);
    const vet = execFileSync(php, ['tests/Frontend/apoyo/c81-vistas.php', 'vet_pacientes'], { cwd: RAIZ, encoding: 'utf8' });
    assert.doesNotMatch(vet, /pill-sidebar--con-mas|barraNavegacionMas/);
    assert.match(vet, /class="pill-logout"[\s\S]*?>Salir</);
});

function servidor(html) {
    const tipos = { '.css': 'text/css', '.js': 'application/javascript', '.png': 'image/png', '.svg': 'image/svg+xml' };
    return http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/index.php' && url.searchParams.get('action') === 'get_horarios_clinica_ajax') {
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify({ success: true, horarios: [] }));
            return;
        }
        if (url.pathname === '/') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html.replace(/data-ajustes-seccion-inicial="[^"]*"/, 'data-ajustes-seccion-inicial="' + (url.searchParams.get('seccion') === 'suscripcion' ? 'suscripcion' : '') + '"').replace(/<script[^>]+src="https:[^>]*><\/script>/g, '').replace(/<link[^>]+href="https:[^>]*>/g, ''));
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

test('Chrome (v2): pestañas en todos los anchos, ventana «Pagar o renovar», total según el ciclo, barra con nombres y sin scroll en 1366×768', { skip: buscarChrome() ? false : 'Sin Chrome ni Chromium' }, async () => {
    const srv = servidor(vista('historial'));
    await new Promise(r => srv.listen(0, '127.0.0.1', r));
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        await nav.enviar('Page.navigate', { url: 'http://127.0.0.1:' + srv.address().port + '/?seccion=suscripcion' });
        await nav.esperar("document.readyState === 'complete' && document.querySelector('[data-suscripcion-pantalla]').dataset.pestana === 'plan'");
        const visible = selector => `(() => { const n = document.querySelector('${selector}'); return !!n && getComputedStyle(n).display !== 'none' && n.getBoundingClientRect().height > 0; })()`;
        assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, 'Sin desborde a 390');
        assert.equal(await nav.evaluar(visible('.suscripcion__plan')), true);
        assert.equal(await nav.evaluar(visible('[data-ajustes-volver-inicio]')), true, 'El atajo de Inicio conserva su regreso en el celular');
        assert.equal(await nav.evaluar("document.querySelector('[data-ajustes-volver-inicio]').getAttribute('href')"), 'index.php?action=admin_panel');
        assert.equal(await nav.evaluar(visible('.suscripcion__historial')), false, 'El historial va en su pestaña');
        assert.equal(await nav.evaluar("document.getElementById('suscripcionRenovarDialogo').open"), false);
        await nav.evaluar("document.querySelector('.suscripcion__abrir-renovar').click(); true");
        await nav.esperar("document.getElementById('suscripcionRenovarDialogo').open");
        assert.equal(await nav.evaluar("document.querySelector('#suscripcionRenovarDialogo').getBoundingClientRect().bottom <= innerHeight + 1"), true, 'Hoja inferior dentro de la pantalla');
        await nav.evaluar("document.querySelector('input[value=anual]').click(); true");
        assert.match(await nav.evaluar("document.querySelector('[data-suscripcion-total]').textContent"), /899\.900/);
        assert.match(await nav.evaluar("document.querySelector('[data-suscripcion-unidad]').textContent"), /año/);
        await nav.evaluar("document.querySelector('#suscripcionRenovarDialogo [data-cerrar-dialogo]').click(); true");
        await nav.esperar("!document.getElementById('suscripcionRenovarDialogo').open");
        await nav.evaluar("document.querySelector('.suscripcion__accion--medio').click(); true");
        await nav.esperar("document.getElementById('suscripcionMedioDialogo').open");
        await nav.evaluar("document.querySelector('#suscripcionMedioDialogo [data-cerrar-dialogo]').click(); true");
        await nav.evaluar("document.querySelector('[data-suscripcion-pestana=historial]').click(); true");
        assert.equal(await nav.evaluar(visible('.suscripcion__historial')), true);
        assert.equal(await nav.evaluar(visible('.suscripcion__plan')), false);
        assert.equal(await nav.evaluar('location.hash'), '#suscripcion', 'La pestaña interior conserva la sección');
        assert.equal(await nav.evaluar("new URLSearchParams(location.search).get('vista_suscripcion')"), 'historial');
        const barra = await nav.evaluar(`(() => {
            const barra = document.querySelector('.pill-sidebar').getBoundingClientRect();
            const visibles = [...document.querySelectorAll('.pill-sidebar :is(.pill-nav-item, .pill-logout)')].filter(n => getComputedStyle(n).display !== 'none');
            return { textos: visibles.map(n => n.textContent.trim()), dentro: visibles.every(n => { const r = n.getBoundingClientRect(); return r.left >= barra.left - 1 && r.right <= barra.right + 1; }) };
        })()`);
        assert.deepEqual(barra.textos, ['Inicio', 'Usuarios', 'Citas', 'Auditoría', 'Ajustes']);
        assert.equal(barra.dentro, true);

        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false });
        await nav.enviar('Page.reload');
        await nav.esperar("document.readyState === 'complete' && document.querySelector('[data-suscripcion-pantalla]').dataset.pestana === 'historial'");
        assert.equal(await nav.evaluar(visible('.suscripcion-pestanas')), true, 'También en escritorio hay pestañas');
        assert.equal(await nav.evaluar(visible('.ajustes-lateral')), true, 'El menú de Ajustes sigue junto a Suscripción');
        assert.equal(await nav.evaluar(visible('.suscripcion__plan')), false);
        assert.equal(await nav.evaluar('document.documentElement.scrollHeight <= innerHeight + 1'), true, 'Historial sin scroll en 1366×768');
        await nav.evaluar("document.querySelector('[data-suscripcion-pestana=plan]').click(); true");
        assert.equal(await nav.evaluar(visible('.suscripcion__plan')), true);
        assert.equal(await nav.evaluar('document.documentElement.scrollHeight <= innerHeight + 1'), true, 'Mi plan sin scroll en 1366×768');
        assert.equal(await nav.evaluar(visible('.pill-logout')), true, 'En escritorio la barra lateral conserva Salir');
        const cabecera = await nav.evaluar(`(() => {
            const normal = document.createElement('h1');
            normal.className = 'top-header-title';
            normal.textContent = 'Configuración';
            document.body.append(normal);
            const titulo = document.querySelector('.encabezado-pagina__titulo');
            const coinciden = getComputedStyle(titulo).fontSize === getComputedStyle(normal).fontSize;
            normal.remove();
            return { tituloNormal: coinciden, subtituloVisible: !!document.querySelector('.encabezado-pagina__subtitulo'), volverAlto: document.querySelector('.encabezado-pagina__volver').getBoundingClientRect().height };
        })()`);
        assert.equal(cabecera.tituloNormal, true, 'Título del mismo tamaño que el encabezado habitual');
        assert.equal(cabecera.subtituloVisible, false, 'La cabecera no repite el contexto de clínica');
        assert.ok(cabecera.volverAlto >= 40, 'Volver mantiene un área táctil cómoda');
        for (const [width, height] of [[360, 800], [390, 844], [820, 1180]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
            await nav.enviar('Page.reload');
            await nav.esperar("document.readyState === 'complete' && document.querySelector('[data-suscripcion-pantalla]').dataset.pestana === 'plan'");
            for (const pesta of ['plan', 'historial']) {
                await nav.evaluar(`document.querySelector('[data-suscripcion-pestana=${pesta}]').click(); true`);
                assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, `${pesta} sin desborde a ${width}`);
                assert.equal(await nav.evaluar("[...document.querySelectorAll('[data-suscripcion-pestana]')].every(n => n.getBoundingClientRect().height >= 40)"), true, `Pestañas accesibles a ${width}`);
            }
            await nav.evaluar("document.querySelector('[data-suscripcion-pestana=plan]').click(); true");
        }
        await nav.evaluar("document.querySelector('[data-config-tab=cuenta]').click(); true");
        await nav.enviar('Page.reload');
        await nav.esperar("document.readyState === 'complete' && !document.querySelector('#perfilCuenta').hidden");
        assert.equal(await nav.evaluar('location.hash'), '#cuenta', 'Suscripción no roba la sección al recargar Ajustes');
        await nav.evaluar("document.querySelector('[data-ajustes-volver]').click(); true");
        await nav.enviar('Page.reload');
        await nav.esperar("document.readyState === 'complete' && document.querySelector('[data-ajustes]').dataset.ajustesVista === 'menu'");
        assert.equal(await nav.evaluar('location.hash'), '', 'Volver a la lista no deja una sección activa en la dirección');
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        srv.close();
    }
});
