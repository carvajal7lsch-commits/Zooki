<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/ValidacionPrescripcion.php';
require_once __DIR__ . '/../models/SugerenciasTratamiento.php';
require_once __DIR__ . '/../models/NodosEnlazables.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

final class PrescripcionController
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db ??= (new Database())->getConnection();
    }

    /** M2 C5b: fármacos activos por etiqueta y sinónimo; solo sugiere, el veterinario elige. */
    public function buscarFarmacosAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            RespuestaJson::enviar(['success' => true, 'farmacos' => (new NodosEnlazables($this->db))->farmacos((string) ($_GET['q'] ?? ''))]);
        }, 'C5b buscar fármacos');
    }

    public function sugerirAjax(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            RespuestaJson::error(405, 'Método no permitido.');
            return;
        }
        RespuestaJson::consulta(function (): void {
            $id = ValidadorClinico::id($_POST['id_mascota'] ?? null);
            $diagnostico = $_POST['diagnostico'] ?? null;
            if ($id === null || !is_string($diagnostico) || strlen($diagnostico) > 100) {
                throw new InvalidArgumentException('Indica mascota y diagnóstico válidos.');
            }
            $valor = $_POST['peso'] ?? '';
            $peso = null;
            if ($valor !== '') {
                if (!is_scalar($valor) || !is_numeric($valor) || !is_finite((float) $valor) || (float) $valor < ValidadorClinico::PESO_MIN || (float) $valor > ValidadorClinico::PESO_MAX) {
                    throw new InvalidArgumentException(ValidadorClinico::AVISOS_CONSULTA['peso']);
                }
                $peso = (float) $valor;
            }
            $fecha = new DateTimeImmutable('now', new DateTimeZone(ZonaHoraria::ZONA));
            RespuestaJson::enviar(['success' => true] + (new SugerenciasTratamiento($this->db))->obtener($id, $diagnostico, $peso, $fecha));
        }, 'Sugerir tratamientos (HU-2.8/2.9)');
    }

    public function validarAjax(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            RespuestaJson::error(405, 'Método no permitido.');
            return;
        }
        RespuestaJson::consulta(function (): void {
            $id = ValidadorClinico::id($_POST['id_mascota'] ?? null);
            $filas = $_POST['tratamientos'] ?? null;
            if ($id === null || !is_array($filas) || !array_is_list($filas)) {
                throw new InvalidArgumentException('Indica la mascota y una lista de tratamientos.');
            }
            try {
                $evaluadas = (new ValidacionPrescripcion($this->db))->evaluar($id, $filas, new DateTimeImmutable('now', new DateTimeZone(ZonaHoraria::ZONA)));
            } catch (CampoInvalido $error) {
                RespuestaJson::error(422, $error->getMessage(), ['campo' => $error->campo, 'aviso' => $error->aviso]);
                return;
            }
            RespuestaJson::enviar(['success' => true, 'filas' => array_column($evaluadas, 'publica')]);
        }, 'C3 validar prescripción');
    }
}
