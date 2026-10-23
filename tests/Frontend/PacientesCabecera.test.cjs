const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { estilosPacientes } = require('./apoyo/pacientes-presentacion.cjs');

test('Pacientes conserva los controles y eventos al distribuir cabecera y filtros', () => {
    const fuente = fs.readFileSync('views/vet/pacientes.php', 'utf8');
    const titulo = fs.readFileSync('views/vet/partials/cabecera_pacientes.php', 'utf8');
    const cabecera = fuente.slice(fuente.indexOf('<div class="pacientes-barra"'), fuente.indexOf('<!-- CONTENEDOR PRINCIPAL'));
    assert.match(titulo, /id="tabOwners"/);
    assert.match(titulo, /id="tabPets"/);
    assert.doesNotMatch(titulo, /data-vincular-mascota-cliente|data-c3-click="evento2"/);
    for (const id of ['ownerSearch', 'tableSearch', 'searchRowOwners', 'searchRowPets', 'ownerViewToggle', 'petViewToggle', 'eprop_todos', 'eprop_activos', 'eprop_inactivos', 'emasc_todos', 'emasc_activos', 'emasc_inactivos']) {
        assert.match(cabecera, new RegExp(`id="${id}"`));
    }
    assert.match(cabecera, /data-vincular-mascota-cliente/);
    assert.match(cabecera, /data-c3-click="evento2"/);
    assert.doesNotMatch(cabecera, /\s(?:style|onclick|onchange|onkeyup)=/);
    assert.match(fs.readFileSync('views/vet/layout.php', 'utf8'), /\$__action === 'vet_pacientes'[\s\S]*partials\/cabecera_pacientes.php/);
});

test('cabecera adaptable y cambio Propietarios/Mascotas conservan filtros, vistas y acciones', { skip: !buscarChrome() }, async () => {
    const fuente = fs.readFileSync('views/vet/pacientes.php', 'utf8');
    const titulo = fs.readFileSync('views/vet/partials/cabecera_pacientes.php', 'utf8');
    const barra = fuente.slice(fuente.indexOf('<div class="pacientes-barra"'), fuente.indexOf('<!-- CONTENEDOR PRINCIPAL')).replace(/<\?php[\s\S]*?\?>/g, '');
    const html = `<header class="top-header top-header--pacientes"><div class="header-left">${titulo}</div><div class="header-right"><span>Clínica Norte · Veterinario</span></div></header><section class="content-body">${barra}</section>`;
    // Cada hoja se parsea por separado, igual que los enlaces/imports de la pantalla real.
    const css = estilosPacientes();
    const js = fs.readFileSync('public/js/medical-module.js', 'utf8');
    const cambiar = js.slice(js.indexOf('function cambiarCabeceraPacientes('), js.indexOf('// --- PROPIETARIOS ---'));
    const navegador = await abrirChrome(buscarChrome());
    try {
        const arbol = await navegador.enviar('Page.getFrameTree');
        await navegador.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html: `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">${css}<style>body{margin:0;padding:24px;box-sizing:border-box}.prueba{max-width:1120px;margin:auto}*,*::before,*::after{animation:none!important;transition:none!important}</style></head><body><main class="prueba">${html}<div id="moduleOwners"><div id="ownersDirectory"></div><div id="dossierView"></div></div><div id="modulePets"></div></main></body></html>` });
        assert.equal(await navegador.evaluar("getComputedStyle(document.querySelector('.pacientes-barra')).display"), 'flex');
        assert.equal(await navegador.evaluar("getComputedStyle(document.querySelector('.top-header--pacientes')).display"), 'flex');
        await navegador.evaluar(`window.loadOwners=()=>{};${cambiar}`);
        for (const width of [1366, 1184, 820, 390, 360]) {
            await navegador.enviar('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
            const posiciones = await navegador.evaluar(`(()=>{const header=document.querySelector('.top-header'),izquierda=header.querySelector('.header-left');const html=izquierda.innerHTML;const medir=()=>{const r=header.querySelector('.header-right').getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height};};header.classList.remove('top-header--pacientes');izquierda.innerHTML='<h1 class="top-header-title"></h1>';const original=medir();izquierda.innerHTML=html;header.classList.add('top-header--pacientes');return {original,actual:medir()};})()`);
            for (const eje of ['x', 'y', 'width', 'height']) assert.ok(Math.abs(posiciones.original[eje] - posiciones.actual[eje]) < 1, `Controles superiores desplazados: ${width}px, ${eje}, ${JSON.stringify(posiciones)}`);
            for (const modulo of ['owners', 'pets']) {
                await navegador.evaluar(`switchModule('${modulo}',{recargar:false});`);
                const resultado = await navegador.evaluar(`(()=>{const visible=id=>getComputedStyle(document.getElementById(id)).display!=='none';const barra=document.querySelector('.pacientes-barra').getBoundingClientRect();const titulo=document.querySelector('.pacientes-cabecera').getBoundingClientRect();return {sinDesborde:document.documentElement.scrollWidth<=innerWidth,barraAbajo:barra.top>=titulo.bottom,owners:visible('searchRowOwners'),pets:visible('searchRowPets'),vistaOwners:visible('ownerViewToggle'),vistaPets:visible('petViewToggle'),acciones:[...document.querySelectorAll('.pacientes-barra .btn-create')].every(b=>{const r=b.getBoundingClientRect();return r.width>0&&r.left>=0&&r.right<=innerWidth;})};})()`);
                assert.equal(resultado.sinDesborde, true, JSON.stringify({ width, modulo, resultado }));
                assert.equal(resultado.barraAbajo, true);
                assert.equal(resultado.owners, modulo === 'owners');
                assert.equal(resultado.pets, modulo === 'pets');
                assert.equal(resultado.vistaOwners, modulo === 'owners');
                assert.equal(resultado.vistaPets, modulo === 'pets');
                assert.equal(resultado.acciones, true);
                if (process.env.ZOOKI_CAPTURA_PACIENTES === '1' && [1184, 390].includes(width)) {
                    const imagen = await navegador.enviar('Page.captureScreenshot', { format: 'png' });
                    fs.writeFileSync(`scratch/pacientes-cabecera-${width}-${modulo}.png`, Buffer.from(imagen.data, 'base64'));
                }
            }
        }
    } finally {
        await navegador.cerrar();
    }
});
