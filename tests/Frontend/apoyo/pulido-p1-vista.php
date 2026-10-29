<?php
// Vistas reales con datos sintéticos: no se consulta la instalación ni se lee .env.
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../config/App.php';
require_once __DIR__ . '/../../../helpers/Csrf.php';
require_once __DIR__ . '/../../../helpers/ValidadorClinica.php';
require_once __DIR__ . '/../../../models/RegistroClinica.php';
putenv('TURNSTILE_SITE_KEY=');
putenv('GOOGLE_CLIENT_ID=');
$pantalla = $argv[1] ?? 'registro';
if (in_array($pantalla, ['atencion', 'pacientes'], true)) {
    $argv[1] = $pantalla === 'atencion' ? 'vet_atencion' : 'vet_pacientes';
    require __DIR__ . '/c81-vistas.php';
    return;
}
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$_SESSION = ['id_usuario' => 1, 'usuario_nombre' => 'Ana Norte'];
$clienteGoogleIdentidad = '';
if ($pantalla === 'registro') {
    require __DIR__ . '/../../../views/auth/registro_clinica.php';
    return;
}
if ($pantalla === 'plataforma') {
    require_once __DIR__ . '/../../Support/PlataformaFixture.php';
    require_once __DIR__ . '/../../../controllers/PlataformaController.php';
    $db = PlataformaFixture::sqlite();
    PlataformaFixture::datos($db);
    PlataformaFixture::plataforma();
    (new CasoSoporte($db))->documentoDuplicado(6, '1000000001', 'RN-G24');
    (new PlataformaController($db))->inicio();
    return;
}
if ($pantalla === 'portal') {
    Contexto::activar(Contexto::dePropietario(), 1);
    $primer_nombre = 'Fabio';
    $_SESSION['usuario_nombre'] = 'Fabio Propietario';
    $usuarioData = ['id_usuario' => 6, 'nombre_completo' => 'Fabio Propietario', 'email' => 'fabio@zooki.test', 'documento' => '1000000006', 'tipo_documento' => 'CC', 'telefono' => '3001112233', 'tiene_password' => true, 'tiene_google' => false];
    $cuenta = ['miembro_desde' => '2026', 'acceso_anterior' => 'ayer', 'fallidos_30' => 0, 'disponible' => true, 'actividad' => []];
    $mascotas = [];
    $agenda = ['proximas' => [], 'pasadas' => []];
    $proxima_cita = null;
    $proximo_salud = null;
    $todas_citas = [];
    $todas_vacunas = [];
    $todas_desparasitaciones = [];
    $clinicas_portal = [];
    $clinicas_disponibles = [];
    $catalogo_especies = [];
    $view = __DIR__ . '/../../../views/portal/index.php';
    require __DIR__ . '/../../../views/portal/layout.php';
    return;
}
Contexto::activar(Contexto::deClinica(1, 'Clínica Norte', Roles::ADMIN), 1);
$personal = [];
$propietarios = [];
$invitaciones = [];
$content_view = __DIR__ . '/../../../views/' . match ($pantalla) {
    'usuarios' => 'admin/usuarios.php',
    'horarios' => 'admin/horarios.php',
    'agenda' => 'vet/calendario.php',
};
$_GET = ['action' => match ($pantalla) { 'usuarios' => 'admin_usuarios', 'horarios' => 'admin_configuracion', default => 'admin_citas' }];
require __DIR__ . '/../../../views/admin/layout.php';
