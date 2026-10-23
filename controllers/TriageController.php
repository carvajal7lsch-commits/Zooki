<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/GrafoConocimiento.php';
require_once __DIR__ . '/../models/PacienteDiagnostico.php';
require_once __DIR__ . '/../helpers/MotorTriage.php';
require_once __DIR__ . '/../helpers/BuscadorSintomas.php';
require_once __DIR__ . '/../helpers/ValidadorTriage.php';
require_once __DIR__ . '/../helpers/ValidadorContextoClinico.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';

/** C4: APIs de lectura/cálculo para M4; nunca escribe citas ni síntomas. */
final class TriageController
{
    private GrafoConocimiento $grafo;
    private PacienteDiagnostico $paciente;
    private DateTimeImmutable $fecha;
    private PDO $db;

    public function __construct(?PDO $db = null, ?DateTimeImmutable $fecha = null)
    {
        $db ??= (new Database())->getConnection();
        $this->db = $db;
        $this->grafo = new GrafoConocimiento($db);
        $this->paciente = new PacienteDiagnostico($db);
        $this->fecha = $fecha ?? new DateTimeImmutable('now', new DateTimeZone(ZonaHoraria::ZONA));
    }

    public function buscarAjax(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            RespuestaJson::error(405, 'Método no permitido.');
            return;
        }
        RespuestaJson::consulta(function (): void {
            $texto = $_GET['q'] ?? '';
            if (!is_string($texto) || !mb_check_encoding($texto, 'UTF-8') || mb_strlen($texto) > BuscadorSintomas::CONSULTA_MAX) {
                throw new InvalidArgumentException('La búsqueda admite hasta ' . BuscadorSintomas::CONSULTA_MAX . ' caracteres.');
            }
            $especie = $_GET['especie'] ?? null;
            $mapa = $this->grafo->mapa();
            $nodos = array_column($mapa['nodes'], null, 'codigo');
            if (isset($_GET['id_mascota'])) {
                // C5a: la consulta filtra por la especie de la ficha, con la misma
                // regla de acceso que las sugerencias (vínculo activo o mascota propia).
                $especie = $this->especieDelPaciente($_GET['id_mascota']);
            } elseif ($especie !== null && (!is_string($especie) || ($nodos[$especie]['tipo'] ?? null) !== 'especie')) {
                throw new InvalidArgumentException('Especie no válida.');
            }
            $tipos = ($_GET['exposiciones'] ?? '') === '1' ? ['sustancia', 'farmaco'] : ['sintoma'];
            RespuestaJson::enviar(['success' => true, 'sugerencias' => BuscadorSintomas::buscar($texto, $mapa['nodes'], $especie, $tipos), 'version' => $mapa['version']['hash'] ?? null]);
        }, 'C4 buscar síntomas');
    }

    /** Sin nodo de especie en el conocimiento, null: se ofrecen todos los síntomas. */
    private function especieDelPaciente($crudo): ?string
    {
        $id = ValidadorClinico::id($crudo);
        if ($id === null) {
            throw new InvalidArgumentException('Mascota no válida.');
        }
        return $this->paciente->contexto($id, $this->grafo->instantanea(), $this->fecha, [])['especie'];
    }

    public function calcularAjax(): void
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
            if (($_POST['agenda'] ?? '') === '1') {
                require_once __DIR__ . '/../models/SolicitudAgenda.php';
                $solicitudes = new SolicitudAgenda($this->db, fn () => $this->fecha);
                if (Contexto::rolActivo() === Roles::PROPIETARIO) {
                    $solicitudes = $solicitudes->enClinicaDelPropietario((int) ($_POST['id_clinica'] ?? 0));
                }
                RespuestaJson::enviar(['success' => true] + $solicitudes->evaluar($_POST));
                return;
            }
            $grafo = $this->grafo->instantanea();
            $paciente = $this->paciente->contexto($id, $grafo, $this->fecha, ValidadorContextoClinico::validar($_POST));
            $sintomas = ValidadorTriage::episodios($_POST['sintomas'] ?? [], false, $grafo);
            $exposiciones = ValidadorTriage::episodios($_POST['exposiciones'] ?? [], true, $grafo);
            if ($sintomas === [] && $exposiciones === []) {
                throw new InvalidArgumentException('Confirma al menos una marca del catálogo o «Otro / no sé». La nota no calcula el nivel.');
            }
            $resultado = MotorTriage::calcular($sintomas, $exposiciones, $paciente, $grafo, $this->fecha);
            RespuestaJson::enviar(['success' => true] + $resultado);
        }, 'C4 calcular triage');
    }
}
