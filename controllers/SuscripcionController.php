<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/Security.php';
require_once __DIR__ . '/../models/Suscripcion.php';

final class SuscripcionController
{
    public function __construct(private PDO $db, private mixed $reloj = null)
    {
    }

    public function vista(): void
    {
        Security::autorizar('admin_suscripcion');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            return;
        }
        $servicio = new Suscripcion($this->db, (int) Contexto::clinicaActiva(), $this->reloj);
        $estadoSuscripcion = $servicio->estado();
        $profesionalSuscripcion = $servicio->profesional();
        $content_view = __DIR__ . '/../views/admin/suscripcion.php';
        require __DIR__ . '/../views/admin/layout.php';
    }
}
