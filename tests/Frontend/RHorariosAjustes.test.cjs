const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const RAIZ = path.resolve(__dirname, '../..');

test('R Horarios: pestañas, filas móviles, guardados, solicitudes y ausencia de desborde', { skip: !buscarChrome() }, async () => {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
    const html = execFileSync(php, ['tests/Frontend/apoyo/ajustes-vista.php'], { cwd: RAIZ, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '' } });
    const horarios = [1, 2, 3, 4, 5, 6, 7].map(dia_semana => ({ dia_semana, activo: dia_semana <= 5 ? 1 : 0, bloque_morning_activo: 1, bloque_afternoon_activo: 1, bloque_morning_inicio: '08:00:00', bloque_morning_fin: '12:00:00', bloque_afternoon_inicio: '14:00:00', bloque_afternoon_fin: '18:00:00' }));
    const franjas = [1, 2, 3, 4, 5].map(dia_semana => ({ dia_semana, hora_inicio: '08:00:00', hora_fin: '12:00:00' }));
    const vista = { success: true, id_veterinario: 2, veterinarios: [{ id_usuario: 2, nombre_completo: 'Beto Norte' }], franjas, propuestas: [{ id_propuesta: 1, id_veterinario: 2, estado: 'pendiente', franjas: JSON.stringify(franjas), motivo_rechazo: null }], cierres: [], afectadas: [] };
    const escrituras = [];
    const srv = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/index.php') {
            const accion = url.searchParams.get('action');
            res.setHeader('Content-Type', 'application/json');
            if (req.method === 'POST') {
                let cuerpo = '';
                req.on('data', datos => { cuerpo += datos; });
                req.on('end', () => {
                    const campos = {};
                    const lineas = cuerpo.split('\r\n');
                    lineas.forEach((linea, i) => {
                        const campo = linea.match(/name="([^"]+)"/);
                        if (campo) campos[campo[1]] = lineas[i + 2];
                    });
                    escrituras.push({ accion, campos });
                    if (accion === 'aprobar_horario_vet_ajax') vista.propuestas[0].estado = 'aprobada';
                    if (accion === 'guardar_horario_vet_ajax') vista.franjas = JSON.parse(campos.franjas);
                    if (accion === 'guardar_horarios_clinica_ajax') {
                        horarios[0].bloque_morning_inicio = campos['horarios[1][morning_inicio]'] + ':00';
                    }
                    res.end(JSON.stringify(accion === 'guardar_horarios_clinica_ajax' ? { success: true, horarios } : { success: true, resultado: { vista } }));
                });
                return;
            }
            res.end(JSON.stringify(accion === 'get_coordinacion_horarios_ajax' ? vista : accion === 'listar_ausencias_ajax' ? { success: true, ausencias: [] } : { success: true, horarios }));
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
    const base = 'http://127.0.0.1:' + srv.address().port + '/';
    try {
        await nav.enviar('Page.navigate', { url: base + '#horarios' });
        await nav.esperar("document.readyState === 'complete' && document.querySelector('[data-horarios-tab]') && document.querySelector('[data-propuestas]').dataset.pendientes === '1' && document.getElementById('resumenDias').textContent === '5'");
        await nav.evaluar("window.__campoOriginal = document.querySelector('.horarios .morning-inicio'); true");
        for (const [width, height] of [[360, 800], [390, 844], [820, 1180], [1024, 768], [1366, 768]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
            await nav.esperar(`document.querySelectorAll('.ajustes-horarios__dia').length === ${width < 768 ? 14 : 0}`);
            assert.equal(await nav.evaluar("__campoOriginal === document.querySelector('.horarios .morning-inicio')"), true, 'Conserva el mismo control al cambiar de ancho');
            assert.equal(await nav.evaluar("document.querySelectorAll('.horarios-tabla input[type=time]').length"), 56, 'No duplica las entradas');
            for (const clave of ['clinica', 'equipo']) {
                await nav.evaluar(`document.querySelector('[data-horarios-tab=${clave}]').click(); true`);
                assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, `${clave} sin desborde a ${width}`);
                assert.equal(await nav.evaluar("[...document.querySelectorAll('.ajustes-horarios .block-picker-display')].filter(b=>b.getClientRects().length).every(b=>b.scrollWidth<=b.clientWidth+1)"), true, 'Los rangos caben dentro de su control');
                assert.equal(await nav.evaluar('location.hash'), '#horarios');
                if (width === 1366) {
                    assert.equal(await nav.evaluar("(() => { const p=document.querySelector('.ajustes-contenido'); return p.scrollHeight <= p.clientHeight + 1; })()"), true, clave + ' sin scroll interior');
                }
            }
        }
        const alineados = await nav.evaluar("Math.abs(document.querySelector('[data-veterinario]').getBoundingClientRect().top-document.querySelector('[data-horario-varios]').getBoundingClientRect().top)<2");
        assert.equal(alineados, true, 'Selector y aplicar a varios días en la misma fila');
        await nav.evaluar("document.querySelector('[data-abrir-dialogo=ajustesSolicitudesHorario]').click(); true");
        assert.equal(await nav.evaluar("document.getElementById('ajustesSolicitudesHorario').open"), true);
        await nav.evaluar("[...document.querySelectorAll('#ajustesSolicitudesHorario button')].find(b=>b.textContent==='Aprobar').click(); true");
        await nav.esperar("document.querySelector('[data-abrir-dialogo=ajustesSolicitudesHorario]').textContent.endsWith('0')");
        assert.equal(escrituras.at(-1).accion, 'aprobar_horario_vet_ajax');
        await nav.evaluar("document.querySelector('#ajustesSolicitudesHorario [data-cerrar-dialogo]').click(); document.querySelector('[data-horario-vet] button[type=submit]').click(); true");
        await nav.esperar("!document.querySelector('[data-horario-vet] button[type=submit]').disabled");
        const guardado = escrituras.find(e => e.accion === 'guardar_horario_vet_ajax');
        assert.deepEqual(JSON.parse(guardado.campos.franjas), franjas, 'Mismo contrato de franjas');
        await nav.evaluar("document.querySelector('[data-horarios-tab=clinica]').click(); document.querySelector('.horarios [data-dia=\"1\"] [data-turno=morning] .block-picker-display').click(); const campo=document.querySelector('.tp-open .bp-hour-start'); campo.value='09'; campo.dispatchEvent(new Event('input')); document.querySelector('.tp-open .tp-btn-ok').click(); true");
        await nav.esperar("document.getElementById('horariosEstado').dataset.estado === 'guardado'");
        assert.equal(escrituras.at(-1).campos['horarios[1][morning_inicio]'], '09:00', 'Conserva el guardado automático');
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        await nav.esperar("document.querySelector('.horarios .horario-dia').closest('details') !== null");
        const resumen = await nav.evaluar("document.querySelector('.horarios .horario-dia').closest('details').querySelector('small').textContent");
        assert.match(resumen, /09:00–12:00/);
        await nav.evaluar("document.querySelector('.horarios .horario-dia').closest('details').querySelector('summary').click(); true");
        assert.equal(await nav.evaluar("document.querySelector('.horarios .horario-dia').closest('details').open"), true);
        assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, 'Día desplegado sin desborde');
        await nav.evaluar("document.querySelector('[data-horarios-tab=equipo]').click(); true");
        await nav.enviar('Page.reload');
        await nav.esperar("document.readyState === 'complete' && document.querySelector('[data-horarios-tab=equipo]')?.getAttribute('aria-selected') === 'true'");
        await nav.evaluar("document.querySelector('[data-config-tab=ausencias]').click(); true");
        assert.equal(await nav.evaluar("document.querySelector('.ajustes-horarios__tabs').hidden"), true, 'No altera Ausencias');
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        srv.closeAllConnections();
        await new Promise(r => srv.close(r));
    }
});
