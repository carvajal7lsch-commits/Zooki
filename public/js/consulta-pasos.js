/** RE-2.1.6/7: navegación y borrador locales; no guarda decisiones clínicas en servidor. */
(function () {
    'use strict';
    const NOMBRES = ['Evaluación', 'Diagnóstico y tratamiento', 'Cierre'];
    const FECHA_ISO = /^\d{4}-\d{2}-\d{2}$/;
    const crear = (form, consulta, opciones = {}) => {
        const raiz = form.querySelector('[data-consulta-tres]');
        const app = form.closest('#atencionApp');
        if (app) form.reset();
        const area = app || form;
        const pasos = [...form.querySelectorAll('[data-consulta-paso]')];
        const navegacion = raiz.querySelector('[data-consulta-navegacion]');
        if (app) app.querySelector('[data-pasos-paciente]')?.append(navegacion);
        const botones = [...navegacion.querySelectorAll('[data-consulta-ir]')];
        const siguientes = [...area.querySelectorAll('[data-consulta-siguiente], #btnPasoSiguiente')];
        const anteriores = [...area.querySelectorAll('[data-consulta-anterior], #btnPasoAnterior')];
        const finalizar = area.querySelector('[data-consulta-finalizar], #btnFinalizarAtencion');
        const panel = form.querySelector('[data-diagnosticos]');
        const fichaMini = app ? panel.querySelector('[data-ficha-mini]') : null;
        const sitioFicha = fichaMini ? document.createComment('Ficha antes de sugerencias') : null;
        if (sitioFicha) fichaMini.before(sitioFicha);
        const sitioPanel = form.querySelector('[data-panel-escritorio]');
        const panelResumen = form.querySelector('[data-panel-resumen]');
        const sitioResumen = panelResumen.parentElement;
        const pie = area.querySelector('#accionesAtencion, .consulta-modal__pie');
        if (pie) form.querySelector('[data-hoja-pie]').append(pie);
        if (app && pie) pie.querySelector('.atencion__borrador').before(form.querySelector('[data-borrador-aviso]'));
        const avisoContexto = form.querySelector('[data-contexto-aviso]');
        const reintentar = form.querySelector('[data-contexto-reintentar]');
        let recuperacionIntentada = false;
        let capturaEditada = false;
        const peso = form.querySelector('[name="peso"]');
        const control = form.querySelector('[data-control-activo]');
        const fechaControl = form.querySelector('[data-control-fecha]');
        const avisoControl = form.querySelector('[data-control-aviso]');
        const avisoBorrador = form.querySelector('[data-borrador-aviso]');
        let paso = 0;
        let contexto = null;
        let clave = null;
        let temporizador = null;
        let restaurando = false;
        let contextoPeticion = 0;
        let calendarioPeticion = 0;
        let fechaManual = false;
        let calendario = null;
        let diasAbiertos = new Set();
        const mesesAbiertos = new Map();
        let finalizada = false;
        const idMascota = () => String(form.querySelector('[name="id_mascota"]')?.value || opciones.idMascota?.() || '');

        function moverPanel() {
            const escritorio = window.matchMedia('(min-width: 1024px)').matches;
            if (app && paso < 2) {
                const siguiente = window.matchMedia('(max-width: 767px)').matches && paso === 0 ? 'Diagnóstico' : NOMBRES[paso + 1];
                for (const boton of siguientes) boton.textContent = 'Siguiente: ' + siguiente;
            }
            if (escritorio) sitioPanel.append(panel);
            else pasos[Math.min(paso, 1)].querySelector('[data-panel-movil]')?.append(panel);
            if (fichaMini) {
                if (escritorio) sitioFicha.after(fichaMini);
                else panel.before(fichaMini);
                fichaMini.classList.toggle('consulta-ficha-mini--cierre', paso === 2);
            }
            panel.hidden = paso === 2;
            if (escritorio) sitioPanel.append(panelResumen);
            else sitioResumen.append(panelResumen);
            panelResumen.hidden = paso !== 2;
        }

        function validarPaso(indice) {
            const invalido = window.ZookiConsultaForm.validarTodo(pasos[indice]);
            const fila = indice === 1 ? consulta.tratamientos?.validar() : null;
            const primero = invalido || fila;
            if (primero) {
                mostrar(indice);
                primero.focus();
                return false;
            }
            if (indice === 2 && control.checked && (!FECHA_ISO.test(fechaControl.value) || (contexto?.tiene_horario !== false && fechaControl.dataset.valida !== '1'))) {
                avisoControl.textContent = 'Elige un día con horario de atención para este veterinario.';
                fechaControl.focus();
                return false;
            }
            return true;
        }

        function mostrar(indice) {
            const cambio = paso !== indice;
            paso = Math.max(0, Math.min(2, indice));
            pasos.forEach((p, i) => {
                p.hidden = i !== paso;
                p.classList.toggle('is-active', i === paso);
            });
            botones.forEach((b, i) => {
                b.classList.toggle('is-active', i === paso);
                b.classList.toggle('is-hecho', i < paso);
                b.querySelector('b').textContent = i < paso ? '✓' : String(i + 1);
                b.setAttribute('aria-current', i === paso ? 'step' : 'false');
            });
            for (const b of anteriores) b.disabled = paso === 0;
            for (const b of siguientes) {
                b.hidden = paso === 2;
                b.textContent = 'Siguiente: ' + NOMBRES[paso + 1];
            }
            if (finalizar) finalizar.hidden = paso !== 2;
            raiz.dataset.pasoActual = String(paso);
            moverPanel();
            if (cambio) {
                form.querySelector('.consulta-tres__contenido').scrollTop = 0;
                form.querySelector('.consulta-modal__cuerpo')?.scrollTo(0, 0);
                if (app && window.matchMedia('(max-width: 767px)').matches) requestAnimationFrame(() => window.scrollTo(0, 0));
            }
            if (paso === 2) {
                proponerControl();
                resumen();
            }
            programarGuardado();
        }

        function ir(indice, validar = true) {
            if (validar && indice > paso) {
                for (let i = paso; i < indice; i++) if (!validarPaso(i)) return false;
            }
            mostrar(indice);
            return true;
        }

        function resumen() {
            const lista = form.querySelector('[data-resumen-cierre]');
            lista.replaceChildren();
            const filas = consulta.tratamientos?.filas() || [];
            const campo = nombre => form.querySelector(`[name="${nombre}"]`)?.value || '—';
            const signos = [...form.querySelectorAll('[data-vital]')].map(tarjeta => {
                const nombre = tarjeta.dataset.vital;
                if (nombre === 'peso' && form.querySelector('[data-sin-peso]').checked) return 'Sin peso registrado';
                const etiqueta = tarjeta.querySelector('label').textContent.replace(' *', '');
                const unidad = tarjeta.querySelector('.consulta-vital__valor span').textContent;
                return etiqueta + ': ' + campo(nombre) + ' ' + unidad;
            }).join(' · ');
            const datos = { Motivo: campo('motivo'), Signos: signos, Diagnóstico: campo('diagnostico'), 'Exámenes y plan': campo('plan_tratamiento'), Tratamiento: filas.map(f => f.querySelector('[data-tratamiento-campo="medicamento"]').value).join(', ') || 'Sin medicamentos', Control: control.checked ? fechaControl.value || 'Por elegir' : 'Sin control solicitado' };
            for (const [titulo, texto] of Object.entries(datos)) {
                const dt = document.createElement('dt');
                const dd = document.createElement('dd');
                dt.textContent = titulo;
                dd.textContent = texto;
                lista.append(dt, dd);
            }
        }

        function aplicarReferencias() {
            for (const tarjeta of form.querySelectorAll('[data-vital]')) {
                const nombre = tarjeta.dataset.vital;
                const campo = tarjeta.querySelector('input');
                const rango = contexto?.referencias?.[nombre];
                const dato = Number(campo.value.replace(',', '.'));
                const valido = campo.value !== '' && Number.isFinite(dato);
                const ayuda = tarjeta.querySelector('[data-vital-rango]');
                const aviso = tarjeta.querySelector('[data-vital-referencia]');
                tarjeta.classList.remove('consulta-vital--fuera');
                aviso.hidden = true;
                if (rango) {
                    campo.dataset.referenciaMinimo = String(rango[0]);
                    campo.dataset.referenciaMaximo = String(rango[1]);
                    ayuda.textContent = `Normal ${String(rango[0]).replace('.', ',')}–${String(rango[1]).replace('.', ',')} ${tarjeta.querySelector('.consulta-vital__valor span').textContent}`;
                    ayuda.title = contexto.aviso_referencias;
                    if (valido && (dato < Number(campo.dataset.referenciaMinimo) || dato > Number(campo.dataset.referenciaMaximo))) {
                        tarjeta.classList.add('consulta-vital--fuera');
                        aviso.textContent = dato < rango[0] ? '↓ Por debajo de lo normal' : '↑ Por encima de lo normal';
                        aviso.hidden = false;
                    }
                } else if (nombre === 'peso' && contexto?.ultimo_peso) {
                    ayuda.textContent = 'Último: ' + contexto.ultimo_peso + ' kg';
                    if (valido && !campo.disabled && Math.abs(dato - Number(contexto.ultimo_peso)) / Number(contexto.ultimo_peso) > Number(contexto.cambio_peso)) {
                        tarjeta.classList.add('consulta-vital--fuera');
                        aviso.textContent = 'El peso cambió más del 10 % respecto del último registro';
                        aviso.hidden = false;
                    }
                } else ayuda.textContent = '';
            }
        }

        function fechaSugerida() {
            const fines = [...form.querySelectorAll('[data-tratamiento-campo="fecha_fin"]')].map(c => c.value).filter(v => FECHA_ISO.test(v)).sort();
            if (fines.length) return fines[fines.length - 1];
            if (!contexto) return '';
            const dia = new Date();
            dia.setDate(dia.getDate() + Number(contexto.control_dias_sin_duracion));
            return `${dia.getFullYear()}-${String(dia.getMonth() + 1).padStart(2, '0')}-${String(dia.getDate()).padStart(2, '0')}`;
        }

        function proponerControl() {
            if (!contexto) {
                control.checked = false;
                form.querySelector('[data-control-contenido]').hidden = true;
                return;
            }
            if (control.dataset.editado !== '1') control.checked = Boolean(form.querySelector('[name="plan_tratamiento"]').value.trim() || consulta.tratamientos?.filas().length);
            form.querySelector('[data-control-contenido]').hidden = !control.checked;
            if (!fechaManual && fechaSugerida()) fechaControl.value = fechaSugerida();
            const ultimaFila = (consulta.tratamientos?.filas() || []).filter(f => f.querySelector('[data-tratamiento-campo="fecha_fin"]').value === fechaSugerida()).pop();
            const descripcion = form.querySelector('[data-control-descripcion]');
            const medicamento = ultimaFila?.querySelector('[data-tratamiento-campo="medicamento"]').value;
            const duracion = ultimaFila?.querySelector('[data-tratamiento-campo="duracion"]').value;
            descripcion.textContent = medicamento && duracion ? `Al terminar los ${duracion} de ${medicamento} · Zooki propone la fecha` : `En ${contexto.control_dias_sin_duracion} días · Zooki propone la fecha`;
            if (control.checked) cargarCalendario(fechaControl.value);
        }

        async function cargarCalendario(fecha) {
            if (!FECHA_ISO.test(fecha)) return;
            const numero = ++calendarioPeticion;
            const primero = fecha.slice(0, 8) + '01';
            const ultimo = new Date(Number(fecha.slice(0, 4)), Number(fecha.slice(5, 7)), 0).getDate();
            const hasta = fecha.slice(0, 8) + String(ultimo);
            try {
                const url = 'index.php?action=fechas_control_ajax&' + new URLSearchParams({ id_mascota: idMascota(), desde: primero, hasta });
                const r = await (await fetch(url)).json();
                if (numero !== calendarioPeticion) return;
                if (!r.success) throw new Error(r.message);
                diasAbiertos = new Set(r.dias_abiertos);
                mesesAbiertos.set(fecha.slice(0, 7), diasAbiertos);
                fechaControl.dataset.valida = mesesAbiertos.get(fechaControl.value.slice(0, 7))?.has(fechaControl.value) ? '1' : '0';
                avisoControl.textContent = contexto?.tiene_horario === false ? 'No tienes horario aprobado. La consulta se guardará y podrás agendar el control después.' : fechaControl.dataset.valida === '1' ? '' : 'Ese día no tiene horario. Elige un día habilitado.';
                if (calendario) {
                    if (fecha.slice(0, 7) === fechaControl.value.slice(0, 7) && fechaControl.dataset.valida === '1') calendario.setDate(fechaControl.value, false);
                    calendario.redraw();
                }
            } catch (error) {
                if (numero !== calendarioPeticion) return;
                fechaControl.dataset.valida = '0';
                avisoControl.textContent = 'No se pudo verificar el horario. Vuelve a elegir la fecha.';
            }
        }

        async function activar(reintento = false) {
            const numero = ++contextoPeticion;
            finalizada = false;
            if (!reintento) {
                fechaManual = false;
                recuperacionIntentada = false;
                capturaEditada = false;
                delete control.dataset.editado;
            }
            fechaControl.dataset.valida = '0';
            calendarioPeticion++;
            contexto = null;
            clave = null;
            // RE-2.1.6: un fallo de contexto nunca impide documentar la consulta.
            control.disabled = true;
            control.checked = false;
            fechaControl.disabled = true;
            form.querySelector('[data-control-contenido]').hidden = true;
            reintentar.disabled = true;
            avisoContexto.hidden = false;
            avisoContexto.querySelector('[data-contexto-mensaje]').textContent = 'Verificando el contexto del próximo control…';
            if (calendario) calendario.destroy();
            calendario = null;
            try {
                const r = await (await fetch('index.php?action=consulta_contexto_ajax&' + new URLSearchParams({ id_mascota: idMascota() }))).json();
                if (numero !== contextoPeticion) return;
                if (!r.success) throw new Error('No se pudo verificar el contexto actual.');
                if (app && (String(r.id_clinica) !== app.dataset.idClinica || String(r.id_veterinario) !== app.dataset.idVeterinario)) {
                    throw new Error('Tu contexto cambió. Abre esta atención de nuevo desde la agenda.');
                }
                contexto = r;
                control.disabled = false;
                fechaControl.disabled = false;
                avisoContexto.hidden = true;
                reintentar.hidden = true;
                consulta.diagnosticos?.actualizarFicha(r.ficha);
                mesesAbiertos.clear();
                aplicarReferencias();
                if (window.flatpickr && !calendario) {
                    const fechaAnterior = fechaControl.value;
                    fechaControl.value = '';
                    fechaControl.type = 'text';
                    calendario = window.flatpickr(fechaControl, {
                        dateFormat: 'Y-m-d',
                        minDate: 'today',
                        disableMobile: true,
                        locale: window.flatpickr.l10ns?.es || 'default',
                        allowInput: false,
                        disable: [fecha => !mesesAbiertos.get(`${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}`)?.has(`${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`)],
                        onOpen: () => {
                            const fecha = fechaControl.value || fechaSugerida();
                            calendario.jumpToDate(fecha, false);
                            cargarCalendario(fecha);
                        },
                        onMonthChange: (_, __, instancia) => cargarCalendario(`${instancia.currentYear}-${String(instancia.currentMonth + 1).padStart(2, '0')}-01`),
                        onYearChange: (_, __, instancia) => cargarCalendario(`${instancia.currentYear}-${String(instancia.currentMonth + 1).padStart(2, '0')}-01`),
                    });
                    fechaControl.value = fechaAnterior;
                }
                if (app) {
                    clave = `zooki_atencion_borrador_${r.id_clinica}_${r.id_veterinario}_${app.dataset.idCita}`;
                    if (!recuperacionIntentada && !reintento && !capturaEditada) recuperar();
                    recuperacionIntentada = true;
                }
                if (paso === 2) proponerControl();
                if (reintento) {
                    const estado = area.querySelector('#estadoBorrador');
                    if (estado) estado.textContent = 'Contexto verificado.';
                    programarGuardado();
                }
            } catch (error) {
                if (numero !== contextoPeticion) return;
                contexto = null;
                control.disabled = true;
                control.checked = false;
                fechaControl.disabled = true;
                form.querySelector('[data-control-contenido]').hidden = true;
                avisoContexto.hidden = false;
                avisoContexto.querySelector('[data-contexto-mensaje]').textContent = 'No se pudo verificar el contexto. Puedes completar la consulta; el próximo control está desactivado.';
                reintentar.hidden = false;
                consulta.diagnosticos?.cargarFicha();
                const estado = area.querySelector('#estadoBorrador');
                if (estado) estado.textContent = error.message || 'No se pudo verificar el borrador de esta atención.';
            } finally {
                if (numero === contextoPeticion) reintentar.disabled = false;
            }
        }

        function exportar() {
            const campos = {};
            for (const campo of form.querySelectorAll('[name]')) {
                if (campo.type === 'file' || campo.name.startsWith('ficha_') || campo.name.endsWith('[]') || ['csrf_token', 'id_mascota', 'id_cita'].includes(campo.name)) continue;
                campos[campo.name] = campo.type === 'checkbox' ? campo.checked : campo.value;
            }
            return { version: 3, campos, paso, fechaManual, tratamientos: consulta.tratamientos?.exportar(), sugerencias: consulta.sugerencias?.exportar(), sintomas: consulta.sintomas?.marcados(), adjuntos: [...form.querySelectorAll('[type="file"]')].some(c => c.files.length > 0) };
        }

        function guardar() {
            if (!clave || restaurando || finalizada) return;
            try {
                localStorage.setItem(clave, JSON.stringify(exportar()));
                const estado = area.querySelector('#estadoBorrador');
                if (estado) estado.textContent = 'Borrador guardado hace un momento';
            } catch (error) {
                const estado = area.querySelector('#estadoBorrador');
                if (estado) estado.textContent = 'El borrador no se pudo guardar en este navegador';
            }
        }

        function programarGuardado() {
            if (restaurando) return;
            clearTimeout(temporizador);
            temporizador = setTimeout(guardar, 2000);
        }

        function recuperar() {
            try {
                const dato = JSON.parse(localStorage.getItem(clave) || 'null');
                if (!dato || dato.version !== 3) return;
                restaurando = true;
                fechaManual = Boolean(dato.fechaManual);
                for (const campo of form.querySelectorAll('[name]')) {
                    if (!(campo.name in dato.campos) || campo.type === 'file' || campo.name.endsWith('[]') || ['csrf_token', 'id_mascota', 'id_cita'].includes(campo.name)) continue;
                    if (campo.type === 'checkbox') campo.checked = dato.campos[campo.name] === true;
                    else if (typeof dato.campos[campo.name] === 'string') campo.value = dato.campos[campo.name];
                    campo.dispatchEvent(new Event(campo.type === 'checkbox' ? 'change' : 'input', { bubbles: true }));
                }
                consulta.tratamientos?.restaurar(dato.tratamientos);
                consulta.sintomas?.poner(dato.sintomas || []);
                consulta.sugerencias?.restaurar(dato.sugerencias);
                mostrar(Number(dato.paso) || 0);
                avisoBorrador.hidden = false;
                if (dato.adjuntos) avisoBorrador.querySelector('span').textContent = 'Recuperamos tu borrador. Vuelve a elegir los adjuntos.';
                aplicarReferencias();
            } catch (error) {
                // Un borrador inválido nunca sustituye datos del servidor ni deshabilita el formulario.
            } finally {
                restaurando = false;
            }
        }

        function borrar(completada = false) {
            finalizada = completada;
            clearTimeout(temporizador);
            if (clave) {
                try {
                    localStorage.removeItem(clave);
                } catch (error) {
                    // El cierre clínico no depende de la disponibilidad del almacenamiento local.
                }
            }
            avisoBorrador.hidden = true;
        }

        const descartar = () => {
            restaurando = true;
            borrar();
            form.reset();
            consulta.limpiar();
            mostrar(0);
            restaurando = false;
        };
        reintentar.addEventListener('click', () => activar(true));
        avisoBorrador.querySelector('[data-descartar-borrador]').addEventListener('click', descartar);
        for (const boton of botones) boton.addEventListener('click', () => ir(Number(boton.dataset.consultaIr)));
        for (const boton of siguientes) boton.addEventListener('click', () => ir(paso + 1));
        for (const boton of anteriores) boton.addEventListener('click', () => ir(paso - 1));
        form.addEventListener('input', () => {
            if (!restaurando && !contexto) capturaEditada = true;
            aplicarReferencias();
            programarGuardado();
            if (paso === 2) resumen();
        });
        form.addEventListener('change', () => {
            aplicarReferencias();
            programarGuardado();
            if (paso === 2) resumen();
        });
        form.addEventListener('zooki:diagnostico-usado', evento => {
            consulta.diagnosticos?.marcarEnUso(evento.detail.codigo);
            if (evento.detail.codigo && !restaurando) ir(1);
        });
        control.addEventListener('change', () => {
            control.dataset.editado = '1';
            form.querySelector('[data-control-contenido]').hidden = !control.checked;
            if (control.checked) proponerControl();
            resumen();
        });
        fechaControl.addEventListener('change', () => {
            fechaManual = true;
            cargarCalendario(fechaControl.value);
            resumen();
        });
        for (const zona of form.querySelectorAll('[data-soltar-adjuntos]')) {
            zona.addEventListener('dragover', evento => {
                evento.preventDefault();
                zona.classList.add('is-arrastrando');
            });
            zona.addEventListener('dragleave', () => zona.classList.remove('is-arrastrando'));
            zona.addEventListener('drop', evento => {
                evento.preventDefault();
                zona.classList.remove('is-arrastrando');
                const archivos = zona.querySelector('[type="file"]');
                archivos.files = evento.dataTransfer.files;
                archivos.dispatchEvent(new Event('change', { bubbles: true }));
                window.updateFileList(archivos);
            });
        }
        window.addEventListener('resize', moverPanel);
        window.addEventListener('pagehide', guardar);
        window.addEventListener('beforeunload', guardar);
        window.addEventListener('pageshow', evento => {
            if (!evento.persisted || !app) return;
            restaurando = true;
            form.reset();
            consulta.limpiar();
            restaurando = false;
            activar();
        });
        mostrar(0);
        if (app) activar();
        return { ir, mostrar, validarPaso, guardar, borrar, activar, exportar, recuperar, get actual() { return paso; } };
    };
    window.ZookiConsultaPasos = { crear };
    window.ZookiCierreConsulta = {
        mostrar(respuesta, form) {
            return new Promise(resolver => {
                const dialogo = document.createElement('dialog');
                dialogo.className = 'consulta-cierre-dialogo';
                const franja = document.createElement('div');
                franja.className = 'consulta-collage';
                const titulo = document.createElement('h2');
                titulo.textContent = 'Atención registrada';
                const perro = document.createElement('img');
                perro.src = 'img/portal-bienvenida.png';
                perro.alt = '';
                franja.append(titulo, perro);
                const mensaje = document.createElement('p');
                mensaje.textContent = respuesta.control?.message || 'La consulta quedó guardada en la historia clínica.';
                const pie = document.createElement('div');
                pie.className = 'consulta-cierre-dialogo__acciones';
                const volver = document.createElement('button');
                volver.type = 'button';
                volver.className = 'consulta-boton consulta-boton--suave';
                volver.textContent = 'Volver a la agenda';
                const terminar = destino => {
                    if (dialogo.open) dialogo.close();
                    dialogo.remove();
                    resolver(destino);
                };
                volver.addEventListener('click', () => terminar('index.php?action=vet_agenda'));
                pie.append(volver);
                if (respuesta.control?.creado === false && respuesta.control.url_agendar) {
                    const agendar = document.createElement('button');
                    agendar.type = 'button';
                    agendar.className = 'consulta-boton';
                    agendar.textContent = 'Agendar control';
                    agendar.addEventListener('click', () => {
                        const destino = new URL(respuesta.control.url_agendar, location.href);
                        terminar(destino.origin === location.origin && destino.searchParams.get('action') === 'vet_agenda' ? destino.href : null);
                    });
                    pie.append(agendar);
                }
                dialogo.addEventListener('cancel', evento => {
                    evento.preventDefault();
                    terminar('index.php?action=vet_agenda');
                });
                dialogo.append(franja, mensaje, pie);
                document.body.append(dialogo);
                dialogo.showModal();
            });
        },
    };
})();
