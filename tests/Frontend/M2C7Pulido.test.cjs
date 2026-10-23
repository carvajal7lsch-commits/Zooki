/**
 * M2 C7 — pulido de lo que dejó M2: «Sin peso registrado», síntomas en el
 * portal (la lista de Consultas se retiró en 2026-10-22), sin style= en medical-module.js, el acceso
 * «Vincular mascota de un cliente» y los textos «Registrar consulta» y «Sin cita».
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { crearDocumento, colgar } = require('./apoyo/dom-datos.cjs');

const RAIZ = path.join(__dirname, '../..');
const leer = archivo => fs.readFileSync(path.join(RAIZ, archivo), 'utf8');

/** medical-module.js con el DOM mínimo de las pruebas. */
function medical(fetch = async () => ({ json: async () => [] })) {
    const documento = crearDocumento();
    const llamadas = { modales: [] };
    const contexto = { console, window: {}, document: documento, fetch, openModal: id => llamadas.modales.push(id) };
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/medical-module.js'), contexto);
    // openModal es de medical-module; se observa sin cambiar lo demás.
    contexto.openModal = id => llamadas.modales.push(id);
    return { contexto, documento, llamadas };
}

/** portal.js con el DOM mínimo de M5C2Portal (escapeHtml viene de avisos.js). */
function portal() {
    const document = {
        addEventListener() {},
        getElementById: () => null,
        querySelectorAll: () => [],
        querySelector: () => null,
        createElement: () => {
            let texto = '';
            return {
                set textContent(valor) { texto = valor; },
                get innerHTML() { return texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); },
            };
        },
    };
    const contexto = { document, window: { addEventListener() {}, scrollTo() {} }, location: { hash: '' }, history: { state: null, pushState() {}, replaceState() {} }, console };
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/avisos.js'), contexto);
    vm.runInContext(leer('public/js/portal.js'), contexto);
    return contexto;
}

test('1. el historial dice «Sin peso registrado» para cualquier peso NULL, en la clínica y en el portal', () => {
    const { contexto } = medical();
    assert.equal(contexto.textoPeso(null), 'Sin peso registrado');
    assert.equal(contexto.textoPeso(''), 'Sin peso registrado');
    assert.equal(contexto.textoPeso('8.5'), '8.5 Kg');
    const p = portal();
    assert.equal(p.textoPesoConsulta(null), 'Sin peso registrado');
    assert.equal(p.textoPesoConsulta('8.5'), '8.5 kg');
    assert.match(leer('public/js/portal.js'), /detClinicaPeso'\)\.textContent = textoPesoConsulta\(consulta\.peso\)/);
    assert.match(leer('views/vet/atencion.php'), /\$peso === '' \? 'Sin peso registrado'/);
    assert.match(leer('views/portal/imprimir_historial.php'), /'Sin peso registrado'/);
    // La lista de Consultas (y su detalle) se retiró por decisión del dueño (2026-10-22).
    // La casilla del formulario no cambia.
    assert.match(leer('views/vet/modal_consulta.php'), /No se pudo pesar/);
    assert.match(leer('views/vet/atencion.php'), /<span>No se pudo pesar<\/span>/);
});

test('2. los síntomas salen en el historial del portal y su imprimible', () => {
    const p = portal();
    assert.equal(p.lineaSintomas([]), '');
    assert.equal(p.lineaSintomas(undefined), '');
    assert.equal(p.lineaSintomas([{ codigo: 'sintoma.vomito', etiqueta: 'Vómito' }, { codigo: 'x', etiqueta: '<b>Tos</b>' }]), '<p><strong>Síntomas:</strong> Vómito, &lt;b&gt;Tos&lt;/b&gt;</p>');
    assert.match(leer('public/js/portal.js'), /\$\{lineaSintomas\(h\.sintomas\)\}/);
    assert.match(leer('views/portal/imprimir_historial.php'), /<strong>Síntomas:<\/strong>/);
});

test('3. el imprimible del portal tiene las alergias y alertas activas con su clínica', () => {
    const vista = leer('views/portal/imprimir_historial.php');
    assert.ok(vista.indexOf('Alergias y alertas activas') < vista.indexOf('Historial de Consultas'), 'Arriba, antes de las consultas');
    assert.match(vista, /MarcaClinica::html\(MarcaClinica::delRegistro\(\$alerta\)\)/);
    const marca = leer('models/MarcaClinica.php');
    assert.match(marca, /'nombre' => \$fila\['clinica_nombre'\]/);
    assert.match(marca, /htmlspecialchars\(\(string\) \$v, ENT_QUOTES/);
    assert.match(vista, /Que no haya registro no significa que no tenga/);
    assert.match(leer('controllers/PortalController.php'), /\$alertas = \$this->historia->alertas\(\$idMascota\);/);
});

test('4. el modal tiene frecuencia respiratoria y observaciones con los límites y avisos del servidor', () => {
    const modal = leer('views/vet/modal_consulta.php');
    assert.match(modal, /name="frecuencia_respiratoria" data-consulta-campo="frecuencia_respiratoria" data-aviso-rango="<\?= \$avisoConsulta\('frecuencia_respiratoria'\) \?>"/);
    // M2 C8 (H8): los límites y la restricción de entrada salen de ValidadorClinico::atributosSigno (FR_MIN y FR_MAX).
    assert.match(modal, /<\?= ValidadorClinico::atributosSigno\('frecuencia_respiratoria'\) \?> name="frecuencia_respiratoria"/);
    assert.match(modal, /name="observaciones" rows="2" maxlength="<\?= ValidadorClinico::TEXTO_MAX \?>"/);
    assert.doesNotMatch(modal, /\son[a-z]+=|style=/);
});

test('5. medical-module.js ya no tiene style= ni eventos en línea en su HTML', () => {
    const codigo = leer('public/js/medical-module.js');
    assert.doesNotMatch(codigo, /style=/);
    assert.doesNotMatch(codigo, /onclick="|onmouseover="|onmouseout="|onerror="/);
    assert.match(codigo, /class="dossier-sin-mascotas"/);
    assert.match(codigo, /pet-dossier-card-hc--numero/);
    assert.match(codigo, /<div class="pie">/);
    assert.match(codigo, /css\/imprimir-historia-personal.css/);
    assert.match(leer('public/css/imprimir-historia-personal.css'), /\.pie \{\s+margin-top: 30px;\s+font-size: 0\.8em;\s+text-align: center;\s+color: #999;\s+\}/, 'La impresión conserva su pie');

    // Los mismos valores que tenían los style=, ahora en clases.
    const pacientes = leer('public/css/pacientes.css');
    assert.match(pacientes, /\.dossier-sin-mascotas \{\s+text-align: center;\s+padding: 3rem;\s+color: var\(--text-muted\);\s+width: 100%;/);
    assert.match(pacientes, /\.pet-dossier-card-hc\.pet-dossier-card-hc--numero \{\s+color: var\(--primary\);/);
    const identidad = leer('public/css/identidad.css');
    assert.match(identidad, /--z-border: #E2E8F0;/);
    assert.match(identidad, /--z-bg-suave: #F8FAFC;/);
    assert.match(identidad, /--z-border-fuerte: #CBD5E1;/);

    // Decisión del dueño (2026-10-22): el buscador modal de «Atención sin cita» se retiró con
    // Consultas; el acceso del Inicio lleva a Agenda → Llegadas (HU-2.2).
    const { contexto } = medical();
    assert.equal(typeof contexto.openNewConsultationFlow, 'undefined');
    assert.doesNotMatch(leer('public/css/consulta-clinica.css'), /paciente-resultado/);
    assert.match(leer('views/vet/area.php'), /href="index\.php\?action=vet_agenda#llegadas"/);
});

test('6. «Vincular mascota de un cliente» abre la búsqueda del propietario con el mismo flujo', () => {
    const vista = leer('views/vet/pacientes.php');
    const acceso = vista.indexOf('data-vincular-mascota-cliente');
    assert.ok(acceso > 0 && acceso < vista.indexOf('Nuevo registro'), 'A la vista, junto a «Nuevo registro»');
    assert.match(vista, /<span>Vincular mascota<\/span>/);
    assert.match(vista, /title="Vincular mascota de un cliente"/);

    const documento = crearDocumento();
    for (const id of ['petOwnerDoc', 'ownerSearchWrapper', 'selectedOwnerInfo', 'mascotasExistentes', 'ownerSearchAyuda']) colgar(documento.raiz, 'div', {}, { id });
    const campo = colgar(documento.raiz, 'input', {}, { id: 'ownerSearchInput', value: 'algo de antes' });
    const abiertos = [];
    const contexto = { console, window: {}, document: documento, abrirModalRegistro: tipo => abiertos.push(tipo), setTimeout, clearTimeout };
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/propietario-mascota.js'), contexto);
    const api = contexto.window.ZookiPropietarioMascota;

    api.abrirVincular();
    assert.deepEqual(abiertos, ['mascota'], 'El mismo registro de mascota, en su pestaña');
    assert.equal(campo.value, '');
    assert.ok(campo.enfocado, 'Directo en la búsqueda del propietario');
    assert.equal(documento.getElementById('ownerSearchAyuda').textContent, api.AYUDA_VINCULAR);

    // Sin vínculo con la clínica: una línea con qué hacer y «Pedir confirmación por correo» (mismo flujo de M5).
    const mensaje = leer('models/PropietarioClinica.php').match(/MENSAJE_SIN_VINCULO = '([^']+)'/)[1];
    assert.match(mensaje, /pide la confirmación por correo o que se vincule desde su portal\.$/);
    const panel = documento.getElementById('mascotasExistentes');
    api.pintar(panel, { vinculado: false, mensaje }, 'fabio@zooki.test');
    assert.equal(documento.getElementById('ownerSearchAyuda').textContent, mensaje);
    assert.equal(panel.hijos[0].textContent, 'Pedir confirmación por correo');
    assert.equal(panel.hijos[0].dataset.pedirConfirmacion, 'fabio@zooki.test');

    const codigo = leer('public/js/propietario-mascota.js');
    assert.match(codigo, /control\.dataset\.vincularMascotaCliente !== undefined\) abrirVincular\(\)/);
    const rutas = [...codigo.matchAll(/index\.php\?action=([a-z_]+)/g)].map(r => r[1]).sort();
    assert.deepEqual([...new Set(rutas)], ['buscar_propietario_exacto_ajax', 'buscar_propietario_nombre_ajax', 'reenviar_activacion_propietario_ajax', 'solicitar_vinculo_propietario_ajax', 'vincular_mascota_ajax'], 'Sin rutas nuevas');
});

test('7. la línea de tiempo vacía dice «Registrar consulta» y la consulta sin cita se distingue', () => {
    const codigo = leer('public/js/medical-module.js');
    assert.match(codigo, /<\/i> Registrar consulta<\/button>/);
    assert.doesNotMatch(codigo, /Registrar primera consulta/);
    assert.match(codigo, /no hace falta una cita \(HU-2\.2\)/);

    const { contexto } = medical();
    assert.match(contexto.etiquetaSinCita({ id_cita: null }), /consultation-sin-cita-badge.*Sin cita/);
    assert.equal(contexto.etiquetaSinCita({ id_cita: 12 }), '');
    assert.match(contexto.filaHistorialCompacto({ id_cita: null }, 'consulta'), /Sin cita/);
    assert.doesNotMatch(contexto.filaHistorialCompacto({ id_cita: 12 }, 'consulta'), /Sin cita/);
    assert.match(leer('views/vet/atencion.php'), /empty\(\$con\['id_cita'\]\).*Sin cita/, 'Historial de Atención');
});
