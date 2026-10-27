const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const RAIZ = path.resolve(__dirname, '../..');
const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
const vista = rol => execFileSync(php, ['tests/Frontend/apoyo/usuarios-movil-vista.php', rol], { cwd: RAIZ, encoding: 'utf8', env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '' } });

test('R Usuarios y cabecera: filas, acciones permitidas, cuenta de ambos roles e iconos', { skip: !buscarChrome(), timeout: 45000 }, async () => {
    const htmlRoles = { admin: vista('admin'), vet: vista('vet') };
    const llamadas = [];
    const servidor = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            let html = htmlRoles[url.searchParams.get('rol') || 'admin'];
            if (fs.existsSync(path.join(RAIZ, 'scratch/usuarios-mockup/bootstrap-icons.css'))) html = html.replace('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css', '/__bootstrap.css');
            html = html.replace(/<script[^>]+src="https:[^>]*><\/script>/g, '').replace(/<link[^>]+href="https:[^>]*>/g, '');
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html);
            return;
        }
        if (url.pathname === '/index.php') {
            res.setHeader('Content-Type', 'application/json');
            if (req.method === 'POST') {
                let cuerpo = '';
                req.on('data', parte => { cuerpo += parte; });
                req.on('end', () => {
                    llamadas.push({ accion: url.searchParams.get('action'), cuerpo });
                    res.end(JSON.stringify({ success: true, message: 'Solicitud de prueba recibida.' }));
                });
            } else res.end(JSON.stringify({ success: true, notificaciones: [] }));
            return;
        }
        if (url.pathname === '/__bootstrap.css') {
            res.setHeader('Content-Type', 'text/css');
            res.end(fs.readFileSync(path.join(RAIZ, 'scratch/usuarios-mockup/bootstrap-icons.css'), 'utf8').replace(/url\([^)]*\)/g, 'url(/__bootstrap.woff2)'));
            return;
        }
        if (url.pathname === '/__bootstrap.woff2') {
            res.setHeader('Content-Type', 'font/woff2');
            res.end(fs.readFileSync(path.join(RAIZ, 'scratch/usuarios-mockup/bootstrap-icons.woff2')));
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
    const capturar = async nombre => {
        if (process.env.ZOOKI_CAPTURAR_USUARIOS !== '1' && process.env.ZOOKI_CAPTURAR_RETOQUE !== '1') return;
        if (process.env.ZOOKI_CAPTURAR_RETOQUE === '1' && !['390-personal', '390-clientes', '390-invitacion', '390-nuevo'].includes(nombre)) return;
        await nav.evaluar("Promise.all([...document.querySelectorAll('.users-modal__panel,.tab-panel')].flatMap(n=>n.getAnimations()).map(a=>a.finished.catch(()=>{})))");
        const carpeta = path.join(RAIZ, 'scratch/r/usuarios-cabecera/despues');
        fs.mkdirSync(carpeta, { recursive: true });
        const captura = await nav.enviar('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(path.join(carpeta, nombre + '.png'), Buffer.from(captura.data, 'base64'));
    };
    try {
        for (const rol of ['admin', 'vet']) {
            await nav.enviar('Page.navigate', { url: base + '/?rol=' + rol });
            await nav.esperar("document.readyState === 'complete' && !!window.zookiAbrirDialogo");
            for (const [width, height] of [[1366, 768], [820, 1180], [390, 844], [360, 800]]) {
                await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
                await nav.evaluar("Promise.all(document.querySelector('.pill-sidebar').getAnimations().map(a=>a.finished.catch(()=>{})))");
                assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, rol + ' a ' + width);
                if (width < 768) {
                    assert.equal(await nav.evaluar("[...document.querySelectorAll('.top-header .users-module-desc,.top-header .module-title>p,.top-header .encabezado-pagina__subtitulo,.cabecera-clinica-movil')].every(n=>n.getClientRects().length===0)"), true, 'Sin párrafos de contexto bajo el título móvil');
                }
                await nav.evaluar("document.querySelector('[data-cabecera-cuenta-boton]').click(); true");
                const cuenta = await nav.evaluar(`(() => {
                    const raiz = document.querySelector('[data-cabecera-cuenta]');
                    const menu = raiz.querySelector('[data-cuenta-menu]');
                    const rect = menu.getBoundingClientRect();
                    const ventana = raiz.querySelector('dialog').getBoundingClientRect();
                    return { abierto: raiz.querySelector('[data-cabecera-cuenta-boton]').getAttribute('aria-expanded'), hoja: raiz.querySelector('dialog').open, inferior: Math.abs(ventana.bottom-innerHeight)<=1, dentro: rect.left >= 0 && rect.right <= innerWidth + 1, ruta: menu.querySelector('[data-cuenta-destino]').getAttribute('href'), salir: !!menu.querySelector('[href="index.php?action=logout"]'), cambiar: !!raiz.querySelector('[href="index.php?action=seleccionar_contexto"]') };
                })()`);
                assert.equal(cuenta.abierto, 'true');
                assert.equal(cuenta.hoja, width < 768);
                if (width < 768) assert.equal(cuenta.inferior, true, 'La cuenta se muestra como hoja inferior');
                assert.equal(cuenta.dentro, true);
                assert.equal(cuenta.salir, true);
                assert.equal(cuenta.cambiar, true);
                assert.match(cuenta.ruta, rol === 'admin' ? /admin_configuracion#cuenta/ : /mi_perfil/);
                if (width === 390 || width === 1366) await capturar(rol + '-' + width + '-cuenta');
                if (width < 768) {
                    assert.equal(await nav.evaluar("document.querySelector('#cabeceraCuentaDialogo .zk-dialogo__cerrar') === null"), true);
                    await nav.evaluar("document.querySelector('#cabeceraCuentaDialogo .zk-hoja__asa').click(); true");
                    assert.equal(await nav.evaluar("document.getElementById('cabeceraCuentaDialogo').open"), false, 'El asa cierra la cuenta');
                } else await nav.evaluar("document.querySelector('[data-cabecera-cuenta]').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true})); true");
                if (rol !== 'admin') continue;
                const distribucion = await nav.evaluar(`(() => {
                    const pestanas = document.querySelector('.tabs-wrapper');
                    const nuevo = document.querySelector('#usuariosModulo [data-accion=nuevo]');
                    return { tituloUnico: document.querySelectorAll('h1').length === 1, arriba: !!pestanas.closest('.top-header'), nuevoEnBarra: !!nuevo.closest('[data-usuarios-barra-acciones]') };
                })()`);
                assert.deepEqual(distribucion, { tituloUnico: true, arriba: width >= 768, nuevoEnBarra: true });
                const filas = await nav.evaluar(`(() => {
                    const nodos = [...document.querySelectorAll('[data-panel=personal] [data-vista-contenido=tarjetas] [data-fila]')];
                    return { vista: document.getElementById('usuariosModulo').dataset.vista, alto: Math.max(...nodos.map(n=>n.getBoundingClientRect().height)), desborde: nodos.some(n=>n.getBoundingClientRect().right>innerWidth), contador: document.querySelector('[data-tab=personal] .usuarios-tab-conteo').textContent };
                })()`);
                if (width < 768) {
                    const botonAlta = await nav.evaluar(`(() => {
                        const boton = document.querySelector('.usuarios-nuevo-movil');
                        const rect = boton.getBoundingClientRect();
                        return { texto: boton.textContent, nombre: boton.getAttribute('aria-label'), ancho: rect.width, alto: rect.height, letra: getComputedStyle(boton).fontSize };
                    })()`);
                    assert.deepEqual(botonAlta, { texto: '+', nombre: 'Nuevo usuario', ancho: 40, alto: 40, letra: '26px' });
                    assert.equal(filas.vista, 'tarjetas');
                    assert.ok(filas.alto <= 72, JSON.stringify(filas));
                    assert.equal(filas.desborde, false);
                    await capturar(width + '-personal');
                    const busqueda = await nav.evaluar(`(() => {
                        const campo = document.querySelector('[data-filtros=personal] [data-filtro-texto]');
                        campo.value = 'Beto';
                        campo.dispatchEvent(new Event('input', { bubbles: true }));
                        const nombres = [...document.querySelectorAll('[data-panel=personal] [data-vista-contenido=tarjetas] [data-fila]:not([hidden]) h3')].map(n=>n.textContent.trim());
                        campo.value = '';
                        campo.dispatchEvent(new Event('input', { bubbles: true }));
                        return nombres;
                    })()`);
                    assert.deepEqual(busqueda, ['Beto Norte']);
                    await nav.evaluar("document.querySelector('[data-panel=personal] .person-card[data-id-usuario=\"1\"] h3').click(); true");
                    assert.equal(await nav.evaluar("document.querySelector('#usuarioAccionesMovil [data-accion=estado]').disabled"), true, 'No se desactiva a sí mismo');
                    assert.equal(await nav.evaluar("document.querySelector('#usuarioAccionesMovil .zk-dialogo__cerrar') === null"), true, 'Sin X adicional en la hoja de persona');
                    await nav.evaluar("document.querySelector('#usuarioAccionesMovil .zk-hoja__asa').click(); true");
                    assert.equal(await nav.evaluar("document.getElementById('usuarioAccionesMovil').open"), false, 'El asa cierra los datos');
                    await nav.evaluar("document.querySelector('[data-id-invitacion] h3').click(); true");
                    assert.deepEqual(await nav.evaluar("[...document.querySelectorAll('#usuarioAccionesMovil button[data-accion]')].map(b=>b.dataset.accion)"), ['reenviar-invitacion', 'cancelar-invitacion']);
                    if (width === 390) await capturar('390-invitacion');
                    await nav.evaluar("document.querySelector('#usuarioAccionesMovil [data-accion=reenviar-invitacion]').click(); true");
                    await nav.esperar("!document.getElementById('usuarioAccionesMovil').open");
                    await nav.evaluar("document.querySelector('[data-tab=clientes]').click(); document.querySelector('[data-panel=clientes] .person-card[data-id-usuario=\"6\"] h3').click(); true");
                    const privado = await nav.evaluar("({datos:document.querySelector('#usuarioAccionesMovil .usuario-acciones-movil__datos').hidden,acciones:[...document.querySelectorAll('#usuarioAccionesMovil button[data-accion]')].map(b=>b.dataset.accion)})");
                    assert.deepEqual(privado, { datos: true, acciones: ['ver-cliente'] });
                    await nav.evaluar("document.getElementById('usuarioAccionesMovil').close(); true");
                    if (width === 390) await capturar('390-clientes');
                    await nav.evaluar("document.querySelector('.usuarios-nuevo-movil').click(); true");
                    const alta = await nav.evaluar(`(() => { const d=document.getElementById('usuarioModal'); const r=d.querySelector('.modal-content').getBoundingClientRect(); return {abierta:d.classList.contains('is-open'),dentro:r.left>=0&&r.right<=innerWidth+1,pie:r.bottom<=innerHeight+1,texto:d.querySelector('[data-texto-guardar]').textContent,tipo:document.getElementById('usuarioTipoDoc').selectedOptions[0].textContent}; })()`);
                    assert.deepEqual(alta, { abierta: true, dentro: true, pie: true, texto: 'Enviar invitación', tipo: 'CC' });
                    assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('#usuarioModal .close-modal')).borderTopStyle"), 'solid', 'El formulario conserva la X con borde del mockup');
                    if (width === 390) await capturar('390-nuevo');
                    await nav.evaluar("document.querySelector('#usuarioModal [data-accion=cerrar]').click(); document.querySelector('[data-tab=personal]').click(); true");
                }
                if (width === 1366) {
                    await nav.evaluar("document.querySelector('[data-tab=clientes]').click(); true");
                    assert.equal(await nav.evaluar("document.querySelector('[data-tab=personal]').getAttribute('aria-selected')"), 'false');
                    assert.equal(await nav.evaluar("document.querySelector('[data-panel=clientes]').hidden"), false);
                    await nav.evaluar("document.querySelector('[data-tab=personal]').click(); true");
                    await capturar('1366-personal');
                }
            }
            if (rol === 'vet') assert.equal(await nav.evaluar("document.getElementById('usuariosModulo')"), null);
        }
        assert.ok(llamadas.some(n=>n.accion==='reenviar_invitacion_ajax' && /11/.test(n.cuerpo)));
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        servidor.closeAllConnections();
        await new Promise(r => servidor.close(r));
    }
});
