<?php
require_once '../config/Database.php';
require_once '../models/Mascota.php';
require_once '../models/Cita.php';
require_once '../models/Vacuna.php';
require_once '../models/Consulta.php';
require_once '../models/Usuario.php';
require_once '../models/Desparasitacion.php';
require_once '../models/Auditoria.php';
require_once '../config/EmailService.php';
require_once '../helpers/PoliticaPassword.php';

class PropietarioController {
    private $db;
    private $mascotaModel;
    private $citaModel;
    private $vacunaModel;
    private $consultaModel;
    private $desparasitacionModel;
    private $usuarioModel;
    private $auditoria;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->mascotaModel = new Mascota($this->db);
        $this->citaModel = new Cita($this->db);
        $this->vacunaModel = new Vacuna($this->db);
        $this->consultaModel = new Consulta($this->db);
        $this->desparasitacionModel = new Desparasitacion($this->db);
        $this->usuarioModel = new Usuario($this->db);
        $this->auditoria = new Auditoria($this->db);
    }

    /**
     * Valida los datos de alta de un propietario (HU-02).
     *
     * M1-03 — Antes no se comprobaba nada: los campos se leian directo de
     * $_POST y el documento o el correo duplicados llegaban hasta la base de
     * datos, donde la restriccion de unicidad lanzaba una PDOException sin
     * capturar. El resultado era una respuesta vacia que rompia el `.json()`
     * del navegador, sin decirle al usuario que el propietario ya existia.
     *
     * Devuelve el mensaje de error, o null si todo esta correcto.
     */
    private function validarDatosPropietario(array $entrada, ?array &$limpios): ?string {
        $limpios = [];

        $requeridos = [
            'documento'       => 'El documento es obligatorio.',
            'tipo_documento'  => 'El tipo de documento es obligatorio.',
            'nombre_completo' => 'El nombre completo es obligatorio.',
            'telefono'        => 'El telefono es obligatorio.',
            'email'           => 'El correo electronico es obligatorio.',
        ];

        foreach ($requeridos as $campo => $mensaje) {
            $valor = trim((string) ($entrada[$campo] ?? ''));
            if ($valor === '') return $mensaje;
            $limpios[$campo] = $valor;
        }

        if (!preg_match('/^\d{5,15}$/', $limpios['documento'])) {
            return 'El documento debe tener entre 5 y 15 digitos.';
        }
        if (!filter_var($limpios['email'], FILTER_VALIDATE_EMAIL)) {
            return 'El correo electronico no tiene un formato valido.';
        }
        if (!preg_match('/^[0-9+\s-]{7,20}$/', $limpios['telefono'])) {
            return 'El telefono no tiene un formato valido.';
        }
        if (mb_strlen($limpios['nombre_completo']) < 3 || mb_strlen($limpios['nombre_completo']) > 100) {
            return 'El nombre completo debe tener entre 3 y 100 caracteres.';
        }
        if (!in_array($limpios['tipo_documento'], ['CC', 'CE', 'TI', 'PP', 'NIT'], true)) {
            return 'El tipo de documento no es valido.';
        }

        // Criterio de HU-02 / RN-G06: ni documento ni correo duplicados.
        if ($this->usuarioModel->getById($limpios['documento'])) {
            return 'Ese documento ya esta registrado en el sistema.';
        }
        if ($this->usuarioModel->getUserByEmail($limpios['email'])) {
            return 'Ese correo electronico ya esta registrado en el sistema.';
        }

        return null;
    }

    /**
     * Crea el propietario y devuelve [ok, mensaje].
     *
     * M1-01 — La contrasena inicial ya no es el numero de documento. RN-G10
     * prohibe expresamente que la clave contenga el documento del titular, y
     * ademas el documento aparece en los listados de pacientes y propietarios:
     * cualquiera del personal que viera esa pantalla podia entrar como
     * cualquier propietario. Ahora se genera una temporal que cumple la
     * politica, se envia por correo y queda marcada como obligatoria de
     * cambiar en el primer ingreso.
     *
     * M1-17 — Un solo metodo para las dos rutas (formulario y AJAX), que antes
     * eran el mismo codigo copiado.
     */
    private function crearPropietario(array $datos): array {
        $temporal = PoliticaPassword::generarTemporal();

        $creado = $this->usuarioModel->create($datos + [
            'password' => password_hash($temporal, PASSWORD_DEFAULT),
            'id_rol' => 4,
            'estado' => 1,
            'debe_cambiar_password' => 1,
        ]);

        if (!$creado) {
            return [false, 'No se pudo registrar el propietario. Intenta nuevamente.'];
        }

        // RN-G05: el alta queda registrada en auditoria.
        $this->auditoria->log(
            $_SESSION['usuario_doc'] ?? 'sistema',
            'INSERT',
            'usuarios',
            $datos['documento'],
            null,
            [
                'nombre_completo' => $datos['nombre_completo'],
                'email' => $datos['email'],
                'id_rol' => 4,
            ],
            'Propietario registrado desde recepcion'
        );

        $emailService = new EmailService();
        $enviado = $emailService->enviarCredencialesUsuario(
            $datos['email'],
            $datos['nombre_completo'],
            $datos['documento'],
            $temporal
        );

        return [true, $enviado
            ? 'Propietario registrado. Se enviaron sus credenciales de acceso al correo indicado.'
            : 'Propietario registrado, pero no se pudo enviar el correo con sus credenciales.'];
    }

    public function registrar() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $error = $this->validarDatosPropietario($_POST, $datos);
            if ($error !== null) {
                $_SESSION['error_message'] = $error;
                header("Location: index.php?action=nuevo_propietario");
                exit();
            }

            try {
                [$ok, $mensaje] = $this->crearPropietario($datos);
            } catch (Exception $e) {
                error_log('Error al registrar propietario: ' . $e->getMessage());
                $ok = false;
                $mensaje = 'No se pudo registrar el propietario. Intenta nuevamente.';
            }

            $_SESSION[$ok ? 'success_message' : 'error_message'] = $mensaje;
            header("Location: index.php?action=nuevo_propietario");
            exit();
        }
    }

    public function registrarAjax() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            header('Content-Type: application/json');

            $error = $this->validarDatosPropietario($_POST, $datos);
            if ($error !== null) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit();
            }

            try {
                [$ok, $mensaje] = $this->crearPropietario($datos);
            } catch (Exception $e) {
                // M1-11: sin este catch, la excepcion dejaba el cuerpo vacio y
                // el navegador reventaba al parsear el JSON.
                error_log('Error al registrar propietario: ' . $e->getMessage());
                $ok = false;
                $mensaje = 'No se pudo registrar el propietario. Intenta nuevamente.';
            }

            echo json_encode(['success' => $ok, 'message' => $mensaje]);
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

        // Obtener todas las citas, vacunas y desparasitaciones de todas las mascotas
        $todas_citas = [];
        $todas_vacunas = [];
        $todas_desparasitaciones = [];
        foreach ($mascotas as $m) {
            $citasMascota = $this->citaModel->getByMascota($m['id_mascota']);
            if (is_array($citasMascota)) {
                foreach ($citasMascota as $c) {
                    $c['nombre_mascota'] = $m['nombre'];
                    $c['foto_mascota'] = $m['url_foto'] ? 'uploads/mascotas/' . htmlspecialchars($m['url_foto']) : null;
                    $todas_citas[] = $c;
                }
            }

            $vacunasMascota = $this->vacunaModel->findByMascota($m['id_mascota']);
            if (is_array($vacunasMascota)) {
                foreach ($vacunasMascota as $v) {
                    $v['nombre_mascota'] = $m['nombre'];
                    $v['foto_mascota'] = $m['url_foto'] ? 'uploads/mascotas/' . htmlspecialchars($m['url_foto']) : null;
                    $todas_vacunas[] = $v;
                }
            }

            $desparasitacionesMascota = $this->desparasitacionModel->findByMascota($m['id_mascota']);
            if (is_array($desparasitacionesMascota)) {
                foreach ($desparasitacionesMascota as $d) {
                    $d['nombre_mascota'] = $m['nombre'];
                    $d['foto_mascota'] = $m['url_foto'] ? 'uploads/mascotas/' . htmlspecialchars($m['url_foto']) : null;
                    $todas_desparasitaciones[] = $d;
                }
            }
        }

        // Ordenar todas las citas por fecha/hora descendente
        usort($todas_citas, function($a, $b) {
            return strtotime($b['fecha'] . ' ' . $b['hora']) - strtotime($a['fecha'] . ' ' . $a['hora']);
        });

        // Ordenar todas las vacunas por fecha descendente
        usort($todas_vacunas, function($a, $b) {
            return strtotime($b['fecha_aplicacion']) - strtotime($a['fecha_aplicacion']);
        });

        // Ordenar todas las desparasitaciones por fecha descendente
        usort($todas_desparasitaciones, function($a, $b) {
            return strtotime($b['fecha_aplicacion']) - strtotime($a['fecha_aplicacion']);
        });

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
                'color' => $oldData['color'] ?? '',
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

    public function getDetalleCitaClinicaAjax() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }

        $id_cita = $_GET['id_cita'] ?? null;
        if (!$id_cita) {
            echo json_encode(['success' => false, 'message' => 'ID de cita requerido']);
            exit();
        }

        $cita = $this->citaModel->getById($id_cita);
        if (!$cita) {
            echo json_encode(['success' => false, 'message' => 'Cita no encontrada']);
            exit();
        }

        $mascota = $this->mascotaModel->getById($cita['id_mascota']);
        if (!$mascota || $mascota['doc_propietario'] !== $_SESSION['usuario_doc']) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit();
        }

        $tipoInfo = null;
        if (!empty($cita['id_tipo_cita'])) {
            $tipoInfo = $this->citaModel->getTipoCitaById($cita['id_tipo_cita']);
        }
        $nombreTipo = $tipoInfo ? ($tipoInfo['nombre_tipo'] ?? $tipoInfo['nombre'] ?? 'Consulta') : 'Consulta';

        $response = [
            'success' => true,
            'cita' => [
                'id_cita' => $cita['id_cita'],
                'fecha' => $cita['fecha'],
                'hora' => $cita['hora'],
                'estado' => $cita['estado'],
                'nombre_mascota' => $mascota['nombre'],
                'foto_mascota' => $mascota['url_foto'] ? 'uploads/mascotas/' . $mascota['url_foto'] : null,
                'nombre_tipo' => $nombreTipo,
                'veterinario' => $cita['veterinario_nombre'] ?? 'Veterinario'
            ],
            'consulta' => null,
            'tratamientos' => []
        ];

        if ($cita['estado'] === 'completada') {
            $consulta = $this->consultaModel->findByCita($id_cita);
            if ($consulta) {
                $response['consulta'] = [
                    'motivo_consulta' => $consulta['motivo_consulta'],
                    'anamnesis' => $consulta['anamnesis'],
                    'peso' => $consulta['peso'],
                    'temperatura' => $consulta['temperatura'],
                    'frecuencia_cardiaca' => $consulta['frecuencia_cardiaca'],
                    'diagnostico' => $consulta['diagnostico'],
                    'plan_tratamiento' => $consulta['plan_tratamiento'],
                    'fecha_hora' => $consulta['fecha_hora']
                ];

                require_once __DIR__ . '/../models/Tratamiento.php';
                $tratamientoModel = new Tratamiento($this->db);
                $response['tratamientos'] = $tratamientoModel->findByConsulta($consulta['id_consulta']);
            }
        }

        echo json_encode($response);
        exit();
    }

    public function imprimirHistorial() {
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            header("Location: index.php?action=login");
            exit();
        }

        $id_mascota = $_GET['id_mascota'] ?? null;
        if (!$id_mascota) {
            echo "Mascota no especificada.";
            exit();
        }

        $mascota = $this->mascotaModel->getById($id_mascota);
        if (!$mascota || $mascota['doc_propietario'] !== $_SESSION['usuario_doc']) {
            echo "Acceso denegado o mascota no encontrada.";
            exit();
        }

        $vacunas = $this->vacunaModel->findByMascota($id_mascota) ?: [];
        $desparasitaciones = $this->desparasitacionModel->findByMascota($id_mascota) ?: [];
        $consultas = $this->consultaModel->findByMascota($id_mascota) ?: [];

        require_once __DIR__ . '/../views/portal/imprimir_historial.php';
    }

    public function actualizarDatosContactoAjax() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit();
        }

        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $documento = $_SESSION['usuario_doc'];

        if (empty($email) || empty($telefono)) {
            echo json_encode(['success' => false, 'message' => 'El correo y el teléfono son requeridos.']);
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'El correo electrónico no es válido.']);
            exit();
        }

        $existente = $this->usuarioModel->getUserByEmailExcluding($email, $documento);
        if ($existente) {
            echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado por otro usuario.']);
            exit();
        }

        if ($this->usuarioModel->updateContactInfo($documento, $email, $telefono)) {
            echo json_encode([
                'success' => true,
                'message' => 'Datos de contacto actualizados correctamente.',
                'email' => $email,
                'telefono' => $telefono
            ]);
            exit();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar los datos de contacto.']);
            exit();
        }
    }
}
?>

