// ==UserScript==
// @name         Zooki · Guion de sustentación
// @namespace    zooki.demo
// @version      1.1.0
// @description  Un paso por tecla sobre los controles reales de Zooki; solo guarda la consulta de Rocco; nunca reserva citas.
// @match        https://zooki.secarvajal.com/*
// @match        https://zooki-pruebas.secarvajal.com/*
// @match        http://localhost:3000/*
// @run-at       document-end
// @grant        none
// @sandbox      raw
// @noframes
// @author       Juan Sebastián Carvajal
// ==/UserScript==

(() => {
    'use strict';
    if (document.getElementById('zkdemo-guion')) return;
    const normalizar = texto => String(texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
    const dia = fecha => `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;
    const visible = nodo => Boolean(nodo && !nodo.hidden && nodo.getClientRects().length && getComputedStyle(nodo).visibility !== 'hidden');
    const host = document.createElement('div');
    host.id = 'zkdemo-guion';
    const sombra = host.attachShadow({ mode: 'open' });
    const css = document.createElement('style');
    css.textContent = `
        :host{all:initial;position:fixed;inset:0;z-index:2147483646;pointer-events:none;font:13px/1.4 Arial,sans-serif;color:#17345f}
        *{box-sizing:border-box} [hidden]{display:none!important}
        .panel{position:absolute;right:12px;bottom:14px;width:min(310px,calc(100vw - 24px));max-height:60vh;overflow:auto;background:#fff;border:1px solid #bad0ff;border-radius:14px;box-shadow:0 6px 24px #12356826;padding:12px;pointer-events:auto}
        header{display:flex;align-items:center;justify-content:space-between;gap:12px;color:#0755ff;font-weight:700}button,a{font:inherit;white-space:nowrap;color:#0755ff;background:#edf3ff;border:1px solid #cbdcff;border-radius:8px;padding:6px 9px;cursor:pointer;text-decoration:none}
        button:disabled{opacity:.5;cursor:wait}button:hover,a:hover{background:#deebff}h2{font-size:14px;margin:9px 0 5px}p{margin:6px 0}small{font-size:11px;color:#526b8d}ol{padding-left:19px;margin:8px 0} .error{color:#b42335} .acciones{display:flex;gap:6px;margin-top:9px}
        .punto{position:absolute;right:14px;bottom:14px;width:18px;height:18px;padding:0;background:#0755ff;border:3px solid #fff;border-radius:50%;box-shadow:0 0 0 1px #0755ff;pointer-events:auto}
        .marca{position:fixed;border:3px solid #0755ff;border-radius:9px;box-shadow:0 0 0 3px #0755ff22;pointer-events:none}
    `;
    const crear = (tipo, texto) => {
        const nodo = document.createElement(tipo);
        if (texto) nodo.textContent = texto;
        return nodo;
    };
    const panel = crear('section');
    panel.className = 'panel';
    panel.setAttribute('aria-label', 'Guion de demostración Zooki');
    const cabecera = crear('header', 'Zooki · Demo');
    const minimizar = crear('button', '−');
    minimizar.type = 'button';
    minimizar.setAttribute('aria-label', 'Minimizar guion');
    cabecera.append(minimizar);
    const titulo = crear('h2');
    const mensaje = crear('p');
    mensaje.setAttribute('role', 'status');
    mensaje.setAttribute('aria-live', 'polite');
    const detalle = crear('div');
    const ayuda = crear('small', 'Shift+S siguiente · Shift+Q reiniciar');
    const acciones = crear('div');
    acciones.className = 'acciones';
    const siguiente = crear('button', 'Siguiente');
    siguiente.type = 'button';
    const reiniciar = crear('button', 'Reiniciar');
    reiniciar.type = 'button';
    acciones.append(siguiente, reiniciar);
    panel.append(cabecera, titulo, mensaje, detalle, ayuda, acciones);
    const punto = crear('button');
    punto.type = 'button';
    punto.className = 'punto';
    punto.hidden = true;
    punto.setAttribute('aria-label', 'Abrir guion Zooki');
    sombra.append(css, panel, punto);
    document.body.append(host);
    minimizar.addEventListener('click', () => {
        panel.hidden = true;
        punto.hidden = false;
    });
    punto.addEventListener('click', () => {
        panel.hidden = false;
        punto.hidden = true;
    });

    let escenario = '';
    let paso = 0;
    let ocupado = false;
    let generacion = 0;
    let campoManual = null;
    let clicManual = 0;
    let marcados = [];
    let fotogramaMarca = null;
    let geometriaMarca = '';
    const sintomasRocco = [['picazón', 'sintoma.picazon'], ['costras', 'sintoma.costras_piel'], ['se le cae el pelo', 'sintoma.perdida_pelo']];
    const sintomasMishi = [['fiebre', 'sintoma.fiebre'], ['decaída', 'sintoma.decaimiento'], ['vómito', 'sintoma.vomito']];

    function detectar() {
        const accion = new URL(location.href).searchParams.get('action');
        if (accion === 'vet_agenda' && document.querySelector('.agenda')) {
            const cerrada = typeof calendarInstance !== 'undefined' && calendarInstance?.getEvents().some(e => dia(e.start) === dia(new Date()) && normalizar(e.extendedProps.mascota_nombre || e.title) === 'rocco' && e.extendedProps.estado === 'completada');
            return cerrada ? 'agenda_mishi' : 'agenda';
        }
        if (accion === 'vet_atencion' && document.querySelector('#atencionApp')) {
            const nombre = document.querySelector('[data-nombre-mascota]')?.dataset.nombreMascota;
            return ['rocco', 'mishi'].includes(normalizar(nombre)) ? normalizar(nombre) : '';
        }
        if (accion === 'portal_propietario' && document.querySelector('#portalBookingForm')) return 'portal';
        return '';
    }

    const nombres = {
        agenda: ['Resaltar Rocco en curso y las siguientes estimadas'],
        rocco: ['Marcar los tres síntomas de sarna', 'Usar sarna y leer el descarte MDR1 bajo los tratamientos', 'Finalizar consulta de Rocco'],
        agenda_mishi: ['Mostrar la agenda recalculada e iniciar Atención de Mishi'],
        mishi: ['Marcar fiebre, decaimiento y vómito en Atención de Mishi', 'Elegir paracetamol y ver el bloqueo felino'],
        portal: ['Evaluar Simón: crea una solicitud de demo, nunca una reserva']
    };

    function informar(texto, error = false) {
        mensaje.textContent = texto;
        mensaje.className = error ? 'error' : '';
        if (error) {
            panel.hidden = false;
            punto.hidden = true;
        }
    }

    function pintar() {
        const pasos = nombres[escenario] || [];
        titulo.textContent = pasos.length ? `Paso ${Math.min(paso + 1, pasos.length)}/${pasos.length}: ${pasos[paso] || 'Escenario terminado'}` : 'Abre Agenda, Atención de Rocco o Mishi, o Portal';
        siguiente.disabled = ocupado || !pasos.length || paso >= pasos.length;
    }

    function limpiarMarcas() {
        cancelAnimationFrame(fotogramaMarca);
        fotogramaMarca = null;
        geometriaMarca = '';
        sombra.querySelectorAll('.marca').forEach(n => n.remove());
        marcados = [];
    }

    function dibujar() {
        sombra.querySelectorAll('.marca').forEach(n => n.remove());
        for (const nodo of marcados.filter(visible)) {
            const r = nodo.getBoundingClientRect();
            const marca = crear('div');
            marca.className = 'marca';
            Object.assign(marca.style, { left: `${r.left - 3}px`, top: `${r.top - 3}px`, width: `${r.width + 6}px`, height: `${r.height + 6}px` });
            sombra.append(marca);
        }
    }

    // Atención redistribuye sus columnas al recibir las pautas; el marco sigue la fila real.
    function seguirMarcas() {
        const geometria = marcados.filter(visible).map(nodo => {
            const r = nodo.getBoundingClientRect();
            return [r.left, r.top, r.width, r.height].join(',');
        }).join('|');
        if (geometria !== geometriaMarca) {
            geometriaMarca = geometria;
            dibujar();
        }
        fotogramaMarca = marcados.length ? requestAnimationFrame(seguirMarcas) : null;
    }

    function resaltar(nodos) {
        limpiarMarcas();
        marcados = nodos.filter(Boolean);
        marcados[0]?.scrollIntoView({ block: innerWidth < 768 ? 'center' : 'nearest' });
        seguirMarcas();
    }
    window.addEventListener('resize', dibujar);
    document.addEventListener('scroll', dibujar, true);

    async function esperar(buscar, falta, turno = generacion) {
        return new Promise((resolver, rechazar) => {
            let terminado = false;
            const cerrar = (error, valor) => {
                if (terminado) return;
                terminado = true;
                observador.disconnect();
                clearInterval(sondeo);
                clearTimeout(limite);
                if (error) rechazar(error);
                else resolver(valor);
            };
            const revisar = () => {
                if (turno !== generacion) return cerrar(new Error('Escenario reiniciado.'));
                try {
                    const valor = buscar();
                    if (valor) cerrar(null, valor);
                } catch (error) {
                    cerrar(error);
                }
            };
            const observador = new MutationObserver(revisar);
            observador.observe(document.body, { childList: true, subtree: true, attributes: true });
            const sondeo = setInterval(revisar, 80);
            const limite = setTimeout(() => cerrar(new Error(`Faltó ${falta} (8 s).`)), 8000);
            revisar();
        });
    }

    function cambiar(campo, valor) {
        if (!campo || campo.matches(':disabled')) throw new Error('El campo está ausente o bloqueado; prepara la escena o abre la consulta correcta.');
        const prototipo = campo instanceof HTMLSelectElement ? HTMLSelectElement.prototype : campo instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
        Object.getOwnPropertyDescriptor(prototipo, 'value').set.call(campo, valor);
        campo.dispatchEvent(new Event('input', { bubbles: true }));
        campo.dispatchEvent(new Event('change', { bubbles: true }));
    }

    async function escribir(campo, texto) {
        const turno = generacion;
        campo.focus();
        cambiar(campo, '');
        for (const letra of texto) {
            if (turno !== generacion) throw new Error('Escenario reiniciado.');
            cambiar(campo, campo.value + letra);
            await new Promise(resolver => setTimeout(resolver, 110 + Math.floor(Math.random() * 41)));
        }
    }

    // Pausas para que el público alcance a ver cada paso (pedido del dueño, 2026-11-02).
    function pausa(ms) {
        return new Promise(resolver => setTimeout(resolver, ms));
    }

    function clic(boton) {
        if (!boton || boton.disabled || (boton.tagName === 'BUTTON' && boton.type === 'submit')) throw new Error('No se encontró un control seguro para este paso.');
        boton.click();
    }

    async function marcar(raiz, lista) {
        for (const [texto, codigo] of lista) {
            if (raiz.querySelector(`[data-sintoma="${codigo}"]`)) continue;
            await escribir(raiz.querySelector('[data-sintomas-buscar]'), texto);
            const opcion = await esperar(() => [...raiz.querySelectorAll('[data-sintomas-sugerencias] [data-codigo]')].find(n => n.dataset.codigo === codigo && visible(n)), `la sugerencia ${texto}`);
            const diagnosticos = raiz.closest('form')?.querySelector('[data-diagnosticos-lista]');
            const anterior = diagnosticos?.firstElementChild;
            await pausa(600);
            clic(opcion);
            await esperar(() => raiz.querySelector(`[data-sintoma="${codigo}"]`), `el síntoma seleccionado ${texto}`);
            // La lista anterior puede seguir visible durante el debounce; esperar la respuesta de esta selección.
            if (diagnosticos) await esperar(() => !diagnosticos.hidden && diagnosticos.firstElementChild && diagnosticos.firstElementChild !== anterior, `el recálculo tras ${texto}`);
            await pausa(1000);
        }
    }

    async function prepararConsulta(form, peso, motivo) {
        await esperar(() => form.querySelector('[data-consulta-campo="peso"]')?.matches(':enabled'), 'la consulta habilitada');
        const campoPeso = form.querySelector('[data-consulta-campo="peso"]');
        if (!campoPeso.value) await escribir(campoPeso, peso);
        const campoMotivo = form.querySelector('[data-consulta-campo="motivo"]');
        if (!campoMotivo.value) await escribir(campoMotivo, motivo);
    }

    async function agenda() {
        const calendario = await esperar(() => typeof calendarInstance !== 'undefined' && calendarInstance?.getEvents()?.length && calendarInstance, 'la Agenda real cargada');
        // Hoy vuelve a pintar la lista con los eventos que ya terminó de cargar el calendario.
        clic(document.querySelector('[data-nav="today"]'));
        const hoy = dia(new Date());
        const citas = calendario.getEvents().filter(e => e.extendedProps.tipo === 'cita' && dia(e.start) === hoy);
        if (escenario === 'agenda_mishi') {
            if (!normalizar(document.querySelector('.cabecera-clinica')?.textContent).includes('san roque')) throw new Error('Este paso solo inicia citas de la demo de San Roque.');
            const rocco = citas.find(e => normalizar(e.extendedProps.mascota_nombre || e.title) === 'rocco' && e.extendedProps.estado === 'completada');
            const mishi = citas.find(e => normalizar(e.extendedProps.mascota_nombre || e.title) === 'mishi' && String(e.extendedProps.id_veterinario) === String(rocco?.extendedProps.id_veterinario));
            if (!rocco || !mishi || !['pendiente', 'confirmada'].includes(mishi.extendedProps.estado) || !mishi.extendedProps.hora_llegada) throw new Error('La escena debe tener Rocco completado y Mishi en sala, sin iniciar.');
            detalle.replaceChildren();
            const lista = crear('ol');
            for (const cita of citas.filter(e => String(e.extendedProps.id_veterinario) === String(mishi.extendedProps.id_veterinario) && ['pendiente', 'confirmada'].includes(e.extendedProps.estado)).sort((a, b) => a.start - b.start)) {
                lista.append(crear('li', `${cita.extendedProps.mascota_nombre || cita.title}: ${cita.extendedProps.inicio_estimado || 'sin estimación publicada'}`));
            }
            detalle.append(lista);
            clic(document.querySelector('[data-agenda-vista="citas"]'));
            const tarjeta = await esperar(() => document.querySelector(`.agenda-item[data-id="${CSS.escape(String(mishi.id))}"]`), 'la cita de Mishi en el día');
            resaltar([tarjeta]);
            informar('Rocco completado. Estas son las nuevas estimaciones de Zooki; iniciando Mishi desde su cita.');
            await pausa(1500);
            clic(tarjeta);
            const id = String(mishi.extendedProps.id_cita || String(mishi.id).replace(/^cita[_-]/, ''));
            const iniciar = await esperar(() => document.querySelector(`[data-accion="iniciar"][data-id="${CSS.escape(id)}"]`), 'el botón real Iniciar atención de Mishi');
            clic(iniciar);
            return;
        }
        const actual = citas.find(e => normalizar(e.extendedProps.mascota_nombre || e.title) === 'rocco' && e.extendedProps.estado === 'en_curso');
        if (!actual) throw new Error('No está Rocco en curso hoy: ejecuta la nueva --escena con la veterinaria principal.');
        const posteriores = citas.filter(e => String(e.extendedProps.id_veterinario) === String(actual.extendedProps.id_veterinario) && ['pendiente', 'confirmada'].includes(e.extendedProps.estado)).sort((a, b) => a.start - b.start).slice(0, 3);
        detalle.replaceChildren();
        const lista = crear('ol');
        const p = actual.extendedProps;
        lista.append(crear('li', `Rocco · En curso · inicio ${p.inicio_estimado || 'sin estimación publicada'}`));
        for (const cita of posteriores) {
            const datos = cita.extendedProps;
            lista.append(crear('li', `${datos.mascota_nombre || cita.title}: ${cita.start.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })} → ${datos.inicio_estimado || 'sin estimación publicada'}`));
        }
        detalle.append(lista);
        const id = actual.extendedProps.id_cita || String(actual.id).replace(/^cita[_-]/, '');
        if (!/^\d+$/.test(String(id))) throw new Error('La Agenda no expone el identificador real de Rocco.');
        const enlace = crear('a', 'Abrir Atención de Rocco');
        enlace.href = `index.php?action=vet_atencion&id_cita=${encodeURIComponent(id)}`;
        detalle.append(enlace);
        resaltar([actual, ...posteriores].map(e => document.querySelector(`.agenda-item[data-id="${CSS.escape(String(e.id))}"]`)));
        informar('Datos de la Agenda de Zooki. Este paso no inicia, alarga ni cierra citas.');
    }

    async function rocco() {
        const form = document.querySelector('#formConsultaAtencion');
        if (!/\brocco\b/i.test(document.querySelector('#atencionApp')?.textContent || '')) throw new Error('Abre la Atención de Rocco, no la de otro paciente.');
        if (paso === 0) {
            await prepararConsulta(form, '28', 'Demo: Rocco');
            await marcar(form.querySelector('[data-sintomas]'), sintomasRocco);
            const candidato = await esperar(() => document.querySelector('[data-usar-diagnostico="diagnostico.sarna_sarcoptica"]'), 'sarna sarcóptica calculada por Zooki');
            resaltar([candidato.closest('article') || candidato]);
            informar('Zooki calculó los diagnósticos. No se guardó la consulta.');
        } else if (paso === 1) {
            const sarna = await esperar(() => document.querySelector('[data-usar-diagnostico="diagnostico.sarna_sarcoptica"]'), 'el diagnóstico de sarna');
            resaltar([sarna.closest('article') || sarna]);
            await pausa(1200);
            clic(sarna);
            await esperar(() => form.querySelector('[data-tratamiento-campo="farmaco"][value="farmaco.selamectina"]') || [...form.querySelectorAll('[data-tratamiento-campo="farmaco"]')].find(n => n.value === 'farmaco.selamectina'), 'la selamectina precargada');
            const descarte = await esperar(() => {
                const fila = form.querySelector('[data-tratamientos] .consulta-descartada[data-prescripcion-descartada="farmaco.ivermectina"]');
                return visible(fila) && fila.querySelector('a[href]') ? fila : null;
            }, 'la fila de ivermectina descartada por MDR1 con su fuente');
            resaltar([descarte]);
            informar('Selamectina y descarte de ivermectina provienen de Zooki. Consulta sin guardar.');
        } else {
            if (!normalizar(document.querySelector('.cabecera-clinica')?.textContent).includes('san roque')) throw new Error('Solo se permite cerrar Rocco en la demo de San Roque.');
            await prepararConsulta(form, '28', 'Demo: Rocco');
            const diagnostico = form.querySelector('[data-consulta-campo="diagnostico"]');
            const selamectina = [...form.querySelectorAll('[data-tratamiento-campo="farmaco"]')].some(n => n.value === 'farmaco.selamectina');
            if (!normalizar(diagnostico.value).includes('sarna sarcoptica') || !selamectina) throw new Error('Completa el paso 2: sarna sarcóptica y selamectina deben estar elegidas.');
            clic(document.querySelector('#atencionApp [data-consulta-ir="2"]'));
            await esperar(() => form.querySelector('[data-consulta-tres]').dataset.pasoActual === '2', 'el paso Cierre con los campos válidos');
            const control = form.querySelector('[data-control-activo]');
            if (control.checked) {
                control.checked = false;
                control.dispatchEvent(new Event('change', { bubbles: true }));
            }
            // Decisión del dueño, 2026-11-03: Rocco es la única consulta que el guion guarda.
            if (document.querySelector('#atencionApp').dataset.consultaRegistrada !== '1') clic(document.querySelector('#btnFinalizarAtencion'));
            await esperar(() => document.querySelector('#atencionApp')?.dataset.consultaRegistrada === '1', 'la consulta de Rocco guardada por Zooki');
            informar('Atención registrada. Zooki vuelve automáticamente a Agenda.');
        }
    }

    async function mishi() {
        const form = document.querySelector('#formConsultaAtencion');
        if (normalizar(document.querySelector('[data-nombre-mascota]')?.dataset.nombreMascota) !== 'mishi') throw new Error('Abre la Atención de Mishi desde su cita.');
        if (paso === 0) {
            await prepararConsulta(form, '4.2', 'Demo: Mishi');
            await marcar(form.querySelector('[data-sintomas]'), sintomasMishi);
            const candidato = await esperar(() => form.querySelector('[data-usar-diagnostico]'), 'diagnósticos del Grafo I para Mishi');
            resaltar([candidato]);
            informar('Diagnósticos calculados por Zooki para Mishi. Consulta sin guardar.');
        } else {
            clic(document.querySelector('#atencionApp [data-consulta-ir="1"]'));
            await pausa(900);
            const antes = form.querySelectorAll('[data-tratamiento]').length;
            let fila = [...form.querySelectorAll('[data-tratamiento]')].find(n => n.querySelector('[data-tratamiento-campo="farmaco"]')?.value === 'farmaco.paracetamol');
            if (!fila) {
                clic(form.querySelector('[data-agregar-tratamiento]'));
                fila = await esperar(() => form.querySelectorAll('[data-tratamiento]').length > antes && [...form.querySelectorAll('[data-tratamiento]')].at(-1), 'la fila de medicamento');
                await escribir(fila.querySelector('[data-tratamiento-campo="medicamento"]'), 'paracetamol');
                await pausa(700);
                clic(await esperar(() => fila.querySelector('[data-farmaco="farmaco.paracetamol"]'), 'paracetamol en el catálogo'));
            }
            await esperar(() => fila.classList.contains('consulta-tratamiento--bloqueo') && fila.querySelector('.consulta-tratamiento__fuente'), 'el bloqueo por toxicidad felina con fuente');
            resaltar([fila.querySelector('[data-prescripcion-lista]')]);
            informar('El bloqueo y su fuente son del motor de Zooki. No se confirma ni guarda la prescripción.');
        }
    }

    async function portal() {
        const clave = `zkdemo-simon-${dia(new Date())}`;
        if (sessionStorage.getItem(clave)) throw new Error('Simón ya fue evaluado en esta pestaña. No crearé otra solicitud: rehace --escena y usa una pestaña nueva para repetir.');
        let modal = document.querySelector('#portalBookingModal');
        if (!visible(modal)) clic(document.querySelector('[data-agendar]'));
        const form = await esperar(() => visible(modal) && document.querySelector('#portalBookingForm'), 'el formulario del Portal');
        const clinica = [...form.querySelectorAll('[name="id_clinica"]')].find(n => normalizar(n.dataset.nombre).includes('san roque'));
        const mascota = [...form.querySelectorAll('[name="id_mascota"]')].find(n => normalizar(n.dataset.nombre) === 'simon');
        if (!clinica || !mascota) throw new Error('La cuenta del Portal debe ser demo-portal, vinculada a San Roque y propietaria de Simón.');
        clic(clinica);
        clic(mascota);
        const tipo = form.querySelector('#booking_tipo_cita');
        const vet = form.querySelector('#booking_veterinario');
        const opcionTipo = await esperar(() => [...tipo.options].find(o => normalizar(o.text).includes('consulta general')), 'los tipos reales de San Roque');
        cambiar(tipo, opcionTipo.value);
        const opcionVet = await esperar(() => [...vet.options].find(o => normalizar(o.text).includes('valentina rojas')), 'la veterinaria principal');
        cambiar(vet, opcionVet.value);
        const fecha = form.querySelector('#booking_fecha');
        if (!fecha._flatpickr || new Date().getDay() === 0) throw new Error('La demo del Portal requiere el calendario de Zooki y un día de atención (lunes a sábado).');
        fecha._flatpickr.setDate(dia(new Date()), true);
        const triage = form.querySelector('[data-triage-agenda]');
        await marcar(triage.querySelector('[data-triage-grupo="sintomas"]'), [['va a la arena y no hace', 'sintoma.intentos_orinar_minimo'], ['dolor al orinar', 'sintoma.dolor_orinar']]);
        for (const fila of triage.querySelectorAll('.triage-captura__tiempo')) {
            cambiar(fila.querySelector('select'), 'conocido');
            await escribir(fila.querySelector('input'), '2');
            cambiar(fila.querySelector('.triage-captura__unidad'), 'horas');
        }
        clic(triage.querySelector('[data-triage-calcular]'));
        // Se marca antes de esperar para que una respuesta perdida no cree un duplicado al reintentar.
        sessionStorage.setItem(clave, 'evaluacion-solicitada');
        const resultado = await esperar(() => {
            const nodo = triage.querySelector('[data-triage-resultado]');
            return visible(nodo) && /rojo|critico|crítico/i.test(nodo.textContent) && /emergencia/i.test(nodo.textContent) && nodo;
        }, 'el resultado rojo y el aviso de emergencia de Zooki');
        resaltar([resultado]);
        informar('Evaluación real de Simón. Zooki creó la derivación; no se pulsó Agendar ni se reservó una cita.');
        // En celular el panel no debe tapar el aviso real de emergencia que acabamos de abrir.
        minimizar.click();
    }

    async function avanzar() {
        if (ocupado || paso >= (nombres[escenario]?.length || 0)) return;
        ocupado = true;
        const turno = generacion;
        informar('Ejecutando este paso en Zooki…');
        pintar();
        try {
            await ({ agenda, agenda_mishi: agenda, rocco, mishi, portal }[escenario])();
            if (turno === generacion) paso++;
        } catch (error) {
            if (turno === generacion) informar(error.message || 'No se pudo completar este paso.', true);
        } finally {
            if (turno === generacion) ocupado = false;
            pintar();
        }
    }

    function resetear() {
        generacion++;
        ocupado = false;
        paso = 0;
        detalle.replaceChildren();
        limpiarMarcas();
        panel.hidden = false;
        punto.hidden = true;
        informar('Guion reiniciado. No se borran datos ni se deshacen acciones de la app.');
        pintar();
    }
    siguiente.addEventListener('click', avanzar);
    reiniciar.addEventListener('click', resetear);
    document.addEventListener('pointerdown', evento => {
        if (evento.isTrusted && evento.target.matches('input, textarea, select, [contenteditable="true"]')) {
            campoManual = evento.target;
            clicManual = Date.now();
        }
    }, true);
    document.addEventListener('keydown', evento => {
        if (!evento.shiftKey || evento.ctrlKey || evento.altKey || evento.metaKey || !['s', 'q'].includes(evento.key.toLowerCase())) return;
        if (evento.target === campoManual && Date.now() - clicManual < 3500) return;
        evento.preventDefault();
        evento.stopImmediatePropagation();
        if (evento.repeat) return;
        if (evento.key.toLowerCase() === 'q') resetear();
        else avanzar();
    }, true);
    setInterval(() => {
        const actual = detectar();
        if (actual === escenario) return;
        escenario = actual;
        resetear();
    }, 400);
    escenario = detectar();
    informar('Una tecla, un paso. Usa únicamente las cuentas y pacientes de demo.');
    pintar();
})();
