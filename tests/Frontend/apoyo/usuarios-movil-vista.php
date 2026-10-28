<?php
if (defined('ZOOKI_CABECERA_VET_PRUEBA')) {
    echo '<section class="cabecera-prueba-contenido">Contenido de la vista veterinaria.</section>';
    return;
}
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
require_once __DIR__ . '/../../../controllers/UsuarioController.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$db = DosClinicas::sqlite();
DosClinicas::crearMascotasSqlite($db);
$esVetPrueba = ($argv[1] ?? '') === 'vet';
$rolPrueba = $esVetPrueba ? Roles::VETERINARIO : Roles::ADMIN;
$_SESSION = ['id_usuario' => $esVetPrueba ? 2 : 1, 'usuario_nombre' => $esVetPrueba ? 'Beto Norte' : 'Ana Norte'];
$contexto = Contexto::deClinica(1, 'Clínica Norte (prueba)', $rolPrueba);
Contexto::activar($contexto, 1);
// Con «uno» la persona solo tiene este contexto: la pastilla de la clínica no es enlace.
$_SESSION['contextos_total'] = ($argv[2] ?? '') === 'uno' ? 1 : 2;
Security::definirFuenteDeContextos(static fn (): array => [$contexto, Contexto::deClinica(2, 'Clínica Sur', $rolPrueba)]);
if ($esVetPrueba) {
    define('ZOOKI_CABECERA_VET_PRUEBA', true);
    $_GET = ['action' => 'vet_agenda'];
    $content_view = __FILE__;
    require __DIR__ . '/../../../views/vet/layout.php';
    return;
}
$db->exec("UPDATE propietario_clinica SET estado = 'inactivo' WHERE id_propietario = 6 AND id_clinica = 1");
$controller = new UsuarioController($db);
$personal = $controller->listar();
$propietarios = $controller->listarPropietarios();
$invitaciones = [['id_invitacion' => 11, 'id_rol' => Roles::VETERINARIO, 'tipo_documento' => 'CC', 'documento' => '1098765432', 'nombre_completo' => 'Valeria Invitada', 'email' => 'valeria@zooki.test', 'telefono' => '3001234567']];
$_GET = ['action' => 'admin_usuarios'];
$content_view = __DIR__ . '/../../../views/admin/usuarios.php';
require __DIR__ . '/../../../views/admin/layout.php';
