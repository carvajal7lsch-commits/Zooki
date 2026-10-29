<?php
// HTTP aislado sin bootstrap para ejercer el shutdown real; el único mailer es falso.
require_once __DIR__ . '/../../Support/PlataformaFixture.php';
require_once __DIR__ . '/../../../models/CasoSoporte.php';
putenv('APP_URL=http://localhost/index.php');
$db = PlataformaFixture::sqlite();
$modo = $_GET['modo'] ?? 'sin';
if ($modo === 'sin') $db->exec('UPDATE usuarios SET es_super_admin = 0');
$correo = static function ($email, $asunto, $cuerpo) use ($db, $modo): bool {
    usleep(1500000);
    $datos = ['estado' => $db->query('SELECT estado FROM casos_soporte')->fetchColumn(), 'asunto' => $asunto, 'cuerpo' => $cuerpo];
    file_put_contents(getenv('ZOOKI_SOPORTE_EVIDENCIA'), json_encode($datos));
    if ($modo === 'fallo') throw new RuntimeException('SMTP falso caído');
    return true;
};
(new CasoSoporte($db, $correo))->documentoDuplicado(6, '1000000001', 'RN-G24');
http_response_code(422);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'message' => 'No se pudo completar el perfil. Revisa los datos o contacta a soporte.']);
