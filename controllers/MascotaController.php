<?php

require_once '../config/Database.php';
require_once '../models/Mascota.php';
require_once '../models/Usuario.php';
require_once '../helpers/FotoMascota.php';

class MascotaController {
    private $db;
    private $mascotaModel;
    private $usuarioModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->mascotaModel = new Mascota($this->db);
        $this->usuarioModel = new Usuario($this->db);
    }

    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $id = $_POST['id_mascota'];
            $oldData = $this->mascotaModel->getById($id);
            
            if (!$oldData) {
                $this->redirectWithError("Error al identificar la mascota.");
            }

            $newData = [
                'id_mascota' => $id,
                'nombre' => trim($_POST['nombre']),
                'id_especie' => $_POST['especie'],
                'id_raza' => $_POST['raza'],
                'fecha_nacimiento' => !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null,
                'peso' => trim($_POST['peso']),
                'sexo' => $_POST['sexo'],
                'color' => '', // Legacy temporal
                'estado' => $_POST['estado'],
                'url_foto' => $oldData['url_foto'] // Por defecto mantenemos la vieja
            ];

            // Manejo de nueva foto si se sube. M1-02: esta era la peor de las
            // tres copias, sin validar extension ni tamano, y con la extension
            // tomada literal del nombre que enviaba el cliente.
            $foto_nombre = $this->procesarFotoMascota($newData['nombre'], $errorFoto);
            if ($foto_nombre === false) {
                $this->redirectWithError($errorFoto);
            }
            if ($foto_nombre !== null) {
                $newData['url_foto'] = $foto_nombre;
            }

            if ($this->mascotaModel->update($newData)) {
                // Registrar Auditoría (Solo campos que cambiaron)
                $camposAValidar = ['nombre', 'id_especie', 'id_raza', 'fecha_nacimiento', 'peso', 'sexo', 'estado'];
                foreach ($camposAValidar as $campo) {
                    if ($oldData[$campo] != $newData[$campo]) {
                        $this->mascotaModel->registrarAuditoria($id, $_SESSION['usuario_doc'], $campo, $oldData[$campo], $newData[$campo]);
                    }
                }

                // HU-15: si el personal cambia la raza, la que indicó el propietario ya quedó resuelta.
                if ($oldData['id_raza'] != $newData['id_raza']) {
                    $this->mascotaModel->guardarRazaIndicada((int) $id, null);
                }

                // Actualizar colores
                $colores = $_POST['colores'] ?? [];
                $this->mascotaModel->saveColores($id, $colores);

                $_SESSION['success_message'] = "¡Mascota actualizada correctamente!";
                header("Location: index.php?action=dashboard");
                exit();
            } else {
                $this->redirectWithError("Error al actualizar.");
            }
        }
    }

    public function listar() {
        return $this->mascotaModel->getAll();
    }

    public function listarMascotasAjax() {
        $mascotas = $this->mascotaModel->getAll();
        header('Content-Type: application/json');
        echo json_encode($mascotas);
        exit;
    }

    /**
     * HU-03 — Buscar paciente por nombre de mascota, nombre del propietario o
     * documento. El modelo filtra por estado = 1 (RN-105).
     *
     * M1-15: el minimo eran 2 caracteres y el criterio de HU-03 pide 3.
     */
    public function buscar() {
        header('Content-Type: application/json');
        $term = trim((string) ($_GET['query'] ?? ''));

        if (mb_strlen($term) < 3) {
            echo json_encode([]);
            return;
        }

        try {
            echo json_encode($this->mascotaModel->search($term));
        } catch (Throwable $e) {
            error_log('Error en la busqueda de mascotas: ' . $e->getMessage());
            echo json_encode([]);
        }
    }

    public function getMascotaAjax() {
        header('Content-Type: application/json');

        // M1-18: sin el parametro no se emitia ningun cuerpo, y el `.json()`
        // del navegador reventaba sobre una respuesta vacia.
        $id = $_GET['id'] ?? null;
        if (!ctype_digit((string) $id) || (int) $id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Mascota no valida.']);
            exit;
        }

        $mascota = $this->mascotaModel->getById((int) $id);
        if (!$mascota) {
            echo json_encode(['success' => false, 'message' => 'La mascota no existe.']);
            exit;
        }

        echo json_encode($mascota);
        exit;
    }

    public function actualizarAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        header('Content-Type: application/json');

        try {
            $id = $_POST['id_mascota'] ?? null;
            if (!ctype_digit((string) $id) || (int) $id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Mascota no valida.']);
                exit;
            }
            $id = (int) $id;

            $oldData = $this->mascotaModel->getById($id);
            if (!$oldData) {
                echo json_encode(['success' => false, 'message' => 'La mascota no existe.']);
                exit;
            }

            $error = $this->validarDatosMascota($_POST, $datos, false);
            if ($error !== null) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            }

            $estado = $_POST['estado'] ?? $oldData['estado'];
            if (!in_array((int) $estado, [0, 1], true)) {
                echo json_encode(['success' => false, 'message' => 'El estado indicado no es valido.']);
                exit;
            }

            // Cambio de dueno: si viene, tiene que ser un propietario real.
            $docPropietario = trim((string) ($_POST['doc_propietario'] ?? ''));
            if ($docPropietario !== '' && !$this->mascotaModel->esPropietarioValido($docPropietario)) {
                echo json_encode(['success' => false, 'message' => 'El propietario indicado no existe en el sistema.']);
                exit;
            }

            $foto = $this->procesarFotoMascota($datos['nombre'], $errorFoto);
            if ($foto === false) {
                echo json_encode(['success' => false, 'message' => $errorFoto]);
                exit;
            }

            // M1-07 — La ficha, los colores y la auditoria se escriben en una
            // sola transaccion: antes eran escrituras sueltas y un fallo a
            // mitad dejaba la mascota sin colores o sin rastro del cambio.
            $this->db->beginTransaction();
            try {
                if (isset($datos['nueva_raza'])) {
                    $datos['id_raza'] = $this->mascotaModel->obtenerOCrearRaza(
                        $datos['id_especie'],
                        $datos['nueva_raza']
                    );
                }

                $newData = [
                    'id_mascota' => $id,
                    'nombre' => $datos['nombre'],
                    'id_especie' => $datos['id_especie'],
                    'id_raza' => $datos['id_raza'],
                    'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? ($oldData['fecha_nacimiento'] ?? null),
                    'peso' => $datos['peso'],
                    'sexo' => $datos['sexo'],
                    'color' => '', // Legacy temporal
                    'estado' => (int) $estado,
                    'url_foto' => $foto ?? ($oldData['url_foto'] ?? null),
                ];
                if ($docPropietario !== '') {
                    $newData['doc_propietario'] = $docPropietario;
                }

                if (!$this->mascotaModel->update($newData)) {
                    throw new RuntimeException('No se pudo actualizar la mascota.');
                }

                // HU-15: si el personal cambia la raza, la que indicó el propietario ya quedó resuelta.
                if (($oldData['id_raza'] ?? null) != ($newData['id_raza'] ?? null)) {
                    $this->mascotaModel->guardarRazaIndicada((int) $id, null);
                }

                $this->mascotaModel->saveColores($id, $datos['colores']);

                // RN-108 / HU-04 — Auditoria de la ficha. M1-09: antes se
                // omitian fecha_nacimiento, doc_propietario, url_foto y los
                // colores, asi que un cambio de dueno o de foto no dejaba
                // ningun rastro pese a que el criterio pide registrar "el
                // campo modificado".
                $this->registrarCambiosMascota($id, $oldData, $newData, $datos['colores']);

                $this->db->commit();
            } catch (Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }

            // M1-16: la foto anterior se borra solo despues del commit. Antes
            // se quedaba en disco para siempre y la carpeta de subidas crecia
            // con una imagen huerfana por cada cambio de foto.
            if ($foto !== null) {
                $this->eliminarFotoAnterior($oldData['url_foto'] ?? null, $foto);
            }

            echo json_encode(['success' => true]);
            exit;
        } catch (Throwable $e) {
            error_log('Error al actualizar mascota: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la mascota. Intenta nuevamente.']);
            exit;
        }
    }

    /**
     * Registra en auditoria_mascotas los campos que realmente cambiaron
     * (RN-108). Los colores se comparan como conjunto porque viven en otra
     * tabla y no aparecen en la fila de la mascota.
     */
    private function registrarCambiosMascota(int $id, array $oldData, array $newData, array $coloresNuevos): void {
        $usuario = $_SESSION['usuario_doc'] ?? 'sistema';

        $campos = [
            'nombre', 'id_especie', 'id_raza', 'fecha_nacimiento',
            'peso', 'sexo', 'estado', 'doc_propietario', 'url_foto',
        ];

        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $newData)) continue;

            $antes = $oldData[$campo] ?? null;
            $despues = $newData[$campo];

            // Comparacion laxa a proposito: la base devuelve numeros como
            // cadenas ("12" vs 12) y eso no es un cambio real.
            if ($antes == $despues) continue;

            $this->mascotaModel->registrarAuditoria($id, $usuario, $campo, $antes, $despues);
        }

        $coloresAntes = array_filter(explode(',', (string) ($oldData['colores_ids'] ?? '')));
        sort($coloresAntes);
        $coloresDespues = array_map('strval', $coloresNuevos);
        sort($coloresDespues);

        if ($coloresAntes !== $coloresDespues) {
            $this->mascotaModel->registrarAuditoria(
                $id,
                $usuario,
                'colores',
                implode(',', $coloresAntes),
                implode(',', $coloresDespues)
            );
        }
    }

    /**
     * Valida los datos de una mascota (HU-01).
     *
     * M1-04 — El backend solo exigia nombre, especie y propietario, pero el
     * criterio de HU-01 pide ademas raza, fecha de nacimiento, peso, sexo y
     * color. Solo lo validaba el formulario, es decir la capa que un atacante
     * no ejecuta.
     *
     * M1-05 — Se comprueba que la raza pertenezca a la especie elegida
     * (RN-106); antes el id_raza se tomaba crudo del POST y podia ser de otra
     * especie.
     *
     * Devuelve el mensaje de error, o null si todo esta correcto.
     */
    private function validarDatosMascota(array $entrada, ?array &$limpios, bool $esAlta): ?string {
        $limpios = [];

        $nombre = trim((string) ($entrada['nombre'] ?? ''));
        if ($nombre === '') return 'El nombre de la mascota es obligatorio.';
        if (mb_strlen($nombre) > 60) return 'El nombre de la mascota es demasiado largo.';
        $limpios['nombre'] = $nombre;

        $especie = $entrada['especie'] ?? '';
        if (!ctype_digit((string) $especie) || (int) $especie <= 0) {
            return 'Debes seleccionar una especie.';
        }
        $limpios['id_especie'] = (int) $especie;

        $sexo = trim((string) ($entrada['sexo'] ?? ''));
        if (!in_array($sexo, ['M', 'H', 'Macho', 'Hembra'], true)) {
            return 'Debes indicar el sexo de la mascota.';
        }
        $limpios['sexo'] = $sexo;

        $peso = trim((string) ($entrada['peso'] ?? ''));
        if ($peso === '' || !is_numeric($peso) || (float) $peso <= 0 || (float) $peso > 500) {
            return 'El peso debe ser un numero mayor que cero.';
        }
        $limpios['peso'] = (float) $peso;

        $fecha = trim((string) ($entrada['fecha_nacimiento'] ?? ''));
        if ($fecha !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $fecha);
            if (!$d || $d->format('Y-m-d') !== $fecha) {
                return 'La fecha de nacimiento no es valida.';
            }
            if ($d > new DateTime('today')) {
                return 'La fecha de nacimiento no puede ser futura.';
            }
            $limpios['fecha_nacimiento'] = $fecha;
        } elseif ($esAlta) {
            return 'La fecha de nacimiento es obligatoria.';
        } else {
            $limpios['fecha_nacimiento'] = null;
        }

        // Raza: puede venir un id existente o 'Otra' + nombre nuevo.
        $raza = $entrada['raza'] ?? '';
        if ($raza === 'Otra') {
            $nuevaRaza = trim((string) ($entrada['nueva_raza'] ?? ''));
            if ($nuevaRaza === '') return 'Escribe el nombre de la nueva raza.';
            $limpios['nueva_raza'] = $nuevaRaza;
            $limpios['id_raza'] = null;
        } else {
            if (!ctype_digit((string) $raza) || (int) $raza <= 0) {
                return 'Debes seleccionar una raza.';
            }
            // RN-106: la raza tiene que ser de la especie elegida.
            if (!$this->mascotaModel->razaPerteneceAEspecie((int) $raza, $limpios['id_especie'])) {
                return 'La raza seleccionada no corresponde a la especie de la mascota.';
            }
            $limpios['id_raza'] = (int) $raza;
        }

        // RN-107: una mascota puede tener varios colores, pero al menos uno.
        $colores = $entrada['colores'] ?? [];
        if (!is_array($colores)) $colores = [$colores];
        $colores = array_values(array_filter(array_map('intval', $colores), fn($c) => $c > 0));
        if ($esAlta && empty($colores)) {
            return 'Debes indicar al menos un color.';
        }
        $limpios['colores'] = $colores;

        return null;
    }

    public function registrarAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        header('Content-Type: application/json');

        try {
            $doc_propietario = trim((string) ($_POST['doc_propietario'] ?? ''));
            if ($doc_propietario === '') {
                echo json_encode(['success' => false, 'message' => 'Debes asignar un propietario a la mascota.']);
                exit;
            }

            // RN-101: no puede existir una mascota sin propietario, y el
            // documento tiene que corresponder a un propietario real. Antes
            // solo se comprobaba que el campo no viniera vacio, asi que un
            // documento inexistente llegaba a la clave foranea y reventaba con
            // una excepcion sin capturar.
            if (!$this->mascotaModel->esPropietarioValido($doc_propietario)) {
                echo json_encode(['success' => false, 'message' => 'El propietario indicado no existe en el sistema.']);
                exit;
            }

            $error = $this->validarDatosMascota($_POST, $datos, true);
            if ($error !== null) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            }

            $foto = $this->procesarFotoMascota($datos['nombre'], $errorFoto);
            if ($foto === false) {
                echo json_encode(['success' => false, 'message' => $errorFoto]);
                exit;
            }

            // M1-07 (RN-107) — El alta y sus colores van en una transaccion.
            // Antes eran dos escrituras sueltas: si la segunda fallaba, la
            // mascota quedaba registrada sin ningun color y nadie se enteraba.
            $this->db->beginTransaction();
            try {
                if (isset($datos['nueva_raza'])) {
                    $datos['id_raza'] = $this->mascotaModel->obtenerOCrearRaza(
                        $datos['id_especie'],
                        $datos['nueva_raza']
                    );
                }

                $newId = $this->mascotaModel->insert([
                    // El numero de historia clinica se asigna en la primera
                    // consulta medica (RN-102), no en el alta.
                    'numero_historia_clinica' => '',
                    'doc_propietario' => $doc_propietario,
                    'nombre' => $datos['nombre'],
                    'id_especie' => $datos['id_especie'],
                    'id_raza' => $datos['id_raza'],
                    'fecha_nacimiento' => $datos['fecha_nacimiento'],
                    'peso' => $datos['peso'],
                    'sexo' => $datos['sexo'],
                    'color' => '', // Legacy temporal
                    'url_foto' => $foto,
                ]);

                if (!$newId) {
                    throw new RuntimeException('No se pudo insertar la mascota.');
                }

                $this->mascotaModel->saveColores($newId, $datos['colores']);
                $this->db->commit();
            } catch (Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }

            echo json_encode(['success' => true, 'id_mascota' => $newId]);
            exit;
        } catch (Throwable $e) {
            // M1-11: sin este catch la excepcion dejaba el cuerpo vacio y el
            // navegador reventaba al parsear el JSON.
            error_log('Error al registrar mascota: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudo registrar la mascota. Intenta nuevamente.']);
            exit;
        }
    }

    public function listarPropietariosAjax() {
        $owners = $this->usuarioModel->getAllOwnersWithPetCount();
        header('Content-Type: application/json');
        echo json_encode($owners);
        exit;
    }

    public function listarMascotasPorPropietarioAjax() {
        $doc = $_GET['doc'] ?? null;
        if ($doc) {
            $pets = $this->mascotaModel->getByPropietario($doc);
            header('Content-Type: application/json');
            echo json_encode($pets);
            exit;
        }
    }

    public function getPropietarioAjax() {
        $doc = $_GET['doc'] ?? null;
        if ($doc) {
            $owner = $this->usuarioModel->getById($doc);
            header('Content-Type: application/json');
            echo json_encode($owner);
            exit;
        }
    }

    public function actualizarPropietarioAjax() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'documento' => $_POST['documento'],
                'nombre_completo' => $_POST['nombre_completo'],
                'telefono' => $_POST['telefono'],
                'email' => $_POST['email'],
                'estado' => $_POST['estado']
            ];

            if ($this->usuarioModel->update($data)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar propietario']);
            }
        }
    }

    public function listarEspeciesAjax() {
        $especies = $this->mascotaModel->getEspecies();
        header('Content-Type: application/json');
        echo json_encode($especies);
        exit;
    }

    public function registrarEspecieAjax() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $nombre = trim((string) ($_POST['nombre_especie'] ?? ''));
            if (empty($nombre)) {
                echo json_encode(['success' => false, 'message' => 'El nombre de la especie no puede estar vacío.']);
                exit;
            }
            
            // M1-12: la consulta vive en el modelo, no aqui.
            $existente = $this->mascotaModel->buscarEspeciePorNombre($nombre);
            if ($existente !== null) {
                echo json_encode(['success' => true, 'id_especie' => $existente, 'nombre_especie' => $nombre]);
                exit;
            }

            $id = $this->mascotaModel->insertEspecie($nombre);
            if ($id) {
                echo json_encode(['success' => true, 'id_especie' => $id, 'nombre_especie' => $nombre]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al guardar la especie en la base de datos.']);
                exit;
            }
        }
    }

    public function listarRazasAjax() {
        $id_especie = $_GET['id_especie'] ?? null;
        if ($id_especie) {
            $razas = $this->mascotaModel->getRazasByEspecie($id_especie);
            header('Content-Type: application/json');
            echo json_encode($razas);
            exit;
        }
    }

    public function listarColoresAjax() {
        $colores = $this->mascotaModel->getColoresBase();
        header('Content-Type: application/json');
        echo json_encode($colores);
        exit;
    }

    /**
     * HU-04 / RN-104 / RN-105 — Marcar la mascota como activa o inactiva.
     *
     * No se elimina nunca: inactivarla la saca de las busquedas activas pero
     * conserva su historia clinica intacta.
     */
    public function cambiarEstadoAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        header('Content-Type: application/json');

        try {
            $id = $_POST['id_mascota'] ?? null;
            $est = $_POST['estado'] ?? null;

            if (!ctype_digit((string) $id) || (int) $id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Mascota no valida.']);
                exit;
            }
            if (!in_array((int) $est, [0, 1], true)) {
                echo json_encode(['success' => false, 'message' => 'El estado indicado no es valido.']);
                exit;
            }

            $id = (int) $id;
            $est = (int) $est;

            // M1-08: hay que comprobar que la mascota exista. updateStatus()
            // devuelve true aunque no afecte ninguna fila, asi que cambiar el
            // estado de una mascota inexistente se reportaba como exito.
            $estadoAnterior = $this->mascotaModel->getEstado($id);
            if ($estadoAnterior === null) {
                echo json_encode(['success' => false, 'message' => 'La mascota no existe.']);
                exit;
            }

            if ($estadoAnterior === $est) {
                echo json_encode(['success' => true, 'message' => 'La mascota ya tenia ese estado.']);
                exit;
            }

            if (!$this->mascotaModel->updateStatus($id, $est)) {
                echo json_encode(['success' => false, 'message' => 'Error al cambiar el estado de la mascota.']);
                exit;
            }

            $usuario = $_SESSION['usuario_doc'] ?? 'sistema';
            $etiqueta = $est === 1 ? 'activa' : 'inactiva';

            // M1-08: se registra el estado anterior real, no la cadena
            // 'desconocido' que dejaba el log inservible (mismo defecto que
            // T-11 en el Modulo T).
            require_once '../models/Auditoria.php';
            (new Auditoria($this->db))->log(
                $usuario,
                'UPDATE',
                'mascotas',
                $id,
                ['estado' => $estadoAnterior],
                ['estado' => $est],
                'Mascota marcada como ' . $etiqueta
            );

            // RN-108: la ficha de la mascota tiene su propia auditoria, y el
            // estado es un campo de la ficha.
            $this->mascotaModel->registrarAuditoria($id, $usuario, 'estado', $estadoAnterior, $est);

            echo json_encode(['success' => true, 'message' => 'La mascota quedo marcada como ' . $etiqueta . '.']);
            exit;
        } catch (Throwable $e) {
            error_log('Error al cambiar estado de mascota: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudo cambiar el estado. Intenta nuevamente.']);
            exit;
        }
    }

    /** La foto la guarda helpers/FotoMascota.php, compartido con el portal del propietario. */
    private function procesarFotoMascota(string $nombreMascota, ?string &$error) {
        return FotoMascota::guardar($_FILES['foto'] ?? null, $nombreMascota, $error);
    }

    private function eliminarFotoAnterior(?string $anterior, string $nueva): void {
        FotoMascota::eliminarAnterior($anterior, $nueva);
    }

    private function redirectWithError($message) {
        $_SESSION['error_message'] = $message;
        header("Location: index.php?action=nueva_mascota");
        exit();
    }
}
?>
