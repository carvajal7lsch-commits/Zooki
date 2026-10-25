<?php
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
require_once __DIR__ . '/../../../controllers/SuscripcionController.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$db = DosClinicas::sqlite();
$_SESSION = ['id_usuario' => 1, 'usuario_nombre' => 'Ana Norte'];
$contexto = Contexto::deClinica(1, 'Clínica Norte', Roles::ADMIN);
Contexto::activar($contexto, 1);
Security::definirFuenteDeContextos(static fn (): array => [$contexto]);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'admin_suscripcion'];
$db->exec("UPDATE clinicas SET nombre='Clínica Norte <prueba>' WHERE id_clinica=1");
$escenario = $argv[1] ?? 'gracia';
if ($escenario !== 'legado') {
    $db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
}
$ahora = VigenciaSuscripcion::fecha($escenario === 'congelada' ? '2026-10-28 00:00:00' : '2026-10-26 12:00:00');
(new SuscripcionController($db, static fn () => $ahora))->vista();
