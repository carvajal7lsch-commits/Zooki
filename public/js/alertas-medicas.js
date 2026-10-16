/*
 * HU-2.1, RE-2.1.5 y RE-2.10.1 — Sección «Alergias y alertas» de la mascota.
 * La usan la historia del veterinario (Pacientes, Consultas y Atención) y, de solo
 * lectura, el portal del propietario. Las funciones puras van en window.ZookiAlertas
 * para probarlas con node --test; el texto de la base se pinta con textContent.
 */
(function (global) {
    'use strict';

    const ICONOS = {
        fa: { alergia: 'fas fa-hand-dots', condicion_cronica: 'fas fa-heart-pulse', medicacion_continua: 'fas fa-pills', otra: 'fas fa-circle-info', titulo: 'fas fa-triangle-exclamation', historial: 'fas fa-clock-rotate-left' },
        ri: { alergia: 'ri-alert-line', condicion_cronica: 'ri-heart-pulse-line', medicacion_continua: 'ri-capsule-line', otra: 'ri-information-line', titulo: 'ri-error-warning-line', historial: 'ri-history-line' },
    };
    const RUTAS = {
        listar: 'index.php?action=listar_alertas_medicas_ajax',
        registrar: 'index.php?action=registrar_alerta_medica_ajax',
        desactivar: 'index.php?action=desactivar_alerta_medica_ajax',
        buscar: 'index.php?action=buscar_nodo_alerta_ajax',
    };
    const BUSQUEDA_MIN = 2;
    const ESPERA_BUSQUEDA = 250;
    // M2 C8 (H4): la sección compacta muestra las primeras y «Ver todas» despliega el resto.
    const VISIBLES_COMPACTA = 2;
    const CARACTERES = /^[\p{L}\p{M}\p{N}\p{P}\p{S} ]+$/u;

    /** Mismo criterio que ArchivosConocimiento::normalizarTexto. */
    function normalizar(texto) {
        return String(texto ?? '').trim().toLowerCase().normalize('NFD').replace(/\p{Mn}/gu, '').replace(/\s+/g, ' ');
    }

    function limpiar(texto) {
        return String(texto ?? '').trim().replace(/\s+/g, ' ');
    }

    /** La misma regla que ValidadorAlertaMedica::descripcion; '' si es válida. */
    function validarDescripcion(texto, maximo) {
        const limpio = limpiar(texto);
        if (limpio === '') {
            return 'Describe la alergia o la alerta.';
        }
        if ([...limpio].length > maximo) {
            return 'La descripción admite hasta ' + maximo + ' caracteres.';
        }
        if (!CARACTERES.test(limpio)) {
            return 'La descripción tiene caracteres no permitidos.';
        }
        return '';
    }

    /** A.2: sin nodo, el motor de prescripción (C3) no podrá cruzar la alerta. */
    function textoEnlace(alerta) {
        if (alerta.nodo_etiqueta) {
            return 'Enlazada al conocimiento: «' + alerta.nodo_etiqueta + '»';
        }
        if (alerta.tipo === 'otra') {
            return '';
        }
        return 'Sin enlace al conocimiento: el motor de prescripción no podrá cruzarla.';
    }

    function fechaCorta(valor) {
        const coincidencia = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(valor ?? ''));
        return coincidencia ? coincidencia[3] + '/' + coincidencia[2] + '/' + coincidencia[1] : '';
    }

    /** Tipos de nodo que admite cada tipo de alerta, leídos del formulario (ValidadorAlertaMedica::NODOS_POR_TIPO). */
    function admiteNodo(nodosPorTipo, tipo) {
        return Array.isArray(nodosPorTipo[tipo]) && nodosPorTipo[tipo].length > 0;
    }

    const api = { normalizar, validarDescripcion, textoEnlace, fechaCorta, admiteNodo, RUTAS };
    global.ZookiAlertas = api;

    const doc = global.document;
    if (!doc || typeof doc.addEventListener !== 'function') {
        return;
    }

    function el(etiqueta, clase, texto) {
        const nodo = doc.createElement(etiqueta);
        if (clase) {
            nodo.className = clase;
        }
        if (texto !== undefined && texto !== null) {
            nodo.textContent = texto;
        }
        return nodo;
    }

    function icono(clase) {
        const i = el('i', clase);
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    function avisar(mensaje, tipo) {
        if (typeof global.zookiToast === 'function') {
            global.zookiToast(mensaje, tipo);
        }
    }

    function tarjeta(alerta, opciones) {
        const iconos = ICONOS[opciones.iconos] || ICONOS.fa;
        const item = el('li', 'alertas-medicas__item alertas-medicas__item--' + alerta.tipo);
        item.dataset.alerta = String(alerta.id_alerta);
        const marca = el('span', 'alertas-medicas__icono');
        marca.appendChild(icono(iconos[alerta.tipo] || iconos.otra));
        const cuerpo = el('div', 'alertas-medicas__cuerpo');
        const cabeza = el('p', 'alertas-medicas__cabeza');
        cabeza.append(el('strong', 'alertas-medicas__tipo', alerta.tipo_nombre || alerta.tipo), el('span', 'alertas-medicas__descripcion', alerta.descripcion));
        cuerpo.appendChild(cabeza);
        const origen = el('p', 'alertas-medicas__origen');
        const partes = [alerta.clinica_nombre];
        if (alerta.veterinario) {
            partes.push(alerta.veterinario);
        }
        if (fechaCorta(alerta.fecha)) {
            partes.push(fechaCorta(alerta.fecha));
        }
        origen.textContent = partes.filter(Boolean).join(' · ');
        if (alerta.es_propia) {
            origen.appendChild(el('span', 'alertas-medicas__propia', 'Tu clínica'));
        }
        cuerpo.appendChild(origen);
        if (!opciones.lectura) {
            const enlace = textoEnlace(alerta);
            if (enlace) {
                cuerpo.appendChild(el('p', 'alertas-medicas__enlace' + (alerta.nodo_etiqueta ? '' : ' alertas-medicas__enlace--sin'), enlace));
            }
        }
        item.append(marca, cuerpo);
        if (alerta.puede_desactivar && !opciones.lectura) {
            const boton = el('button', 'alertas-medicas__desactivar', 'Desactivar');
            boton.type = 'button';
            boton.dataset.desactivarAlerta = String(alerta.id_alerta);
            boton.addEventListener('click', () => desactivar(alerta, opciones));
            item.appendChild(boton);
        }
        return item;
    }

    function itemHistorial(alerta) {
        const item = el('li', 'alertas-medicas__pasada');
        item.appendChild(el('strong', null, (alerta.tipo_nombre || alerta.tipo) + ': '));
        item.appendChild(doc.createTextNode(alerta.descripcion));
        const detalle = ['Registrada por ' + alerta.clinica_nombre + (fechaCorta(alerta.fecha) ? ' el ' + fechaCorta(alerta.fecha) : '')];
        if (alerta.desactivada_en) {
            detalle.push('desactivada' + (alerta.desactivada_por ? ' por ' + alerta.desactivada_por : '') + ' el ' + fechaCorta(alerta.desactivada_en));
        }
        item.appendChild(el('span', 'alertas-medicas__pasada-detalle', detalle.join('; ')));
        return item;
    }

    /**
     * Pinta la sección. datos: {activas, historial, solo_lectura}. opciones:
     * idMascota, nombreMascota, lectura (portal o personal sin permiso de escribir) e iconos ('fa' | 'ri').
     */
    function pintar(contenedor, datos, opciones = {}) {
        if (!contenedor) {
            return;
        }
        const ajustes = { iconos: 'fa', lectura: false, ...opciones };
        const activas = (datos && datos.activas) || [];
        const historial = (datos && datos.historial) || [];
        const escritura = !ajustes.lectura && !(datos && datos.solo_lectura);
        const iconos = ICONOS[ajustes.iconos] || ICONOS.fa;
        contenedor.dataset.idMascota = String(ajustes.idMascota ?? contenedor.dataset.idMascota ?? '');
        contenedor._alertas = { opciones: ajustes };
        contenedor.replaceChildren();
        contenedor.hidden = false;
        contenedor.classList.toggle('alertas-medicas--con-alertas', activas.length > 0);
        const cabeza = el('div', 'alertas-medicas__barra');
        const titulo = el('h4', 'alertas-medicas__titulo');
        titulo.append(icono(iconos.titulo), doc.createTextNode(' Alergias y alertas'), el('span', 'alertas-medicas__contador', String(activas.length)));
        cabeza.appendChild(titulo);
        if (escritura) {
            const registrar = el('button', 'alertas-medicas__registrar', 'Registrar');
            registrar.type = 'button';
            registrar.dataset.registrarAlerta = '';
            // M2 C8 (H4): en la columna angosta de Atención el botón se ve solo como icono.
            registrar.title = 'Registrar alergia o alerta';
            registrar.setAttribute('aria-label', 'Registrar alergia o alerta');
            registrar.prepend(icono('fas fa-plus'), doc.createTextNode(' '));
            registrar.addEventListener('click', () => abrirFormulario(contenedor.dataset.idMascota, ajustes.nombreMascota));
            cabeza.appendChild(registrar);
        }
        contenedor.appendChild(cabeza);
        if (activas.length === 0) {
            // Que no haya registro no significa que no tenga: se dice así.
            contenedor.appendChild(el('p', 'alertas-medicas__vacio', 'No hay alergias ni alertas registradas. Que no haya registro no significa que no tenga.'));
        } else {
            const lista = el('ul', 'alertas-medicas__lista');
            for (const alerta of activas) {
                lista.appendChild(tarjeta(alerta, ajustes));
            }
            contenedor.appendChild(lista);
            if (contenedor.classList.contains('alertas-medicas--compacta') && activas.length > VISIBLES_COMPACTA) {
                contenedor.appendChild(botonVerTodas(lista, activas.length));
            }
        }
        if (historial.length > 0 && !ajustes.lectura) {
            const detalles = el('details', 'alertas-medicas__historial');
            const resumen = el('summary', null, 'Historial (' + historial.length + ' desactivada' + (historial.length === 1 ? '' : 's') + ')');
            resumen.prepend(icono(iconos.historial), doc.createTextNode(' '));
            detalles.appendChild(resumen);
            const lista = el('ul', 'alertas-medicas__pasadas');
            for (const alerta of historial) {
                lista.appendChild(itemHistorial(alerta));
            }
            detalles.appendChild(lista);
            contenedor.appendChild(detalles);
        }
    }

    /** M2 C8 (H4): limita la lista compacta y alterna entre ver todas y ver menos. */
    function botonVerTodas(lista, total) {
        lista.classList.add('alertas-medicas__lista--limitada');
        const boton = el('button', 'alertas-medicas__ver-todas', 'Ver todas (' + total + ')');
        boton.type = 'button';
        boton.dataset.verTodasAlertas = '';
        boton.setAttribute('aria-expanded', 'false');
        boton.addEventListener('click', () => {
            const expandida = !lista.classList.contains('is-expandida');
            lista.classList.toggle('is-expandida', expandida);
            boton.setAttribute('aria-expanded', String(expandida));
            boton.textContent = expandida ? 'Ver menos' : 'Ver todas (' + total + ')';
        });
        return boton;
    }

    /** Toda sección de la página que muestre la misma mascota se actualiza con la respuesta. */
    function repintar(idMascota, alertas) {
        for (const contenedor of doc.querySelectorAll('[data-alertas-medicas]')) {
            if (contenedor.dataset.idMascota === String(idMascota) && contenedor._alertas) {
                pintar(contenedor, alertas, contenedor._alertas.opciones);
            }
        }
        // M2 C5b: una alergia o medicación continua nueva cambia los cruces de la prescripción en curso.
        if (typeof global.CustomEvent === 'function' && typeof doc.dispatchEvent === 'function') {
            doc.dispatchEvent(new global.CustomEvent('zooki:alertas-medicas', { detail: { idMascota: String(idMascota) } }));
        }
    }

    async function pedir(url, opciones) {
        const respuesta = await global.fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, ...opciones });
        let cuerpo = null;
        try {
            cuerpo = await respuesta.json();
        } catch (error) {
            cuerpo = null;
        }
        return { ok: respuesta.ok && Boolean(cuerpo && cuerpo.success), cuerpo: cuerpo || {} };
    }

    async function cargar(contenedor) {
        const idMascota = contenedor.dataset.idMascota;
        contenedor.replaceChildren(el('p', 'alertas-medicas__vacio', 'Cargando alergias y alertas…'));
        const { ok, cuerpo } = await pedir(RUTAS.listar + '&id_mascota=' + encodeURIComponent(idMascota));
        if (!ok) {
            contenedor.replaceChildren(el('p', 'alertas-medicas__vacio alertas-medicas__vacio--error', cuerpo.message || 'No se pudieron cargar las alergias y alertas.'));
            return;
        }
        pintar(contenedor, cuerpo.alertas, { idMascota, nombreMascota: contenedor.dataset.nombreMascota, lectura: contenedor.dataset.lectura === '1' });
    }

    async function desactivar(alerta, opciones) {
        const confirmar = global.zookiConfirmarCorto;
        // Quitar una alergia quita un bloqueo de seguridad de la prescripción: se confirma.
        const aceptado = typeof confirmar === 'function'
            ? await confirmar('¿Desactivar esta ' + (alerta.tipo === 'alergia' ? 'alergia' : 'alerta') + '?', 'Desactivar', [
                ['Tipo', alerta.tipo_nombre || alerta.tipo],
                ['Descripción', alerta.descripcion],
                ['Efecto', 'Deja de mostrarse y de alertar; queda en el historial.'],
            ])
            : false;
        if (!aceptado) {
            return;
        }
        const datos = new global.FormData();
        datos.append('id_alerta', String(alerta.id_alerta));
        const { ok, cuerpo } = await pedir(RUTAS.desactivar, { method: 'POST', body: datos });
        if (!ok) {
            avisar(cuerpo.message || 'No se pudo desactivar la alerta.', 'error');
            return;
        }
        repintar(opciones.idMascota, cuerpo.alertas);
        avisar(cuerpo.message || 'Alerta desactivada.', 'success');
    }

    // ── Formulario de registro (modal compartido de views/partials/modal_alerta_medica.php) ──

    const formulario = { modal: null, form: null, idMascota: null, temporizador: null, nodosPorTipo: {} };

    function campo(nombre) {
        return formulario.form.querySelector('[data-alerta-campo="' + nombre + '"]');
    }

    function tipoElegido() {
        const marcado = formulario.form.querySelector('[data-alerta-tipo]:checked');
        return marcado ? marcado.value : '';
    }

    function mostrarAviso(nombre, mensaje) {
        const aviso = formulario.form.querySelector('[data-alerta-aviso="' + nombre + '"]');
        if (aviso) {
            aviso.textContent = mensaje;
            aviso.classList.toggle('cuenta-validacion--error', mensaje !== '');
        }
    }

    function validarCampoDescripcion() {
        const area = campo('descripcion');
        const maximo = Number(area.getAttribute('maxlength')) || 255;
        const mensaje = validarDescripcion(area.value, maximo);
        mostrarAviso('descripcion', area.value === '' && !area.dataset.tocado ? '' : mensaje);
        const contador = formulario.form.querySelector('[data-alerta-contador]');
        if (contador) {
            contador.textContent = [...area.value].length + '/' + maximo;
        }
        return mensaje;
    }

    function quitarNodo() {
        campo('nodo').value = '';
        const elegido = formulario.form.querySelector('[data-alerta-nodo-elegido]');
        elegido.replaceChildren();
        elegido.hidden = true;
        formulario.form.querySelector('[data-alerta-buscar]').hidden = false;
    }

    function elegirNodo(nodo) {
        campo('nodo').value = nodo.codigo;
        const elegido = formulario.form.querySelector('[data-alerta-nodo-elegido]');
        const quitar = el('button', 'alertas-medicas__quitar-nodo', '×');
        quitar.type = 'button';
        quitar.setAttribute('aria-label', 'Quitar el enlace');
        quitar.addEventListener('click', quitarNodo);
        elegido.replaceChildren(el('span', null, 'Enlazada a «' + nodo.etiqueta + '»'), quitar);
        elegido.hidden = false;
        formulario.form.querySelector('[data-alerta-buscar]').hidden = true;
        cerrarSugerencias();
    }

    /** Cierra la lista de nodos sugeridos (también con Esc, M2 C8). */
    function cerrarSugerencias() {
        const caja = formulario.form.querySelector('[data-alerta-sugerencias]');
        caja.replaceChildren();
        caja.hidden = true;
        const buscador = campo('busqueda');
        buscador.setAttribute('aria-expanded', 'false');
        if (global.ZookiCombobox) {
            global.ZookiCombobox.reiniciar(buscador);
        }
    }

    function actualizarTipo() {
        const tipo = tipoElegido();
        const admite = admiteNodo(formulario.nodosPorTipo, tipo);
        const zona = formulario.form.querySelector('[data-alerta-zona-nodo]');
        zona.hidden = !admite;
        // Un nodo de otro tipo no se conserva al cambiar de tipo.
        quitarNodo();
        const buscador = campo('busqueda');
        buscador.value = '';
        cerrarSugerencias();
        mostrarAviso('tipo', '');
    }

    async function buscarNodos() {
        const texto = campo('busqueda').value;
        const caja = formulario.form.querySelector('[data-alerta-sugerencias]');
        if (normalizar(texto).length < BUSQUEDA_MIN) {
            cerrarSugerencias();
            return;
        }
        const { ok, cuerpo } = await pedir(RUTAS.buscar + '&tipo=' + encodeURIComponent(tipoElegido()) + '&q=' + encodeURIComponent(texto));
        caja.replaceChildren();
        const nodos = ok ? cuerpo.nodos : [];
        if (nodos.length === 0) {
            caja.appendChild(el('p', 'alertas-medicas__sin-resultados', 'No está en el conocimiento. Puedes guardar solo la descripción.'));
        }
        for (const nodo of nodos) {
            const opcion = el('button', 'alertas-medicas__sugerencia');
            opcion.type = 'button';
            opcion.tabIndex = -1;
            opcion.setAttribute('role', 'option');
            opcion.dataset.nodoSugerido = nodo.codigo;
            opcion.appendChild(el('strong', null, nodo.etiqueta));
            if (normalizar(nodo.coincidencia) !== normalizar(nodo.etiqueta)) {
                opcion.appendChild(el('span', null, 'por «' + nodo.coincidencia + '»'));
            }
            opcion.addEventListener('click', () => elegirNodo(nodo));
            caja.appendChild(opcion);
        }
        caja.hidden = false;
        campo('busqueda').setAttribute('aria-expanded', 'true');
        if (global.ZookiCombobox) {
            global.ZookiCombobox.reiniciar(campo('busqueda'));
        }
    }

    function abrirFormulario(idMascota, nombreMascota) {
        if (!formulario.form) {
            return;
        }
        formulario.idMascota = String(idMascota);
        formulario.form.reset();
        campo('id_mascota').value = formulario.idMascota;
        campo('descripcion').dataset.tocado = '';
        mostrarAviso('descripcion', '');
        mostrarAviso('tipo', '');
        formulario.form.querySelector('[data-alerta-resultado]').textContent = '';
        const subtitulo = formulario.modal.querySelector('[data-alerta-mascota]');
        if (subtitulo) {
            subtitulo.textContent = nombreMascota ? 'Mascota: ' + nombreMascota : '';
        }
        actualizarTipo();
        validarCampoDescripcion();
        formulario.modal.classList.add('is-open');
        formulario.modal.setAttribute('aria-hidden', 'false');
        const primero = formulario.form.querySelector('[data-alerta-tipo]');
        if (primero && typeof primero.focus === 'function') {
            primero.focus();
        }
    }

    function cerrarFormulario() {
        formulario.modal.classList.remove('is-open');
        formulario.modal.setAttribute('aria-hidden', 'true');
    }

    async function enviar(evento) {
        evento.preventDefault();
        const area = campo('descripcion');
        area.dataset.tocado = '1';
        const errorDescripcion = validarCampoDescripcion();
        const errorTipo = tipoElegido() === '' ? 'Elige el tipo de alerta.' : '';
        mostrarAviso('tipo', errorTipo);
        if (errorDescripcion || errorTipo) {
            return;
        }
        const boton = formulario.form.querySelector('[data-alerta-enviar]');
        boton.disabled = true;
        const datos = new global.FormData(formulario.form);
        datos.set('descripcion', limpiar(area.value));
        const { ok, cuerpo } = await pedir(RUTAS.registrar, { method: 'POST', body: datos });
        boton.disabled = false;
        if (!ok) {
            const mensaje = cuerpo.message || 'No se pudo registrar la alerta.';
            if (/descripci/i.test(mensaje)) {
                mostrarAviso('descripcion', mensaje);
            } else {
                formulario.form.querySelector('[data-alerta-resultado]').textContent = mensaje;
            }
            return;
        }
        cerrarFormulario();
        repintar(formulario.idMascota, cuerpo.alertas);
        avisar(cuerpo.message || 'Alerta registrada.', 'success');
    }

    function prepararFormulario() {
        formulario.modal = doc.querySelector('[data-alerta-modal]');
        formulario.form = formulario.modal ? formulario.modal.querySelector('[data-alerta-form]') : null;
        if (!formulario.form) {
            return;
        }
        try {
            formulario.nodosPorTipo = JSON.parse(formulario.form.dataset.nodosPorTipo || '{}');
        } catch (error) {
            formulario.nodosPorTipo = {};
        }
        formulario.form.addEventListener('submit', enviar);
        for (const radio of formulario.form.querySelectorAll('[data-alerta-tipo]')) {
            radio.addEventListener('change', actualizarTipo);
        }
        const area = campo('descripcion');
        area.addEventListener('input', () => {
            area.dataset.tocado = '1';
            validarCampoDescripcion();
        });
        const buscador = campo('busqueda');
        buscador.addEventListener('input', () => {
            global.clearTimeout(formulario.temporizador);
            formulario.temporizador = global.setTimeout(buscarNodos, ESPERA_BUSQUEDA);
        });
        // M2 C8 (H6): flechas, Enter y Esc; Esc cierra la lista sin cerrar el formulario.
        if (global.ZookiCombobox) {
            global.ZookiCombobox.enlazar(buscador, formulario.form.querySelector('[data-alerta-sugerencias]'), { cerrar: cerrarSugerencias });
        }
        for (const cerrar of formulario.modal.querySelectorAll('[data-alerta-cerrar]')) {
            cerrar.addEventListener('click', cerrarFormulario);
        }
        formulario.modal.addEventListener('click', evento => {
            if (evento.target === formulario.modal) {
                cerrarFormulario();
            }
        });
        doc.addEventListener('keydown', evento => {
            if (evento.defaultPrevented) {
                return;
            }
            if (evento.key === 'Escape' && formulario.modal.classList.contains('is-open')) {
                cerrarFormulario();
            }
        });
    }

    function iniciar() {
        prepararFormulario();
        for (const contenedor of doc.querySelectorAll('[data-alertas-medicas][data-cargar]')) {
            cargar(contenedor);
        }
    }

    api.pintar = pintar;
    api.cargar = cargar;
    api.abrirFormulario = abrirFormulario;
    api.buscarNodos = buscarNodos;
    doc.addEventListener('DOMContentLoaded', iniciar);
})(typeof window !== 'undefined' ? window : globalThis);
