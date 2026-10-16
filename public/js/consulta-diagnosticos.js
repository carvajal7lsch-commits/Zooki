/**
 * M2 C5a — HU-2.8 (RE-2.8.1 a RE-2.8.4, RN-209, RN-202): diagnósticos
 * sugeridos en la consulta, antes del campo de diagnóstico.
 *
 * Al cambiar los síntomas (con una espera corta) pide sugerir_diagnosticos_ajax
 * y muestra hasta 5 candidatos con su cobertura, los síntomas que coinciden y
 * la fuente, sin porcentajes. «Usar este diagnóstico» copia el texto al campo
 * para que el veterinario lo edite: nunca se rellena solo y el campo sigue
 * siendo obligatorio. Los datos que ayudarían (revisión de C2) salen con
 * nombre legible; vacunación y días desde el celo tienen un campo opcional que
 * vuelve a calcular y no se guarda en la consulta.
 */
(function () {
    'use strict';

    const RUTA_SUGERIR = 'index.php?action=sugerir_diagnosticos_ajax';
    const ESPERA_MS = 400;
    const MAXIMO = 5;
    const APOYO = 'Sugerencia de apoyo · pendiente de validación veterinaria';
    const TEXTOS = {
        sinSintomas: 'Marca síntomas en «Motivo» para ver diagnósticos probables del conocimiento.',
        buscando: 'Buscando diagnósticos probables…',
        sinCoincidencias: 'Sin coincidencias en el conocimiento: escribe el diagnóstico.',
        error: 'No se pudieron calcular las sugerencias. Escribe el diagnóstico.',
    };

    /** Nombre legible de cada dato que informa el motor; los códigos internos nunca se muestran. */
    const DATOS = {
        vacunacion: { etiqueta: 'Estado de vacunación', editable: true },
        dias_desde_celo: { etiqueta: 'Días desde el último celo', editable: true },
        edad_meses: { etiqueta: 'Edad (falta la fecha de nacimiento en la ficha)', editable: false },
        sexo: { etiqueta: 'Sexo (en la ficha)', editable: false },
        esterilizado: { etiqueta: 'Si está esterilizado (en la ficha)', editable: false },
        especie: { etiqueta: 'Especie (en la ficha)', editable: false },
        raza: { etiqueta: 'Datos de la raza (en la ficha)', editable: false },
        inicio: { etiqueta: 'Desde cuándo empezaron los síntomas', editable: false },
        exposicion: { etiqueta: 'Exposición a un tóxico', editable: false },
    };

    function claveDato(campo) {
        if (typeof campo !== 'string') return null;
        if (Object.prototype.hasOwnProperty.call(DATOS, campo)) return campo;
        if (campo.startsWith('raza.')) return 'raza';
        if (campo.startsWith('sintomas.') && campo.endsWith('.minutos')) return 'inicio';
        if (campo.startsWith('exposiciones')) return 'exposicion';
        return null;
    }

    /** datos_faltantes del motor → [{clave, etiqueta, editable}] sin repetir y sin códigos. */
    function datosLegibles(faltantes) {
        const claves = [];
        for (const faltante of Array.isArray(faltantes) ? faltantes : []) {
            for (const campo of Array.isArray(faltante && faltante.campos) ? faltante.campos : []) {
                const clave = claveDato(campo);
                if (clave !== null && !claves.includes(clave)) claves.push(clave);
            }
        }
        return claves.map(clave => ({ clave, etiqueta: DATOS[clave].etiqueta, editable: DATOS[clave].editable }));
    }

    function urlSegura(url) {
        return typeof url === 'string' && /^https?:\/\//i.test(url) ? url : null;
    }

    /** La fuente que se muestra: la clínica primero; el resto se cuenta. */
    function fuentePrincipal(fuentes) {
        const lista = (Array.isArray(fuentes) ? fuentes : []).filter(f => f && (f.titulo || f.entidad));
        if (lista.length === 0) return null;
        const principal = lista.find(f => f.fundamento === 'clinico') || lista[0];
        const texto = [principal.entidad, principal.titulo].filter(Boolean).join(' — ');
        return { texto, url: urlSegura(principal.url), otras: lista.length - 1 };
    }

    /** Copia la etiqueta al campo (sin borrar lo escrito) y deja editar; RE-2.8.2. */
    function usarDiagnostico(campo, etiqueta) {
        const actual = campo.value.trim();
        const lineas = actual.split('\n').map(linea => linea.trim());
        if (!lineas.includes(etiqueta)) {
            campo.value = actual === '' ? etiqueta : `${actual}\n${etiqueta}`;
            campo.dispatchEvent(new Event('input', { bubbles: true }));
        }
        campo.focus();
    }

    function cuerpoPeticion(idMascota, sintomas, datos) {
        const cuerpo = new URLSearchParams();
        cuerpo.append('id_mascota', String(idMascota));
        sintomas.forEach(sintoma => cuerpo.append('sintomas[]', sintoma.codigo));
        Object.entries(datos).forEach(([clave, valor]) => {
            if (valor !== '') cuerpo.append(clave, valor);
        });
        return cuerpo;
    }

    function elemento(etiqueta, clase, texto) {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    }

    /**
     * Prepara un bloque [data-diagnosticos]. opciones.idMascota() da la
     * mascota; opciones.campoDiagnostico es el textarea obligatorio.
     */
    function crear(raiz, opciones = {}) {
        const estado = raiz.querySelector('[data-diagnosticos-estado]');
        const lista = raiz.querySelector('[data-diagnosticos-lista]');
        const zonaDatos = raiz.querySelector('[data-diagnosticos-datos]');
        const ficha = raiz.querySelector('[data-diagnosticos-ficha]');
        const campos = [...raiz.querySelectorAll('[data-dato-campo]')];
        const idMascota = opciones.idMascota || (() => '');
        let sintomas = [];
        let temporizador = null;
        let peticion = 0;

        function datosDeclarados() {
            const datos = {};
            campos.forEach(campo => { datos[campo.dataset.datoCampo] = String(campo.value || '').trim(); });
            return datos;
        }

        function mostrarEstado(texto) {
            estado.textContent = texto;
            estado.hidden = texto === '';
        }

        function pintarDatos(legibles) {
            const declarados = datosDeclarados();
            const pedidos = legibles.map(dato => dato.clave);
            raiz.querySelectorAll('[data-dato]').forEach(envoltura => {
                const clave = envoltura.dataset.dato;
                envoltura.hidden = !pedidos.includes(clave) && declarados[clave] === '';
            });
            const deLaFicha = legibles.filter(dato => !dato.editable);
            ficha.replaceChildren(...deLaFicha.map(dato => elemento('li', '', dato.etiqueta)));
            ficha.hidden = deLaFicha.length === 0;
            const algunCampo = [...raiz.querySelectorAll('[data-dato]')].some(envoltura => !envoltura.hidden);
            zonaDatos.hidden = !algunCampo && deLaFicha.length === 0;
        }

        function tarjeta(candidato, etiquetas) {
            const item = elemento('li', 'consulta-sugerencia');
            item.dataset.diagnostico = candidato.codigo;
            const cabecera = elemento('div', 'consulta-sugerencia__cabecera');
            cabecera.append(elemento('strong', 'consulta-sugerencia__nombre', candidato.etiqueta));
            cabecera.append(elemento('span', 'consulta-sugerencia__cobertura', candidato.cobertura && candidato.cobertura.texto ? candidato.cobertura.texto : ''));
            item.append(cabecera);
            const coinciden = (candidato.sintomas_coincidentes || []).map(codigo => etiquetas[codigo]).filter(Boolean);
            if (coinciden.length) item.append(elemento('p', 'consulta-sugerencia__sintomas', `Coinciden: ${coinciden.join(', ')}`));
            const fuente = fuentePrincipal(candidato.fuentes);
            if (fuente) {
                const parrafo = elemento('p', 'consulta-sugerencia__fuente', 'Fuente: ');
                if (fuente.url) {
                    const enlace = elemento('a', '', fuente.texto);
                    enlace.href = fuente.url;
                    enlace.target = '_blank';
                    enlace.rel = 'noopener noreferrer';
                    parrafo.append(enlace);
                } else {
                    parrafo.append(fuente.texto);
                }
                if (fuente.otras > 0) parrafo.append(` (+${fuente.otras})`);
                item.append(parrafo);
            }
            item.append(elemento('p', 'consulta-sugerencia__apoyo', APOYO));
            const usar = elemento('button', 'consulta-sugerencia__usar', 'Usar este diagnóstico');
            usar.type = 'button';
            usar.dataset.usarDiagnostico = candidato.codigo;
            usar.addEventListener('click', () => {
                if (opciones.campoDiagnostico) usarDiagnostico(opciones.campoDiagnostico, candidato.etiqueta);
            });
            item.append(usar);
            return item;
        }

        function pintar(respuesta) {
            const etiquetas = {};
            sintomas.forEach(sintoma => { etiquetas[sintoma.codigo] = sintoma.etiqueta; });
            const candidatos = (Array.isArray(respuesta.candidatos) ? respuesta.candidatos : []).slice(0, MAXIMO);
            lista.replaceChildren(...candidatos.map(candidato => tarjeta(candidato, etiquetas)));
            lista.hidden = candidatos.length === 0;
            mostrarEstado(candidatos.length === 0 ? TEXTOS.sinCoincidencias : '');
            pintarDatos(datosLegibles(respuesta.datos_faltantes));
        }

        function reiniciar() {
            peticion++;
            clearTimeout(temporizador);
            lista.replaceChildren();
            lista.hidden = true;
            mostrarEstado(TEXTOS.sinSintomas);
            pintarDatos([]);
        }

        async function calcular() {
            const numero = ++peticion;
            mostrarEstado(TEXTOS.buscando);
            try {
                const respuesta = await (await fetch(RUTA_SUGERIR, { method: 'POST', body: cuerpoPeticion(idMascota(), sintomas, datosDeclarados()) })).json();
                if (numero !== peticion) return;
                if (!respuesta.success) {
                    lista.replaceChildren();
                    lista.hidden = true;
                    mostrarEstado(respuesta.message || TEXTOS.error);
                    return;
                }
                pintar(respuesta);
            } catch (error) {
                if (numero !== peticion) return;
                lista.hidden = true;
                mostrarEstado(TEXTOS.error);
            }
        }

        function programar() {
            clearTimeout(temporizador);
            if (sintomas.length === 0) {
                reiniciar();
                return;
            }
            temporizador = setTimeout(calcular, ESPERA_MS);
        }

        campos.forEach(campo => {
            campo.addEventListener('change', programar);
            campo.addEventListener('input', programar);
        });
        reiniciar();

        return {
            actualizar(nuevos) {
                sintomas = Array.isArray(nuevos) ? nuevos.slice() : [];
                programar();
            },
            limpiar() {
                sintomas = [];
                campos.forEach(campo => { campo.value = ''; });
                reiniciar();
            },
        };
    }

    window.ZookiDiagnosticos = { RUTA_SUGERIR, MAXIMO, APOYO, TEXTOS, datosLegibles, fuentePrincipal, usarDiagnostico, cuerpoPeticion, crear };
})();
