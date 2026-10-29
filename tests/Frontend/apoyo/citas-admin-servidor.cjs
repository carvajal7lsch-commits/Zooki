const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const raiz = path.resolve(__dirname, '../../..');
const fecha = desplazamiento => {
    const dia = new Date();
    dia.setDate(dia.getDate() + desplazamiento);
    return `${dia.getFullYear()}-${String(dia.getMonth()+1).padStart(2,'0')}-${String(dia.getDate()).padStart(2,'0')}`;
};
async function servidorCitas(rol = 'admin') {
    const html = execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', [path.join(__dirname, 'citas-admin-vista.php'), rol], { encoding: 'utf8', env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '' } });
    const veterinarios = [{ id_usuario: 2, nombre_completo: 'Beto Norte' }, { id_usuario: 5, nombre_completo: 'Elena Doble' }, { id_usuario: 9, nombre_completo: 'Valeria Invitada' }];
    const citas = Array.from({ length: 10 }, (_, i) => ({ id_cita: i+1, id_veterinario: veterinarios[Math.floor(i/4)].id_usuario, fecha: fecha(i===8?-1:i===9?2:0), hora: `${String(8+i).padStart(2,'0')}:00:00`, mascota_nombre: ['Luna','Max','Nala','Toby'][i%4], propietario_nombre: 'Fabio Propietario', tipo_cita_nombre: i%2?'Vacunación':'Consulta', motivo: 'Revisión de rutina', prioridad: ['verde','amarillo','naranja','rojo'][i%4], estado: ['pendiente','confirmada','completada','cancelada','en_curso','no_asistio','sin_cerrar','pendiente'][i%8] }));
    const solicitudes = [{ id_solicitud: 7, nombre: 'Luna', nivel_calculado: 'naranja', nivel_aprobado: null, contexto_triage: JSON.stringify({ entrada: { sintomas: [{ codigo: 'respiracion', tiempo: 'conocido', minutos: 20 }] }, paciente: { edad_meses: 24, sexo: 'Hembra' }, declarados: { vacunacion: 'completa' } }) }];
    const catalogos = { mascotas: [{ id_mascota: 1, id_propietario: 6, nombre: 'Luna' }], propietarios: [{ id_usuario: 6, nombre_completo: 'Fabio Propietario' }], especies: [{ id_especie: 1, nombre_especie: 'Canino' }], razas: [{ id_raza: 49, id_especie: 1, nombre_raza: 'Mestizo' }], tipos: [{ id_tipo_cita: 1, nombre_tipo: 'Consulta' }] };
    const urgencias = { success: true, catalogos, rojos: [{ id_solicitud: 8, nombre: 'Toby', id_mascota: 1, estado: 'derivada', version_solicitud: 1, fecha_llegada: null }], ingresos: [{ id_ingreso: 3, nombre: 'Paciente provisional', id_mascota_provisional: 20, estado: 'pendiente', nombre_acompanante: 'Mario', telefono_acompanante: '' }] };
    const llamadas = [];
    const servidor = http.createServer((req,res) => {
        const url = new URL(req.url,'http://localhost');
        if (url.pathname === '/') {
            res.setHeader('Content-Type','text/html; charset=utf-8');
            let contenido = html;
            const cache = {
                'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css': '/__bootstrap.css',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css': '/__flatpickr.css',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js': '/__flatpickr-es.js',
                'https://cdn.jsdelivr.net/npm/flatpickr': '/__flatpickr.js'
            };
            for (const [url, local] of Object.entries(cache)) contenido = contenido.replaceAll(url, local);
            res.end(contenido.replace(/<script[^>]+src="https:[^>]*><\/script>/g,'').replace(/<link[^>]+href="https:[^>]*>/g,''));
            return;
        }
        const cache = { '/__bootstrap.css':'usuarios-mockup/bootstrap-icons.css', '/__bootstrap.woff2':'usuarios-mockup/bootstrap-icons.woff2', '/__flatpickr.css':'flatpickr.min.css', '/__flatpickr.js':'flatpickr.min.js', '/__flatpickr-es.js':'flatpickr-es.js' };
        if (cache[url.pathname] && fs.existsSync(path.join(raiz,'scratch',cache[url.pathname]))) {
            res.setHeader('Content-Type',url.pathname.endsWith('.css')?'text/css':url.pathname.endsWith('.js')?'application/javascript':'font/woff2');
            const dato = fs.readFileSync(path.join(raiz,'scratch',cache[url.pathname]));
            res.end(url.pathname === '/__bootstrap.css' ? dato.toString().replace(/url\([^)]*\)/g,'url(/__bootstrap.woff2)') : dato);
            return;
        }
        if (url.pathname === '/index.php') {
            res.setHeader('Content-Type','application/json');
            const accion = url.searchParams.get('action');
            if (req.method === 'POST') {
                let cuerpo = '';
                req.on('data', parte => { cuerpo += parte; });
                req.on('end', () => { llamadas.push({ accion, cuerpo }); res.end(JSON.stringify({success:true,message:'Guardado simulado'})); });
                return;
            }
            res.end(JSON.stringify(accion === 'listar_todas_citas_ajax' ? {success:true,citas} : accion === 'listar_veterinarios_ajax' ? veterinarios : accion === 'listar_solicitudes_agenda_ajax' ? {success:true,solicitudes,etiquetas:{respiracion:'Dificultad respiratoria'}} : accion === 'listar_urgencias_ajax' ? urgencias : {success:true,notificaciones:[]}));
            return;
        }
        const archivo = path.resolve(raiz,'public','.'+url.pathname);
        if (!archivo.startsWith(path.join(raiz,'public')+path.sep) || !fs.existsSync(archivo)) { res.writeHead(404); res.end(); return; }
        res.setHeader('Content-Type',archivo.endsWith('.css')?'text/css':archivo.endsWith('.js')?'application/javascript':'image/png');
        res.end(fs.readFileSync(archivo));
    });
    await new Promise(r=>servidor.listen(0,'127.0.0.1',r));
    return { url:'http://127.0.0.1:'+servidor.address().port, citas, veterinarios, solicitudes, urgencias, llamadas, cerrar:async()=>{servidor.closeAllConnections();await new Promise(r=>servidor.close(r));} };
}
module.exports = { servidorCitas, fecha };
