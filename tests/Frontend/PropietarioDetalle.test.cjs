const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { estilosPacientes } = require('./apoyo/pacientes-presentacion.cjs');

const fuente = fs.readFileSync('public/js/medical-module.js', 'utf8');
function tramo(desde, hasta) {
    const inicio = fuente.indexOf(desde);
    const fin = fuente.indexOf(hasta, inicio);
    assert.ok(inicio >= 0 && fin > inicio);
    return fuente.slice(inicio, fin);
}

test('detalle del propietario: cuadrícula, filtros, ficha, regreso, datos desconocidos y controles superiores', { skip: !buscarChrome() }, async () => {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' && fs.existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
    const vista = execFileSync(php, ['tests/Frontend/apoyo/propietario-detalle-vista.php'], { encoding: 'utf8', env: { ...process.env, TRUSTED_PROXIES: '127.0.0.2', ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' } });
    assert.doesNotMatch(vista, /Fatal error|Warning:/);
    assert.doesNotMatch(vista, /\s(?:style|onclick|onkeyup)=/);
    const html = vista.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '');
    const navegador = await abrirChrome(buscarChrome());
    try {
        const arbol = await navegador.enviar('Page.getFrameTree');
        await navegador.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html: `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">${estilosPacientes()}<style>body{margin:0}.prueba{max-width:1120px;margin:auto}*,*::before,*::after{transition:none!important;animation:none!important}</style></head><body><div class="prueba">${html}</div></body></html>` });
        await navegador.evaluar(`window.scrollTo=()=>{};window.loadOwners=()=>{};window.loadPetDashboard=id=>{window.__ficha=id;};window.__escape='<img src=x onerror=alert(1)>';function escaparTexto(s){return String(s??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');}let dossierOrigen='owners';
            ${tramo('function cambiarCabeceraPacientes(', '// --- PROPIETARIOS ---')}
            ${tramo('function inicialesDueno(', '/** Una etiqueta mini')}
            ${tramo('const FOTO_MASCOTA_DEFECTO =', '/** HU-15:')}
            ${tramo('function razaConIndicada(', '/**')}
            ${tramo('function renderOwnerDossier(', 'function addNewPetFromDossier(')}
            ${tramo('function filterDossierPets(', 'window.filterDossierPets = ')}
            window.__owner={nombre_completo:'Fabio Propietario',documento:'1000000006',telefono:'3001112233',email:'fabio@zooki.test',estado:1};
            window.__pets=[{id_mascota:1,nombre:'Doki',especie:'Canino',raza:'Dóberman',sexo:'Macho',numero_historia_clinica:'HC-000002',estado:1},{id_mascota:2,nombre:'gato',especie:'Felino',raza:'Bengala',sexo:'Macho',numero_historia_clinica:'HC-000003',estado:1},{id_mascota:3,nombre:'Kira',especie:'Canino',raza:'Mestizo',sexo:null,numero_historia_clinica:null,estado:1},{id_mascota:4,nombre:'Luna',especie:'Canino',raza:'Sin raza definida',sexo:'Hembra',numero_historia_clinica:'HC-000001',estado:1},{id_mascota:5,nombre:'Pelusa',especie:'Felino',raza:'Mestizo',sexo:'Hembra',estado:0}];`);
        for (const width of [1366, 1184, 820, 390, 360]) {
            await navegador.enviar('Emulation.setDeviceMetricsOverride', { width, height: 950, deviceScaleFactor: 1, mobile: width < 768 });
            const resultado = await navegador.evaluar(`(()=>{switchModule('owners',{recargar:false});const antes=document.querySelector('.header-right').getBoundingClientRect();renderOwnerDossier(__owner,__pets);mostrarDossier('listado');const despues=document.querySelector('.header-right').getBoundingClientRect();const pista=document.getElementById('dossierPetsScroll');return {sinDesborde:document.documentElement.scrollWidth<=innerWidth,grid:getComputedStyle(pista).display,columnas:getComputedStyle(pista).gridTemplateColumns.split(' ').length,cantidad:pista.children.length,avatar:document.getElementById('dossierOwnerAvatar').innerText,barra:getComputedStyle(document.querySelector('.pacientes-barra')).display,detalle:getComputedStyle(document.querySelector('[data-pacientes-detalle]')).display,controles:[antes.x,antes.y,antes.width,antes.height].every((v,i)=>Math.abs(v-[despues.x,despues.y,despues.width,despues.height][i])<1)};})()`);
            assert.equal(resultado.sinDesborde, true, JSON.stringify({ width, resultado }));
            assert.equal(resultado.grid, 'grid');
            assert.equal(resultado.columnas, 1);
            assert.equal(resultado.cantidad, 5);
            assert.equal(resultado.avatar, 'FP');
            assert.equal(resultado.barra, 'none');
            assert.equal(resultado.detalle, 'flex');
            if (width < 768) assert.equal(resultado.controles, false, 'El detalle móvil conserva su cabecera aprobada; la cabecera de escritorio cambió en R A');
            const botonVolver = await navegador.evaluar(`(() => {
                const boton = document.querySelector('[data-pacientes-detalle] .propietario-volver');
                return {
                    texto: getComputedStyle(boton).color,
                    flecha: getComputedStyle(boton.querySelector('svg')).stroke,
                    fondo: getComputedStyle(boton).backgroundColor,
                };
            })()`);
            const colorVolver = width < 768 ? 'rgb(15, 23, 42)' : 'rgb(71, 85, 105)';
            assert.deepEqual(botonVolver, { texto: colorVolver, flecha: colorVolver, fondo: 'rgb(255, 255, 255)' });
            if (process.env.ZOOKI_CAPTURA_PACIENTES === '1' && [1184, 390].includes(width)) {
                const imagen = await navegador.enviar('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(`scratch/propietario-detalle-${width}.png`, Buffer.from(imagen.data, 'base64'));
            }
        }
        const respaldo = await navegador.evaluar(`(()=>{renderOwnerDossier(__owner,[{...__pets[0],url_foto:'archivo-inexistente.jpg'}]);const foto=document.querySelector('.pet-dossier-card-photo');foto.dispatchEvent(new Event('error'));const correcto=foto.hidden&&!foto.nextElementSibling.hidden&&foto.nextElementSibling.textContent==='D';renderOwnerDossier(__owner,__pets);return correcto;})()`);
        assert.equal(respaldo, true, 'Foto fallida conserva las iniciales del paciente');
        const comportamiento = await navegador.evaluar(`(()=>{document.getElementById('dossierPetSearch').value='gato';filterDossierPets();const nombre=[...document.querySelectorAll('.pet-dossier-card')].filter(c=>c.style.display!=='none').length;document.getElementById('dossierPetSearch').value='';document.getElementById('dossierPetSpeciesFilter').value='Canino';filterDossierPets();const especie=[...document.querySelectorAll('.pet-dossier-card')].filter(c=>c.style.display!=='none').length;document.querySelector('.btn-ficha').click();const ficha=window.__ficha;dossierOrigen='pets';hideDossier();const vuelto=document.getElementById('modulePets').classList.contains('active')&&!document.querySelector('.pacientes-barra').hidden;renderOwnerDossier({...__owner,nombre_completo:__escape},[]);mostrarDossier('listado');return {nombre,especie,ficha,vuelto,vacio:document.querySelector('.dossier-sin-mascotas')!==null,seguro:document.getElementById('dossierOwnerTitle').innerText===__escape&&!document.getElementById('dossierOwnerTitle').querySelector('img'),sinHistoria:document.getElementById('dossierPetsTotal').innerText==='0',filtrosLimpios:document.getElementById('dossierPetSearch').value===''&&document.getElementById('dossierPetSpeciesFilter').value===''};})()`);
        assert.deepEqual(comportamiento, { nombre: 1, especie: 3, ficha: 1, vuelto: true, vacio: true, seguro: true, sinHistoria: true, filtrosLimpios: true });
    } finally {
        await navegador.cerrar();
    }
});
