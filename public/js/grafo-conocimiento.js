/*
 * HU-2.12 — Mapa del conocimiento clínico (M2 C6), solo lectura.
 * Las funciones puras (estilos, grosor, búsqueda, filtros y textos legibles) se exponen en
 * window.ZookiGrafo para probarlas con node --test; la pantalla usa 3d-force-graph local si hay WebGL y, si no, muestra la lista de los mismos datos (RE-2.12.6).
 */
(function (global) {
    'use strict';

    // RE-2.12.2: color y forma por tipo; la leyenda se dibuja con estas mismas constantes.
    const TIPOS = {
        sintoma: { nombre: 'Síntoma', plural: 'Síntomas', color: '#2F6BFF', forma: 'ellipse' },
        diagnostico: { nombre: 'Diagnóstico', plural: 'Diagnósticos', color: '#7C3AED', forma: 'round-rectangle' },
        farmaco: { nombre: 'Fármaco', plural: 'Fármacos', color: '#0D9488', forma: 'diamond' },
        sustancia: { nombre: 'Sustancia', plural: 'Sustancias', color: '#DB2777', forma: 'triangle' },
        especie: { nombre: 'Especie', plural: 'Especies', color: '#334155', forma: 'hexagon' },
        raza: { nombre: 'Raza', plural: 'Razas', color: '#94A3B8', forma: 'round-pentagon' },
        condicion: { nombre: 'Condición', plural: 'Condiciones', color: '#0891B2', forma: 'star' },
    };
    const COLOR_ALARMA = '#dc2626';
    const COLORES_MAPA = { fondo:'#FFFFFF', marca:'#0052FF', tenue:'rgba(148,163,184,0.18)', enlace:'rgba(71,85,105,0.30)', enlaceTenue:'rgba(148,163,184,0.06)' };
    const EFECTOS = {
        evidencia: { nombre: 'Evidencia', linea: 'solid', flecha: 'triangle' },
        bloqueo: { nombre: 'Bloqueo', linea: 'solid', flecha: 'tee', color: '#b91c1c' },
        advertencia: { nombre: 'Advertencia', linea: 'dashed', flecha: 'triangle', color: '#d97706' },
    };
    const SIGNOS = {
        positivo: { nombre: 'Positivo', color: '#2563eb', linea: 'solid' },
        negativo: { nombre: 'Negativo', color: '#e11d48', linea: 'dotted' },
    };
    const RELACIONES = {
        sugiere: 'sugiere', trata: 'trata', toxico_para: 'tóxico para', alergia: 'alergia',
        interactua_con: 'interactúa con', contraindica: 'contraindica', asociado: 'asociado a',
    };
    const NIVELES = { rojo: 'Rojo', naranja: 'Naranja', amarillo: 'Amarillo', verde: 'Verde' };
    const COBERTURAS = { parcial: 'Parcial', completa: 'Completa', sin_cobertura: 'Sin cobertura' };
    const MOTORES = { diagnosticos: 'Diagnósticos', prescripcion: 'Prescripción', triage: 'Triage' };
    const FUNDAMENTOS = { clinico: 'Clínico', politica: 'Política', heuristico: 'Heurístico' };
    const GROSOR_MIN = 1;
    const GROSOR_MAX = 6;
    const BUSQUEDA_MIN = 2;
    const BUSQUEDA_MAX = 60;
    const SUGERENCIAS = 8;

    /** Mismo criterio que ArchivosConocimiento::normalizarTexto: sin tildes, minúsculas y espacios simples. */
    function normalizar(texto) {
        return String(texto ?? '').trim().toLowerCase().normalize('NFD').replace(/\p{Mn}/gu, '').replace(/\s+/g, ' ');
    }

    function estiloNodo(nodo) {
        const tipo = TIPOS[nodo.tipo] || { color: '#94a3b8', forma: 'ellipse' };
        const alarma = Boolean(nodo.alarma_universal);
        return {
            color: tipo.color,
            forma: tipo.forma,
            borde: alarma ? COLOR_ALARMA : '#ffffff',
            grosorBorde: alarma ? 4 : 1.5,
            tamano: nodo.tipo === 'especie' ? 34 : (alarma ? 30 : 24),
        };
    }

    /** El grosor crece con el peso (0 a 1), dentro de un rango legible. */
    function grosorArista(peso) {
        const valor = Number(peso);
        const acotado = Number.isFinite(valor) ? Math.min(1, Math.max(0, valor)) : 0;
        return Math.round((GROSOR_MIN + acotado * (GROSOR_MAX - GROSOR_MIN)) * 10) / 10;
    }

    /** Bloqueo y advertencia tienen su propio trazo; la evidencia se distingue por signo. */
    function estiloArista(arista) {
        const efecto = EFECTOS[arista.efecto] || EFECTOS.evidencia;
        const signo = SIGNOS[arista.signo] || SIGNOS.positivo;
        const esEvidencia = arista.efecto === 'evidencia' || !EFECTOS[arista.efecto];
        return {
            color: esEvidencia ? signo.color : efecto.color,
            linea: esEvidencia ? signo.linea : efecto.linea,
            flecha: efecto.flecha,
            grosor: grosorArista(arista.peso),
        };
    }

    function indexar(nodos) {
        const indice = {};
        for (const nodo of nodos) {
            indice[nodo.codigo] = nodo;
        }
        return indice;
    }

    /** Busca por etiqueta y sinónimos; primero lo exacto, luego lo que empieza igual y al final lo que contiene. */
    function buscar(nodos, consulta, limite = SUGERENCIAS) {
        const buscado = normalizar(consulta).slice(0, BUSQUEDA_MAX);
        if (buscado.length < BUSQUEDA_MIN) {
            return [];
        }
        const resultados = [];
        for (const nodo of nodos) {
            let mejor = null;
            const candidatos = [[nodo.etiqueta, 0], ...(nodo.sinonimos || []).map(texto => [texto, 1])];
            for (const [texto, esSinonimo] of candidatos) {
                const normal = normalizar(texto);
                let puntaje = null;
                if (normal === buscado) {
                    puntaje = 0;
                } else if (normal.startsWith(buscado)) {
                    puntaje = 2;
                } else if (normal.includes(buscado)) {
                    puntaje = 4;
                }
                if (puntaje === null) {
                    continue;
                }
                puntaje += esSinonimo;
                if (mejor === null || puntaje < mejor.puntaje) {
                    mejor = { puntaje, coincidencia: texto };
                }
            }
            if (mejor !== null) {
                resultados.push({ codigo: nodo.codigo, etiqueta: nodo.etiqueta, tipo: nodo.tipo, coincidencia: mejor.coincidencia, puntaje: mejor.puntaje });
            }
        }
        resultados.sort((a, b) => a.puntaje - b.puntaje || a.etiqueta.localeCompare(b.etiqueta, 'es'));
        return resultados.slice(0, limite);
    }

    function especiesDeCondicion(condicion) {
        if (!condicion || typeof condicion !== 'object') {
            return [];
        }
        if (Array.isArray(condicion.todos) || Array.isArray(condicion.alguno)) {
            return (condicion.todos || condicion.alguno).flatMap(especiesDeCondicion);
        }
        return condicion.predicado === 'especie' && condicion.codigo ? [condicion.codigo] : [];
    }

    /** Especies a las que se limita una arista: por sus condiciones o por un extremo de especie o raza. */
    function especiesDeArista(arista, nodosPorCodigo) {
        const origen = nodosPorCodigo[arista.origen];
        const destino = nodosPorCodigo[arista.destino];
        const especies = [
            ...especiesDeCondicion(arista.condiciones),
            ...((origen && origen.especies) || []),
            ...((destino && destino.especies) || []),
        ];
        return [...new Set(especies)];
    }

    /**
     * RE-2.12.3: filtra por especie y tipo. Lo que no está limitado a una especie es universal y se
     * conserva; una alarma universal (RN-417) se ve con cualquier especie.
     */
    function filtrar(datos, opciones = {}) {
        const especie = opciones.especie || '';
        const tipos = Array.isArray(opciones.tipos) ? new Set(opciones.tipos) : null;
        const porCodigo = indexar(datos.nodes);
        const aristaEspecie = new Map();
        const incidentes = {};
        for (const arista of datos.edges) {
            const especies = especiesDeArista(arista, porCodigo);
            aristaEspecie.set(arista.codigo, especie === '' || especies.length === 0 || especies.includes(especie));
            (incidentes[arista.origen] = incidentes[arista.origen] || []).push(arista.codigo);
            (incidentes[arista.destino] = incidentes[arista.destino] || []).push(arista.codigo);
        }
        const nodos = new Set();
        for (const nodo of datos.nodes) {
            if (tipos !== null && !tipos.has(nodo.tipo)) {
                continue;
            }
            let visible;
            if ((nodo.especies || []).length > 0) {
                visible = especie === '' || nodo.especies.includes(especie);
            } else if (especie === '' || nodo.alarma_universal) {
                visible = true;
            } else {
                const propias = incidentes[nodo.codigo] || [];
                visible = propias.length === 0 || propias.some(codigo => aristaEspecie.get(codigo));
            }
            if (visible) {
                nodos.add(nodo.codigo);
            }
        }
        const aristas = new Set();
        for (const arista of datos.edges) {
            if (aristaEspecie.get(arista.codigo) && nodos.has(arista.origen) && nodos.has(arista.destino)) {
                aristas.add(arista.codigo);
            }
        }
        return { nodos, aristas };
    }

    /** El nodo y los que comparten una arista con él, en cualquier sentido. */
    function vecinos(datos, codigo) {
        const conjunto = new Set([codigo]);
        for (const arista of datos.edges) {
            if (arista.origen === codigo) {
                conjunto.add(arista.destino);
            }
            if (arista.destino === codigo) {
                conjunto.add(arista.origen);
            }
        }
        return conjunto;
    }

    function cantidad(valor, unidad) {
        if (unidad === 'minutos' && valor >= 60 && valor % 60 === 0) {
            return (valor / 60) + ' h';
        }
        return valor + ' ' + unidad;
    }

    function intervalo(condicion, unidad) {
        const partes = [];
        if (condicion.minimo !== undefined && condicion.minimo !== null) {
            partes.push((condicion.incluye_minimo ? '≥ ' : '> ') + cantidad(condicion.minimo, unidad));
        }
        if (condicion.maximo !== undefined && condicion.maximo !== null) {
            partes.push((condicion.incluye_maximo ? '≤ ' : '< ') + cantidad(condicion.maximo, unidad));
        }
        return partes.join(' y ');
    }

    /** RE-2.12.4: las condiciones como frase, sin JSON. */
    function condicionLegible(condicion, etiquetas = {}) {
        if (!condicion || typeof condicion !== 'object') {
            return 'Sin condiciones';
        }
        const nombre = codigo => '«' + (etiquetas[codigo] || codigo) + '»';
        for (const [grupo, union] of [['todos', ' y '], ['alguno', ' o ']]) {
            if (Array.isArray(condicion[grupo])) {
                return condicion[grupo].map(hijo => {
                    const texto = condicionLegible(hijo, etiquetas);
                    return hijo && (hijo.todos || hijo.alguno) && hijo[grupo] === undefined ? '(' + texto + ')' : texto;
                }).join(union);
            }
        }
        switch (condicion.predicado) {
            case 'sintoma_marcado':
                return 'marca ' + nombre(condicion.codigo);
            case 'especie':
                return 'especie ' + nombre(condicion.codigo);
            case 'raza':
                if (condicion.atributo === 'braquicefalo') {
                    return condicion.valor ? 'raza braquicéfala' : 'raza no braquicéfala';
                }
                return 'raza de tamaño ' + String(condicion.valor).replace('pequeno', 'pequeño');
            case 'edad_meses':
                return 'edad ' + intervalo(condicion, 'meses');
            case 'sexo':
                return 'sexo ' + String(condicion.valor).toLowerCase();
            case 'esterilizado':
                return condicion.valor ? 'esterilizado' : 'sin esterilizar';
            case 'alerta_activa':
                return 'alerta activa de ' + nombre(condicion.codigo);
            case 'inicio_episodio':
                return 'inicio de ' + nombre(condicion.codigo) + ' hace ' + intervalo(condicion, 'minutos');
            case 'minutos_desde_exposicion':
                return 'exposición a ' + nombre(condicion.codigo) + ' hace ' + intervalo(condicion, 'minutos');
            case 'exposicion':
                return 'exposición a ' + nombre(condicion.codigo);
            case 'vacunacion':
                return 'vacunación ' + condicion.valor;
            case 'dias_desde_celo':
                return 'último celo hace ' + intervalo(condicion, 'días');
            default:
                return 'condición no reconocida';
        }
    }

    function resultadoLegible(resultado, etiquetas = {}) {
        const partes = [];
        if (!resultado || typeof resultado !== 'object') {
            return '';
        }
        if (resultado.nivel) {
            partes.push('nivel ' + (NIVELES[resultado.nivel] || resultado.nivel).toLowerCase());
        }
        if (resultado.revision) {
            partes.push('revisión del personal');
        }
        if (resultado.revision_inmediata) {
            partes.push('revisión inmediata');
        }
        if (resultado.contagio) {
            partes.push('aviso de posible contagio');
        }
        if (resultado.diagnostico) {
            partes.push('suma ' + resultado.peso + ' a «' + (etiquetas[resultado.diagnostico] || resultado.diagnostico) + '»');
        }
        if (resultado.efecto) {
            partes.push((EFECTOS[resultado.efecto] ? EFECTOS[resultado.efecto].nombre : resultado.efecto).toLowerCase() + (resultado.motivo ? ' por ' + resultado.motivo : ''));
        }
        return partes.join(', ');
    }

    function enlaceSeguro(url) {
        return typeof url === 'string' && /^https:\/\/[^\s]+$/.test(url);
    }

    function coberturaLegible(estado) {
        const valores = Object.keys(MOTORES).map(motor => estado[motor]);
        if (valores.every(valor => valor === valores[0])) {
            return COBERTURAS[valores[0]] || valores[0];
        }
        return Object.keys(MOTORES).map(motor => MOTORES[motor] + ': ' + (COBERTURAS[estado[motor]] || estado[motor])).join(' · ');
    }

    const api = {
        TIPOS, EFECTOS, SIGNOS, normalizar, estiloNodo, grosorArista, estiloArista, buscar, especiesDeArista,
        filtrar, vecinos, condicionLegible, resultadoLegible, enlaceSeguro, coberturaLegible,
    };
    global.ZookiGrafo = api;

    // ── Pantalla ────────────────────────────────────────────────────────────
    const doc = global.document;
    if (!doc || typeof doc.addEventListener !== 'function') {
        return;
    }

    const estado = { datos: null, porCodigo: {}, etiquetas: {}, aristas: {}, reglas: {}, visibles: null, mapa: null, nodosMapa: [], enlacesMapa: [], foco: new Set(), girando: false, reducido: false, vista: 'mapa', seleccion: null };

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

    function mostrarEstado(raiz, tipo, titulo, texto, conReintento) {
        const caja = raiz.querySelector('[data-grafo-estado]');
        caja.replaceChildren();
        caja.hidden = false;
        caja.className = 'plataforma-vacio grafo-estado grafo-estado--' + tipo;
        const iconos = { cargando: 'bi bi-hourglass-split', vacio: 'bi bi-diagram-3', error: 'bi bi-exclamation-triangle' };
        const marco = el('span', 'plataforma-vacio__icono');
        marco.appendChild(icono(iconos[tipo] || iconos.error));
        marco.setAttribute('aria-hidden', 'true');
        const cuerpo = el('div');
        cuerpo.appendChild(el('h3', null, titulo));
        for (const parrafo of [].concat(texto)) {
            cuerpo.appendChild(el('p', null, parrafo));
        }
        if (conReintento) {
            const boton = el('button', 'plataforma-boton-mini', 'Intentar de nuevo');
            boton.type = 'button';
            boton.addEventListener('click', () => cargar(raiz));
            cuerpo.appendChild(boton);
        }
        caja.append(marco, cuerpo);
    }

    async function cargar(raiz) {
        mostrarEstado(raiz, 'cargando', 'Cargando el conocimiento…', 'Un momento.');
        let respuesta;
        try {
            const peticion = await fetch(raiz.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            respuesta = await peticion.json();
            if (!peticion.ok || !respuesta.success) {
                throw new Error(respuesta.message || 'Respuesta no válida');
            }
        } catch (error) {
            mostrarEstado(raiz, 'error', 'No se pudo leer el conocimiento', 'Revisa la conexión e inténtalo de nuevo. No se muestra ninguna cobertura mientras tanto.', true);
            return;
        }
        const datos = respuesta.datos;
        if (datos.vacio) {
            // RE-2.12.6: sin conjunto no hay cobertura que mostrar.
            mostrarEstado(raiz, 'vacio', 'Todavía no hay conocimiento cargado', [
                'No hay nodos activos en la base, así que no se declara ninguna cobertura clínica.',
                'Al desplegar se carga solo. En local, ejecuta php scripts/migrar.php y luego php scripts/cargar_conocimiento.php.',
            ]);
            return;
        }
        preparar(raiz, datos);
    }

    function preparar(raiz, datos) {
        estado.datos = datos;
        estado.porCodigo = indexar(datos.nodes);
        estado.etiquetas = {};
        for (const nodo of datos.nodes) {
            estado.etiquetas[nodo.codigo] = nodo.etiqueta;
        }
        estado.aristas = {};
        for (const arista of datos.edges) {
            estado.aristas[arista.codigo] = arista;
        }
        estado.reglas = {};
        for (const regla of datos.reglas) {
            estado.reglas[regla.codigo] = regla;
        }
        raiz.querySelector('[data-grafo-estado]').hidden = true;
        pintarFranja(raiz, datos);
        pintarFiltros(raiz, datos);
        pintarLeyenda(raiz);
        pintarLista(raiz, datos);
        raiz.querySelector('[data-grafo-area]').hidden = false;
        raiz.querySelector('[data-grafo-filtros]').hidden = false;
        if (!crearMapa(raiz, datos)) {
            // RE-2.12.6: biblioteca ausente o WebGL indisponible, mismos datos en lista.
            raiz.querySelector('[data-grafo-aviso-cdn]').hidden = false;
            for (const boton of raiz.querySelectorAll('[data-vista-boton]')) {
                if (boton.dataset.vistaBoton !== 'lista') boton.disabled = true;
            }
            cambiarVista(raiz, 'lista');
        }
        aplicarFiltros(raiz);
    }

    function pintarFranja(raiz, datos) {
        const franja = raiz.querySelector('[data-grafo-franja]');
        franja.replaceChildren();
        const version = el('div', 'grafo-franja__dato');
        version.appendChild(el('span', 'grafo-franja__rotulo', 'Versión cargada'));
        const codigo = el('code', 'grafo-franja__version', datos.version ? datos.version.corta : 'sin registro de carga');
        if (datos.version) {
            codigo.title = datos.version.hash;
        }
        version.appendChild(codigo);
        const conteos = el('div', 'grafo-franja__dato');
        conteos.appendChild(el('span', 'grafo-franja__rotulo', 'Contenido'));
        conteos.appendChild(el('span', null, datos.conteos.nodos + ' nodos · ' + datos.conteos.aristas + ' relaciones · ' + datos.conteos.reglas + ' reglas · ' + datos.conteos.sinonimos + ' sinónimos'));
        const cobertura = el('div', 'grafo-franja__dato grafo-franja__cobertura');
        cobertura.appendChild(el('span', 'grafo-franja__rotulo', 'Cobertura por especie'));
        const fichas = el('div', 'card-tags-mini');
        for (const especie of datos.cobertura) {
            const ficha = el('span', 'tag-mini grafo-cobertura grafo-cobertura--' + (especie.triage === 'sin_cobertura' ? 'nula' : 'parcial'), especie.etiqueta + ': ' + coberturaLegible(especie));
            fichas.appendChild(ficha);
        }
        cobertura.appendChild(fichas);
        const validacion = el('p', 'grafo-franja__validacion');
        validacion.appendChild(icono('bi bi-shield-exclamation'));
        validacion.appendChild(el('strong', null, ' Pendiente de validación veterinaria: '));
        validacion.appendChild(doc.createTextNode(datos.pendientes + ' elementos sin validar. ' + datos.limitaciones));
        franja.append(version, conteos, cobertura, validacion);
        franja.hidden = false;
    }

    function pintarFiltros(raiz, datos) {
        const especies = raiz.querySelector('[data-grafo-especie]');
        especies.replaceChildren(el('option', null, 'Todas las especies'));
        especies.firstChild.value = '';
        for (const especie of datos.cobertura) {
            const opcion = el('option', null, especie.etiqueta);
            opcion.value = especie.codigo;
            especies.appendChild(opcion);
        }
        const tipos = raiz.querySelector('[data-grafo-tipos-opciones]');
        tipos.replaceChildren();
        for (const [clave, tipo] of Object.entries(TIPOS)) {
            const total = datos.conteos.por_tipo[clave] || 0;
            const etiqueta = el('label', 'grafo-tipo');
            const casilla = el('input');
            casilla.type = 'checkbox';
            casilla.value = clave;
            casilla.checked = true;
            casilla.dataset.grafoTipo = clave;
            const muestra = el('span', 'grafo-muestra grafo-muestra--' + tipo.forma);
            muestra.style.setProperty('--grafo-color', tipo.color);
            muestra.setAttribute('aria-hidden', 'true');
            etiqueta.append(casilla, muestra, el('span', null, tipo.plural + ' (' + total + ')'));
            tipos.appendChild(etiqueta);
        }
    }

    function pintarLeyenda(raiz) {
        const leyenda = raiz.querySelector('[data-grafo-leyenda]');
        leyenda.replaceChildren(el('h3', 'grafo-leyenda__titulo', 'Leyenda'));
        const nodos = el('ul', 'grafo-leyenda__lista');
        for (const tipo of Object.values(TIPOS)) {
            const item = el('li');
            const muestra = el('span', 'grafo-muestra grafo-muestra--' + tipo.forma);
            muestra.style.setProperty('--grafo-color', tipo.color);
            muestra.setAttribute('aria-hidden', 'true');
            item.append(muestra, el('span', null, tipo.nombre));
            nodos.appendChild(item);
        }
        const alarma = el('li');
        const muestraAlarma = el('span', 'grafo-muestra grafo-muestra--ellipse grafo-muestra--alarma');
        muestraAlarma.style.setProperty('--grafo-color', TIPOS.sintoma.color);
        muestraAlarma.setAttribute('aria-hidden', 'true');
        alarma.append(muestraAlarma, el('span', null, 'Alarma universal: punto rojo'));
        nodos.appendChild(alarma);
        const aristas = el('ul', 'grafo-leyenda__lista');
        const trazos = [
            ['Relación con foco', { color: COLORES_MAPA.marca, linea: 'solid' }],
            ['Bloqueo por toxicidad', { color: COLOR_ALARMA, linea: 'solid' }],
        ];
        for (const [texto, estilo] of trazos) {
            const item = el('li');
            const trazo = el('span', 'grafo-trazo grafo-trazo--' + estilo.linea);
            trazo.style.setProperty('--grafo-color', estilo.color);
            trazo.setAttribute('aria-hidden', 'true');
            item.append(trazo, el('span', null, texto));
            aristas.appendChild(item);
        }
        aristas.appendChild(el('li', 'grafo-leyenda__nota', 'Las flechas van del origen al destino; el grosor crece con el peso (heurístico, no es una probabilidad). Consulta signo, efecto y condiciones en la ficha de la relación.'));
        leyenda.append(nodos, aristas);
    }

    function pintarLista(raiz, datos) {
        const lista = raiz.querySelector('[data-vista-grafo="lista"]');
        lista.replaceChildren();
        for (const [clave, tipo] of Object.entries(TIPOS)) {
            const nodos = datos.nodes.filter(nodo => nodo.tipo === clave);
            if (nodos.length === 0) {
                continue;
            }
            const grupo = el('section', 'grafo-lista__grupo');
            grupo.dataset.grupoTipo = clave;
            grupo.appendChild(el('h3', 'grafo-lista__titulo', tipo.plural));
            const filas = el('div', 'grafo-lista__filas');
            for (const nodo of nodos) {
                const boton = el('button', 'grafo-lista__fila');
                boton.type = 'button';
                boton.dataset.nodo = nodo.codigo;
                const muestra = el('span', 'grafo-muestra grafo-muestra--' + tipo.forma + (nodo.alarma_universal ? ' grafo-muestra--alarma' : ''));
                muestra.style.setProperty('--grafo-color', tipo.color);
                muestra.setAttribute('aria-hidden', 'true');
                boton.append(muestra, el('span', 'grafo-lista__etiqueta', nodo.etiqueta));
                if (nodo.alarma_universal) {
                    boton.appendChild(el('span', 'tag-mini grafo-ficha grafo-ficha--alarma', 'Alarma P1'));
                } else if (nodo.nivel_triage) {
                    boton.appendChild(el('span', 'tag-mini grafo-ficha', 'Nivel ' + NIVELES[nodo.nivel_triage].toLowerCase()));
                }
                boton.addEventListener('click', () => seleccionar(raiz, nodo.codigo));
                filas.appendChild(boton);
            }
            grupo.appendChild(filas);
            lista.appendChild(grupo);
        }
    }

    function crearMapa(raiz, datos) {
        if (typeof global.ForceGraph3D !== 'function') return false;
        const prueba = doc.createElement('canvas');
        try {
            const contexto = prueba.getContext?.('webgl2') || prueba.getContext?.('webgl');
            if (!contexto) return false;
            contexto.getExtension?.('WEBGL_lose_context')?.loseContext();
        } catch (_) {
            return false;
        }
        const lienzo = raiz.querySelector('[data-grafo-mapa]');
        const capa = el('div', 'grafo-etiquetas');
        capa.setAttribute('aria-hidden', 'true');
        const etiquetas = new Map();
        const grado = new Map(datos.nodes.map(n => [n.codigo, 0]));
        for (const arista of datos.edges) {
            grado.set(arista.origen, (grado.get(arista.origen) || 0) + 1);
            grado.set(arista.destino, (grado.get(arista.destino) || 0) + 1);
        }
        estado.nodosMapa = datos.nodes.map(n => ({ ...n, id:n.codigo, grado:grado.get(n.codigo) }));
        estado.enlacesMapa = datos.edges.map(a => ({ ...a, source:a.origen, target:a.destino }));
        const movimiento = global.matchMedia?.('(prefers-reduced-motion: reduce)');
        estado.reducido = Boolean(movimiento?.matches);
        movimiento?.addEventListener?.('change', evento => {
            estado.reducido = evento.matches;
            if (estado.reducido) girar(raiz, false);
            refrescarMapa();
        });
        estado.girando = !estado.reducido;
        try {
            const g = global.ForceGraph3D({ controlType:'orbit' })(lienzo);
            estado.mapa = g;
            g.width(lienzo.clientWidth || 600);
            g.height(lienzo.clientHeight || 500);
            g.backgroundColor(COLORES_MAPA.fondo);
            g.showNavInfo(false);
            g.nodeId('id');
            g.nodeLabel(() => '');
            g.nodeVal(n => (n.tipo === 'diagnostico' ? 6 : n.tipo === 'especie' ? 7 : 2) + n.grado * 0.6);
            g.nodeRelSize(3.2);
            g.nodeResolution(18);
            g.nodeOpacity(0.95);
            g.linkOpacity(1);
            g.linkDirectionalArrowLength(3);
            g.linkDirectionalArrowRelPos(1);
            g.linkDirectionalParticleSpeed(l => 0.003 + Number(l.peso || 0.4) * 0.006);
            g.linkDirectionalParticleColor(l => l.efecto === 'bloqueo' ? COLOR_ALARMA : COLORES_MAPA.marca);
            g.onNodeClick(n => seleccionar(raiz, n.codigo));
            let nodoSobrevolado = null;
            g.onNodeHover(n => { nodoSobrevolado = n?.codigo; });
            g.onLinkClick(l => verArista(raiz, l.codigo));
            g.onBackgroundClick(() => limpiarSeleccion(raiz));
            g.graphData({ nodes:estado.nodosMapa, links:estado.enlacesMapa });
            g.d3Force('charge')?.strength(-60);
            g.cooldownTicks(100);
            let encuadrado = false;
            g.onEngineStop(() => {
                if (encuadrado) return;
                encuadrado = true;
                g.zoomToFit(estado.reducido ? 0 : 700, 35);
            });
            const controles = g.controls();
            controles.autoRotate = estado.girando;
            controles.autoRotateSpeed = 0.35;
            lienzo.appendChild(capa);
            lienzo.addEventListener('pointerdown', () => girar(raiz, false));
            function medir() {
                g.width(lienzo.clientWidth || 600);
                let alto = lienzo.clientHeight || 500;
                const detalle = raiz.querySelector('[data-grafo-detalle]');
                // La hoja móvil no debe tapar el nodo enfocado: la cámara usa la parte visible del mapa.
                if (global.innerWidth < 768 && detalle && !detalle.hidden) {
                    const descubierto = detalle.getBoundingClientRect().top - lienzo.getBoundingClientRect().top;
                    alto = Math.max(100, Math.min(alto, descubierto));
                }
                g.height(alto);
            }
            estado.ajustarMapa = medir;
            if (global.ResizeObserver) new global.ResizeObserver(medir).observe(lienzo);
            else global.addEventListener?.('resize', medir);
            function rotular() {
                if (!lienzo.isConnected && lienzo.isConnected !== undefined) return;
                for (const n of estado.nodosMapa) {
                    const visible = estado.visibles?.nodos.has(n.codigo) !== false;
                    const conNombre = estado.seleccion ? estado.foco.has(n.codigo) : ['diagnostico', 'especie'].includes(n.tipo) || nodoSobrevolado === n.codigo;
                    const rotulo = visible && (conNombre || n.alarma_universal);
                    let etiqueta = etiquetas.get(n.codigo);
                    if (rotulo && !etiqueta) {
                        etiqueta = el('span', 'grafo-etiqueta', n.etiqueta);
                        etiqueta.classList.toggle('grafo-etiqueta--alarma', Boolean(n.alarma_universal));
                        capa.appendChild(etiqueta);
                        etiquetas.set(n.codigo, etiqueta);
                    }
                    if (!etiqueta) continue;
                    etiqueta.textContent = conNombre ? n.etiqueta : '';
                    etiqueta.hidden = !rotulo || !Number.isFinite(n.x);
                    if (etiqueta.hidden) continue;
                    const p = g.graph2ScreenCoords(n.x, n.y, n.z || 0);
                    etiqueta.hidden = !Number.isFinite(p.x) || !Number.isFinite(p.y) || p.x < 0 || p.y < 0 || p.x > lienzo.clientWidth || p.y > lienzo.clientHeight;
                    etiqueta.style.transform = 'translate(-50%, 10px) translate(' + p.x + 'px,' + p.y + 'px)';
                    etiqueta.classList.toggle('grafo-etiqueta--foco', n.codigo === estado.seleccion);
                }
                global.requestAnimationFrame?.(rotular);
            }
            global.requestAnimationFrame?.(rotular);
            refrescarMapa();
            girar(raiz, estado.girando);
            return true;
        } catch (_) {
            estado.mapa?.pauseAnimation?.();
            estado.mapa = null;
            lienzo.replaceChildren();
            return false;
        }
    }

    function girar(raiz, activo) {
        estado.girando = Boolean(activo) && !estado.reducido;
        if (estado.mapa) estado.mapa.controls().autoRotate = estado.girando;
        const boton = raiz.querySelector('[data-grafo-girar]');
        boton?.setAttribute('aria-pressed', String(estado.girando));
    }

    function refrescarMapa() {
        const g = estado.mapa;
        if (!g) return;
        g.nodeColor(n => estado.seleccion && !estado.foco.has(n.codigo) ? COLORES_MAPA.tenue : estiloNodo(n).color);
        g.nodeVisibility(n => estado.visibles?.nodos.has(n.codigo) !== false);
        g.linkVisibility(l => estado.visibles?.aristas.has(l.codigo) !== false);
        g.linkColor(l => {
            if (estado.seleccion && l.origen !== estado.seleccion && l.destino !== estado.seleccion) return COLORES_MAPA.enlaceTenue;
            return l.efecto === 'bloqueo' ? COLOR_ALARMA : estado.seleccion ? COLORES_MAPA.marca : COLORES_MAPA.enlace;
        });
        g.linkWidth(l => estado.seleccion && (l.origen === estado.seleccion || l.destino === estado.seleccion) ? 0.6 + Number(l.peso || 0.5) * 2.2 : 0);
        g.linkDirectionalParticles(l => estado.reducido ? 0 : estado.seleccion && (l.origen === estado.seleccion || l.destino === estado.seleccion) ? 3 : 1);
        g.linkDirectionalParticleWidth(l => estado.seleccion && (l.origen === estado.seleccion || l.destino === estado.seleccion) ? 2.2 : 1.1);
    }

    function opcionesFiltro(raiz) {
        const tipos = [...raiz.querySelectorAll('[data-grafo-tipo]')].filter(casilla => casilla.checked).map(casilla => casilla.value);
        return { especie: raiz.querySelector('[data-grafo-especie]').value, tipos };
    }

    function aplicarFiltros(raiz) {
        if (!estado.datos) {
            return;
        }
        estado.visibles = filtrar(estado.datos, opcionesFiltro(raiz));
        refrescarMapa();
        for (const fila of raiz.querySelectorAll('[data-nodo]')) {
            fila.hidden = !estado.visibles.nodos.has(fila.dataset.nodo);
        }
        for (const grupo of raiz.querySelectorAll('[data-grupo-tipo]')) {
            grupo.hidden = ![...grupo.querySelectorAll('[data-nodo]')].some(fila => !fila.hidden);
        }
        const total = estado.visibles.nodos.size;
        raiz.querySelector('[data-grafo-visibles]').textContent = total + ' de ' + estado.datos.nodes.length + ' nodos visibles';
    }

    function cambiarVista(raiz, vista) {
        estado.vista = vista;
        const panelVisible = vista === 'lista' ? 'lista' : 'mapa';
        for (const panel of raiz.querySelectorAll('[data-vista-grafo]')) {
            panel.hidden = panel.dataset.vistaGrafo !== panelVisible;
        }
        for (const boton of raiz.querySelectorAll('[data-vista-boton]')) {
            boton.setAttribute('aria-pressed', String(boton.dataset.vistaBoton === vista));
            boton.classList.toggle('active', boton.dataset.vistaBoton === vista);
        }
        if (estado.mapa && vista !== 'lista') {
            estado.mapa.numDimensions(vista === 'plano' ? 2 : 3);
            if (vista === 'plano') girar(raiz, false);
            estado.mapa.width(raiz.querySelector('[data-grafo-mapa]').clientWidth || 600);
        }
    }

    function resaltar(codigo) {
        estado.foco = vecinos(estado.datos, codigo);
        estado.foco.add(codigo);
        refrescarMapa();
    }

    function limpiarSeleccion(raiz) {
        estado.seleccion = null;
        estado.foco = new Set();
        refrescarMapa();
        raiz.querySelector('[data-grafo-foco]')?.setAttribute('hidden', '');
        if (estado.mapa) estado.mapa.zoomToFit(estado.reducido ? 0 : 700, 35);
        for (const fila of raiz.querySelectorAll('[data-nodo].is-activo')) {
            fila.classList.remove('is-activo');
        }
        cerrarDetalle(raiz);
    }

    function seleccionar(raiz, codigo) {
        const nodo = estado.porCodigo[codigo];
        if (!nodo) {
            return;
        }
        // La búsqueda es global; su elección debe verse aunque antes se ocultara ese tipo o especie.
        if (estado.visibles && !estado.visibles.nodos.has(codigo)) {
            const tipo = raiz.querySelector('[data-grafo-tipo="' + nodo.tipo + '"]');
            if (tipo) tipo.checked = true;
            raiz.querySelector('[data-grafo-especie]').value = '';
            aplicarFiltros(raiz);
        }
        estado.seleccion = codigo;
        girar(raiz, false);
        resaltar(codigo);
        const foco = raiz.querySelector('[data-grafo-foco]');
        if (foco) foco.hidden = false;
        const textoFoco = raiz.querySelector('[data-grafo-foco-texto]');
        if (textoFoco) textoFoco.textContent = 'Enfocado: ' + nodo.etiqueta;
        if (estado.mapa) {
            const n = estado.nodosMapa.find(n => n.codigo === codigo);
            if (n && Number.isFinite(n.x)) {
                const distancia = 175;
                const radio = Math.hypot(n.x, n.y, n.z || 0) || 1;
                const k = 1 + distancia / radio;
                estado.mapa.cameraPosition({ x:n.x*k+60, y:n.y*k+80, z:(n.z||0)*k+(estado.vista==='plano'?distancia:0) }, n, estado.reducido ? 0 : 1100);
            }
        }
        for (const fila of raiz.querySelectorAll('[data-nodo]')) {
            fila.classList.toggle('is-activo', fila.dataset.nodo === codigo);
        }
        verNodo(raiz, nodo);
    }

    function abrirDetalle(raiz, titulo, subtitulo, contenido) {
        const panel = raiz.querySelector('[data-grafo-detalle]');
        const cuerpo = panel.querySelector('[data-grafo-detalle-cuerpo]');
        panel.querySelector('[data-grafo-detalle-titulo]').textContent = titulo;
        panel.querySelector('[data-grafo-detalle-subtitulo]').textContent = subtitulo;
        cuerpo.replaceChildren(...contenido);
        cuerpo.scrollTop = 0;
        panel.hidden = false;
        raiz.classList.add('grafo--con-detalle');
        global.requestAnimationFrame?.(() => estado.ajustarMapa?.());
    }

    function cerrarDetalle(raiz) {
        raiz.querySelector('[data-grafo-detalle]').hidden = true;
        raiz.classList.remove('grafo--con-detalle');
        global.requestAnimationFrame?.(() => estado.ajustarMapa?.());
    }

    function seccion(titulo, ...hijos) {
        const bloque = el('section', 'grafo-detalle__seccion');
        bloque.appendChild(el('h4', 'grafo-detalle__titulo-seccion', titulo));
        bloque.append(...hijos);
        return bloque;
    }

    function datosClave(pares) {
        const lista = el('dl', 'grafo-detalle__datos');
        for (const [clave, valor] of pares) {
            if (valor === null || valor === undefined || valor === '') {
                continue;
            }
            lista.append(el('dt', null, clave), el('dd', null, valor));
        }
        return lista;
    }

    function marcaValidacion(estadoValidacion) {
        const marca = el('p', 'grafo-detalle__validacion grafo-detalle__validacion--' + estadoValidacion);
        marca.appendChild(icono('bi bi-shield-exclamation'));
        marca.appendChild(doc.createTextNode(estadoValidacion === 'validado' ? ' Validado por un veterinario' : ' Pendiente de validación veterinaria'));
        return marca;
    }

    function listaFuentes(fuentes) {
        const lista = el('ul', 'grafo-fuentes');
        for (const fuente of fuentes) {
            const item = el('li', 'grafo-fuente');
            const cabeza = el('p', 'grafo-fuente__titulo');
            if (enlaceSeguro(fuente.url)) {
                const enlace = el('a', null, fuente.titulo || fuente.url);
                enlace.href = fuente.url;
                enlace.target = '_blank';
                enlace.rel = 'noopener noreferrer';
                cabeza.append(enlace, doc.createTextNode(' '), icono('bi bi-box-arrow-up-right'));
            } else {
                cabeza.textContent = fuente.titulo || 'Fuente sin enlace';
            }
            item.appendChild(cabeza);
            item.appendChild(el('p', 'grafo-fuente__entidad', fuente.entidad || ''));
            if (fuente.localizador) {
                item.appendChild(el('p', 'grafo-fuente__localizador', fuente.localizador));
            }
            const pie = el('p', 'grafo-fuente__pie');
            pie.appendChild(el('span', 'tag-mini grafo-ficha grafo-ficha--' + (fuente.fundamento || 'otro'), FUNDAMENTOS[fuente.fundamento] || 'Sin fundamento'));
            if (fuente.fecha_consulta) {
                pie.appendChild(el('span', 'grafo-fuente__fecha', 'Consultada: ' + fuente.fecha_consulta));
            }
            item.appendChild(pie);
            lista.appendChild(item);
        }
        return lista;
    }

    function botonNodo(raiz, codigo) {
        const boton = el('button', 'grafo-enlace', estado.etiquetas[codigo] || codigo);
        boton.type = 'button';
        boton.addEventListener('click', () => seleccionar(raiz, codigo));
        return boton;
    }

    function descripcionArista(arista) {
        const partes = [RELACIONES[arista.tipo_relacion] || arista.tipo_relacion, (EFECTOS[arista.efecto] || EFECTOS.evidencia).nombre.toLowerCase()];
        if (arista.efecto === 'evidencia') {
            partes.push('peso ' + arista.peso);
        }
        return partes.join(' · ');
    }

    function verNodo(raiz, nodo) {
        const tipo = TIPOS[nodo.tipo] || { nombre: nodo.tipo };
        const contenido = [marcaValidacion(nodo.estado_validacion)];
        const metadatos = nodo.metadatos || {};
        const pares = [
            ['Tipo', tipo.nombre],
            ['Código', nodo.codigo],
            ['Nivel de triage', nodo.nivel_triage ? NIVELES[nodo.nivel_triage] : null],
            ['Alarma universal', nodo.alarma_universal ? 'Sí, rojo en cualquier especie (RN-417)' : null],
            ['Síntoma moderado', nodo.moderado ? 'Sí, cuenta para combinaciones (P3)' : null],
            ['Tamaño', metadatos.tamanio ? String(metadatos.tamanio).replace('pequeno', 'pequeño') : null],
            ['Braquicéfalo', metadatos.braquicefalo === undefined ? null : (metadatos.braquicefalo ? 'Sí' : 'No')],
        ];
        if (metadatos.cobertura) {
            pares.push(['Cobertura', coberturaLegible({ diagnosticos: 'sin_cobertura', prescripcion: 'sin_cobertura', triage: 'sin_cobertura', ...metadatos.cobertura })]);
        }
        const secundarios = [datosClave(pares)];
        if ((nodo.sinonimos || []).length > 0) {
            const fichas = el('div', 'card-tags-mini grafo-sinonimos');
            for (const texto of nodo.sinonimos) {
                fichas.appendChild(el('span', 'tag-mini', texto));
            }
            secundarios.push(seccion('Sinónimos para el buscador (' + nodo.sinonimos.length + ')', fichas));
        }
        const relaciones = estado.datos.edges.filter(arista => arista.origen === nodo.codigo || arista.destino === nodo.codigo);
        const fichas = el('div', 'grafo-fichas');
        fichas.append(el('span', 'tag-mini', relaciones.length + ' relaciones'));
        if (nodo.alarma_universal) fichas.append(el('span', 'tag-mini grafo-alarma', 'Señal de alarma'));
        contenido.unshift(fichas);
        const grupos = new Map();
        for (const arista of relaciones) {
            const sale = arista.origen === nodo.codigo;
            const titulo = arista.tipo_relacion === 'sugiere' ? (sale ? 'Sugiere' : 'Lo sugieren')
                : arista.tipo_relacion === 'toxico_para' ? 'Es tóxico para'
                : arista.tipo_relacion === 'interactua_con' ? 'Interactúa con'
                : (RELACIONES[arista.tipo_relacion] || arista.tipo_relacion);
            if (!grupos.has(titulo)) grupos.set(titulo, el('ul', 'grafo-relaciones'));
            const item = el('li', 'grafo-relacion');
            const destino = sale ? arista.destino : arista.origen;
            const peso = el('progress', 'grafo-peso' + (arista.efecto === 'bloqueo' ? ' grafo-peso--bloqueo' : ''));
            peso.max = 1;
            peso.value = Math.min(1, Math.max(0, Number(arista.peso) || 0));
            peso.setAttribute('aria-label', 'Peso de la relación');
            const valor = el('small', 'grafo-peso-valor', Number(arista.peso || 0).toFixed(2));
            const detalle = el('button', 'grafo-relacion__detalle', descripcionArista(arista));
            detalle.type = 'button';
            detalle.addEventListener('click', () => verArista(raiz, arista.codigo));
            item.append(botonNodo(raiz, destino), peso, valor, detalle);
            grupos.get(titulo).append(item);
        }
        for (const [titulo, lista] of grupos) contenido.push(seccion(titulo, lista));
        const reglas = (nodo.reglas || []).map(codigo => estado.reglas[codigo]).filter(Boolean);
        if (reglas.length > 0) {
            const lista = el('div', 'grafo-reglas');
            for (const regla of reglas) {
                lista.appendChild(tarjetaRegla(regla));
            }
            contenido.push(seccion('Reglas que lo usan (' + reglas.length + ')', el('p', 'grafo-detalle__nota', 'Una regla combina varias condiciones; no es una arista del mapa.'), lista));
        }
        contenido.push(seccion('Fuentes', listaFuentes(nodo.fuentes || [])));
        const datosNodo = el('details', 'grafo-nodo-datos');
        datosNodo.append(el('summary', null, 'Datos del nodo y sinónimos'), ...secundarios);
        contenido.push(datosNodo);
        contenido.push(el('p', 'grafo-detalle__nota', 'Es apoyo a la decisión; decide el veterinario. Los pesos son heurísticos, no probabilidades.'));
        abrirDetalle(raiz, nodo.etiqueta, tipo.nombre, contenido);
    }

    function tarjetaRegla(regla) {
        const tarjeta = el('article', 'grafo-regla');
        const cabeza = el('p', 'grafo-regla__cabeza');
        cabeza.append(el('span', 'tag-mini grafo-ficha', MOTORES[regla.motor] || regla.motor), el('code', null, regla.codigo));
        tarjeta.append(
            cabeza,
            datosClave([
                ['Si', condicionLegible(regla.condiciones, estado.etiquetas)],
                ['Entonces', resultadoLegible(regla.resultado, estado.etiquetas)],
            ]),
            marcaValidacion(regla.estado_validacion)
        );
        const fuentes = el('details', 'grafo-regla__fuentes');
        fuentes.appendChild(el('summary', null, 'Fuentes (' + regla.fuentes.length + ')'));
        fuentes.appendChild(listaFuentes(regla.fuentes));
        tarjeta.appendChild(fuentes);
        return tarjeta;
    }

    function verArista(raiz, codigo) {
        const arista = estado.aristas[codigo];
        if (!arista) {
            return;
        }
        girar(raiz, false);
        const ruta = el('p', 'grafo-detalle__ruta');
        ruta.append(botonNodo(raiz, arista.origen), el('span', 'grafo-relacion__sentido', ' → '), botonNodo(raiz, arista.destino));
        const contenido = [
            marcaValidacion(arista.estado_validacion),
            ruta,
            datosClave([
                ['Relación', RELACIONES[arista.tipo_relacion] || arista.tipo_relacion],
                ['Signo', (SIGNOS[arista.signo] || { nombre: arista.signo }).nombre],
                ['Peso', arista.efecto === 'evidencia' ? String(arista.peso) + ' (heurístico)' : null],
                ['Efecto', (EFECTOS[arista.efecto] || { nombre: arista.efecto }).nombre],
                ['Condiciones', arista.condiciones ? condicionLegible(arista.condiciones, estado.etiquetas) : 'Ninguna: aplica siempre'],
                ['Código', arista.codigo],
            ]),
            seccion('Fuentes', listaFuentes(arista.fuentes || [])),
        ];
        abrirDetalle(raiz, (estado.etiquetas[arista.origen] || arista.origen) + ' → ' + (estado.etiquetas[arista.destino] || arista.destino), 'Relación', contenido);
    }

    function sugerir(raiz, texto) {
        const caja = raiz.querySelector('[data-grafo-sugerencias]');
        caja.replaceChildren();
        const resultados = estado.datos ? buscar(estado.datos.nodes, texto) : [];
        caja.hidden = resultados.length === 0;
        for (const resultado of resultados) {
            const opcion = el('button', 'grafo-sugerencia');
            opcion.type = 'button';
            opcion.setAttribute('role', 'option');
            opcion.dataset.sugerencia = resultado.codigo;
            const muestra = el('span', 'grafo-muestra grafo-muestra--' + TIPOS[resultado.tipo].forma);
            muestra.style.setProperty('--grafo-color', TIPOS[resultado.tipo].color);
            muestra.setAttribute('aria-hidden', 'true');
            const textos = el('span', 'grafo-sugerencia__texto');
            textos.appendChild(el('strong', null, resultado.etiqueta));
            if (resultado.coincidencia !== resultado.etiqueta) {
                textos.appendChild(el('span', null, 'por «' + resultado.coincidencia + '»'));
            }
            opcion.append(muestra, textos, el('span', 'grafo-sugerencia__tipo', TIPOS[resultado.tipo].nombre));
            opcion.addEventListener('click', () => {
                caja.hidden = true;
                seleccionar(raiz, resultado.codigo);
            });
            caja.appendChild(opcion);
        }
        return resultados;
    }

    function iniciar() {
        const raiz = doc.querySelector('[data-grafo]');
        if (!raiz) {
            return;
        }
        const buscador = raiz.querySelector('[data-grafo-buscar]');
        buscador.addEventListener('input', () => sugerir(raiz, buscador.value));
        buscador.addEventListener('keydown', evento => {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                const primero = raiz.querySelector('[data-sugerencia]');
                if (primero) {
                    primero.click();
                }
            }
            if (evento.key === 'Escape') {
                raiz.querySelector('[data-grafo-sugerencias]').hidden = true;
            }
        });
        raiz.querySelector('[data-grafo-filtros]').addEventListener('submit', evento => evento.preventDefault());
        raiz.querySelector('[data-grafo-especie]').addEventListener('change', () => aplicarFiltros(raiz));
        raiz.querySelector('[data-grafo-tipos]').addEventListener('change', () => aplicarFiltros(raiz));
        for (const boton of raiz.querySelectorAll('[data-vista-boton]')) {
            boton.addEventListener('click', () => cambiarVista(raiz, boton.dataset.vistaBoton));
        }
        raiz.querySelector('[data-grafo-cerrar-detalle]').addEventListener('click', () => limpiarSeleccion(raiz));
        doc.addEventListener('keydown', evento => {
            if (evento.key === 'Escape' && !raiz.querySelector('[data-grafo-detalle]').hidden) {
                limpiarSeleccion(raiz);
            }
        });
        raiz.querySelector('[data-grafo-todo]')?.addEventListener('click', () => limpiarSeleccion(raiz));
        raiz.querySelector('[data-grafo-girar]')?.addEventListener('click', () => girar(raiz, !estado.girando));
        raiz.querySelector('[data-grafo-pantalla]')?.addEventListener('click', () => {
            try {
                // Pantalla completa con los filtros y la ficha (pedido del dueño, 2026-10-28), no solo el mapa.
                const panel = raiz.querySelector('.grafo-composicion') || raiz.querySelector('.grafo-lienzo');
                const peticion = doc.fullscreenElement ? doc.exitFullscreen?.() : panel.requestFullscreen?.();
                peticion?.catch(() => {});
            } catch (_) {
                // Pantalla completa es opcional; rechazarla conserva la vista.
            }
        });
        doc.addEventListener('fullscreenchange', () => {
            // Al entrar o salir cambia el tamaño: se vuelve a encuadrar el nodo enfocado o todo el mapa.
            global.setTimeout(() => {
                if (estado.seleccion) seleccionar(raiz, estado.seleccion);
                else if (estado.mapa) estado.mapa.zoomToFit(estado.reducido ? 0 : 500, 35);
            }, 250);
        });
        cargar(raiz);
    }

    doc.addEventListener('DOMContentLoaded', iniciar);
})(typeof window !== 'undefined' ? window : globalThis);
