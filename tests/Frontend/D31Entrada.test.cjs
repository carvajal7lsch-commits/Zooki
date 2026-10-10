const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const codigo = fs.readFileSync(path.join(__dirname, '../../public/js/interacciones.js'), 'utf8');

function pagina() {
    const eventos = {};
    const documento = {
        addEventListener(tipo, callback, captura) {
            eventos[tipo] ??= [];
            eventos[tipo].push({ callback, captura });
        },
    };
    const entorno = vm.createContext({ document: documento, TextEncoder, Event: class {
        constructor(tipo) {
            this.type = tipo;
        }
    } });
    vm.runInContext(codigo, entorno);
    const emitir = (tipo, evento) => eventos[tipo].forEach(({ callback }) => callback(evento));
    function campo(dataset, maximo, valor = '') {
        return {
            dataset,
            maxLength: maximo,
            value: valor,
            type: 'text',
            selectionStart: valor.length,
            selectionEnd: valor.length,
            setSelectionRange(inicio, fin) {
                this.selectionStart = inicio;
                this.selectionEnd = fin;
            },
            dispatchEvent(evento) {
                emitir(evento.type, { target: this });
            },
        };
    }
    function escribir(input, texto, pegar = false) {
        const evento = {
            target: input,
            data: texto,
            inputType: 'insertText',
            cancelable: true,
            clipboardData: { getData: () => texto },
            preventDefault() {
                this.impedido = true;
            },
        };
        emitir(pegar ? 'paste' : 'beforeinput', evento);
        if (!evento.impedido) {
            const inicio = input.selectionStart;
            const fin = input.selectionEnd;
            input.value = input.value.slice(0, inicio) + texto + input.value.slice(fin);
            input.setSelectionRange(inicio + texto.length, inicio + texto.length);
            emitir('input', { target: input });
        }
        return evento;
    }
    return { entorno, eventos, emitir, campo, escribir };
}

test('documento: las letras no entran al escribir ni al pegar y el máximo es 15', () => {
    const p = pagina();
    const campo = p.campo({ caracteres: '0-9' }, 15);
    p.escribir(campo, 'a');
    assert.equal(campo.value, '');
    p.escribir(campo, 'abc102345678901234567890', true);
    assert.equal(campo.value, '102345678901234');
    p.escribir(campo, '9');
    assert.equal(campo.value, '102345678901234');
    assert.equal(p.eventos.input[0].captura, true);
});

test('NIT: sin letras, solo un guion después del número y un dígito después', () => {
    const p = pagina();
    const campo = p.campo({ caracteres: '0-9\\-', formato: 'nit' }, 25);
    p.escribir(campo, 'a');
    assert.equal(campo.value, '');
    p.escribir(campo, '900123456');
    p.escribir(campo, '-');
    p.escribir(campo, '-');
    assert.equal(campo.value, '900123456-');
    p.escribir(campo, '8');
    p.escribir(campo, '9');
    assert.equal(campo.value, '900123456-8');
    const pegado = p.campo(campo.dataset, 25);
    p.escribir(pegado, 'a900.123.456--8xyz', true);
    assert.equal(pegado.value, '900123456-8');
});

test('pegar respeta la selección y permite reemplazar el dígito del NIT', () => {
    const p = pagina();
    const campo = p.campo({ caracteres: '0-9\\-', formato: 'nit' }, 25, '900123456-2');
    campo.selectionStart = 10;
    campo.selectionEnd = 11;
    p.escribir(campo, 'a8', true);
    assert.equal(campo.value, '900123456-8');
    assert.equal(campo.selectionStart, 11);
});

test('nombre: tildes, ñ y apóstrofo permanecen; números y etiquetas se filtran', () => {
    const p = pagina();
    const campo = p.campo({ caracteres: "\\p{L}\\p{M} '’\\-", espacios: 'normalizar' }, 100);
    p.escribir(campo, "  María\t O'Neill-Peña 123 <>  ", true);
    p.emitir('blur', { target: campo });
    assert.equal(campo.value, "María O'Neill-Peña");
    p.escribir(campo, 'a'.repeat(150), true);
    assert.equal(campo.value.length, 100);
});

test('clínica y dirección admiten números y signos de dirección con su máximo', () => {
    const p = pagina();
    const dataset = { caracteres: "\\p{L}\\p{M}\\p{N} .,'’&()+/#:\\-", espacios: 'normalizar' };
    const campo = p.campo(dataset, 255);
    p.escribir(campo, '  Carrera  7 # 12-34, local (2) <> ', true);
    p.emitir('blur', { target: campo });
    assert.equal(campo.value, 'Carrera 7 # 12-34, local (2)');
    const nombre = p.campo(dataset, 150);
    p.escribir(nombre, 'ñ'.repeat(200), true);
    assert.equal(nombre.value.length, 150);
});

test('teléfono: signos permitidos, espacios normalizados y máximo 20', () => {
    const p = pagina();
    const campo = p.campo({ caracteres: '0-9+ \\-', espacios: 'normalizar' }, 20);
    p.escribir(campo, ' +57  300a-1234567 ', true);
    p.emitir('blur', { target: campo });
    assert.equal(campo.value, '+57 300-1234567');
    p.escribir(campo, '8'.repeat(25), true);
    assert.equal(campo.value.length, 20);
});

test('correo: sin espacios al escribir o pegar y hasta 255 caracteres', () => {
    const p = pagina();
    const campo = p.campo({ caracteres: '\\S' }, 255);
    p.escribir(campo, ' ana @zooki.test\n', true);
    assert.equal(campo.value, 'ana@zooki.test');
    p.escribir(campo, 'a'.repeat(300), true);
    assert.equal(campo.value.length, 255);
});

test('contraseña: conserva espacios y símbolos, pero no supera los 72 bytes', () => {
    const p = pagina();
    const campo = p.campo({ caracteres: '\\s\\S', maxBytes: '72' }, 72);
    campo.type = 'password';
    p.escribir(campo, ' Clave#ñ🙂  ', true);
    assert.equal(campo.value, ' Clave#ñ🙂  ');
    p.escribir(campo, 'ñ'.repeat(100), true);
    assert.ok(new TextEncoder().encode(campo.value).length <= 72);
});

test('correo nativo sin API de selección conserva el cursor y filtra después de pegar', () => {
    const p = pagina();
    const campo = p.campo({ caracteres: '\\S' }, 255, 'ana@zooki.test');
    campo.type = 'email';
    campo.selectionStart = null;
    campo.selectionEnd = null;
    const evento = {
        target: campo,
        inputType: 'insertText',
        data: 'x',
        cancelable: true,
        preventDefault() { this.impedido = true; },
    };
    p.emitir('beforeinput', evento);
    assert.equal(evento.impedido, undefined);
    assert.equal(campo.value, 'ana@zooki.test');
    evento.data = ' ';
    p.emitir('beforeinput', evento);
    assert.equal(evento.impedido, true);
    campo.value = ' ana @zooki.test ';
    p.emitir('input', { target: campo });
    assert.equal(campo.value, 'ana@zooki.test');
});

test('entrada nativa e IME se filtran sin duplicar los eventos al cargar otra vez', () => {
    const p = pagina();
    const campo = p.campo({ caracteres: '0-9' }, 15, '1a2b3');
    p.emitir('input', { target: campo });
    assert.equal(campo.value, '123');
    vm.runInContext(codigo, p.entorno);
    assert.equal(p.eventos.input.length, 1);
    assert.equal(p.eventos.click.length, 1);
});

test('una tecla o pegado válidos conservan la edición y deshacer del navegador', () => {
    const p = pagina();
    const campo = p.campo({ caracteres: '0-9' }, 15);
    assert.equal(p.escribir(campo, '1').impedido, undefined);
    assert.equal(p.escribir(campo, '23', true).impedido, undefined);
    assert.equal(campo.value, '123');
    assert.equal(p.escribir(campo, 'a').impedido, true);
    assert.equal(campo.value, '123');
});
