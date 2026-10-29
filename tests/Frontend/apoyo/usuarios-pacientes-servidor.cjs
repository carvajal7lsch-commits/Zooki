const { execFileSync } = require('node:child_process');
const { servidorPacientes } = require('./pacientes-movil-servidor.cjs');

async function servidorUsuariosPacientes(tipo, treinta = false) {
    const archivo = tipo === 'usuarios' ? 'usuarios-movil-vista.php' : 'pacientes-movil-vista.php';
    const argumento = treinta ? 'treinta' : tipo === 'usuarios' ? '' : 'directorio';
    const html = execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', ['tests/Frontend/apoyo/' + archivo, argumento], {
        encoding: 'utf8',
        env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' },
    });
    const respuestasVista = {};
    if (treinta) {
        respuestasVista.listar_propietarios_ajax = Array.from({ length: 30 }, (_, i) => ({
            id_usuario: i + 6, nombre_completo: 'Propietario ' + String(i + 1).padStart(2, '0'), documento: String(1000000000 + i),
            telefono: '3001112233', email: 'propietario' + i + '@zooki.test', estado: 1, total_mascotas: i % 4,
            mascotas_resumen: ['Luna', 'Doki', 'Kira'].slice(0, i % 4), pendiente_activacion: i % 5 === 0 ? 1 : 0,
        }));
    }
    return servidorPacientes({ htmlVista: html, directorio: true, respuestasVista });
}

module.exports = { servidorUsuariosPacientes };
