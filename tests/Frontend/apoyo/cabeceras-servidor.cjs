const { execFileSync } = require('node:child_process');
const { servidorPacientes } = require('./pacientes-movil-servidor.cjs');

// RNF-16: vistas PHP reales con sus layouts, sin configuración ni base de trabajo.
const pantallasCabecera = [
    ['Admin · Inicio', 'inicio-admin-vista.php', 'dos'],
    ['Admin · Usuarios', 'usuarios-movil-vista.php'],
    ['Admin · Citas', 'citas-admin-vista.php'],
    ['Admin · Auditoría', 'auditoria-vista.php'],
    ['Admin · Ajustes', 'ajustes-vista.php'],
    ['Admin · Mi perfil (Mi cuenta en Ajustes)', 'perfil-vista.php', 'config', 'layout'],
    ['Vet · Inicio', 'inicio-pulido-vista.php'],
    ['Vet · Pacientes', 'pacientes-movil-vista.php', 'directorio'],
    ['Vet · Agenda', 'agenda-vet-vista.php'],
    ['Vet · Atención', 'c81-vistas.php', 'vet_atencion'],
    ['Vet · Mi perfil', 'perfil-vista.php', 'vet', 'layout'],
    ['Plataforma · Clínicas', 'plataforma-pagos-vista.php', 'inicio'],
    ['Plataforma · Conocimiento', 'plataforma-grafo-vista.php'],
];

async function servidorCabecera(pantalla) {
    const argumentos = ['tests/Frontend/apoyo/' + pantalla[1], ...pantalla.slice(2)];
    const html = execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', argumentos, {
        encoding: 'utf8',
        env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' },
    });
    // Se mide el layout de PHP/CSS; las regresiones funcionales tienen sus propios servidores AJAX.
    const htmlVista = html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
    return servidorPacientes({ htmlVista });
}

async function medirCabecera(nav) {
    await nav.esperar("document.readyState==='complete'");
    return nav.evaluar(`new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(()=>{
        const cabecera=document.querySelector('.top-header');
        const titulo=Array.from(cabecera.querySelectorAll('h1,h2')).find(e=>e.textContent.trim() && e.getBoundingClientRect().height);
        const cuerpo=document.querySelector('.content-body');
        const contenedor=cuerpo.querySelector('.content-wrapper') || cuerpo;
        const hoja=Array.from(contenedor.children).find(e=>e.getBoundingClientRect().height>0 && !['fixed','absolute'].includes(getComputedStyle(e).position)) || cuerpo;
        const rect=e=>e ? {y:e.getBoundingClientRect().y,alto:e.getBoundingClientRect().height}:null;
        r({cabecera:rect(cabecera),titulo:rect(titulo),cuerpo:rect(cuerpo),hoja:rect(hoja),texto:titulo?.textContent.trim(),ancho:document.documentElement.scrollWidth,ventana:innerWidth});
    })))`);
}

module.exports = { pantallasCabecera, servidorCabecera, medirCabecera };
