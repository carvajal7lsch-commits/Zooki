const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const raiz = path.resolve(__dirname, '../../..');
const fecha = desplazamiento => {
    const dia = new Date();
    dia.setDate(dia.getDate() + desplazamiento);
    return `${dia.getFullYear()}-${String(dia.getMonth() + 1).padStart(2, '0')}-${String(dia.getDate()).padStart(2, '0')}`;
};
async function servidorAgendaVet({ iconos = true, triage = null, contextoAgenda = null, horas = null } = {}) {
    let html = execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', [path.join(__dirname, 'agenda-vet-vista.php')], { encoding: 'utf8', env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' } });
    if (contextoAgenda) {
        html = html.replace(/ data-contexto-agenda="[^"]*"/, '');
        html = html.replace('class="agenda"', 'class="agenda" data-contexto-agenda="' + JSON.stringify(contextoAgenda).replaceAll('"', '&quot;') + '"');
    }
    const citas = Array.from({ length: 6 }, (_, i) => ({ id_cita: i + 1, fecha: fecha(0), hora: `${String(8 + i).padStart(2, '0')}:30:00`, tipo: ['cita', 'cita', 'cita', 'cita', 'vacunacion', 'desparasitacion'][i], mascota_nombre: ['Toby', 'Mia', 'Luna', 'Max', 'Rocky', 'Kira'][i], propietario_nombre: 'Fabio Propietario', id_veterinario: 2, veterinario_nombre: 'Beto Norte', tipo_cita_nombre: 'Consulta general', estado: i < 2 ? 'completada' : 'pendiente', prioridad: ['verde', 'verde', 'naranja', 'amarillo', '', ''][i] }));
    const solicitudes = [{ id_solicitud: 7, nombre: 'Luna', huella_entrada: 'huella-original-prueba', nivel_calculado: 'naranja', nivel_aprobado: null, contexto_triage: JSON.stringify({ entrada: { sintomas: [{ codigo: 'vomito', tiempo: 'conocido', minutos: 20 }] }, paciente: { edad_meses: 24, sexo: 'Hembra' }, declarados: { vacunacion: 'completa' } }) }];
    const llegadas = { citas: [{ id_cita: 3, hora: '10:30:00', mascota: 'Luna', prioridad: 'amarillo' }], solicitudes: [{ id_solicitud: 9, mascota: 'Max', nivel_calculado: 'verde' }], espera: [{ id_solicitud: 10, mascota: 'Kira', nivel_calculado: 'amarillo', fecha_llegada: fecha(0) + ' 10:00:00' }], historial: [] };
    const propuestas = [{ id_reasignacion: 4, mascota: 'Rocky', inicio_propuesto: fecha(0) + ' 15:20:00', fecha_limite: fecha(0) + ' 15:05:00' }];
    const catalogos = { mascotas: [{ id_mascota: 1, id_propietario: 6, nombre: 'Luna' }], propietarios: [{ id_usuario: 6, nombre_completo: 'Fabio Propietario' }], especies: [], razas: [], tipos: [{ id_tipo_cita: 1, nombre_tipo: 'Consulta' }] };
    const urgencias = { success: true, catalogos, rojos: [{ id_solicitud: 8, nombre: 'Toby', estado: 'derivada', version_solicitud: 1, fecha_llegada: null }], ingresos: [] };
    const llamadas = [];
    const pendientes = [];
    let retener = false;
    const servidor = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            let pagina = html.replace('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', '/__fullcalendar.js').replace('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css', '/__bootstrap.css').replace('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', '/__fontawesome.css');
            if (!iconos) pagina = pagina.replace(/<link[^>]+href="\/__fontawesome.css"[^>]*>/g, '');
            pagina = pagina.replace(/<script[^>]+src="https:[^>]*><\/script>/g, '').replace(/<link[^>]+href="https:[^>]*>/g, '');
            res.end(pagina.replace('</head>', '<style>*{animation:none!important;transition:none!important}#global-loader{display:none!important}</style></head>'));
            return;
        }
        if (url.pathname === '/__fullcalendar.js') {
            res.setHeader('Content-Type', 'application/javascript');
            const cache = path.join(raiz, 'scratch/r/agenda-vet-movil/fullcalendar.min.js');
            res.end(fs.existsSync(cache) ? fs.readFileSync(cache) : `window.FullCalendar={Calendar:class{constructor(el,o){this.o=o;this.d=new Date();this.e=[]}render(){this.gotoDate(this.d)}getEvents(){return this.e}getEventById(id){return this.e.find(e=>e.id===id)}gotoDate(d){this.d=new Date(d);this.o.datesSet({view:{title:this.d.toLocaleDateString('es',{month:'long',year:'numeric'}),type:'dayGridMonth',currentStart:new Date(this.d.getFullYear(),this.d.getMonth(),1),currentEnd:new Date(this.d.getFullYear(),this.d.getMonth()+1,1)}});this.refetchEvents()}refetchEvents(){this.o.events({startStr:new Date(this.d.getFullYear(),this.d.getMonth(),-5).toISOString(),endStr:new Date(this.d.getFullYear(),this.d.getMonth()+1,7).toISOString()},a=>{this.e=a.map(x=>({...x,start:new Date(x.start),setProp(k,v){this[k]=v}}));this.o.loading(false)},()=>{})}updateSize(){}prev(){this.d.setMonth(this.d.getMonth()-1);this.gotoDate(this.d)}next(){this.d.setMonth(this.d.getMonth()+1);this.gotoDate(this.d)}today(){this.gotoDate(new Date())}changeView(){}}};`);
            return;
        }
        if (url.pathname === '/__fontawesome.css' || url.pathname === '/__solid.woff2') {
            const css = url.pathname.endsWith('.css');
            const archivo = path.join(raiz, 'scratch/r/agenda-vet-movil', css ? 'fontawesome.css' : 'fa-solid-900.woff2');
            res.setHeader('Content-Type', css ? 'text/css' : 'font/woff2');
            const dato = fs.existsSync(archivo) ? fs.readFileSync(archivo) : '';
            res.end(css ? String(dato).replace(/url\([^)]*\)/g, 'url(/__solid.woff2)') : dato);
            return;
        }
        if (url.pathname.startsWith('/__bootstrap')) {
            const css = url.pathname.endsWith('.css');
            const archivo = path.join(raiz, 'scratch/usuarios-mockup', css ? 'bootstrap-icons.css' : 'bootstrap-icons.woff2');
            res.setHeader('Content-Type', css ? 'text/css' : 'font/woff2');
            const dato = fs.existsSync(archivo) ? fs.readFileSync(archivo) : '';
            res.end(css ? String(dato).replace(/url\([^)]*\)/g, 'url(/__bootstrap.woff2)') : dato);
            return;
        }
        if (url.pathname === '/index.php') {
            res.setHeader('Content-Type', 'application/json');
            const accion = url.searchParams.get('action');
            if (req.method === 'POST') {
                let cuerpo = '';
                req.on('data', parte => { cuerpo += parte; });
                req.on('end', () => {
                    llamadas.push({ accion, cuerpo });
                    const respuesta = accion === 'registrar_llegada_ajax' ? { success: true, message: 'Llegada registrada', resultado: { opciones: { segundos_restantes: 1200, dentro_tolerancia: true, reducida: true, otro_veterinario: [] } } } : accion === 'calcular_triage_ajax' ? (triage ?? { success: true, id_solicitud: 123, huella: 'huella-calculo', reservable: true, nivel: 'amarillo' }) : { success: true, message: 'Guardado simulado' };
                    const enviar = () => res.end(JSON.stringify(respuesta));
                    if (retener) pendientes.push(enviar);
                    else enviar();
                });
                return;
            }
            llamadas.push({ accion, consulta: url.search });
            const respuestas = {
                listar_citas_ajax: citas,
                listar_veterinarios_ajax: [{ id_usuario: 2, nombre_completo: 'Beto Norte' }],
                listar_tipos_cita_ajax: { success: true, tipos: [{ id_tipo_cita: 1, nombre: 'Consulta general', duracion_minutos: 30 }] },
                listar_mascotas_ajax: [{ id_mascota: 1, nombre: 'Doki', propietario_nombre: 'Fabio Propietario', sexo: 'Macho' }],
                listar_solicitudes_agenda_ajax: { success: true, solicitudes, etiquetas: { vomito: 'Vómito' } },
                listar_llegadas_ajax: { success: true, resultado: llegadas },
                listar_reasignaciones_ajax: { success: true, propuestas },
                listar_urgencias_ajax: urgencias,
                get_sugerencias_horario_ajax: { success: true, sugerencias: horas ?? ['09:00:30', '10:00:00', '11:30:00', '14:00:00', '15:40:00'] },
                get_cita_ajax: { success: true, cita: { id_cita: 3, id_mascota: 1, id_tipo_cita: 1, id_veterinario: 2, fecha: fecha(1), hora: '10:30:00' } },
                triage_catalogos_ajax: { success: true, sintomas: [], exposiciones: [] }
            };
            res.end(JSON.stringify(respuestas[accion] || { success: true, notificaciones: [] }));
            return;
        }
        const archivo = path.resolve(raiz, 'public', '.' + url.pathname);
        if (!archivo.startsWith(path.join(raiz, 'public') + path.sep) || !fs.existsSync(archivo)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', archivo.endsWith('.css') ? 'text/css' : archivo.endsWith('.js') ? 'application/javascript' : 'image/png');
        res.end(fs.readFileSync(archivo));
    });
    await new Promise(r => servidor.listen(0, '127.0.0.1', r));
    return { url: 'http://127.0.0.1:' + servidor.address().port, citas, solicitudes, propuestas, llamadas, pendientes, retener: valor => { retener = valor; }, cerrar: async () => { servidor.closeAllConnections(); await new Promise(r => servidor.close(r)); } };
}
module.exports = { servidorAgendaVet, fecha };
