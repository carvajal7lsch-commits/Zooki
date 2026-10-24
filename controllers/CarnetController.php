<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/CarnetPropietario.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

/** HU-5.10: endpoints privados; el acceso público se implementa en B. */
final class CarnetController
{
    private CarnetPropietario $carnets;

    public function __construct(?PDO $db = null)
    {
        $this->carnets = new CarnetPropietario($db ?? (new Database())->getConnection());
    }

    private function id(array $entrada): int
    {
        $id = ValidadorClinico::id($entrada['id_mascota'] ?? null);
        if ($id === null) {
            throw new InvalidArgumentException('Selecciona una mascota válida.');
        }
        return $id;
    }

    private function sinCache(): void
    {
        header('Cache-Control: private, no-store, max-age=0');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
    }

    private function tarjeta(array $carnet, int $idMascota): string
    {
        ob_start();
        try {
            require __DIR__ . '/../views/portal/carnet.php';
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    public function vistaAjax(): void
    {
        $this->sinCache();
        RespuestaJson::consulta(function (): void {
            $idMascota = $this->id($_GET);
            RespuestaJson::enviar(['success' => true, 'html' => $this->tarjeta($this->carnets->obtener($idMascota), $idMascota)]);
        }, 'Carnet del propietario');
    }

    public function regenerarAjax(): void
    {
        $this->sinCache();
        RespuestaJson::modificacion(function (): array {
            $idMascota = $this->id($_POST);
            $carnet = $this->carnets->regenerar($idMascota, $_POST['token_visto'] ?? null);
            return ['html' => $this->tarjeta($carnet, $idMascota), 'regenerado' => $carnet['regenerado']];
        }, 'Regeneración del carnet');
    }

    public function svg(): void
    {
        $this->sinCache();
        RespuestaJson::consulta(function (): void {
            $carnet = $this->carnets->obtener($this->id($_GET));
            if (!$carnet['disponible']) {
                http_response_code(404);
                return;
            }
            $svg = QrCarnet::svg($carnet['token']);
            header('Content-Type: image/svg+xml; charset=utf-8');
            echo $svg;
        }, 'SVG del carnet');
    }

    public function imprimir(): void
    {
        $this->sinCache();
        RespuestaJson::consulta(function (): void {
            $idMascota = $this->id($_GET);
            $carnet = $this->carnets->obtener($idMascota);
            require __DIR__ . '/../views/portal/imprimir_carnet.php';
        }, 'Impresión del carnet');
    }
}
