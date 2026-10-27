<?php
// Vista real con SQLite efímero: nunca lee .env ni consulta una base de trabajo.
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
require_once __DIR__ . '/../../../controllers/AuditoriaController.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$db = DosClinicas::sqlite();
$_SESSION = ['id_usuario' => 1, 'usuario_nombre' => 'Ana Norte'];
$contexto = Contexto::deClinica(1, 'Clínica Norte', Roles::ADMIN);
Contexto::activar($contexto, 1);
Security::definirFuenteDeContextos(static fn (): array => [$contexto]);
$insertar = $db->prepare('INSERT INTO auditoria_sistema (id_clinica, id_usuario, ip_address, fecha_hora, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, descripcion) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?)');
for ($i = 1; $i <= 53; $i++) {
    $fecha = date('Y-m-d', strtotime($i % 3 === 0 ? '-1 day' : 'today')) . ' 10:00:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $antes = json_encode(['duracion_minutos' => 30, 'margen_minutos' => 10, 'token' => 'SECRETO-ANTES', 'anidado' => ['password' => 'CLAVE-PRIVADA']]);
    $despues = json_encode(['duracion_minutos' => 45, 'margen_minutos' => 15, 'token' => 'SECRETO-NUEVO', 'anidado' => ['password' => 'OTRA-CLAVE']]);
    $insertar->execute([1, '::1', $fecha, $i % 4 ? 'UPDATE' : 'LOGIN_FAIL', 'tipos_cita', $i, $antes, $despues, 'Configuración <img src=x onerror="window.ataque=1"> de tipos de cita']);
}
$insertar->execute([2, '::1', date('Y-m-d H:i:s'), 'DELETE', 'usuarios', 99, null, null, 'CLÍNICA AJENA']);
parse_str($argv[1] ?? '', $filtrosPrueba);
$_GET = array_merge($filtrosPrueba, ['action' => 'admin_auditoria']);
$_SERVER['REQUEST_METHOD'] = 'GET';
(new AuditoriaController($db))->listar();
