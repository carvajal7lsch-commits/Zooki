<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/LlegadaAgenda.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

final class LlegadaAgendaController
{
    private LlegadaAgenda $agenda;

    public function __construct(?PDO $db = null, ?callable $reloj = null)
    {
        $this->agenda = new LlegadaAgenda($db ?? (new Database())->getConnection(), $reloj);
    }

    private function id(array $datos, string $campo): int
    {
        $id = ValidadorClinico::id($datos[$campo] ?? null);
        if ($id === null) {
            throw new InvalidArgumentException('Selecciona un registro válido.');
        }
        return $id;
    }

    public function leer(): void
    {
        RespuestaJson::consulta(function (): void {
            $id = $_GET['id_solicitud'] ?? null;
            RespuestaJson::enviar(['success' => true, 'resultado' => $id === null ? $this->agenda->leer() : $this->agenda->alternativasLlegada($this->id($_GET, 'id_solicitud'))]);
        }, 'C8 llegadas');
    }

    public function escribir(string $accion): void
    {
        RespuestaJson::modificacion(function () use ($accion): array {
            if (!Csrf::validate() || (string) ($_POST['id_clinica'] ?? '') !== (string) Contexto::clinicaActiva()) {
                throw new AccesoDenegado(403, 'El formulario pertenece a otro contexto o está vencido.');
            }
            $clave = $_POST['clave_operacion'] ?? null;
            if (!is_string($clave)) {
                throw new InvalidArgumentException('Falta la clave de operación. Recarga.');
            }
            $r = match ($accion) {
                'registrar' => $this->agenda->llegada($this->id($_POST, 'id_cita'), $clave),
                'decidir' => $this->agenda->decidir($this->id($_POST, 'id_cita'), $_POST),
                'ajustar' => $this->agenda->ajustar($this->id($_POST, 'id_cita'), (string) ($_POST['nivel'] ?? ''), (string) ($_POST['motivo'] ?? ''), $clave),
                'presentar' => $this->agenda->presentar($this->id($_POST, 'id_solicitud'), $clave),
                'asignar' => $this->agenda->asignar($this->id($_POST, 'id_solicitud'), (string) ($_POST['hora'] ?? ''), $clave),
                default => throw new InvalidArgumentException('Acción no válida.'),
            };
            return ['resultado' => $r, 'message' => $r['message']];
        }, 'C8 llegadas');
    }
}
