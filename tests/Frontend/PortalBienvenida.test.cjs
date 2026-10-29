const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorPortalInicio } = require('./apoyo/portal-inicio-servidor.cjs');
const php = process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php';
const entorno = { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' };

test('Bienvenida: saludo en la hora de Bogotá, límites de mañana/tarde/noche y escape del nombre', () => {
    for (const [hora, esperado] of [
        ['2026-10-29T00:00:00-05:00', 'Buenos días'],
        ['2026-10-29T11:59:59-05:00', 'Buenos días'],
        ['2026-10-29T12:00:00-05:00', 'Buenas tardes'],
        ['2026-10-29T18:59:59-05:00', 'Buenas tardes'],
        ['2026-10-29T19:00:00-05:00', 'Buenas noches'],
        ['2026-10-29T23:59:59-05:00', 'Buenas noches'],
        ['2026-10-29T19:00:00+00:00', 'Buenas tardes'],
    ]) {
        assert.equal(execFileSync(php, ['tests/Frontend/apoyo/portal-inicio-saludo.php', hora], { encoding: 'utf8', env: entorno }), esperado);
    }
    const html = execFileSync(php, ['tests/Frontend/apoyo/portal-inicio-vista.php', '<img/src=x/onerror=1> Apellido'], { encoding: 'utf8', env: entorno });
    assert.match(html, /home-bienvenida__nombre">&lt;img\/src=x\/onerror=1&gt;<\/span>/);
    assert.doesNotMatch(html, /<img\/src=x/);
    assert.match(html, /img\/portal-bienvenida\.png" alt="" aria-hidden="true"/);
    assert.ok(fs.statSync('public/img/portal-bienvenida.png').size > 0, 'La imagen sustituible existe; puede cambiar de raza sin editar la vista');
});

test('Bienvenida en Chrome: medidas, patas salidas y control idéntico en celular/tableta', { skip: !buscarChrome() }, async () => {
    const servidor = await servidorPortalInicio();
    const nav = await abrirChrome(buscarChrome());
    try {
        const medidas = [];
        for (const width of [1366, 1920, 1024, 1280, 1440, 390, 820]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: width < 1024 ? 844 : 768, deviceScaleFactor: 1, mobile: width < 768 });
            await nav.enviar('Page.navigate', { url: servidor.url });
            await nav.esperar("document.readyState==='complete' && document.querySelector('.home-bienvenida__perro img').complete");
            await nav.evaluar('new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))');
            const medida = await nav.evaluar(`(() => {
                const banner=document.querySelector('.home-bienvenida');
                const figura=banner.querySelector('.home-bienvenida__perro');
                const img=figura.querySelector('img');
                const cruz=banner.querySelector('.home-bienvenida__cruz');
                const saludo=document.getElementById('homeSaludo');
                const rect=e=>{
                    const r=e.getBoundingClientRect();
                    return {x:r.x,y:r.y,width:r.width,height:r.height,bottom:r.bottom};
                };
                let unas=null,cabeza=null;
                if(getComputedStyle(figura).display!=='none'){
                    const canvas=document.createElement('canvas');
                    canvas.width=img.naturalWidth;
                    canvas.height=img.naturalHeight;
                    const ctx=canvas.getContext('2d');
                    ctx.drawImage(img,0,0);
                    const pixeles=ctx.getImageData(0,0,canvas.width,canvas.height).data;
                    let ultimo=0,primero=canvas.height;
                    for(let y=0;y<canvas.height;y++) {
                        for(let x=0;x<canvas.width;x++) {
                            if(pixeles[(y*canvas.width+x)*4+3]>=16) {
                                ultimo=Math.max(ultimo,y+1);
                                primero=Math.min(primero,y);
                            }
                        }
                    }
                    const r=img.getBoundingClientRect();
                    unas=r.top+ultimo/canvas.height*r.height;
                    cabeza=r.top+primero/canvas.height*r.height;
                }
                return {ancho:innerWidth,banner:rect(banner),figura:rect(figura),cruz:rect(cruz),lateral:rect(document.querySelector('.home-grid__lateral')),unas,cabeza,figuraVisible:getComputedStyle(figura).display!=='none',fuente:getComputedStyle(saludo).fontSize,texto:saludo.innerText,saludo:rect(saludo),fechaVisible:getComputedStyle(banner.querySelector('.home-bienvenida__fecha')).display!=='none',saludoAnteriorVisible:getComputedStyle(banner.querySelector('.home-bienvenida__actual')).display!=='none',desborde:document.documentElement.scrollWidth>innerWidth+1};
            })()`);
            medidas.push(medida);
            assert.equal(medida.desborde, false, width + ' sin desborde');
            if (width >= 1024) {
                assert.equal(medida.banner.height, 112);
                assert.equal(medida.figuraVisible, true);
                assert.equal(medida.saludoAnteriorVisible, false);
                const relieve = medida.unas - medida.figura.bottom;
                assert.ok(relieve >= 18 && relieve <= 22, width + ': patas salidas ' + relieve + 'px');
                assert.ok(medida.cabeza <= medida.figura.y + 18, 'El perro baja sin agrandarse ni recortar la cabeza');
                assert.ok(Math.abs(medida.banner.y - medida.lateral.y) <= 1, 'Horario de hoy alinea con el banner');
                if (width >= 1280) {
                    assert.equal(medida.figura.width, 170);
                    assert.equal(medida.figura.height, 112);
                    assert.equal(medida.cruz.width, 112);
                    assert.equal(medida.cruz.height, 112);
                    assert.equal(medida.fuente, '30px');
                }
            } else {
                assert.equal(medida.figuraVisible, false);
                assert.equal(medida.fechaVisible, false);
                assert.equal(medida.saludoAnteriorVisible, true);
                assert.equal(medida.texto, 'Fabio');
                // Chrome desvanece su scrollbar superpuesta: el margen derecho de16px no es contenido del portal.
                const clip = { x: 0, y: 0, width: width - 16, height: 844, scale: 1 };
                const captura = await nav.enviar('Page.captureScreenshot', { format: 'png', clip, captureBeyondViewport: false });
                // Comparar con el marcado anterior en el mismo documento evita depender del día,
                // de un archivo ignorado en scratch o de la plataforma donde se ejecute Chrome.
                await nav.evaluar(`(() => {
                    window.bienvenidaOriginal=document.querySelector('.home-cabecera');
                    const anterior=window.bienvenidaOriginal.cloneNode(true);
                    anterior.classList.remove('home-bienvenida');
                    anterior.querySelector('.home-bienvenida__saludo').removeAttribute('class');
                    anterior.querySelector('.home-bienvenida__actual').classList.remove('home-bienvenida__actual');
                    for(const clase of ['fecha','perro','cruz']) anterior.querySelector('.home-bienvenida__'+clase).remove();
                    anterior.querySelector('#homeSaludo').textContent=anterior.querySelector('.home-bienvenida__nombre').textContent;
                    window.bienvenidaOriginal.replaceWith(anterior);
                })()`);
                const anterior = await nav.enviar('Page.captureScreenshot', { format: 'png', clip, captureBeyondViewport: false });
                fs.writeFileSync('scratch/r/portal-bienvenida/control-actual-' + width + '.png', Buffer.from(captura.data, 'base64'));
                fs.writeFileSync('scratch/r/portal-bienvenida/control-anterior-' + width + '.png', Buffer.from(anterior.data, 'base64'));
                const huella = datos => createHash('sha256').update(Buffer.from(datos, 'base64')).digest('hex');
                assert.equal(huella(captura.data), huella(anterior.data), 'Control ' + width + ' idéntico píxel por píxel');
                await nav.evaluar("document.querySelector('.home-cabecera').replaceWith(window.bienvenidaOriginal)");
            }
            fs.mkdirSync('scratch/r/portal-bienvenida', { recursive: true });
            const captura = await nav.enviar('Page.captureScreenshot', { format: 'png' });
            fs.writeFileSync('scratch/r/portal-bienvenida/despues-' + width + '.png', Buffer.from(captura.data, 'base64'));
        }
        fs.writeFileSync('scratch/r/portal-bienvenida/despues.json', JSON.stringify(medidas, null, 2));
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
});
