<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/GrafoConocimiento.php';
require_once __DIR__ . '/../models/PacienteDiagnostico.php';
require_once __DIR__ . '/../helpers/MotorDiagnosticos.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

/** C2: consulta POST protegida por Security, sin guardar diagnóstico ni síntomas (RN-202). */
final class DiagnosticoController
{
    private GrafoConocimiento $grafo;
    private PacienteDiagnostico $paciente;
    private DateTimeImmutable $fecha;

    public function __construct(?PDO $db = null, ?DateTimeImmutable $fecha = null)
    {
        $db ??= (new Database())->getConnection();
        $this->grafo = new GrafoConocimiento($db);
        $this->paciente = new PacienteDiagnostico($db);
        $this->fecha = $fecha ?? new DateTimeImmutable('now', new DateTimeZone(ZonaHoraria::ZONA));
    }

    public function sugerirAjax(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            RespuestaJson::error(405, 'Método no permitido.');
            return;
        }
        RespuestaJson::consulta(function (): void {
            $id = ValidadorClinico::id($_POST['id_mascota'] ?? null);
            if ($id === null) {
                throw new InvalidArgumentException('Mascota no válida.');
            }
            $grafo = $this->grafo->instantanea();
            $contexto = $this->paciente->contexto($id, $grafo, $this->fecha, $this->declarados());
            $sintomas = $_POST['sintomas'] ?? null;
            if (!is_array($sintomas) || !array_is_list($sintomas)) {
                throw new InvalidArgumentException('Los síntomas deben ser una lista de códigos.');
            }
            $contexto['sintomas'] = [];
            foreach ($sintomas as $codigo) {
                if (!is_string($codigo) || ($grafo->nodos[$codigo]['tipo'] ?? null) !== 'sintoma') {
                    throw new InvalidArgumentException('Síntoma no válido, retirado o de otro tipo.');
                }
                $contexto['sintomas'][$codigo] = ['minutos' => null];
            }
            $respuesta = MotorDiagnosticos::sugerir($grafo, $contexto, $this->fecha);
            if ($respuesta['candidatos'] === []) {
                $respuesta['message'] = 'No hay coincidencias. Escribe el diagnóstico; es obligatorio para guardar la consulta.';
            }
            RespuestaJson::enviar(['success' => true] + $respuesta);
        }, 'C2 sugerir diagnósticos');
    }

    private function declarados(): array
    {
        $vacunacion = $_POST['vacunacion'] ?? null;
        if ($vacunacion !== null && !in_array($vacunacion, ['completa', 'incompleta', 'desconocida'], true)) {
            throw new InvalidArgumentException('Vacunación no válida.');
        }
        $dias = $_POST['dias_desde_celo'] ?? null;
        if ($dias !== null && ((!is_string($dias) && !is_int($dias) && !is_float($dias)) || !is_numeric($dias) || !is_finite((float) $dias) || (float) $dias < 0)) {
            throw new InvalidArgumentException('Días desde el celo no válidos.');
        }
        return ['vacunacion' => $vacunacion, 'dias_desde_celo' => $dias === null ? null : (float) $dias];
    }
}
