<?php
require_once '../config/Database.php';
require_once '../models/Consulta.php';
require_once '../models/Mascota.php';
require_once '../models/Tratamiento.php';
require_once '../models/Vacuna.php';
require_once '../models/Desparasitacion.php';
require_once '../helpers/ValidadorClinico.php';

class ConsultaController {
    private $db;
    private $consultaModel;
    private $mascotaModel;
    private $tratamientoModel;
    private $vacunaModel;
    private $desparasitacionModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->consultaModel = new Consulta($this->db);
        $this->mascotaModel = new Mascota($this->db);
        $this->tratamientoModel = new Tratamiento($this->db);
        $this->vacunaModel = new Vacuna($this->db);
        $this->desparasitacionModel = new Desparasitacion($this->db);
    }

    // Listado global de consultas
    public function listar() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['usuario_doc'])) {
            header('Location: index.php');
            exit;
        }
        return $this->consultaModel->findAll();
    }

    // Registrar nueva consulta vía AJAX
    public function registrarAjax() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Validar si la sesión está activa (Evita el Fatal Error que rompe el JSON)
            if (!isset($_SESSION['usuario_doc'])) {
                echo json_encode(['success' => false, 'message' => 'Tu sesión ha expirado. Por favor, recarga la página e inicia sesión nuevamente.']);
                exit;
            }
            
            // HU-35: la mascota debe existir y estar activa antes de colgarle
            // nada. Sin esto, un id_mascota inventado creaba una consulta
            // huerfana o reventaba contra la llave foranea.
            $idMascota = ValidadorClinico::id($_POST['id_mascota'] ?? null);
            if ($idMascota === null || $this->mascotaModel->getPropietarioSiActiva($idMascota) === null) {
                echo json_encode(['success' => false, 'message' => 'La mascota indicada no existe o está inactiva']);
                exit;
            }

            // RN-202: una consulta no se guarda sin diagnóstico.
            $diagnostico = ValidadorClinico::textoRequerido($_POST['diagnostico'] ?? null, 5000);
            if ($diagnostico === null) {
                echo json_encode(['success' => false, 'message' => 'El diagnóstico es obligatorio']);
                exit;
            }

            // RN-203: la cita es opcional, pero si viene debe ser de ESTA
            // mascota; si no, la consulta quedaría atada a la cita de otra.
            $idCita = null;
            if (!empty($_POST['id_cita'])) {
                $idCita = ValidadorClinico::id($_POST['id_cita']);
                if ($idCita === null || !$this->citaPerteneceAMascota($idCita, $idMascota)) {
                    echo json_encode(['success' => false, 'message' => 'La cita indicada no corresponde a esta mascota']);
                    exit;
                }
            }

            // Signos vitales: se aceptan solo dentro de rangos plausibles. Un
            // valor fuera de rango es un error de digitación, y guardarlo
            // ensucia la historia clínica de forma permanente (RN-206).
            $signos = [
                'peso' => [$_POST['peso'] ?? null, ValidadorClinico::PESO_MIN, ValidadorClinico::PESO_MAX, 'El peso debe estar entre 0.01 y 200 kg'],
                'temperatura' => [$_POST['temperatura'] ?? null, ValidadorClinico::TEMP_MIN, ValidadorClinico::TEMP_MAX, 'La temperatura debe estar entre 25 y 45 °C'],
            ];
            $valores = [];
            foreach ($signos as $campo => [$crudo, $min, $max, $mensaje]) {
                if ($crudo === null || $crudo === '') { $valores[$campo] = null; continue; }
                $valores[$campo] = ValidadorClinico::decimal($crudo, $min, $max);
                if ($valores[$campo] === null) {
                    echo json_encode(['success' => false, 'message' => $mensaje]);
                    exit;
                }
            }

            $frecuencia = null;
            if (!empty($_POST['frecuencia_cardiaca'])) {
                $frecuencia = ValidadorClinico::entero($_POST['frecuencia_cardiaca'], ValidadorClinico::FC_MIN, ValidadorClinico::FC_MAX);
                if ($frecuencia === null) {
                    echo json_encode(['success' => false, 'message' => 'La frecuencia cardíaca debe estar entre 10 y 400 lpm']);
                    exit;
                }
            }

            $data = [
                'id_mascota' => $idMascota,
                'id_cita' => $idCita,
                'doc_veterinario' => $_SESSION['usuario_doc'],
                'motivo_consulta' => ValidadorClinico::textoOpcional($_POST['motivo'] ?? null, 5000),
                'anamnesis' => ValidadorClinico::textoOpcional($_POST['anamnesis'] ?? null, 5000),
                'peso' => $valores['peso'],
                'temperatura' => $valores['temperatura'],
                'frecuencia_cardiaca' => $frecuencia,
                'diagnostico' => $diagnostico,
                'plan_tratamiento' => ValidadorClinico::textoOpcional($_POST['plan_tratamiento'] ?? null, 5000)
            ];

            $res = $this->consultaModel->insert($data);

            if ($res) {
                // Lógica de Historia Clínica Única
                $mascota = $this->mascotaModel->getById($data['id_mascota']);
                if (empty($mascota['numero_historia_clinica'])) {
                    $nuevoHC = "HC-" . $data['id_mascota'] . "-" . date('Y');
                    $this->mascotaModel->actualizarHC($data['id_mascota'], $nuevoHC);
                }

                // Manejo de Archivos Adjuntos (Sprint 2)
                if (isset($_FILES['archivos'])) {
                    $totalFiles = count($_FILES['archivos']['name']);
                    for ($i = 0; $i < $totalFiles; $i++) {
                        if ($_FILES['archivos']['error'][$i] === UPLOAD_ERR_OK) {
                            $name = $_FILES['archivos']['name'][$i];
                            $tmpName = $_FILES['archivos']['tmp_name'][$i];
                            $size = $_FILES['archivos']['size'][$i];
                            $type = $_FILES['archivos']['type'][$i];
                            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            
                            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                            if (in_array($ext, $allowed) && $size <= 10 * 1024 * 1024) {
                                $newName = "CLI_" . $res . "_" . time() . "_" . $i . "." . $ext;
                                $targetPath = "../public/uploads/clinicos/" . $newName;
                                
                                if (move_uploaded_file($tmpName, $targetPath)) {
                                    $this->consultaModel->saveArchivo([
                                        'id_consulta' => $res,
                                        'nombre_original' => $name,
                                        'nombre_servidor' => $newName,
                                        'ruta_archivo' => 'uploads/clinicos/' . $newName,
                                        'tipo_archivo' => $type,
                                        'extension' => $ext,
                                        'tamano_bytes' => $size,
                                        'descripcion' => 'Adjunto de consulta'
                                    ]);
                                }
                            }
                        }
                    }
                }

                // Manejo de Tratamientos Farmacológicos (Sprint 2)
                if (isset($_POST['med_nombre']) && is_array($_POST['med_nombre'])) {
                    foreach ($_POST['med_nombre'] as $index => $med) {
                        if (!empty($med)) {
                            $this->tratamientoModel->insert([
                                'id_consulta' => $res,
                                'medicamento' => $med,
                                'dosis' => $_POST['med_dosis'][$index],
                                'via_administracion' => $_POST['med_via'][$index],
                                'duracion' => $_POST['med_duracion'][$index],
                                'observaciones' => $_POST['med_obs'][$index] ?? ''
                            ]);
                        }
                    }
                }

                echo json_encode(['success' => true, 'message' => 'Consulta registrada correctamente con sus adjuntos y tratamientos']);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al guardar la consulta']);
                exit;
            }
        }
    }

    // Listar historial de una mascota
    public function listarHistorialAjax() {
        $id = $_GET['id_mascota'] ?? null;
        if ($id) {
            // Datos de la mascota (para HC, nombre, etc)
            $mascota = $this->mascotaModel->getById($id);
            
            // Consultas clínicas
            $historial = $this->consultaModel->findByMascota($id);
            
            // Adjuntar archivos y tratamientos a cada consulta
            foreach ($historial as &$c) {
                $c['archivos'] = $this->consultaModel->getArchivosByConsulta($c['id_consulta']);
                $c['tratamientos'] = $this->tratamientoModel->findByConsulta($c['id_consulta']);
            }
            
            // Vacunas
            $vacunas = $this->vacunaModel->findByMascota($id);
            
            // Desparasitaciones
            $desparasitaciones = $this->desparasitacionModel->findByMascota($id);
            
            header('Content-Type: application/json');
            echo json_encode([
                'mascota' => $mascota,
                'consultas' => $historial,
                'vacunas' => $vacunas,
                'desparasitaciones' => $desparasitaciones
            ]);
            exit;
        }
    }
    public function getDashboardStatsAjax() {
        header('Content-Type: application/json');
        
        try {
            // Mascotas
            $stmt = $this->db->query("SELECT COUNT(*) FROM mascotas WHERE estado = 1");
            $pacientes = $stmt->fetchColumn();

            // Clientes (Propietarios)
            $stmt = $this->db->query("SELECT COUNT(*) FROM usuarios WHERE id_rol = 4 AND estado = 1");
            $clientes = $stmt->fetchColumn();

            // Citas hoy
            $hoy = date('Y-m-d');
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM citas WHERE fecha = :hoy AND estado != 'cancelada'");
            $stmt->execute(['hoy' => $hoy]);
            $citasHoy = $stmt->fetchColumn();

            // Consultas totales
            $stmt = $this->db->query("SELECT COUNT(*) FROM consultas");
            $consultas = $stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'stats' => [
                    'pacientes' => $pacientes,
                    'clientes' => $clientes,
                    'citasHoy' => $citasHoy,
                    'consultas' => $consultas
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    /**
     * HU-35 / RN-203: la cita que origina la consulta tiene que ser de la
     * misma mascota. Sin esta comprobación se podía adjuntar una consulta a
     * la cita de otro paciente pasando un id_cita cualquiera.
     */
    private function citaPerteneceAMascota($idCita, $idMascota) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM citas WHERE id_cita = :cita AND id_mascota = :mascota"
        );
        $stmt->bindValue(':cita', (int) $idCita, PDO::PARAM_INT);
        $stmt->bindValue(':mascota', (int) $idMascota, PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }
}
?>
