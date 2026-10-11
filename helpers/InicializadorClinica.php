<?php
require_once __DIR__ . '/../models/CopiaCatalogosClinica.php';

/** RE-0.2.5 / D-1: punto compartido entre activación y datos de prueba. */
final class InicializadorClinica
{
    private CopiaCatalogosClinica $modelo;

    public function __construct(PDO $db)
    {
        $this->modelo = new CopiaCatalogosClinica($db);
    }

    public function copiar(int $idClinica): void
    {
        $this->modelo->copiar($idClinica);
    }
}
