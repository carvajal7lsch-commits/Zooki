<?php
/**
 * RN-410 — Avisa de las atenciones que quedaron abiertas y pasa a "sin cerrar"
 * las que terminaron el día sin cerrarse.
 *
 * Se programa cada 5 minutos (scripts/zooki.cron o el Programador de tareas de
 * Windows). El calendario hace la misma revisión al cargarse, así que sin esta
 * tarea el sistema sigue funcionando, pero los avisos solo salen cuando alguien
 * abre la agenda.
 */

require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/helpers/VigilanteAtenciones.php';

$db = (new Database())->getConnection();
$ahora = new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA));

$resultado = (new VigilanteAtenciones($db))->revisar($ahora);

echo $ahora->format('Y-m-d H:i')
    . " · avisos de atención abierta: {$resultado['avisadas']}"
    . " · pasadas a sin cerrar: {$resultado['sin_cerrar']}\n";
