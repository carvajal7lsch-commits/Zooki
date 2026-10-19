const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { errorCampo, enviar } = require('../../public/js/configuracion-clinica.js');

const reglas = { nombreMin: 3, nombreMax: 150, tipoMax: 100, direccionMax: 255, logoMax: 255,
    telefonoMin: 7, telefonoMax: 20, correoMax: 255, enteroMax: 2147483647,
    caracteresTexto: "\\p{L}\\p{M}\\p{N} .,'’&()+/#:\\-" };

test('C1 valida mientras se escribe con límites de marca, teléfono, tipos y pausable explícito', () => {
    for (const [campo, valor] of [['nombre', 'AB'], ['nombre_tipo', 'a'.repeat(101)], ['telefono_urgencias', 'abc'],
        ['duracion_minutos', '0'], ['duracion_minutos', '2147483648'], ['margen_minutos', '-1'], ['pausable', ''],
        ['url_logo', 'javascript:alert(1)'], ['url_logo', 'https://example.com/'.padEnd(256, 'a')], ['email_contacto', 'sin-arroba']]) {
        assert.notEqual(errorCampo(campo, valor, reglas), '', campo);
    }
    for (const [campo, valor] of [['nombre', 'Norte Veterinaria'], ['nombre_tipo', 'Cirugía / Control'], ['pausable', '0'],
        ['margen_minutos', '0'], ['telefono_urgencias', '+57 3001234567'], ['url_logo', 'img/logo.png'], ['telefono', '']]) {
        assert.equal(errorCampo(campo, valor, reglas), '', campo);
    }
});

test('C1 bloquea doble envío y actualiza con la respuesta antes del aviso', async () => {
    let resolver;
    let llamadas = 0;
    const eventos = [];
    const estado = { enviando: false };
    const dependencias = {
        fetch: async () => {
            llamadas++;
            await new Promise(resolve => { resolver = resolve; });
            return { ok: true, json: async () => ({ success: true, tipo: { activo: 0 }, message: 'Guardado' }) };
        },
        bloquear: b => eventos.push(b ? 'bloquear' : 'habilitar'),
        error: () => assert.fail('No debe fallar'),
        aviso: () => eventos.push('aviso')
    };
    const primera = enviar(estado, {}, 'guardar_tipo_cita_ajax', datos => {
        assert.equal(datos.tipo.activo, 0);
        eventos.push('actualizar');
    }, dependencias);
    assert.equal(await enviar(estado, {}, 'guardar_tipo_cita_ajax', () => assert.fail(), dependencias), false);
    resolver();
    assert.equal(await primera, true);
    assert.equal(llamadas, 1);
    assert.deepEqual(eventos, ['bloquear', 'actualizar', 'aviso', 'habilitar']);
    assert.equal(estado.enviando, false);
});

test('C1 conserva error de servidor junto al campo y habilita para corregir', async () => {
    const errores = [];
    const bloqueos = [];
    const estado = { enviando: false };
    assert.equal(await enviar(estado, {}, 'guardar_marca_clinica_ajax', () => assert.fail(), {
        fetch: async () => ({ ok: false, json: async () => ({ success: false, campo: 'telefono_urgencias', aviso: 'Formato', message: 'Teléfono inválido' }) }),
        bloquear: b => bloqueos.push(b), error: d => errores.push(d), aviso: () => assert.fail()
    }), false);
    assert.equal(errores[0].campo, 'telefono_urgencias');
    assert.equal(errores[0].aviso, 'Formato');
    assert.deepEqual(bloqueos, [true, false]);
    assert.equal(estado.enviando, false);
});

test('C1 recupera el formulario ante red o JSON inválido sin anunciar éxito', async () => {
    for (const fetch of [async () => { throw new Error('red'); }, async () => ({ json: async () => { throw new Error('JSON'); } })]) {
        const estado = { enviando: false };
        let mensaje = '';
        await enviar(estado, {}, 'guardar_tipo_cita_ajax', () => assert.fail(), {
            fetch, bloquear: () => {}, error: d => { mensaje = d.message; }, aviso: () => assert.fail()
        });
        assert.match(mensaje, /No se pudo guardar/);
        assert.equal(estado.enviando, false);
    }
});

test('C1 conserva HU-7.1, restricciones de entrada, contexto del formulario y no usa confirmación', () => {
    const raiz = path.join(__dirname, '../..');
    const vista = fs.readFileSync(path.join(raiz, 'views/admin/configuracion.php'), 'utf8');
    const script = fs.readFileSync(path.join(raiz, 'public/js/configuracion-clinica.js'), 'utf8');
    assert.match(vista, /require __DIR__ \. '\/horarios.php'/);
    assert.match(vista, /name="id_clinica"/);
    assert.match(vista, /ValidadorTelefono::atributosHtml/);
    assert.match(vista, /data-caracteres="0-9"/);
    assert.match(script, /addEventListener\('input'/);
    assert.match(script, /validar_configuracion_clinica_ajax/);
    assert.match(script, /versiones.get\(entrada\) !== version/);
    assert.match(script, /datos.campo/);
    assert.doesNotMatch(script, /\bconfirm\(|zookiConfirmar|Swal\.fire/);
    assert.doesNotMatch(vista, /\s(?:onclick|style)=|<script(?![^>]*src=)[^>]*>/i);
    const imprimible = fs.readFileSync(path.join(raiz, 'public/js/medical-module.js'), 'utf8');
    assert.doesNotMatch(imprimible, /<style>/i);
    assert.match(imprimible, /css\/imprimir-historia-personal.css/);
});
