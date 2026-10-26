<?php
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/PlataformaFixture.php';
require_once __DIR__ . '/../../Support/FinanzasFixture.php';
require_once __DIR__ . '/../../../controllers/PlataformaController.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$db = PlataformaFixture::sqlite();
putenv('APP_URL=http://localhost/Zooki/public');
FinanzasFixture::datos($db);
FinanzasFixture::plataforma();
$contexto = Contexto::dePlataforma();
Security::definirFuenteDeContextos(static fn (): array => [$contexto]);
$_SERVER['REQUEST_METHOD'] = 'GET';
$controller = new PlataformaController($db);
$escenario = $argv[1] ?? 'inicio';
if ($escenario === 'inicio') {
    $controller->inicio();
} else {
    $id = (int) ($argv[2] ?? 1);
    $_GET = ['id_clinica' => $id];
    if ($escenario === 'revisada') {
        $panel = new Plataforma($db);
        $panel->revisarIncidencia((int) $panel->finanzas($id)['incidencias'][0]['id_evento'], $id, 'Revisión de prueba sin modificar el pago');
    }
    $controller->pagos();
}
