<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/UrgenciaAgenda.php';
require_once __DIR__ . '/../models/ConciliacionAgenda.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

final class UrgenciaAgendaController
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
    }

    private function id(string $campo): int
    {
        $id = ValidadorClinico::id($_POST[$campo] ?? null);
        if ($id === null) {
            throw new InvalidArgumentException('Selecciona un registro válido.');
        }
        return $id;
    }

    public function leer(): void
    {
        RespuestaJson::consulta(function (): void {
            $urgencias = new UrgenciaAgenda($this->db);
            RespuestaJson::enviar(['success'=>true,'catalogos'=>$urgencias->catalogos(),'rojos'=>$urgencias->listar(),'ingresos'=>(new ConciliacionAgenda($this->db))->listar()]);
        }, 'C7 urgencias');
    }

    public function escribir(string $accion): void
    {
        RespuestaJson::modificacion(function () use ($accion): array {
            if (!Csrf::validate() || (string)($_POST['id_clinica'] ?? '') !== (string)Contexto::clinicaActiva()) {
                throw new AccesoDenegado(403,'El formulario pertenece a otro contexto o está vencido.');
            }
            $clave = $_POST['clave_operacion'] ?? null;
            if (!is_string($clave)) {
                throw new InvalidArgumentException('Falta la clave del comando. Recarga el formulario.');
            }
            $urgencias = new UrgenciaAgenda($this->db);
            $resultado = match ($accion) {
                'ingresar' => $urgencias->ingresar($_POST),
                'llegada' => $urgencias->acreditarLlegada($this->id('id_solicitud'),$clave),
                'atender' => $urgencias->atender($this->id('id_solicitud'),$this->id('id_tipo_cita'),(string)($_POST['decision'] ?? ''),$clave),
                'ordenar' => $urgencias->reordenar($this->id('id_solicitud'),(string)($_POST['motivo'] ?? ''),$clave),
                'revisar' => $urgencias->revisarContexto($this->id('id_solicitud'),(int)($_POST['version'] ?? -1),(string)($_POST['motivo'] ?? ''),$clave),
                'conciliar' => (new ConciliacionAgenda($this->db))->resolver($this->id('id_ingreso'),$_POST),
                default => throw new InvalidArgumentException('Acción no válida.'),
            };
            return ['resultado'=>$resultado,'message'=>$resultado['message']];
        }, 'C7 urgencias');
    }
}
