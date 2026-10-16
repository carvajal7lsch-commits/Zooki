/**
 * M2 C5a — RN-210 y A.1: síntomas del catálogo en la consulta.
 *
 * Lo comparten el modal de consulta (Pacientes, Consultas, Calendario) y
 * Atención. El buscador (buscar_sintomas_ajax, filtrado por la especie de la
 * ficha) solo sugiere: un síntoma se marca cuando el veterinario lo elige, y
 * queda como chip con su campo oculto sintomas[] y una × para quitarlo.
 * Lo que no esté en el catálogo se escribe en la anamnesis.
 */
(function () {
    'use strict';

    const RUTA_BUSCAR = 'index.php?action=buscar_sintomas_ajax';
    const BUSQUEDA_MIN = 2;
    const ESPERA_MS = 250;
    // «Otro / no sé» sirve al propietario en el triage; en la consulta el veterinario escribe la anamnesis.
    const RESERVA = 'sintoma.otro_no_se';

    function urlBusqueda(texto, idMascota) {
        const parametros = new URLSearchParams({ q: texto });
        if (idMascota) parametros.set('id_mascota', String(idMascota));
        return `${RUTA_BUSCAR}&${parametros.toString()}`;
    }

    /** Las sugerencias que se ofrecen: sin la reserva y sin lo ya marcado. */
    function filtrarSugerencias(sugerencias, marcados) {
        const yaMarcados = new Set(marcados.map(s => s.codigo));
        return (Array.isArray(sugerencias) ? sugerencias : [])
            .filter(s => s && s.codigo !== RESERVA && s.coincidencia !== 'reserva')
            .filter(s => !yaMarcados.has(s.codigo));
    }

    function elemento(etiqueta, clase, texto) {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    }

    /**
     * Prepara un bloque [data-sintomas]. opciones.idMascota() da la mascota
     * de la consulta abierta; opciones.alCambiar(lista) avisa al ranking.
     */
    function crear(raiz, opciones = {}) {
        const chips = raiz.querySelector('[data-sintomas-chips]');
        const buscador = raiz.querySelector('[data-sintomas-buscar]');
        const lista = raiz.querySelector('[data-sintomas-sugerencias]');
        const aviso = raiz.querySelector('[data-campo-aviso="sintomas"]');
        const maximo = Number(raiz.dataset.max) || 30;
        const idMascota = opciones.idMascota || (() => '');
        const alCambiar = opciones.alCambiar || (() => {});
        let marcados = [];
        let temporizador = null;
        let peticion = 0;

        function mostrarAviso(texto, error = true) {
            if (!aviso) return;
            aviso.textContent = texto;
            aviso.classList.toggle('cuenta-validacion--error', Boolean(texto) && error);
        }

        function cerrarSugerencias() {
            lista.replaceChildren();
            lista.hidden = true;
            buscador.setAttribute('aria-expanded', 'false');
        }

        function pintarChips() {
            const nodos = marcados.map(sintoma => {
                const chip = elemento('span', 'consulta-sintoma');
                chip.dataset.sintoma = sintoma.codigo;
                chip.append(elemento('span', 'consulta-sintoma__texto', sintoma.etiqueta));
                const quitar = elemento('button', 'consulta-sintoma__quitar', '×');
                quitar.type = 'button';
                quitar.setAttribute('aria-label', `Quitar ${sintoma.etiqueta}`);
                quitar.addEventListener('click', () => quitarSintoma(sintoma.codigo));
                chip.append(quitar);
                const oculto = elemento('input');
                oculto.type = 'hidden';
                oculto.name = 'sintomas[]';
                oculto.value = sintoma.codigo;
                chip.append(oculto);
                return chip;
            });
            chips.replaceChildren(...nodos);
            chips.hidden = marcados.length === 0;
            buscador.disabled = marcados.length >= maximo;
        }

        function cambiar(nuevos) {
            marcados = nuevos;
            pintarChips();
            mostrarAviso(marcados.length >= maximo ? `Marcaste el máximo de ${maximo} síntomas.` : '', false);
            alCambiar(marcados.slice());
        }

        function marcarSintoma(sintoma) {
            if (marcados.some(s => s.codigo === sintoma.codigo) || marcados.length >= maximo) return;
            cambiar([...marcados, { codigo: sintoma.codigo, etiqueta: sintoma.etiqueta }]);
            buscador.value = '';
            cerrarSugerencias();
            buscador.focus();
        }

        function quitarSintoma(codigo) {
            cambiar(marcados.filter(s => s.codigo !== codigo));
            buscador.focus();
        }

        function pintarSugerencias(sugerencias) {
            if (sugerencias.length === 0) {
                lista.replaceChildren(elemento('li', 'consulta-sintomas__vacio', 'Sin coincidencias en el catálogo: descríbelo en la anamnesis.'));
            } else {
                lista.replaceChildren(...sugerencias.map(sintoma => {
                    const item = elemento('li');
                    const boton = elemento('button', 'consulta-sintomas__opcion', sintoma.etiqueta);
                    boton.type = 'button';
                    boton.dataset.codigo = sintoma.codigo;
                    boton.setAttribute('role', 'option');
                    boton.addEventListener('click', () => marcarSintoma(sintoma));
                    item.append(boton);
                    return item;
                }));
            }
            lista.hidden = false;
            buscador.setAttribute('aria-expanded', 'true');
        }

        async function buscar(texto) {
            const numero = ++peticion;
            try {
                const respuesta = await (await fetch(urlBusqueda(texto, idMascota()))).json();
                if (numero !== peticion) return;
                if (!respuesta.success) {
                    cerrarSugerencias();
                    mostrarAviso(respuesta.message || 'No se pudo buscar el síntoma.');
                    return;
                }
                mostrarAviso('', false);
                pintarSugerencias(filtrarSugerencias(respuesta.sugerencias, marcados));
            } catch (error) {
                if (numero !== peticion) return;
                cerrarSugerencias();
                mostrarAviso('No se pudo buscar el síntoma. Revisa tu conexión.');
            }
        }

        buscador.addEventListener('input', () => {
            clearTimeout(temporizador);
            const texto = buscador.value.trim();
            if (texto.length < BUSQUEDA_MIN) {
                peticion++;
                cerrarSugerencias();
                return;
            }
            temporizador = setTimeout(() => buscar(texto), ESPERA_MS);
        });
        // Enter no envía la consulta ni marca nada: el síntoma se elige de la lista.
        buscador.addEventListener('keydown', evento => {
            if (evento.key === 'Enter') evento.preventDefault();
            if (evento.key === 'Escape') cerrarSugerencias();
        });

        return {
            marcados: () => marcados.slice(),
            poner(lista) {
                cambiar((Array.isArray(lista) ? lista : []).filter(s => s && s.codigo && s.etiqueta).slice(0, maximo));
            },
            limpiar() {
                peticion++;
                clearTimeout(temporizador);
                buscador.value = '';
                cerrarSugerencias();
                cambiar([]);
            },
            mostrarError(texto) {
                mostrarAviso(texto);
            },
            campo: buscador,
        };
    }

    window.ZookiSintomas = { RUTA_BUSCAR, BUSQUEDA_MIN, RESERVA, urlBusqueda, filtrarSugerencias, crear };
})();
