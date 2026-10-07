<?php
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/AccesoDenegado.php';

/** RNF-11: resolver el alcance en los modelos y fallar cerrado sin contexto. */
abstract class ModeloClinica
{
    public function __construct(protected PDO $conn) {}

    protected function clinica(): int
    {
        $id = Contexto::clinicaActiva();
        if ($id === null || $id < 1) {
            throw new AccesoDenegado(403, 'Selecciona un contexto de clínica.');
        }
        return $id;
    }
}
