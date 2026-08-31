<?php

class LandingController {
    public function index() {
        // Renderizar la vista de la landing page
        require_once '../views/landing/index.php';
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
