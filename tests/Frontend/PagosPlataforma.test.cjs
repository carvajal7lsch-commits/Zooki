const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');
const {abrirChrome, buscarChrome} = require('./apoyo/chrome-cdp.cjs');
const RAIZ = path.join(__dirname, '../..');
const codigo = fs.readFileSync(path.join(RAIZ, 'public/js/pagos-plataforma.js'), 'utf8');

function entorno(fetch) {
    const contexto = {WeakMap, encodeURIComponent, FormData: class {}, window: {ZookiPlataforma: {validar: () => true}, zookiToast() {}}, document: {addEventListener() {}}, fetch};
    vm.runInNewContext(codigo, contexto);
    return contexto.window.ZookiPagosPlataforma;
}

test('S4: una consulta atrasada no sustituye la clínica actual; errores como texto', async () => {
    const pendientes = [];
    const api = entorno(() => new Promise(resolve => pendientes.push(resolve)));
    const destino = {dataset: {}, innerHTML: '', textContent: ''};
    const norte = api.cargar(destino, 1);
    const sur = api.cargar(destino, 2);
    pendientes[1]({ok: true, text: async () => '<p>Sur</p>'});
    await sur;
    pendientes[0]({ok: true, text: async () => '<p>Norte</p>'});
    await norte;
    assert.equal(destino.innerHTML, '<p>Sur</p>');
    const error = entorno(async () => ({ok: false, text: async () => '<script>error</script>'}));
    await error.cargar(destino, 0);
    assert.equal(destino.textContent, '<script>error</script>');
});

test('S4: revisar deshabilita al primer clic, evita duplicado y conserva nota ante error', async () => {
    let terminar;
    let llamadas = 0;
    const boton = {disabled: false};
    const salida = {textContent: ''};
    const nota = {};
    const destino = {dataset: {clinica: '1'}};
    const form = {dataset: {}, querySelector: s => s === 'textarea' ? nota : s === '[data-resultado]' ? salida : boton, reportValidity: () => true, closest: () => destino};
    const api = entorno(() => {
        llamadas++;
        assert.equal(boton.disabled, true);
        return new Promise(resolve => terminar = resolve);
    });
    const primera = api.revisar(form);
    await api.revisar(form);
    assert.equal(llamadas, 1);
    terminar({ok: false, json: async () => ({success: false, message: 'La nota no es válida'})});
    await primera;
    assert.equal(boton.disabled, false);
    assert.equal(salida.textContent, 'La nota no es válida');
    assert.equal(form.dataset.enviando, undefined);
    assert.doesNotMatch(fs.readFileSync(path.join(RAIZ, 'views/plataforma/pagos.php'), 'utf8'), /\sstyle=|\son[a-z]+=|<script/i);
});

test('S4 recuperación: ID validado en vivo, motivo y un solo envío manual; nunca se elige clínica', async () => {
    let terminar;
    let llamadas = 0;
    const boton = {disabled: false};
    const salida = {textContent: ''};
    const error = {textContent: ''};
    const id = {value: '../pago', pattern: '[a-zA-Z0-9_\\-]{1,128}', setCustomValidity(m) {this.error = m;}, setAttribute() {}, parentElement: {querySelector: () => error}};
    const destino = {dataset: {clinica: '2'}};
    const form = {dataset: {}, querySelector: s => s === 'textarea' ? {} : s === 'input[name="id_transaccion"]' ? id : s === '[data-resultado]' ? salida : boton, reportValidity: () => true, closest: () => destino};
    const api = entorno(url => {
        llamadas++;
        assert.match(url, /action=plataforma_conciliar_ajax$/);
        assert.equal(boton.disabled, true);
        return new Promise(resolve => terminar = resolve);
    });
    await api.revisar(form, true);
    assert.match(error.textContent, /Revisa el ID/);
    assert.equal(llamadas, 0);
    id.value = '01-1531231271-19365';
    const primera = api.revisar(form, true);
    await api.revisar(form, true);
    assert.equal(llamadas, 1);
    terminar({ok: false, json: async () => ({success: false, message: 'No se pudo consultar Wompi'})});
    await primera;
    assert.equal(boton.disabled, false);
    assert.equal(salida.textContent, 'No se pudo consultar Wompi');
    const vista = fs.readFileSync(path.join(RAIZ, 'views/plataforma/pagos.php'), 'utf8');
    const formulario = vista.split('data-finanzas-conciliar>')[1].split('</form>')[0];
    assert.doesNotMatch(formulario, /name="id_clinica"/);
    assert.match(formulario, /ID de transacción Wompi/);
    assert.match(formulario, /name="motivo"/);
});

function vista(escenario, id = 1) {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
    return execFileSync(php, ['tests/Frontend/apoyo/plataforma-pagos-vista.php', escenario, String(id)], {cwd: RAIZ, encoding: 'utf8', env: {...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2'}});
}

function cssLocal(archivo) {
    return fs.readFileSync(archivo, 'utf8').replace(/@import\s+url\(['"]?([^'"\)]+)['"]?\);/g, (_, ruta) => /^https?:/.test(ruta) ? '' : cssLocal(path.join(path.dirname(archivo), ruta.split('?')[0])));
}

test('S4 Chrome: plataforma real a 1366/390, pagos, incidencias, legado y revisión sin red', {skip: !buscarChrome()}, async () => {
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Network.enable');
        await nav.enviar('Network.setBlockedURLs', {urls: ['http://*', 'https://*']});
        let html = vista('inicio');
        assert.doesNotMatch(html, /Fatal error|Warning:|Notice:/);
        const estilos = [...html.matchAll(/<link[^>]+href="(css\/[^"?]+)[^"]*"[^>]*>/g)].map(m => '<style>' + cssLocal(path.join(RAIZ, 'public', m[1])) + '</style>').join('\n');
        html = html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '').replace(/<img\b[^>]*>/gi, '').replace('</head>', estilos + '<style>*{animation:none!important;transition:none!important}</style></head>');
        const parciales = {'1': vista('pagos', 1), '2': vista('pagos', 2), '0': vista('pagos', 0)};
        const revisada = vista('revisada', 1);
        for (const ancho of [1366, 390]) {
            await nav.enviar('Page.navigate', {url: 'about:blank'});
            await nav.enviar('Emulation.setDeviceMetricsOverride', {width: ancho, height: 844, deviceScaleFactor: 1, mobile: ancho < 768});
            const arbol = await nav.enviar('Page.getFrameTree');
            await nav.enviar('Page.setDocumentContent', {frameId: arbol.frameTree.frame.id, html});
            await nav.evaluar(`window.localStorage={getItem:()=>null,setItem(){}};window.zookiToast=()=>{};window.s4Parciales=${JSON.stringify(parciales)};window.s4Revisada=${JSON.stringify(revisada)};window.s4Reviso=false;window.fetch=async(url,opciones)=>{if(opciones?.method==='POST'){window.s4UltimoPost={url,clinica:opciones.body.get('id_clinica')};window.s4Reviso=true;return{ok:true,json:async()=>({success:true,message:'Revisada'})}}const id=new URL(url,'http://localhost/').searchParams.get('id_clinica');return{ok:true,text:async()=>window.s4Reviso&&id==='1'?window.s4Revisada:window.s4Parciales[id]}};`);
            await nav.evaluar(fs.readFileSync(path.join(RAIZ, 'public/js/plataforma.js'), 'utf8'));
            await nav.evaluar(codigo);
            await nav.evaluar(`document.dispatchEvent(new Event('DOMContentLoaded'));`);
            for (const id of [1, 2]) {
                await nav.evaluar(`document.querySelector('[data-gestionar-clinica="${id}"]').click();document.querySelector('[data-modal-pestana="pagos"]').click();`);
                await nav.esperar(`document.querySelector('#gestionarClinica [data-finanzas-contenido]')!==null`);
                const estado = await nav.evaluar(`({cabe:document.documentElement.scrollWidth<=innerWidth,modalCabe:document.querySelector('#gestionarClinica .modal-content').getBoundingClientRect().width<=innerWidth,texto:document.querySelector('#gestionarClinica [data-finanzas-destino]').textContent})`);
                assert.equal(estado.cabe, true);
                assert.equal(estado.modalCabe, true);
                assert.match(estado.texto, id === 1 ? /Pago anulado/ : /Legado sin fecha/);
                assert.match(estado.texto, /Concesiones administrativas/);
                assert.doesNotMatch(estado.texto, /00:00|id_fuente_wompi|prv_test_/);
                if (process.env.ZOOKI_CAPTURAS_S4 === '1') {
                    fs.mkdirSync(path.join(RAIZ, 'scratch'), {recursive: true});
                    const captura = await nav.enviar('Page.captureScreenshot', {format: 'png'});
                    fs.writeFileSync(path.join(RAIZ, `scratch/s4-plataforma-${id}-${ancho}.png`), Buffer.from(captura.data, 'base64'));
                }
                if (id === 1) {
                    await nav.evaluar(`(()=>{const f=document.querySelector('#gestionarClinica [data-finanzas-revision]');f.querySelector('textarea').value='Revisión de prueba sin modificar el pago';f.querySelector('textarea').dispatchEvent(new Event('input',{bubbles:true}));f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));})()`);
                    await nav.esperar(`document.querySelector('#gestionarClinica [data-finanzas-destino]').textContent.includes('Revisada el')`);
                }
                if (id === 1) {
                    await nav.evaluar(`(()=>{const f=document.querySelector('#gestionarClinica [data-finanzas-conciliar]');const id=f.querySelector('input[name="id_transaccion"]');id.value='../inventado';id.dispatchEvent(new Event('input',{bubbles:true}));})()`);
                    assert.equal(await nav.evaluar(`document.querySelector('#gestionarClinica [data-finanzas-conciliar] input[name="id_transaccion"]').checkValidity()`), false);
                    await nav.evaluar(`(()=>{const f=document.querySelector('#gestionarClinica [data-finanzas-conciliar]');const id=f.querySelector('input[name="id_transaccion"]');id.value='01-1531231271-19365';id.dispatchEvent(new Event('input',{bubbles:true}));f.querySelector('textarea').value='Recuperar evento perdido';f.querySelector('textarea').dispatchEvent(new Event('input',{bubbles:true}));f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));})()`);
                    await nav.esperar(`window.s4UltimoPost?.url.includes('plataforma_conciliar_ajax')`);
                    assert.equal(await nav.evaluar(`window.s4UltimoPost.clinica`), null);
                    await nav.esperar(`document.querySelector('#gestionarClinica [data-finanzas-conciliar]')!==null`);
                }
                await nav.evaluar(`document.querySelector('#gestionarClinica [data-cerrar-dialogo]').click();`);
            }
            await nav.evaluar(`document.querySelector('[data-finanzas-global]').click();`);
            await nav.esperar(`document.querySelector('#incidenciasSinClinica [data-finanzas-contenido]')!==null`);
            assert.match(await nav.evaluar(`document.querySelector('#incidenciasSinClinica').textContent`), /Referencia desconocida/);
            if (process.env.ZOOKI_CAPTURAS_S4 === '1') {
                const captura = await nav.enviar('Page.captureScreenshot', {format: 'png'});
                fs.writeFileSync(path.join(RAIZ, `scratch/s4-incidencias-${ancho}.png`), Buffer.from(captura.data, 'base64'));
            }
        }
    } finally {
        await nav.cerrar();
    }
});
