<?php
require_once '../config/Database.php';
require_once '../models/Vacuna.php';
require_once '../models/Mascota.php';
require_once '../helpers/ValidadorClinico.php';

class VacunaController {
    private $db;
    private $vacunaModel;
    private $mascotaModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->vacunaModel = new Vacuna($this->db);
        $this->mascotaModel = new Mascota($this->db);
    }

    public function registrarAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');

            // HU-35: la mascota debe existir y estar activa. Antes se insertaba
            // lo que llegara en $_POST sin comprobar absolutamente nada.
            $idMascota = ValidadorClinico::id($_POST['id_mascota'] ?? null);
            if ($idMascota === null || $this->mascotaModel->getPropietarioSiActiva($idMascota) === null) {
                echo json_encode(['success' => false, 'message' => 'La mascota indicada no existe o está inactiva']);
                exit;
            }

            $nombreVacuna = ValidadorClinico::textoRequerido($_POST['nombre_vacuna'] ?? null, 150);
            if ($nombreVacuna === null) {
                echo json_encode(['success' => false, 'message' => 'El nombre de la vacuna es obligatorio']);
                exit;
            }

            // No se puede aplicar una vacuna en el futuro.
            $fechaAplicacion = ValidadorClinico::fechaNoFutura($_POST['fecha_aplicacion'] ?? null);
            if ($fechaAplicacion === null) {
                echo json_encode(['success' => false, 'message' => 'La fecha de aplicación no es válida o está en el futuro']);
                exit;
            }

            // La próxima dosis sí es futura, pero nunca anterior a la aplicación.
            $fechaProxima = null;
            if (!empty($_POST['fecha_proxima'])) {
                $fechaProxima = ValidadorClinico::fecha($_POST['fecha_proxima']);
                if ($fechaProxima === null || $fechaProxima < $fechaAplicacion) {
                    echo json_encode(['success' => false, 'message' => 'La fecha de próxima dosis no es válida o es anterior a la aplicación']);
                    exit;
                }
            }

            $data = [
                'id_mascota' => $idMascota,
                'nombre_vacuna' => $nombreVacuna,
                'laboratorio' => ValidadorClinico::textoOpcional($_POST['laboratorio'] ?? null, 150),
                'lote' => ValidadorClinico::textoOpcional($_POST['lote'] ?? null, 100),
                'fecha_aplicacion' => $fechaAplicacion,
                'fecha_proxima_dosis' => $fechaProxima,
                'observaciones' => ValidadorClinico::textoOpcional($_POST['observaciones'] ?? null, 5000)
            ];

            if ($this->vacunaModel->insert($data)) {
                echo json_encode(['success' => true, 'message' => 'Vacuna registrada con éxito']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al registrar la vacuna']);
            }
            exit;
        }
    }

    public function listarPendientesAjax() {
        $pendientes = $this->vacunaModel->getPendientesSemana();
        header('Content-Type: application/json');
        echo json_encode($pendientes);
        exit;
    }

    public function getVacunasPorEspecieAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id_mascota'])) {
            // Obtener la especie de la mascota
            $query = "SELECT id_especie FROM mascotas WHERE id_mascota = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $_GET['id_mascota']);
            $stmt->execute();
            $mascota = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($mascota) {
                $vacunas = $this->vacunaModel->getVacunasPorEspecie($mascota['id_especie']);
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'vacunas' => $vacunas]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Mascota no encontrada']);
            }
            exit;
        }
    }

    public function registrarNuevaVacunaAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre_vacuna = trim($_POST['nombre_vacuna']);
            $id_mascota = $_POST['id_mascota'];
            $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : null;

            if (empty($nombre_vacuna)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'El nombre de la vacuna es requerido']);
                exit;
            }

            // Obtener la especie de la mascota
            $query = "SELECT id_especie FROM mascotas WHERE id_mascota = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id_mascota);
            $stmt->execute();
            $mascota = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mascota) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Mascota no encontrada']);
                exit;
            }

            // Insertar nueva vacuna
            $id_vacuna_base = $this->vacunaModel->insertarNuevaVacuna($nombre_vacuna, $descripcion);

            if ($id_vacuna_base) {
                // Relacionar con la especie
                if ($this->vacunaModel->relacionarVacunaConEspecie($id_vacuna_base, $mascota['id_especie'])) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Vacuna registrada exitosamente',
                        'id_vacuna_base' => $id_vacuna_base,
                        'nombre_vacuna' => $nombre_vacuna
                    ]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error al relacionar vacuna con especie']);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error al registrar la vacuna']);
            }
            exit;
        }
    }

    public function registrarNuevoLaboratorioAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre_laboratorio = trim($_POST['nombre_laboratorio']);

            if (empty($nombre_laboratorio)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'El nombre del laboratorio es requerido']);
                exit;
            }

            // Insertar nuevo laboratorio
            $id_laboratorio = $this->vacunaModel->insertarNuevoLaboratorio($nombre_laboratorio);

            if ($id_laboratorio) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Laboratorio registrado exitosamente',
                    'id_laboratorio' => $id_laboratorio,
                    'nombre_laboratorio' => $nombre_laboratorio
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error al registrar el laboratorio']);
            }
            exit;
        }
    }

    public function getLaboratoriosAjax() {
        $query = "SELECT id_laboratorio, nombre_laboratorio FROM laboratorios_base WHERE estado = 1 ORDER BY nombre_laboratorio";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $laboratorios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'laboratorios' => $laboratorios]);
        exit;
    }

    // HU-20: Panel de vacunaciones pendientes agrupadas por día y especie
    public function getVacunasPendientesPanelAjax() {
        header('Content-Type: application/json');
        try {
            $grupos = $this->vacunaModel->getPendientesPorDiaYEspecie();
            
            // Organizar por día para el frontend
            $porDia = [];
            foreach ($grupos as $g) {
                $fecha = $g['fecha'];
                if (!isset($porDia[$fecha])) {
                    $porDia[$fecha] = [
                        'fecha' => $fecha,
                        'dia_semana' => $g['dia_semana'],
                        'especies' => []
                    ];
                }
                $porDia[$fecha]['especies'][] = [
                    'especie' => $g['especie'],
                    'total' => (int)$g['total'],
                    'mascotas' => $g['mascotas']
                ];
            }
            
            echo json_encode(['success' => true, 'pendientes' => array_values($porDia)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>
