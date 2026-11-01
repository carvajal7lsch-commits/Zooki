/** RE-2.1.6/7, RE-2.8.1/6: presentación V3; reutiliza campos, eventos y validación existentes. */
(function () {
    'use strict';
    function iniciar() {
        const app = document.getElementById('atencionApp');
        if (!app) return;
        const escritorio = window.matchMedia('(min-width: 1024px)');
        const movimientos = [];
        const columnas = [];
        const editores = new Map();
        const ordenTratamientos = new WeakMap();
        let siguienteTratamiento = 0;
        const barra = app.querySelector('.atencion__barra');
        const paciente = app.querySelector('.atencion-franja');
        const volver = app.querySelector('.atencion__volver');
        const estado = app.querySelector('#atencionEstado');
        const fichaPaciente = app.querySelector('[data-ficha-paciente]');
        const alertasPaciente = app.querySelector('.atencion-franja__alertas');
        const abrirFichaPaciente = () => {
            if (!fichaPaciente.open) fichaPaciente.showModal();
        };
        for (const boton of app.querySelectorAll('[data-abrir-ficha-paciente]')) boton.addEventListener('click', abrirFichaPaciente);
        fichaPaciente.querySelector('[data-cerrar-ficha-paciente]').addEventListener('click', () => fichaPaciente.close());
        fichaPaciente.addEventListener('click', evento => {
            if (evento.target !== fichaPaciente) return;
            const caja = fichaPaciente.getBoundingClientRect();
            if (evento.clientX < caja.left || evento.clientX > caja.right || evento.clientY < caja.top || evento.clientY > caja.bottom) fichaPaciente.close();
        });
        // El formulario de alertas existente está fuera del diálogo nativo y debe poder recibir el foco.
        fichaPaciente.addEventListener('click', evento => {
            if (evento.target.closest('[data-registrar-alerta]')) fichaPaciente.close();
        }, true);
        fichaPaciente.querySelector('[data-ficha-ver-historial]').addEventListener('click', () => {
            fichaPaciente.close();
            app.querySelector('[data-tab=historial]').click();
        });
        app.addEventListener('zooki:ficha-actualizada', evento => {
            if (String(evento.detail.idMascota) !== app.dataset.idMascota) return;
            const datos = evento.detail.datos;
            const dato = nombre => fichaPaciente.querySelector(`[data-ficha-paciente-dato="${nombre}"]`);
            if (datos.sexo) texto(dato('sexo'), datos.sexo);
            if (datos.fecha_nacimiento && typeof window.calculateAge === 'function') texto(dato('edad'), window.calculateAge(datos.fecha_nacimiento));
            const identidad = ['sexo', 'edad'].map(nombre => dato(nombre).textContent).filter(valor => valor !== 'Sin registrar').join(' y ') || 'Sexo y edad sin registrar';
            texto(paciente.querySelector('.atencion-franja__resumen'), [dato('especie').textContent, identidad, dato('peso').textContent].join(' · '));
        });
        const herramientas = app.querySelector('.atencion__herramientas');
        const pie = app.querySelector('#accionesAtencion');
        const ficha = app.querySelector('[data-ficha-mini]');
        const listaTratamientos = app.querySelector('[data-tratamientos-lista]');
        const sintomas = app.querySelector('[data-sintomas]');
        const frecuentes = nodo('div', 'atencion-sintomas-frecuentes');
        frecuentes.append(nodo('span', 'consulta-campo__etiqueta', 'Frecuentes'));
        const opcionesFrecuentes = nodo('div', 'atencion-sintomas-frecuentes__opciones');
        for (const [codigo, etiqueta] of [['vomito', 'Vómito'], ['diarrea', 'Diarrea'], ['tos', 'Tos'], ['no_come', 'No come'], ['decaimiento', 'Decaimiento'], ['fiebre', 'Fiebre']]) {
            const opcion = nodo('button', 'atencion-sintoma-frecuente');
            opcion.append(nodo('span', 'atencion-sintoma-frecuente__signo', '+'), nodo('span', '', etiqueta));
            opcion.type = 'button';
            opcion.dataset.sintomaFrecuente = 'sintoma.' + codigo;
            opcion.dataset.etiqueta = etiqueta;
            opcionesFrecuentes.append(opcion);
        }
        frecuentes.append(opcionesFrecuentes);
        sintomas?.append(frecuentes);
        app.addEventListener('click', evento => {
            const tarjeta = evento.target.closest('.consulta-vital');
            if (!tarjeta || evento.target.closest('input, label, button, a, select, textarea')) return;
            tarjeta.querySelector('input:not([type=checkbox]):not(:disabled)')?.focus();
        });
        const apoyo = app.querySelector('[data-diagnosticos]');
        const avisoContexto = app.querySelector('[data-contexto-aviso]');
        const titulo = app.querySelector('.atencion__titulo');
        envolverTexto(paciente.querySelector('.atencion-franja__identidad strong'), 'atencion-nombre-paciente');
        envolverTexto(paciente.querySelector('.atencion-franja__datos dl > div:last-child dd'), 'atencion-propietario-nombre');
        let activo = false;
        let programado = false;

        function nodo(tipo, clase, texto) {
            const elemento = document.createElement(tipo);
            elemento.className = clase;
            if (texto) elemento.textContent = texto;
            return elemento;
        }
        function envolverTexto(elemento, clase) {
            const original = elemento?.firstChild;
            if (!original || original.nodeType !== Node.TEXT_NODE) return;
            const envoltura = nodo('span', clase, original.textContent);
            envoltura.title = original.textContent;
            original.replaceWith(envoltura);
        }
        function boton(texto, accion) {
            const elemento = nodo('button', 'consulta-boton consulta-boton--suave', texto);
            elemento.type = 'button';
            elemento.addEventListener('click', accion);
            return elemento;
        }
        function recuadro(titulo, clase) {
            const dialogo = nodo('dialog', clase);
            const cabecera = nodo('div', 'atencion-flotante__cabecera');
            cabecera.append(nodo('strong', '', titulo), boton('Cerrar', () => dialogo.close()));
            dialogo.append(cabecera);
            return dialogo;
        }
        function texto(elemento, valor) {
            if (elemento.textContent !== valor) elemento.textContent = valor;
        }
        function ocultar(elemento, valor) {
            if (elemento.hidden !== valor) elemento.hidden = valor;
        }
        function mover(elemento, destino, primero = false) {
            if (!elemento) return;
            const marca = document.createComment('Posición original de la interfaz móvil');
            elemento.before(marca);
            movimientos.push({ elemento, marca });
            if (primero) destino.prepend(elemento);
            else destino.append(elemento);
        }
        function columna(paso, elementos, crece) {
            const envoltura = nodo('div', 'atencion-columna');
            paso.append(envoltura);
            columnas.push(envoltura);
            for (const elemento of elementos.filter(Boolean)) mover(elemento, envoltura);
            if (crece) crece.classList.add('atencion-campo-crece');
        }
        const resumenFicha = nodo('div', 'atencion-ficha-resumen');
        const faltantes = nodo('span', 'atencion-ficha-resumen__texto');
        const completar = boton('Completar', () => {
            ficha.showPopover();
            ficha.querySelector('input:not(:disabled), select:not(:disabled)')?.focus();
        });
        completar.dataset.completarFicha = '';
        resumenFicha.append(faltantes, completar);
        apoyo?.querySelector('.consulta-sugerencias__cabecera').after(resumenFicha);
        const cabeceraFicha = nodo('div', 'atencion-flotante__cabecera');
        cabeceraFicha.append(nodo('strong', '', 'Completar la ficha'), boton('Cerrar', () => ficha.hidePopover()));
        ficha?.prepend(cabeceraFicha);
        const contexto = nodo('span', 'atencion-contexto');
        contexto.textContent = [titulo.querySelector('h1').childNodes[0].textContent.trim(), titulo.querySelector('p').textContent.trim().replace(/\s+/g, ' ')].join(' · ');
        contexto.title = contexto.textContent;
        herramientas.insertBefore(contexto, app.querySelector('[data-pasos-paciente]'));
        const estadoPie = nodo('div', 'atencion-pie-estado');
        const masTratamientos = nodo('div', 'atencion-tratamientos-mas');
        masTratamientos.popover = 'manual';
        const contenidoMas = nodo('div', 'atencion-tratamientos-mas__lista');
        let paginaMas = 0;
        const navegacionMas = nodo('div', 'atencion-lista-dialogo__navegacion');
        const anteriorMas = boton('Anterior', () => {
            paginaMas--;
            paginarTratamientos();
        });
        const siguienteMas = boton('Siguiente', () => {
            paginaMas++;
            paginarTratamientos();
        });
        navegacionMas.append(anteriorMas, siguienteMas);
        const abrirMas = boton('', () => masTratamientos.showPopover());
        abrirMas.dataset.verTratamientos = '';
        masTratamientos.append(boton('Cerrar', () => masTratamientos.hidePopover()), contenidoMas, navegacionMas);
        listaTratamientos?.after(abrirMas);
        listaTratamientos?.append(masTratamientos);

        function paginarTratamientos() {
            const filas = [...contenidoMas.children];
            const editando = filas.findIndex(fila => fila.dataset.editando === '1');
            if (editando >= 0) paginaMas = Math.floor(editando / 5);
            paginaMas = Math.max(0, Math.min(paginaMas, Math.ceil(filas.length / 5) - 1));
            filas.forEach((fila, indice) => ocultar(fila, Math.floor(indice / 5) !== paginaMas));
            anteriorMas.disabled = paginaMas === 0;
            siguienteMas.disabled = (paginaMas + 1) * 5 >= filas.length;
            ocultar(navegacionMas, filas.length <= 5);
        }

        function prepararEditor(fila) {
            if (editores.has(fila)) return editores.get(fila);
            const campos = fila.querySelector('.consulta-tratamiento__campos');
            const prescripcion = fila.querySelector('[data-prescripcion]');
            const dialogo = recuadro('Medicamento', 'atencion-editor');
            const marcaCampos = document.createComment('Campos del tratamiento');
            const marcaPrescripcion = document.createComment('Seguridad de la prescripción');
            campos.before(marcaCampos);
            prescripcion.before(marcaPrescripcion);
            fila.append(dialogo);
            const cerrar = () => {
                fila.dataset.editando = '0';
                campos.hidden = true;
                fila.querySelector('[data-editar-tratamiento]').hidden = false;
                if (dialogo.dataset.abrioLista === '1') {
                    if (masTratamientos.matches(':popover-open')) masTratamientos.hidePopover();
                    delete dialogo.dataset.abrioLista;
                }
            };
            dialogo.addEventListener('close', cerrar);
            dialogo.addEventListener('cancel', evento => {
                evento.preventDefault();
                dialogo.close();
            });
            const registro = { dialogo, campos, prescripcion, marcaCampos, marcaPrescripcion };
            editores.set(fila, registro);
            return registro;
        }
        function tratamientos() {
            if (!listaTratamientos) return;
            const filas = [...listaTratamientos.querySelectorAll('[data-tratamiento]')];
            for (const fila of filas) {
                if (!ordenTratamientos.has(fila)) ordenTratamientos.set(fila, siguienteTratamiento++);
            }
            filas.sort((a, b) => ordenTratamientos.get(a) - ordenTratamientos.get(b));
            for (const [fila, editor] of editores) {
                if (fila.isConnected) continue;
                if (editor.dialogo.open) editor.dialogo.close();
                editores.delete(fila);
            }
            const exceso = filas.length > 5;
            if (!exceso && masTratamientos.matches(':popover-open')) masTratamientos.hidePopover();
            ocultar(abrirMas, !exceso);
            texto(abrirMas, 'Ver ' + Math.max(0, filas.length - 4) + ' más');
            for (const [indice, fila] of filas.entries()) {
                const destino = exceso && indice >= 4 ? contenidoMas : listaTratamientos;
                // Conserva el orden que usan la validación, el borrador y los errores por índice.
                const posicion = destino.children[destino === listaTratamientos ? indice : indice - 4];
                if (posicion !== fila) destino.insertBefore(fila, posicion || null);
                const editor = prepararEditor(fila);
                if (editor.campos.parentElement !== editor.dialogo) editor.dialogo.append(editor.campos, editor.prescripcion);
                const editando = fila.dataset.editando === '1';
                if (editando) ocultar(fila, false);
                if (editando && !editor.dialogo.open) {
                    // Un diálogo dentro de un popover cerrado tiene open pero carece de caja visible.
                    if (fila.closest('.atencion-tratamientos-mas') && !masTratamientos.matches(':popover-open')) {
                        masTratamientos.showPopover();
                        editor.dialogo.dataset.abrioLista = '1';
                    }
                    editor.dialogo.showModal();
                    const primero = editor.dialogo.querySelector('[aria-invalid="true"]:not([type="hidden"])') || editor.campos.querySelector('[data-tratamiento-campo="medicamento"]');
                    primero?.focus();
                }
                if (!editando && editor.dialogo.open) editor.dialogo.close();
                for (const etiqueta of fila.querySelectorAll('.tratamiento-tarjeta strong, .tratamiento-tarjeta__metadatos')) etiqueta.title = etiqueta.textContent;
                let aviso = fila.querySelector('.atencion-tratamiento-aviso');
                if (!aviso) {
                    aviso = boton('', () => fila._tarjeta.editar());
                    aviso.classList.add('atencion-tratamiento-aviso');
                    fila.querySelector('.tratamiento-tarjeta').append(aviso);
                }
                const bloqueada = fila.classList.contains('consulta-tratamiento--bloqueo');
                const advertencia = fila.classList.contains('consulta-tratamiento--advertencia');
                const incompleta = fila.classList.contains('consulta-tratamiento--incompleta');
                ocultar(aviso, !bloqueada && !advertencia && !incompleta);
                texto(aviso, bloqueada ? 'Prescripción bloqueada · Revisar' : advertencia ? 'Advertencia · Revisar' : 'Evaluación incompleta · Revisar');
                aviso.title = editor.prescripcion.textContent.trim();
            }
            if (masTratamientos.nextElementSibling) listaTratamientos.append(masTratamientos);
            paginarTratamientos();
        }
        function listaCompacta(lista) {
            if (!lista || lista.closest('[hidden]')) return;
            const filas = [...lista.children].filter(e => e.tagName === 'LI');
            if (!filas.length) return;
            let control = lista.nextElementSibling;
            if (!control?.matches('[data-lista-mas]')) {
                control = boton('Ver más', () => {
                    const filas = [...lista.children].filter(e => e.tagName === 'LI');
                    const dialogo = recuadro('Registros', 'atencion-lista-dialogo');
                    const contenido = nodo('ul', 'atencion-lista-dialogo__contenido');
                    const navegacion = nodo('div', 'atencion-lista-dialogo__navegacion');
                    let pagina = 0;
                    const referencias = filas.map(fila => {
                        const marca = document.createComment('Registro en su lista original');
                        fila.before(marca);
                        return { fila, marca };
                    });
                    function pintar() {
                        for (const [indice, fila] of filas.entries()) fila.hidden = Math.floor(indice / 5) !== pagina;
                        anterior.disabled = pagina === 0;
                        siguiente.disabled = (pagina + 1) * 5 >= filas.length;
                    }
                    const anterior = boton('Anterior', () => {
                        pagina--;
                        pintar();
                    });
                    const siguiente = boton('Siguiente', () => {
                        pagina++;
                        pintar();
                    });
                    navegacion.append(anterior, siguiente);
                    contenido.append(...filas);
                    dialogo.append(contenido, navegacion);
                    app.append(dialogo);
                    dialogo.addEventListener('close', () => {
                        for (const { fila, marca } of referencias) {
                            marca.replaceWith(fila);
                            fila.hidden = false;
                        }
                        dialogo.remove();
                        programar();
                    });
                    pintar();
                    dialogo.showModal();
                });
                control.dataset.listaMas = '';
                lista.after(control);
            }
            const disponibles = Math.max(1, Math.floor((lista.getBoundingClientRect().height - 38) / 58));
            filas.forEach((fila, indice) => ocultar(fila, indice >= disponibles));
            ocultar(control, filas.length <= disponibles);
        }
        function actualizar() {
            programado = false;
            if (!activo) return;
            const alertas = [...alertasPaciente.querySelectorAll('.consulta-chip--alerta')];
            const resumenAlertas = app.querySelector('[data-ficha-alertas-resumen]');
            const errorAlertas = alertasPaciente.querySelector('.alertas-medicas__vacio--error');
            texto(resumenAlertas, errorAlertas ? 'Alertas sin cargar' : alertas.length === 1 ? '1 alerta: ' + alertas[0].textContent.split(':')[0] : alertas.length ? alertas.length + ' alertas' : alertasPaciente.querySelector('.consulta-chip') ? 'Sin alertas' : 'Cargando alertas…');
            resumenAlertas.parentElement.classList.toggle('sin-alertas', !errorAlertas && alertas.length === 0);
            const etiquetaAlertas = alertasPaciente.querySelector('.atencion-franja__etiqueta');
            if (etiquetaAlertas) texto(etiquetaAlertas, 'Alergias y alertas');
            const nombres = [...(ficha?.querySelectorAll('[data-ficha-fila]') || [])].filter(e => !e.hidden).map(e => ({ sexo: 'sexo', esterilizado: 'esterilización', fecha_nacimiento: 'nacimiento' })[e.dataset.fichaFila]);
            ocultar(resumenFicha, !nombres.length || ficha.hidden);
            texto(faltantes, 'Faltan ' + nombres.join(', ') + ' en la ficha');
            faltantes.title = faltantes.textContent;
            const consultaActiva = app.querySelector('[data-vista="consulta"]').classList.contains('is-active');
            if (avisoContexto) {
                const destinoAviso = apoyo.hidden ? app.querySelector('[data-panel-resumen]') : apoyo;
                const franjaAviso = destinoAviso.querySelector('.consulta-collage');
                if (franjaAviso?.nextElementSibling !== avisoContexto) franjaAviso?.after(avisoContexto);
            }
            const borrador = app.querySelector('#estadoBorrador');
            if (borrador) borrador.title = borrador.textContent;
            if (ficha && (ficha.hidden || apoyo.hidden || !consultaActiva) && ficha.matches(':popover-open')) ficha.hidePopover();
            if (!consultaActiva && masTratamientos.matches(':popover-open')) masTratamientos.hidePopover();
            for (const input of app.querySelectorAll('[data-recomendacion]')) input.title = input.value;
            tratamientos();
            for (const lista of app.querySelectorAll('#atencionHistorial, .procedimiento .registros')) listaCompacta(lista);
        }
        function programar() {
            if (programado) return;
            programado = true;
            requestAnimationFrame(actualizar);
        }
        function activar() {
            if (activo === escritorio.matches) {
                programar();
                return;
            }
            activo = escritorio.matches;
            app.classList.toggle('atencion--escritorio', activo);
            if (activo) {
                mover(paciente, barra, true);
                mover(volver, paciente, true);
                mover(estado, paciente.querySelector('.atencion-franja__identidad strong'));
                mover(alertasPaciente, fichaPaciente.querySelector('[data-ficha-paciente-alertas]'));
                const pasos = app.querySelectorAll('[data-consulta-paso]');
                if (pasos.length) {
                    const campo = (paso, nombre) => paso.querySelector(`[data-consulta-campo="${nombre}"]`).closest('.consulta-campo');
                    pasos[0].classList.add('atencion-evaluacion');
                    for (const elemento of [pasos[0].querySelector('.consulta-vitales'), campo(pasos[0], 'motivo'), sintomas, campo(pasos[0], 'anamnesis')]) mover(elemento, pasos[0]);
                    mover(sintomas.querySelector('[data-sintomas-chips]'), frecuentes);
                    campo(pasos[0], 'anamnesis').classList.add('atencion-campo-crece');
                    columna(pasos[1], [campo(pasos[1], 'diagnostico'), pasos[1].querySelector('[data-plan-elegible]')]);
                    columna(pasos[1], [pasos[1].querySelector('[data-tratamientos]'), campo(pasos[1], 'otras_indicaciones')], campo(pasos[1], 'otras_indicaciones'));
                    columna(pasos[2], [pasos[2].querySelector('[data-soltar-adjuntos]'), campo(pasos[2], 'observaciones')], campo(pasos[2], 'observaciones'));
                    columna(pasos[2], [pasos[2].querySelector('[data-proximo-control]')]);
                    pie.prepend(estadoPie);
                    for (const elemento of [app.querySelector('[data-sugerencia-aviso]'), app.querySelector('[data-borrador-aviso]'), app.querySelector('#estadoBorrador')]) mover(elemento, estadoPie);
                }
                if (ficha) ficha.popover = 'manual';
                if (avisoContexto) mover(avisoContexto, apoyo);
            } else {
                if (fichaPaciente.open) fichaPaciente.close();
                const etiquetaAlertas = alertasPaciente.querySelector('.atencion-franja__etiqueta');
                if (etiquetaAlertas) texto(etiquetaAlertas, 'Alertas');
                for (const dialogo of app.querySelectorAll('.atencion-lista-dialogo[open]')) dialogo.close();
                ficha?.hidePopover();
                ficha?.removeAttribute('popover');
                masTratamientos.hidePopover();
                for (const { dialogo, campos, prescripcion, marcaCampos, marcaPrescripcion } of editores.values()) {
                    if (dialogo.open) dialogo.close();
                    marcaCampos.after(campos);
                    marcaPrescripcion.after(prescripcion);
                }
                if (listaTratamientos) {
                    const filas = [...listaTratamientos.querySelectorAll('[data-tratamiento]')];
                    for (const fila of filas) ocultar(fila, false);
                    listaTratamientos.prepend(...filas);
                }
                for (const { elemento, marca } of movimientos.reverse()) marca.replaceWith(elemento);
                movimientos.length = 0;
                for (const envoltura of columnas) envoltura.remove();
                columnas.length = 0;
                for (const elemento of app.querySelectorAll('.atencion-campo-crece')) elemento.classList.remove('atencion-campo-crece');
                for (const elemento of app.querySelectorAll('[data-lista-mas]')) elemento.remove();
                for (const elemento of app.querySelectorAll('.registros > li, #atencionHistorial > li')) elemento.hidden = false;
                estadoPie.remove();
            }
            programar();
        }
        const observador = new MutationObserver(programar);
        app.addEventListener('click', evento => {
            const resumen = evento.target.closest('.historia-fila__resumen');
            if (!activo || !resumen || resumen.closest('.atencion-lista-dialogo')) return;
            const detalle = resumen.parentElement.querySelector('.historia-fila__detalle');
            if (!detalle) return;
            evento.preventDefault();
            const marca = document.createComment('Detalle original del registro');
            detalle.before(marca);
            const dialogo = recuadro('Detalle del registro', 'atencion-lista-dialogo');
            dialogo.append(detalle);
            app.append(dialogo);
            dialogo.addEventListener('close', () => {
                marca.replaceWith(detalle);
                dialogo.remove();
                programar();
            });
            dialogo.showModal();
        });
        observador.observe(app, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'data-editando'] });
        window.addEventListener('resize', activar);
        activar();
    }
    document.addEventListener('DOMContentLoaded', iniciar);
})();
