const test = require('node:test');
const assert = require('node:assert/strict');
const { servidorAgendaVet, fecha } = require('./apoyo/agenda-vet-servidor.cjs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

for (const width of [1366, 390]) {
    test(`Citas ${width}: registrar abre modal y ajustar nivel exige presencia`, { skip: !buscarChrome() }, async () => {
        const servidor = await servidorAgendaVet();
        const nav = await abrirChrome(buscarChrome());
        try {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: 844, deviceScaleFactor: 1, mobile: width < 768 });
            const lista = width < 768 ? '[data-agenda-dia-lista]' : '#agendaPanelBody';
            const accion = nombre => `${lista} [data-llegada-cita="3"][data-llegada-accion="${nombre}"]`;
            await nav.enviar('Page.navigate', { url: servidor.url });
            await nav.esperar(`document.querySelector('${accion('registrar')}') !== null`);
            assert.equal(await nav.evaluar(`document.querySelector('${accion('ajustar')}')`), null);
            assert.equal(await nav.evaluar(`document.querySelector('[data-agenda-tab="llegadas"]')`), null);
            await nav.evaluar(`document.querySelector('${accion('registrar')}').click();`);
            await nav.esperar(`document.querySelector('.llegada-dialogo[open] .ah-decisiones [type="submit"]:not(:disabled)') !== null`);
            assert.equal(await nav.evaluar(`document.querySelector('${lista} .ah-decisiones')`), null);
            assert.doesNotMatch(await nav.evaluar(`document.querySelector('.llegada-dialogo').textContent`), /tiempo clínico|tolerancia superada|hora válida/);
            assert.equal(await nav.evaluar(`(() => {const d=document.querySelector('.llegada-dialogo');const r=d.getBoundingClientRect();return r.width>0 && r.left>=0 && r.right<=innerWidth+1 && d.scrollWidth<=d.clientWidth+1;})()`), true);
            await nav.evaluar(`document.querySelector('.llegada-dialogo [data-cerrar-dialogo]').click();`);
            await nav.esperar(`document.querySelector('${accion('ajustar')}') !== null`);
            await nav.evaluar(`document.querySelector('${accion('ajustar')}').click();`);
            assert.equal(await nav.evaluar(`document.querySelectorAll('.llegada-dialogo .motivos-revision button').length`), 4);
            await nav.evaluar(`document.querySelector('.llegada-dialogo [data-cerrar-dialogo]').click();`);
            assert.equal(servidor.llamadas.filter(l => l.accion === 'registrar_llegada_ajax').length, 1);
            assert.equal(servidor.llamadas.filter(l => l.accion === 'ajustar_llegada_ajax').length, 0);
            assert.equal(servidor.llamadas.filter(l => l.accion === 'listar_llegadas_ajax').length, 0, 'No consulta la antigua cola de Sala');
            assert.deepEqual(nav.errores, []);
        } finally {
            await nav.cerrar();
            await servidor.cerrar();
        }
    });
}
