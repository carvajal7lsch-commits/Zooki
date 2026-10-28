/**
 * Piezas comunes de los Inicios del veterinario (HU-6.1) y del administrador
 * (HU-6.2).
 *
 * La vista llega pintada desde el servidor. Aquí se cambia de día y de mes en
 * el calendario: la acción AJAX de cada Inicio (data-inicio-accion en la raíz)
 * devuelve los mismos parciales PHP del primer render, y una respuesta vieja
 * nunca pisa a la nueva. También se abren y cierran las ventanas del Inicio y
 * se recarga la página si estuvo oculta un buen rato. Los datos de la base
 * llegan ya escapados en el HTML del servidor.
 */
(function (global) {
    'use strict';

    const REFRESCAR_TRAS_MS = 5 * 60 * 1000;

    /** Pide un fragmento del Inicio a su acción; falla si la respuesta no sirve. */
    async function pedirFragmento(accion, parametros) {
        const respuesta = await fetch('index.php?action=' + encodeURIComponent(accion) + '&' + new URLSearchParams(parametros));
        const datos = await respuesta.json();
        if (!datos || !datos.success || typeof datos.html !== 'string') {
            throw new Error((datos && datos.message) || 'No se pudo cargar.');
        }
        return datos.html;
    }

    /** RE-6.1.8/RE-6.1.9 y RE-6.2.8/RE-6.2.9: el día tocado y el mes elegido. */
    function iniciarCalendario(raiz) {
        const accion = raiz.dataset.inicioAccion;
        const calendario = raiz.querySelector('[data-inicio-calendario]');
        const contenidoCalendario = raiz.querySelector('[data-inicio-calendario-contenido]');
        const dia = raiz.querySelector('[data-inicio-dia]');
        const contenidoDia = raiz.querySelector('[data-inicio-dia-contenido]');
        let turnoDia = 0;
        let turnoMes = 0;
        const api = { mostrarDia, mostrarMes, consultarMes, alCambiar: null };

        function consultarMes(mes) {
            return pedirFragmento(accion, { mes, elegido: calendario.dataset.elegido });
        }

        async function mostrarDia(fecha) {
            const turno = ++turnoDia;
            calendario.dataset.elegido = fecha;
            api.alCambiar?.({ tipo: 'seleccion', fecha });
            contenidoCalendario.querySelectorAll('[data-inicio-fecha]').forEach((boton) => {
                const elegido = boton.dataset.inicioFecha === fecha;
                boton.classList.toggle('is-elegido', elegido);
                boton.setAttribute('aria-pressed', elegido ? 'true' : 'false');
            });
            dia.setAttribute('aria-busy', 'true');
            try {
                const html = await pedirFragmento(accion, { fecha });
                if (turno !== turnoDia) return;
                contenidoDia.innerHTML = html;
                contenidoDia.scrollTop = 0;
                api.alCambiar?.({ tipo: 'dia', fecha });
            } catch (error) {
                if (turno !== turnoDia) return;
                global.zookiAviso?.('No se pudo cargar ese día. Intenta nuevamente.');
            } finally {
                if (turno === turnoDia) dia.setAttribute('aria-busy', 'false');
            }
        }

        async function mostrarMes(mes) {
            const turno = ++turnoMes;
            try {
                const html = await consultarMes(mes);
                if (turno !== turnoMes) return;
                contenidoCalendario.innerHTML = html;
                api.alCambiar?.({ tipo: 'mes', mes, html });
            } catch (error) {
                if (turno === turnoMes) global.zookiAviso?.('No se pudo cargar ese mes. Intenta nuevamente.');
            }
        }

        contenidoCalendario.addEventListener('click', (evento) => {
            const boton = evento.target.closest('[data-inicio-fecha]');
            if (boton) {
                mostrarDia(boton.dataset.inicioFecha);
                return;
            }
            const flecha = evento.target.closest('[data-inicio-mes]');
            if (flecha) mostrarMes(flecha.dataset.inicioMes);
        });
        return api;
    }

    /** Ventanas del Inicio: se abren con data-inicio-abrir y se cierran con su botón o con clic en el fondo. */
    function iniciarDialogos(raiz) {
        raiz.addEventListener('click', (evento) => {
            const abrir = evento.target.closest('[data-inicio-abrir]');
            if (abrir) {
                const dialogo = global.document.getElementById(abrir.dataset.inicioAbrir);
                if (dialogo && typeof dialogo.showModal === 'function') dialogo.showModal();
                return;
            }
            if (evento.target.closest('[data-inicio-cerrar]')) evento.target.closest('[data-inicio-dialogo]')?.close();
        });
        // Clic en el fondo del diálogo: el objetivo es el propio <dialog>, no su contenido.
        raiz.querySelectorAll('[data-inicio-dialogo]').forEach((dialogo) => {
            dialogo.addEventListener('click', (evento) => {
                if (evento.target === dialogo) dialogo.close();
            });
        });
    }

    /** La agenda cambia durante el día: si la pestaña estuvo oculta un buen rato, al volver se recarga. */
    function recargarAlVolver(documento) {
        let ocultaDesde = null;
        documento.addEventListener('visibilitychange', () => {
            if (documento.hidden) {
                ocultaDesde = Date.now();
            } else if (ocultaDesde && Date.now() - ocultaDesde > REFRESCAR_TRAS_MS) {
                global.location.reload();
            }
        });
    }

    /** RNF-16: componentes móviles optativos; no se activan por el rol ni por el ancho solos. */
    function pintarFranjaSemana(interfaz, fechas, elegido, hoy, conEventos) {
        const documento = global.document;
        const franja = interfaz.querySelector('[data-inicio-semana-dias]');
        franja.replaceChildren();
        fechas.forEach((fecha, indice) => {
            const boton = documento.createElement('button');
            boton.type = 'button';
            boton.className = 'inicio-semana__dia';
            boton.dataset.inicioSemanaFecha = fecha;
            boton.setAttribute('aria-pressed', String(fecha === elegido));
            if (fecha === hoy) boton.setAttribute('aria-current', 'date');
            const tieneCitas = conEventos(fecha);
            const etiqueta = new Date(fecha + 'T12:00:00').toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' });
            boton.setAttribute('aria-label', etiqueta + (tieneCitas ? ', con citas' : ''));
            const nombre = documento.createElement('small');
            nombre.textContent = ['L', 'M', 'M', 'J', 'V', 'S', 'D'][indice];
            const numero = documento.createElement('strong');
            numero.textContent = Number(fecha.slice(8));
            boton.append(nombre, numero);
            if (tieneCitas) {
                const punto = documento.createElement('span');
                punto.className = 'inicio-semana__punto';
                punto.setAttribute('aria-hidden', 'true');
                boton.append(punto);
            }
            franja.append(boton);
        });
    }

    function iniciarMovil(raiz, calendarioApi) {
        if (typeof global.matchMedia !== 'function' || !raiz.hasAttribute('data-inicio-movil-habilitado')) return;
        const documento = global.document;
        const movil = global.matchMedia('(max-width: 767px)');
        const interfaz = raiz.querySelector('[data-inicio-movil]');
        const clavesPestañas = Array.from(interfaz.querySelectorAll('[data-inicio-tab]'), boton => boton.dataset.inicioTab);
        const calendario = raiz.querySelector('[data-inicio-calendario]');
        const contenidoCalendario = raiz.querySelector('[data-inicio-calendario-contenido]');
        const dialogo = raiz.querySelector('#inicioMovilCalendario');
        const selector = interfaz.querySelector('[data-inicio-selector-vet]');
        const claveRecuerdo = 'zooki-inicio-pestana-' + raiz.dataset.inicioAccion;
        const meses = new Map();
        const lecturasMes = new Map();
        let activo = false;
        let veterinario = '';
        let turnoSemana = 0;
        let pestaña = clavesPestañas[0];
        const partes = Array.from(interfaz.querySelectorAll('[data-inicio-destino]')).flatMap(destino => {
            const clave = destino.dataset.inicioDestino;
            const nodo = raiz.querySelector('[data-inicio-bloque="' + clave + '"]');
            if (!nodo) return [];
            const marca = documento.createComment('Posición de escritorio: ' + clave);
            nodo.before(marca);
            return [{ nodo, marca, destino }];
        });
        const marcaCalendario = documento.createComment('Calendario de escritorio');
        calendario.before(marcaCalendario);

        function fechaLocal(fecha) {
            return fecha.getFullYear() + '-' + String(fecha.getMonth() + 1).padStart(2, '0') + '-' + String(fecha.getDate()).padStart(2, '0');
        }
        function fechaDesdeTexto(texto) {
            const partes = texto.split('-').map(Number);
            return new Date(partes[0], partes[1] - 1, partes[2], 12);
        }
        function leerMes(contenido) {
            const metadatos = contenido.querySelector('[data-inicio-mes-actual]');
            if (!metadatos) return;
            meses.set(metadatos.dataset.inicioMesActual, {
                titulo: contenido.querySelector('#inicioTituloCal').textContent,
                puntos: new Set(Array.from(contenido.querySelectorAll('[data-inicio-fecha]')).filter(n => n.querySelector('.inicio-cal__marca')).map(n => n.dataset.inicioFecha))
            });
        }
        leerMes(contenidoCalendario);

        async function asegurarMes(mes) {
            if (meses.has(mes)) return meses.get(mes);
            if (!lecturasMes.has(mes)) {
                lecturasMes.set(mes, calendarioApi.consultarMes(mes).then(html => {
                    const molde = documento.createElement('template');
                    molde.innerHTML = html;
                    leerMes(molde.content);
                    return meses.get(mes);
                }).finally(() => lecturasMes.delete(mes)));
            }
            return lecturasMes.get(mes);
        }

        async function pintarSemana() {
            if (!activo) return;
            const turno = ++turnoSemana;
            const elegido = calendario.dataset.elegido;
            const inicio = fechaDesdeTexto(elegido);
            inicio.setDate(inicio.getDate() - ((inicio.getDay() + 6) % 7));
            const fechas = Array.from({ length: 7 }, (_, indice) => {
                const fecha = new Date(inicio);
                fecha.setDate(fecha.getDate() + indice);
                return fechaLocal(fecha);
            });
            try {
                const claves = [...new Set(fechas.map(fecha => fecha.slice(0, 7)))];
                await Promise.all(claves.map(asegurarMes));
                if (!activo || turno !== turnoSemana) return;
                interfaz.querySelector('[data-inicio-semana-mes]').textContent = meses.get(elegido.slice(0, 7))?.titulo || '';
                pintarFranjaSemana(interfaz, fechas, elegido, raiz.dataset.hoy, fecha => meses.get(fecha.slice(0, 7))?.puntos.has(fecha));
            } catch (_) {
                if (turno === turnoSemana) global.zookiToast?.('No se pudo cargar la semana. Intenta nuevamente.', 'error');
            }
        }

        function elegirVeterinario(id) {
            veterinario = id;
            raiz.querySelectorAll('[data-inicio-veterinario]').forEach(columna => {
                columna.hidden = activo && columna.dataset.inicioVeterinario !== id;
            });
            selector.querySelectorAll('[data-inicio-elegir-vet]').forEach(boton => {
                boton.setAttribute('aria-pressed', String(boton.dataset.inicioElegirVet === id));
            });
        }
        function pintarVeterinarios() {
            if (!activo || !selector) return;
            const columnas = Array.from(raiz.querySelectorAll('[data-inicio-veterinario]'));
            selector.replaceChildren();
            selector.hidden = columnas.length <= 1;
            for (const columna of columnas) {
                const boton = documento.createElement('button');
                boton.type = 'button';
                boton.className = 'inicio-selector-vet__opcion';
                boton.dataset.inicioElegirVet = columna.dataset.inicioVeterinario;
                const cabeza = columna.querySelector('.inicio-agenda__cabeza');
                for (const hijo of cabeza.children) boton.append(hijo.cloneNode(true));
                const ocupacion = columna.dataset.inicioOcupacion;
                const citas = Number(columna.dataset.inicioCitas);
                boton.querySelector('.inicio-meta').textContent = citas + (citas === 1 ? ' cita' : ' citas') + (ocupacion === '' ? ' · sin jornada' : ' · ' + ocupacion + ' % ocupado');
                selector.append(boton);
            }
            const elegido = columnas.find(columna => columna.dataset.inicioVeterinario === veterinario) || columnas[0];
            elegirVeterinario(elegido?.dataset.inicioVeterinario || '');
        }
        function activarPestaña(clave, enfocar = false) {
            if (!clavesPestañas.includes(clave)) clave = clavesPestañas[0];
            pestaña = clave;
            interfaz.querySelectorAll('[data-inicio-tab]').forEach(boton => {
                const elegida = boton.dataset.inicioTab === clave;
                boton.setAttribute('aria-selected', String(elegida));
                boton.tabIndex = elegida ? 0 : -1;
                if (elegida && enfocar) boton.focus();
            });
            interfaz.querySelectorAll('[data-inicio-panel]').forEach(panel => {
                panel.hidden = panel.dataset.inicioPanel !== clave;
            });
            try {
                global.sessionStorage.setItem(claveRecuerdo, clave);
            } catch (_) {
                // Si se bloquea el almacenamiento, el hash aún puede recordar la pestaña.
            }
            try {
                global.history.replaceState(null, '', '#inicio-' + clave);
            } catch (_) {
                // Cambiar de pestaña no depende de que el historial esté disponible.
            }
        }
        function recordarPestaña() {
            const hash = global.location.hash.replace(/^#inicio-/, '');
            if (clavesPestañas.includes(hash)) return hash;
            try {
                return global.sessionStorage.getItem(claveRecuerdo) || pestaña;
            } catch (_) {
                return pestaña;
            }
        }
        function ajustar() {
            if (activo === movil.matches) return;
            activo = movil.matches;
            if (activo) {
                for (const parte of partes) parte.destino.append(parte.nodo);
                interfaz.querySelector('[data-inicio-calendario-destino]').append(calendario);
                activarPestaña(recordarPestaña());
                pintarVeterinarios();
                pintarSemana();
            } else {
                ++turnoSemana;
                if (dialogo.open) dialogo.close();
                for (const parte of partes) parte.marca.after(parte.nodo);
                marcaCalendario.after(calendario);
                raiz.querySelectorAll('[data-inicio-veterinario]').forEach(columna => { columna.hidden = false; });
            }
        }
        calendarioApi.alCambiar = cambio => {
            if (cambio.tipo === 'mes') leerMes(contenidoCalendario);
            if (!activo) return;
            if (cambio.tipo === 'mes') {
                contenidoCalendario.querySelectorAll('[data-inicio-fecha]').forEach(boton => {
                    const elegido = boton.dataset.inicioFecha === calendario.dataset.elegido;
                    boton.classList.toggle('is-elegido', elegido);
                    boton.setAttribute('aria-pressed', String(elegido));
                });
            }
            if (cambio.tipo === 'dia') pintarVeterinarios();
            if (cambio.tipo !== 'mes') pintarSemana();
        };
        raiz.addEventListener('click', evento => {
            if (!activo) return;
            const tab = evento.target.closest('[data-inicio-tab]');
            if (tab) activarPestaña(tab.dataset.inicioTab);
            const vet = evento.target.closest('[data-inicio-elegir-vet]');
            if (vet) elegirVeterinario(vet.dataset.inicioElegirVet);
            const dia = evento.target.closest('[data-inicio-semana-fecha]');
            if (dia) calendarioApi.mostrarDia(dia.dataset.inicioSemanaFecha);
            const paso = evento.target.closest('[data-inicio-semana-paso]');
            if (paso) {
                const fecha = fechaDesdeTexto(calendario.dataset.elegido);
                fecha.setDate(fecha.getDate() + Number(paso.dataset.inicioSemanaPaso) * 7);
                calendarioApi.mostrarDia(fechaLocal(fecha));
            }
            if (evento.target.closest('[data-inicio-calendario-abrir]')) {
                const mes = calendario.dataset.elegido.slice(0, 7);
                if (contenidoCalendario.querySelector('[data-inicio-mes-actual]')?.dataset.inicioMesActual !== mes) calendarioApi.mostrarMes(mes);
                dialogo.showModal();
            }
            if (evento.target.closest('[data-inicio-fecha]') && dialogo.open) dialogo.close();
        });
        interfaz.addEventListener('keydown', evento => {
            const tab = evento.target.closest('[data-inicio-tab]');
            if (!tab || !['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(evento.key)) return;
            evento.preventDefault();
            const claves = clavesPestañas;
            const indice = evento.key === 'Home' ? 0 : evento.key === 'End' ? claves.length - 1 : (claves.indexOf(tab.dataset.inicioTab) + (evento.key === 'ArrowRight' ? 1 : claves.length - 1)) % claves.length;
            activarPestaña(claves[indice], true);
        });
        global.addEventListener('hashchange', () => { if (activo) activarPestaña(recordarPestaña()); });
        movil.addEventListener('change', ajustar);
        ajustar();
    }

    const api = { pintarSemana: pintarFranjaSemana, iniciarCalendario, iniciarDialogos, recargarAlVolver, iniciarMovil };
    global.ZookiInicio = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(globalThis);
