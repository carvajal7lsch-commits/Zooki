<?php
/** Guion real en la base temporal; no se abre la conexión configurada de la aplicación. */
require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../Integration/SemillaDemoTest.php';
$modo = $argv[1] ?? 'crear';
if ($modo === 'crear') {
    SemillaDemoTest::setUpBeforeClass();
}
$db = (new Database(Database::configuracionPruebas()))->getConnection();
$fecha = new DateTimeImmutable('2026-11-02T10:30:00-05:00');
$reloj = static fn () => $fecha;
if ($modo === 'crear') {
    if (ComandoSemillaDemo::ejecutar(['semilla_demo.php', '--entorno=local', '--si', '--dia=2026-11-02'], $reloj) !== 0) {
        exit(1);
    }
}
if (in_array($modo, ['crear', 'escena'], true)) {
    if (ComandoSemillaDemo::ejecutar(['semilla_demo.php', '--entorno=local', '--escena'], $reloj) !== 0) {
        exit(1);
    }
}
$filas = static function (string $sql, array $datos = []) use ($db): array {
    $q = $db->prepare($sql);
    $q->execute($datos);
    return $q->fetchAll(PDO::FETCH_ASSOC);
};
$clinica = (int) $filas('SELECT id_clinica FROM clinicas WHERE email_contacto=?', [GuionDemo::correo('sanroque-clinica')])[0]['id_clinica'];
$datos = [
    'clinica' => $clinica,
    'cuentas' => $filas('SELECT id_usuario,nombre_completo,email,version_sesion FROM usuarios WHERE email IN (?,?)', [GuionDemo::correo('sanroque-vet-1'), GuionDemo::correo('portal')]),
    'citas' => $filas("SELECT c.*,m.nombre FROM citas c JOIN mascotas m ON m.id_mascota=c.id_mascota WHERE c.id_clinica=? AND c.fecha='2026-11-02'", [$clinica]),
    'consultas' => (int) $db->query('SELECT COUNT(*) FROM consultas')->fetchColumn(),
    'tratamientos' => (int) $db->query('SELECT COUNT(*) FROM tratamientos')->fetchColumn(),
    'solicitudes_simon' => $filas("SELECT s.* FROM solicitudes_agenda s JOIN mascotas m ON m.id_mascota=s.id_mascota WHERE s.id_clinica=? AND m.nombre='Simón' AND JSON_UNQUOTE(JSON_EXTRACT(s.contexto_triage,'$.entrada.fecha'))='2026-11-02'", [$clinica]),
];
echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
