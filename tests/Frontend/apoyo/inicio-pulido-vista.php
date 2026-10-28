<?php
// Vistas y controlador reales sobre SQLite de pruebas; nunca usa la base de la aplicación.
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
require_once __DIR__ . '/../../../controllers/PanelController.php';
require_once __DIR__ . '/../../../config/App.php';

$db = DosClinicas::sqlite();
DosClinicas::crearMascotasSqlite($db);
DosClinicas::crearCatalogosSqlite($db);
DosClinicas::crearHistoriaSqlite($db);
DosClinicas::poblarAgenda($db);
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$_SESSION = ['id_usuario' => DosClinicas::VET_NORTE, 'usuario_nombre' => 'Beto Norte'];
Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Clínica Norte', Roles::VETERINARIO), ($argv[2] ?? '') === 'varios' ? 2 : 1);
$_GET = ['action' => 'vet_area'];
$caso = $argv[1] ?? 'breve';
// 14 citas: con 8, sin la fuente Inter (CI) la lista cabía entera y no había scroll que probar.
$cantidad = $caso === 'muchas' ? 14 : ($caso === 'vacio' ? 0 : 1);
for ($i = 0; $i < $cantidad; $i++) {
    $db->prepare("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,motivo,estado,duracion_minutos,margen_minutos,prioridad) VALUES (1,1,2,1,'2030-01-07',?,'Control','pendiente',30,10,'verde')")
        ->execute([sprintf('%02d:00:00', 9 + $i)]);
}
$ahora = new DateTimeImmutable('2030-01-07 18:00:00', new DateTimeZone('America/Bogota'));
$panel = (new PanelController($db, fn () => $ahora))->datosVeterinario(DosClinicas::VET_NORTE, $ahora);
$panel['triages'] = $caso === 'vacio' ? [] : [['nombre' => 'Mia'], ['nombre' => 'Mia'], ['nombre' => 'Doki']];
if ($caso === 'escape') {
    $panel['triages'] = [['nombre' => '<img src=x>'], ['nombre' => '<img src=x>']];
}
require __DIR__ . '/../../../views/vet/layout.php';
