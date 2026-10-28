const test = require('node:test');
const assert = require('node:assert/strict');
const { servidorPacientes } = require('./apoyo/pacientes-movil-servidor.cjs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const casos = [
    ["abrirModalRegistro('propietario')", '#modalNuevoRegistro'],
    ["abrirModalRegistro('mascota')", '#modalNuevoRegistro'],
    ["document.querySelector('[data-vincular-mascota-cliente]').click()", '#modalNuevoRegistro'],
    ['editOwner(6)', '#modalEditarPropietario'],
    ['editPet(1)', '#modalEditarMascota'],
    ["openDrawer('drawerHistorial')", '#drawerHistorial'],
    ["openVaccineModal(1,'Doki')", '#drawerVacuna'],
    ["openDewormingModal(1,'Doki')", '#drawerDesparasitacion']
];
test('R B: ocho entradas en hojas sin desborde, cuerpo desplazable, pie y cierre visibles', { skip: !buscarChrome() }, async () => {
    const s = await servidorPacientes({ directorio: true });
    const n = await abrirChrome(buscarChrome());
    try {
        await n.enviar('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        for (const [abrir, selector] of casos) {
            await n.enviar('Page.navigate', { url: s.url });
            await n.esperar("document.querySelectorAll('#ownersGrid .client-card').length===8");
            await n.evaluar(abrir);
            const medidas = await n.evaluar(`(() => {
                const raiz = document.querySelector('${selector}');
                const panel = raiz.querySelector('.users-modal__panel') || raiz;
                const r = panel.getBoundingClientRect();
                const cierre = raiz.querySelector('.close-modal, .drawer-close').getBoundingClientRect();
                const pie = [...raiz.querySelectorAll('.users-modal__footer')].find(e => e.getBoundingClientRect().height);
                const p = pie?.getBoundingClientRect();
                const cuerpo = [...raiz.querySelectorAll('.users-modal__body, .drawer-body')].find(e => e.getBoundingClientRect().height);
                cuerpo.scrollTop = cuerpo.scrollHeight;
                return { ancho: r.width, izquierda: r.left, fondo: r.bottom, alto: r.height, cerrar: cierre.width >= 40 && cierre.right <= innerWidth, pie: !p || p.bottom <= innerHeight && p.top > r.top, desborde: cuerpo.scrollWidth > cuerpo.clientWidth, pagina: document.documentElement.scrollWidth > innerWidth };
            })()`);
            assert.equal(medidas.ancho, 390, selector);
            assert.equal(medidas.izquierda, 0, selector);
            assert.equal(medidas.fondo, 844, selector);
            assert.equal(medidas.cerrar, true, JSON.stringify({ selector, medidas }));
            assert.equal(medidas.pie, true, selector);
            assert.equal(medidas.desborde, false, selector);
            assert.equal(medidas.pagina, false, selector);
            await n.evaluar(`document.querySelector('${selector} .close-modal, ${selector} .drawer-close').click()`);
            assert.equal(await n.evaluar(`(()=>{const e=document.querySelector('${selector}');return e.classList.contains('d-none')||!e.classList.contains('is-open')})()`), true);
        }
        // Los botones conservan sus formularios y disparan los handlers originales con sus POST.
        await n.evaluar("window.Swal={fire:async()=>({}),mixin:()=>({fire:async()=>({})})};window.zookiRecargarConAviso=()=>{}");
        await n.evaluar('editOwner(6)');
        await n.evaluar("document.getElementById('formEditPropietario').requestSubmit()");
        await n.esperar("document.getElementById('modalEditarPropietario').classList.contains('d-none')");
        for (const [abrir, form, valores, accion] of [
            ["openVaccineModal(1,'Doki')", 'formVacuna', { nombre_vacuna:'Rabia', laboratorio:'Laboratorio', lote:'ABC123' }, 'registrar_vacuna_ajax'],
            ["openDewormingModal(1,'Doki')", 'formDesparasitacion', { producto:'Producto' }, 'registrar_desparasitacion_ajax']
        ]) {
            await n.evaluar(abrir);
            await n.evaluar(`(()=>{const f=document.getElementById('${form}');Object.entries(${JSON.stringify(valores)}).forEach(([k,v])=>f.elements[k].value=v);f.requestSubmit()})()`);
            await n.esperar(`!document.getElementById('${form}').closest('.drawer').classList.contains('is-open')`);
            assert.equal(s.llamadas.some(l => l.metodo === 'POST' && l.accion === accion), true, accion);
        }
        assert.equal(s.llamadas.some(l => l.metodo === 'POST' && l.accion === 'actualizar_propietario_ajax'), true);
        await n.evaluar("abrirModalRegistro('propietario')");
        await n.evaluar(`(() => {
            const f = document.getElementById('tabNuevoPropietario').querySelector('form');
            const valores = { documento:'1080361992', nombre_completo:'Ana Pérez', email:'ana@zooki.test', telefono:'3001112233' };
            Object.entries(valores).forEach(([k,v]) => f.elements[k].value=v);
            f.requestSubmit();
        })()`);
        await n.esperar("document.getElementById('tabNuevaMascota').classList.contains('active')");
        assert.equal(s.llamadas.some(l => l.metodo === 'POST' && l.accion === 'guardar_propietario_ajax'), true);
        for (const [abrir, form, accion] of [
            ["abrirModalRegistro('mascota')", 'formMascota', 'guardar_mascota_ajax'],
            ['editPet(1)', 'formEditMascota', 'actualizar_mascota_ajax']
        ]) {
            await n.evaluar(abrir);
            await n.evaluar(`loadBreeds(1, '${form === 'formMascota' ? 'new_raza' : 'edit_raza'}', 1)`);
            const valido = await n.evaluar(`(() => {
                const f = document.getElementById('${form}');
                const valores = { nombre:'Doki', especie:'1', raza:'1', sexo:'Macho', fecha_nacimiento:'2024-12-31', peso:'9', id_propietario:'6', estado:'1' };
                Object.entries(valores).forEach(([k,v]) => { if(f.elements[k]) f.elements[k].value=v; });
                const valido = mascotaValida(f);
                f.requestSubmit();
                return { valido, avisos:[...f.querySelectorAll('.mascota-aviso')].filter(e=>e.textContent).map(e=>[e.id,e.textContent]), campos:[...f.elements].filter(e=>e.name).map(e=>[e.name,e.value]) };
            })()`);
            assert.equal(valido.valido, true, JSON.stringify({ form, ...valido }));
            await n.esperar(`document.getElementById('${form}').closest('.users-modal').classList.contains('d-none')`);
            assert.equal(s.llamadas.some(l => l.metodo === 'POST' && l.accion === accion), true, accion);
        }
        // La misma pieza vuelve a su posición de escritorio al cambiar de ancho.
        await n.enviar('Emulation.setDeviceMetricsOverride', { width: 820, height: 1180, deviceScaleFactor: 1, mobile: false });
        await n.evaluar('new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
        assert.equal(await n.evaluar("!!document.querySelector('#formVacuna .drawer-body > [type=submit]') && !document.querySelector('#formVacuna .users-modal__footer')"), true);
        assert.deepEqual(n.errores, []);
    } finally {
        await n.cerrar();
        await s.cerrar();
    }
});
