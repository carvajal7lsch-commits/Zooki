const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');

function vista(caso = 'breve', contexto = 'unico') {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
    return execFileSync(php, ['tests/Frontend/apoyo/inicio-pulido-vista.php', caso, contexto], { encoding: 'utf8', env: { ...process.env, TRUSTED_PROXIES: '127.0.0.2', ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' } });
}

test('Inicio: agrupa nombres, conserva el total y escapa el resumen; recientes vacío ilustrado', () => {
    const html = vista();
    assert.doesNotMatch(html, /Fatal error|Warning:|Mia y Mia/);
    assert.match(html, /Mia \(2\) y Doki/);
    assert.match(html, /3 por resolver/);
    assert.match(html, /inicio-recientes__vacio[\s\S]*golden-retriever\.png[\s\S]*Aún no has atendido pacientes/);
    assert.match(html, /inicio-lineas inicio-lineas--breve/);
    assert.doesNotMatch(vista('muchas'), /inicio-lineas--breve/);
    assert.match(vista('vacio'), /Día libre/);
    assert.match(vista('escape'), /&lt;img src=x&gt; \(2\)/);
    assert.doesNotMatch(vista('escape'), /<img src=x>/);
    for (const archivo of ['views/vet/area.php','views/vet/partials/inicio_dia.php']) {
        assert.doesNotMatch(fs.readFileSync(archivo,'utf8'), /\s(?:style|onclick)=|<script/i);
    }
});

function cssLocal(archivo) {
    return fs.readFileSync(archivo, 'utf8').replace(/@import\s+url\(['"]?([^'"\)]+)['"]?\);/g, (_, ruta) => {
        if (/^https?:/.test(ruta)) return '';
        return cssLocal(path.join(path.dirname(archivo), ruta.split('?')[0]));
    });
}

function pagina(html) {
    const estilos = [...html.matchAll(/<link[^>]+href="(css\/[^"?]+)[^"]*"[^>]*>/g)].map(m => '<style>' + cssLocal('public/' + m[1]) + '</style>').join('\n');
    return html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '')
        .replace(/loading="lazy"/g, 'loading="eager"')
        .replace('</head>', `${estilos}<style>#global-loader{display:none}*{animation:none!important;transition:none!important}</style></head>`)
        .replace(/src="(img\/[^"?]+)"/g, (atributo, ruta) => fs.existsSync('public/' + ruta) ? `src="data:image/png;base64,${fs.readFileSync('public/' + ruta).toString('base64')}"` : atributo);
}

test('Inicio real: agenda corta centrada, lista larga accesible y contexto móvil sin pastilla vacía', { skip: !buscarChrome() }, async () => {
    const nav = await abrirChrome(buscarChrome());
    try {
        for (const caso of ['breve','muchas','vacio']) {
            const arbol = await nav.enviar('Page.getFrameTree');
            await nav.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html: pagina(vista(caso)) });
            for (const width of [1366,900,390,360]) {
                await nav.enviar('Emulation.setDeviceMetricsOverride', {width,height:width===1366?768:844,deviceScaleFactor:1,mobile:width<768});
                await nav.esperar(`document.querySelector('.inicio-recientes__vacio img').complete && document.querySelector('.inicio-recientes__vacio img').naturalWidth>0`);
                const estado = await nav.evaluar(`(() => {
                    const lista=document.querySelector('.inicio-lineas');
                    const r=lista?.getBoundingClientRect();
                    const filas=lista?[...lista.children]:[];
                    const primero=filas[0]?.getBoundingClientRect();
                    const ultimo=filas.at(-1)?.getBoundingClientRect();
                    const vacio=document.querySelector('.inicio-recientes__vacio');
                    return {horizontal:document.documentElement.scrollWidth<=innerWidth,pagina:document.documentElement.scrollHeight<=innerHeight,centrado:r?Math.abs((primero.top+ultimo.bottom)/2-(r.top+r.bottom)/2)<2:true,primerAccesible:r?primero.top>=r.top-1:true,scroll:r?lista.scrollHeight>lista.clientHeight:false,contexto:getComputedStyle(document.querySelector('.cabecera-cuenta [data-clinica-escritorio]')).display,vacioIlustrado:!!vacio?.querySelector('img'),vacioCabe:vacio.scrollWidth<=vacio.clientWidth};
                })()`);
                assert.equal(estado.horizontal,true,JSON.stringify({caso,width,estado}));
                assert.equal(estado.primerAccesible,true);
                assert.equal(estado.vacioIlustrado,true);
                assert.equal(estado.vacioCabe,true,JSON.stringify({caso,width,estado}));
                if(width>=1024) {
                    assert.equal(estado.pagina,true,JSON.stringify({caso,width,estado}));
                    if(caso==='breve') assert.equal(estado.centrado,true,JSON.stringify(estado));
                    if(caso==='muchas') assert.equal(estado.scroll,true);
                }
                if(width<768) assert.equal(estado.contexto,'none');
                if(process.env.ZOOKI_CAPTURA_INICIO==='1') {
                    const captura=await nav.enviar('Page.captureScreenshot',{format:'png'});
                    fs.writeFileSync(`scratch/inicio-pulido-${caso}-${width}.png`,Buffer.from(captura.data,'base64'));
                }
            }
        }
        const arbol = await nav.enviar('Page.getFrameTree');
        await nav.enviar('Page.setDocumentContent', {frameId:arbol.frameTree.frame.id,html:pagina(vista('breve','varios'))});
        assert.equal(await nav.evaluar(`document.querySelector('a.cabecera-clinica[href*="seleccionar_contexto"] .cabecera-clinica__cambiar')?.textContent==='Cambiar'`),true);
        assert.deepEqual(nav.errores,[]);
    } finally {
        await nav.cerrar();
    }
});
