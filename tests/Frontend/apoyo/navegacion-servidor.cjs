const { execFileSync } = require('node:child_process');
const { servidorPacientes } = require('./pacientes-movil-servidor.cjs');

const rutasNavegacion = {
    admin: ['admin_usuarios', 'admin_citas'],
    vet: ['vet_pacientes', 'vet_agenda'],
    plataforma: ['plataforma_inicio', 'plataforma_grafo'],
};

async function servidorNavegacion(rol, opciones = {}) {
    const procesos = {
        admin_panel: ['inicio-admin-vista.php', 'dos'],
        admin_auditoria: ['auditoria-vista.php'],
        admin_configuracion: ['ajustes-vista.php'],
        admin_usuarios: ['usuarios-movil-vista.php'],
        admin_citas: ['citas-admin-vista.php'],
        vet_pacientes: ['pacientes-movil-vista.php', 'directorio'],
        vet_agenda: ['agenda-vet-vista.php'],
        vet_area: ['inicio-pulido-vista.php'],
        vet_atencion: ['c81-vistas.php', 'vet_atencion'],
        mi_perfil: ['perfil-vista.php', 'vet', 'layout'],
        plataforma_inicio: ['plataforma-pagos-vista.php', 'inicio'],
        plataforma_grafo: ['plataforma-grafo-vista.php'],
    };
    const vistas = {};
    const acciones = [...rutasNavegacion[rol], ...(rol === 'admin' ? ['admin_panel', 'admin_auditoria', 'admin_configuracion'] : rol === 'vet' ? ['vet_area', 'mi_perfil'] : [])];
    for (const accion of acciones) {
        const [archivo, ...argumentos] = procesos[accion];
        const html = execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', ['tests/Frontend/apoyo/' + archivo, ...argumentos], {
            encoding: 'utf8',
            env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' },
        });
        // Vistas/layouts reales. Se aísla la navegación de los AJAX propios de cada módulo.
        vistas[accion] = html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, etiqueta => /src="js\/(barra-navegacion|navegacion-transicion|carga-contenido|dashboard)\.js/.test(etiqueta) ? etiqueta : '');
    }
    return servidorPacientes({ htmlVista: vistas[rutasNavegacion[rol][0]], vistas, animaciones: true, ocultarLoader: false, ...opciones });
}

module.exports = { servidorNavegacion, rutasNavegacion };
