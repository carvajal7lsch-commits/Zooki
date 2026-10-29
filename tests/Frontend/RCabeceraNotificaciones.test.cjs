// R — Cabecera minimalista y notificaciones (mockup aprobado, 2026-10-27). HU-T.5/T.17, HU-4.11, RNF-16.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const RAIZ = path.resolve(__dirname, '../..');
const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
const vista = (rol, contextos = '') => execFileSync(php, ['tests/Frontend/apoyo/usuarios-movil-vista.php', rol, contextos], { cwd: RAIZ, encoding: 'utf8', env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '' } });
const leer = archivo => fs.readFileSync(path.join(RAIZ, archivo), 'utf8');

const aviso = (id, tipo, leida = 0, extra = {}) => ({ id, tipo, titulo: 'Aviso ' + id, mensaje: 'Mensaje ' + id, leida, enlace: null, fecha_creacion: '2026-10-27 08:00:00', ...extra });

test('R cabecera: sin timbre ni amarillos, sin onclick generado y título común', () => {
    const css = leer('public/css/dashboard-modules/layout.css');
    const js = leer('public/js/dashboard.js');
    const parcial = leer('views/partials/cabecera_cuenta.php');
    assert.doesNotMatch(css, /ringing|bell-ring|badge-ping/);
    assert.doesNotMatch(js, /ringing|onclick=/);
    assert.doesNotMatch(parcial, /Alertas Cl/);
    assert.match(parcial, /<h3 id="notifTitulo">Notificaciones<\/h3>/);
    const bloque = css.slice(css.indexOf('/* Notificaciones — mockup aprobado'), css.indexOf('/* Cards and Tables */'));
    assert.doesNotMatch(bloque, /#[0-9a-f]{3,6}\b|rgba?\(/i, 'Colores de las notificaciones solo con --z-*');
    // Citas y Pacientes comparten la regla del icono del título con Usuarios.
    assert.match(leer('public/css/usuarios.css'), /\.directorio-cabecera \.users-page-title > i \{[^}]*color: var\(--z-primary\);/);
    for (const archivo of ['views/admin/partials/cabecera_citas.php', 'views/admin/partials/cabecera_usuarios.php', 'views/vet/partials/cabecera_pacientes.php']) {
        assert.match(leer(archivo), /directorio-cabecera/, archivo);
    }
});

test('R cabecera: contador, ventana, avisos y pastilla de la clínica en admin y vet', { skip: !buscarChrome(), timeout: 90000 }, async () => {
    const paginas = { admin: vista('admin'), vet: vista('vet'), uno: vista('admin', 'uno') };
    const estado = { lista: [], noLeidas: 0 };
    const llamadas = [];
    const servidor = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            const html = paginas[url.searchParams.get('rol') || 'admin'].replace(/<script[^>]+src="https:[^>]*><\/script>/g, '').replace(/<link[^>]+href="https:[^>]*>/g, '');
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html);
            return;
        }
        if (url.pathname === '/index.php') {
            res.setHeader('Content-Type', 'application/json');
            const accion = url.searchParams.get('action');
            if (req.method === 'POST') {
                let cuerpo = '';
                req.on('data', parte => { cuerpo += parte; });
                req.on('end', () => {
                    llamadas.push({ accion, cuerpo });
                    if (accion === 'marcar_todas_notificaciones_leidas_ajax') {
                        estado.lista = estado.lista.map(n => ({ ...n, leida: 1 }));
                        estado.noLeidas = 0;
                    }
                    res.end(JSON.stringify({ success: true }));
                });
                return;
            }
            res.end(JSON.stringify(accion === 'get_notificaciones_ajax' ? { success: true, no_leidas: estado.noLeidas, notificaciones: estado.lista } : { success: true }));
            return;
        }
        const base = path.join(RAIZ, 'public');
        const archivo = path.resolve(base, '.' + url.pathname);
        if (!archivo.startsWith(base + path.sep) || !fs.existsSync(archivo)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', archivo.endsWith('.css') ? 'text/css' : archivo.endsWith('.js') ? 'application/javascript' : 'image/png');
        res.end(fs.readFileSync(archivo));
    });
    await new Promise(r => servidor.listen(0, '127.0.0.1', r));
    const nav = await abrirChrome(buscarChrome());
    const base = 'http://127.0.0.1:' + servidor.address().port;
    const recargarAvisos = async (lista, noLeidas) => {
        estado.lista = lista;
        estado.noLeidas = noLeidas;
        await nav.evaluar('loadSystemNotifications()');
    };
    try {
        for (const rol of ['admin', 'vet']) {
            estado.lista = [];
            estado.noLeidas = 0;
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false });
            await nav.enviar('Page.navigate', { url: base + '/?rol=' + rol });
            await nav.esperar("document.readyState === 'complete' && !!document.querySelector('#pendingVaccinesList .notif-empty')");

            // Campana: gris sin nuevas, número exacto de 1 a 9 y «9+» desde 10, sin timbre.
            for (const [cantidad, texto] of [[0, null], [1, '1'], [5, '5'], [9, '9'], [10, '9+'], [27, '9+']]) {
                await recargarAvisos([aviso(1, 'NUEVA_CITA', cantidad ? 0 : 1)], cantidad);
                const campana = await nav.evaluar(`(() => {
                    const b = document.getElementById('notifBadge');
                    const bell = document.getElementById('notifBell');
                    return { oculto: b.hidden && b.getClientRects().length === 0, texto: b.textContent, etiqueta: bell.getAttribute('aria-label'), ringing: bell.classList.contains('ringing'), sinLeer: document.getElementById('notifCount').textContent, color: getComputedStyle(b).backgroundColor };
                })()`);
                assert.equal(campana.ringing, false);
                assert.equal(campana.sinLeer, `${cantidad} sin leer`);
                if (texto === null) {
                    assert.equal(campana.oculto, true, 'Sin nuevas no hay contador');
                    assert.equal(campana.etiqueta, 'Notificaciones, ninguna nueva');
                    continue;
                }
                assert.equal(campana.oculto, false);
                assert.equal(campana.texto, texto);
                assert.equal(campana.color, 'rgb(239, 68, 68)', 'Contador en --z-danger');
                assert.equal(campana.etiqueta, `Notificaciones, ${cantidad} ${cantidad === 1 ? 'nueva' : 'nuevas'}`);
            }

            // Tres piezas de 40 px; avatar sin nombre ni flecha; título común.
            const piezas = await nav.evaluar(`(() => {
                const medir = n => { const r = n.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height)]; };
                const avatar = document.querySelector('[data-cabecera-cuenta-boton]');
                return { clinica: medir(document.querySelector('[data-clinica-escritorio] .cabecera-clinica'))[1], campana: medir(document.getElementById('notifBell')), avatar: medir(avatar), redondo: getComputedStyle(avatar).borderRadius, fondo: getComputedStyle(avatar).backgroundColor, textoAvatar: avatar.innerText.trim(), rolVisible: document.querySelector('[data-clinica-escritorio] .cabecera-clinica__texto > span').getClientRects().length > 0, etiquetaCuenta: avatar.getAttribute('aria-label'), titulo: document.getElementById('notifTitulo').textContent };
            })()`);
            assert.deepEqual({ clinica: piezas.clinica, campana: piezas.campana, avatar: piezas.avatar }, { clinica: 40, campana: [40, 40], avatar: [40, 40] });
            assert.equal(piezas.redondo, '50%');
            assert.equal(piezas.fondo, 'rgb(0, 82, 255)');
            assert.equal(piezas.textoAvatar, rol === 'admin' ? 'AN' : 'BN', 'Solo las iniciales');
            assert.equal(piezas.rolVisible, false, 'El rol se lee en el menú de cuenta');
            assert.match(piezas.etiquetaCuenta, /^Mi cuenta: /);
            assert.equal(piezas.titulo, 'Notificaciones');

            // El encabezado del menú de cuenta muestra nombre, clínica y rol.
            await nav.evaluar("document.querySelector('[data-cabecera-cuenta-boton]').click(); true");
            const identidad = await nav.evaluar("document.querySelector('.cabecera-cuenta__identidad').innerText");
            assert.match(identidad, rol === 'admin' ? /Ana Norte/ : /Beto Norte/);
            assert.match(identidad, /Clínica Norte \(prueba\) · /);
            await nav.evaluar("document.querySelector('[data-cabecera-cuenta]').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true})); true");

            // Color: azul suave en todo, rojo solo para CITA_CANCELADA y LIMITE_PLAN.
            const tipos = ['NUEVA_CITA', 'CITA_REPROGRAMADA', 'CITA_CANCELADA', 'CITA_REASIGNADA', 'REPROGRAMAR_NECESARIO', 'PROPUESTA_REASIGNACION_ADMIN', 'LIMITE_PLAN', 'ausencia', 'ATENCION_ABIERTA', 'ATENCION_SIN_CERRAR', 'OTRO'];
            await recargarAvisos(tipos.map((tipo, i) => aviso(i + 1, tipo, i % 2)), 6);
            const colores = await nav.evaluar(`[...document.querySelectorAll('#pendingVaccinesList .notif-item')].map(n => ({ tipo: n.querySelector('.notif-item__title').textContent, grave: n.querySelector('.notif-item__icon').classList.contains('is-grave'), color: getComputedStyle(n.querySelector('.notif-item__icon')).color }))`);
            assert.equal(colores.length, tipos.length);
            colores.forEach((c, i) => {
                const grave = ['CITA_CANCELADA', 'LIMITE_PLAN'].includes(tipos[i]);
                assert.equal(c.grave, grave, tipos[i]);
                assert.equal(c.color, grave ? 'rgb(185, 28, 28)' : 'rgb(0, 82, 255)', tipos[i]);
            });
            assert.equal(await nav.evaluar("document.querySelectorAll('#notifDropdown [onclick], #notifDropdown .notif-header span').length"), 0, 'Sin onclick ni pastilla «N nuevas»');
            assert.equal(await nav.evaluar("document.querySelectorAll('#pendingVaccinesList .notif-item.is-unread .notif-item__dot').length"), 6);

            // Escape de título y mensaje escritos por usuarios.
            const malicioso = '<img src=x onerror="window.__xss=1">';
            await recargarAvisos([aviso(70, 'NUEVA_CITA', 0, { titulo: malicioso, mensaje: '<script>window.__xss=2<\/script>"\'&', enlace: 'index.php?action=x&"y' })], 1);
            const escape = await nav.evaluar(`(() => {
                const fila = document.querySelector('#pendingVaccinesList .notif-item');
                return { imagenes: fila.querySelectorAll('img,script').length, titulo: fila.querySelector('.notif-item__title').textContent, mensaje: fila.querySelector('.notif-item__msg').textContent, enlace: fila.dataset.notifEnlace, xss: window.__xss === undefined };
            })()`);
            assert.deepEqual(escape, { imagenes: 0, titulo: malicioso, mensaje: '<script>window.__xss=2</script>"\'&', enlace: 'index.php?action=x&"y', xss: true });

            // Clic en un aviso: un solo oyente de la lista marca ese id (sin enlace, recarga la lista).
            await recargarAvisos([aviso(41, 'NUEVA_CITA', 0), aviso(42, 'CITA_CANCELADA', 0)], 2);
            await nav.evaluar("document.getElementById('notifBell').click(); true");
            assert.equal(await nav.evaluar("document.getElementById('notifBell').getAttribute('aria-expanded')"), 'true');
            await nav.evaluar("document.querySelector('[data-notif-id=\"42\"] .notif-item__msg').click(); true");
            for (let i = 0; i < 50 && !llamadas.some(l => l.accion === 'marcar_notificacion_leida_ajax'); i++) await new Promise(r => setTimeout(r, 50));
            const marcada = llamadas.filter(l => l.accion === 'marcar_notificacion_leida_ajax').pop();
            assert.match(marcada.cuerpo, /name="id"\r\n\r\n42\r\n/);
            assert.equal(await nav.evaluar("document.getElementById('notifDropdown').classList.contains('active')"), true, 'La ventana sigue abierta tras marcar sin enlace');

            // Marcar todas: misma acción, contador a 0 y botón oculto.
            const panel = await nav.evaluar(`(() => { const r = document.getElementById('notifDropdown').getBoundingClientRect(); const b = document.getElementById('notifMarkAll'); return { ancho: Math.round(r.width), dentro: r.left >= 0 && r.right <= innerWidth, visible: !b.hidden, boton: [b.offsetWidth, b.offsetHeight], etiqueta: b.getAttribute('aria-label'), titulo: b.title, texto: b.innerText.trim() }; })()`);
            assert.deepEqual(panel, { ancho: 380, dentro: true, visible: true, boton: [36, 36], etiqueta: 'Marcar todas como leídas', titulo: 'Marcar todas como leídas', texto: '' });
            await nav.evaluar("document.getElementById('notifMarkAll').click(); true");
            await nav.esperar("document.getElementById('notifBadge').hidden && document.getElementById('notifMarkAll').hidden");
            assert.ok(llamadas.some(l => l.accion === 'marcar_todas_notificaciones_leidas_ajax'));
            assert.equal(await nav.evaluar("document.getElementById('notifCount').textContent"), '0 sin leer');
            assert.equal(await nav.evaluar("document.getElementById('notifDropdown').classList.contains('active')"), true, 'Marcar todas no cierra la ventana');

            // Vacío.
            await recargarAvisos([], 0);
            assert.equal(await nav.evaluar("document.querySelector('#pendingVaccinesList .notif-empty').innerText.trim()"), 'No tienes notificaciones');
            assert.equal(await nav.evaluar("!!document.querySelector('#pendingVaccinesList .notif-empty .bi-bell-slash')"), true);
            await nav.evaluar("document.body.click(); true");
            assert.equal(await nav.evaluar("document.getElementById('notifBell').getAttribute('aria-expanded')"), 'false', 'Clic fuera cierra');

            // Icono del título del módulo en azul (Usuarios; Citas y Pacientes usan la misma regla).
            if (rol === 'admin') assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.users-page-title > i')).color"), 'rgb(0, 82, 255)');

            // Anchos: sin desborde; en el celular, hoja inferior con el mismo contenido.
            await recargarAvisos([aviso(51, 'LIMITE_PLAN', 0, { mensaje: 'Un mensaje largo '.repeat(12) }), aviso(52, 'NUEVA_CITA', 1)], 1);
            for (const [width, height] of [[1366, 768], [820, 1180], [390, 844], [360, 800]]) {
                await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
                await nav.evaluar("Promise.all(document.querySelector('.pill-sidebar').getAnimations().map(a=>a.finished.catch(()=>{})))");
                assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, `${rol} a ${width}`);
                await nav.evaluar("document.getElementById('notifBell').click(); true");
                const abierta = await nav.evaluar(`(() => {
                    const hoja = document.getElementById('notifDialogo');
                    const caja = (hoja.open ? hoja : document.getElementById('notifDropdown')).getBoundingClientRect();
                    const campana = document.getElementById('notifBell').getBoundingClientRect();
                    return { hoja: hoja.open, contenido: !!(hoja.open ? hoja : document.getElementById('notifDropdown')).querySelector('#pendingVaccinesList .notif-item'), dentro: caja.left >= 0 && caja.right <= innerWidth + 1, inferior: Math.abs(caja.bottom - innerHeight) <= 1, campana: [Math.round(campana.width), Math.round(campana.height)], clinica: document.querySelector('.top-header [data-clinica-cuenta]')?.getClientRects().length > 0, sinDesborde: document.documentElement.scrollWidth <= innerWidth };
                })()`);
                assert.equal(abierta.hoja, width < 768, `hoja a ${width}`);
                assert.equal(abierta.contenido, true);
                assert.equal(abierta.dentro, true, `${rol} ventana a ${width}`);
                assert.equal(abierta.sinDesborde, true);
                assert.deepEqual(abierta.campana, [40, 40]);
                assert.equal(abierta.clinica, width >= 768, 'En el celular la clínica va dentro de la hoja de cuenta');
                if (width < 768) {
                    assert.equal(abierta.inferior, true, 'Hoja inferior');
                    assert.equal(await nav.evaluar("document.querySelector('#notifDialogo .notif-mark-all').offsetWidth"), 44);
                    await nav.evaluar("document.querySelector('#notifDialogo .zk-hoja__asa').click(); true");
                    assert.equal(await nav.evaluar("document.getElementById('notifDialogo').open"), false, 'El asa cierra la hoja');
                    await nav.esperar("document.getElementById('notifBell').getAttribute('aria-expanded') === 'false'");
                } else {
                    await nav.evaluar("document.querySelector('[data-cabecera-cuenta]').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true})); true");
                    assert.equal(await nav.evaluar("document.getElementById('notifDropdown').classList.contains('active')"), false, 'Esc cierra');
                }
            }
        }

        // Pastilla: enlace con flecha solo si puede cambiar de contexto.
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false });
        for (const [pagina, cambia] of [['admin', true], ['uno', false]]) {
            await nav.enviar('Page.navigate', { url: base + '/?rol=' + pagina });
            await nav.esperar("document.readyState === 'complete' && !!document.querySelector('[data-clinica-cuenta]')");
            const pastilla = await nav.evaluar(`(() => { const p = document.querySelector('[data-clinica-cuenta]'); return { etiqueta: p.tagName, href: p.getAttribute('href'), flecha: !!p.querySelector('.bi-chevron-down'), nombre: p.querySelector('strong').textContent }; })()`);
            assert.deepEqual(pastilla, cambia
                ? { etiqueta: 'A', href: 'index.php?action=seleccionar_contexto', flecha: true, nombre: 'Clínica Norte (prueba)' }
                : { etiqueta: 'SPAN', href: null, flecha: false, nombre: 'Clínica Norte (prueba)' });
        }
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        servidor.closeAllConnections();
        await new Promise(r => servidor.close(r));
    }
});
