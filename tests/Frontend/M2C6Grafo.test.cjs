/**
 * M2 C6, HU-2.12 — Mapa del conocimiento: funciones puras de grafo-conocimiento.js y la
 * pantalla con un DOM mínimo (conjunto vacío, respaldo a lista y mapa 3D con filtros).
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { crearNodo, textoNodo } = require('./apoyo/dom-datos.cjs');

const codigo = fs.readFileSync(path.join(__dirname, '../../public/js/grafo-conocimiento.js'), 'utf8');

const FUENTE = { entidad: 'MSD', titulo: 'Página', url: 'https://www.msdvetmanual.com/x', localizador: 'Apartado', fundamento: 'clinico', fecha_consulta: '2026-10-14' };

function nodo(codigoNodo, tipo, extra = {}) {
    return { codigo: codigoNodo, tipo, etiqueta: codigoNodo, nivel_triage: null, alarma_universal: false, moderado: false, metadatos: {}, especies: [], sinonimos: [], reglas: [], fuentes: [FUENTE], estado_validacion: 'pendiente', ...extra };
}

function arista(codigoArista, origen, destino, extra = {}) {
    return { codigo: codigoArista, origen, destino, tipo_relacion: 'sugiere', signo: 'positivo', peso: 0.6, efecto: 'evidencia', condiciones: null, fuentes: [FUENTE], estado_validacion: 'pendiente', ...extra };
}

function datosDePrueba() {
    const nodes = [
        nodo('especie.canino', 'especie', { etiqueta: 'Perro', especies: ['especie.canino'], metadatos: { cobertura: { diagnosticos: 'parcial', prescripcion: 'parcial', triage: 'parcial' } } }),
        nodo('especie.felino', 'especie', { etiqueta: 'Gato', especies: ['especie.felino'] }),
        nodo('raza.gran_danes', 'raza', { etiqueta: 'Gran danés', especies: ['especie.canino'], metadatos: { tamanio: 'gigante' } }),
        nodo('sintoma.vomito', 'sintoma', { etiqueta: 'Vómito', nivel_triage: 'amarillo', moderado: true, sinonimos: ['bomita', 'trasboca'], reglas: ['triage.digestivo'] }),
        nodo('sintoma.vomito_sangre', 'sintoma', { etiqueta: 'Vómito con sangre', sinonimos: ['bomita sangre'] }),
        nodo('sintoma.colapso', 'sintoma', { etiqueta: 'Colapso, desmayo o no responde', nivel_triage: 'rojo', alarma_universal: true }),
        nodo('diagnostico.parvovirosis', 'diagnostico', { etiqueta: 'Parvovirosis canina' }),
        nodo('diagnostico.panleucopenia', 'diagnostico', { etiqueta: 'Panleucopenia felina' }),
        nodo('sustancia.lilium', 'sustancia', { etiqueta: 'Lirio verdadero (Lilium)' }),
        nodo('farmaco.meloxicam', 'farmaco', { etiqueta: 'Meloxicam' }),
        nodo('farmaco.prednisona', 'farmaco', { etiqueta: 'Prednisona' }),
    ];
    const edges = [
        arista('a.parvo', 'sintoma.vomito', 'diagnostico.parvovirosis', { condiciones: { predicado: 'especie', codigo: 'especie.canino' } }),
        arista('a.panleuco', 'sintoma.vomito', 'diagnostico.panleucopenia', { condiciones: { predicado: 'especie', codigo: 'especie.felino' }, peso: 0.2 }),
        arista('a.lirio', 'especie.felino', 'sustancia.lilium', { tipo_relacion: 'toxico_para', signo: 'negativo', peso: 1, efecto: 'bloqueo' }),
        arista('a.aine', 'farmaco.meloxicam', 'farmaco.prednisona', { tipo_relacion: 'interactua_con', signo: 'negativo', peso: 1, efecto: 'advertencia' }),
    ];
    const reglas = [{
        codigo: 'triage.digestivo', motor: 'triage', nodos: ['sintoma.vomito', 'especie.canino'], fuentes: [FUENTE], estado_validacion: 'pendiente',
        condiciones: { todos: [{ predicado: 'especie', codigo: 'especie.canino' }, { alguno: [{ predicado: 'sintoma_marcado', codigo: 'sintoma.vomito' }, { predicado: 'edad_meses', maximo: 6, incluye_maximo: false }] }] },
        resultado: { nivel: 'naranja', revision: true },
    }];
    return {
        vacio: false,
        version: { hash: 'a'.repeat(64), corta: 'a'.repeat(12) },
        limitaciones: 'Todo pendiente de validación veterinaria.',
        conteos: { nodos: nodes.length, aristas: edges.length, reglas: 1, sinonimos: 3, por_tipo: { especie: 2, raza: 1, sintoma: 3, diagnostico: 2, sustancia: 1, farmaco: 2 } },
        pendientes: 16,
        cobertura: [
            { codigo: 'especie.canino', etiqueta: 'Perro', diagnosticos: 'parcial', prescripcion: 'parcial', triage: 'parcial' },
            { codigo: 'especie.felino', etiqueta: 'Gato', diagnosticos: 'sin_cobertura', prescripcion: 'sin_cobertura', triage: 'sin_cobertura' },
        ],
        nodes, edges, reglas,
    };
}

function cargarApi() {
    const contexto = { window: {} };
    vm.runInNewContext(codigo, contexto);
    return contexto.window.ZookiGrafo;
}

test('C6: cada tipo tiene color y forma propios y la alarma universal se distingue (RE-2.12.2)', () => {
    const api = cargarApi();
    const tipos = ['sintoma', 'diagnostico', 'farmaco', 'sustancia', 'especie', 'raza', 'condicion'];
    const estilos = tipos.map(tipo => api.estiloNodo({ tipo }));
    assert.equal(new Set(estilos.map(e => e.color)).size, tipos.length);
    assert.equal(new Set(estilos.map(e => e.forma)).size, tipos.length);
    const normal = api.estiloNodo({ tipo: 'sintoma' });
    const alarma = api.estiloNodo({ tipo: 'sintoma', alarma_universal: true });
    assert.equal(alarma.color, normal.color);
    assert.notEqual(alarma.borde, normal.borde);
    assert.ok(alarma.grosorBorde > normal.grosorBorde);
});

test('C6: el grosor crece con el peso dentro de un rango y bloqueo y advertencia se diferencian', () => {
    const api = cargarApi();
    assert.equal(api.grosorArista(0), 1);
    assert.equal(api.grosorArista(1), 6);
    assert.ok(api.grosorArista(0.8) > api.grosorArista(0.3));
    assert.equal(api.grosorArista(7), 6);
    assert.equal(api.grosorArista('no'), 1);
    const positivo = api.estiloArista({ efecto: 'evidencia', signo: 'positivo', peso: 0.5 });
    const negativo = api.estiloArista({ efecto: 'evidencia', signo: 'negativo', peso: 0.5 });
    const bloqueo = api.estiloArista({ efecto: 'bloqueo', signo: 'negativo', peso: 1 });
    const advertencia = api.estiloArista({ efecto: 'advertencia', signo: 'negativo', peso: 1 });
    assert.notEqual(positivo.color, negativo.color);
    assert.equal(bloqueo.flecha, 'tee');
    assert.equal(advertencia.linea, 'dashed');
    assert.notEqual(bloqueo.color, advertencia.color);
    assert.equal(positivo.flecha, 'triangle');
});

test('C6: la búsqueda ignora tildes y mayúsculas y usa sinónimos: «bomita» encuentra vómito (RE-2.12.3)', () => {
    const api = cargarApi();
    const { nodes } = datosDePrueba();
    assert.equal(api.normalizar('  VÓMITO   con  Sangre '), 'vomito con sangre');
    assert.equal(api.normalizar('Ñandú'), 'nandu');
    const bomita = api.buscar(nodes, 'bomita');
    assert.equal(bomita[0].codigo, 'sintoma.vomito', 'La coincidencia exacta va primero');
    assert.equal(bomita[0].coincidencia, 'bomita');
    assert.ok(bomita.some(r => r.codigo === 'sintoma.vomito_sangre'));
    assert.equal(api.buscar(nodes, 'VOMITO')[0].codigo, 'sintoma.vomito');
    assert.equal(api.buscar(nodes, 'vómito con')[0].codigo, 'sintoma.vomito_sangre');
    assert.equal(api.buscar(nodes, 'GRAN DANES')[0].codigo, 'raza.gran_danes');
    assert.equal(api.buscar(nodes, 'v').length, 0, 'Una letra no busca');
    assert.equal(api.buscar(nodes, 'zzz').length, 0);
});

test('C6: filtrar por especie conserva las alarmas universales y lo universal, y oculta lo de otra especie', () => {
    const api = cargarApi();
    const datos = datosDePrueba();
    const gato = api.filtrar(datos, { especie: 'especie.felino' });
    assert.ok(gato.nodos.has('sintoma.colapso'), 'Alarma universal');
    assert.ok(gato.nodos.has('farmaco.meloxicam'), 'Interacción sin especie: universal');
    assert.ok(gato.aristas.has('a.aine'));
    assert.ok(gato.nodos.has('sintoma.vomito'));
    assert.ok(gato.aristas.has('a.panleuco'));
    assert.ok(gato.aristas.has('a.lirio'));
    assert.ok(!gato.aristas.has('a.parvo'), 'Arista limitada a perros');
    assert.ok(!gato.nodos.has('diagnostico.parvovirosis'), 'Solo tenía relaciones caninas');
    assert.ok(!gato.nodos.has('raza.gran_danes'));
    assert.ok(!gato.nodos.has('especie.canino'));
    const perro = api.filtrar(datos, { especie: 'especie.canino' });
    assert.ok(perro.nodos.has('raza.gran_danes'));
    assert.ok(perro.nodos.has('sintoma.colapso'));
    assert.ok(!perro.nodos.has('sustancia.lilium'), 'Su única relación es felina');
    const todo = api.filtrar(datos, {});
    assert.equal(todo.nodos.size, datos.nodes.length);
    assert.equal(todo.aristas.size, datos.edges.length);
    const soloSintomas = api.filtrar(datos, { tipos: ['sintoma'] });
    assert.deepEqual([...soloSintomas.nodos].sort(), ['sintoma.colapso', 'sintoma.vomito', 'sintoma.vomito_sangre']);
    assert.equal(soloSintomas.aristas.size, 0, 'Sin el otro extremo no hay arista');
    assert.deepEqual([...api.vecinos(datos, 'sintoma.vomito')].sort(), ['diagnostico.panleucopenia', 'diagnostico.parvovirosis', 'sintoma.vomito']);
});

test('C6: condiciones y resultados legibles, sin JSON (RE-2.12.4)', () => {
    const api = cargarApi();
    const datos = datosDePrueba();
    const etiquetas = Object.fromEntries(datos.nodes.map(n => [n.codigo, n.etiqueta]));
    assert.equal(api.condicionLegible(datos.reglas[0].condiciones, etiquetas), 'especie «Perro» y (marca «Vómito» o edad < 6 meses)');
    assert.equal(api.condicionLegible({ predicado: 'inicio_episodio', codigo: 'sintoma.vomito', minimo: 1440, incluye_minimo: true }, etiquetas), 'inicio de «Vómito» hace ≥ 24 h');
    assert.equal(api.condicionLegible({ predicado: 'dias_desde_celo', minimo: 28, incluye_minimo: true, maximo: 42, incluye_maximo: true }), 'último celo hace ≥ 28 días y ≤ 42 días');
    assert.equal(api.condicionLegible({ predicado: 'raza', atributo: 'braquicefalo', valor: false }), 'raza no braquicéfala');
    assert.equal(api.condicionLegible({ predicado: 'raza', atributo: 'tamanio', valor: 'pequeno' }), 'raza de tamaño pequeño');
    assert.equal(api.condicionLegible(null), 'Sin condiciones');
    assert.equal(api.resultadoLegible({ nivel: 'naranja', revision: true }), 'nivel naranja, revisión del personal');
    assert.equal(api.resultadoLegible({ diagnostico: 'diagnostico.parvovirosis', peso: 0.3 }, etiquetas), 'suma 0.3 a «Parvovirosis canina»');
    assert.equal(api.coberturaLegible({ diagnosticos: 'parcial', prescripcion: 'parcial', triage: 'parcial' }), 'Parcial');
    assert.ok(api.enlaceSeguro('https://www.aspca.org/x'));
    assert.ok(!api.enlaceSeguro('javascript:alert(1)'));
    assert.ok(!api.enlaceSeguro('http://inseguro.test'));
});

// ── Pantalla con un DOM mínimo ────────────────────────────────────────────

function pagina({ respuesta, ForceGraph3D, webgl = true, reducido = false, animaciones = false }) {
    const documento = { escuchas: {} };
    documento.createElement = etiqueta => {
        const elemento = crearNodo(etiqueta, documento);
        if (etiqueta === 'canvas') elemento.getContext = () => webgl ? {} : null;
        return elemento;
    };
    documento.createTextNode = textoNodo;
    documento.addEventListener = (tipo, fn) => { (documento.escuchas[tipo] = documento.escuchas[tipo] || []).push(fn); };
    const raiz = crearNodo('main', documento);
    raiz.dataset.grafo = '';
    raiz.dataset.url = 'index.php?action=obtener_grafo_conocimiento_ajax';
    const con = (etiqueta, dataset, padre = raiz, extra = {}) => {
        const n = crearNodo(etiqueta, documento);
        Object.assign(n.dataset, dataset);
        Object.assign(n, extra);
        padre.appendChild(n);
        return n;
    };
    const filtros = con('form', { grafoFiltros: '' }, raiz, { hidden: true });
    con('input', { grafoBuscar: '' }, filtros);
    con('div', { grafoSugerencias: '' }, filtros, { hidden: true });
    con('select', { grafoEspecie: '' }, filtros);
    const tipos = con('fieldset', { grafoTipos: '' }, filtros);
    con('div', { grafoTiposOpciones: '' }, tipos);
    con('button', { vistaBoton: 'mapa' });
    con('button', { vistaBoton: 'lista' });
    con('button', { vistaBoton: 'plano' });
    con('button', { grafoGirar: '' });
    con('button', { grafoTodo: '' });
    con('div', { grafoFoco: '' }, raiz, { hidden:true });
    con('span', { grafoFocoTexto: '' });
    con('section', { grafoFranja: '' }, raiz, { hidden: true });
    con('div', { grafoEstado: '' });
    con('p', { grafoAvisoCdn: '' }, raiz, { hidden: true });
    const area = con('section', { grafoArea: '' }, raiz, { hidden: true });
    con('p', { grafoVisibles: '' }, area);
    con('div', { vistaGrafo: 'mapa', grafoMapa: '' }, area);
    con('div', { vistaGrafo: 'lista' }, area, { hidden: true });
    const detalle = con('aside', { grafoDetalle: '' }, area, { hidden: true });
    con('p', { grafoDetalleSubtitulo: '' }, detalle);
    con('h3', { grafoDetalleTitulo: '' }, detalle);
    con('button', { grafoCerrarDetalle: '' }, detalle);
    con('div', { grafoDetalleCuerpo: '' }, detalle);
    con('section', { grafoLeyenda: '' });
    documento.querySelector = selector => (selector === '[data-grafo]' ? raiz : raiz.querySelector(selector));
    const peticiones = [];
    const ventana = {};
    if (ForceGraph3D) ventana.ForceGraph3D = ForceGraph3D;
    ventana.matchMedia = () => ({ matches: reducido });
    const cuadros = [];
    if (animaciones) ventana.requestAnimationFrame = fn => cuadros.push(fn);
    const contexto = {
        window: ventana,
        fetch: async (url, opciones) => {
            peticiones.push([url, opciones]);
            return { ok: true, json: async () => respuesta };
        },
    };
    ventana.document = documento;
    vm.runInNewContext(codigo, contexto);
    return { raiz, documento, peticiones, animar: () => cuadros.shift()?.(), q: selector => raiz.querySelector(selector) };
}

async function iniciar(p) {
    p.documento.escuchas.DOMContentLoaded.forEach(fn => fn());
    await new Promise(resolver => setImmediate(resolver));
}

test('C6: conocimiento vacío da un estado explícito, sin franja ni cobertura (RE-2.12.6)', async () => {
    const p = pagina({ respuesta: { success: true, datos: { vacio: true, cobertura: [], nodes: [], edges: [], reglas: [] } } });
    await iniciar(p);
    assert.equal(p.peticiones[0][0], 'index.php?action=obtener_grafo_conocimiento_ajax');
    const estado = p.q('[data-grafo-estado]');
    assert.equal(estado.hidden, false);
    assert.match(estado.textContent, /Todavía no hay conocimiento cargado/);
    assert.match(estado.textContent, /php scripts\/cargar_conocimiento\.php/);
    assert.equal(p.q('[data-grafo-franja]').hidden, true);
    assert.equal(p.q('[data-grafo-area]').hidden, true);
});

test('C6: sin biblioteca 3D, los mismos datos se ven en lista con la franja y el detalle', async () => {
    const p = pagina({ respuesta: { success: true, datos: datosDePrueba() } });
    await iniciar(p);
    assert.equal(p.q('[data-grafo-aviso-cdn]').hidden, false);
    assert.equal(p.q('[data-vista-boton="mapa"]').disabled, true);
    assert.equal(p.q('[data-vista-grafo="lista"]').hidden, false);
    assert.equal(p.q('[data-vista-grafo="mapa"]').hidden, true);
    assert.equal(p.raiz.querySelectorAll('[data-nodo]').length, datosDePrueba().nodes.length);
    const franja = p.q('[data-grafo-franja]').textContent;
    assert.match(franja, /aaaaaaaaaaaa/);
    assert.match(franja, /Pendiente de validación veterinaria/);
    assert.match(franja, /Todo pendiente de validación veterinaria\./);
    assert.match(franja, /Perro: Parcial/);
    assert.match(franja, /Gato: Sin cobertura/);
    p.q('[data-nodo="sintoma.vomito"]').click();
    const detalle = p.q('[data-grafo-detalle]');
    assert.equal(detalle.hidden, false);
    const texto = detalle.textContent;
    assert.match(texto, /Vómito/);
    assert.match(texto, /Pendiente de validación veterinaria/);
    assert.match(texto, /Reglas que lo usan \(1\)/);
    assert.match(texto, /especie «Perro» y \(marca «Vómito» o edad < 6 meses\)/);
    assert.match(texto, /bomita/);
    const enlace = detalle.todos().find(n => n.tagName === 'A');
    assert.equal(enlace.href, FUENTE.url);
    assert.equal(enlace.target, '_blank');
    assert.equal(enlace.rel, 'noopener noreferrer');
    const especie = p.q('[data-grafo-especie]');
    especie.value = 'especie.felino';
    especie.disparar('change');
    assert.equal(p.q('[data-nodo="raza.gran_danes"]').hidden, true);
    assert.equal(p.q('[data-nodo="sintoma.colapso"]').hidden, false);
});

function forceGraphFalso() {
    const valores = new Map();
    const controles = {};
    let escena;
    escena = new Proxy({}, { get: (_, clave) => {
        if (clave === 'controls') return () => controles;
        if (clave === 'd3Force') return () => ({ strength:() => {} });
        if (clave === 'graph2ScreenCoords') return () => ({ x: 80, y: 80 });
        return (...args) => {
            if (!args.length) return valores.get(clave);
            valores.set(clave, args[0]);
            if (clave === 'graphData') args[0].nodes.forEach((n,i) => { n.x=i+1; n.y=i+2; n.z=i+3; });
            return escena;
        };
    } });
    return { valores, controles, ForceGraph3D:() => () => escena };
}

test('C6 3D: colores, tipos/especie, foco/cámara, relaciones/fuentes, Ver todo y Plano/Lista', async () => {
    const g = forceGraphFalso();
    const p = pagina({ respuesta:{success:true,datos:datosDePrueba()}, ForceGraph3D:g.ForceGraph3D });
    await iniciar(p);
    assert.equal(p.q('[data-grafo-aviso-cdn]').hidden, true);
    assert.equal(g.valores.get('backgroundColor'), '#FFFFFF');
    const nodos = g.valores.get('graphData').nodes;
    const aristas = g.valores.get('graphData').links;
    const vomito = nodos.find(n => n.codigo === 'sintoma.vomito');
    const parvo = nodos.find(n => n.codigo === 'diagnostico.parvovirosis');
    const lirio = nodos.find(n => n.codigo === 'sustancia.lilium');
    assert.equal(g.valores.get('nodeColor')(vomito),'#2F6BFF');
    assert.equal(g.valores.get('nodeColor')(parvo),'#7C3AED');
    assert.equal(g.valores.get('nodeColor')(lirio),'#DB2777');
    assert.ok(g.valores.get('nodeVal')(vomito)>2);
    const especie = p.q('[data-grafo-especie]');
    especie.value = 'especie.felino';
    especie.disparar('change');
    assert.equal(g.valores.get('linkVisibility')(aristas.find(l=>l.codigo==='a.parvo')),false);
    assert.equal(g.valores.get('nodeVisibility')(nodos.find(n=>n.codigo==='raza.gran_danes')),false);
    assert.equal(g.valores.get('nodeVisibility')(nodos.find(n=>n.codigo==='sintoma.colapso')),true);
    const tipo = p.q('[data-grafo-tipo="farmaco"]');
    tipo.checked = false;
    p.q('[data-grafo-tipos]').disparar('change');
    assert.equal(g.valores.get('nodeVisibility')(nodos.find(n=>n.codigo==='farmaco.meloxicam')),false);
    const buscador = p.q('[data-grafo-buscar]');
    buscador.value = 'bomita';
    buscador.disparar('input');
    p.raiz.querySelectorAll('[data-sugerencia]')[0].click();
    assert.ok(g.valores.get('cameraPosition'));
    assert.equal(g.valores.get('nodeColor')(lirio),'rgba(148,163,184,0.18)');
    assert.equal(g.valores.get('nodeColor')(parvo),'#7C3AED');
    assert.ok(g.valores.get('linkWidth')(aristas[0])>g.valores.get('linkWidth')(aristas[2]));
    assert.equal(g.valores.get('linkDirectionalParticles')(aristas[0]),3);
    assert.match(p.q('[data-grafo-detalle]').textContent,/Sugiere/);
    assert.match(p.q('[data-grafo-detalle]').textContent,/Fuentes/);
    assert.match(p.q('[data-grafo-detalle]').textContent,/decide el veterinario/);
    p.q('[data-vista-boton="plano"]').click();
    assert.equal(g.valores.get('numDimensions'),2);
    p.q('[data-vista-boton="lista"]').click();
    assert.equal(p.q('[data-vista-grafo="lista"]').hidden,false);
    p.q('[data-grafo-todo]').click();
    assert.equal(p.q('[data-grafo-detalle]').hidden,true);
    assert.equal(g.valores.get('nodeColor')(lirio),'#DB2777');
    assert.ok(g.valores.has('zoomToFit'));
    buscador.value = 'Meloxicam';
    buscador.disparar('input');
    p.raiz.querySelectorAll('[data-sugerencia]')[0].click();
    assert.equal(tipo.checked, true, 'Buscar muestra el tipo que estaba desmarcado');
    assert.equal(g.valores.get('nodeVisibility')(nodos.find(n => n.codigo === 'farmaco.meloxicam')), true);
});

test('C6 3D: sin WebGL conserva lista, filtros y ficha; las etiquetas usan texto escapado', async () => {
    const g = forceGraphFalso();
    const datos = datosDePrueba();
    datos.nodes[0].etiqueta = '<img src=x onerror=alert(1)>';
    const p = pagina({respuesta:{success:true,datos}, ForceGraph3D:g.ForceGraph3D, webgl:false});
    await iniciar(p);
    assert.equal(p.q('[data-vista-grafo="lista"]').hidden,false);
    assert.equal(p.q('[data-grafo-aviso-cdn]').hidden,false);
    assert.equal(g.valores.has('graphData'),false);
    p.q('[data-nodo="especie.canino"]').click();
    assert.equal(p.q('[data-grafo-detalle-titulo]').textContent,datos.nodes[0].etiqueta);
    assert.equal(p.q('[data-grafo-detalle-titulo]').hijos.length,0);
});

test('C6 3D: etiquetas HTML como texto y movimiento reducido sin giro ni partículas', async () => {
    const g = forceGraphFalso();
    const datos = datosDePrueba();
    const etiqueta = '<img src=x onerror=alert(1)>';
    datos.nodes[0].etiqueta = etiqueta;
    const p = pagina({ respuesta: { success: true, datos }, ForceGraph3D: g.ForceGraph3D, reducido: true, animaciones: true });
    await iniciar(p);
    p.q('[data-grafo-mapa]').clientWidth = 600;
    p.q('[data-grafo-mapa]').clientHeight = 500;
    p.animar();
    const rotulo = p.raiz.todos().find(n => n.className?.includes('grafo-etiqueta') && n.textContent === etiqueta);
    assert.ok(rotulo);
    assert.equal(rotulo.hijos.length, 0);
    assert.equal(g.controles.autoRotate, false);
    assert.equal(g.valores.get('linkDirectionalParticles')(g.valores.get('graphData').links[0]), 0);
    p.q('[data-grafo-girar]').click();
    assert.equal(g.controles.autoRotate, false);
    g.valores.get('onNodeClick')(g.valores.get('graphData').nodes[0]);
    assert.match(p.q('[data-grafo-detalle]').textContent, /Fuentes/);
});

test('C6: si el servidor falla, estado de error con reintento y sin cobertura', async () => {
    const p = pagina({ respuesta: { success: false, message: 'Error' } });
    await iniciar(p);
    const estado = p.q('[data-grafo-estado]');
    assert.match(estado.textContent, /No se pudo leer el conocimiento/);
    assert.equal(p.q('[data-grafo-franja]').hidden, true);
    estado.todos().find(n => n.tagName === 'BUTTON').click();
    await new Promise(resolver => setImmediate(resolver));
    assert.equal(p.peticiones.length, 2);
});
