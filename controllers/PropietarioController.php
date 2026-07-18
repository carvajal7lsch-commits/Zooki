<?php
require_once '../config/Database.php';
require_once '../models/Mascota.php';
require_once '../models/Cita.php';
require_once '../models/Vacuna.php';
require_once '../models/Consulta.php';
require_once '../models/Usuario.php';
require_once '../models/Desparasitacion.php';

class PropietarioController {
    private $db;
    private $mascotaModel;
    private $citaModel;
    private $vacunaModel;
    private $consultaModel;
    private $desparasitacionModel;
    private $usuarioModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->mascotaModel = new Mascota($this->db);
        $this->citaModel = new Cita($this->db);
        $this->vacunaModel = new Vacuna($this->db);
        $this->consultaModel = new Consulta($this->db);
        $this->desparasitacionModel = new Desparasitacion($this->db);
        $this->usuarioModel = new Usuario($this->db);
    }

    public function registrar() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'documento' => $_POST['documento'],
                'tipo_documento' => $_POST['tipo_documento'],
                'nombre_completo' => $_POST['nombre_completo'],
                'telefono' => $_POST['telefono'],
                'email' => $_POST['email'],
                'password' => password_hash($_POST['documento'], PASSWORD_DEFAULT),
                'id_rol' => 4, // Propietario
                'estado' => 1
            ];

            if ($this->usuarioModel->create($data)) {
                $_SESSION['success_message'] = "Propietario registrado con éxito. Su contraseña inicial es su número de documento.";
                header("Location: index.php?action=nuevo_propietario");
            } else {
                $_SESSION['error_message'] = "Error al registrar el propietario.";
                header("Location: index.php?action=nuevo_propietario");
            }
            exit();
        }
    }

    public function registrarAjax() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'documento' => $_POST['documento'],
                'tipo_documento' => $_POST['tipo_documento'],
                'nombre_completo' => $_POST['nombre_completo'],
                'telefono' => $_POST['telefono'],
                'email' => $_POST['email'],
                'password' => password_hash($_POST['documento'], PASSWORD_DEFAULT),
                'id_rol' => 4,
                'estado' => 1
            ];

            if ($this->usuarioModel->create($data)) {
                echo json_encode(['success' => true, 'message' => 'Propietario registrado con éxito.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al registrar el propietario.']);
            }
            exit();
        }
    }

    public function index() {
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            header("Location: index.php?action=login");
            exit();
        }

        $doc_propietario = $_SESSION['usuario_doc'];
        $mascotas = $this->mascotaModel->getByPropietario($doc_propietario);

        foreach ($mascotas as &$m) {
            $m['proxima_cita'] = $this->citaModel->getProximaByMascota($m['id_mascota']);
        }
        unset($m);

        $nombre = $_SESSION['usuario_nombre'] ?? 'Propietario';
        $primer_nombre = explode(' ', trim($nombre))[0];
        
        // Obtener más detalles del usuario (correo, teléfono)
        $usuarioData = $this->usuarioModel->getUserByDocumento($doc_propietario);

        $view = '../views/portal/index.php';
        require_once '../views/portal/layout.php';
    }

    public function verDetalleMascotaAjax() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }

        $id_mascota = $_GET['id_mascota'];
        $doc_propietario = $_SESSION['usuario_doc'];

        // SEGURIDAD: Verificar que la mascota pertenece al propietario
        $mascota = $this->mascotaModel->getById($id_mascota);
        if (!$mascota || $mascota['doc_propietario'] !== $doc_propietario) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado. Esta mascota no le pertenece.']);
            exit();
        }

        // Obtener historial, citas y vacunas
        $historial = $this->consultaModel->findByMascota($id_mascota);
        $citas = $this->citaModel->getByMascota($id_mascota);
        $vacunas = $this->vacunaModel->findByMascota($id_mascota);
        $desparasitaciones = $this->desparasitacionModel->findByMascota($id_mascota);

        // Adjuntar archivos a cada consulta en el historial
        foreach ($historial as &$h) {
            $h['archivos'] = $this->consultaModel->getArchivosByConsulta($h['id_consulta']);
        }
        unset($h);

        $mascota['especie'] = $mascota['nombre_especie'] ?? '';
        $mascota['raza'] = $mascota['nombre_raza'] ?? '';

        echo json_encode([
            'success' => true,
            'mascota' => $mascota,
            'historial' => $historial,
            'citas' => $citas,
            'vacunas' => $vacunas,
            'desparasitaciones' => $desparasitaciones
        ]);
        exit();
    }

    public function registrarMascotaAjax() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $nombre = trim($_POST['nombre']);
            $especie = $_POST['especie'];
            $peso = trim($_POST['peso']);
            $doc_propietario = $_SESSION['usuario_doc'];

            if (empty($nombre) || empty($especie) || empty($peso)) {
                echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
                exit();
            }

            $foto_nombre = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] != UPLOAD_ERR_NO_FILE) {
                if ($_FILES['foto']['error'] != UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'Error al subir la foto de perfil.']);
                    exit();
                }

                $allowed = ['jpg', 'jpeg', 'png'];
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    echo json_encode(['success' => false, 'message' => 'Solo se permiten imágenes JPG o PNG.']);
                    exit();
                }

                if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'La foto no debe superar los 5MB.']);
                    exit();
                }

                $foto_nombre = time() . '_' . str_replace(' ', '_', $nombre) . '.' . $ext;
                $target_dir = '../public/uploads/mascotas/';
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                if (!move_uploaded_file($_FILES['foto']['tmp_name'], $target_dir . $foto_nombre)) {
                    echo json_encode(['success' => false, 'message' => 'Error al guardar la foto de perfil en el servidor.']);
                    exit();
                }
            }

            $id_raza = $_POST['raza'];
            if ($id_raza === 'Otra' && !empty($_POST['nueva_raza'])) {
                $id_raza = $this->mascotaModel->insertRaza($especie, $_POST['nueva_raza']);
            }

            $data = [
                'numero_historia_clinica' => '',
                'doc_propietario' => $doc_propietario,
                'nombre' => $nombre,
                'id_especie' => $especie,
                'id_raza' => $id_raza,
                'fecha_nacimiento' => !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null,
                'peso' => $peso,
                'sexo' => $_POST['sexo'],
                'color' => '',
                'url_foto' => $foto_nombre
            ];

            $newId = $this->mascotaModel->insert($data);
            if ($newId) {
                $colores = $_POST['colores'] ?? [];
                $this->mascotaModel->saveColores($newId, $colores);
                echo json_encode(['success' => true]);
                exit();
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al guardar en base de datos.']);
                exit();
            }
        }
    }

    public function actualizarMascotaAjax() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $id = $_POST['id_mascota'];
            $doc_propietario = $_SESSION['usuario_doc'];

            $oldData = $this->mascotaModel->getById($id);
            if (!$oldData || $oldData['doc_propietario'] !== $doc_propietario) {
                echo json_encode(['success' => false, 'message' => 'Acceso denegado. Esta mascota no le pertenece.']);
                exit();
            }

            $id_raza = $_POST['raza'];
            if ($id_raza === 'Otra' && !empty($_POST['nueva_raza'])) {
                $id_raza = $this->mascotaModel->insertRaza($_POST['especie'], $_POST['nueva_raza']);
            }

            $newData = [
                'id_mascota' => $id,
                'nombre' => trim($_POST['nombre']),
                'id_especie' => $_POST['especie'],
                'id_raza' => $id_raza,
                'fecha_nacimiento' => !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : ($oldData['fecha_nacimiento'] ?? null),
                'peso' => trim($_POST['peso']),
                'sexo' => $_POST['sexo'],
                'estado' => $oldData['estado'] ?? 1, // Mantener el estado actual
                'url_foto' => $oldData['url_foto'] ?? null
            ];

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] != UPLOAD_ERR_NO_FILE) {
                if ($_FILES['foto']['error'] != UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'Error al subir la nueva foto de perfil.']);
                    exit();
                }

                $allowed = ['jpg', 'jpeg', 'png'];
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    echo json_encode(['success' => false, 'message' => 'Solo se permiten imágenes JPG o PNG.']);
                    exit();
                }

                if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'La foto no debe superar los 5MB.']);
                    exit();
                }

                $foto_nombre = time() . '_' . str_replace(' ', '_', $newData['nombre']) . '.' . $ext;
                $target_dir = '../public/uploads/mascotas/';
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_dir . $foto_nombre)) {
                    $newData['url_foto'] = $foto_nombre;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al guardar la nueva foto en el servidor.']);
                    exit();
                }
            }

            if ($this->mascotaModel->update($newData)) {
                $colores = $_POST['colores'] ?? [];
                $this->mascotaModel->saveColores($id, $colores);

                // Auditoría de cambios
                $campos = ['nombre', 'id_especie', 'id_raza', 'peso', 'sexo'];
                foreach ($campos as $c) {
                    if (isset($oldData[$c]) && isset($newData[$c]) && $oldData[$c] != $newData[$c]) {
                        $this->mascotaModel->registrarAuditoria($id, $doc_propietario, $c, $oldData[$c], $newData[$c]);
                    }
                }

                echo json_encode(['success' => true]);
                exit();
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la mascota.']);
                exit();
            }
        }
    }
}
?>
