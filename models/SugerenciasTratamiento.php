<?php
require_once __DIR__ . '/ValidacionPrescripcion.php';

/** RE-2.9.4: lectura autorizada; la medicación protegida solo pasa por PrescripcionPublica. */
final class SugerenciasTratamiento
{
    public function __construct(private PDO $db)
    {
    }

    public function obtener(int $idMascota, string $diagnostico, ?float $peso, DateTimeImmutable $fecha): array
    {
        $medicacion = (new MedicacionVigente($this->db))->leer($idMascota, $fecha);
        $grafo = (new GrafoConocimiento($this->db))->instantanea();
        $contexto = (new PacienteDiagnostico($this->db))->contexto($idMascota, $grafo, $fecha, []);
        return MotorPrescripcion::sugerir($diagnostico, $peso, $contexto, $medicacion['alergias'], $medicacion['vigentes'], $grafo, $fecha);
    }
}
