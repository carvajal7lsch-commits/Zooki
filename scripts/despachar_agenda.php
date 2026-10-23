<?php
/** C4/B1: recuperación de eventos pendientes; no reenvía reclamados o inciertos. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/EventoAgenda.php';
require_once __DIR__ . '/../helpers/CorreoAgenda.php';
$db = (new Database())->getConnection();
EventoAgenda::despacharPendientes($db, [CorreoAgenda::class, 'enviar']);
