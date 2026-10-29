<?php
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../helpers/ReglaAtencion.php';
// Ejecuta el bloque de presentación real con un reloj fijo, sin añadir una entrada de prueba al portal.
$vista = file_get_contents(__DIR__ . '/../../../views/portal/index.php');
$inicio = strpos($vista, '$horaBienvenida =');
$fin = strpos($vista, '$mascotaPorId =', $inicio);
$hoy = (new DateTimeImmutable($argv[1]))->setTimezone(new DateTimeZone(ReglaAtencion::ZONA));
eval(substr($vista, $inicio, $fin - $inicio));
echo $saludoBienvenida;
