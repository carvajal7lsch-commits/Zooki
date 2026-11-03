const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { errores } = require('../../public/js/ausencias-agenda.js');
const { errorCampo } = require('../../public/js/urgencias-agenda.js');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const reglas = { nombreMax:100,nombreCaracteres:"\\p{L}\\p{M} '’\\-",telefonoMin:7,telefonoMax:20,mascotaMax:50 };

test('C6 valida rango exacto, motivo y extremos antes de escribir', () => {
    assert.deepEqual(errores({ inicio:'2030-01-07T00:00',fin:'2030-01-08T00:00',motivo:'' }),{});
    assert.ok(errores({ inicio:'2030-01-07T10:00:00',fin:'2030-01-07T10:00:00' }).fin);
    assert.ok(errores({ inicio:'2030-01-07T10:00:01',fin:'2030-01-07T10:00:00' }).fin);
    assert.ok(errores({ inicio:'ayer',fin:'mañana' }).inicio);
    assert.ok(errores({ inicio:'2030-01-07T10:00',fin:'2030-01-07T11:00',motivo:'a'.repeat(256) }).motivo);
});

test('C7 contacto opcional no acredita titularidad; validación coincide con límites compartidos', () => {
    for (const campo of ['nombre_acompanante','telefono_acompanante','nombre_mascota']) assert.equal(errorCampo(campo,'',false,reglas),'');
    assert.equal(errorCampo('nombre_acompanante','María Pérez',false,reglas),'');
    assert.notEqual(errorCampo('nombre_acompanante','A2',false,reglas),'');
    assert.notEqual(errorCampo('telefono_acompanante','123',false,reglas),'');
    assert.equal(errorCampo('telefono_acompanante','+57 3001234567',false,reglas),'');
    assert.notEqual(errorCampo('motivo','',true,reglas),'');
    assert.notEqual(errorCampo('peso','0',true,reglas),'');
    assert.notEqual(errorCampo('evidencia','x'.repeat(256),true,reglas),'');
});

async function pagina(rol, ejecutar, ingresos = []) {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' && fs.existsSync('C:\\xampp\\php\\php.exe') ? 'C:\\xampp\\php\\php.exe' : 'php');
    const html = execFileSync(php,[path.join(__dirname,'apoyo/agenda-clinica-vistas.php'),rol],{ encoding:'utf8',env:{...process.env,TRUSTED_PROXIES:'127.0.0.2',ZOOKI_TEST_MYSQL_HOST:'',ZOOKI_TEST_MYSQL_DB:'zooki_test_base_v2'} });
    assert.doesNotMatch(html,/Fatal error|Warning:/);
    assert.doesNotMatch(html,/\s(?:style|onclick)=|<script(?![^>]*src=)[^>]*>/i);
    const navegador = await abrirChrome(buscarChrome());
    try {
        const arbol = await navegador.enviar('Page.getFrameTree');
        await navegador.enviar('Page.setDocumentContent',{frameId:arbol.frameTree.frame.id,html:`<!doctype html><html><body>${html}</body></html>`});
        await navegador.esperar("document.readyState === 'complete'");
        await navegador.evaluar(`window.__ingresos = ${JSON.stringify(ingresos)};`);
        await navegador.evaluar(`window.__lecturas=[];window.__escrituras=[];
          window.__catalogos={mascotas:[{id_mascota:1,id_propietario:6,nombre:'Luna',propietario:'Fabio',documento_propietario:'12345678'}],propietarios:[{id_usuario:6,nombre_completo:'Fabio'}],tipos:[{id_tipo_cita:1,nombre_tipo:'Consulta'}],especies:[{id_especie:1,nombre_especie:'Canino'}],razas:[{id_raza:49,id_especie:1,nombre_raza:'Sin raza definida'}]};
          window.fetch=(url,opciones)=>new Promise(resolve=>{
            (opciones ? __escrituras : __lecturas).push({url,resolve,body:opciones?.body});
          });`);
        for (const archivo of ['agenda-herramientas','dialogos-zooki','comando-agenda','configuracion-clinica','urgencias-agenda','ausencias-agenda']) await navegador.evaluar(fs.readFileSync(path.join(__dirname,`../../public/js/${archivo}.js`),'utf8'));
        await navegador.esperar('__lecturas.length >= 2');
        await navegador.evaluar(`for(const q of __lecturas) q.resolve({ok:true,json:async()=>q.url.includes('listar_urgencias')?{success:true,catalogos:__catalogos,rojos:[],ingresos:__ingresos}:q.url.includes('coordinacion')?{success:true,veterinarios:[{id_usuario:2,nombre_completo:'Beto'}]}:{success:true,ausencias:[]}});`);
        await ejecutar(navegador);
        assert.deepEqual(navegador.errores,[]);
    } finally {
        await navegador.cerrar();
    }
}

const chrome = { skip:buscarChrome() ? false : 'Sin Chrome para el DOM aislado' };

test('Identificación: opciones claras y buscadores conservan los datos del servidor', chrome, async () => {
    for (const rol of ['vet', 'admin']) {
        for (const width of [1366, 390]) {
            await pagina(rol, async n => {
                await n.esperar("document.querySelector('[data-conciliar-identidad]')");
                const estilos = ['identidad.css', 'styles.css', 'mascota-sexo.css', 'dialogos-zooki.css', 'agenda-herramientas.css', 'agenda-panel.css'].map(archivo => fs.readFileSync('public/css/' + archivo, 'utf8').replace(/@import[^;]+;/g, '')).join('\n');
                await n.evaluar(`var estilos = document.createElement('style'); estilos.textContent = ${JSON.stringify(estilos)}; document.head.append(estilos);`);
                await n.enviar('Emulation.setDeviceMetricsOverride', { width, height: 844, deviceScaleFactor: 1, mobile: width < 768 });
                await n.evaluar(`document.querySelector('[data-urgencias-agenda]').hidden = false; document.querySelector('[data-conciliar-identidad]').click();`);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion[open] h3').textContent`), 'Completar identificación');
                assert.equal(await n.evaluar(`!!document.querySelector('.urgencia-conciliacion h3 .fa-id-card')`), true);
                await n.evaluar(`document.querySelector('[data-identidad-buscar="id_propietario"]').click();`);
                assert.equal(await n.evaluar(`document.querySelector('[data-identidad-buscar="id_propietario"]').getAttribute('aria-expanded')`), 'true');
                await n.evaluar(`var buscar = document.querySelector('[data-identidad-buscar="id_propietario"]'); buscar.value='No existe'; buscar.dispatchEvent(new Event('input', {bubbles:true}));`);
                assert.equal(await n.evaluar(`document.querySelectorAll('#conciliarIdentidad-9-id_propietario-opciones button').length`), 0);
                await n.evaluar(`var buscar = document.querySelector('[data-identidad-buscar="id_propietario"]'); buscar.value='Fab'; buscar.dispatchEvent(new Event('input', {bubbles:true})); buscar.dispatchEvent(new KeyboardEvent('keydown', {key:'ArrowDown', bubbles:true})); buscar.dispatchEvent(new KeyboardEvent('keydown', {key:'Enter', bubbles:true}));`);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion [name="id_propietario"]').value`), '6');
                await n.evaluar(`document.querySelector('[data-identidad-buscar="id_mascota_final"]').click();`);
                assert.equal(await n.evaluar(`document.querySelector('[data-identidad-buscar="id_mascota_final"]').getAttribute('aria-expanded')`), 'true');
                await n.evaluar(`document.querySelector('#conciliarIdentidad-9-id_mascota_final-opcion-1').click();`);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion [name="id_mascota_final"]').value`), '1');
                await n.evaluar(`document.querySelector('[data-identidad-modo="completar"]').click();`);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion fieldset').hidden`), false);
                assert.equal(await n.evaluar(`document.querySelectorAll('.identidad-mascota .form-grid-2-gap').length`), 3);
                assert.equal(await n.evaluar(`document.querySelectorAll('.identidad-mascota .mascota-sexo input[type="radio"]').length`), 2);
                const columnas = await n.evaluar(`(() => { const fila = document.querySelector('.identidad-mascota .form-grid-2-gap'); const [a,b] = [...fila.children].map(n => n.getBoundingClientRect()); return {mismaFila:Math.abs(a.top-b.top)<1, ancho:a.width, fila:fila.clientWidth}; })()`);
                assert.equal(columnas.mismaFila, width >= 1024);
                if (width >= 1024) assert.ok(columnas.ancho < columnas.fila * .6, JSON.stringify(columnas));
                await n.evaluar(`var f = document.querySelector('.urgencia-conciliacion form'); f.elements.nombre.value='Luna'; f.elements.especie.value='1'; f.elements.especie.dispatchEvent(new Event('change',{bubbles:true})); f.elements.raza.value='49'; f.elements.peso.value='4,5'; f.elements.evidencia.checked=true; f.elements.peso.dispatchEvent(new Event('input',{bubbles:true}));`);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion [type="submit"]').disabled`), true, 'Sexo sigue obligatorio');
                await n.evaluar(`document.querySelector('.identidad-mascota [name="sexo"][value="Hembra"]').click();`);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion [type="submit"]').disabled`), false);
                assert.equal(await n.evaluar(`document.querySelector('[data-identidad-modo="completar"]').getAttribute('aria-pressed')`), 'true');
                await n.evaluar(`document.querySelector('[data-identidad-modo="consolidar"]').click();`);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion fieldset').disabled`), true);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion [name="id_mascota_final"]').value`), '1');
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion__contacto').open`), false);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion [name="evidencia"]').type`), 'checkbox');
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion [name="evidencia"]').checked`), false, 'Cambiar el destino requiere confirmar de nuevo');
                await n.evaluar(`document.querySelector('.urgencia-conciliacion form').dispatchEvent(new Event('submit', {cancelable:true}));`);
                assert.equal(await n.evaluar('__escrituras.length'), 0, 'Sin marcar la casilla no se envía');
                await n.evaluar(`var f = document.querySelector('.urgencia-conciliacion form'); f.elements.evidencia.click(); f.dispatchEvent(new Event('submit', {cancelable:true}));`);
                assert.equal(await n.evaluar('__escrituras.length'), 1);
                assert.deepEqual(await n.evaluar(`Object.fromEntries([...__escrituras[0].body].filter(([k]) => ['id_ingreso', 'id_propietario', 'id_mascota_final', 'modo', 'evidencia'].includes(k)))`), { modo: 'consolidar', id_propietario: '6', id_mascota_final: '1', evidencia: 'El operador confirmó con el propietario que esta es su mascota.', id_ingreso: '9' });
                await n.evaluar(`__escrituras[0].resolve({ok:false,json:async()=>({success:false,message:'Verificación pendiente'})});`);
                await n.esperar(`document.querySelector('.urgencia-conciliacion [data-aviso]').textContent === 'Verificación pendiente'`);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion [name="id_propietario"]').value`), '6');
                await n.evaluar(`document.querySelector('.urgencia-conciliacion [data-cerrar-dialogo]').click();`);
                assert.equal(await n.evaluar(`document.querySelector('.urgencia-conciliacion').open`), false);
            }, [{ id_ingreso: 9, id_mascota_provisional: 99, nombre: 'Paciente sin identificar', nombre_acompanante: 'Acompañante', estado: 'pendiente' }]);
        }
    }
});

test('Nueva urgencia: tocar el buscador abre todas las mascotas sin escribir', chrome, async () => {
    await pagina('vet', async n => {
        await n.esperar("document.querySelector('[data-urgencia-resultados] [data-paciente]')");
        await n.evaluar(`__catalogos.mascotas = Array.from({length:12}, (_,i) => ({id_mascota:i+1, nombre:'Mascota '+(i+1), propietario:'Titular', documento_propietario:String(i+1)}));`);
        for (const width of [1366, 390]) {
            await n.enviar('Emulation.setDeviceMetricsOverride', { width, height: 844, deviceScaleFactor: 1, mobile: width < 768 });
            await n.evaluar(`zookiAbrirDialogo('dialogoRojo'); document.querySelector('[data-urgencia-identidad="conocida"]').click();`);
            assert.equal(await n.evaluar(`document.querySelector('[data-urgencia-buscar]').getAttribute('aria-expanded')`), 'true');
            assert.equal(await n.evaluar(`document.querySelectorAll('[data-urgencia-resultados] [data-paciente]').length`), 12);
            await n.evaluar(`document.querySelector('[data-paciente="12"]').click();`);
            assert.equal(await n.evaluar(`document.querySelector('[data-ingreso-rojo]').elements.id_mascota.value`), '12');
            assert.equal(await n.evaluar(`document.querySelector('[data-urgencia-resultados]').hidden`), true);
            await n.evaluar(`document.querySelector('[data-urgencia-buscar]').click();`);
            assert.equal(await n.evaluar(`document.querySelector('[data-urgencia-resultados]').hidden`), false);
            await n.evaluar(`var b = document.querySelector('[data-urgencia-buscar]'); b.value='Mascota 3'; b.dispatchEvent(new Event('input', {bubbles:true}));`);
            assert.equal(await n.evaluar(`document.querySelectorAll('[data-urgencia-resultados] [data-paciente]').length`), 1);
            await n.evaluar(`document.querySelector('#dialogoRojo [data-cerrar-dialogo]').click();`);
            assert.equal(await n.evaluar(`document.querySelector('#dialogoRojo').open`), false);
            await n.evaluar(`var b = document.querySelector('[data-urgencia-buscar]'); b.value=''; b.dispatchEvent(new Event('input', {bubbles:true}));`);
        }
        assert.equal(await n.evaluar('__escrituras.length'), 0);
    });
});
test('C7 DOM real: llegada provisional, doble envío y replay tras respuesta perdida',chrome,async () => {
    await pagina('vet',async n => {
        await n.esperar("document.querySelector('[data-urgencia-resultados] [data-paciente]')");
        await n.evaluar(`var f=document.querySelector('[data-ingreso-rojo]');
            f.elements.identidad.value='provisional';f.elements.identidad.dispatchEvent(new Event('change',{bubbles:true}));
            f.elements.motivo.value='Rojo clínico';f.elements.motivo.dispatchEvent(new Event('input',{bubbles:true}));
            f.querySelector('[data-ingreso-solo-llegada]').click();f.querySelector('[data-ingreso-solo-llegada]').click();`);
        assert.equal(await n.evaluar('__escrituras.length'),1);
        assert.equal(await n.evaluar("__escrituras[0].body.has('id_mascota')"),false);
        assert.equal(await n.evaluar("__escrituras[0].body.has('nombre_acompanante')"),false);
        await n.evaluar("__escrituras[0].resolve({ok:false,json:async()=>({success:false,message:'Respuesta perdida'})})");
        await n.esperar("!document.querySelector('[data-ingreso-rojo] [type=submit]').disabled");
        await n.evaluar("document.querySelector('[data-ingreso-solo-llegada]').click()");
        assert.equal(await n.evaluar("__escrituras[0].body.get('clave_operacion')===__escrituras[1].body.get('clave_operacion')"),true);
        await n.evaluar("__escrituras[1].resolve({ok:true,json:async()=>({success:true,resultado:{id_solicitud:3},message:'Llegada registrada'})})");
        await n.esperar('__lecturas.length>=3');
        await n.evaluar(`__lecturas.at(-1).resolve({ok:true,json:async()=>({success:true,catalogos:__catalogos,rojos:[{id_solicitud:3,id_mascota:9,nombre:'<img src=x onerror=error()>',fecha_llegada:'2030-01-07 09:00:00',estado:'espera_presencial'}],ingresos:[]})})`);
        await n.esperar("document.querySelector('[data-rojos-lista] h3') !== null");
        assert.equal(await n.evaluar("document.querySelector('[data-rojos-lista] img')===null"),true);
        assert.match(await n.evaluar("document.querySelector('[data-urgencias-aviso]').textContent"),/registrada/);
    });
});

test('C6 DOM real: validación en vivo y denegación de doble envío, actualización inmediata',chrome,async () => {
    await pagina('admin',async n => {
        assert.equal(await n.evaluar("document.querySelector('[data-ingreso-rojo]')===null"),true);
        await n.esperar("document.querySelector('[data-form-ausencia] select').options.length===1");
        await n.evaluar(`var f=document.querySelector('[data-form-ausencia]');f.elements.inicio.value='2030-01-07T09:00:00';f.elements.fin.value='2030-01-07T09:00:00';f.dispatchEvent(new Event('input',{bubbles:true}));`);
        assert.equal(await n.evaluar("document.querySelector('[data-form-ausencia] [type=submit]').disabled"),true);
        await n.evaluar(`var f=document.querySelector('[data-form-ausencia]');f.elements.fin.value='2030-01-07T10:00:00';f.dispatchEvent(new Event('input',{bubbles:true}));f.dispatchEvent(new Event('submit',{cancelable:true}));f.dispatchEvent(new Event('submit',{cancelable:true}));`);
        assert.equal(await n.evaluar('__escrituras.length'),1);
        await n.evaluar("__escrituras[0].resolve({ok:true,json:async()=>({success:true,message:'Ausencia efectiva'})})");
        await n.esperar("document.querySelector('[data-form-ausencia] [data-aviso]').textContent==='Ausencia efectiva'");
        await n.evaluar(`__lecturas.at(-1).resolve({ok:true,json:async()=>({success:true,ausencias:[{id:7,veterinario:'Beto',fecha_hora_inicio:'2030-01-07 09:00:00',fecha_hora_fin:'2030-01-07 10:00:00',estado:'aprobada'}]})})`);
        await n.esperar("document.querySelector('[data-lista-ausencias]').textContent.toLowerCase().includes('aprobada')");
        assert.equal(await n.evaluar("document.querySelector('[data-lista-ausencias] .coord-estado').dataset.estado"),'aprobada');
    });
});
