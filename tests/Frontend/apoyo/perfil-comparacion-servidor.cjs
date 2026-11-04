const { execFileSync } = require('node:child_process');
const { servidorPacientes } = require('./pacientes-movil-servidor.cjs');
async function servidorPerfil(rol) {
    const htmlVista = execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', ['tests/Frontend/apoyo/perfil-vista.php', rol === 'admin' ? 'config' : 'vet', 'layout'], { encoding:'utf8', env:{ ...process.env, ZOOKI_TEST_MYSQL_HOST:'', ZOOKI_TEST_MYSQL_DB:'zooki_test_base_v2' } });
    const respuestasVista = {
        get_coordinacion_horarios_ajax: { success:true, id_veterinario:2, veterinarios:[{id_usuario:2,nombre_completo:'Fabio Propietario'}], franjas:[1,2,3,4,5].flatMap(d=>[{dia_semana:d,hora_inicio:'08:00:00',hora_fin:'12:00:00'},{dia_semana:d,hora_inicio:'14:00:00',hora_fin:'18:00:00'}]), propuestas:[], cierres:[], afectadas:[] },
        listar_ausencias_ajax: { success:true, ausencias:[] },
        get_horarios_ajax: { success:true, horarios:[] }
    };
    respuestasVista.get_coordinacion_horarios_ajax.apertura = Array.from({ length: 7 }, (_, i) => ({ dia_semana: i + 1, activo: i < 5 ? 1 : 0, bloque_morning_activo: 1, bloque_afternoon_activo: 1, bloque_morning_inicio: '08:00:00', bloque_morning_fin: '12:00:00', bloque_afternoon_inicio: '14:00:00', bloque_afternoon_fin: '18:00:00' }));
    return servidorPacientes({htmlVista,respuestasVista});
}
module.exports={servidorPerfil};
