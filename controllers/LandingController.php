<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/CatalogoPlanes.php';
require_once __DIR__ . '/../models/Suscripcion.php';

class LandingController {
    public function __construct(private ?PDO $db = null) {
    }

    public function index() {
        $db = $this->db ?? (new Database())->getConnection();
        $clinica = Contexto::clinicaActiva();
        $oferta = $clinica === null || !(new Suscripcion($db, $clinica))->tienePagosAprobados();
        $tarifasPublicas = TarifaSuscripcion::textos(CatalogoPlanes::profesional($db), $oferta);
        // Renderizar la vista de la landing page
        require_once __DIR__ . '/../views/landing/index.php';
    }

    public function privacidad() {
        // Política de Tratamiento de Datos Personales (Ley 1581 de 2012)
        require_once '../views/legal/privacidad.php';
    }

    public function terminos() {
        // Términos y condiciones de uso del sistema
        require_once '../views/legal/terminos.php';
    }

    public function cookies() {
        // Cookies y almacenamiento local utilizados por el sistema
        require_once '../views/legal/cookies.php';
    }
}
