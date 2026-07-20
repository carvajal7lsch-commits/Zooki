<?php
require_once '../config/Database.php';
require_once '../models/Vacuna.php';

class VacunaController {
    private $db;
    private $vacunaModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->vacunaModel = new Vacuna($this->db);
    }

    public function registrarAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'id_mascota' => $_POST['id_mascota'],
                'nombre_vacuna' => $_POST['nombre_vacuna'],
                'laboratorio' => $_POST['laboratorio'],
                'lote' => $_POST['lote'],
                'fecha_aplicacion' => $_POST['fecha_aplicacion'],
                'fecha_proxima_dosis' => !empty($_POST['fecha_proxima']) ? $_POST['fecha_proxima'] : null,
                'observaciones' => $_POST['observaciones']
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
