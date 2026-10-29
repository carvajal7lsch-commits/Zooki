const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const http = require('node:http');
const { spawn, execFileSync } = require('node:child_process');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { errorCampo } = require('../../public/js/configuracion-clinica.js');
const { errorArchivo } = require('../../public/js/logo-clinica.js');
const RAIZ = path.resolve(__dirname, '../..');
const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');

test('minutos con topes y logo con peso y formatos aprobados', () => {
    const reglas = { duracionMax: 720, margenMax: 120 };
    for (const [campo, valor] of [['duracion_minutos', '720'], ['margen_minutos', '0'], ['margen_minutos', '120']]) assert.equal(errorCampo(campo, valor, reglas), '');
    for (const [campo, valor] of [['duracion_minutos', '721'], ['margen_minutos', '121'], ['duracion_minutos', '150000']]) assert.notEqual(errorCampo(campo, valor, reglas), '');
    for (const type of ['image/jpeg', 'image/png', 'image/webp']) assert.equal(errorArchivo({ type, size: 5242880 }, 5242880), '');
    assert.match(errorArchivo({ type: 'image/svg+xml', size: 12 }, 5242880), /JPG, PNG o WebP/);
    assert.match(errorArchivo({ type: 'image/png', size: 5242881 }, 5242880), /5 MB/);
});

test('HTTP real: logo de 5 MB, tipo real, CSRF, clínica, cancelación y rollback', async () => {
    const temporal = fs.mkdtempSync(path.join(os.tmpdir(), 'zooki-logo-http-'));
    const anterior = path.join(temporal, 'anterior.png');
    const png = fs.readFileSync(path.join(RAIZ, 'public/img/icon_blue.png'));
    fs.writeFileSync(anterior, png);
    const reserva = http.createServer();
    await new Promise(r => reserva.listen(0, '127.0.0.1', r));
    const puerto = reserva.address().port;
    await new Promise(r => reserva.close(r));
    const proceso = spawn(php, ['-d', 'upload_max_filesize=5M', '-d', 'post_max_size=8M', '-S', '127.0.0.1:' + puerto, '-t', 'tests/Frontend/apoyo'], {
        cwd: RAIZ,
        stdio: ['ignore', 'ignore', 'ignore'],
        env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_PRUEBA_LOGOS_DIR: temporal }
    });
    const url = 'http://127.0.0.1:' + puerto + '/ajustes-guardar-marca.php';
    const enviar = async (archivo, parametros = '', campos = {}) => {
        const form = new FormData();
        for (const [nombre, valor] of Object.entries({ nombre: 'Clínica Prueba', direccion: '', telefono: '+573001234567', telefono_urgencias: '', email_contacto: 'clinica@example.com', url_logo: 'uploads/logos/anterior.png', id_clinica: '1', csrf_token: 'c'.repeat(64), ...campos })) form.set(nombre, valor);
        if (archivo) form.set('logo_archivo', new Blob([archivo], { type: 'image/png' }), '../../archivo.php');
        const respuesta = await fetch(url + parametros, { method: 'POST', body: form });
        return { estado: respuesta.status, datos: await respuesta.json() };
    };
    try {
        let iniciado = false;
        for (let intento = 0; intento < 60 && !iniciado; intento++) {
            iniciado = await fetch(url).then(() => true).catch(() => false);
            if (!iniciado) await new Promise(r => setTimeout(r, 50));
        }
        assert.equal(iniciado, true, 'Arranca el servidor efímero de la prueba');
        let r = await enviar(null);
        assert.equal(r.estado, 200, JSON.stringify(r));
        assert.equal(r.datos.marca.url_logo, 'uploads/logos/anterior.png', 'Sin archivo conserva el logo');
        assert.equal(fs.readdirSync(temporal).length, 1);
        for (const [parametros, campos] of [['', { csrf_token: 'incorrecto' }], ['', { id_clinica: '2' }], ['?rol=vet', {}]]) {
            r = await enviar(png, parametros, campos);
            assert.equal(r.estado, 403);
            assert.equal(fs.readdirSync(temporal).length, 1, 'No escribe un logo sin permisos');
        }
        r = await enviar(Buffer.from('Esto no es una imagen'));
        assert.equal(r.estado, 422);
        assert.equal(r.datos.campo, 'logo_archivo');
        r = await enviar(Buffer.alloc(5242881));
        assert.equal(r.estado, 422, 'PHP y el helper rechazan más de 5 MB');
        assert.equal(fs.readdirSync(temporal).length, 1);
        r = await enviar(png, '?fallo_auditoria=1');
        assert.equal(r.estado, 500);
        assert.deepEqual(fs.readdirSync(temporal), ['anterior.png'], 'El rollback retira solo el archivo nuevo');
        const limite = Buffer.concat([png, Buffer.alloc(5242880 - png.length)]);
        r = await enviar(limite);
        assert.equal(r.estado, 200, JSON.stringify(r));
        assert.match(r.datos.marca.url_logo, /^uploads\/logos\/clinica-1-[a-f0-9]{32}\.png$/);
        assert.equal(fs.statSync(path.join(temporal, path.basename(r.datos.marca.url_logo))).size, 5242880);
        for (const extension of ['jpg', 'webp']) {
            r = await enviar(fs.readFileSync(path.join(RAIZ, 'tests/Fixtures/logos/imagen.' + extension)));
            assert.equal(r.estado, 200, JSON.stringify(r));
            assert.equal(path.extname(r.datos.marca.url_logo), '.' + extension, 'El tipo sale del contenido real, aunque el cliente declare PNG');
        }
        assert.deepEqual(fs.readFileSync(anterior), png, 'No modifica el logo anterior');
    } finally {
        const salida = proceso.exitCode !== null ? Promise.resolve() : new Promise(r => proceso.once('exit', r));
        proceso.kill();
        await salida;
        assert.equal(path.dirname(temporal), path.resolve(os.tmpdir()));
        fs.rmSync(temporal, { recursive: true, force: true });
    }
});

test('Chrome: formularios reales sin desborde, mensaje único, arrastre y selección de imagen', { skip: !buscarChrome() }, async () => {
    const html = execFileSync(php, ['tests/Frontend/apoyo/ajustes-vista.php'], { cwd: RAIZ, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '' } });
    const srv = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/index.php') {
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify({ success: true, horarios: [], aviso: '' }));
            return;
        }
        if (url.pathname === '/') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html.replace(/<script[^>]+src="https:[^>]*><\/script>/g, '').replace(/<link[^>]+href="https:[^>]*>/g, ''));
            return;
        }
        const archivo = path.resolve(RAIZ, 'public', '.' + url.pathname);
        if (!archivo.startsWith(path.join(RAIZ, 'public') + path.sep) || !fs.existsSync(archivo)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', archivo.endsWith('.js') ? 'application/javascript' : archivo.endsWith('.css') ? 'text/css' : 'image/png');
        res.end(fs.readFileSync(archivo));
    });
    await new Promise(r => srv.listen(0, '127.0.0.1', r));
    const nav = await abrirChrome(buscarChrome());
    try {
        for (const [width, height] of [[360, 800], [390, 844], [820, 1180], [1366, 768]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
            await nav.enviar('Page.navigate', { url: 'http://127.0.0.1:' + srv.address().port + '/?ancho=' + width + '#clinica' });
            await nav.esperar(`location.search === '?ancho=${width}' && document.readyState === 'complete' && !!document.querySelector('[data-ajustes]').dataset.ajustesVista`);
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, 'Panel a ' + width);
            await nav.evaluar("document.querySelector('[data-nuevo-tipo]').click(); true");
            assert.equal(await nav.evaluar("[...document.querySelectorAll('#dialogoTipo input:not([type=hidden])')].every(c => c.placeholder)"), true);
            const error = await nav.evaluar(`(() => {
                const c = document.querySelector('[name=duracion_minutos]');
                c.value = '721';
                c.dispatchEvent(new Event('change'));
                return { ayuda: document.getElementById('tipo-ayuda-duracion_minutos').hidden, error: document.getElementById('tipo-error-duracion_minutos').textContent, alineado: getComputedStyle(document.getElementById('tipo-error-duracion_minutos')).textAlign };
            })()`);
            assert.deepEqual(error, { ayuda: true, error: 'La duración debe ser de 1 a 720 minutos.', alineado: 'left' });
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, 'Modal tipo a ' + width);
            await nav.evaluar("document.querySelector('#dialogoTipo').close(); document.querySelector('[data-abrir-dialogo=dialogoMarca]').click(); true");
            assert.equal(await nav.evaluar("[...document.querySelectorAll('#dialogoMarca input:not([type=hidden]):not([type=file])')].every(c => c.placeholder)"), true);
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, 'Modal marca a ' + width);
            await nav.evaluar("document.querySelector('#dialogoMarca').close(); true");
        }
        await nav.evaluar("document.querySelector('[data-abrir-dialogo=dialogoMarca]').click(); true");
        for (const tipo of ['image/png', 'image/jpeg', 'image/webp']) {
            const contenido = await nav.evaluar(`(async () => {
                const lienzo = document.createElement('canvas');
                lienzo.width = 2;
                lienzo.height = 2;
                const blob = await new Promise(r => lienzo.toBlob(r, '${tipo}'));
                const archivo = new File([blob], 'logo', { type: '${tipo}' });
                const dt = new DataTransfer();
                dt.items.add(archivo);
                document.querySelector('[data-logo-soltar]').dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: dt }));
                return { tipo: document.querySelector('[data-logo-archivo]').files[0].type };
            })()`);
            assert.equal(contenido.tipo, tipo);
            await nav.esperar("!document.querySelector('[data-logo-vista]').hidden && document.querySelector('[data-logo-vista]').naturalWidth === 2");
        }
        await nav.evaluar("document.querySelector('[data-logo-cancelar]').click(); true");
        assert.equal(await nav.evaluar("document.querySelector('[data-logo-archivo]').files.length"), 0);
        const selector = await nav.enviar('DOM.getDocument');
        const campo = await nav.enviar('DOM.querySelector', { nodeId: selector.root.nodeId, selector: '[data-logo-archivo]' });
        await nav.enviar('DOM.setFileInputFiles', { nodeId: campo.nodeId, files: [path.join(RAIZ, 'public/img/icon_blue.png')] });
        await nav.esperar("document.querySelector('[data-logo-vista]').naturalWidth > 2");
        assert.equal(await nav.evaluar("document.querySelector('[data-logo-cancelar]').hidden"), false);
        const envio = await nav.evaluar(`(async () => {
            let recibido = null;
            const original = window.fetch;
            window.fetch = async (url, opciones) => {
                if (!String(url).includes('guardar_marca_clinica_ajax')) return original(url, opciones);
                recibido = { nombre: opciones.body.get('logo_archivo').name, bytes: opciones.body.get('logo_archivo').size };
                return { ok: false, json: async () => ({ success: false, campo: 'email_contacto', aviso: 'Corrige el correo.', message: 'Corrige el correo.' }) };
            };
            document.querySelector('[data-form-marca]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            for (let intento = 0; intento < 50 && !document.getElementById('marca-error-email_contacto').textContent; intento++) await new Promise(r => setTimeout(r, 10));
            return { recibido, error: document.getElementById('marca-error-email_contacto').textContent, pie: document.querySelector('[data-form-marca] [data-aviso]').textContent };
        })()`);
        assert.ok(envio.recibido.bytes > 0, 'FormData conserva la imagen seleccionada');
        assert.equal(envio.error, 'Corrige el correo.');
        assert.equal(envio.pie, '', 'No repite el error del campo en el pie');
        const guardado = await nav.evaluar(`(async () => {
            const llamadas = [];
            const telefono = document.querySelector('[name=telefono]');
            telefono.zookiTelefono = { setNumber(numero) { llamadas.push(numero); telefono.value = numero.replace(/^\\+57/, ''); } };
            window.fetch = async () => ({ ok: true, json: async () => ({ success: true, marca: { nombre: 'Marca guardada', telefono: '+573001234567', telefono_urgencias: '', email_contacto: '', direccion: '', url_logo: 'img/icon_blue.png' } }) });
            document.querySelector('[data-form-marca]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            for (let intento = 0; intento < 50 && document.querySelector('[data-marca-dato=nombre]').textContent !== 'Marca guardada'; intento++) await new Promise(r => setTimeout(r, 10));
            return { llamadas, visible: telefono.value, nombre: document.querySelector('[data-marca-dato=nombre]').textContent };
        })()`);
        assert.deepEqual(guardado, { llamadas: ['+573001234567'], visible: '3001234567', nombre: 'Marca guardada' }, 'El registro canónico actualiza la vista y el selector sin duplicar +57');
        await nav.esperar("document.querySelector('[data-logo-archivo]').files.length === 0");
        assert.equal(await nav.evaluar("document.querySelector('[data-logo-archivo]').files.length"), 0, 'Cancelar no conserva una subida pendiente');
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        srv.closeAllConnections();
        await new Promise(r => srv.close(r));
    }
});
