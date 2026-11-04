const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const raiz = path.resolve(__dirname, '../../..');
const dependencias = path.join(__dirname, 'portal-dependencias');
const php = argumento => execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', [path.join(__dirname, 'portal-movil-vista.php'), argumento], { encoding:'utf8', env:{ ...process.env, ZOOKI_TEST_MYSQL_HOST:'', ZOOKI_TEST_MYSQL_DB:'zooki_test_base_v2' } });

async function servidorPortalMovil({ uno = false, reajuste = false, html = null, triage = null, horas = null } = {}) {
    let pagina = html ?? php(reajuste ? 'reajuste' : uno ? 'uno' : '');
    const remotas = {
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css':'flatpickr.min.css',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js':'flatpickr-es.js',
        'https://cdn.jsdelivr.net/npm/flatpickr':'flatpickr.min.js',
        'https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/css/intlTelInput.css':'intlTelInput.css',
        'https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/intlTelInput.min.js':'intlTelInput.min.js',
        'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css':'remixicon.css',
    };
    Object.entries(remotas).forEach(([url, nombre]) => { pagina = pagina.replaceAll(url, '/__deps/' + nombre); });
    pagina = pagina.replace(/<script[^>]+src="https:[^>]*><\/script>/g, '').replace(/<link[^>]+href="https:[^>]*>/g, '');
    pagina = pagina.replace('</head>', '<style>*{animation:none!important;transition:none!important}#global-loader{display:none!important}</style></head>');
    const detalle = JSON.parse(php('detalle'));
    const llamadas = [];
    const servidor = http.createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        const accion = url.searchParams.get('action');
        if (url.pathname === '/' || (url.pathname === '/index.php' && (!accion || ['portal','portal_propietario'].includes(accion)))) {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(pagina);
            return;
        }
        if (url.pathname === '/index.php') {
            if (accion === 'portal_imprimir_historial') {
                res.setHeader('Content-Type', 'text/html; charset=utf-8');
                res.end(php('imprimir-' + (url.searchParams.get('origen') || '')));
                return;
            }
            let cuerpo = '';
            for await (const parte of req) cuerpo += parte;
            llamadas.push({ accion, cuerpo, consulta:url.search, metodo:req.method });
            const fecha = new Date(Date.now() + 86400000).toLocaleDateString('en-CA');
            const respuestas = {
                calcular_triage_ajax: triage ?? { success: true, message: 'Guardado simulado' },
                ver_detalle_mascota_propietario_ajax:detalle,
                get_cita_ajax:{ success:true, cita:{ id_cita:1, id_clinica:1, id_mascota:1, id_tipo_cita:1, id_veterinario:2, fecha } },
                portal_get_vets_ajax:[{ id_usuario:2, nombre_completo:'Beto Norte' }],
                portal_get_tipos_cita_ajax:{ success:true, tipos:[{ id_tipo_cita:1, nombre_tipo:'Consulta general', duracion_minutos:30 }] },
                portal_get_sugerencias_ajax:{ success:true, sugerencias:horas ?? ['09:30:00','10:30:00'] },
                get_notificaciones_ajax:{ success:true, notificaciones:[] },
                validar_cuenta_ajax:{ success:true, exists:false, error:null },
                cambiar_documento_ajax:{ success:true, message:'Guardado simulado', documento:'1080361992' },
            };
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify(respuestas[accion] ?? { success:true, message:'Guardado simulado' }));
            return;
        }
        const esDependencia = url.pathname.startsWith('/__deps/');
        const archivo = esDependencia ? path.join(dependencias, path.basename(url.pathname)) : path.resolve(raiz, 'public', '.' + url.pathname);
        if ((!esDependencia && !archivo.startsWith(path.join(raiz,'public')+path.sep)) || !fs.existsSync(archivo) || !fs.statSync(archivo).isFile()) {
            res.writeHead(404);
            res.end();
            return;
        }
        let contenido = fs.readFileSync(archivo);
        if (archivo.endsWith('intlTelInput.css')) contenido = String(contenido).replaceAll('../img/', '/__deps/');
        if (archivo.endsWith('telefono-internacional.js')) contenido = String(contenido).replace('https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/utils.js', '/__deps/intl-utils.js');
        res.setHeader('Content-Type', archivo.endsWith('.css')?'text/css':archivo.endsWith('.js')?'application/javascript':archivo.endsWith('.woff2')?'font/woff2':archivo.endsWith('.svg')?'image/svg+xml':'image/png');
        res.end(contenido);
    });
    await new Promise(resolve => servidor.listen(0,'127.0.0.1',resolve));
    return { url:'http://127.0.0.1:'+servidor.address().port, llamadas, detalle, cerrar:async()=>{ servidor.closeAllConnections(); await new Promise(resolve=>servidor.close(resolve)); } };
}
module.exports = { servidorPortalMovil };
