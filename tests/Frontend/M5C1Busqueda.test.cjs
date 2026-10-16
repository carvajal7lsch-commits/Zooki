const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

// M5 C1, hueco 2 (RE-1.5.1, RN-109): sin vínculo activo el panel de la
// búsqueda exacta solo muestra el aviso del servidor y la solicitud por correo.
// Desde C3 la búsqueda vive en propietario-mascota.js.

function elemento(tag) {
    const n = {
        tagName: String(tag).toUpperCase(),
        hijos: [],
        textContent: '',
        value: '',
        hidden: false,
        dataset: {},
        classList: { toggle() {}, add() {}, contains: () => false },
        append(...nodos) { this.hijos.push(...nodos); },
        replaceChildren(...nodos) { this.hijos = [...nodos]; this.textContent = ''; },
        addEventListener() {},
        // M2 C8: las mini-tarjetas y el carrusel ponen atributos de accesibilidad.
        setAttribute() {},
        get texto() { return [this.textContent, ...this.hijos.map(h => h.texto)].join(' '); },
    };
    return n;
}

function pintar(propietario) {
    const panel = elemento('div');
    const ayuda = elemento('small');
    const ids = { mascotasExistentes: panel, ownerSearchAyuda: ayuda, petOwnerDoc: elemento('input'), ownerSearchWrapper: elemento('div'), selectedOwnerName: elemento('span'), selectedOwnerInfo: elemento('div') };
    const contexto = {
        console,
        window: {},
        document: { addEventListener() {}, getElementById: (id) => ids[id] || null, createElement: elemento },
    };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/propietario-mascota.js'), contexto);
    contexto.window.ZookiPropietarioMascota.pintar(panel, propietario, '1000000006');
    return { panel, ayuda };
}

test('sin vínculo: solo el aviso y la solicitud, sin nombre ni mascotas', () => {
    const { panel, ayuda } = pintar({ vinculado: false, mensaje: 'Hay una cuenta con ese dato. Pide la confirmación por correo.' });

    assert.match(ayuda.textContent, /Hay una cuenta con ese dato\. Pide la confirmación por correo\./);
    assert.match(panel.texto, /Pedir confirmación por correo/);
    assert.doesNotMatch(panel.texto, /undefined|Vincular|registrar nueva/);
    assert.equal(panel.hijos.length, 1);
});

test('con vínculo: el nombre y sus mascotas para vincular', () => {
    const { panel } = pintar({
        vinculado: true,
        id_usuario: 6,
        nombre_completo: 'Fabio Dueño',
        mascotas: [{ id_mascota: 1, nombre: 'Luna', nombre_especie: 'Canino', nombre_raza: 'Sin raza definida', raza_indicada: null, url_foto: null }],
    });

    assert.match(panel.texto, /Fabio Dueño/);
    // M2 C8 (H2): el botón de la mini-tarjeta dice «Vincular» (su nombre accesible, «Vincular a Luna a la clínica»).
    assert.match(panel.texto, /Luna Canino · Sin raza definida Vincular/);
    assert.match(panel.texto, /Es otra mascota: registrar nueva/);
});
