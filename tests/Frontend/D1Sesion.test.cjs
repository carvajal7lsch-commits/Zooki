const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

// HU-T.16 (RE-T.16.2): con la sesión vencida, una petición AJAX recibe 401 y
// la interfaz lleva al inicio de sesión.
function pagina(estado) {
    const llamadas = [];
    const window = {
        location: { href: 'index.php?action=admin_panel' },
        fetch: async (...argumentos) => {
            llamadas.push(argumentos);
            return { status: estado };
        },
    };
    vm.runInNewContext(codigo('public/js/sesion.js'), { window });
    return { window, llamadas };
}

test('un 401 lleva al inicio de sesión y devuelve la respuesta', async () => {
    const { window, llamadas } = pagina(401);
    const respuesta = await window.fetch('index.php?action=get_pendientes_ajax', { method: 'GET' });
    assert.equal(respuesta.status, 401);
    assert.equal(window.location.href, 'index.php?action=login');
    assert.deepEqual(llamadas[0], ['index.php?action=get_pendientes_ajax', { method: 'GET' }]);
});

test('una respuesta normal o un 403 no cambian de página', async () => {
    for (const estado of [200, 403, 422]) {
        const { window } = pagina(estado);
        await window.fetch('index.php?action=x');
        assert.equal(window.location.href, 'index.php?action=admin_panel');
    }
});

test('cargar el script dos veces no envuelve fetch dos veces', async () => {
    const { window, llamadas } = pagina(200);
    const envuelto = window.fetch;
    vm.runInNewContext(codigo('public/js/sesion.js'), { window });
    assert.equal(window.fetch, envuelto);
    await window.fetch('index.php?action=x');
    assert.equal(llamadas.length, 1);
});

test('los tres layouts cargan sesion.js', () => {
    for (const layout of ['views/admin/layout.php', 'views/vet/layout.php', 'views/portal/layout.php']) {
        assert.match(codigo(layout), /<script src="js\/sesion\.js\?v=\d+"><\/script>/, layout);
    }
});

test('login.php no tiene JS ni estilos en línea y el client_id viaja en data-*', () => {
    const vista = codigo('views/auth/login.php');
    assert.doesNotMatch(vista, /<script>(?!\s*<\/script>)/);
    assert.doesNotMatch(vista, /\sstyle="/);
    assert.match(vista, /data-google-client-id="<\?= \$e\(\$googleClientId\) \?>"/);
    assert.match(codigo('public/js/login.js'), /document\.body\.dataset\.googleClientId/);
});
