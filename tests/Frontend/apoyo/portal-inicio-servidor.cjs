const { execFileSync } = require('node:child_process');
const { servidorPacientes } = require('./pacientes-movil-servidor.cjs');

async function servidorPortalInicio(nombre = 'Fabio Propietario') {
    const html = execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', ['tests/Frontend/apoyo/portal-inicio-vista.php', nombre], {
        encoding: 'utf8',
        env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' },
    });
    // Para medir el Inicio pintado por PHP; los comportamientos del portal tienen sus pruebas propias.
    return servidorPacientes({ htmlVista: html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '') });
}

module.exports = { servidorPortalInicio };
