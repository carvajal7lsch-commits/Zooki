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
        if (!isset($_SESSION['usuario_doc'])) {
            header('Location: index.php?action=login');
            exit;
        }
        return $this->consultaModel->findAll();
    }

    /**
     * HU-06 / RN-204 — Revisa los adjuntos ANTES de tocar la base de datos.
     *
     * Devuelve [aceptados, rechazados]. Los rechazados llevan el nombre del
     * archivo y el motivo, uno por uno, porque es lo que pide HU-34: el
     * veterinario tiene que enterarse de que una radiografía no entró.
     *
     * Se valida antes de abrir la transacción a propósito: si un adjunto no
     * sirve, no se guarda nada y el veterinario corrige y reintenta con la
     * consulta completa, en vez de descubrir semanas después que la evidencia
     * no está.
     */
    private function revisarAdjuntos(): array {
        $aceptados = [];
        $rechazados = [];

        if (!isset($_FILES['archivos']) || !is_array($_FILES['archivos']['name'] ?? null)) {
            return [$aceptados, $rechazados];
        }

        $motivosSubida = [
            UPLOAD_ERR_INI_SIZE   => 'supera el tamaño máximo que admite el servidor',
            UPLOAD_ERR_FORM_SIZE  => 'supera el tamaño máximo permitido',
            UPLOAD_ERR_PARTIAL    => 'se subió incompleto',
            UPLOAD_ERR_NO_TMP_DIR => 'no se pudo guardar: falta la carpeta temporal del servidor',
            UPLOAD_ERR_CANT_WRITE => 'no se pudo escribir en el disco del servidor',
            UPLOAD_ERR_EXTENSION  => 'fue bloqueado por una extensión del servidor',
        ];

        // El tipo sale del contenido real, no de la extensión del nombre ni del
        // Content-Type, que los controla por completo quien sube el archivo.
        $imagenes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];

        $total = count($_FILES['archivos']['name']);
        for ($i = 0; $i < $total; $i++) {
            $nombre = $_FILES['archivos']['name'][$i];
            $error  = $_FILES['archivos']['error'][$i];

            if ($error === UPLOAD_ERR_NO_FILE) continue;

            if ($error !== UPLOAD_ERR_OK) {
                $rechazados[] = ['archivo' => $nombre, 'motivo' => $motivosSubida[$error] ?? 'no se pudo subir'];
                continue;
            }

            $tmp = $_FILES['archivos']['tmp_name'][$i];
            $tam = (int) $_FILES['archivos']['size'][$i];

            if ($tam <= 0) {
                $rechazados[] = ['archivo' => $nombre, 'motivo' => 'está vacío'];
                continue;
            }
            if ($tam > 10 * 1024 * 1024) {
                $rechazados[] = ['archivo' => $nombre, 'motivo' => 'supera los 10 MB permitidos'];
                continue;
            }

            $ext = null;
            $info = @getimagesize($tmp);
            if ($info !== false && isset($imagenes[$info[2]])) {
                $ext = $imagenes[$info[2]];
            } elseif (@file_get_contents($tmp, false, null, 0, 5) === '%PDF-') {
                $ext = 'pdf';
            }

            if ($ext === null) {
                $rechazados[] = ['archivo' => $nombre, 'motivo' => 'no es un JPG, PNG ni PDF válido'];
                continue;
            }

            $aceptados[] = [
                'nombre_original' => mb_substr($nombre, 0, 255),
                'tmp' => $tmp,
                'tam' => $tam,
                'ext' => $ext,
            ];
        }

        return [$aceptados, $rechazados];
    }

    /**
     * HU-07 — Tratamientos prescritos en la consulta.
     *
     * M2-08: los campos se leían con `$_POST['med_dosis'][$index]` sin
     * comprobar que existieran, así que un envío con los arreglos
     * desalineados generaba avisos de PHP y guardaba tratamientos a medias.
     */
    private function revisarTratamientos(?string &$error): array {
        $error = null;
        $tratamientos = [];

        $medicamentos = $_POST['med_nombre'] ?? null;
        if (!is_array($medicamentos)) return $tratamientos;

        foreach ($medicamentos as $i => $medicamento) {
            $medicamento = trim((string) $medicamento);
            if ($medicamento === '') continue;

            $dosis    = trim((string) ($_POST['med_dosis'][$i] ?? ''));
            $via      = trim((string) ($_POST['med_via'][$i] ?? ''));
            $duracion = trim((string) ($_POST['med_duracion'][$i] ?? ''));

            if ($dosis === '' || $via === '' || $duracion === '') {
                $error = sprintf(
                    'El tratamiento "%s" necesita dosis, vía de administración y duración.',
                    $medicamento
                );
                return [];
            }

            $tratamientos[] = [
                'medicamento' => mb_substr($medicamento, 0, 150),
                'dosis' => mb_substr($dosis, 0, 100),
                'via_administracion' => mb_substr($via, 0, 50),
                'duracion' => mb_substr($duracion, 0, 100),
                'observaciones' => mb_substr(trim((string) ($_POST['med_obs'][$i] ?? '')), 0, 500),
            ];
        }

        return $tratamientos;
    }

    // Registrar nueva consulta vía AJAX
    public function registrarAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        header('Content-Type: application/json');

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

        // HU-05 / HU-56: el motivo también es obligatorio. Los dos formularios
        // (atención por cita y atención sin cita) ya lo pedían, pero el
        // servidor aceptaba la consulta sin él.
        if (ValidadorClinico::textoOpcional($_POST['motivo'] ?? null, 5000) === '') {
            echo json_encode(['success' => false, 'message' => 'El motivo de la consulta es obligatorio']);
            exit;
        }

        // RN-203: la cita es opcional, pero si viene debe ser de ESTA
        // mascota; si no, la consulta quedaría atada a la cita de otra.
        // RN-406 / RN-408: además tiene que ser del veterinario en sesión y
        // estar en curso, porque guardar la consulta es lo que la completa.
        $idCita = null;
        if (!empty($_POST['id_cita'])) {
            $idCita = ValidadorClinico::id($_POST['id_cita']);
            $cita = $idCita === null ? null : $this->citaDeLaMascota($idCita, $idMascota);
            if ($cita === null) {
                echo json_encode(['success' => false, 'message' => 'La cita indicada no corresponde a esta mascota']);
                exit;
            }
            if ($cita['doc_veterinario'] !== $_SESSION['usuario_doc']) {
                echo json_encode(['success' => false, 'message' => 'Solo el veterinario asignado puede registrar la consulta de esta cita.']);
                exit;
            }
            // RN-410: una atención que quedó sin cerrar también se documenta.
            if (!in_array($cita['estado'], ['en_curso', 'sin_cerrar'], true)) {
                echo json_encode(['success' => false, 'message' => 'La atención de esta cita no está en curso. Iníciala desde el calendario.']);
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

        $frecuenciaRespiratoria = null;
        if (!empty($_POST['frecuencia_respiratoria'])) {
            $frecuenciaRespiratoria = ValidadorClinico::entero($_POST['frecuencia_respiratoria'], ValidadorClinico::FR_MIN, ValidadorClinico::FR_MAX);
            if ($frecuenciaRespiratoria === null) {
                echo json_encode(['success' => false, 'message' => 'La frecuencia respiratoria debe estar entre 5 y 150 rpm']);
                exit;
            }
        }

        // Una cita genera una sola consulta (id_cita es UNIQUE): se avisa con
        // un mensaje claro en vez de dejar que la base rechace el INSERT.
        if ($idCita !== null && $this->consultaModel->findByCita($idCita)) {
            echo json_encode(['success' => false, 'message' => 'Esta cita ya tiene una consulta registrada.']);
            exit;
        }

        $tratamientos = $this->revisarTratamientos($errorTratamiento);
        if ($errorTratamiento !== null) {
            echo json_encode(['success' => false, 'message' => $errorTratamiento]);
            exit;
        }

        // HU-34 — Los adjuntos se revisan ANTES de escribir nada. Si alguno no
        // sirve se rechaza la consulta entera y se dice cuál y por qué: antes
        // se descartaban en silencio dentro del bucle y la respuesta seguía
        // diciendo "registrada correctamente con sus adjuntos", aunque no
        // hubiera entrado ninguno. Perder una radiografía sin enterarse es
        // peor que tener que reintentar.
        [$adjuntos, $rechazados] = $this->revisarAdjuntos();
        if (!empty($rechazados)) {
            echo json_encode([
                'success' => false,
                'message' => 'No se guardó la consulta porque hay adjuntos que no se pueden aceptar. Corrígelos y vuelve a enviarla.',
                'adjuntos_rechazados' => $rechazados,
            ]);
            exit;
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
            'frecuencia_respiratoria' => $frecuenciaRespiratoria,
            'diagnostico' => $diagnostico,
            'plan_tratamiento' => ValidadorClinico::textoOpcional($_POST['plan_tratamiento'] ?? null, 5000),
            'observaciones' => ValidadorClinico::textoOpcional($_POST['observaciones'] ?? null, 5000)
        ];

        // HU-34 — Consulta, número de historia clínica, archivos y tratamientos
        // en una sola transacción. Antes eran cuatro escrituras sueltas: si
        // fallaba la última, la consulta quedaba guardada a medias y el
        // veterinario recibía un mensaje de éxito igualmente.
        $movidos = [];
        $this->db->beginTransaction();

        try {
            $idConsulta = $this->consultaModel->insert($data);
            if (!$idConsulta) {
                throw new RuntimeException('No se pudo insertar la consulta.');
            }

            // RN-102 / HU-05: el número de historia clínica se asigna en la
            // primera consulta y no se reutiliza.
            $mascota = $this->mascotaModel->getById($idMascota);
            if (empty($mascota['numero_historia_clinica'])) {
                $this->mascotaModel->actualizarHC($idMascota, 'HC-' . $idMascota . '-' . date('Y'));
            }

            $destino = __DIR__ . '/../public/uploads/clinicos/';
            if (!is_dir($destino) && !mkdir($destino, 0755, true) && !is_dir($destino)) {
                throw new RuntimeException('No se pudo preparar la carpeta de adjuntos.');
            }

            foreach ($adjuntos as $i => $adjunto) {
                $nombreServidor = sprintf(
                    'CLI_%d_%d_%s_%d.%s',
                    $idConsulta,
                    time(),
                    bin2hex(random_bytes(4)),
                    $i,
                    $adjunto['ext']
                );

                if (!move_uploaded_file($adjunto['tmp'], $destino . $nombreServidor)) {
                    throw new RuntimeException('No se pudo guardar el adjunto ' . $adjunto['nombre_original']);
                }
                $movidos[] = $destino . $nombreServidor;

                $guardado = $this->consultaModel->saveArchivo([
                    'id_consulta' => $idConsulta,
                    'nombre_original' => $adjunto['nombre_original'],
                    'nombre_servidor' => $nombreServidor,
                    // La ruta se guarda por compatibilidad con los registros
                    // antiguos, pero la descarga va siempre por ver_archivo.php,
                    // que es quien comprueba el permiso (M2-05).
                    'ruta_archivo' => 'uploads/clinicos/' . $nombreServidor,
                    'tipo_archivo' => $adjunto['ext'] === 'pdf' ? 'application/pdf' : 'image/' . ($adjunto['ext'] === 'png' ? 'png' : 'jpeg'),
                    'extension' => $adjunto['ext'],
                    'tamano_bytes' => $adjunto['tam'],
                    'descripcion' => 'Adjunto de consulta'
                ]);

                if (!$guardado) {
                    throw new RuntimeException('No se pudo registrar el adjunto ' . $adjunto['nombre_original']);
                }
            }

            foreach ($tratamientos as $tratamiento) {
                if (!$this->tratamientoModel->insert($tratamiento + ['id_consulta' => $idConsulta])) {
                    throw new RuntimeException('No se pudo registrar el tratamiento ' . $tratamiento['medicamento']);
                }
            }

            // RN-406: la consulta de una cita es lo que la completa, y va en la
            // misma transacción. Antes eran dos peticiones: si fallaba la
            // segunda, la consulta quedaba guardada y la cita seguía en curso.
            if ($idCita !== null) {
                require_once __DIR__ . '/../models/Cita.php';
                $ahora = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');
                if (!(new Cita($this->db))->completarAtencion($idCita, $ahora)) {
                    throw new RuntimeException('La cita ' . $idCita . ' dejó de estar en curso antes de guardar la consulta.');
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();

            // Los archivos ya movidos se borran a mano: el sistema de archivos
            // no participa del rollback de la base de datos.
            foreach ($movidos as $ruta) {
                if (is_file($ruta)) @unlink($ruta);
            }

            error_log('Error al registrar consulta: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo guardar la consulta. No se registró nada; revisa los datos e inténtalo de nuevo.',
            ]);
            exit;
        }

        $resumen = 'Consulta registrada correctamente';
        if (!empty($adjuntos)) {
            $resumen .= sprintf(' con %d adjunto%s', count($adjuntos), count($adjuntos) === 1 ? '' : 's');
        }
        if (!empty($tratamientos)) {
            $resumen .= sprintf(' y %d tratamiento%s', count($tratamientos), count($tratamientos) === 1 ? '' : 's');
        }

        echo json_encode([
            'success' => true,
            'id_consulta' => $idConsulta,
            'adjuntos_guardados' => count($adjuntos),
            'tratamientos_guardados' => count($tratamientos),
            'message' => $resumen . '.',
        ]);
        exit;
    }

    /**
     * HU-08 — Historial clínico completo de una mascota.
     *
     * M2-09: sin el parámetro no se emitía ningún cuerpo y el `.json()` del
     * navegador reventaba sobre una respuesta vacía; con un id inexistente
     * devolvía `mascota: null` y la vista fallaba al leer sus campos.
     *
     * M2-10: los adjuntos y los tratamientos se pedían consulta por consulta.
     * Con 100 consultas eran 201 viajes a la base de datos, y el criterio pide
     * cargar en menos de 3 segundos justo para ese tamaño. Ahora son 5
     * consultas fijas, independientemente del tamaño del historial.
     */
    public function listarHistorialAjax() {
        header('Content-Type: application/json');

        $id = $_GET['id_mascota'] ?? null;
        if (!ctype_digit((string) $id) || (int) $id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Mascota no válida.']);
            exit;
        }
        $id = (int) $id;

        try {
            $mascota = $this->mascotaModel->getById($id);
            if (!$mascota) {
                echo json_encode(['success' => false, 'message' => 'La mascota no existe.']);
                exit;
            }

            // RN-206: orden cronológico inverso, el más reciente primero.
            $historial = $this->consultaModel->findByMascota($id);

            $ids = array_column($historial, 'id_consulta');
            $archivosPorConsulta = $this->consultaModel->getArchivosDeConsultas($ids);
            $tratamientosPorConsulta = $this->tratamientoModel->findByConsultas($ids);

            foreach ($historial as &$c) {
                $idc = (int) $c['id_consulta'];
                $c['archivos'] = $archivosPorConsulta[$idc] ?? [];
                $c['tratamientos'] = $tratamientosPorConsulta[$idc] ?? [];
            }
            unset($c);

            echo json_encode([
                'success' => true,
                'mascota' => $mascota,
                'consultas' => $historial,
                'vacunas' => $this->vacunaModel->findByMascota($id),
                'desparasitaciones' => $this->desparasitacionModel->findByMascota($id)
            ]);
            exit;
        } catch (Throwable $e) {
            error_log('Error al cargar el historial clínico: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudo cargar el historial.']);
            exit;
        }
    }
    /**
     * HU-35 / RN-203: la cita que origina la consulta tiene que ser de la
     * misma mascota. Sin esta comprobación se podía adjuntar una consulta a
     * la cita de otro paciente pasando un id_cita cualquiera.
     *
     * Devuelve la cita (veterinario y estado, que registrarAjax también
     * revisa) o null si no existe o es de otra mascota.
     */
    private function citaDeLaMascota($idCita, $idMascota) {
        $stmt = $this->db->prepare(
            "SELECT id_cita, doc_veterinario, estado FROM citas WHERE id_cita = :cita AND id_mascota = :mascota"
        );
        $stmt->bindValue(':cita', (int) $idCita, PDO::PARAM_INT);
        $stmt->bindValue(':mascota', (int) $idMascota, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
?>
