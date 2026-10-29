const test = require('node:test');
const assert = require('node:assert/strict');
const { servidorPerfil } = require('./apoyo/perfil-comparacion-servidor.cjs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

test('R C: menú común más horario/ausencias, navegación y secciones reales sin desborde', { skip: !buscarChrome() }, async () => {
    const n = await abrirChrome(buscarChrome());
    const referencia = {};
    try {
        for (const rol of ['admin', 'vet']) {
            const s = await servidorPerfil(rol);
            try {
                for (const width of [360, 390, 820, 1366]) {
                    await n.enviar('Emulation.setDeviceMetricsOverride', { width, height: width === 1366 ? 768 : 1180, deviceScaleFactor: 1, mobile: width < 768 });
                    await n.enviar('Page.navigate', { url: s.url });
                    await n.esperar("document.readyState==='complete' && document.querySelector('[data-ajustes]')?.dataset.ajustesVista");
                    const menu = await n.evaluar(`(() => {
                        const r = document.querySelector('[data-ajustes]');
                        return { claves:[...r.querySelectorAll('[data-config-tab]')].map(e=>e.dataset.configTab), estructura:[...r.querySelectorAll('#perfilTabCuenta,#perfilTabSeguridad')].map(e=>[e.tagName,e.className,e.parentElement.className,e.parentElement.parentElement.className]), resúmenes:r.querySelectorAll('.perfil-resumen').length, titulo:!!document.querySelector('.encabezado-pagina .top-header-title') };
                    })()`);
                    assert.equal(menu.titulo, true);
                    assert.equal(menu.resúmenes, 0);
                    if (rol === 'admin') {
                        assert.deepEqual(menu.claves, ['clinica', 'horarios', 'ausencias', 'suscripcion', 'cuenta', 'seguridad'], 'Se conserva el menú aprobado del administrador');
                        referencia[width] = menu.estructura;
                        assert.equal(await n.evaluar("document.querySelectorAll('#perfilContactoForm').length===1 && document.querySelectorAll('#perfilPasswordForm').length===1 && document.querySelectorAll('[data-actividad-extra]').length===3"), true, 'Se conservan formularios y actividad del administrador');
                    }
                    else {
                        assert.deepEqual(menu.claves, ['cuenta', 'seguridad', 'horarios', 'ausencias']);
                        assert.deepEqual(menu.estructura, referencia[width]);
                    }
                    for (const clave of rol === 'admin' ? ['cuenta', 'seguridad'] : menu.claves) {
                        await n.evaluar(`document.querySelector('[data-config-tab="${clave}"]').click()`);
                        const estado = await n.evaluar(`(() => {
                            const r = document.querySelector('[data-ajustes]');
                            const p = r.querySelector('[data-config-panel="${clave}"]');
                            const volver = r.querySelector('[data-ajustes-volver]');
                            return { desborde:document.documentElement.scrollWidth>innerWidth, vista:r.dataset.ajustesVista, visible:!p.hidden, hash:location.hash, volver:volver.getBoundingClientRect().width, filas:r.querySelectorAll('.ajustes-horarios__dia').length };
                        })()`);
                        assert.equal(estado.desborde, false, JSON.stringify({ rol, width, clave, estado }));
                        assert.equal(estado.visible, true, JSON.stringify({ rol, width, clave, estado }));
                        assert.equal(estado.vista, 'seccion');
                        assert.equal(estado.hash, '#' + clave);
                        if (width < 1024) assert.ok(estado.volver >= 40);
                        if (rol === 'vet' && clave === 'horarios') assert.equal(estado.filas, width < 768 ? 7 : 0);
                        await n.evaluar("document.querySelector('[data-ajustes-volver]').click()");
                        assert.equal(await n.evaluar("document.querySelector('[data-ajustes]').dataset.ajustesVista"), 'menu');
                    }
                    if (rol === 'vet') {
                        const pendiente = await n.evaluar(`(() => {
                            const item = document.createElement('div');
                            item.className = 'coord-item';
                            const titulo = document.createElement('strong');
                            titulo.textContent = 'Mi horario · Pendiente';
                            item.append(titulo);
                            document.querySelector('[data-propuestas]').append(item);
                            return new Promise(r=>requestAnimationFrame(()=>r(!document.querySelector('[data-perfil-pendiente="horarios"]').hidden)));
                        })()`);
                        assert.equal(pendiente, true);
                    }
                }
            } finally {
                await s.cerrar();
            }
        }
        assert.deepEqual(n.errores, []);
    } finally {
        await n.cerrar();
    }
});
