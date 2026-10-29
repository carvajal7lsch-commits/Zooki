const test = require('node:test');
const assert = require('node:assert/strict');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorNavegacion, rutasNavegacion } = require('./apoyo/navegacion-servidor.cjs');

async function observarTransiciones(nav) {
    await nav.enviar('Page.addScriptToEvaluateOnNewDocument', { source: `
        window.pruebaNavegacion={documento:Math.random(),compatible:'CSSViewTransitionRule' in window};
        window.addEventListener('pagereveal',e=>{
            window.pruebaNavegacion.transicion=Boolean(e.viewTransition);
            if(!e.viewTransition)return;
            e.viewTransition.ready.then(()=>{
                window.pruebaNavegacion.animaciones=document.getAnimations().map(a=>a.animationName);
                window.pruebaNavegacion.barra=getComputedStyle(document.documentElement,'::view-transition-group(zooki-barra)').animationName;
            }).catch(error=>{window.pruebaNavegacion.error=error.message});
            e.viewTransition.finished.then(()=>{window.pruebaNavegacion.terminada=true}).catch(error=>{window.pruebaNavegacion.error=error.message;window.pruebaNavegacion.terminada=true});
        });
    ` });
}

async function entrar(nav, url, width = 1366) {
    await nav.enviar('Page.bringToFront');
    if (width < 768) {
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: 844, deviceScaleFactor: 1, mobile: true });
    } else {
        await nav.enviar('Emulation.clearDeviceMetricsOverride');
        const ventana = await nav.enviar('Browser.getWindowForTarget');
        await nav.enviar('Browser.setWindowBounds', { windowId: ventana.windowId, bounds: { width, height: 855 } });
    }
    await nav.enviar('Page.navigate', { url });
    await nav.esperar("document.readyState==='complete' && document.querySelector('.pill-sidebar')?.dataset.ayudasListas==='1'");
    await nav.evaluar('new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))');
}

const selector = accion => '.pill-nav-item[href="index.php?action=' + accion + '"]';

test('R recorrido completo: Inicio de ida y vuelta en todos los destinos, con caché fría y transición real', { skip: !buscarChrome() }, async () => {
    const nav = await abrirChrome(buscarChrome());
    try {
        await observarTransiciones(nav);
        await nav.enviar('Network.enable');
        await nav.enviar('Network.setCacheDisabled', { cacheDisabled: true });
        const recorridos = {
            admin: ['admin_panel', 'admin_usuarios', 'admin_citas', 'admin_auditoria', 'admin_configuracion'],
            vet: ['vet_area', 'vet_pacientes', 'vet_agenda', 'mi_perfil'],
            plataforma: ['plataforma_inicio', 'plataforma_grafo'],
        };
        for (const [rol, [inicio, ...destinos]] of Object.entries(recorridos)) {
            const servidor = await servidorNavegacion(rol);
            try {
                await entrar(nav, servidor.url + '/index.php?action=' + inicio);
                for (const destino of destinos) {
                    for (const accion of [destino, inicio]) {
                        const enlace = selector(accion);
                        const posicion = await nav.evaluar(`(() => {const r=document.querySelector(${JSON.stringify(enlace)}).getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2}})()`);
                        await nav.enviar('Input.dispatchMouseEvent', { type: 'mousePressed', ...posicion, button: 'left', clickCount: 1 });
                        await nav.enviar('Input.dispatchMouseEvent', { type: 'mouseReleased', ...posicion, button: 'left', clickCount: 1 });
                        await nav.esperar(`location.search==='?action=${accion}' && document.readyState==='complete' && window.pruebaNavegacion.transicion !== undefined`);
                        const medida = await nav.evaluar('window.pruebaNavegacion');
                        assert.equal(medida.transicion, true, rol + ' → ' + accion + ' conserva el fotograma anterior');
                        await nav.esperar('window.pruebaNavegacion.terminada');
                        assert.equal(await nav.evaluar('window.pruebaNavegacion.error'), undefined, rol + ' → ' + accion + ' no omite la transición');
                    }
                }
            } finally {
                await servidor.cerrar();
            }
        }
    } finally {
        await nav.cerrar();
    }
});

test('R navegación: documentos PHP, transición real, Atrás y ayudas seguras en los tres roles', { skip: !buscarChrome() }, async () => {
    const nav = await abrirChrome(buscarChrome());
    try {
        await observarTransiciones(nav);
        for (const rol of ['admin', 'vet', 'plataforma']) {
            const servidor = await servidorNavegacion(rol);
            const [origen, destino] = rutasNavegacion[rol];
            const enlace = selector(destino);
            try {
                await entrar(nav, servidor.url + '/index.php?action=' + origen);
                const documentoAnterior = await nav.evaluar('window.pruebaNavegacion.documento');
                assert.notEqual(await nav.evaluar("getComputedStyle(document.querySelector('.main-content')).animationName"), 'pageFadeIn', 'No reaparece durante medio segundo');
                assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.main-content')).viewTransitionName"), 'none', 'Sin apilamiento que encierre los modales fuera de la transición');
                const rect = await nav.evaluar(`(() => {const r=document.querySelector(${JSON.stringify(enlace)}).getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2}})()`);
                await nav.enviar('Input.dispatchMouseEvent', { type: 'mousePressed', x: rect.x, y: rect.y, button: 'left', clickCount: 1 });
                await nav.enviar('Input.dispatchMouseEvent', { type: 'mouseReleased', x: rect.x, y: rect.y, button: 'left', clickCount: 1 });
                await nav.esperar(`location.search==='?action=${destino}' && document.readyState==='complete'`);
                assert.notEqual(await nav.evaluar('window.pruebaNavegacion.documento'), documentoAnterior, 'El servidor entrega el documento y mantiene su inicialización');
                if (await nav.evaluar('window.pruebaNavegacion.compatible')) {
                    await nav.esperar('window.pruebaNavegacion.transicion !== undefined');
                    const iniciada = await nav.evaluar('window.pruebaNavegacion.transicion');
                    if (iniciada) {
                        await nav.esperar('window.pruebaNavegacion.terminada');
                        const transicion = await nav.evaluar('window.pruebaNavegacion');
                        if (!transicion.error) {
                            assert.ok(transicion.animaciones.includes('barra-contenido-entra'), JSON.stringify(transicion));
                            assert.equal(transicion.barra, 'none', 'La barra no vuelve a desvanecerse');
                        }
                    }
                    const omitida = await nav.evaluar('!window.pruebaNavegacion.transicion || Boolean(window.pruebaNavegacion.error)');
                    if (omitida) {
                        await nav.esperar("document.documentElement.classList.contains('navegacion-respaldo')");
                        assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.main-content')).animationName"), 'barra-contenido-entra');
                        assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.main-content')).animationDuration"), '0.16s');
                    }
                }
                assert.equal(await nav.evaluar(`document.querySelector(${JSON.stringify(enlace)}).getAttribute('aria-current')`), 'page');
                const historial = await nav.enviar('Page.getNavigationHistory');
                await nav.enviar('Page.navigateToHistoryEntry', { entryId: historial.entries[historial.currentIndex - 1].id });
                await nav.esperar(`location.search==='?action=${origen}' && document.readyState==='complete'`);
                assert.equal(await nav.evaluar(`document.querySelector(${JSON.stringify(selector(origen))}).getAttribute('aria-current')`), 'page');
                await nav.evaluar('new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))');
                {
                    const rect = await nav.evaluar(`(() => {const r=document.querySelector(${JSON.stringify(enlace)}).getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2}})()`);
                    await nav.enviar('Input.dispatchMouseEvent', { type: 'mouseMoved', x: rect.x, y: rect.y });
                    await nav.esperar("!document.querySelector('.pill-ayuda').hidden");
                    const ayuda = await nav.evaluar(`(() => {
                        const a=document.querySelector('.pill-ayuda');
                        const e=document.querySelector(${JSON.stringify(enlace)});
                        const c=getComputedStyle(a),r=a.getBoundingClientRect();
                        const icono=e.querySelector(':scope > i, :scope > svg, .pill-logo-img')?.getBoundingClientRect();
                        const referencia=icono?.width?icono:e.getBoundingClientRect();
                        return {texto:a.textContent,nombre:e.getAttribute('aria-label'),fondo:c.backgroundColor,color:c.color,title:e.title,descripcion:e.getAttribute('aria-describedby'),derecha:r.right,abajo:r.bottom,separacion:r.left-referencia.right};
                    })()`);
                    assert.equal(ayuda.texto, ayuda.nombre);
                    assert.equal(ayuda.title, '');
                    assert.equal(ayuda.descripcion, 'barraNavegacionAyuda');
                    assert.equal(ayuda.fondo, 'rgb(255, 255, 255)');
                    assert.equal(ayuda.color, 'rgb(0, 82, 255)');
                    assert.ok(Math.abs(ayuda.separacion - 18) <= 1, rol + ': ayuda próxima al icono');
                    assert.ok(ayuda.derecha <= 1366 && ayuda.abajo <= 768);
                    // Un nombre se trata como texto, aunque contuviera marcado.
                    await nav.evaluar(`document.querySelector(${JSON.stringify(enlace)}).dataset.barraAyuda='<img src=x onerror=alert(1)> ` + rol + `';document.querySelector(${JSON.stringify(selector(origen))}).focus();document.querySelector(${JSON.stringify(enlace)}).focus()`);
                    assert.equal(await nav.evaluar("document.querySelector('.pill-ayuda').children.length"), 0);
                    assert.match(await nav.evaluar("document.querySelector('.pill-ayuda').textContent"), /<img/);
                    await nav.enviar('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                    await nav.enviar('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                    assert.equal(await nav.evaluar("document.querySelector('.pill-ayuda').hidden"), true);
                    // Ctrl-clic sigue siendo un enlace nativo: el script no cancela el evento.
                    assert.equal(await nav.evaluar(`(() => {
                        const a=document.querySelector(${JSON.stringify(enlace)});
                        let cancelado;
                        document.addEventListener('click',e=>{cancelado=e.defaultPrevented;e.preventDefault()},{once:true});
                        a.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true,ctrlKey:true}));
                        return cancelado;
                    })()`), false);
                }
            } finally {
                await servidor.cerrar();
            }
        }
        // CDP también notifica el aborto interno de Chrome al omitir una captura entre documentos.
        // Ese caso ya exige arriba el respaldo de 160 ms; ningún error de la aplicación se admite.
        const abortoDeCaptura = 'InvalidStateError: Transition was aborted because of invalid state. ViewTransition opt-in disabled';
        assert.deepEqual(nav.errores.filter(error => error !== abortoDeCaptura), []);
    } finally {
        await nav.cerrar();
    }
});

test('R navegación: cancelar beforeunload no cambia módulo ni selección', { skip: !buscarChrome() }, async () => {
    const nav = await abrirChrome(buscarChrome());
    const servidor = await servidorNavegacion('admin');
    try {
        await entrar(nav, servidor.url + '/index.php?action=admin_usuarios');
        await nav.evaluar("window.addEventListener('beforeunload',e=>{e.preventDefault();e.returnValue=''})");
        const navegacion = nav.enviar('Runtime.evaluate', { expression: "document.querySelector('.pill-nav-item[href=\"index.php?action=admin_citas\"]').click()", userGesture: true });
        await new Promise(r => setTimeout(r, 150));
        await nav.enviar('Page.handleJavaScriptDialog', { accept: false });
        await navegacion;
        assert.equal(await nav.evaluar('location.search'), '?action=admin_usuarios');
        assert.equal(await nav.evaluar("document.querySelector('.pill-nav-item.active').getAttribute('href')"), 'index.php?action=admin_usuarios');
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
});

test('R navegación: movimiento reducido, respaldo y barra móvil sin ayudas superpuestas', { skip: !buscarChrome() }, async () => {
    const nav = await abrirChrome(buscarChrome());
    const servidor = await servidorNavegacion('vet');
    try {
        await observarTransiciones(nav);
        await nav.enviar('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
        await entrar(nav, servidor.url + '/index.php?action=vet_pacientes');
        await nav.evaluar("document.querySelector('.pill-nav-item[href=\"index.php?action=vet_agenda\"]').click()");
        await nav.esperar("location.search==='?action=vet_agenda' && document.readyState==='complete'");
        await nav.evaluar('new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))');
        const movimiento = await nav.evaluar('window.pruebaNavegacion');
        assert.ok(!(movimiento.animaciones || []).includes('barra-contenido-entra'), 'Movimiento reducido sin fundido');
        assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.main-content')).animationName"), 'none');
        await entrar(nav, servidor.url + '/index.php?action=vet_pacientes', 390);
        await nav.evaluar("document.querySelector('.pill-nav-item').dispatchEvent(new PointerEvent('pointerover',{bubbles:true,pointerType:'mouse'}))");
        assert.equal(await nav.evaluar("document.querySelector('.pill-ayuda').hidden"), true);
        assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth+1'), true);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
});
