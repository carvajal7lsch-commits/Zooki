const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

// M5 C1, hueco 2 (RE-1.5.1, RN-109): sin vínculo activo el panel de la
// búsqueda exacta solo muestra el aviso del servidor y la solicitud por correo.

function elemento(tag) {
    return {
        tag,
        hijos: [],
        textContent: '',
        value: '',
        dataset: {},
        append(...nodos) { this.hijos.push(...nodos); },
        replaceChildren() { this.hijos = []; this.textContent = ''; },
        addEventListener() {},
        get texto() { return [this.textContent, ...this.hijos.map(h => h.texto)].join(' '); },
    };
}

async function buscar(propietario) {
    const panel = elemento('div');
    const ids = { mascotasExistentes: panel, petOwnerDoc: elemento('input') };
    const document = {
        addEventListener() {},
        getElementById: (id) => ids[id] || elemento('div'),
        createElement: elemento,
    };
    const contexto = {
        document,
        window: {},
        console,
        fetch: async () => ({ json: async () => ({ success: true, propietario }) }),
    };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/medical-module.js'), contexto);
    await contexto.searchOwnersForPet('1000000006');
    return panel;
}

test('sin vínculo: solo el aviso y la solicitud, sin nombre ni mascotas', async () => {
    const panel = await buscar({ vinculado: false, mensaje: 'Hay una cuenta con ese dato. Pide la confirmación por correo.' });

    assert.match(panel.texto, /Hay una cuenta con ese dato\. Pide la confirmación por correo\./);
    assert.match(panel.texto, /Solicitar confirmación por correo/);
    assert.doesNotMatch(panel.texto, /undefined|Vincular|registrar nueva/);
    assert.equal(panel.hijos.length, 2);
});

test('con vínculo: el nombre y sus mascotas para vincular', async () => {
    const panel = await buscar({
        vinculado: true,
        id_usuario: 6,
        nombre_completo: 'Fabio Dueño',
        mascotas: [{ id_mascota: 1, nombre: 'Luna', nombre_especie: 'Canino', nombre_raza: 'Sin raza definida', raza_indicada: null, url_foto: null }],
    });

    assert.match(panel.texto, /Fabio Dueño/);
    assert.match(panel.texto, /Vincular Luna/);
    assert.match(panel.texto, /Es otra mascota: registrar nueva/);
});
