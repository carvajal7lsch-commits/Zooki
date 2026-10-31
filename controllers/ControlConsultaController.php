<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/ControlConsulta.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';

final class ControlConsultaController
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db ??= (new Database())->getConnection();
    }

    private function mascota(): int
    {
        $id = ValidadorClinico::id($_GET['id_mascota'] ?? null);
        if ($id === null) {
            throw new InvalidArgumentException('Mascota no válida.');
        }
        return $id;
    }

    public function contextoAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            RespuestaJson::enviar(['success' => true] + (new ControlConsulta($this->db))->contexto($this->mascota()));
        }, 'Contexto de consulta en tres pasos');
    }

    public function fechasAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            if (!is_string($_GET['desde'] ?? null) || !is_string($_GET['hasta'] ?? null)) {
                throw new InvalidArgumentException('Indica las fechas del calendario.');
            }
            $dias = (new ControlConsulta($this->db))->diasAbiertos($this->mascota(), $_GET['desde'], $_GET['hasta']);
            RespuestaJson::enviar(['success' => true, 'dias_abiertos' => $dias]);
        }, 'Calendario del próximo control');
    }
}
