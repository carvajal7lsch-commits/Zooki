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

test('ficha de mascota: resumen real, navegación, permisos, foto e información adaptable', { skip: !buscarChrome() }, async () => {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' && fs.existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
    const vista = execFileSync(php, ['tests/Frontend/apoyo/propietario-detalle-vista.php'], { encoding: 'utf8', env: { ...process.env, TRUSTED_PROXIES: '127.0.0.2', ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' } });
    assert.doesNotMatch(vista, /Fatal error|Warning:|\s(?:style|onclick)=/);
    const html = vista.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '');
    const navegador = await abrirChrome(buscarChrome());
    try {
        const arbol = await navegador.enviar('Page.getFrameTree');
        await navegador.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html: `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">${estilosPacientes()}<style>${fs.readFileSync('public/css/alertas-medicas.css', 'utf8')}</style><style>body{margin:0}.prueba{max-width:1120px;
margin:auto}*,*::before,*::after{transition:none!important;
animation:none!important}</style></head><body><div class="prueba">${html}</div></body></html>` });
        await navegador.evaluar(fs.readFileSync('public/js/alertas-medicas.js', 'utf8'));
        await navegador.evaluar(fs.readFileSync('public/js/fechas.js', 'utf8'));
        await navegador.evaluar(`window.scrollTo=()=>{};
window.loadOwners=()=>{};
let dossierOrigen='owners';
const AVISO_SOLO_LECTURA='Mascota desvinculada: solo lectura';
function escaparTexto(s){const d=document.createElement('div');
d.textContent=s??'';
return d.innerHTML;
}function filaHistorialCompacto(r,t){return '<p>'+escaparTexto(t+': '+(r.diagnostico||r.nombre_vacuna||r.producto))+'</p>';
}function mostrarSinHistoria(el){el.textContent='Sin historia';
}window.editPet=id=>{window.__editada=id;
};
            ${tramo('function cambiarCabeceraPacientes(', '// --- PROPIETARIOS ---')}
            ${tramo('function inicialesDueno(', '/** Una etiqueta mini')}
            ${tramo('const FOTO_MASCOTA_DEFECTO =', '/** HU-15:')}
            ${tramo('function razaConIndicada(', '/**')}
            ${tramo('function renderOwnerDossier(', 'function addNewPetFromDossier(')}
            ${tramo('function avisoSoloLectura(', '/** M2 C5a')}
            ${tramo('function eventosHistorialCompacto(', 'function filaHistorialCompacto(')}
            ${tramo('function calculateAge(', '// --- DRAWER FUNCTIONS ---')}
            ${tramo('function renderPetDashboard(', '// RE-7.4.4:')}
            ${tramo('function switchPetDashTab(', 'function toggleDashConsultaDetails(')}
            window.__owner={nombre_completo:'Fabio Propietario',estado:1,documento:'1000000006',telefono:'3001112233',email:'fabio@zooki.test'};
            window.__data={mascota:{nombre:'Doki',nombre_especie:'Canino',nombre_raza:'Dóberman',sexo:'Macho',peso:'9.00',fecha_nacimiento:'2024-12-31',numero_historia_clinica:'HC-000002',estado:1},consultas:[{fecha_hora:'2026-09-10 08:00:00',diagnostico:'Anterior'},{fecha_hora:'2026-10-21 08:00:00',diagnostico:'Control',clinica_nombre:'Norte'}],vacunas:[{fecha_aplicacion:'2026-10-01',nombre_vacuna:'Rabia',fecha_proxima_dosis:'2027-10-01',clinica_nombre:'Sur'}],desparasitaciones:[{fecha_aplicacion:'2026-10-05',producto:'Producto registrado',clinica_nombre:'Norte'}],alertas:{activas:[],historial:[],solo_lectura:false}};
            document.querySelector('[data-c3-click="evento17"]').addEventListener('click',hideDossier);
            document.querySelector('[data-c3-click="evento27"]').addEventListener('click',e=>switchPetDashTab(e,'dashGeneral'));
            document.querySelector('[data-c3-click="evento28"]').addEventListener('click',e=>switchPetDashTab(e,'dashHistorial'));
`);
        for (const width of [1366, 1184, 820, 390, 360]) {
            await navegador.enviar('Emulation.setDeviceMetricsOverride', { width, height: 950, deviceScaleFactor: 1, mobile: width < 768 });
            const resultado = await navegador.evaluar(`(()=>{switchModule('owners',{recargar:false});
const a=document.querySelector('.header-right').getBoundingClientRect();
renderOwnerDossier(__owner,[]);
renderPetDashboard(__data,1);
mostrarDossier('ficha');
const b=document.querySelector('.header-right').getBoundingClientRect();
return {sinDesborde:document.documentElement.scrollWidth<=innerWidth,columnas:getComputedStyle(document.querySelector('.ficha-mascota-datos .dossier-pet-details')).gridTemplateColumns.split(' ').length,titulo:document.getElementById('dossierOwnerTitle').innerText,volver:document.getElementById('dossierVolverTexto').innerText,fecha:document.getElementById('dashPetFechaNac').innerText,inicial:document.getElementById('dashPetIniciales').textContent,controles:[a.x,a.y,a.width,a.height].every((v,i)=>Math.abs(v-[b.x,b.y,b.width,b.height][i])<1),consulta:document.getElementById('dashUltimaConsulta').textContent,vacuna:document.getElementById('dashUltimaVacuna').textContent,desparasita:document.getElementById('dashUltimaDesparasitacion').textContent};
})()`);
            assert.equal(resultado.sinDesborde, true, JSON.stringify({ width, resultado }));
            assert.equal(resultado.columnas, width >= 1024 ? 3 : width > 500 ? 2 : 1);
            assert.equal(resultado.titulo, 'Ficha de Doki');
            assert.equal(resultado.volver, 'Mascotas de Fabio');
            assert.match(resultado.fecha, /^31\/12\/2024/);
            assert.equal(resultado.inicial, 'D');
            assert.equal(resultado.controles, true, 'El bloque derecho conserva su posición');
            const cabecera = await navegador.evaluar(`(() => {
                const boton = document.querySelector('.pacientes-cabecera--ficha .propietario-volver');
                const estilo = getComputedStyle(boton);
                const ruta = document.querySelector('.pacientes-cabecera--ficha .propietario-ruta');
                const tamanos = () => ({
                    boton: getComputedStyle(boton).fontSize,
                    ruta: getComputedStyle(ruta.querySelector('p')).fontSize,
                    titulo: getComputedStyle(ruta.querySelector('h1')).fontSize,
                    altoBoton: boton.getBoundingClientRect().height,
                });
                mostrarSeccionDossier('listado');
                const referenciaPropietario = tamanos();
                mostrarSeccionDossier('ficha');
                return {
                    color: estilo.color,
                    borde: estilo.borderTopStyle,
                    radio: estilo.borderTopLeftRadius,
                    alto: boton.getBoundingClientRect().height,
                    flecha: !!boton.querySelector('svg path'),
                    separador: getComputedStyle(ruta).borderLeftWidth,
                    titulo: getComputedStyle(ruta.querySelector('h1')).fontSize,
                    mascota: ruta.querySelector('strong').textContent,
                    peso: getComputedStyle(ruta.querySelector('strong')).fontWeight,
                    colorRuta: getComputedStyle(ruta.querySelector('p')).color,
                    colorNombre: getComputedStyle(ruta.querySelector('strong')).color,
                    tamanos: tamanos(),
                    referenciaPropietario,
                };
            })()`);
            assert.equal(cabecera.color, 'rgb(0, 82, 255)');
            assert.equal(cabecera.borde, 'solid');
            assert.equal(cabecera.radio, '10px');
            assert.deepEqual(cabecera.tamanos, cabecera.referenciaPropietario, 'La ficha tiene los mismos tamaños que el ingreso al propietario');
            assert.equal(cabecera.flecha, true);
            assert.equal(cabecera.separador, '1px');
            assert.equal(cabecera.mascota, 'Doki');
            assert.equal(cabecera.peso, '700');
            assert.notEqual(cabecera.colorRuta, 'rgb(100, 116, 139)', 'La ruta usa el azul suave solicitado, no el gris anterior');
            assert.equal(cabecera.colorNombre, 'rgb(15, 23, 42)', 'La mascota mantiene el contraste oscuro de la referencia');
            assert.equal(resultado.consulta, '21/10/2026 · Control · Norte');
            assert.match(resultado.vacuna, /Rabia · Sur · Próxima: 01\/10\/2027/);
            assert.match(resultado.desparasita, /Sin próxima fecha registrada$/);
            assert.doesNotMatch(resultado.vacuna + resultado.desparasita, /Al día/i);
            if (process.env.ZOOKI_CAPTURA_PACIENTES === '1' && [1184, 390].includes(width)) {
                const imagen = await navegador.enviar('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(`scratch/mascota-detalle-${width}.png`, Buffer.from(imagen.data, 'base64'));
            }
        }
        const interaccion = await navegador.evaluar(`(()=>{document.querySelector('[data-c3-click="evento28"]').click();
const historial=document.getElementById('dashHistorial').classList.contains('d-block')&&getComputedStyle(document.querySelector('[data-alertas-ficha]')).display!=='none';
document.querySelector('[data-c3-click="evento27"]').click();
document.getElementById('dashEditPetBtn').click();
document.querySelector('[data-c3-click="evento17"]').click();
const lista=document.getElementById('dossierListSection').style.display==='block'&&document.getElementById('dossierOwnerTitle').innerText==='Fabio Propietario'&&document.getElementById('dossierPetRuta').hidden;
document.querySelector('[data-c3-click="evento17"]').click();
return {historial,general:document.getElementById('dashGeneral').classList.contains('d-block'),editada:window.__editada,lista,directorio:!document.querySelector('.pacientes-barra').hidden};
})()`);
        assert.deepEqual(interaccion, { historial: true, general: true, editada: 1, lista: true, directorio: true });
        const seguro = await navegador.evaluar(`(()=>{const nombre='<img src=x onerror=alert(1)>';
renderPetDashboard({mascota:{nombre,estado:0,solo_lectura:true,url_foto:'inexistente.jpg'},consultas:[],vacunas:[],desparasitaciones:[],alertas:{activas:[],historial:[],solo_lectura:true}},2);
mostrarDossier('ficha');
document.getElementById('dashPetPhoto').dispatchEvent(new Event('error'));
return {escape:document.getElementById('dashPetName').textContent===nombre&&!document.getElementById('dashPetName').querySelector('img'),soloLectura:document.getElementById('dashEditPetBtn').hidden&&!document.querySelector('[data-alertas-ficha] [data-registrar-alerta]'),aviso:!!document.querySelector('#dossierPetDashboardSection > .aviso-solo-lectura'),foto:document.getElementById('dashPetPhoto').hidden&&!document.getElementById('dashPetIniciales').hidden,inactiva:document.getElementById('dashPetStatus').textContent==='Inactiva',vacio:document.getElementById('dashUltimaConsulta').textContent==='Sin consultas registradas'&&document.getElementById('dashUltimaVacuna').textContent==='Sin registros',sinHistoria:document.getElementById('dashHistorialTimeline').textContent==='Sin historia'};
})()`);
        assert.deepEqual(seguro, { escape: true, soloLectura: true, aviso: true, foto: true, inactiva: true, vacio: true, sinHistoria: true });
    } finally {
        await navegador.cerrar();
    }
});

