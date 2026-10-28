const { execFileSync } = require('node:child_process');
const { servidorPacientes } = require('./pacientes-movil-servidor.cjs');

function php(argumentos) {
    return execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', argumentos, {
        encoding: 'utf8',
        env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' },
    });
}

async function servidorPlataforma(grafo = false) {
    const htmlVista = php(grafo ? ['tests/Frontend/apoyo/plataforma-grafo-vista.php'] : ['tests/Frontend/apoyo/plataforma-pagos-vista.php', 'inicio']);
    const respuestasVista = {};
    respuestasVista.plataforma_pagos_ajax = url => php(['tests/Frontend/apoyo/plataforma-pagos-vista.php', 'pagos', url.searchParams.get('id_clinica') || '0']);
    if (grafo) {
        respuestasVista.obtener_grafo_conocimiento_ajax = JSON.parse(php(['tests/Frontend/apoyo/plataforma-grafo-vista.php', 'datos']));
    }
    return servidorPacientes({ htmlVista, respuestasVista });
}
module.exports = { servidorPlataforma };
