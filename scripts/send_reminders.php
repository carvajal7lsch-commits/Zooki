<?php
/**
 * HU-3.2/3.6: recordatorios por clínica, a las 07:00 America/Bogota.
 * La ventana, los tres intentos y las marcas de envío conservan la v1.11.0.
 * El MER todavía no ofrece una zona configurable para cada clínica.
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/config/EmailService.php';
require_once dirname(__DIR__) . '/helpers/EnviadorRecordatorios.php';

// Las pruebas incluyen este script con conexión, correo y reloj propios;
// no abren la base de la aplicación ni envían correo real.
$db = $db ?? (new Database())->getConnection();
$emailService = $emailService ?? new EmailService();
$ahora = $ahora ?? new DateTimeImmutable('now');
if (!isset($appUrl)) {
    $envFile = dirname(__DIR__) . '/.env';
    $env = is_file($envFile) ? parse_ini_file($envFile) : [];
    $appUrl = rtrim($env['APP_URL'] ?? 'https://zooki.secarvajal.com', '/') . '/index.php';
}
if (!$db instanceof PDO) {
    throw new RuntimeException('No se pudo abrir la base para los recordatorios.');
}
$enviador = new EnviadorRecordatorios(new Recordatorio($db), $emailService);
echo 'Inicio de recordatorios: ' . VentanaRecordatorio::hoy($ahora) . "\n";
$intentos = $enviador->ejecutar($ahora, $appUrl);
echo "Recordatorios terminados: $intentos intento(s).\n";
