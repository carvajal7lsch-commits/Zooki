const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const raiz = path.resolve(__dirname, '../../..');
async function servidorPacientes() {
    const html = execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', [path.join(__dirname, 'pacientes-movil-vista.php')], { encoding: 'utf8', env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' } });
    const propietario = { id_usuario: 6, nombre_completo: 'Fabio Propietario', documento: '1000000006', telefono: '3001112233', email: 'fabio@zooki.test', estado: 1, total_mascotas: 5 };
    const mascotas = [
        { id_mascota: 1, nombre: 'Doki', especie: 'Canino', raza: 'Dóberman', sexo: 'Macho', numero_historia_clinica: 'HC-000002', estado: 1 },
        { id_mascota: 2, nombre: 'Gato', especie: 'Felino', raza: 'Bengala', sexo: 'Macho', numero_historia_clinica: 'HC-000003', estado: 1 },
        { id_mascota: 3, nombre: 'Kira', especie: 'Canino', raza: 'Mestizo', sexo: null, numero_historia_clinica: null, estado: 1 },
        { id_mascota: 4, nombre: 'Luna', especie: 'Canino', raza: 'Sin raza definida', sexo: 'Hembra', numero_historia_clinica: 'HC-000001', estado: 1 },
        { id_mascota: 5, nombre: 'Pelusa', especie: 'Felino', raza: 'Mestizo', sexo: 'Hembra', estado: 0 }
    ];
    const historial = { mascota: { id_mascota: 1, id_propietario: 6, nombre: 'Doki', nombre_especie: 'Canino', nombre_raza: 'Dóberman', sexo: 'Macho', peso: '9.00', fecha_nacimiento: '2024-12-31', numero_historia_clinica: 'HC-000002', estado: 1 }, consultas: [{ fecha_hora: '2026-10-21 08:00:00', diagnostico: 'Control', clinica_nombre: 'Norte', id_consulta: 1 }], vacunas: [{ fecha_aplicacion: '2026-10-01', nombre_vacuna: 'Rabia', fecha_proxima_dosis: '2027-10-01', clinica_nombre: 'Sur' }], desparasitaciones: [{ fecha_aplicacion: '2026-10-05', producto: 'Producto registrado', clinica_nombre: 'Norte' }], alertas: { activas: [], historial: [], solo_lectura: false } };
    const llamadas = [];
    const servidor = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            const rutas = {
                'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css': '/__bootstrap.css',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css': '/__fontawesome.css',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css': '/__flatpickr.css',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js': '/__flatpickr-es.js',
                'https://cdn.jsdelivr.net/npm/flatpickr': '/__flatpickr.js'
            };
            let pagina = html;
            Object.entries(rutas).forEach(([remota, local]) => { pagina = pagina.replaceAll(remota, local); });
            pagina = pagina.replace(/<script[^>]+src="https:[^>]*><\/script>/g, '').replace(/<link[^>]+href="https:[^>]*>/g, '');
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(pagina.replace('</head>', '<style>*{animation:none!important;transition:none!important}#global-loader{display:none!important}</style></head>'));
            return;
        }
        const cache = { '/__bootstrap.css': 'usuarios-mockup/bootstrap-icons.css', '/__bootstrap.woff2': 'usuarios-mockup/bootstrap-icons.woff2', '/__fontawesome.css': 'r/agenda-vet-movil/fontawesome.css', '/__solid.woff2': 'r/agenda-vet-movil/fa-solid-900.woff2', '/__flatpickr.css': 'flatpickr.min.css', '/__flatpickr.js': 'flatpickr.min.js', '/__flatpickr-es.js': 'flatpickr-es.js' };
        if (cache[url.pathname]) {
            const archivo = path.join(raiz, 'scratch', cache[url.pathname]);
            const dato = fs.existsSync(archivo) ? fs.readFileSync(archivo) : '';
            res.setHeader('Content-Type', url.pathname.endsWith('.css') ? 'text/css' : url.pathname.endsWith('.js') ? 'application/javascript' : 'font/woff2');
            const fuente = url.pathname === '/__bootstrap.css' ? '/__bootstrap.woff2' : '/__solid.woff2';
            res.end(url.pathname.endsWith('.css') ? String(dato).replace(/url\([^)]*\)/g, 'url(' + fuente + ')') : dato);
            return;
        }
        if (url.pathname === '/index.php') {
            const accion = url.searchParams.get('action');
            llamadas.push({ accion, consulta: url.search });
            const respuestas = {
                listar_propietarios_ajax: [propietario],
                get_propietario_ajax: propietario,
                listar_mascotas_propietario_ajax: mascotas,
                listar_historial_ajax: historial,
                get_mascota_ajax: { ...historial.mascota, puede_editar: true },
                get_especies_ajax: [{ id_especie: 1, nombre_especie: 'Canino' }],
                get_razas_ajax: [],
                get_notificaciones_ajax: { success: true, notificaciones: [] },
                cuenta_reglas_ajax: { success: true }
            };
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify(respuestas[accion] || []));
            return;
        }
        const archivo = path.resolve(raiz, 'public', '.' + url.pathname);
        if (!archivo.startsWith(path.join(raiz, 'public') + path.sep) || !fs.existsSync(archivo) || !fs.statSync(archivo).isFile()) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', archivo.endsWith('.css') ? 'text/css' : archivo.endsWith('.js') ? 'application/javascript' : 'image/png');
        res.end(fs.readFileSync(archivo));
    });
    await new Promise(r => servidor.listen(0, '127.0.0.1', r));
    return { url: 'http://127.0.0.1:' + servidor.address().port, propietario, mascotas, historial, llamadas, cerrar: async () => { servidor.closeAllConnections(); await new Promise(r => servidor.close(r)); } };
}
module.exports = { servidorPacientes };
