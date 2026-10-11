<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Vacuna.php';
require_once __DIR__ . '/../models/Mascota.php';
require_once __DIR__ . '/../models/CatalogoClinica.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';

/** C4: vacunas en la clínica activa (HU-3.1, RN-112, RN-207). */
class VacunaController
{
    private PDO $db;
    private Vacuna $vacunaModel;
    private Mascota $mascotaModel;
    private CatalogoClinica $catalogo;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->vacunaModel = new Vacuna($this->db);
        $this->mascotaModel = new Mascota($this->db);
        $this->catalogo = new CatalogoClinica($this->db);
    }

    public function registrarAjax(): void
    {
        RespuestaJson::modificacion(function () {
            $idVacuna = $this->vacunaModel->registrar($_POST);
            return ['id_vacuna' => $idVacuna, 'message' => 'Vacuna registrada con éxito.'];
        }, 'C4 vacunas');
    }

    /** Vacunas del catálogo de la clínica para la especie de la mascota. */
    public function getVacunasPorEspecieAjax(): void
    {
        $idMascota = ValidadorClinico::id($_GET['id_mascota'] ?? null);
        if ($idMascota === null) {
            RespuestaJson::error(422, 'Mascota no válida.');
            return;
        }

        $mascota = $this->mascotaModel->getById($idMascota);
        $vacunas = $this->vacunaModel->getVacunasPorEspecie((int) $mascota['id_especie']);
        RespuestaJson::enviar(['success' => true, 'vacunas' => $vacunas]);
    }

    /** «Otra vacuna»: se agrega al catálogo de la clínica activa, para la especie de la mascota. */
    public function registrarNuevaVacunaAjax(): void
    {
        RespuestaJson::modificacion(function () {
            $idMascota = ValidadorClinico::id($_POST['id_mascota'] ?? null);
            if ($idMascota === null) {
                throw new InvalidArgumentException('Mascota no válida.');
            }
            $mascota = $this->mascotaModel->getById($idMascota);

            $nombre = trim((string) ($_POST['nombre_vacuna'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
            $idEspecie = (int) $mascota['id_especie'];
            $idVacunaBase = $this->catalogo->agregarVacuna($nombre, $descripcion === '' ? null : $descripcion, $idEspecie);

            return [
                'message' => 'Vacuna agregada al catálogo de la clínica.',
                'id_vacuna_base' => $idVacunaBase,
                'nombre_vacuna' => $nombre,
            ];
        }, 'C4 vacunas');
    }

    public function registrarNuevoLaboratorioAjax(): void
    {
        RespuestaJson::modificacion(function () {
            $nombre = trim((string) ($_POST['nombre_laboratorio'] ?? ''));
            $idLaboratorio = $this->catalogo->agregarLaboratorio($nombre);
            return [
                'message' => 'Laboratorio agregado al catálogo de la clínica.',
                'id_laboratorio' => $idLaboratorio,
                'nombre_laboratorio' => $nombre,
            ];
        }, 'C4 vacunas');
    }

    public function getLaboratoriosAjax(): void
    {
        RespuestaJson::enviar(['success' => true, 'laboratorios' => $this->catalogo->laboratorios()]);
    }
}
